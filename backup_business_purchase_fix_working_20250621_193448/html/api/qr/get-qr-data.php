<?php
require_once '../../core/config.php';
require_once '../../core/session.php';
require_once '../../core/auth.php';
require_once '../../core/business_utils.php';

// Set JSON content type
header('Content-Type: application/json');

// Check if user is logged in and has business role
if (!isLoggedIn() || !hasRole('business')) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

try {
    $business_id = getOrCreateBusinessId($pdo, $_SESSION['user_id']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to get business ID']);
    exit;
}

// Get QR IDs from request
$ids = isset($_GET['ids']) ? $_GET['ids'] : '';
if (empty($ids)) {
    echo json_encode(['success' => false, 'error' => 'No QR code IDs provided']);
    exit;
}

$qr_ids = explode(',', $ids);
$qr_ids = array_map('intval', $qr_ids);
$qr_ids = array_filter($qr_ids); // Remove invalid IDs

if (empty($qr_ids)) {
    echo json_encode(['success' => false, 'error' => 'No valid QR code IDs provided']);
    exit;
}

try {
    // Create placeholders for prepared statement
    $placeholders = str_repeat('?,', count($qr_ids) - 1) . '?';
    
    $stmt = $pdo->prepare("
        SELECT 
            id,
            code,
            qr_type,
            machine_name,
            machine_location,
            url,
            meta,
            status,
            created_at
        FROM qr_codes 
        WHERE id IN ($placeholders) 
        AND business_id = ? 
        AND status = 'active'
        ORDER BY created_at DESC
    ");
    
    // Execute with QR IDs and business ID
    $params = array_merge($qr_ids, [$business_id]);
    $stmt->execute($params);
    $qr_codes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process QR codes to add image paths
    $processed_qr_codes = [];
    foreach ($qr_codes as $qr) {
        $qr_data = [
            'id' => $qr['id'],
            'code' => $qr['code'],
            'qr_type' => $qr['qr_type'],
            'machine_name' => $qr['machine_name'],
            'machine_location' => $qr['machine_location'],
            'url' => $qr['url'],
            'status' => $qr['status'],
            'created_at' => $qr['created_at']
        ];
        
        // Determine image path
        $meta = json_decode($qr['meta'] ?? '{}', true);
        $image_path = null;
        
        // Check multiple possible file locations
        $possible_paths = [
            $meta['file_path'] ?? null,
            '/uploads/qr/' . $qr['code'] . '.png',
            '/uploads/qr/1/' . $qr['code'] . '.png',
            '/uploads/qr/business/' . $qr['code'] . '.png',
            '/assets/img/qr/' . $qr['code'] . '.png',
            '/qr/' . $qr['code'] . '.png'
        ];
        
        foreach ($possible_paths as $path) {
            if ($path && file_exists(__DIR__ . '/../../' . $path)) {
                $image_path = $path;
                break;
            }
        }
        
        // Fallback to default path if no file found
        if (!$image_path) {
            $image_path = '/uploads/qr/' . $qr['code'] . '.png';
        }
        
        $qr_data['image_path'] = $image_path;
        $processed_qr_codes[] = $qr_data;
    }
    
    echo json_encode([
        'success' => true,
        'data' => $processed_qr_codes,
        'count' => count($processed_qr_codes)
    ]);
    
} catch (Exception $e) {
    error_log("Error in get-qr-data.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
?> 