<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/csrf.php';

// Set Content Security Policy
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; font-src 'self' https://cdn.jsdelivr.net; img-src 'self' data:; connect-src 'self'");

// Require business role
require_role('business');

// Generate CSRF token
$csrf_token = generate_csrf_token();

$message = '';
$message_type = '';

// Get business details
$stmt = $pdo->prepare("SELECT business_id FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user || !$user['business_id']) {
    // Create business record if it doesn't exist
    $stmt = $pdo->prepare("INSERT INTO businesses (name, slug) VALUES (?, ?)");
    $slug = 'my-business-' . time(); // Generate a unique slug
    $stmt->execute(['My Business', $slug]);
    $business_id = $pdo->lastInsertId();
    
    // Update user with business_id
    $stmt = $pdo->prepare("UPDATE users SET business_id = ? WHERE id = ?");
    $stmt->execute([$business_id, $_SESSION['user_id']]);
} else {
    $business_id = $user['business_id'];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_voting_list') {
    // Set JSON response header
    header('Content-Type: application/json');
    
    try {
        // Debug logging
        error_log("Form submitted with data: " . print_r($_POST, true));
        error_log("User ID: " . $_SESSION['user_id']);
        
        $pdo->beginTransaction();
        
        // Get business details
        $stmt = $pdo->prepare("SELECT business_id FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        error_log("User data: " . print_r($user, true));
        
        if (!$user || !$user['business_id']) {
            error_log("Creating new business for user");
            // Create business record if it doesn't exist
            $stmt = $pdo->prepare("INSERT INTO businesses (name, slug) VALUES (?, ?)");
            $slug = 'my-business-' . time(); // Generate a unique slug
            $stmt->execute(['My Business', $slug]);
            $business_id = $pdo->lastInsertId();
            error_log("Created business with ID: " . $business_id);
            
            // Update user with business_id
            $stmt = $pdo->prepare("UPDATE users SET business_id = ? WHERE id = ?");
            $stmt->execute([$business_id, $_SESSION['user_id']]);
            error_log("Updated user with business_id");
        } else {
            $business_id = $user['business_id'];
            error_log("Using existing business_id: " . $business_id);
        }
        
        // Insert list items
        $items = json_decode($_POST['items'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Invalid items data: " . json_last_error_msg());
        }
        error_log("Items to insert: " . print_r($items, true));
        
        // Create the voting list (this will also create the machine record via the view)
        $stmt = $pdo->prepare("
            INSERT INTO voting_lists (business_id, name, description)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([
            $business_id,
            $_POST['name'],
            $_POST['description']
        ]);
        
        $voting_list_id = $pdo->lastInsertId();
        error_log("Created voting list with ID: " . $voting_list_id);
        
        // Insert the items
        $stmt = $pdo->prepare("
            INSERT INTO voting_list_items (
                voting_list_id, item_name, item_category,
                retail_price, list_type, is_imported,
                popularity, shelf_life
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        foreach ($items as $item) {
            $allowed_types = ['regular', 'vote_in', 'vote_out', 'showcase'];
            $list_type = isset($item['list_type']) && in_array($item['list_type'], $allowed_types) ? $item['list_type'] : 'regular';
            $stmt->execute([
                $voting_list_id,
                $item['name'],
                $item['category'] ?? $item['type'] ?? 'snack',
                $item['suggested_price'] ?? $item['retail_price'] ?? 0,
                $list_type,
                $item['is_imported'] ?? 0,
                $item['popularity'] ?? 'medium',
                $item['shelf_life'] ?? 30
            ]);
            error_log("Inserted item: " . print_r($item, true));
        }
        
        $pdo->commit();
        
        // Return success response
        echo json_encode([
            'success' => true,
            'message' => 'List created successfully!',
            'redirect' => 'manage-lists.php?message=' . urlencode('List created successfully!') . '&message_type=success'
        ]);
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error creating list: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        
        // Return error response
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error creating list: ' . $e->getMessage()
        ]);
        exit;
    }
}

// Get all master items for selection
$stmt = $pdo->prepare("
    SELECT * FROM master_items 
    WHERE status = 'active'
    ORDER BY category, name
");
$stmt->execute();
$master_items = $stmt->fetchAll();

// Group items by category
$items_by_category = [];
foreach ($master_items as $item) {
    $items_by_category[$item['category']][] = $item;
}

require_once __DIR__ . '/../core/includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Create New List</h1>
            <p class="text-muted">Select items from the master list to create your voting list</p>
        </div>
        <a href="manage-lists.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-2"></i>Back to Lists
        </a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <form id="createListForm" method="POST" class="needs-validation" novalidate autocomplete="off">
        <input type="hidden" name="action" id="formAction" value="create_voting_list" autocomplete="off">
        <input type="hidden" name="csrf_token" id="csrfToken" value="<?php echo $csrf_token; ?>" autocomplete="off">
        <input type="hidden" name="items" id="selectedItemsInput" autocomplete="off">

        <div class="row">
            <div class="col-md-4">
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">List Details</h5>
                        <div class="mb-3">
                            <label for="listName" class="form-label">List Name</label>
                            <input type="text" 
                                   class="form-control" 
                                   id="listName" 
                                   name="name" 
                                   required 
                                   autocomplete="off"
                                   aria-label="List Name"
                                   placeholder="Enter list name">
                            <div class="invalid-feedback">Please enter a list name</div>
                        </div>
                        <div class="mb-3">
                            <label for="listDescription" class="form-label">Description</label>
                            <textarea class="form-control" 
                                      id="listDescription" 
                                      name="description" 
                                      rows="3"
                                      autocomplete="off"
                                      aria-label="List Description"
                                      placeholder="Enter list description"></textarea>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Selected Items</h5>
                        <div id="selectedItems" class="list-group mb-3" role="list" aria-label="Selected Items">
                            <div class="text-center text-muted py-3">
                                No items selected
                            </div>
                        </div>
                        <button type="submit" 
                                class="btn btn-primary w-100" 
                                id="submitBtn" 
                                disabled
                                aria-label="Create List">
                            Create List
                        </button>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Master Items</h5>
                        <div class="mb-3">
                            <label for="searchItems" class="form-label visually-hidden">Search Items</label>
                            <input type="text" 
                                   class="form-control" 
                                   id="searchItems" 
                                   name="search"
                                   autocomplete="off"
                                   aria-label="Search items"
                                   placeholder="Search items...">
                        </div>
                        <div class="accordion" id="itemsAccordion" role="tablist">
                            <?php foreach ($items_by_category as $category => $items): ?>
                                <div class="accordion-item">
                                    <h2 class="accordion-header" role="tab">
                                        <button class="accordion-button collapsed" 
                                                type="button" 
                                                data-bs-toggle="collapse" 
                                                data-bs-target="#category<?php echo md5($category); ?>"
                                                id="categoryHeader<?php echo md5($category); ?>"
                                                name="category_<?php echo md5($category); ?>"
                                                aria-expanded="false"
                                                aria-controls="category<?php echo md5($category); ?>">
                                            <?php echo htmlspecialchars($category); ?>
                                        </button>
                                    </h2>
                                    <div id="category<?php echo md5($category); ?>" 
                                         class="accordion-collapse collapse" 
                                         data-bs-parent="#itemsAccordion"
                                         role="tabpanel"
                                         aria-labelledby="categoryHeader<?php echo md5($category); ?>">
                                        <div class="accordion-body">
                                            <div class="list-group" role="list">
                                                <?php foreach ($items as $item): ?>
                                                    <button type="button" 
                                                            class="list-group-item list-group-item-action d-flex justify-content-between align-items-center item-select"
                                                            data-item='<?php echo htmlspecialchars(json_encode($item)); ?>'
                                                            id="item<?php echo $item['id']; ?>"
                                                            name="item_<?php echo $item['id']; ?>"
                                                            aria-label="Select <?php echo htmlspecialchars($item['name']); ?>"
                                                            autocomplete="off">
                                                        <?php echo htmlspecialchars($item['name']); ?>
                                                        <span class="badge bg-primary rounded-pill">$<?php echo number_format($item['retail_price'], 2); ?></span>
                                                    </button>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Confirmation Modal -->
<div class="modal fade" id="createListModal" tabindex="-1" role="dialog" aria-labelledby="createListModalLabel">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createListModalLabel">Confirm List Creation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to create this list with <span id="itemCount">0</span> items?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirmCreateList">Create List</button>
            </div>
        </div>
    </div>
</div>

<script>
// Store selected items in a Set
const selectedItems = new Set();

// DOM Elements
const form = document.getElementById('createListForm');
const selectedItemsDiv = document.getElementById('selectedItems');
const selectedItemsInput = document.getElementById('selectedItemsInput');
const submitBtn = document.getElementById('submitBtn');
const searchInput = document.getElementById('searchItems');
const createListModal = new bootstrap.Modal(document.getElementById('createListModal'));
const confirmCreateListBtn = document.getElementById('confirmCreateList');
const itemCountSpan = document.getElementById('itemCount');

// Load previously selected items from localStorage
try {
    const savedItems = JSON.parse(localStorage.getItem('selectedItems') || '[]');
    savedItems.forEach(item => addItem(item));
} catch (e) {
    console.error('Error loading saved items:', e);
    localStorage.removeItem('selectedItems');
}

// Handle item selection
document.querySelectorAll('.item-select').forEach(button => {
    button.addEventListener('click', function() {
        try {
            const item = JSON.parse(this.dataset.item);
            if (selectedItems.has(item.id)) {
                removeItem(item);
            } else {
                addItem(item);
            }
        } catch (e) {
            console.error('Error handling item selection:', e);
        }
    });
});

// Handle search
searchInput.addEventListener('input', function() {
    const searchTerm = this.value.toLowerCase();
    document.querySelectorAll('.item-select').forEach(button => {
        const itemName = button.textContent.toLowerCase();
        const category = button.closest('.accordion-item');
        if (itemName.includes(searchTerm)) {
            button.style.display = '';
            category.style.display = '';
        } else {
            button.style.display = 'none';
            // Hide category if all items are hidden
            const visibleItems = category.querySelectorAll('.item-select[style=""]').length;
            category.style.display = visibleItems ? '' : 'none';
        }
    });
});

// Handle form submission
form.addEventListener('submit', function(e) {
    e.preventDefault();
    console.log('Form submitted');
    
    if (selectedItems.size === 0) {
        alert('Please select at least one item');
        return;
    }
    
    try {
        const itemsArray = Array.from(selectedItems);
        console.log('Selected items:', itemsArray);
        
        // Update modal content
        itemCountSpan.textContent = itemsArray.length;
        
        // Show confirmation modal
        createListModal.show();
    } catch (error) {
        console.error('Error preparing form submission:', error);
        alert('Error preparing form submission. Please try again.');
    }
});

// Handle confirmation
confirmCreateListBtn.addEventListener('click', function() {
    try {
        const itemsArray = Array.from(selectedItems);
        selectedItemsInput.value = JSON.stringify(itemsArray);
        console.log('Form data before submit:', {
            name: form.querySelector('#listName').value,
            description: form.querySelector('#listDescription').value,
            items: selectedItemsInput.value
        });
        
        // Validate the form
        if (!form.checkValidity()) {
            form.classList.add('was-validated');
            return;
        }
        
        // Hide modal
        createListModal.hide();
        
        // Submit form using fetch
        fetch(form.action, {
            method: 'POST',
            body: new FormData(form)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Clear localStorage
                localStorage.removeItem('selectedItems');
                // Redirect to success page
                window.location.href = data.redirect;
            } else {
                throw new Error(data.message || 'Unknown error occurred');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert(error.message || 'Error creating list. Please try again.');
        });
    } catch (error) {
        console.error('Error submitting form:', error);
        alert('Error submitting form. Please try again.');
    }
});

// Add item to selection
function addItem(item) {
    selectedItems.add(item);
    updateSelectedItemsDisplay();
    updateSubmitButton();
    saveToLocalStorage();
    // Update button appearance
    const button = document.getElementById('item' + item.id);
    if (button) {
        button.classList.add('selected');
    }
}

// Remove item from selection
function removeItem(item) {
    selectedItems.delete(item);
    updateSelectedItemsDisplay();
    updateSubmitButton();
    saveToLocalStorage();
    // Update button appearance
    const button = document.getElementById('item' + item.id);
    if (button) {
        button.classList.remove('selected');
    }
}

// Update selected items display
function updateSelectedItemsDisplay() {
    if (selectedItems.size === 0) {
        selectedItemsDiv.innerHTML = '<div class="text-center text-muted py-3">No items selected</div>';
        return;
    }

    const items = Array.from(selectedItems);
    const html = items.map(item => {
        const name = document.createTextNode(item.name).textContent;
        const price = parseFloat(item.retail_price).toFixed(2);
        return `
            <div class="list-group-item d-flex justify-content-between align-items-center">
                ${name}
                <div>
                    <span class="badge bg-primary me-2">$${price}</span>
                    <button type="button" class="btn btn-sm btn-outline-danger remove-item" data-item-id="${item.id}">
                        <i class="bi bi-x"></i>
                    </button>
                </div>
            </div>
        `;
    }).join('');
    
    selectedItemsDiv.innerHTML = html;

    // Add event listeners to remove buttons
    selectedItemsDiv.querySelectorAll('.remove-item').forEach(button => {
        button.addEventListener('click', function() {
            const itemId = this.dataset.itemId;
            const item = Array.from(selectedItems).find(i => i.id === itemId);
            if (item) {
                removeItem(item);
            }
        });
    });
}

// Update submit button state
function updateSubmitButton() {
    submitBtn.disabled = selectedItems.size === 0;
}

// Save to localStorage
function saveToLocalStorage() {
    try {
        localStorage.setItem('selectedItems', JSON.stringify(Array.from(selectedItems)));
    } catch (e) {
        console.error('Error saving to localStorage:', e);
    }
}
</script>

<?php require_once __DIR__ . '/../core/includes/footer.php'; ?> 