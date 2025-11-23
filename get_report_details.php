<?php
// get_report_details.php - Get detailed information for a specific report including media

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Credentials: true");

require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/auth.php';

startSession();

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Authentication required']);
    exit;
}

// Check if user is admin or crew (managers)
$userRole = getUserRole();
if ($userRole !== 'admin' && $userRole !== 'crew') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Access denied']);
    exit;
}

// Get parameters
$reportId = isset($_GET['id']) ? (int)$_GET['id'] : null;
$reportType = isset($_GET['type']) ? $_GET['type'] : null;

if (!$reportId || !$reportType) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Report ID and type are required']);
    exit;
}

try {
    $pdo = getDBConnection();
    $report = null;

    if ($reportType === 'analysis') {
        // Fetch from analysis_reports
        $stmt = $pdo->prepare("
            SELECT
                ar.id,
                'analysis' as report_type,
                ar.top_hazard as title,
                ar.executive_conclusion as description,
                ar.severity,
                ar.category,
                ar.status,
                ar.latitude,
                ar.longitude,
                ar.address,
                ar.assigned_to_user_id,
                u.username as assigned_to_username,
                ar.eta_solved,
                ar.created_at,
                ar.timestamp
            FROM analysis_reports ar
            LEFT JOIN users u ON ar.assigned_to_user_id = u.id
            WHERE ar.id = ?
        ");
        $stmt->execute([$reportId]);
        $report = $stmt->fetch(PDO::FETCH_ASSOC);

        // Analysis reports don't have attached media files
        if ($report) {
            $report['image_file'] = null;
            $report['audio_file'] = null;
        }

    } elseif ($reportType === 'general') {
        // Fetch from general_reports
        $stmt = $pdo->prepare("
            SELECT
                gr.id,
                'general' as report_type,
                gr.title,
                gr.description,
                gr.severity,
                gr.category,
                gr.status,
                gr.latitude,
                gr.longitude,
                gr.address,
                gr.assigned_to_user_id,
                u.username as assigned_to_username,
                gr.created_at,
                gr.images,
                gr.audio_files
            FROM general_reports gr
            LEFT JOIN users u ON gr.assigned_to_user_id = u.id
            WHERE gr.id = ?
        ");
        $stmt->execute([$reportId]);
        $report = $stmt->fetch(PDO::FETCH_ASSOC);

        // Extract first image and audio file from JSON arrays
        if ($report) {
            $images = json_decode($report['images'], true);
            $audioFiles = json_decode($report['audio_files'], true);

            $report['image_file'] = (!empty($images) && is_array($images)) ? $images[0]['path'] : null;
            $report['audio_file'] = (!empty($audioFiles) && is_array($audioFiles)) ? $audioFiles[0]['path'] : null;

            // Clean up the JSON fields
            unset($report['images'], $report['audio_files']);
        }
    }

    if (!$report) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Report not found']);
        exit;
    }

    echo json_encode([
        'status' => 'success',
        'report' => $report
    ], JSON_PRETTY_PRINT);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
    error_log("❌ Error in get_report_details.php: " . $e->getMessage());
}
?>
