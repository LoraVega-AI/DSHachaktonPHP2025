<?php
// create_demo_accounts.php - Create demo accounts for testing

header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/db_config.php';

try {
    $pdo = getDBConnection();
    
    // Ensure trust_score column exists (DECIMAL type)
    try {
        $checkTrustScore = $pdo->query("SHOW COLUMNS FROM users LIKE 'trust_score'");
        if ($checkTrustScore->rowCount() === 0) {
            $pdo->exec("ALTER TABLE users ADD COLUMN trust_score DECIMAL(5,2) DEFAULT 1.0 AFTER role");
            $pdo->exec("ALTER TABLE users ADD INDEX idx_trust_score (trust_score)");
        } else {
            // Update existing INT to DECIMAL if needed
            $col = $checkTrustScore->fetch(PDO::FETCH_ASSOC);
            if (strpos(strtolower($col['Type']), 'decimal') === false) {
                $pdo->exec("ALTER TABLE users MODIFY COLUMN trust_score DECIMAL(5,2) DEFAULT 1.0");
            }
        }
    } catch (PDOException $e) {
        // Column might already exist, ignore
    }
    
    // Demo accounts to create
    $demoAccounts = [
        [
            'username' => 'admin',
            'email' => 'admin@urbanpulse.demo',
            'password' => 'admin123',
            'role' => 'admin',
            'trust_score' => 20 // Admin starts with high trust score
        ],
        [
            'username' => 'user1',
            'email' => 'user1@urbanpulse.demo',
            'password' => 'user123',
            'role' => 'user',
            'trust_score' => 2.5 // Trusted user
        ],
        [
            'username' => 'john_doe',
            'email' => 'john@urbanpulse.demo',
            'password' => 'john123',
            'role' => 'user',
            'trust_score' => 4.5 // Expert user
        ],
        [
            'username' => 'sarah_smith',
            'email' => 'sarah@urbanpulse.demo',
            'password' => 'sarah123',
            'role' => 'user',
            'trust_score' => 1.2 // Novice user
        ],
        [
            'username' => 'mike_jones',
            'email' => 'mike@urbanpulse.demo',
            'password' => 'mike123',
            'role' => 'user',
            'trust_score' => 1.0 // New user
        ],
        // Crew demo accounts
        [
            'username' => 'crew_demo',
            'email' => 'crew@urbanpulse.demo',
            'password' => 'crew123',
            'role' => 'crew',
            'trust_score' => 3.5 // Experienced crew member
        ],
        [
            'username' => 'alex_tech',
            'email' => 'alex@urbanpulse.demo',
            'password' => 'alex123',
            'role' => 'crew',
            'trust_score' => 4.2 // Senior crew member
        ],
        [
            'username' => 'maria_field',
            'email' => 'maria@urbanpulse.demo',
            'password' => 'maria123',
            'role' => 'crew',
            'trust_score' => 3.8 // Experienced crew member
        ],
        [
            'username' => 'david_repair',
            'email' => 'david@urbanpulse.demo',
            'password' => 'david123',
            'role' => 'crew',
            'trust_score' => 2.9 // Mid-level crew member
        ],
        [
            'username' => 'lisa_crew',
            'email' => 'lisa@urbanpulse.demo',
            'password' => 'lisa123',
            'role' => 'crew',
            'trust_score' => 1.5 // New crew member
        ]
    ];
    
    $created = [];
    $skipped = [];
    
    foreach ($demoAccounts as $account) {
        // Check if user already exists
        $checkStmt = $pdo->prepare("SELECT id FROM users WHERE username = :username OR email = :email");
        $checkStmt->execute([
            ':username' => $account['username'],
            ':email' => $account['email']
        ]);
        
        if ($checkStmt->fetch()) {
            $skipped[] = $account['username'];
            continue;
        }
        
        // Hash password
        $passwordHash = password_hash($account['password'], PASSWORD_DEFAULT);
        
        // Insert user with trust_score (use DECIMAL value)
        $trustScore = isset($account['trust_score']) ? floatval($account['trust_score']) : 1.0;
        $insertStmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, role, trust_score) VALUES (:username, :email, :password_hash, :role, :trust_score)");
        $result = $insertStmt->execute([
            ':username' => $account['username'],
            ':email' => $account['email'],
            ':password_hash' => $passwordHash,
            ':role' => $account['role'],
            ':trust_score' => $trustScore
        ]);
        
        if ($result) {
            $userId = $pdo->lastInsertId();
            $created[] = [
                'id' => $userId,
                'username' => $account['username'],
                'email' => $account['email'],
                'role' => $account['role'],
                'trust_score' => $trustScore,
                'password' => $account['password'] // Include plain password for demo purposes only!
            ];
            
            // If crew member, create default schedule (available Mon-Fri 8am-5pm)
            if ($account['role'] === 'crew') {
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
                    // Schedule creation failed, but account was created - log and continue
                    error_log("Note: Could not create schedule for crew member {$account['username']}: " . $e->getMessage());
                }
            }
        }
    }
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Demo accounts setup complete',
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
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
?>

