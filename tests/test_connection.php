<?php
// Simple test to check if database connection works
error_reporting(E_ALL);
ini_set('display_errors', 1);
header("Content-Type: application/json");

try {
    require_once __DIR__ . '/db_config.php';
    
    $pdo = getDBConnection();
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Database connection successful',
        'database' => DB_NAME
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
?>

