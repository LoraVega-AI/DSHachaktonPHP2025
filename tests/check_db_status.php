<?php
// check_db_status.php - Quick database status check
header("Content-Type: application/json");

try {
    require_once __DIR__ . '/../db_config.php';
    
    $status = [
        'db_config_loaded' => function_exists('getDBConnection'),
        'connection' => false,
        'database_exists' => false,
        'table_exists' => false,
        'record_count' => 0,
        'last_record' => null,
        'error' => null
    ];
    
    if ($status['db_config_loaded']) {
        try {
            $pdo = getDBConnection();
            $status['connection'] = true;
            
            // Check database
            $stmt = $pdo->query("SELECT DATABASE()");
            $db = $stmt->fetchColumn();
            $status['database_exists'] = ($db === 'hackathondb');
            
            // Check table
            $stmt = $pdo->query("SHOW TABLES LIKE 'analysis_reports'");
            $status['table_exists'] = ($stmt->fetch() !== false);
            
            if ($status['table_exists']) {
                // Count records
                $stmt = $pdo->query("SELECT COUNT(*) FROM analysis_reports");
                $status['record_count'] = intval($stmt->fetchColumn());
                
                // Get last record
                if ($status['record_count'] > 0) {
                    $stmt = $pdo->query("SELECT id, timestamp, top_hazard, created_at FROM analysis_reports ORDER BY created_at DESC LIMIT 1");
                    $status['last_record'] = $stmt->fetch(PDO::FETCH_ASSOC);
                }
            }
        } catch (Exception $e) {
            $status['error'] = $e->getMessage();
        }
    } else {
        $status['error'] = 'getDBConnection function not available';
    }
    
    echo json_encode($status, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage(),
        'db_config_loaded' => false
    ], JSON_PRETTY_PRINT);
}
?>

