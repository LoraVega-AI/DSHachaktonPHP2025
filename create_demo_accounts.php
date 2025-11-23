<?php
// create_demo_accounts.php - Create demo accounts for testing

header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/db_config.php';

try {
    $pdo = getDBConnection();
    
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
            'trust_score' => 8 // Trusted user
        ],
        [
            'username' => 'john_doe',
            'email' => 'john@urbanpulse.demo',
            'password' => 'john123',
            'role' => 'user',
            'trust_score' => 15 // Expert user
        ],
        [
            'username' => 'sarah_smith',
            'email' => 'sarah@urbanpulse.demo',
            'password' => 'sarah123',
            'role' => 'user',
            'trust_score' => 3 // Novice user
        ],
        [
            'username' => 'mike_jones',
            'email' => 'mike@urbanpulse.demo',
            'password' => 'mike123',
            'role' => 'user',
            'trust_score' => 0 // New user
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
        
        // Insert user with trust_score
        $trustScore = $account['trust_score'] ?? 0;
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

