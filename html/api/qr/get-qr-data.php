<?php
require_once __DIR__ . '/../../core/config.php';
require_once __DIR__ . '/../../core/session.php';
require_once __DIR__ . '/../../core/auth.php';
require_once __DIR__ . '/../../core/business_utils.php';

header('Content-Type: application/json');

// Check if user is authenticated
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Require business role
if (!has_role('business')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Business access required']);
    exit;
}

try {
    // Get business_id
    $business_id = getOrCreateBusinessId($pdo, $_SESSION['user_id']);
    
    // Get QR code IDs from request
    $qr_ids = $_GET['ids'] ?? '';
    if (empty($qr_ids)) {
        throw new Exception('No QR code IDs provided');
    }
    
    $ids = explode(',', $qr_ids);
    $ids = array_map('intval', $ids);
    $ids = array_filter($ids);
    
    if (empty($ids)) {
        throw new Exception('Invalid QR code IDs');
    }
    
    // Fetch QR codes data
    $placeholders = str_repeat('?,', count($ids) - 1) . '?';
    $stmt = $pdo->prepare("
        SELECT 
            id,
            code,
            qr_type,
            machine_name,
            meta
        FROM qr_codes 
        WHERE id IN ($placeholders) AND business_id = ? AND status = 'active'
        ORDER BY created_at DESC
    ");
    
    $params = array_merge($ids, [$business_id]);
    $stmt->execute($params);
    $qr_codes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process QR codes to add image paths
    foreach ($qr_codes as &$qr) {
        $meta = $qr['meta'] ? json_decode($qr['meta'], true) : [];
        
        // Try to get image path from meta
        if (isset($meta['file_path'])) {
            $qr['image_path'] = $meta['file_path'];
        } else {
            // Fallback to standard path
            $qr['image_path'] = '/uploads/qr/' . $qr['code'] . '.png';
        }
        
        // Check if file exists
        $file_path = __DIR__ . '/../../' . ltrim($qr['image_path'], '/');
        $qr['image_exists'] = file_exists($file_path);
        
        // If file doesn't exist, try alternative paths
        if (!$qr['image_exists']) {
            $alternative_paths = [
                '/uploads/qr/1/' . $qr['code'] . '.png',
                '/uploads/qr/business/' . $qr['code'] . '.png',
                '/assets/img/qr/' . $qr['code'] . '.png'
            ];
            
            foreach ($alternative_paths as $alt_path) {
                $alt_file_path = __DIR__ . '/../../' . ltrim($alt_path, '/');
                if (file_exists($alt_file_path)) {
                    $qr['image_path'] = $alt_path;
                    $qr['image_exists'] = true;
                    break;
                }
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'data' => $qr_codes
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?> 