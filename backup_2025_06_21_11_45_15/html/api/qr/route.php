<?php
/**
 * QR API Router - Compatibility Layer
 * Routes requests to appropriate QR generation endpoints
 */

header("Content-Type: application/json");

// Get the requested endpoint from the path
$request_uri = $_SERVER["REQUEST_URI"];
$path = parse_url($request_uri, PHP_URL_PATH);

// Route to appropriate handler
switch ($path) {
    case "/api/qr/generate.php":
        require_once __DIR__ . "/generate.php";
        break;
    
    case "/api/qr/enhanced-generate.php":
        require_once __DIR__ . "/enhanced-generate.php";
        break;
    
    case "/api/qr/unified-generate.php":
        require_once __DIR__ . "/unified-generate.php";
        break;
    
    case "/api/qr/preview.php":
        require_once __DIR__ . "/preview.php";
        break;
    
    case "/api/qr/enhanced-preview.php":
        require_once __DIR__ . "/enhanced-preview.php";
        break;
    
    default:
        http_response_code(404);
        echo json_encode([
            "success" => false,
            "error" => "QR API endpoint not found: $path"
        ]);
        break;
}
?>