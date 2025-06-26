<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/functions.php';

// Ensure business access
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'business') {
    header('Location: /login.php');
    exit;
}

// Get business details
$stmt = $pdo->prepare("
    SELECT b.*, u.username 
    FROM businesses b 
    JOIN users u ON b.id = u.business_id 
    WHERE u.id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$business = $stmt->fetch();

if (!$business) {
    die('Business not found.');
}

$business_id = $business['id'];
$message = '';
$message_type = '';

// Generate random promo code
function generatePromoCode($length = 8) {
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= $chars[rand(0, strlen($chars) - 1)];
    }
    return $code;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create_promotion':
            $list_id = $_POST['list_id'] ?? null;
            $item_ids = $_POST['item_ids'] ?? []; // Changed to support multiple items
            $discount_type = $_POST['discount_type'] ?? null;
            $discount_value = $_POST['discount_value'] ?? null;
            $start_date = $_POST['start_date'] ?? null;
            $end_date = $_POST['end_date'] ?? null;
            $description = $_POST['description'] ?? '';
            $manual_pricing = isset($_POST['manual_pricing']) ? 1 : 0;
            $display_message = $_POST['display_message'] ?? '';
            $promo_code = generatePromoCode();
            
            // Support both single item (backward compatibility) and multiple items
            if (isset($_POST['item_id']) && !empty($_POST['item_id'])) {
                $item_ids = [$_POST['item_id']];
            }
            
            if ($list_id && !empty($item_ids) && $discount_type && $discount_value && $start_date && $end_date) {
                try {
                    $pdo->beginTransaction();
                    
                    // Create promotion for each selected item
                    $stmt = $pdo->prepare("
                        INSERT INTO promotions (
                            business_id, list_id, item_id, discount_type, discount_value,
                            start_date, end_date, promo_code, description, status, manual_pricing, display_message
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, ?)
                    ");
                    
                    $success_count = 0;
                    foreach ($item_ids as $item_id) {
                        if ($stmt->execute([$business_id, $list_id, $item_id, $discount_type, $discount_value, $start_date, $end_date, $promo_code, $description, $manual_pricing, $display_message])) {
                            $success_count++;
                        }
                    }
                    
                    $pdo->commit();
                    
                    if ($success_count > 0) {
                        $message = "Promotion created successfully for {$success_count} item(s)! Code: $promo_code";
                        $message_type = "success";
                    } else {
                        $message = "Error creating promotion.";
                        $message_type = "danger";
                    }
                } catch (Exception $e) {
                    $pdo->rollback();
                    $message = "Error creating promotion: " . $e->getMessage();
                    $message_type = "danger";
                }
            } else {
                $message = "Please fill in all required fields.";
                $message_type = "warning";
            }
            break;
            
        case 'update_promotion':
            $promo_id = $_POST['promo_id'] ?? null;
            $status = $_POST['status'] ?? null;
            
            if ($promo_id && $status) {
                $stmt = $pdo->prepare("
                    UPDATE promotions 
                    SET status = ? 
                    WHERE id = ? AND business_id = ?
                ");
                
                if ($stmt->execute([$status, $promo_id, $business_id])) {
                    $message = "Promotion updated successfully!";
                    $message_type = "success";
                } else {
                    $message = "Error updating promotion.";
                    $message_type = "danger";
                }
            }
            break;
            
        case 'delete_promotion':
            $promo_id = $_POST['promo_id'] ?? null;
            
            if ($promo_id) {
                try {
                    // First check if promotion exists and belongs to this business
                    $stmt = $pdo->prepare("
                        SELECT id, promo_code FROM promotions 
                        WHERE id = ? AND business_id = ?
                    ");
                    $stmt->execute([$promo_id, $business_id]);
                    $promotion = $stmt->fetch();
                    
                    if ($promotion) {
                        // Delete the promotion
                        $stmt = $pdo->prepare("
                            DELETE FROM promotions 
                            WHERE id = ? AND business_id = ?
                        ");
                        
                        if ($stmt->execute([$promo_id, $business_id])) {
                            $message = "Promotion '{$promotion['promo_code']}' deleted successfully!";
                            $message_type = "success";
                        } else {
                            $message = "Error deleting promotion.";
                            $message_type = "danger";
                        }
                    } else {
                        $message = "Promotion not found.";
                        $message_type = "warning";
                    }
                } catch (Exception $e) {
                    $message = "Error deleting promotion: " . $e->getMessage();
                    $message_type = "danger";
                }
            }
            break;
            
        case 'create_bulk_promotions':
            $bulk_list_id = $_POST['bulk_list_id'] ?? null;
            $bulk_promotions = $_POST['bulk_promotions'] ?? [];
            $bulk_start_date = $_POST['bulk_start_date'] ?? null;
            $bulk_end_date = $_POST['bulk_end_date'] ?? null;
            
            if ($bulk_list_id && !empty($bulk_promotions) && $bulk_start_date && $bulk_end_date) {
                try {
                    $pdo->beginTransaction();
                    
                    $total_created = 0;
                    $promotion_codes = [];
                    
                    foreach ($bulk_promotions as $promo_data) {
                        $promo_type = $promo_data['type'] ?? '';
                        $discount_value = $promo_data['discount_value'] ?? 0;
                        $discount_type = $promo_data['discount_type'] ?? 'percentage';
                        $manual_pricing = isset($promo_data['manual_pricing']) ? 1 : 0;
                        $restock_note = $promo_data['restock_note'] ?? '';
                        $promo_code = generatePromoCode();
                        
                        $promotion_codes[] = $promo_code;
                        
                        // Determine which items to apply promotion to
                        $items_to_promote = [];
                        
                        switch ($promo_type) {
                            case 'single':
                                if (!empty($promo_data['items'])) {
                                    $items_to_promote = [$promo_data['items']];
                                }
                                break;
                                
                            case 'multiple':
                                if (!empty($promo_data['items']) && is_array($promo_data['items'])) {
                                    $items_to_promote = $promo_data['items'];
                                }
                                break;
                                
                            case 'category':
                                // Get all items in the selected category
                                $category = $promo_data['category'] ?? '';
                                if ($category) {
                                    $stmt = $pdo->prepare("
                                        SELECT id FROM voting_list_items 
                                        WHERE list_id = ? AND (category = ? OR (category IS NULL AND ? = 'uncategorized'))
                                        AND status = 'active'
                                    ");
                                    $stmt->execute([$bulk_list_id, $category, $category]);
                                    $category_items = $stmt->fetchAll(PDO::FETCH_COLUMN);
                                    $items_to_promote = $category_items;
                                }
                                break;
                        }
                        
                        // Create promotion for each item
                        $stmt = $pdo->prepare("
                            INSERT INTO promotions (
                                business_id, list_id, item_id, discount_type, discount_value,
                                start_date, end_date, promo_code, description, status, manual_pricing, display_message
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, ?)
                        ");
                        
                        foreach ($items_to_promote as $item_id) {
                            $description = "Bulk promotion - {$promo_type} discount";
                            if ($stmt->execute([$business_id, $bulk_list_id, $item_id, $discount_type, $discount_value, $bulk_start_date, $bulk_end_date, $promo_code, $description, $manual_pricing, $restock_note])) {
                                $total_created++;
                            }
                        }
                    }
                    
                    $pdo->commit();
                    
                    if ($total_created > 0) {
                        $codes_list = implode(', ', $promotion_codes);
                        $message = "Successfully created {$total_created} promotions! Codes: {$codes_list}";
                        $message_type = "success";
                    } else {
                        $message = "No promotions were created. Please check your selections.";
                        $message_type = "warning";
                    }
                } catch (Exception $e) {
                    $pdo->rollback();
                    $message = "Error creating bulk promotions: " . $e->getMessage();
                    $message_type = "danger";
                }
            } else {
                $message = "Please fill in all required fields for bulk promotion creation.";
                $message_type = "warning";
            }
            break;
    }
}

// Get active promotions
$stmt = $pdo->prepare("
    SELECT p.*, vli.item_name as item_name, vli.retail_price, vl.name as list_name
    FROM promotions p
    JOIN voting_list_items vli ON p.item_id = vli.id
    JOIN voting_lists vl ON p.list_id = vl.id
    WHERE p.business_id = ?
    ORDER BY p.start_date DESC
");
$stmt->execute([$business_id]);
$promotions = $stmt->fetchAll();

// Get available voting lists for new promotions
$stmt = $pdo->prepare("
    SELECT id, name
    FROM voting_lists
    WHERE business_id = ?
    ORDER BY name
");
$stmt->execute([$business_id]);
$lists = $stmt->fetchAll();

// Get promotion statistics
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_promotions,
        COUNT(CASE WHEN status = 'active' THEN 1 END) as active_promotions,
        COUNT(CASE WHEN end_date < CURDATE() THEN 1 END) as expired_promotions
    FROM promotions 
    WHERE business_id = ?
");
$stmt->execute([$business_id]);
$stats = $stmt->fetch();

require_once __DIR__ . '/../core/includes/header.php';
?>

<style>
/* Enhanced table styling for better visibility */
.table {
    background: rgba(255, 255, 255, 0.15) !important;
    backdrop-filter: blur(20px) !important;
    border-radius: 12px !important;
    overflow: hidden !important;
}

.table thead th {
    background: rgba(30, 60, 114, 0.6) !important;
    color: #ffffff !important;
    font-weight: 600 !important;
    border-bottom: 2px solid rgba(255, 255, 255, 0.3) !important;
    padding: 1rem 0.75rem !important;
}

.table tbody td {
    background: rgba(255, 255, 255, 0.12) !important;
    color: rgba(255, 255, 255, 0.95) !important;
    border-bottom: 1px solid rgba(255, 255, 255, 0.15) !important;
    padding: 0.875rem 0.75rem !important;
}

.table tbody tr:hover td {
    background: rgba(255, 255, 255, 0.18) !important;
    color: #ffffff !important;
}

/* Button styling inside tables */
.table .btn-outline-primary,
.table .btn-outline-secondary,
.table .btn-outline-success,
.table .btn-outline-warning,
.table .btn-outline-danger {
    background: rgba(255, 255, 255, 0.1) !important;
    border-color: rgba(255, 255, 255, 0.3) !important;
    color: rgba(255, 255, 255, 0.9) !important;
}

.table .btn-outline-primary:hover {
    background: rgba(13, 110, 253, 0.8) !important;
    color: #ffffff !important;
}

.table .btn-outline-secondary:hover {
    background: rgba(108, 117, 125, 0.8) !important;
    color: #ffffff !important;
}

.table .btn-outline-success:hover {
    background: rgba(25, 135, 84, 0.8) !important;
    color: #ffffff !important;
}

.table .btn-outline-warning:hover {
    background: rgba(255, 193, 7, 0.8) !important;
    color: #000000 !important;
}

.table .btn-outline-danger:hover {
    background: rgba(220, 53, 69, 0.8) !important;
    color: #ffffff !important;
}

/* Badge styling in tables */
.table .badge {
    background: rgba(255, 255, 255, 0.2) !important;
    color: rgba(255, 255, 255, 0.95) !important;
    border: 1px solid rgba(255, 255, 255, 0.3) !important;
}

.table .badge.bg-success {
    background: rgba(25, 135, 84, 0.7) !important;
    color: #ffffff !important;
}

.table .badge.bg-warning {
    background: rgba(255, 193, 7, 0.7) !important;
    color: #000000 !important;
}

.table .badge.bg-danger {
    background: rgba(220, 53, 69, 0.7) !important;
    color: #ffffff !important;
}

.table .badge.bg-secondary {
    background: rgba(108, 117, 125, 0.7) !important;
    color: #ffffff !important;
}

/* Empty state styling */
.table tbody td.text-center.text-muted {
    color: rgba(255, 255, 255, 0.7) !important;
}

.text-center.py-4 .text-muted {
    color: rgba(255, 255, 255, 0.8) !important;
}

/* Form styling improvements */
.card .text-muted {
    color: rgba(255, 255, 255, 0.8) !important;
}
</style>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1>Promotions Management</h1>
            <p class="text-muted">Create and manage promotional campaigns for your voting lists</p>
        </div>
        <div class="btn-group">
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newPromotionModal">
                <i class="bi bi-plus-circle me-2"></i>New Promotion
            </button>
            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#bulkPromotionModal">
                <i class="bi bi-collection me-2"></i>Bulk Create
            </button>
        </div>
    </div>
    
    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-4 mb-4">
            <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(45deg, #2B32B2, #1488CC);">
                <div class="card-body text-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0">Total Promotions</h6>
                            <h2 class="mt-2 mb-0"><?php echo $stats['total_promotions']; ?></h2>
                        </div>
                        <i class="bi bi-tag-fill h1 mb-0 opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-4">
            <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(45deg, #1A2980, #26D0CE);">
                <div class="card-body text-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0">Active Promotions</h6>
                            <h2 class="mt-2 mb-0"><?php echo $stats['active_promotions']; ?></h2>
                        </div>
                        <i class="bi bi-check-circle-fill h1 mb-0 opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-4">
            <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(45deg, #16222A, #3A6073);">
                <div class="card-body text-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0">Expired</h6>
                            <h2 class="mt-2 mb-0"><?php echo $stats['expired_promotions']; ?></h2>
                        </div>
                        <i class="bi bi-clock-fill h1 mb-0 opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Active Promotions -->
    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0">Your Promotions</h5>
        </div>
        <div class="card-body">
            <?php if (empty($promotions)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-tag text-muted" style="font-size: 3rem;"></i>
                    <h5 class="mt-3 text-muted">No promotions created yet</h5>
                    <p class="text-muted">Create your first promotion to boost engagement!</p>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newPromotionModal">
                        Create Promotion
                    </button>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>List</th>
                                <th>Discount</th>
                                <th>Promo Code</th>
                                <th>Duration</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($promotions as $promo): ?>
                                <tr>
                                    <td>
                                        <div>
                                            <h6 class="mb-0"><?php echo htmlspecialchars($promo['item_name']); ?></h6>
                                            <small class="text-muted">$<?php echo number_format($promo['retail_price'], 2); ?></small>
                                            <?php if (isset($promo['manual_pricing']) && $promo['manual_pricing'] && !empty($promo['display_message'])): ?>
                                                <div class="mt-1">
                                                    <small class="text-warning">
                                                        <i class="bi bi-tools me-1"></i>Restock Note: <?php echo htmlspecialchars($promo['display_message']); ?>
                                                    </small>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($promo['list_name']); ?></td>
                                    <td>
                                        <?php if ($promo['discount_type'] === 'percentage'): ?>
                                            <span class="badge bg-success"><?php echo $promo['discount_value']; ?>% off</span>
                                        <?php else: ?>
                                            <span class="badge bg-info">$<?php echo number_format($promo['discount_value'], 2); ?> off</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <code><?php echo $promo['promo_code']; ?></code>
                                    </td>
                                    <td>
                                        <small>
                                            <?php echo date('M d', strtotime($promo['start_date'])); ?> - 
                                            <?php echo date('M d, Y', strtotime($promo['end_date'])); ?>
                                        </small>
                                    </td>
                                    <td>
                                        <?php
                                        $status_class = 'secondary';
                                        if ($promo['status'] === 'active' && $promo['end_date'] >= date('Y-m-d')) {
                                            $status_class = 'success';
                                        } elseif ($promo['end_date'] < date('Y-m-d')) {
                                            $status_class = 'danger';
                                        }
                                        ?>
                                        <span class="badge bg-<?php echo $status_class; ?>">
                                            <?php echo $promo['end_date'] < date('Y-m-d') ? 'Expired' : ucfirst($promo['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-outline-primary" 
                                                    onclick="generatePromotionQR('<?php echo $promo['promo_code']; ?>', '<?php echo htmlspecialchars($promo['item_name']); ?>')">
                                                <i class="bi bi-qr-code"></i>
                                            </button>
                                            <?php if ($promo['status'] === 'active'): ?>
                                                <button type="button" class="btn btn-outline-warning" 
                                                        onclick="togglePromotion(<?php echo $promo['id']; ?>, 'inactive')">
                                                    <i class="bi bi-pause"></i>
                                                </button>
                                            <?php else: ?>
                                                <button type="button" class="btn btn-outline-success" 
                                                        onclick="togglePromotion(<?php echo $promo['id']; ?>, 'active')">
                                                    <i class="bi bi-play"></i>
                                                </button>
                                            <?php endif; ?>
                                            <button type="button" class="btn btn-outline-danger" 
                                                    onclick="deletePromotion(<?php echo $promo['id']; ?>, '<?php echo htmlspecialchars($promo['promo_code']); ?>', '<?php echo htmlspecialchars($promo['item_name']); ?>')">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
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

<!-- New Promotion Modal -->
<div class="modal fade" id="newPromotionModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="">
                <input type="hidden" name="action" value="create_promotion">
                
                <div class="modal-header">
                    <h5 class="modal-title">Create New Promotion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <!-- Promotion Type Selection -->
                    <div class="mb-3">
                        <label class="form-label">Promotion Type</label>
                        <select class="form-select" name="promotion_type" id="promotionType" required onchange="togglePromotionFields()">
                            <option value="">Select promotion type</option>
                            <option value="single_item">Single Item Promotion</option>
                            <option value="multi_item">Multiple Items Promotion</option>
                            <option value="combo_deal">Combo Deal</option>
                        </select>
                    </div>

                    <!-- Voting List Selection -->
                    <div class="mb-3">
                        <label class="form-label">Voting List</label>
                        <select class="form-select" name="list_id" id="listSelect" required onchange="loadListItems()">
                            <option value="">Select a voting list</option>
                            <?php foreach ($lists as $list): ?>
                                <option value="<?php echo $list['id']; ?>">
                                    <?php echo htmlspecialchars($list['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Single Item Selection -->
                    <div class="mb-3" id="singleItemField" style="display: none;">
                        <label class="form-label">Item</label>
                        <select class="form-select" name="item_id">
                            <option value="">Select an item</option>
                            <!-- Items will be populated by JavaScript -->
                        </select>
                    </div>

                    <!-- Multiple Items Selection -->
                    <div class="mb-3" id="multiItemField" style="display: none;">
                        <label class="form-label">Items (Select Multiple)</label>
                        <div id="itemCheckboxes" class="form-check-container" style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; border-radius: 5px;">
                            <!-- Checkboxes will be populated by JavaScript -->
                        </div>
                        <small class="text-muted">Select multiple items for bulk promotion with same code</small>
                    </div>

                    <!-- Combo Deal Items -->
                    <div class="mb-3" id="comboItemField" style="display: none;">
                        <label class="form-label">Combo Items</label>
                        <div id="comboItemsContainer">
                            <!-- Combo items will be added dynamically -->
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="addComboItem()">
                            <i class="bi bi-plus"></i> Add Item to Combo
                        </button>
                    </div>

                    <!-- Discount Configuration -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Discount Type</label>
                                <select class="form-select" name="discount_type" id="discountType" required onchange="updateDiscountInput()">
                                    <option value="">Select type</option>
                                    <option value="percentage">Percentage</option>
                                    <option value="fixed">Fixed Amount</option>
                                    <option value="buy_x_get_y" id="buyXGetYOption" style="display: none;">Buy X Get Y Free</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Discount Value</label>
                                <div class="input-group">
                                    <span class="input-group-text" id="discountPrefix">%</span>
                                    <input type="number" class="form-control" name="discount_value" 
                                           min="0" max="100" step="0.01" required>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Buy X Get Y Configuration (for combo deals) -->
                    <div class="row" id="buyXGetYFields" style="display: none;">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Buy Quantity</label>
                                <input type="number" class="form-control" name="buy_quantity" min="1" value="2">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Get Free Quantity</label>
                                <input type="number" class="form-control" name="get_quantity" min="1" value="1">
                            </div>
                        </div>
                    </div>

                    <!-- Manual Pricing Option -->
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="manual_pricing" id="manualPricing" onchange="toggleManualPricing()">
                            <label class="form-check-label" for="manualPricing">
                                <strong>Manual Pricing Workflow</strong> - Vendor sets pricing during restock
                            </label>
                        </div>
                        <small class="text-muted">Check this if you need to manually set machine prices during restocking. Customers will still see calculated prices online.</small>
                    </div>

                    <!-- Display Message for Manual Pricing -->
                    <div class="mb-3" id="displayMessageField" style="display: none;">
                        <label class="form-label">Restock Instructions (Internal Note)</label>
                        <input type="text" class="form-control" name="display_message" 
                               placeholder="e.g., 'Set Coke to $1.00 when restocking' or 'Apply 20% discount at machine'">
                        <small class="text-muted">This note will help you remember pricing changes during restock. Customers won't see this.</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description (Optional)</label>
                        <textarea class="form-control" name="description" rows="2" 
                                  placeholder="Brief description of the promotion"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Start Date</label>
                                <input type="date" class="form-control" name="start_date" 
                                       min="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">End Date</label>
                                <input type="date" class="form-control" name="end_date" 
                                       min="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Promotion</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bulk Promotion Creation Modal -->
<div class="modal fade" id="bulkPromotionModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <form method="POST" action="" id="bulkPromotionForm">
                <input type="hidden" name="action" value="create_bulk_promotions">
                
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-collection me-2"></i>Bulk Create Promotions
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        <strong>Bulk Creation:</strong> Create multiple promotions with different types and discounts. They'll all appear on the same promotion page for customers.
                    </div>
                    
                    <!-- Voting List Selection for Bulk -->
                    <div class="mb-4">
                        <label class="form-label">Voting List</label>
                        <select class="form-select" name="bulk_list_id" id="bulkListSelect" required onchange="loadBulkListItems()">
                            <option value="">Select a voting list</option>
                            <?php foreach ($lists as $list): ?>
                                <option value="<?php echo $list['id']; ?>">
                                    <?php echo htmlspecialchars($list['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Promotion Builder Area -->
                    <div id="bulkPromotionContainer">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="mb-0">Promotions to Create</h6>
                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="addBulkPromotion()">
                                <i class="bi bi-plus"></i> Add Promotion
                            </button>
                        </div>
                        <div id="bulkPromotionsList">
                            <!-- Promotions will be added here -->
                        </div>
                    </div>
                    
                    <!-- Duration for all promotions -->
                    <div class="row mt-4">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Start Date (All Promotions)</label>
                                <input type="date" class="form-control" name="bulk_start_date" 
                                       min="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">End Date (All Promotions)</label>
                                <input type="date" class="form-control" name="bulk_end_date" 
                                       min="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-collection me-1"></i>Create All Promotions
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- QR Code Modal -->
<div class="modal fade" id="qrCodeModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Promotion QR Code</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <div id="qrcode" class="mb-3"></div>
                <h6 id="promoItemName" class="mb-2"></h6>
                <p class="text-muted small mb-0">Scan to redeem promotion</p>
                <div class="mt-3">
                    <button class="btn btn-sm btn-outline-primary" onclick="downloadQR()">
                        <i class="bi bi-download me-1"></i>Download
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcode-generator/1.4.4/qrcode.min.js"></script>
<script>
let currentQRCode = null;

// Handle discount type change
function updateDiscountInput() {
    const discountType = document.getElementById('discountType').value;
    const prefix = document.getElementById('discountPrefix');
    const input = document.querySelector('input[name="discount_value"]');
    const buyXGetYFields = document.getElementById('buyXGetYFields');
    
    // Hide buy X get Y fields by default
    buyXGetYFields.style.display = 'none';
    
    switch(discountType) {
        case 'percentage':
            prefix.textContent = '%';
            input.max = '100';
            input.style.display = 'block';
            break;
        case 'fixed':
            prefix.textContent = '$';
            input.removeAttribute('max');
            input.style.display = 'block';
            break;
        case 'buy_x_get_y':
            input.style.display = 'none';
            buyXGetYFields.style.display = 'block';
            break;
        default:
            prefix.textContent = '%';
            input.style.display = 'block';
    }
}

// Load items for selected list - FIXED VERSION
function loadListItems() {
    const listId = document.getElementById('listSelect').value;
    
    console.log('loadListItems called with listId:', listId);
    
    if (!listId) {
        // Clear all item displays
        const singleItemSelect = document.querySelector('#singleItemField select');
        const itemCheckboxes = document.getElementById('itemCheckboxes');
        
        if (singleItemSelect) singleItemSelect.innerHTML = '<option value="">Select an item</option>';
        if (itemCheckboxes) itemCheckboxes.innerHTML = '';
        return;
    }
    
    // Show loading state
    const singleItemSelect = document.querySelector('#singleItemField select');
    const itemCheckboxes = document.getElementById('itemCheckboxes');
    
    if (singleItemSelect) singleItemSelect.innerHTML = '<option value="">Loading items...</option>';
    if (itemCheckboxes) itemCheckboxes.innerHTML = '<div class="text-center p-2">Loading items...</div>';
    
    // Make AJAX call with correct path
    fetch(`get-list-items.php?list_id=${listId}`)
        .then(response => {
            console.log('Response status:', response.status);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('API response:', data);
            
            if (data.error) {
                console.error('API error:', data.error);
                if (singleItemSelect) singleItemSelect.innerHTML = '<option value="">Error: ' + data.error + '</option>';
                if (itemCheckboxes) itemCheckboxes.innerHTML = '<div class="text-danger p-2">Error: ' + data.error + '</div>';
                return;
            }
            
            // Clear and populate single item select
            if (singleItemSelect) {
                singleItemSelect.innerHTML = '<option value="">Select an item</option>';
                data.items.forEach(item => {
                    const option = document.createElement('option');
                    option.value = item.id;
                    option.textContent = `${item.item_name} ($${parseFloat(item.retail_price).toFixed(2)})`;
                    singleItemSelect.appendChild(option);
                });
            }
            
            // Clear and populate multiple items checkboxes
            if (itemCheckboxes) {
                itemCheckboxes.innerHTML = '';
                data.items.forEach(item => {
                    const checkboxDiv = document.createElement('div');
                    checkboxDiv.className = 'form-check';
                    checkboxDiv.innerHTML = `
                        <input class="form-check-input" type="checkbox" name="item_ids[]" value="${item.id}" id="item_${item.id}">
                        <label class="form-check-label" for="item_${item.id}">
                            ${item.item_name} <span class="text-muted">($${parseFloat(item.retail_price).toFixed(2)})</span>
                        </label>
                    `;
                    itemCheckboxes.appendChild(checkboxDiv);
                });
            }
            
            console.log('Items loaded successfully');
        })
        .catch(error => {
            console.error('Error loading items:', error);
            if (singleItemSelect) singleItemSelect.innerHTML = '<option value="">Error loading items</option>';
            if (itemCheckboxes) itemCheckboxes.innerHTML = '<div class="text-danger p-2">Error loading items</div>';
        });
}

// Generate QR Code for promotion
function generatePromotionQR(promoCode, itemName) {
    const modal = new bootstrap.Modal(document.getElementById('qrCodeModal'));
    const qrContainer = document.getElementById('qrcode');
    const itemNameEl = document.getElementById('promoItemName');
    
    qrContainer.innerHTML = '';
    itemNameEl.textContent = itemName;
    
    // Generate QR code for promotion redemption
    const qr = qrcode(0, 'M');
    qr.addData(`${window.location.origin}/redeem.php?code=${promoCode}`);
    qr.make();
    
    currentQRCode = qr;
    qrContainer.innerHTML = qr.createImgTag(4);
    
    modal.show();
}

// Download QR code
function downloadQR() {
    if (!currentQRCode) return;
    
    const canvas = document.createElement('canvas');
    const ctx = canvas.getContext('2d');
    const img = new Image();
    
    img.onload = function() {
        canvas.width = img.width;
        canvas.height = img.height;
        ctx.drawImage(img, 0, 0);
        
        const link = document.createElement('a');
        link.download = 'promotion-qr-code.png';
        link.href = canvas.toDataURL();
        link.click();
    };
    
    img.src = document.querySelector('#qrcode img').src;
}

// Toggle promotion status
function togglePromotion(promoId, newStatus) {
    if (confirm('Are you sure you want to change this promotion status?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="update_promotion">
            <input type="hidden" name="promo_id" value="${promoId}">
            <input type="hidden" name="status" value="${newStatus}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Toggle promotion field visibility based on type
function togglePromotionFields() {
    const promotionType = document.getElementById('promotionType').value;
    const singleItemField = document.getElementById('singleItemField');
    const multiItemField = document.getElementById('multiItemField');
    const comboItemField = document.getElementById('comboItemField');
    const buyXGetYOption = document.getElementById('buyXGetYOption');
    
    // Hide all fields first
    singleItemField.style.display = 'none';
    multiItemField.style.display = 'none';
    comboItemField.style.display = 'none';
    buyXGetYOption.style.display = 'none';
    
    // Show relevant fields based on selection
    switch(promotionType) {
        case 'single_item':
            singleItemField.style.display = 'block';
            break;
        case 'multi_item':
            multiItemField.style.display = 'block';
            break;
        case 'combo_deal':
            comboItemField.style.display = 'block';
            buyXGetYOption.style.display = 'block';
            break;
    }
}

// Add combo item
let comboItemCounter = 0;
function addComboItem() {
    const listId = document.getElementById('listSelect').value;
    if (!listId) {
        alert('Please select a voting list first');
        return;
    }
    
    comboItemCounter++;
    const container = document.getElementById('comboItemsContainer');
    const comboItemDiv = document.createElement('div');
    comboItemDiv.className = 'combo-item-row border rounded p-3 mb-2';
    comboItemDiv.innerHTML = `
        <div class="row">
            <div class="col-md-6">
                <label class="form-label">Item</label>
                <select class="form-select" name="combo_items[${comboItemCounter}][item_id]" required>
                    <option value="">Select an item</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Quantity</label>
                <input type="number" class="form-control" name="combo_items[${comboItemCounter}][quantity]" min="1" value="1" required>
            </div>
            <div class="col-md-2">
                <label class="form-label">Required</label>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="combo_items[${comboItemCounter}][is_required]" checked>
                </div>
            </div>
            <div class="col-md-1">
                <label class="form-label">&nbsp;</label>
                <button type="button" class="btn btn-outline-danger btn-sm w-100" onclick="removeComboItem(this)">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
        </div>
    `;
    container.appendChild(comboItemDiv);
    
    // Load items for this combo item
    loadComboItemOptions(comboItemDiv.querySelector('select'));
}

// Remove combo item
function removeComboItem(button) {
    button.closest('.combo-item-row').remove();
}

// Load combo item options
function loadComboItemOptions(selectElement) {
    const listId = document.getElementById('listSelect').value;
    if (!listId) return;
    
    fetch(`get-list-items.php?list_id=${listId}`)
        .then(response => response.json())
        .then(data => {
            selectElement.innerHTML = '<option value="">Select an item</option>';
            data.items.forEach(item => {
                const option = document.createElement('option');
                option.value = item.id;
                option.textContent = `${item.item_name} ($${parseFloat(item.retail_price).toFixed(2)})`;
                selectElement.appendChild(option);
            });
        })
        .catch(error => console.error('Error loading combo items:', error));
}

// Toggle manual pricing fields
function toggleManualPricing() {
    const manualPricing = document.getElementById('manualPricing').checked;
    const displayMessageField = document.getElementById('displayMessageField');
    
    if (manualPricing) {
        displayMessageField.style.display = 'block';
    } else {
        displayMessageField.style.display = 'none';
    }
}

// Delete promotion
function deletePromotion(promoId, promoCode, itemName) {
    if (confirm(`Are you sure you want to delete the promotion '${promoCode}'? This action cannot be undone.`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_promotion">
            <input type="hidden" name="promo_id" value="${promoId}">
        `;
        
        document.body.appendChild(form);
        form.submit();
    }
}

// Bulk promotion functions
let bulkPromotionCounter = 0;
let bulkItemsCache = [];

function loadBulkListItems() {
    const listId = document.getElementById('bulkListSelect').value;
    if (!listId) {
        bulkItemsCache = [];
        return;
    }
    
    fetch(`get-list-items.php?list_id=${listId}`)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                console.error('Error loading bulk items:', data.error);
                return;
            }
            bulkItemsCache = data.items;
            console.log('Bulk items loaded:', bulkItemsCache.length);
        })
        .catch(error => {
            console.error('Error loading bulk items:', error);
        });
}

function addBulkPromotion() {
    if (bulkItemsCache.length === 0) {
        alert('Please select a voting list first to load items');
        return;
    }
    
    bulkPromotionCounter++;
    const container = document.getElementById('bulkPromotionsList');
    const promotionDiv = document.createElement('div');
    promotionDiv.className = 'card mb-3 bulk-promotion-item';
    promotionDiv.innerHTML = `
        <div class="card-header d-flex justify-content-between align-items-center">
            <h6 class="mb-0">Promotion #${bulkPromotionCounter}</h6>
            <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeBulkPromotion(this)">
                <i class="bi bi-trash"></i>
            </button>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <label class="form-label">Promotion Type</label>
                    <select class="form-select" name="bulk_promotions[${bulkPromotionCounter}][type]" onchange="updateBulkPromotionFields(this, ${bulkPromotionCounter})" required>
                        <option value="">Select type</option>
                        <option value="single">Single Item</option>
                        <option value="multiple">Multiple Items</option>
                        <option value="category">All in Category</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Discount</label>
                    <div class="input-group">
                        <input type="number" class="form-control" name="bulk_promotions[${bulkPromotionCounter}][discount_value]" 
                               min="0" max="100" step="0.01" required placeholder="Amount">
                        <select class="form-select" name="bulk_promotions[${bulkPromotionCounter}][discount_type]" style="max-width: 100px;" required>
                            <option value="percentage">%</option>
                            <option value="fixed">$</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="row mt-3">
                <div class="col-12">
                    <div id="bulkItemSelection_${bulkPromotionCounter}" style="display: none;">
                        <!-- Item selection will be populated here -->
                    </div>
                </div>
            </div>
            
            <div class="row mt-3">
                <div class="col-md-6">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="bulk_promotions[${bulkPromotionCounter}][manual_pricing]">
                        <label class="form-check-label">Manual Pricing Workflow</label>
                    </div>
                </div>
                <div class="col-md-6">
                    <input type="text" class="form-control form-control-sm" 
                           name="bulk_promotions[${bulkPromotionCounter}][restock_note]" 
                           placeholder="Restock instructions (optional)">
                </div>
            </div>
        </div>
    `;
    
    container.appendChild(promotionDiv);
}

function removeBulkPromotion(button) {
    button.closest('.bulk-promotion-item').remove();
}

function updateBulkPromotionFields(selectElement, promotionIndex) {
    const promotionType = selectElement.value;
    const itemSelectionDiv = document.getElementById(`bulkItemSelection_${promotionIndex}`);
    
    if (!promotionType) {
        itemSelectionDiv.style.display = 'none';
        return;
    }
    
    itemSelectionDiv.style.display = 'block';
    
    switch (promotionType) {
        case 'single':
            itemSelectionDiv.innerHTML = `
                <label class="form-label">Select Item</label>
                <select class="form-select" name="bulk_promotions[${promotionIndex}][items]" required>
                    <option value="">Choose item</option>
                    ${bulkItemsCache.map(item => 
                        `<option value="${item.id}">${item.item_name} ($${parseFloat(item.retail_price).toFixed(2)})</option>`
                    ).join('')}
                </select>
            `;
            break;
        case 'multiple':
            itemSelectionDiv.innerHTML = `
                <label class="form-label">Select Multiple Items</label>
                <div style="max-height: 150px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; border-radius: 5px;">
                    ${bulkItemsCache.map(item => `
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="bulk_promotions[${promotionIndex}][items][]" value="${item.id}" id="bulk_item_${promotionIndex}_${item.id}">
                            <label class="form-check-label" for="bulk_item_${promotionIndex}_${item.id}">
                                ${item.item_name} <span class="text-muted">($${parseFloat(item.retail_price).toFixed(2)})</span>
                            </label>
                        </div>
                    `).join('')}
                </div>
            `;
            break;
        case 'category':
            // Get unique categories
            const categories = [...new Set(bulkItemsCache.map(item => item.category || 'uncategorized'))];
            itemSelectionDiv.innerHTML = `
                <label class="form-label">Select Category</label>
                <select class="form-select" name="bulk_promotions[${promotionIndex}][category]" required>
                    <option value="">Choose category</option>
                    ${categories.map(category => 
                        `<option value="${category}">${category.charAt(0).toUpperCase() + category.slice(1)}</option>`
                    ).join('')}
                </select>
                <small class="text-muted">All items in this category will get the same discount</small>
            `;
            break;
    }
}
</script>

<?php require_once __DIR__ . '/../core/includes/footer.php'; ?> 