<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';

// Require business role
require_role('business');

$message = '';
$message_type = '';

// Get business details
$stmt = $pdo->prepare("
    SELECT b.*, u.username 
    FROM businesses b 
    JOIN users u ON b.id = u.business_id 
    WHERE u.id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$business = $stmt->fetch();

// Handle item actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add') {
            $name = trim($_POST['name']);
            $type = $_POST['type'];
            $price = (float)$_POST['price'];
            $list_type = $_POST['list_type'];
            $machine_id = (int)$_POST['machine_id'];
            $category_id = (int)$_POST['category_id'];
            $brand = trim($_POST['brand'] ?? '');
            
            if (empty($name)) {
                $message = "Item name is required.";
                $message_type = "danger";
            } elseif ($category_id <= 0) {
                $message = "Please select a category.";
                $message_type = "danger";
            } else {
                try {
                    $pdo->beginTransaction();
                    
                    // Get category name for master_items
                    $stmt = $pdo->prepare("SELECT name FROM categories WHERE id = ?");
                    $stmt->execute([$category_id]);
                    $category_name = $stmt->fetchColumn();
                    
                    // Check if this item already exists in master_items
                    $stmt = $pdo->prepare("
                        SELECT id FROM master_items 
                        WHERE name = ? AND category = ? AND type = ?
                    ");
                    $stmt->execute([$name, $category_name, $type]);
                    $master_item_id = $stmt->fetchColumn();
                    
                    // If not in master_items, add it
                    if (!$master_item_id) {
                        $stmt = $pdo->prepare("
                            INSERT INTO master_items (name, category, type, brand, suggested_price, suggested_cost, status) 
                            VALUES (?, ?, ?, ?, ?, ?, 'active')
                        ");
                        $suggested_cost = $price * 0.7; // 30% margin
                        $stmt->execute([$name, $category_name, $type, $brand, $price, $suggested_cost]);
                        $master_item_id = $pdo->lastInsertId();
                    }
                    
                    // Add to items table
                    $stmt = $pdo->prepare("
                        INSERT INTO items (machine_id, name, type, price, cost_price, list_type, status) 
                        VALUES (?, ?, ?, ?, ?, ?, 'active')
                    ");
                    $cost_price = $price * 0.7; // 30% margin
                    
                    if ($stmt->execute([$machine_id, $name, $type, $price, $cost_price, $list_type])) {
                        $item_id = $pdo->lastInsertId();
                        
                        // Create mapping between master_item and item
                        $stmt = $pdo->prepare("
                            INSERT INTO item_mapping (master_item_id, item_id) 
                            VALUES (?, ?)
                        ");
                        $stmt->execute([$master_item_id, $item_id]);
                        
                        $pdo->commit();
                        $message = "Item added successfully to both your machine and master catalog!";
                        $message_type = "success";
                    } else {
                        $pdo->rollback();
                        $message = "Error adding item. Please try again.";
                        $message_type = "danger";
                    }
                    
                } catch (Exception $e) {
                    $pdo->rollback();
                    $message = "Error adding item: " . $e->getMessage();
                    $message_type = "danger";
                }
            }
        } elseif ($_POST['action'] === 'edit') {
            $item_id = (int)$_POST['item_id'];
            $name = trim($_POST['name']);
            $price = (float)$_POST['price'];
            $status = $_POST['status'];
            $machine_id = (int)$_POST['machine_id'];
            $category_id = (int)$_POST['category_id'];
            $brand = trim($_POST['brand'] ?? '');
            $type = $_POST['type'];
            $list_type = $_POST['list_type'];
            
            if (empty($name)) {
                $message = "Item name is required.";
                $message_type = "danger";
            } elseif ($category_id <= 0) {
                $message = "Please select a category.";
                $message_type = "danger";
            } else {
                try {
                    $pdo->beginTransaction();
                    
                    // Get category name
                    $stmt = $pdo->prepare("SELECT name FROM categories WHERE id = ?");
                    $stmt->execute([$category_id]);
                    $category_name = $stmt->fetchColumn();
                    
                    // Update the item
                    $stmt = $pdo->prepare("
                        UPDATE items 
                        SET name = ?, 
                            type = ?,
                            price = ?, 
                            cost_price = ?, 
                            list_type = ?,
                            status = ?,
                            machine_id = ?
                        WHERE id = ?
                    ");
                    $cost_price = $price * 0.7; // 30% margin
                    
                    if ($stmt->execute([$name, $type, $price, $cost_price, $list_type, $status, $machine_id, $item_id])) {
                        // Find the corresponding master_item through mapping
                        $stmt = $pdo->prepare("
                            SELECT master_item_id 
                            FROM item_mapping 
                            WHERE item_id = ?
                        ");
                        $stmt->execute([$item_id]);
                        $master_item_id = $stmt->fetchColumn();
                        
                        if ($master_item_id) {
                            // Update master_items table
                            $stmt = $pdo->prepare("
                                UPDATE master_items 
                                SET name = ?, 
                                    category = ?,
                                    type = ?,
                                    brand = ?,
                                    suggested_price = ?, 
                                    suggested_cost = ?,
                                    status = ?
                                WHERE id = ?
                            ");
                            $stmt->execute([$name, $category_name, $type, $brand, $price, $cost_price, $status, $master_item_id]);
                        }
                        
                        $pdo->commit();
                        $message = "Item updated successfully in both your machine and master catalog!";
                        $message_type = "success";
                    } else {
                        $pdo->rollback();
                        $message = "Error updating item. Please try again.";
                        $message_type = "danger";
                    }
                    
                } catch (Exception $e) {
                    $pdo->rollback();
                    $message = "Error updating item: " . $e->getMessage();
                    $message_type = "danger";
                }
            }
        } elseif ($_POST['action'] === 'delete') {
            $item_id = (int)$_POST['item_id'];
            
            $stmt = $pdo->prepare("
                DELETE FROM items 
                WHERE id = ?
            ");
            
            if ($stmt->execute([$item_id])) {
                $message = "Item deleted successfully!";
                $message_type = "success";
            } else {
                $message = "Error deleting item. Please try again.";
                $message_type = "danger";
            }
        }
    }
}

// Pagination and filtering parameters
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$items_per_page = 10;
$offset = ($page - 1) * $items_per_page;
$selected_machine = isset($_GET['machine_id']) ? (int)$_GET['machine_id'] : 0;

// Get all machines for this business
$stmt = $pdo->prepare("
    SELECT id, name 
    FROM machines 
    WHERE business_id = ?
    ORDER BY name
");
$stmt->execute([$business['id']]);
$machines = $stmt->fetchAll();

// Get all categories
$stmt = $pdo->prepare("
    SELECT id, name, description 
    FROM categories 
    ORDER BY name
");
$stmt->execute();
$categories = $stmt->fetchAll();

// Build the WHERE clause for machine filtering
$where_clause = "WHERE m.business_id = ?";
$params = [$business['id']];

if ($selected_machine > 0) {
    $where_clause .= " AND i.machine_id = ?";
    $params[] = $selected_machine;
}

// Get total count of items for pagination
$count_stmt = $pdo->prepare("
    SELECT COUNT(*) as total
    FROM items i 
    JOIN machines m ON i.machine_id = m.id 
    $where_clause
");
$count_stmt->execute($params);
$total_items = $count_stmt->fetch()['total'];
$total_pages = ceil($total_items / $items_per_page);

// Get items for current page with machine filtering
$stmt = $pdo->prepare("
    SELECT i.*, m.name as machine_name,
           mi.category, mi.brand, mi.id as master_item_id,
           c.id as category_id
    FROM items i 
    JOIN machines m ON i.machine_id = m.id 
    LEFT JOIN item_mapping im ON i.id = im.item_id
    LEFT JOIN master_items mi ON im.master_item_id = mi.id
    LEFT JOIN categories c ON mi.category = c.name
    $where_clause
    ORDER BY i.created_at DESC
    LIMIT $items_per_page OFFSET $offset
");
$stmt->execute($params);
$items = $stmt->fetchAll();

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

.table .badge.bg-secondary {
    background: rgba(108, 117, 125, 0.7) !important;
    color: #ffffff !important;
}

/* Empty state styling */
.table tbody td.text-center.text-muted {
    color: rgba(255, 255, 255, 0.7) !important;
}

/* Enhanced empty state */
.text-center.py-4 .text-muted {
    color: rgba(255, 255, 255, 0.8) !important;
}

/* Form styling improvements */
.card .text-muted {
    color: rgba(255, 255, 255, 0.8) !important;
}
</style>

<div class="row mb-4">
    <div class="col-12">
        <h1 class="h3 mb-0">Manage Items</h1>
        <p class="text-muted">Add and manage items for your vending machines</p>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="row">
    <!-- Add Item Form -->
    <div class="col-md-4 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Add New Item</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label for="machine_id" class="form-label">Machine</label>
                        <select class="form-select" id="machine_id" name="machine_id" required>
                            <?php foreach ($machines as $machine): ?>
                                <option value="<?php echo $machine['id']; ?>">
                                    <?php echo htmlspecialchars($machine['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="category_id" class="form-label">Category</label>
                        <select class="form-select" id="category_id" name="category_id" required>
                            <option value="0">Select a category</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>">
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text text-muted">Choose the category for this item</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="name" class="form-label">Item Name</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="brand" class="form-label">Brand</label>
                        <input type="text" class="form-control" id="brand" name="brand" placeholder="Optional">
                        <small class="form-text text-muted">Brand name (optional)</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="type" class="form-label">Item Type</label>
                        <select class="form-select" id="type" name="type" required>
                            <option value="snack">Snack</option>
                            <option value="drink">Drink</option>
                            <option value="pizza">Pizza</option>
                            <option value="side">Side</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="list_type" class="form-label">List Type</label>
                        <select class="form-select" id="list_type" name="list_type" required>
                            <option value="regular">Regular - Standard items in the list</option>
                            <option value="vote_in">Vote In - Items that can be voted into the list</option>
                            <option value="vote_out">Vote Out - Items being voted to be removed</option>
                            <option value="showcase">Showcase - Featured or highlighted items</option>
                        </select>
                        <small class="form-text text-muted">
                            Regular: Standard items in the list<br>
                            Vote In: Items that can be voted into the list<br>
                            Vote Out: Items being voted to be removed<br>
                            Showcase: Featured or highlighted items
                        </small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="price" class="form-label">Price</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" class="form-control" id="price" name="price" 
                                   step="0.01" min="0" value="0.00" required>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-plus-circle me-2"></i>Add Item
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Items List -->
    <div class="col-md-8 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Your Items</h5>
                <small class="text-muted">
                    <?php echo $total_items; ?> total item<?php echo $total_items !== 1 ? 's' : ''; ?>
                    <?php if ($selected_machine > 0): ?>
                        <?php 
                        $selected_machine_name = '';
                        foreach ($machines as $machine) {
                            if ($machine['id'] == $selected_machine) {
                                $selected_machine_name = $machine['name'];
                                break;
                            }
                        }
                        ?>
                        in <?php echo htmlspecialchars($selected_machine_name); ?>
                    <?php endif; ?>
                </small>
            </div>
            <div class="card-body">
                <!-- Machine Filter -->
                <div class="row mb-3">
                    <div class="col-md-6">
                        <form method="GET" class="d-flex gap-2">
                            <select class="form-select" name="machine_id" onchange="this.form.submit()">
                                <option value="0"<?php echo $selected_machine === 0 ? ' selected' : ''; ?>>
                                    All Machines
                                </option>
                                <?php foreach ($machines as $machine): ?>
                                    <option value="<?php echo $machine['id']; ?>"<?php echo $selected_machine === (int)$machine['id'] ? ' selected' : ''; ?>>
                                        <?php echo htmlspecialchars($machine['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    </div>
                </div>

                <?php if (empty($items)): ?>
                    <div class="text-center py-4">
                        <i class="bi bi-box-seam text-muted" style="font-size: 3rem;"></i>
                        <p class="text-muted mb-0 mt-2">
                            <?php if ($selected_machine > 0): ?>
                                No items found for the selected machine
                            <?php else: ?>
                                No items added yet
                            <?php endif; ?>
                        </p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th>Category</th>
                                    <th>Machine</th>
                                    <th>List Type</th>
                                    <th>Price</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items as $item): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($item['name']); ?></td>
                                        <td><?php echo htmlspecialchars($item['category'] ?? 'Uncategorized'); ?></td>
                                        <td><?php echo htmlspecialchars($item['machine_name']); ?></td>
                                        <td><?php echo ucfirst(str_replace('_', ' ', $item['list_type'])); ?></td>
                                        <td>$<?php echo number_format($item['price'], 2); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $item['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                                <?php echo ucfirst($item['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" 
                                                        class="btn btn-outline-primary edit-item" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#editItemModal"
                                                        data-item='<?php echo json_encode($item); ?>'>
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <form method="POST" class="d-inline" 
                                                      onsubmit="return confirm('Are you sure you want to delete this item?');">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                                    <button type="submit" class="btn btn-outline-danger">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <div class="text-muted small">
                                Showing <?php echo (($page - 1) * $items_per_page) + 1; ?> to 
                                <?php echo min($page * $items_per_page, $total_items); ?> of 
                                <?php echo $total_items; ?> items
                            </div>
                            <nav aria-label="Items pagination">
                                <ul class="pagination pagination-sm mb-0">
                                    <!-- Previous Page -->
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo $selected_machine > 0 ? '&machine_id=' . $selected_machine : ''; ?>" aria-label="Previous">
                                                <span aria-hidden="true">&laquo;</span>
                                            </a>
                                        </li>
                                    <?php else: ?>
                                        <li class="page-item disabled">
                                            <span class="page-link" aria-label="Previous">
                                                <span aria-hidden="true">&laquo;</span>
                                            </span>
                                        </li>
                                    <?php endif; ?>

                                    <!-- Page Numbers -->
                                    <?php
                                    $start_page = max(1, $page - 2);
                                    $end_page = min($total_pages, $page + 2);
                                    
                                    if ($start_page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=1<?php echo $selected_machine > 0 ? '&machine_id=' . $selected_machine : ''; ?>">1</a>
                                        </li>
                                        <?php if ($start_page > 2): ?>
                                            <li class="page-item disabled">
                                                <span class="page-link">...</span>
                                            </li>
                                        <?php endif; ?>
                                    <?php endif; ?>

                                    <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?><?php echo $selected_machine > 0 ? '&machine_id=' . $selected_machine : ''; ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>

                                    <?php if ($end_page < $total_pages): ?>
                                        <?php if ($end_page < $total_pages - 1): ?>
                                            <li class="page-item disabled">
                                                <span class="page-link">...</span>
                                            </li>
                                        <?php endif; ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $total_pages; ?><?php echo $selected_machine > 0 ? '&machine_id=' . $selected_machine : ''; ?>">
                                                <?php echo $total_pages; ?>
                                            </a>
                                        </li>
                                    <?php endif; ?>

                                    <!-- Next Page -->
                                    <?php if ($page < $total_pages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo $selected_machine > 0 ? '&machine_id=' . $selected_machine : ''; ?>" aria-label="Next">
                                                <span aria-hidden="true">&raquo;</span>
                                            </a>
                                        </li>
                                    <?php else: ?>
                                        <li class="page-item disabled">
                                            <span class="page-link" aria-label="Next">
                                                <span aria-hidden="true">&raquo;</span>
                                            </span>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Edit Item Modal -->
<div class="modal fade" id="editItemModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="item_id" id="edit_item_id">
                
                <div class="modal-header">
                    <h5 class="modal-title">Edit Item</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_machine_id" class="form-label">Machine</label>
                        <select class="form-select" id="edit_machine_id" name="machine_id" required>
                            <?php foreach ($machines as $machine): ?>
                                <option value="<?php echo $machine['id']; ?>">
                                    <?php echo htmlspecialchars($machine['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_category_id" class="form-label">Category</label>
                        <select class="form-select" id="edit_category_id" name="category_id" required>
                            <option value="0">Select a category</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>">
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_name" class="form-label">Item Name</label>
                        <input type="text" class="form-control" id="edit_name" name="name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_brand" class="form-label">Brand</label>
                        <input type="text" class="form-control" id="edit_brand" name="brand" placeholder="Optional">
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_type" class="form-label">Item Type</label>
                        <select class="form-select" id="edit_type" name="type" required>
                            <option value="snack">Snack</option>
                            <option value="drink">Drink</option>
                            <option value="pizza">Pizza</option>
                            <option value="side">Side</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_list_type" class="form-label">List Type</label>
                        <select class="form-select" id="edit_list_type" name="list_type" required>
                            <option value="regular">Regular - Standard items in the list</option>
                            <option value="vote_in">Vote In - Items that can be voted into the list</option>
                            <option value="vote_out">Vote Out - Items being voted to be removed</option>
                            <option value="showcase">Showcase - Featured or highlighted items</option>
                        </select>
                        <small class="form-text text-muted">
                            Regular: Standard items in the list<br>
                            Vote In: Items that can be voted into the list<br>
                            Vote Out: Items being voted to be removed<br>
                            Showcase: Featured or highlighted items
                        </small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_price" class="form-label">Price</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" class="form-control" id="edit_price" name="price" 
                                   step="0.01" min="0" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_status" class="form-label">Status</label>
                        <select class="form-select" id="edit_status" name="status" required>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle edit item modal
    const editButtons = document.querySelectorAll('.edit-item');
    editButtons.forEach(button => {
        button.addEventListener('click', function() {
            const item = JSON.parse(this.dataset.item);
            document.getElementById('edit_item_id').value = item.id;
            document.getElementById('edit_machine_id').value = item.machine_id;
            document.getElementById('edit_category_id').value = item.category_id;
            document.getElementById('edit_name').value = item.name;
            document.getElementById('edit_brand').value = item.brand;
            document.getElementById('edit_type').value = item.type;
            document.getElementById('edit_list_type').value = item.list_type;
            document.getElementById('edit_price').value = item.price;
            document.getElementById('edit_status').value = item.status;
        });
    });
});
</script>

<?php require_once __DIR__ . '/../core/includes/footer.php'; ?> 