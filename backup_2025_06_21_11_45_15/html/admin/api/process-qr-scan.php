<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../core/config.php';
require_once __DIR__ . '/../../core/session.php';
require_once __DIR__ . '/../../core/auth.php';

// Check admin access
if (!is_logged_in() || !has_role('admin')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Admin access required']);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['qr_code'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'QR code required']);
    exit();
}

$qr_code = trim($input['qr_code']);
$scan_type = $input['scan_type'] ?? 'test';
$device_info = $input['device_info'] ?? [];

$start_time = microtime(true);
$response = [
    'success' => false,
    'qr_code' => $qr_code,
    'scan_type' => $scan_type,
    'timestamp' => date('c')
];

try {
    // Initialize scanner logs table if not exists
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS admin_scanner_logs (
            id INT PRIMARY KEY AUTO_INCREMENT,
            admin_user_id INT NOT NULL,
            qr_code_scanned VARCHAR(255),
            payload_data JSON,
            scan_type ENUM('live', 'test', 'invalid') DEFAULT 'test',
            device_info JSON,
            processing_time_ms INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (admin_user_id) REFERENCES users(id)
        )
    ");

    // Extract QR code from URL if it's a full URL
    if (strpos($qr_code, 'http') === 0) {
        $parsed_url = parse_url($qr_code);
        parse_str($parsed_url['query'] ?? '', $query_params);
        $qr_code = $query_params['code'] ?? $qr_code;
    }

    // Remove common QR prefixes
    $qr_code = preg_replace('/^(qr_|QR_)/', '', $qr_code);
    
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
        WHERE qr.code = ? OR qr.code = CONCAT('qr_', ?)
        LIMIT 1
    ");
    
    $stmt->execute([$qr_code, $qr_code]);
    $qr_info = $stmt->fetch();

    if (!$qr_info) {
        $response['error'] = 'QR code not found in database';
        $response['suggestions'] = [
            'Check if QR code is properly formatted',
            'Verify QR code exists in the system',
            'Try scanning again with better lighting'
        ];
        $scan_type = 'invalid';
    } else {
        $response['success'] = true;
        $response['qr_info'] = [
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
        ];

        // Simulate NAYAX processing based on QR type
        $response['nayax_simulation'] = simulateNayaxProcessing($qr_info);
        
        // Get additional analytics
        $response['analytics'] = getQRAnalytics($pdo, $qr_info['id']);
    }

} catch (Exception $e) {
    $response['error'] = 'Database error: ' . $e->getMessage();
    $scan_type = 'invalid';
    error_log("QR Scanner API Error: " . $e->getMessage());
}

// Calculate processing time
$processing_time = round((microtime(true) - $start_time) * 1000);
$response['processing_time_ms'] = $processing_time;

// Log the scan
try {
    $stmt = $pdo->prepare("
        INSERT INTO admin_scanner_logs 
        (admin_user_id, qr_code_scanned, payload_data, scan_type, device_info, processing_time_ms)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $_SESSION['user_id'],
        $qr_code,
        json_encode($response),
        $scan_type,
        json_encode($device_info),
        $processing_time
    ]);
} catch (Exception $e) {
    error_log("Failed to log scanner activity: " . $e->getMessage());
}

echo json_encode($response, JSON_PRETTY_PRINT);

function simulateNayaxProcessing($qr_info) {
    $simulation = [
        'nayax_machine_id' => 'SIMULATOR_' . $qr_info['business_id'],
        'device_id' => 'DEV_' . str_pad($qr_info['id'], 6, '0', STR_PAD_LEFT),
        'transaction_id' => 'TXN_' . time() . '_' . rand(1000, 9999),
        'processing_status' => 'SUCCESS',
        'timestamp' => date('c')
    ];

    switch ($qr_info['qr_type']) {
        case 'dynamic_voting':
            $simulation['action'] = 'VOTING_INITIATED';
            $simulation['voting_session_id'] = 'VS_' . time();
            $simulation['available_items'] = 'Retrieved from machine inventory';
            break;
            
        case 'machine_sales':
            $simulation['action'] = 'PURCHASE_READY';
            $simulation['payment_methods'] = ['cash', 'card', 'qr_coins'];
            $simulation['inventory_check'] = 'PASSED';
            break;
            
        case 'spin_wheel':
            $simulation['action'] = 'SPIN_WHEEL_ACTIVATED';
            $simulation['spin_cost'] = '50 QR Coins';
            $simulation['user_balance_check'] = 'PENDING';
            break;
            
        case 'promotion':
            $simulation['action'] = 'PROMOTION_VALIDATED';
            $simulation['discount_applied'] = 'Variable based on campaign';
            $simulation['promo_code'] = 'PROMO_' . substr($qr_info['code'], -8);
            break;
            
        case 'dynamic_vending':
            $simulation['action'] = 'VENDING_SESSION_STARTED';
            $simulation['machine_status'] = 'ONLINE';
            $simulation['cooling_status'] = 'OPTIMAL';
            break;
            
        default:
            $simulation['action'] = 'UNKNOWN_QR_TYPE';
            $simulation['fallback_action'] = 'REDIRECT_TO_VOTING';
    }

    return $simulation;
}

function getQRAnalytics($pdo, $qr_id) {
    try {
        // Get scan statistics
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_scans,
                MAX(scan_time) as last_scan,
                COUNT(DISTINCT DATE(scan_time)) as active_days
            FROM qr_code_stats 
            WHERE qr_code_id = ?
        ");
        $stmt->execute([$qr_id]);
        $scan_stats = $stmt->fetch();

        // Get vote statistics
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total_votes, MAX(created_at) as last_vote
            FROM votes 
            WHERE qr_code_id = ?
        ");
        $stmt->execute([$qr_id]);
        $vote_stats = $stmt->fetch();

        return [
            'scans' => [
                'total' => (int)$scan_stats['total_scans'],
                'last_scan' => $scan_stats['last_scan'],
                'active_days' => (int)$scan_stats['active_days']
            ],
            'votes' => [
                'total' => (int)$vote_stats['total_votes'],
                'last_vote' => $vote_stats['last_vote']
            ],
            'performance' => [
                'scan_to_vote_ratio' => $scan_stats['total_scans'] > 0 ? 
                    round($vote_stats['total_votes'] / $scan_stats['total_scans'] * 100, 1) : 0,
                'status' => $scan_stats['total_scans'] > 10 ? 'ACTIVE' : 'LOW_USAGE'
            ]
        ];
        
    } catch (Exception $e) {
        return ['error' => 'Failed to get analytics'];
    }
}
?> 