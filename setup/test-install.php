<?php
// Quick test of install.php
session_start();
header('Content-Type: application/json');

$action = $_POST['action'] ?? 'none';
$response = [
    'success' => true,
    'message' => 'Test successful - Action received: ' . $action,
    'session_id' => session_id(),
    'post_data' => $_POST
];

echo json_encode($response);
?>