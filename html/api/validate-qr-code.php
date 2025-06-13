<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/qr_code_manager.php';

// Set JSON content type
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle OPTIONS request for CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    // Get request data
    $input = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $input = $_GET;
    } else {
        throw new Exception('Invalid request method');
    }
    
    if (!$input || !isset($input['qr_content'])) {
        throw new Exception('QR code content is required');
    }
    
    // Get additional validation parameters
    $scanner_ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    $nayax_machine_id = $input['nayax_machine_id'] ?? null;
    $action = $input['action'] ?? 'validate'; // validate, redeem
    
    // Validate the QR code
    $validation_result = QRCodeManager::validateQRCode(
        $input['qr_content'],
        $scanner_ip,
        $user_agent
    );
    
    if (!$validation_result['success']) {
        echo json_encode([
            'success' => false,
            'error' => $validation_result['message'],
            'scan_result' => $validation_result['scan_result']
        ]);
        exit();
    }
    
    // If this is a redemption request, mark as redeemed
    if ($action === 'redeem' && $validation_result['success']) {
        $purchase = $validation_result['purchase'];
        $redemption_success = QRCodeManager::markAsRedeemed(
            $purchase['id'],
            $nayax_machine_id
        );
        
        if (!$redemption_success) {
            echo json_encode([
                'success' => false,
                'error' => 'Failed to mark discount as redeemed. It may have already been used.',
                'scan_result' => 'already_used'
            ]);
            exit();
        }
    }
    
    // Return successful validation with discount details
    $response = [
        'success' => true,
        'scan_result' => 'success',
        'discount_info' => [
            'purchase_id' => $validation_result['purchase']['id'],
            'purchase_code' => $validation_result['purchase']['purchase_code'],
            'discount_percentage' => $validation_result['discount_percentage'],
            'business_name' => $validation_result['business_name'],
            'item_name' => $validation_result['purchase']['item_name'],
            'expires_at' => $validation_result['expires_at'],
            'user_id' => $validation_result['purchase']['user_id']
        ],
        'machine_instructions' => [
            'apply_discount' => true,
            'discount_type' => 'percentage',
            'discount_value' => $validation_result['discount_percentage'],
            'max_discount_amount' => null, // Can be set per business
            'selected_items' => $validation_result['selected_items']
        ],
        'nayax_integration' => [
            'transaction_reference' => 'QR-' . $validation_result['purchase']['purchase_code'],
            'machine_id' => $nayax_machine_id,
            'validation_timestamp' => date('c'),
            'security_verified' => true
        ]
    ];
    
    // Add redemption confirmation if this was a redemption
    if ($action === 'redeem') {
        $response['redeemed'] = true;
        $response['redemption_timestamp'] = date('c');
        $response['message'] = 'Discount successfully redeemed!';
    } else {
        $response['redeemed'] = false;
        $response['message'] = 'QR code validated successfully. Ready for redemption.';
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("QR validation API error: " . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'scan_result' => 'error'
    ]);
}
?> 