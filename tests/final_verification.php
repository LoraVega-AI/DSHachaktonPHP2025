<?php
echo "\n=== FINAL VERIFICATION ===\n\n";

// Check extensions
echo "1. PHP Extensions:\n";
echo "   PDO: " . (extension_loaded('pdo') ? '✅ ENABLED' : '❌ DISABLED') . "\n";
echo "   PDO MySQL: " . (extension_loaded('pdo_mysql') ? '✅ ENABLED' : '❌ DISABLED') . "\n";
echo "   MySQLi: " . (extension_loaded('mysqli') ? '✅ ENABLED' : '❌ DISABLED') . "\n";
echo "\n";

// Check connection
echo "2. MySQL Connection:\n";
try {
    $pdo = new PDO('mysql:host=localhost', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "   Connection: ✅ SUCCESS\n";
    
    // Create database
    $pdo->exec('CREATE DATABASE IF NOT EXISTS hackathondb');
    echo "   Database hackathondb: ✅ EXISTS\n";
    
    // Connect to database
    $pdo = new PDO('mysql:host=localhost;dbname=hackathondb', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Check table
    $stmt = $pdo->query("SHOW TABLES LIKE 'analysis_reports'");
    if ($stmt->rowCount() > 0) {
        echo "   Table analysis_reports: ✅ EXISTS\n";
        
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM analysis_reports");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "   Records in table: " . $result['count'] . "\n";
    } else {
        echo "   Table analysis_reports: ⚠️ DOES NOT EXIST (will be auto-created)\n";
    }
    
} catch (PDOException $e) {
    echo "   Connection: ❌ FAILED\n";
    echo "   Error: " . $e->getMessage() . "\n";
}

echo "\n3. Configuration:\n";
echo "   PHP Version: " . phpversion() . "\n";
echo "   PHP INI: " . php_ini_loaded_file() . "\n";
echo "   Available PDO Drivers: " . implode(', ', PDO::getAvailableDrivers()) . "\n";

echo "\n=== SUMMARY ===\n";
if (extension_loaded('pdo_mysql')) {
    echo "🎉 ALL SYSTEMS GO! Database is fully operational.\n";
    echo "\nYou can now:\n";
    echo "  - Run your PHP application\n";
    echo "  - Access the database via CLI or web server\n";
    echo "  - Test via web: http://localhost/DSHackathon2025/test_web_db.php\n";
} else {
    echo "❌ PDO MySQL is not enabled. Please check php.ini configuration.\n";
}
echo "\n";
?>

