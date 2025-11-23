<?php
// get_unassigned_reports.php - Get unassigned high-priority reports for assignment

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

try {
    $pdo = getDBConnection();
    $unassignedReports = [];
    
    // Query analysis_reports for unassigned high-priority reports
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
            AND status IN ('pending', 'in_progress')
            AND severity IN ('HIGH', 'CRITICAL')
        ORDER BY 
            FIELD(severity, 'CRITICAL', 'HIGH'),
            created_at DESC
        LIMIT 50
    ");
    $unassignedReports = array_merge($unassignedReports, $stmt->fetchAll(PDO::FETCH_ASSOC));
    
    // Query general_reports for unassigned high-priority reports
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
            AND status IN ('pending', 'in_progress')
            AND severity IN ('HIGH', 'CRITICAL')
        ORDER BY 
            FIELD(severity, 'CRITICAL', 'HIGH'),
            created_at DESC
        LIMIT 50
    ");
    $unassignedReports = array_merge($unassignedReports, $stmt->fetchAll(PDO::FETCH_ASSOC));
    
    // Sort combined results to ensure CRITICAL always comes first, then HIGH
    usort($unassignedReports, function($a, $b) {
        $severityOrder = ['CRITICAL' => 1, 'HIGH' => 2];
        $aOrder = $severityOrder[$a['severity']] ?? 3;
        $bOrder = $severityOrder[$b['severity']] ?? 3;
        
        if ($aOrder !== $bOrder) {
            return $aOrder - $bOrder;
        }
        
        // If same severity, sort by created_at DESC (newest first)
        $aTime = strtotime($a['created_at'] ?? $a['timestamp'] ?? '1970-01-01');
        $bTime = strtotime($b['created_at'] ?? $b['timestamp'] ?? '1970-01-01');
        return $bTime - $aTime;
    });
    
    echo json_encode([
        'status' => 'success',
        'reports' => $unassignedReports,
        'total' => count($unassignedReports)
    ], JSON_PRETTY_PRINT);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
    error_log("❌ Error in get_unassigned_reports.php: " . $e->getMessage());
}
?>

