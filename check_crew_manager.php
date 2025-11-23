<?php
// check_crew_manager.php - Check if current crew user is a manager (can access crew management)

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Credentials: true");

require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/auth.php';

startSession();

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Authentication required', 'is_manager' => false]);
    exit;
}

$userRole = getUserRole();

// Admin is always a manager
if ($userRole === 'admin') {
    echo json_encode([
        'status' => 'success',
        'is_manager' => true,
        'role' => $userRole
    ]);
    exit;
}

// For crew users, check if they can access crew management
// Currently, all crew users can access crew management, so they're managers
// In the future, this could check a database field like is_manager
if ($userRole === 'crew') {
    // Check if user has accessed crew_dashboard or crew_management (manager pages)
    // For now, all crew users are considered managers unless they're specifically on crew_member_dashboard
    $currentPage = $_GET['page'] ?? '';
    
    // If explicitly on member dashboard, they're a member
    if ($currentPage === 'crew_member_dashboard') {
        echo json_encode([
            'status' => 'success',
            'is_manager' => false,
            'role' => $userRole
        ]);
        exit;
    }
    
    // Otherwise, crew users are managers
    echo json_encode([
        'status' => 'success',
        'is_manager' => true,
        'role' => $userRole
    ]);
    exit;
}

// Other roles are not managers
echo json_encode([
    'status' => 'success',
    'is_manager' => false,
    'role' => $userRole
]);
?>

