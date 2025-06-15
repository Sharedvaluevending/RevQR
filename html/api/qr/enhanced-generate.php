<?php
// Suppress deprecation warnings to prevent output corruption
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);

require_once __DIR__ . '/../../core/config.php';
require_once __DIR__ . '/../../core/session.php';
require_once __DIR__ . '/../../core/auth.php';
require_once __DIR__ . '/../../core/functions.php';
require_once __DIR__ . '/../../core/business_utils.php';
require_once __DIR__ . '/../../includes/QRGenerator.php';

// Check authentication without redirecting
if (!is_logged_in()) {
    http_response_code(403);
    echo 'Authentication required';
    exit;
}

if (!has_role('business')) {
    http_response_code(403);
    echo 'Business role required';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Invalid request method';
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
    
    // CRITICAL FIX: Add machine validation for machine-related QR types
    $machine_requiring_types = ['dynamic_vending', 'machine_sales', 'promotion'];
    if (in_array($data['qr_type'], $machine_requiring_types)) {
        // Extract machine name from various possible fields
        $machine_name = !empty($data['machine_name_sales']) ? $data['machine_name_sales'] : 
                       (!empty($data['machine_name_promotion']) ? $data['machine_name_promotion'] : 
                       (!empty($data['machine_name']) ? $data['machine_name'] : ''));
        
        if (empty($machine_name)) {
            throw new Exception('Machine name is required for ' . $data['qr_type'] . ' QR codes');
        }
        
        // Validate machine exists and belongs to this business
        $stmt = $pdo->prepare("SELECT id FROM machines WHERE name = ? AND business_id = ?");
        $stmt->execute([$machine_name, $business_id]);
        $machine_id = $stmt->fetchColumn();
        
        if (!$machine_id) {
            throw new Exception('Invalid machine name: "' . $machine_name . '". Machine not found or does not belong to your business.');
        }
        
        // Store validated machine_id for later use
        $validated_machine_id = $machine_id;
        $validated_machine_name = $machine_name;
    }
    
    // Validate spin wheel for spin wheel QR codes
    if ($data['qr_type'] === 'spin_wheel') {
        if (empty($data['spin_wheel_id'])) {
            throw new Exception('Spin wheel is required for spin wheel QR codes');
        }
        
        // Validate spin wheel exists and belongs to this business
        $stmt = $pdo->prepare("SELECT id FROM spin_wheels WHERE id = ? AND business_id = ? AND is_active = 1");
        $stmt->execute([intval($data['spin_wheel_id']), $business_id]);
        $spin_wheel = $stmt->fetch();
        
        if (!$spin_wheel) {
            throw new Exception('Invalid spin wheel selected or spin wheel does not belong to your business.');
        }
        
        $validated_spin_wheel_id = $data['spin_wheel_id'];
    }
    
    // Validate pizza tracker for pizza tracker QR codes
    if ($data['qr_type'] === 'pizza_tracker') {
        if (empty($data['pizza_tracker_id'])) {
            throw new Exception('Pizza tracker is required for pizza tracker QR codes');
        }
        
        // Validate pizza tracker exists and belongs to this business
        $stmt = $pdo->prepare("
            SELECT pt.id, pt.name 
            FROM pizza_trackers pt 
            WHERE pt.id = ? AND pt.business_id = ? AND pt.is_active = 1
        ");
        $stmt->execute([intval($data['pizza_tracker_id']), $business_id]);
        $tracker = $stmt->fetch();
        
        if (!$tracker) {
            throw new Exception('Invalid pizza tracker selected or tracker does not belong to your business.');
        }
        
        $validated_pizza_tracker_id = $data['pizza_tracker_id'];
    }
    
    // Generate a unique code for this QR (used for both URL and DB)
    $qr_code = uniqid('qr_', true);
    
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
            $content = APP_URL . '/vote.php?code=' . $qr_code;
            break;
            
        case 'dynamic_vending':
            // Machine already validated above
            $content = APP_URL . '/public/promotions.php?machine=' . urlencode($validated_machine_name) . '&view=vending';
            break;
            
        case 'machine_sales':
            // Machine already validated above
            $content = APP_URL . '/public/promotions.php?machine=' . urlencode($validated_machine_name);
            break;
            
        case 'promotion':
            // Machine already validated above
            $content = APP_URL . '/public/promotions.php?machine=' . urlencode($validated_machine_name) . '&view=promotions';
            break;
            
        case 'spin_wheel':
            // Spin wheel already validated above
            $content = APP_URL . '/public/spin-wheel.php?wheel_id=' . intval($validated_spin_wheel_id);
            break;
            
        case 'pizza_tracker':
            // Pizza tracker already validated above
            $content = APP_URL . '/public/pizza-tracker.php?tracker_id=' . intval($validated_pizza_tracker_id) . '&source=qr';
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
        'output_format' => $data['output_format'] ?? 'png',
        'location' => $data['location'] ?? '',
        'machine_name' => $validated_machine_name ?? ($data['machine_name'] ?? ''),
        'campaign_id' => $data['campaign_id'] ?? null,
        'spin_wheel_id' => $validated_spin_wheel_id ?? null,
        'pizza_tracker_id' => $validated_pizza_tracker_id ?? null
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
    }
    
    // Add bottom text
    if (!empty($data['enable_bottom_text']) && !empty($data['bottom_text'])) {
        $options['enable_bottom_text'] = true;
        $options['bottom_text'] = $data['bottom_text'];
        $options['bottom_font'] = $data['bottom_font'] ?? 'Arial';
        $options['bottom_size'] = intval($data['bottom_size'] ?? 14);
        $options['bottom_color'] = $data['bottom_color'] ?? '#666666';
        $options['bottom_alignment'] = $data['bottom_alignment'] ?? 'center';
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
    
    // Get the file path from result
    $qr_code_url = $result['data']['qr_code_url'] ?? '';
    $file_path = __DIR__ . '/../../' . ltrim($qr_code_url, '/');
    
    // Save to database
    try {
        error_log("Enhanced QR: Attempting to save to database");
        error_log("Enhanced QR: Business ID: " . $business_id);
        error_log("Enhanced QR: QR Code URL: " . $qr_code_url);
        
        // CRITICAL FIX: Updated query to include business_id, machine_id, and url field
        $stmt = $pdo->prepare("
            INSERT INTO qr_codes (
                business_id, machine_id, campaign_id, qr_type, machine_name, machine_location, 
                url, code, meta, created_at, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'active')
        ");
        
        // Prepare metadata including all the enhanced options and content
        $metadata = [
            'business_id' => $business_id,
            'content' => $content,
            'file_path' => $qr_code_url,
            'location' => $data['location'] ?? '',
            'options' => $options,
            'validated_machine_id' => $validated_machine_id ?? null,
            'validated_machine_name' => $validated_machine_name ?? null,
            'spin_wheel_id' => $validated_spin_wheel_id ?? null
        ];
        
        error_log("Enhanced QR: Metadata: " . json_encode($metadata));
        
        $result = $stmt->execute([
            $business_id, // business_id (REQUIRED for multi-tenant isolation)
            $validated_machine_id ?? null, // machine_id (validated if machine-related QR type)
            $data['campaign_id'] ?? null, // campaign_id
            $data['qr_type'], // qr_type
            $validated_machine_name ?? ($data['machine_name'] ?? ''), // machine_name
            $data['location'] ?? '', // machine_location
            $content, // url (CRITICAL FIX: Store the actual URL)
            $qr_code, // code
            json_encode($metadata) // meta
        ]);
        
        if ($result) {
            error_log("Enhanced QR: Database insert successful");
        } else {
            error_log("Enhanced QR: Database insert failed");
        }
        
    } catch (Exception $dbError) {
        error_log("Enhanced QR: Database error: " . $dbError->getMessage());
        // Don't throw the error, just log it so the file download still works
    }
    
    // Get the file content and serve it
    if (!file_exists($file_path)) {
        throw new Exception('Generated file not found: ' . $file_path);
    }
    
    // Set appropriate headers for download
    $format = $options['output_format'];
    $filename = 'enhanced-qr-code-' . date('Y-m-d-H-i-s') . '.' . $format;
    
    if ($format === 'svg') {
        header('Content-Type: image/svg+xml');
    } else {
        header('Content-Type: image/png');
    }
    
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($file_path));
    
    // Output the file
    readfile($file_path);
    
} catch (Exception $e) {
    http_response_code(500);
    echo 'Error: ' . $e->getMessage();
} 