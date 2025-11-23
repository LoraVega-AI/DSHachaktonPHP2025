<?php
// Diagnostic and fix guide for PDO MySQL driver
header("Content-Type: text/html; charset=UTF-8");
?>
<!DOCTYPE html>
<html>
<head>
    <title>PDO MySQL Driver Diagnostic</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
        .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .info { background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .code { background: #f4f4f4; padding: 10px; border-left: 3px solid #007bff; margin: 10px 0; font-family: monospace; }
        h1 { color: #333; }
        h2 { color: #666; margin-top: 20px; }
        ul { line-height: 1.8; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 PDO MySQL Driver Diagnostic</h1>
        
        <?php
        $pdo_loaded = extension_loaded('pdo');
        $pdo_mysql_loaded = extension_loaded('pdo_mysql');
        $php_ini_loaded = php_ini_loaded_file();
        $php_ini_scanned = php_ini_scanned_files();
        $available_drivers = PDO::getAvailableDrivers();
        
        if ($pdo_mysql_loaded) {
            echo '<div class="success">';
            echo '<h2>✅ PDO MySQL Driver is LOADED!</h2>';
            echo '<p>The driver is working correctly. If you\'re still getting errors, try:</p>';
            echo '<ul>';
            echo '<li>Clear your browser cache</li>';
            echo '<li>Restart Apache in XAMPP Control Panel</li>';
            echo '<li>Check browser console for other errors</li>';
            echo '</ul>';
            echo '</div>';
        } else {
            echo '<div class="error">';
            echo '<h2>❌ PDO MySQL Driver is NOT LOADED</h2>';
            echo '<p>You need to enable the extension in php.ini</p>';
            echo '</div>';
        }
        ?>
        
        <h2>Current Status</h2>
        <div class="info">
            <strong>PDO Extension:</strong> <?php echo $pdo_loaded ? '✅ Loaded' : '❌ Not Loaded'; ?><br>
            <strong>PDO MySQL Driver:</strong> <?php echo $pdo_mysql_loaded ? '✅ Loaded' : '❌ Not Loaded'; ?><br>
            <strong>PHP Version:</strong> <?php echo phpversion(); ?><br>
            <strong>PHP.ini File:</strong> <?php echo $php_ini_loaded ?: 'Not found'; ?><br>
            <strong>Available PDO Drivers:</strong> <?php echo implode(', ', $available_drivers); ?>
        </div>
        
        <?php if (!$pdo_mysql_loaded): ?>
        <h2>🔧 How to Fix</h2>
        <div class="info">
            <h3>Step 1: Find php.ini</h3>
            <p>The php.ini file being used is:</p>
            <div class="code"><?php echo $php_ini_loaded ?: 'Could not determine php.ini location'; ?></div>
            
            <h3>Step 2: Edit php.ini</h3>
            <p>Open the php.ini file above in a text editor (like Notepad++) and find these lines:</p>
            <div class="code">
;extension=pdo_mysql<br>
;extension=mysqli
            </div>
            <p><strong>Remove the semicolon (;) at the beginning</strong> to make them:</p>
            <div class="code">
extension=pdo_mysql<br>
extension=mysqli
            </div>
            
            <h3>Step 3: Save and Restart</h3>
            <ul>
                <li>Save the php.ini file</li>
                <li>Open XAMPP Control Panel</li>
                <li>Click <strong>Stop</strong> for Apache</li>
                <li>Wait 2 seconds</li>
                <li>Click <strong>Start</strong> for Apache</li>
                <li>Refresh this page to verify it's working</li>
            </ul>
            
            <h3>Step 4: Alternative Method (if above doesn't work)</h3>
            <p>If you can't find the extension line, add these lines at the end of php.ini:</p>
            <div class="code">
extension=pdo_mysql<br>
extension=mysqli
            </div>
        </div>
        <?php endif; ?>
        
        <h2>Quick Test</h2>
        <div class="info">
            <p>After enabling the extension and restarting Apache, refresh this page.</p>
            <p>You should see a green success message at the top if it's working.</p>
        </div>
        
        <h2>Manual Test</h2>
        <div class="code">
            <?php
            if ($pdo_mysql_loaded) {
                try {
                    $test_pdo = new PDO("mysql:host=localhost", "root", "");
                    echo "✅ PDO MySQL connection test: SUCCESS!";
                } catch (Exception $e) {
                    echo "❌ PDO MySQL connection test: FAILED - " . $e->getMessage();
                }
            } else {
                echo "❌ Cannot test - PDO MySQL driver not loaded";
            }
            ?>
        </div>
    </div>
</body>
</html>

