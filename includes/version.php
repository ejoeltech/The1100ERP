<?php
// Central application identity. Safe to include anywhere; contains no secrets.
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) { http_response_code(403); exit('Forbidden'); }

if (!defined('APP_NAME')) define('APP_NAME', 'Eleven100 ERP');
if (!defined('APP_VERSION')) define('APP_VERSION', '1.1.0-dev');
if (!defined('APP_REPO_DEFAULT')) define('APP_REPO_DEFAULT', 'ejoeltech/1100erp');
