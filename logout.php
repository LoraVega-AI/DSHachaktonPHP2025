<?php
// logout.php - User logout endpoint

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Credentials: true");

// Include authentication functions
require_once __DIR__ . '/auth.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

try {
    // Allow both GET and POST for logout
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Only POST and GET requests are allowed');
    }
    
    // Log the user out
    $result = logout();
    
    echo json_encode($result, JSON_PRETTY_PRINT);
    error_log("✅ User logged out successfully");
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
    error_log("❌ Error in logout.php: " . $e->getMessage());
}
?>

