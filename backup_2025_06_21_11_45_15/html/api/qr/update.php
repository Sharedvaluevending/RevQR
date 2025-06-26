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

// Check if this is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
    exit;
}

// Get business ID
$business_id = get_business_id();

// Get request data
$input = json_decode(file_get_contents('php://input'), true);
$qr_id = $input['qr_id'] ?? null;
$content = $input['content'] ?? null;

if (!$qr_id || !$content) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'QR code ID and content are required'
    ]);
    exit;
}

try {
    // First, get the QR code details to verify ownership
    $stmt = $pdo->prepare("
        SELECT qc.*, 
               COALESCE(c.business_id, vl.business_id, JSON_UNQUOTE(JSON_EXTRACT(qc.meta, '$.business_id'))) as owner_business_id
        FROM qr_codes qc
        LEFT JOIN campaigns c ON qc.campaign_id = c.id
        LEFT JOIN voting_lists vl ON qc.machine_id = vl.id
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
            'message' => 'You do not have permission to edit this QR code'
        ]);
        exit;
    }
    
    // Start transaction
    $pdo->beginTransaction();
    
    // Update QR code content based on type
    switch($qr_code['qr_type']) {
        case 'static':
        case 'dynamic':
            if (!isset($content['url'])) {
                throw new Exception('URL is required for static/dynamic QR codes');
            }
            $stmt = $pdo->prepare("UPDATE qr_codes SET url = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$content['url'], $qr_id]);
            break;
            
        case 'dynamic_voting':
            if (!isset($content['campaign_id'])) {
                throw new Exception('Campaign ID is required for voting QR codes');
            }
            // Verify campaign ownership
            $stmt = $pdo->prepare("SELECT business_id FROM campaigns WHERE id = ?");
            $stmt->execute([$content['campaign_id']]);
            $campaign = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$campaign || $campaign['business_id'] != $business_id) {
                throw new Exception('Invalid campaign selected');
            }
            $stmt = $pdo->prepare("UPDATE qr_codes SET campaign_id = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$content['campaign_id'], $qr_id]);
            break;
            
        case 'dynamic_vending':
        case 'machine_sales':
        case 'promotion':
            if (!isset($content['machine_id'])) {
                throw new Exception('Machine ID is required for vending/sales/promotion QR codes');
            }
            // Verify machine ownership
            $stmt = $pdo->prepare("SELECT business_id FROM machines WHERE id = ?");
            $stmt->execute([$content['machine_id']]);
            $machine = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$machine || $machine['business_id'] != $business_id) {
                throw new Exception('Invalid machine selected');
            }
            $stmt = $pdo->prepare("UPDATE qr_codes SET machine_id = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$content['machine_id'], $qr_id]);
            break;
            
        case 'pizza_tracker':
            if (!isset($content['tracker_id'])) {
                throw new Exception('Tracker ID is required for pizza tracker QR codes');
            }
            // Verify tracker ownership
            $stmt = $pdo->prepare("SELECT business_id FROM pizza_trackers WHERE id = ?");
            $stmt->execute([$content['tracker_id']]);
            $tracker = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$tracker || $tracker['business_id'] != $business_id) {
                throw new Exception('Invalid tracker selected');
            }
            $stmt = $pdo->prepare("UPDATE qr_codes SET tracker_id = ?, pizza_list_id = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$content['tracker_id'], $content['pizza_list_id'] ?? null, $qr_id]);
            break;
            
        default:
            throw new Exception('Unsupported QR code type');
    }
    
    // Get updated QR code data
    $stmt = $pdo->prepare("
        SELECT qc.*, 
               c.name as campaign_name,
               m.name as machine_name,
               pt.name as tracker_name,
               pl.name as pizza_list_name
        FROM qr_codes qc
        LEFT JOIN campaigns c ON qc.campaign_id = c.id
        LEFT JOIN machines m ON qc.machine_id = m.id
        LEFT JOIN pizza_trackers pt ON qc.tracker_id = pt.id
        LEFT JOIN pizza_lists pl ON qc.pizza_list_id = pl.id
        WHERE qc.id = ?
    ");
    $stmt->execute([$qr_id]);
    $updated_qr = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Commit transaction
    $pdo->commit();
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'QR code updated successfully',
        'data' => $updated_qr
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollback();
    }
    
    error_log("Error updating QR code: " . $e->getMessage());
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
exit;
?> 