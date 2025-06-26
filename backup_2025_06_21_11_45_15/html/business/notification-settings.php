<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/business_utils.php';
require_once __DIR__ . '/../core/notification_system.php';

// Require business role
require_role('business');

$business_id = getOrCreateBusinessId($pdo, $_SESSION['user_id']);
$notificationSystem = new PizzaTrackerNotificationSystem($pdo);

$message = '';
$message_type = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        switch ($_POST['action']) {
            case 'update_preferences':
                $preferences = [
                    'email_enabled' => isset($_POST['email_enabled']) ? 1 : 0,
                    'sms_enabled' => isset($_POST['sms_enabled']) ? 1 : 0,
                    'push_enabled' => isset($_POST['push_enabled']) ? 1 : 0,
                    'email_addresses' => $_POST['email_addresses'] ?? '',
                    'phone_numbers' => $_POST['phone_numbers'] ?? '',
                    'milestones' => $_POST['milestones'] ?? [25, 50, 75, 90, 100]
                ];
                
                $success = $notificationSystem->setNotificationPreferences($business_id, $preferences);
                
                if ($success) {
                    $message = "Notification preferences updated successfully!";
                    $message_type = "success";
                } else {
                    $message = "Error updating notification preferences.";
                    $message_type = "danger";
                }
                break;
                
            case 'test_notification':
                $type = $_POST['test_type'] ?? 'email';
                $success = $notificationSystem->sendTestNotification($business_id, $type);
                
                if ($success) {
                    $message = "Test notification sent successfully!";
                    $message_type = "success";
                } else {
                    $message = "Error sending test notification. Please check your settings.";
                    $message_type = "danger";
                }
                break;
        }
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $message_type = "danger";
    }
}

// Get current preferences
$stmt = $pdo->prepare("
    SELECT * FROM pizza_tracker_notification_preferences 
    WHERE business_id = ? AND is_active = 1
");
$stmt->execute([$business_id]);
$preferences = $stmt->fetch();

// Default preferences if none exist
if (!$preferences) {
    $preferences = [
        'email_enabled' => false,
        'sms_enabled' => false,
        'push_enabled' => false,
        'email_addresses' => '',
        'phone_numbers' => '',
        'milestones' => '[25, 50, 75, 90, 100]'
    ];
}

// Parse milestones JSON
$milestones = json_decode($preferences['milestones'] ?? '[25, 50, 75, 90, 100]', true);

require_once __DIR__ . '/../core/includes/header.php';
?>

<style>
.notification-card {
    border: 1px solid #e9ecef;
    border-radius: 10px;
    transition: all 0.3s ease;
}

.notification-card:hover {
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.notification-toggle {
    transform: scale(1.2);
}

.milestone-badge {
    background: linear-gradient(135deg, #ff6b6b 0%, #feca57 100%);
    color: white;
    border-radius: 20px;
    padding: 5px 15px;
    margin: 2px;
    display: inline-block;
}

.test-section {
    background: #f8f9fa;
    border-radius: 10px;
    padding: 20px;
    margin-top: 20px;
}
</style>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1><i class="bi bi-bell me-2"></i>Notification Settings</h1>
            <p class="text-muted">Configure Pizza Tracker milestone notifications</p>
        </div>
        <a href="pizza-tracker.php" class="btn btn-outline-primary">
            <i class="bi bi-arrow-left me-2"></i>Back to Pizza Tracker
        </a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <form method="POST">
        <input type="hidden" name="action" value="update_preferences">
        
        <div class="row">
            <!-- Email Notifications -->
            <div class="col-md-4 mb-4">
                <div class="notification-card p-4 h-100">
                    <div class="d-flex align-items-center mb-3">
                        <i class="bi bi-envelope-fill text-primary me-3" style="font-size: 2rem;"></i>
                        <div>
                            <h5 class="mb-0">Email Notifications</h5>
                            <small class="text-muted">Get milestone updates via email</small>
                        </div>
                    </div>
                    
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input notification-toggle" type="checkbox" 
                               id="email_enabled" name="email_enabled" 
                               <?php echo $preferences['email_enabled'] ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="email_enabled">
                            Enable Email Notifications
                        </label>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email_addresses" class="form-label">Email Addresses</label>
                        <textarea class="form-control" id="email_addresses" name="email_addresses" 
                                  rows="3" placeholder="email1@example.com, email2@example.com"><?php echo htmlspecialchars($preferences['email_addresses']); ?></textarea>
                        <small class="form-text text-muted">Separate multiple emails with commas</small>
                    </div>
                </div>
            </div>

            <!-- SMS Notifications -->
            <div class="col-md-4 mb-4">
                <div class="notification-card p-4 h-100">
                    <div class="d-flex align-items-center mb-3">
                        <i class="bi bi-phone-fill text-success me-3" style="font-size: 2rem;"></i>
                        <div>
                            <h5 class="mb-0">SMS Notifications</h5>
                            <small class="text-muted">Get milestone updates via text</small>
                        </div>
                    </div>
                    
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input notification-toggle" type="checkbox" 
                               id="sms_enabled" name="sms_enabled" 
                               <?php echo $preferences['sms_enabled'] ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="sms_enabled">
                            Enable SMS Notifications
                        </label>
                    </div>
                    
                    <div class="mb-3">
                        <label for="phone_numbers" class="form-label">Phone Numbers</label>
                        <textarea class="form-control" id="phone_numbers" name="phone_numbers" 
                                  rows="3" placeholder="+1234567890, +0987654321"><?php echo htmlspecialchars($preferences['phone_numbers']); ?></textarea>
                        <small class="form-text text-muted">Include country code, separate with commas</small>
                    </div>
                </div>
            </div>

            <!-- Push Notifications -->
            <div class="col-md-4 mb-4">
                <div class="notification-card p-4 h-100">
                    <div class="d-flex align-items-center mb-3">
                        <i class="bi bi-bell-fill text-warning me-3" style="font-size: 2rem;"></i>
                        <div>
                            <h5 class="mb-0">Push Notifications</h5>
                            <small class="text-muted">Browser push notifications</small>
                        </div>
                    </div>
                    
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input notification-toggle" type="checkbox" 
                               id="push_enabled" name="push_enabled" 
                               <?php echo $preferences['push_enabled'] ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="push_enabled">
                            Enable Push Notifications
                        </label>
                    </div>
                    
                    <div class="alert alert-info">
                        <small>
                            <i class="bi bi-info-circle me-1"></i>
                            Push notifications will be sent to users logged into your business dashboard
                        </small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Milestone Selection -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-flag me-2"></i>Notification Milestones</h5>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3">Select which progress milestones should trigger notifications:</p>
                
                <div class="row">
                    <?php foreach ([25, 50, 75, 90, 100] as $milestone): ?>
                        <div class="col-md-2 mb-2">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" 
                                       name="milestones[]" value="<?php echo $milestone; ?>" 
                                       id="milestone_<?php echo $milestone; ?>"
                                       <?php echo in_array($milestone, $milestones) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="milestone_<?php echo $milestone; ?>">
                                    <span class="milestone-badge"><?php echo $milestone; ?>%</span>
                                </label>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Save Settings -->
        <div class="text-center mb-4">
            <button type="submit" class="btn btn-primary btn-lg">
                <i class="bi bi-check-circle me-2"></i>Save Notification Settings
            </button>
        </div>
    </form>

    <!-- Test Notifications -->
    <div class="test-section">
        <h5><i class="bi bi-send me-2"></i>Test Notifications</h5>
        <p class="text-muted">Send test notifications to verify your settings are working correctly.</p>
        
        <div class="row">
            <div class="col-md-4">
                <form method="POST" class="d-inline">
                    <input type="hidden" name="action" value="test_notification">
                    <input type="hidden" name="test_type" value="email">
                    <button type="submit" class="btn btn-outline-primary w-100" 
                            <?php echo !$preferences['email_enabled'] ? 'disabled' : ''; ?>>
                        <i class="bi bi-envelope me-2"></i>Test Email
                    </button>
                </form>
            </div>
            <div class="col-md-4">
                <form method="POST" class="d-inline">
                    <input type="hidden" name="action" value="test_notification">
                    <input type="hidden" name="test_type" value="sms">
                    <button type="submit" class="btn btn-outline-success w-100" 
                            <?php echo !$preferences['sms_enabled'] ? 'disabled' : ''; ?>>
                        <i class="bi bi-phone me-2"></i>Test SMS
                    </button>
                </form>
            </div>
            <div class="col-md-4">
                <form method="POST" class="d-inline">
                    <input type="hidden" name="action" value="test_notification">
                    <input type="hidden" name="test_type" value="push">
                    <button type="submit" class="btn btn-outline-warning w-100" 
                            <?php echo !$preferences['push_enabled'] ? 'disabled' : ''; ?>>
                        <i class="bi bi-bell me-2"></i>Test Push
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Enable/disable form fields based on toggle switches
document.addEventListener('DOMContentLoaded', function() {
    const toggles = document.querySelectorAll('.notification-toggle');
    
    toggles.forEach(toggle => {
        toggle.addEventListener('change', function() {
            const card = this.closest('.notification-card');
            const inputs = card.querySelectorAll('textarea');
            
            inputs.forEach(input => {
                input.disabled = !this.checked;
                if (!this.checked) {
                    input.style.opacity = '0.5';
                } else {
                    input.style.opacity = '1';
                }
            });
        });
        
        // Trigger initial state
        toggle.dispatchEvent(new Event('change'));
    });
});
</script>

<?php require_once __DIR__ . '/../core/includes/footer.php'; ?> 