<?php
// update_assignment.php - Admin API for assigning reports to crew members

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, PUT");
header("Access-Control-Allow-Headers: Content-Type");

require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/audit_helper.php';

// Start session and check authentication
startSession();

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Authentication required']);
    exit;
}

// Check if user is admin or crew (crew managers can assign tasks)
$userRole = getUserRole();
if ($userRole !== 'admin' && $userRole !== 'crew') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Access denied']);
    exit;
}

// Get request method
$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'POST' && $method !== 'PUT') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

// Get input data
$input = json_decode(file_get_contents('php://input'), true);

// Validate required fields
$reportId = isset($input['report_id']) ? intval($input['report_id']) : 0;
$reportType = isset($input['report_type']) ? $input['report_type'] : 'general';
$assignedToUserId = isset($input['assigned_to_user_id']) ? intval($input['assigned_to_user_id']) : null;
$etaSolved = isset($input['eta_solved']) ? $input['eta_solved'] : null;

if ($reportId === 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Report ID required']);
    exit;
}

if (!in_array($reportType, ['analysis', 'general'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid report type']);
    exit;
}

try {
    $pdo = getDBConnection();
    $currentUserId = getCurrentUserId();
    
    // If assigning to a user, verify they have crew role
    if ($assignedToUserId !== null && $assignedToUserId > 0) {
        $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$assignedToUserId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Assigned user not found']);
            exit;
        }
        
        // Crew managers can only assign to crew members, not admins
        if ($userRole === 'crew' && $user['role'] === 'admin') {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Crew managers can only assign tasks to crew members, not admins']);
            exit;
        }
        
        if ($user['role'] !== 'crew' && $user['role'] !== 'admin') {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Can only assign to crew members or admins']);
            exit;
        }
    }
    
    // Get current assignment details for audit log
    $table = $reportType === 'analysis' ? 'analysis_reports' : 'general_reports';
    $stmt = $pdo->prepare("SELECT assigned_to_user_id, eta_solved FROM $table WHERE id = ?");
    $stmt->execute([$reportId]);
    $currentReport = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$currentReport) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Report not found']);
        exit;
    }
    
    $oldAssignment = $currentReport['assigned_to_user_id'];
    $oldEta = $currentReport['eta_solved'];
    
    // Update assignment
    $sql = "UPDATE $table SET assigned_to_user_id = :assigned_to, eta_solved = :eta WHERE id = :report_id";
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([
        ':assigned_to' => $assignedToUserId,
        ':eta' => $etaSolved,
        ':report_id' => $reportId
    ]);
    
    if ($result) {
        // Log to audit log
        $oldValue = json_encode([
            'assigned_to' => $oldAssignment,
            'eta_solved' => $oldEta
        ]);
        
        $newValue = json_encode([
            'assigned_to' => $assignedToUserId,
            'eta_solved' => $etaSolved
        ]);
        
        logAuditAction($reportId, $reportType, $currentUserId, 'assignment_change', $oldValue, $newValue);
        
        // Broadcast event for real-time updates (will be implemented with SSE)
        if (function_exists('broadcastEvent')) {
            broadcastEvent('assignment_change', [
                'report_id' => $reportId,
                'report_type' => $reportType,
                'assigned_to' => $assignedToUserId,
                'eta_solved' => $etaSolved
            ]);
        }
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Assignment updated successfully',
            'report_id' => $reportId,
            'assigned_to' => $assignedToUserId,
            'eta_solved' => $etaSolved
        ], JSON_PRETTY_PRINT);
        
        error_log("✅ Report #$reportId ($reportType) assigned to user #$assignedToUserId by admin #$currentUserId");
    } else {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to update assignment']);
    }
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
    error_log("❌ Error in update_assignment.php: " . $e->getMessage());
}
?>

