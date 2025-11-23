<?php
// get_report_timeline.php - Get detailed report information for timeline view

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type");

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/db_config.php';

try {
    $reportId = $_GET['id'] ?? null;
    $reportType = $_GET['type'] ?? 'analysis';

    if (!$reportId) {
        throw new Exception('Report ID is required');
    }

    $pdo = getDBConnection();
    
    // Ensure database is initialized (this will add missing columns)
    if (function_exists('initializeDatabase')) {
        @initializeDatabase();
    }
    
    // Determine which table to query
    $tableName = $reportType === 'general' ? 'general_reports' : 'analysis_reports';
    $idColumn = 'id';
    
    // Ensure required columns exist for analysis_reports
    $hasStatusColumn = true;
    $hasVerificationColumns = true;
    
    if ($reportType === 'analysis') {
        // Check and add status column if needed
        try {
            $checkStatus = $pdo->query("SHOW COLUMNS FROM analysis_reports LIKE 'status'");
            if ($checkStatus->rowCount() === 0) {
                try {
                    $pdo->exec("ALTER TABLE analysis_reports ADD COLUMN status VARCHAR(20) DEFAULT 'pending' AFTER category");
                    $pdo->exec("ALTER TABLE analysis_reports ADD INDEX idx_status (status)");
                    error_log("✅ Added status column to analysis_reports table");
                } catch (PDOException $e) {
                    error_log("Note: Could not add status column: " . $e->getMessage());
                    $hasStatusColumn = false;
                }
            }
        } catch (PDOException $e) {
            $hasStatusColumn = false;
        }
        
        // Check and add verification columns if needed
        try {
            $checkCol = $pdo->query("SHOW COLUMNS FROM analysis_reports LIKE 'verification_photo'");
            if ($checkCol->rowCount() === 0) {
                try {
                    // First ensure status column exists (needed for AFTER clause)
                    if (!$hasStatusColumn) {
                        $pdo->exec("ALTER TABLE analysis_reports ADD COLUMN status VARCHAR(20) DEFAULT 'pending' AFTER category");
                    }
                    $pdo->exec("ALTER TABLE analysis_reports ADD COLUMN verification_photo VARCHAR(255) DEFAULT NULL AFTER status");
                    $pdo->exec("ALTER TABLE analysis_reports ADD COLUMN verified_at TIMESTAMP NULL DEFAULT NULL AFTER verification_photo");
                    $pdo->exec("ALTER TABLE analysis_reports ADD COLUMN verified_by VARCHAR(100) DEFAULT NULL AFTER verified_at");
                    error_log("✅ Added verification columns to analysis_reports table");
                } catch (PDOException $e) {
                    error_log("Note: Could not add verification columns: " . $e->getMessage());
                    $hasVerificationColumns = false;
                }
            }
        } catch (PDOException $e) {
            $hasVerificationColumns = false;
        }
    }
    
    // Build query based on table type
    if ($reportType === 'general') {
        $sql = "SELECT 
            id,
            'general' as report_type,
            title as hazard,
            description as classification,
            severity,
            category,
            COALESCE(status, 'pending') as status,
            latitude,
            longitude,
            address,
            verification_photo,
            verified_at,
            verified_by,
            created_at,
            COALESCE(updated_at, created_at) as updated_at
        FROM {$tableName}
        WHERE {$idColumn} = :id";
    } else {
        // analysis_reports - handle missing verification columns gracefully
        if ($hasVerificationColumns) {
            $sql = "SELECT 
                id,
                'analysis' as report_type,
                top_hazard as hazard,
                classification,
                executive_conclusion,
                severity,
                category,
                COALESCE(status, 'pending') as status,
                latitude,
                longitude,
                address,
                verification_photo,
                verified_at,
                verified_by,
                created_at,
                timestamp,
                COALESCE(created_at, timestamp) as updated_at
            FROM {$tableName}
            WHERE {$idColumn} = :id";
        } else {
            // Fallback query without verification columns
            $sql = "SELECT 
                id,
                'analysis' as report_type,
                top_hazard as hazard,
                classification,
                executive_conclusion,
                severity,
                category,
                COALESCE(status, 'pending') as status,
                latitude,
                longitude,
                address,
                NULL as verification_photo,
                NULL as verified_at,
                NULL as verified_by,
                created_at,
                timestamp,
                COALESCE(created_at, timestamp) as updated_at
            FROM {$tableName}
            WHERE {$idColumn} = :id";
        }
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $reportId]);
    $report = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$report) {
        throw new Exception('Report not found');
    }

    // Normalize timestamps
    if (empty($report['created_at']) && !empty($report['timestamp'])) {
        $report['created_at'] = $report['timestamp'];
    }

    echo json_encode([
        'status' => 'success',
        'report' => $report
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
    error_log("Error in get_report_timeline.php: " . $e->getMessage());
}
?>

