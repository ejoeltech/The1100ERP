<?php
// WP3: refuse direct web execution; this file only works when included.
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) { http_response_code(403); exit('Forbidden'); }
/**
 * Shared document export builder.
 *
 * PDF, HTML and JPEG all render the same template HTML. The browser version
 * adds A4 page geometry, inlines the logo/signature as data URIs, and appends
 * the same Terms & Conditions / Warranty pages that the PDF adds. JPEG is
 * produced by rasterising that PDF (Imagick or a CLI rasteriser); if no
 * server rasteriser exists it falls back to html2canvas in the browser, so
 * the image is rendered from the same HTML instead of a hand-drawn canvas.
 */

/**
 * Render one of the existing document templates.
 *
 * @param string $type quote|invoice|receipt
 * @param array  $record
 * @param array  $lineItems
 * @param array|null $parentInvoice
 * @return string
 */
function documentRenderTemplate($type, $record, $lineItems = [], $parentInvoice = null)
{
    $html = '';
    if ($type === 'quote') {
        $quote = $record;
        $line_items = $lineItems;
        $html = include __DIR__ . '/pdf-template.php';
    } elseif ($type === 'invoice') {
        $invoice = $record;
        $line_items = $lineItems;
        $html = include __DIR__ . '/invoice-pdf-template.php';
    } elseif ($type === 'receipt') {
        $receipt = $record;
        $parent_invoice = $parentInvoice;
        $html = include __DIR__ . '/receipt-pdf-template.php';
    }
    return is_string($html) ? $html : '';
}

/**
 * Replace filesystem image src values with data URIs so the same HTML works
 * in a browser (CSP allows data:), in mPDF, and in html2canvas.
 */
function documentInlineImages($html)
{
    if (!is_string($html) || $html === '') {
        return $html;
    }
    return preg_replace_callback('/\bsrc=(["\'])([^"\']+)\1/i', function ($m) {
        $src = html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($src === '' || preg_match('#^(?:data:|https?:|//)#i', $src)) {
            return $m[0];
        }
        $path = $src;
        if (!preg_match('#^(?:[A-Za-z]:[\\\\/]|/)#', $path)) {
            $path = dirname(__DIR__) . DIRECTORY_SEPARATOR . ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
        }
        if (!is_file($path) || !is_readable($path)) {
            return $m[0];
        }
        $bytes = @file_get_contents($path);
        if ($bytes === false) {
            return $m[0];
        }
        $mime = function_exists('mime_content_type') ? mime_content_type($path) : '';
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($ext === 'svg') {
            $mime = 'image/svg+xml';
        }
        if (!$mime) {
            $mime = in_array($ext, ['jpg', 'jpeg'], true) ? 'image/jpeg' : 'image/png';
        }
        return 'src=' . $m[1] . 'data:' . $mime . ';base64,' . base64_encode($bytes) . $m[1];
    }, $html);
}

/** Terms & Conditions and Warranty content, in PDF order. */
function documentQuoteExtras()
{
    $extras = [];
    $terms = function_exists('getSetting') ? (string) getSetting('quote_terms', '') : '';
    if (trim($terms) !== '') {
        $extras[] = ['Terms & Conditions', $terms];
    }
    $warranty = function_exists('getSetting') ? (string) getSetting('quote_warranty', '') : '';
    if (trim($warranty) !== '') {
        $extras[] = ['Warranty Information', $warranty];
    }
    return $extras;
}

/** Browser/HTML A4 contract. Injected after the template's own styles. */
function documentA4Css()
{
    return '
    <style>
        @page { size: A4; margin: 15mm; }
        html, body {
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
            background: #ffffff;
        }
        /* 15mm white margins + 180mm content = A4 width on screen. */
        body {
            width: 180mm;
            min-height: 267mm;
            padding: 15mm;
            margin: 0 auto;
            box-sizing: content-box;
        }
        table { border-collapse: collapse; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        .totals-section, .footer, .prepared-by, .signature, .bank-section { page-break-inside: avoid; }
        .document-page-break { page-break-before: always; }
        @media screen {
            .document-page-break { margin-top: 18mm; padding-top: 6mm; border-top: 1px dashed #cbd5e1; }
        }
        @media print {
            body { width: auto; min-height: 0; padding: 0; margin: 0; }
            .document-page-break { margin: 0; padding: 0; border: 0; }
        }
    </style>';
}

/** Append T&C / Warranty pages to browser HTML. */
function documentAppendQuoteExtrasHtml($html)
{
    $extras = documentQuoteExtras();
    if (!$extras) {
        return $html;
    }
    $theme = defined('THEME_COLOR') ? THEME_COLOR : '#0076BE';
    $blocks = '';
    foreach ($extras as $extra) {
        $blocks .= '<div class="document-page-break">'
            . '<h2 style="font-family: sans-serif; color: ' . htmlspecialchars($theme) . '; font-size: 24px; margin-bottom: 20px;">'
            . htmlspecialchars($extra[0]) . '</h2>'
            . $extra[1]
            . '</div>';
    }
    return preg_replace('/<\/body>/i', $blocks . '</body>', $html, 1);
}

/** Add charset/title and A4 CSS to a complete template document. */
function documentPrepareBrowserHtml($type, $html, $record)
{
    if (stripos($html, '<meta charset') === false) {
        $html = preg_replace('/<head>/i', '<head><meta charset="UTF-8">', $html, 1);
    }
    if (stripos($html, '<title>') === false) {
        $number = is_array($record) ? (string) ($record['document_number'] ?? '') : '';
        $label = $type === 'quote' ? 'Quotation' : ($type === 'invoice' ? 'Invoice' : 'Receipt');
        $title = trim($label . ' ' . $number);
        $html = preg_replace('/<\/head>/i', '<title>' . htmlspecialchars($title) . '</title></head>', $html, 1);
    }
    return preg_replace('/<\/head>/i', documentA4Css() . '</head>', $html, 1);
}

/**
 * Complete HTML for the HTML export and the client-side JPEG renderer.
 */
function documentHtmlForBrowser($type, $record, $lineItems = [], $parentInvoice = null)
{
    $html = documentRenderTemplate($type, $record, $lineItems, $parentInvoice);
    $html = documentInlineImages($html);
    if ($type === 'quote') {
        $html = documentAppendQuoteExtrasHtml($html);
    }
    return documentPrepareBrowserHtml($type, $html, $record);
}

/** Template HTML for mPDF/JPEG; images inlined, no browser-only CSS. */
function documentHtmlForPdf($type, $record, $lineItems = [], $parentInvoice = null)
{
    return documentInlineImages(documentRenderTemplate($type, $record, $lineItems, $parentInvoice));
}

/** Create an mPDF instance with the same geometry as the PDF endpoints. */
function documentCreatePdf($html, $type, array $options = [])
{
    if (!class_exists('\Mpdf\Mpdf')) {
        require_once dirname(__DIR__) . '/vendor/autoload.php';
    }
    $defaults = [
        'mode' => 'utf-8',
        'format' => 'A4',
        'margin_left' => 15,
        'margin_right' => 15,
        'margin_top' => 15,
        'margin_bottom' => 15,
        'tempDir' => dirname(__DIR__) . '/tmp/mpdf',
    ];
    if ($type === 'invoice') {
        $defaults['margin_header'] = 10;
        $defaults['margin_footer'] = 10;
    }
    $mpdf = new \Mpdf\Mpdf(array_merge($defaults, $options));
    $mpdf->WriteHTML($html);
    return $mpdf;
}

/**
 * Build a PDF exactly like the PDF export: main template, then one page per
 * Terms/Warranty block for quotes.
 */
function documentBuildPdf($type, $record, $lineItems = [], $parentInvoice = null, array $options = [])
{
    $mpdf = documentCreatePdf(documentHtmlForPdf($type, $record, $lineItems, $parentInvoice), $type, $options);
    if ($type === 'quote') {
        $theme = defined('THEME_COLOR') ? THEME_COLOR : '#0076BE';
        foreach (documentQuoteExtras() as $extra) {
            $mpdf->AddPage();
            $mpdf->WriteHTML('<h2 style="font-family: sans-serif; color: ' . htmlspecialchars($theme) . '; font-size: 24px; margin-bottom: 20px;">'
                . htmlspecialchars($extra[0]) . '</h2>');
            $mpdf->WriteHTML($extra[1]);
        }
    }
    return $mpdf;
}

/** Find an executable in PATH (Windows and Unix). */
function documentFindExecutable($names)
{
    if (!function_exists('exec')) {
        return '';
    }
    foreach ((array) $names as $name) {
        $cmd = stripos(PHP_OS, 'WIN') === 0 ? 'where ' . $name . ' 2>NUL' : 'command -v ' . $name . ' 2>/dev/null';
        $out = [];
        $rc = 0;
        @exec($cmd, $out, $rc);
        if ($rc === 0 && !empty($out[0])) {
            return trim($out[0]);
        }
    }
    return '';
}

/** Combine page JPEGs vertically into one JPEG. */
function documentCombineJpegs(array $files, $outPath)
{
    if (!function_exists('imagecreatefromjpeg') || !$files) {
        return false;
    }
    $images = [];
    $width = 0;
    $height = 0;
    foreach ($files as $file) {
        $img = @imagecreatefromjpeg($file);
        if (!$img) {
            continue;
        }
        $images[] = $img;
        $width = max($width, imagesx($img));
        $height += imagesy($img);
    }
    if (!$images || $width < 1 || $height < 1) {
        return false;
    }
    $canvas = imagecreatetruecolor($width, $height);
    $white = imagecolorallocate($canvas, 255, 255, 255);
    imagefill($canvas, 0, 0, $white);
    $y = 0;
    foreach ($images as $img) {
        imagecopy($canvas, $img, 0, $y, 0, 0, imagesx($img), imagesy($img));
        $y += imagesy($img);
        imagedestroy($img);
    }
    $ok = imagejpeg($canvas, $outPath, 95);
    imagedestroy($canvas);
    return $ok && is_file($outPath) && filesize($outPath) > 0;
}

/**
 * Rasterise a PDF to a single JPEG. Tries Imagick, then Ghostscript,
 * pdftoppm, and ImageMagick CLI. Returns true when $outPath was written.
 */
function documentRasterizePdfToJpeg($pdfPath, $outPath, $dpi = 200)
{
    // 1) Imagick extension (all pages, stacked vertically).
    if (class_exists('Imagick')) {
        try {
            $pdf = new Imagick();
            $pdf->setResolution($dpi, $dpi);
            $pdf->readImage($pdfPath);
            $pages = [];
            foreach ($pdf as $frame) {
                $page = clone $frame;
                $page->setImageBackgroundColor('white');
                $page->setImageFormat('jpeg');
                $page->setImageCompressionQuality(95);
                $pages[] = $page->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);
            }
            $pdf->clear();
            if (!$pages) {
                return false;
            }
            $combined = new Imagick();
            foreach ($pages as $page) {
                $combined->addImage($page);
                $page->clear();
            }
            $combined->setImageFormat('jpeg');
            $combined->setImageCompressionQuality(95);
            $combined = $combined->appendImages(true);
            $combined->writeImage($outPath);
            $combined->clear();
            return is_file($outPath) && filesize($outPath) > 0;
        } catch (Throwable $e) {
            error_log('Imagick PDF raster failed: ' . $e->getMessage());
        }
    }

    // 2) CLI rasterisers. Each writes per-page JPEGs, then GD stacks them.
    if (!function_exists('exec')) {
        return false;
    }
    $prefix = tempnam(sys_get_temp_dir(), 'erp-jpg-');
    if ($prefix === false) {
        return false;
    }
    @unlink($prefix);
    $gs = documentFindExecutable(['gswin64c', 'gswin32c', 'gs']);
    $pdftoppm = documentFindExecutable(['pdftoppm']);
    // Windows' built-in convert.exe is the disk converter, not ImageMagick.
    $magick = documentFindExecutable(stripos(PHP_OS, 'WIN') === 0 ? ['magick'] : ['magick', 'convert']);

    $commands = [];
    if ($gs !== '') {
        $commands[] = escapeshellarg($gs)
            . ' -q -dNOPAUSE -dBATCH -sDEVICE=jpeg -r' . (int) $dpi
            . ' -dJPEGQ=95 -sOutputFile=' . escapeshellarg($prefix . '-%d.jpg')
            . ' ' . escapeshellarg($pdfPath) . ' 2>&1';
    }
    if ($pdftoppm !== '') {
        $commands[] = escapeshellarg($pdftoppm) . ' -jpeg -r ' . (int) $dpi
            . ' ' . escapeshellarg($pdfPath) . ' ' . escapeshellarg($prefix) . ' 2>&1';
    }
    if ($magick !== '') {
        $commands[] = escapeshellarg($magick) . ' -density ' . (int) $dpi
            . ' ' . escapeshellarg($pdfPath) . ' -background white -alpha remove -quality 95 '
            . escapeshellarg($prefix . '-%d.jpg') . ' 2>&1';
    }

    foreach ($commands as $cmd) {
        $out = [];
        $rc = 0;
        @exec($cmd, $out, $rc);
        $files = array_merge(
            glob($prefix . '-*.jpg') ?: [],
            glob($prefix . '-*.jpeg') ?: []
        );
        sort($files, SORT_NATURAL);
        if ($files && documentCombineJpegs($files, $outPath)) {
            foreach ($files as $file) {
                @unlink($file);
            }
            return true;
        }
        foreach ($files as $file) {
            @unlink($file);
        }
    }
    return false;
}

/**
 * Serve a document as JPEG. The PDF is rasterised when the server has
 * Imagick or a CLI rasteriser; otherwise the browser renders the identical
 * HTML with html2canvas and downloads the JPEG.
 */
function documentServeJpeg($type, $record, $lineItems = [], $parentInvoice = null, $downloadName = 'document.jpg')
{
    $safeName = preg_replace('/[^A-Za-z0-9._-]+/', '_', basename((string) $downloadName));
    if ($safeName === '' || $safeName === '.' || $safeName === '..') {
        $safeName = 'document.jpg';
    }

    $pdfPath = null;
    $jpegPath = null;
    try {
        $mpdf = documentBuildPdf($type, $record, $lineItems, $parentInvoice);
        $pdfPath = tempnam(sys_get_temp_dir(), 'erp-pdf-');
        $jpegPath = tempnam(sys_get_temp_dir(), 'erp-jpeg-');
        if ($pdfPath === false || $jpegPath === false) {
            throw new RuntimeException('Could not create a temporary export file.');
        }
        $mpdf->Output($pdfPath, 'F');
        if (documentRasterizePdfToJpeg($pdfPath, $jpegPath)) {
            header('Content-Type: image/jpeg');
            header('Content-Disposition: attachment; filename="' . $safeName . '"');
            header('Content-Length: ' . filesize($jpegPath));
            readfile($jpegPath);
            return;
        }
    } catch (Throwable $e) {
        error_log('JPEG export PDF/raster failed: ' . $e->getMessage());
    } finally {
        if ($pdfPath && is_file($pdfPath)) {
            @unlink($pdfPath);
        }
        if ($jpegPath && is_file($jpegPath)) {
            @unlink($jpegPath);
        }
    }

    // No server rasteriser available: use the same HTML in the browser.
    $html = documentHtmlForBrowser($type, $record, $lineItems, $parentInvoice);
    header('Content-Type: text/html; charset=UTF-8');
    echo documentClientJpegPage($html, $safeName);
}

/**
 * Add a download toolbar + html2canvas script to the browser HTML. Used when
 * neither Imagick nor a CLI rasteriser is available on the server.
 */
function documentClientJpegPage($html, $downloadName)
{
    $nonce = function_exists('cspNonce') ? cspNonce() : '';
    $lib = '../modules/hr/assets/idmaker/vendor/html2canvas.min.js';
    $name = json_encode((string) $downloadName);
    $script = '
<div id="jpeg-toolbar" style="position:fixed;left:0;right:0;bottom:0;z-index:99999;background:#111827;color:#fff;padding:10px 16px;font:14px Arial,sans-serif;display:flex;gap:12px;align-items:center;justify-content:space-between;">
  <span id="jpeg-status">Preparing JPEG...</span>
  <button id="jpeg-download" type="button" style="background:#2563eb;color:#fff;border:0;border-radius:6px;padding:8px 14px;font-weight:bold;cursor:pointer;">Download JPEG</button>
</div>
<script src="' . htmlspecialchars($lib) . '"></script>
<script nonce="' . htmlspecialchars($nonce) . '">
(function(){
  var name = ' . $name . ';
  var statusEl = document.getElementById("jpeg-status");
  function setStatus(t){ if(statusEl) statusEl.textContent = t; }
  function render(){
    if (typeof html2canvas !== "function") {
      setStatus("JPEG renderer did not load. Use the HTML export or print to PDF.");
      return;
    }
    setStatus("Rendering JPEG...");
    var target = document.body;
    var scale = Math.max(2, Math.min(3, 2000 / Math.max(1, target.scrollWidth)));
    html2canvas(target, {
      scale: scale,
      useCORS: true,
      backgroundColor: "#ffffff",
      logging: false,
      ignoreElements: function(el){ return el.id === "jpeg-toolbar" || el.tagName === "SCRIPT"; }
    }).then(function(canvas){
      canvas = normalizeA4(canvas);
      canvas.toBlob(function(blob){
        if (!blob) { setStatus("Could not create the JPEG. Click Download JPEG to retry."); return; }
        var a = document.createElement("a");
        a.href = URL.createObjectURL(blob);
        a.download = name;
        document.body.appendChild(a);
        a.click();
        setTimeout(function(){ URL.revokeObjectURL(a.href); a.remove(); }, 1500);
        setStatus("JPEG downloaded. You can close this tab.");
      }, "image/jpeg", 0.95);
    }).catch(function(err){
      console.error(err);
      setStatus("Automatic download was blocked. Click Download JPEG.");
    });
  }
  function normalizeA4(canvas){
    var ratio = 297 / 210; // A4 height / width
    var pages = Math.max(1, Math.ceil(canvas.height / (canvas.width * ratio)));
    var targetHeight = Math.round(canvas.width * ratio * pages);
    if (Math.abs(targetHeight - canvas.height) < 2) return canvas;
    var out = document.createElement("canvas");
    out.width = canvas.width;
    out.height = targetHeight;
    var ctx = out.getContext("2d");
    ctx.fillStyle = "#ffffff";
    ctx.fillRect(0, 0, out.width, out.height);
    ctx.drawImage(canvas, 0, 0);
    return out;
  }
  document.getElementById("jpeg-download").addEventListener("click", render);
  window.addEventListener("load", function(){ setTimeout(render, 400); });
})();
</script>';
    return preg_replace('/<\/body>/i', $script . '</body>', $html, 1);
}
