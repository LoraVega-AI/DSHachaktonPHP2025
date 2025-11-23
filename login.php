<?php
// login.php - User login endpoint

// Start session FIRST before any output
session_start();

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Credentials: true");

// Include authentication functions
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db_config.php';

// Ensure session is started
startSession();

// Initialize database and demo accounts if needed
try {
    initializeUsersTable();
    
    // Check if demo accounts exist, create them if not
    $pdo = getDBConnection();
    $checkDemo = $pdo->query("SELECT COUNT(*) as count FROM users WHERE username IN ('admin', 'user1', 'crew_demo', 'alex_tech')");
    $demoCount = $checkDemo->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($demoCount < 4) {
        // Demo accounts missing, create them inline
        $demoAccounts = [
            ['username' => 'admin', 'email' => 'admin@urbanpulse.demo', 'password' => 'admin123', 'role' => 'admin', 'trust_score' => 20],
            ['username' => 'user1', 'email' => 'user1@urbanpulse.demo', 'password' => 'user123', 'role' => 'user', 'trust_score' => 2.5],
            ['username' => 'john_doe', 'email' => 'john@urbanpulse.demo', 'password' => 'john123', 'role' => 'user', 'trust_score' => 4.5],
            ['username' => 'crew_demo', 'email' => 'crew@urbanpulse.demo', 'password' => 'crew123', 'role' => 'crew', 'trust_score' => 3.5],
            ['username' => 'alex_tech', 'email' => 'alex@urbanpulse.demo', 'password' => 'alex123', 'role' => 'crew', 'trust_score' => 4.2],
        ];
        
        foreach ($demoAccounts as $account) {
            $checkStmt = $pdo->prepare("SELECT id FROM users WHERE username = :username");
            $checkStmt->execute([':username' => $account['username']]);
            if (!$checkStmt->fetch()) {
                $passwordHash = password_hash($account['password'], PASSWORD_DEFAULT);
                $insertStmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, role, trust_score) VALUES (:username, :email, :password_hash, :role, :trust_score)");
                $insertStmt->execute([
                    ':username' => $account['username'],
                    ':email' => $account['email'],
                    ':password_hash' => $passwordHash,
                    ':role' => $account['role'],
                    ':trust_score' => $account['trust_score']
                ]);
                error_log("✅ Created demo account: {$account['username']}");
            }
        }
    }
} catch (Exception $e) {
    error_log("Note: Demo account initialization: " . $e->getMessage());
}

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

try {
    // Check if it's a POST request
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Only POST requests are allowed');
    }
    
    // Get JSON input
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    // If JSON parsing failed, try to get POST data
    if (json_last_error() !== JSON_ERROR_NONE) {
        $data = $_POST;
    }
    
    // Get login credentials
    $username = $data['username'] ?? '';
    $password = $data['password'] ?? '';
    
    // Validate required fields
    if (empty($username) || empty($password)) {
        throw new Exception('Username and password are required');
    }
    
    // Call login function
    $result = login($username, $password);
    
    // Set appropriate HTTP status code
    if ($result['status'] === 'error') {
        http_response_code(401); // Unauthorized
    } else {
        http_response_code(200);
        error_log("✅ User logged in successfully: $username");
    }
    
    echo json_encode($result, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
    error_log("❌ Error in login.php: " . $e->getMessage());
}
?>

