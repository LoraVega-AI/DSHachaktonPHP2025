<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Credentials: true");

require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/auth.php';

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Only GET requests are allowed');
    }

    $userRole = getUserRole();
    $currentUserId = getCurrentUserId();

    if ($userRole === 'guest' || $currentUserId === null) {
        throw new Exception('Authentication required');
    }

    $pdo = getDBConnection();

    // Get total reports for this user
    $stmtTotal = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM (
            SELECT id FROM analysis_reports WHERE user_id = :user_id
            UNION ALL
            SELECT id FROM general_reports WHERE user_id = :user_id
        ) as combined
    ");
    $stmtTotal->execute([':user_id' => $currentUserId]);
    $totalReports = $stmtTotal->fetchColumn() ?: 0;

    // Get solved reports
    $stmtSolved = $pdo->prepare("
        SELECT COUNT(*) as solved 
        FROM (
            SELECT id FROM analysis_reports WHERE user_id = :user_id AND status = 'SOLVED'
            UNION ALL
            SELECT id FROM general_reports WHERE user_id = :user_id AND status = 'SOLVED'
        ) as combined
    ");
    $stmtSolved->execute([':user_id' => $currentUserId]);
    $solvedReports = $stmtSolved->fetchColumn() ?: 0;

    // Get pending reports
    $stmtPending = $pdo->prepare("
        SELECT COUNT(*) as pending 
        FROM (
            SELECT id FROM analysis_reports WHERE user_id = :user_id AND status = 'PENDING'
            UNION ALL
            SELECT id FROM general_reports WHERE user_id = :user_id AND status = 'PENDING'
        ) as combined
    ");
    $stmtPending->execute([':user_id' => $currentUserId]);
    $pendingReports = $stmtPending->fetchColumn() ?: 0;

    echo json_encode([
        'status' => 'success',
        'total_reports' => (int)$totalReports,
        'solved_reports' => (int)$solvedReports,
        'pending_reports' => (int)$pendingReports
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
    error_log("❌ Error in get_user_stats.php: " . $e->getMessage());
}
?>

