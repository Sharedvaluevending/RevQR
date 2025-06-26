<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';

// Allow CORS for admin tools
header('Access-Control-Allow-Origin: ' . APP_URL);
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['qr_code'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'QR code required']);
    exit();
}

$qr_code = trim($input['qr_code']);
$admin_scan = $input['admin_scan'] ?? false;

try {
    // Extract QR code from URL if it's a full URL
    if (strpos($qr_code, 'http') === 0) {
        $parsed_url = parse_url($qr_code);
        if (isset($parsed_url['query'])) {
            parse_str($parsed_url['query'], $query_params);
            $qr_code = $query_params['code'] ?? $qr_code;
        }
    }

    // Remove common QR prefixes for lookup
    $lookup_code = preg_replace('/^(qr_|QR_)/', '', $qr_code);
    
    // Look up QR code in database
    $stmt = $pdo->prepare("
        SELECT 
            qr.*,
            b.name as business_name,
            c.name as campaign_name,
            c.campaign_type
        FROM qr_codes qr
        LEFT JOIN businesses b ON qr.business_id = b.id
        LEFT JOIN campaigns c ON qr.campaign_id = c.id
        WHERE qr.code = ? OR qr.code = CONCAT('qr_', ?) OR qr.code = ?
        LIMIT 1
    ");
    
    $stmt->execute([$qr_code, $lookup_code, $lookup_code]);
    $qr_info = $stmt->fetch();

    if (!$qr_info) {
        echo json_encode([
            'success' => false,
            'error' => 'QR code not found',
            'qr_code' => $qr_code,
            'suggestions' => [
                'Check if QR code is properly formatted',
                'Verify QR code exists in the system'
            ]
        ]);
        exit();
    }

    // Record scan if not admin test
    if (!$admin_scan) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO qr_code_stats (qr_code_id, scan_time, ip_address, user_agent)
                VALUES (?, NOW(), ?, ?)
            ");
            $stmt->execute([
                $qr_info['id'],
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);
        } catch (Exception $e) {
            error_log("Failed to record QR scan: " . $e->getMessage());
        }
    }

    echo json_encode([
        'success' => true,
        'qr_info' => [
            'id' => $qr_info['id'],
            'code' => $qr_info['code'],
            'qr_type' => $qr_info['qr_type'],
            'business_id' => $qr_info['business_id'],
            'business_name' => $qr_info['business_name'],
            'machine_name' => $qr_info['machine_name'] ?: 'Unnamed Machine',
            'machine_location' => $qr_info['machine_location'],
            'status' => $qr_info['status'],
            'campaign_name' => $qr_info['campaign_name'],
            'campaign_type' => $qr_info['campaign_type'],
            'created_at' => $qr_info['created_at']
        ],
        'redirect_url' => determineRedirectUrl($qr_info),
        'admin_scan' => $admin_scan
    ]);

} catch (Exception $e) {
    error_log("QR Processing API Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Processing failed: ' . $e->getMessage()
    ]);
}

function determineRedirectUrl($qr_info) {
    $base_url = APP_URL;
    
    switch ($qr_info['qr_type']) {
        case 'dynamic_voting':
            return $base_url . '/vote.php?code=' . $qr_info['code'];
            
        case 'machine_sales':
            return $base_url . '/purchase.php?code=' . $qr_info['code'];
            
        case 'spin_wheel':
            return $base_url . '/spin-wheel.php?code=' . $qr_info['code'];
            
        case 'promotion':
            return $base_url . '/promotion.php?code=' . $qr_info['code'];
            
        case 'dynamic_vending':
            return $base_url . '/vending.php?code=' . $qr_info['code'];
            
        default:
            return $base_url . '/vote.php?code=' . $qr_info['code'];
    }
}
?> 