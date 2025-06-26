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
                // Check if email already exists
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$_POST['email']]);
                if ($stmt->fetch()) {
                    $_SESSION['error'] = 'Email address already exists';
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO users (username, email, password_hash, role)
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $_POST['name'],
                        $_POST['email'],
                        password_hash($_POST['password'], PASSWORD_DEFAULT),
                        $_POST['role']
                    ]);
                    $_SESSION['success'] = 'User added successfully';
                }
                break;
                
            case 'edit':
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET username = ?, email = ?, role = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $_POST['name'],
                    $_POST['email'],
                    $_POST['role'],
                    $_POST['user_id']
                ]);
                
                // Update password if provided
                if (!empty($_POST['password'])) {
                    $stmt = $pdo->prepare("
                        UPDATE users 
                        SET password_hash = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        password_hash($_POST['password'], PASSWORD_DEFAULT),
                        $_POST['user_id']
                    ]);
                }
                
                $_SESSION['success'] = 'User updated successfully';
                break;
                
            case 'delete':
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$_POST['user_id']]);
                $_SESSION['success'] = 'User deleted successfully';
                break;
        }
        
        header('Location: manage-users.php');
        exit;
    }
}

// Fetch all users
$stmt = $pdo->prepare("
    SELECT u.*, 
           COUNT(DISTINCT b.id) as business_count,
           COUNT(DISTINCT v.id) as vote_count,
           DATE_FORMAT(MAX(v.created_at), '%Y-%m-%d %H:%i') as last_vote
    FROM users u
    LEFT JOIN businesses b ON u.id = b.user_id
    LEFT JOIN votes v ON u.id = v.user_id
    GROUP BY u.id
    ORDER BY u.username ASC
");
$stmt->execute();
$users = $stmt->fetchAll();

// Include header
require_once __DIR__ . '/../core/includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Manage Users</h1>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
            <i class="bi bi-plus-lg"></i> Add New User
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
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Businesses</th>
                            <th>Votes</th>
                            <th>Last Vote</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['username'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($user['email'] ?? ''); ?></td>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo match($user['role']) {
                                            'admin' => 'danger',
                                            'business' => 'primary',
                                            default => 'secondary'
                                        };
                                    ?>">
                                        <?php echo ucfirst($user['role']); ?>
                                    </span>
                                </td>
                                <td><?php echo $user['business_count']; ?></td>
                                <td><?php echo $user['vote_count']; ?></td>
                                <td>
                                    <?php if ($user['last_vote']): ?>
                                        <?php echo $user['last_vote']; ?>
                                    <?php else: ?>
                                        <span class="text-muted">No votes</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-success">
                                        Active
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-sm btn-outline-primary" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#editUserModal"
                                                data-user='<?php echo htmlspecialchars(json_encode($user, JSON_HEX_APOS | JSON_HEX_QUOT)); ?>'>
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-danger"
                                                data-bs-toggle="modal"
                                                data-bs-target="#deleteUserModal"
                                                data-user-id="<?php echo $user['id']; ?>"
                                                data-user-name="<?php echo htmlspecialchars($user['username'] ?? ''); ?>">
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

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="add">
                
                <div class="modal-header">
                    <h5 class="modal-title">Add New User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" class="form-control" name="password" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Role</label>
                        <select class="form-select" name="role" required>
                            <option value="user">User</option>
                            <option value="business">Business</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="user_id" id="editUserId">
                
                <div class="modal-header">
                    <h5 class="modal-title">Edit User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input type="text" class="form-control" name="name" id="editUserName" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email" id="editUserEmail" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">New Password</label>
                        <input type="password" class="form-control" name="password">
                        <small class="text-muted">Leave blank to keep current password</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Role</label>
                        <select class="form-select" name="role" id="editUserRole" required>
                            <option value="user">User</option>
                            <option value="business">Business</option>
                            <option value="admin">Admin</option>
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

<!-- Delete User Modal -->
<div class="modal fade" id="deleteUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="user_id" id="deleteUserId">
                
                <div class="modal-header">
                    <h5 class="modal-title">Delete User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <p>Are you sure you want to delete <strong id="deleteUserName"></strong>?</p>
                    <p class="text-danger">This action cannot be undone.</p>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Handle edit modal data
document.getElementById('editUserModal').addEventListener('show.bs.modal', function (event) {
    const button = event.relatedTarget;
    const user = JSON.parse(button.getAttribute('data-user'));
    
    document.getElementById('editUserId').value = user.id || '';
    document.getElementById('editUserName').value = user.username || '';
    document.getElementById('editUserEmail').value = user.email || '';
    document.getElementById('editUserRole').value = user.role || '';
});

// Handle delete modal data
document.getElementById('deleteUserModal').addEventListener('show.bs.modal', function (event) {
    const button = event.relatedTarget;
    const userId = button.getAttribute('data-user-id');
    const userName = button.getAttribute('data-user-name');
    
    document.getElementById('deleteUserId').value = userId;
    document.getElementById('deleteUserName').textContent = userName;
});
</script>

<?php require_once __DIR__ . '/../core/includes/footer.php'; ?> 