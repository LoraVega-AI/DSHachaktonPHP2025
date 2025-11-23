<?php
// auth.php - Authentication and session management for user roles system

// Prevent direct access
if (!defined('AUTH_INCLUDED')) {
    define('AUTH_INCLUDED', true);
}

// Include database configuration
require_once __DIR__ . '/db_config.php';

/**
 * Start PHP session if not already started
 */
function startSession() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

/**
 * Check if user is logged in
 * @return bool True if user is logged in
 */
function isLoggedIn() {
    startSession();
    return isset($_SESSION['user_id']) && isset($_SESSION['username']) && isset($_SESSION['role']);
}

/**
 * Get current logged-in user data
 * @return array|null User data array or null if not logged in
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    return [
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'email' => $_SESSION['email'] ?? null,
        'role' => $_SESSION['role']
    ];
}

/**
 * Get current user role
 * @return string|null Role (guest/user/admin) or null if not logged in
 */
function getUserRole() {
    if (!isLoggedIn()) {
        return 'guest'; // Not logged in = guest
    }
    return $_SESSION['role'];
}

/**
 * Check if user has a specific role or higher
 * @param string $requiredRole Required role (user/admin)
 * @return bool True if user has required role
 */
function hasRole($requiredRole) {
    $currentRole = getUserRole();
    
    // Define role hierarchy
    $roleHierarchy = [
        'guest' => 0,
        'user' => 1,
        'admin' => 2
    ];
    
    $currentLevel = $roleHierarchy[$currentRole] ?? 0;
    $requiredLevel = $roleHierarchy[$requiredRole] ?? 99;
    
    return $currentLevel >= $requiredLevel;
}

/**
 * Require user to be logged in, redirect if not
 * @param string $redirectUrl URL to redirect to if not logged in
 */
function requireLogin($redirectUrl = 'index.html') {
    if (!isLoggedIn()) {
        header("Location: $redirectUrl?error=login_required");
        exit;
    }
}

/**
 * Require user to have a specific role, return error if not
 * @param string $requiredRole Required role
 * @return bool True if user has role, otherwise sends JSON error and exits
 */
function requireRole($requiredRole) {
    if (!hasRole($requiredRole)) {
        header('Content-Type: application/json');
        http_response_code(403);
        echo json_encode([
            'status' => 'error',
            'message' => 'Access denied. Required role: ' . $requiredRole,
            'current_role' => getUserRole()
        ]);
        exit;
    }
    return true;
}

/**
 * Authenticate user and create session
 * @param string $username Username or email
 * @param string $password Plain text password
 * @return array Result array with status and user data or error message
 */
function login($username, $password) {
    try {
        $pdo = getDBConnection();
        
        // Find user by username or email
        $stmt = $pdo->prepare("SELECT id, username, email, password_hash, role FROM users WHERE username = :username OR email = :email");
        $stmt->execute([
            ':username' => $username,
            ':email' => $username
        ]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            return [
                'status' => 'error',
                'message' => 'Invalid username or password'
            ];
        }
        
        // Verify password
        if (!password_verify($password, $user['password_hash'])) {
            return [
                'status' => 'error',
                'message' => 'Invalid username or password'
            ];
        }
        
        // Create session
        startSession();
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $user['role'];
        
        return [
            'status' => 'success',
            'message' => 'Login successful',
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'role' => $user['role']
            ]
        ];
        
    } catch (PDOException $e) {
        error_log("Login error: " . $e->getMessage());
        return [
            'status' => 'error',
            'message' => 'Database error occurred'
        ];
    }
}

/**
 * Log out current user and destroy session
 * @return array Result array with status
 */
function logout() {
    startSession();
    
    // Unset all session variables
    $_SESSION = array();
    
    // Destroy the session cookie
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    
    // Destroy the session
    session_destroy();
    
    return [
        'status' => 'success',
        'message' => 'Logged out successfully'
    ];
}

/**
 * Register a new user
 * @param string $username Username
 * @param string $email Email address
 * @param string $password Plain text password
 * @return array Result array with status and user data or error message
 */
function registerUser($username, $email, $password) {
    try {
        $pdo = getDBConnection();
        
        // Validate input
        if (empty($username) || empty($email) || empty($password)) {
            return [
                'status' => 'error',
                'message' => 'All fields are required'
            ];
        }
        
        // Validate username length
        if (strlen($username) < 3 || strlen($username) > 100) {
            return [
                'status' => 'error',
                'message' => 'Username must be between 3 and 100 characters'
            ];
        }
        
        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [
                'status' => 'error',
                'message' => 'Invalid email address'
            ];
        }
        
        // Validate password length
        if (strlen($password) < 6) {
            return [
                'status' => 'error',
                'message' => 'Password must be at least 6 characters'
            ];
        }
        
        // Check if username already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = :username");
        $stmt->execute([':username' => $username]);
        if ($stmt->fetch()) {
            return [
                'status' => 'error',
                'message' => 'Username already exists'
            ];
        }
        
        // Check if email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
        $stmt->execute([':email' => $email]);
        if ($stmt->fetch()) {
            return [
                'status' => 'error',
                'message' => 'Email already exists'
            ];
        }
        
        // Hash password
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert user
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, role) VALUES (:username, :email, :password_hash, 'user')");
        $result = $stmt->execute([
            ':username' => $username,
            ':email' => $email,
            ':password_hash' => $passwordHash
        ]);
        
        if (!$result) {
            return [
                'status' => 'error',
                'message' => 'Failed to create user'
            ];
        }
        
        $userId = $pdo->lastInsertId();
        
        // Auto-login after registration
        startSession();
        $_SESSION['user_id'] = $userId;
        $_SESSION['username'] = $username;
        $_SESSION['email'] = $email;
        $_SESSION['role'] = 'user';
        
        return [
            'status' => 'success',
            'message' => 'Registration successful',
            'user' => [
                'id' => $userId,
                'username' => $username,
                'email' => $email,
                'role' => 'user'
            ]
        ];
        
    } catch (PDOException $e) {
        error_log("Registration error: " . $e->getMessage());
        return [
            'status' => 'error',
            'message' => 'Database error occurred'
        ];
    }
}

/**
 * Get user ID from session or null if not logged in
 * @return int|null User ID or null
 */
function getCurrentUserId() {
    if (!isLoggedIn()) {
        return null;
    }
    return $_SESSION['user_id'] ?? null;
}

/**
 * Check authentication status and return as JSON
 * @return array Auth status with user data
 */
function getAuthStatus() {
    if (isLoggedIn()) {
        return [
            'authenticated' => true,
            'user' => getCurrentUser()
        ];
    } else {
        return [
            'authenticated' => false,
            'user' => null,
            'role' => 'guest'
        ];
    }
}
?>

