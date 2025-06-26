<?php
/**
 * QR Code Regenerate API
 * Regenerates QR code images for existing QR codes
 */

require_once __DIR__ . '/../../core/config.php';
require_once __DIR__ . '/../../core/session.php';
require_once __DIR__ . '/../../core/auth.php';
require_once __DIR__ . '/../../includes/QRGenerator.php';

// Set JSON response headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

try {
    // Get request data
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['qr_id'])) {
        throw new Exception('QR ID is required');
    }
    
    $qr_id = (int)$input['qr_id'];
    
    // Check authentication for API requests
    $business_id = null;
    if (isset($_SESSION['business_id'])) {
        $business_id = $_SESSION['business_id'];
    } else if (isset($_SESSION['user_id'])) {
        // Get business ID from user
        $stmt = $pdo->prepare("SELECT id FROM businesses WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $business = $stmt->fetch();
        $business_id = $business ? $business['id'] : 1;
    } else {
        // For testing purposes, default to business 1
        $business_id = 1;
    }
    
    // Get QR code details
    $stmt = $pdo->prepare("
        SELECT id, code, qr_type, url, machine_name, business_id,
               JSON_EXTRACT(meta, '$.content') as meta_content,
               JSON_EXTRACT(meta, '$.size') as meta_size,
               JSON_EXTRACT(meta, '$.foreground_color') as meta_fg,
               JSON_EXTRACT(meta, '$.background_color') as meta_bg,
               JSON_EXTRACT(meta, '$.error_correction_level') as meta_ecl
        FROM qr_codes 
        WHERE id = ? AND business_id = ? AND status = 'active'
    ");
    $stmt->execute([$qr_id, $business_id]);
    $qr_data = $stmt->fetch();
    
    if (!$qr_data) {
        throw new Exception('QR code not found or access denied');
    }
    
    // Prepare generation options
    $generation_options = [
        'type' => $qr_data['qr_type'],
        'content' => $qr_data['meta_content'] ? json_decode($qr_data['meta_content'], true) : $qr_data['url'],
        'size' => $qr_data['meta_size'] ? (int)json_decode($qr_data['meta_size']) : 400,
        'foreground_color' => $qr_data['meta_fg'] ? json_decode($qr_data['meta_fg'], true) : '#000000',
        'background_color' => $qr_data['meta_bg'] ? json_decode($qr_data['meta_bg'], true) : '#FFFFFF',
        'error_correction_level' => $qr_data['meta_ecl'] ? json_decode($qr_data['meta_ecl'], true) : 'H',
        'code' => $qr_data['code'],
        'preview' => false
    ];
    
    // Initialize QR generator
    $generator = new QRGenerator();
    
    // Generate new QR code
    $result = $generator->generate($generation_options);
    
    if (!$result['success']) {
        throw new Exception('QR generation failed: ' . ($result['error'] ?? 'Unknown error'));
    }
    
    // Update QR code record with new file path
    $new_file_path = $result['url'] ?? $result['data']['qr_code_url'] ?? null;
    
    if ($new_file_path) {
        // Update meta field with new file path
        $meta_update = [
            'file_path' => $new_file_path,
            'regenerated_at' => date('Y-m-d H:i:s'),
            'generation_method' => 'api_regenerate'
        ];
        
        $stmt = $pdo->prepare("
            UPDATE qr_codes 
            SET meta = JSON_MERGE_PATCH(COALESCE(meta, '{}'), ?),
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([json_encode($meta_update), $qr_id]);
    }
    
    // Log the regeneration
    $stmt = $pdo->prepare("
        INSERT INTO qr_code_regenerations (qr_code_id, business_id, regenerated_at, file_path, status)
        VALUES (?, ?, NOW(), ?, 'success')
        ON DUPLICATE KEY UPDATE 
        regenerated_at = NOW(), file_path = VALUES(file_path), status = VALUES(status)
    ");
    $stmt->execute([$qr_id, $business_id, $new_file_path]);
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'QR code regenerated successfully',
        'data' => [
            'qr_id' => $qr_id,
            'qr_code' => $qr_data['code'],
            'new_file_path' => $new_file_path,
            'regenerated_at' => date('Y-m-d H:i:s')
        ]
    ]);
    
} catch (Exception $e) {
    // Log error
    error_log("QR Regenerate API Error: " . $e->getMessage());
    
    // Log failed regeneration attempt
    if (isset($qr_id) && isset($business_id)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO qr_code_regenerations (qr_code_id, business_id, regenerated_at, error_message, status)
                VALUES (?, ?, NOW(), ?, 'failed')
                ON DUPLICATE KEY UPDATE 
                regenerated_at = NOW(), error_message = VALUES(error_message), status = VALUES(status)
            ");
            $stmt->execute([$qr_id, $business_id, $e->getMessage()]);
        } catch (Exception $log_error) {
            // Ignore logging errors
        }
    }
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?> 