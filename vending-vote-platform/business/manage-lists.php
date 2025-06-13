<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';

// Require business role
require_role('business');

$message = '';
$message_type = '';

// Get message from URL if present
if (isset($_GET['message']) && isset($_GET['message_type'])) {
    $message = $_GET['message'];
    $message_type = $_GET['message_type'];
}

// Get business details
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

// Handle list deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_list') {
    try {
        $list_id = (int)$_POST['list_id'];
        
        $pdo->beginTransaction();
        
        // Delete list items first (due to foreign key constraint)
        $stmt = $pdo->prepare("DELETE FROM items WHERE list_id = ?");
        $stmt->execute([$list_id]);
        
        // Delete campaign associations
        $stmt = $pdo->prepare("DELETE FROM campaign_lists WHERE list_id = ?");
        $stmt->execute([$list_id]);
        
        // Delete the list
        $stmt = $pdo->prepare("DELETE FROM lists WHERE id = ? AND business_id = ?");
        $stmt->execute([$list_id, $business_id]);
        
        $pdo->commit();
        $message = "List deleted successfully!";
        $message_type = "success";
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "Error deleting list: " . $e->getMessage();
        $message_type = "danger";
    }
}

// Get all lists for this business
$stmt = $pdo->prepare("
    SELECT m.*, 
           COUNT(i.id) as item_count,
           DATE_FORMAT(m.created_at, '%Y-%m-%d %H:%i') as formatted_date,
           GROUP_CONCAT(DISTINCT c.name) as campaign_names,
           GROUP_CONCAT(
               CONCAT(
                   i.list_type,
                   ':',
                   COUNT(i.id)
               )
           ) as type_counts
    FROM machines m
    LEFT JOIN items i ON m.id = i.machine_id
    LEFT JOIN campaign_lists cl ON m.id = cl.machine_id
    LEFT JOIN campaigns c ON cl.campaign_id = c.id
    WHERE m.business_id = ?
    GROUP BY m.id
    ORDER BY m.created_at DESC
");
$stmt->execute([$business_id]);
$lists = $stmt->fetchAll();

// Process type counts for each list
foreach ($lists as &$list) {
    $typeCounts = [
        'regular' => 0,
        'vote_in' => 0,
        'vote_out' => 0,
        'showcase' => 0
    ];
    
    if ($list['type_counts']) {
        foreach (explode(',', $list['type_counts']) as $count) {
            list($type, $count) = explode(':', $count);
            $typeCounts[$type] = (int)$count;
        }
    }
    
    $list['type_counts'] = $typeCounts;
}
unset($list);

require_once __DIR__ . '/../core/includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Manage Lists</h1>
            <p class="text-muted">View, edit, and delete your saved lists</p>
        </div>
        <a href="list-maker.php" class="btn btn-primary">
            <i class="bi bi-plus-lg me-2"></i>Create New List
        </a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <?php if (empty($lists)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-list-ul display-1 text-muted"></i>
                    <h3 class="mt-3">No Lists Found</h3>
                    <p class="text-muted">Create your first list to get started</p>
                    <a href="list-maker.php" class="btn btn-primary">
                        <i class="bi bi-plus-lg me-2"></i>Create New List
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Items</th>
                                <th>Campaigns</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lists as $list): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($list['name']); ?></td>
                                    <td>
                                        <?php echo $list['item_count']; ?> items
                                        <div class="small text-muted">
                                            <?php
                                            $typeLabels = [];
                                            if ($list['type_counts']['regular'] > 0) $typeLabels[] = $list['type_counts']['regular'] . ' regular';
                                            if ($list['type_counts']['vote_in'] > 0) $typeLabels[] = $list['type_counts']['vote_in'] . ' vote in';
                                            if ($list['type_counts']['vote_out'] > 0) $typeLabels[] = $list['type_counts']['vote_out'] . ' vote out';
                                            if ($list['type_counts']['showcase'] > 0) $typeLabels[] = $list['type_counts']['showcase'] . ' showcase';
                                            echo implode(', ', $typeLabels);
                                            ?>
                                        </div>
                                    </td>
                                    <td><?php echo $list['campaign_names'] ? htmlspecialchars($list['campaign_names']) : 'None'; ?></td>
                                    <td><?php echo $list['formatted_date']; ?></td>
                                    <td>
                                        <div class="btn-group">
                                            <button type="button" 
                                                    class="btn btn-sm btn-outline-primary view-list" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#viewListModal"
                                                    data-list-id="<?php echo $list['id']; ?>">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <a href="edit-list.php?id=<?php echo $list['id']; ?>" 
                                               class="btn btn-sm btn-outline-secondary">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <a href="/vote.php?list=<?php echo $list['id']; ?>" target="_blank" class="btn btn-sm btn-outline-success" title="View Public Vote Page">
                                                <i class="bi bi-box-arrow-up-right"></i>
                                            </a>
                                            <button type="button" 
                                                    class="btn btn-sm btn-outline-danger delete-list"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#deleteListModal"
                                                    data-list-id="<?php echo $list['id']; ?>"
                                                    data-list-name="<?php echo htmlspecialchars($list['name']); ?>">
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

<!-- View List Modal -->
<div class="modal fade" id="viewListModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">View List Items</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Item Name</th>
                                <th>Type</th>
                                <th>Category</th>
                                <th>Price</th>
                            </tr>
                        </thead>
                        <tbody id="viewListItems">
                            <!-- Items will be loaded here via AJAX -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delete List Modal -->
<div class="modal fade" id="deleteListModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete List</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete the list "<span id="deleteListName"></span>"?</p>
                <p class="text-danger">This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <form method="POST">
                    <input type="hidden" name="action" value="delete_list">
                    <input type="hidden" name="list_id" id="deleteListId">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete List</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle view list button click
    document.querySelectorAll('.view-list').forEach(button => {
        button.addEventListener('click', function() {
            const listId = this.dataset.listId;
            fetch(`get-list-items.php?id=${listId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const tbody = document.getElementById('viewListItems');
                        tbody.innerHTML = '';
                        
                        data.items.forEach(item => {
                            const typeIcons = {
                                'regular': 'bi-list-ul text-primary',
                                'vote_in': 'bi-plus-circle text-success',
                                'vote_out': 'bi-dash-circle text-danger',
                                'showcase': 'bi-star text-warning'
                            };
                            
                            const typeLabels = {
                                'regular': 'Regular',
                                'vote_in': 'Vote In',
                                'vote_out': 'Vote Out',
                                'showcase': 'Showcase'
                            };
                            
                            const row = document.createElement('tr');
                            row.innerHTML = `
                                <td>${item.item_name}</td>
                                <td>
                                    <i class="bi ${typeIcons[item.list_type]}"></i>
                                    ${typeLabels[item.list_type]}
                                </td>
                                <td>${item.item_category}</td>
                                <td>$${parseFloat(item.retail_price).toFixed(2)}</td>
                            `;
                            tbody.appendChild(row);
                        });
                    }
                });
        });
    });
    
    // Handle delete list button click
    document.querySelectorAll('.delete-list').forEach(button => {
        button.addEventListener('click', function() {
            document.getElementById('deleteListId').value = this.dataset.listId;
            document.getElementById('deleteListName').textContent = this.dataset.listName;
        });
    });
});
</script>

<?php require_once __DIR__ . '/../core/includes/footer.php'; ?> 