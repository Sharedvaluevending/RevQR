<?php
/**
 * Nayax Webhook Handler
 * Receives real-time transaction notifications from Nayax and updates unified inventory
 */

require_once __DIR__ . '/../../core/config.php';
require_once __DIR__ . '/../../core/services/UnifiedSyncEngine.php';

// Set JSON response headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Only POST method allowed']);
    exit;
}

try {
    // Get raw webhook data
    $rawInput = file_get_contents('php://input');
    $webhookData = json_decode($rawInput, true);
    
    // Log incoming webhook for debugging
    error_log("Nayax Webhook Received: " . $rawInput);
    
    // Validate webhook data
    if (!$webhookData) {
        throw new Exception('Invalid JSON payload');
    }
    
    // Verify webhook signature (if implemented by Nayax)
    if (!verifyNayaxSignature($rawInput, $_SERVER['HTTP_X_NAYAX_SIGNATURE'] ?? '')) {
        throw new Exception('Invalid webhook signature');
    }
    
    // Initialize sync engine
    $syncEngine = new UnifiedSyncEngine($pdo);
    
    // Process the webhook
    $result = $syncEngine->processNayaxWebhook($webhookData);
    
    if ($result['success']) {
        // Log successful processing
        error_log("Nayax webhook processed successfully: " . $result['message']);
        
        // Return success response to Nayax
        http_response_code(200);
        echo json_encode([
            'status' => 'success',
            'message' => $result['message'],
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    } else {
        // Log error but return 200 to prevent Nayax retries for invalid data
        error_log("Nayax webhook processing failed: " . $result['error']);
        
        http_response_code(200);
        echo json_encode([
            'status' => 'error',
            'message' => $result['error'],
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
    
} catch (Exception $e) {
    // Log critical errors
    error_log("Nayax webhook handler critical error: " . $e->getMessage());
    
    // Return 500 for system errors to trigger Nayax retry
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Internal server error',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

/**
 * Verify webhook signature from Nayax
 * (Implementation depends on Nayax's security requirements)
 */
function verifyNayaxSignature($payload, $signature) {
    // If no signature provided, skip verification for now
    // In production, you'd implement proper signature verification
    if (empty($signature)) {
        return true; // Allow for testing
    }
    
    // Example implementation:
    // $expectedSignature = hash_hmac('sha256', $payload, NAYAX_WEBHOOK_SECRET);
    // return hash_equals($expectedSignature, $signature);
    
    return true;
}

/**
 * Store webhook for debugging/replay purposes
 */
function storeWebhookForDebug($rawPayload, $processed = false) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO nayax_webhook_log 
            (payload, processed, received_at) 
            VALUES (?, ?, NOW())
        ");
        $stmt->execute([$rawPayload, $processed ? 1 : 0]);
    } catch (Exception $e) {
        // Don't let logging errors break webhook processing
        error_log("Failed to store webhook log: " . $e->getMessage());
    }
}
?> 