<?php
// tests/test_all_database_features.php - Comprehensive test suite for all database features

echo "<!DOCTYPE html><html><head><title>Database Features Test Suite</title>";
echo "<style>
body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
.container { max-width: 1400px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
h1 { color: #333; border-bottom: 3px solid #4CAF50; padding-bottom: 10px; }
h2 { color: #555; margin-top: 30px; border-bottom: 2px solid #eee; padding-bottom: 5px; }
h3 { color: #666; margin-top: 20px; }
.test-result { margin: 10px 0; padding: 12px; border-radius: 5px; }
.success { background: #d4edda; border-left: 4px solid #28a745; }
.error { background: #f8d7da; border-left: 4px solid #dc3545; }
.warning { background: #fff3cd; border-left: 4px solid #ffc107; }
.info { background: #d1ecf1; border-left: 4px solid #17a2b8; }
.status { font-weight: bold; }
.details { margin-top: 5px; font-size: 14px; color: #666; }
.summary { background: #e7f3ff; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #2196F3; }
pre { background: #f4f4f4; padding: 10px; border-radius: 4px; overflow-x: auto; font-size: 12px; max-height: 300px; overflow-y: auto; }
.test-category { background: #f8f9fa; padding: 15px; margin: 20px 0; border-radius: 5px; border-left: 4px solid #6c757d; }
table { width: 100%; border-collapse: collapse; margin: 10px 0; }
th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; font-size: 13px; }
th { background: #f8f9fa; font-weight: bold; }
</style></head><body><div class='container'>";

echo "<h1>🧪 Database Features Comprehensive Test Suite</h1>";
echo "<p>Testing all database endpoints, CRUD operations, and error scenarios.</p>";

$results = [
    'categories' => [],
    'total_tests' => 0,
    'passed' => 0,
    'failed' => 0,
    'warnings' => 0
];

function runTest($name, $callable) {
    global $results;
    $results['total_tests']++;
    
    try {
        $result = $callable();
        if ($result['status'] === 'success') {
            $results['passed']++;
            echo "<div class='test-result success'>";
            echo "<div class='status'>✅ PASS: {$name}</div>";
        } elseif ($result['status'] === 'warning') {
            $results['warnings']++;
            echo "<div class='test-result warning'>";
            echo "<div class='status'>⚠️ WARNING: {$name}</div>";
        } else {
            $results['failed']++;
            echo "<div class='test-result error'>";
            echo "<div class='status'>❌ FAIL: {$name}</div>";
        }
        
        if (!empty($result['message'])) {
            echo "<div class='details'>" . htmlspecialchars($result['message']) . "</div>";
        }
        
        if (!empty($result['data'])) {
            echo "<details><summary>Details</summary><pre>" . htmlspecialchars(print_r($result['data'], true)) . "</pre></details>";
        }
        
        echo "</div>";
        return $result;
        
    } catch (Exception $e) {
        $results['failed']++;
        echo "<div class='test-result error'>";
        echo "<div class='status'>❌ ERROR: {$name}</div>";
        echo "<div class='details'>Exception: " . htmlspecialchars($e->getMessage()) . "</div>";
        echo "</div>";
        return ['status' => 'error', 'message' => $e->getMessage()];
    }
}

// ===================================
// AUTHENTICATION ENDPOINT TESTS
// ===================================
echo "<h2>1. Authentication Endpoints</h2>";
echo "<div class='test-category'>";

$testUser = 'testuser_' . time();
$testEmail = $testUser . '@test.com';
$testPassword = 'Test123456';

// Test Registration
runTest("Register new user", function() use ($testUser, $testEmail, $testPassword) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "http://localhost/DSHackathon2025/register.php");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'username' => $testUser,
        'email' => $testEmail,
        'password' => $testPassword
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $data = json_decode($response, true);
    
    if ($httpCode === 200 && $data['status'] === 'success') {
        return ['status' => 'success', 'message' => 'User registered successfully', 'data' => $data];
    } else {
        return ['status' => 'error', 'message' => 'Registration failed: ' . ($data['message'] ?? 'Unknown error')];
    }
});

// Test Login
runTest("Login with valid credentials", function() use ($testUser, $testPassword) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "http://localhost/DSHackathon2025/login.php");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'username' => $testUser,
        'password' => $testPassword
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $data = json_decode($response, true);
    
    if ($httpCode === 200 && $data['status'] === 'success') {
        return ['status' => 'success', 'message' => 'Login successful', 'data' => $data];
    } else {
        return ['status' => 'error', 'message' => 'Login failed: ' . ($data['message'] ?? 'Unknown error')];
    }
});

// Test Login with invalid credentials
runTest("Login with invalid credentials (should fail)", function() {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "http://localhost/DSHackathon2025/login.php");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'username' => 'nonexistent_user',
        'password' => 'wrong_password'
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    
    $data = json_decode($response, true);
    
    if ($data['status'] === 'error') {
        return ['status' => 'success', 'message' => 'Correctly rejected invalid credentials'];
    } else {
        return ['status' => 'error', 'message' => 'Should have rejected invalid credentials'];
    }
});

// Test Check Auth
runTest("Check authentication status", function() {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "http://localhost/DSHackathon2025/check_auth.php");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $data = json_decode($response, true);
    
    if ($httpCode === 200 && isset($data['authenticated'])) {
        return ['status' => 'success', 'message' => 'Auth status check works', 'data' => $data];
    } else {
        return ['status' => 'error', 'message' => 'Auth status check failed'];
    }
});

echo "</div>";

// ===================================
// USER MANAGEMENT ENDPOINT TESTS
// ===================================
echo "<h2>2. User Management Endpoints</h2>";
echo "<div class='test-category'>";

// Test Get All Users (requires admin)
runTest("Get all users endpoint", function() {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "http://localhost/DSHackathon2025/get_all_users.php");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $data = json_decode($response, true);
    
    if ($httpCode === 200 || $httpCode === 403) {
        // Either success (if admin) or forbidden (if not admin) is acceptable
        return ['status' => 'success', 'message' => 'Endpoint responds correctly (HTTP ' . $httpCode . ')', 'data' => $data];
    } else {
        return ['status' => 'error', 'message' => 'Unexpected HTTP code: ' . $httpCode];
    }
});

// Test Get User Stats
runTest("Get user statistics endpoint", function() {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "http://localhost/DSHackathon2025/get_user_stats.php");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $data = json_decode($response, true);
    
    if ($httpCode === 200 || $httpCode === 400) {
        return ['status' => 'success', 'message' => 'Endpoint responds (HTTP ' . $httpCode . ')', 'data' => $data];
    } else {
        return ['status' => 'error', 'message' => 'Unexpected error'];
    }
});

echo "</div>";

// ===================================
// ANALYTICS ENDPOINT TESTS
// ===================================
echo "<h2>3. Analytics Endpoints</h2>";
echo "<div class='test-category'>";

// Test Admin Analytics
runTest("Get admin analytics", function() {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "http://localhost/DSHackathon2025/get_admin_analytics.php");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $data = json_decode($response, true);
    
    if ($httpCode === 200 || $httpCode === 403) {
        return ['status' => 'success', 'message' => 'Analytics endpoint responds (HTTP ' . $httpCode . ')', 'data' => $data];
    } else {
        return ['status' => 'error', 'message' => 'Analytics endpoint error: HTTP ' . $httpCode];
    }
});

// Test Heatmap Data
runTest("Get heatmap data", function() {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "http://localhost/DSHackathon2025/get_heatmap_data.php");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $data = json_decode($response, true);
    
    if ($httpCode === 200 && isset($data['status'])) {
        return ['status' => 'success', 'message' => 'Heatmap data loads', 'data' => $data];
    } else {
        return ['status' => 'error', 'message' => 'Heatmap data failed: HTTP ' . $httpCode];
    }
});

echo "</div>";

// ===================================
// REPORT ENDPOINT TESTS
// ===================================
echo "<h2>4. Report Endpoints</h2>";
echo "<div class='test-category'>";

// Test Get Detection History
runTest("Get detection history", function() {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "http://localhost/DSHackathon2025/get_detection_history.php");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $data = json_decode($response, true);
    
    if ($httpCode === 200 && isset($data['status'])) {
        return ['status' => 'success', 'message' => 'Detection history loads', 'data' => [
            'total_reports' => $data['total_reports'] ?? 0,
            'status' => $data['status']
        ]];
    } else {
        return ['status' => 'error', 'message' => 'Detection history failed: HTTP ' . $httpCode];
    }
});

// Test Get Map Reports
runTest("Get map reports", function() {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "http://localhost/DSHackathon2025/get_map_reports.php");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $data = json_decode($response, true);
    
    if ($httpCode === 200 && isset($data['status'])) {
        return ['status' => 'success', 'message' => 'Map reports load', 'data' => [
            'report_count' => count($data['reports'] ?? []),
            'status' => $data['status']
        ]];
    } else {
        return ['status' => 'error', 'message' => 'Map reports failed: HTTP ' . $httpCode];
    }
});

// Test Rankings
runTest("Get rankings", function() {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "http://localhost/DSHackathon2025/rankings.php");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $data = json_decode($response, true);
    
    // Rankings may require login (403 for guests is acceptable)
    if ($httpCode === 200 || $httpCode === 403) {
        return ['status' => 'success', 'message' => 'Rankings endpoint responds (HTTP ' . $httpCode . ')'];
    } else {
        return ['status' => 'error', 'message' => 'Rankings failed: HTTP ' . $httpCode];
    }
});

echo "</div>";

// ===================================
// DATABASE CONNECTION TEST
// ===================================
echo "<h2>5. Direct Database Tests</h2>";
echo "<div class='test-category'>";

require_once __DIR__ . '/../db_config.php';

runTest("Database connection", function() {
    $pdo = getDBConnection();
    if ($pdo) {
        return ['status' => 'success', 'message' => 'Database connection established'];
    } else {
        return ['status' => 'error', 'message' => 'Cannot connect to database'];
    }
});

runTest("Users table exists", function() {
    $pdo = getDBConnection();
    $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
    if ($stmt->rowCount() > 0) {
        return ['status' => 'success', 'message' => 'Users table exists'];
    } else {
        return ['status' => 'error', 'message' => 'Users table does not exist'];
    }
});

runTest("Analysis reports table exists", function() {
    $pdo = getDBConnection();
    $stmt = $pdo->query("SHOW TABLES LIKE 'analysis_reports'");
    if ($stmt->rowCount() > 0) {
        return ['status' => 'success', 'message' => 'Analysis reports table exists'];
    } else {
        return ['status' => 'error', 'message' => 'Analysis reports table does not exist'];
    }
});

runTest("General reports table exists", function() {
    $pdo = getDBConnection();
    $stmt = $pdo->query("SHOW TABLES LIKE 'general_reports'");
    if ($stmt->rowCount() > 0) {
        return ['status' => 'success', 'message' => 'General reports table exists'];
    } else {
        return ['status' => 'warning', 'message' => 'General reports table does not exist (will be auto-created)'];
    }
});

echo "</div>";

// ===================================
// SUMMARY
// ===================================
echo "<h2>📊 Test Summary</h2>";
echo "<div class='summary'>";
echo "<div><strong>Total Tests:</strong> {$results['total_tests']}</div>";
echo "<div style='color: #28a745;'><strong>Passed:</strong> {$results['passed']}</div>";
echo "<div style='color: #dc3545;'><strong>Failed:</strong> {$results['failed']}</div>";
echo "<div style='color: #ffc107;'><strong>Warnings:</strong> {$results['warnings']}</div>";

$successRate = $results['total_tests'] > 0 ? round(($results['passed'] / $results['total_tests']) * 100, 1) : 0;
echo "<div style='margin-top: 10px;'><strong>Success Rate:</strong> {$successRate}%</div>";

echo "<div style='margin-top: 10px;'><strong>Overall Status:</strong> ";
if ($results['failed'] === 0) {
    echo "<span style='color: #28a745; font-weight: bold;'>✅ ALL TESTS PASSED</span>";
} else {
    echo "<span style='color: #dc3545; font-weight: bold;'>❌ SOME TESTS FAILED</span>";
}
echo "</div>";
echo "</div>";

// Recommendations
echo "<h2>💡 Recommendations</h2>";
echo "<div class='test-result info'>";
echo "<ul>";
if ($results['failed'] > 0) {
    echo "<li>Review failed tests above and fix the underlying issues</li>";
    echo "<li>Ensure MySQL is running on port 3307</li>";
    echo "<li>Verify all database tables are created and initialized</li>";
}
if ($results['warnings'] > 0) {
    echo "<li>Address warnings to ensure optimal system performance</li>";
}
echo "<li>Run schema validator (<code>tests/validate_database_schema.php</code>) to check column structures</li>";
echo "<li>Test with admin account for full feature coverage</li>";
echo "<li>Monitor application logs for any runtime errors</li>";
echo "</ul>";
echo "</div>";

echo "</div></body></html>";
?>

