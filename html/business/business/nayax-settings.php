<?php
/**
 * Nayax Integration Settings for Businesses
 * Allows businesses to connect their Nayax accounts and sync machines
 */

require_once __DIR__ . '/../html/core/config.php';
require_once __DIR__ . '/../html/core/session.php';
require_once __DIR__ . '/../html/core/auth.php';

// Check if user is logged in and is business user
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'business') {
    header('Location: /html/login.php');
    exit;
}

$business_id = $_SESSION['business_id'];
$success_message = '';
$error_message = '';

// Handle form submission
if ($_POST) {
    try {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'save_credentials') {
            $nayax_access_token = trim($_POST['nayax_access_token'] ?? '');
            $nayax_api_url = trim($_POST['nayax_api_url'] ?? 'https://lynx.nayax.com/operational/api/v1');
            
            if (empty($nayax_access_token)) {
                throw new Exception('Access token is required');
            }
            
            // Test the connection first
            $test_result = testNayaxConnection($nayax_access_token, $nayax_api_url);
            if (!$test_result['success']) {
                throw new Exception('Failed to connect to Nayax: ' . $test_result['error']);
            }
            
            // Store credentials (encrypted)
            $stmt = $pdo->prepare("
                INSERT INTO business_nayax_credentials (business_id, access_token, api_url, is_active, created_at, updated_at)
                VALUES (?, AES_ENCRYPT(?, 'nayax_secure_key_2025'), ?, 1, NOW(), NOW())
                ON DUPLICATE KEY UPDATE 
                access_token = AES_ENCRYPT(VALUES(access_token), 'nayax_secure_key_2025'),
                api_url = VALUES(api_url),
                is_active = 1,
                updated_at = NOW()
            ");
            $stmt->execute([$business_id, $nayax_access_token, $nayax_api_url]);
            
            // Sync machines immediately
            $sync_result = syncNayaxMachines($business_id, $nayax_access_token, $nayax_api_url);
            
            $success_message = "Nayax integration configured successfully! Synced " . $sync_result['machines_count'] . " machines.";
            
        } elseif ($action === 'sync_machines') {
            // Re-sync machines
            $credentials = getNayaxCredentials($business_id);
            if (!$credentials) {
                throw new Exception('Please configure your Nayax credentials first');
            }
            
            $sync_result = syncNayaxMachines($business_id, $credentials['access_token'], $credentials['api_url']);
            $success_message = "Machine sync completed! Found " . $sync_result['machines_count'] . " machines.";
            
        } elseif ($action === 'sync_inventory') {
            // Sync inventory for all machines
            $sync_result = syncAllMachineInventory($business_id);
            $success_message = "Inventory sync completed for " . $sync_result['synced_count'] . " machines.";
            
        } elseif ($action === 'test_token') {
            // Test provided token without saving
            $test_token = trim($_POST['test_token'] ?? '');
            $test_api_url = trim($_POST['test_api_url'] ?? 'https://lynx.nayax.com/operational/api/v1');
            
            if (empty($test_token)) {
                throw new Exception('Test token is required');
            }
            
            $test_result = testNayaxConnection($test_token, $test_api_url);
            if ($test_result['success']) {
                $device_count = count($test_result['data'] ?? []);
                $success_message = "✅ Token test successful! Found $device_count devices. You can now save this token to connect your Nayax account.";
            } else {
                throw new Exception('Token test failed: ' . $test_result['error']);
            }
            
        } elseif ($action === 'disconnect') {
            // Disconnect Nayax integration
            $stmt = $pdo->prepare("UPDATE business_nayax_credentials SET is_active = 0 WHERE business_id = ?");
            $stmt->execute([$business_id]);
            $success_message = "Nayax integration disconnected successfully.";
        }
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Get current settings
$current_settings = null;
$stmt = $pdo->prepare("
    SELECT api_url, 
           CASE WHEN access_token IS NOT NULL THEN 'configured' ELSE 'not_configured' END as token_status,
           is_active, updated_at, last_sync_at, total_machines
    FROM business_nayax_credentials 
    WHERE business_id = ?
");
$stmt->execute([$business_id]);
$current_settings = $stmt->fetch(PDO::FETCH_ASSOC);

// Get synced machines
$stmt = $pdo->prepare("
    SELECT nayax_machine_id, machine_name, status, last_sync_at, location,
           CASE WHEN device_info IS NOT NULL THEN JSON_EXTRACT(device_info, '$.Name') ELSE machine_name END as display_name
    FROM nayax_machines 
    WHERE business_id = ? 
    ORDER BY machine_name
");
$stmt->execute([$business_id]);
$machines = $stmt->fetchAll(PDO::FETCH_ASSOC);

/**
 * Test Nayax API connection
 */
function testNayaxConnection($access_token, $api_url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url . '/devices');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $access_token,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if ($curl_error) {
        return ['success' => false, 'error' => 'Connection error: ' . $curl_error];
    }
    
    if ($http_code === 200) {
        $data = json_decode($response, true);
        return ['success' => true, 'data' => $data];
    } else {
        return ['success' => false, 'error' => 'HTTP ' . $http_code . ': ' . $response];
    }
}

/**
 * Sync machines from Nayax API
 */
function syncNayaxMachines($business_id, $access_token, $api_url) {
    global $pdo;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url . '/devices');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $access_token,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200) {
        throw new Exception('Failed to fetch machines from Nayax API: HTTP ' . $http_code);
    }
    
    $devices = json_decode($response, true);
    if (!is_array($devices)) {
        throw new Exception('Invalid response format from Nayax API');
    }
    
    $machines_count = 0;
    foreach ($devices as $device) {
        $nayax_machine_id = $device['Id'] ?? $device['id'] ?? '';
        $machine_name = $device['Name'] ?? $device['name'] ?? 'Machine ' . $nayax_machine_id;
        $location = $device['Location'] ?? $device['location'] ?? '';
        
        if (!$nayax_machine_id) continue;
        
        // Insert or update machine
        $stmt = $pdo->prepare("
            INSERT INTO nayax_machines (business_id, nayax_machine_id, machine_name, location, device_info, status, last_sync_at, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, 'active', NOW(), NOW(), NOW())
            ON DUPLICATE KEY UPDATE 
            machine_name = VALUES(machine_name),
            location = VALUES(location),
            device_info = VALUES(device_info),
            status = 'active',
            last_sync_at = NOW(),
            updated_at = NOW()
        ");
        $stmt->execute([$business_id, $nayax_machine_id, $machine_name, $location, json_encode($device)]);
        $machines_count++;
    }
    
    // Update total machines count in credentials
    $stmt = $pdo->prepare("UPDATE business_nayax_credentials SET total_machines = ?, last_sync_at = NOW() WHERE business_id = ?");
    $stmt->execute([$machines_count, $business_id]);
    
    return ['machines_count' => $machines_count];
}

/**
 * Get Nayax credentials for business
 */
function getNayaxCredentials($business_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT AES_DECRYPT(access_token, 'nayax_secure_key_2025') as access_token, api_url
        FROM business_nayax_credentials 
        WHERE business_id = ? AND is_active = 1
    ");
    $stmt->execute([$business_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Sync inventory for all machines
 */
function syncAllMachineInventory($business_id) {
    global $pdo;
    
    $credentials = getNayaxCredentials($business_id);
    if (!$credentials) {
        throw new Exception('No Nayax credentials found');
    }
    
    $stmt = $pdo->prepare("SELECT nayax_machine_id FROM nayax_machines WHERE business_id = ? AND status = 'active'");
    $stmt->execute([$business_id]);
    $machines = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $synced_count = 0;
    foreach ($machines as $machine_id) {
        try {
            fetchMachineInventory($machine_id, $credentials['access_token'], $credentials['api_url']);
            $synced_count++;
            usleep(500000); // 0.5 second delay between requests
        } catch (Exception $e) {
            error_log("Failed to sync inventory for machine $machine_id: " . $e->getMessage());
        }
    }
    
    return ['synced_count' => $synced_count];
}

/**
 * Fetch inventory for a specific machine
 */
function fetchMachineInventory($machine_id, $access_token, $api_url) {
    global $pdo;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url . '/machines/' . $machine_id . '/products');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $access_token,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200) {
        throw new Exception('Failed to fetch inventory: HTTP ' . $http_code);
    }
    
    $products = json_decode($response, true);
    if (!is_array($products)) {
        throw new Exception('Invalid inventory response format');
    }
    
    // Store inventory data
    $stmt = $pdo->prepare("
        INSERT INTO nayax_machine_inventory (machine_id, business_id, inventory_data, product_count, last_updated)
        VALUES (?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE 
        inventory_data = VALUES(inventory_data),
        product_count = VALUES(product_count),
        last_updated = NOW()
    ");
    $stmt->execute([$machine_id, $_SESSION['business_id'], json_encode($products), count($products)]);
    
    return $products;
}

// Include header
require_once __DIR__ . '/../html/core/includes/header.php';
?>

<div class="container mt-4">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <h2><i class="bi bi-gear me-2"></i>Nayax Settings</h2>
                    <p class="text-muted mb-0">Configure your Nayax integration and sync machines</p>
                </div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="/html/business/dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item active">Nayax Settings</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>

    <!-- Messages -->
    <?php if ($success_message): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($success_message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="bi bi-exclamation-circle me-2"></i><?= htmlspecialchars($error_message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Integration Status -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-activity me-2"></i>Integration Status</h5>
                </div>
                <div class="card-body">
                    <?php if ($current_settings && $current_settings['is_active']): ?>
                    <div class="d-flex align-items-center mb-3">
                        <div class="status-indicator bg-success me-3"></div>
                        <div>
                            <h6 class="mb-1">✅ Connected</h6>
                            <small class="text-muted">
                                Last updated: <?= $current_settings['updated_at'] ? date('M j, Y g:i A', strtotime($current_settings['updated_at'])) : 'Never' ?>
                            </small>
                        </div>
                    </div>

                    <div class="row text-center">
                        <div class="col-md-4">
                            <div class="metric">
                                <div class="metric-value"><?= $current_settings['total_machines'] ?? 0 ?></div>
                                <div class="metric-label">Synced Machines</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="metric">
                                <div class="metric-value"><?= count($machines) ?></div>
                                <div class="metric-label">Active Machines</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="metric">
                                <div class="metric-value">
                                    <?= $current_settings['last_sync_at'] ? date('M j', strtotime($current_settings['last_sync_at'])) : 'Never' ?>
                                </div>
                                <div class="metric-label">Last Sync</div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-3">
                        <form method="post" class="d-inline me-2">
                            <input type="hidden" name="action" value="sync_machines">
                            <button type="submit" class="btn btn-outline-primary">
                                <i class="bi bi-arrow-clockwise me-1"></i>Sync Machines
                            </button>
                        </form>
                        <form method="post" class="d-inline me-2">
                            <input type="hidden" name="action" value="sync_inventory">
                            <button type="submit" class="btn btn-outline-info">
                                <i class="bi bi-box me-1"></i>Sync Inventory
                            </button>
                        </form>
                        <form method="post" class="d-inline">
                            <input type="hidden" name="action" value="disconnect">
                            <button type="submit" class="btn btn-outline-danger" onclick="return confirm('Are you sure you want to disconnect Nayax integration?')">
                                <i class="bi bi-x-circle me-1"></i>Disconnect
                            </button>
                        </form>
                    </div>
                    <?php else: ?>
                    <div class="d-flex align-items-center">
                        <div class="status-indicator bg-warning me-3"></div>
                        <div>
                            <h6 class="mb-1">⚠️ Not Connected</h6>
                            <small class="text-muted">Configure your Nayax access token below to get started</small>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Test Your Token -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header" style="background: linear-gradient(135deg, #e3f2fd, #bbdefb); border-bottom: 1px solid #90caf9;">
                    <h5 class="mb-0" style="color: #1565c0;"><i class="bi bi-shield-check me-2"></i>Test Your Token</h5>
                    <small class="text-muted">Test your access token without saving it to verify it works</small>
                </div>
                <div class="card-body">
                    <form method="post">
                        <input type="hidden" name="action" value="test_token">
                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label for="test_token" class="form-label">Nayax Access Token</label>
                                    <input type="text" class="form-control" id="test_token" name="test_token" 
                                           placeholder="Enter your Nayax access token..." required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="test_api_url" class="form-label">API URL</label>
                                    <input type="url" class="form-control" id="test_api_url" name="test_api_url" 
                                           value="https://lynx.nayax.com/operational/api/v1">
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-info">
                            <i class="bi bi-play-circle me-1"></i>Test Token
                        </button>
                        <small class="text-muted ms-3">This will not save your token, just test if it works</small>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Configuration -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-sliders me-2"></i>Nayax Configuration</h5>
                </div>
                <div class="card-body">
                    <form method="post">
                        <input type="hidden" name="action" value="save_credentials">
                        
                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label for="nayax_access_token" class="form-label">
                                        Nayax Access Token <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" class="form-control" id="nayax_access_token" name="nayax_access_token" 
                                           placeholder="Enter your Nayax access token..." required>
                                    <div class="form-text">
                                        Your token will be encrypted and stored securely. Get this from your Nayax Lynx account.
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="nayax_api_url" class="form-label">API URL</label>
                                    <input type="url" class="form-control" id="nayax_api_url" name="nayax_api_url" 
                                           value="<?= htmlspecialchars($current_settings['api_url'] ?? 'https://lynx.nayax.com/operational/api/v1') ?>">
                                    <div class="form-text">Leave default unless instructed otherwise</div>
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save me-1"></i>Save & Test Connection
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Synced Machines -->
    <?php if (!empty($machines)): ?>
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-cpu me-2"></i>Synced Machines (<?= count($machines) ?>)</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($machines as $machine): ?>
                        <div class="col-md-6 col-lg-4 mb-3">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h6 class="card-title"><?= htmlspecialchars($machine['display_name']) ?></h6>
                                    <p class="card-text">
                                        <small class="text-muted">ID: <?= htmlspecialchars($machine['nayax_machine_id']) ?></small><br>
                                        <?php if ($machine['location']): ?>
                                        <small class="text-muted">📍 <?= htmlspecialchars($machine['location']) ?></small><br>
                                        <?php endif; ?>
                                        <span class="badge bg-<?= $machine['status'] === 'active' ? 'success' : 'secondary' ?>">
                                            <?= ucfirst($machine['status']) ?>
                                        </span>
                                    </p>
                                    <small class="text-muted">
                                        Last sync: <?= $machine['last_sync_at'] ? date('M j, g:i A', strtotime($machine['last_sync_at'])) : 'Never' ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
.status-indicator {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    display: inline-block;
}

.metric {
    padding: 1rem;
    border-radius: 0.5rem;
    background: #f8f9fa;
}

.metric-value {
    font-size: 1.5rem;
    font-weight: bold;
    color: #495057;
}

.metric-label {
    font-size: 0.875rem;
    color: #6c757d;
    margin-top: 0.25rem;
}
</style>

<?php require_once __DIR__ . '/../html/core/includes/footer.php'; ?> 