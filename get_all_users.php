<?php
// get_all_users.php - Get all registered users (admin only)

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Credentials: true");

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/auth.php';

try {
    // Require admin role
    requireRole('admin');

    $pdo = getDBConnection();
    
    // Check if users table exists
    $checkUsers = $pdo->query("SHOW TABLES LIKE 'users'");
    if ($checkUsers->rowCount() === 0) {
        throw new Exception("Users table does not exist");
    }
    
    // Check if profile_img and bio columns exist
    $checkProfileImg = $pdo->query("SHOW COLUMNS FROM users LIKE 'profile_img'");
    $hasProfileImg = $checkProfileImg->rowCount() > 0;
    
    $checkBio = $pdo->query("SHOW COLUMNS FROM users LIKE 'bio'");
    $hasBio = $checkBio->rowCount() > 0;
    
    // Build query based on available columns
    $profileImgField = $hasProfileImg ? "u.profile_img" : "NULL as profile_img";
    $bioField = $hasBio ? "u.bio" : "NULL as bio";
    
    // Check if report tables exist
    $checkAnalysisReports = $pdo->query("SHOW TABLES LIKE 'analysis_reports'");
    $hasAnalysisReports = $checkAnalysisReports->rowCount() > 0;
    
    $checkGeneralReports = $pdo->query("SHOW TABLES LIKE 'general_reports'");
    $hasGeneralReports = $checkGeneralReports->rowCount() > 0;
    
    // Build report count subquery - use correlated subqueries (can't use UNION in nested derived table)
    if ($hasAnalysisReports && $hasGeneralReports) {
        $reportCountSubquery = "(
            (SELECT COUNT(*) FROM analysis_reports WHERE user_id = u.id) +
            (SELECT COUNT(*) FROM general_reports WHERE user_id = u.id)
        )";
    } elseif ($hasAnalysisReports) {
        $reportCountSubquery = "(SELECT COUNT(*) FROM analysis_reports WHERE user_id = u.id)";
    } elseif ($hasGeneralReports) {
        $reportCountSubquery = "(SELECT COUNT(*) FROM general_reports WHERE user_id = u.id)";
    } else {
        $reportCountSubquery = "0";
    }
    
    // Get all users with profile images and report counts
    $query = "
        SELECT 
            u.id, 
            u.username, 
            u.email, 
            u.role, 
            u.created_at,
            {$profileImgField} as profile_img,
            {$bioField} as bio,
            {$reportCountSubquery} as report_count
        FROM users u 
        ORDER BY u.created_at DESC
    ";
    
    $stmt = $pdo->query($query);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get statistics
    $stats = [
        'total_users' => count($users),
        'admin_count' => 0,
        'user_count' => 0,
        'guest_count' => 0
    ];
    
    foreach ($users as $user) {
        switch ($user['role']) {
            case 'admin':
                $stats['admin_count']++;
                break;
            case 'user':
                $stats['user_count']++;
                break;
            case 'guest':
                $stats['guest_count']++;
                break;
        }
    }
    
    echo json_encode([
        'status' => 'success',
        'users' => $users,
        'statistics' => $stats
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'debug' => [
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ], JSON_PRETTY_PRINT);
    error_log("Error in get_all_users.php: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
}
?>

