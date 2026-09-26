<?php
// WP3: refuse direct web execution; this file only works when included.
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) { http_response_code(403); exit('Forbidden'); }
/**
 * Bluedots Technologies — Notifications helper (Telegram).
 * Self-hosted, zero recurring cost. Reads bot token + chat id from the
 * `settings` table via getSetting() (already defined in config.php).
 *
 * Usage:
 *   if (telegramConfigured()) { sendTelegram("Hello *boss*"); }
 */

if (!function_exists('telegramConfigured')) {
    function telegramConfigured(): bool {
        $t = function_exists('getSetting') ? getSetting('telegram_bot_token', '') : '';
        $c = function_exists('getSetting') ? getSetting('telegram_chat_id', '') : '';
        return !empty($t) && !empty($c);
    }
}

if (!function_exists('sendTelegram')) {
    /**
     * Send a Markdown-formatted message to the configured Telegram chat.
     * @param string $text  Markdown (Telegram flavour). Keep under 4096 chars.
     * @return bool true on success
     */
    function sendTelegram(string $text): bool {
        if (!telegramConfigured()) {
            return false;
        }
        $token = getSetting('telegram_bot_token', '');
        $chat  = getSetting('telegram_chat_id', '');

        // Truncate defensively (Telegram hard limit 4096).
        if (mb_strlen($text) > 4000) {
            $text = mb_substr($text, 0, 3990) . "\n…(truncated)";
        }

        $url = "https://api.telegram.org/bot{$token}/sendMessage";
        $payload = [
            'chat_id'    => $chat,
            'text'       => $text,
            'parse_mode' => 'Markdown',
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);
        $resp = curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($resp === false || $http !== 200) {
            error_log("Telegram send failed (HTTP $http): " . ($err ?: $resp));
            return false;
        }
        return true;
    }
}
