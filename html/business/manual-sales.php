<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/csrf.php';

// Require business role
require_role('business');

// Generate CSRF token
$csrf_token = generate_csrf_token();

$message = '';
$message_type = '';

// Get business details
$stmt = $pdo->prepare("SELECT b.id FROM businesses b JOIN users u ON b.id = u.business_id WHERE u.id = ?");
$stmt->execute([$_SESSION['user_id']]);
$business = $stmt->fetch();
$business_id = $business ? $business['id'] : 0;

if (!$business) {
    header('Location: /business/dashboard.php?error=no_business');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token()) {
    try {
        $voting_list_id = (int)($_POST['voting_list_id'] ?? 0);
        $item_id = (int)($_POST['item_id'] ?? 0);
        $quantity = (int)($_POST['quantity'] ?? 1);
        $sale_price = (float)($_POST['sale_price'] ?? 0);
        $sale_time = $_POST['sale_time'] ?? date('Y-m-d H:i:s');
        $notes = trim($_POST['notes'] ?? '');
        
        if (!$voting_list_id || !$item_id || $quantity <= 0 || $sale_price <= 0) {
            throw new Exception('Please fill in all required fields with valid values');
        }
        
        // Verify voting list belongs to business
        $stmt = $pdo->prepare("SELECT id FROM voting_lists WHERE id = ? AND business_id = ?");
        $stmt->execute([$voting_list_id, $business_id]);
        if (!$stmt->fetch()) {
            throw new Exception('Invalid machine selection');
        }
        
        // Verify item exists in this voting list
        $stmt = $pdo->prepare("SELECT id, item_name, inventory FROM voting_list_items WHERE id = ? AND voting_list_id = ?");
        $stmt->execute([$item_id, $voting_list_id]);
        $item = $stmt->fetch();
        if (!$item) {
            throw new Exception('Item not found in selected machine');
        }
        
        // Check if enough inventory
        if ($item['inventory'] < $quantity) {
            throw new Exception("Insufficient inventory. Only {$item['inventory']} units available.");
        }
        
        $pdo->beginTransaction();
        
        // Insert sale record - try with machine_id first, fallback without it
        try {
            $stmt = $pdo->prepare("
                INSERT INTO sales (business_id, item_id, machine_id, quantity, sale_price, sale_time)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$business_id, $item_id, $voting_list_id, $quantity, $sale_price, $sale_time]);
        } catch (PDOException $e) {
            // If machine_id is not in sales table, try without it
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO sales (business_id, item_id, quantity, sale_price, sale_time)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$business_id, $item_id, $quantity, $sale_price, $sale_time]);
            } catch (PDOException $e2) {
                throw new Exception("Failed to record sale: " . $e2->getMessage());
            }
        }
        
        // Update inventory
        $new_inventory = $item['inventory'] - $quantity;
        $stmt = $pdo->prepare("UPDATE voting_list_items SET inventory = ? WHERE id = ?");
        $stmt->execute([$new_inventory, $item_id]);
        
        // Log the transaction (if stock_log table exists)
        try {
            // Simple approach: Just try the insert and handle failure gracefully
            $stmt = $pdo->prepare("
                INSERT INTO stock_log (business_id, item_id, action_type, quantity, notes, user_id, created_at)
                VALUES (?, ?, 'manual_sale', ?, ?, ?, NOW())
            ");
            $stmt->execute([$business_id, $item_id, $quantity, "Manual sale from voting list: $voting_list_id - $notes", $_SESSION['user_id']]);
            
        } catch (PDOException $e) {
            // If that fails, try with machine_id set to voting_list_id
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO stock_log (business_id, item_id, machine_id, action_type, quantity, notes, user_id, created_at)
                    VALUES (?, ?, ?, 'manual_sale', ?, ?, ?, NOW())
                ");
                $stmt->execute([$business_id, $item_id, $voting_list_id, $quantity, "Manual sale: $notes", $_SESSION['user_id']]);
                
            } catch (PDOException $e2) {
                // Log the error but don't let it break the sale
                error_log("Stock log failed completely in manual-sales.php: " . $e2->getMessage());
                error_log("Original error: " . $e->getMessage());
                error_log("Details: business_id=$business_id, item_id=$item_id, voting_list_id=$voting_list_id, quantity=$quantity");
                // Continue without stock logging
            }
        }
        
        $pdo->commit();
        
        $message = "Sale recorded successfully! Sold {$quantity} units of {$item['item_name']} for $" . number_format($sale_price * $quantity, 2);
        $message_type = "success";
        
        // Clear form data
        $_POST = [];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = $e->getMessage();
        $message_type = "danger";
    }
}

// Get voting lists (machines) for this business
$stmt = $pdo->prepare("SELECT id, name, location FROM voting_lists WHERE business_id = ? ORDER BY name");
$stmt->execute([$business_id]);
$voting_lists = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent sales for display
$stmt = $pdo->prepare("
    SELECT 
        s.id,
        s.quantity,
        s.sale_price,
        s.sale_time,
        vli.item_name,
        vl.name as machine_name
    FROM sales s
    JOIN voting_list_items vli ON s.item_id = vli.id
    JOIN voting_lists vl ON vli.voting_list_id = vl.id
    WHERE s.business_id = ?
    ORDER BY s.sale_time DESC
    LIMIT 20
");
$stmt->execute([$business_id]);
$recent_sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

/* Enhanced text styling for better visibility */
.text-muted {
    color: rgba(255, 255, 255, 0.8) !important;
}

.card-header {
    background: rgba(255, 255, 255, 0.05) !important;
}

.card-header h5 {
    color: rgba(255, 255, 255, 0.95) !important;
}

.form-label {
    color: rgba(255, 255, 255, 0.9) !important;
}

.form-text {
    color: rgba(255, 255, 255, 0.7) !important;
}

/* Small text in tables */
.table small {
    color: rgba(255, 255, 255, 0.8) !important;
}

.table .text-muted {
    color: rgba(255, 255, 255, 0.7) !important;
}

/* Empty state styling */
.text-center h6 {
    color: rgba(255, 255, 255, 0.9) !important;
}

.text-center p {
    color: rgba(255, 255, 255, 0.7) !important;
}

.display-3.text-muted {
    color: rgba(255, 255, 255, 0.5) !important;
}

/* Header section override */
.bg-white {
    background: rgba(255, 255, 255, 0.05) !important;
}

.border-bottom {
    border-color: rgba(255, 255, 255, 0.15) !important;
}

/* Ensure headings are visible */
h1, h2, h3, h4, h5, h6 {
    color: rgba(255, 255, 255, 0.95) !important;
}

/* Required field asterisk */
.text-danger {
    color: rgba(255, 100, 100, 0.9) !important;
}
</style>

<div class="container-fluid px-0">
    <!-- Header Section -->
    <div class="bg-white border-bottom">
        <div class="container py-4">
            <div class="row align-items-center">
                <div class="col">
                    <h1 class="h3 mb-1">
                        <i class="bi bi-cash-coin me-2"></i>Manual Sales Entry
                    </h1>
                    <p class="text-muted mb-0">
                        Record sales transactions manually when items are sold
                    </p>
                </div>
            </div>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show mx-4 mt-4" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="container-fluid py-4">
        <div class="row">
            <!-- Sales Entry Form -->
            <div class="col-lg-6">
                <div class="card shadow-sm">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0">
                            <i class="bi bi-plus-circle me-2"></i>Record New Sale
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="salesForm">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            
                            <div class="mb-3">
                                <label for="voting_list_id" class="form-label">Machine/Location <span class="text-danger">*</span></label>
                                <select class="form-select" id="voting_list_id" name="voting_list_id" required spellcheck="false" autocomplete="off">
                                    <option value="">Select a machine...</option>
                                    <?php foreach ($voting_lists as $vl): ?>
                                        <option value="<?php echo $vl['id']; ?>" 
                                                <?php echo (($_POST['voting_list_id'] ?? '') == $vl['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($vl['name']); ?>
                                            <?php if ($vl['location']): ?>
                                                - <?php echo htmlspecialchars($vl['location']); ?>
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="item_id" class="form-label">Item <span class="text-danger">*</span></label>
                                <select class="form-select" id="item_id" name="item_id" required disabled spellcheck="false" autocomplete="off">
                                    <option value="">First select a machine...</option>
                                </select>
                                <div class="form-text">Available items will load when you select a machine</div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="quantity" class="form-label">Quantity <span class="text-danger">*</span></label>
                                        <input type="number" class="form-control" id="quantity" name="quantity" 
                                               min="1" max="100" value="<?php echo htmlspecialchars($_POST['quantity'] ?? '1'); ?>" required spellcheck="false" autocomplete="off">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="sale_price" class="form-label">Price per Unit <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text">$</span>
                                            <input type="number" class="form-control" id="sale_price" name="sale_price" 
                                                   min="0.01" step="0.01" max="999.99" 
                                                   value="<?php echo htmlspecialchars($_POST['sale_price'] ?? ''); ?>" required spellcheck="false" autocomplete="off">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="sale_time" class="form-label">Sale Date & Time</label>
                                <input type="datetime-local" class="form-control" id="sale_time" name="sale_time" 
                                       value="<?php echo date('Y-m-d\TH:i'); ?>" spellcheck="false" autocomplete="off">
                                <div class="form-text">Leave as current time or adjust if needed</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="notes" class="form-label">Notes (Optional)</label>
                                <textarea class="form-control" id="notes" name="notes" rows="2" 
                                          placeholder="Payment method, customer info, etc." spellcheck="false" autocomplete="off"><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-success">
                                    <i class="bi bi-check-lg me-1"></i>Record Sale
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Recent Sales -->
            <div class="col-lg-6">
                <div class="card shadow-sm">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0">
                            <i class="bi bi-clock-history me-2"></i>Recent Sales
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recent_sales)): ?>
                            <div class="text-center py-4">
                                <i class="bi bi-receipt display-3 text-muted"></i>
                                <h6 class="mt-3">No Sales Yet</h6>
                                <p class="text-muted">Your recorded sales will appear here</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Time</th>
                                            <th>Item</th>
                                            <th>Qty</th>
                                            <th>Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_sales as $sale): ?>
                                            <tr>
                                                <td>
                                                    <small>
                                                        <?php echo date('M j, g:ia', strtotime($sale['sale_time'])); ?>
                                                        <br><span class="text-muted"><?php echo htmlspecialchars($sale['machine_name']); ?></span>
                                                    </small>
                                                </td>
                                                <td><?php echo htmlspecialchars($sale['item_name']); ?></td>
                                                <td><?php echo $sale['quantity']; ?></td>
                                                <td class="fw-bold">$<?php echo number_format($sale['sale_price'] * $sale['quantity'], 2); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const votingListSelect = document.getElementById('voting_list_id');
    const itemSelect = document.getElementById('item_id');
    const salePriceInput = document.getElementById('sale_price');
    
    // AGGRESSIVE: Remove all possible sources of blue lines
    function removeBlueLines(element) {
        if (element) {
            element.setAttribute('spellcheck', 'false');
            element.setAttribute('autocomplete', 'off');
            element.style.textDecoration = 'none';
            element.style.textDecorationLine = 'none';
            element.style.textDecorationStyle = 'none';
            element.style.textDecorationColor = 'transparent';
            element.style.webkitTextDecoration = 'none';
            element.style.webkitTextDecorationLine = 'none';
            element.style.webkitTextDecorationStyle = 'none';
            element.style.webkitTextDecorationColor = 'transparent';
            element.style.mozTextDecoration = 'none';
            element.style.mozTextDecorationLine = 'none';
            element.style.mozTextDecorationStyle = 'none';
            element.style.mozTextDecorationColor = 'transparent';
            
            // Remove all possible focus and active listeners that might add decoration
            element.addEventListener('focus', function(e) {
                this.style.textDecoration = 'none';
                this.style.textDecorationLine = 'none';
                this.style.outline = 'none';
            });
            
            element.addEventListener('click', function(e) {
                this.style.textDecoration = 'none';
                this.style.textDecorationLine = 'none';
                this.style.outline = 'none';
            });
            
            // Also fix all existing options
            if (element.options) {
                Array.from(element.options).forEach(option => {
                    option.style.textDecoration = 'none';
                    option.style.textDecorationLine = 'none';
                    option.style.textDecorationColor = 'transparent';
                    option.setAttribute('spellcheck', 'false');
                });
            }
        }
    }
    
    // Apply fixes to both dropdowns
    removeBlueLines(votingListSelect);
    removeBlueLines(itemSelect);
    
    // Re-apply fixes every 100ms to catch any dynamic changes
    setInterval(function() {
        removeBlueLines(votingListSelect);
        removeBlueLines(itemSelect);
        
        // Fix any dynamically added options
        if (itemSelect && itemSelect.options) {
            Array.from(itemSelect.options).forEach(option => {
                option.style.textDecoration = 'none';
                option.style.textDecorationLine = 'none';
                option.style.textDecorationColor = 'transparent';
                option.setAttribute('spellcheck', 'false');
            });
        }
    }, 100);
    
    // Load items when machine is selected
    votingListSelect.addEventListener('change', function() {
        const votingListId = this.value;
        
        if (!votingListId) {
            itemSelect.innerHTML = '<option value="">First select a machine...</option>';
            itemSelect.disabled = true;
            removeBlueLines(itemSelect);
            return;
        }
        
        // Show loading
        itemSelect.innerHTML = '<option value="">Loading items...</option>';
        itemSelect.disabled = true;
        removeBlueLines(itemSelect);
        
        // Fetch items for this voting list
        fetch(`get_voting_list_items.php?voting_list_id=${votingListId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    itemSelect.innerHTML = '<option value="">Select an item...</option>';
                    
                    data.items.forEach(item => {
                        const option = document.createElement('option');
                        option.value = item.id;
                        
                        // Show different text based on inventory status
                        if (parseInt(item.inventory) > 0) {
                            option.textContent = `${item.item_name} - $${parseFloat(item.retail_price).toFixed(2)} (${item.inventory} in stock)`;
                        } else {
                            option.textContent = `${item.item_name} - $${parseFloat(item.retail_price).toFixed(2)} (OUT OF STOCK)`;
                            option.disabled = true;
                            option.style.color = '#999999';
                        }
                        
                        option.dataset.price = item.retail_price;
                        option.dataset.inventory = item.inventory;
                        option.setAttribute('spellcheck', 'false');
                        option.style.textDecoration = 'none';
                        option.style.textDecorationLine = 'none';
                        option.style.textDecorationColor = 'transparent';
                        option.style.webkitTextDecoration = 'none';
                        option.style.mozTextDecoration = 'none';
                        itemSelect.appendChild(option);
                    });
                    
                    itemSelect.disabled = false;
                    removeBlueLines(itemSelect);
                } else {
                    itemSelect.innerHTML = '<option value="">No items available</option>';
                    removeBlueLines(itemSelect);
                }
            })
            .catch(error => {
                console.error('Error loading items:', error);
                itemSelect.innerHTML = '<option value="">Error loading items</option>';
                removeBlueLines(itemSelect);
            });
    });
    
    // Auto-fill price when item is selected
    itemSelect.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        if (selectedOption.dataset.price && !salePriceInput.value) {
            salePriceInput.value = parseFloat(selectedOption.dataset.price).toFixed(2);
        }
        removeBlueLines(this);
    });
    
    // Form validation
    document.getElementById('salesForm').addEventListener('submit', function(e) {
        const quantity = parseInt(document.getElementById('quantity').value);
        const selectedOption = itemSelect.options[itemSelect.selectedIndex];
        const inventory = parseInt(selectedOption.dataset.inventory || 0);
        
        if (inventory === 0) {
            e.preventDefault();
            alert('Cannot sell out-of-stock items. Please select an item with inventory or restock this item first.');
            return false;
        }
        
        if (quantity > inventory) {
            e.preventDefault();
            alert(`Cannot sell ${quantity} units. Only ${inventory} units available in stock.`);
            return false;
        }
    });
});
</script>

<style>
/* Custom table styling to match other business section tables */
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

/* Badge styling improvements */
.table .badge {
    font-weight: 500 !important;
    padding: 0.375rem 0.5rem !important;
}

/* Empty state styling */
.table tbody td.text-center.text-muted {
    color: rgba(255, 255, 255, 0.7) !important;
}

/* Form controls styling to match business theme */
.form-control {
    background: rgba(255, 255, 255, 0.1) !important;
    border: 1px solid rgba(255, 255, 255, 0.2) !important;
    color: #ffffff !important;
    border-radius: 8px !important;
}

.form-control:focus {
    background: rgba(255, 255, 255, 0.15) !important;
    border-color: #64b5f6 !important;
    box-shadow: 0 0 0 0.25rem rgba(100, 181, 246, 0.25) !important;
    color: #ffffff !important;
    outline: none !important;
}

.form-control::placeholder {
    color: rgba(255, 255, 255, 0.5) !important;
}

/* Form select styling - UNIVERSAL WHITE STYLING */
.form-select {
    background: #ffffff !important;
    border: 1px solid #ced4da !important;
    color: #333333 !important;
    border-radius: 8px !important;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23333333' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m1 6 7 7 7-7'/%3e%3c/svg%3e") !important;
    background-repeat: no-repeat !important;
    background-position: right 0.75rem center !important;
    background-size: 16px 12px !important;
    padding-right: 2.25rem !important;
    text-decoration: none !important;
    spellcheck: false !important;
}

.form-select:focus {
    background: #ffffff !important;
    border-color: #007bff !important;
    box-shadow: 0 0 0 0.25rem rgba(0, 123, 255, 0.25) !important;
    color: #333333 !important;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23007bff' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m1 6 7 7 7-7'/%3e%3c/svg%3e") !important;
    outline: none !important;
    text-decoration: none !important;
}

/* Form select option styling */
.form-select option,
select.form-select option,
select option {
    background: #ffffff !important;
    color: #333333 !important;
    font-family: inherit !important;
    text-decoration: none !important;
    font-style: normal !important;
    font-weight: normal !important;
    padding: 8px 12px !important;
}

.form-select option:checked {
    background: #007bff !important;
    color: #ffffff !important;
}

/* Input group styling */
.input-group-text {
    background: rgba(255, 255, 255, 0.1) !important;
    border: 1px solid rgba(255, 255, 255, 0.2) !important;
    color: #ffffff !important;
    border-radius: 8px 0 0 8px !important;
}

.input-group .form-control {
    border-radius: 0 8px 8px 0 !important;
    border-left: 0 !important;
}

.input-group .form-control:focus {
    border-left: 0 !important;
}

/* Form labels */
.form-label {
    color: rgba(255, 255, 255, 0.9) !important;
    font-weight: 500 !important;
}

/* Form text */
.form-text {
    color: rgba(255, 255, 255, 0.6) !important;
}

/* Card styling improvements */
.card {
    background: rgba(255, 255, 255, 0.12) !important;
    backdrop-filter: blur(20px) !important;
    border: 1px solid rgba(255, 255, 255, 0.15) !important;
    border-radius: 12px !important;
}

.card-header.bg-white {
    background: rgba(255, 255, 255, 0.08) !important;
    backdrop-filter: blur(15px) !important;
    border-bottom: 1px solid rgba(255, 255, 255, 0.15) !important;
    color: rgba(255, 255, 255, 0.9) !important;
}

.card-body {
    background: transparent !important;
}

/* Disabled state styling */
.form-select:disabled,
.form-control:disabled {
    background: rgba(255, 255, 255, 0.05) !important;
    border-color: rgba(255, 255, 255, 0.1) !important;
    color: rgba(255, 255, 255, 0.4) !important;
    opacity: 0.6 !important;
}

/* Text muted improvements */
.text-muted {
    color: rgba(255, 255, 255, 0.6) !important;
}

/* Required field indicator */
.text-danger {
    color: #ff6b6b !important;
}

/* Button improvements */
.btn-success {
    background: linear-gradient(135deg, #2e7d32 0%, #1b5e20 100%) !important;
    border: none !important;
    box-shadow: 0 4px 15px rgba(46, 125, 50, 0.3) !important;
    border-radius: 8px !important;
}

.btn-success:hover {
    background: linear-gradient(135deg, #388e3c 0%, #2e7d32 100%) !important;
    transform: translateY(-1px) !important;
    box-shadow: 0 6px 20px rgba(46, 125, 50, 0.4) !important;
}

/* CRITICAL: Fix for blue squiggly lines in dropdowns */
.form-select,
select.form-select,
select {
    spellcheck: false !important;
    -webkit-text-decoration-skip: none !important;
    text-decoration: none !important;
    text-decoration-line: none !important;
    text-decoration-style: none !important;
    text-decoration-color: transparent !important;
    text-underline-offset: unset !important;
    text-decoration-thickness: 0 !important;
    -webkit-text-decoration-line: none !important;
    -webkit-text-decoration-style: none !important;
    -webkit-text-decoration-color: transparent !important;
    -moz-text-decoration-line: none !important;
    -moz-text-decoration-style: none !important;
    -moz-text-decoration-color: transparent !important;
}

/* CRITICAL: Fix for blue lines when clicking/focusing dropdowns */
.form-select:focus,
.form-select:active,
.form-select:focus-visible,
.form-select:focus-within,
select.form-select:focus,
select.form-select:active,
select.form-select:focus-visible,
select.form-select:focus-within,
select:focus,
select:active,
select:focus-visible,
select:focus-within {
    spellcheck: false !important;
    text-decoration: none !important;
    text-decoration-line: none !important;
    text-decoration-style: none !important;
    text-decoration-color: transparent !important;
    text-underline-offset: unset !important;
    text-decoration-thickness: 0 !important;
    -webkit-text-decoration: none !important;
    -webkit-text-decoration-line: none !important;
    -webkit-text-decoration-style: none !important;
    -webkit-text-decoration-color: transparent !important;
    -moz-text-decoration: none !important;
    -moz-text-decoration-line: none !important;
    -moz-text-decoration-style: none !important;
    -moz-text-decoration-color: transparent !important;
    -webkit-text-decoration-skip: none !important;
    text-decoration-skip-ink: none !important;
    outline: none !important;
    border-color: #64b5f6 !important;
    box-shadow: 0 0 0 0.25rem rgba(100, 181, 246, 0.25) !important;
}

/* Remove any browser default focus styling that might cause lines */
.form-select::-moz-focus-inner,
select.form-select::-moz-focus-inner,
select::-moz-focus-inner {
    border: 0 !important;
    outline: none !important;
    text-decoration: none !important;
}

.form-select::-webkit-focus-ring-color,
select.form-select::-webkit-focus-ring-color,
select::-webkit-focus-ring-color {
    color: transparent !important;
}

/* Additional focus state fixes */
.form-select[data-bs-focus="true"],
.form-select.focus,
select.form-select[data-bs-focus="true"],
select.form-select.focus,
select[data-bs-focus="true"],
select.focus {
    text-decoration: none !important;
    text-decoration-line: none !important;
    text-decoration-style: none !important;
    text-decoration-color: transparent !important;
}

.form-select option,
select.form-select option,
select option {
    background: #ffffff !important;
    color: #333333 !important;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
    text-decoration: none !important;
    text-decoration-line: none !important;
    text-decoration-style: none !important;
    text-decoration-color: transparent !important;
    text-underline-offset: unset !important;
    text-decoration-thickness: 0 !important;
    -webkit-text-decoration-line: none !important;
    -webkit-text-decoration-style: none !important;
    -webkit-text-decoration-color: transparent !important;
    -moz-text-decoration-line: none !important;
    -moz-text-decoration-style: none !important;
    -moz-text-decoration-color: transparent !important;
    font-style: normal !important;
    font-weight: normal !important;
    padding: 8px 12px !important;
    border: none !important;
    outline: none !important;
    spellcheck: false !important;
}

/* Fix for when options are focused/selected */
.form-select option:focus,
.form-select option:active,
.form-select option:hover,
select.form-select option:focus,
select.form-select option:active,
select.form-select option:hover,
select option:focus,
select option:active,
select option:hover {
    text-decoration: none !important;
    text-decoration-line: none !important;
    text-decoration-style: none !important;
    text-decoration-color: transparent !important;
    background: #f8f9fa !important;
    color: #333333 !important;
    outline: none !important;
    border: none !important;
}

/* Additional fixes for text decoration issues */
.form-select *,
select.form-select *,
select * {
    text-decoration: none !important;
    text-decoration-line: none !important;
    text-decoration-style: none !important;
    text-decoration-color: transparent !important;
    -webkit-text-decoration: none !important;
    -moz-text-decoration: none !important;
}

/* Override any global link or text styling that might affect options */
.form-select option:link,
.form-select option:visited,
.form-select option:hover,
.form-select option:active,
select.form-select option:link,
select.form-select option:visited,
select.form-select option:hover,
select.form-select option:active,
select option:link,
select option:visited,
select option:hover,
select option:active {
    text-decoration: none !important;
    text-decoration-line: none !important;
    text-decoration-style: none !important;
    text-decoration-color: transparent !important;
    color: #333333 !important;
}

/* Ensure no underlines from any parent elements */
.form-select,
.form-select *,
select.form-select,
select.form-select *,
select,
select * {
    text-underline-position: unset !important;
    text-decoration-skip-ink: none !important;
    -webkit-text-decoration-skip: none !important;
}

/* NUCLEAR OPTION: Maximum specificity override for all possible selectors */
body div.container-fluid div.row div.col-lg-6 div.card div.card-body form div.mb-3 select.form-select,
body div.container-fluid div.row div.col-lg-6 div.card div.card-body form div.mb-3 select.form-select:focus,
body div.container-fluid div.row div.col-lg-6 div.card div.card-body form div.mb-3 select.form-select:active,
body div.container-fluid div.row div.col-lg-6 div.card div.card-body form div.mb-3 select.form-select:focus-visible,
#voting_list_id,
#voting_list_id:focus,
#voting_list_id:active,
#voting_list_id:focus-visible,
#item_id,
#item_id:focus,
#item_id:active,
#item_id:focus-visible {
    text-decoration: none !important;
    text-decoration-line: none !important;
    text-decoration-style: none !important;
    text-decoration-color: transparent !important;
    text-underline-offset: unset !important;
    text-decoration-thickness: 0 !important;
    -webkit-text-decoration: none !important;
    -webkit-text-decoration-line: none !important;
    -webkit-text-decoration-style: none !important;
    -webkit-text-decoration-color: transparent !important;
    -moz-text-decoration: none !important;
    -moz-text-decoration-line: none !important;
    -moz-text-decoration-style: none !important;
    -moz-text-decoration-color: transparent !important;
    -webkit-text-decoration-skip: none !important;
    text-decoration-skip-ink: none !important;
    outline: none !important;
    spellcheck: false !important;
}

/* Force override any possible browser defaults with inline styles */
#voting_list_id option,
#item_id option,
#voting_list_id option:focus,
#item_id option:focus,
#voting_list_id option:active,
#item_id option:active,
#voting_list_id option:hover,
#item_id option:hover {
    text-decoration: none !important;
    text-decoration-line: none !important;
    text-decoration-style: none !important;
    text-decoration-color: transparent !important;
    background: #ffffff !important;
    color: #333333 !important;
    border: none !important;
    outline: none !important;
}
</style>

<?php require_once __DIR__ . '/../core/includes/footer.php'; ?> 