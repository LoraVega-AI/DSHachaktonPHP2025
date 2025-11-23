<?php
// Test PDO MySQL driver availability
header("Content-Type: application/json");

$results = [
    'pdo_available' => extension_loaded('pdo'),
    'pdo_mysql_available' => extension_loaded('pdo_mysql'),
    'mysql_available' => extension_loaded('mysql'),
    'mysqli_available' => extension_loaded('mysqli'),
    'php_version' => phpversion(),
    'php_ini_file' => php_ini_loaded_file(),
    'pdo_drivers' => PDO::getAvailableDrivers(),
    'all_extensions' => get_loaded_extensions()
];

// Test connection if driver is available
if ($results['pdo_mysql_available']) {
    try {
        $test_pdo = new PDO("mysql:host=localhost", "root", "");
        $results['connection_test'] = 'SUCCESS';
    } catch (Exception $e) {
        $results['connection_test'] = 'FAILED: ' . $e->getMessage();
    }
} else {
    $results['connection_test'] = 'SKIPPED - Driver not loaded';
    $results['fix_instructions'] = [
        'step1' => 'Open php.ini file: ' . ($results['php_ini_file'] ?: 'C:\\xampp\\php\\php.ini'),
        'step2' => 'Find line: ;extension=pdo_mysql',
        'step3' => 'Remove semicolon: extension=pdo_mysql',
        'step4' => 'Save file and restart Apache in XAMPP Control Panel'
    ];
}

echo json_encode($results, JSON_PRETTY_PRINT);
?>

