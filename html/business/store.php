<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/functions.php';
require_once __DIR__ . '/../core/config_manager.php';
require_once __DIR__ . '/../core/business_qr_manager.php';
require_once __DIR__ . '/../core/store_manager.php';

// Require business role
require_role('business');

$business_id = $_SESSION['business_id'];
$user_id = $_SESSION['user_id'];

// Get business subscription
$subscription = BusinessQRManager::getSubscription($business_id);
if (!$subscription) {
    die("No active subscription found. Please contact support.");
}

// Handle AJAX requests
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'add_item':
            $result = addStoreItem($_POST);
            echo json_encode($result);
            exit;
            
        case 'toggle_item':
            $result = toggleStoreItem($_POST['item_id']);
            echo json_encode($result);
            exit;
            
        case 'redeem_code':
            $result = redeemBusinessPurchaseCode($_POST['purchase_code'], $user_id);
            echo json_encode($result);
            exit;
            
        case 'delete_item':
            $result = deleteStoreItem($_POST['item_id']);
            echo json_encode($result);
            exit;
            
        case 'calculate_qr_cost':
            $price = (float) $_POST['price'];
            $discount = (float) $_POST['discount'] / 100;
            $calculation = BusinessQRManager::calculateQRCoinCost($price, $discount, 100);
            echo json_encode([
                'success' => true,
                'qr_coin_cost' => $calculation['qr_coin_cost'],
                'discount_amount_usd' => $calculation['discount_amount_usd'],
                'coin_value_usd' => $calculation['coin_value_usd'],
                'breakdown' => $calculation['breakdown']
            ]);
            exit;
    }
}

function addStoreItem($data) {
    global $pdo, $business_id;
    
    try {
        $qr_coin_cost = BusinessQRManager::calculateQRCoinCost(
            $data['regular_price'] / 100,
            $data['discount_percentage'] / 100,
            100 // Default user base size
        );
        
        $stmt = $pdo->prepare("
            INSERT INTO business_store_items 
            (business_id, item_name, item_description, regular_price_cents, discount_percentage, qr_coin_cost, category, max_per_user)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $success = $stmt->execute([
            $business_id,
            $data['item_name'],
            $data['item_description'],
            (int) $data['regular_price'],
            (float) $data['discount_percentage'],
            $qr_coin_cost['qr_coin_cost'],
            $data['category'],
            (int) $data['max_per_user']
        ]);
        
        return [
            'success' => $success,
            'message' => $success ? 'Item added successfully!' : 'Failed to add item',
            'qr_coin_cost' => $qr_coin_cost['qr_coin_cost']
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

function toggleStoreItem($item_id) {
    global $pdo, $business_id;
    
    try {
        $stmt = $pdo->prepare("
            UPDATE business_store_items 
            SET is_active = NOT is_active 
            WHERE id = ? AND business_id = ?
        ");
        $success = $stmt->execute([$item_id, $business_id]);
        
        return [
            'success' => $success,
            'message' => $success ? 'Item status updated' : 'Failed to update item'
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

function deleteStoreItem($item_id) {
    global $pdo, $business_id;
    
    try {
        // First check if item has any purchases
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM business_purchases 
            WHERE business_store_item_id = ?
        ");
        $stmt->execute([$item_id]);
        $purchase_count = $stmt->fetchColumn();
        
        if ($purchase_count > 0) {
            return [
                'success' => false, 
                'message' => "Cannot delete item with {$purchase_count} existing purchases. Disable it instead."
            ];
        }
        
        // Delete the item
        $stmt = $pdo->prepare("
            DELETE FROM business_store_items 
            WHERE id = ? AND business_id = ?
        ");
        $success = $stmt->execute([$item_id, $business_id]);
        
        return [
            'success' => $success,
            'message' => $success ? 'Item deleted successfully' : 'Failed to delete item'
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

function redeemBusinessPurchaseCode($purchase_code, $business_user_id) {
    global $pdo, $business_id;
    
    if (!$purchase_code || !$business_user_id) {
        return ['success' => false, 'message' => 'Invalid parameters'];
    }
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("
            SELECT 
                bp.*,
                bsi.item_name,
                bp.discount_percentage,
                u.username
            FROM business_purchases bp
            JOIN business_store_items bsi ON bp.business_store_item_id = bsi.id
            JOIN users u ON bp.user_id = u.id
            WHERE bp.purchase_code = ? AND bp.status = 'pending' AND bp.business_id = ?
        ");
        $stmt->execute([$purchase_code, $business_id]);
        $purchase = $stmt->fetch();
        
        if (!$purchase) {
            $pdo->rollback();
            return ['success' => false, 'message' => 'Invalid code or already used'];
        }
        
        // Update purchase status
        $stmt = $pdo->prepare("
            UPDATE business_purchases 
            SET status = 'redeemed', redeemed_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$purchase['id']]);
        
        $pdo->commit();
        
        return [
            'success' => true,
            'message' => 'Code redeemed successfully!',
            'purchase' => [
                'item_name' => $purchase['item_name'],
                'discount_percentage' => $purchase['discount_percentage'],
                'username' => $purchase['username'],
                'qr_coins_spent' => $purchase['qr_coins_spent']
            ]
        ];
        
    } catch (Exception $e) {
        $pdo->rollback();
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

// Get business wallet balance
$business_wallet_balance = 0;
try {
    $stmt = $pdo->prepare("SELECT qr_coin_balance FROM business_wallets WHERE business_id = ?");
    $stmt->execute([$business_id]);
    $wallet = $stmt->fetch();
    $business_wallet_balance = $wallet['qr_coin_balance'] ?? 0;
} catch (Exception $e) {
    $business_wallet_balance = 0;
}

// Get store items
$store_items = StoreManager::getBusinessStoreItems($business_id, false);

// Get recent sales
try {
    $stmt = $pdo->prepare("
        SELECT 
            bp.*,
            bsi.item_name,
            u.username,
            bp.discount_percentage
        FROM business_purchases bp
        JOIN business_store_items bsi ON bp.business_store_item_id = bsi.id
        JOIN users u ON bp.user_id = u.id
        WHERE bp.business_id = ?
        ORDER BY bp.created_at DESC
        LIMIT 20
    ");
    $stmt->execute([$business_id]);
    $recent_sales = $stmt->fetchAll();
} catch (Exception $e) {
    $recent_sales = [];
}

// Calculate pricing for different discounts
$pricing_calculator = [
    5 => BusinessQRManager::calculateQRCoinCost(5.00, 0.05, 100),
    10 => BusinessQRManager::calculateQRCoinCost(5.00, 0.10, 100),
    15 => BusinessQRManager::calculateQRCoinCost(5.00, 0.15, 100),
    20 => BusinessQRManager::calculateQRCoinCost(5.00, 0.20, 100)
];

$page_title = "Business Store Management";

// Include header first (handles all HTML structure)
require_once __DIR__ . '/../core/includes/header.php';
?>

<style>
        /* Dark theme styling to match platform */
        html, body {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 25%, #34495e 75%, #2c3e50 100%) !important;
            background-attachment: fixed !important;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
            min-height: 100vh !important;
        }
        
        /* Glass morphism cards */
        .card {
            background: rgba(255, 255, 255, 0.12) !important;
            backdrop-filter: blur(20px) !important;
            border: 1px solid rgba(255, 255, 255, 0.15) !important;
            border-radius: 16px !important;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3) !important;
            transition: all 0.3s ease !important;
            color: #ffffff !important;
        }
        
        .card:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.4) !important;
            border: 1px solid rgba(255, 255, 255, 0.25) !important;
        }
        
        .card-header {
            background: rgba(30, 60, 114, 0.3) !important;
            border-bottom: 1px solid rgba(255, 255, 255, 0.15) !important;
            color: #ffffff !important;
        }
        
        .card-body {
            color: #ffffff !important;
        }
        
        .card-title, .card-header h5 {
            color: #ffffff !important;
        }
        
        /* Store item cards with enhanced styling */
        .store-item-card {
            transition: all 0.3s ease;
            border-left: 4px solid rgba(255, 255, 255, 0.3);
            background: rgba(255, 255, 255, 0.08) !important;
            backdrop-filter: blur(15px) !important;
        }
        .store-item-card.active {
            border-left-color: #4caf50;
        }
        .store-item-card.inactive {
            border-left-color: #f44336;
            opacity: 0.7;
        }
        
        /* QR coin badge with better contrast */
        .qr-coin-badge {
            background: linear-gradient(45deg, #ffd700, #ffed4e) !important;
            color: #000 !important;
            font-weight: bold !important;
        }
        
        /* Rarity colors adjusted for dark theme */
        .rarity-legendary { border-left-color: #ff6b35 !important; }
        .rarity-epic { border-left-color: #9b59b6 !important; }
        .rarity-rare { border-left-color: #64b5f6 !important; }
        .rarity-common { border-left-color: rgba(255, 255, 255, 0.5) !important; }
        
        .calculator-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
            color: white !important;
        }
        
        /* Text colors for dark theme */
        .text-muted {
            color: rgba(255, 255, 255, 0.75) !important;
        }
        
        .text-success {
            color: #4caf50 !important;
        }
        
        .text-warning {
            color: #ff9800 !important;
        }
        
        h1, h2, h3, h4, h5, h6 {
            color: #ffffff !important;
        }
        
        /* Form controls */
        .form-control, .form-select {
            background: rgba(255, 255, 255, 0.1) !important;
            border: 1px solid rgba(255, 255, 255, 0.2) !important;
            color: #ffffff !important;
        }
        
        .form-control:focus, .form-select:focus {
            background: rgba(255, 255, 255, 0.15) !important;
            border-color: #64b5f6 !important;
            box-shadow: 0 0 0 0.25rem rgba(100, 181, 246, 0.25) !important;
            color: #ffffff !important;
        }
        
        .form-control::placeholder {
            color: rgba(255, 255, 255, 0.5) !important;
        }
        
        .form-label {
            color: #ffffff !important;
        }
        
        /* Modal styling */
        .modal-content {
            background: rgba(30, 60, 114, 0.95) !important;
            backdrop-filter: blur(20px) !important;
            border: 1px solid rgba(255, 255, 255, 0.15) !important;
            border-radius: 16px !important;
            color: #ffffff !important;
        }
        
        .modal-header {
            border-bottom: 1px solid rgba(255, 255, 255, 0.15) !important;
        }
        
        .modal-footer {
            border-top: 1px solid rgba(255, 255, 255, 0.15) !important;
        }
        
        /* Badges */
        .badge.bg-success { background: #2e7d32 !important; color: #ffffff !important; }
        .badge.bg-warning { background: #f57c00 !important; color: #ffffff !important; }
        .badge.bg-info { background: #0288d1 !important; color: #ffffff !important; }
        .badge.bg-secondary { background: #424242 !important; color: #ffffff !important; }
        
        /* List group items */
        .list-group-item {
            background: rgba(255, 255, 255, 0.05) !important;
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
            color: #ffffff !important;
        }
        
        /* Alerts */
        .alert-info {
            background: rgba(33, 150, 243, 0.15) !important;
            border: 1px solid rgba(33, 150, 243, 0.3) !important;
            color: #64b5f6 !important;
        }
        
        .alert-success {
            background: rgba(76, 175, 80, 0.15) !important;
            border: 1px solid rgba(76, 175, 80, 0.3) !important;
            color: #4caf50 !important;
        }
        
                 .alert-danger {
             background: rgba(244, 67, 54, 0.15) !important;
             border: 1px solid rgba(244, 67, 54, 0.3) !important;
             color: #f44336 !important;
         }
         
         /* Add padding for fixed navbar */
         body {
             padding-top: 70px !important;
         }
     </style>

     <div class="container-fluid py-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1><i class="bi bi-shop me-2"></i><?php echo $page_title; ?></h1>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addItemModal">
                        <i class="bi bi-plus-circle me-2"></i>Add Store Item
                    </button>
                </div>
            </div>
        </div>

        <!-- Store Status & Quick Stats -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h5 class="card-title">Store Status</h5>
                        <span class="badge bg-<?php echo ConfigManager::get('business_store_enabled') ? 'success' : 'warning'; ?> fs-6">
                            <?php echo ConfigManager::get('business_store_enabled') ? 'Active' : 'Coming Soon'; ?>
                        </span>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h5 class="card-title">Total Items</h5>
                        <h3 class="text-primary"><?php echo count($store_items); ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h5 class="card-title">Total Sales</h5>
                        <h3 class="text-success"><?php echo count($recent_sales); ?></h3>
                        <small class="text-muted">All-time purchases</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h5 class="card-title">QR Wallet</h5>
                        <h3 class="text-warning"><?php echo number_format($business_wallet_balance); ?></h3>
                        <small class="text-muted">QR Coins earned</small>
                        <br>
                        <a href="wallet.php" class="btn btn-sm btn-outline-warning mt-2">
                            <i class="bi bi-wallet2 me-1"></i>View Wallet
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- QR Coin Pricing Calculator -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card calculator-card">
                    <div class="card-header">
                        <h5 class="mb-0 text-white"><i class="bi bi-calculator me-2"></i>QR Coin Pricing Calculator</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-white mb-3">Recommended QR coin costs for $5.00 items at different discount levels:</p>
                        <div class="row">
                            <?php foreach ($pricing_calculator as $discount => $calc): ?>
                            <div class="col-md-3">
                                <div class="card bg-white text-dark">
                                    <div class="card-body text-center">
                                        <h6><?php echo $discount; ?>% Discount</h6>
                                        <span class="badge qr-coin-badge"><?php echo number_format($calc['qr_coin_cost']); ?> QR Coins</span>
                                        <small class="d-block mt-1">$<?php echo number_format($calc['discount_amount_usd'], 2); ?> value</small>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Store Items Management -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-grid me-2"></i>Store Items</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($store_items)): ?>
                            <div class="text-center py-5">
                                <i class="bi bi-shop display-1 text-muted"></i>
                                <h5 class="text-muted mt-3">No Store Items Yet</h5>
                                <p class="text-muted">Add your first discount item to get started!</p>
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addItemModal">
                                    Add First Item
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($store_items as $item): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="card store-item-card <?php echo $item['is_active'] ? 'active' : 'inactive'; ?>">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <h6 class="card-title mb-0"><?php echo htmlspecialchars($item['item_name'] ?? ''); ?></h6>
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="form-check form-switch">
                                                        <input class="form-check-input" type="checkbox" 
                                                               <?php echo $item['is_active'] ? 'checked' : ''; ?>
                                                               onchange="toggleItem(<?php echo $item['id']; ?>)">
                                                    </div>
                                                    <button type="button" class="btn btn-sm btn-outline-danger" 
                                                            onclick="deleteItem(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars($item['item_name'] ?? ''); ?>')"
                                                            title="Delete Item">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                            <p class="card-text small text-muted"><?php echo htmlspecialchars($item['item_description'] ?? ''); ?></p>
                                            <div class="row small">
                                                <div class="col-6">
                                                    <strong><?php echo $item['discount_percentage']; ?>% Off</strong><br>
                                                    <span class="text-muted">$<?php echo number_format($item['regular_price_cents'] / 100, 2); ?> items</span>
                                                </div>
                                                <div class="col-6 text-end">
                                                    <span class="badge qr-coin-badge"><?php echo number_format($item['qr_coin_cost']); ?> QR Coins</span><br>
                                                    <small class="text-muted">Max: <?php echo $item['max_per_user']; ?> per user</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Recent Sales & Code Redemption -->
            <div class="col-lg-4">
                <!-- Code Redemption -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-qr-code me-2"></i>Redeem Purchase Code</h5>
                    </div>
                    <div class="card-body">
                        <form id="redeemForm">
                            <div class="mb-3">
                                <label for="purchaseCode" class="form-label">Purchase Code</label>
                                <input type="text" class="form-control text-center" id="purchaseCode" 
                                       placeholder="Enter 8-digit code" maxlength="8" style="font-family: monospace; font-size: 1.2rem;">
                            </div>
                            <button type="submit" class="btn btn-success w-100">
                                <i class="bi bi-check-circle me-2"></i>Redeem Code
                            </button>
                        </form>
                        <div id="redeemResult" class="mt-3"></div>
                    </div>
                </div>

                <!-- Recent Sales -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>Recent Sales</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recent_sales)): ?>
                            <p class="text-muted text-center">No sales yet</p>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach (array_slice($recent_sales, 0, 10) as $sale): ?>
                                <div class="list-group-item px-0">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($sale['item_name'] ?? ''); ?></h6>
                                            <small class="text-muted">@<?php echo htmlspecialchars($sale['username'] ?? ''); ?></small>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge bg-<?php echo $sale['status'] === 'redeemed' ? 'success' : 'warning'; ?>">
                                                <?php echo ucfirst($sale['status']); ?>
                                            </span>
                                            <small class="d-block text-muted"><?php echo date('M j', strtotime($sale['created_at'])); ?></small>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Item Modal -->
    <div class="modal fade" id="addItemModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Store Item</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="addItemForm">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="itemName" class="form-label">Item Name</label>
                            <input type="text" class="form-control" id="itemName" name="item_name" required>
                        </div>
                        <div class="mb-3">
                            <label for="itemDescription" class="form-label">Description</label>
                            <textarea class="form-control" id="itemDescription" name="item_description" rows="2"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="regularPrice" class="form-label">Regular Price ($)</label>
                                    <input type="number" class="form-control" id="regularPrice" name="regular_price" step="0.01" value="5.00" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="discountPercentage" class="form-label">Discount (%)</label>
                                    <input type="number" class="form-control" id="discountPercentage" name="discount_percentage" min="1" max="20" value="5" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="category" class="form-label">Category</label>
                                    <select class="form-select" id="category" name="category">
                                        <option value="discount">Discount</option>
                                        <option value="food">Food</option>
                                        <option value="beverage">Beverage</option>
                                        <option value="snack">Snack</option>
                                        <option value="combo">Combo</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="maxPerUser" class="form-label">Max Per User</label>
                                    <input type="number" class="form-control" id="maxPerUser" name="max_per_user" min="1" value="1" required>
                                </div>
                            </div>
                        </div>
                        <!-- Real-time QR Cost Calculator -->
                        <div class="alert alert-info" id="qrCostCalculator">
                            <div class="row align-items-center">
                                <div class="col-8">
                                    <small><strong>QR Coin Cost:</strong> <span id="calculatedQRCost">500</span> coins</small><br>
                                    <small><strong>Real Dollar Discount:</strong> $<span id="calculatedDiscount">0.25</span></small><br>
                                    <small class="text-muted">Rate: 0.001 USD per QR coin</small>
                                </div>
                                <div class="col-4 text-end">
                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="showCalculationBreakdown()">
                                        <i class="bi bi-info-circle"></i> Details
                                    </button>
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

    <script>
        // Add item form
        document.getElementById('addItemForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'add_item');
            
            // Convert dollar amount to cents
            const price = parseFloat(formData.get('regular_price')) * 100;
            formData.set('regular_price', price);
            
            fetch('store.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message + ' QR Coin Cost: ' + data.qr_coin_cost);
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                alert('Error: ' + error.message);
            });
        });

        // Toggle item status
        function toggleItem(itemId) {
            const formData = new FormData();
            formData.append('action', 'toggle_item');
            formData.append('item_id', itemId);
            
            fetch('store.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    alert('Error: ' + data.message);
                    location.reload();
                }
            });
        }

        // Redeem purchase code
        document.getElementById('redeemForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const code = document.getElementById('purchaseCode').value.toUpperCase();
            if (code.length !== 8) {
                alert('Please enter a valid 8-character code');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'redeem_code');
            formData.append('purchase_code', code);
            
            fetch('store.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                const resultDiv = document.getElementById('redeemResult');
                if (data.success) {
                    resultDiv.innerHTML = `
                        <div class="alert alert-success">
                            <strong>Code Redeemed!</strong><br>
                            Customer: @${data.customer_username}<br>
                            Item: ${data.item_name}<br>
                            Discount: $${data.discount_amount.toFixed(2)} (${data.discount_percentage}%)
                        </div>
                    `;
                    document.getElementById('purchaseCode').value = '';
                } else {
                    resultDiv.innerHTML = `<div class="alert alert-danger">${data.message}</div>`;
                }
            })
            .catch(error => {
                document.getElementById('redeemResult').innerHTML = `<div class="alert alert-danger">Error: ${error.message}</div>`;
            });
        });

        // Auto-uppercase purchase code input
        document.getElementById('purchaseCode').addEventListener('input', function(e) {
            e.target.value = e.target.value.toUpperCase();
        });

        // Delete item function
        function deleteItem(itemId, itemName) {
            if (!confirm(`Are you sure you want to delete "${itemName}"?\n\nThis action cannot be undone. If this item has existing purchases, you should disable it instead.`)) {
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'delete_item');
            formData.append('item_id', itemId);
            
            fetch('store.php', {
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
        }

        // Real-time QR cost calculation
        function calculateQRCost() {
            const price = parseFloat(document.getElementById('regularPrice').value) || 0;
            const discount = parseFloat(document.getElementById('discountPercentage').value) || 0;
            
            if (price <= 0 || discount <= 0) {
                document.getElementById('calculatedQRCost').textContent = '0';
                document.getElementById('calculatedDiscount').textContent = '0.00';
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'calculate_qr_cost');
            formData.append('price', price);
            formData.append('discount', discount);
            
            fetch('store.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('calculatedQRCost').textContent = data.qr_coin_cost.toLocaleString();
                    document.getElementById('calculatedDiscount').textContent = data.discount_amount_usd.toFixed(2);
                    
                    // Store breakdown data for details popup
                    window.currentCalculation = data;
                }
            })
            .catch(error => {
                console.error('Calculation error:', error);
            });
        }

        // Show calculation breakdown
        function showCalculationBreakdown() {
            if (!window.currentCalculation) {
                alert('No calculation data available');
                return;
            }
            
            const calc = window.currentCalculation;
            const breakdown = calc.breakdown;
            
            const message = `QR Coin Cost Breakdown:

Base Cost: ${breakdown.base_cost} coins
Demand Adjustment: ${breakdown.demand_adjustment} coins  
Scarcity Adjustment: ${breakdown.scarcity_adjustment} coins
─────────────────────
Total QR Coins: ${calc.qr_coin_cost} coins

Conversion Details:
• Coin Value: $${calc.coin_value_usd.toFixed(3)} USD per coin
• Total Discount Value: $${calc.discount_amount_usd.toFixed(2)}
• Cost to Business: ${calc.qr_coin_cost} × $0.001 = $${(calc.qr_coin_cost * 0.001).toFixed(2)}

This ensures fair pricing based on demand and scarcity factors.`;
            
            alert(message);
        }

        // Add real-time calculation listeners
        document.getElementById('regularPrice').addEventListener('input', calculateQRCost);
        document.getElementById('discountPercentage').addEventListener('input', calculateQRCost);
        
        // Calculate initial values when modal opens
        document.getElementById('addItemModal').addEventListener('shown.bs.modal', function() {
            calculateQRCost();
        });
    </script>

<?php require_once __DIR__ . '/../core/includes/footer.php'; ?> 