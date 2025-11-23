<?php
// Quick MySQL status checker
echo "<h2>MySQL Connection Status Check</h2>";

$host = 'localhost';
$user = 'root';
$pass = '';
$port = 3307;

// Check 1: Port availability
echo "<h3>1. Port Check</h3>";
$connection = @fsockopen($host, $port, $errno, $errstr, 2);
if ($connection) {
    echo "✅ Port $port is open and accepting connections<br>";
    fclose($connection);
} else {
    echo "❌ Port $port is NOT accessible: $errstr ($errno)<br>";
    echo "<strong>MySQL service is not running!</strong><br>";
}

// Check 2: MySQL connection
echo "<h3>2. MySQL Connection Test</h3>";
try {
    $dsn = "mysql:host=$host;port=$port;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 2
    ]);
    echo "✅ Successfully connected to MySQL server!<br>";
    
    // Get MySQL version
    $version = $pdo->query('SELECT VERSION()')->fetchColumn();
    echo "MySQL Version: $version<br>";
    
} catch (PDOException $e) {
    echo "❌ Connection failed: " . $e->getMessage() . "<br>";
    echo "<strong>Action Required:</strong> Start MySQL from XAMPP Control Panel<br>";
}

// Check 3: XAMPP MySQL process
echo "<h3>3. Process Check</h3>";
$processes = shell_exec('tasklist /FI "IMAGENAME eq mysqld.exe" 2>nul');
if (strpos($processes, 'mysqld.exe') !== false) {
    echo "✅ MySQL process (mysqld.exe) is running<br>";
} else {
    echo "❌ MySQL process (mysqld.exe) is NOT running<br>";
}

echo "<hr>";
echo "<h3>How to Fix:</h3>";
echo "<ol>";
echo "<li>Open <strong>XAMPP Control Panel</strong></li>";
echo "<li>Find <strong>MySQL</strong> in the services list</li>";
echo "<li>Click the <strong>Start</strong> button next to MySQL</li>";
echo "<li>Wait until status shows 'Running' (green)</li>";
echo "<li>Refresh this page to verify</li>";
echo "</ol>";
?>

