<?php
// WP3: refuse direct web execution; this file only works when included.
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) { http_response_code(403); exit('Forbidden'); }
/**
 * Shared GD document renderer (quote/invoice/receipt JPEG fallback).
 * Mirrors the PDF template sections (header, meta, items, totals, footer)
 * so the JPEG reads like a screenshot of the PDF: ALL items, dynamic
 * canvas height, theme color. Used only when Imagick is unavailable —
 * the Imagick path renders the actual PDF and stays preferred.
 *
 * $doc = [
 *   'title' => 'QUOTATION', 'subtitle' => '...', 'company' => ..., 'contact' => ...,
 *   'info_left_label' => 'Quote For:', 'customer' => ...,
 *   'info_rows' => [[label, value], ...],           // right column
 *   'meta_heads' => [4 strings], 'meta_vals' => [4 strings],
 *   'items' => [['qty','desc','unit','total'], ...],
 *   'totals' => [[label, value], ...],              // above grand bar
 *   'grand_label' => 'Total Quote:', 'grand' => ...,
 *   'thanks' => '...', 'pay_label' => '...', 'banks' => [[name, number], ...],
 *   'prepared' => 'Quote prepared by: ...',
 * ]
 */

function jpegHex($img, $hex)
{
    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }
    return imagecolorallocate($img, hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2)));
}

function jpegTextWidth($size, $font, $text)
{
    $box = imagettfbbox($size, 0, $font, $text);
    return $box[2] - $box[0];
}

function jpegWrap($size, $font, $text, $maxW)
{
    $lines = [];
    foreach (explode("\n", $text) as $para) {
        $words = preg_split('/\s+/', $para);
        $line = '';
        foreach ($words as $w) {
            $try = $line === '' ? $w : $line . ' ' . $w;
            if (jpegTextWidth($size, $font, $try) > $maxW && $line !== '') {
                $lines[] = $line;
                $line = $w;
            } else {
                $line = $try;
            }
        }
        $lines[] = $line;
    }
    return $lines;
}

function renderDocumentJpeg($doc, $downloadName)
{
    $fontDir = __DIR__ . '/../vendor/mpdf/mpdf/ttfonts/';
    $font = $fontDir . 'DejaVuSans.ttf';
    $fontB = $fontDir . 'DejaVuSans-Bold.ttf';
    if (!file_exists($font) || !file_exists($fontB)) {
        throw new Exception('Document fonts are missing on the server.');
    }

    $theme = $doc['theme'] ?? (defined('THEME_COLOR') ? THEME_COLOR : '#0076BE');
    $W = 1600;
    $M = 90; // margin
    $CW = $W - 2 * $M; // content width

    // Pre-wrap descriptions to size the canvas for ALL items.
    $descW = (int)($CW * 0.52);
    $wrapped = [];
    $rowLines = 0;
    foreach ($doc['items'] as $it) {
        $wl = jpegWrap(21, $font, $it['qty'] . ' x ' . $it['desc'], $descW);
        $wrapped[] = $wl;
        $rowLines += max(1, count($wl));
    }

    $H = 760 + $rowLines * 46 + 560;
    $img = imagecreatetruecolor($W, $H);
    $white = imagecolorallocate($img, 255, 255, 255);
    $black = imagecolorallocate($img, 30, 30, 30);
    $gray = imagecolorallocate($img, 100, 100, 100);
    $lgray = imagecolorallocate($img, 220, 222, 226);
    $bg = imagecolorallocate($img, 245, 246, 248);
    $themeC = jpegHex($img, $theme);
    imagefill($img, 0, 0, $white);

    $y = 90;

    // Company header (centered)
    $t = $doc['company'] ?? (defined('COMPANY_NAME') ? COMPANY_NAME : 'Eleven100 ERP');
    imagettftext($img, 42, 0, (int)(($W - jpegTextWidth(42, $fontB, $t)) / 2), $y, $themeC, $fontB, $t);
    $y += 52;
    $t = $doc['contact'] ?? '';
    if ($t !== '') {
        foreach (jpegWrap(19, $font, $t, $CW) as $ln) {
            imagettftext($img, 19, 0, (int)(($W - jpegTextWidth(19, $font, $ln)) / 2), $y, $gray, $font, $ln);
            $y += 32;
        }
    }
    $y += 14;
    imagefilledrectangle($img, $M, $y, $W - $M, $y + 4, $themeC);
    $y += 64;

    // Title + subtitle (centered)
    $t = $doc['title'];
    imagettftext($img, 44, 0, (int)(($W - jpegTextWidth(44, $fontB, $t)) / 2), $y, $themeC, $fontB, $t);
    $y += 52;
    if (!empty($doc['subtitle'])) {
        $t = $doc['subtitle'];
        imagettftext($img, 21, 0, (int)(($W - jpegTextWidth(21, $font, $t)) / 2), $y, $gray, $font, $t);
        $y += 44;
    }
    $y += 10;

    // Info: customer box (left) + detail rows (right)
    $boxH = 150;
    imagefilledrectangle($img, $M, $y, $M + (int)($CW * 0.52), $y + $boxH, $bg);
    imagerectangle($img, $M, $y, $M + (int)($CW * 0.52), $y + $boxH, $lgray);
    imagettftext($img, 20, 0, $M + 24, $y + 44, $black, $fontB, $doc['info_left_label'] ?? 'Bill To:');
    $cn = $doc['customer'] ?? '';
    foreach (array_slice(jpegWrap(22, $fontB, $cn, (int)($CW * 0.52) - 48), 0, 2) as $k => $ln) {
        imagettftext($img, 22, 0, $M + 24, $y + 84 + $k * 36, $black, $fontB, $ln);
    }
    $rx = $M + (int)($CW * 0.58);
    $ry = $y + 40;
    foreach ($doc['info_rows'] as $row) {
        imagettftext($img, 20, 0, $rx, $ry, $black, $fontB, $row[0]);
        $v = $row[1];
        imagettftext($img, 20, 0, $W - $M - (int)jpegTextWidth(20, $fontB, $v), $ry, $themeC, $fontB, $v);
        $ry += 42;
    }
    $y += $boxH + 46;

    // Meta strip: 4 header cells + values (like the PDF meta-table)
    $colW = (int)($CW / 4);
    imagefilledrectangle($img, $M, $y, $W - $M, $y + 52, $bg);
    for ($c = 0; $c < 4; $c++) {
        $h = $doc['meta_heads'][$c] ?? '';
        imagettftext($img, 18, 0, $M + $c * $colW + 20, $y + 35, $black, $fontB, $h);
        if ($c > 0) imagettftext($img, 18, 0, $M + $c * $colW, $y, $lgray, $font, '|');
    }
    $y += 52;
    imagerectangle($img, $M, $y - 52, $W - $M, $y + 48, $lgray);
    for ($c = 0; $c < 4; $c++) {
        $v = $doc['meta_vals'][$c] ?? '-';
        imagettftext($img, 18, 0, $M + $c * $colW + 20, $y + 32, $black, $font, $v);
    }
    $y += 48 + 44;

    // Items header
    $xNum = $M; $xQty = $M + 70; $xDesc = $M + 190; $xUnit = $W - $M - 460; $xTotal = $W - $M - 260;
    imagefilledrectangle($img, $M, $y, $W - $M, $y + 58, $themeC);
    imagettftext($img, 20, 0, $xNum + 20, $y + 39, $white, $fontB, '#');
    imagettftext($img, 20, 0, $xQty, $y + 39, $white, $fontB, 'Qty');
    imagettftext($img, 20, 0, $xDesc, $y + 39, $white, $fontB, 'Description');
    imagettftext($img, 20, 0, $xUnit, $y + 39, $white, $fontB, 'Unit Price');
    $tt = 'Line Total';
    imagettftext($img, 20, 0, $W - $M - 20 - (int)jpegTextWidth(20, $fontB, $tt), $y + 39, $white, $fontB, $tt);
    $y += 58;

    // Item rows (zebra, wrapped)
    $n = 1;
    foreach ($doc['items'] as $idx => $it) {
        $lines = $wrapped[$idx];
        $rh = count($lines) * 46 + 22;
        if (($n % 2) === 1) {
            imagefilledrectangle($img, $M, $y, $W - $M, $y + $rh, $bg);
        }
        imagettftext($img, 20, 0, $xNum + 20, $y + 38, $black, $font, (string)$n);
        imagettftext($img, 20, 0, $xQty, $y + 38, $black, $font, (string)$it['qty']);
        $ly = $y + 38;
        foreach ($lines as $ln) {
            imagettftext($img, 21, 0, $xDesc, $ly, $black, $font, $ln);
            $ly += 46;
        }
        imagettftext($img, 20, 0, $xUnit, $y + 38, $black, $font, $it['unit']);
        $tv = $it['total'];
        imagettftext($img, 20, 0, $W - $M - 20 - (int)jpegTextWidth(20, $fontB, $tv), $y + 38, $black, $fontB, $tv);
        $y += $rh;
        $n++;
    }
    $y += 40;

    // Totals (right block)
    $tx = $W - $M - 560;
    foreach ($doc['totals'] as $row) {
        imagettftext($img, 21, 0, $tx, $y, $gray, $fontB, $row[0]);
        imagettftext($img, 21, 0, $W - $M - 20 - (int)jpegTextWidth(21, $fontB, $row[1]), $y, $black, $fontB, $row[1]);
        $y += 52;
    }
    $y += 6;
    imagefilledrectangle($img, $tx - 30, $y - 8, $W - $M, $y + 62, $themeC);
    imagettftext($img, 22, 0, $tx, $y + 40, $white, $fontB, $doc['grand_label']);
    $gv = $doc['grand'];
    imagettftext($img, 22, 0, $W - $M - 20 - (int)jpegTextWidth(22, $fontB, $gv), $y + 40, $white, $fontB, $gv);
    $y += 62 + 70;

    // Signature line (right)
    $sx = $W - $M - 420;
    imagettftext($img, 18, 0, $sx, $y, $black, $fontB, 'Authorized Signature');
    $y += 8;
    imagefilledrectangle($img, $sx - 20, $y, $W - $M, $y + 2, $black);
    $y += 60;

    // Footer
    imagefilledrectangle($img, $M, $y, $W - $M, $y + 4, $themeC);
    $y += 44;
    if (!empty($doc['thanks'])) {
        $t = $doc['thanks'];
        imagettftext($img, 20, 0, (int)(($W - jpegTextWidth(20, $font, $t)) / 2), $y, $gray, $font, $t);
        $y += 44;
    }
    if (!empty($doc['pay_label'])) {
        $t = $doc['pay_label'];
        $bw = (int)jpegTextWidth(20, $fontB, $t) + 60;
        imagefilledrectangle($img, (int)(($W - $bw) / 2), $y - 32, (int)(($W + $bw) / 2), $y + 12, $themeC);
        imagettftext($img, 20, 0, (int)(($W - jpegTextWidth(20, $fontB, $t)) / 2), $y, $white, $fontB, $t);
        $y += 52;
        foreach ($doc['banks'] as $b) {
            $t = $b[0] . ': ' . $b[1];
            imagettftext($img, 19, 0, (int)(($W - jpegTextWidth(19, $font, $t)) / 2), $y, $black, $font, $t);
            $y += 38;
        }
        $y += 10;
    }
    if (!empty($doc['prepared'])) {
        $t = $doc['prepared'];
        imagettftext($img, 17, 0, $W - $M - (int)jpegTextWidth(17, $font, $t), $y, $gray, $font, $t);
        $y += 40;
    }

    // Crop canvas to content
    $final = imagecreatetruecolor($W, $y + 60);
    imagefill($final, 0, 0, $white);
    imagecopy($final, $img, 0, 0, 0, 0, $W, $y + 60);
    imagedestroy($img);

    header('Content-Type: image/jpeg');
    header('Content-Disposition: attachment; filename="' . $downloadName . '"');
    imagejpeg($final, null, 92);
    imagedestroy($final);
    exit;
}
