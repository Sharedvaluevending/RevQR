<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/session.php';

// User roles
define('ROLE_ADMIN', 'admin');
define('ROLE_BUSINESS', 'business');
define('ROLE_USER', 'user');

// Authentication functions
function authenticate_user($username, $password) {
    global $pdo;
    
    error_log("Attempting authentication for username: " . $username);
    
    // Get user by username
    $stmt = $pdo->prepare("
        SELECT id, username, password_hash, role, business_id 
        FROM users 
        WHERE username = ?
    ");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if (!$user) {
        error_log("No user found with username: " . $username);
        return false;
    }
    
    error_log("Found user: " . print_r([
        'id' => $user['id'],
        'username' => $user['username'],
        'role' => $user['role'],
        'business_id' => $user['business_id']
    ], true));
    
    if (!password_verify($password, $user['password_hash'])) {
        error_log("Password verification failed for user: " . $username);
        return false;
    }
    
    error_log("Authentication successful for user: " . $username);
    return [
        'user_id' => $user['id'],
        'role' => $user['role'],
        'business_id' => $user['business_id']
    ];
}

function create_user($username, $password, $role, $business_id = null) {
    global $pdo;
    
    error_log("Creating new user: " . $username . " with role: " . $role);
    
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    
    $stmt = $pdo->prepare("
        INSERT INTO users (username, password_hash, role, business_id, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ");
    
    $result = $stmt->execute([$username, $password_hash, $role, $business_id]);
    
    if ($result) {
        error_log("Successfully created user: " . $username);
    } else {
        error_log("Failed to create user: " . $username . " - " . print_r($stmt->errorInfo(), true));
    }
    
    return $result;
}

function update_user_password($user_id, $new_password) {
    global $pdo;
    
    error_log("Updating password for user ID: " . $user_id);
    
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    
    $stmt = $pdo->prepare("
        UPDATE users 
        SET password_hash = ?, updated_at = NOW() 
        WHERE id = ?
    ");
    
    $result = $stmt->execute([$hashed_password, $user_id]);
    
    if ($result) {
        error_log("Successfully updated password for user ID: " . $user_id);
    } else {
        error_log("Failed to update password for user ID: " . $user_id . " - " . print_r($stmt->errorInfo(), true));
    }
    
    return $result;
}

function get_user_data($user_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT id, username, role, business_id, created_at, updated_at 
        FROM users 
        WHERE id = ?
    ");
    
    $stmt->execute([$user_id]);
    return $stmt->fetch();
}

// Role-based access control
function can_access($required_role) {
    if (!is_logged_in()) {
        error_log("Access denied: User not logged in");
        return false;
    }
    
    $user_role = $_SESSION['role'];
    error_log("Checking access for role: " . $user_role . " against required role: " . $required_role);
    
    // Admin can access everything
    if ($user_role === ROLE_ADMIN) {
        error_log("Access granted: User is admin");
        return true;
    }
    
    // Business can access business and user areas
    if ($user_role === ROLE_BUSINESS && in_array($required_role, [ROLE_BUSINESS, ROLE_USER])) {
        error_log("Access granted: User is business and required role is allowed");
        return true;
    }
    
    // Users can only access user areas
    $result = $user_role === $required_role;
    error_log("Access " . ($result ? "granted" : "denied") . ": User role matches required role");
    return $result;
} 