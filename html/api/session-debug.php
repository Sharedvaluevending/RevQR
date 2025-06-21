<?php
/**
 * Session Debug API Endpoint
 * Helps troubleshoot authentication and session issues
 */

// Set JSON headers first
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

// Only accept GET requests
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

try {
    require_once __DIR__ . '/../core/config.php';
    
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        session_start();
    }
    
    $debug_info = [
        'session' => [
            'status' => session_status(),
            'status_text' => [
                PHP_SESSION_DISABLED => 'PHP_SESSION_DISABLED',
                PHP_SESSION_NONE => 'PHP_SESSION_NONE',
                PHP_SESSION_ACTIVE => 'PHP_SESSION_ACTIVE'
            ][session_status()],
            'id' => session_id(),
            'name' => session_name(),
            'cookie_params' => session_get_cookie_params()
        ],
        'authentication' => [
            'user_id' => $_SESSION['user_id'] ?? null,
            'role' => $_SESSION['role'] ?? null,
            'business_id' => $_SESSION['business_id'] ?? null,
            'is_logged_in' => isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])
        ],
        'cookies' => [
            'session_cookie_exists' => isset($_COOKIE[session_name()]),
            'session_cookie_value' => $_COOKIE[session_name()] ?? null,
            'all_cookies' => array_keys($_COOKIE)
        ],
        'server' => [
            'current_time' => time(),
            'session_lifetime' => SESSION_LIFETIME,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? null
        ]
    ];
    
    echo json_encode([
        'success' => true,
        'debug' => $debug_info
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    error_log("Session Debug API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => 'Internal server error',
        'message' => $e->getMessage()
    ]);
} 