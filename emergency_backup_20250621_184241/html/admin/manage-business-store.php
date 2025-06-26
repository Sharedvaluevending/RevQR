<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/store_manager.php';
require_once __DIR__ . '/../core/business_qr_manager.php';

// Require admin role
require_role('admin');

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'add_business_item':
            $result = addBusinessStoreItem($_POST);
            echo json_encode($result);
            exit;
            
        case 'update_business_item':
            $result = updateBusinessStoreItem($_POST);
            echo json_encode($result);
            exit;
            
        case 'toggle_business_item':
            $result = toggleBusinessStoreItem($_POST['item_id']);
            echo json_encode($result);
            exit;
            
        case 'delete_business_item':
            $result = deleteBusinessStoreItem($_POST['item_id']);
            echo json_encode($result);
            exit;
            
        case 'create_promotional_sale':
            $result = createPromotionalSale($_POST);
            echo json_encode($result);
            exit;
    }
}

function addBusinessStoreItem($data) {
    global $pdo;
    
    try {
        // Handle promotional sale dates
        $valid_from = !empty($data['valid_from']) ? $data['valid_from'] : null;
        $valid_until = !empty($data['valid_until']) ? $data['valid_until'] : null;
        
        // Calculate QR coin cost
        $qr_coin_cost = BusinessQRManager::calculateQRCoinCost(
            $data['regular_price'] / 100,
            $data['discount_percentage'] / 100,
            100
        );
        
        $stmt = $pdo->prepare("
            INSERT INTO business_store_items 
            (business_id, item_name, item_description, regular_price_cents, discount_percentage, 
             qr_coin_cost, category, stock_quantity, max_per_user, is_active, valid_from, 
             valid_until, promotional_sale, original_discount_percentage, promotional_boost)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $is_promotional = !empty($data['promotional_sale']) ? 1 : 0;
        $original_discount = $is_promotional && !empty($data['original_discount_percentage']) ? 
                           (float)$data['original_discount_percentage'] : null;
        $promotional_boost = $is_promotional && !empty($data['promotional_boost']) ? 
                           (float)$data['promotional_boost'] : null;
        
        $success = $stmt->execute([
            (int)$data['business_id'],
            $data['item_name'],
            $data['item_description'] ?? '',
            (int)$data['regular_price'],
            (float)$data['discount_percentage'],
            $qr_coin_cost['qr_coin_cost'],
            $data['category'] ?? 'discount',
            !empty($data['stock_quantity']) ? (int)$data['stock_quantity'] : -1,
            !empty($data['max_per_user']) ? (int)$data['max_per_user'] : 1,
            !empty($data['is_active']) ? 1 : 1,
            $valid_from,
            $valid_until,
            $is_promotional,
            $original_discount,
            $promotional_boost
        ]);
        
        return [
            'success' => $success,
            'message' => $success ? 'Business store item added successfully!' : 'Failed to add item'
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

function updateBusinessStoreItem($data) {
    global $pdo;
    
    try {
        $valid_from = !empty($data['valid_from']) ? $data['valid_from'] : null;
        $valid_until = !empty($data['valid_until']) ? $data['valid_until'] : null;
        
        $qr_coin_cost = BusinessQRManager::calculateQRCoinCost(
            $data['regular_price'] / 100,
            $data['discount_percentage'] / 100,
            100
        );
        
        $stmt = $pdo->prepare("
            UPDATE business_store_items SET
                item_name = ?, item_description = ?, regular_price_cents = ?, 
                discount_percentage = ?, qr_coin_cost = ?, category = ?, 
                stock_quantity = ?, max_per_user = ?, is_active = ?, 
                valid_from = ?, valid_until = ?, promotional_sale = ?, 
                original_discount_percentage = ?, promotional_boost = ?
            WHERE id = ?
        ");
        
        $is_promotional = !empty($data['promotional_sale']) ? 1 : 0;
        $original_discount = $is_promotional && !empty($data['original_discount_percentage']) ? 
                           (float)$data['original_discount_percentage'] : null;
        $promotional_boost = $is_promotional && !empty($data['promotional_boost']) ? 
                           (float)$data['promotional_boost'] : null;
        
        $success = $stmt->execute([
            $data['item_name'],
            $data['item_description'] ?? '',
            (int)$data['regular_price'],
            (float)$data['discount_percentage'],
            $qr_coin_cost['qr_coin_cost'],
            $data['category'] ?? 'discount',
            !empty($data['stock_quantity']) ? (int)$data['stock_quantity'] : -1,
            !empty($data['max_per_user']) ? (int)$data['max_per_user'] : 1,
            !empty($data['is_active']) ? 1 : 0,
            $valid_from,
            $valid_until,
            $is_promotional,
            $original_discount,
            $promotional_boost,
            (int)$data['item_id']
        ]);
        
        return [
            'success' => $success,
            'message' => $success ? 'Business store item updated successfully!' : 'Failed to update item'
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

function toggleBusinessStoreItem($item_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("UPDATE business_store_items SET is_active = NOT is_active WHERE id = ?");
        $success = $stmt->execute([$item_id]);
        
        return [
            'success' => $success,
            'message' => $success ? 'Item status updated!' : 'Failed to update status'
        ];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

function deleteBusinessStoreItem($item_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("DELETE FROM business_store_items WHERE id = ?");
        $success = $stmt->execute([$item_id]);
        
        return [
            'success' => $success,
            'message' => $success ? 'Item deleted successfully!' : 'Failed to delete item'
        ];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

function createPromotionalSale($data) {
    global $pdo;
    
    try {
        $duration_hours = (int)$data['duration_hours'];
        $valid_until = date('Y-m-d H:i:s', strtotime("+{$duration_hours} hours"));
        
        // Enhanced discount for promotional sale
        $enhanced_discount = (float)$data['original_discount'] + (float)$data['promotional_boost'];
        
        $qr_coin_cost = BusinessQRManager::calculateQRCoinCost(
            $data['regular_price'] / 100,
            $enhanced_discount / 100,
            100
        );
        
        $stmt = $pdo->prepare("
            INSERT INTO business_store_items 
            (business_id, item_name, item_description, regular_price_cents, discount_percentage, 
             qr_coin_cost, category, stock_quantity, max_per_user, is_active, valid_from, 
             valid_until, promotional_sale, original_discount_percentage, promotional_boost)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), ?, 1, ?, ?)
        ");
        
        $success = $stmt->execute([
            (int)$data['business_id'],
            $data['item_name'] . ' - PROMO SALE',
            'Limited time promotional sale! ' . ($data['item_description'] ?? ''),
            (int)$data['regular_price'],
            $enhanced_discount,
            $qr_coin_cost['qr_coin_cost'],
            $data['category'] ?? 'discount',
            (int)$data['stock_quantity'],
            !empty($data['max_per_user']) ? (int)$data['max_per_user'] : 1,
            $valid_until,
            (float)$data['original_discount'],
            (float)$data['promotional_boost']
        ]);
        
        return [
            'success' => $success,
            'message' => $success ? 'Promotional sale created successfully!' : 'Failed to create promotional sale'
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

// Add new columns if they don't exist
try {
    $pdo->exec("ALTER TABLE business_store_items ADD COLUMN IF NOT EXISTS promotional_sale BOOLEAN DEFAULT FALSE");
    $pdo->exec("ALTER TABLE business_store_items ADD COLUMN IF NOT EXISTS original_discount_percentage DECIMAL(5,2) NULL");
    $pdo->exec("ALTER TABLE business_store_items ADD COLUMN IF NOT EXISTS promotional_boost DECIMAL(5,2) NULL");
} catch (Exception $e) {
    // Columns may already exist
}

// Get all businesses for dropdown
$stmt = $pdo->prepare("SELECT id, name FROM businesses ORDER BY name");
$stmt->execute();
$businesses = $stmt->fetchAll();

// Get all business store items with enhanced data
$stmt = $pdo->prepare("
    SELECT 
        bsi.*,
        b.name as business_name,
        CASE 
            WHEN bsi.valid_until IS NOT NULL AND bsi.valid_until > NOW() 
            THEN TIMESTAMPDIFF(SECOND, NOW(), bsi.valid_until)
            ELSE 0 
        END as seconds_remaining,
        (SELECT COUNT(*) FROM user_store_purchases WHERE store_item_id = bsi.id) as total_purchased
    FROM business_store_items bsi 
    JOIN businesses b ON bsi.business_id = b.id
    ORDER BY bsi.promotional_sale DESC, bsi.created_at DESC
");
$stmt->execute();
$business_store_items = $stmt->fetchAll();

$page_title = "Business Store Management";
require_once __DIR__ . '/../core/includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1><i class="bi bi-building me-2"></i><?php echo $page_title; ?></h1>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#promotionalSaleModal">
                        <i class="bi bi-megaphone me-2"></i>Create Promotional Sale
                    </button>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addBusinessItemModal">
                        <i class="bi bi-plus-circle me-2"></i>Add Business Item
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
                    <h3><?php echo count($business_store_items); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body text-center">
                    <h5 class="card-title">Active Items</h5>
                    <h3><?php echo count(array_filter($business_store_items, fn($item) => $item['is_active'])); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body text-center">
                    <h5 class="card-title">Promotional Sales</h5>
                    <h3><?php echo count(array_filter($business_store_items, fn($item) => $item['promotional_sale'])); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body text-center">
                    <h5 class="card-title">Total Purchases</h5>
                    <h3><?php echo array_sum(array_column($business_store_items, 'total_purchased')); ?></h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Business Store Items Table -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0"><i class="bi bi-list me-2"></i>Business Store Items</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Business</th>
                            <th>Item</th>
                            <th>Price & Discount</th>
                            <th>QR Cost</th>
                            <th>Category</th>
                            <th>Stock</th>
                            <th>Status</th>
                            <th>Sales</th>
                            <th>Timer</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($business_store_items as $item): ?>
                            <tr class="<?php echo $item['promotional_sale'] ? 'table-warning' : ''; ?>">
                                <td>
                                    <strong><?php echo htmlspecialchars($item['business_name']); ?></strong>
                                </td>
                                <td>
                                    <div>
                                        <strong><?php echo htmlspecialchars($item['item_name']); ?></strong>
                                        <?php if ($item['promotional_sale']): ?>
                                            <span class="badge bg-warning text-dark ms-1">PROMO</span>
                                        <?php endif; ?>
                                        <br>
                                        <small class="text-muted"><?php echo htmlspecialchars(substr($item['item_description'], 0, 50)); ?></small>
                                    </div>
                                </td>
                                <td>
                                    <div>
                                        <small class="text-muted">Regular: $<?php echo number_format($item['regular_price_cents'] / 100, 2); ?></small><br>
                                        <?php if ($item['promotional_sale'] && $item['original_discount_percentage']): ?>
                                            <del class="text-muted"><?php echo $item['original_discount_percentage']; ?>% off</del><br>
                                            <strong class="text-success"><?php echo $item['discount_percentage']; ?>% off</strong>
                                            <?php if ($item['promotional_boost']): ?>
                                                <span class="badge bg-success">+<?php echo $item['promotional_boost']; ?>%</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <strong class="text-success"><?php echo $item['discount_percentage']; ?>% off</strong>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <strong><?php echo number_format($item['qr_coin_cost']); ?> QR</strong>
                                </td>
                                <td>
                                    <span class="badge bg-secondary">
                                        <?php echo ucfirst($item['category']); ?>
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
                                            <small class="text-<?php echo $item['promotional_sale'] ? 'warning' : 'info'; ?>">
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
                                                onclick="editBusinessItem(<?php echo htmlspecialchars(json_encode($item)); ?>)">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-<?php echo $item['is_active'] ? 'warning' : 'success'; ?>"
                                                onclick="toggleBusinessItem(<?php echo $item['id']; ?>)">
                                            <i class="bi bi-<?php echo $item['is_active'] ? 'pause' : 'play'; ?>"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-danger"
                                                onclick="deleteBusinessItem(<?php echo $item['id']; ?>)">
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

<!-- Add Business Item Modal -->
<div class="modal fade" id="addBusinessItemModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Business Store Item</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addBusinessItemForm">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Business</label>
                                <select class="form-select" name="business_id" required>
                                    <option value="">Select Business</option>
                                    <?php foreach ($businesses as $business): ?>
                                        <option value="<?php echo $business['id']; ?>">
                                            <?php echo htmlspecialchars($business['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Category</label>
                                <select class="form-select" name="category">
                                    <option value="discount">Discount</option>
                                    <option value="food">Food</option>
                                    <option value="beverage">Beverage</option>
                                    <option value="snack">Snack</option>
                                    <option value="combo">Combo</option>
                                    <option value="other">Other</option>
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

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Regular Price (USD)</label>
                                <input type="number" class="form-control" name="regular_price" step="0.01" required min="0.01">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Discount Percentage</label>
                                <input type="number" class="form-control" name="discount_percentage" step="0.01" required min="1" max="50">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Stock Quantity (-1 = unlimited)</label>
                                <input type="number" class="form-control" name="stock_quantity" value="-1">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Max per User</label>
                                <input type="number" class="form-control" name="max_per_user" value="1" min="1">
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
                                <input class="form-check-input" type="checkbox" name="promotional_sale" id="promotionalSaleCheck">
                                <label class="form-check-label" for="promotionalSaleCheck">
                                    Promotional Sale Item
                                </label>
                            </div>

                            <div id="promotionalSaleFields" class="mt-3" style="display: none;">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Original Discount %</label>
                                            <input type="number" class="form-control" name="original_discount_percentage" step="0.01">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Promotional Boost %</label>
                                            <input type="number" class="form-control" name="promotional_boost" step="0.01">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_active" checked>
                        <label class="form-check-label">Active</label>
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

<!-- Promotional Sale Modal -->
<div class="modal fade" id="promotionalSaleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title text-dark">
                    <i class="bi bi-megaphone me-2"></i>Create Promotional Sale
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="promotionalSaleForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Business</label>
                        <select class="form-select" name="business_id" required>
                            <option value="">Select Business</option>
                            <?php foreach ($businesses as $business): ?>
                                <option value="<?php echo $business['id']; ?>">
                                    <?php echo htmlspecialchars($business['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Promotional Sale Name</label>
                        <input type="text" class="form-control" name="item_name" required 
                               placeholder="e.g., Weekend Special Discount">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="item_description" rows="2"
                                  placeholder="Special promotional sale description..."></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Regular Price (USD)</label>
                                <input type="number" class="form-control" name="regular_price" step="0.01" required min="0.01">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Category</label>
                                <select class="form-select" name="category">
                                    <option value="discount">Discount</option>
                                    <option value="food">Food</option>
                                    <option value="beverage">Beverage</option>
                                    <option value="combo">Combo</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Original Discount %</label>
                                <input type="number" class="form-control" name="original_discount" step="0.01" required min="1" max="30">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Promotional Boost %</label>
                                <input type="number" class="form-control" name="promotional_boost" step="0.01" required min="1" max="20">
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
                                    <option value="48">48 Hours</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Stock Quantity</label>
                                <input type="number" class="form-control" name="stock_quantity" required min="1" value="25">
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Max Purchases per User</label>
                        <input type="number" class="form-control" name="max_per_user" value="1" min="1">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning text-dark">
                        <i class="bi bi-megaphone me-1"></i>Create Promotional Sale
                    </button>
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

// Promotional sale checkbox toggle
document.getElementById('promotionalSaleCheck').addEventListener('change', function() {
    const promotionalSaleFields = document.getElementById('promotionalSaleFields');
    promotionalSaleFields.style.display = this.checked ? 'block' : 'none';
});

// Form submissions
document.getElementById('addBusinessItemForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'add_business_item');
    
    // Convert dollar amount to cents
    const price = parseFloat(formData.get('regular_price')) * 100;
    formData.set('regular_price', price);
    
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

document.getElementById('promotionalSaleForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'create_promotional_sale');
    
    // Convert dollar amount to cents
    const price = parseFloat(formData.get('regular_price')) * 100;
    formData.set('regular_price', price);
    
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
function editBusinessItem(item) {
    // Implementation for edit modal would go here
    alert('Edit functionality - to be implemented');
}

function toggleBusinessItem(itemId) {
    const formData = new FormData();
    formData.append('action', 'toggle_business_item');
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

function deleteBusinessItem(itemId) {
    if (confirm('Are you sure you want to delete this business item?')) {
        const formData = new FormData();
        formData.append('action', 'delete_business_item');
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

.promotional-sale-badge {
    animation: pulse 1.5s infinite;
}

@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
}
</style>

<?php require_once __DIR__ . '/../core/includes/footer.php'; ?> 