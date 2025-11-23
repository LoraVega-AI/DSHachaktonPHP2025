<?php
// create_crew_demo.php - Quick script to create crew_demo account

header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/db_config.php';

try {
    $pdo = getDBConnection();
    
    // Ensure role ENUM includes 'crew'
    try {
        $checkRole = $pdo->query("SHOW COLUMNS FROM users WHERE Field = 'role'");
        $roleCol = $checkRole->fetch(PDO::FETCH_ASSOC);
        if ($roleCol && strpos($roleCol['Type'], 'crew') === false) {
            $pdo->exec("ALTER TABLE users MODIFY COLUMN role ENUM('guest', 'user', 'admin', 'crew') NOT NULL DEFAULT 'user'");
            error_log("✅ Updated role ENUM to include 'crew'");
        }
    } catch (PDOException $e) {
        error_log("Note: Role ENUM check: " . $e->getMessage());
    }
    
    // Check if crew_demo already exists
    $checkStmt = $pdo->prepare("SELECT id, username, role FROM users WHERE username = 'crew_demo' OR email = 'crew@urbanpulse.demo'");
    $checkStmt->execute();
    $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing) {
        // Account exists - check if role is correct
        if ($existing['role'] !== 'crew') {
            // Update role to crew
            $updateStmt = $pdo->prepare("UPDATE users SET role = 'crew' WHERE id = ?");
            $updateStmt->execute([$existing['id']]);
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Crew demo account already exists - role updated to crew',
                'user_id' => $existing['id'],
                'username' => $existing['username'],
                'action' => 'updated'
            ], JSON_PRETTY_PRINT);
        } else {
            echo json_encode([
                'status' => 'success',
                'message' => 'Crew demo account already exists',
                'user_id' => $existing['id'],
                'username' => $existing['username'],
                'action' => 'exists'
            ], JSON_PRETTY_PRINT);
        }
    } else {
        // Create the account
        $passwordHash = password_hash('crew123', PASSWORD_DEFAULT);
        
        $insertStmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, role, trust_score) VALUES (?, ?, ?, 'crew', 3.5)");
        $result = $insertStmt->execute([
            'crew_demo',
            'crew@urbanpulse.demo',
            $passwordHash
        ]);
        
        if ($result) {
            $userId = $pdo->lastInsertId();
            
            // Create default schedule
            try {
                $pdo->exec("CREATE TABLE IF NOT EXISTS crew_schedule (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    day_of_week INT NOT NULL,
                    start_time TIME NOT NULL,
                    end_time TIME NOT NULL,
                    is_available BOOLEAN DEFAULT TRUE,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                    INDEX idx_user_day (user_id, day_of_week),
                    UNIQUE KEY unique_user_day (user_id, day_of_week)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
                
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
                error_log("Note: Schedule creation: " . $e->getMessage());
            }
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Crew demo account created successfully',
                'user_id' => $userId,
                'username' => 'crew_demo',
                'password' => 'crew123',
                'action' => 'created'
            ], JSON_PRETTY_PRINT);
        } else {
            throw new Exception('Failed to create account');
        }
    }
    
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

