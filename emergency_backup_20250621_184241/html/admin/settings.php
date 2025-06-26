<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/functions.php';

// Ensure admin access
require_role('admin');

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'update_spin_settings':
            $settings = [
                'spin_hoodie_probability' => floatval($_POST['hoodie_probability']),
                'spin_bogo_probability' => floatval($_POST['bogo_probability']),
                'spin_nothing_probability' => floatval($_POST['nothing_probability']),
                'spin_cooldown_hours' => intval($_POST['cooldown_hours'])
            ];
            
            $stmt = $pdo->prepare("
                UPDATE system_settings 
                SET value = :value 
                WHERE setting_key = :key
            ");
            
            foreach ($settings as $key => $value) {
                $stmt->execute(['key' => $key, 'value' => $value]);
            }
            
            $_SESSION['success'] = "Spin settings updated successfully!";
            break;
            
        case 'update_qr_settings':
            $settings = [
                'qr_default_size' => intval($_POST['default_size']),
                'qr_default_margin' => intval($_POST['default_margin']),
                'qr_default_error_correction' => $_POST['error_correction'],
                'qr_max_logo_size' => intval($_POST['max_logo_size'])
            ];
            
            $stmt = $pdo->prepare("
                UPDATE system_settings 
                SET value = :value 
                WHERE setting_key = :key
            ");
            
            foreach ($settings as $key => $value) {
                $stmt->execute(['key' => $key, 'value' => $value]);
            }
            
            $_SESSION['success'] = "QR settings updated successfully!";
            break;
            
        case 'update_system_limits':
            $settings = [
                'max_votes_per_ip' => intval($_POST['max_votes_per_ip']),
                'max_qr_codes_per_business' => intval($_POST['max_qr_codes']),
                'max_items_per_business' => intval($_POST['max_items']),
                'max_file_upload_size' => intval($_POST['max_file_size'])
            ];
            
            $stmt = $pdo->prepare("
                UPDATE system_settings 
                SET value = :value 
                WHERE setting_key = :key
            ");
            
            foreach ($settings as $key => $value) {
                $stmt->execute(['key' => $key, 'value' => $value]);
            }
            
            $_SESSION['success'] = "System limits updated successfully!";
            break;
            
        case 'update_email_templates':
            $templates = [
                'welcome_email' => $_POST['welcome_email'],
                'password_reset' => $_POST['password_reset'],
                'prize_notification' => $_POST['prize_notification'],
                'business_approval' => $_POST['business_approval']
            ];
            
            $stmt = $pdo->prepare("
                UPDATE email_templates 
                SET template_content = :content 
                WHERE template_name = :name
            ");
            
            foreach ($templates as $name => $content) {
                $stmt->execute(['name' => $name, 'content' => $content]);
            }
            
            $_SESSION['success'] = "Email templates updated successfully!";
            break;
    }
    
    redirect('/admin/settings.php');
}

// Fetch current settings
$stmt = $pdo->query("SELECT setting_key, value FROM system_settings");
$settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Fetch email templates
$stmt = $pdo->query("SELECT template_name, template_content FROM email_templates");
$templates = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

include '../core/includes/header.php';
?>

<div class="container py-4">
    <h1 class="mb-4">System Settings</h1>
    
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success">
            <?php 
            echo $_SESSION['success'];
            unset($_SESSION['success']);
            ?>
        </div>
    <?php endif; ?>
    
    <div class="row">
        <!-- Spin Settings -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Spin Settings</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="update_spin_settings">
                        
                        <div class="mb-3">
                            <label class="form-label">Hoodie Probability (%)</label>
                            <input type="number" class="form-control" name="hoodie_probability" 
                                   value="<?php echo $settings['spin_hoodie_probability'] ?? 5; ?>" 
                                   min="0" max="100" step="0.1">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">BOGO Probability (%)</label>
                            <input type="number" class="form-control" name="bogo_probability" 
                                   value="<?php echo $settings['spin_bogo_probability'] ?? 15; ?>" 
                                   min="0" max="100" step="0.1">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Nothing Probability (%)</label>
                            <input type="number" class="form-control" name="nothing_probability" 
                                   value="<?php echo $settings['spin_nothing_probability'] ?? 80; ?>" 
                                   min="0" max="100" step="0.1">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Spin Cooldown (hours)</label>
                            <input type="number" class="form-control" name="cooldown_hours" 
                                   value="<?php echo $settings['spin_cooldown_hours'] ?? 24; ?>" 
                                   min="1" max="168">
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Update Spin Settings</button>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- QR Settings -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">QR Code Settings</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="update_qr_settings">
                        
                        <div class="mb-3">
                            <label class="form-label">Default Size (px)</label>
                            <input type="number" class="form-control" name="default_size" 
                                   value="<?php echo $settings['qr_default_size'] ?? 300; ?>" 
                                   min="100" max="1000">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Default Margin (px)</label>
                            <input type="number" class="form-control" name="default_margin" 
                                   value="<?php echo $settings['qr_default_margin'] ?? 10; ?>" 
                                   min="0" max="50">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Default Error Correction</label>
                            <select class="form-select" name="error_correction">
                                <option value="L" <?php echo ($settings['qr_default_error_correction'] ?? 'M') === 'L' ? 'selected' : ''; ?>>Low (7%)</option>
                                <option value="M" <?php echo ($settings['qr_default_error_correction'] ?? 'M') === 'M' ? 'selected' : ''; ?>>Medium (15%)</option>
                                <option value="Q" <?php echo ($settings['qr_default_error_correction'] ?? 'M') === 'Q' ? 'selected' : ''; ?>>Quartile (25%)</option>
                                <option value="H" <?php echo ($settings['qr_default_error_correction'] ?? 'M') === 'H' ? 'selected' : ''; ?>>High (30%)</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Max Logo Size (px)</label>
                            <input type="number" class="form-control" name="max_logo_size" 
                                   value="<?php echo $settings['qr_max_logo_size'] ?? 50; ?>" 
                                   min="10" max="200">
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Update QR Settings</button>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- System Limits -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">System Limits</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="update_system_limits">
                        
                        <div class="mb-3">
                            <label class="form-label">Max Votes per IP (per day)</label>
                            <input type="number" class="form-control" name="max_votes_per_ip" 
                                   value="<?php echo $settings['max_votes_per_ip'] ?? 10; ?>" 
                                   min="1" max="100">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Max QR Codes per Business</label>
                            <input type="number" class="form-control" name="max_qr_codes" 
                                   value="<?php echo $settings['max_qr_codes_per_business'] ?? 50; ?>" 
                                   min="1" max="1000">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Max Items per Business</label>
                            <input type="number" class="form-control" name="max_items" 
                                   value="<?php echo $settings['max_items_per_business'] ?? 100; ?>" 
                                   min="1" max="1000">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Max File Upload Size (MB)</label>
                            <input type="number" class="form-control" name="max_file_size" 
                                   value="<?php echo $settings['max_file_upload_size'] ?? 5; ?>" 
                                   min="1" max="50">
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Update System Limits</button>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Email Templates -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Email Templates</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="update_email_templates">
                        
                        <div class="mb-3">
                            <label class="form-label">Welcome Email</label>
                            <textarea class="form-control" name="welcome_email" rows="4"><?php echo htmlspecialchars($templates['welcome_email'] ?? ''); ?></textarea>
                            <small class="text-muted">Available variables: {name}, {email}, {login_url}</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Password Reset</label>
                            <textarea class="form-control" name="password_reset" rows="4"><?php echo htmlspecialchars($templates['password_reset'] ?? ''); ?></textarea>
                            <small class="text-muted">Available variables: {name}, {reset_url}, {expiry_hours}</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Prize Notification</label>
                            <textarea class="form-control" name="prize_notification" rows="4"><?php echo htmlspecialchars($templates['prize_notification'] ?? ''); ?></textarea>
                            <small class="text-muted">Available variables: {name}, {prize_name}, {prize_value}, {claim_url}</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Business Approval</label>
                            <textarea class="form-control" name="business_approval" rows="4"><?php echo htmlspecialchars($templates['business_approval'] ?? ''); ?></textarea>
                            <small class="text-muted">Available variables: {business_name}, {owner_name}, {login_url}</small>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Update Email Templates</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../core/includes/footer.php'; ?> 