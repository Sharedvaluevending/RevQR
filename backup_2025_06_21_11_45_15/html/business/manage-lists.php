<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/business_utils.php';

// Require business role
require_role('business');

$message = '';
$message_type = '';

// Get message from URL if present
if (isset($_GET['message']) && isset($_GET['message_type'])) {
    $message = $_GET['message'];
    $message_type = $_GET['message_type'];
}

// Get business_id with proper error handling
try {
    $business_id = getOrCreateBusinessId($pdo, $_SESSION['user_id']);
} catch (Exception $e) {
    $message = "Error: " . $e->getMessage();
    $message_type = "danger";
    $business_id = null;
}

// Handle list deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_list') {
    if (!$business_id) {
        $message = "Business association error. Cannot delete list.";
        $message_type = "danger";
    } else {
        try {
            $list_id = (int)$_POST['list_id'];
            
            // Validate that the list belongs to this business
            if (!validateBusinessAccess($pdo, $_SESSION['user_id'], $business_id)) {
                throw new Exception("Access denied");
            }
            
            $pdo->beginTransaction();
            
            // Check which table structure we're using
            $table_structure = getListTableStructure($pdo);
            
            if ($table_structure === 'voting_lists') {
                // Delete list items first (due to foreign key constraint)
                $stmt = $pdo->prepare("DELETE FROM voting_list_items WHERE voting_list_id = ?");
                $stmt->execute([$list_id]);
                // Delete the list
                $stmt = $pdo->prepare("DELETE FROM voting_lists WHERE id = ? AND business_id = ?");
                $stmt->execute([$list_id, $business_id]);
            } else {
                // Fallback to old structure
                $stmt = $pdo->prepare("DELETE FROM items WHERE machine_id = ?");
                $stmt->execute([$list_id]);
                $stmt = $pdo->prepare("DELETE FROM machines WHERE id = ? AND business_id = ?");
                $stmt->execute([$list_id, $business_id]);
            }
            
            $pdo->commit();
            $message = "List deleted successfully!";
            $message_type = "success";
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Error deleting list: " . $e->getMessage());
            $message = "Error deleting list. Please try again.";
            $message_type = "danger";
        }
    }
}

// Get all lists for this business
$lists = [];
if ($business_id) {
    try {
        $table_structure = getListTableStructure($pdo);
        
        if ($table_structure === 'voting_lists') {
            $stmt = $pdo->prepare("
                SELECT l.*, 
                       COUNT(i.id) as item_count,
                       DATE_FORMAT(l.created_at, '%Y-%m-%d %H:%i') as formatted_date
                FROM voting_lists l
                LEFT JOIN voting_list_items i ON l.id = i.voting_list_id
                WHERE l.business_id = ?
                GROUP BY l.id
                ORDER BY l.created_at DESC
            ");
        } else {
            // Fallback to old structure
            $stmt = $pdo->prepare("
                SELECT m.id, m.name, 
                       COUNT(i.id) as item_count,
                       DATE_FORMAT(m.created_at, '%Y-%m-%d %H:%i') as formatted_date
                FROM machines m
                LEFT JOIN items i ON m.id = i.machine_id
                WHERE m.business_id = ?
                GROUP BY m.id
                ORDER BY m.created_at DESC
            ");
        }
        
        $stmt->execute([$business_id]);
        $lists = $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Error fetching lists: " . $e->getMessage());
        $message = "Error loading lists. Please refresh the page.";
        $message_type = "danger";
    }
}

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
.table .btn-outline-secondary,
.table .btn-outline-success,
.table .btn-outline-danger {
    background: rgba(255, 255, 255, 0.1) !important;
    border-color: rgba(255, 255, 255, 0.3) !important;
    color: rgba(255, 255, 255, 0.9) !important;
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

/* Empty state styling */
.table tbody td.text-center.text-muted {
    color: rgba(255, 255, 255, 0.7) !important;
}

/* Enhanced empty state */
.text-center.py-4 .text-muted {
    color: rgba(255, 255, 255, 0.8) !important;
}
</style>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Manage Lists</h1>
            <p class="text-muted">View, edit, and delete your saved lists</p>
            <div class="alert alert-info py-2 mt-2 mb-0">
                <small>
                    <i class="bi bi-info-circle me-1"></i>
                    <strong>Note:</strong> List names shown here are your internal list names. You'll specify the machine name when creating QR codes.
                </small>
            </div>
        </div>
        <a href="list-maker.php" class="btn btn-primary">
            <i class="bi bi-plus-lg me-2"></i>Create New List
        </a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo htmlspecialchars($message_type); ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <?php if (empty($lists)): ?>
                <div class="text-center py-4">
                    <p class="text-muted mb-0">No lists found</p>
                    <a href="list-maker.php" class="btn btn-primary mt-3">
                        <i class="bi bi-plus-lg me-2"></i>Create Your First List
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>List Name</th>
                                <th>Items</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lists as $list): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($list['name']); ?></td>
                                    <td><?php echo intval($list['item_count']); ?> items</td>
                                    <td><?php echo htmlspecialchars($list['formatted_date']); ?></td>
                                    <td>
                                        <div class="btn-group">
                                            <?php 
                                            $table_structure = getListTableStructure($pdo);
                                            $edit_param = ($table_structure === 'voting_lists') ? 'voting_list_id' : 'machine_id';
                                            ?>
                                            <a href="edit-items.php?<?php echo $edit_param; ?>=<?php echo $list['id']; ?>" 
                                               class="btn btn-sm btn-outline-secondary" title="Edit Items">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <a href="/vote.php?list=<?php echo $list['id']; ?>" 
                                               target="_blank" 
                                               class="btn btn-sm btn-outline-success" 
                                               title="View Public Vote Page">
                                                <i class="bi bi-box-arrow-up-right"></i>
                                            </a>
                                            <form method="POST" action="" style="display:inline;">
                                                <input type="hidden" name="action" value="delete_list">
                                                <input type="hidden" name="list_id" value="<?php echo $list['id']; ?>">
                                                <button type="submit" 
                                                        class="btn btn-sm btn-outline-danger" 
                                                        onclick="return confirm('Are you sure you want to delete this list? This action cannot be undone.');"
                                                        title="Delete List">
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
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../core/includes/footer.php'; ?> 