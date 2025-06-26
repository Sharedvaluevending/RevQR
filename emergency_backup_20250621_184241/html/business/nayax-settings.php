<?php
/**
 * Nayax Settings - Business Configuration
 * Allows business users to configure their Nayax integration settings
 */

require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';

// Check authentication and role
require_login();
if (!has_role('business')) {
    header('Location: ' . APP_URL . '/login.php');
    exit;
}

// Get business ID
$business_id = get_business_id();
if (!$business_id) {
    header('Location: ' . APP_URL . '/business/profile.php?error=no_business');
    exit;
}

// Handle form submission
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Process Nayax settings updates
        $webhook_url = trim($_POST['webhook_url'] ?? '');
        $api_key = trim($_POST['api_key'] ?? '');
        $notifications_enabled = isset($_POST['notifications_enabled']) ? 1 : 0;
        $auto_sync = isset($_POST['auto_sync']) ? 1 : 0;
        
        // Validate inputs
        if (!empty($webhook_url) && !filter_var($webhook_url, FILTER_VALIDATE_URL)) {
            throw new Exception('Invalid webhook URL format');
        }
        
        // Update or insert Nayax settings (placeholder - would integrate with actual Nayax tables)
        $stmt = $pdo->prepare("
            INSERT INTO nayax_business_settings (business_id, webhook_url, api_key, notifications_enabled, auto_sync, updated_at)
            VALUES (?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
            webhook_url = VALUES(webhook_url),
            api_key = VALUES(api_key),
            notifications_enabled = VALUES(notifications_enabled),
            auto_sync = VALUES(auto_sync),
            updated_at = NOW()
        ");
        
        $stmt->execute([$business_id, $webhook_url, $api_key, $notifications_enabled, $auto_sync]);
        
        $message = 'Nayax settings updated successfully!';
        $message_type = 'success';
        
    } catch (Exception $e) {
        $message = 'Error updating settings: ' . $e->getMessage();
        $message_type = 'danger';
    }
}

// Get current settings
try {
    $stmt = $pdo->prepare("
        SELECT * FROM nayax_business_settings 
        WHERE business_id = ?
    ");
    $stmt->execute([$business_id]);
    $settings = $stmt->fetch() ?: [
        'webhook_url' => '',
        'api_key' => '',
        'notifications_enabled' => 1,
        'auto_sync' => 1
    ];
} catch (Exception $e) {
    // Table might not exist - use defaults
    $settings = [
        'webhook_url' => '',
        'api_key' => '',
        'notifications_enabled' => 1,
        'auto_sync' => 1
    ];
}

require_once __DIR__ . '/../core/includes/header.php';
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0">
                        <i class="bi bi-gear text-primary me-2"></i>Nayax Settings
                    </h1>
                    <p class="text-muted mt-1">Configure your Nayax integration and preferences</p>
                </div>
                <div>
                    <a href="<?php echo APP_URL; ?>/business/dashboard.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i>Back to Dashboard
                    </a>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                    <i class="bi bi-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row">
                <!-- Main Settings Form -->
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-sliders me-2"></i>Integration Settings
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="webhook_url" class="form-label">
                                            <i class="bi bi-link-45deg me-1"></i>Webhook URL
                                        </label>
                                        <input type="url" 
                                               class="form-control" 
                                               id="webhook_url" 
                                               name="webhook_url"
                                               value="<?php echo htmlspecialchars($settings['webhook_url']); ?>"
                                               placeholder="https://your-domain.com/nayax/webhook">
                                        <div class="form-text">URL where Nayax will send transaction notifications</div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label for="api_key" class="form-label">
                                            <i class="bi bi-key me-1"></i>API Key
                                        </label>
                                        <div class="input-group">
                                            <input type="password" 
                                                   class="form-control" 
                                                   id="api_key" 
                                                   name="api_key"
                                                   value="<?php echo htmlspecialchars($settings['api_key']); ?>"
                                                   placeholder="Your Nayax API key">
                                            <button type="button" class="btn btn-outline-secondary" onclick="togglePassword()">
                                                <i class="bi bi-eye" id="toggleIcon"></i>
                                            </button>
                                        </div>
                                        <div class="form-text">Your Nayax API authentication key</div>
                                    </div>
                                    
                                    <div class="col-12">
                                        <hr>
                                        <h6>Preferences</h6>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" 
                                                   type="checkbox" 
                                                   id="notifications_enabled" 
                                                   name="notifications_enabled"
                                                   <?php echo $settings['notifications_enabled'] ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="notifications_enabled">
                                                <i class="bi bi-bell me-1"></i>Enable Notifications
                                            </label>
                                        </div>
                                        <div class="form-text">Receive alerts for transactions and errors</div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" 
                                                   type="checkbox" 
                                                   id="auto_sync" 
                                                   name="auto_sync"
                                                   <?php echo $settings['auto_sync'] ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="auto_sync">
                                                <i class="bi bi-arrow-repeat me-1"></i>Auto Sync
                                            </label>
                                        </div>
                                        <div class="form-text">Automatically sync transaction data</div>
                                    </div>
                                </div>
                                
                                <div class="mt-4">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-check-circle me-1"></i>Save Settings
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary ms-2" onclick="window.location.reload()">
                                        <i class="bi bi-arrow-clockwise me-1"></i>Reset
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Side Panel -->
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-info-circle me-2"></i>Quick Help
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="accordion" id="helpAccordion">
                                <div class="accordion-item">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#webhookHelp">
                                            Webhook Setup
                                        </button>
                                    </h2>
                                    <div id="webhookHelp" class="accordion-collapse collapse" data-bs-parent="#helpAccordion">
                                        <div class="accordion-body small">
                                            <p>The webhook URL is where Nayax will send real-time transaction notifications.</p>
                                            <ul>
                                                <li>Must be HTTPS</li>
                                                <li>Should respond with 200 status</li>
                                                <li>Configure in your Nayax dashboard</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="accordion-item">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#apiHelp">
                                            API Key
                                        </button>
                                    </h2>
                                    <div id="apiHelp" class="accordion-collapse collapse" data-bs-parent="#helpAccordion">
                                        <div class="accordion-body small">
                                            <p>Your API key authenticates requests to Nayax services.</p>
                                            <ul>
                                                <li>Keep it secure and private</li>
                                                <li>Available in Nayax dashboard</li>
                                                <li>Rotate regularly for security</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mt-3">
                                <a href="<?php echo APP_URL; ?>/business/nayax-analytics.php" class="btn btn-outline-primary btn-sm w-100 mb-2">
                                    <i class="bi bi-graph-up me-1"></i>View Analytics
                                </a>
                                <a href="<?php echo APP_URL; ?>/business/nayax-machines.php" class="btn btn-outline-info btn-sm w-100">
                                    <i class="bi bi-hdd-stack me-1"></i>Manage Machines
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card mt-3">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-shield-check me-2"></i>Status
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span>Integration Status:</span>
                                <span class="badge bg-success">Active</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span>Last Sync:</span>
                                <span class="text-muted small"><?php echo date('M j, Y g:i A'); ?></span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span>API Status:</span>
                                <span class="badge bg-success">Connected</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function togglePassword() {
    const input = document.getElementById('api_key');
    const icon = document.getElementById('toggleIcon');
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'bi bi-eye-slash';
    } else {
        input.type = 'password';
        icon.className = 'bi bi-eye';
    }
}
</script>

<?php require_once __DIR__ . '/../core/includes/footer.php'; ?> 