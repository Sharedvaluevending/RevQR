<?php
/**
 * Enhanced Discount Store with Nayax Integration
 * Allows businesses to create machine-specific discounts based on real inventory
 */

require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/auth.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'business') {
    header('Location: /login.php');
    exit;
}

$business_id = $_SESSION['business_id'];
$success_message = '';
$error_message = '';

// Check if business has Nayax integration
$stmt = $pdo->prepare("
    SELECT api_url, is_active, total_machines
    FROM business_nayax_credentials 
    WHERE business_id = ? AND is_active = 1
");
$stmt->execute([$business_id]);
$nayax_integration = $stmt->fetch(PDO::FETCH_ASSOC);

// Get business Nayax machines
$machines = [];
if ($nayax_integration) {
    $stmt = $pdo->prepare("
        SELECT nm.nayax_machine_id, nm.machine_name, nm.status, nm.last_sync_at, nm.location,
               COUNT(bsi.id) as discount_items_count,
               nmi.product_count, nmi.last_updated as inventory_last_updated,
               CASE WHEN nm.device_info IS NOT NULL THEN JSON_EXTRACT(nm.device_info, '$.Name') ELSE nm.machine_name END as display_name
        FROM nayax_machines nm
        LEFT JOIN business_store_items bsi ON nm.nayax_machine_id = bsi.nayax_machine_id AND bsi.category = 'discount' AND bsi.is_active = 1
        LEFT JOIN nayax_machine_inventory nmi ON nm.nayax_machine_id = nmi.machine_id
        WHERE nm.business_id = ? AND nm.status = 'active'
        GROUP BY nm.id
        ORDER BY nm.machine_name
    ");
    $stmt->execute([$business_id]);
    $machines = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Handle machine selection for creating discounts
$selected_machine = null;
$machine_inventory = [];

if (isset($_GET['machine_id']) && $nayax_integration) {
    $machine_id = $_GET['machine_id'];
    
    // Get machine details
    $stmt = $pdo->prepare("SELECT * FROM nayax_machines WHERE nayax_machine_id = ? AND business_id = ?");
    $stmt->execute([$machine_id, $business_id]);
    $selected_machine = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($selected_machine) {
        // Get cached inventory
        $stmt = $pdo->prepare("
            SELECT inventory_data, last_updated 
            FROM nayax_machine_inventory 
            WHERE machine_id = ? AND business_id = ?
        ");
        $stmt->execute([$machine_id, $business_id]);
        $inventory_cache = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($inventory_cache && $inventory_cache['inventory_data']) {
            $machine_inventory = json_decode($inventory_cache['inventory_data'], true) ?: [];
        } else {
            // Try to fetch from API if no cache
            $machine_inventory = fetchMachineInventoryLive($machine_id);
        }
    }
}

// Handle new discount creation
if ($_POST && isset($_POST['create_discount'])) {
    try {
        $machine_id = $_POST['machine_id'];
        $item_selection = $_POST['item_selection'];
        $item_name = $_POST['item_name'];
        $original_price_cents = (int)$_POST['original_price_cents'];
        $discount_percent = (float)$_POST['discount_percent'];
        $qr_coin_price = (int)$_POST['qr_coin_price'];
        $max_uses = (int)$_POST['max_uses'];
        
        if ($discount_percent < 5 || $discount_percent > 50) {
            throw new Exception('Discount percentage must be between 5% and 50%');
        }
        
        if ($qr_coin_price < 1) {
            throw new Exception('QR coin price must be at least 1');
        }
        
        // Create discount item
        $stmt = $pdo->prepare("
            INSERT INTO business_store_items (
                business_id, nayax_machine_id, nayax_item_selection, 
                item_name, category, discount_percentage, original_price_cents,
                qr_coin_cost, max_uses, is_active, created_at
            ) VALUES (?, ?, ?, ?, 'discount', ?, ?, ?, ?, 1, NOW())
        ");
        $stmt->execute([
            $business_id, $machine_id, $item_selection, 
            $item_name, $discount_percent, $original_price_cents, $qr_coin_price, $max_uses
        ]);
        
        // Create corresponding QR store item
        $business_store_item_id = $pdo->lastInsertId();
        $discount_description = "Get {$discount_percent}% off {$item_name} at machine {$machine_id}";
        $stmt = $pdo->prepare("
            INSERT INTO qr_store_items (
                business_store_item_id, item_name, description, qr_coin_price,
                item_type, nayax_compatible, is_active, created_at
            ) VALUES (?, ?, ?, ?, 'discount', 1, 1, NOW())
        ");
        $stmt->execute([
            $business_store_item_id,
            $item_name . " - " . $discount_percent . "% Off",
            $discount_description,
            $qr_coin_price
        ]);
        
        $success_message = "Discount created successfully! Users can now purchase this discount with QR coins.";
        
        // Clear the form by redirecting
        header("Location: discount-store.php?machine_id=" . urlencode($machine_id) . "&success=1");
        exit;
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Handle discount item deletion
if ($_POST && isset($_POST['delete_discount'])) {
    try {
        $discount_id = (int)$_POST['discount_id'];
        
        // Get the discount item
        $stmt = $pdo->prepare("
            SELECT id FROM business_store_items 
            WHERE id = ? AND business_id = ? AND category = 'discount'
        ");
        $stmt->execute([$discount_id, $business_id]);
        $discount = $stmt->fetch();
        
        if (!$discount) {
            throw new Exception('Discount not found');
        }
        
        // Delete the business store item (this will cascade to QR store items)
        $stmt = $pdo->prepare("UPDATE business_store_items SET is_active = 0 WHERE id = ?");
        $stmt->execute([$discount_id]);
        
        $success_message = "Discount deleted successfully!";
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Get existing discounts for selected machine
$existing_discounts = [];
if ($selected_machine) {
    $stmt = $pdo->prepare("
        SELECT bsi.*, 
               bsi.current_uses,
               qsi.id as qr_store_item_id,
               COUNT(usp.id) as total_purchases
        FROM business_store_items bsi
        LEFT JOIN qr_store_items qsi ON bsi.id = qsi.business_store_item_id
        LEFT JOIN user_store_purchases usp ON qsi.id = usp.store_item_id
        WHERE bsi.nayax_machine_id = ? AND bsi.business_id = ? AND bsi.category = 'discount' AND bsi.is_active = 1
        GROUP BY bsi.id
        ORDER BY bsi.created_at DESC
    ");
    $stmt->execute([$selected_machine['nayax_machine_id'], $business_id]);
    $existing_discounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Fetch machine inventory from Nayax API (live)
 */
function fetchMachineInventoryLive($machine_id) {
    global $pdo, $business_id;
    
    // Get Nayax credentials
    $stmt = $pdo->prepare("
        SELECT AES_DECRYPT(access_token, 'nayax_secure_key_2025') as access_token, api_url
        FROM business_nayax_credentials 
        WHERE business_id = ? AND is_active = 1
    ");
    $stmt->execute([$business_id]);
    $credentials = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$credentials) {
        return [];
    }
    
    // Fetch from Nayax API
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $credentials['api_url'] . '/machines/' . $machine_id . '/products');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $credentials['access_token'],
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        $inventory = json_decode($response, true);
        
        // Cache the inventory
        $stmt = $pdo->prepare("
            INSERT INTO nayax_machine_inventory (machine_id, business_id, inventory_data, product_count)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
            inventory_data = VALUES(inventory_data),
            product_count = VALUES(product_count)
        ");
        $stmt->execute([$machine_id, $business_id, json_encode($inventory), count($inventory)]);
        
        return $inventory;
    }
    
    return [];
}

include __DIR__ . '/../templates/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2><i class="fas fa-tag"></i> Discount Store Management</h2>
                    <p class="text-muted">Create machine-specific discounts that customers can purchase with QR coins</p>
                </div>
                <div>
                    <a href="nayax-settings.php" class="btn btn-outline-secondary">
                        <i class="fas fa-cog"></i> Nayax Settings
                    </a>
                </div>
            </div>
            
            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle"></i> Discount created successfully!
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
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
            
            <?php if (!$nayax_integration): ?>
                <div class="alert alert-warning">
                    <h5><i class="fas fa-exclamation-triangle"></i> Nayax Integration Required</h5>
                    <p>You need to connect your Nayax account to create machine-specific discounts.</p>
                    <a href="nayax-settings.php" class="btn btn-primary">
                        <i class="fas fa-cog"></i> Configure Nayax Integration
                    </a>
                </div>
            <?php elseif (empty($machines)): ?>
                <div class="alert alert-info">
                    <h5><i class="fas fa-info-circle"></i> No Machines Found</h5>
                    <p>No active Nayax machines found. Please sync your machines first.</p>
                    <a href="nayax-settings.php" class="btn btn-primary">
                        <i class="fas fa-sync"></i> Sync Machines
                    </a>
                </div>
            <?php else: ?>
                
                <!-- Machine Selection -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h4><i class="fas fa-desktop"></i> Select Machine to Create Discounts</h4>
                        <small class="text-muted">Choose a machine to view its inventory and create discounts for specific items</small>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach ($machines as $machine): ?>
                            <div class="col-md-4 col-lg-3 mb-3">
                                <div class="card h-100 <?= isset($_GET['machine_id']) && $_GET['machine_id'] === $machine['nayax_machine_id'] ? 'border-primary shadow' : '' ?>">
                                    <div class="card-body text-center">
                                        <h6 class="card-title"><?= htmlspecialchars($machine['display_name'] ?? $machine['machine_name']) ?></h6>
                                        <p class="text-muted small"><?= htmlspecialchars($machine['location']) ?></p>
                                        
                                        <div class="row text-center mb-3">
                                            <div class="col-6">
                                                <small class="text-success">
                                                    <strong><?= $machine['discount_items_count'] ?></strong><br>
                                                    <span class="text-muted">Discounts</span>
                                                </small>
                                            </div>
                                            <div class="col-6">
                                                <small class="text-info">
                                                    <strong><?= $machine['product_count'] ?: '?' ?></strong><br>
                                                    <span class="text-muted">Products</span>
                                                </small>
                                            </div>
                                        </div>
                                        
                                        <a href="?machine_id=<?= urlencode($machine['nayax_machine_id']) ?>" 
                                           class="btn btn-<?= isset($_GET['machine_id']) && $_GET['machine_id'] === $machine['nayax_machine_id'] ? 'primary' : 'outline-primary' ?> btn-sm w-100">
                                            <i class="fas fa-boxes"></i> 
                                            <?= isset($_GET['machine_id']) && $_GET['machine_id'] === $machine['nayax_machine_id'] ? 'Selected' : 'Select Machine' ?>
                                        </a>
                                        
                                        <?php if ($machine['inventory_last_updated']): ?>
                                            <small class="text-muted d-block mt-1">
                                                Updated: <?= date('M j, H:i', strtotime($machine['inventory_last_updated'])) ?>
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Selected Machine Details -->
                <?php if ($selected_machine): ?>
                <div class="row">
                    <div class="col-lg-8">
                        <!-- Machine Inventory & Discount Creation -->
                        <div class="card">
                            <div class="card-header">
                                <h4><i class="fas fa-list"></i> Machine Inventory: <?= htmlspecialchars($selected_machine['machine_name']) ?></h4>
                                <small class="text-muted">Create discounts for items in this machine</small>
                            </div>
                            <div class="card-body">
                                <?php if (empty($machine_inventory)): ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-boxes fa-3x text-muted mb-3"></i>
                                        <p>No inventory data available for this machine.</p>
                                        <a href="nayax-settings.php" class="btn btn-outline-primary">
                                            <i class="fas fa-sync"></i> Sync Inventory
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Selection</th>
                                                    <th>Product Name</th>
                                                    <th>Price</th>
                                                    <th>Stock</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($machine_inventory as $item): ?>
                                                    <?php 
                                                    $selection = $item['Selection'] ?? $item['selection'] ?? 'N/A';
                                                    $name = $item['ProductName'] ?? $item['name'] ?? 'Unknown Product';
                                                    $price = ($item['Price'] ?? $item['price'] ?? 0);
                                                    $quantity = $item['Quantity'] ?? $item['quantity'] ?? 0;
                                                    ?>
                                                <tr>
                                                    <td><span class="badge bg-secondary"><?= htmlspecialchars($selection) ?></span></td>
                                                    <td><strong><?= htmlspecialchars($name) ?></strong></td>
                                                    <td>$<?= number_format($price / 100, 2) ?></td>
                                                    <td>
                                                        <span class="badge bg-<?= $quantity > 0 ? 'success' : 'warning' ?>">
                                                            <?= $quantity ?> in stock
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php if ($quantity > 0): ?>
                                                            <button type="button" class="btn btn-sm btn-primary" 
                                                                    onclick="createDiscount('<?= htmlspecialchars($selection) ?>', '<?= htmlspecialchars($name) ?>', <?= $price ?>)">
                                                                <i class="fas fa-percent"></i> Create Discount
                                                            </button>
                                                        <?php else: ?>
                                                            <span class="text-muted">Out of stock</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-4">
                        <!-- Existing Discounts -->
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-tags"></i> Active Discounts (<?= count($existing_discounts) ?>)</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($existing_discounts)): ?>
                                    <p class="text-muted text-center">No discounts created yet for this machine.</p>
                                <?php else: ?>
                                    <?php foreach ($existing_discounts as $discount): ?>
                                    <div class="border rounded p-3 mb-3">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div class="flex-grow-1">
                                                <h6 class="mb-1"><?= htmlspecialchars($discount['item_name']) ?></h6>
                                                <div class="small text-muted">
                                                    <div><strong><?= $discount['discount_percentage'] ?>% OFF</strong></div>
                                                    <div>Selection: <code><?= htmlspecialchars($discount['nayax_item_selection']) ?></code></div>
                                                    <div>Price: <?= $discount['qr_coin_cost'] ?> QR coins</div>
                                                    <div>Used: <?= $discount['current_uses'] ?>/<?= $discount['max_uses'] ?></div>
                                                </div>
                                            </div>
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                    <i class="fas fa-ellipsis-v"></i>
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <li>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="delete_discount" value="1">
                                                            <input type="hidden" name="discount_id" value="<?= $discount['id'] ?>">
                                                            <button type="submit" class="dropdown-item text-danger" 
                                                                    onclick="return confirm('Are you sure you want to delete this discount?')">
                                                                <i class="fas fa-trash"></i> Delete
                                                            </button>
                                                        </form>
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Discount Creation Modal -->
<div class="modal fade" id="discountModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-percent"></i> Create Discount</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="create_discount" value="1">
                    <input type="hidden" name="machine_id" value="<?= $selected_machine['nayax_machine_id'] ?? '' ?>">
                    <input type="hidden" name="item_selection" id="item_selection">
                    <input type="hidden" name="item_name" id="item_name">
                    <input type="hidden" name="original_price_cents" id="original_price_cents">
                    
                    <div class="alert alert-info">
                        <strong>Product:</strong> <span id="display_item_name"></span><br>
                        <strong>Machine:</strong> <?= htmlspecialchars($selected_machine['machine_name'] ?? '') ?><br>
                        <strong>Original Price:</strong> $<span id="display_price">0.00</span>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label">Discount Percentage *</label>
                            <div class="input-group">
                                <input type="number" name="discount_percent" class="form-control" 
                                       min="5" max="50" value="15" required id="discount_percent">
                                <span class="input-group-text">%</span>
                            </div>
                            <small class="text-muted">Between 5% and 50%</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">QR Coin Price *</label>
                            <input type="number" name="qr_coin_price" class="form-control" 
                                   min="1" value="50" required id="qr_coin_price">
                            <small class="text-muted">Cost in QR coins to purchase this discount</small>
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <label class="form-label">Maximum Uses</label>
                        <input type="number" name="max_uses" class="form-control" 
                               min="1" max="1000" value="100" required>
                        <small class="text-muted">How many times this discount can be used across all customers</small>
                    </div>
                    
                    <div class="mt-3 p-3 bg-light rounded">
                        <h6>Discount Preview:</h6>
                        <div id="discount_preview">
                            <div class="d-flex justify-content-between">
                                <span>Original Price:</span>
                                <span>$<span id="preview_original">0.00</span></span>
                            </div>
                            <div class="d-flex justify-content-between text-success">
                                <span>Discount (<span id="preview_percent">15</span>%):</span>
                                <span>-$<span id="preview_discount">0.00</span></span>
                            </div>
                            <div class="d-flex justify-content-between fw-bold">
                                <span>Final Price:</span>
                                <span>$<span id="preview_final">0.00</span></span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Create Discount
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function createDiscount(selection, itemName, priceCents) {
    document.getElementById('item_selection').value = selection;
    document.getElementById('item_name').value = itemName;
    document.getElementById('original_price_cents').value = priceCents;
    document.getElementById('display_item_name').textContent = itemName + ' (Selection: ' + selection + ')';
    document.getElementById('display_price').textContent = (priceCents / 100).toFixed(2);
    
    updateDiscountPreview();
    
    var modal = new bootstrap.Modal(document.getElementById('discountModal'));
    modal.show();
}

function updateDiscountPreview() {
    const originalPrice = parseFloat(document.getElementById('original_price_cents').value) / 100;
    const discountPercent = parseFloat(document.getElementById('discount_percent').value) || 0;
    
    const discountAmount = originalPrice * (discountPercent / 100);
    const finalPrice = originalPrice - discountAmount;
    
    document.getElementById('preview_original').textContent = originalPrice.toFixed(2);
    document.getElementById('preview_percent').textContent = discountPercent;
    document.getElementById('preview_discount').textContent = discountAmount.toFixed(2);
    document.getElementById('preview_final').textContent = finalPrice.toFixed(2);
}

// Update preview when discount percentage changes
document.addEventListener('DOMContentLoaded', function() {
    const discountInput = document.getElementById('discount_percent');
    if (discountInput) {
        discountInput.addEventListener('input', updateDiscountPreview);
    }
});

// Auto-refresh page every 5 minutes to update inventory
setInterval(function() {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('machine_id')) {
        // Only refresh if we're viewing a specific machine
        location.reload();
    }
}, 300000); // 5 minutes
</script>

<?php include __DIR__ . '/../templates/footer.php'; ?> 