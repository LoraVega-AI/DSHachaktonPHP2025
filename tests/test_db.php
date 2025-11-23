<?php
// test_db.php - Test database connection and table structure

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Database Connection Test</h2>";

try {
    require_once __DIR__ . '/db_config.php';
    echo "✅ db_config.php loaded successfully<br>";
    
    // Test connection
    $pdo = getDBConnection();
    echo "✅ Database connection successful<br>";
    
    // Check if database exists
    $stmt = $pdo->query("SELECT DATABASE()");
    $db = $stmt->fetchColumn();
    echo "✅ Connected to database: " . $db . "<br><br>";
    
    // Check if table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'analysis_reports'");
    $tableExists = $stmt->fetch();
    
    if ($tableExists) {
        echo "✅ Table 'analysis_reports' exists<br><br>";
        
        // Show table structure
        echo "<h3>Table Structure:</h3>";
        $stmt = $pdo->query("DESCRIBE analysis_reports");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
        foreach ($columns as $col) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($col['Field']) . "</td>";
            echo "<td>" . htmlspecialchars($col['Type']) . "</td>";
            echo "<td>" . htmlspecialchars($col['Null']) . "</td>";
            echo "<td>" . htmlspecialchars($col['Key']) . "</td>";
            echo "<td>" . htmlspecialchars($col['Default'] ?? 'NULL') . "</td>";
            echo "</tr>";
        }
        echo "</table><br>";
        
        // Count existing records
        $stmt = $pdo->query("SELECT COUNT(*) FROM analysis_reports");
        $count = $stmt->fetchColumn();
        echo "✅ Total records in table: " . $count . "<br><br>";
        
        // Show recent records
        if ($count > 0) {
            echo "<h3>Recent Records (last 5):</h3>";
            $stmt = $pdo->query("SELECT id, timestamp, top_hazard, signature_name, confidence_score, created_at FROM analysis_reports ORDER BY created_at DESC LIMIT 5");
            $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo "<table border='1' cellpadding='5'>";
            echo "<tr><th>ID</th><th>Timestamp</th><th>Top Hazard</th><th>Signature</th><th>Confidence</th><th>Created At</th></tr>";
            foreach ($records as $record) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($record['id']) . "</td>";
                echo "<td>" . htmlspecialchars($record['timestamp']) . "</td>";
                echo "<td>" . htmlspecialchars($record['top_hazard']) . "</td>";
                echo "<td>" . htmlspecialchars($record['signature_name'] ?? 'N/A') . "</td>";
                echo "<td>" . htmlspecialchars($record['confidence_score']) . "</td>";
                echo "<td>" . htmlspecialchars($record['created_at']) . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
        
        // Test insert
        echo "<br><h3>Test Insert:</h3>";
        try {
            $testSql = "INSERT INTO analysis_reports (
                timestamp, top_hazard, confidence_score, rms_level, spectral_centroid, frequency,
                signature_name, classification, executive_conclusion, severity, is_problem, verdict,
                risk_description, action_steps, who_to_contact, full_report_data
            ) VALUES (
                NOW(), :top_hazard, :confidence_score, :rms_level, :spectral_centroid, :frequency,
                :signature_name, :classification, :executive_conclusion, :severity, :is_problem, :verdict,
                :risk_description, :action_steps, :who_to_contact, :full_report_data
            )";
            
            $testStmt = $pdo->prepare($testSql);
            $testStmt->execute([
                ':top_hazard' => 'Test Hazard',
                ':confidence_score' => 0.85,
                ':rms_level' => 0.1,
                ':spectral_centroid' => null,
                ':frequency' => null,
                ':signature_name' => 'Test Signature',
                ':classification' => 'Test Classification',
                ':executive_conclusion' => 'Test Conclusion',
                ':severity' => 'LOW',
                ':is_problem' => 'NO',
                ':verdict' => 'SAFE',
                ':risk_description' => 'Test risk description',
                ':action_steps' => 'Test action steps',
                ':who_to_contact' => 'Test contact',
                ':full_report_data' => json_encode(['test' => 'data'])
            ]);
            
            $testId = $pdo->lastInsertId();
            echo "✅ Test insert successful! ID: " . $testId . "<br>";
            
            // Delete test record
            $pdo->exec("DELETE FROM analysis_reports WHERE id = " . $testId);
            echo "✅ Test record deleted<br>";
            
        } catch (Exception $e) {
            echo "❌ Test insert failed: " . $e->getMessage() . "<br>";
            echo "Error details: " . print_r($e->getTraceAsString(), true) . "<br>";
        }
        
    } else {
        echo "❌ Table 'analysis_reports' does NOT exist!<br>";
        echo "Attempting to create table...<br>";
        initializeDatabase();
        echo "✅ Table creation attempted. Please refresh this page.<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
    echo "Stack trace: " . $e->getTraceAsString() . "<br>";
}

echo "<br><hr>";
echo "<h3>PHP Error Log (last 20 lines):</h3>";
$errorLog = ini_get('error_log');
if ($errorLog && file_exists($errorLog)) {
    $lines = file($errorLog);
    $lastLines = array_slice($lines, -20);
    echo "<pre>" . htmlspecialchars(implode('', $lastLines)) . "</pre>";
} else {
    echo "Error log location: " . ($errorLog ?: 'Not set') . "<br>";
    echo "Check XAMPP error logs or PHP error logs<br>";
}
?>

