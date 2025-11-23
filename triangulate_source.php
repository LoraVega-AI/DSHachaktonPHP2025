<?php
// triangulate_source.php - Acoustic source localization using triangulation

require_once __DIR__ . '/db_config.php';

/**
 * Attempt to triangulate the source location of an acoustic hazard
 * Requires ≥3 reports of the same hazard type within 50m radius
 * Uses weighted average by RMS level to refine location
 * 
 * @param int $reportId The ID of the newly added analysis report
 * @return array Triangulation results
 */
function triangulateSource($reportId) {
    try {
        $pdo = getDBConnection();
        
        // Get the new report details
        $stmt = $pdo->prepare("
            SELECT 
                id,
                top_hazard,
                latitude,
                longitude,
                rms_level,
                timestamp,
                category,
                severity
            FROM analysis_reports 
            WHERE id = ?
        ");
        $stmt->execute([$reportId]);
        $report = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$report || !$report['latitude'] || !$report['longitude']) {
            return [
                'status' => 'skipped',
                'message' => 'Report has no location data',
                'cluster_reports' => []
            ];
        }
        
        $lat = $report['latitude'];
        $lng = $report['longitude'];
        $hazardType = $report['top_hazard'];
        
        // Find similar reports within 50m radius (not already triangulated)
        $sql = "SELECT 
                    id,
                    top_hazard,
                    latitude,
                    longitude,
                    rms_level,
                    timestamp,
                    (6371000 * acos(
                        cos(radians(:lat)) * cos(radians(latitude)) * 
                        cos(radians(longitude) - radians(:lng)) + 
                        sin(radians(:lat)) * sin(radians(latitude))
                    )) as distance_meters
                FROM analysis_reports
                WHERE latitude IS NOT NULL 
                    AND longitude IS NOT NULL
                    AND top_hazard = :hazard_type
                    AND (is_triangulated IS NULL OR is_triangulated = FALSE)
                    AND id != :report_id
                HAVING distance_meters <= 50
                ORDER BY distance_meters ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':lat' => $lat,
            ':lng' => $lng,
            ':hazard_type' => $hazardType,
            ':report_id' => $reportId
        ]);
        
        $nearbyReports = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Include the current report
        $allReports = array_merge([$report], $nearbyReports);
        
        if (count($allReports) < 3) {
            return [
                'status' => 'insufficient_data',
                'message' => 'Need at least 3 reports for triangulation (found ' . count($allReports) . ')',
                'cluster_reports' => $allReports
            ];
        }
        
        // Calculate weighted centroid using RMS levels as weights
        $totalWeight = 0;
        $weightedLat = 0;
        $weightedLng = 0;
        
        foreach ($allReports as $r) {
            $weight = floatval($r['rms_level']);
            $totalWeight += $weight;
            $weightedLat += $weight * floatval($r['latitude']);
            $weightedLng += $weight * floatval($r['longitude']);
        }
        
        $refinedLat = $weightedLat / $totalWeight;
        $refinedLng = $weightedLng / $totalWeight;
        
        // Create a unique cluster ID
        $clusterIdStmt = $pdo->query("SELECT COALESCE(MAX(cluster_id), 0) + 1 as next_id FROM analysis_reports");
        $clusterId = $clusterIdStmt->fetch(PDO::FETCH_ASSOC)['next_id'];
        
        // Mark all reports in this cluster as triangulated
        $reportIds = array_map(function($r) { return $r['id']; }, $allReports);
        $placeholders = implode(',', array_fill(0, count($reportIds), '?'));
        
        $updateSql = "UPDATE analysis_reports 
                      SET is_triangulated = TRUE, cluster_id = ? 
                      WHERE id IN ($placeholders)";
        $updateStmt = $pdo->prepare($updateSql);
        $updateStmt->execute(array_merge([$clusterId], $reportIds));
        
        // Create a virtual triangulated report (optional - for visualization)
        $avgSeverity = calculateAverageSeverity($allReports);
        $avgConfidence = array_sum(array_column($allReports, 'confidence_score')) / count($allReports);
        
        $insertSql = "INSERT INTO analysis_reports 
                      (top_hazard, latitude, longitude, rms_level, confidence_score, severity, 
                       is_triangulated, cluster_id, executive_conclusion, timestamp, category)
                      VALUES 
                      (:hazard, :lat, :lng, :rms, :confidence, :severity, 
                       TRUE, :cluster_id, :conclusion, NOW(), :category)";
        
        $insertStmt = $pdo->prepare($insertSql);
        $insertStmt->execute([
            ':hazard' => $hazardType . ' (Triangulated Source)',
            ':lat' => $refinedLat,
            ':lng' => $refinedLng,
            ':rms' => array_sum(array_column($allReports, 'rms_level')) / count($allReports),
            ':confidence' => $avgConfidence,
            ':severity' => $avgSeverity,
            ':cluster_id' => $clusterId,
            ':conclusion' => 'Triangulated source location from ' . count($allReports) . ' correlated acoustic reports.',
            ':category' => $report['category']
        ]);
        
        $virtualReportId = $pdo->lastInsertId();
        
        error_log("✅ Triangulation: Created cluster #$clusterId with " . count($allReports) . 
                  " reports for hazard '$hazardType' at ($refinedLat, $refinedLng)");
        
        return [
            'status' => 'triangulated',
            'message' => 'Successfully triangulated source from ' . count($allReports) . ' reports',
            'cluster_id' => $clusterId,
            'cluster_reports' => $allReports,
            'refined_location' => [
                'latitude' => $refinedLat,
                'longitude' => $refinedLng
            ],
            'virtual_report_id' => $virtualReportId,
            'report_count' => count($allReports)
        ];
        
    } catch (PDOException $e) {
        error_log("❌ Triangulation error: " . $e->getMessage());
        return [
            'status' => 'error',
            'message' => $e->getMessage(),
            'cluster_reports' => []
        ];
    }
}

/**
 * Calculate average severity from multiple reports
 */
function calculateAverageSeverity($reports) {
    $severityMap = [
        'LOW' => 1,
        'MEDIUM' => 2,
        'HIGH' => 3,
        'CRITICAL' => 4
    ];
    
    $reverseMap = [1 => 'LOW', 2 => 'MEDIUM', 3 => 'HIGH', 4 => 'CRITICAL'];
    
    $total = 0;
    $count = 0;
    
    foreach ($reports as $r) {
        if (isset($r['severity']) && isset($severityMap[$r['severity']])) {
            $total += $severityMap[$r['severity']];
            $count++;
        }
    }
    
    if ($count === 0) return 'MEDIUM';
    
    $avg = round($total / $count);
    return $reverseMap[$avg] ?? 'MEDIUM';
}

/**
 * API endpoint for manual triangulation
 * Only run when accessed directly, not when included
 */
if (php_sapi_name() !== 'cli' && basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    header("Content-Type: application/json; charset=UTF-8");
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $reportId = isset($input['report_id']) ? intval($input['report_id']) : 0;
        
        if ($reportId === 0) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Report ID required']);
            exit;
        }
        
        $result = triangulateSource($reportId);
        echo json_encode($result, JSON_PRETTY_PRINT);
    } else {
        http_response_code(405);
        echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    }
}
?>

