<?php
/**
 * Session Test API - Debug authentication issues
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    echo json_encode(['status' => 'OK']);
    exit;
}

try {
    require_once __DIR__ . '/../../core/config.php';
    require_once __DIR__ . '/../../core/session.php';
    
    $response = [
        'session_status' => session_status(),
        'session_id' => session_id(),
        'session_name' => session_name(),
        'cookie_params' => session_get_cookie_params(),
        'is_logged_in' => function_exists('is_logged_in') ? is_logged_in() : false,
        'user_id' => $_SESSION['user_id'] ?? null,
        'role' => $_SESSION['role'] ?? null,
        'session_data' => $_SESSION ?? [],
        'request_method' => $_SERVER['REQUEST_METHOD'],
        'cookies_received' => $_COOKIE ?? [],
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Session test failed',
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
?> 