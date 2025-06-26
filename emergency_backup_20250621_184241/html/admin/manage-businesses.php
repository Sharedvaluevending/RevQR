<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';

// Require admin role
require_role('admin');

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                // Check if user exists and is a business
                $stmt = $pdo->prepare("
                    SELECT id FROM users 
                    WHERE id = ? AND role = 'business'
                ");
                $stmt->execute([$_POST['user_id']]);
                if (!$stmt->fetch()) {
                    $_SESSION['error'] = 'Invalid business user selected';
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO businesses (user_id, name, slug)
                        VALUES (?, ?, ?)
                    ");
                    $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $_POST['name'])) . '-' . time();
                    $stmt->execute([
                        $_POST['user_id'],
                        $_POST['name'],
                        $slug
                    ]);
                    $_SESSION['success'] = 'Business added successfully';
                }
                break;
                
            case 'edit':
                $stmt = $pdo->prepare("
                    UPDATE businesses 
                    SET name = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $_POST['name'],
                    $_POST['business_id']
                ]);
                $_SESSION['success'] = 'Business updated successfully';
                break;
                
            case 'delete':
                $stmt = $pdo->prepare("DELETE FROM businesses WHERE id = ?");
                $stmt->execute([$_POST['business_id']]);
                $_SESSION['success'] = 'Business deleted successfully';
                break;
        }
        
        header('Location: manage-businesses.php');
        exit;
    }
}

// Fetch all businesses with their owners
$stmt = $pdo->prepare("
    SELECT b.*, u.username as owner_username, u.email,
           (SELECT COUNT(*) FROM machines WHERE business_id = b.id) as machine_count,
           (SELECT COUNT(*) FROM items i JOIN machines m ON i.machine_id = m.id WHERE m.business_id = b.id) as item_count,
           (SELECT COUNT(*) FROM votes v JOIN items i ON v.item_id = i.id JOIN machines m ON i.machine_id = m.id WHERE m.business_id = b.id) as vote_count,
           (SELECT MAX(v.created_at) FROM votes v JOIN items i ON v.item_id = i.id JOIN machines m ON i.machine_id = m.id WHERE m.business_id = b.id) as last_vote,
           '' as location,
           '' as device_id
    FROM businesses b
    LEFT JOIN users u ON b.user_id = u.id
    ORDER BY b.created_at DESC
");
$stmt->execute();
$businesses = $stmt->fetchAll();

// Fetch business users for dropdown
$stmt = $pdo->prepare("
    SELECT id, username, email 
    FROM users 
    WHERE role = 'business'
    ORDER BY username ASC
");
$stmt->execute();
$business_users = $stmt->fetchAll();

// Include header
require_once __DIR__ . '/../core/includes/header.php';
?>

<style>
/* Admin Table Glass Styling */
.card {
    background: rgba(255, 255, 255, 0.05) !important;
    backdrop-filter: blur(20px) !important;
    border: 1px solid rgba(255, 255, 255, 0.18) !important;
    border-radius: 16px !important;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3) !important;
}

.card-body {
    background: transparent !important;
}

.table {
    background: transparent !important;
    color: #fff !important;
}

.table td,
.table th {
    background: transparent !important;
    border-color: rgba(255, 255, 255, 0.1) !important;
    color: #fff !important;
}

.table thead th {
    background: rgba(0, 0, 0, 0.3) !important;
    border-color: rgba(255, 255, 255, 0.2) !important;
    color: #fff !important;
    font-weight: 600;
}

.table tbody tr {
    background: transparent !important;
    transition: all 0.3s ease;
}

.table tbody tr:hover {
    background: rgba(255, 255, 255, 0.1) !important;
}

.table tbody tr td {
    background: transparent !important;
}

.text-muted {
    color: rgba(255, 255, 255, 0.6) !important;
}

.btn-outline-primary {
    border-color: rgba(13, 110, 253, 0.8);
    color: #0d6efd;
}

.btn-outline-primary:hover {
    background-color: #0d6efd;
    border-color: #0d6efd;
    color: #fff;
}

.btn-outline-danger {
    border-color: rgba(220, 53, 69, 0.8);
    color: #dc3545;
}

.btn-outline-danger:hover {
    background-color: #dc3545;
    border-color: #dc3545;
    color: #fff;
}

.alert {
    background: rgba(255, 255, 255, 0.1) !important;
    border: 1px solid rgba(255, 255, 255, 0.2) !important;
    backdrop-filter: blur(10px) !important;
    color: #fff !important;
}

.alert-success {
    border-color: rgba(25, 135, 84, 0.5) !important;
    background: rgba(25, 135, 84, 0.1) !important;
}

.alert-danger {
    border-color: rgba(220, 53, 69, 0.5) !important;
    background: rgba(220, 53, 69, 0.1) !important;
}

.modal-content {
    background: rgba(255, 255, 255, 0.95) !important;
    backdrop-filter: blur(20px) !important;
    border: 1px solid rgba(255, 255, 255, 0.3) !important;
}

.modal-content .form-label,
.modal-content .modal-title,
.modal-content p {
    color: #333 !important;
}

h1 {
    color: #fff !important;
}
</style>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Manage Businesses</h1>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addBusinessModal">
            <i class="bi bi-plus-lg"></i> Add New Business
        </button>
    </div>
    
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php 
            echo $_SESSION['success'];
            unset($_SESSION['success']);
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php 
            echo $_SESSION['error'];
            unset($_SESSION['error']);
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Business Name</th>
                            <th>Owner</th>
                            <th>Items</th>
                            <th>Votes</th>
                            <th>Last Vote</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($businesses as $business): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($business['name']); ?></td>
                                <td>
                                    <div><?php echo htmlspecialchars($business['owner_username'] ?? 'No Owner'); ?></div>
                                    <small class="text-muted">
                                        <?php echo htmlspecialchars($business['email'] ?? 'No Email'); ?>
                                    </small>
                                </td>
                                <td><?php echo $business['item_count']; ?></td>
                                <td><?php echo $business['vote_count']; ?></td>
                                <td>
                                    <?php if ($business['last_vote']): ?>
                                        <?php echo date('Y-m-d H:i', strtotime($business['last_vote'])); ?>
                                    <?php else: ?>
                                        <span class="text-muted">No votes</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-sm btn-outline-primary" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#editBusinessModal"
                                                data-business='<?php echo htmlspecialchars(json_encode($business)); ?>'>
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-danger"
                                                data-bs-toggle="modal"
                                                data-bs-target="#deleteBusinessModal"
                                                data-business-id="<?php echo $business['id']; ?>"
                                                data-business-name="<?php echo htmlspecialchars($business['name']); ?>">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Business Modal -->
<div class="modal fade" id="addBusinessModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="add">
                
                <div class="modal-header">
                    <h5 class="modal-title">Add New Business</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Business Owner</label>
                        <select class="form-select" name="user_id" required>
                            <option value="">Select Owner</option>
                            <?php foreach ($business_users as $user): ?>
                                <option value="<?php echo $user['id']; ?>">
                                    <?php echo htmlspecialchars($user['username']); ?> 
                                    (<?php echo htmlspecialchars($user['email']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Business Name</label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Business</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Business Modal -->
<div class="modal fade" id="editBusinessModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="business_id" id="editBusinessId">
                
                <div class="modal-header">
                    <h5 class="modal-title">Edit Business</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Business Name</label>
                        <input type="text" class="form-control" name="name" id="editBusinessName" required>
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

<!-- Delete Business Modal -->
<div class="modal fade" id="deleteBusinessModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="business_id" id="deleteBusinessId">
                
                <div class="modal-header">
                    <h5 class="modal-title">Delete Business</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <p>Are you sure you want to delete <strong id="deleteBusinessName"></strong>?</p>
                    <p class="text-danger">This action cannot be undone.</p>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete Business</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Handle edit modal data
document.getElementById('editBusinessModal').addEventListener('show.bs.modal', function (event) {
    const button = event.relatedTarget;
    const business = JSON.parse(button.getAttribute('data-business'));
    
    document.getElementById('editBusinessId').value = business.id;
    document.getElementById('editBusinessName').value = business.name;
});

// Handle delete modal data
document.getElementById('deleteBusinessModal').addEventListener('show.bs.modal', function (event) {
    const button = event.relatedTarget;
    const businessId = button.getAttribute('data-business-id');
    const businessName = button.getAttribute('data-business-name');
    
    document.getElementById('deleteBusinessId').value = businessId;
    document.getElementById('deleteBusinessName').textContent = businessName;
});
</script>

<!-- Clean table styling - high specificity to override header.php -->
<style>
/* Cache-busting comment: <?php echo time(); ?> */
/* High specificity table styling (learned from nuclear approach) */
.card .card-body .table-responsive .table {
    background: rgba(255, 255, 255, 0.08) !important;
    backdrop-filter: blur(10px) !important;
    border-radius: 8px !important;
    overflow: hidden !important;
}

.card .card-body .table-responsive .table thead th {
    background: rgba(255, 255, 255, 0.15) !important;
    color: #ffffff !important;
    border-bottom: 1px solid rgba(255, 255, 255, 0.2) !important;
    font-weight: 600 !important;
}

.card .card-body .table-responsive .table tbody tr {
    border-bottom: 1px solid rgba(255, 255, 255, 0.1) !important;
}

.card .card-body .table-responsive .table tbody tr:hover {
    background: rgba(255, 255, 255, 0.12) !important;
    transform: translateX(2px) !important;
    transition: all 0.2s ease !important;
}

/* Critical: Force white text with high specificity */
.card .card-body .table-responsive .table td,
.card .card-body .table-responsive .table th {
    color: #ffffff !important;
    border-color: rgba(255, 255, 255, 0.1) !important;
    padding: 12px !important;
}

/* Fix nested elements and .text-muted */
.card .card-body .table-responsive .table td div,
.card .card-body .table-responsive .table td small,
.card .card-body .table-responsive .table td span,
.card .card-body .table-responsive .table .text-muted {
    color: rgba(255, 255, 255, 0.75) !important;
}

/* Ensure nested content inherits white color */
.card .card-body .table-responsive .table td * {
    color: inherit !important;
}

/* Button styling */
.card .card-body .table-responsive .table .btn-outline-primary {
    color: #ffffff !important;
    border-color: rgba(255, 255, 255, 0.5) !important;
}

.card .card-body .table-responsive .table .btn-outline-danger {
    color: #ff6b6b !important;
    border-color: rgba(255, 107, 107, 0.5) !important;
}
</style>

<?php require_once __DIR__ . '/../core/includes/footer.php'; ?> 