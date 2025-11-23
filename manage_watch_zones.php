<?php
// manage_watch_zones.php - CRUD API for user watch zones (proximity alerts)

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type");

require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/auth.php';

// Start session and check authentication
startSession();

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Authentication required']);
    exit;
}

$userId = getCurrentUserId();
$method = $_SERVER['REQUEST_METHOD'];

try {
    $pdo = getDBConnection();
    
    switch ($method) {
        case 'GET':
            // Get all watch zones for current user
            $stmt = $pdo->prepare("
                SELECT id, latitude, longitude, radius_meters, alert_frequency, created_at
                FROM user_watch_zones
                WHERE user_id = ?
                ORDER BY created_at DESC
            ");
            $stmt->execute([$userId]);
            $zones = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'status' => 'success',
                'watch_zones' => $zones
            ], JSON_PRETTY_PRINT);
            break;
            
        case 'POST':
            // Create new watch zone
            $input = json_decode(file_get_contents('php://input'), true);
            
            $latitude = isset($input['latitude']) ? floatval($input['latitude']) : null;
            $longitude = isset($input['longitude']) ? floatval($input['longitude']) : null;
            $radius = isset($input['radius_meters']) ? intval($input['radius_meters']) : 1000;
            $frequency = isset($input['alert_frequency']) ? $input['alert_frequency'] : 'realtime';
            
            if ($latitude === null || $longitude === null) {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'Latitude and longitude required']);
                exit;
            }
            
            if (!in_array($frequency, ['realtime', 'daily', 'weekly'])) {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'Invalid alert frequency']);
                exit;
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO user_watch_zones (user_id, latitude, longitude, radius_meters, alert_frequency)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$userId, $latitude, $longitude, $radius, $frequency]);
            $zoneId = $pdo->lastInsertId();
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Watch zone created',
                'zone_id' => $zoneId
            ], JSON_PRETTY_PRINT);
            
            error_log("✅ Watch zone created: User #$userId at ($latitude, $longitude) radius {$radius}m");
            break;
            
        case 'PUT':
            // Update watch zone
            $input = json_decode(file_get_contents('php://input'), true);
            
            $zoneId = isset($input['zone_id']) ? intval($input['zone_id']) : 0;
            $radius = isset($input['radius_meters']) ? intval($input['radius_meters']) : null;
            $frequency = isset($input['alert_frequency']) ? $input['alert_frequency'] : null;
            
            if ($zoneId === 0) {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'Zone ID required']);
                exit;
            }
            
            // Verify zone belongs to user
            $stmt = $pdo->prepare("SELECT id FROM user_watch_zones WHERE id = ? AND user_id = ?");
            $stmt->execute([$zoneId, $userId]);
            if (!$stmt->fetch()) {
                http_response_code(403);
                echo json_encode(['status' => 'error', 'message' => 'Zone not found or access denied']);
                exit;
            }
            
            // Update fields
            $updates = [];
            $params = [];
            
            if ($radius !== null) {
                $updates[] = "radius_meters = ?";
                $params[] = $radius;
            }
            
            if ($frequency !== null && in_array($frequency, ['realtime', 'daily', 'weekly'])) {
                $updates[] = "alert_frequency = ?";
                $params[] = $frequency;
            }
            
            if (empty($updates)) {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'No valid fields to update']);
                exit;
            }
            
            $params[] = $zoneId;
            $params[] = $userId;
            
            $sql = "UPDATE user_watch_zones SET " . implode(', ', $updates) . " WHERE id = ? AND user_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Watch zone updated'
            ], JSON_PRETTY_PRINT);
            break;
            
        case 'DELETE':
            // Delete watch zone
            $zoneId = isset($_GET['zone_id']) ? intval($_GET['zone_id']) : 0;
            
            if ($zoneId === 0) {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'Zone ID required']);
                exit;
            }
            
            $stmt = $pdo->prepare("DELETE FROM user_watch_zones WHERE id = ? AND user_id = ?");
            $stmt->execute([$zoneId, $userId]);
            
            if ($stmt->rowCount() > 0) {
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Watch zone deleted'
                ], JSON_PRETTY_PRINT);
            } else {
                http_response_code(404);
                echo json_encode(['status' => 'error', 'message' => 'Zone not found or access denied']);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    }
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
    error_log("❌ Error in manage_watch_zones.php: " . $e->getMessage());
}
?>

