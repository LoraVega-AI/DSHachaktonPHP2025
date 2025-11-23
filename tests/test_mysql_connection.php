<?php
// Test MySQL connection directly
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>MySQL Connection Test</h2>";

$host = 'localhost';
$dbname = 'hackathondb';
$user = 'root';
$pass = '';

try {
    // Test 1: Connect without database
    echo "<h3>Test 1: Connect to MySQL server</h3>";
    $dsn = "mysql:host=$host;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ Connected to MySQL server<br>";
    
    // Test 2: Create database
    echo "<h3>Test 2: Create database</h3>";
    $pdo->exec("CREATE DATABASE IF NOT EXISTS $dbname CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "✅ Database '$dbname' created/verified<br>";
    
    // Test 3: Connect to database
    echo "<h3>Test 3: Connect to database</h3>";
    $dsn_db = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
    $pdo_db = new PDO($dsn_db, $user, $pass);
    $pdo_db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ Connected to database '$dbname'<br>";
    
    // Test 4: Create table
    echo "<h3>Test 4: Create table</h3>";
    $sql = "CREATE TABLE IF NOT EXISTS analysis_reports (
        id INT AUTO_INCREMENT PRIMARY KEY,
        timestamp DATETIME NOT NULL,
        top_hazard VARCHAR(255) NOT NULL,
        confidence_score DECIMAL(5,3) NOT NULL,
        rms_level DECIMAL(10,6) NOT NULL,
        spectral_centroid DECIMAL(10,2) DEFAULT NULL,
        frequency VARCHAR(50) DEFAULT NULL,
        signature_name TEXT,
        classification TEXT,
        executive_conclusion TEXT,
        severity VARCHAR(20) DEFAULT NULL,
        is_problem VARCHAR(10) DEFAULT NULL,
        verdict VARCHAR(20) DEFAULT NULL,
        risk_description TEXT,
        action_steps TEXT,
        who_to_contact TEXT,
        full_report_data JSON,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_timestamp (timestamp),
        INDEX idx_severity (severity),
        INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo_db->exec($sql);
    echo "✅ Table 'analysis_reports' created/verified<br>";
    
    // Test 5: Query table
    echo "<h3>Test 5: Query table</h3>";
    $stmt = $pdo_db->query("SELECT COUNT(*) as total FROM analysis_reports");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "✅ Table query successful. Total records: " . $result['total'] . "<br>";
    
    echo "<h3>✅ All tests passed! MySQL is working correctly.</h3>";
    
} catch (PDOException $e) {
    echo "<h3>❌ PDO Error:</h3>";
    echo "Message: " . $e->getMessage() . "<br>";
    echo "Code: " . $e->getCode() . "<br>";
    echo "SQL State: " . ($e->errorInfo[0] ?? 'N/A') . "<br>";
} catch (Exception $e) {
    echo "<h3>❌ General Error:</h3>";
    echo "Message: " . $e->getMessage() . "<br>";
}
?>

