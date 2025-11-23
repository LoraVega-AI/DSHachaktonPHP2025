<?php
// test_report_saving.php - Test report saving functionality

header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/auth.php';

$results = [
    'status' => 'success',
    'tests' => []
];

try {
    $pdo = getDBConnection();
    
    // Test 1: Save analysis report as user1
    $results['tests']['analysis_report_user'] = ['name' => 'Save Analysis Report (User)', 'status' => 'pending'];
    try {
        // Get user1
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = 'user1'");
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            $userId = $user['id'];
            
            // Insert test analysis report
            $sql = "INSERT INTO analysis_reports (
                user_id, is_anonymous, timestamp, top_hazard, confidence_score, rms_level, 
                severity, category, latitude, longitude, address, status
            ) VALUES (
                :user_id, 0, NOW(), 'Test Acoustic Anomaly', 0.85, 0.65,
                'HIGH', 'Infrastructure', 42.6629, 21.1655, 'Test Location, Prishtina', 'pending'
            )";
            
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute([':user_id' => $userId]);
            
            if ($result) {
                $reportId = $pdo->lastInsertId();
                $results['tests']['analysis_report_user'] = [
                    'name' => 'Save Analysis Report (User)',
                    'status' => 'success',
                    'report_id' => $reportId,
                    'user_id' => $userId
                ];
            } else {
                $results['tests']['analysis_report_user'] = [
                    'name' => 'Save Analysis Report (User)',
                    'status' => 'error',
                    'message' => 'Insert returned false'
                ];
            }
        } else {
            $results['tests']['analysis_report_user'] = [
                'name' => 'Save Analysis Report (User)',
                'status' => 'error',
                'message' => 'User1 not found'
            ];
        }
    } catch (PDOException $e) {
        $results['tests']['analysis_report_user'] = [
            'name' => 'Save Analysis Report (User)',
            'status' => 'error',
            'message' => $e->getMessage()
        ];
    }
    
    // Test 2: Save analysis report as anonymous
    $results['tests']['analysis_report_anonymous'] = ['name' => 'Save Analysis Report (Anonymous)', 'status' => 'pending'];
    try {
        $sql = "INSERT INTO analysis_reports (
            user_id, is_anonymous, timestamp, top_hazard, confidence_score, rms_level, 
            severity, category, latitude, longitude, address, status
        ) VALUES (
            NULL, 1, NOW(), 'Anonymous Acoustic Event', 0.75, 0.55,
            'MEDIUM', 'Other', 42.6629, 21.1655, 'Anonymous Location', 'pending'
        )";
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute();
        
        if ($result) {
            $reportId = $pdo->lastInsertId();
            $results['tests']['analysis_report_anonymous'] = [
                'name' => 'Save Analysis Report (Anonymous)',
                'status' => 'success',
                'report_id' => $reportId
            ];
        } else {
            $results['tests']['analysis_report_anonymous'] = [
                'name' => 'Save Analysis Report (Anonymous)',
                'status' => 'error',
                'message' => 'Insert returned false'
            ];
        }
    } catch (PDOException $e) {
        $results['tests']['analysis_report_anonymous'] = [
            'name' => 'Save Analysis Report (Anonymous)',
            'status' => 'error',
            'message' => $e->getMessage()
        ];
    }
    
    // Test 3: Save general report
    $results['tests']['general_report'] = ['name' => 'Save General Report', 'status' => 'pending'];
    try {
        // Ensure general_reports table exists (use same structure as submit_report.php)
        $tableCheck = $pdo->query("SHOW TABLES LIKE 'general_reports'");
        if ($tableCheck->rowCount() === 0) {
            $createTableSql = "CREATE TABLE IF NOT EXISTS general_reports (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT DEFAULT NULL,
                is_anonymous BOOLEAN DEFAULT FALSE,
                title VARCHAR(255) NOT NULL,
                description TEXT NOT NULL,
                severity VARCHAR(20) NOT NULL,
                category VARCHAR(50) NOT NULL,
                latitude DECIMAL(10, 8) DEFAULT NULL,
                longitude DECIMAL(11, 8) DEFAULT NULL,
                address TEXT DEFAULT NULL,
                images JSON,
                audio_files JSON,
                status VARCHAR(20) DEFAULT 'pending',
                verification_photo VARCHAR(255) DEFAULT NULL,
                verified_at TIMESTAMP NULL DEFAULT NULL,
                verified_by VARCHAR(100) DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_severity (severity),
                INDEX idx_category (category),
                INDEX idx_status (status),
                INDEX idx_created_at (created_at),
                INDEX idx_location (latitude, longitude),
                INDEX idx_user_id (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            $pdo->exec($createTableSql);
        } else {
            // Ensure user_id column exists (for existing tables)
            try {
                $checkUserId = $pdo->query("SHOW COLUMNS FROM general_reports LIKE 'user_id'");
                if ($checkUserId->rowCount() === 0) {
                    $pdo->exec("ALTER TABLE general_reports ADD COLUMN user_id INT DEFAULT NULL AFTER id");
                    $pdo->exec("ALTER TABLE general_reports ADD COLUMN is_anonymous BOOLEAN DEFAULT FALSE AFTER user_id");
                    $pdo->exec("ALTER TABLE general_reports ADD INDEX idx_user_id (user_id)");
                }
            } catch (PDOException $e) {
                // Column might already exist, ignore
            }
        }
        
        // Get user1
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = 'user1'");
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $userId = $user ? $user['id'] : null;
        
        $sql = "INSERT INTO general_reports (
            user_id, is_anonymous, title, description, severity, category,
            latitude, longitude, address, status
        ) VALUES (
            :user_id, :is_anonymous, 'Test General Report', 'This is a test general report',
            'MEDIUM', 'Infrastructure', 42.6629, 21.1655, 'Test Location', 'pending'
        )";
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            ':user_id' => $userId,
            ':is_anonymous' => $userId ? 0 : 1
        ]);
        
        if ($result) {
            $reportId = $pdo->lastInsertId();
            $results['tests']['general_report'] = [
                'name' => 'Save General Report',
                'status' => 'success',
                'report_id' => $reportId,
                'user_id' => $userId
            ];
        } else {
            $results['tests']['general_report'] = [
                'name' => 'Save General Report',
                'status' => 'error',
                'message' => 'Insert returned false'
            ];
        }
    } catch (PDOException $e) {
        $results['tests']['general_report'] = [
            'name' => 'Save General Report',
            'status' => 'error',
            'message' => $e->getMessage()
        ];
    }
    
    // Test 4: Verify reports are retrievable
    $results['tests']['retrieve_reports'] = ['name' => 'Retrieve Reports', 'status' => 'pending'];
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM analysis_reports");
        $analysisCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        $tableCheck = $pdo->query("SHOW TABLES LIKE 'general_reports'");
        $generalCount = 0;
        if ($tableCheck->rowCount() > 0) {
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM general_reports");
            $generalCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        }
        
        $results['tests']['retrieve_reports'] = [
            'name' => 'Retrieve Reports',
            'status' => 'success',
            'analysis_reports_count' => intval($analysisCount),
            'general_reports_count' => intval($generalCount)
        ];
    } catch (PDOException $e) {
        $results['tests']['retrieve_reports'] = [
            'name' => 'Retrieve Reports',
            'status' => 'error',
            'message' => $e->getMessage()
        ];
    }
    
    // Test 5: Verify user_id is properly linked
    $results['tests']['user_id_linking'] = ['name' => 'User ID Linking', 'status' => 'pending'];
    try {
        $stmt = $pdo->query("
            SELECT ar.id, ar.user_id, u.username 
            FROM analysis_reports ar 
            LEFT JOIN users u ON ar.user_id = u.id 
            WHERE ar.user_id IS NOT NULL 
            LIMIT 5
        ");
        $linkedReports = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $results['tests']['user_id_linking'] = [
            'name' => 'User ID Linking',
            'status' => 'success',
            'linked_reports_count' => count($linkedReports),
            'sample_reports' => $linkedReports
        ];
    } catch (PDOException $e) {
        $results['tests']['user_id_linking'] = [
            'name' => 'User ID Linking',
            'status' => 'error',
            'message' => $e->getMessage()
        ];
    }
    
    // Count successes
    $successCount = 0;
    $totalCount = count($results['tests']);
    foreach ($results['tests'] as $test) {
        if (isset($test['status']) && $test['status'] === 'success') {
            $successCount++;
        }
    }
    
    $results['summary'] = [
        'total_tests' => $totalCount,
        'passed' => $successCount,
        'failed' => $totalCount - $successCount
    ];
    
} catch (Exception $e) {
    $results['status'] = 'error';
    $results['message'] = $e->getMessage();
}

echo json_encode($results, JSON_PRETTY_PRINT);
?>

