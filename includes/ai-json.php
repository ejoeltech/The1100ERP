<?php
// Shared helpers for AI endpoints that expect JSON.
// Models routinely wrap JSON in markdown fences, add prose, or get cut off
// mid-array when max_tokens is too small. These helpers normalise all three
// without trusting the model.
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) { http_response_code(403); exit('Forbidden'); }

if (!function_exists('aiExtractJson')) {
    /**
     * Return the decoded JSON array/object found in $text, or null.
     */
    function aiExtractJson($text)
    {
        if (!is_string($text)) return null;
        $text = trim(preg_replace('/^\xEF\xBB\xBF/', '', $text));
        if ($text === '') return null;

        // Closed markdown fences (```json ... ```) or a bare language tag line.
        $unfenced = preg_replace('/^\s*```[a-zA-Z0-9_-]*\s*/', '', $text);
        $unfenced = preg_replace('/\s*```\s*$/', '', (string)$unfenced);
        $unfenced = trim((string)$unfenced);

        foreach ([$unfenced, $text] as $candidate) {
            $decoded = json_decode($candidate, true);
            if (is_array($decoded) && $decoded !== []) return $decoded;
        }

        // Locate the first structural character (ignore prose before it).
        $objPos = strpos($text, '{');
        $arrPos = strpos($text, '[');
        if ($objPos === false && $arrPos === false) return null;
        if ($objPos === false) $start = $arrPos;
        elseif ($arrPos === false) $start = $objPos;
        else $start = min($objPos, $arrPos);

        $slice = substr($text, $start);

        // 1) Balanced extract (handles trailing prose and fences).
        $end = aiBalancedEnd($slice);
        if ($end !== null) {
            $decoded = json_decode(substr($slice, 0, $end + 1), true);
            if (is_array($decoded) && $decoded !== []) return $decoded;
        }

        // 2) Truncated output: keep everything through the last complete
        //    element, then close the open brackets.
        $repaired = aiRepairTruncatedJson($slice);
        if ($repaired !== null) {
            $decoded = json_decode($repaired, true);
            if (is_array($decoded) && $decoded !== []) return $decoded;
        }

        return null;
    }
}

if (!function_exists('aiBalancedEnd')) {
    /** Index of the bracket that closes the structure starting at $s[0]. */
    function aiBalancedEnd($s)
    {
        $depth = 0;
        $inStr = false;
        $esc = false;
        $len = strlen($s);
        for ($i = 0; $i < $len; $i++) {
            $ch = $s[$i];
            if ($inStr) {
                if ($esc) $esc = false;
                elseif ($ch === '\\') $esc = true;
                elseif ($ch === '"') $inStr = false;
                continue;
            }
            if ($ch === '"') { $inStr = true; continue; }
            if ($ch === '{' || $ch === '[') { $depth++; continue; }
            if ($ch === '}' || $ch === ']') {
                $depth--;
                if ($depth === 0) return $i;
            }
        }
        return null;
    }
}

if (!function_exists('aiBracketStack')) {
    /** Open brackets still unclosed, in order. */
    function aiBracketStack($s)
    {
        $stack = [];
        $inStr = false;
        $esc = false;
        $len = strlen($s);
        for ($i = 0; $i < $len; $i++) {
            $ch = $s[$i];
            if ($inStr) {
                if ($esc) $esc = false;
                elseif ($ch === '\\') $esc = true;
                elseif ($ch === '"') $inStr = false;
                continue;
            }
            if ($ch === '"') { $inStr = true; continue; }
            if ($ch === '{' || $ch === '[') { $stack[] = $ch; continue; }
            if ($ch === '}' || $ch === ']') {
                $want = $ch === '}' ? '{' : '[';
                if (!empty($stack) && end($stack) === $want) array_pop($stack);
                elseif (!empty($stack)) return null; // malformed nesting
            }
        }
        return $inStr ? null : $stack;
    }
}

if (!function_exists('aiRepairTruncatedJson')) {
    /**
     * Trim a cut-off JSON document back to its last complete element and
     * append the missing closers. Returns null when nothing usable remains.
     */
    function aiRepairTruncatedJson($s)
    {
        $lastObj = strrpos($s, '}');
        $lastArr = strrpos($s, ']');
        $pos = -1;
        if ($lastObj !== false) $pos = $lastObj;
        if ($lastArr !== false && $lastArr > $pos) $pos = $lastArr;
        if ($pos < 0) return null;

        $prefix = substr($s, 0, $pos + 1);
        $stack = aiBracketStack($prefix);
        if ($stack === null) return null;

        $closers = '';
        for ($i = count($stack) - 1; $i >= 0; $i--) {
            $closers .= $stack[$i] === '[' ? ']' : '}';
        }
        return $prefix . $closers;
    }
}

if (!function_exists('aiCallJson')) {
    /**
     * Call the configured AI provider and return decoded JSON.
     * Retries once with a larger token budget: reasoning models can spend the
     * whole allowance before emitting content, and long arrays get truncated.
     *
     * @throws Exception when the model never yields usable JSON.
     */
    function aiCallJson($prompt, $systemPrompt = '', $options = [])
    {
        if (!function_exists('callAiAPI')) require_once __DIR__ . '/ai-config.php';

        $max = (int)($options['max_tokens'] ?? 2000);
        $raw = '';
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $opts = $options;
            $opts['max_tokens'] = $max;
            $raw = callAiAPI($prompt, $systemPrompt, $opts);
            $decoded = aiExtractJson($raw);
            if ($decoded !== null) return $decoded;
            $max = min($max * 2, 8000);
        }

        $snippet = trim(preg_replace('/\s+/', ' ', (string)$raw));
        if ($snippet === '') {
            throw new Exception('AI returned an empty response. Try again, or pick a non-reasoning model in Settings > AI.');
        }
        if (function_exists('mb_substr')) $snippet = mb_substr($snippet, 0, 160);
        else $snippet = substr($snippet, 0, 160);
        throw new Exception('AI returned unusable JSON: ' . $snippet . ' — try again, or switch model in Settings > AI.');
    }
}
