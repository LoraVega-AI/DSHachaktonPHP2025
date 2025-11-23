<?php
// get_user_details.php - Get detailed user information including reports (admin or own profile)

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Credentials: true");

require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/auth.php';

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Only GET requests are allowed');
    }

    // Require authentication
    if (!isLoggedIn()) {
        throw new Exception('Authentication required');
    }

    $currentUserId = getCurrentUserId();
    $currentUserRole = getUserRole();
    
    // Get requested user ID from query parameter
    $requestedUserId = isset($_GET['user_id']) ? intval($_GET['user_id']) : $currentUserId;
    
    // Check permissions: only admins can view other users' details
    if ($requestedUserId !== $currentUserId && $currentUserRole !== 'admin') {
        throw new Exception('Permission denied. Only admins can view other users\' details.');
    }

    $pdo = getDBConnection();
    
    // Get user details
    $userStmt = $pdo->prepare("
        SELECT id, username, email, role, created_at, profile_img, bio
        FROM users 
        WHERE id = :user_id
    ");
    $userStmt->execute([':user_id' => $requestedUserId]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        throw new Exception('User not found');
    }
    
    // Check if columns exist in tables
    $checkAnalysisStatus = $pdo->query("SHOW COLUMNS FROM analysis_reports LIKE 'status'");
    $hasAnalysisStatus = $checkAnalysisStatus->rowCount() > 0;
    
    $checkAnalysisUserId = $pdo->query("SHOW COLUMNS FROM analysis_reports LIKE 'user_id'");
    $hasAnalysisUserId = $checkAnalysisUserId->rowCount() > 0;
    
    $checkGeneralUserId = $pdo->query("SHOW COLUMNS FROM general_reports LIKE 'user_id'");
    $hasGeneralUserId = $checkGeneralUserId->rowCount() > 0;
    
    // Get user's reports from both tables
    $reports = [];
    
    // Get analysis reports if user_id column exists
    if ($hasAnalysisUserId) {
        try {
            $analysisStatusField = $hasAnalysisStatus ? "COALESCE(ar.status, 'pending')" : "'pending'";
            $analysisStmt = $pdo->prepare("
                SELECT 
                    ar.id,
                    'analysis' as report_type,
                    COALESCE(ar.signature_name, ar.top_hazard, 'Analysis Report') as title,
                    COALESCE(ar.classification, ar.executive_conclusion, 'No description') as description,
                    COALESCE(ar.severity, 'MEDIUM') as severity,
                    COALESCE(ar.category, 'General') as category,
                    COALESCE(ar.created_at, ar.timestamp) as created_at,
                    {$analysisStatusField} as status,
                    ar.latitude,
                    ar.longitude,
                    ar.address
                FROM analysis_reports ar
                WHERE ar.user_id = :user_id
            ");
            $analysisStmt->execute([':user_id' => $requestedUserId]);
            $analysisReports = $analysisStmt->fetchAll(PDO::FETCH_ASSOC);
            $reports = array_merge($reports, $analysisReports);
        } catch (PDOException $e) {
            error_log("Error fetching analysis reports: " . $e->getMessage());
            // Continue with general reports even if analysis fails
        }
    }
    
    // Get general reports if user_id column exists
    if ($hasGeneralUserId) {
        try {
            $generalStmt = $pdo->prepare("
                SELECT 
                    gr.id,
                    'general' as report_type,
                    COALESCE(gr.title, 'General Report') as title,
                    COALESCE(gr.description, 'No description') as description,
                    COALESCE(gr.severity, 'MEDIUM') as severity,
                    COALESCE(gr.category, 'General') as category,
                    gr.created_at,
                    COALESCE(gr.status, 'pending') as status,
                    gr.latitude,
                    gr.longitude,
                    gr.address
                FROM general_reports gr
                WHERE gr.user_id = :user_id
            ");
            $generalStmt->execute([':user_id' => $requestedUserId]);
            $generalReports = $generalStmt->fetchAll(PDO::FETCH_ASSOC);
            $reports = array_merge($reports, $generalReports);
        } catch (PDOException $e) {
            error_log("Error fetching general reports: " . $e->getMessage());
        }
    }
    
    // Sort reports by created_at descending
    usort($reports, function($a, $b) {
        $dateA = strtotime($a['created_at'] ?? '1970-01-01');
        $dateB = strtotime($b['created_at'] ?? '1970-01-01');
        return $dateB - $dateA;
    });
    
    // Calculate statistics from the reports we fetched
    $stats = [
        'total_reports' => count($reports),
        'solved_reports' => 0,
        'pending_reports' => 0,
        'critical_reports' => 0,
        'high_reports' => 0
    ];
    
    foreach ($reports as $report) {
        $status = strtoupper($report['status'] ?? 'pending');
        $severity = strtoupper($report['severity'] ?? 'MEDIUM');
        
        if ($status === 'SOLVED') {
            $stats['solved_reports']++;
        } else {
            $stats['pending_reports']++;
        }
        
        if ($severity === 'CRITICAL') {
            $stats['critical_reports']++;
        } elseif ($severity === 'HIGH') {
            $stats['high_reports']++;
        }
    }
    
    // Format reports for output
    $formattedReports = array_map(function($report) {
        return [
            'id' => intval($report['id']),
            'report_type' => $report['report_type'],
            'title' => $report['title'],
            'description' => $report['description'],
            'severity' => $report['severity'],
            'category' => $report['category'],
            'created_at' => $report['created_at'],
            'status' => $report['status'],
            'latitude' => $report['latitude'] ? floatval($report['latitude']) : null,
            'longitude' => $report['longitude'] ? floatval($report['longitude']) : null,
            'address' => $report['address']
        ];
    }, $reports);
    
    echo json_encode([
        'status' => 'success',
        'user' => [
            'id' => intval($user['id']),
            'username' => $user['username'],
            'email' => $user['email'],
            'role' => $user['role'],
            'created_at' => $user['created_at'],
            'profile_img' => $user['profile_img'],
            'bio' => $user['bio']
        ],
        'statistics' => [
            'total_reports' => intval($stats['total_reports'] ?? 0),
            'solved_reports' => intval($stats['solved_reports'] ?? 0),
            'pending_reports' => intval($stats['pending_reports'] ?? 0),
            'critical_reports' => intval($stats['critical_reports'] ?? 0),
            'high_reports' => intval($stats['high_reports'] ?? 0)
        ],
        'reports' => $formattedReports
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
    error_log("Error in get_user_details.php: " . $e->getMessage());
}
?>

