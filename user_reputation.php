<?php
// user_reputation.php - User reputation and trust score management

require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/auth.php';

/**
 * Update user trust score based on report verification
 * 
 * @param int $userId User ID
 * @param string $action 'verified', 'false', 'spam'
 * @return array Result with new trust score
 */
function updateTrustScore($userId, $action) {
    try {
        $pdo = getDBConnection();
        
        // Define score changes
        $scoreChanges = [
            'verified' => 0.1,   // Increase by 0.1 when report is verified
            'false' => -0.2,     // Decrease by 0.2 when marked false
            'spam' => -0.2       // Decrease by 0.2 when marked spam
        ];
        
        $scoreChange = $scoreChanges[$action] ?? 0;
        
        if ($scoreChange === 0) {
            return [
                'status' => 'no_change',
                'message' => 'Invalid action or no score change required'
            ];
        }
        
        // Update trust score (min 0.0, max 5.0)
        $sql = "UPDATE users 
                SET trust_score = GREATEST(0.0, LEAST(5.0, trust_score + :change))
                WHERE id = :user_id";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':change' => $scoreChange,
            ':user_id' => $userId
        ]);
        
        // Get new trust score
        $stmt = $pdo->prepare("SELECT trust_score, username FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            error_log("📊 Trust score update: User #{$userId} ({$user['username']}) {$action} => " . 
                     number_format($user['trust_score'], 2) . " (" . ($scoreChange > 0 ? '+' : '') . $scoreChange . ")");
            
            return [
                'status' => 'success',
                'message' => 'Trust score updated',
                'user_id' => $userId,
                'action' => $action,
                'score_change' => $scoreChange,
                'new_score' => floatval($user['trust_score'])
            ];
        }
        
        return [
            'status' => 'error',
            'message' => 'User not found'
        ];
        
    } catch (PDOException $e) {
        error_log("❌ Trust score update error: " . $e->getMessage());
        return [
            'status' => 'error',
            'message' => $e->getMessage()
        ];
    }
}

/**
 * Get user trust score and reputation details
 * 
 * @param int $userId User ID
 * @return array User reputation data
 */
function getUserReputation($userId) {
    try {
        $pdo = getDBConnection();
        
        // Get user info
        $stmt = $pdo->prepare("SELECT id, username, trust_score, created_at FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            return [
                'status' => 'error',
                'message' => 'User not found'
            ];
        }
        
        // Count reports by status
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_reports,
                SUM(CASE WHEN status = 'verified' THEN 1 ELSE 0 END) as verified_reports,
                SUM(CASE WHEN status = 'solved' THEN 1 ELSE 0 END) as solved_reports,
                SUM(CASE WHEN status IN ('false', 'spam') THEN 1 ELSE 0 END) as false_reports
            FROM (
                SELECT status FROM analysis_reports WHERE user_id = ?
                UNION ALL
                SELECT status FROM general_reports WHERE user_id = ?
            ) as all_reports
        ");
        $stmt->execute([$userId, $userId]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Calculate reputation level based on trust score
        $trustScore = floatval($user['trust_score']);
        $level = 'Novice';
        if ($trustScore >= 4.0) {
            $level = 'Expert';
        } elseif ($trustScore >= 3.0) {
            $level = 'Veteran';
        } elseif ($trustScore >= 2.0) {
            $level = 'Trusted';
        } elseif ($trustScore >= 1.5) {
            $level = 'Reliable';
        }
        
        return [
            'status' => 'success',
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'trust_score' => $trustScore,
                'reputation_level' => $level,
                'member_since' => $user['created_at']
            ],
            'stats' => [
                'total_reports' => intval($stats['total_reports']),
                'verified_reports' => intval($stats['verified_reports']),
                'solved_reports' => intval($stats['solved_reports']),
                'false_reports' => intval($stats['false_reports']),
                'verification_rate' => $stats['total_reports'] > 0 ? 
                    round(($stats['verified_reports'] / $stats['total_reports']) * 100, 1) : 0
            ]
        ];
        
    } catch (PDOException $e) {
        error_log("❌ Get reputation error: " . $e->getMessage());
        return [
            'status' => 'error',
            'message' => $e->getMessage()
        ];
    }
}

/**
 * Apply trust score multiplier to new report confidence
 * 
 * @param float $baseConfidence Base confidence score (0-1)
 * @param int $userId User ID
 * @return float Adjusted confidence
 */
function applyTrustMultiplier($baseConfidence, $userId) {
    try {
        $pdo = getDBConnection();
        
        $stmt = $pdo->prepare("SELECT trust_score FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            $trustScore = floatval($user['trust_score']);
            // Trust score acts as a multiplier (0.0 to 5.0 -> 0% to 100% bonus)
            // Normalized: trust_score / 5.0 gives 0.0 to 1.0
            // We'll use it as: base * (1 + trust_score / 10)
            $multiplier = 1.0 + ($trustScore / 10.0);
            $adjustedConfidence = min(1.0, $baseConfidence * $multiplier);
            
            return $adjustedConfidence;
        }
        
        return $baseConfidence;
        
    } catch (PDOException $e) {
        error_log("❌ Trust multiplier error: " . $e->getMessage());
        return $baseConfidence;
    }
}

/**
 * API endpoint
 */
if (php_sapi_name() !== 'cli') {
    header("Content-Type: application/json; charset=UTF-8");
    
    startSession();
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Get reputation
        $userId = isset($_GET['user_id']) ? intval($_GET['user_id']) : getCurrentUserId();
        
        if (!$userId) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'User ID required']);
            exit;
        }
        
        $result = getUserReputation($userId);
        echo json_encode($result, JSON_PRETTY_PRINT);
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Update trust score (admin only)
        requireRole('admin');
        
        $input = json_decode(file_get_contents('php://input'), true);
        $userId = isset($input['user_id']) ? intval($input['user_id']) : 0;
        $action = isset($input['action']) ? $input['action'] : '';
        
        if ($userId === 0 || !in_array($action, ['verified', 'false', 'spam'])) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Invalid user_id or action']);
            exit;
        }
        
        $result = updateTrustScore($userId, $action);
        echo json_encode($result, JSON_PRETTY_PRINT);
        
    } else {
        http_response_code(405);
        echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    }
}
?>

