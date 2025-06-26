<?php
require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/core/session.php';
require_once __DIR__ . '/core/includes/header.php';
?>

<style>
.unauthorized-container {
    min-height: 80vh;
    display: flex;
    align-items: center;
    justify-content: center;
}

.unauthorized-card {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 20px;
    padding: 3rem;
    text-align: center;
    max-width: 500px;
    width: 100%;
}

.unauthorized-icon {
    font-size: 4rem;
    color: #ff6b6b;
    margin-bottom: 1rem;
}

.unauthorized-title {
    color: #fff;
    font-size: 2rem;
    margin-bottom: 1rem;
}

.unauthorized-message {
    color: rgba(255, 255, 255, 0.8);
    margin-bottom: 2rem;
    line-height: 1.6;
}

.role-info {
    background: rgba(255, 255, 255, 0.05);
    border-radius: 10px;
    padding: 1rem;
    margin-bottom: 2rem;
}

.role-info h6 {
    color: #ffd700;
    margin-bottom: 0.5rem;
}

.role-info p {
    color: rgba(255, 255, 255, 0.7);
    margin: 0;
    font-size: 0.9rem;
}
</style>

<div class="container unauthorized-container">
    <div class="unauthorized-card">
        <i class="bi bi-shield-exclamation unauthorized-icon"></i>
        <h1 class="unauthorized-title">Access Denied</h1>
        <p class="unauthorized-message">
            You don't have permission to access this page. This area is restricted to business users only.
        </p>
        
        <div class="role-info">
            <h6>Current Session:</h6>
            <p>
                <?php if (is_logged_in()): ?>
                    Logged in as: <strong><?php echo htmlspecialchars($_SESSION['username'] ?? 'Unknown'); ?></strong><br>
                    Role: <strong><?php echo htmlspecialchars($_SESSION['role'] ?? 'None'); ?></strong>
                <?php else: ?>
                    Not logged in
                <?php endif; ?>
            </p>
        </div>
        
        <div class="d-flex gap-3 justify-content-center flex-wrap">
            <?php if (strpos($_SERVER['REQUEST_URI'], 'qr_manager') !== false): ?>
                <a href="qr-display-public.php" class="btn btn-success">
                    <i class="bi bi-eye me-2"></i>View Public QR Gallery
                </a>
            <?php endif; ?>
            
            <?php if (is_logged_in()): ?>
                <a href="user/dashboard.php" class="btn btn-primary">
                    <i class="bi bi-house me-2"></i>Go to Dashboard
                </a>
                <a href="logout.php" class="btn btn-outline-light">
                    <i class="bi bi-box-arrow-right me-2"></i>Logout
                </a>
            <?php else: ?>
                <a href="login.php" class="btn btn-primary">
                    <i class="bi bi-box-arrow-in-right me-2"></i>Login
                </a>
                <a href="register.php" class="btn btn-outline-light">
                    <i class="bi bi-person-plus me-2"></i>Register
                </a>
            <?php endif; ?>
        </div>
        
        <div class="mt-4">
            <p class="small text-muted">
                Need business access? Contact your administrator or 
                <a href="mailto:support@revenueqr.com" class="text-primary">support@revenueqr.com</a>
            </p>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/core/includes/footer.php'; ?> 