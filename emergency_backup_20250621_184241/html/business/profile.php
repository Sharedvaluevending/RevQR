<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';

// Require business role
require_role('business');

// Fetch business details
$stmt = $pdo->prepare("
    SELECT b.*, u.username, u.email
    FROM businesses b
    JOIN users u ON b.id = u.business_id
    WHERE u.id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$business = $stmt->fetch();

// Ensure business array has all required fields with default values
$business = array_merge([
    'name' => '',
    'slug' => '',
    'username' => '',
    'email' => '',
    'type' => 'vending',
    'created_at' => null
], $business ?: []);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_profile':
                // Update business details
                $stmt = $pdo->prepare("
                    UPDATE businesses 
                    SET name = ?, type = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $_POST['business_name'],
                    $_POST['type'],
                    $business['id']
                ]);
                
                // Update user email
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET email = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $_POST['email'],
                    $_SESSION['user_id']
                ]);
                
                // Handle logo upload
                if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                    $uploadDir = __DIR__ . '/../uploads/logos/';
                    if (!file_exists($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }
                    $fileName = uniqid() . '_' . basename($_FILES['logo']['name']);
                    $uploadFile = $uploadDir . $fileName;
                    if (move_uploaded_file($_FILES['logo']['tmp_name'], $uploadFile)) {
                        // Update logo path in database with the relative path
                        $stmt = $pdo->prepare("
                            UPDATE businesses 
                            SET logo_path = ?
                            WHERE id = ?
                        ");
                        $stmt->execute([
                            'uploads/logos/' . $fileName,
                            $business['id']
                        ]);
                    }
                }
                
                $_SESSION['success'] = 'Profile updated successfully';
                break;
                
            case 'change_password':
                if ($_POST['new_password'] !== $_POST['confirm_password']) {
                    $_SESSION['error'] = 'Passwords do not match';
                } else {
                    // Verify current password
                    $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
                    $stmt->execute([$_SESSION['user_id']]);
                    $user = $stmt->fetch();
                    
                    if (password_verify($_POST['current_password'], $user['password_hash'])) {
                        // Update password
                        $stmt = $pdo->prepare("
                            UPDATE users 
                            SET password_hash = ?
                            WHERE id = ?
                        ");
                        $stmt->execute([
                            password_hash($_POST['new_password'], PASSWORD_DEFAULT),
                            $_SESSION['user_id']
                        ]);
                        $_SESSION['success'] = 'Password changed successfully';
                    } else {
                        $_SESSION['error'] = 'Current password is incorrect';
                    }
                }
                break;
        }
        
        header('Location: profile.php');
        exit;
    }
}

// Include header
require_once __DIR__ . '/../core/includes/header.php';
?>

<div class="container py-4">
    <div class="row">
        <!-- Profile Information -->
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Business Profile</h5>
                </div>
                <div class="card-body">
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
                    
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="update_profile">
                        
                        <div class="mb-3">
                            <label for="business_name" class="form-label">Business Name</label>
                            <input type="text" class="form-control" id="business_name" name="business_name" autocomplete="organization" 
                                   value="<?php echo htmlspecialchars($business['name']); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" autocomplete="email" 
                                   value="<?php echo htmlspecialchars($business['email']); ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="type" class="form-label">Business Type</label>
                            <select id="type" name="type" class="form-control" autocomplete="organization-type" required>
                                <option value="vending" <?php echo ($business['type'] === 'vending') ? 'selected' : ''; ?>>Vending</option>
                                <option value="restaurant" <?php echo ($business['type'] === 'restaurant') ? 'selected' : ''; ?>>Restaurant</option>
                                <option value="cannabis" <?php echo ($business['type'] === 'cannabis') ? 'selected' : ''; ?>>Cannabis</option>
                                <option value="retail" <?php echo ($business['type'] === 'retail') ? 'selected' : ''; ?>>Retail</option>
                                <option value="other" <?php echo ($business['type'] === 'other') ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="logo" class="form-label">Upload Logo</label>
                            <input type="file" class="form-control" id="logo" name="logo" accept="image/*">
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Update Profile</button>
                    </form>
                </div>
            </div>
            
            <!-- Change Password -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Change Password</h5>
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
                            <input type="password" class="form-control" id="new_password" name="new_password" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Change Password</button>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Account Information -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Account Information</h5>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Account Type</dt>
                        <dd class="col-sm-8">Business</dd>
                        
                        <dt class="col-sm-4">Member Since</dt>
                        <dd class="col-sm-8">
                            <?php echo isset($business['created_at']) ? date('M j, Y', strtotime($business['created_at'])) : 'N/A'; ?>
                        </dd>
                        
                        <dt class="col-sm-4">Last Login</dt>
                        <dd class="col-sm-8">
                            <?php 
                            if (isset($_SESSION['last_login'])) {
                                echo date('M j, Y g:i A', strtotime($_SESSION['last_login']));
                            } else {
                                echo 'N/A';
                            }
                            ?>
                        </dd>
                    </dl>
                </div>
            </div>
            
            <!-- Quick Links -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Quick Links</h5>
                </div>
                <div class="card-body">
                    <div class="list-group list-group-flush">
                        <a href="dashboard.php" class="list-group-item list-group-item-action">
                            <i class="bi bi-speedometer2 me-2"></i> Dashboard
                        </a>
                        <a href="edit-items.php" class="list-group-item list-group-item-action">
                            <i class="bi bi-box me-2"></i> Manage Items
                        </a>
                        <a href="qr-generator.php" class="list-group-item list-group-item-action">
                            <i class="bi bi-qr-code me-2"></i> QR Generator
                        </a>
                        <a href="view-votes.php" class="list-group-item list-group-item-action">
                            <i class="bi bi-bar-chart me-2"></i> View Votes
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../core/includes/footer.php'; ?> 