<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/business_utils.php';
require_once __DIR__ . '/../core/pizza_tracker_utils.php';

// Require business role
require_role('business');

$message = '';
$message_type = '';

// Get business_id with proper error handling
try {
    $business_id = getOrCreateBusinessId($pdo, $_SESSION['user_id']);
} catch (Exception $e) {
    $message = "Error: " . $e->getMessage();
    $message_type = "danger";
    $business_id = null;
}

// Initialize pizza tracker utility
$pizzaTracker = new PizzaTracker($pdo);

// Get current tracker ID from query params
$current_tracker_id = isset($_GET['tracker_id']) ? (int)$_GET['tracker_id'] : null;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $business_id) {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'create_tracker':
                    $tracker_id = $pizzaTracker->createTracker(
                        $business_id,
                        $_POST['name'],
                        $_POST['description'],
                        (float)$_POST['pizza_cost'],
                        (float)$_POST['revenue_goal'],
                        $_POST['tracker_type'] ?? 'campaign',
                        !empty($_POST['campaign_id']) ? (int)$_POST['campaign_id'] : null
                    );
                    
                    if ($tracker_id) {
                        $message = "Pizza tracker created successfully!";
                        $message_type = "success";
                        $current_tracker_id = $tracker_id;
                    } else {
                        $message = "Error creating pizza tracker.";
                        $message_type = "danger";
                    }
                    break;
                    
                case 'add_revenue':
                    $success = $pizzaTracker->addRevenue(
                        (int)$_POST['tracker_id'],
                        (float)$_POST['revenue_amount'],
                        'manual',
                        $_POST['notes'] ?? '',
                        $_SESSION['user_id']
                    );
                    
                    if ($success) {
                        $message = "Revenue added successfully!";
                        $message_type = "success";
                    } else {
                        $message = "Error adding revenue.";
                        $message_type = "danger";
                    }
                    break;
                    
                case 'reset_tracker':
                    $success = $pizzaTracker->resetTracker((int)$_POST['tracker_id']);
                    if ($success) {
                        $message = "Tracker reset successfully!";
                        $message_type = "success";
                    } else {
                        $message = "Error resetting tracker.";
                        $message_type = "danger";
                    }
                    break;
                    
                case 'update_promo':
                    $tracker_id = (int)$_POST['tracker_id'];
                    $promo_message = trim($_POST['promo_message']);
                    $promo_active = isset($_POST['promo_active']) ? 1 : 0;
                    $promo_expire_date = !empty($_POST['promo_expire_date']) ? $_POST['promo_expire_date'] : null;
                    
                    // Update promotional message
                    $stmt = $pdo->prepare("
                        UPDATE pizza_trackers 
                        SET promo_message = ?, promo_active = ?, promo_expire_date = ?, promo_updated_at = NOW()
                        WHERE id = ? AND business_id = ?
                    ");
                    
                    $success = $stmt->execute([
                        $promo_message,
                        $promo_active,
                        $promo_expire_date,
                        $tracker_id,
                        $business_id
                    ]);
                    
                    if ($success) {
                        $message = "Promotional message updated successfully!";
                        $message_type = "success";
                        
                        // If deactivating, log analytics
                        if (!$promo_active && !empty($promo_message)) {
                            // Could add analytics logging here
                        }
                    } else {
                        $message = "Error updating promotional message.";
                        $message_type = "danger";
                    }
                    break;
            }
        }
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $message_type = "danger";
    }
}

// Get all trackers for this business
$trackers = [];
$current_tracker = null;
if ($business_id) {
    $trackers = $pizzaTracker->getBusinessTrackers($business_id);
    
    // If no tracker is selected but we have trackers, select the first one
    if (!$current_tracker_id && !empty($trackers)) {
        $current_tracker_id = $trackers[0]['id'];
    }
    
    // Get current tracker details
    if ($current_tracker_id) {
        $current_tracker = $pizzaTracker->getTrackerDetails($current_tracker_id);
    }
}

// Get campaigns for new tracker creation
$campaigns = [];
if ($business_id) {
    $stmt = $pdo->prepare("SELECT id, name FROM campaigns WHERE business_id = ? AND status = 'active' ORDER BY name");
    $stmt->execute([$business_id]);
    $campaigns = $stmt->fetchAll();
}

// Include header
require_once __DIR__ . '/../core/includes/header.php';
?>

<div class="container py-4">
    <div class="row mb-4">
        <div class="col-12">
            <h1 class="mb-0"><i class="bi bi-trophy-fill text-warning me-2"></i>Pizza Tracker Management</h1>
            <p class="text-muted">Manage pizza progress tracking for your campaigns and locations.</p>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (!$business_id): ?>
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle me-2"></i>
            Unable to determine business association. Please contact support.
        </div>
    <?php else: ?>

    <!-- Tracker Selection and Create New -->
    <div class="row mb-4">
        <div class="col-md-6">
            <label for="tracker-select" class="form-label">Select Pizza Tracker</label>
            <select class="form-select" id="tracker-select" onchange="changeTracker()">
                <option value="">Choose a tracker...</option>
                <?php foreach ($trackers as $tracker): ?>
                    <option value="<?php echo $tracker['id']; ?>" 
                            <?php echo ($current_tracker_id == $tracker['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($tracker['name']); ?> 
                        (<?php echo $tracker['progress_percent'] ?? 0; ?>% - 
                         <?php echo $tracker['completion_count']; ?> pizzas earned)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-6 d-flex align-items-end">
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newTrackerModal">
                <i class="bi bi-plus-circle me-2"></i>Create New Tracker
            </button>
        </div>
    </div>

    <!-- Current Tracker Details -->
    <?php if ($current_tracker): ?>
        <div class="row">
            <div class="col-lg-8">
                <!-- Progress Display -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-pizza me-2"></i>
                            <?php echo htmlspecialchars($current_tracker['name']); ?>
                        </h5>
                        <?php if ($current_tracker['campaign_name']): ?>
                            <small class="text-muted">Campaign: <?php echo htmlspecialchars($current_tracker['campaign_name']); ?></small>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <!-- Progress Bar -->
                        <div class="mb-4">
                            <div class="d-flex justify-content-between mb-2">
                                <span>Progress to Next Pizza</span>
                                <span><strong><?php echo $current_tracker['progress_percent']; ?>%</strong></span>
                            </div>
                            <div class="progress" style="height: 30px;">
                                <div class="progress-bar bg-success progress-bar-striped <?php echo $current_tracker['is_complete'] ? 'bg-warning' : ''; ?>" 
                                     style="width: <?php echo $current_tracker['progress_percent']; ?>%">
                                    <?php if ($current_tracker['is_complete']): ?>
                                        <i class="bi bi-check-circle me-1"></i>Complete!
                                    <?php else: ?>
                                        <?php echo $current_tracker['progress_percent']; ?>%
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Stats Row -->
                        <div class="row text-center">
                            <div class="col-md-3">
                                <div class="border-end">
                                    <h4 class="text-success">$<?php echo number_format($current_tracker['current_revenue'], 2); ?></h4>
                                    <small class="text-muted">Current Revenue</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="border-end">
                                    <h4 class="text-primary">$<?php echo number_format($current_tracker['revenue_goal'], 2); ?></h4>
                                    <small class="text-muted">Goal Amount</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="border-end">
                                    <h4 class="text-warning">$<?php echo number_format($current_tracker['pizza_cost'], 2); ?></h4>
                                    <small class="text-muted">Pizza Cost</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <h4 class="text-info"><?php echo $current_tracker['completion_count']; ?></h4>
                                <small class="text-muted">Pizzas Earned</small>
                            </div>
                        </div>
                        
                        <?php if ($current_tracker['remaining_amount'] > 0): ?>
                            <div class="alert alert-info mt-3">
                                <i class="bi bi-info-circle me-2"></i>
                                <strong>$<?php echo number_format($current_tracker['remaining_amount'], 2); ?></strong> 
                                remaining to earn the next pizza!
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($current_tracker['last_completion_date']): ?>
                            <div class="mt-3">
                                <small class="text-muted">
                                    Last pizza earned: <?php echo date('M j, Y g:i A', strtotime($current_tracker['last_completion_date'])); ?>
                                </small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Promotional Message Management -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="bi bi-megaphone-fill me-2"></i>Promotional Message</h6>
                        <small class="text-muted">Promote high-margin items to boost revenue</small>
                    </div>
                    <div class="card-body">
                        <form method="post">
                            <input type="hidden" name="action" value="update_promo">
                            <input type="hidden" name="tracker_id" value="<?php echo $current_tracker['id']; ?>">
                            
                            <div class="mb-3">
                                <label class="form-label">Promotional Message</label>
                                <textarea name="promo_message" class="form-control" rows="3" 
                                          maxlength="200" id="promoMessage"
                                          placeholder="🍕 Try our premium deep-dish pizza - earn rewards faster with every order!"><?php echo htmlspecialchars($current_tracker['promo_message'] ?? ''); ?></textarea>
                                <div class="form-text">
                                    <span id="charCount"><?php echo strlen($current_tracker['promo_message'] ?? ''); ?></span>/200 characters
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="promo_active" 
                                               id="promoActive" <?php echo ($current_tracker['promo_active'] ?? false) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="promoActive">
                                            Active
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <input type="datetime-local" name="promo_expire_date" class="form-control form-control-sm" 
                                           value="<?php echo $current_tracker['promo_expire_date'] ? date('Y-m-d\TH:i', strtotime($current_tracker['promo_expire_date'])) : ''; ?>"
                                           placeholder="Auto-expire (optional)">
                                    <small class="text-muted">Optional auto-expire</small>
                                </div>
                            </div>
                            
                            <?php if ($current_tracker['promo_views'] > 0 || $current_tracker['promo_clicks'] > 0): ?>
                                <div class="row text-center mb-3">
                                    <div class="col-6">
                                        <small class="text-muted d-block">Views</small>
                                        <strong class="text-primary"><?php echo number_format($current_tracker['promo_views'] ?? 0); ?></strong>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted d-block">Clicks</small>
                                        <strong class="text-success"><?php echo number_format($current_tracker['promo_clicks'] ?? 0); ?></strong>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-megaphone me-2"></i>Update Promotion
                            </button>
                        </form>
                        
                        <?php if ($current_tracker['promo_updated_at']): ?>
                            <div class="mt-2">
                                <small class="text-muted">
                                    Last updated: <?php echo date('M j, Y g:i A', strtotime($current_tracker['promo_updated_at'])); ?>
                                </small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <!-- Revenue Entry -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="bi bi-cash-coin me-2"></i>Add Revenue</h6>
                    </div>
                    <div class="card-body">
                        <form method="post">
                            <input type="hidden" name="action" value="add_revenue">
                            <input type="hidden" name="tracker_id" value="<?php echo $current_tracker['id']; ?>">
                            
                            <div class="mb-3">
                                <label class="form-label">Revenue Amount ($)</label>
                                <input type="number" name="revenue_amount" class="form-control" 
                                       step="0.01" min="0.01" placeholder="0.00" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Notes (Optional)</label>
                                <textarea name="notes" class="form-control" rows="2" 
                                          placeholder="Source of revenue..."></textarea>
                            </div>
                            
                            <button type="submit" class="btn btn-success w-100">
                                <i class="bi bi-plus-circle me-2"></i>Add Revenue
                            </button>
                        </form>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="bi bi-gear me-2"></i>Quick Actions</h6>
                    </div>
                    <div class="card-body">
                        <form method="post" class="mb-2" onsubmit="return confirm('Are you sure you want to reset this tracker?')">
                            <input type="hidden" name="action" value="reset_tracker">
                            <input type="hidden" name="tracker_id" value="<?php echo $current_tracker['id']; ?>">
                            <button type="submit" class="btn btn-warning w-100 mb-2">
                                <i class="bi bi-arrow-clockwise me-2"></i>Reset Progress
                            </button>
                        </form>
                        
                        <a href="../public/pizza-tracker.php?tracker_id=<?php echo $current_tracker['id']; ?>" 
                           class="btn btn-outline-primary w-100 mb-2" target="_blank">
                            <i class="bi bi-eye me-2"></i>View Public Page
                        </a>
                        
                        <a href="pizza-analytics.php?tracker_id=<?php echo $current_tracker['id']; ?>" 
                           class="btn btn-outline-info w-100 mb-2">
                            <i class="bi bi-graph-up me-2"></i>View Analytics
                        </a>
                        
                        <a href="notification-settings.php" 
                           class="btn btn-outline-secondary w-100">
                            <i class="bi bi-bell me-2"></i>Notification Settings
                        </a>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <!-- No Trackers State -->
        <div class="text-center py-5">
            <i class="bi bi-pizza display-1 text-warning"></i>
            <h3 class="mt-3">No Pizza Trackers Yet</h3>
            <p class="text-muted">Create your first pizza tracker to start tracking revenue progress!</p>
            <button class="btn btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#newTrackerModal">
                <i class="bi bi-pizza me-2"></i>Create Your First Tracker
            </button>
        </div>
    <?php endif; ?>

    <?php endif; ?>
</div>

<!-- Create New Tracker Modal -->
<div class="modal fade" id="newTrackerModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="post">
                <input type="hidden" name="action" value="create_tracker">
                
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pizza me-2 text-warning"></i>Create New Pizza Tracker</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Tracker Name</label>
                            <input type="text" name="name" class="form-control" 
                                   placeholder="Downtown Mall Pizza Fund" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Pizza Cost ($)</label>
                            <input type="number" name="pizza_cost" class="form-control" 
                                   step="0.01" min="0.01" placeholder="25.00" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Revenue Goal ($)</label>
                            <input type="number" name="revenue_goal" class="form-control" 
                                   step="0.01" min="0.01" placeholder="1000.00" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tracker Type</label>
                            <select name="tracker_type" class="form-select" id="trackerType">
                                <option value="campaign">Campaign Tracker</option>
                                <option value="machine">Machine Tracker</option>
                                <option value="qr_standalone">Standalone QR Tracker</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3" id="campaignSelection">
                            <label class="form-label">Link to Campaign (Optional)</label>
                            <select name="campaign_id" class="form-select">
                                <option value="">No campaign link</option>
                                <?php foreach ($campaigns as $campaign): ?>
                                    <option value="<?php echo $campaign['id']; ?>">
                                        <?php echo htmlspecialchars($campaign['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description (Optional)</label>
                        <textarea name="description" class="form-control" rows="3" 
                                  placeholder="Track revenue progress for free pizza rewards..."></textarea>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-pizza me-2"></i>Create Tracker
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function changeTracker() {
    const select = document.getElementById('tracker-select');
    if (select.value) {
        window.location.href = `pizza-tracker.php?tracker_id=${select.value}`;
    }
}

// Track unsaved changes and prevent auto-refresh
let hasUnsavedChanges = false;
let originalPromoMessage = '';
let autoRefreshEnabled = true;

// Character counter for promotional message
document.addEventListener('DOMContentLoaded', function() {
    const promoMessage = document.getElementById('promoMessage');
    const charCount = document.getElementById('charCount');
    const promoForm = promoMessage ? promoMessage.closest('form') : null;
    
    if (promoMessage && charCount) {
        // Store original message
        originalPromoMessage = promoMessage.value;
        
        promoMessage.addEventListener('input', function() {
            const length = this.value.length;
            charCount.textContent = length;
            
            // Check for unsaved changes
            hasUnsavedChanges = (this.value !== originalPromoMessage);
            updateAutoRefreshStatus();
            
            // Color coding
            if (length > 180) {
                charCount.style.color = '#dc3545'; // Red
            } else if (length > 150) {
                charCount.style.color = '#fd7e14'; // Orange
            } else {
                charCount.style.color = '#6c757d'; // Default gray
            }
        });
        
        // Check for changes in active checkbox too
        const promoActive = document.getElementById('promoActive');
        if (promoActive) {
            promoActive.addEventListener('change', function() {
                hasUnsavedChanges = true;
                updateAutoRefreshStatus();
            });
        }
        
        // Reset unsaved changes flag when form is submitted
        if (promoForm) {
            promoForm.addEventListener('submit', function() {
                hasUnsavedChanges = false;
                autoRefreshEnabled = true;
                updateStatusIndicator();
            });
        }
    }
    
    // Add status indicator
    addStatusIndicator();
});

function updateAutoRefreshStatus() {
    autoRefreshEnabled = !hasUnsavedChanges;
    updateStatusIndicator();
}

function updateStatusIndicator() {
    let indicator = document.getElementById('refresh-status');
    if (!indicator) return;
    
    if (hasUnsavedChanges) {
        indicator.innerHTML = '<i class="bi bi-exclamation-triangle text-warning me-1"></i>Auto-refresh disabled - unsaved changes';
        indicator.className = 'small text-warning';
    } else if (autoRefreshEnabled) {
        indicator.innerHTML = '<i class="bi bi-arrow-clockwise text-success me-1"></i>Auto-refresh enabled';
        indicator.className = 'small text-muted';
    } else {
        indicator.innerHTML = '<i class="bi bi-pause-circle text-info me-1"></i>Auto-refresh paused';
        indicator.className = 'small text-info';
    }
}

function addStatusIndicator() {
    // Add status indicator to the page
    const container = document.querySelector('.container.py-4');
    if (container && !document.getElementById('refresh-status')) {
        const statusDiv = document.createElement('div');
        statusDiv.id = 'refresh-status';
        statusDiv.className = 'small text-muted text-center mb-2';
        statusDiv.innerHTML = '<i class="bi bi-arrow-clockwise text-success me-1"></i>Auto-refresh enabled';
        container.insertBefore(statusDiv, container.firstChild);
    }
}

// Modified auto-refresh that respects unsaved changes
setInterval(function() {
    if (autoRefreshEnabled && document.getElementById('tracker-select').value) {
        location.reload();
    }
}, 30000);

// Warn user before leaving with unsaved changes
window.addEventListener('beforeunload', function(e) {
    if (hasUnsavedChanges) {
        e.preventDefault();
        e.returnValue = 'You have unsaved changes to your promotional message. Are you sure you want to leave?';
        return e.returnValue;
    }
});
</script>

<?php require_once __DIR__ . '/../core/includes/footer.php'; ?> 