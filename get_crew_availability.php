<?php
// get_crew_availability.php - Get crew member availability status

require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/auth.php';

/**
 * Check if a crew member is currently available
 * 
 * @param int $userId User ID
 * @return array Availability status
 */
function getCrewAvailability($userId) {
    try {
        $pdo = getDBConnection();
        
        // Get current day of week (0=Sunday, 1=Monday, etc.)
        $currentDay = (int)date('w');
        $currentTime = date('H:i:s');
        
        // Get today's schedule
        $stmt = $pdo->prepare("
            SELECT start_time, end_time, is_available
            FROM crew_schedule
            WHERE user_id = ? AND day_of_week = ?
        ");
        $stmt->execute([$userId, $currentDay]);
        $schedule = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$schedule) {
            return [
                'is_available' => false,
                'status' => 'no_schedule',
                'message' => 'No schedule set for today',
                'current_time' => $currentTime,
                'day_of_week' => $currentDay
            ];
        }
        
        if (!$schedule['is_available']) {
            return [
                'is_available' => false,
                'status' => 'unavailable',
                'message' => 'Marked as unavailable today',
                'current_time' => $currentTime,
                'schedule' => $schedule
            ];
        }
        
        // Check if current time is within working hours
        $startTime = $schedule['start_time'];
        $endTime = $schedule['end_time'];
        
        $isInWorkingHours = ($currentTime >= $startTime && $currentTime <= $endTime);
        
        return [
            'is_available' => $isInWorkingHours && $schedule['is_available'],
            'status' => $isInWorkingHours ? 'available' : 'off_hours',
            'message' => $isInWorkingHours ? 'Currently available' : 'Outside working hours',
            'current_time' => $currentTime,
            'working_hours' => [
                'start' => $startTime,
                'end' => $endTime
            ],
            'schedule' => $schedule
        ];
        
    } catch (PDOException $e) {
        error_log("❌ Availability check error: " . $e->getMessage());
        return [
            'is_available' => false,
            'status' => 'error',
            'message' => $e->getMessage()
        ];
    }
}

/**
 * Get full weekly schedule for a crew member
 * 
 * @param int $userId User ID
 * @return array Weekly schedule
 */
function getCrewWeeklySchedule($userId) {
    try {
        $pdo = getDBConnection();
        
        $stmt = $pdo->prepare("
            SELECT day_of_week, start_time, end_time, is_available
            FROM crew_schedule
            WHERE user_id = ?
            ORDER BY day_of_week ASC
        ");
        $stmt->execute([$userId]);
        $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        
        $weeklySchedule = [];
        foreach ($schedules as $schedule) {
            $day = intval($schedule['day_of_week']);
            $weeklySchedule[$day] = [
                'day_name' => $dayNames[$day],
                'day_number' => $day,
                'start_time' => $schedule['start_time'],
                'end_time' => $schedule['end_time'],
                'is_available' => (bool)$schedule['is_available']
            ];
        }
        
        return [
            'status' => 'success',
            'weekly_schedule' => $weeklySchedule
        ];
        
    } catch (PDOException $e) {
        error_log("❌ Weekly schedule error: " . $e->getMessage());
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
    header("Access-Control-Allow-Origin: *");
    
    startSession();
    
    if (!isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Authentication required']);
        exit;
    }
    
    $userRole = getUserRole();
    if ($userRole !== 'admin' && $userRole !== 'crew') {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Access denied']);
        exit;
    }
    
    $userId = isset($_GET['user_id']) ? intval($_GET['user_id']) : getCurrentUserId();
    $weekly = isset($_GET['weekly']) && $_GET['weekly'] === 'true';
    
    if ($weekly) {
        $result = getCrewWeeklySchedule($userId);
    } else {
        $result = getCrewAvailability($userId);
    }
    
    echo json_encode($result, JSON_PRETTY_PRINT);
}
?>

