<?php
// get_map_reports.php - Fetch reports with location data for map display

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type");

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

try {
    require_once __DIR__ . '/db_config.php';
    require_once __DIR__ . '/auth.php';
    
    $pdo = getDBConnection();
    
    // Ensure user_id column exists in analysis_reports (migration check)
    try {
        $columnCheck = $pdo->query("SHOW COLUMNS FROM analysis_reports LIKE 'user_id'");
        if ($columnCheck->rowCount() === 0) {
            // Ensure users table exists first
            $usersTableCheck = $pdo->query("SHOW TABLES LIKE 'users'");
            if ($usersTableCheck->rowCount() === 0) {
                $createUsersSql = "CREATE TABLE IF NOT EXISTS users (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    username VARCHAR(100) NOT NULL UNIQUE,
                    email VARCHAR(255) NOT NULL UNIQUE,
                    password_hash VARCHAR(255) NOT NULL,
                    role ENUM('guest', 'user', 'admin') NOT NULL DEFAULT 'user',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_username (username),
                    INDEX idx_email (email),
                    INDEX idx_role (role)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
                $pdo->exec($createUsersSql);
            }
            
            // Add user_id and is_anonymous columns
            $pdo->exec("ALTER TABLE analysis_reports ADD COLUMN user_id INT DEFAULT NULL AFTER id");
            $pdo->exec("ALTER TABLE analysis_reports ADD COLUMN is_anonymous BOOLEAN DEFAULT FALSE AFTER user_id");
            $pdo->exec("ALTER TABLE analysis_reports ADD INDEX idx_user_id (user_id)");
            
            // Try to add foreign key (may fail if users table doesn't exist, but that's ok)
            try {
                $pdo->exec("ALTER TABLE analysis_reports ADD FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL");
            } catch (PDOException $fkError) {
                // Ignore foreign key errors
            }
        }
    } catch (PDOException $e) {
        error_log("Error checking/adding user_id column: " . $e->getMessage());
    }
    
    // Check user role and ID
    $userRole = getUserRole(); // guest, user, or admin
    $currentUserId = getCurrentUserId();
    
    // Get limit from query parameter (default 1000, max 5000)
    $limit = isset($_GET['limit']) ? min(intval($_GET['limit']), 5000) : 1000;
    
    // Get filter parameters
    $severity = $_GET['severity'] ?? null;
    $category = $_GET['category'] ?? null;
    $filterOwn = isset($_GET['filter_own']) && $_GET['filter_own'] === 'true'; // Only show user's own reports
    
    // Ensure general_reports table exists
    try {
        $tableCheck = $pdo->query("SHOW TABLES LIKE 'general_reports'");
        if ($tableCheck->rowCount() === 0) {
            $createTableSql = "CREATE TABLE IF NOT EXISTS general_reports (
                id INT AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(255) NOT NULL,
                description TEXT NOT NULL,
                severity VARCHAR(20) NOT NULL,
                category VARCHAR(50) NOT NULL,
                latitude DECIMAL(10, 8) DEFAULT NULL,
                longitude DECIMAL(11, 8) DEFAULT NULL,
                address TEXT DEFAULT NULL,
                images JSON,
                audio_files JSON,
                status VARCHAR(20) DEFAULT 'pending',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_severity (severity),
                INDEX idx_category (category),
                INDEX idx_status (status),
                INDEX idx_created_at (created_at),
                INDEX idx_location (latitude, longitude)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            $pdo->exec($createTableSql);
        }
    } catch (PDOException $tableError) {
        error_log("Error checking/creating general_reports table: " . $tableError->getMessage());
    }
    
    // Build query - combine analysis_reports and general_reports, prioritize reports with location
    // Reports with location will be shown first, then others
    $whereAnalysis = "1=1";
    $whereGeneral = "1=1";
    $params = [];
    
    // Filter by user's own reports if requested (only for registered users)
    if ($filterOwn && $userRole !== 'guest' && $currentUserId !== null) {
        $whereAnalysis .= " AND user_id = :user_id_analysis";
        $whereGeneral .= " AND user_id = :user_id_general";
        $params[':user_id_analysis'] = $currentUserId;
        $params[':user_id_general'] = $currentUserId;
    }
    
    if ($severity) {
        $whereAnalysis .= " AND severity = :severity_analysis";
        $whereGeneral .= " AND severity = :severity_general";
        $params[':severity_analysis'] = $severity;
        $params[':severity_general'] = $severity;
    }
    
    if ($category) {
        $whereAnalysis .= " AND category = :category_analysis";
        $whereGeneral .= " AND category = :category_general";
        $params[':category_analysis'] = $category;
        $params[':category_general'] = $category;
    }
    
    $sql = "(SELECT 
        ar.id,
        'analysis' as report_type,
        ar.signature_name as title,
        ar.classification as description,
        ar.severity,
        ar.category,
        ar.latitude,
        ar.longitude,
        ar.address,
        NULL as images,
        NULL as audio_files,
        NULL as status,
        ar.user_id,
        ar.created_at,
        u.username,
        u.profile_img,
        CASE 
            WHEN ar.latitude IS NOT NULL AND ar.longitude IS NOT NULL 
                 AND ar.latitude != 0 AND ar.longitude != 0 
            THEN 1 
            ELSE 0 
        END as has_location
    FROM analysis_reports ar
    LEFT JOIN users u ON ar.user_id = u.id
    WHERE " . $whereAnalysis . ")
    UNION ALL
    (SELECT 
        gr.id,
        'general' as report_type,
        gr.title,
        gr.description,
        gr.severity,
        gr.category,
        gr.latitude,
        gr.longitude,
        gr.address,
        gr.images,
        gr.audio_files,
        gr.status,
        gr.user_id,
        gr.created_at,
        u.username,
        u.profile_img,
        CASE 
            WHEN gr.latitude IS NOT NULL AND gr.longitude IS NOT NULL 
                 AND gr.latitude != 0 AND gr.longitude != 0 
            THEN 1 
            ELSE 0 
        END as has_location
    FROM general_reports gr
    LEFT JOIN users u ON gr.user_id = u.id
    WHERE " . $whereGeneral . ")
    ORDER BY has_location DESC, created_at DESC LIMIT :limit";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    
    $stmt->execute();
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total count from both tables
    $countWhereAnalysis = "1=1";
    $countWhereGeneral = "1=1";
    if ($severity) {
        $countWhereAnalysis .= " AND severity = " . $pdo->quote($severity);
        $countWhereGeneral .= " AND severity = " . $pdo->quote($severity);
    }
    if ($category) {
        $countWhereAnalysis .= " AND category = " . $pdo->quote($category);
        $countWhereGeneral .= " AND category = " . $pdo->quote($category);
    }
    $countSql = "SELECT 
        (SELECT COUNT(*) FROM analysis_reports WHERE " . $countWhereAnalysis . ") + 
        (SELECT COUNT(*) FROM general_reports WHERE " . $countWhereGeneral . ") as total";
    $countStmt = $pdo->query($countSql);
    $countResult = $countStmt->fetch(PDO::FETCH_ASSOC);
    $total = intval($countResult['total']);
    
    // Format reports
    $formattedReports = array_map(function($report) use ($currentUserId) {
        // Decode JSON fields
        $images = json_decode($report['images'], true) ?? [];
        $audioFiles = json_decode($report['audio_files'], true) ?? [];
        
        // Validate and parse coordinates
        $latitude = null;
        $longitude = null;
        
        if ($report['latitude'] !== null && $report['longitude'] !== null) {
            $lat = floatval($report['latitude']);
            $lng = floatval($report['longitude']);
            
            // Only set if valid coordinates (not 0,0 and within valid ranges)
            if ($lat != 0 && $lng != 0 && 
                $lat >= -90 && $lat <= 90 && 
                $lng >= -180 && $lng <= 180) {
                $latitude = $lat;
                $longitude = $lng;
            }
        }
        
        // Check if this is user's own report
        $isOwnReport = false;
        if ($currentUserId !== null && isset($report['user_id'])) {
            $isOwnReport = (intval($report['user_id']) === intval($currentUserId));
        }
        
        // User information
        $userInfo = null;
        if (isset($report['user_id']) && $report['user_id'] !== null) {
            $userInfo = [
                'user_id' => intval($report['user_id']),
                'username' => $report['username'] ?? 'Unknown User',
                'profile_img' => $report['profile_img'] ?? null
            ];
        }
        
        return [
            'id' => intval($report['id']),
            'report_type' => $report['report_type'] ?? 'general',
            'title' => $report['title'],
            'description' => $report['description'],
            'severity' => $report['severity'],
            'category' => $report['category'],
            'latitude' => $latitude,
            'longitude' => $longitude,
            'address' => $report['address'],
            'images' => $images,
            'audioFiles' => $audioFiles,
            'status' => $report['status'],
            'created_at' => $report['created_at'],
            'timeAgo' => getTimeAgo($report['created_at']),
            'hasLocation' => ($latitude !== null && $longitude !== null),
            'isOwnReport' => $isOwnReport,
            'user' => $userInfo
        ];
    }, $reports);
    
    echo json_encode([
        'status' => 'success',
        'reports' => $formattedReports,
        'total' => $total,
        'limit' => $limit
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ], JSON_PRETTY_PRINT);
    error_log("PDO Error in get_map_reports.php: " . $e->getMessage());
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
    error_log("Error in get_map_reports.php: " . $e->getMessage());
}

function getTimeAgo($datetime) {
    $timestamp = strtotime($datetime);
    $diff = time() - $timestamp;
    
    if ($diff < 60) {
        return 'Just now';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' min' . ($mins > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        return date('M j, Y', $timestamp);
    }
}
?>

