<?php
// rankings.php - Report rankings API (most critical, most recent, etc.)

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
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Only GET requests are allowed');
    }
    
    // Require login (guests cannot access rankings)
    $userRole = getUserRole();
    if ($userRole === 'guest') {
        http_response_code(403);
        throw new Exception('Rankings access requires login. Please log in or register.');
    }
    
    $pdo = getDBConnection();
    
    // Get most critical reports
    $criticalSql = "(SELECT 
        id,
        'analysis' as report_type,
        signature_name as title,
        classification as description,
        severity,
        category,
        created_at,
        status
    FROM analysis_reports
    WHERE severity IN ('CRITICAL', 'HIGH')
    ORDER BY 
        CASE severity 
            WHEN 'CRITICAL' THEN 1 
            WHEN 'HIGH' THEN 2 
            ELSE 3 
        END,
        created_at DESC
    LIMIT 10)
    UNION ALL
    (SELECT 
        id,
        'general' as report_type,
        title,
        description,
        severity,
        category,
        created_at,
        status
    FROM general_reports
    WHERE severity IN ('CRITICAL', 'HIGH')
    ORDER BY 
        CASE severity 
            WHEN 'CRITICAL' THEN 1 
            WHEN 'HIGH' THEN 2 
            ELSE 3 
        END,
        created_at DESC
    LIMIT 10)
    ORDER BY 
        CASE severity 
            WHEN 'CRITICAL' THEN 1 
            WHEN 'HIGH' THEN 2 
            ELSE 3 
        END,
        created_at DESC
    LIMIT 10";
    
    $criticalStmt = $pdo->query($criticalSql);
    $criticalReports = $criticalStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get most recent reports
    $recentSql = "(SELECT 
        id,
        'analysis' as report_type,
        signature_name as title,
        classification as description,
        severity,
        category,
        created_at,
        status
    FROM analysis_reports
    ORDER BY created_at DESC
    LIMIT 10)
    UNION ALL
    (SELECT 
        id,
        'general' as report_type,
        title,
        description,
        severity,
        category,
        created_at,
        status
    FROM general_reports
    ORDER BY created_at DESC
    LIMIT 10)
    ORDER BY created_at DESC
    LIMIT 10";
    
    $recentStmt = $pdo->query($recentSql);
    $recentReports = $recentStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get pending reports (unsolved)
    $pendingSql = "(SELECT 
        id,
        'analysis' as report_type,
        signature_name as title,
        classification as description,
        severity,
        category,
        created_at,
        status
    FROM analysis_reports
    WHERE status = 'pending'
    ORDER BY created_at DESC
    LIMIT 10)
    UNION ALL
    (SELECT 
        id,
        'general' as report_type,
        title,
        description,
        severity,
        category,
        created_at,
        status
    FROM general_reports
    WHERE status = 'pending'
    ORDER BY created_at DESC
    LIMIT 10)
    ORDER BY created_at DESC
    LIMIT 10";
    
    $pendingStmt = $pdo->query($pendingSql);
    $pendingReports = $pendingStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total counts by category
    $categorySql = "SELECT 
        category,
        COUNT(*) as count
    FROM (
        SELECT category FROM analysis_reports
        UNION ALL
        SELECT category FROM general_reports
    ) as all_reports
    GROUP BY category
    ORDER BY count DESC";
    
    $categoryStmt = $pdo->query($categorySql);
    $categoryStats = $categoryStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Helper function to format time ago
    function getTimeAgo($datetime) {
        $time = strtotime($datetime);
        $diff = time() - $time;
        
        if ($diff < 60) return $diff . 's ago';
        if ($diff < 3600) return floor($diff / 60) . 'm ago';
        if ($diff < 86400) return floor($diff / 3600) . 'h ago';
        if ($diff < 604800) return floor($diff / 86400) . 'd ago';
        return floor($diff / 604800) . 'w ago';
    }
    
    // Format reports
    $formatReport = function($report) {
        return [
            'id' => intval($report['id']),
            'report_type' => $report['report_type'],
            'title' => $report['title'],
            'description' => substr($report['description'], 0, 100) . '...',
            'severity' => $report['severity'],
            'category' => $report['category'],
            'status' => $report['status'],
            'created_at' => $report['created_at'],
            'timeAgo' => getTimeAgo($report['created_at'])
        ];
    };
    
    echo json_encode([
        'status' => 'success',
        'rankings' => [
            'most_critical' => array_map($formatReport, $criticalReports),
            'most_recent' => array_map($formatReport, $recentReports),
            'pending' => array_map($formatReport, $pendingReports),
            'by_category' => $categoryStats
        ]
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
    error_log("Error in rankings.php: " . $e->getMessage());
}
?>

