<?php
// get_admin_analytics.php - Get comprehensive analytics for admin dashboard

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
    // Require admin role
    requireRole('admin');

    $pdo = getDBConnection();
    
    // Check if report tables exist
    $checkAnalysisReports = $pdo->query("SHOW TABLES LIKE 'analysis_reports'");
    $hasAnalysisReports = $checkAnalysisReports->rowCount() > 0;
    
    $checkGeneralReports = $pdo->query("SHOW TABLES LIKE 'general_reports'");
    $hasGeneralReports = $checkGeneralReports->rowCount() > 0;
    
    // Check if analysis_reports has created_at column, fallback to timestamp if not
    // This matches the approach used in get_detection_history.php
    $analysisDateColumn = "timestamp"; // Default
    if ($hasAnalysisReports) {
        $checkAnalysisCreatedAt = $pdo->query("SHOW COLUMNS FROM analysis_reports LIKE 'created_at'");
        if ($checkAnalysisCreatedAt->rowCount() > 0) {
            $analysisDateColumn = "created_at";
        }
    }
    
    if (!$hasAnalysisReports && !$hasGeneralReports) {
        // No report tables exist, return empty analytics
        echo json_encode([
            'status' => 'success',
            'analytics' => [
                'total_reports' => 0,
                'reports_today' => 0,
                'reports_this_week' => 0,
                'reports_this_month' => 0,
                'by_severity' => ['CRITICAL' => 0, 'HIGH' => 0, 'MEDIUM' => 0, 'LOW' => 0],
                'by_category' => [],
                'by_status' => ['pending' => 0, 'SOLVED' => 0, 'in_progress' => 0],
                'by_type' => ['analysis' => 0, 'general' => 0],
                'recent_activity' => [],
                'user_engagement' => ['active_users' => 0, 'total_user_reports' => 0],
                'top_contributors' => [],
                'avg_response_time_hours' => null
            ]
        ], JSON_PRETTY_PRINT);
        exit;
    }
    
    // Total reports from both tables
    $analysisCount = $hasAnalysisReports ? "(SELECT COUNT(*) FROM analysis_reports)" : "0";
    $generalCount = $hasGeneralReports ? "(SELECT COUNT(*) FROM general_reports)" : "0";
    $totalReportsQuery = "SELECT {$analysisCount} + {$generalCount} as total";
    $totalReportsStmt = $pdo->query($totalReportsQuery);
    $totalReports = $totalReportsStmt->fetchColumn() ?? 0;
    
    // Reports by severity
    if ($hasAnalysisReports && $hasGeneralReports) {
        $severityQuery = "SELECT severity, COUNT(*) as count FROM (
            SELECT severity FROM analysis_reports
            UNION ALL SELECT severity FROM general_reports
        ) as all_reports
        GROUP BY severity";
    } elseif ($hasAnalysisReports) {
        $severityQuery = "SELECT severity, COUNT(*) as count FROM analysis_reports GROUP BY severity";
    } elseif ($hasGeneralReports) {
        $severityQuery = "SELECT severity, COUNT(*) as count FROM general_reports GROUP BY severity";
    } else {
        $severityQuery = "SELECT 'NONE' as severity, 0 as count";
    }
    $severityStmt = $pdo->query($severityQuery);
    $severityData = $severityStmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Reports by category
    if ($hasAnalysisReports && $hasGeneralReports) {
        $categoryQuery = "SELECT category, COUNT(*) as count FROM (
            SELECT category FROM analysis_reports
            UNION ALL SELECT category FROM general_reports
        ) as all_reports
        GROUP BY category";
    } elseif ($hasAnalysisReports) {
        $categoryQuery = "SELECT category, COUNT(*) as count FROM analysis_reports GROUP BY category";
    } elseif ($hasGeneralReports) {
        $categoryQuery = "SELECT category, COUNT(*) as count FROM general_reports GROUP BY category";
    } else {
        $categoryQuery = "SELECT 'NONE' as category, 0 as count";
    }
    $categoryStmt = $pdo->query($categoryQuery);
    $categoryData = $categoryStmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Reports by status - use same approach as get_detection_history.php
    // Check if analysis_reports has status column (it might not exist in older tables)
    $checkAnalysisStatus = $pdo->query("SHOW COLUMNS FROM analysis_reports LIKE 'status'");
    $hasAnalysisStatus = $checkAnalysisStatus->rowCount() > 0;
    
    // Build status field for analysis_reports
    $analysisStatusField = $hasAnalysisStatus ? "COALESCE(status, 'pending')" : "'pending'";
    
    if ($hasAnalysisReports && $hasGeneralReports) {
        $statusQuery = "SELECT status, COUNT(*) as count FROM (
            SELECT {$analysisStatusField} as status FROM analysis_reports
            UNION ALL 
            SELECT COALESCE(status, 'pending') as status FROM general_reports
        ) as all_reports
        GROUP BY status";
    } elseif ($hasAnalysisReports) {
        $statusQuery = "SELECT {$analysisStatusField} as status, COUNT(*) as count FROM analysis_reports GROUP BY {$analysisStatusField}";
    } elseif ($hasGeneralReports) {
        $statusQuery = "SELECT COALESCE(status, 'pending') as status, COUNT(*) as count FROM general_reports GROUP BY COALESCE(status, 'pending')";
    } else {
        $statusQuery = "SELECT 'pending' as status, 0 as count";
    }
    $statusStmt = $pdo->query($statusQuery);
    $statusData = $statusStmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Reports by type
    $analysisTypeCount = $hasAnalysisReports ? "(SELECT COUNT(*) FROM analysis_reports)" : "0";
    $generalTypeCount = $hasGeneralReports ? "(SELECT COUNT(*) FROM general_reports)" : "0";
    $typeQuery = "SELECT 
        {$analysisTypeCount} as analysis_count,
        {$generalTypeCount} as general_count";
    $typeStmt = $pdo->query($typeQuery);
    $typeData = $typeStmt->fetch(PDO::FETCH_ASSOC);
    
    // Recent activity (last 7 days) - use same date column approach
    if ($hasAnalysisReports && $hasGeneralReports) {
        $recentQuery = "SELECT DATE(date_col) as date, COUNT(*) as count FROM (
            SELECT {$analysisDateColumn} as date_col FROM analysis_reports WHERE {$analysisDateColumn} >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            UNION ALL SELECT created_at as date_col FROM general_reports WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ) as all_reports
        GROUP BY DATE(date_col)
        ORDER BY date DESC";
    } elseif ($hasAnalysisReports) {
        $recentQuery = "SELECT DATE({$analysisDateColumn}) as date, COUNT(*) as count FROM analysis_reports 
            WHERE {$analysisDateColumn} >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY DATE({$analysisDateColumn})
            ORDER BY date DESC";
    } elseif ($hasGeneralReports) {
        $recentQuery = "SELECT DATE(created_at) as date, COUNT(*) as count FROM general_reports 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY DATE(created_at)
            ORDER BY date DESC";
    } else {
        $recentQuery = "SELECT CURDATE() as date, 0 as count";
    }
    $recentStmt = $pdo->query($recentQuery);
    $recentActivity = $recentStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Reports today - use same approach as get_detection_history.php
    if ($hasAnalysisReports && $hasGeneralReports) {
        $todayQuery = "SELECT COUNT(*) as count FROM (
            SELECT id FROM analysis_reports WHERE DATE({$analysisDateColumn}) = CURDATE()
            UNION ALL SELECT id FROM general_reports WHERE DATE(created_at) = CURDATE()
        ) as today_reports";
    } elseif ($hasAnalysisReports) {
        $todayQuery = "SELECT COUNT(*) FROM analysis_reports WHERE DATE({$analysisDateColumn}) = CURDATE()";
    } elseif ($hasGeneralReports) {
        $todayQuery = "SELECT COUNT(*) FROM general_reports WHERE DATE(created_at) = CURDATE()";
    } else {
        $todayQuery = "SELECT 0";
    }
    $todayStmt = $pdo->query($todayQuery);
    $reportsToday = $todayStmt->fetchColumn() ?? 0;
    
    // Reports this week - use same date column approach
    if ($hasAnalysisReports && $hasGeneralReports) {
        $weekQuery = "SELECT COUNT(*) as count FROM (
            SELECT id FROM analysis_reports WHERE {$analysisDateColumn} >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            UNION ALL SELECT id FROM general_reports WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ) as week_reports";
    } elseif ($hasAnalysisReports) {
        $weekQuery = "SELECT COUNT(*) FROM analysis_reports WHERE {$analysisDateColumn} >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
    } elseif ($hasGeneralReports) {
        $weekQuery = "SELECT COUNT(*) FROM general_reports WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
    } else {
        $weekQuery = "SELECT 0";
    }
    $weekStmt = $pdo->query($weekQuery);
    $reportsThisWeek = $weekStmt->fetchColumn() ?? 0;
    
    // Reports this month - use same date column approach
    if ($hasAnalysisReports && $hasGeneralReports) {
        $monthQuery = "SELECT COUNT(*) as count FROM (
            SELECT id FROM analysis_reports WHERE {$analysisDateColumn} >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            UNION ALL SELECT id FROM general_reports WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ) as month_reports";
    } elseif ($hasAnalysisReports) {
        $monthQuery = "SELECT COUNT(*) FROM analysis_reports WHERE {$analysisDateColumn} >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
    } elseif ($hasGeneralReports) {
        $monthQuery = "SELECT COUNT(*) FROM general_reports WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
    } else {
        $monthQuery = "SELECT 0";
    }
    $monthStmt = $pdo->query($monthQuery);
    $reportsThisMonth = $monthStmt->fetchColumn() ?? 0;
    
    // User engagement stats
    if ($hasAnalysisReports && $hasGeneralReports) {
        $userEngagementQuery = "SELECT 
            COUNT(DISTINCT user_id) as active_users,
            COUNT(*) as total_user_reports
        FROM (
            SELECT user_id FROM analysis_reports WHERE user_id IS NOT NULL
            UNION ALL SELECT user_id FROM general_reports WHERE user_id IS NOT NULL
        ) as user_reports";
    } elseif ($hasAnalysisReports) {
        $userEngagementQuery = "SELECT 
            COUNT(DISTINCT user_id) as active_users,
            COUNT(*) as total_user_reports
        FROM analysis_reports WHERE user_id IS NOT NULL";
    } elseif ($hasGeneralReports) {
        $userEngagementQuery = "SELECT 
            COUNT(DISTINCT user_id) as active_users,
            COUNT(*) as total_user_reports
        FROM general_reports WHERE user_id IS NOT NULL";
    } else {
        $userEngagementQuery = "SELECT 0 as active_users, 0 as total_user_reports";
    }
    $userEngagementStmt = $pdo->query($userEngagementQuery);
    $userEngagement = $userEngagementStmt->fetch(PDO::FETCH_ASSOC);
    
    // Top contributors
    if ($hasAnalysisReports && $hasGeneralReports) {
        $topContributorsQuery = "SELECT 
            u.id, u.username, u.email, u.role, 
            COUNT(r.user_id) as report_count
        FROM users u
        INNER JOIN (
            SELECT user_id FROM analysis_reports WHERE user_id IS NOT NULL
            UNION ALL SELECT user_id FROM general_reports WHERE user_id IS NOT NULL
        ) as r ON u.id = r.user_id
        GROUP BY u.id, u.username, u.email, u.role
        ORDER BY report_count DESC
        LIMIT 10";
    } elseif ($hasAnalysisReports) {
        $topContributorsQuery = "SELECT 
            u.id, u.username, u.email, u.role, 
            COUNT(ar.user_id) as report_count
        FROM users u
        INNER JOIN analysis_reports ar ON u.id = ar.user_id
        WHERE ar.user_id IS NOT NULL
        GROUP BY u.id, u.username, u.email, u.role
        ORDER BY report_count DESC
        LIMIT 10";
    } elseif ($hasGeneralReports) {
        $topContributorsQuery = "SELECT 
            u.id, u.username, u.email, u.role, 
            COUNT(gr.user_id) as report_count
        FROM users u
        INNER JOIN general_reports gr ON u.id = gr.user_id
        WHERE gr.user_id IS NOT NULL
        GROUP BY u.id, u.username, u.email, u.role
        ORDER BY report_count DESC
        LIMIT 10";
    } else {
        $topContributorsQuery = "SELECT u.id, u.username, u.email, u.role, 0 as report_count FROM users u LIMIT 0";
    }
    $topContributorsStmt = $pdo->query($topContributorsQuery);
    $topContributors = $topContributorsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Average response time (for solved reports) - check if updated_at exists
    $checkAnalysisUpdatedAt = $pdo->query("SHOW COLUMNS FROM analysis_reports LIKE 'updated_at'");
    $analysisHasUpdatedAt = $checkAnalysisUpdatedAt->rowCount() > 0;
    
    // Use the same status field approach as above
    if ($hasAnalysisReports && $hasGeneralReports && $analysisHasUpdatedAt) {
        $avgResponseQuery = "SELECT 
            AVG(TIMESTAMPDIFF(HOUR, date_col, updated_at)) as avg_hours
        FROM (
            SELECT {$analysisDateColumn} as date_col, updated_at FROM analysis_reports 
            WHERE {$analysisStatusField} = 'SOLVED' AND updated_at IS NOT NULL
            UNION ALL SELECT created_at as date_col, updated_at FROM general_reports 
            WHERE COALESCE(status, 'pending') = 'SOLVED' AND updated_at IS NOT NULL
        ) as solved_reports";
    } elseif ($hasAnalysisReports && $analysisHasUpdatedAt) {
        $avgResponseQuery = "SELECT 
            AVG(TIMESTAMPDIFF(HOUR, {$analysisDateColumn}, updated_at)) as avg_hours
        FROM analysis_reports 
        WHERE {$analysisStatusField} = 'SOLVED' AND updated_at IS NOT NULL";
    } elseif ($hasGeneralReports) {
        $avgResponseQuery = "SELECT 
            AVG(TIMESTAMPDIFF(HOUR, created_at, updated_at)) as avg_hours
        FROM general_reports 
        WHERE COALESCE(status, 'pending') = 'SOLVED' AND updated_at IS NOT NULL";
    } else {
        $avgResponseQuery = "SELECT NULL as avg_hours";
    }
    $avgResponseStmt = $pdo->query($avgResponseQuery);
    $avgResponseTime = $avgResponseStmt->fetchColumn();
    
    echo json_encode([
        'status' => 'success',
        'analytics' => [
            'total_reports' => intval($totalReports),
            'reports_today' => intval($reportsToday),
            'reports_this_week' => intval($reportsThisWeek),
            'reports_this_month' => intval($reportsThisMonth),
            'by_severity' => [
                'CRITICAL' => intval($severityData['CRITICAL'] ?? 0),
                'HIGH' => intval($severityData['HIGH'] ?? 0),
                'MEDIUM' => intval($severityData['MEDIUM'] ?? 0),
                'LOW' => intval($severityData['LOW'] ?? 0)
            ],
            'by_category' => $categoryData,
            'by_status' => [
                'pending' => intval($statusData['pending'] ?? 0),
                'SOLVED' => intval($statusData['SOLVED'] ?? 0),
                'in_progress' => intval($statusData['in_progress'] ?? 0)
            ],
            'by_type' => [
                'analysis' => intval($typeData['analysis_count']),
                'general' => intval($typeData['general_count'])
            ],
            'recent_activity' => $recentActivity,
            'user_engagement' => [
                'active_users' => intval($userEngagement['active_users'] ?? 0),
                'total_user_reports' => intval($userEngagement['total_user_reports'] ?? 0)
            ],
            'top_contributors' => $topContributors,
            'avg_response_time_hours' => $avgResponseTime ? round(floatval($avgResponseTime), 1) : null
        ]
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'debug' => [
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ], JSON_PRETTY_PRINT);
    error_log("Error in get_admin_analytics.php: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
}
?>

