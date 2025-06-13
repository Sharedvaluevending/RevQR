<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/functions.php';

// Ensure user is logged in
if (!is_logged_in()) {
    redirect('/login.php');
}

// Get user data from database
$stmt = $pdo->prepare("SELECT username, role, created_at FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user_data = $stmt->fetch();

// Get success/error messages
$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';

// Clear messages after displaying
if ($success_message) unset($_SESSION['success_message']);
if ($error_message) unset($_SESSION['error_message']);

include '../core/includes/header.php';
?>

<div class="container py-4">
    <?php if ($success_message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle me-2"></i>
            <?php echo htmlspecialchars($success_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <?php echo htmlspecialchars($error_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-4">
            <div class="card">
                <div class="card-body text-center">
                    <!-- Display equipped avatar if available -->
                    <?php 
                    $equipped_avatar_id = getUserEquippedAvatar();
                    $avatar_filename = getAvatarFilename($equipped_avatar_id);
                    ?>
                    <div class="mb-3">
                        <img src="../assets/img/avatars/<?php echo $avatar_filename; ?>" 
                             alt="User Avatar"
                             class="img-fluid"
                             style="width: 100px; height: 100px; object-fit: cover;"
                             onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                        <i class="bi bi-person-circle display-1 text-primary" style="display: none;"></i>
                    </div>
                    <h4 class="card-title"><?php echo htmlspecialchars($user_data['username'] ?? 'User'); ?></h4>
                    <p class="text-muted">
                        <?php echo ucfirst($user_data['role'] ?? 'user'); ?> • 
                        Member since <?php echo date('F Y', strtotime($user_data['created_at'] ?? 'now')); ?>
                    </p>
                    <a href="avatars.php" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-person-badge me-1"></i>
                        Manage Avatars
                    </a>
                </div>
            </div>
        </div>
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Profile Information</h5>
                </div>
                <div class="card-body">
                    <form method="post" action="../core/update-profile.php">
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" name="username" 
                                   value="<?php echo htmlspecialchars($user_data['username'] ?? ''); ?>" 
                                   required minlength="3">
                            <div class="form-text">Username must be at least 3 characters long and unique.</div>
                        </div>
                        <button type="submit" class="btn btn-primary">Update Profile</button>
                    </form>
                </div>
            </div>

            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="mb-0">Change Password</h5>
                </div>
                <div class="card-body">
                    <form method="post" action="../core/update-password.php">
                        <div class="mb-3">
                            <label for="current_password" class="form-label">Current Password</label>
                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                        </div>
                        <div class="mb-3">
                            <label for="new_password" class="form-label">New Password</label>
                            <input type="password" class="form-control" id="new_password" name="new_password" required minlength="6">
                            <div class="form-text">Password must be at least 6 characters long.</div>
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
    </div>
</div>

<script>
// Add client-side password confirmation validation
document.addEventListener('DOMContentLoaded', function() {
    const newPassword = document.getElementById('new_password');
    const confirmPassword = document.getElementById('confirm_password');
    
    function validatePasswordMatch() {
        if (newPassword.value !== confirmPassword.value) {
            confirmPassword.setCustomValidity('Passwords do not match');
        } else {
            confirmPassword.setCustomValidity('');
        }
    }
    
    newPassword.addEventListener('input', validatePasswordMatch);
    confirmPassword.addEventListener('input', validatePasswordMatch);
});
</script>

<?php include '../core/includes/footer.php'; ?> 