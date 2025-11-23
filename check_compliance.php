<?php
// check_compliance.php - Check regulatory compliance for reports

require_once __DIR__ . '/db_config.php';

/**
 * Check compliance status for a report
 * Compares time elapsed since submission against regulatory rules
 * 
 * @param int $reportId Report ID
 * @param string $reportType 'analysis' or 'general'
 * @return array Compliance status
 */
function checkCompliance($reportId, $reportType) {
    try {
        $pdo = getDBConnection();
        
        // Get report details
        $table = $reportType === 'analysis' ? 'analysis_reports' : 'general_reports';
        $timestampField = $reportType === 'analysis' ? 'timestamp' : 'created_at';
        
        $stmt = $pdo->prepare("
            SELECT 
                id,
                category,
                severity,
                status,
                $timestampField as report_time,
                compliance_status
            FROM $table 
            WHERE id = ?
        ");
        $stmt->execute([$reportId]);
        $report = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$report) {
            return [
                'status' => 'error',
                'message' => 'Report not found'
            ];
        }
        
        // Skip if already resolved
        if (in_array($report['status'], ['solved', 'verified'])) {
            return [
                'status' => 'resolved',
                'compliance_status' => 'compliant',
                'message' => 'Report already resolved'
            ];
        }
        
        // Get compliance rule for this category
        $stmt = $pdo->prepare("
            SELECT max_response_time_hours, severity_threshold, description
            FROM compliance_rules
            WHERE category = ?
            ORDER BY max_response_time_hours ASC
            LIMIT 1
        ");
        $stmt->execute([$report['category']]);
        $rule = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$rule) {
            // No specific rule, use default
            $maxHours = 72; // Default 3 days
            $description = 'General compliance standard';
        } else {
            $maxHours = intval($rule['max_response_time_hours']);
            $description = $rule['description'];
        }
        
        // Calculate time elapsed
        $reportTime = new DateTime($report['report_time']);
        $now = new DateTime();
        $elapsed = $now->diff($reportTime);
        $hoursElapsed = ($elapsed->days * 24) + $elapsed->h + ($elapsed->i / 60);
        
        // Determine compliance status
        $complianceStatus = 'compliant';
        $message = 'Within compliance timeframe';
        
        if ($hoursElapsed > $maxHours) {
            $complianceStatus = 'overdue';
            $message = 'Exceeds maximum response time';
        } elseif ($hoursElapsed > ($maxHours * 0.8)) {
            $complianceStatus = 'at_risk';
            $message = 'Approaching compliance deadline';
        }
        
        // Update compliance status in database if changed
        if ($report['compliance_status'] !== $complianceStatus) {
            $updateSql = "UPDATE $table SET compliance_status = :status WHERE id = :id";
            $updateStmt = $pdo->prepare($updateSql);
            $updateStmt->execute([
                ':status' => $complianceStatus,
                ':id' => $reportId
            ]);
            
            if ($complianceStatus === 'overdue') {
                error_log("⚠️ Compliance: Report #$reportId is OVERDUE ({$hoursElapsed}h > {$maxHours}h)");
            } elseif ($complianceStatus === 'at_risk') {
                error_log("⏰ Compliance: Report #$reportId is AT RISK ({$hoursElapsed}h / {$maxHours}h)");
            }
        }
        
        return [
            'status' => 'success',
            'compliance_status' => $complianceStatus,
            'message' => $message,
            'details' => [
                'report_id' => $reportId,
                'category' => $report['category'],
                'severity' => $report['severity'],
                'hours_elapsed' => round($hoursElapsed, 1),
                'max_hours_allowed' => $maxHours,
                'hours_remaining' => max(0, $maxHours - $hoursElapsed),
                'compliance_percentage' => min(100, round(($hoursElapsed / $maxHours) * 100, 1)),
                'rule_description' => $description
            ]
        ];
        
    } catch (PDOException $e) {
        error_log("❌ Compliance check error: " . $e->getMessage());
        return [
            'status' => 'error',
            'message' => $e->getMessage()
        ];
    }
}

/**
 * Get compliance summary for all active reports
 * 
 * @return array Compliance summary statistics
 */
function getComplianceSummary() {
    try {
        $pdo = getDBConnection();
        
        // Get counts by compliance status
        $sql = "SELECT 
                    compliance_status,
                    COUNT(*) as count
                FROM (
                    SELECT compliance_status FROM analysis_reports WHERE status NOT IN ('solved', 'verified')
                    UNION ALL
                    SELECT compliance_status FROM general_reports WHERE status NOT IN ('solved', 'verified')
                ) as all_reports
                GROUP BY compliance_status";
        
        $stmt = $pdo->query($sql);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $summary = [
            'compliant' => 0,
            'at_risk' => 0,
            'overdue' => 0,
            'total' => 0
        ];
        
        foreach ($results as $row) {
            $status = $row['compliance_status'] ?? 'compliant';
            $count = intval($row['count']);
            $summary[$status] = $count;
            $summary['total'] += $count;
        }
        
        return [
            'status' => 'success',
            'summary' => $summary
        ];
        
    } catch (PDOException $e) {
        error_log("❌ Compliance summary error: " . $e->getMessage());
        return [
            'status' => 'error',
            'message' => $e->getMessage()
        ];
    }
}

/**
 * API endpoint
 */
if (php_sapi_name() !== 'cli') {
    header("Content-Type: application/json; charset=UTF-8");
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if (isset($_GET['summary'])) {
            // Get compliance summary
            $result = getComplianceSummary();
            echo json_encode($result, JSON_PRETTY_PRINT);
        } else {
            // Check specific report
            $reportId = isset($_GET['report_id']) ? intval($_GET['report_id']) : 0;
            $reportType = isset($_GET['report_type']) ? $_GET['report_type'] : 'general';
            
            if ($reportId === 0) {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'Report ID required']);
                exit;
            }
            
            $result = checkCompliance($reportId, $reportType);
            echo json_encode($result, JSON_PRETTY_PRINT);
        }
    } else {
        http_response_code(405);
        echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    }
}
?>

