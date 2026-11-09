<?php
// WP3: refuse direct web execution; this file only works when included.
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) { http_response_code(403); exit('Forbidden'); }
/**
 * Helper Functions
 * Common utility functions used throughout the application
 */

if (!function_exists('erpCompanyName')) {
    /**
     * The operator's company name from Settings, never the app's default name.
     * Falls back to APP_NAME only when no company name has been configured.
     */
    function erpCompanyName($fallback = null)
    {
        $name = defined('COMPANY_NAME') ? trim((string) COMPANY_NAME) : '';
        if ($name !== '' && strcasecmp($name, 'Your Company Name') !== 0) {
            return $name;
        }

        if (function_exists('getSetting')) {
            $name = trim((string) getSetting('company_name', ''));
            if ($name !== '' && strcasecmp($name, 'Your Company Name') !== 0) {
                return $name;
            }
        }

        if ($fallback !== null && trim((string) $fallback) !== '') {
            return trim((string) $fallback);
        }
        return defined('APP_NAME') ? APP_NAME : 'Our Company';
    }
}

/**
 * Format a number as Naira currency
 * @param float $amount The amount to format
 * @return string Formatted currency string
 */
function formatNaira($amount)
{
    $val = (float)$amount;
    $decimals = (floor($val) == $val) ? 0 : 2;
    return '₦' . number_format($val, $decimals);
}

/**
 * Format a number simply (remove .00 if whole number)
 * @param float $num
 * @param int $maxDecimals
 * @return string
 */
function formatNumberSimple($num, $maxDecimals = 2)
{
    $val = (float)$num;
    $decimals = (floor($val) == $val) ? 0 : $maxDecimals;
    return number_format($val, $decimals);
}

/**
 * Format a date in a readable format
 * @param string $date Date string
 * @param string $format Output format (default: 'd/m/Y')
 * @return string Formatted date
 */
function formatDate($date, $format = 'd/m/Y')
{
    if (empty($date)) {
        return '-';
    }
    return date($format, strtotime($date));
}

/**
 * Generate a unique document number
 * @param string $prefix Prefix for the number (e.g., 'Q', 'INV', 'REC')
 * @param int $id The ID to use in the number
 * @return string Formatted document number
 */
function generateDocumentNumber($prefix, $id)
{
    return $prefix . '-' . str_pad($id, 5, '0', STR_PAD_LEFT);
}

/**
 * Sanitize user input
 * @param string $input Input to sanitize
 * @return string Sanitized input
 */
function sanitizeInput($input)
{
    return htmlspecialchars(trim($input), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Short helper for htmlspecialchars
 * @param string $data
 * @return string
 */
if (!function_exists('h')) {
    function h($data)
    {
        return htmlspecialchars((string)$data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}

/**
 * Check if a string is a valid email
 * @param string $email Email to validate
 * @return bool True if valid, false otherwise
 */
function isValidEmail($email)
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Read the highest existing number for a prefix so a brand-new counter can
 * start after it. Used only to bootstrap document_sequences from live data.
 */
function documentNumberBootstrap($pdo, $table, $column, $prefix)
{
    if (!preg_match('/^[A-Za-z0-9_]{1,64}$/', $table)
        || !preg_match('/^[A-Za-z0-9_]{1,64}$/', $column)) {
        throw new Exception('Invalid document number source');
    }
    $stmt = $pdo->prepare("SELECT `$column` FROM `$table` WHERE `$column` LIKE ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$prefix . '%']);
    $last = $stmt->fetchColumn();
    return $last !== false ? (int)substr((string)$last, strlen($prefix)) : 0;
}

/**
 * Atomically allocate the next document number. The counter row is
 * incremented with LAST_INSERT_ID() so concurrent requests cannot receive
 * the same value. $table/$column are only read to bootstrap a new counter.
 */
function nextDocumentNumber($pdo, $sequenceKey, $table, $column, $prefix, $padding = 3)
{
    $bootstrap = documentNumberBootstrap($pdo, $table, $column, $prefix);
    $initial = $bootstrap + 1;

    try {
        $stmt = $pdo->prepare("INSERT INTO document_sequences (sequence_key, last_value) VALUES (?, ?)
            ON DUPLICATE KEY UPDATE last_value = LAST_INSERT_ID(GREATEST(last_value + 1, ?))");
        $stmt->execute([$sequenceKey, $initial, $initial]);
        $value = $stmt->rowCount() === 1 ? $initial : (int)$pdo->lastInsertId();
    } catch (PDOException $e) {
        // Schema patch not applied yet: keep document creation working and
        // log loudly. Concurrent requests can race until the patch is run.
        if (stripos($e->getMessage(), 'document_sequences') === false) {
            throw $e;
        }
        error_log('document_sequences missing; falling back to non-atomic document numbering. Run the schema patcher.');
        $value = $initial;
    }

    return $prefix . str_pad((string)$value, $padding, '0', STR_PAD_LEFT);
}

/**
 * Preview the next number without consuming it (used by create forms).
 */
function previewDocumentNumber($pdo, $sequenceKey, $table, $column, $prefix, $padding = 3)
{
    $bootstrap = documentNumberBootstrap($pdo, $table, $column, $prefix);
    $current = 0;
    try {
        $stmt = $pdo->prepare("SELECT last_value FROM document_sequences WHERE sequence_key = ?");
        $stmt->execute([$sequenceKey]);
        $current = (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        // See note in nextDocumentNumber().
    }
    $next = max($bootstrap, $current) + 1;
    return $prefix . str_pad((string)$next, $padding, '0', STR_PAD_LEFT);
}

function generateQuoteNumber($pdo)
{
    $prefix = 'QUOT-' . date('Y') . '-';
    return nextDocumentNumber($pdo, 'quote:' . date('Y'), 'quotes', 'quote_number', $prefix, 3);
}

function previewQuoteNumber($pdo)
{
    $prefix = 'QUOT-' . date('Y') . '-';
    return previewDocumentNumber($pdo, 'quote:' . date('Y'), 'quotes', 'quote_number', $prefix, 3);
}

function generateReceiptNumber($pdo)
{
    $prefix = 'REC-' . date('Y') . '-';
    return nextDocumentNumber($pdo, 'receipt:' . date('Y'), 'receipts', 'receipt_number', $prefix, 3);
}

function generateInvoiceNumber($pdo)
{
    return nextDocumentNumber($pdo, 'invoice', 'invoices', 'invoice_number', 'INV-', 4);
}

function generatePaymentNumber($pdo)
{
    $ym = date('ym');
    return nextDocumentNumber($pdo, 'payment:' . $ym, 'payments', 'payment_number', 'PAY-' . $ym . '-', 3);
}

/**
 * Map UI payment labels to the receipts.payment_method ENUM keys.
 */
function normalizePaymentMethod($method)
{
    $m = strtolower(trim((string)$method));
    $map = [
        'cash' => 'cash',
        'bank transfer' => 'bank_transfer',
        'bank_transfer' => 'bank_transfer',
        'transfer' => 'bank_transfer',
        'cheque' => 'cheque',
        'check' => 'cheque',
        'pos' => 'card',
        'card' => 'card',
        'online' => 'other',
        'online transfer' => 'other',
        'credit applied' => 'other',
        'other' => 'other',
    ];
    return $map[$m] ?? 'other';
}

/**
 * Parse a number from a form input (removing commas)
 * @param mixed $input Input string or number
 * @return float Parsed float value
 */
function parseFormNumber($input)
{
    if (empty($input)) return 0.0;
    if (is_numeric($input)) return (float) $input;
    // Remove commas and other non-numeric chars except decimal and minus
    $clean = preg_replace('/[^\d.-]/', '', (string)$input);
    return (float) $clean;
}

/**
 * Server-side recalculation for quote/invoice line items (WP3-D).
 * Client-submitted vat_amount/line_total/subtotal/vat/grand_total are NEVER
 * trusted: every figure is recomputed from quantity × unit_price + VAT.
 * Returns ['items' => normalized rows, 'subtotal' => f, 'vat' => f, 'grand' => f].
 * Throws on empty/invalid items. VAT rate comes from settings (VAT_RATE).
 */
function recalcDocumentTotals($items)
{
    $rate = (defined('VAT_RATE') ? (float)VAT_RATE : 7.5) / 100.0;
    $out = [];
    $subtotal = 0.0;
    $totalVat = 0.0;
    $n = 1;
    if (!is_array($items) || empty($items)) {
        throw new Exception('No line items provided');
    }
    foreach ($items as $item) {
        if (!is_array($item)) {
            throw new Exception('Invalid line item data');
        }
        $quantity = parseFormNumber($item['quantity'] ?? 0);
        $description = trim($item['description'] ?? '');
        $unit_price = parseFormNumber($item['unit_price'] ?? 0);
        if ($description === '' || $quantity <= 0 || $unit_price < 0) {
            throw new Exception('Invalid line item data');
        }
        $base = round($quantity * $unit_price, 2);
        $vat = !empty($item['vat_applicable']) ? round($base * $rate, 2) : 0.0;
        $lineTotal = round($base + $vat, 2);
        $out[] = [
            'item_number' => $n++,
            'quantity' => $quantity,
            'description' => $description,
            'unit_price' => $unit_price,
            'vat_applicable' => !empty($item['vat_applicable']) ? 1 : 0,
            'vat_amount' => $vat,
            'line_total' => $lineTotal,
            'item_id' => !empty($item['item_id']) ? intval($item['item_id']) : null,
            'product_id' => !empty($item['product_id']) ? intval($item['product_id']) : null,
            'item_name' => isset($item['item_name']) && $item['item_name'] !== '' ? trim($item['item_name']) : null,
        ];
        $subtotal = round($subtotal + $base, 2);
        $totalVat = round($totalVat + $vat, 2);
    }
    return ['items' => $out, 'subtotal' => $subtotal, 'vat' => $totalVat, 'grand' => round($subtotal + $totalVat, 2)];
}

/**
 * Allow-list for client-settable document status (WP3-D mass assignment).
 */
function sanitizeDocumentStatus($status)
{
    $status = strtolower(trim((string)$status));
    return in_array($status, ['draft', 'finalized'], true) ? $status : 'draft';
}
