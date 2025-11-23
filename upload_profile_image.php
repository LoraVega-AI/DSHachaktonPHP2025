<?php
// upload_profile_image.php - Upload user profile image

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Credentials: true");

require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/auth.php';

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Only POST requests are allowed');
    }

    // Require user to be logged in
    if (!isLoggedIn()) {
        throw new Exception('Authentication required');
    }

    $currentUserId = getCurrentUserId();
    
    // Check if file was uploaded
    if (!isset($_FILES['profile_image'])) {
        throw new Exception('No image file uploaded');
    }

    $file = $_FILES['profile_image'];
    
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('File upload error: ' . $file['error']);
    }

    // Validate file type
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    $fileType = mime_content_type($file['tmp_name']);
    
    if (!in_array($fileType, $allowedTypes)) {
        throw new Exception('Invalid file type. Only JPEG, PNG, GIF, and WebP images are allowed.');
    }

    // Validate file size (max 5MB)
    $maxSize = 5 * 1024 * 1024; // 5MB
    if ($file['size'] > $maxSize) {
        throw new Exception('File size too large. Maximum size is 5MB.');
    }

    // Create uploads directory if it doesn't exist
    $uploadsDir = __DIR__ . '/uploads/profiles';
    if (!is_dir($uploadsDir)) {
        mkdir($uploadsDir, 0755, true);
    }

    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'user_' . $currentUserId . '_' . time() . '.' . $extension;
    $filepath = $uploadsDir . '/' . $filename;
    $relativeFilePath = 'uploads/profiles/' . $filename;

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        throw new Exception('Failed to save uploaded file');
    }

    // Update database
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("UPDATE users SET profile_img = :profile_img WHERE id = :user_id");
    $stmt->execute([
        ':profile_img' => $relativeFilePath,
        ':user_id' => $currentUserId
    ]);

    echo json_encode([
        'status' => 'success',
        'message' => 'Profile image uploaded successfully',
        'profile_img' => $relativeFilePath
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
    error_log("Error in upload_profile_image.php: " . $e->getMessage());
}
?>

