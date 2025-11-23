<?php
// login.php - User login endpoint

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Credentials: true");

// Include authentication functions
require_once __DIR__ . '/auth.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

try {
    // Check if it's a POST request
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Only POST requests are allowed');
    }
    
    // Get JSON input
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    // If JSON parsing failed, try to get POST data
    if (json_last_error() !== JSON_ERROR_NONE) {
        $data = $_POST;
    }
    
    // Get login credentials
    $username = $data['username'] ?? '';
    $password = $data['password'] ?? '';
    
    // Validate required fields
    if (empty($username) || empty($password)) {
        throw new Exception('Username and password are required');
    }
    
    // Call login function
    $result = login($username, $password);
    
    // Set appropriate HTTP status code
    if ($result['status'] === 'error') {
        http_response_code(401); // Unauthorized
    } else {
        http_response_code(200);
        error_log("✅ User logged in successfully: $username");
    }
    
    echo json_encode($result, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
    error_log("❌ Error in login.php: " . $e->getMessage());
}
?>

