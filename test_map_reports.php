<?php
// test_map_reports.php - Test script to verify reports with locations are in database

header("Content-Type: text/html; charset=UTF-8");

require_once __DIR__ . '/db_config.php';

try {
    $pdo = getDBConnection();
    
    echo "<h2>Testing Map Reports</h2>";
    
    // Check if table exists
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'general_reports'");
    if ($tableCheck->rowCount() === 0) {
        echo "<p style='color: red;'>❌ general_reports table does not exist!</p>";
        exit;
    }
    
    echo "<p style='color: green;'>✅ general_reports table exists</p>";
    
    // Get all reports
    $allReports = $pdo->query("SELECT COUNT(*) as total FROM general_reports")->fetch(PDO::FETCH_ASSOC);
    echo "<p>Total reports in database: <strong>" . $allReports['total'] . "</strong></p>";
    
    // Get reports with location
    $reportsWithLocation = $pdo->query("
        SELECT COUNT(*) as total 
        FROM general_reports 
        WHERE latitude IS NOT NULL 
        AND longitude IS NOT NULL 
        AND latitude != 0 
        AND longitude != 0
    ")->fetch(PDO::FETCH_ASSOC);
    
    echo "<p>Reports with valid location: <strong>" . $reportsWithLocation['total'] . "</strong></p>";
    
    // Show sample reports with location
    $sampleReports = $pdo->query("
        SELECT id, title, latitude, longitude, address, severity, category, created_at
        FROM general_reports 
        WHERE latitude IS NOT NULL 
        AND longitude IS NOT NULL 
        AND latitude != 0 
        AND longitude != 0
        ORDER BY created_at DESC 
        LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($sampleReports) > 0) {
        echo "<h3>Sample Reports with Location:</h3>";
        echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>Title</th><th>Latitude</th><th>Longitude</th><th>Address</th><th>Severity</th><th>Category</th><th>Created</th></tr>";
        
        foreach ($sampleReports as $report) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($report['id']) . "</td>";
            echo "<td>" . htmlspecialchars($report['title']) . "</td>";
            echo "<td>" . htmlspecialchars($report['latitude']) . "</td>";
            echo "<td>" . htmlspecialchars($report['longitude']) . "</td>";
            echo "<td>" . htmlspecialchars($report['address'] ?? 'N/A') . "</td>";
            echo "<td>" . htmlspecialchars($report['severity']) . "</td>";
            echo "<td>" . htmlspecialchars($report['category']) . "</td>";
            echo "<td>" . htmlspecialchars($report['created_at']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: orange;'>⚠️ No reports with valid location found in database!</p>";
        echo "<p>You need to submit reports with location data first.</p>";
    }
    
    // Test API endpoint
    echo "<h3>Testing API Endpoint:</h3>";
    $apiUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/get_map_reports.php?limit=10';
    echo "<p>API URL: <a href='$apiUrl' target='_blank'>$apiUrl</a></p>";
    
    $apiResponse = @file_get_contents($apiUrl);
    if ($apiResponse) {
        $apiData = json_decode($apiResponse, true);
        if ($apiData && isset($apiData['status']) && $apiData['status'] === 'success') {
            echo "<p style='color: green;'>✅ API is working! Returned " . count($apiData['reports']) . " reports</p>";
            
            $reportsWithLoc = array_filter($apiData['reports'], function($r) {
                return $r['latitude'] !== null && $r['longitude'] !== null;
            });
            
            echo "<p>Reports with location in API response: <strong>" . count($reportsWithLoc) . "</strong></p>";
            
            if (count($reportsWithLoc) > 0) {
                echo "<h4>First report with location from API:</h4>";
                echo "<pre>" . print_r($reportsWithLoc[0], true) . "</pre>";
            }
        } else {
            echo "<p style='color: red;'>❌ API returned error: " . ($apiData['message'] ?? 'Unknown error') . "</p>";
        }
    } else {
        echo "<p style='color: red;'>❌ Could not fetch from API endpoint</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>

