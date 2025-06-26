<?php
// Auto-login functionality for returning users
// Include this file AFTER session.php and config.php

/**
 * Check for auto-login opportunity and attempt login if possible
 */
function attemptAutoLogin() {
    global $pdo;
    
    // Don't auto-login if already logged in
    if (is_logged_in()) {
        return false;
    }
    
    // Check for remember token cookie
    if (isset($_COOKIE['remember_user']) && !empty($_COOKIE['remember_user'])) {
        $user_token = $_COOKIE['remember_user'];
        try {
            // Look for user with this remember token
            $stmt = $pdo->prepare("
                SELECT id, username, role, business_id, remember_token_expires 
                FROM users 
                WHERE remember_token = ? 
                AND remember_token IS NOT NULL 
                AND (remember_token_expires IS NULL OR remember_token_expires > NOW())
            ");
            $stmt->execute([$user_token]);
            $user = $stmt->fetch();
            
            if ($user) {
                // Auto-login the user
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['business_id'] = $user['business_id'];
                
                // Extend the remember token expiration
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET remember_token_expires = DATE_ADD(NOW(), INTERVAL 30 DAY) 
                    WHERE id = ?
                ");
                $stmt->execute([$user['id']]);
                
                error_log("Auto-login successful for user: " . $user['username']);
                return true;
            }
        } catch (Exception $e) {
            error_log("Auto-login error: " . $e->getMessage());
        }
    }
    
    return false;
}

/**
 * Set a remember token for a user (call after successful login)
 */
function setRememberToken($user_id) {
    global $pdo;
    
    // Generate secure random token
    $token = bin2hex(random_bytes(32));
    
    try {
        // Update user with remember token
        $stmt = $pdo->prepare("
            UPDATE users 
            SET remember_token = ?, remember_token_expires = DATE_ADD(NOW(), INTERVAL 30 DAY)
            WHERE id = ?
        ");
        $stmt->execute([$token, $user_id]);
        
        // Set cookie for 30 days
        setcookie('remember_user', $token, time() + (30 * 24 * 60 * 60), '/', '', true, true);
        
        return true;
    } catch (Exception $e) {
        error_log("Remember token error: " . $e->getMessage());
        return false;
    }
}

/**
 * Clear remember token (call on logout)
 */
function clearRememberToken($user_id = null) {
    global $pdo;
    
    // Clear cookie
    if (isset($_COOKIE['remember_user'])) {
        setcookie('remember_user', '', time() - 3600, '/', '', true, true);
    }
    
    // Clear from database if user_id provided
    if ($user_id) {
        try {
            $stmt = $pdo->prepare("
                UPDATE users 
                SET remember_token = NULL, remember_token_expires = NULL 
                WHERE id = ?
            ");
            $stmt->execute([$user_id]);
        } catch (Exception $e) {
            error_log("Clear remember token error: " . $e->getMessage());
        }
    }
}

// Auto-attempt login when this file is included
attemptAutoLogin();
?> 