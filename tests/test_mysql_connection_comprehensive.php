<?php
// tests/test_mysql_connection_comprehensive.php - Comprehensive MySQL connection testing
echo "<!DOCTYPE html><html><head><title>MySQL Connection Test</title>";
echo "<style>
body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
.container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
h1 { color: #333; border-bottom: 3px solid #4CAF50; padding-bottom: 10px; }
h2 { color: #555; margin-top: 30px; }
.test-result { margin: 15px 0; padding: 15px; border-radius: 5px; }
.success { background: #d4edda; border-left: 4px solid #28a745; }
.error { background: #f8d7da; border-left: 4px solid #dc3545; }
.warning { background: #fff3cd; border-left: 4px solid #ffc107; }
.info { background: #d1ecf1; border-left: 4px solid #17a2b8; }
.status { font-weight: bold; }
.details { margin-top: 10px; font-size: 14px; color: #666; }
pre { background: #f4f4f4; padding: 10px; border-radius: 4px; overflow-x: auto; }
.summary { background: #e7f3ff; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #2196F3; }
</style></head><body><div class='container'>";

echo "<h1>🔍 MySQL Connection Comprehensive Test</h1>";
echo "<p>Testing database connectivity and configuration for UrbanPulse application.</p>";

$results = [
    'tests' => [],
    'overall_status' => 'success'
];

// Test 1: Check PDO MySQL Driver
echo "<h2>Test 1: PDO MySQL Driver</h2>";
try {
    if (class_exists('PDO')) {
        $drivers = PDO::getAvailableDrivers();
        if (in_array('mysql', $drivers)) {
            echo "<div class='test-result success'>";
            echo "<div class='status'>✅ PASS: PDO MySQL driver is available</div>";
            echo "<div class='details'>Available drivers: " . implode(', ', $drivers) . "</div>";
            echo "</div>";
            $results['tests']['pdo_driver'] = ['status' => 'success', 'message' => 'PDO MySQL driver available'];
        } else {
            echo "<div class='test-result error'>";
            echo "<div class='status'>❌ FAIL: PDO MySQL driver NOT found</div>";
            echo "<div class='details'>Available drivers: " . implode(', ', $drivers) . "</div>";
            echo "<div class='details'><strong>Fix:</strong> Enable extension=pdo_mysql in php.ini</div>";
            echo "</div>";
            $results['tests']['pdo_driver'] = ['status' => 'error', 'message' => 'PDO MySQL driver not found'];
            $results['overall_status'] = 'error';
        }
    } else {
        echo "<div class='test-result error'>";
        echo "<div class='status'>❌ FAIL: PDO class not available</div>";
        echo "</div>";
        $results['tests']['pdo_driver'] = ['status' => 'error', 'message' => 'PDO class not available'];
        $results['overall_status'] = 'error';
    }
} catch (Exception $e) {
    echo "<div class='test-result error'>";
    echo "<div class='status'>❌ ERROR: " . htmlspecialchars($e->getMessage()) . "</div>";
    echo "</div>";
    $results['tests']['pdo_driver'] = ['status' => 'error', 'message' => $e->getMessage()];
    $results['overall_status'] = 'error';
}

// Test 2: Port Availability
echo "<h2>Test 2: MySQL Port Availability</h2>";
$host = 'localhost';
$port = 3307;

$connection = @fsockopen($host, $port, $errno, $errstr, 5);
if ($connection) {
    echo "<div class='test-result success'>";
    echo "<div class='status'>✅ PASS: Port {$port} is open and accepting connections</div>";
    echo "<div class='details'>Host: {$host}, Port: {$port}</div>";
    echo "</div>";
    fclose($connection);
    $results['tests']['port_check'] = ['status' => 'success', 'message' => "Port {$port} is accessible"];
} else {
    echo "<div class='test-result error'>";
    echo "<div class='status'>❌ FAIL: Port {$port} is NOT accessible</div>";
    echo "<div class='details'>Error: {$errstr} (Code: {$errno})</div>";
    echo "<div class='details'><strong>Fix:</strong> Start MySQL from XAMPP Control Panel</div>";
    echo "</div>";
    $results['tests']['port_check'] = ['status' => 'error', 'message' => "Port {$port} not accessible: {$errstr}"];
    $results['overall_status'] = 'error';
}

// Test 3: MySQL Process Check (Windows)
echo "<h2>Test 3: MySQL Process Check</h2>";
$processes = @shell_exec('tasklist /FI "IMAGENAME eq mysqld.exe" 2>nul');
if ($processes && strpos($processes, 'mysqld.exe') !== false) {
    echo "<div class='test-result success'>";
    echo "<div class='status'>✅ PASS: MySQL process (mysqld.exe) is running</div>";
    echo "</div>";
    $results['tests']['process_check'] = ['status' => 'success', 'message' => 'MySQL process running'];
} else {
    echo "<div class='test-result warning'>";
    echo "<div class='status'>⚠️ WARNING: MySQL process (mysqld.exe) not detected</div>";
    echo "<div class='details'>This may be a false negative if running on non-Windows or as a service</div>";
    echo "</div>";
    $results['tests']['process_check'] = ['status' => 'warning', 'message' => 'MySQL process not detected (may be false negative)'];
}

// Test 4: MySQL Connection without Database
echo "<h2>Test 4: MySQL Server Connection</h2>";
try {
    $dsn = "mysql:host={$host};port={$port};charset=utf8mb4";
    $pdo = new PDO($dsn, 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 5
    ]);
    
    echo "<div class='test-result success'>";
    echo "<div class='status'>✅ PASS: Successfully connected to MySQL server</div>";
    
    // Get MySQL version
    $version = $pdo->query('SELECT VERSION()')->fetchColumn();
    echo "<div class='details'>MySQL Version: {$version}</div>";
    
    // Get server info
    $serverInfo = $pdo->getAttribute(PDO::ATTR_SERVER_INFO);
    echo "<div class='details'>Server Info: {$serverInfo}</div>";
    
    echo "</div>";
    $results['tests']['mysql_connection'] = ['status' => 'success', 'message' => 'Connected to MySQL server', 'version' => $version];
    
} catch (PDOException $e) {
    echo "<div class='test-result error'>";
    echo "<div class='status'>❌ FAIL: Cannot connect to MySQL server</div>";
    echo "<div class='details'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    echo "<div class='details'>Error Code: " . $e->getCode() . "</div>";
    echo "<div class='details'><strong>Fix:</strong> Ensure MySQL is running and credentials are correct</div>";
    echo "</div>";
    $results['tests']['mysql_connection'] = ['status' => 'error', 'message' => $e->getMessage()];
    $results['overall_status'] = 'error';
}

// Test 5: Database Existence
echo "<h2>Test 5: Database 'hackathondb' Verification</h2>";
if (isset($pdo)) {
    try {
        // Check if database exists
        $stmt = $pdo->query("SHOW DATABASES LIKE 'hackathondb'");
        $dbExists = $stmt->rowCount() > 0;
        
        if ($dbExists) {
            echo "<div class='test-result success'>";
            echo "<div class='status'>✅ PASS: Database 'hackathondb' exists</div>";
            echo "</div>";
            $results['tests']['database_exists'] = ['status' => 'success', 'message' => 'Database exists'];
        } else {
            echo "<div class='test-result warning'>";
            echo "<div class='status'>⚠️ WARNING: Database 'hackathondb' does not exist</div>";
            echo "<div class='details'>It will be created automatically on first connection</div>";
            echo "</div>";
            $results['tests']['database_exists'] = ['status' => 'warning', 'message' => 'Database does not exist (will be auto-created)'];
        }
        
        // List all databases
        $stmt = $pdo->query("SHOW DATABASES");
        $databases = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo "<div class='info test-result'>";
        echo "<div class='details'><strong>Available databases:</strong> " . implode(', ', $databases) . "</div>";
        echo "</div>";
        
    } catch (PDOException $e) {
        echo "<div class='test-result error'>";
        echo "<div class='status'>❌ ERROR: " . htmlspecialchars($e->getMessage()) . "</div>";
        echo "</div>";
        $results['tests']['database_exists'] = ['status' => 'error', 'message' => $e->getMessage()];
        $results['overall_status'] = 'error';
    }
} else {
    echo "<div class='test-result error'>";
    echo "<div class='status'>❌ SKIP: Cannot test database (no connection)</div>";
    echo "</div>";
    $results['tests']['database_exists'] = ['status' => 'error', 'message' => 'No connection available'];
    $results['overall_status'] = 'error';
}

// Test 6: Database Connection with Database Selected
echo "<h2>Test 6: Connect to 'hackathondb' Database</h2>";
try {
    $dsn_db = "mysql:host={$host};port={$port};dbname=hackathondb;charset=utf8mb4";
    $pdo_db = new PDO($dsn_db, 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_TIMEOUT => 5
    ]);
    
    echo "<div class='test-result success'>";
    echo "<div class='status'>✅ PASS: Successfully connected to 'hackathondb' database</div>";
    
    // Get database collation
    $stmt = $pdo_db->query("SELECT @@character_set_database, @@collation_database");
    $charset = $stmt->fetch();
    echo "<div class='details'>Character Set: {$charset['@@character_set_database']}</div>";
    echo "<div class='details'>Collation: {$charset['@@collation_database']}</div>";
    
    echo "</div>";
    $results['tests']['database_connection'] = ['status' => 'success', 'message' => 'Connected to hackathondb'];
    
} catch (PDOException $e) {
    if ($e->getCode() == 1049) { // Database doesn't exist
        echo "<div class='test-result warning'>";
        echo "<div class='status'>⚠️ WARNING: Database 'hackathondb' doesn't exist yet</div>";
        echo "<div class='details'>This is normal for first-time setup. It will be created automatically.</div>";
        echo "</div>";
        $results['tests']['database_connection'] = ['status' => 'warning', 'message' => 'Database will be auto-created'];
    } else {
        echo "<div class='test-result error'>";
        echo "<div class='status'>❌ FAIL: Cannot connect to database</div>";
        echo "<div class='details'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
        echo "</div>";
        $results['tests']['database_connection'] = ['status' => 'error', 'message' => $e->getMessage()];
        $results['overall_status'] = 'error';
    }
}

// Test 7: Test db_config.php Connection Function
echo "<h2>Test 7: Test db_config.php getDBConnection()</h2>";
try {
    require_once __DIR__ . '/../db_config.php';
    
    $test_pdo = getDBConnection();
    
    echo "<div class='test-result success'>";
    echo "<div class='status'>✅ PASS: db_config.php getDBConnection() works correctly</div>";
    echo "<div class='details'>Connection established using application's DB configuration</div>";
    echo "</div>";
    $results['tests']['db_config_connection'] = ['status' => 'success', 'message' => 'db_config.php works'];
    
} catch (Exception $e) {
    echo "<div class='test-result error'>";
    echo "<div class='status'>❌ FAIL: db_config.php connection failed</div>";
    echo "<div class='details'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    echo "</div>";
    $results['tests']['db_config_connection'] = ['status' => 'error', 'message' => $e->getMessage()];
    $results['overall_status'] = 'error';
}

// Summary
echo "<h2>📊 Test Summary</h2>";
echo "<div class='summary'>";
$passed = 0;
$failed = 0;
$warnings = 0;

foreach ($results['tests'] as $test => $result) {
    if ($result['status'] === 'success') $passed++;
    elseif ($result['status'] === 'error') $failed++;
    elseif ($result['status'] === 'warning') $warnings++;
}

echo "<div><strong>Total Tests:</strong> " . count($results['tests']) . "</div>";
echo "<div style='color: #28a745;'><strong>Passed:</strong> {$passed}</div>";
echo "<div style='color: #dc3545;'><strong>Failed:</strong> {$failed}</div>";
echo "<div style='color: #ffc107;'><strong>Warnings:</strong> {$warnings}</div>";
echo "<div style='margin-top: 10px;'><strong>Overall Status:</strong> ";

if ($results['overall_status'] === 'success') {
    echo "<span style='color: #28a745; font-weight: bold;'>✅ ALL SYSTEMS OPERATIONAL</span>";
} else {
    echo "<span style='color: #dc3545; font-weight: bold;'>❌ ISSUES DETECTED - REVIEW FAILURES ABOVE</span>";
}

echo "</div>";
echo "</div>";

// Action Items
if ($failed > 0) {
    echo "<h2>🔧 Action Items</h2>";
    echo "<div class='test-result warning'>";
    echo "<ol>";
    if (isset($results['tests']['pdo_driver']) && $results['tests']['pdo_driver']['status'] === 'error') {
        echo "<li>Enable <code>extension=pdo_mysql</code> in php.ini and restart Apache</li>";
    }
    if (isset($results['tests']['port_check']) && $results['tests']['port_check']['status'] === 'error') {
        echo "<li>Start MySQL service from XAMPP Control Panel</li>";
    }
    if (isset($results['tests']['mysql_connection']) && $results['tests']['mysql_connection']['status'] === 'error') {
        echo "<li>Verify MySQL username and password are correct (default: root with empty password)</li>";
    }
    echo "</ol>";
    echo "</div>";
}

// JSON Results (for programmatic access)
echo "<h2>📄 JSON Results</h2>";
echo "<pre>" . json_encode($results, JSON_PRETTY_PRINT) . "</pre>";

echo "</div></body></html>";
?>

