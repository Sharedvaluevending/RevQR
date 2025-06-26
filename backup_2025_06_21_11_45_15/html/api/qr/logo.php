<?php
require_once __DIR__ . '/../../core/config.php';
require_once __DIR__ . '/../../core/auth.php';

// Check if user is authenticated
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized'
    ]);
    exit;
}

// Get business ID
$business_id = get_business_id();

// Logo directory
$logoDir = __DIR__ . '/../../assets/img/logos/';

// Ensure directory exists
if (!file_exists($logoDir)) {
    mkdir($logoDir, 0755, true);
}

// Handle different request methods
switch ($_SERVER['REQUEST_METHOD']) {
    case 'POST':
        // Handle logo upload
        if (!isset($_FILES['logo'])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'No logo file provided'
            ]);
            exit;
        }

        $file = $_FILES['logo'];
        
        // Validate file
        if ($file['error'] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'File upload error: ' . $file['error']
            ]);
            exit;
        }

        // Validate file type
        $allowedTypes = ['image/png', 'image/jpeg', 'image/jpg'];
        if (!in_array($file['type'], $allowedTypes)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid file type. Only PNG and JPEG images are allowed.'
            ]);
            exit;
        }

        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'logo_' . uniqid() . '.' . $extension;
        $filepath = $logoDir . $filename;

        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            // Set proper permissions
            chmod($filepath, 0644);
            
            echo json_encode([
                'success' => true,
                'filename' => $filename,
                'url' => '/assets/img/logos/' . $filename,
                'data' => [
                    'filename' => $filename,
                    'url' => '/assets/img/logos/' . $filename
                ]
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Failed to save logo file'
            ]);
        }
        break;

    case 'DELETE':
        // Handle logo deletion
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['filename'])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'No filename provided'
            ]);
            exit;
        }

        $filename = basename($data['filename']); // Sanitize filename
        $filepath = $logoDir . $filename;

        // Verify file exists and is within logo directory
        if (file_exists($filepath) && strpos(realpath($filepath), realpath($logoDir)) === 0) {
            if (unlink($filepath)) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Logo deleted successfully'
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to delete logo file'
                ]);
            }
        } else {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Logo file not found'
            ]);
        }
        break;

    case 'GET':
        // List available logos
        $logos = [];
        if (is_dir($logoDir)) {
            $files = glob($logoDir . '*.{png,jpg,jpeg}', GLOB_BRACE);
            foreach ($files as $file) {
                $logos[] = [
                    'filename' => basename($file),
                    'size' => filesize($file),
                    'modified' => filemtime($file)
                ];
            }
        }
        
        echo json_encode([
            'success' => true,
            'data' => [
                'logos' => $logos
            ]
        ]);
        break;

    default:
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => 'Method not allowed'
        ]);
        break;
} 