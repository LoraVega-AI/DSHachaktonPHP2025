<?php
// get_route_points.php - Get route points for crew navigation

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/auth.php';

// Check authentication
startSession();
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Authentication required']);
    exit;
}

// Check if user is crew or admin
$userRole = getUserRole();
if ($userRole !== 'crew' && $userRole !== 'admin') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Access denied']);
    exit;
}

// Get parameters
$reportId = isset($_GET['report_id']) ? intval($_GET['report_id']) : 0;
$reportType = isset($_GET['report_type']) ? $_GET['report_type'] : 'general';
$currentLat = isset($_GET['current_lat']) ? floatval($_GET['current_lat']) : null;
$currentLng = isset($_GET['current_lng']) ? floatval($_GET['current_lng']) : null;

if ($reportId === 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Report ID required']);
    exit;
}

if ($currentLat === null || $currentLng === null) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Current location (current_lat, current_lng) required']);
    exit;
}

try {
    $pdo = getDBConnection();
    
    // Get target report details
    $table = $reportType === 'analysis' ? 'analysis_reports' : 'general_reports';
    $titleField = $reportType === 'analysis' ? 'top_hazard as title' : 'title';
    
    $stmt = $pdo->prepare("
        SELECT 
            id,
            $titleField,
            latitude,
            longitude,
            address,
            severity,
            category,
            status
        FROM $table 
        WHERE id = ?
    ");
    $stmt->execute([$reportId]);
    $targetReport = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$targetReport) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Report not found']);
        exit;
    }
    
    if ($targetReport['latitude'] === null || $targetReport['longitude'] === null) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Target report has no location data']);
        exit;
    }
    
    // Calculate distance to target using Haversine formula
    $targetDistance = calculateDistance(
        $currentLat, 
        $currentLng, 
        $targetReport['latitude'], 
        $targetReport['longitude']
    );
    
    // Get nearby high-priority unassigned reports (within 5km of the route)
    $nearbyReports = [];
    
    // Query both tables for high-priority unassigned reports
    $queries = [
        "SELECT 
            id,
            'analysis' as report_type,
            top_hazard as title,
            latitude,
            longitude,
            severity,
            category,
            address
        FROM analysis_reports 
        WHERE assigned_to_user_id IS NULL 
            AND status IN ('pending', 'verified')
            AND severity IN ('HIGH', 'CRITICAL')
            AND latitude IS NOT NULL 
            AND longitude IS NOT NULL
        LIMIT 10",
        
        "SELECT 
            id,
            'general' as report_type,
            title,
            latitude,
            longitude,
            severity,
            category,
            address
        FROM general_reports 
        WHERE assigned_to_user_id IS NULL 
            AND status IN ('pending', 'verified')
            AND severity IN ('HIGH', 'CRITICAL')
            AND latitude IS NOT NULL 
            AND longitude IS NOT NULL
        LIMIT 10"
    ];
    
    foreach ($queries as $query) {
        $stmt = $pdo->query($query);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($results as $report) {
            // Check if report is reasonably close to the route (within 5km)
            $distanceFromCurrent = calculateDistance(
                $currentLat,
                $currentLng,
                $report['latitude'],
                $report['longitude']
            );
            
            $distanceFromTarget = calculateDistance(
                $targetReport['latitude'],
                $targetReport['longitude'],
                $report['latitude'],
                $report['longitude']
            );
            
            // Simple heuristic: report is "on route" if combined distance isn't much more than direct distance
            $routeDeviation = ($distanceFromCurrent + $distanceFromTarget) - $targetDistance;
            
            if ($routeDeviation < 5000) { // Within 5km deviation
                $report['distance_from_current'] = $distanceFromCurrent;
                $report['distance_from_target'] = $distanceFromTarget;
                $nearbyReports[] = $report;
            }
        }
    }
    
    // Sort by distance from current location and take top 5
    usort($nearbyReports, function($a, $b) {
        return $a['distance_from_current'] <=> $b['distance_from_current'];
    });
    $nearbyReports = array_slice($nearbyReports, 0, 5);
    
    // Format response
    $response = [
        'status' => 'success',
        'current_location' => [
            'lat' => $currentLat,
            'lng' => $currentLng
        ],
        'target' => [
            'id' => $targetReport['id'],
            'type' => $reportType,
            'title' => $targetReport['title'],
            'lat' => floatval($targetReport['latitude']),
            'lng' => floatval($targetReport['longitude']),
            'address' => $targetReport['address'],
            'severity' => $targetReport['severity'],
            'category' => $targetReport['category'],
            'status' => $targetReport['status'],
            'distance_meters' => round($targetDistance),
            'distance_km' => round($targetDistance / 1000, 2)
        ],
        'nearby_priority' => array_map(function($report) {
            return [
                'id' => $report['id'],
                'type' => $report['report_type'],
                'title' => $report['title'],
                'lat' => floatval($report['latitude']),
                'lng' => floatval($report['longitude']),
                'severity' => $report['severity'],
                'category' => $report['category'],
                'address' => $report['address'],
                'distance_from_current_meters' => round($report['distance_from_current'])
            ];
        }, $nearbyReports)
    ];
    
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
    error_log("❌ Error in get_route_points.php: " . $e->getMessage());
}

/**
 * Calculate distance between two coordinates using Haversine formula
 * Returns distance in meters
 */
function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    $earthRadius = 6371000; // Earth radius in meters
    
    $lat1 = deg2rad($lat1);
    $lon1 = deg2rad($lon1);
    $lat2 = deg2rad($lat2);
    $lon2 = deg2rad($lon2);
    
    $dlat = $lat2 - $lat1;
    $dlon = $lon2 - $lon1;
    
    $a = sin($dlat / 2) * sin($dlat / 2) + 
         cos($lat1) * cos($lat2) * 
         sin($dlon / 2) * sin($dlon / 2);
    
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    
    return $earthRadius * $c;
}
?>

