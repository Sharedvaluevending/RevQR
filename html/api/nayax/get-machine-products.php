<?php
/**
 * Get Machine Products API Endpoint
 * Fetches products from Nayax machine products API
 * Based on: https://developerhub.nayax.com/reference/get-machine-products
 */

require_once __DIR__ . '/../../core/config.php';
require_once __DIR__ . '/../../core/auth.php';

header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'business') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$business_id = $_SESSION['business_id'];

// Get request data
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['machine_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Machine ID is required']);
    exit;
}

$machine_id = $input['machine_id'];

try {
    // Get Nayax credentials
    $stmt = $pdo->prepare("
        SELECT AES_DECRYPT(access_token, 'nayax_secure_key_2025') as access_token, api_url
        FROM business_nayax_credentials 
        WHERE business_id = ? AND is_active = 1
    ");
    $stmt->execute([$business_id]);
    $credentials = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$credentials) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Nayax credentials not found']);
        exit;
    }
    
    // Verify machine belongs to this business
    $stmt = $pdo->prepare("
        SELECT nayax_machine_id FROM nayax_machines 
        WHERE nayax_machine_id = ? AND business_id = ? AND status = 'active'
    ");
    $stmt->execute([$machine_id, $business_id]);
    $machine = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$machine) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Machine not found or inactive']);
        exit;
    }
    
    // First try to get cached products
    $stmt = $pdo->prepare("
        SELECT product_selection, product_name, product_price, quantity, last_updated
        FROM nayax_machine_inventory 
        WHERE machine_id = ? AND business_id = ?
        AND last_updated > DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ORDER BY product_selection
    ");
    $stmt->execute([$machine_id, $business_id]);
    $cached_products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // If we have recent cached data, use it
    if (!empty($cached_products)) {
        $products = [];
        foreach ($cached_products as $product) {
            $products[] = [
                'selection' => $product['product_selection'],
                'name' => $product['product_name'],
                'price' => floatval($product['product_price']),
                'quantity' => intval($product['quantity']),
                'cached' => true,
                'last_updated' => $product['last_updated']
            ];
        }
        
        echo json_encode([
            'success' => true,
            'products' => $products,
            'source' => 'cache'
        ]);
        exit;
    }
    
    // Fetch fresh data from Nayax API
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $credentials['api_url'] . '/machines/' . $machine_id . '/products');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $credentials['access_token'],
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if ($curl_error) {
        error_log("Nayax API cURL error for machine $machine_id: $curl_error");
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Network error connecting to Nayax API']);
        exit;
    }
    
    if ($http_code !== 200) {
        error_log("Nayax API HTTP error for machine $machine_id: HTTP $http_code - $response");
        http_response_code(502);
        echo json_encode([
            'success' => false, 
            'error' => 'Nayax API returned error',
            'http_code' => $http_code,
            'debug' => substr($response, 0, 200) // First 200 chars for debugging
        ]);
        exit;
    }
    
    $api_data = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("Nayax API JSON decode error for machine $machine_id: " . json_last_error_msg());
        http_response_code(502);
        echo json_encode(['success' => false, 'error' => 'Invalid JSON response from Nayax API']);
        exit;
    }
    
    // Handle different possible response formats
    $products_data = [];
    if (isset($api_data['products'])) {
        $products_data = $api_data['products'];
    } elseif (isset($api_data['data'])) {
        $products_data = $api_data['data'];
    } elseif (is_array($api_data)) {
        $products_data = $api_data;
    }
    
    if (empty($products_data)) {
        echo json_encode([
            'success' => true,
            'products' => [],
            'source' => 'api',
            'message' => 'No products found in machine'
        ]);
        exit;
    }
    
    $products = [];
    
    // Process and cache products
    foreach ($products_data as $product) {
        $selection = $product['selection'] ?? $product['slot'] ?? '';
        $name = $product['name'] ?? $product['productName'] ?? 'Unknown Product';
        $price = floatval($product['price'] ?? $product['unitPrice'] ?? 0);
        $quantity = intval($product['quantity'] ?? $product['stock'] ?? 0);
        
        if (empty($selection)) {
            continue; // Skip products without selection code
        }
        
        $products[] = [
            'selection' => $selection,
            'name' => $name,
            'price' => $price,
            'quantity' => $quantity,
            'cached' => false
        ];
        
        // Cache the product data
        $stmt = $pdo->prepare("
            INSERT INTO nayax_machine_inventory (
                business_id, machine_id, product_selection, product_name, 
                product_price, quantity, last_updated
            ) VALUES (?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
            product_name = VALUES(product_name),
            product_price = VALUES(product_price),
            quantity = VALUES(quantity),
            last_updated = NOW()
        ");
        
        $stmt->execute([
            $business_id,
            $machine_id,
            $selection,
            $name,
            $price,
            $quantity
        ]);
    }
    
    echo json_encode([
        'success' => true,
        'products' => $products,
        'source' => 'api',
        'count' => count($products)
    ]);
    
} catch (Exception $e) {
    error_log("Error in get-machine-products.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error',
        'message' => $e->getMessage()
    ]);
} 