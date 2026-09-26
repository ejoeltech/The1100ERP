<?php
// WP3: refuse direct web execution; this file only works when included.
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) { http_response_code(403); exit('Forbidden'); }
/**
 * Helper Functions
 * Common utility functions used throughout the application
 */

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
 * Generate the next quote number
 * @param PDO $pdo Database connection
 * @return string Next quote number (e.g. QUOT-YYYY-001)
 */
function generateQuoteNumber($pdo)
{
    $year = date('Y');
    $prefix = 'QUOT-' . $year . '-';

    $stmt = $pdo->prepare("SELECT quote_number FROM quotes WHERE quote_number LIKE ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$prefix . '%']);
    $lastQuote = $stmt->fetch();

    if ($lastQuote) {
        $lastNumber = intval(substr($lastQuote['quote_number'], strlen($prefix)));
        $nextNumber = $lastNumber + 1;
    } else {
        $nextNumber = 1;
    }

    return $prefix . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
}

/**
 * Generate the next receipt number
 * @param PDO $pdo Database connection
 * @return string Next receipt number (e.g. REC-YYYY-001)
 */
function generateReceiptNumber($pdo)
{
    $year = date('Y');
    $prefix = 'REC-' . $year . '-';

    // We use a locking read or just optimistic logic. 
    // Since this is called within a transaction in save-payment, we should be careful.
    // However, save-payment locks rows, not the whole table.
    // For simplicity in this context, we'll select the max.

    $stmt = $pdo->prepare("SELECT receipt_number FROM receipts WHERE receipt_number LIKE ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$prefix . '%']);
    $lastReceipt = $stmt->fetch();

    if ($lastReceipt) {
        $lastNumber = intval(substr($lastReceipt['receipt_number'], strlen($prefix)));
        $nextNumber = $lastNumber + 1;
    } else {
        $nextNumber = 1;
    }

    return $prefix . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
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
