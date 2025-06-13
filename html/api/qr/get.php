<?php
require_once __DIR__ . '/../../core/config.php';
require_once __DIR__ . '/../../core/session.php';
require_once __DIR__ . '/../../core/auth.php';

// Check authentication
if (!is_logged_in()) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Authentication required'
    ]);
    exit;
}

// Require business role
require_role('business');

// Get business ID
$business_id = get_business_id();

// Get QR code ID from query string
$qr_id = $_GET['id'] ?? null;

if (!$qr_id) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'QR code ID is required'
    ]);
    exit;
}

try {
    // Get QR code details
    $stmt = $pdo->prepare("
        SELECT qc.*, 
               COALESCE(c.business_id, vl.business_id, JSON_UNQUOTE(JSON_EXTRACT(qc.meta, '$.business_id'))) as owner_business_id,
               c.name as campaign_name,
               m.name as machine_name,
               pt.name as tracker_name,
               pl.name as pizza_list_name
        FROM qr_codes qc
        LEFT JOIN campaigns c ON qc.campaign_id = c.id
        LEFT JOIN voting_lists vl ON qc.machine_id = vl.id
        LEFT JOIN machines m ON qc.machine_id = m.id
        LEFT JOIN pizza_trackers pt ON qc.tracker_id = pt.id
        LEFT JOIN pizza_lists pl ON qc.pizza_list_id = pl.id
        WHERE qc.id = ? AND qc.status = 'active'
    ");
    $stmt->execute([$qr_id]);
    $qr_code = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$qr_code) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'QR code not found'
        ]);
        exit;
    }
    
    // Verify ownership
    if ($qr_code['owner_business_id'] != $business_id) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'You do not have permission to view this QR code'
        ]);
        exit;
    }
    
    // Get additional data based on QR type
    switch($qr_code['qr_type']) {
        case 'dynamic_voting':
            // Get available campaigns
            $stmt = $pdo->prepare("SELECT id, name FROM campaigns WHERE business_id = ? AND status = 'active' ORDER BY name");
            $stmt->execute([$business_id]);
            $qr_code['available_campaigns'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
            
        case 'dynamic_vending':
        case 'machine_sales':
        case 'promotion':
            // Get available machines
            $stmt = $pdo->prepare("SELECT id, name FROM machines WHERE business_id = ? AND status = 'active' ORDER BY name");
            $stmt->execute([$business_id]);
            $qr_code['available_machines'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
            
        case 'pizza_tracker':
            // Get available trackers
            $stmt = $pdo->prepare("SELECT id, name FROM pizza_trackers WHERE business_id = ? AND status = 'active' ORDER BY name");
            $stmt->execute([$business_id]);
            $qr_code['available_trackers'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get available pizza lists
            $stmt = $pdo->prepare("SELECT id, name FROM pizza_lists WHERE business_id = ? AND status = 'active' ORDER BY name");
            $stmt->execute([$business_id]);
            $qr_code['available_pizza_lists'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
    }
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'data' => $qr_code
    ]);
    
} catch (Exception $e) {
    error_log("Error fetching QR code: " . $e->getMessage());
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch QR code details'
    ]);
}
exit;
?> 