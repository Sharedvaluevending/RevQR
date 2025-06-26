<?php
/**
 * Nayax Machine Inventory Sync API
 * Fetches and caches machine inventory data from Nayax API
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once __DIR__ . '/../../core/config.php';
require_once __DIR__ . '/../../core/auth.php';

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    // Authentication check
    if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'business') {
        throw new Exception('Authentication required', 401);
    }
    
    $business_id = $_SESSION['business_id'];
    $action = $_GET['action'] ?? $_POST['action'] ?? 'sync_all';
    
    // Get Nayax credentials
    $stmt = $pdo->prepare("
        SELECT AES_DECRYPT(access_token, 'nayax_secure_key_2025') as access_token, 
               api_url, is_active
        FROM business_nayax_credentials 
        WHERE business_id = ?
    ");
    $stmt->execute([$business_id]);
    $credentials = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$credentials || !$credentials['is_active']) {
        throw new Exception('Nayax integration not configured or inactive', 400);
    }
    
    if (!$credentials['access_token']) {
        throw new Exception('Invalid Nayax credentials', 400);
    }
    
    switch ($action) {
        case 'sync_all':
            $result = syncAllMachineInventory($business_id, $credentials);
            break;
            
        case 'sync_machine':
            $machine_id = $_GET['machine_id'] ?? $_POST['machine_id'] ?? null;
            if (!$machine_id) {
                throw new Exception('Machine ID is required', 400);
            }
            $result = syncSingleMachineInventory($business_id, $machine_id, $credentials);
            break;
            
        case 'get_cached':
            $machine_id = $_GET['machine_id'] ?? null;
            $result = getCachedInventory($business_id, $machine_id);
            break;
            
        case 'machines_status':
            $result = getMachinesStatus($business_id);
            break;
            
        default:
            throw new Exception('Invalid action', 400);
    }
    
    echo json_encode([
        'success' => true,
        'data' => $result,
        'timestamp' => time(),
        'action' => $action
    ]);
    
} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => time()
    ]);
}

/**
 * Sync inventory for all business machines
 */
function syncAllMachineInventory($business_id, $credentials) {
    global $pdo;
    
    // Get all active machines
    $stmt = $pdo->prepare("
        SELECT nayax_machine_id, machine_name 
        FROM nayax_machines 
        WHERE business_id = ? AND status = 'active'
    ");
    $stmt->execute([$business_id]);
    $machines = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $results = [];
    $success_count = 0;
    $error_count = 0;
    
    foreach ($machines as $machine) {
        try {
            $inventory = fetchMachineInventoryFromAPI($machine['nayax_machine_id'], $credentials);
            
            if ($inventory !== null) {
                // Cache the inventory
                $stmt = $pdo->prepare("
                    INSERT INTO nayax_machine_inventory (machine_id, business_id, inventory_data, product_count, last_updated)
                    VALUES (?, ?, ?, ?, NOW())
                    ON DUPLICATE KEY UPDATE 
                    inventory_data = VALUES(inventory_data),
                    product_count = VALUES(product_count),
                    last_updated = NOW()
                ");
                $stmt->execute([
                    $machine['nayax_machine_id'],
                    $business_id,
                    json_encode($inventory),
                    count($inventory)
                ]);
                
                // Update machine sync timestamp
                $stmt = $pdo->prepare("
                    UPDATE nayax_machines 
                    SET last_sync_at = NOW() 
                    WHERE nayax_machine_id = ? AND business_id = ?
                ");
                $stmt->execute([$machine['nayax_machine_id'], $business_id]);
                
                $results[$machine['nayax_machine_id']] = [
                    'success' => true,
                    'product_count' => count($inventory),
                    'machine_name' => $machine['machine_name']
                ];
                $success_count++;
            } else {
                $results[$machine['nayax_machine_id']] = [
                    'success' => false,
                    'error' => 'No inventory data received',
                    'machine_name' => $machine['machine_name']
                ];
                $error_count++;
            }
            
        } catch (Exception $e) {
            $results[$machine['nayax_machine_id']] = [
                'success' => false,
                'error' => $e->getMessage(),
                'machine_name' => $machine['machine_name']
            ];
            $error_count++;
        }
        
        // Small delay between requests to avoid rate limiting
        usleep(500000); // 0.5 seconds
    }
    
    return [
        'total_machines' => count($machines),
        'success_count' => $success_count,
        'error_count' => $error_count,
        'results' => $results
    ];
}

/**
 * Sync inventory for a single machine
 */
function syncSingleMachineInventory($business_id, $machine_id, $credentials) {
    global $pdo;
    
    // Verify machine belongs to business
    $stmt = $pdo->prepare("
        SELECT machine_name 
        FROM nayax_machines 
        WHERE nayax_machine_id = ? AND business_id = ? AND status = 'active'
    ");
    $stmt->execute([$machine_id, $business_id]);
    $machine = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$machine) {
        throw new Exception('Machine not found or inactive', 404);
    }
    
    $inventory = fetchMachineInventoryFromAPI($machine_id, $credentials);
    
    if ($inventory === null) {
        throw new Exception('Failed to fetch inventory from Nayax API', 500);
    }
    
    // Cache the inventory
    $stmt = $pdo->prepare("
        INSERT INTO nayax_machine_inventory (machine_id, business_id, inventory_data, product_count, last_updated)
        VALUES (?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE 
        inventory_data = VALUES(inventory_data),
        product_count = VALUES(product_count),
        last_updated = NOW()
    ");
    $stmt->execute([$machine_id, $business_id, json_encode($inventory), count($inventory)]);
    
    // Update machine sync timestamp
    $stmt = $pdo->prepare("
        UPDATE nayax_machines 
        SET last_sync_at = NOW() 
        WHERE nayax_machine_id = ? AND business_id = ?
    ");
    $stmt->execute([$machine_id, $business_id]);
    
    return [
        'machine_id' => $machine_id,
        'machine_name' => $machine['machine_name'],
        'product_count' => count($inventory),
        'inventory' => $inventory,
        'sync_timestamp' => time()
    ];
}

/**
 * Get cached inventory data
 */
function getCachedInventory($business_id, $machine_id = null) {
    global $pdo;
    
    if ($machine_id) {
        // Get specific machine inventory
        $stmt = $pdo->prepare("
            SELECT nmi.*, nm.machine_name, nm.location,
                   TIMESTAMPDIFF(MINUTE, nmi.last_updated, NOW()) as minutes_old
            FROM nayax_machine_inventory nmi
            JOIN nayax_machines nm ON nmi.machine_id = nm.nayax_machine_id
            WHERE nmi.machine_id = ? AND nmi.business_id = ?
        ");
        $stmt->execute([$machine_id, $business_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            $result['inventory_data'] = json_decode($result['inventory_data'], true);
            return $result;
        } else {
            return null;
        }
    } else {
        // Get all machine inventories
        $stmt = $pdo->prepare("
            SELECT nmi.machine_id, nmi.product_count, nmi.last_updated,
                   nm.machine_name, nm.location,
                   TIMESTAMPDIFF(MINUTE, nmi.last_updated, NOW()) as minutes_old
            FROM nayax_machine_inventory nmi
            JOIN nayax_machines nm ON nmi.machine_id = nm.nayax_machine_id
            WHERE nmi.business_id = ?
            ORDER BY nm.machine_name
        ");
        $stmt->execute([$business_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

/**
 * Get machine sync status
 */
function getMachinesStatus($business_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT nm.nayax_machine_id, nm.machine_name, nm.location, nm.status,
               nm.last_sync_at, nmi.product_count, nmi.last_updated as inventory_updated,
               TIMESTAMPDIFF(MINUTE, nm.last_sync_at, NOW()) as sync_minutes_ago,
               TIMESTAMPDIFF(MINUTE, nmi.last_updated, NOW()) as inventory_minutes_ago,
               COUNT(bsi.id) as discount_count
        FROM nayax_machines nm
        LEFT JOIN nayax_machine_inventory nmi ON nm.nayax_machine_id = nmi.machine_id
        LEFT JOIN business_store_items bsi ON nm.nayax_machine_id = bsi.nayax_machine_id 
                                            AND bsi.category = 'discount' AND bsi.is_active = 1
        WHERE nm.business_id = ?
        GROUP BY nm.id
        ORDER BY nm.machine_name
    ");
    $stmt->execute([$business_id]);
    $machines = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $total_machines = count($machines);
    $active_machines = 0;
    $machines_with_inventory = 0;
    $machines_with_discounts = 0;
    
    foreach ($machines as &$machine) {
        if ($machine['status'] === 'active') {
            $active_machines++;
        }
        if ($machine['product_count'] > 0) {
            $machines_with_inventory++;
        }
        if ($machine['discount_count'] > 0) {
            $machines_with_discounts++;
        }
        
        // Add status indicators
        $machine['needs_sync'] = $machine['sync_minutes_ago'] > 60 || $machine['sync_minutes_ago'] === null;
        $machine['inventory_stale'] = $machine['inventory_minutes_ago'] > 120 || $machine['inventory_minutes_ago'] === null;
    }
    
    return [
        'summary' => [
            'total_machines' => $total_machines,
            'active_machines' => $active_machines,
            'machines_with_inventory' => $machines_with_inventory,
            'machines_with_discounts' => $machines_with_discounts
        ],
        'machines' => $machines
    ];
}

/**
 * Fetch machine inventory from Nayax API
 */
function fetchMachineInventoryFromAPI($machine_id, $credentials) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $credentials['api_url'] . '/machines/' . $machine_id . '/products');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $credentials['access_token'],
        'Content-Type: application/json',
        'User-Agent: RevenueQR-Integration/1.0'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if ($curl_error) {
        throw new Exception('Curl error: ' . $curl_error);
    }
    
    if ($http_code !== 200) {
        throw new Exception('HTTP error ' . $http_code . ': ' . $response);
    }
    
    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON response from Nayax API');
    }
    
    return $data;
}

/**
 * Log API call for debugging
 */
function logAPICall($endpoint, $method, $response_code, $response_time) {
    global $pdo, $business_id;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO api_logs (business_id, endpoint, method, response_code, response_time_ms, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$business_id, $endpoint, $method, $response_code, $response_time]);
    } catch (Exception $e) {
        // Ignore logging errors
    }
}
?> 