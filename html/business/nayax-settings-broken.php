<?php
/**
 * Nayax Integration Settings for Businesses
 * Allows businesses to connect their Nayax accounts and sync machines
 */

require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/auth.php';

// Check if user is logged in and is business user
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'business') {
    header('Location: /login.php');
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
            
        } else                        if ($action === 'test_token') {
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

// Get machine inventory counts
$inventory_stats = [];
if (!empty($machines)) {
    $machine_ids = array_column($machines, 'nayax_machine_id');
    $placeholders = str_repeat('?,', count($machine_ids) - 1) . '?';
    $stmt = $pdo->prepare("
        SELECT machine_id, product_count, last_updated
        FROM nayax_machine_inventory 
        WHERE machine_id IN ($placeholders) AND business_id = ?
    ");
    $stmt->execute([...$machine_ids, $business_id]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $inventory_stats[$row['machine_id']] = $row;
    }
}

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
        $device_id = $device['Id'] ?? $device['id'] ?? null;
        $device_name = $device['Name'] ?? $device['name'] ?? 'Unknown Machine ' . $device_id;
        $location = $device['City'] ?? $device['location'] ?? '';
        
        if (!$device_id) continue;
        
        $stmt = $pdo->prepare("
            INSERT INTO nayax_machines (
                business_id, nayax_machine_id, nayax_device_id, machine_name, 
                location, status, device_info, last_sync_at, created_at, updated_at
            )
            VALUES (?, ?, ?, ?, ?, 'active', ?, NOW(), NOW(), NOW())
            ON DUPLICATE KEY UPDATE
            machine_name = VALUES(machine_name),
            location = VALUES(location),
            status = VALUES(status),
            device_info = VALUES(device_info),
            last_sync_at = NOW(),
            updated_at = NOW()
        ");
        $stmt->execute([
            $business_id,
            $device_id,
            $device_id, // Using device ID as machine ID for now
            $device_name,
            $location,
            json_encode($device)
        ]);
        $machines_count++;
    }
    
    // Update credentials table with machine count
    $stmt = $pdo->prepare("
        UPDATE business_nayax_credentials 
        SET total_machines = ?, last_sync_at = NOW() 
        WHERE business_id = ?
    ");
    $stmt->execute([$machines_count, $business_id]);
    
    return ['machines_count' => $machines_count];
}

/**
 * Get decrypted Nayax credentials
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
        throw new Exception('No active Nayax credentials found');
    }
    
    $stmt = $pdo->prepare("SELECT nayax_machine_id FROM nayax_machines WHERE business_id = ? AND status = 'active'");
    $stmt->execute([$business_id]);
    $machines = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $synced_count = 0;
    foreach ($machines as $machine_id) {
        try {
            $inventory = fetchMachineInventory($machine_id, $credentials['access_token'], $credentials['api_url']);
            if ($inventory) {
                $stmt = $pdo->prepare("
                    INSERT INTO nayax_machine_inventory (machine_id, business_id, inventory_data, product_count)
                    VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE 
                    inventory_data = VALUES(inventory_data),
                    product_count = VALUES(product_count)
                ");
                $stmt->execute([$machine_id, $business_id, json_encode($inventory), count($inventory)]);
                $synced_count++;
            }
        } catch (Exception $e) {
            // Log error but continue with other machines
            error_log("Failed to sync inventory for machine $machine_id: " . $e->getMessage());
        }
    }
    
    return ['synced_count' => $synced_count];
}

/**
 * Fetch machine inventory from Nayax API
 */
function fetchMachineInventory($machine_id, $access_token, $api_url) {
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
    
    if ($http_code === 200) {
        return json_decode($response, true);
    }
    
    return null;
}

include __DIR__ . '/../templates/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-12">
            <h2><i class="fas fa-cog"></i> Nayax Integration Settings</h2>
            <p class="text-muted">Connect your Nayax account to enable machine-specific discount distribution</p>
            
            <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success_message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error_message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <!-- Connection Status -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4><i class="fas fa-plug"></i> Connection Status</h4>
                    <?php if ($current_settings && $current_settings['is_active']): ?>
                        <span class="badge bg-success fs-6">Connected</span>
                    <?php else: ?>
                        <span class="badge bg-danger fs-6">Not Connected</span>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if ($current_settings && $current_settings['is_active']): ?>
                        <div class="row">
                            <div class="col-md-3 text-center">
                                <h5 class="text-success"><?= $current_settings['total_machines'] ?></h5>
                                <small class="text-muted">Machines Synced</small>
                            </div>
                            <div class="col-md-3 text-center">
                                <h5 class="text-info"><?= count($machines) ?></h5>
                                <small class="text-muted">Active Machines</small>
                            </div>
                            <div class="col-md-3 text-center">
                                <h5 class="text-warning"><?= $current_settings['last_sync_at'] ? date('M j, H:i', strtotime($current_settings['last_sync_at'])) : 'Never' ?></h5>
                                <small class="text-muted">Last Sync</small>
                            </div>
                            <div class="col-md-3 text-center">
                                <h5 class="text-primary"><?= htmlspecialchars($current_settings['api_url']) ?></h5>
                                <small class="text-muted">API Endpoint</small>
                            </div>
                        </div>
                        
                        <div class="mt-3">
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="action" value="sync_machines">
                                <button type="submit" class="btn btn-outline-primary me-2">
                                    <i class="fas fa-sync"></i> Sync Machines
                                </button>
                            </form>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="action" value="sync_inventory">
                                <button type="submit" class="btn btn-outline-info me-2">
                                    <i class="fas fa-boxes"></i> Sync Inventory
                                </button>
                            </form>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="action" value="disconnect">
                                <button type="submit" class="btn btn-outline-danger" 
                                        onclick="return confirm('Are you sure you want to disconnect Nayax integration?')">
                                    <i class="fas fa-unlink"></i> Disconnect
                                </button>
                            </form>
                        </div>
                    <?php else: ?>
                        <p class="text-muted mb-0">
                            <i class="fas fa-info-circle"></i> 
                            Connect your Nayax account to start syncing machines and creating machine-specific discounts.
                        </p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Test Token -->
            <div class="card mb-4">
                <div class="card-header bg-light">
                    <h5><i class="fas fa-vial"></i> Test Your Token</h5>
                    <small class="text-muted">Test your Nayax API token before saving it to verify it works</small>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="test_token">
                        
                        <div class="row">
                            <div class="col-md-8">
                                <label class="form-label">Test Access Token</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-flask"></i></span>
                                    <input type="password" name="test_token" class="form-control" 
                                           placeholder="Paste your Nayax access token here to test" required>
                                </div>
                                <small class="text-muted">This will NOT be saved - just tested to verify it works</small>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Test API URL</label>
                                <input type="url" name="test_api_url" class="form-control" 
                                       value="https://lynx.nayax.com/operational/api/v1">
                            </div>
                        </div>
                        
                        <div class="mt-3">
                            <button type="submit" class="btn btn-outline-info">
                                <i class="fas fa-vial"></i> Test Token (No Save)
                            </button>
                            <small class="text-muted ms-3">
                                <i class="fas fa-info-circle"></i> 
                                This will test the connection and show how many devices are found
                            </small>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Configuration Form -->
            <div class="card mb-4">
                <div class="card-header">
                    <h4><i class="fas fa-key"></i> API Configuration</h4>
                    <small class="text-muted">Enter your Nayax API credentials to connect your account</small>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="save_credentials">
                        
                        <div class="row">
                            <div class="col-md-8">
                                <label class="form-label">Nayax Access Token *</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-key"></i></span>
                                    <input type="password" name="nayax_access_token" class="form-control" 
                                           placeholder="Enter your Nayax access token" required
                                           value="<?= $current_settings ? '••••••••••••••••••••••••••••••••••••••••••••••' : '' ?>">
                                </div>
                                <small class="text-muted">
                                    Get this from your Nayax dashboard under API settings. 
                                    <a href="https://lynx.nayax.com" target="_blank">Open Nayax Dashboard <i class="fas fa-external-link-alt"></i></a>
                                </small>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">API URL</label>
                                <input type="url" name="nayax_api_url" class="form-control" 
                                       value="<?= $current_settings['api_url'] ?? 'https://lynx.nayax.com/operational/api/v1' ?>">
                                <small class="text-muted">Default endpoint should work for most cases</small>
                            </div>
                        </div>
                        
                        <div class="mt-3">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> 
                                <?= $current_settings ? 'Update Connection' : 'Connect & Sync Machines' ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Synced Machines -->
            <?php if (!empty($machines)): ?>
            <div class="card">
                <div class="card-header">
                    <h4><i class="fas fa-desktop"></i> Your Nayax Machines (<?= count($machines) ?>)</h4>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Machine ID</th>
                                    <th>Name</th>
                                    <th>Location</th>
                                    <th>Status</th>
                                    <th>Inventory</th>
                                    <th>Last Sync</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($machines as $machine): ?>
                                <tr>
                                    <td><code><?= htmlspecialchars($machine['nayax_machine_id']) ?></code></td>
                                    <td>
                                        <strong><?= htmlspecialchars($machine['display_name'] ?? $machine['machine_name']) ?></strong>
                                    </td>
                                    <td><?= htmlspecialchars($machine['location']) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $machine['status'] === 'active' ? 'success' : 'warning' ?>">
                                            <?= ucfirst($machine['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (isset($inventory_stats[$machine['nayax_machine_id']])): ?>
                                            <span class="badge bg-info">
                                                <?= $inventory_stats[$machine['nayax_machine_id']]['product_count'] ?> items
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">Not synced</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <?= $machine['last_sync_at'] ? date('M j, H:i', strtotime($machine['last_sync_at'])) : 'Never' ?>
                                        </small>
                                    </td>
                                    <td>
                                        <a href="discount-store.php?machine_id=<?= urlencode($machine['nayax_machine_id']) ?>" 
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-tag"></i> Create Discounts
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Auto-refresh sync status every 30 seconds if actively syncing
setInterval(function() {
    const syncButtons = document.querySelectorAll('button[type="submit"]');
    const isDisabled = Array.from(syncButtons).some(btn => btn.disabled);
    
    if (isDisabled) {
        location.reload();
    }
}, 30000);
</script>

<?php include __DIR__ . '/../templates/footer.php'; ?> 