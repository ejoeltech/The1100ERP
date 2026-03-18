<?php
// Fix install.php by removing duplicate session_start()
$file = __DIR__ . '/install.php';
$content = file_get_contents($file);

// Remove the duplicate session_start() at the end
$content = preg_replace('/\/\/ Start session for storing.*?session_start\(\);.*?\?>/s', '?>', $content);

file_put_contents($file, $content);
echo "Fixed install.php - removed duplicate session_start()";
?>