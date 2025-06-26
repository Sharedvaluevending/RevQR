<?php
/**
 * Nayax Webhook Endpoint
 * Receives transaction notifications from Nayax payment system
 * 
 * @author RevenueQR Team
 * @version 1.0
 * @date 2025-01-17
 */

header('Content-Type: application/json; charset=utf-8');

// Rate limiting and security
$rate_limit_file = __DIR__ . '/../../logs/nayax_webhook_rate_limit.json';
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$current_time = time();

// Basic rate limiting (100 requests per minute per IP)
if (file_exists($rate_limit_file)) {
    $rate_data = json_decode(file_get_contents($rate_limit_file), true) ?: [];
    $rate_data = array_filter($rate_data, function($timestamp) use ($current_time) {
        return ($current_time - $timestamp) < 60; // Keep only last minute
    });
    
    if (isset($rate_data[$ip]) && count($rate_data[$ip]) >= 100) {
        http_response_code(429);
        echo json_encode(['error' => 'Rate limit exceeded']);
        exit;
    }
    
    $rate_data[$ip] = $rate_data[$ip] ?? [];
    $rate_data[$ip][] = $current_time;
} else {
    $rate_data = [$ip => [$current_time]];
}

file_put_contents($rate_limit_file, json_encode($rate_data));

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Log webhook activity
$log_file = __DIR__ . '/../../logs/nayax_webhook.log';
$log_entry = "[" . date('Y-m-d H:i:s') . "] Webhook received from IP: {$ip}\n";
file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);

try {
    // Include required files
    require_once __DIR__ . '/../core/config.php';
    require_once __DIR__ . '/../core/nayax_manager.php';
    
    // Check if Nayax integration is enabled
    $integration_enabled = ConfigManager::get('nayax_integration_enabled', false);
    if (!$integration_enabled) {
        http_response_code(503);
        echo json_encode(['error' => 'Nayax integration is currently disabled']);
        exit;
    }
    
    // Get request body
    $raw_payload = file_get_contents('php://input');
    if (empty($raw_payload)) {
        http_response_code(400);
        echo json_encode(['error' => 'Empty payload']);
        exit;
    }
    
    // Verify webhook signature if configured
    $signature_header = $_SERVER['HTTP_X_NAYAX_SIGNATURE'] ?? '';
    if ($signature_header) {
        $nayax_manager = new NayaxManager($pdo);
        if (!$nayax_manager->verifyWebhookSignature($raw_payload, $signature_header)) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid signature']);
            exit;
        }
    }
    
    // Decode JSON payload
    $payload = json_decode($raw_payload, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON payload']);
        exit;
    }
    
    // Log full payload for debugging
    $debug_log = "[" . date('Y-m-d H:i:s') . "] Payload: " . json_encode($payload) . "\n";
    file_put_contents($log_file, $debug_log, FILE_APPEND | LOCK_EX);
    
    // Validate required fields
    if (!isset($payload['TransactionId']) || !isset($payload['MachineId'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required fields: TransactionId or MachineId']);
        exit;
    }
    
    // Check for duplicate transaction
    $stmt = $pdo->prepare("SELECT id FROM nayax_transactions WHERE nayax_transaction_id = ?");
    $stmt->execute([$payload['TransactionId']]);
    if ($stmt->fetch()) {
        // Transaction already processed
        echo json_encode([
            'success' => true,
            'message' => 'Transaction already processed',
            'transaction_id' => $payload['TransactionId']
        ]);
        exit;
    }
    
    // Initialize Nayax Manager
    if (!isset($nayax_manager)) {
        $nayax_manager = new NayaxManager($pdo);
    }
    
    // Process the transaction
    $result = $nayax_manager->processTransaction($payload);
    
    if ($result['success']) {
        $success_log = "[" . date('Y-m-d H:i:s') . "] Transaction processed successfully: {$payload['TransactionId']}\n";
        file_put_contents($log_file, $success_log, FILE_APPEND | LOCK_EX);
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => $result['message'],
            'transaction_id' => $result['transaction_id'],
            'qr_coins_awarded' => $result['qr_coins_awarded']
        ]);
    } else {
        $error_log = "[" . date('Y-m-d H:i:s') . "] Transaction processing failed: {$payload['TransactionId']} - {$result['message']}\n";
        file_put_contents($log_file, $error_log, FILE_APPEND | LOCK_EX);
        
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $result['message']
        ]);
    }
    
} catch (Exception $e) {
    // Log the error
    $error_log = "[" . date('Y-m-d H:i:s') . "] Webhook error: " . $e->getMessage() . "\n";
    $error_log .= "Stack trace: " . $e->getTraceAsString() . "\n";
    file_put_contents($log_file, $error_log, FILE_APPEND | LOCK_EX);
    
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
?> 