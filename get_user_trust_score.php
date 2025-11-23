<?php
// get_user_trust_score.php - Get user trust score and badge level

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type");

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/auth.php';

try {
    $userId = $_GET['user_id'] ?? null;
    
    if (!$userId) {
        throw new Exception('User ID is required');
    }
    
    $pdo = getDBConnection();
    
    // Ensure trust_score column exists
    try {
        $checkTrustScore = $pdo->query("SHOW COLUMNS FROM users LIKE 'trust_score'");
        if ($checkTrustScore->rowCount() === 0) {
            $pdo->exec("ALTER TABLE users ADD COLUMN trust_score INT DEFAULT 0 AFTER role");
            $pdo->exec("ALTER TABLE users ADD INDEX idx_trust_score (trust_score)");
        }
    } catch (PDOException $e) {
        // Column might already exist, ignore
    }
    
    // Get user trust score
    $sql = "SELECT trust_score, username FROM users WHERE id = :user_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':user_id' => $userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        throw new Exception('User not found');
    }
    
    $trustScore = intval($user['trust_score'] ?? 0);
    
    // Calculate badge level
    $badgeLevel = 'Novice';
    $badgeColor = '#6b7280'; // Gray for Novice
    
    if ($trustScore >= 16) {
        $badgeLevel = 'Expert';
        $badgeColor = '#10b981'; // Green for Expert
    } elseif ($trustScore >= 6) {
        $badgeLevel = 'Trusted';
        $badgeColor = '#3b82f6'; // Blue for Trusted
    }
    
    echo json_encode([
        'status' => 'success',
        'user_id' => intval($userId),
        'username' => $user['username'],
        'trust_score' => $trustScore,
        'badge_level' => $badgeLevel,
        'badge_color' => $badgeColor
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
    error_log("Error in get_user_trust_score.php: " . $e->getMessage());
}
?>

