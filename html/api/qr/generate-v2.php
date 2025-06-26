<?php
/**
 * Unified General QR Code API v2
 * 
 * Consolidates general QR code generation functionality.
 * DOES NOT HANDLE business purchase or Nayax QRs (those remain separate).
 */

error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);

require_once __DIR__ . '/../../core/config.php';
require_once __DIR__ . '/../../core/session.php';
require_once __DIR__ . '/../../core/auth.php';
require_once __DIR__ . '/../../core/business_utils.php';
require_once __DIR__ . '/../../includes/QRGenerator.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentication required']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

try {
    $business_id = getOrCreateBusinessId($pdo, $_SESSION['user_id']);
    
    if (!$business_id) {
        throw new Exception('Unable to determine business ID');
    }
    
    // Get request data
    $content_type = $_SERVER['CONTENT_TYPE'] ?? '';
    
    if (strpos($content_type, 'application/json') !== false) {
        $data = json_decode(file_get_contents('php://input'), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON data');
        }
    } else {
        $data = $_POST;
    }
    
    if (empty($data) || empty($data['qr_type'])) {
        throw new Exception('QR code type is required');
    }
    
    // Allowed general QR types (NOT business purchase or Nayax)
    $allowed_types = ['static', 'dynamic', 'dynamic_voting', 'dynamic_vending', 'machine_sales', 'promotion', 'spin_wheel', 'pizza_tracker'];
    
    if (!in_array($data['qr_type'], $allowed_types)) {
        throw new Exception('Invalid QR code type for general API');
    }
    
    // Basic validation
    if (in_array($data['qr_type'], ['static', 'dynamic']) && empty($data['content']) && empty($data['url'])) {
        throw new Exception('URL/content is required');
    }
    
    // Generate QR code
    $qr_code = uniqid('qr_', true);
    $content = $data['content'] ?? $data['url'] ?? APP_URL . '/' . $data['qr_type'] . '?code=' . $qr_code;
    
    $options = [
        'type' => $data['qr_type'],
        'content' => $content,
        'size' => intval($data['size'] ?? 400),
        'foreground_color' => $data['foreground_color'] ?? '#000000',
        'background_color' => $data['background_color'] ?? '#FFFFFF',
        'error_correction_level' => $data['error_correction_level'] ?? 'H'
    ];
    
    // Handle preview
    if (!empty($data['preview']) || (!empty($data['action']) && $data['action'] === 'preview')) {
        $options['preview'] = true;
    }
    
    $generator = new QRGenerator();
    $result = $generator->generate($options);
    
    if (!$result['success']) {
        throw new Exception($result['message'] ?? 'QR generation failed');
    }
    
    // Save to database if not preview
    if (empty($options['preview'])) {
        $meta = [
            'content' => $content,
            'file_path' => $result['data']['qr_code_url'],
            'generated_by_api' => 'v2',
            'generated_at' => date('Y-m-d H:i:s')
        ];
        
        $stmt = $pdo->prepare("
            INSERT INTO qr_codes (
                business_id, qr_type, url, code, meta, status, created_at
            ) VALUES (
                ?, ?, ?, ?, ?, 'active', NOW()
            )
        ");
        
        $stmt->execute([
            $business_id,
            $data['qr_type'],
            $content,
            $qr_code,
            json_encode($meta)
        ]);
        
        $qr_id = $pdo->lastInsertId();
        
        echo json_encode([
            'success' => true,
            'data' => [
                'id' => $qr_id,
                'code' => $qr_code,
                'qr_url' => $result['data']['qr_code_url'],
                'content_url' => $content,
                'type' => $data['qr_type']
            ],
            'message' => 'QR code generated successfully'
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'preview_url' => $result['url'],
            'info' => [
                'type' => ucfirst(str_replace('_', ' ', $data['qr_type'])),
                'size' => $options['size']
            ]
        ]);
    }
    
} catch (Exception $e) {
    error_log("QR API v2 Error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>