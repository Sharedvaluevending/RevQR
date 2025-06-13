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

$success = false;
$error = '';

try {
    // Validate input
    $username = trim($_POST['username'] ?? '');
    
    if (empty($username)) {
        throw new Exception('Username is required');
    }
    
    if (strlen($username) < 3) {
        throw new Exception('Username must be at least 3 characters long');
    }
    
    // Check if username is already taken by another user
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
    $stmt->execute([$username, $_SESSION['user_id']]);
    if ($stmt->fetch()) {
        throw new Exception('This username is already taken');
    }
    
    // Update user information
    $stmt = $pdo->prepare("UPDATE users SET username = ?, updated_at = NOW() WHERE id = ?");
    if ($stmt->execute([$username, $_SESSION['user_id']])) {
        // Update session variables
        $_SESSION['username'] = $username;
        
        $_SESSION['success_message'] = 'Profile updated successfully!';
        $success = true;
    } else {
        throw new Exception('Failed to update profile. Please try again.');
    }
    
} catch (Exception $e) {
    $_SESSION['error_message'] = $e->getMessage();
}

// Redirect back to profile page
redirect('/user/profile.php');
?> 