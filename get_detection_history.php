<?php
// get_detection_history.php - Fetch detection history from database

// Set headers first
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type");

// Start output buffering to catch any errors
ob_start();

// Enable error reporting for debugging (but don't display, just log)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

try {
    // Include database config and auth functions
    require_once __DIR__ . '/db_config.php';
    require_once __DIR__ . '/auth.php';
    
    // Ensure database is initialized
    if (!function_exists('getDBConnection')) {
        throw new Exception("Database connection function not available");
    }
    
    // Get database connection - this will create DB if needed
    $pdo = getDBConnection();
    
    // Check user role for access control
    $userRole = getUserRole(); // guest, user, or admin
    $currentUserId = getCurrentUserId();
    
    // Initialize table if needed (getDBConnection already created DB)
    if (function_exists('initializeDatabase')) {
        @initializeDatabase(); // This will create the table
    }
    
    // Verify tables exist, create if not
    try {
        // Check and create analysis_reports table
        $tableCheck = $pdo->query("SHOW TABLES LIKE 'analysis_reports'");
        if ($tableCheck->rowCount() === 0) {
            $createTableSql = "CREATE TABLE IF NOT EXISTS analysis_reports (
                id INT AUTO_INCREMENT PRIMARY KEY,
                timestamp DATETIME NOT NULL,
                top_hazard VARCHAR(255) NOT NULL,
                confidence_score DECIMAL(5,3) NOT NULL,
                rms_level DECIMAL(10,6) NOT NULL,
                spectral_centroid DECIMAL(10,2) DEFAULT NULL,
                frequency VARCHAR(50) DEFAULT NULL,
                signature_name TEXT,
                classification TEXT,
                executive_conclusion TEXT,
                severity VARCHAR(20) DEFAULT NULL,
                is_problem VARCHAR(10) DEFAULT NULL,
                verdict VARCHAR(20) DEFAULT NULL,
                risk_description TEXT,
                action_steps TEXT,
                who_to_contact TEXT,
                category VARCHAR(100) DEFAULT NULL,
                full_report_data JSON,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_timestamp (timestamp),
                INDEX idx_severity (severity),
                INDEX idx_category (category),
                INDEX idx_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            $pdo->exec($createTableSql);
            
            // Add category column if it doesn't exist (for existing tables)
            try {
                $checkColumn = $pdo->query("SHOW COLUMNS FROM analysis_reports LIKE 'category'");
                if ($checkColumn->rowCount() === 0) {
                    $pdo->exec("ALTER TABLE analysis_reports ADD COLUMN category VARCHAR(100) DEFAULT NULL AFTER who_to_contact");
                    $pdo->exec("ALTER TABLE analysis_reports ADD INDEX idx_category (category)");
                }
            } catch (PDOException $e) {
                // Column might already exist, ignore
            }
        }
        
        // Check and create general_reports table
        $tableCheck2 = $pdo->query("SHOW TABLES LIKE 'general_reports'");
        if ($tableCheck2->rowCount() === 0) {
            $createTableSql2 = "CREATE TABLE IF NOT EXISTS general_reports (
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
            $pdo->exec($createTableSql2);
        }
    } catch (PDOException $tableError) {
        // If table check/creation fails, try initializeDatabase
        if (function_exists('initializeDatabase')) {
            @initializeDatabase();
        }
    }
    
    // Get limit from query parameter (default 10, max 1000 for history page)
    $limit = isset($_GET['limit']) ? min(intval($_GET['limit']), 1000) : 10;
    
    // Get offset for pagination
    $offset = isset($_GET['offset']) ? max(0, intval($_GET['offset'])) : 0;
    
    // Get filter parameters
    $category = $_GET['category'] ?? null;
    $severity = $_GET['severity'] ?? null;
    
    // Role-based access control
    // Admins see all reports, regular users see only their own reports, guests see nothing
    if ($userRole === 'guest') {
        // Guests cannot access history
        http_response_code(403);
        throw new Exception('Please log in to view your detection history.');
    }
    
    // Check if tables exist and if user_id column exists in both tables
    $hasAnalysisReports = false;
    $hasGeneralReports = false;
    $hasAnalysisUserId = false;
    $hasGeneralUserId = false;
    
    try {
        $tableCheckAnalysis = $pdo->query("SHOW TABLES LIKE 'analysis_reports'");
        $hasAnalysisReports = $tableCheckAnalysis->rowCount() > 0;
        
        if ($hasAnalysisReports) {
            $checkAnalysisUserId = $pdo->query("SHOW COLUMNS FROM analysis_reports LIKE 'user_id'");
            $hasAnalysisUserId = $checkAnalysisUserId->rowCount() > 0;
        }
    } catch (Exception $e) {
        error_log("Could not check analysis_reports table/columns: " . $e->getMessage());
    }
    
    try {
        $tableCheckGeneral = $pdo->query("SHOW TABLES LIKE 'general_reports'");
        $hasGeneralReports = $tableCheckGeneral->rowCount() > 0;
        
        if ($hasGeneralReports) {
            $checkGeneralUserId = $pdo->query("SHOW COLUMNS FROM general_reports LIKE 'user_id'");
            $hasGeneralUserId = $checkGeneralUserId->rowCount() > 0;
        }
    } catch (Exception $e) {
        error_log("Could not check general_reports table/columns: " . $e->getMessage());
    }
    
    // Build WHERE clauses
    $where_analysis = "1=1";
    $where_general = "1=1";
    $params = [];
    
    // If user is not admin, filter by their user_id
    if ($userRole !== 'admin' && $currentUserId !== null) {
        if ($hasAnalysisUserId) {
            $where_analysis .= " AND ar.user_id = :user_id_analysis";
            $params[':user_id_analysis'] = $currentUserId;
        } else {
            // If user_id column doesn't exist, user can't see any analysis reports
            $where_analysis .= " AND 1=0"; // Always false
        }
        
        if ($hasGeneralUserId) {
            $where_general .= " AND gr.user_id = :user_id_general";
            $params[':user_id_general'] = $currentUserId;
        } else {
            // If user_id column doesn't exist, user can't see any general reports
            $where_general .= " AND 1=0"; // Always false
        }
    }
    // Admins see all reports (no user_id filter)
    
    if ($category) {
        $where_analysis .= " AND category = :category_analysis";
        $where_general .= " AND category = :category_general";
        $params[':category_analysis'] = $category;
        $params[':category_general'] = $category;
    }
    
    if ($severity) {
        $where_analysis .= " AND severity = :severity_analysis";
        $where_general .= " AND severity = :severity_general";
        $params[':severity_analysis'] = $severity;
        $params[':severity_general'] = $severity;
    }
    
    // Fetch from both tables using UNION
    // We'll combine analysis_reports and general_reports, normalizing the data
    $sql = "(SELECT 
        ar.id,
        'analysis' as report_type,
        ar.timestamp as report_timestamp,
        ar.created_at,
        ar.top_hazard as title_or_hazard,
        ar.signature_name,
        ar.classification,
        ar.executive_conclusion,
        ar.confidence_score,
        ar.rms_level,
        ar.spectral_centroid,
        ar.frequency,
        ar.severity,
        ar.is_problem,
        ar.verdict,
        ar.risk_description,
        ar.action_steps,
        ar.who_to_contact,
        ar.category,
        ar.latitude,
        ar.longitude,
        ar.address,
        NULL as images,
        NULL as audio_files,
        COALESCE(ar.status, 'pending') as status,
        ar.user_id,
        u.username,
        u.profile_img
    FROM analysis_reports ar
    LEFT JOIN users u ON ar.user_id = u.id
    WHERE " . $where_analysis . ")
    UNION ALL
    (SELECT 
        gr.id,
        'general' as report_type,
        gr.created_at as report_timestamp,
        gr.created_at,
        gr.title as title_or_hazard,
        NULL as signature_name,
        gr.description as classification,
        gr.description as executive_conclusion,
        NULL as confidence_score,
        NULL as rms_level,
        NULL as spectral_centroid,
        NULL as frequency,
        gr.severity,
        NULL as is_problem,
        CASE 
            WHEN gr.severity = 'CRITICAL' THEN 'DANGEROUS'
            WHEN gr.severity = 'HIGH' THEN 'ATTENTION'
            WHEN gr.severity = 'MEDIUM' THEN 'SAFE'
            ELSE 'SAFE'
        END as verdict,
        NULL as risk_description,
        NULL as action_steps,
        NULL as who_to_contact,
        gr.category,
        gr.latitude,
        gr.longitude,
        gr.address,
        gr.images,
        gr.audio_files,
        gr.status,
        gr.user_id,
        u.username,
        u.profile_img
    FROM general_reports gr
    LEFT JOIN users u ON gr.user_id = u.id
    WHERE " . $where_general . ")
    ORDER BY created_at DESC 
    LIMIT :limit OFFSET :offset";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    
    $stmt->execute();
    
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total count from both tables with filters (including user_id filter for non-admins)
    $total = 0;
    try {
        $countWhereAnalysis = "1=1";
        $countWhereGeneral = "1=1";
        
        // Add user_id filter for non-admin users
        if ($userRole !== 'admin' && $currentUserId !== null) {
            if ($hasAnalysisUserId) {
                $countWhereAnalysis .= " AND user_id = " . $pdo->quote($currentUserId);
            } else {
                $countWhereAnalysis .= " AND 1=0"; // Always false
            }
            
            if ($hasGeneralUserId) {
                $countWhereGeneral .= " AND user_id = " . $pdo->quote($currentUserId);
            } else {
                $countWhereGeneral .= " AND 1=0"; // Always false
            }
        }
        
        if ($category) {
            $countWhereAnalysis .= " AND category = " . $pdo->quote($category);
            $countWhereGeneral .= " AND category = " . $pdo->quote($category);
        }
        if ($severity) {
            $countWhereAnalysis .= " AND severity = " . $pdo->quote($severity);
            $countWhereGeneral .= " AND severity = " . $pdo->quote($severity);
        }
        $countStmt = $pdo->query("SELECT 
            (SELECT COUNT(*) FROM analysis_reports WHERE " . $countWhereAnalysis . ") + 
            (SELECT COUNT(*) FROM general_reports WHERE " . $countWhereGeneral . ") as total");
        $countResult = $countStmt->fetch(PDO::FETCH_ASSOC);
        $total = $countResult ? intval($countResult['total']) : 0;
    } catch (Exception $countError) {
        // If count fails, just use the number of reports we got
        $total = count($reports);
    }
    
    // Format the response - normalize both report types
    $formattedReports = [];
    if (!empty($reports)) {
        $formattedReports = array_map(function($report) {
        // Calculate frequency - prefer frequency field, fallback to spectral_centroid
        $frequency = null;
        if (!empty($report['frequency'])) {
            $frequency = $report['frequency'];
        } elseif (!empty($report['spectral_centroid']) && floatval($report['spectral_centroid']) > 0) {
            $frequency = round(floatval($report['spectral_centroid'])) . 'Hz';
        }
        
        // Normalize hazard/title field
        $hazard = !empty($report['signature_name']) ? $report['signature_name'] : 
                  (!empty($report['title_or_hazard']) ? $report['title_or_hazard'] : 'Unknown');
        
        // Normalize classification/description
        $classification = !empty($report['classification']) ? $report['classification'] : 
                          (!empty($report['executive_conclusion']) ? $report['executive_conclusion'] : 'No description available');
        
        // Normalize confidence (general reports don't have confidence, use 0 or null)
        $confidence = isset($report['confidence_score']) && $report['confidence_score'] !== null ? 
                      round(floatval($report['confidence_score']) * 100) : null;
        
        // Normalize RMS (general reports don't have RMS)
        $rms = isset($report['rms_level']) && $report['rms_level'] !== null ? 
               number_format(floatval($report['rms_level']), 2) : null;
        
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
            'report_type' => $report['report_type'], // 'analysis' or 'general'
            'timestamp' => $report['report_timestamp'],
            'timeAgo' => getTimeAgo($report['created_at']),
            'hazard' => $hazard,
            'classification' => $classification,
            'confidence' => $confidence,
            'frequency' => $frequency,
            'rms' => $rms,
            'severity' => !empty($report['severity']) ? $report['severity'] : 'UNKNOWN',
            'is_problem' => $report['is_problem'],
            'verdict' => !empty($report['verdict']) ? $report['verdict'] : 'SAFE',
            'executive_conclusion' => $report['executive_conclusion'],
            'user' => $userInfo,
            'risk_description' => $report['risk_description'],
            'action_steps' => $report['action_steps'],
            'who_to_contact' => $report['who_to_contact'],
            // Additional fields for general reports
            'category' => $report['category'],
            'status' => !empty($report['status']) ? $report['status'] : 'pending',
            'latitude' => $report['latitude'],
            'longitude' => $report['longitude'],
            'address' => $report['address'],
            'images' => $report['images'] ? json_decode($report['images'], true) : null,
            'audio_files' => $report['audio_files'] ? json_decode($report['audio_files'], true) : null,
            'status' => $report['status']
        ];
        }, $reports);
    }
    
    // Clear any output before sending JSON
    ob_clean();
    
    echo json_encode([
        'status' => 'success',
        'reports' => $formattedReports,
        'total' => intval($total),
        'limit' => $limit,
        'offset' => $offset
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    
} catch (PDOException $e) {
    ob_clean();
    http_response_code(500);
    $errorInfo = $e->errorInfo ?? [];
    $message = $e->getMessage();
    
    // Check if it's a driver error
    if (strpos($message, 'could not find driver') !== false || strpos($message, 'driver') !== false) {
        $message .= " - PDO MySQL driver is not enabled. Please enable extension=pdo_mysql in php.ini and restart Apache.";
    }
    
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $message,
        'code' => $e->getCode(),
        'sqlstate' => $errorInfo[0] ?? 'N/A',
        'driver_code' => $errorInfo[1] ?? 'N/A',
        'driver_message' => $errorInfo[2] ?? 'N/A',
        'fix_instructions' => 'If you see "could not find driver", enable extension=pdo_mysql in php.ini and restart Apache'
    ], JSON_PRETTY_PRINT);
    error_log("PDO Error in get_detection_history.php: " . $e->getMessage());
} catch (Exception $e) {
    ob_clean();
    http_response_code(500);
    $message = $e->getMessage();
    
    // Check if it's a driver error
    if (strpos($message, 'PDO MySQL driver') !== false || strpos($message, 'driver') !== false) {
        $message .= " - Please enable extension=pdo_mysql in php.ini and restart Apache.";
    }
    
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to fetch detection history: ' . $message,
        'type' => get_class($e),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'fix_instructions' => 'If you see "driver" error, enable extension=pdo_mysql in php.ini and restart Apache'
    ], JSON_PRETTY_PRINT);
    error_log("Exception in get_detection_history.php: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
} catch (Error $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Fatal error: ' . $e->getMessage(),
        'type' => get_class($e),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ], JSON_PRETTY_PRINT);
    error_log("Fatal Error in get_detection_history.php: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
}

// Helper function to calculate time ago
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

