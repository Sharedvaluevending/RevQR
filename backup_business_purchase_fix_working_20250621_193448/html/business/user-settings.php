<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';

// Require business role
require_role('business');

// Get user and business data
$stmt = $pdo->prepare("
    SELECT u.*, b.name as business_name, b.logo_path, b.type as business_type, b.slug as business_slug
    FROM users u 
    JOIN businesses b ON u.business_id = b.id 
    WHERE u.id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

$success = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'update_profile':
                    $business_name = trim($_POST['business_name']);
                    $business_type = $_POST['business_type'];
                    
                    if (empty($business_name)) {
                        throw new Exception('Business name is required');
                    }
                    
                    // Generate slug from business name
                    $slug = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $business_name));
                    if (empty($slug)) {
                        $slug = 'business' . $user['business_id'];
                    }
                    
                    $stmt = $pdo->prepare("
                        UPDATE businesses 
                        SET name = ?, type = ?, slug = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$business_name, $business_type, $slug, $user['business_id']]);
                    
                    $success = 'Business profile updated successfully!';
                    break;
                    
                case 'change_password':
                    $current_password = $_POST['current_password'];
                    $new_password = $_POST['new_password'];
                    $confirm_password = $_POST['confirm_password'];
                    
                    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                        throw new Exception('All password fields are required');
                    }
                    
                    if (!password_verify($current_password, $user['password_hash'])) {
                        throw new Exception('Current password is incorrect');
                    }
                    
                    if ($new_password !== $confirm_password) {
                        throw new Exception('New passwords do not match');
                    }
                    
                    if (strlen($new_password) < 8) {
                        throw new Exception('New password must be at least 8 characters long');
                    }
                    
                    $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                    $stmt->execute([$new_password_hash, $_SESSION['user_id']]);
                    
                    $success = 'Password changed successfully!';
                    break;
                    
                case 'update_account':
                    $username = trim($_POST['username']);
                    $email = trim($_POST['email']);
                    
                    if (empty($username) || empty($email)) {
                        throw new Exception('Username and email are required');
                    }
                    
                    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        throw new Exception('Invalid email format');
                    }
                    
                    // Check if username/email already exists for other users
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
                    $stmt->execute([$username, $email, $_SESSION['user_id']]);
                    if ($stmt->fetch()) {
                        throw new Exception('Username or email already exists');
                    }
                    
                    $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ? WHERE id = ?");
                    $stmt->execute([$username, $email, $_SESSION['user_id']]);
                    
                    $success = 'Account information updated successfully!';
                    break;
            }
        }
        
        // Refresh user data
        $stmt = $pdo->prepare("
            SELECT u.*, b.name as business_name, b.logo_path, b.type as business_type, b.slug as business_slug
            FROM users u 
            JOIN businesses b ON u.business_id = b.id 
            WHERE u.id = ?
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

require_once __DIR__ . '/../core/includes/header.php';
?>

<div class="container py-4">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php" class="text-decoration-none">Dashboard</a></li>
                    <li class="breadcrumb-item active">User Settings</li>
                </ol>
            </nav>
            <h1 class="mb-2">Account Settings</h1>
            <p class="text-muted">Manage your account and business profile</p>
        </div>
    </div>

    <!-- Alert Messages -->
    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle me-2"></i><?php echo $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i><?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Account Information -->
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-person-circle me-2"></i>Account Information</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="update_account">
                        
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" name="username" 
                                   value="<?php echo htmlspecialchars($user['username']); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Role</label>
                            <input type="text" class="form-control" value="Business User" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Account Created</label>
                            <input type="text" class="form-control" 
                                   value="<?php echo date('F j, Y', strtotime($user['created_at'])); ?>" readonly>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-2"></i>Update Account
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Business Profile -->
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-building me-2"></i>Business Profile</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="update_profile">
                        
                        <div class="mb-3">
                            <label for="business_name" class="form-label">Business Name</label>
                            <input type="text" class="form-control" id="business_name" name="business_name" 
                                   value="<?php echo htmlspecialchars($user['business_name']); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="business_type" class="form-label">Business Type</label>
                            <select class="form-select" id="business_type" name="business_type" required>
                                <option value="vending" <?php echo $user['business_type'] === 'vending' ? 'selected' : ''; ?>>Vending Machines</option>
                                <option value="restaurant" <?php echo $user['business_type'] === 'restaurant' ? 'selected' : ''; ?>>Restaurant</option>
                                <option value="cannabis" <?php echo $user['business_type'] === 'cannabis' ? 'selected' : ''; ?>>Cannabis</option>
                                <option value="retail" <?php echo $user['business_type'] === 'retail' ? 'selected' : ''; ?>>Retail</option>
                                <option value="other" <?php echo $user['business_type'] === 'other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Business Slug</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['business_slug']); ?>" readonly>
                            <div class="form-text">Auto-generated from business name</div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Business Logo</label>
                            <?php if ($user['logo_path']): ?>
                                <div class="mb-2">
                                    <img src="<?php echo APP_URL . '/' . $user['logo_path']; ?>" alt="Business Logo" style="max-height: 100px; max-width: 200px;" class="img-thumbnail">
                                </div>
                                <div class="form-text">Logo is set. Visit the logo upload page to change it.</div>
                            <?php else: ?>
                                <div class="form-text">No logo uploaded. Visit the logo upload page to add one.</div>
                            <?php endif; ?>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-2"></i>Update Profile
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Password Change -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-shield-lock me-2"></i>Change Password</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="change_password">
                        
                        <div class="mb-3">
                            <label for="current_password" class="form-label">Current Password</label>
                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="new_password" class="form-label">New Password</label>
                            <input type="password" class="form-control" id="new_password" name="new_password" 
                                   minlength="8" required>
                            <div class="form-text">Must be at least 8 characters long</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                   minlength="8" required>
                        </div>
                        
                        <button type="submit" class="btn btn-warning">
                            <i class="bi bi-key me-2"></i>Change Password
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-graph-up me-2"></i>Account Summary</h5>
                </div>
                <div class="card-body">
                    <?php
                    // Get quick stats
                    $stmt = $pdo->prepare("SELECT COUNT(*) as machine_count FROM machines WHERE business_id = ?");
                    $stmt->execute([$user['business_id']]);
                    $machine_count = $stmt->fetch()['machine_count'];
                    
                    $stmt = $pdo->prepare("SELECT COUNT(*) as vote_count FROM votes v JOIN machines m ON v.machine_id = m.id WHERE m.business_id = ?");
                    $stmt->execute([$user['business_id']]);
                    $vote_count = $stmt->fetch()['vote_count'];
                    
                    $stmt = $pdo->prepare("SELECT COUNT(*) as sales_count FROM sales WHERE business_id = ?");
                    $stmt->execute([$user['business_id']]);
                    $sales_count = $stmt->fetch()['sales_count'];
                    
                    // Get voting lists count
                    $stmt = $pdo->prepare("SELECT COUNT(*) as voting_lists_count FROM voting_lists WHERE business_id = ?");
                    $stmt->execute([$user['business_id']]);
                    $voting_lists_count = $stmt->fetch()['voting_lists_count'];
                    ?>
                    
                    <div class="row text-center">
                        <div class="col-3">
                            <h4 class="text-info"><?php echo $machine_count; ?></h4>
                            <small class="text-muted">Machines</small>
                        </div>
                        <div class="col-3">
                            <h4 class="text-warning"><?php echo $voting_lists_count; ?></h4>
                            <small class="text-muted">Vote Lists</small>
                        </div>
                        <div class="col-3">
                            <h4 class="text-primary"><?php echo $vote_count; ?></h4>
                            <small class="text-muted">Votes</small>
                        </div>
                        <div class="col-3">
                            <h4 class="text-success"><?php echo $sales_count; ?></h4>
                            <small class="text-muted">Sales</small>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="d-grid gap-2">
                        <a href="dashboard.php" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-speedometer2 me-2"></i>Go to Dashboard
                        </a>
                        <a href="analytics/sales.php" class="btn btn-outline-success btn-sm">
                            <i class="bi bi-graph-up me-2"></i>View Analytics
                        </a>
                        <a href="stock-management.php" class="btn btn-outline-info btn-sm">
                            <i class="bi bi-boxes me-2"></i>Manage Inventory
                        </a>
                        <a href="cross_references_details.php" class="btn btn-outline-warning btn-sm">
                            <i class="bi bi-graph-up-arrow me-2"></i>Cross References
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Password confirmation validation
document.getElementById('confirm_password').addEventListener('input', function() {
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = this.value;
    
    if (newPassword !== confirmPassword) {
        this.setCustomValidity('Passwords do not match');
    } else {
        this.setCustomValidity('');
    }
});

document.getElementById('new_password').addEventListener('input', function() {
    const confirmPassword = document.getElementById('confirm_password');
    confirmPassword.dispatchEvent(new Event('input'));
});
</script>

<?php require_once __DIR__ . '/../core/includes/footer.php'; ?> 