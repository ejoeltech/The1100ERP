#!/usr/bin/env php
<?php
/**
 * Bluedots Technologies — Daily Operations Digest (CLI cron).
 * Sends the owner a once-daily Telegram snapshot: new leads, overdue
 * follow-ups, converted today, and a quick health line. Reuses
 * includes/notifications.php (sendTelegram) + config.php (getSetting).
 *
 * Schedule daily before business hours, e.g.:
 *   0 8 * * *  php /var/www/erp/cron/daily-digest.php >> /var/log/bluedots-digest.log 2>&1
 */
if (PHP_SAPI !== 'cli') { exit(1); }

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/notifications.php';

if (!(bool)getSetting('lead_digest_enabled', '1')) {
    echo date('c') . " daily digest disabled.\n";
    exit(0);
}

function cnt($pdo, $sql) {
    $s = $pdo->prepare($sql);
    $s->execute();
    return (int)$s->fetchColumn();
}

try {
    $newToday   = cnt($pdo, "SELECT COUNT(*) FROM leads WHERE DATE(created_at) = CURDATE()");
    $overdue    = cnt($pdo, "SELECT COUNT(*) FROM leads WHERE status IN ('new','contacted','qualified') AND next_followup_at < NOW()");
    $converted  = cnt($pdo, "SELECT COUNT(*) FROM leads WHERE status='converted' AND DATE(updated_at) = CURDATE()");
    $openLeads  = cnt($pdo, "SELECT COUNT(*) FROM leads WHERE status IN ('new','contacted','qualified')");
} catch (PDOException $e) {
    fwrite(STDERR, "DB error: " . $e->getMessage() . "\n");
    exit(1);
}

$body = "📊 *Bluedots Daily Digest* — " . date('D, j M Y') . "\n";
$body .= "• New leads today: *$newToday*\n";
$body .= "• Open leads: *$openLeads*\n";
$body .= "• Overdue follow-ups: *$overdue*\n";
$body .= "• Converted today: *$converted*\n";

if ($newToday + $overdue + $converted === 0) {
    $body .= "\n✅ Quiet day — nothing slipping through.";
} else {
    $body .= "\n👉 Login to action overdue items: " . (getSetting('company_website','') ?: 'ERP');
}

if (telegramConfigured()) {
    $ok = sendTelegram($body);
    echo date('c') . " digest " . ($ok ? "sent." : "FAILED to send.\n");
    exit($ok ? 0 : 1);
}

echo date('c') . " Telegram not configured — digest skipped.\n";
exit(0);
