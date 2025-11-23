<?php
// verify_report.php - Verify a report with photo upload

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/db_config.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Only POST requests are allowed');
    }

    $reportId = $_POST['report_id'] ?? null;
    $reportType = $_POST['report_type'] ?? 'analysis'; // 'analysis' or 'general'
    $isFixed = isset($_POST['is_fixed']) ? filter_var($_POST['is_fixed'], FILTER_VALIDATE_BOOLEAN) : false;
    $verifiedBy = $_POST['verified_by'] ?? 'Unknown';

    if (!$reportId) {
        throw new Exception('Report ID is required');
    }

    $pdo = getDBConnection();
    
    // Determine which table to update
    $tableName = $reportType === 'general' ? 'general_reports' : 'analysis_reports';
    $idColumn = 'id';
    
    // Handle photo upload
    $verificationPhoto = null;
    if (isset($_FILES['verification_photo']) && $_FILES['verification_photo']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/uploads/verifications/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $file = $_FILES['verification_photo'];
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        
        if (!in_array($file['type'], $allowedTypes)) {
            throw new Exception('Invalid file type. Only images are allowed.');
        }

        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'verify_' . time() . '_' . uniqid() . '.' . $extension;
        $filepath = $uploadDir . $filename;

        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            $verificationPhoto = 'uploads/verifications/' . $filename;
        } else {
            throw new Exception('Failed to upload verification photo');
        }
    }

    // Update report status based on verification
    if ($isFixed) {
        // If fixed, set status to 'verified'
        $newStatus = 'verified';
    } else {
        // If not fixed, set status back to 'pending'
        $newStatus = 'pending';
    }

    // Build update query
    $updateFields = ['status = :status', 'verified_at = NOW()', 'verified_by = :verified_by', 'updated_at = NOW()'];
    $params = [
        ':status' => $newStatus,
        ':verified_by' => $verifiedBy,
        ':id' => $reportId
    ];

    if ($verificationPhoto) {
        $updateFields[] = 'verification_photo = :verification_photo';
        $params[':verification_photo'] = $verificationPhoto;
    }

    $sql = "UPDATE {$tableName} SET " . implode(', ', $updateFields) . " WHERE {$idColumn} = :id";
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute($params);

    if ($result) {
        echo json_encode([
            'status' => 'success',
            'message' => $isFixed ? 'Report verified as fixed' : 'Report marked as not fixed, returned to pending',
            'report_id' => $reportId,
            'new_status' => $newStatus,
            'verification_photo' => $verificationPhoto
        ]);
    } else {
        throw new Exception('Failed to verify report');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
    error_log("Error in verify_report.php: " . $e->getMessage());
}
?>

