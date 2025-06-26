<?php
/**
 * User Behavior Tracking API Endpoint
 * Receives and stores user behavior data from the JavaScript tracker
 */

require_once '../core/config.php';
require_once '../core/session.php';
require_once '../core/functions.php';

// Set JSON response headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Get JSON input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON data']);
    exit;
}

// Get user ID from session (if logged in)
$user_id = is_logged_in() ? get_user_id() : null;

// Get client IP and user agent
$ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

try {
    switch ($data['action']) {
        case 'page_visit':
            handlePageVisit($pdo, $data, $user_id, $ip_address, $user_agent);
            break;
            
        case 'page_exit':
            handlePageExit($pdo, $data, $user_id);
            break;
            
        case 'behavior_update':
            handleBehaviorUpdate($pdo, $data, $user_id);
            break;
            
        case 'interaction':
            handleInteraction($pdo, $data, $user_id);
            break;
            
        case 'task_start':
            handleTaskStart($pdo, $data, $user_id);
            break;
            
        case 'task_complete':
            handleTaskComplete($pdo, $data, $user_id);
            break;
            
        case 'performance':
            handlePerformance($pdo, $data, $user_id);
            break;
            
        case 'error':
            handleError($pdo, $data, $user_id);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Unknown action']);
            exit;
    }
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    error_log("Behavior tracking error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}

function handlePageVisit($pdo, $data, $user_id, $ip_address, $user_agent) {
    $stmt = $pdo->prepare("
        INSERT INTO user_page_visits 
        (user_id, session_id, page_url, page_title, time_spent, bounce, user_agent, ip_address, device_type, created_at) 
        VALUES (?, ?, ?, ?, 0, 0, ?, ?, ?, NOW())
    ");
    
    $stmt->execute([
        $user_id,
        $data['sessionId'] ?? null,
        $data['pageUrl'] ?? '',
        $data['pageTitle'] ?? '',
        $user_agent,
        $ip_address,
        $data['deviceType'] ?? 'unknown'
    ]);
    
    // Update session tracking
    updateSession($pdo, $data['sessionId'], $user_id, $ip_address, $user_agent, $data);
}

function handlePageExit($pdo, $data, $user_id) {
    // Update the page visit record with exit data
    $stmt = $pdo->prepare("
        UPDATE user_page_visits 
        SET time_spent = ?, bounce = ?
        WHERE user_id = ? AND session_id = ? AND page_url = ?
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    
    $stmt->execute([
        $data['timeSpent'] ?? 0,
        $data['bounce'] ?? 0,
        $user_id,
        $data['sessionId'] ?? '',
        $data['pageUrl'] ?? ''
    ]);
    
    // Update session with exit page
    $stmt = $pdo->prepare("
        UPDATE user_sessions 
        SET ended_at = NOW(), 
            session_duration = TIMESTAMPDIFF(SECOND, started_at, NOW()),
            pages_visited = pages_visited + 1,
            actions_performed = actions_performed + ?
        WHERE session_id = ?
    ");
    
    $stmt->execute([
        ($data['clickCount'] ?? 0) + ($data['keystrokes'] ?? 0),
        $data['sessionId'] ?? ''
    ]);
}

function handleBehaviorUpdate($pdo, $data, $user_id) {
    // Update ongoing page visit with current behavior data
    $stmt = $pdo->prepare("
        UPDATE user_page_visits 
        SET time_spent = ?
        WHERE user_id = ? AND session_id = ? AND page_url = ?
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    
    $stmt->execute([
        $data['timeSpent'] ?? 0,
        $user_id,
        $data['sessionId'] ?? '',
        $data['pageUrl'] ?? ''
    ]);
}

function handleInteraction($pdo, $data, $user_id) {
    // Log specific interactions for detailed analysis
    $interaction_data = json_encode($data['elementInfo'] ?? []);
    
    // For now, we'll just update the session actions count
    $stmt = $pdo->prepare("
        UPDATE user_sessions 
        SET actions_performed = actions_performed + 1
        WHERE session_id = ?
    ");
    
    $stmt->execute([$data['sessionId'] ?? '']);
}

function handleTaskStart($pdo, $data, $user_id) {
    $stmt = $pdo->prepare("
        INSERT INTO user_task_tracking 
        (user_id, session_id, task_type, task_id, started_at, completed, completion_time, created_at) 
        VALUES (?, ?, ?, ?, NOW(), 0, NULL, NOW())
    ");
    
    $stmt->execute([
        $user_id,
        $data['sessionId'] ?? null,
        $data['taskType'] ?? 'unknown',
        $data['taskId'] ?? null
    ]);
}

function handleTaskComplete($pdo, $data, $user_id) {
    $stmt = $pdo->prepare("
        UPDATE user_task_tracking 
        SET completed = 1, 
            completed_at = NOW(), 
            completion_time = ?
        WHERE user_id = ? AND task_id = ?
    ");
    
    $stmt->execute([
        $data['completionTime'] ?? 0,
        $user_id,
        $data['taskId'] ?? ''
    ]);
    
    // Track feature usage
    if (isset($data['taskType'])) {
        $business_id = null;
        if ($user_id) {
            $stmt = $pdo->prepare("SELECT business_id FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            $business_id = $user['business_id'] ?? null;
        }
        
        $feature_name = mapTaskToFeature($data['taskType']);
        $success_rate = 100.0; // Completed successfully
        
        $stmt = $pdo->prepare("
            INSERT INTO feature_usage_tracking 
            (user_id, business_id, feature_name, action_type, success_rate, usage_context, created_at) 
            VALUES (?, ?, ?, 'complete', ?, ?, NOW())
        ");
        
        $stmt->execute([
            $user_id,
            $business_id,
            $feature_name,
            $success_rate,
            $data['pageUrl'] ?? ''
        ]);
    }
}

function handlePerformance($pdo, $data, $user_id) {
    $performance_data = $data['performanceData'] ?? [];
    
    $stmt = $pdo->prepare("
        INSERT INTO page_performance 
        (page_url, load_time, user_id, session_id, created_at) 
        VALUES (?, ?, ?, ?, NOW())
    ");
    
    $stmt->execute([
        $data['pageUrl'] ?? '',
        $performance_data['loadTime'] ?? 0,
        $user_id,
        $data['sessionId'] ?? null
    ]);
}

function handleError($pdo, $data, $user_id) {
    $error_data = $data['errorData'] ?? [];
    
    $stmt = $pdo->prepare("
        INSERT INTO error_tracking 
        (user_id, session_id, error_type, error_message, error_file, error_line, page_url, user_action, severity, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'medium', NOW())
    ");
    
    $stmt->execute([
        $user_id,
        $data['sessionId'] ?? null,
        $error_data['type'] ?? 'unknown',
        $error_data['message'] ?? '',
        $error_data['filename'] ?? '',
        $error_data['lineno'] ?? 0,
        $data['pageUrl'] ?? '',
        $error_data['userAction'] ?? ''
    ]);
}

function updateSession($pdo, $session_id, $user_id, $ip_address, $user_agent, $data) {
    if (!$session_id) return;
    
    // Check if session exists
    $stmt = $pdo->prepare("SELECT id FROM user_sessions WHERE session_id = ?");
    $stmt->execute([$session_id]);
    
    if ($stmt->fetch()) {
        // Update existing session
        $stmt = $pdo->prepare("
            UPDATE user_sessions 
            SET pages_visited = pages_visited + 1
            WHERE session_id = ?
        ");
        $stmt->execute([$session_id]);
    } else {
        // Create new session
        $stmt = $pdo->prepare("
            INSERT INTO user_sessions 
            (user_id, session_id, started_at, pages_visited, ip_address, user_agent, device_type, created_at) 
            VALUES (?, ?, NOW(), 1, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $user_id,
            $session_id,
            $ip_address,
            $user_agent,
            $data['deviceType'] ?? 'unknown'
        ]);
    }
}

function mapTaskToFeature($task_type) {
    $mapping = [
        'create_campaign' => 'Campaign Management',
        'add_store_item' => 'Store Management',
        'generate_qr' => 'QR Code Generator',
        'setup_nayax' => 'Nayax Integration',
        'casino_play' => 'Casino System',
        'horse_racing' => 'Horse Racing'
    ];
    
    return $mapping[$task_type] ?? 'Unknown Feature';
}
?> 