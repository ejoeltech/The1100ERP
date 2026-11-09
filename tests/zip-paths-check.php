<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
$zipPath = $argv[1] ?? 'C:/Users/user/Downloads/1100erp-v1.2.10-update.zip';
$z = new ZipArchive();
if ($z->open($zipPath) !== true) { fwrite(STDERR, "cannot open\n"); exit(1); }
$bad = 0;
for ($i = 0; $i < $z->numFiles; $i++) {
    $n = $z->getNameIndex($i);
    if (strpos($n, '\\') !== false) { echo "BACKSLASH: $n\n"; $bad++; }
    if (strpos($n, 'version.php') !== false) { echo "VERSION ENTRY: $n\n"; }
}
echo "entries={$z->numFiles} backslash={$bad}\n";
$z->close();
