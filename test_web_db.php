<!DOCTYPE html>
<html>
<head>
    <title>Database Connection Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .success { color: #28a745; font-weight: bold; }
        .error { color: #dc3545; font-weight: bold; }
        .info { background: #e7f3ff; padding: 10px; border-left: 4px solid #2196f3; margin: 10px 0; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 4px; overflow-x: auto; }
        h1 { color: #333; }
        h2 { color: #666; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Database Connection Test</h1>
        
        <h2>1. PHP Configuration</h2>
        <div class="info">
            <strong>PHP Version:</strong> <?php echo phpversion(); ?><br>
            <strong>PHP INI File:</strong> <?php echo php_ini_loaded_file(); ?><br>
            <strong>SAPI:</strong> <?php echo php_sapi_name(); ?><br>
            <strong>Server Software:</strong> <?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'N/A'; ?>
        </div>
        
        <h2>2. PDO Extensions</h2>
        <?php
        $pdo_loaded = extension_loaded('pdo');
        $pdo_mysql_loaded = extension_loaded('pdo_mysql');
        $mysqli_loaded = extension_loaded('mysqli');
        ?>
        <ul>
            <li><span class="<?php echo $pdo_loaded ? 'success' : 'error'; ?>">
                <?php echo $pdo_loaded ? '✅' : '❌'; ?> PDO Extension: <?php echo $pdo_loaded ? 'LOADED' : 'NOT LOADED'; ?>
            </span></li>
            <li><span class="<?php echo $pdo_mysql_loaded ? 'success' : 'error'; ?>">
                <?php echo $pdo_mysql_loaded ? '✅' : '❌'; ?> PDO MySQL Driver: <?php echo $pdo_mysql_loaded ? 'LOADED' : 'NOT LOADED'; ?>
            </span></li>
            <li><span class="<?php echo $mysqli_loaded ? 'success' : 'error'; ?>">
                <?php echo $mysqli_loaded ? '✅' : '❌'; ?> MySQLi Extension: <?php echo $mysqli_loaded ? 'LOADED' : 'NOT LOADED'; ?>
            </span></li>
        </ul>
        
        <?php if (class_exists('PDO')): ?>
        <div class="info">
            <strong>Available PDO Drivers:</strong> <?php echo implode(', ', PDO::getAvailableDrivers()); ?>
        </div>
        <?php endif; ?>
        
        <h2>3. MySQL Connection Test</h2>
        <?php
        $connection_success = false;
        $connection_error = null;
        
        try {
            // Test basic MySQL connection
            $test_pdo = new PDO("mysql:host=localhost", "root", "");
            $test_pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $connection_success = true;
            
            echo '<p class="success">✅ MySQL Connection: SUCCESS</p>';
            
            // Test database creation/existence
            $test_pdo->exec("CREATE DATABASE IF NOT EXISTS hackathondb CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            echo '<p class="success">✅ Database "hackathondb": EXISTS/CREATED</p>';
            
            // Connect to the database
            $db_pdo = new PDO("mysql:host=localhost;dbname=hackathondb", "root", "");
            $db_pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            echo '<p class="success">✅ Connected to "hackathondb" database</p>';
            
            // Check if table exists
            $stmt = $db_pdo->query("SHOW TABLES LIKE 'analysis_reports'");
            $table_exists = $stmt->rowCount() > 0;
            
            if ($table_exists) {
                echo '<p class="success">✅ Table "analysis_reports": EXISTS</p>';
                
                // Get record count
                $stmt = $db_pdo->query("SELECT COUNT(*) as count FROM analysis_reports");
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $record_count = $result['count'];
                
                echo '<p class="success">✅ Records in table: ' . $record_count . '</p>';
                
                if ($record_count > 0) {
                    // Get last record
                    $stmt = $db_pdo->query("SELECT id, timestamp, top_hazard, created_at FROM analysis_reports ORDER BY created_at DESC LIMIT 1");
                    $last_record = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    echo '<div class="info"><strong>Last Record:</strong><br>';
                    echo '<pre>' . print_r($last_record, true) . '</pre></div>';
                }
            } else {
                echo '<p class="error">⚠️ Table "analysis_reports": DOES NOT EXIST (will be created automatically)</p>';
            }
            
        } catch (PDOException $e) {
            $connection_error = $e->getMessage();
            echo '<p class="error">❌ MySQL Connection: FAILED</p>';
            echo '<div class="info"><strong>Error Details:</strong><br>';
            echo '<pre>' . htmlspecialchars($connection_error) . '</pre></div>';
            
            if (strpos($connection_error, 'could not find driver') !== false) {
                echo '<div class="error"><strong>Fix Instructions:</strong><br>';
                echo '1. Open php.ini file: ' . php_ini_loaded_file() . '<br>';
                echo '2. Find lines: ;extension=mysqli and ;extension=pdo_mysql<br>';
                echo '3. Remove semicolons to uncomment them<br>';
                echo '4. Restart Apache in XAMPP Control Panel<br>';
                echo '5. Refresh this page</div>';
            }
        }
        ?>
        
        <h2>4. Test db_config.php</h2>
        <?php
        $db_config_path = __DIR__ . '/db_config.php';
        if (file_exists($db_config_path)) {
            echo '<p class="success">✅ db_config.php: EXISTS</p>';
            
            try {
                require_once $db_config_path;
                
                if (function_exists('getDBConnection')) {
                    echo '<p class="success">✅ getDBConnection() function: AVAILABLE</p>';
                    
                    try {
                        $pdo = getDBConnection();
                        echo '<p class="success">✅ Database connection via db_config.php: SUCCESS</p>';
                        
                        // Test query
                        $stmt = $pdo->query("SELECT DATABASE() as db_name");
                        $result = $stmt->fetch(PDO::FETCH_ASSOC);
                        echo '<p class="success">✅ Current database: ' . $result['db_name'] . '</p>';
                        
                    } catch (Exception $e) {
                        echo '<p class="error">❌ Database connection via db_config.php: FAILED</p>';
                        echo '<div class="info"><pre>' . htmlspecialchars($e->getMessage()) . '</pre></div>';
                    }
                } else {
                    echo '<p class="error">❌ getDBConnection() function: NOT AVAILABLE</p>';
                }
                
            } catch (Exception $e) {
                echo '<p class="error">❌ Error loading db_config.php</p>';
                echo '<div class="info"><pre>' . htmlspecialchars($e->getMessage()) . '</pre></div>';
            }
        } else {
            echo '<p class="error">❌ db_config.php: NOT FOUND at ' . $db_config_path . '</p>';
        }
        ?>
        
        <h2>5. Summary</h2>
        <?php
        $all_ok = $pdo_loaded && $pdo_mysql_loaded && $connection_success;
        if ($all_ok) {
            echo '<div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 4px; border: 1px solid #c3e6cb;">';
            echo '<h3 style="margin-top: 0;">✅ ALL TESTS PASSED!</h3>';
            echo '<p>Your MySQL database configuration is working correctly. You can now use the database in your application.</p>';
            echo '</div>';
        } else {
            echo '<div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 4px; border: 1px solid #f5c6cb;">';
            echo '<h3 style="margin-top: 0;">❌ ISSUES DETECTED</h3>';
            echo '<p>Please review the errors above and follow the fix instructions.</p>';
            echo '</div>';
        }
        ?>
        
        <p style="margin-top: 20px; color: #666; font-size: 14px;">
            <strong>Note:</strong> This test page can be accessed via your web browser at 
            <code>http://localhost/DSHackathon2025/test_web_db.php</code>
        </p>
    </div>
</body>
</html>

