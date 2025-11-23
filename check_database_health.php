<?php
// check_database_health.php - Comprehensive database health check endpoint

echo "<!DOCTYPE html><html><head><title>Database Health Check</title>";
echo "<style>
body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
.container { max-width: 1000px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
h1 { color: #333; border-bottom: 3px solid #4CAF50; padding-bottom: 10px; }
h2 { color: #555; margin-top: 30px; }
.health-status { padding: 20px; border-radius: 8px; margin: 20px 0; text-align: center; font-size: 24px; font-weight: bold; }
.healthy { background: #d4edda; color: #155724; border: 2px solid #28a745; }
.degraded { background: #fff3cd; color: #856404; border: 2px solid #ffc107; }
.unhealthy { background: #f8d7da; color: #721c24; border: 2px solid #dc3545; }
.metric { margin: 15px 0; padding: 15px; border-radius: 5px; background: #f8f9fa; }
.metric-label { font-weight: bold; color: #555; }
.metric-value { font-size: 18px; color: #333; margin-top: 5px; }
.check-item { padding: 10px; margin: 8px 0; border-radius: 4px; }
.check-pass { background: #d4edda; border-left: 4px solid #28a745; }
.check-warning { background: #fff3cd; border-left: 4px solid #ffc107; }
.check-fail { background: #f8d7da; border-left: 4px solid #dc3545; }
table { width: 100%; border-collapse: collapse; margin: 15px 0; }
th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
th { background: #f8f9fa; font-weight: bold; }
.timestamp { text-align: center; color: #6c757d; margin-top: 20px; font-size: 14px; }
.refresh-btn { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; }
.refresh-btn:hover { background: #0056b3; }
</style></head><body><div class='container'>";

echo "<h1>🏥 Database Health Check</h1>";
echo "<p>Real-time monitoring of database status, tables, and connectivity.</p>";

$health = [
    'status' => 'HEALTHY',
    'checks' => [],
    'metrics' => [],
    'issues' => [],
    'timestamp' => date('Y-m-d H:i:s')
];

// ===================================
// CONNECTION CHECK
// ===================================
echo "<h2>🔌 Connection Status</h2>";

try {
    require_once __DIR__ . '/db_config.php';
    $pdo = getDBConnection();
    
    echo "<div class='check-item check-pass'>";
    echo "<strong>✅ Database Connection:</strong> Connected successfully";
    echo "</div>";
    $health['checks']['connection'] = 'pass';
    
    // Get MySQL version
    $version = $pdo->query('SELECT VERSION()')->fetchColumn();
    echo "<div class='metric'>";
    echo "<div class='metric-label'>MySQL Version</div>";
    echo "<div class='metric-value'>{$version}</div>";
    echo "</div>";
    $health['metrics']['mysql_version'] = $version;
    
} catch (Exception $e) {
    echo "<div class='check-item check-fail'>";
    echo "<strong>❌ Database Connection:</strong> FAILED - " . htmlspecialchars($e->getMessage());
    echo "</div>";
    $health['checks']['connection'] = 'fail';
    $health['status'] = 'UNHEALTHY';
    $health['issues'][] = 'Database connection failed';
}

// ===================================
// TABLE EXISTENCE CHECKS
// ===================================
echo "<h2>📋 Table Status</h2>";

$requiredTables = ['users', 'analysis_reports', 'general_reports'];
$tableStatus = [];

foreach ($requiredTables as $table) {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE '{$table}'");
        $exists = $stmt->rowCount() > 0;
        
        if ($exists) {
            // Get row count
            $countStmt = $pdo->query("SELECT COUNT(*) FROM {$table}");
            $rowCount = $countStmt->fetchColumn();
            
            echo "<div class='check-item check-pass'>";
            echo "<strong>✅ {$table}:</strong> Table exists ({$rowCount} rows)";
            echo "</div>";
            
            $tableStatus[$table] = ['exists' => true, 'rows' => $rowCount];
            $health['checks']['table_' . $table] = 'pass';
            $health['metrics'][$table . '_rows'] = $rowCount;
        } else {
            echo "<div class='check-item check-warning'>";
            echo "<strong>⚠️ {$table}:</strong> Table does not exist (will be auto-created)";
            echo "</div>";
            
            $tableStatus[$table] = ['exists' => false, 'rows' => 0];
            $health['checks']['table_' . $table] = 'warning';
            if ($health['status'] === 'HEALTHY') $health['status'] = 'DEGRADED';
            $health['issues'][] = "Table '{$table}' is missing";
        }
    } catch (PDOException $e) {
        echo "<div class='check-item check-fail'>";
        echo "<strong>❌ {$table}:</strong> Error checking table - " . htmlspecialchars($e->getMessage());
        echo "</div>";
        
        $health['checks']['table_' . $table] = 'fail';
        $health['status'] = 'UNHEALTHY';
        $health['issues'][] = "Error checking table '{$table}'";
    }
}

// ===================================
// COLUMN CHECKS
// ===================================
echo "<h2>📊 Critical Columns Check</h2>";

$criticalColumns = [
    'users' => ['profile_img', 'bio', 'trust_score'],
    'analysis_reports' => ['status', 'user_id', 'is_anonymous', 'verification_photo'],
    'general_reports' => ['status', 'user_id', 'is_anonymous', 'verification_photo']
];

foreach ($criticalColumns as $table => $columns) {
    if (!$tableStatus[$table]['exists']) {
        continue;
    }
    
    echo "<h3>{$table}</h3>";
    
    foreach ($columns as $column) {
        try {
            $stmt = $pdo->query("SHOW COLUMNS FROM {$table} LIKE '{$column}'");
            $exists = $stmt->rowCount() > 0;
            
            if ($exists) {
                echo "<div class='check-item check-pass'>";
                echo "<strong>✅ {$column}:</strong> Column exists";
                echo "</div>";
                $health['checks']['column_' . $table . '_' . $column] = 'pass';
            } else {
                echo "<div class='check-item check-warning'>";
                echo "<strong>⚠️ {$column}:</strong> Column missing (will be auto-added)";
                echo "</div>";
                $health['checks']['column_' . $table . '_' . $column] = 'warning';
                if ($health['status'] === 'HEALTHY') $health['status'] = 'DEGRADED';
                $health['issues'][] = "Column '{$table}.{$column}' is missing";
            }
        } catch (PDOException $e) {
            echo "<div class='check-item check-fail'>";
            echo "<strong>❌ {$column}:</strong> Error - " . htmlspecialchars($e->getMessage());
            echo "</div>";
            $health['checks']['column_' . $table . '_' . $column] = 'fail';
        }
    }
}

// ===================================
// PERFORMANCE METRICS
// ===================================
echo "<h2>⚡ Performance Metrics</h2>";

try {
    // Query performance test
    $start = microtime(true);
    $pdo->query("SELECT 1");
    $queryTime = round((microtime(true) - $start) * 1000, 2);
    
    echo "<div class='metric'>";
    echo "<div class='metric-label'>Query Response Time</div>";
    echo "<div class='metric-value'>{$queryTime} ms</div>";
    echo "</div>";
    $health['metrics']['query_response_time_ms'] = $queryTime;
    
    if ($queryTime > 100) {
        $health['issues'][] = "Slow query response time: {$queryTime}ms";
        if ($health['status'] === 'HEALTHY') $health['status'] = 'DEGRADED';
    }
    
    // Database size
    $sizeStmt = $pdo->query("
        SELECT 
            ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as size_mb
        FROM information_schema.TABLES 
        WHERE table_schema = 'hackathondb'
    ");
    $sizeMB = $sizeStmt->fetchColumn();
    
    echo "<div class='metric'>";
    echo "<div class='metric-label'>Database Size</div>";
    echo "<div class='metric-value'>{$sizeMB} MB</div>";
    echo "</div>";
    $health['metrics']['database_size_mb'] = $sizeMB;
    
} catch (PDOException $e) {
    echo "<div class='check-item check-warning'>";
    echo "<strong>⚠️ Performance Metrics:</strong> Could not retrieve some metrics";
    echo "</div>";
}

// ===================================
// RECENT ACTIVITY
// ===================================
echo "<h2>📈 Recent Activity</h2>";

try {
    // Reports in last 24 hours
    $recentAnalysis = 0;
    $recentGeneral = 0;
    
    if ($tableStatus['analysis_reports']['exists']) {
        $stmt = $pdo->query("SELECT COUNT(*) FROM analysis_reports WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
        $recentAnalysis = $stmt->fetchColumn();
    }
    
    if ($tableStatus['general_reports']['exists']) {
        $stmt = $pdo->query("SELECT COUNT(*) FROM general_reports WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
        $recentGeneral = $stmt->fetchColumn();
    }
    
    $totalRecent = $recentAnalysis + $recentGeneral;
    
    echo "<div class='metric'>";
    echo "<div class='metric-label'>Reports in Last 24 Hours</div>";
    echo "<div class='metric-value'>{$totalRecent} ({$recentAnalysis} analysis, {$recentGeneral} general)</div>";
    echo "</div>";
    $health['metrics']['reports_last_24h'] = $totalRecent;
    
} catch (PDOException $e) {
    echo "<div class='check-item check-warning'>";
    echo "<strong>⚠️ Recent Activity:</strong> Could not retrieve activity data";
    echo "</div>";
}

// ===================================
// OVERALL HEALTH STATUS
// ===================================
echo "<h2>🏥 Overall Health Status</h2>";

$statusClass = $health['status'] === 'HEALTHY' ? 'healthy' : 
               ($health['status'] === 'DEGRADED' ? 'degraded' : 'unhealthy');

$statusIcon = $health['status'] === 'HEALTHY' ? '✅' : 
              ($health['status'] === 'DEGRADED' ? '⚠️' : '❌');

echo "<div class='health-status {$statusClass}'>";
echo "{$statusIcon} {$health['status']}";
echo "</div>";

if (!empty($health['issues'])) {
    echo "<div class='check-item check-warning'>";
    echo "<strong>Issues Found:</strong>";
    echo "<ul>";
    foreach ($health['issues'] as $issue) {
        echo "<li>" . htmlspecialchars($issue) . "</li>";
    }
    echo "</ul>";
    echo "</div>";
}

// ===================================
// RECOMMENDATIONS
// ===================================
if ($health['status'] !== 'HEALTHY') {
    echo "<h2>💡 Recommendations</h2>";
    echo "<div class='check-item check-warning'>";
    echo "<ul>";
    
    if (isset($health['checks']['connection']) && $health['checks']['connection'] === 'fail') {
        echo "<li>Start MySQL service from XAMPP Control Panel</li>";
        echo "<li>Verify MySQL is running on port 3307</li>";
        echo "<li>Check database credentials in db_config.php</li>";
    }
    
    foreach ($requiredTables as $table) {
        if (isset($tableStatus[$table]) && !$tableStatus[$table]['exists']) {
            echo "<li>Table '{$table}' will be auto-created on next request to relevant endpoint</li>";
        }
    }
    
    if ($health['status'] === 'DEGRADED') {
        echo "<li>Some features may not work until missing columns/tables are added</li>";
        echo "<li>Refresh the page or trigger initialization scripts</li>";
    }
    
    echo "</ul>";
    echo "</div>";
}

// ===================================
// QUICK ACTIONS
// ===================================
echo "<h2>🔧 Quick Actions</h2>";
echo "<div class='metric'>";
echo "<button class='refresh-btn' onclick='location.reload()'>🔄 Refresh Health Check</button> ";
echo "<a href='tests/validate_database_schema.php' class='refresh-btn' style='text-decoration: none; display: inline-block; margin-left: 10px;'>📋 Validate Schema</a> ";
echo "<a href='tests/test_all_database_features.php' class='refresh-btn' style='text-decoration: none; display: inline-block; margin-left: 10px;'>🧪 Run Tests</a>";
echo "</div>";

// ===================================
// JSON EXPORT
// ===================================
echo "<h2>📄 JSON Health Report</h2>";
echo "<details><summary>Click to expand</summary>";
echo "<pre>" . json_encode($health, JSON_PRETTY_PRINT) . "</pre>";
echo "</details>";

echo "<div class='timestamp'>Last checked: {$health['timestamp']}</div>";

echo "</div></body></html>";

// Also provide JSON API endpoint
if (isset($_GET['format']) && $_GET['format'] === 'json') {
    header('Content-Type: application/json');
    echo json_encode($health, JSON_PRETTY_PRINT);
    exit;
}
?>

