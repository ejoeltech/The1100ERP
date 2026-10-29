<?php
// WP3: refuse direct web execution; this file only works when included.
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) { http_response_code(403); exit('Forbidden'); }

// Public pages used to ship a stripped-down header that dropped the main
// navigation. They now share the normal ERP shell: signed-in staff keep the
// full menu, while anonymous visitors only see the company name and Login
// (header.php already hides every admin link when $current_user is empty).
require __DIR__ . '/header.php';
