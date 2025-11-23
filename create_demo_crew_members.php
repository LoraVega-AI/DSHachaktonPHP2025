<?php
// create_demo_crew_members.php - Create 3 demo crew member accounts

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

require_once __DIR__ . '/db_config.php';

try {
    $pdo = getDBConnection();
    
    // Ensure role ENUM includes 'crew'
    try {
        $checkRole = $pdo->query("SHOW COLUMNS FROM users WHERE Field = 'role'");
        $roleCol = $checkRole->fetch(PDO::FETCH_ASSOC);
        if ($roleCol && strpos($roleCol['Type'], 'crew') === false) {
            $pdo->exec("ALTER TABLE users MODIFY COLUMN role ENUM('guest', 'user', 'admin', 'crew') NOT NULL DEFAULT 'user'");
        }
    } catch (PDOException $e) {
        error_log("Note: Role ENUM check: " . $e->getMessage());
    }
    
    // Ensure trust_score column exists
    try {
        $checkTrustScore = $pdo->query("SHOW COLUMNS FROM users LIKE 'trust_score'");
        if ($checkTrustScore->rowCount() === 0) {
            $pdo->exec("ALTER TABLE users ADD COLUMN trust_score DECIMAL(5,2) DEFAULT 1.0 AFTER role");
        }
    } catch (PDOException $e) {
        // Column might already exist
    }
    
    // 3 demo crew member accounts
    $demoMembers = [
        [
            'username' => 'worker1',
            'email' => 'worker1@urbanpulse.demo',
            'password' => 'worker123',
            'role' => 'crew',
            'trust_score' => 2.5 // Basic crew member
        ],
        [
            'username' => 'worker2',
            'email' => 'worker2@urbanpulse.demo',
            'password' => 'worker123',
            'role' => 'crew',
            'trust_score' => 3.5 // Mid-level crew member
        ],
        [
            'username' => 'worker3',
            'email' => 'worker3@urbanpulse.demo',
            'password' => 'worker123',
            'role' => 'crew',
            'trust_score' => 1.5 // New crew member
        ]
    ];
    
    $created = [];
    $skipped = [];
    
    foreach ($demoMembers as $member) {
        // Check if user already exists
        $checkStmt = $pdo->prepare("SELECT id FROM users WHERE username = :username OR email = :email");
        $checkStmt->execute([
            ':username' => $member['username'],
            ':email' => $member['email']
        ]);
        
        if ($checkStmt->fetch()) {
            $skipped[] = $member['username'];
            continue;
        }
        
        // Hash password
        $passwordHash = password_hash($member['password'], PASSWORD_DEFAULT);
        
        // Insert user
        $insertStmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, role, trust_score) VALUES (:username, :email, :password_hash, :role, :trust_score)");
        $result = $insertStmt->execute([
            ':username' => $member['username'],
            ':email' => $member['email'],
            ':password_hash' => $passwordHash,
            ':role' => $member['role'],
            ':trust_score' => $member['trust_score']
        ]);
        
        if ($result) {
            $userId = $pdo->lastInsertId();
            $created[] = [
                'id' => $userId,
                'username' => $member['username'],
                'email' => $member['email'],
                'role' => $member['role'],
                'trust_score' => $member['trust_score']
            ];
            
            // Create default schedule for crew member
            try {
                // Ensure crew_schedule table exists
                $pdo->exec("CREATE TABLE IF NOT EXISTS crew_schedule (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    day_of_week INT NOT NULL COMMENT '0=Sunday, 1=Monday, ..., 6=Saturday',
                    start_time TIME NOT NULL,
                    end_time TIME NOT NULL,
                    is_available BOOLEAN DEFAULT TRUE,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                    INDEX idx_user_day (user_id, day_of_week),
                    UNIQUE KEY unique_user_day (user_id, day_of_week)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
                
                // Create default schedule: Monday-Friday, 8am-5pm
                $defaultSchedule = [
                    [1, '08:00:00', '17:00:00'], // Monday
                    [2, '08:00:00', '17:00:00'], // Tuesday
                    [3, '08:00:00', '17:00:00'], // Wednesday
                    [4, '08:00:00', '17:00:00'], // Thursday
                    [5, '08:00:00', '17:00:00'], // Friday
                ];
                
                $scheduleStmt = $pdo->prepare("INSERT INTO crew_schedule (user_id, day_of_week, start_time, end_time, is_available) VALUES (?, ?, ?, ?, TRUE)");
                foreach ($defaultSchedule as $schedule) {
                    $scheduleStmt->execute([$userId, $schedule[0], $schedule[1], $schedule[2]]);
                }
            } catch (PDOException $e) {
                error_log("Note: Could not create schedule for {$member['username']}: " . $e->getMessage());
            }
        }
    }
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Demo crew members created',
        'created' => $created,
        'skipped' => $skipped,
        'total_created' => count($created),
        'total_skipped' => count($skipped)
    ], JSON_PRETTY_PRINT);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ], JSON_PRETTY_PRINT);
    error_log("❌ Error in create_demo_crew_members.php: " . $e->getMessage());
}
?>

