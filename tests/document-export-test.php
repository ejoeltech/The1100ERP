<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/document-export.php';
require_once __DIR__ . '/../vendor/autoload.php';

function fail($message) { fwrite(STDERR, "FAIL: $message\n"); exit(1); }

$pdo->beginTransaction();
try {
    // Temporary T&C/warranty so the quote has the same 3 pages as production.
    $pdo->exec("UPDATE settings SET setting_value = '<p>Test terms.</p>' WHERE setting_key = 'quote_terms'");
    $pdo->exec("UPDATE settings SET setting_value = '<p>Test warranty.</p>' WHERE setting_key = 'quote_warranty'");

    $pdo->exec("INSERT INTO quotes (quote_number, quote_title, customer_name, salesperson, quote_date, subtotal, total_vat, grand_total, payment_terms, delivery_period, status, created_by)
        VALUES ('TEST-EXPORT-Q','Export Test Quotation','Test Customer Ltd','Test Sales',CURDATE(),3000000,225000,3225000,'80% Initial Deposit','Immediate','draft',1)");
    $qid = (int) $pdo->lastInsertId();
    $pdo->exec("INSERT INTO quote_line_items (quote_id,item_number,quantity,description,unit_price,vat_applicable,vat_amount,line_total) VALUES
        ($qid,1,1,'5kVA 48V Hybrid Inverter with MPPT charge controller and monitoring module',1200000,1,90000,1290000),
        ($qid,2,8,'600W Monocrystalline Solar Panels with mounting rails and DC isolators',150000,1,90000,1290000),
        ($qid,3,4,'200Ah 12V Tall Tubular Batteries with battery rack and interconnecting cables',150000,1,45000,645000)");

    $pdo->exec("INSERT INTO invoices (invoice_number, quote_id, invoice_title, customer_name, salesperson, invoice_date, due_date, subtotal, total_vat, grand_total, amount_paid, balance_due, payment_terms, status, created_by)
        VALUES ('TEST-EXPORT-I',$qid,'Export Test Invoice','Test Customer Ltd','Test Sales',CURDATE(),DATE_ADD(CURDATE(), INTERVAL 14 DAY),3000000,225000,3225000,1000000,2225000,'80% Initial Deposit','partial',1)");
    $iid = (int) $pdo->lastInsertId();
    $pdo->exec("INSERT INTO invoice_line_items (invoice_id,item_number,quantity,description,unit_price,vat_applicable,vat_amount,line_total) VALUES
        ($iid,1,1,'5kVA 48V Hybrid Inverter with MPPT charge controller and monitoring module',1200000,1,90000,1290000),
        ($iid,2,8,'600W Monocrystalline Solar Panels with mounting rails and DC isolators',150000,1,90000,1290000),
        ($iid,3,4,'200Ah 12V Tall Tubular Batteries with battery rack and interconnecting cables',150000,1,45000,645000)");

    $pdo->exec("INSERT INTO receipts (receipt_number, invoice_id, customer_name, amount_paid, payment_method, payment_date, reference_number, status, created_by)
        VALUES ('TEST-EXPORT-R',$iid,'Test Customer Ltd',1000000,'bank_transfer',CURDATE(),'TEST-REF-001','valid',1)");

    $quote = $pdo->query("SELECT *, quote_number AS document_number FROM quotes WHERE id = $qid")->fetch();
    $invoice = $pdo->query("SELECT *, invoice_number AS document_number, invoice_title AS quote_title, invoice_date AS quote_date FROM invoices WHERE id = $iid")->fetch();
    $receipt = $pdo->query("SELECT r.*, r.receipt_number AS document_number, i.invoice_title AS quote_title, i.grand_total AS invoice_grand_total
        FROM receipts r LEFT JOIN invoices i ON r.invoice_id=i.id WHERE r.invoice_id = $iid LIMIT 1")->fetch();
    $quoteItems = $pdo->query("SELECT * FROM quote_line_items WHERE quote_id = $qid ORDER BY item_number")->fetchAll();
    $invoiceItems = $pdo->query("SELECT * FROM invoice_line_items WHERE invoice_id = $iid ORDER BY item_number")->fetchAll();
    $parent = $pdo->query("SELECT *, invoice_number AS document_number FROM invoices WHERE id = $iid")->fetch();
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

    $pdo->rollBack();
    exit(0);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fail($e->getMessage());
}
