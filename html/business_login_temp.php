<?php
require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/core/session.php';

$message = '';
$message_type = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['auto_login'])) {
    // Create/login as business user for testing
    try {
        // Set business session manually for testing
        $_SESSION['user_id'] = 1;
        $_SESSION['role'] = 'business';
        $_SESSION['business_id'] = 1;
        $_SESSION['username'] = 'test_business';
        
        $message = "✅ Business login successful! You can now access the Dynamic QR Manager.";
        $message_type = "success";
        
        // Redirect after successful login
        header("refresh:2;url=qr_dynamic_manager.php");
        
    } catch (Exception $e) {
        $message = "❌ Login failed: " . $e->getMessage();
        $message_type = "danger";
    }
}

require_once __DIR__ . '/core/includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">🏢 Business Login (Temporary)</h4>
                </div>
                <div class="card-body">
                    
                    <?php if ($message): ?>
                        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                            <?php echo $message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <div class="alert alert-info">
                        <strong>🔧 Test Access</strong><br>
                        This creates a temporary business session so you can access the Dynamic QR Manager with proper business role authentication.
                    </div>
                    
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Business Access:</label>
                            <p class="text-muted">Click below to login as a business user and access role-specific features.</p>
                        </div>
                        
                        <button type="submit" name="auto_login" class="btn btn-primary w-100">
                            🔑 Login as Business User
                        </button>
                    </form>
                    
                    <hr>
                    
                    <div class="text-center">
                        <h6>After Login Access:</h6>
                        <div class="list-group list-group-flush">
                            <div class="list-group-item">
                                <a href="qr_dynamic_manager.php" class="text-decoration-none">
                                    🔄 Dynamic QR Manager
                                </a>
                                <small class="text-muted d-block">Manage and edit QR codes by business</small>
                            </div>
                            <div class="list-group-item">
                                <a href="qr-generator.php" class="text-decoration-none">
                                    ➕ QR Generator  
                                </a>
                                <small class="text-muted d-block">Create new QR codes</small>
                            </div>
                            <div class="list-group-item">
                                <a href="qr_manager.php" class="text-decoration-none">
                                    📋 QR Manager
                                </a>
                                <small class="text-muted d-block">View all QR codes</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="mt-3 text-center">
                <small class="text-muted">
                    Current Status: 
                    <?php if (is_logged_in()): ?>
                        ✅ Logged in as <?php echo $_SESSION['role'] ?? 'unknown'; ?> 
                        (Business ID: <?php echo $_SESSION['business_id'] ?? 'none'; ?>)
                    <?php else: ?>
                        ❌ Not logged in
                    <?php endif; ?>
                </small>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/core/includes/footer.php'; ?> 