<?php
// get_crew_reports.php - API for fetching crew-specific reports

// Start session FIRST before any output
session_start();

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Credentials: true");

require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/auth.php';

// Ensure session is started
startSession();

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Authentication required']);
    exit;
}

// Check if user is crew or admin
$userRole = getUserRole();
if ($userRole !== 'crew' && $userRole !== 'admin') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Access denied. Crew or Admin role required.']);
    exit;
}

$userId = getCurrentUserId();

try {
    $pdo = getDBConnection();
    
    // Get assigned reports for this crew member
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
        ORDER BY created_at DESC
    ");
    $stmt->execute([':user_id' => $userId]);
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
        ORDER BY created_at DESC
    ");
    $stmt->execute([':user_id' => $userId]);
    $assignedReports = array_merge($assignedReports, $stmt->fetchAll(PDO::FETCH_ASSOC));
    
    // Get unassigned high-priority reports nearby (within 50km)
    // For simplicity, we'll get all unassigned HIGH and CRITICAL reports
    $unassignedReports = [];
    
    // Query analysis_reports
    $stmt = $pdo->query("
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
        WHERE assigned_to_user_id IS NULL 
            AND status IN ('pending', 'verified')
            AND severity IN ('HIGH', 'CRITICAL')
        ORDER BY 
            FIELD(severity, 'CRITICAL', 'HIGH'),
            created_at DESC
        LIMIT 20
    ");
    $unassignedReports = array_merge($unassignedReports, $stmt->fetchAll(PDO::FETCH_ASSOC));
    
    // Query general_reports
    $stmt = $pdo->query("
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
        WHERE assigned_to_user_id IS NULL 
            AND status IN ('pending', 'verified')
            AND severity IN ('HIGH', 'CRITICAL')
        ORDER BY 
            FIELD(severity, 'CRITICAL', 'HIGH'),
            created_at DESC
        LIMIT 20
    ");
    $unassignedReports = array_merge($unassignedReports, $stmt->fetchAll(PDO::FETCH_ASSOC));
    
    // Calculate status breakdown for assigned reports
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
    
    // Get crew member details
    $stmt = $pdo->prepare("SELECT username, email, trust_score FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $crewInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'status' => 'success',
        'crew_info' => $crewInfo,
        'assigned_reports' => $assignedReports,
        'unassigned_reports' => $unassignedReports,
        'status_counts' => $statusCounts,
        'total_assigned' => count($assignedReports),
        'total_unassigned' => count($unassignedReports)
    ], JSON_PRETTY_PRINT);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
    error_log("❌ Error in get_crew_reports.php: " . $e->getMessage());
}
?>

