<?php
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
    return '₦' . number_format((float) $amount, 2);
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
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
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
