<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';

// Require business role
require_role('business');

$message = '';
$message_type = '';

// Get business details
$stmt = $pdo->prepare("SELECT id FROM businesses WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$business = $stmt->fetch();

// Get list ID from request
$list_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get list details
$stmt = $pdo->prepare("
    SELECT * FROM voting_lists 
    WHERE id = ? AND business_id = ?
");
$stmt->execute([$list_id, $business['id']]);
$list = $stmt->fetch();

if (!$list) {
    header('Location: manage-lists.php');
    exit;
}

// Get list items
$stmt = $pdo->prepare("
    SELECT * FROM voting_list_items
    WHERE voting_list_id = ?
    ORDER BY item_name ASC
");
$stmt->execute([$list_id]);
$items = $stmt->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        
        // Update list details
        $stmt = $pdo->prepare("
            UPDATE voting_lists 
            SET name = ?, description = ?
            WHERE id = ? AND business_id = ?
        ");
        $stmt->execute([
            $_POST['name'],
            $_POST['description'],
            $list_id,
            $business['id']
        ]);
        
        // Delete existing items
        $stmt = $pdo->prepare("DELETE FROM voting_list_items WHERE voting_list_id = ?");
        $stmt->execute([$list_id]);
        
        // Insert new items
        $stmt = $pdo->prepare("
            INSERT INTO voting_list_items (
                voting_list_id, item_name, list_type, item_category, 
                retail_price, cost_price, popularity, shelf_life
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        foreach ($_POST['items'] as $item) {
            if (empty($item['item_name'])) continue;
            
            $stmt->execute([
                $list_id,
                $item['item_name'],
                $item['list_type'] ?? 'regular',
                $item['category'],
                $item['retail_price'],
                $item['cost_price'],
                $item['popularity'],
                $item['shelf_life']
            ]);
        }
        
        $pdo->commit();
        $message = "List updated successfully!";
        $message_type = "success";
        
        // Refresh list items
        $stmt = $pdo->prepare("
            SELECT * FROM voting_list_items
            WHERE voting_list_id = ?
            ORDER BY item_name ASC
        ");
        $stmt->execute([$list_id]);
        $items = $stmt->fetchAll();
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "Error updating list: " . $e->getMessage();
        $message_type = "danger";
    }
}

require_once __DIR__ . '/../core/includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Edit Voting List</h1>
            <p class="text-muted">Modify your saved voting list</p>
        </div>
        <a href="manage-lists.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-2"></i>Back to Lists
        </a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <form method="POST" id="editListForm">
        <div class="card mb-4">
            <div class="card-body">
                <div class="mb-3">
                    <label for="name" class="form-label">List Name</label>
                    <input type="text" 
                           class="form-control" 
                           id="name" 
                           name="name" 
                           value="<?php echo htmlspecialchars($list['name']); ?>" 
                           autocomplete="off"
                           required>
                </div>
                <div class="mb-3">
                    <label for="description" class="form-label">Description</label>
                    <textarea class="form-control" 
                              id="description" 
                              name="description" 
                              rows="2"
                              autocomplete="off"><?php echo htmlspecialchars($list['description']); ?></textarea>
                </div>
                <div class="mb-3">
                    <label for="header_image" class="form-label">Header Image</label>
                    <?php if (!empty($list['header_image'])): ?>
                        <div class="mb-2">
                            <img src="<?php echo htmlspecialchars($list['header_image']); ?>" alt="Header Image" class="img-fluid rounded" style="max-height:120px;">
                        </div>
                    <?php endif; ?>
                    <input type="file" class="form-control" id="header_image" name="header_image" accept="image/*">
                    <div class="form-text">Upload a banner/header image for the public voting page. Recommended size: 1200x300px.</div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">List Items</h5>
                <button type="button" class="btn btn-primary btn-sm" id="addItemBtn">
                    <i class="bi bi-plus-lg me-2"></i>Add Item
                </button>
            </div>
            <div class="card-body">
                <div id="itemsContainer">
                    <?php foreach ($items as $index => $item): ?>
                        <div class="item-row mb-3 p-3 border rounded">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h6 class="mb-0">Item #<?php echo $index + 1; ?></h6>
                                <button type="button" class="btn btn-sm btn-outline-danger remove-item">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="item_name_<?php echo $index; ?>" class="form-label">Item Name</label>
                                    <input type="text" 
                                           class="form-control" 
                                           id="item_name_<?php echo $index; ?>"
                                           name="items[<?php echo $index; ?>][item_name]" 
                                           value="<?php echo htmlspecialchars($item['item_name']); ?>" 
                                           autocomplete="off"
                                           required>
                                </div>
                                <div class="col-md-6">
                                    <label for="item_category_<?php echo $index; ?>" class="form-label">Category</label>
                                    <input type="text" 
                                           class="form-control" 
                                           id="item_category_<?php echo $index; ?>"
                                           name="items[<?php echo $index; ?>][category]" 
                                           value="<?php echo htmlspecialchars($item['item_category']); ?>" 
                                           autocomplete="off"
                                           required>
                                </div>
                                <div class="col-md-3">
                                    <label for="item_retail_<?php echo $index; ?>" class="form-label">Retail Price</label>
                                    <input type="number" 
                                           class="form-control" 
                                           id="item_retail_<?php echo $index; ?>"
                                           name="items[<?php echo $index; ?>][retail_price]" 
                                           value="<?php echo $item['retail_price']; ?>" 
                                           step="0.01" 
                                           autocomplete="off"
                                           required>
                                </div>
                                <div class="col-md-3">
                                    <label for="item_cost_<?php echo $index; ?>" class="form-label">Cost Price</label>
                                    <input type="number" 
                                           class="form-control" 
                                           id="item_cost_<?php echo $index; ?>"
                                           name="items[<?php echo $index; ?>][cost_price]" 
                                           value="<?php echo $item['cost_price']; ?>" 
                                           step="0.01" 
                                           autocomplete="off"
                                           required>
                                </div>
                                <div class="col-md-3">
                                    <label for="item_popularity_<?php echo $index; ?>" class="form-label">Popularity</label>
                                    <input type="number" 
                                           class="form-control" 
                                           id="item_popularity_<?php echo $index; ?>"
                                           name="items[<?php echo $index; ?>][popularity]" 
                                           value="<?php echo $item['popularity']; ?>" 
                                           autocomplete="off"
                                           required>
                                </div>
                                <div class="col-md-3">
                                    <label for="item_shelf_life_<?php echo $index; ?>" class="form-label">Shelf Life (days)</label>
                                    <input type="number" 
                                           class="form-control" 
                                           id="item_shelf_life_<?php echo $index; ?>"
                                           name="items[<?php echo $index; ?>][shelf_life]" 
                                           value="<?php echo $item['shelf_life']; ?>" 
                                           autocomplete="off"
                                           required>
                                </div>
                                <div class="col-md-3">
                                    <label for="item_promotion_<?php echo $index; ?>" class="form-label">Promotion</label>
                                    <input type="checkbox" name="items[<?php echo $index; ?>][promotion]" value="1" <?php if (!empty($item['promotion'])) echo 'checked'; ?>>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="mt-4">
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-save me-2"></i>Save Changes
            </button>
            <a href="manage-lists.php" class="btn btn-outline-secondary ms-2">Cancel</a>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const itemsContainer = document.getElementById('itemsContainer');
    const addItemBtn = document.getElementById('addItemBtn');
    let itemCount = <?php echo count($items); ?>;
    
    // Add new item
    addItemBtn.addEventListener('click', function() {
        const itemHtml = `
            <div class="item-row mb-3 p-3 border rounded">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <h6 class="mb-0">Item #${itemCount + 1}</h6>
                    <button type="button" class="btn btn-sm btn-outline-danger remove-item">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Item Name</label>
                        <input type="text" 
                               class="form-control" 
                               name="items[${itemCount}][item_name]" 
                               required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Category</label>
                        <input type="text" 
                               class="form-control" 
                               name="items[${itemCount}][category]" 
                               required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Retail Price</label>
                        <input type="number" 
                               class="form-control" 
                               name="items[${itemCount}][retail_price]" 
                               step="0.01" 
                               required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Cost Price</label>
                        <input type="number" 
                               class="form-control" 
                               name="items[${itemCount}][cost_price]" 
                               step="0.01" 
                               required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Popularity</label>
                        <input type="number" 
                               class="form-control" 
                               name="items[${itemCount}][popularity]" 
                               required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Shelf Life (days)</label>
                        <input type="number" 
                               class="form-control" 
                               name="items[${itemCount}][shelf_life]" 
                               required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Promotion</label>
                        <input type="checkbox" name="items[${itemCount}][promotion]" value="1">
                    </div>
                </div>
            </div>
        `;
        
        itemsContainer.insertAdjacentHTML('beforeend', itemHtml);
        itemCount++;
    });
    
    // Remove item
    itemsContainer.addEventListener('click', function(e) {
        if (e.target.closest('.remove-item')) {
            e.target.closest('.item-row').remove();
        }
    });
});
</script>

<?php require_once __DIR__ . '/../core/includes/footer.php'; ?> 