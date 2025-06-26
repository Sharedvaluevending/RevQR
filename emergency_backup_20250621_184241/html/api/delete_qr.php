<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/csrf.php';

header('Content-Type: application/json');

// Check if user is authenticated
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized'
    ]);
    exit;
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
    exit;
}

// Get request data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($data['filename'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Filename is required'
    ]);
    exit;
}

$filename = basename($data['filename']); // Sanitize filename
$qr_directory = __DIR__ . '/../uploads/qr/';
$file_path = $qr_directory . $filename;

// Security checks
if (!$filename || $filename === '.' || $filename === '..') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid filename'
    ]);
    exit;
}

// Only allow PNG files
if (!preg_match('/^qr_[a-f0-9]+\.[0-9]+\.png$/', $filename) && 
    !preg_match('/^qr_[a-f0-9]+\.[0-9]+_preview\.png$/', $filename)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid QR code filename format'
    ]);
    exit;
}

// Check if file exists and is within the QR directory
if (!file_exists($file_path)) {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'message' => 'QR code file not found'
    ]);
    exit;
}

// Verify the file is actually within the QR directory (prevent path traversal)
$real_qr_dir = realpath($qr_directory);
$real_file_path = realpath($file_path);

if (!$real_file_path || strpos($real_file_path, $real_qr_dir) !== 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid file path'
    ]);
    exit;
}

try {
    // Delete the main file
    if (unlink($file_path)) {
        $deleted_files = [$filename];
        
        // Also try to delete the preview file if it exists
        $preview_filename = str_replace('.png', '_preview.png', $filename);
        $preview_path = $qr_directory . $preview_filename;
        
        if (file_exists($preview_path)) {
            if (unlink($preview_path)) {
                $deleted_files[] = $preview_filename;
            }
        }
        
        // Also try to delete any WebP versions
        $webp_filename = $filename . '.webp';
        $webp_path = $qr_directory . $webp_filename;
        
        if (file_exists($webp_path)) {
            if (unlink($webp_path)) {
                $deleted_files[] = $webp_filename;
            }
        }
        
        $preview_webp_filename = str_replace('.png', '_preview.png.webp', $filename);
        $preview_webp_path = $qr_directory . $preview_webp_filename;
        
        if (file_exists($preview_webp_path)) {
            if (unlink($preview_webp_path)) {
                $deleted_files[] = $preview_webp_filename;
            }
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'QR code deleted successfully',
            'deleted_files' => $deleted_files
        ]);
        
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to delete QR code file'
        ]);
    }
    
} catch (Exception $e) {
    error_log('QR Delete Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error deleting QR code: ' . $e->getMessage()
    ]);
}
?> 