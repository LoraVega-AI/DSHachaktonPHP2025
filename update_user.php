<?php
// update_user.php - Update user information (admin only, or own profile for basic fields)

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

    // Require authentication
    if (!isLoggedIn()) {
        throw new Exception('Authentication required');
    }

    $currentUserId = getCurrentUserId();
    $currentUserRole = getUserRole();
    
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['user_id'])) {
        throw new Exception('User ID is required');
    }
    
    $targetUserId = intval($input['user_id']);
    
    // Check permissions
    $isOwnProfile = ($targetUserId === $currentUserId);
    $isAdmin = ($currentUserRole === 'admin');
    
    if (!$isOwnProfile && !$isAdmin) {
        throw new Exception('Permission denied');
    }
    
    $pdo = getDBConnection();
    $updates = [];
    $params = [':user_id' => $targetUserId];
    
    // Fields users can update on their own profile
    if (isset($input['email']) && filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
        $updates[] = "email = :email";
        $params[':email'] = $input['email'];
    }
    
    if (isset($input['bio'])) {
        $bio = trim($input['bio']);
        if (strlen($bio) <= 500) {
            $updates[] = "bio = :bio";
            $params[':bio'] = $bio;
        }
    }
    
    // Admin-only fields
    if ($isAdmin) {
        if (isset($input['role']) && in_array($input['role'], ['guest', 'user', 'admin'])) {
            $updates[] = "role = :role";
            $params[':role'] = $input['role'];
        }
        
        if (isset($input['username'])) {
            $username = trim($input['username']);
            if (strlen($username) >= 3 && strlen($username) <= 50) {
                $updates[] = "username = :username";
                $params[':username'] = $username;
            }
        }
    }
    
    if (empty($updates)) {
        throw new Exception('No valid fields to update');
    }
    
    // Build and execute update query
    $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = :user_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    // Get updated user data
    $userStmt = $pdo->prepare("SELECT id, username, email, role, created_at, profile_img, bio FROM users WHERE id = :user_id");
    $userStmt->execute([':user_id' => $targetUserId]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'status' => 'success',
        'message' => 'User updated successfully',
        'user' => $user
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
    error_log("Error in update_user.php: " . $e->getMessage());
}
?>

