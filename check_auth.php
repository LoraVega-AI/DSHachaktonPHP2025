<?php
// check_auth.php - Check authentication status endpoint

// Start session FIRST before any output
session_start();

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Credentials: true");

// Include authentication functions
require_once __DIR__ . '/auth.php';

// Ensure session is started
startSession();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

try {
    // Check if it's a GET request
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Only GET requests are allowed');
    }
    
    // Get authentication status
    $status = getAuthStatus();
    
    echo json_encode($status, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'authenticated' => false,
        'user' => null,
        'role' => 'guest',
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
    error_log("❌ Error in check_auth.php: " . $e->getMessage());
}
?>

