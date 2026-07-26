<?php
/**
 * cron/notify.php — Send a Telegram notification from a shell script.
 * Reuses includes/notifications.php (sendTelegram) so all alerts share
 * one config + one code path.
 *
 * Usage (from bash):  php cron/notify.php "Your message here"
 * Markdown is preserved. Exits 0 on success/disabled, 1 on send failure.
 */
if (PHP_SAPI !== 'cli') {
    exit(1);
}
$msg = $argv[1] ?? '';
if ($msg === '') {
    exit(0);
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/notifications.php';

if (!telegramConfigured()) {
    // Silent no-op if Telegram isn't set up yet.
    exit(0);
}

$ok = sendTelegram($msg);
exit($ok ? 0 : 1);
