<?php
require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/core/session.php';
require_once __DIR__ . '/core/auth.php';
require_once __DIR__ . '/core/functions.php';
require_once __DIR__ . '/core/csrf.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();
    
    $username = htmlspecialchars(trim($_POST['username'] ?? ''), ENT_QUOTES, 'UTF-8');
    
    if (empty($username)) {
        $error = 'Please enter your username';
    } else {
        // Check if user exists
        $stmt = $pdo->prepare("SELECT id, username FROM users WHERE username = ? AND status = 'active'");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user) {
            // Generate reset token
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Store token in database
            $stmt = $pdo->prepare("
                INSERT INTO password_resets (user_id, token, expires_at) 
                VALUES (?, ?, ?)
            ");
            
            if ($stmt->execute([$user['id'], $token, $expires])) {
                // Send reset email
                $reset_url = APP_URL . "/reset-password.php?token=" . $token;
                $variables = [
                    'name' => $user['username'],
                    'reset_url' => $reset_url,
                    'expiry_hours' => 1
                ];
                
                if (send_system_email('password_reset', $user['username'], $variables)) {
                    $success = 'Password reset instructions have been sent to your email';
                } else {
                    $error = 'Failed to send reset email. Please try again.';
                }
            } else {
                $error = 'Failed to generate reset token. Please try again.';
            }
        } else {
            // Don't reveal if username exists
            $success = 'If your username exists, you will receive password reset instructions';
        }
    }
}

require_once __DIR__ . '/core/includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6 col-lg-4">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h2 class="text-center mb-4">Reset Password</h2>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <?php echo $success; ?>
                        <div class="mt-2">
                            <a href="<?php echo APP_URL; ?>/login.php" class="btn btn-primary btn-sm">
                                <i class="bi bi-box-arrow-in-right me-2"></i>Back to Login
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <form method="POST" action="">
                        <?php echo csrf_field(); ?>
                        
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                            <div class="form-text">
                                Enter your username to receive reset instructions
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-envelope me-2"></i>Send Reset Instructions
                        </button>
                    </form>

                    <div class="text-center mt-4">
                        <p class="mb-0">Remember your password?</p>
                        <a href="<?php echo APP_URL; ?>/login.php" class="text-decoration-none">Login here</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/core/includes/footer.php'; ?> 