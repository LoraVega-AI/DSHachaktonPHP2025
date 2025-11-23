<?php
// get_member_tasks.php - Get tasks assigned to a specific crew member (for managers)

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Credentials: true");

require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/auth.php';

startSession();

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Authentication required']);
    exit;
}

// Check if user is admin or crew (managers)
$userRole = getUserRole();
if ($userRole !== 'admin' && $userRole !== 'crew') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Access denied']);
    exit;
}

// Get member ID from query parameter
$memberId = isset($_GET['member_id']) ? intval($_GET['member_id']) : 0;

if ($memberId === 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Member ID required']);
    exit;
}

try {
    $pdo = getDBConnection();
    
    // Verify the member exists and is a crew member
    $stmt = $pdo->prepare("SELECT id, username, email, role FROM users WHERE id = ?");
    $stmt->execute([$memberId]);
    $member = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$member) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Member not found']);
        exit;
    }
    
    // If current user is crew manager, ensure they can only view crew members (not admins)
    if ($userRole === 'crew' && $member['role'] === 'admin') {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Crew managers can only view tasks for crew members']);
        exit;
    }
    
    $assignedReports = [];
    
    // Query analysis_reports
    $stmt = $pdo->prepare("
        SELECT 
            id, 
            'analysis' as report_type,
            top_hazard as title,
            executive_conclusion as description,
            severity,
            category,
            status,
            latitude,
            longitude,
            address,
            assigned_to_user_id,
            eta_solved,
            created_at,
            timestamp
        FROM analysis_reports 
        WHERE assigned_to_user_id = :user_id 
        ORDER BY 
            FIELD(status, 'pending', 'in_progress', 'solved', 'verified'),
            created_at DESC
    ");
    $stmt->execute([':user_id' => $memberId]);
    $assignedReports = array_merge($assignedReports, $stmt->fetchAll(PDO::FETCH_ASSOC));
    
    // Query general_reports
    $stmt = $pdo->prepare("
        SELECT 
            id,
            'general' as report_type,
            title,
            description,
            severity,
            category,
            status,
            latitude,
            longitude,
            address,
            assigned_to_user_id,
            eta_solved,
            created_at,
            created_at as timestamp
        FROM general_reports 
        WHERE assigned_to_user_id = :user_id 
        ORDER BY 
            FIELD(status, 'pending', 'in_progress', 'solved', 'verified'),
            created_at DESC
    ");
    $stmt->execute([':user_id' => $memberId]);
    $assignedReports = array_merge($assignedReports, $stmt->fetchAll(PDO::FETCH_ASSOC));
    
    // Count by status
    $statusCounts = [
        'pending' => 0,
        'in_progress' => 0,
        'solved' => 0,
        'verified' => 0
    ];
    
    foreach ($assignedReports as $report) {
        $status = $report['status'] ?? 'pending';
        if (isset($statusCounts[$status])) {
            $statusCounts[$status]++;
        }
    }
    
    echo json_encode([
        'status' => 'success',
        'member_info' => [
            'id' => $member['id'],
            'username' => $member['username'],
            'email' => $member['email'],
            'role' => $member['role']
        ],
        'tasks' => $assignedReports,
        'status_counts' => $statusCounts,
        'total_tasks' => count($assignedReports)
    ], JSON_PRETTY_PRINT);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
    error_log("❌ Error in get_member_tasks.php: " . $e->getMessage());
}
?>

