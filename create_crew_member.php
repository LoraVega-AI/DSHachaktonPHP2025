<?php
// create_crew_member.php - Create or update crew member accounts

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
    
    // Require admin authentication
    startSession();
    if (!isLoggedIn()) {
        http_response_code(401);
        throw new Exception('Authentication required');
    }
    
    $userRole = getUserRole();
    // Both admin and crew managers can create crew members
    if ($userRole !== 'admin' && $userRole !== 'crew') {
        http_response_code(403);
        throw new Exception('Admin or Crew manager access required');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        $input = $_POST;
    }
    
    $username = trim($input['username'] ?? '');
    $email = trim($input['email'] ?? '');
    $password = $input['password'] ?? '';
    $crewId = isset($input['id']) ? intval($input['id']) : null;
    
    // Validate required fields
    if (empty($username) || empty($email)) {
        throw new Exception('Username and email are required');
    }
    
    // If creating new, password is required
    if (!$crewId && empty($password)) {
        throw new Exception('Password is required for new crew members');
    }
    
    // Validate username length
    if (strlen($username) < 3 || strlen($username) > 100) {
        throw new Exception('Username must be between 3 and 100 characters');
    }
    
    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email address');
    }
    
    // Validate password length if provided
    if (!empty($password) && strlen($password) < 6) {
        throw new Exception('Password must be at least 6 characters');
    }
    
    $pdo = getDBConnection();
    
    // Check if username already exists (excluding current user if editing)
    $checkUsername = $pdo->prepare("SELECT id FROM users WHERE username = :username" . ($crewId ? " AND id != :id" : ""));
    $checkUsername->bindValue(':username', $username);
    if ($crewId) {
        $checkUsername->bindValue(':id', $crewId, PDO::PARAM_INT);
    }
    $checkUsername->execute();
    
    if ($checkUsername->fetch()) {
        throw new Exception('Username already exists');
    }
    
    // Check if email already exists (excluding current user if editing)
    $checkEmail = $pdo->prepare("SELECT id FROM users WHERE email = :email" . ($crewId ? " AND id != :id" : ""));
    $checkEmail->bindValue(':email', $email);
    if ($crewId) {
        $checkEmail->bindValue(':id', $crewId, PDO::PARAM_INT);
    }
    $checkEmail->execute();
    
    if ($checkEmail->fetch()) {
        throw new Exception('Email already exists');
    }
    
    if ($crewId) {
        // Update existing crew member
        if (!empty($password)) {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET username = :username, email = :email, password_hash = :password_hash WHERE id = :id");
            $stmt->execute([
                ':username' => $username,
                ':email' => $email,
                ':password_hash' => $passwordHash,
                ':id' => $crewId
            ]);
        } else {
            $stmt = $pdo->prepare("UPDATE users SET username = :username, email = :email WHERE id = :id");
            $stmt->execute([
                ':username' => $username,
                ':email' => $email,
                ':id' => $crewId
            ]);
        }
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Crew member updated successfully',
            'crew_id' => $crewId
        ], JSON_PRETTY_PRINT);
        
    } else {
        // Create new crew member
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        
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
        
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, role, trust_score) VALUES (:username, :email, :password_hash, 'crew', 1.0)");
        $stmt->execute([
            ':username' => $username,
            ':email' => $email,
            ':password_hash' => $passwordHash
        ]);
        
        $newCrewId = $pdo->lastInsertId();
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Crew member created successfully',
            'crew_id' => $newCrewId
        ], JSON_PRETTY_PRINT);
        
        error_log("✅ Crew member created: $username (ID: $newCrewId)");
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
    error_log("❌ Error in create_crew_member.php: " . $e->getMessage());
}
?>

