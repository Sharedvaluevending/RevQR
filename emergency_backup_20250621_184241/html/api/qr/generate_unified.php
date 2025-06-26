<?php
/**
 * Unified QR Generation API
 * Single endpoint to replace fragmented QR generation system
 * 
 * This endpoint consolidates:
 * - generate.php
 * - enhanced-generate.php  
 * - unified-generate.php
 * 
 * API: POST /api/qr/generate_unified.php
 * Content-Type: application/json
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed. Use POST.',
        'allowed_methods' => ['POST']
    ]);
    exit;
}

// Include required files
require_once '../../config/database.php';
require_once '../../config/auth.php';
require_once '../../core/services/QRService.php';

try {
    // Get input data
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON input: ' . json_last_error_msg());
    }
    
    // Validate authentication
    $auth_result = validateAuth();
    if (!$auth_result['valid']) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'Authentication required',
            'auth_error' => $auth_result['error']
        ]);
        exit;
    }
    
    $business_id = $auth_result['business_id'];
    
    // Initialize QR service
    QRService::init($pdo);
    
    // Check if this is a preview request
    if (isset($data['preview']) && $data['preview'] === true) {
        $result = QRService::generatePreview($data);
    } else {
        // Full QR generation
        $result = QRService::generateQR($data, $business_id);
    }
    
    // Set appropriate response code
    if ($result['success']) {
        http_response_code(200);
    } else {
        // Determine error response code based on error type
        $error_code = $result['error_code'] ?? 'GENERAL_ERROR';
        switch ($error_code) {
            case 'INVALID_TYPE':
            case 'MISSING_FIELD':
            case 'INVALID_URL':
                http_response_code(400); // Bad Request
                break;
            case 'NOT_FOUND':
                http_response_code(404); // Not Found
                break;
            case 'ACCESS_DENIED':
                http_response_code(403); // Forbidden
                break;
            default:
                http_response_code(500); // Internal Server Error
                break;
        }
    }
    
    // Add metadata to response
    $result['meta'] = [
        'api_version' => '2.0',
        'endpoint' => 'unified_generation',
        'timestamp' => date('c'),
        'request_id' => uniqid('qr_', true)
    ];
    
    echo json_encode($result, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    error_log("Unified QR Generation API error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error',
        'error_code' => 'INTERNAL_ERROR',
        'meta' => [
            'api_version' => '2.0',
            'endpoint' => 'unified_generation',
            'timestamp' => date('c'),
            'request_id' => uniqid('err_', true)
        ]
    ], JSON_PRETTY_PRINT);
}

/**
 * Validate authentication and return business context
 */
function validateAuth() {
    global $pdo;
    
    // Check for API key in header
    $api_key = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['HTTP_X_API_KEY'] ?? null;
    
    if ($api_key) {
        // Remove "Bearer " prefix if present
        $api_key = preg_replace('/^Bearer\s+/', '', $api_key);
        
        // Validate API key
        $stmt = $pdo->prepare("
            SELECT b.id, b.name, b.status 
            FROM businesses b 
            JOIN business_api_keys bak ON b.id = bak.business_id 
            WHERE bak.api_key = ? AND bak.is_active = 1 AND b.status = 'active'
        ");
        $stmt->execute([$api_key]);
        $business = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($business) {
            return [
                'valid' => true,
                'business_id' => $business['id'],
                'business_name' => $business['name'],
                'auth_method' => 'api_key'
            ];
        }
    }
    
    // Check for session authentication
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (isset($_SESSION['business_id']) && isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
        return [
            'valid' => true,
            'business_id' => $_SESSION['business_id'],
            'business_name' => $_SESSION['business_name'] ?? 'Unknown',
            'auth_method' => 'session'
        ];
    }
    
    return [
        'valid' => false,
        'error' => 'No valid authentication found'
    ];
}
?> 