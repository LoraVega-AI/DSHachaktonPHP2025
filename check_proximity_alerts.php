<?php
// check_proximity_alerts.php - Check for proximity alerts and trigger notifications

require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/broadcast_helper.php';

/**
 * Check if new report triggers any proximity alerts
 * 
 * @param int $reportId Report ID
 * @param string $reportType 'analysis' or 'general'
 * @param float $latitude Report latitude
 * @param float $longitude Report longitude
 * @param string $severity Report severity
 * @return array Alert results
 */
function checkProximityAlerts($reportId, $reportType, $latitude, $longitude, $severity) {
    try {
        $pdo = getDBConnection();
        
        // Only trigger alerts for HIGH and CRITICAL severity
        if (!in_array($severity, ['HIGH', 'CRITICAL'])) {
            return [
                'status' => 'skipped',
                'message' => 'Severity not high enough for proximity alerts',
                'alerts_triggered' => 0
            ];
        }
        
        // Find all watch zones that contain this report location
        $sql = "SELECT 
                    uwz.id as zone_id,
                    uwz.user_id,
                    uwz.latitude as zone_lat,
                    uwz.longitude as zone_lng,
                    uwz.radius_meters,
                    uwz.alert_frequency,
                    u.username,
                    u.email,
                    (6371000 * acos(
                        cos(radians(:lat)) * cos(radians(uwz.latitude)) * 
                        cos(radians(uwz.longitude) - radians(:lng)) + 
                        sin(radians(:lat)) * sin(radians(uwz.latitude))
                    )) as distance_meters
                FROM user_watch_zones uwz
                JOIN users u ON uwz.user_id = u.id
                HAVING distance_meters <= uwz.radius_meters
                ORDER BY distance_meters ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':lat' => $latitude,
            ':lng' => $longitude
        ]);
        
        $alertZones = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($alertZones) === 0) {
            return [
                'status' => 'no_alerts',
                'message' => 'No watch zones triggered',
                'alerts_triggered' => 0
            ];
        }
        
        // Trigger alerts for realtime zones via SSE
        $alertsTriggered = 0;
        
        foreach ($alertZones as $zone) {
            if ($zone['alert_frequency'] === 'realtime') {
                // Broadcast alert via SSE
                broadcastEvent('proximity_alert', [
                    'user_id' => $zone['user_id'],
                    'zone_id' => $zone['zone_id'],
                    'report_id' => $reportId,
                    'report_type' => $reportType,
                    'severity' => $severity,
                    'distance_meters' => round($zone['distance_meters']),
                    'message' => "New $severity severity report within your watch zone"
                ]);
                
                $alertsTriggered++;
                error_log("🔔 Proximity alert: User #{$zone['user_id']} notified about $reportType report #$reportId");
            }
            
            // For daily/weekly, we would queue for batch processing
            // (Not implemented in this version, would require a cron job)
        }
        
        return [
            'status' => 'success',
            'message' => 'Proximity alerts triggered',
            'alerts_triggered' => $alertsTriggered,
            'zones_matched' => count($alertZones)
        ];
        
    } catch (PDOException $e) {
        error_log("❌ Proximity alert error: " . $e->getMessage());
        return [
            'status' => 'error',
            'message' => $e->getMessage(),
            'alerts_triggered' => 0
        ];
    }
}
?>

