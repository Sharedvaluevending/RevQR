<?php
/**
 * Track Promotional Message Clicks API
 * Updates promotional message click analytics
 */

require_once __DIR__ . '/../core/config.php';

// Set JSON response headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['tracker_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing tracker_id']);
        exit;
    }
    
    $tracker_id = (int)$input['tracker_id'];
    
    // Validate tracker exists and is active
    $stmt = $pdo->prepare("SELECT id, promo_active FROM pizza_trackers WHERE id = ? AND is_active = 1");
    $stmt->execute([$tracker_id]);
    $tracker = $stmt->fetch();
    
    if (!$tracker) {
        http_response_code(404);
        echo json_encode(['error' => 'Tracker not found or inactive']);
        exit;
    }
    
    if (!$tracker['promo_active']) {
        http_response_code(400);
        echo json_encode(['error' => 'Promotional message not active']);
        exit;
    }
    
    // Update click count
    $stmt = $pdo->prepare("
        UPDATE pizza_trackers 
        SET promo_clicks = promo_clicks + 1 
        WHERE id = ?
    ");
    $success = $stmt->execute([$tracker_id]);
    
    if ($success) {
        // Optionally log detailed click analytics
        $stmt = $pdo->prepare("
            INSERT INTO pizza_tracker_clicks 
            (tracker_id, source_page, ip_address, user_agent, created_at) 
            VALUES (?, 'promo_message', ?, ?, NOW())
        ");
        $stmt->execute([
            $tracker_id,
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Click tracked successfully'
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to track click']);
    }
    
} catch (Exception $e) {
    error_log("Promo click tracking error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
?> 