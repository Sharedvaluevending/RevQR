<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';

// Ensure user is logged in
if (!is_logged_in()) {
    redirect('/login.php');
}

// Only handle POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/user/profile.php');
}

try {
    // Validate input
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($current_password)) {
        throw new Exception('Current password is required');
    }
    
    if (empty($new_password)) {
        throw new Exception('New password is required');
    }
    
    if (strlen($new_password) < 6) {
        throw new Exception('New password must be at least 6 characters long');
    }
    
    if ($new_password !== $confirm_password) {
        throw new Exception('New password and confirmation do not match');
    }
    
    // Get current user's password hash
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    if (!$user) {
        throw new Exception('User not found');
    }
    
    // Verify current password
    if (!password_verify($current_password, $user['password'])) {
        throw new Exception('Current password is incorrect');
    }
    
    // Hash new password
    $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
    
    // Update password
    $stmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
    if ($stmt->execute([$new_password_hash, $_SESSION['user_id']])) {
        $_SESSION['success_message'] = 'Password changed successfully!';
    } else {
        throw new Exception('Failed to update password. Please try again.');
    }
    
} catch (Exception $e) {
    $_SESSION['error_message'] = $e->getMessage();
}

// Redirect back to profile page
redirect('/user/profile.php');
?> 