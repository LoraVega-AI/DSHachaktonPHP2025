<?php
// get_heatmap_data.php - Fetch report density data for heatmap visualization

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type");

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

try {
    require_once __DIR__ . '/db_config.php';
    require_once __DIR__ . '/auth.php';
    
    $pdo = getDBConnection();
    
    // Check if general_reports table exists
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'general_reports'");
    $generalReportsExists = ($tableCheck->rowCount() > 0);
    
    // Check user role and ID
    $userRole = getUserRole();
    $currentUserId = getCurrentUserId();
    
    // Get filter parameters
    $severity = $_GET['severity'] ?? null;
    $category = $_GET['category'] ?? null;
    $filterOwn = isset($_GET['filter_own']) && $_GET['filter_own'] === 'true';
    
    // Build query conditions
    $whereAnalysis = "latitude IS NOT NULL AND longitude IS NOT NULL AND latitude != 0 AND longitude != 0";
    $whereGeneral = "latitude IS NOT NULL AND longitude IS NOT NULL AND latitude != 0 AND longitude != 0";
    $params = [];
    
    // Filter by user's own reports if requested
    if ($filterOwn && $userRole !== 'guest' && $currentUserId !== null) {
        $whereAnalysis .= " AND user_id = :user_id_analysis";
        $whereGeneral .= " AND user_id = :user_id_general";
        $params[':user_id_analysis'] = $currentUserId;
        $params[':user_id_general'] = $currentUserId;
    }
    
    if ($severity) {
        $whereAnalysis .= " AND severity = :severity_analysis";
        $whereGeneral .= " AND severity = :severity_general";
        $params[':severity_analysis'] = $severity;
        $params[':severity_general'] = $severity;
    }
    
    if ($category) {
        $whereAnalysis .= " AND category = :category_analysis";
        $whereGeneral .= " AND category = :category_general";
        $params[':category_analysis'] = $category;
        $params[':category_general'] = $category;
    }
    
    // Query to get heatmap points with intensity based on severity
    // Weight: CRITICAL=1.0, HIGH=0.75, MEDIUM=0.5, LOW=0.25
    $sql = "(SELECT 
        latitude,
        longitude,
        severity,
        CASE 
            WHEN severity = 'CRITICAL' THEN 1.0
            WHEN severity = 'HIGH' THEN 0.75
            WHEN severity = 'MEDIUM' THEN 0.5
            WHEN severity = 'LOW' THEN 0.25
            ELSE 0.5
        END as intensity
    FROM analysis_reports
    WHERE " . $whereAnalysis . ")";
    
    // Only add UNION if general_reports table exists
    if ($generalReportsExists) {
        $sql .= " UNION ALL
        (SELECT 
            latitude,
            longitude,
            severity,
            CASE 
                WHEN severity = 'CRITICAL' THEN 1.0
                WHEN severity = 'HIGH' THEN 0.75
                WHEN severity = 'MEDIUM' THEN 0.5
                WHEN severity = 'LOW' THEN 0.25
                ELSE 0.5
            END as intensity
        FROM general_reports
        WHERE " . $whereGeneral . ")";
    }
    
    $stmt = $pdo->prepare($sql);
    
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    
    $stmt->execute();
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format data for heatmap: [lat, lng, intensity]
    $heatmapData = array_map(function($report) {
        return [
            floatval($report['latitude']),
            floatval($report['longitude']),
            floatval($report['intensity'])
        ];
    }, $reports);
    
    // Get statistics
    $stats = [
        'total_points' => count($heatmapData),
        'severity_distribution' => [
            'critical' => count(array_filter($reports, fn($r) => $r['severity'] === 'CRITICAL')),
            'high' => count(array_filter($reports, fn($r) => $r['severity'] === 'HIGH')),
            'medium' => count(array_filter($reports, fn($r) => $r['severity'] === 'MEDIUM')),
            'low' => count(array_filter($reports, fn($r) => $r['severity'] === 'LOW'))
        ]
    ];
    
    echo json_encode([
        'status' => 'success',
        'heatmap_data' => $heatmapData,
        'stats' => $stats
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ], JSON_PRETTY_PRINT);
    error_log("PDO Error in get_heatmap_data.php: " . $e->getMessage());
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
    error_log("Error in get_heatmap_data.php: " . $e->getMessage());
}
?>

