<?php
// validate_reports.php - Cross-modal report validation and correlation

require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/audit_helper.php';

/**
 * Validate a newly submitted report against existing reports
 * Looks for correlations within 100m radius and ±2 hour window
 * 
 * @param int $reportId The ID of the report to validate
 * @param string $reportType 'analysis' or 'general'
 * @return array Validation results with correlations found
 */
function validateReport($reportId, $reportType) {
    try {
        $pdo = getDBConnection();
        
        // Get the report details
        $table = $reportType === 'analysis' ? 'analysis_reports' : 'general_reports';
        $timestampField = $reportType === 'analysis' ? 'timestamp' : 'created_at';
        
        $stmt = $pdo->prepare("
            SELECT 
                id,
                latitude,
                longitude,
                $timestampField as report_time,
                category,
                severity,
                confidence_score
            FROM $table 
            WHERE id = ?
        ");
        $stmt->execute([$reportId]);
        $report = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$report || !$report['latitude'] || !$report['longitude']) {
            return [
                'status' => 'skipped',
                'message' => 'Report has no location data',
                'correlations' => []
            ];
        }
        
        $lat = $report['latitude'];
        $lng = $report['longitude'];
        $reportTime = new DateTime($report['report_time']);
        $twoHoursBefore = clone $reportTime;
        $twoHoursBefore->modify('-2 hours');
        $twoHoursAfter = clone $reportTime;
        $twoHoursAfter->modify('+2 hours');
        
        // Look for correlating reports in the OTHER table (cross-modal)
        $otherTable = $reportType === 'analysis' ? 'general_reports' : 'analysis_reports';
        $otherTimestampField = $reportType === 'analysis' ? 'created_at' : 'timestamp';
        
        // Using Haversine formula to find reports within 100m
        // Formula: 6371000 * acos(cos(radians(lat1)) * cos(radians(lat2)) * cos(radians(lng2) - radians(lng1)) + sin(radians(lat1)) * sin(radians(lat2)))
        $sql = "SELECT 
                    id,
                    latitude,
                    longitude,
                    $otherTimestampField as report_time,
                    category,
                    severity,
                    (6371000 * acos(
                        cos(radians(:lat)) * cos(radians(latitude)) * 
                        cos(radians(longitude) - radians(:lng)) + 
                        sin(radians(:lat)) * sin(radians(latitude))
                    )) as distance_meters
                FROM $otherTable
                WHERE latitude IS NOT NULL 
                    AND longitude IS NOT NULL
                    AND $otherTimestampField >= :time_before
                    AND $otherTimestampField <= :time_after
                    AND id != :report_id
                HAVING distance_meters <= 100
                ORDER BY distance_meters ASC
                LIMIT 5";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':lat' => $lat,
            ':lng' => $lng,
            ':time_before' => $twoHoursBefore->format('Y-m-d H:i:s'),
            ':time_after' => $twoHoursAfter->format('Y-m-d H:i:s'),
            ':report_id' => $reportId
        ]);
        
        $correlations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($correlations) > 0) {
            // Found correlating reports - update confidence and status
            $bestMatch = $correlations[0];
            $currentConfidence = floatval($report['confidence_score'] ?? 0.5);
            $newConfidence = min(1.0, $currentConfidence * 1.10); // Increase by 10%
            
            // Update current report
            $updateSql = "UPDATE $table 
                          SET confidence_score = :confidence,
                              status = 'verified'
                          WHERE id = :id";
            $updateStmt = $pdo->prepare($updateSql);
            $updateStmt->execute([
                ':confidence' => $newConfidence,
                ':id' => $reportId
            ]);
            
            // Add note about cross-modal corroboration
            $correlationNote = "Cross-Modal Corroboration: Validated with " . 
                              ($reportType === 'analysis' ? 'General' : 'Analysis') . 
                              " Report #" . $bestMatch['id'] . 
                              " (Distance: " . round($bestMatch['distance_meters']) . "m)";
            
            // Log the validation
            error_log("✅ Cross-modal validation: $reportType report #$reportId correlated with " . 
                     ($reportType === 'analysis' ? 'general' : 'analysis') . " report #" . $bestMatch['id']);
            
            return [
                'status' => 'validated',
                'message' => 'Cross-modal correlation found',
                'correlations' => $correlations,
                'best_match' => $bestMatch,
                'confidence_increase' => ($newConfidence - $currentConfidence),
                'new_confidence' => $newConfidence,
                'note' => $correlationNote
            ];
        }
        
        return [
            'status' => 'no_correlation',
            'message' => 'No correlating reports found',
            'correlations' => []
        ];
        
    } catch (PDOException $e) {
        error_log("❌ Report validation error: " . $e->getMessage());
        return [
            'status' => 'error',
            'message' => $e->getMessage(),
            'correlations' => []
        ];
    }
}

/**
 * API endpoint for manual validation
 * Only run when accessed directly, not when included
 */
if (php_sapi_name() !== 'cli' && basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    header("Content-Type: application/json; charset=UTF-8");
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $reportId = isset($input['report_id']) ? intval($input['report_id']) : 0;
        $reportType = isset($input['report_type']) ? $input['report_type'] : 'general';
        
        if ($reportId === 0) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Report ID required']);
            exit;
        }
        
        $result = validateReport($reportId, $reportType);
        echo json_encode($result, JSON_PRETTY_PRINT);
    } else {
        http_response_code(405);
        echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    }
}
?>

