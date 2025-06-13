<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set error handler to catch all errors
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("PHP Error [$errno]: $errstr in $errfile on line $errline");
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => "PHP Error: $errstr",
        'file' => $errfile,
        'line' => $errline
    ]);
    exit;
});

// Set exception handler to catch uncaught exceptions
set_exception_handler(function($e) {
    error_log("Uncaught Exception: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    exit;
});

require_once __DIR__ . '/../../core/config.php';
require_once __DIR__ . '/../../core/auth.php';
require_once __DIR__ . '/../../includes/QRGenerator.php';

// Log function
function logError($message) {
    error_log("[QR Preview Error] " . $message);
}

// Check if user is authenticated
if (!is_logged_in()) {
    logError("User not authenticated");
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Authentication required. Please log in to use QR preview.',
        'redirect' => '/login.php'
    ]);
    exit;
}

// Get request data
$data = json_decode(file_get_contents('php://input'), true);

// Validate required fields
if (empty($data['content'])) {
    logError('Content is required');
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Content is required'
    ]);
    exit;
}

// Set default options
$options = [
    'type' => $data['qr_type'] ?? 'static',
    'content' => $data['content'],
    'size' => $data['size'] ?? 300,
    'foreground_color' => $data['foreground_color'] ?? '#000000',
    'background_color' => $data['background_color'] ?? '#FFFFFF',
    'error_correction_level' => $data['error_correction_level'] ?? 'H',
    'preview' => true
];

// Add logo if specified
if (!empty($data['logo'])) {
    $options['logo'] = $data['logo'];
}

// Add all advanced features
// Module customization
if (!empty($data['module_shape'])) {
    $options['module_shape'] = $data['module_shape'];
}
if (isset($data['module_size'])) {
    $options['module_size'] = $data['module_size'];
}
if (isset($data['module_spacing'])) {
    $options['module_spacing'] = $data['module_spacing'];
}
if (!empty($data['module_glow'])) {
    $options['module_glow'] = $data['module_glow'];
    if (!empty($data['module_glow_color'])) {
        $options['module_glow_color'] = $data['module_glow_color'];
    }
    if (isset($data['module_glow_intensity'])) {
        $options['module_glow_intensity'] = $data['module_glow_intensity'];
    }
}

// Gradient options
if (!empty($data['enable_gradient']) && !empty($data['gradient_type']) && $data['gradient_type'] !== 'none') {
    $options['enable_gradient'] = $data['enable_gradient'];
    $options['gradient_type'] = $data['gradient_type'];
    $options['gradient_start'] = $data['gradient_start'] ?? '#000000';
    $options['gradient_end'] = $data['gradient_end'] ?? '#0000FF';
    $options['gradient_angle'] = $data['gradient_angle'] ?? 45;
    $options['gradient_opacity'] = $data['gradient_opacity'] ?? 1.0;
}

// Eye customization
if (!empty($data['eye_style'])) {
    $options['eye'] = [
        'style' => $data['eye_style'],
        'color' => $data['eye_color'] ?? '#000000',
        'size' => $data['eye_size'] ?? 1
    ];

    if (!empty($data['eye_border'])) {
        $options['eye']['border'] = [
            'color' => $data['eye_border_color'] ?? '#000000',
            'width' => $data['eye_border_width'] ?? 1
        ];
    }

    if (!empty($data['eye_glow'])) {
        $options['eye']['glow'] = [
            'color' => $data['eye_glow_color'] ?? '#000000',
            'intensity' => $data['eye_glow_intensity'] ?? 5
        ];
    }
}

// Frame customization
if (!empty($data['frame_style']) && $data['frame_style'] !== 'none') {
    $options['frame'] = [
        'style' => $data['frame_style'],
        'color' => $data['frame_color'] ?? '#000000',
        'width' => $data['frame_width'] ?? 2,
        'radius' => $data['frame_radius'] ?? 5
    ];

    if (!empty($data['frame_glow'])) {
        $options['frame']['glow'] = [
            'color' => $data['frame_glow_color'] ?? '#000000',
            'intensity' => $data['frame_glow_intensity'] ?? 5
        ];
    }
}

// Text options - match QRGenerator format
if (!empty($data['enable_label']) && !empty($data['label_text'])) {
    $options['enable_label'] = $data['enable_label'];
    $options['label_text'] = $data['label_text'];
    $options['label_font'] = $data['label_font'] ?? 'Arial';
    $options['label_size'] = $data['label_size'] ?? 16;
    $options['label_color'] = $data['label_color'] ?? '#000000';
    $options['label_alignment'] = $data['label_alignment'] ?? 'center';
}

// Bottom text options - match QRGenerator format
if (!empty($data['enable_bottom_text']) && !empty($data['bottom_text'])) {
    $options['enable_bottom_text'] = $data['enable_bottom_text'];
    $options['bottom_text'] = $data['bottom_text'];
    $options['bottom_font'] = $data['bottom_font'] ?? 'Arial';
    $options['bottom_size'] = $data['bottom_size'] ?? 14;
    $options['bottom_color'] = $data['bottom_color'] ?? '#666666';
    $options['bottom_alignment'] = $data['bottom_alignment'] ?? 'center';
}

// Shadow effects - match QRGenerator format
if (!empty($data['enable_shadow'])) {
    $options['enable_shadow'] = $data['enable_shadow'];
    $options['shadow_color'] = $data['shadow_color'] ?? '#000000';
    $options['shadow_blur'] = $data['shadow_blur'] ?? 5;
    $options['shadow_offset_x'] = $data['shadow_offset_x'] ?? 2;
    $options['shadow_offset_y'] = $data['shadow_offset_y'] ?? 2;
}

// Module shape - match QRGenerator format
if (!empty($data['enable_module_shape'])) {
    $options['enable_module_shape'] = $data['enable_module_shape'];
    $options['module_shape'] = $data['module_shape'] ?? 'square';
    $options['module_size'] = $data['module_size'] ?? 1;
}

// Statistics options
if (!empty($data['enable_stats'])) {
    $options['stats'] = [
        'enabled' => true,
        'display' => $data['stats_display'] ?? 'none'
    ];
}

try {
    $generator = new QRGenerator();
    $result = $generator->generate($options);
    if (!$result['success']) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $result['message'] ?? 'QR code preview failed'
        ]);
        exit;
    }
    // Return preview data
    echo json_encode([
        'success' => true,
        'preview_url' => $result['preview_url'] ?? $result['url'],
        'url' => $result['url']
    ]);
} catch (Exception $e) {
    error_log("Error generating QR code: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 