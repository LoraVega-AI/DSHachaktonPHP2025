<?php
// manage_crew_schedule.php - CRUD API for crew member schedules

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

$userRole = getUserRole();
$currentUserId = getCurrentUserId();
$method = $_SERVER['REQUEST_METHOD'];

try {
    $pdo = getDBConnection();
    
    switch ($method) {
        case 'GET':
            // Get schedule for a user
            $userId = isset($_GET['user_id']) ? intval($_GET['user_id']) : $currentUserId;
            
            // Crew can only view their own schedule, admin can view any
            if ($userRole === 'crew' && $userId !== $currentUserId) {
                http_response_code(403);
                echo json_encode(['status' => 'error', 'message' => 'Can only view own schedule']);
                exit;
            }
            
            require_once __DIR__ . '/get_crew_availability.php';
            $result = getCrewWeeklySchedule($userId);
            echo json_encode($result, JSON_PRETTY_PRINT);
            break;
            
        case 'POST':
            // Create or update schedule entry
            $input = json_decode(file_get_contents('php://input'), true);
            
            $userId = isset($input['user_id']) ? intval($input['user_id']) : $currentUserId;
            $dayOfWeek = isset($input['day_of_week']) ? intval($input['day_of_week']) : null;
            $startTime = isset($input['start_time']) ? $input['start_time'] : null;
            $endTime = isset($input['end_time']) ? $input['end_time'] : null;
            $isAvailable = isset($input['is_available']) ? (bool)$input['is_available'] : true;
            
            // Crew can only update their own schedule, admin can update any
            if ($userRole === 'crew' && $userId !== $currentUserId) {
                http_response_code(403);
                echo json_encode(['status' => 'error', 'message' => 'Can only update own schedule']);
                exit;
            }
            
            if ($dayOfWeek === null || $startTime === null || $endTime === null) {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'day_of_week, start_time, and end_time required']);
                exit;
            }
            
            if ($dayOfWeek < 0 || $dayOfWeek > 6) {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'day_of_week must be 0-6 (Sunday-Saturday)']);
                exit;
            }
            
            // Verify user is crew
            $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user || ($user['role'] !== 'crew' && $user['role'] !== 'admin')) {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'User must be crew or admin']);
                exit;
            }
            
            // Insert or update (ON DUPLICATE KEY UPDATE)
            $stmt = $pdo->prepare("
                INSERT INTO crew_schedule (user_id, day_of_week, start_time, end_time, is_available)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    start_time = VALUES(start_time),
                    end_time = VALUES(end_time),
                    is_available = VALUES(is_available),
                    updated_at = NOW()
            ");
            $stmt->execute([$userId, $dayOfWeek, $startTime, $endTime, $isAvailable ? 1 : 0]);
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Schedule updated',
                'user_id' => $userId,
                'day_of_week' => $dayOfWeek
            ], JSON_PRETTY_PRINT);
            break;
            
        case 'PUT':
            // Bulk update schedule (set entire week)
            $input = json_decode(file_get_contents('php://input'), true);
            
            $userId = isset($input['user_id']) ? intval($input['user_id']) : $currentUserId;
            $schedule = isset($input['schedule']) ? $input['schedule'] : null;
            
            // Crew can only update their own schedule
            if ($userRole === 'crew' && $userId !== $currentUserId) {
                http_response_code(403);
                echo json_encode(['status' => 'error', 'message' => 'Can only update own schedule']);
                exit;
            }
            
            if (!$schedule || !is_array($schedule)) {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'Schedule array required']);
                exit;
            }
            
            // Delete existing schedule
            $stmt = $pdo->prepare("DELETE FROM crew_schedule WHERE user_id = ?");
            $stmt->execute([$userId]);
            
            // Insert new schedule entries
            $insertStmt = $pdo->prepare("
                INSERT INTO crew_schedule (user_id, day_of_week, start_time, end_time, is_available)
                VALUES (?, ?, ?, ?, ?)
            ");
            
            $inserted = 0;
            foreach ($schedule as $day) {
                if (isset($day['day_of_week']) && isset($day['start_time']) && isset($day['end_time'])) {
                    $insertStmt->execute([
                        $userId,
                        intval($day['day_of_week']),
                        $day['start_time'],
                        $day['end_time'],
                        isset($day['is_available']) ? ($day['is_available'] ? 1 : 0) : 1
                    ]);
                    $inserted++;
                }
            }
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Schedule updated',
                'user_id' => $userId,
                'days_updated' => $inserted
            ], JSON_PRETTY_PRINT);
            break;
            
        case 'DELETE':
            // Delete schedule entry
            $userId = isset($_GET['user_id']) ? intval($_GET['user_id']) : $currentUserId;
            $dayOfWeek = isset($_GET['day_of_week']) ? intval($_GET['day_of_week']) : null;
            
            // Crew can only delete their own schedule
            if ($userRole === 'crew' && $userId !== $currentUserId) {
                http_response_code(403);
                echo json_encode(['status' => 'error', 'message' => 'Can only delete own schedule']);
                exit;
            }
            
            if ($dayOfWeek === null) {
                // Delete entire schedule
                $stmt = $pdo->prepare("DELETE FROM crew_schedule WHERE user_id = ?");
                $stmt->execute([$userId]);
            } else {
                // Delete specific day
                $stmt = $pdo->prepare("DELETE FROM crew_schedule WHERE user_id = ? AND day_of_week = ?");
                $stmt->execute([$userId, $dayOfWeek]);
            }
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Schedule deleted'
            ], JSON_PRETTY_PRINT);
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
    error_log("❌ Error in manage_crew_schedule.php: " . $e->getMessage());
}
?>

