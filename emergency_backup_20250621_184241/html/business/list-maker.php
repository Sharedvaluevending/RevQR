<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/csrf.php';
require_once __DIR__ . '/../core/business_utils.php';

// Require business role
require_role('business');

// Generate CSRF token
$csrf_token = generate_csrf_token();

$message = '';
$message_type = '';

// Business ID logic is now in business_utils.php

// Get business_id with proper error handling
try {
    $business_id = getOrCreateBusinessId($pdo, $_SESSION['user_id']);
} catch (Exception $e) {
    $message = "Error: " . $e->getMessage();
    $message_type = "danger";
    $business_id = null;
}

// Handle form submission with CSRF protection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_voting_list') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Invalid security token. Please refresh the page and try again.'
        ]);
        exit;
    }
    
    // Validate required fields
    if (empty($_POST['name']) || empty($_POST['items'])) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'List name and items are required.'
        ]);
        exit;
    }
    
    // Validate list name
    $list_name = trim($_POST['name']);
    if (strlen($list_name) < 1 || strlen($list_name) > 255) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'List name must be between 1 and 255 characters.'
        ]);
        exit;
    }
    
    // Validate and parse items
    $items_json = $_POST['items'];
    $items = json_decode($items_json, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Invalid items data format.'
        ]);
        exit;
    }
    
    if (empty($items) || !is_array($items)) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'At least one item is required.'
        ]);
        exit;
    }
    
    // Validate business_id exists
    if (!$business_id) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Business association error. Please contact support.'
        ]);
        exit;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Check if we're using the new voting_lists table or old machines table
        $table_structure = getListTableStructure($pdo);
        $use_voting_lists = ($table_structure === 'voting_lists');
        
        if ($use_voting_lists) {
            // Use new voting_lists table structure
            $stmt = $pdo->prepare("
                INSERT INTO voting_lists (business_id, name, description, status)
                VALUES (?, ?, ?, 'active')
            ");
            $stmt->execute([
                $business_id,
                $list_name,
                'Created via List Maker'
            ]);
            
            $list_id = $pdo->lastInsertId();
            
            // Insert items into voting_list_items
            $stmt = $pdo->prepare("
                INSERT INTO voting_list_items (
                    voting_list_id, item_name, list_type, item_category, 
                    retail_price, cost_price, popularity, shelf_life
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            foreach ($items as $item) {
                $price = isset($item['suggested_price']) ? floatval($item['suggested_price']) : 0;
                $stmt->execute([
                    $list_id,
                    $item['name'],
                    $item['list_type'] ?? 'regular',
                    $item['type'] ?? 'other',
                    $price,
                    0, // cost_price
                    'medium', // popularity
                    30 // shelf_life
                ]);
            }
        } else {
            // Fallback to old machines/items table structure for backward compatibility
            $stmt = $pdo->prepare("
                INSERT INTO machines (business_id, name)
                VALUES (?, ?)
            ");
            $stmt->execute([
                $business_id,
                $list_name
            ]);
            
            $machine_id = $pdo->lastInsertId();
            
            // Insert items
            $stmt = $pdo->prepare("
                INSERT INTO items (
                    machine_id, name, type, price, list_type, status
                ) VALUES (?, ?, ?, ?, ?, 'active')
            ");
            
            foreach ($items as $item) {
                $price = isset($item['suggested_price']) ? floatval($item['suggested_price']) : 0;
                $stmt->execute([
                    $machine_id,
                    $item['name'],
                    $item['type'] ?? 'other',
                    $price,
                    $item['list_type'] ?? 'regular'
                ]);
            }
        }
        
        $pdo->commit();
        
        // Return JSON response
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => 'List created successfully!'
        ]);
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error creating list: " . $e->getMessage());
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Error creating list. Please try again.'
        ]);
        exit;
    }
}

// Get filter parameters with validation
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category = isset($_GET['category']) ? trim($_GET['category']) : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'name';
$page = max(1, intval($_GET['page'] ?? 1));
$itemsPerPage = max(10, min(100, intval($_GET['per_page'] ?? 50))); // Reduced max from 200 to 100

// Whitelist allowed sort options to prevent SQL injection
$allowed_sorts = ['name', 'name_desc', 'category', 'category_desc', 'brand', 'brand_desc'];
if (!in_array($sort, $allowed_sorts)) {
    $sort = 'name';
}

// Build the base query with proper escaping
$query = "
    SELECT mi.*,
           COALESCE(mi.category, 'Other') as display_category
    FROM master_items mi
    WHERE mi.status = 'active'
";
$params = [];

// Add search condition with proper escaping
if (!empty($search) && strlen($search) <= 100) { // Limit search length
    $query .= " AND (mi.name LIKE ? OR mi.brand LIKE ?)";
    $search_param = "%" . $search . "%";
    $params[] = $search_param;
    $params[] = $search_param;
}

// Add category filter with validation
if (!empty($category) && strlen($category) <= 100) { // Limit category length
    $query .= " AND mi.category = ?";
    $params[] = $category;
}

// Add sorting with whitelisted options
switch ($sort) {
    case 'name':
        $query .= " ORDER BY mi.name ASC";
        break;
    case 'name_desc':
        $query .= " ORDER BY mi.name DESC";
        break;
    case 'category':
        $query .= " ORDER BY display_category ASC, mi.name ASC";
        break;
    case 'category_desc':
        $query .= " ORDER BY display_category DESC, mi.name ASC";
        break;
    case 'brand':
        $query .= " ORDER BY mi.brand ASC, mi.name ASC";
        break;
    case 'brand_desc':
        $query .= " ORDER BY mi.brand DESC, mi.name ASC";
        break;
    default:
        $query .= " ORDER BY mi.name ASC";
}

// Get total count for pagination with same filters
$countQuery = "SELECT COUNT(*) as total FROM master_items WHERE status = 'active'";
$countParams = [];

if (!empty($search) && strlen($search) <= 100) {
    $countQuery .= " AND (name LIKE ? OR brand LIKE ?)";
    $countParams[] = $search_param;
    $countParams[] = $search_param;
}

if (!empty($category) && strlen($category) <= 100) {
    $countQuery .= " AND category = ?";
    $countParams[] = $category;
}

try {
    $countStmt = $pdo->prepare($countQuery);
    $countStmt->execute($countParams);
    $totalItems = $countStmt->fetchColumn();
    
    $totalPages = ceil($totalItems / $itemsPerPage);
    
    // Add pagination
    $query .= " LIMIT ? OFFSET ?";
    $params[] = $itemsPerPage;
    $params[] = ($page - 1) * $itemsPerPage;
    
    // Execute the main query
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get all categories for the filter dropdown
    $categories_stmt = $pdo->prepare("
        SELECT DISTINCT category 
        FROM master_items 
        WHERE category IS NOT NULL AND status = 'active'
        ORDER BY category
    ");
    $categories_stmt->execute();
    $categories = $categories_stmt->fetchAll(PDO::FETCH_COLUMN);
    
} catch (Exception $e) {
    error_log("Error fetching items: " . $e->getMessage());
    $items = [];
    $categories = [];
    $totalItems = 0;
    $totalPages = 0;
    $message = "Error loading items. Please refresh the page.";
    $message_type = "danger";
}

require_once __DIR__ . '/../core/includes/header.php';
?>

<style>
/* Custom table styling to fix visibility issues */
#availableItemsTable,
#selectedItemsTable {
    background: rgba(255, 255, 255, 0.15) !important;
    backdrop-filter: blur(20px) !important;
    border-radius: 12px !important;
    overflow: hidden !important;
}

#availableItemsTable thead th,
#selectedItemsTable thead th {
    background: rgba(30, 60, 114, 0.6) !important;
    color: #ffffff !important;
    font-weight: 600 !important;
    border-bottom: 2px solid rgba(255, 255, 255, 0.3) !important;
    padding: 1rem 0.75rem !important;
}

#availableItemsTable tbody td,
#selectedItemsTable tbody td {
    background: rgba(255, 255, 255, 0.12) !important;
    color: rgba(255, 255, 255, 0.95) !important;
    border-bottom: 1px solid rgba(255, 255, 255, 0.15) !important;
    padding: 0.875rem 0.75rem !important;
}

#availableItemsTable tbody tr:hover td,
#selectedItemsTable tbody tr:hover td {
    background: rgba(255, 255, 255, 0.18) !important;
    color: #ffffff !important;
}

/* Button styling inside tables */
#availableItemsTable .btn-outline-primary,
#selectedItemsTable .btn-outline-danger {
    background: rgba(255, 255, 255, 0.9) !important;
    color: #333333 !important;
    border: 1px solid rgba(255, 255, 255, 0.3) !important;
}

#availableItemsTable .btn-outline-primary:hover {
    background: rgba(13, 110, 253, 0.8) !important;
    color: #ffffff !important;
}

#selectedItemsTable .btn-outline-danger:hover {
    background: rgba(220, 53, 69, 0.8) !important;
    color: #ffffff !important;
}

/* Fix form controls inside tables */
/* Table-specific form-select styling removed to use universal approach */

/* Empty state styling */
.table tbody td.text-center.text-muted {
    color: rgba(255, 255, 255, 0.7) !important;
}
</style>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">List Maker</h1>
            <p class="text-muted">Create and manage your item lists</p>
        </div>
        <div>
            <button type="button" class="btn btn-primary" id="saveListBtn">
                <i class="bi bi-save me-2"></i>Save List
            </button>
        </div>
    </div>
    
    <?php if ($message): ?>
        <div class="alert alert-<?php echo htmlspecialchars($message_type); ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Available Items -->
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header bg-white py-3">
                    <div class="row align-items-center g-3">
                        <div class="col">
                            <h5 class="mb-0">Available Items</h5>
                        </div>
                        <div class="col-auto">
                            <form id="searchForm" class="d-flex">
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0">
                                        <i class="bi bi-search"></i>
                                    </span>
                                    <input type="text" 
                                           class="form-control border-start-0" 
                                           id="searchItems" 
                                           name="search" 
                                           placeholder="Search items..." 
                                           value="<?php echo htmlspecialchars($search); ?>"
                                           maxlength="100"
                                           autocomplete="off">
                                    <button class="btn btn-outline-secondary" 
                                            type="button" 
                                            id="clearSearch">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <!-- Filters -->
                    <div class="bg-light p-3 border-bottom">
                        <form id="filterForm" class="row g-3">
                            <div class="col-md-6">
                                <label for="categoryFilter" class="form-label small text-muted">Category</label>
                                <select class="form-select" 
                                        id="categoryFilter" 
                                        name="category" 
                                        autocomplete="off">
                                    <option value="">All Categories</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo htmlspecialchars($cat); ?>"
                                                <?php echo $category === $cat ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cat); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="sortFilter" class="form-label small text-muted">Sort By</label>
                                <select class="form-select" 
                                        id="sortFilter" 
                                        name="sort" 
                                        autocomplete="off">
                                    <option value="name" <?php echo $sort === 'name' ? 'selected' : ''; ?>>Name</option>
                                    <option value="category" <?php echo $sort === 'category' ? 'selected' : ''; ?>>Category</option>
                                    <option value="brand" <?php echo $sort === 'brand' ? 'selected' : ''; ?>>Brand</option>
                                </select>
                            </div>
                        </form>
                    </div>

                    <!-- Items Table -->
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" id="availableItemsTable">
                            <thead class="bg-light">
                                <tr>
                                    <th scope="col" class="ps-4">Item</th>
                                    <th scope="col">Category</th>
                                    <th scope="col" class="pe-4">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($items)): ?>
                                    <tr>
                                        <td colspan="3" class="text-center py-4 text-muted">
                                            No items found
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($items as $item): ?>
                                        <tr>
                                            <td class="ps-4">
                                                <div class="fw-medium"><?php echo htmlspecialchars($item['name']); ?></div>
                                                <small class="text-muted">
                                                    $<?php echo number_format(floatval($item['suggested_price']), 2); ?>
                                                </small>
                                            </td>
                                            <td><?php echo htmlspecialchars($item['display_category']); ?></td>
                                            <td class="pe-4">
                                                <button type="button" 
                                                        class="btn btn-sm btn-outline-primary add-item" 
                                                        data-item='<?php echo htmlspecialchars(json_encode($item), ENT_QUOTES, 'UTF-8'); ?>'>
                                                    <i class="bi bi-plus-lg"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <div class="d-flex justify-content-between align-items-center p-3 bg-light border-top">
                            <div class="text-muted small">
                                Page <?php echo $page; ?> of <?php echo $totalPages; ?> (<?php echo $totalItems; ?> items)
                            </div>
                            <nav aria-label="Items pagination">
                                <ul class="pagination pagination-sm mb-0">
                                    <li class="page-item <?php echo $page === 1 ? 'disabled' : ''; ?>">
                                        <a class="page-link" 
                                           href="?page=<?php echo $page - 1; ?>&per_page=<?php echo $itemsPerPage; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>&sort=<?php echo urlencode($sort); ?>">
                                            <span aria-hidden="true">&laquo;</span>
                                        </a>
                                    </li>
                                    <?php 
                                    $start_page = max(1, $page - 2);
                                    $end_page = min($totalPages, $page + 2);
                                    ?>
                                    <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                        <li class="page-item <?php echo $page === $i ? 'active' : ''; ?>">
                                            <a class="page-link" 
                                               href="?page=<?php echo $i; ?>&per_page=<?php echo $itemsPerPage; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>&sort=<?php echo urlencode($sort); ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                    <li class="page-item <?php echo $page === $totalPages ? 'disabled' : ''; ?>">
                                        <a class="page-link" 
                                           href="?page=<?php echo $page + 1; ?>&per_page=<?php echo $itemsPerPage; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>&sort=<?php echo urlencode($sort); ?>">
                                            <span aria-hidden="true">&raquo;</span>
                                        </a>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Selected Items -->
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0">Selected Items</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" id="selectedItemsTable">
                            <thead class="bg-light">
                                <tr>
                                    <th scope="col" class="ps-4">Item</th>
                                    <th scope="col">List Type</th>
                                    <th scope="col" class="pe-4">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Selected items will be added here dynamically -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectedItems = new Map();
    const selectedItemsTable = document.getElementById('selectedItemsTable').querySelector('tbody');
    const saveListBtn = document.getElementById('saveListBtn');
    const filterForm = document.getElementById('filterForm');
    const searchForm = document.getElementById('searchForm');
    const searchInput = document.getElementById('searchItems');
    const clearSearch = document.getElementById('clearSearch');

    // Load selected items from sessionStorage on page load with error handling
    function loadSelectedItems() {
        try {
            const savedItems = sessionStorage.getItem('selectedItems');
            console.log('Loading saved items:', savedItems);
            if (savedItems) {
                const items = JSON.parse(savedItems);
                if (Array.isArray(items)) {
                    items.forEach(item => {
                        if (item && item.id) {
                            const numericId = parseInt(item.id, 10);
                            if (!isNaN(numericId)) {
                                selectedItems.set(numericId, {
                                    ...item,
                                    id: numericId
                                });
                            }
                        }
                    });
                    console.log('Loaded items:', Array.from(selectedItems.entries()));
                    updateSelectedItemsTable();
                }
            }
        } catch (error) {
            console.error('Error loading selected items:', error);
            sessionStorage.removeItem('selectedItems'); // Clear corrupted data
        }
    }

    // Call loadSelectedItems on page load
    loadSelectedItems();

    // Add item to selected list
    document.querySelectorAll('.add-item').forEach(button => {
        button.addEventListener('click', function() {
            try {
                const item = JSON.parse(this.dataset.item);
                console.log('Adding item:', item);
                if (item && item.id && !selectedItems.has(parseInt(item.id))) {
                    selectedItems.set(parseInt(item.id), {
                        ...item,
                        list_type: 'regular' // Default list type
                    });
                    console.log('Items after adding:', Array.from(selectedItems.keys()));
                    updateSelectedItemsTable();
                    saveSelectedItemsToStorage();
                }
            } catch (error) {
                console.error('Error adding item:', error);
                alert('Error adding item. Please try again.');
            }
        });
    });

    // Update selected items table
    function updateSelectedItemsTable() {
        selectedItemsTable.innerHTML = '';
        console.log('Updating table with items:', Array.from(selectedItems.entries()));
        
        if (selectedItems.size === 0) {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td colspan="3" class="text-center py-4 text-muted">
                    No items selected
                </td>
            `;
            selectedItemsTable.appendChild(row);
            return;
        }
        
        selectedItems.forEach((item, id) => {
            console.log('Creating row for item:', id, item);
            const row = document.createElement('tr');
            const price = parseFloat(item.suggested_price || 0);
            row.innerHTML = `
                <td class="ps-4">
                    <div class="fw-medium">${escapeHtml(item.name)}</div>
                    <small class="text-muted">$${price.toFixed(2)}</small>
                </td>
                <td>
                    <select class="form-select form-select-sm item-type" data-item-id="${id}">
                        <option value="regular" ${item.list_type === 'regular' ? 'selected' : ''}>Regular</option>
                        <option value="in" ${item.list_type === 'in' ? 'selected' : ''}>In</option>
                        <option value="vote_in" ${item.list_type === 'vote_in' ? 'selected' : ''}>Vote In</option>
                        <option value="vote_out" ${item.list_type === 'vote_out' ? 'selected' : ''}>Vote Out</option>
                        <option value="showcase" ${item.list_type === 'showcase' ? 'selected' : ''}>Showcase</option>
                    </select>
                </td>
                <td class="pe-4">
                    <button type="button" class="btn btn-sm btn-link text-danger p-0 remove-item" data-item-id="${id}">
                        <i class="bi bi-trash"></i>
                    </button>
                </td>
            `;
            selectedItemsTable.appendChild(row);
        });

        // Add event listeners to new elements
        document.querySelectorAll('.item-type').forEach(select => {
            select.addEventListener('change', function() {
                const itemId = parseInt(this.dataset.itemId, 10);
                const item = selectedItems.get(itemId);
                if (item) {
                    item.list_type = this.value;
                    selectedItems.set(itemId, item);
                    saveSelectedItemsToStorage();
                }
            });
        });
    }

    // HTML escape function
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    // Handle remove button clicks
    selectedItemsTable.addEventListener('click', function(e) {
        const removeButton = e.target.closest('.remove-item');
        if (removeButton) {
            e.preventDefault();
            e.stopPropagation();
            
            const itemId = parseInt(removeButton.dataset.itemId, 10);
            console.log('Attempting to remove item:', itemId);
            console.log('Current items in Map:', Array.from(selectedItems.entries()));
            
            if (selectedItems.has(itemId)) {
                console.log('Found item to remove');
                selectedItems.delete(itemId);
                console.log('Items after removal:', Array.from(selectedItems.entries()));
                updateSelectedItemsTable();
                saveSelectedItemsToStorage();
                console.log('Item removed successfully');
            } else {
                console.log('Item not found in Map. Available items:', Array.from(selectedItems.keys()));
            }
        }
    });

    // Save selected items to sessionStorage with error handling
    function saveSelectedItemsToStorage() {
        try {
            const items = Array.from(selectedItems.values());
            console.log('Saving items to storage:', items);
            sessionStorage.setItem('selectedItems', JSON.stringify(items));
        } catch (error) {
            console.error('Error saving items to storage:', error);
        }
    }

    // Handle filters without losing selected items
    function submitForm(form) {
        const formData = new FormData(form);
        const params = new URLSearchParams(formData);
        params.set('page', '1');
        window.location.href = '?' + params.toString();
    }

    // Update search as you type with debounce
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

    searchInput.addEventListener('input', debounce(() => {
        submitForm(searchForm);
    }, 500));

    filterForm.addEventListener('change', () => submitForm(filterForm));
    searchForm.addEventListener('submit', (e) => {
        e.preventDefault();
        submitForm(searchForm);
    });

    clearSearch.addEventListener('click', function() {
        searchInput.value = '';
        submitForm(searchForm);
    });

    // Save list with improved validation and error handling
    saveListBtn.addEventListener('click', function() {
        if (selectedItems.size === 0) {
            alert('Please add items to your list first.');
            return;
        }

        const listName = prompt('Enter a name for your list:');
        if (!listName) return;
        
        // Validate list name
        const trimmedName = listName.trim();
        if (trimmedName.length < 1 || trimmedName.length > 255) {
            alert('List name must be between 1 and 255 characters.');
            return;
        }

        const items = Array.from(selectedItems.values());
        const formData = new FormData();
        formData.append('action', 'create_voting_list');
        formData.append('name', trimmedName);
        formData.append('items', JSON.stringify(items));
        formData.append('csrf_token', '<?php echo $csrf_token; ?>');

        // Disable button to prevent double submission
        saveListBtn.disabled = true;
        saveListBtn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Saving...';

        fetch('list-maker.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                // Clear selected items from storage after successful save
                sessionStorage.removeItem('selectedItems');
                window.location.href = 'manage-lists.php?message=' + encodeURIComponent(data.message) + '&message_type=success';
            } else {
                alert('Error: ' + (data.message || 'Unknown error occurred'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while saving the list. Please try again.');
        })
        .finally(() => {
            // Re-enable button
            saveListBtn.disabled = false;
            saveListBtn.innerHTML = '<i class="bi bi-save me-2"></i>Save List';
        });
    });
});
</script>

<?php require_once __DIR__ . '/../core/includes/footer.php'; ?> 