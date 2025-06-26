<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/store_manager.php';
require_once __DIR__ . '/../core/config_manager.php';

// Require admin role
require_role('admin');

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'add_item':
            $result = addQRStoreItem($_POST);
            echo json_encode($result);
            exit;
            
        case 'update_item':
            $result = updateQRStoreItem($_POST);
            echo json_encode($result);
            exit;
            
        case 'toggle_item':
            $result = toggleQRStoreItem($_POST['item_id']);
            echo json_encode($result);
            exit;
            
        case 'delete_item':
            $result = deleteQRStoreItem($_POST['item_id']);
            echo json_encode($result);
            exit;
            
        case 'create_flash_sale':
            $result = createFlashSale($_POST);
            echo json_encode($result);
            exit;
    }
}

function addQRStoreItem($data) {
    global $pdo;
    
    try {
        // Handle flash sale dates
        $valid_from = !empty($data['valid_from']) ? $data['valid_from'] : null;
        $valid_until = !empty($data['valid_until']) ? $data['valid_until'] : null;
        
        // Build item data JSON
        $item_data = [];
        if (!empty($data['avatar_url'])) $item_data['avatar_url'] = $data['avatar_url'];
        if (!empty($data['duration_days'])) $item_data['duration_days'] = (int)$data['duration_days'];
        if (!empty($data['duration_hours'])) $item_data['duration_hours'] = (int)$data['duration_hours'];
        if (!empty($data['spins'])) $item_data['spins'] = (int)$data['spins'];
        if (!empty($data['boost_percentage'])) $item_data['boost_percentage'] = (float)$data['boost_percentage'];
        if (!empty($data['pack_type'])) $item_data['pack_type'] = $data['pack_type'];
        if (!empty($data['features'])) $item_data['features'] = explode(',', $data['features']);
        
        $stmt = $pdo->prepare("
            INSERT INTO qr_store_items 
            (item_type, item_name, item_description, image_url, qr_coin_cost, item_data, 
             rarity, is_active, is_limited, stock_quantity, purchase_limit_per_user, 
             valid_from, valid_until, flash_sale, original_price)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $is_flash_sale = !empty($data['flash_sale']) ? 1 : 0;
        $original_price = $is_flash_sale && !empty($data['original_price']) ? (int)$data['original_price'] : null;
        
        $success = $stmt->execute([
            $data['item_type'],
            $data['item_name'],
            $data['item_description'] ?? '',
            $data['image_url'] ?? '',
            (int)$data['qr_coin_cost'],
            json_encode($item_data),
            $data['rarity'] ?? 'common',
            !empty($data['is_active']) ? 1 : 0,
            !empty($data['is_limited']) ? 1 : 0,
            !empty($data['stock_quantity']) ? (int)$data['stock_quantity'] : -1,
            !empty($data['purchase_limit_per_user']) ? (int)$data['purchase_limit_per_user'] : -1,
            $valid_from,
            $valid_until,
            $is_flash_sale,
            $original_price
        ]);
        
        return [
            'success' => $success,
            'message' => $success ? 'QR Store item added successfully!' : 'Failed to add item'
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

function updateQRStoreItem($data) {
    global $pdo;
    
    try {
        $valid_from = !empty($data['valid_from']) ? $data['valid_from'] : null;
        $valid_until = !empty($data['valid_until']) ? $data['valid_until'] : null;
        
        $item_data = [];
        if (!empty($data['avatar_url'])) $item_data['avatar_url'] = $data['avatar_url'];
        if (!empty($data['duration_days'])) $item_data['duration_days'] = (int)$data['duration_days'];
        if (!empty($data['duration_hours'])) $item_data['duration_hours'] = (int)$data['duration_hours'];
        if (!empty($data['spins'])) $item_data['spins'] = (int)$data['spins'];
        if (!empty($data['boost_percentage'])) $item_data['boost_percentage'] = (float)$data['boost_percentage'];
        if (!empty($data['pack_type'])) $item_data['pack_type'] = $data['pack_type'];
        if (!empty($data['features'])) $item_data['features'] = explode(',', $data['features']);
        
        $stmt = $pdo->prepare("
            UPDATE qr_store_items SET
                item_type = ?, item_name = ?, item_description = ?, image_url = ?, 
                qr_coin_cost = ?, item_data = ?, rarity = ?, is_active = ?, 
                is_limited = ?, stock_quantity = ?, purchase_limit_per_user = ?,
                valid_from = ?, valid_until = ?, flash_sale = ?, original_price = ?
            WHERE id = ?
        ");
        
        $is_flash_sale = !empty($data['flash_sale']) ? 1 : 0;
        $original_price = $is_flash_sale && !empty($data['original_price']) ? (int)$data['original_price'] : null;
        
        $success = $stmt->execute([
            $data['item_type'],
            $data['item_name'],
            $data['item_description'] ?? '',
            $data['image_url'] ?? '',
            (int)$data['qr_coin_cost'],
            json_encode($item_data),
            $data['rarity'] ?? 'common',
            !empty($data['is_active']) ? 1 : 0,
            !empty($data['is_limited']) ? 1 : 0,
            !empty($data['stock_quantity']) ? (int)$data['stock_quantity'] : -1,
            !empty($data['purchase_limit_per_user']) ? (int)$data['purchase_limit_per_user'] : -1,
            $valid_from,
            $valid_until,
            $is_flash_sale,
            $original_price,
            (int)$data['item_id']
        ]);
        
        return [
            'success' => $success,
            'message' => $success ? 'QR Store item updated successfully!' : 'Failed to update item'
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

function toggleQRStoreItem($item_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("UPDATE qr_store_items SET is_active = NOT is_active WHERE id = ?");
        $success = $stmt->execute([$item_id]);
        
        return [
            'success' => $success,
            'message' => $success ? 'Item status updated!' : 'Failed to update status'
        ];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

function deleteQRStoreItem($item_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("DELETE FROM qr_store_items WHERE id = ?");
        $success = $stmt->execute([$item_id]);
        
        return [
            'success' => $success,
            'message' => $success ? 'Item deleted successfully!' : 'Failed to delete item'
        ];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

function createFlashSale($data) {
    global $pdo;
    
    try {
        $duration_hours = (int)$data['duration_hours'];
        $valid_until = date('Y-m-d H:i:s', strtotime("+{$duration_hours} hours"));
        
        $item_data = [
            'flash_sale' => true,
            'duration_hours' => $duration_hours,
            'original_price' => (int)$data['original_price']
        ];
        
        $stmt = $pdo->prepare("
            INSERT INTO qr_store_items 
            (item_type, item_name, item_description, image_url, qr_coin_cost, item_data, 
             rarity, is_active, is_limited, stock_quantity, purchase_limit_per_user, 
             valid_from, valid_until, flash_sale, original_price)
            VALUES (?, ?, ?, ?, ?, ?, ?, 1, 1, ?, ?, NOW(), ?, 1, ?)
        ");
        
        $success = $stmt->execute([
            $data['item_type'],
            $data['item_name'] . ' - FLASH SALE',
            'Limited time flash sale! ' . ($data['item_description'] ?? ''),
            $data['image_url'] ?? '',
            (int)$data['sale_price'],
            json_encode($item_data),
            'epic', // Flash sales are epic by default
            (int)$data['stock_quantity'],
            !empty($data['purchase_limit_per_user']) ? (int)$data['purchase_limit_per_user'] : 1,
            $valid_until,
            (int)$data['original_price']
        ]);
        
        return [
            'success' => $success,
            'message' => $success ? 'Flash sale created successfully!' : 'Failed to create flash sale'
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

// Add flash_sale and original_price columns if they don't exist
try {
    $pdo->exec("ALTER TABLE qr_store_items ADD COLUMN IF NOT EXISTS flash_sale BOOLEAN DEFAULT FALSE");
    $pdo->exec("ALTER TABLE qr_store_items ADD COLUMN IF NOT EXISTS original_price INT NULL");
} catch (Exception $e) {
    // Columns may already exist
}

// Get all QR store items with enhanced data
$stmt = $pdo->prepare("
    SELECT 
        qsi.*,
        CASE 
            WHEN qsi.valid_until IS NOT NULL AND qsi.valid_until > NOW() 
            THEN TIMESTAMPDIFF(SECOND, NOW(), qsi.valid_until)
            ELSE 0 
        END as seconds_remaining,
        (SELECT COUNT(*) FROM user_qr_store_purchases WHERE qr_store_item_id = qsi.id) as total_purchased
    FROM qr_store_items qsi 
    ORDER BY qsi.flash_sale DESC, qsi.created_at DESC
");
$stmt->execute();
$qr_store_items = $stmt->fetchAll();

// Parse JSON data for display
foreach ($qr_store_items as &$item) {
    if ($item['item_data']) {
        $item['item_data'] = json_decode($item['item_data'], true);
    }
}

$page_title = "QR Store Management";
require_once __DIR__ . '/../core/includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1><i class="bi bi-shop me-2"></i><?php echo $page_title; ?></h1>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#flashSaleModal">
                        <i class="bi bi-lightning-charge me-2"></i>Create Flash Sale
                    </button>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addItemModal">
                        <i class="bi bi-plus-circle me-2"></i>Add Store Item
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Store Statistics -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body text-center">
                    <h5 class="card-title">Total Items</h5>
                    <h3><?php echo count($qr_store_items); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body text-center">
                    <h5 class="card-title">Active Items</h5>
                    <h3><?php echo count(array_filter($qr_store_items, fn($item) => $item['is_active'])); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body text-center">
                    <h5 class="card-title">Flash Sales</h5>
                    <h3><?php echo count(array_filter($qr_store_items, fn($item) => $item['flash_sale'])); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body text-center">
                    <h5 class="card-title">Total Purchases</h5>
                    <h3><?php echo array_sum(array_column($qr_store_items, 'total_purchased')); ?></h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Store Items Table -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0"><i class="bi bi-list me-2"></i>Store Items</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Type</th>
                            <th>Price</th>
                            <th>Rarity</th>
                            <th>Stock</th>
                            <th>Status</th>
                            <th>Sales</th>
                            <th>Timer</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($qr_store_items as $item): ?>
                            <tr class="<?php echo $item['flash_sale'] ? 'table-warning' : ''; ?>">
                                <td>
                                    <div class="d-flex align-items-center">
                                        <?php if ($item['image_url']): ?>
                                            <img src="<?php echo htmlspecialchars($item['image_url']); ?>" 
                                                 alt="Item" class="rounded me-2" style="width: 40px; height: 40px; object-fit: contain;">
                                        <?php endif; ?>
                                        <div>
                                            <strong><?php echo htmlspecialchars($item['item_name']); ?></strong>
                                            <?php if ($item['flash_sale']): ?>
                                                <span class="badge bg-warning text-dark ms-1">FLASH</span>
                                            <?php endif; ?>
                                            <?php if ($item['is_limited']): ?>
                                                <span class="badge bg-info ms-1">LIMITED</span>
                                            <?php endif; ?>
                                            <br>
                                            <small class="text-muted"><?php echo htmlspecialchars(substr($item['item_description'], 0, 50)); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-secondary">
                                        <?php echo ucfirst(str_replace('_', ' ', $item['item_type'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($item['flash_sale'] && $item['original_price']): ?>
                                        <del class="text-muted"><?php echo number_format($item['original_price']); ?></del><br>
                                        <strong class="text-success"><?php echo number_format($item['qr_coin_cost']); ?> QR</strong>
                                    <?php else: ?>
                                        <strong><?php echo number_format($item['qr_coin_cost']); ?> QR</strong>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo $item['rarity'] === 'legendary' ? 'warning' : 
                                             ($item['rarity'] === 'epic' ? 'danger' : 
                                              ($item['rarity'] === 'rare' ? 'success' : 'secondary')); 
                                    ?>">
                                        <?php echo ucfirst($item['rarity']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($item['stock_quantity'] >= 0): ?>
                                        <span class="<?php echo $item['stock_quantity'] <= 5 ? 'text-danger' : 'text-success'; ?>">
                                            <?php echo $item['stock_quantity']; ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">∞</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $item['is_active'] ? 'success' : 'secondary'; ?>">
                                        <?php echo $item['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                    <?php if ($item['valid_until'] && strtotime($item['valid_until']) < time()): ?>
                                        <span class="badge bg-danger">Expired</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="text-info"><?php echo $item['total_purchased']; ?></span>
                                </td>
                                <td>
                                    <?php if ($item['valid_until'] && $item['seconds_remaining'] > 0): ?>
                                        <div class="countdown-timer" data-end-time="<?php echo strtotime($item['valid_until']); ?>">
                                            <small class="text-<?php echo $item['flash_sale'] ? 'warning' : 'info'; ?>">
                                                <i class="bi bi-clock me-1"></i>
                                                <span class="countdown-display">Loading...</span>
                                            </small>
                                        </div>
                                    <?php elseif ($item['valid_until']): ?>
                                        <small class="text-muted">
                                            <i class="bi bi-clock-history me-1"></i>Expired
                                        </small>
                                    <?php else: ?>
                                        <small class="text-muted">No limit</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <button type="button" class="btn btn-sm btn-outline-primary" 
                                                onclick="editItem(<?php echo htmlspecialchars(json_encode($item)); ?>)">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-<?php echo $item['is_active'] ? 'warning' : 'success'; ?>"
                                                onclick="toggleItem(<?php echo $item['id']; ?>)">
                                            <i class="bi bi-<?php echo $item['is_active'] ? 'pause' : 'play'; ?>"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-danger"
                                                onclick="deleteItem(<?php echo $item['id']; ?>)">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Item Modal -->
<div class="modal fade" id="addItemModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add QR Store Item</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addItemForm">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Item Type</label>
                                <select class="form-select" name="item_type" required onchange="updateItemFields(this.value)">
                                    <option value="">Select Type</option>
                                    <option value="avatar">Avatar</option>
                                    <option value="spin_pack">Spin Pack</option>
                                    <option value="slot_pack">Slot Pack</option>
                                    <option value="vote_pack">Vote Pack</option>
                                    <option value="loot_box">Loot Box</option>
                                    <option value="multiplier">Multiplier</option>
                                    <option value="insurance">Insurance</option>
                                    <option value="analytics">Analytics</option>
                                    <option value="boost">Boost</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Rarity</label>
                                <select class="form-select" name="rarity">
                                    <option value="common">Common</option>
                                    <option value="rare">Rare</option>
                                    <option value="epic">Epic</option>
                                    <option value="legendary">Legendary</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Item Name</label>
                        <input type="text" class="form-control" name="item_name" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="item_description" rows="2"></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Image URL</label>
                        <input type="url" class="form-control" name="image_url">
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">QR Coin Cost</label>
                                <input type="number" class="form-control" name="qr_coin_cost" required min="1">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Stock Quantity (-1 = unlimited)</label>
                                <input type="number" class="form-control" name="stock_quantity" value="-1">
                            </div>
                        </div>
                    </div>

                    <!-- Time-based Settings -->
                    <div class="card mb-3">
                        <div class="card-header">
                            <h6 class="mb-0">Time-based Settings</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Valid From</label>
                                        <input type="datetime-local" class="form-control" name="valid_from">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Valid Until</label>
                                        <input type="datetime-local" class="form-control" name="valid_until">
                                    </div>
                                </div>
                            </div>

                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="flash_sale" id="flashSaleCheck">
                                <label class="form-check-label" for="flashSaleCheck">
                                    Flash Sale Item
                                </label>
                            </div>

                            <div id="flashSaleFields" class="mt-3" style="display: none;">
                                <div class="mb-3">
                                    <label class="form-label">Original Price (QR Coins)</label>
                                    <input type="number" class="form-control" name="original_price">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Dynamic Item Fields -->
                    <div id="itemSpecificFields"></div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Purchase Limit per User (-1 = unlimited)</label>
                                <input type="number" class="form-control" name="purchase_limit_per_user" value="-1">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check mt-4">
                                <input class="form-check-input" type="checkbox" name="is_active" checked>
                                <label class="form-check-label">Active</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_limited">
                                <label class="form-check-label">Limited Edition</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Item</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Flash Sale Modal -->
<div class="modal fade" id="flashSaleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title text-dark">
                    <i class="bi bi-lightning-charge me-2"></i>Create Flash Sale
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="flashSaleForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Item Type</label>
                        <select class="form-select" name="item_type" required>
                            <option value="spin_pack">Spin Pack</option>
                            <option value="slot_pack">Slot Pack</option>
                            <option value="loot_box">Loot Box</option>
                            <option value="boost">Boost</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Flash Sale Name</label>
                        <input type="text" class="form-control" name="item_name" required 
                               placeholder="e.g., Lightning Spin Pack">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="item_description" rows="2"
                                  placeholder="Special flash sale description..."></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Image URL</label>
                        <input type="url" class="form-control" name="image_url">
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Original Price (QR Coins)</label>
                                <input type="number" class="form-control" name="original_price" required min="1">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Sale Price (QR Coins)</label>
                                <input type="number" class="form-control" name="sale_price" required min="1">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Duration (Hours)</label>
                                <select class="form-select" name="duration_hours" required>
                                    <option value="1">1 Hour</option>
                                    <option value="2">2 Hours</option>
                                    <option value="4">4 Hours</option>
                                    <option value="6">6 Hours</option>
                                    <option value="12">12 Hours</option>
                                    <option value="24">24 Hours</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Stock Quantity</label>
                                <input type="number" class="form-control" name="stock_quantity" required min="1" value="50">
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Purchase Limit per User</label>
                        <input type="number" class="form-control" name="purchase_limit_per_user" value="1" min="1">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning text-dark">
                        <i class="bi bi-lightning-charge me-1"></i>Create Flash Sale
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Item Modal -->
<div class="modal fade" id="editItemModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit QR Store Item</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editItemForm">
                <input type="hidden" name="item_id" id="edit_item_id">
                <div class="modal-body" id="editItemContent">
                    <!-- Content will be populated by JavaScript -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Item</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Initialize countdown timers
function initCountdownTimers() {
    document.querySelectorAll('.countdown-timer').forEach(timer => {
        const endTime = parseInt(timer.dataset.endTime) * 1000;
        const display = timer.querySelector('.countdown-display');
        
        function updateTimer() {
            const now = new Date().getTime();
            const distance = endTime - now;
            
            if (distance > 0) {
                const days = Math.floor(distance / (1000 * 60 * 60 * 24));
                const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((distance % (1000 * 60)) / 1000);
                
                let timeString = '';
                if (days > 0) timeString += days + 'd ';
                if (hours > 0) timeString += hours + 'h ';
                if (minutes > 0) timeString += minutes + 'm ';
                timeString += seconds + 's';
                
                display.textContent = timeString;
            } else {
                display.textContent = 'EXPIRED';
                display.className = 'text-danger';
            }
        }
        
        updateTimer();
        setInterval(updateTimer, 1000);
    });
}

// Flash sale checkbox toggle
document.getElementById('flashSaleCheck').addEventListener('change', function() {
    const flashSaleFields = document.getElementById('flashSaleFields');
    flashSaleFields.style.display = this.checked ? 'block' : 'none';
});

// Update item-specific fields based on type
function updateItemFields(itemType) {
    const container = document.getElementById('itemSpecificFields');
    let html = '';
    
    switch (itemType) {
        case 'avatar':
            html = `
                <div class="card mb-3">
                    <div class="card-header"><h6 class="mb-0">Avatar Settings</h6></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Avatar Image URL</label>
                            <input type="url" class="form-control" name="avatar_url" placeholder="Direct link to avatar image">
                        </div>
                    </div>
                </div>
            `;
            break;
            
        case 'spin_pack':
        case 'slot_pack':
            html = `
                <div class="card mb-3">
                    <div class="card-header"><h6 class="mb-0">${itemType === 'spin_pack' ? 'Spin' : 'Slot'} Pack Settings</h6></div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Number of ${itemType === 'spin_pack' ? 'Spins' : 'Slots'}</label>
                                    <input type="number" class="form-control" name="spins" min="1" value="10">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Duration (Days)</label>
                                    <input type="number" class="form-control" name="duration_days" min="1" value="7">
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Pack Type</label>
                            <select class="form-select" name="pack_type">
                                <option value="standard">Standard Pack</option>
                                <option value="premium">Premium Pack</option>
                                <option value="mega">Mega Pack</option>
                            </select>
                        </div>
                    </div>
                </div>
            `;
            break;
            
        case 'boost':
            html = `
                <div class="card mb-3">
                    <div class="card-header"><h6 class="mb-0">Boost Settings</h6></div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Boost Percentage</label>
                                    <input type="number" class="form-control" name="boost_percentage" min="1" max="200" value="25">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Duration (Hours)</label>
                                    <input type="number" class="form-control" name="duration_hours" min="1" value="24">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            break;
            
        case 'analytics':
            html = `
                <div class="card mb-3">
                    <div class="card-header"><h6 class="mb-0">Analytics Settings</h6></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Duration (Days)</label>
                            <input type="number" class="form-control" name="duration_days" min="1" value="30">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Features (comma-separated)</label>
                            <input type="text" class="form-control" name="features" 
                                   placeholder="detailed_stats,comparisons,trends">
                        </div>
                    </div>
                </div>
            `;
            break;
    }
    
    container.innerHTML = html;
}

// Form submissions
document.getElementById('addItemForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'add_item');
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        alert('Error: ' + error.message);
    });
});

document.getElementById('flashSaleForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'create_flash_sale');
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        alert('Error: ' + error.message);
    });
});

document.getElementById('editItemForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'update_item');
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        alert('Error: ' + error.message);
    });
});

// Action functions
function editItem(item) {
    document.getElementById('edit_item_id').value = item.id;
    
    // Populate edit form - this would need the same structure as add form
    // For brevity, showing the concept
    const editContent = document.getElementById('editItemContent');
    editContent.innerHTML = `
        <div class="mb-3">
            <label class="form-label">Item Name</label>
            <input type="text" class="form-control" name="item_name" value="${item.item_name}">
        </div>
        <!-- Add all other fields similar to add form -->
    `;
    
    new bootstrap.Modal(document.getElementById('editItemModal')).show();
}

function toggleItem(itemId) {
    const formData = new FormData();
    formData.append('action', 'toggle_item');
    formData.append('item_id', itemId);
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    });
}

function deleteItem(itemId) {
    if (confirm('Are you sure you want to delete this item?')) {
        const formData = new FormData();
        formData.append('action', 'delete_item');
        formData.append('item_id', itemId);
        
        fetch('', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        });
    }
}

// Initialize timers when page loads
document.addEventListener('DOMContentLoaded', function() {
    initCountdownTimers();
});
</script>

<style>
.countdown-timer {
    font-family: 'Courier New', monospace;
}

.table-warning {
    background-color: rgba(255, 193, 7, 0.1) !important;
}

.flash-sale-badge {
    animation: pulse 1.5s infinite;
}

@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
}
</style>

<?php require_once __DIR__ . '/../core/includes/footer.php'; ?> 