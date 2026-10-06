<?php
// Optional business-history context for AI prompts (opt-in via ai_use_history).
// Aggregates only: item names, average prices, frequencies. No customer
// names, no PII, read-only, capped rows. Returns '' when disabled or empty.
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) { http_response_code(403); exit('Forbidden'); }

function getAiBusinessContext($pdo, $maxItems = 15) {
    try {
        if (function_exists('getSetting') && getSetting('ai_use_history', '0') !== '1') return '';
        $q = (int)$pdo->query("SELECT COUNT(*) FROM quotes")->fetchColumn();
        $inv = (int)$pdo->query("SELECT COUNT(*) FROM invoices")->fetchColumn();
        if ($q + $inv === 0) return '';
        $parts = ["This business has $q past quotes and $inv past invoices."];
        $rows = $pdo->query(
            "SELECT item_name, unit_price FROM quote_line_items WHERE item_name IS NOT NULL AND item_name <> ''" .
            " UNION ALL " .
            "SELECT item_name, unit_price FROM invoice_line_items WHERE item_name IS NOT NULL AND item_name <> ''" .
            " LIMIT 2000"
        )->fetchAll(PDO::FETCH_ASSOC);
        if (!$rows) return implode(' ', $parts);
        $agg = [];
        foreach ($rows as $r) {
            $name = trim((string)($r['item_name'] ?? ''));
            if ($name === '') continue;
            $key = function_exists('mb_strtolower') ? mb_strtolower($name) : strtolower($name);
            if (!isset($agg[$key])) $agg[$key] = ['name' => $name, 'n' => 0, 'sum' => 0.0];
            $agg[$key]['n']++;
            $agg[$key]['sum'] += (float)($r['unit_price'] ?? 0);
        }
        if (!$agg) return implode(' ', $parts);
        uasort($agg, fn($a, $b) => $b['n'] <=> $a['n']);
        $ex = [];
        foreach (array_slice($agg, 0, max(1, (int)$maxItems)) as $a) {
            $ex[] = "'" . $a['name'] . "' (used " . $a['n'] . "x, avg " . number_format($a['sum'] / $a['n'], 2) . " NGN)";
        }
        $parts[] = "Frequently quoted items: " . implode('; ', $ex) . ". Prefer these names and stay near these prices unless asked otherwise.";
        return implode(' ', $parts);
    } catch (Throwable $e) {
        return '';
    }
}
