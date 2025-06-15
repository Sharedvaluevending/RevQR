<?php
// Suppress deprecation warnings to prevent JSON corruption
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);

require_once __DIR__ . '/../../core/config.php';
require_once __DIR__ . '/../../core/session.php';
require_once __DIR__ . '/../../core/auth.php';
require_once __DIR__ . '/../../core/functions.php';
require_once __DIR__ . '/../../core/business_utils.php';
require_once __DIR__ . '/../../includes/QRGenerator.php';

// Suppress deprecation warnings to prevent JSON corruption
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);

// Set JSON header
header('Content-Type: application/json');

// Check authentication without redirecting
if (!is_logged_in()) {
    echo json_encode(['success' => false, 'error' => 'Authentication required']);
    exit;
}

if (!has_role('business')) {
    echo json_encode(['success' => false, 'error' => 'Business role required']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

try {
    $business_id = getOrCreateBusinessId($pdo, $_SESSION['user_id']);
    
    // Get form data - handle both JSON and FormData
    $data = [];
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    
    if (strpos($contentType, 'application/json') !== false) {
        // Handle JSON data (like regular QR generator)
        $input = json_decode(file_get_contents('php://input'), true);
        if ($input) {
            $data = $input;
        }
    } else {
        // Handle FormData/POST data (from enhanced form)
        $data = $_POST;
    }
    
    // Validate required fields
    if (empty($data['qr_type'])) {
        throw new Exception('QR code type is required');
    }
    
    // Build content based on QR type
    $content = '';
    switch ($data['qr_type']) {
        case 'static':
        case 'dynamic':
            $url = $data['content'] ?? $data['url'] ?? '';
            if (empty($url)) {
                throw new Exception('URL is required for this QR type');
            }
            $content = $url;
            break;
            
        case 'dynamic_voting':
            if (empty($data['campaign_id'])) {
                throw new Exception('Campaign is required for voting QR codes');
            }
            $content = APP_URL . '/public/vote.php?campaign=' . $data['campaign_id'];
            break;
            
        case 'dynamic_vending':
            $machine_name = $data['machine_name'] ?? '';
            if (empty($machine_name)) {
                throw new Exception('Machine name is required');
            }
            $content = APP_URL . '/public/vote.php?machine=' . urlencode($machine_name);
            break;
            
        case 'machine_sales':
            $machine_name = !empty($data['machine_name_sales']) ? $data['machine_name_sales'] : 
                           (!empty($data['machine_name_promotion']) ? $data['machine_name_promotion'] : 
                           (!empty($data['machine_name']) ? $data['machine_name'] : ''));
            if (empty($machine_name)) {
                throw new Exception('Machine name is required');
            }
            $content = APP_URL . '/public/promotions.php?machine=' . urlencode($machine_name);
            break;
            
        case 'promotion':
            $machine_name = !empty($data['machine_name_sales']) ? $data['machine_name_sales'] : 
                           (!empty($data['machine_name_promotion']) ? $data['machine_name_promotion'] : 
                           (!empty($data['machine_name']) ? $data['machine_name'] : ''));
            if (empty($machine_name)) {
                throw new Exception('Machine name is required');
            }
            $content = APP_URL . '/public/promotions.php?machine=' . urlencode($machine_name) . '&view=promotions';
            break;
            
        case 'spin_wheel':
            if (empty($data['spin_wheel_id'])) {
                throw new Exception('Spin wheel is required for spin wheel QR codes');
            }
            $content = APP_URL . '/public/spin-wheel.php?wheel_id=' . intval($data['spin_wheel_id']);
            break;
            
        case 'pizza_tracker':
            if (empty($data['pizza_tracker_id'])) {
                throw new Exception('Pizza tracker is required for pizza tracker QR codes');
            }
            $content = APP_URL . '/public/pizza-tracker.php?tracker_id=' . intval($data['pizza_tracker_id']) . '&source=qr';
            break;
            
        default:
            throw new Exception('Invalid QR code type');
    }
    
    // Build QR options
    $options = [
        'type' => $data['qr_type'],
        'content' => $content,
        'size' => intval($data['size'] ?? 400),
        'foreground_color' => $data['foreground_color'] ?? '#000000',
        'background_color' => $data['background_color'] ?? '#FFFFFF',
        'error_correction_level' => $data['error_correction_level'] ?? 'H',
        'preview' => true
    ];
    
    // Add enhanced background gradient
    if (!empty($data['enable_background_gradient'])) {
        $options['enable_background_gradient'] = true;
        $options['bg_gradient_type'] = $data['bg_gradient_type'] ?? 'linear';
        $options['bg_gradient_start'] = $data['bg_gradient_start'] ?? '#ff7e5f';
        $options['bg_gradient_middle'] = $data['bg_gradient_middle'] ?? '#feb47b';
        $options['bg_gradient_end'] = $data['bg_gradient_end'] ?? '#ff6b6b';
        $options['bg_gradient_angle'] = intval($data['bg_gradient_angle'] ?? 135);
        $options['bg_gradient_opacity'] = floatval($data['bg_gradient_opacity'] ?? 1);
        $options['bg_blend_mode'] = $data['bg_blend_mode'] ?? 'normal';
        $options['bg_gradient_animation'] = $data['bg_gradient_animation'] ?? 'none';
    }
    
    // Add enhanced borders
    if (!empty($data['enable_enhanced_border'])) {
        $options['enable_enhanced_border'] = true;
        $options['border_style'] = $data['border_style'] ?? 'solid';
        $options['border_width'] = intval($data['border_width'] ?? 2);
        $options['border_radius_style'] = $data['border_radius_style'] ?? 'md';
        $options['border_pattern'] = $data['border_pattern'] ?? 'uniform';
        $options['border_color_primary'] = $data['border_color_primary'] ?? '#0d6efd';
        $options['border_color_secondary'] = $data['border_color_secondary'] ?? '#6610f2';
        $options['border_color_accent'] = $data['border_color_accent'] ?? '#d63384';
        $options['border_glow_intensity'] = intval($data['border_glow_intensity'] ?? 0);
        $options['border_shadow_offset'] = intval($data['border_shadow_offset'] ?? 2);
        $options['border_animation_speed'] = $data['border_animation_speed'] ?? 'none';
    }
    
    // Add QR code gradients
    if (!empty($data['enable_qr_gradient'])) {
        $options['enable_qr_gradient'] = true;
        $options['qr_gradient_type'] = $data['qr_gradient_type'] ?? 'linear';
        $options['qr_gradient_start'] = $data['qr_gradient_start'] ?? '#000000';
        $options['qr_gradient_middle'] = $data['qr_gradient_middle'] ?? '#444444';
        $options['qr_gradient_end'] = $data['qr_gradient_end'] ?? '#333333';
        $options['qr_gradient_angle'] = intval($data['qr_gradient_angle'] ?? 45);
    }
    
    // Add custom eye finder patterns
    if (!empty($data['enable_custom_eyes'])) {
        $options['enable_custom_eyes'] = true;
        $options['eye_shape'] = $data['eye_shape'] ?? 'square';
        $options['eye_style'] = $data['eye_style'] ?? 'solid';

        $options['eye_outer_color'] = $data['eye_outer_color'] ?? '#000000';
        $options['eye_inner_color'] = $data['eye_inner_color'] ?? '#000000';
        $options['eye_center_color'] = $data['eye_center_color'] ?? '#000000';
        $options['eye_background_color'] = $data['eye_background_color'] ?? '#FFFFFF';
        $options['eye_gradient_type'] = $data['eye_gradient_type'] ?? 'none';
        $options['eye_gradient_angle'] = intval($data['eye_gradient_angle'] ?? 45);
        $options['eye_border_width'] = intval($data['eye_border_width'] ?? 0);
        $options['eye_glow_intensity'] = intval($data['eye_glow_intensity'] ?? 0);
        $options['eye_shadow_offset'] = intval($data['eye_shadow_offset'] ?? 0);
        $options['eye_rotation'] = intval($data['eye_rotation'] ?? 0);
    }
    
    // Add text labels
    if (!empty($data['enable_label']) && !empty($data['label_text'])) {
        $options['enable_label'] = true;
        $options['label_text'] = $data['label_text'];
        $options['label_font'] = $data['label_font'] ?? 'Arial';
        $options['label_size'] = intval($data['label_size'] ?? 16);
        $options['label_color'] = $data['label_color'] ?? '#000000';
        $options['label_alignment'] = $data['label_alignment'] ?? 'center';
        $options['label_bold'] = !empty($data['label_bold']);
        $options['label_underline'] = !empty($data['label_underline']);
        $options['label_shadow'] = !empty($data['label_shadow']);
        $options['label_outline'] = !empty($data['label_outline']);
        $options['label_shadow_color'] = $data['label_shadow_color'] ?? '#000000';
        $options['label_outline_color'] = $data['label_outline_color'] ?? '#000000';
    }
    
    // Add bottom text
    if (!empty($data['enable_bottom_text']) && !empty($data['bottom_text'])) {
        $options['enable_bottom_text'] = true;
        $options['bottom_text'] = $data['bottom_text'];
        $options['bottom_font'] = $data['bottom_font'] ?? 'Arial';
        $options['bottom_size'] = intval($data['bottom_size'] ?? 14);
        $options['bottom_color'] = $data['bottom_color'] ?? '#666666';
        $options['bottom_alignment'] = $data['bottom_alignment'] ?? 'center';
        $options['bottom_bold'] = !empty($data['bottom_bold']);
        $options['bottom_underline'] = !empty($data['bottom_underline']);
        $options['bottom_shadow'] = !empty($data['bottom_shadow']);
        $options['bottom_outline'] = !empty($data['bottom_outline']);
        $options['bottom_shadow_color'] = $data['bottom_shadow_color'] ?? '#000000';
        $options['bottom_outline_color'] = $data['bottom_outline_color'] ?? '#000000';
    }
    
    // Add shadow effects
    if (!empty($data['enable_shadow'])) {
        $options['enable_shadow'] = true;
        $options['shadow_color'] = $data['shadow_color'] ?? '#000000';
        $options['shadow_blur'] = intval($data['shadow_blur'] ?? 5);
        $options['shadow_offset_x'] = intval($data['shadow_offset_x'] ?? 2);
        $options['shadow_offset_y'] = intval($data['shadow_offset_y'] ?? 2);
    }
    
    // Add module shape
    if (!empty($data['enable_module_shape'])) {
        $options['enable_module_shape'] = true;
        $options['module_shape'] = $data['module_shape'] ?? 'square';
    }
    
    // Generate QR code
    $generator = new QRGenerator();
    $result = $generator->generate($options);
    
    if (!$result['success']) {
        throw new Exception($result['error'] ?? 'Failed to generate QR code');
    }
    
    // Calculate scan distance estimate
    $size = $options['size'];
    $scan_distance = round($size / 10) . ' inches'; // Rough estimate
    
    // Get the preview URL from the result
    $preview_url = $result['url'] ?? $result['preview_url'] ?? $result['data']['qr_code_url'] ?? '';
    
    // Return success response
    echo json_encode([
        'success' => true,
        'preview_url' => $preview_url,
        'info' => [
            'type' => ucfirst(str_replace('_', ' ', $data['qr_type'])),
            'size' => $options['size'],
            'error_correction' => $options['error_correction_level'],
            'scan_distance' => $scan_distance
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} 