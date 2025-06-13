<?php
/**
 * Unified QR Generation API
 * Replaces multiple QR generation endpoints with single, consistent interface
 */

// Suppress warnings to prevent output corruption
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);

require_once __DIR__ . '/../../core/config.php';
require_once __DIR__ . '/../../core/session.php';
require_once __DIR__ . '/../../core/auth.php';
require_once __DIR__ . '/../../core/unified_qr_manager.php';
require_once __DIR__ . '/../../core/services/VotingService.php';
require_once __DIR__ . '/../../core/services/PizzaTrackerService.php';

// Set JSON header
header('Content-Type: application/json');

// Check authentication
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Authentication required'
    ]);
    exit;
}

// Check business role
if (!has_role('business')) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => 'Business role required'
    ]);
    exit;
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Invalid request method'
    ]);
    exit;
}

try {
    // Get business ID
    $business_id = get_business_id();
    if (!$business_id) {
        throw new Exception('Business ID not found');
    }
    
    // Get request data (support both JSON and form data)
    $content_type = $_SERVER['CONTENT_TYPE'] ?? '';
    
    if (strpos($content_type, 'application/json') !== false) {
        $data = json_decode(file_get_contents('php://input'), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON data');
        }
    } else {
        $data = $_POST;
    }
    
    if (empty($data)) {
        throw new Exception('No data provided');
    }
    
    // Initialize unified QR manager
    $qr_manager = new UnifiedQRManager($pdo, $business_id);
    
    // Handle preview vs generation
    $action = $data['action'] ?? 'generate';
    
    if ($action === 'preview') {
        // Generate preview (temporary QR without saving to DB)
        $preview_data = array_merge($data, ['preview' => true]);
        $result = $qr_manager->generateQR($preview_data);
        
        if ($result['success']) {
            echo json_encode([
                'success' => true,
                'preview_url' => $result['data']['qr_code_url'],
                'info' => [
                    'type' => ucfirst(str_replace('_', ' ', $data['qr_type'])),
                    'size' => $data['size'] ?? '400',
                    'error_correction' => $data['error_correction_level'] ?? 'High',
                    'scan_distance' => $qr_manager->calculateScanDistance($data['size'] ?? 400)
                ]
            ]);
        } else {
            throw new Exception($result['error']);
        }
    } else {
        // Full QR code generation and save
        $result = $qr_manager->generateQR($data);
        
        if ($result['success']) {
            // Additional processing based on QR type
            switch ($data['qr_type']) {
                case 'dynamic_voting':
                    // Validate voting campaign
                    $campaign_result = VotingService::validateCampaign($data['campaign_id'], $business_id);
                    if (!$campaign_result['success']) {
                        throw new Exception('Invalid voting campaign: ' . $campaign_result['error']);
                    }
                    break;
                    
                case 'pizza_tracker':
                    // Validate pizza tracker
                    $tracker_result = PizzaTrackerService::getTracker($data['tracker_id']);
                    if (!$tracker_result['success']) {
                        throw new Exception('Invalid pizza tracker: ' . $tracker_result['error']);
                    }
                    break;
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'QR code generated successfully',
                'data' => $result['data']
            ]);
        } else {
            throw new Exception($result['error']);
        }
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * Calculate estimated scan distance based on QR size
 */
function calculateScanDistance($size) {
    $size = intval($size);
    if ($size <= 200) return '1-2 inches';
    if ($size <= 400) return '2-4 inches'; 
    if ($size <= 600) return '4-6 inches';
    return '6+ inches';
} 