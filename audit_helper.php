<?php
// audit_helper.php - Helper functions for audit logging

require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/auth.php';

/**
 * Log an audit action to the audit_log table
 * 
 * @param int $reportId Report ID
 * @param string $reportType 'analysis' or 'general'
 * @param int $userId User performing the action
 * @param string $actionType Type of action (e.g., 'status_change', 'assignment', 'verification')
 * @param mixed $oldValue Previous value (will be JSON encoded if array/object)
 * @param mixed $newValue New value (will be JSON encoded if array/object)
 * @return bool True on success, false on failure
 */
function logAuditAction($reportId, $reportType, $userId, $actionType, $oldValue = null, $newValue = null) {
    try {
        $pdo = getDBConnection();
        
        // Convert arrays/objects to JSON
        if (is_array($oldValue) || is_object($oldValue)) {
            $oldValue = json_encode($oldValue);
        }
        if (is_array($newValue) || is_object($newValue)) {
            $newValue = json_encode($newValue);
        }
        
        $sql = "INSERT INTO audit_log (report_id, report_type, user_id, action_type, old_value, new_value, timestamp) 
                VALUES (:report_id, :report_type, :user_id, :action_type, :old_value, :new_value, NOW())";
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            ':report_id' => $reportId,
            ':report_type' => $reportType,
            ':user_id' => $userId,
            ':action_type' => $actionType,
            ':old_value' => $oldValue,
            ':new_value' => $newValue
        ]);
        
        if ($result) {
            error_log("📋 Audit: $actionType on $reportType report #$reportId by user #$userId");
            return true;
        }
        
        return false;
    } catch (PDOException $e) {
        error_log("❌ Audit logging failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Get audit history for a specific report
 * 
 * @param int $reportId Report ID
 * @param string $reportType 'analysis' or 'general'
 * @param int $limit Maximum number of records to return
 * @return array Array of audit log entries
 */
function getReportAuditHistory($reportId, $reportType, $limit = 50) {
    try {
        $pdo = getDBConnection();
        
        $sql = "SELECT a.*, u.username 
                FROM audit_log a 
                LEFT JOIN users u ON a.user_id = u.id 
                WHERE a.report_id = :report_id AND a.report_type = :report_type 
                ORDER BY a.timestamp DESC 
                LIMIT :limit";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':report_id', $reportId, PDO::PARAM_INT);
        $stmt->bindValue(':report_type', $reportType, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("❌ Failed to fetch audit history: " . $e->getMessage());
        return [];
    }
}

/**
 * Get recent audit actions by a specific user
 * 
 * @param int $userId User ID
 * @param int $limit Maximum number of records to return
 * @return array Array of audit log entries
 */
function getUserAuditHistory($userId, $limit = 50) {
    try {
        $pdo = getDBConnection();
        
        $sql = "SELECT * FROM audit_log 
                WHERE user_id = :user_id 
                ORDER BY timestamp DESC 
                LIMIT :limit";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("❌ Failed to fetch user audit history: " . $e->getMessage());
        return [];
    }
}

/**
 * Get audit statistics for admin dashboard
 * 
 * @return array Statistics array with counts by action type
 */
function getAuditStatistics() {
    try {
        $pdo = getDBConnection();
        
        $sql = "SELECT action_type, COUNT(*) as count 
                FROM audit_log 
                WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY action_type 
                ORDER BY count DESC";
        
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("❌ Failed to fetch audit statistics: " . $e->getMessage());
        return [];
    }
}
?>

