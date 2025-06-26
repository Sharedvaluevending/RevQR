<?php
/**
 * Session Refresh Endpoint
 * Handles session renewal for balance sync and other AJAX operations
 */
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

try {
    // Check if session exists and is valid
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    
    $user_id = $_SESSION['user_id'] ?? null;
    
    if (!$user_id || !is_logged_in()) {
        // Session is invalid - return failure
        echo json_encode([
            'success' => false,
            'error' => 'Session expired or invalid',
            'code' => 401,
            'action' => 'redirect_login'
        ]);
        exit;
    }
    
    // Refresh session activity timestamp
    $_SESSION['last_activity'] = time();
    
    // Update session in database if using database sessions
    if (isset($_SESSION['session_token'])) {
        global $pdo;
        $stmt = $pdo->prepare("
            UPDATE user_sessions 
            SET last_activity = NOW(), 
                expires_at = DATE_ADD(NOW(), INTERVAL 24 HOUR)
            WHERE session_token = ? AND user_id = ?
        ");
        $stmt->execute([$_SESSION['session_token'], $user_id]);
    }
    
    // Verify user still exists and is active
    global $pdo;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE id = ? AND status = 'active'");
    $stmt->execute([$user_id]);
    
    if (!$stmt->fetchColumn()) {
        // User no longer exists or is inactive
        session_destroy();
        echo json_encode([
            'success' => false,
            'error' => 'User account inactive',
            'code' => 403,
            'action' => 'redirect_login'
        ]);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Session refreshed',
        'user_id' => $user_id,
        'timestamp' => time(),
        'expires_in' => 86400 // 24 hours
    ]);
    
} catch (Exception $e) {
    error_log("Session refresh error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Session refresh failed',
        'code' => 500
    ]);
}
?> 