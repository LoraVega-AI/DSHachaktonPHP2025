<?php
// get_crew_members.php - Get list of all crew members for assignment dropdown

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/auth.php';

// Start session and check authentication
startSession();

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Authentication required']);
    exit;
}

// Check if user is admin or crew (crew can see other crew members)
$userRole = getUserRole();
if ($userRole !== 'admin' && $userRole !== 'crew') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Access denied']);
    exit;
}

try {
    $pdo = getDBConnection();
    
    // Get all crew members and admins
    $stmt = $pdo->query("
        SELECT 
            id,
            username,
            email,
            role,
            trust_score,
            created_at
        FROM users 
        WHERE role IN ('crew', 'admin')
        ORDER BY role DESC, username ASC
    ");
    
    $crewMembers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get assignment counts and availability for each crew member
    require_once __DIR__ . '/get_crew_availability.php';
    
    foreach ($crewMembers as &$member) {
        // Count assigned reports from both tables
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count FROM (
                SELECT id FROM analysis_reports WHERE assigned_to_user_id = ? AND status NOT IN ('solved', 'verified')
                UNION ALL
                SELECT id FROM general_reports WHERE assigned_to_user_id = ? AND status NOT IN ('solved', 'verified')
            ) as combined
        ");
        $stmt->execute([$member['id'], $member['id']]);
        $count = $stmt->fetch(PDO::FETCH_ASSOC);
        $member['active_assignments'] = intval($count['count']);
        
        // Get availability status
        $availability = getCrewAvailability($member['id']);
        $member['is_available'] = $availability['is_available'];
        $member['availability_status'] = $availability['status'];
        $member['availability_message'] = $availability['message'];
        
        // Get working hours for today
        if (isset($availability['working_hours'])) {
            $member['working_hours'] = $availability['working_hours'];
        }
    }
    
    echo json_encode([
        'status' => 'success',
        'crew_members' => $crewMembers
    ], JSON_PRETTY_PRINT);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
    error_log("❌ Error in get_crew_members.php: " . $e->getMessage());
}
?>

