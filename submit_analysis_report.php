<?php
// submit_analysis_report.php - Handle analysis-based report submissions

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Include database config and auth functions
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/auth.php';

try {
    // Check if it's a POST request
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Only POST requests are allowed');
    }

    // Get form data
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $severity = trim($_POST['severity'] ?? 'Medium');
    $category = trim($_POST['category'] ?? 'Other');
    $timestamp = $_POST['timestamp'] ?? date('c');
    $analysisType = $_POST['analysis_type'] ?? 'unknown';
    $diagnosisData = isset($_POST['diagnosis_data']) ? json_decode($_POST['diagnosis_data'], true) : null;
    
    // Handle latitude/longitude - convert empty strings to null
    $latitude = isset($_POST['latitude']) && $_POST['latitude'] !== '' ? floatval($_POST['latitude']) : null;
    $longitude = isset($_POST['longitude']) && $_POST['longitude'] !== '' ? floatval($_POST['longitude']) : null;
    $address = trim($_POST['address'] ?? '');

    // Log received data for debugging
    error_log("📋 Analysis Report Submission - Title: '$title', Description length: " . strlen($description) . ", Severity: $severity, Category: $category");

    // Validate required fields
    if (empty($title)) {
        error_log("❌ Validation failed: Title is empty");
        throw new Exception('Title is required');
    }
    if (empty($description)) {
        error_log("❌ Validation failed: Description is empty");
        throw new Exception('Description is required');
    }

    // Create uploads directory if it doesn't exist
    $uploadDir = __DIR__ . '/uploads/reports/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $uploadedImages = [];
    $uploadedAudio = [];
    $mediaFiles = []; // Track for media_files table

    // Handle image uploads (from image analysis)
    foreach ($_FILES as $key => $file) {
        if (strpos($key, 'image_') === 0 && $file['error'] === UPLOAD_ERR_OK) {
            $allowedImageTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
            
            if (!in_array($file['type'], $allowedImageTypes)) {
                continue; // Skip invalid file types
            }

            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'img_' . time() . '_' . uniqid() . '.' . $extension;
            $filepath = $uploadDir . $filename;

            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                $relPath = 'uploads/reports/' . $filename;
                $uploadedImages[] = $relPath;
                $mediaFiles[] = ['path' => $relPath, 'type' => 'image'];
            }
        }
    }

    // Get database connection
    $pdo = getDBConnection();
    
    // Check if user is logged in - guest users will have null userId
    $userId = getCurrentUserId();
    $isAnonymous = ($userId === null) ? true : false;
    
    // Log for debugging - guest reports are saved with user_id = NULL and is_anonymous = 1
    if ($isAnonymous) {
        error_log("📝 Guest user submitting analysis/media report - will be saved as anonymous");
    } else {
        error_log("📝 Authenticated user (ID: $userId) submitting analysis/media report");
    }

    // Create reports table if it doesn't exist (same as submit_report.php)
    $createTableSql = "CREATE TABLE IF NOT EXISTS general_reports (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT DEFAULT NULL,
        is_anonymous BOOLEAN DEFAULT FALSE,
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
        verification_photo VARCHAR(255) DEFAULT NULL,
        verified_at TIMESTAMP NULL DEFAULT NULL,
        verified_by VARCHAR(100) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_severity (severity),
        INDEX idx_category (category),
        INDEX idx_status (status),
        INDEX idx_created_at (created_at),
        INDEX idx_location (latitude, longitude),
        INDEX idx_user_id (user_id),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($createTableSql);
    
    // Add verification columns if they don't exist (for existing tables)
    try {
        $checkVerification = $pdo->query("SHOW COLUMNS FROM general_reports LIKE 'verification_photo'");
        if ($checkVerification->rowCount() === 0) {
            $pdo->exec("ALTER TABLE general_reports ADD COLUMN verification_photo VARCHAR(255) DEFAULT NULL AFTER status");
            $pdo->exec("ALTER TABLE general_reports ADD COLUMN verified_at TIMESTAMP NULL DEFAULT NULL AFTER verification_photo");
            $pdo->exec("ALTER TABLE general_reports ADD COLUMN verified_by VARCHAR(100) DEFAULT NULL AFTER verified_at");
        }
    } catch (PDOException $e) {
        // Columns might already exist, ignore
    }
    
    // Add user_id and is_anonymous columns if they don't exist (for user roles system)
    try {
        $checkUserId = $pdo->query("SHOW COLUMNS FROM general_reports LIKE 'user_id'");
        if ($checkUserId->rowCount() === 0) {
            // Check if id column exists to determine position
            $idCheck = $pdo->query("SHOW COLUMNS FROM general_reports LIKE 'id'");
            if ($idCheck->rowCount() > 0) {
                $pdo->exec("ALTER TABLE general_reports ADD COLUMN user_id INT DEFAULT NULL AFTER id");
                $pdo->exec("ALTER TABLE general_reports ADD COLUMN is_anonymous BOOLEAN DEFAULT FALSE AFTER user_id");
            } else {
                $pdo->exec("ALTER TABLE general_reports ADD COLUMN user_id INT DEFAULT NULL FIRST");
                $pdo->exec("ALTER TABLE general_reports ADD COLUMN is_anonymous BOOLEAN DEFAULT FALSE AFTER user_id");
            }
            
            // Add index
            try {
                $pdo->exec("ALTER TABLE general_reports ADD INDEX idx_user_id (user_id)");
            } catch (PDOException $ie) {
                // Index might already exist
            }
            
            // Try to add foreign key, but don't fail if users table doesn't exist yet
            try {
                $usersCheck = $pdo->query("SHOW TABLES LIKE 'users'");
                if ($usersCheck->rowCount() > 0) {
                    $pdo->exec("ALTER TABLE general_reports ADD FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL");
                }
            } catch (PDOException $fke) {
                // Foreign key might fail if already exists or users table doesn't exist yet
            }
        }
    } catch (PDOException $e) {
        // Columns might already exist, ignore
        error_log("Note: User ID columns check: " . $e->getMessage());
    }
    
    // Update existing reports with 'OPEN' status to 'pending'
    try {
        $pdo->exec("UPDATE general_reports SET status = 'pending' WHERE status = 'OPEN' OR status IS NULL");
    } catch (PDOException $e) {
        // Ignore if update fails
    }

    // Insert report into database with status 'pending' and user info
    $sql = "INSERT INTO general_reports (
        user_id, is_anonymous, title, description, severity, category, latitude, longitude, address, images, audio_files, status
    ) VALUES (
        :user_id, :is_anonymous, :title, :description, :severity, :category, :latitude, :longitude, :address, :images, :audio_files, 'pending'
    )";

    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([
        ':user_id' => $userId,
        ':is_anonymous' => $isAnonymous ? 1 : 0,
        ':title' => $title,
        ':description' => $description,
        ':severity' => $severity,
        ':category' => $category,
        ':latitude' => $latitude,
        ':longitude' => $longitude,
        ':address' => $address,
        ':images' => json_encode($uploadedImages),
        ':audio_files' => json_encode($uploadedAudio)
    ]);

    if ($result) {
        $reportId = $pdo->lastInsertId();
        
        // Verify the saved data
        $verifyStmt = $pdo->prepare("SELECT title, description, severity, category FROM general_reports WHERE id = ?");
        $verifyStmt->execute([$reportId]);
        $savedReport = $verifyStmt->fetch(PDO::FETCH_ASSOC);
        error_log("✅ Report saved - ID: $reportId, Saved Title: '" . ($savedReport['title'] ?? 'NOT FOUND') . "', Saved Description length: " . strlen($savedReport['description'] ?? ''));
        
        // Insert media files into media_files table
        if (!empty($mediaFiles)) {
            $mediaStmt = $pdo->prepare("INSERT INTO media_files (report_id, report_type, file_path, file_type) VALUES (?, 'general', ?, ?)");
            foreach ($mediaFiles as $media) {
                $mediaStmt->execute([$reportId, $media['path'], $media['type']]);
            }
        }
        
        // Validate report for cross-modal correlation
        $validationResult = [
            'status' => 'skipped',
            'message' => 'Validation skipped',
            'correlations' => []
        ];
        try {
            // Only validate if we have location data
            if ($latitude !== null && $longitude !== null) {
                require_once __DIR__ . '/validate_reports.php';
                if (function_exists('validateReport')) {
                    $validationResult = validateReport($reportId, 'general');
                    if ($validationResult['status'] === 'validated') {
                        error_log("✅ Cross-modal validation: Report #$reportId validated with " . count($validationResult['correlations']) . " correlations");
                    }
                }
            } else {
                $validationResult['message'] = 'Validation skipped - no location data';
            }
        } catch (Exception $validationError) {
            error_log("⚠️ Validation error (non-fatal): " . $validationError->getMessage());
            // Continue even if validation fails
        }
        
        // Check proximity alerts
        if ($latitude !== null && $longitude !== null) {
            require_once __DIR__ . '/check_proximity_alerts.php';
            $alertResult = checkProximityAlerts($reportId, 'general', $latitude, $longitude, $severity);
            if ($alertResult['alerts_triggered'] > 0) {
                error_log("🔔 Proximity alerts: " . $alertResult['alerts_triggered'] . " users notified");
            }
        }
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Report created successfully from analysis',
            'report_id' => $reportId,
            'images_uploaded' => count($uploadedImages),
            'audio_uploaded' => count($uploadedAudio),
            'analysis_type' => $analysisType,
            'validation' => $validationResult
        ], JSON_PRETTY_PRINT);
        
        error_log("✅ Analysis-based report submitted successfully (ID: $reportId, Type: $analysisType)");
    } else {
        throw new Exception('Failed to save report to database');
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ], JSON_PRETTY_PRINT);
    error_log("❌ PDO Error in submit_analysis_report.php: " . $e->getMessage());
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
    error_log("❌ Error in submit_analysis_report.php: " . $e->getMessage());
}
?>

