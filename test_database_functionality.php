<?php
// test_database_functionality.php - Comprehensive database functionality test

header("Content-Type: text/html; charset=UTF-8");

require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/auth.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Database Functionality Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .test-section { background: white; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .test-section h2 { margin-top: 0; color: #333; }
        .success { color: #10b981; font-weight: bold; }
        .error { color: #ef4444; font-weight: bold; }
        .info { color: #3b82f6; }
        .warning { color: #f59e0b; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f9fafb; font-weight: 600; }
        .badge { display: inline-block; padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: 600; }
        .badge-novice { background: #6b728020; color: #6b7280; }
        .badge-trusted { background: #3b82f620; color: #3b82f6; }
        .badge-expert { background: #10b98120; color: #10b981; }
    </style>
</head>
<body>
    <h1>🔍 Database Functionality Test</h1>";

try {
    $pdo = getDBConnection();
    $results = [];
    
    // Test 1: Database Connection
    echo "<div class='test-section'>";
    echo "<h2>1. Database Connection</h2>";
    if ($pdo) {
        echo "<p class='success'>✅ Database connection successful</p>";
        $results['connection'] = true;
    } else {
        echo "<p class='error'>❌ Database connection failed</p>";
        $results['connection'] = false;
    }
    echo "</div>";
    
    // Test 2: Users Table
    echo "<div class='test-section'>";
    echo "<h2>2. Users Table</h2>";
    try {
        $stmt = $pdo->query("SELECT id, username, email, role, trust_score FROM users ORDER BY id");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<p class='success'>✅ Users table accessible</p>";
        echo "<p class='info'>Found " . count($users) . " users</p>";
        
        if (count($users) > 0) {
            echo "<table>";
            echo "<tr><th>ID</th><th>Username</th><th>Email</th><th>Role</th><th>Trust Score</th><th>Badge</th></tr>";
            foreach ($users as $user) {
                $score = intval($user['trust_score'] ?? 0);
                $badge = 'Novice';
                $badgeClass = 'badge-novice';
                if ($score >= 16) {
                    $badge = 'Expert';
                    $badgeClass = 'badge-expert';
                } elseif ($score >= 6) {
                    $badge = 'Trusted';
                    $badgeClass = 'badge-trusted';
                }
                
                echo "<tr>";
                echo "<td>{$user['id']}</td>";
                echo "<td>{$user['username']}</td>";
                echo "<td>{$user['email']}</td>";
                echo "<td>{$user['role']}</td>";
                echo "<td>{$score}</td>";
                echo "<td><span class='badge {$badgeClass}'>⭐ {$badge}</span></td>";
                echo "</tr>";
            }
            echo "</table>";
        }
        $results['users'] = true;
    } catch (PDOException $e) {
        echo "<p class='error'>❌ Error accessing users table: " . $e->getMessage() . "</p>";
        $results['users'] = false;
    }
    echo "</div>";
    
    // Test 3: Analysis Reports Table
    echo "<div class='test-section'>";
    echo "<h2>3. Analysis Reports Table</h2>";
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM analysis_reports");
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        echo "<p class='success'>✅ Analysis reports table accessible</p>";
        echo "<p class='info'>Total reports: {$count}</p>";
        
        // Check recent reports
        $stmt = $pdo->query("SELECT id, top_hazard, severity, status, user_id, created_at FROM analysis_reports ORDER BY created_at DESC LIMIT 5");
        $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($reports) > 0) {
            echo "<h3>Recent Reports:</h3>";
            echo "<table>";
            echo "<tr><th>ID</th><th>Hazard</th><th>Severity</th><th>Status</th><th>User ID</th><th>Created</th></tr>";
            foreach ($reports as $report) {
                echo "<tr>";
                echo "<td>{$report['id']}</td>";
                echo "<td>" . htmlspecialchars($report['top_hazard'] ?? 'N/A') . "</td>";
                echo "<td>{$report['severity']}</td>";
                echo "<td>{$report['status']}</td>";
                echo "<td>" . ($report['user_id'] ?? 'NULL') . "</td>";
                echo "<td>{$report['created_at']}</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
        $results['analysis_reports'] = true;
    } catch (PDOException $e) {
        echo "<p class='error'>❌ Error accessing analysis_reports table: " . $e->getMessage() . "</p>";
        $results['analysis_reports'] = false;
    }
    echo "</div>";
    
    // Test 4: General Reports Table
    echo "<div class='test-section'>";
    echo "<h2>4. General Reports Table</h2>";
    try {
        $tableCheck = $pdo->query("SHOW TABLES LIKE 'general_reports'");
        if ($tableCheck->rowCount() > 0) {
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM general_reports");
            $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            echo "<p class='success'>✅ General reports table accessible</p>";
            echo "<p class='info'>Total reports: {$count}</p>";
            
            // Check recent reports
            $stmt = $pdo->query("SELECT id, title, severity, status, user_id, created_at FROM general_reports ORDER BY created_at DESC LIMIT 5");
            $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($reports) > 0) {
                echo "<h3>Recent Reports:</h3>";
                echo "<table>";
                echo "<tr><th>ID</th><th>Title</th><th>Severity</th><th>Status</th><th>User ID</th><th>Created</th></tr>";
                foreach ($reports as $report) {
                    echo "<tr>";
                    echo "<td>{$report['id']}</td>";
                    echo "<td>" . htmlspecialchars($report['title'] ?? 'N/A') . "</td>";
                    echo "<td>{$report['severity']}</td>";
                    echo "<td>{$report['status']}</td>";
                    echo "<td>" . ($report['user_id'] ?? 'NULL') . "</td>";
                    echo "<td>{$report['created_at']}</td>";
                    echo "</tr>";
                }
                echo "</table>";
            }
            $results['general_reports'] = true;
        } else {
            echo "<p class='warning'>⚠️ General reports table does not exist (will be created on first use)</p>";
            $results['general_reports'] = true; // Not an error, just not created yet
        }
    } catch (PDOException $e) {
        echo "<p class='error'>❌ Error accessing general_reports table: " . $e->getMessage() . "</p>";
        $results['general_reports'] = false;
    }
    echo "</div>";
    
    // Test 5: Trust Score Column
    echo "<div class='test-section'>";
    echo "<h2>5. Trust Score System</h2>";
    try {
        $checkTrustScore = $pdo->query("SHOW COLUMNS FROM users LIKE 'trust_score'");
        if ($checkTrustScore->rowCount() > 0) {
            echo "<p class='success'>✅ Trust score column exists</p>";
            
            $stmt = $pdo->query("SELECT username, trust_score FROM users WHERE trust_score > 0 ORDER BY trust_score DESC");
            $scores = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($scores) > 0) {
                echo "<h3>Users with Trust Scores:</h3>";
                echo "<table>";
                echo "<tr><th>Username</th><th>Trust Score</th><th>Badge Level</th></tr>";
                foreach ($scores as $score) {
                    $trustScore = intval($score['trust_score']);
                    $badge = 'Novice';
                    $badgeClass = 'badge-novice';
                    if ($trustScore >= 16) {
                        $badge = 'Expert';
                        $badgeClass = 'badge-expert';
                    } elseif ($trustScore >= 6) {
                        $badge = 'Trusted';
                        $badgeClass = 'badge-trusted';
                    }
                    
                    echo "<tr>";
                    echo "<td>{$score['username']}</td>";
                    echo "<td>{$trustScore}</td>";
                    echo "<td><span class='badge {$badgeClass}'>⭐ {$badge}</span></td>";
                    echo "</tr>";
                }
                echo "</table>";
            }
            $results['trust_score'] = true;
        } else {
            echo "<p class='error'>❌ Trust score column does not exist</p>";
            $results['trust_score'] = false;
        }
    } catch (PDOException $e) {
        echo "<p class='error'>❌ Error checking trust score: " . $e->getMessage() . "</p>";
        $results['trust_score'] = false;
    }
    echo "</div>";
    
    // Test 6: Report Status Values
    echo "<div class='test-section'>";
    echo "<h2>6. Report Status Values</h2>";
    try {
        // Check analysis_reports status values
        $stmt = $pdo->query("SELECT DISTINCT status FROM analysis_reports WHERE status IS NOT NULL");
        $statuses = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        echo "<p class='success'>✅ Status column accessible</p>";
        echo "<p class='info'>Status values in analysis_reports: " . (count($statuses) > 0 ? implode(', ', $statuses) : 'None') . "</p>";
        
        $allowedStatuses = ['pending', 'solved', 'verified', 'false', 'spam'];
        echo "<p class='info'>Allowed status values: " . implode(', ', $allowedStatuses) . "</p>";
        
        $results['status'] = true;
    } catch (PDOException $e) {
        echo "<p class='error'>❌ Error checking status: " . $e->getMessage() . "</p>";
        $results['status'] = false;
    }
    echo "</div>";
    
    // Test 7: User ID in Reports
    echo "<div class='test-section'>";
    echo "<h2>7. User ID in Reports</h2>";
    try {
        // Check analysis_reports
        $stmt = $pdo->query("SELECT COUNT(*) as total, COUNT(user_id) as with_user FROM analysis_reports");
        $analysis = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo "<p class='success'>✅ User ID column accessible in analysis_reports</p>";
        echo "<p class='info'>Total reports: {$analysis['total']}, Reports with user_id: {$analysis['with_user']}</p>";
        
        // Check general_reports if exists
        $tableCheck = $pdo->query("SHOW TABLES LIKE 'general_reports'");
        if ($tableCheck->rowCount() > 0) {
            $stmt = $pdo->query("SELECT COUNT(*) as total, COUNT(user_id) as with_user FROM general_reports");
            $general = $stmt->fetch(PDO::FETCH_ASSOC);
            echo "<p class='info'>General reports - Total: {$general['total']}, With user_id: {$general['with_user']}</p>";
        }
        
        $results['user_id'] = true;
    } catch (PDOException $e) {
        echo "<p class='error'>❌ Error checking user_id: " . $e->getMessage() . "</p>";
        $results['user_id'] = false;
    }
    echo "</div>";
    
    // Summary
    echo "<div class='test-section'>";
    echo "<h2>📊 Test Summary</h2>";
    $passed = count(array_filter($results));
    $total = count($results);
    
    echo "<p><strong>Tests Passed: {$passed} / {$total}</strong></p>";
    
    if ($passed === $total) {
        echo "<p class='success'>✅ All tests passed! Database is fully functional.</p>";
    } else {
        echo "<p class='error'>❌ Some tests failed. Please check the errors above.</p>";
    }
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='test-section'>";
    echo "<h2 class='error'>❌ Critical Error</h2>";
    echo "<p class='error'>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}

echo "</body></html>";
?>

