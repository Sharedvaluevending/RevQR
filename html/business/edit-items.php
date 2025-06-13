<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';

// Require business role
require_role('business');

$message = '';
$message_type = '';

// Get business ID
$stmt = $pdo->prepare("SELECT id FROM businesses WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$business = $stmt->fetch();

if (!$business) {
    // Create business record if it doesn't exist
    $stmt = $pdo->prepare("INSERT INTO businesses (user_id, name, slug) VALUES (?, ?, ?)");
    $slug = 'my-business-' . time(); // Generate a unique slug
    $stmt->execute([$_SESSION['user_id'], 'My Business', $slug]);
    $business_id = $pdo->lastInsertId();
} else {
    $business_id = $business['id'];
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                // First get or create a voting list for this business
                $stmt = $pdo->prepare("SELECT id FROM voting_lists WHERE business_id = ? LIMIT 1");
                $stmt->execute([$business_id]);
                $voting_list = $stmt->fetch();
                
                if (!$voting_list) {
                    // Create a default voting list if none exists
                    $stmt = $pdo->prepare("INSERT INTO voting_lists (business_id, name, description) VALUES (?, ?, ?)");
                    $stmt->execute([$business_id, 'Default List', 'Default voting list']);
                    $voting_list_id = $pdo->lastInsertId();
                } else {
                    $voting_list_id = $voting_list['id'];
                }

                // Handle different add types
                if ($_POST['add_type'] === 'master_item' && !empty($_POST['master_item_id'])) {
                    // Adding from master list
                    $stmt = $pdo->prepare("SELECT * FROM master_items WHERE id = ?");
                    $stmt->execute([$_POST['master_item_id']]);
                    $master_item = $stmt->fetch();
                    
                    if ($master_item) {
                        $stmt = $pdo->prepare("
                            INSERT INTO voting_list_items (voting_list_id, item_name, item_category, retail_price, cost_price, list_type, status, master_item_id)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        if ($stmt->execute([
                            $voting_list_id,
                            $master_item['name'],
                            $master_item['category'],
                            $master_item['suggested_price'],
                            $master_item['suggested_cost'],
                            $_POST['list_type'] ?? 'regular',
                            'active',
                            $master_item['id']
                        ])) {
                            $message = "Item added from master list successfully!";
                            $message_type = "success";
                        }
                    }
                } elseif ($_POST['add_type'] === 'catalog_item' && !empty($_POST['catalog_item_id'])) {
                    // Adding from catalog
                    $stmt = $pdo->prepare("
                        SELECT uci.*, mi.name, mi.category, mi.suggested_price, mi.suggested_cost 
                        FROM user_catalog_items uci 
                        JOIN master_items mi ON uci.master_item_id = mi.id 
                        WHERE uci.id = ? AND uci.user_id = ?
                    ");
                    $stmt->execute([$_POST['catalog_item_id'], $_SESSION['user_id']]);
                    $catalog_item = $stmt->fetch();
                    
                    if ($catalog_item) {
                        $stmt = $pdo->prepare("
                            INSERT INTO voting_list_items (voting_list_id, item_name, item_category, retail_price, cost_price, list_type, status, master_item_id)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        if ($stmt->execute([
                            $voting_list_id,
                            $catalog_item['custom_name'] ?: $catalog_item['name'],
                            $catalog_item['category'],
                            $catalog_item['custom_price'] ?: $catalog_item['suggested_price'],
                            $catalog_item['custom_cost'] ?: $catalog_item['suggested_cost'],
                            $_POST['list_type'] ?? 'regular',
                            'active',
                            $catalog_item['master_item_id']
                        ])) {
                            $message = "Item added from catalog successfully!";
                            $message_type = "success";
                        }
                    }
                } else {
                    // Adding new custom item
                    $stmt = $pdo->prepare("
                        INSERT INTO voting_list_items (voting_list_id, item_name, item_category, retail_price, list_type, status)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    if ($stmt->execute([
                        $voting_list_id,
                        $_POST['item_name'],
                        $_POST['type'],
                        $_POST['price'],
                        $_POST['list_type'] ?? 'regular',
                        'active'
                    ])) {
                        $message = "Custom item added successfully!";
                        $message_type = "success";
                    }
                }
                break;

            case 'edit':
                $stmt = $pdo->prepare("
                    UPDATE voting_list_items i
                    JOIN voting_lists l ON i.voting_list_id = l.id
                    SET i.item_name = ?, i.item_category = ?, i.retail_price = ?, i.list_type = ?, i.status = ?
                    WHERE i.id = ? AND l.business_id = ?
                ");
                if ($stmt->execute([
                    $_POST['item_name'],
                    $_POST['type'],
                    $_POST['price'],
                    $_POST['list_type'] ?? 'regular',
                    $_POST['status'] ?? 'active',
                    $_POST['item_id'],
                    $business_id
                ])) {
                    $message = "Item updated successfully!";
                    $message_type = "success";
                }
                break;

            case 'delete':
                $stmt = $pdo->prepare("
                    DELETE i FROM voting_list_items i
                    JOIN voting_lists l ON i.voting_list_id = l.id
                    WHERE i.id = ? AND l.business_id = ?
                ");
                if ($stmt->execute([$_POST['item_id'], $business_id])) {
                    $message = "Item deleted successfully!";
                    $message_type = "success";
                }
                break;
        }
    }
}

// Get all items for this business
$stmt = $pdo->prepare("
    SELECT i.*, l.name as voting_list_name
    FROM voting_list_items i
    JOIN voting_lists l ON i.voting_list_id = l.id
    WHERE l.business_id = ? 
    ORDER BY i.list_type, i.item_name
");
$stmt->execute([$business_id]);
$items = $stmt->fetchAll();

// Group items by list type
$vote_in_items = array_filter($items, function($item) { return $item['list_type'] === 'vote_in'; });
$vote_out_items = array_filter($items, function($item) { return $item['list_type'] === 'vote_out'; });
$regular_items = array_filter($items, function($item) { return $item['list_type'] === 'regular'; });
$showcase_items = array_filter($items, function($item) { return $item['list_type'] === 'showcase'; });

// Get categories for filtering
$stmt = $pdo->query("SELECT DISTINCT category FROM master_items WHERE category IS NOT NULL ORDER BY category ASC");
$categories = $stmt->fetchAll(PDO::FETCH_COLUMN);

require_once __DIR__ . '/../core/includes/header.php';
?>

<style>
.form-check-label {
    cursor: pointer;
    padding: 0.5rem;
    border-radius: 0.375rem;
    transition: all 0.2s;
}

.form-check-label:hover {
    background-color: rgba(0, 123, 255, 0.1);
}

.form-check-input:checked + .form-check-label {
    background-color: rgba(0, 123, 255, 0.15);
    border: 1px solid rgba(0, 123, 255, 0.3);
}

#masterItemSelect, #catalogItemSelect {
    max-height: 200px;
    overflow-y: auto;
}

#masterItemSelect option, #catalogItemSelect option {
    padding: 0.5rem;
    border-bottom: 1px solid #eee;
}

.loading-spinner {
    display: inline-block;
    width: 1rem;
    height: 1rem;
    border: 2px solid #f3f3f3;
    border-top: 2px solid #007bff;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
</style>

<div class="container py-4">
    <h1 class="mb-4">Manage Items</h1>
    
    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Add Item Button -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <button type="button" class="btn btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#addItemModal">
            <i class="bi bi-plus-circle me-2"></i>Add New Item
            <span class="badge bg-light text-primary ms-2">Enhanced</span>
        </button>
        <div class="text-muted small">
            <i class="bi bi-info-circle me-1"></i>
            Choose from Master List, Your Catalog, or Create Custom Items
        </div>
    </div>

    <!-- Vote In Items -->
    <div class="card mb-4">
        <div class="card-header bg-success text-white">
            <h5 class="mb-0">Items to Vote In</h5>
        </div>
        <div class="card-body">
            <?php if (empty($vote_in_items)): ?>
                <p class="text-muted">No items to vote in yet.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Type</th>
                                <th>Price</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($vote_in_items as $item): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                                    <td><?php echo ucfirst(htmlspecialchars($item['item_category'])); ?></td>
                                    <td>$<?php echo number_format($item['retail_price'], 2); ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-primary" onclick="editItem(<?php echo htmlspecialchars(json_encode($item)); ?>)">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button class="btn btn-sm btn-danger" onclick="deleteItem(<?php echo $item['id']; ?>)">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Vote Out Items -->
    <div class="card mb-4">
        <div class="card-header bg-danger text-white">
            <h5 class="mb-0">Items to Vote Out</h5>
        </div>
        <div class="card-body">
            <?php if (empty($vote_out_items)): ?>
                <p class="text-muted">No items to vote out yet.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Type</th>
                                <th>Price</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($vote_out_items as $item): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                                    <td><?php echo ucfirst(htmlspecialchars($item['item_category'])); ?></td>
                                    <td>$<?php echo number_format($item['retail_price'], 2); ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-primary" onclick="editItem(<?php echo htmlspecialchars(json_encode($item)); ?>)">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button class="btn btn-sm btn-danger" onclick="deleteItem(<?php echo $item['id']; ?>)">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Regular Items -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">Regular Items</h5>
        </div>
        <div class="card-body">
            <?php if (empty($regular_items)): ?>
                <p class="text-muted">No regular items yet.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Type</th>
                                <th>Price</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($regular_items as $item): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                                    <td><?php echo ucfirst(htmlspecialchars($item['item_category'])); ?></td>
                                    <td>$<?php echo number_format($item['retail_price'], 2); ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-primary" onclick="editItem(<?php echo htmlspecialchars(json_encode($item)); ?>)">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button class="btn btn-sm btn-danger" onclick="deleteItem(<?php echo $item['id']; ?>)">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Promotional Features Management -->
    <div class="card mb-4">
        <div class="card-header bg-warning text-dark">
            <h5 class="mb-0">
                <i class="bi bi-megaphone me-2"></i>Promotional Features
                <span class="badge bg-dark ms-2">Dynamic Content</span>
            </h5>
        </div>
        <div class="card-body">
            <div class="row">
                <!-- Promotional Ads -->
                <div class="col-md-4 mb-3">
                    <div class="card h-100">
                        <div class="card-header bg-info text-white">
                            <h6 class="mb-0"><i class="bi bi-badge-ad me-1"></i>Promotional Ads</h6>
                        </div>
                        <div class="card-body">
                            <p class="small text-muted">Manage rotating promotional advertisements</p>
                            <div class="d-grid gap-2">
                                <button class="btn btn-success btn-sm" onclick="addPromotionalAd()">
                                    <i class="bi bi-plus-circle me-1"></i>Add Ad
                                </button>
                                <button class="btn btn-outline-primary btn-sm" onclick="managePromotionalAds()">
                                    <i class="bi bi-gear me-1"></i>Manage Ads
                                </button>
                                <button class="btn btn-outline-danger btn-sm" onclick="togglePromotionalAds()">
                                    <i class="bi bi-eye-slash me-1"></i>Hide All
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Spin Wheel -->
                <div class="col-md-4 mb-3">
                    <div class="card h-100">
                        <div class="card-header bg-warning text-dark">
                            <h6 class="mb-0"><i class="bi bi-arrow-clockwise me-1"></i>Spin Wheel</h6>
                        </div>
                        <div class="card-body">
                            <p class="small text-muted">Interactive spin wheel for rewards</p>
                            <div class="d-grid gap-2">
                                <button class="btn btn-success btn-sm" onclick="enableSpinWheel()">
                                    <i class="bi bi-play-circle me-1"></i>Enable
                                </button>
                                <button class="btn btn-outline-primary btn-sm" onclick="configureSpinWheel()">
                                    <i class="bi bi-gear me-1"></i>Configure
                                </button>
                                <button class="btn btn-outline-danger btn-sm" onclick="disableSpinWheel()">
                                    <i class="bi bi-pause-circle me-1"></i>Disable
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pizza Tracker -->
                <div class="col-md-4 mb-3">
                    <div class="card h-100">
                        <div class="card-header bg-success text-white">
                            <h6 class="mb-0"><i class="bi bi-geo-alt me-1"></i>Pizza Tracker</h6>
                        </div>
                        <div class="card-body">
                            <p class="small text-muted">Real-time order tracking system</p>
                            <div class="d-grid gap-2">
                                <button class="btn btn-success btn-sm" onclick="enablePizzaTracker()">
                                    <i class="bi bi-play-circle me-1"></i>Enable
                                </button>
                                <button class="btn btn-outline-primary btn-sm" onclick="configurePizzaTracker()">
                                    <i class="bi bi-gear me-1"></i>Configure
                                </button>
                                <button class="btn btn-outline-danger btn-sm" onclick="disablePizzaTracker()">
                                    <i class="bi bi-pause-circle me-1"></i>Disable
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Feature Status Display -->
            <div class="mt-3">
                <h6>Current Status:</h6>
                <div class="row">
                    <div class="col-md-4">
                        <span class="badge bg-success">Promotional Ads: Active</span>
                    </div>
                    <div class="col-md-4">
                        <span class="badge bg-warning text-dark">Spin Wheel: Configurable</span>
                    </div>
                    <div class="col-md-4">
                        <span class="badge bg-info">Pizza Tracker: Available</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Showcase Items -->
    <div class="card mb-4">
        <div class="card-header bg-warning text-dark">
            <h5 class="mb-0">Showcase Items</h5>
        </div>
        <div class="card-body">
            <?php if (empty($showcase_items)): ?>
                <p class="text-muted">No showcase items yet.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Type</th>
                                <th>Price</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($showcase_items as $item): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                                    <td><?php echo ucfirst(htmlspecialchars($item['item_category'])); ?></td>
                                    <td>$<?php echo number_format($item['retail_price'], 2); ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-primary" onclick="editItem(<?php echo htmlspecialchars(json_encode($item)); ?>)">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button class="btn btn-sm btn-danger" onclick="deleteItem(<?php echo $item['id']; ?>)">
                                            <i class="bi bi-trash"></i>
                                        </button>
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

<!-- Add Item Modal -->
<div class="modal fade" id="addItemModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Item</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="addItemForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    
                    <!-- Item Source Selection -->
                    <div class="mb-4">
                        <label class="form-label fw-bold">Choose Item Source</label>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="add_type" id="addTypeCustom" 
                                           value="custom" checked>
                                    <label class="form-check-label" for="addTypeCustom">
                                        <i class="bi bi-plus-circle text-primary me-1"></i>
                                        <strong>Create New</strong><br>
                                        <small class="text-muted">Add a completely new item</small>
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="add_type" id="addTypeMaster" 
                                           value="master_item">
                                    <label class="form-check-label" for="addTypeMaster">
                                        <i class="bi bi-list-ul text-success me-1"></i>
                                        <strong>From Master List</strong><br>
                                        <small class="text-muted">Pick from master catalog</small>
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="add_type" id="addTypeCatalog" 
                                           value="catalog_item">
                                    <label class="form-check-label" for="addTypeCatalog">
                                        <i class="bi bi-bookmark text-warning me-1"></i>
                                        <strong>From My Catalog</strong><br>
                                        <small class="text-muted">Use saved catalog items</small>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Custom Item Fields -->
                    <div id="customItemFields">
                        <div class="mb-3">
                            <label class="form-label">Item Name</label>
                            <input type="text" class="form-control" name="item_name" id="customItemName">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Category</label>
                            <select class="form-select" name="type" id="customItemType">
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo htmlspecialchars($cat); ?>">
                                        <?php echo htmlspecialchars($cat); ?>
                                    </option>
                                <?php endforeach; ?>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Price</label>
                            <input type="number" class="form-control" name="price" id="customItemPrice" step="0.01">
                        </div>
                    </div>

                    <!-- Master List Selection -->
                    <div id="masterItemFields" style="display: none;">
                        <div class="mb-3">
                            <label class="form-label">Filter by Category</label>
                            <select class="form-select" id="masterCategoryFilter">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo htmlspecialchars($cat); ?>">
                                        <?php echo htmlspecialchars($cat); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Search Items</label>
                            <input type="text" class="form-control" id="masterItemSearch" 
                                   placeholder="Search by name, brand, or category...">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Select Item from Master List</label>
                            <select class="form-select" name="master_item_id" id="masterItemSelect" size="8">
                                <option value="">Loading master items...</option>
                            </select>
                        </div>
                    </div>

                    <!-- Catalog Selection -->
                    <div id="catalogItemFields" style="display: none;">
                        <div class="mb-3">
                            <label class="form-label">Filter by Category</label>
                            <select class="form-select" id="catalogCategoryFilter">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo htmlspecialchars($cat); ?>">
                                        <?php echo htmlspecialchars($cat); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Select Item from Catalog</label>
                            <select class="form-select" name="catalog_item_id" id="catalogItemSelect" size="8">
                                <option value="">Loading catalog items...</option>
                            </select>
                        </div>
                    </div>

                    <!-- Common Fields -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">List Type</label>
                                <select class="form-select" name="list_type" required>
                                    <option value="regular">Regular</option>
                                    <option value="vote_in">Vote In</option>
                                    <option value="vote_out">Vote Out</option>
                                    <option value="showcase">Showcase</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status">
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
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

<!-- Edit Item Modal -->
<div class="modal fade" id="editItemModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Item</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="item_id" id="editItemId">
                    
                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input type="text" class="form-control" name="item_name" id="editName" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Type</label>
                        <select class="form-select" name="type" id="editType" required>
                            <option value="">Select Type</option>
                            <option value="snack">Snack</option>
                            <option value="drink">Drink</option>
                            <option value="pizza">Pizza</option>
                            <option value="side">Side</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Price</label>
                        <input type="number" class="form-control" name="price" id="editPrice" step="0.01" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">List Type</label>
                        <select class="form-select" name="list_type" id="editListType" required>
                            <option value="regular">Regular</option>
                            <option value="vote_in">Vote In</option>
                            <option value="vote_out">Vote Out</option>
                            <option value="showcase">Showcase</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status" id="editStatus" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Item Modal -->
<div class="modal fade" id="deleteItemModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete Item</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this item?</p>
            </div>
            <div class="modal-footer">
                <form method="POST">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="item_id" id="deleteItemId">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Global variables for data storage
let masterItems = [];
let catalogItems = [];

// Form switching functionality
document.addEventListener('DOMContentLoaded', function() {
    const addTypeRadios = document.querySelectorAll('input[name="add_type"]');
    const customFields = document.getElementById('customItemFields');
    const masterFields = document.getElementById('masterItemFields');
    const catalogFields = document.getElementById('catalogItemFields');
    
    addTypeRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            // Hide all field sets
            customFields.style.display = 'none';
            masterFields.style.display = 'none';
            catalogFields.style.display = 'none';
            
            // Show relevant fields and load data
            switch(this.value) {
                case 'custom':
                    customFields.style.display = 'block';
                    // Clear required attributes from other fields
                    clearRequiredAttributes(['master_item_id', 'catalog_item_id']);
                    setRequiredAttributes(['item_name', 'type', 'price']);
                    break;
                case 'master_item':
                    masterFields.style.display = 'block';
                    loadMasterItems();
                    clearRequiredAttributes(['item_name', 'type', 'price', 'catalog_item_id']);
                    setRequiredAttributes(['master_item_id']);
                    break;
                case 'catalog_item':
                    catalogFields.style.display = 'block';
                    loadCatalogItems();
                    clearRequiredAttributes(['item_name', 'type', 'price', 'master_item_id']);
                    setRequiredAttributes(['catalog_item_id']);
                    break;
            }
        });
    });
    
    // Master list filtering
    document.getElementById('masterCategoryFilter').addEventListener('change', filterMasterItems);
    document.getElementById('masterItemSearch').addEventListener('input', debounce(filterMasterItems, 300));
    
    // Catalog filtering
    document.getElementById('catalogCategoryFilter').addEventListener('change', filterCatalogItems);
});

function clearRequiredAttributes(fieldNames) {
    fieldNames.forEach(name => {
        const field = document.querySelector(`[name="${name}"]`);
        if (field) field.removeAttribute('required');
    });
}

function setRequiredAttributes(fieldNames) {
    fieldNames.forEach(name => {
        const field = document.querySelector(`[name="${name}"]`);
        if (field) field.setAttribute('required', 'required');
    });
}

// Form validation before submission
document.getElementById('addItemForm').addEventListener('submit', function(e) {
    const addType = document.querySelector('input[name="add_type"]:checked').value;
    
    switch(addType) {
        case 'custom':
            const itemName = document.getElementById('customItemName').value.trim();
            const itemType = document.getElementById('customItemType').value;
            const itemPrice = document.getElementById('customItemPrice').value;
            
            if (!itemName || !itemType || !itemPrice) {
                e.preventDefault();
                alert('Please fill in all required fields for the custom item.');
                return false;
            }
            break;
            
        case 'master_item':
            const masterItemId = document.getElementById('masterItemSelect').value;
            if (!masterItemId) {
                e.preventDefault();
                alert('Please select an item from the master list.');
                return false;
            }
            break;
            
        case 'catalog_item':
            const catalogItemId = document.getElementById('catalogItemSelect').value;
            if (!catalogItemId) {
                e.preventDefault();
                alert('Please select an item from your catalog.');
                return false;
            }
            break;
    }
    
    return true;
});

function loadMasterItems() {
    if (masterItems.length > 0) {
        displayMasterItems(masterItems);
        return;
    }
    
    fetch('/business/get_master_items.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                masterItems = data.items || [];
                displayMasterItems(masterItems);
            } else {
                document.getElementById('masterItemSelect').innerHTML = 
                    '<option value="">Error loading items</option>';
            }
        })
        .catch(error => {
            console.error('Error loading master items:', error);
            document.getElementById('masterItemSelect').innerHTML = 
                '<option value="">Error loading items</option>';
        });
}

function displayMasterItems(items) {
    const select = document.getElementById('masterItemSelect');
    select.innerHTML = '<option value="">Choose an item...</option>';
    
    if (items.length === 0) {
        select.innerHTML = '<option value="">No items found</option>';
        return;
    }
    
    items.forEach(item => {
        const option = document.createElement('option');
        option.value = item.id;
        option.textContent = `${item.name} - ${item.category} - $${parseFloat(item.suggested_price).toFixed(2)}`;
        if (item.brand) {
            option.textContent = `${item.name} (${item.brand}) - ${item.category} - $${parseFloat(item.suggested_price).toFixed(2)}`;
        }
        select.appendChild(option);
    });
}

function filterMasterItems() {
    const categoryFilter = document.getElementById('masterCategoryFilter').value.toLowerCase();
    const searchFilter = document.getElementById('masterItemSearch').value.toLowerCase();
    
    let filteredItems = masterItems.filter(item => {
        const matchesCategory = !categoryFilter || 
            (item.category && item.category.toLowerCase().includes(categoryFilter));
        const matchesSearch = !searchFilter || 
            item.name.toLowerCase().includes(searchFilter) ||
            (item.brand && item.brand.toLowerCase().includes(searchFilter)) ||
            (item.category && item.category.toLowerCase().includes(searchFilter));
        
        return matchesCategory && matchesSearch;
    });
    
    displayMasterItems(filteredItems);
}

function loadCatalogItems() {
    if (catalogItems.length > 0) {
        displayCatalogItems(catalogItems);
        return;
    }
    
    fetch('/business/get_catalog_items.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                catalogItems = data.items || [];
                displayCatalogItems(catalogItems);
            } else {
                document.getElementById('catalogItemSelect').innerHTML = 
                    '<option value="">No catalog items found</option>';
            }
        })
        .catch(error => {
            console.error('Error loading catalog items:', error);
            document.getElementById('catalogItemSelect').innerHTML = 
                '<option value="">Error loading catalog items</option>';
        });
}

function displayCatalogItems(items) {
    const select = document.getElementById('catalogItemSelect');
    select.innerHTML = '<option value="">Choose an item...</option>';
    
    if (items.length === 0) {
        select.innerHTML = '<option value="">No catalog items found</option>';
        return;
    }
    
    items.forEach(item => {
        const option = document.createElement('option');
        option.value = item.id;
        const displayName = item.custom_name || item.name;
        const price = item.custom_price || item.suggested_price;
        option.textContent = `${displayName} - ${item.category} - $${parseFloat(price).toFixed(2)}`;
        select.appendChild(option);
    });
}

function filterCatalogItems() {
    const categoryFilter = document.getElementById('catalogCategoryFilter').value.toLowerCase();
    
    let filteredItems = catalogItems.filter(item => {
        return !categoryFilter || 
            (item.category && item.category.toLowerCase().includes(categoryFilter));
    });
    
    displayCatalogItems(filteredItems);
}

// Utility function for debouncing search input
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Existing functions
function editItem(item) {
    document.getElementById('editItemId').value = item.id;
    document.getElementById('editName').value = item.item_name;
    document.getElementById('editType').value = item.item_category;
    document.getElementById('editPrice').value = item.retail_price;
    document.getElementById('editListType').value = item.list_type;
    document.getElementById('editStatus').value = item.status;
    
    new bootstrap.Modal(document.getElementById('editItemModal')).show();
}

function deleteItem(itemId) {
    document.getElementById('deleteItemId').value = itemId;
    new bootstrap.Modal(document.getElementById('deleteItemModal')).show();
}

// Promotional Features Management Functions
function addPromotionalAd() {
    // Create modal for adding promotional ad
    const modalHtml = `
        <div class="modal fade" id="addPromotionalAdModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Add Promotional Ad</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form method="post" action="manage_promotional_features.php">
                        <div class="modal-body">
                            <input type="hidden" name="action" value="add_promotional_ad">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Ad Title</label>
                                    <input type="text" class="form-control" name="ad_title" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Background Color</label>
                                    <input type="color" class="form-control" name="background_color" value="#007bff">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Ad Description</label>
                                <textarea class="form-control" name="ad_description" rows="3" required></textarea>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Call-to-Action Text</label>
                                    <input type="text" class="form-control" name="cta_text" placeholder="Learn More">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Call-to-Action URL</label>
                                    <input type="url" class="form-control" name="cta_url" placeholder="https://...">
                                </div>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_active" value="1" checked>
                                <label class="form-check-label">Active (show on voting page)</label>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-success">Add Promotional Ad</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    `;
    
    // Remove existing modal if present
    const existingModal = document.getElementById('addPromotionalAdModal');
    if (existingModal) existingModal.remove();
    
    // Add modal to page
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    new bootstrap.Modal(document.getElementById('addPromotionalAdModal')).show();
}

function managePromotionalAds() {
    window.location.href = 'manage_promotional_ads.php';
}

function togglePromotionalAds() {
    if (confirm('Are you sure you want to hide all promotional ads from the voting page?')) {
        fetch('manage_promotional_features.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=toggle_promotional_ads'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Promotional ads visibility toggled successfully!');
                location.reload();
            } else {
                alert('Error: ' + (data.message || 'Failed to toggle promotional ads'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while toggling promotional ads');
        });
    }
}

function enableSpinWheel() {
    fetch('manage_promotional_features.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=enable_spin_wheel'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Spin wheel enabled successfully!');
            location.reload();
        } else {
            alert('Error: ' + (data.message || 'Failed to enable spin wheel'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while enabling spin wheel');
    });
}

function configureSpinWheel() {
    window.location.href = 'configure_spin_wheel.php';
}

function disableSpinWheel() {
    if (confirm('Are you sure you want to disable the spin wheel feature?')) {
        fetch('manage_promotional_features.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=disable_spin_wheel'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Spin wheel disabled successfully!');
                location.reload();
            } else {
                alert('Error: ' + (data.message || 'Failed to disable spin wheel'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while disabling spin wheel');
        });
    }
}

function enablePizzaTracker() {
    fetch('manage_promotional_features.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=enable_pizza_tracker'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Pizza tracker enabled successfully!');
            location.reload();
        } else {
            alert('Error: ' + (data.message || 'Failed to enable pizza tracker'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while enabling pizza tracker');
    });
}

function configurePizzaTracker() {
    window.location.href = 'configure_pizza_tracker.php';
}

function disablePizzaTracker() {
    if (confirm('Are you sure you want to disable the pizza tracker feature?')) {
        fetch('manage_promotional_features.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=disable_pizza_tracker'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Pizza tracker disabled successfully!');
                location.reload();
            } else {
                alert('Error: ' + (data.message || 'Failed to disable pizza tracker'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while disabling pizza tracker');
        });
    }
}
</script>

<?php require_once __DIR__ . '/../core/includes/footer.php'; ?> 