<?php
// update_report_status.php - Admin endpoint to update report status to 'solved'

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/audit_helper.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Only POST requests are allowed');
    }
    
    // Require authentication (admin or crew)
    if (!isLoggedIn()) {
        http_response_code(401);
        throw new Exception('Authentication required');
    }
    
    $userRole = getUserRole();
    $currentUserId = getCurrentUserId();

    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        $data = $_POST;
    }

    $reportId = $data['report_id'] ?? null;
    $reportType = $data['report_type'] ?? 'analysis'; // 'analysis' or 'general'
    $newStatus = $data['status'] ?? 'solved';

    if (!$reportId) {
        throw new Exception('Report ID is required');
    }

    // Validate status (added 'in_progress')
    $allowedStatuses = ['pending', 'in_progress', 'solved', 'verified', 'false', 'spam'];
    if (!in_array($newStatus, $allowedStatuses)) {
        throw new Exception('Invalid status. Allowed: pending, in_progress, solved, verified, false, spam');
    }
    
    // Define which roles can set which statuses
    $adminOnlyStatuses = ['verified', 'false', 'spam'];
    $crewStatuses = ['pending', 'in_progress', 'solved'];
    
    // Check permissions based on role
    if ($userRole === 'crew') {
        // Crew can set pending, in_progress, solved for any report (admin-like privileges)
        if (!in_array($newStatus, $crewStatuses)) {
            http_response_code(403);
            throw new Exception('Crew members can only set status to: pending, in_progress, solved');
        }
    } elseif ($userRole !== 'admin') {
        http_response_code(403);
        throw new Exception('Insufficient permissions to update report status');
    }

    $pdo = getDBConnection();
    
    // Start transaction for atomic updates
    $pdo->beginTransaction();
    
    try {
        // Determine which table to update
        $tableName = $reportType === 'general' ? 'general_reports' : 'analysis_reports';
        $idColumn = 'id';
        
        // Get current report status, user_id, and assignment before updating
        $getReportSql = "SELECT status, user_id, assigned_to_user_id FROM {$tableName} WHERE {$idColumn} = :id";
        $getReportStmt = $pdo->prepare($getReportSql);
        $getReportStmt->execute([':id' => $reportId]);
        $report = $getReportStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$report) {
            throw new Exception('Report not found');
        }
        
        // Crew members have admin-like privileges and can update any report
        // No additional permission check needed for crew
        
        $oldStatus = $report['status'] ?? 'pending';
        $userId = $report['user_id'] ?? null;
        
        // Only update trust score if user_id exists and status is actually changing
        $scoreChange = 0;
        $shouldUpdateScore = false;
        
        if ($userId !== null && $oldStatus !== $newStatus) {
            // Calculate score change based on status transitions
            // Only award points for positive transitions, penalize for negative ones
            // Prevent score changes if reverting from higher status to lower
            
            if ($newStatus === 'verified' && $oldStatus !== 'verified') {
                // Award +1 for verified (only if not already verified)
                $scoreChange = 1;
                $shouldUpdateScore = true;
            } elseif ($newStatus === 'solved' && $oldStatus !== 'solved' && $oldStatus !== 'verified') {
                // Award +3 for solved (only if not already solved or verified)
                $scoreChange = 3;
                $shouldUpdateScore = true;
            } elseif (($newStatus === 'false' || $newStatus === 'spam') && $oldStatus !== 'false' && $oldStatus !== 'spam') {
                // Penalize -1 for false/spam (only if not already false/spam)
                $scoreChange = -1;
                $shouldUpdateScore = true;
            } elseif (($newStatus === 'pending' || $newStatus === 'solved') && ($oldStatus === 'verified')) {
                // Reverting from verified: remove the +1 that was given
                $scoreChange = -1;
                $shouldUpdateScore = true;
            } elseif ($newStatus === 'pending' && $oldStatus === 'solved') {
                // Reverting from solved: remove the +3 that was given
                $scoreChange = -3;
                $shouldUpdateScore = true;
            }
        }
        
        // Update report status
        try {
            $checkColumn = $pdo->query("SHOW COLUMNS FROM {$tableName} LIKE 'updated_at'");
            $hasUpdatedAt = $checkColumn->rowCount() > 0;
        } catch (PDOException $e) {
            $hasUpdatedAt = false;
        }
        
        if ($hasUpdatedAt) {
            $sql = "UPDATE {$tableName} SET status = :status, updated_at = NOW() WHERE {$idColumn} = :id";
        } else {
            $sql = "UPDATE {$tableName} SET status = :status WHERE {$idColumn} = :id";
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':status' => $newStatus,
            ':id' => $reportId
        ]);
        
        // Update user trust score if needed
        if ($shouldUpdateScore && $userId !== null) {
            // Ensure trust_score column exists
            try {
                $checkTrustScore = $pdo->query("SHOW COLUMNS FROM users LIKE 'trust_score'");
                if ($checkTrustScore->rowCount() === 0) {
                    $pdo->exec("ALTER TABLE users ADD COLUMN trust_score INT DEFAULT 0 AFTER role");
                    $pdo->exec("ALTER TABLE users ADD INDEX idx_trust_score (trust_score)");
                }
            } catch (PDOException $e) {
                // Column might already exist, ignore
            }
            
            // Update trust score (ensure it doesn't go below 0)
            $updateScoreSql = "UPDATE users SET trust_score = GREATEST(0, trust_score + :score_change) WHERE id = :user_id";
            $updateScoreStmt = $pdo->prepare($updateScoreSql);
            $updateScoreStmt->execute([
                ':score_change' => $scoreChange,
                ':user_id' => $userId
            ]);
        }
        
        // Log to audit log
        logAuditAction($reportId, $reportType, $currentUserId, 'status_change', $oldStatus, $newStatus);
        
        // Broadcast event for real-time updates (will be implemented with SSE)
        if (function_exists('broadcastEvent')) {
            broadcastEvent('status_change', [
                'report_id' => $reportId,
                'report_type' => $reportType,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'changed_by' => $currentUserId
            ]);
        }
        
        // Commit transaction
        $pdo->commit();
        
        echo json_encode([
            'status' => 'success',
            'message' => "Report status updated to '{$newStatus}'",
            'report_id' => $reportId,
            'new_status' => $newStatus,
            'old_status' => $oldStatus,
            'score_change' => $shouldUpdateScore ? $scoreChange : 0,
            'user_id' => $userId,
            'changed_by' => $currentUserId
        ]);
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
    error_log("Error in update_report_status.php: " . $e->getMessage());
}
?>

