<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
$p = $argv[1] ?? '';
if ($p === '' || !is_file($p)) { fwrite(STDERR, "missing\n"); exit(1); }
$i = getimagesize($p);
echo $i[0] . 'x' . $i[1] . ' ' . $i['mime'] . ' ' . filesize($p) . " bytes\n";
