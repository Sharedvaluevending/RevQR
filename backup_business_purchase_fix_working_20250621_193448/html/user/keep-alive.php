<?php
// Enhanced session keep-alive
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';

header('Content-Type: application/json');

if (is_logged_in()) {
    $_SESSION['last_activity'] = time();
    echo json_encode([
        'success' => true, 
        'message' => 'Session updated',
        'user_id' => $_SESSION['user_id'],
        'last_activity' => $_SESSION['last_activity']
    ]);
} else {
    echo json_encode([
        'success' => false, 
        'error' => 'No valid session',
        'code' => 401
    ]);
}
?> 