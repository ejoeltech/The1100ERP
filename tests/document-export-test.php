<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/document-export.php';
require_once __DIR__ . '/../vendor/autoload.php';

function fail($message) { fwrite(STDERR, "FAIL: $message\n"); exit(1); }

$quote = $pdo->query("SELECT *, quote_number AS document_number FROM quotes WHERE quote_number = 'TEST-Q-001' LIMIT 1")->fetch();
$invoice = $pdo->query("SELECT *, invoice_number AS document_number, invoice_title AS quote_title, invoice_date AS quote_date FROM invoices WHERE invoice_number = 'TEST-I-001' LIMIT 1")->fetch();
$receipt = $pdo->query("SELECT r.*, r.receipt_number AS document_number, i.invoice_title AS quote_title, i.grand_total AS invoice_grand_total FROM receipts r LEFT JOIN invoices i ON r.invoice_id=i.id WHERE r.receipt_number = 'TEST-R-001' LIMIT 1")->fetch();
if (!$quote || !$invoice || !$receipt) fail('test documents not found');

$quoteItems = $pdo->query("SELECT * FROM quote_line_items WHERE quote_id = " . (int)$quote['id'] . " ORDER BY item_number")->fetchAll();
$invoiceItems = $pdo->query("SELECT * FROM invoice_line_items WHERE invoice_id = " . (int)$invoice['id'] . " ORDER BY item_number")->fetchAll();
$parent = $pdo->query("SELECT *, invoice_number AS document_number FROM invoices WHERE id = " . (int)$receipt['invoice_id'])->fetch();
$receipt['grand_total'] = $receipt['invoice_grand_total'];

$cases = [
    'quote' => documentBuildPdf('quote', $quote, $quoteItems),
    'invoice' => documentBuildPdf('invoice', $invoice, $invoiceItems),
    'receipt' => documentBuildPdf('receipt', $receipt, [], $parent),
];
foreach ($cases as $type => $mpdf) {
    $tmp = tempnam(sys_get_temp_dir(), 'erp-test-pdf-');
    $mpdf->Output($tmp, 'F');
    $bytes = (string) file_get_contents($tmp);
    if (strncmp($bytes, '%PDF-', 5) !== 0) fail("$type did not produce a PDF");
    $pages = preg_match_all('#/Type\s*/Page\b#', $bytes, $m);
    echo "PASS - $type PDF: " . strlen($bytes) . " bytes, pages=" . $pages . PHP_EOL;
    @unlink($tmp);
}

foreach (['quote' => [$quote, $quoteItems], 'invoice' => [$invoice, $invoiceItems]] as $type => $data) {
    $html = documentHtmlForBrowser($type, $data[0], $data[1]);
    if (strpos($html, '@page { size: A4; margin: 15mm; }') === false) fail("$type HTML missing A4 rule");
    if (strpos($html, 'src="data:image') === false) fail("$type HTML logo is not a data URI");
    if (strpos($html, 'src="C:') !== false) fail("$type HTML still contains a filesystem image path");
}
echo "PASS - browser HTML has A4 geometry and inline images" . PHP_EOL;
