<?php
// Encrypt existing plaintext NIN/BVN rows and upgrade legacy envelopes.
// CLI-only: php scripts/encrypt-pii.php [--dry-run]
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('Forbidden: run via CLI as the deploy user.');
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/security.php';

$dryRun = in_array('--dry-run', $argv ?? [], true);
$targets = [
    ['hr_employees', 'nin_number'],
    ['hr_employees', 'bvn_number'],
    ['hr_onboarding_entries', 'nin_number'],
    ['hr_onboarding_entries', 'bvn_number'],
];

$encrypted = 0;
$upgraded = 0;
$skipped = 0;
$failed = 0;

echo $dryRun ? "DRY RUN — no rows will be written.\n" : "Encrypting existing PII...\n";

// Encrypted envelopes are longer than the original varchar(50) columns.
// Widen first so an update can never truncate the ciphertext.
if (!$dryRun) {
    foreach (['hr_employees', 'hr_onboarding_entries'] as $table) {
        try {
            $pdo->exec("ALTER TABLE `$table`
                MODIFY COLUMN `nin_number` VARCHAR(255) DEFAULT NULL,
                MODIFY COLUMN `bvn_number` VARCHAR(255) DEFAULT NULL");
        } catch (PDOException $e) {
            echo "  $table: could not widen PII columns (" . $e->getMessage() . ")\n";
        }
    }
}

foreach ($targets as [$table, $column]) {
    try {
        $rows = $pdo->query("SELECT id, `$column` AS value FROM `$table` WHERE `$column` IS NOT NULL AND `$column` <> ''")->fetchAll();
    } catch (PDOException $e) {
        echo "  $table.$column: skipped (table/column missing)\n";
        continue;
    }

    $update = $pdo->prepare("UPDATE `$table` SET `$column` = ? WHERE id = ?");
    foreach ($rows as $row) {
        $value = (string)$row['value'];
        try {
            if (strpos($value, PII_ENVELOPE_PREFIX) === 0) {
                if (decryptPII($value) === '') {
                    $failed++;
                    echo "  $table.$column#{$row['id']}: v2 value will not decrypt\n";
                } else {
                    $skipped++;
                }
                continue;
            }
            $isLegacy = piiIsEncrypted($value);
            $plain = $isLegacy ? decryptPII($value) : $value;
            if ($isLegacy && ($plain === '' || $plain === null)) {
                $failed++;
                echo "  $table.$column#{$row['id']}: legacy ciphertext is corrupt/truncated; left unchanged\n";
                continue;
            }
            if ($plain === '' || $plain === null) {
                $skipped++;
                continue;
            }
            $newValue = encryptPII($plain);
            if (!$dryRun) {
                $update->execute([$newValue, (int)$row['id']]);
            }
            if ($isLegacy) {
                $upgraded++;
            } else {
                $encrypted++;
            }
        } catch (Throwable $e) {
            $failed++;
            echo "  $table.$column#{$row['id']}: failed\n";
        }
    }
}

echo "Encrypted: $encrypted | Upgraded: $upgraded | Already current/empty: $skipped | Failed: $failed\n";
if ($failed > 0) {
    exit(1);
}
