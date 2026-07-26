#!/usr/bin/env php
<?php
/**
 * Bluedots Technologies — Lead Follow-up Engine (CLI cron).
 * Finds leads due for a nudge, sends a Telegram reminder to the owner,
 * and schedules the next attempt. Honours max-attempts + interval days.
 *
 * Schedule daily, e.g.:
 *   30 9 * * *  php /var/www/erp/cron/followup.php >> /var/log/bluedots-followup.log 2>&1
 *
 * Reuses includes/notifications.php (sendTelegram) and config.php (getSetting).
 */
if (PHP_SAPI !== 'cli') { exit(1); }

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/notifications.php';

$enabled = (bool)getSetting('lead_followup_enabled', '1');
if (!$enabled) {
    echo date('c') . " follow-up disabled (lead_followup_enabled=0)\n";
    exit(0);
}

$interval = (int)getSetting('lead_followup_interval_days', '3');
$max      = (int)getSetting('lead_followup_max_attempts', '3');

try {
    // Leads not yet converted/lost, due for follow-up (or never scheduled).
    $stmt = $pdo->prepare(
        "SELECT * FROM leads
         WHERE status IN ('new','contacted','qualified')
           AND followup_count < ?
           AND (next_followup_at IS NULL OR next_followup_at <= NOW())
         ORDER BY created_at ASC"
    );
    $stmt->execute([$max]);
    $due = $stmt->fetchAll();
} catch (PDOException $e) {
    fwrite(STDERR, "DB error: " . $e->getMessage() . "\n");
    exit(1);
}

if (empty($due)) {
    echo date('c') . " no leads due for follow-up.\n";
    exit(0);
}

$now = date('Y-m-d H:i:s');
$next = date('Y-m-d H:i:s', strtotime("+$interval days"));
$sent = 0;

foreach ($due as $lead) {
    $msg = "🔔 *Follow-up reminder* (#" . $lead['id'] . ")\n"
          . "*" . $lead['name'] . "* — " . $lead['phone'] . "\n"
          . "Source: " . $lead['source'] . " · Attempt " . ($lead['followup_count'] + 1) . "/$max\n"
          . ($lead['interest'] ? "💡 " . $lead['interest'] . "\n" : "")
          . "Status: " . $lead['status'];

    $ok = false;
    if (telegramConfigured()) {
        $ok = sendTelegram($msg);
    }

    // Always update counters/schedule regardless of Telegram success,
    // so we don't spam-retry a broken notification forever.
    $stmt = $pdo->prepare(
        "UPDATE leads
            SET followup_count = followup_count + 1,
                last_followup_at = ?,
                next_followup_at = ?,
                updated_at = NOW()
         WHERE id = ?"
    );
    $stmt->execute([$now, $next, $lead['id']]);

    if ($ok) { $sent++; }
}

echo date('c') . " follow-up run complete: $sent/" . count($due) . " reminders sent.\n";
exit(0);
