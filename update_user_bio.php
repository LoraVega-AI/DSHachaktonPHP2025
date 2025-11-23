<?php
// update_user_bio.php - Update user bio/description

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Credentials: true");

require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/auth.php';

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Only POST requests are allowed');
    }

    // Require user to be logged in
    if (!isLoggedIn()) {
        throw new Exception('Authentication required');
    }

    $currentUserId = getCurrentUserId();
    
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['bio'])) {
        throw new Exception('Bio field is required');
    }

    $bio = trim($input['bio']);
    
    // Validate bio length (max 500 characters)
    if (strlen($bio) > 500) {
        throw new Exception('Bio must be 500 characters or less');
    }

    // Update database
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("UPDATE users SET bio = :bio WHERE id = :user_id");
    $stmt->execute([
        ':bio' => $bio,
        ':user_id' => $currentUserId
    ]);

    echo json_encode([
        'status' => 'success',
        'message' => 'Bio updated successfully',
        'bio' => $bio
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
    error_log("Error in update_user_bio.php: " . $e->getMessage());
}
?>

