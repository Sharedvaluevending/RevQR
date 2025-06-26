<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/business_utils.php';

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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $business_id) {
    try {
        $pdo->beginTransaction();
        
        // Create campaign
        $stmt = $pdo->prepare("
            INSERT INTO campaigns (
                business_id, name, description, 
                start_date, end_date, status
            ) VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $business_id,
            $_POST['name'],
            $_POST['description'],
            $_POST['start_date'] ?: null,
            $_POST['end_date'] ?: null,
            $_POST['status']
        ]);
        $campaign_id = $pdo->lastInsertId();
        
        // Handle pizza tracker creation
        $pizza_tracker_id = null;
        if (isset($_POST['create_pizza_tracker']) && $_POST['create_pizza_tracker'] == '1') {
            require_once __DIR__ . '/../core/pizza_tracker_utils.php';
            $pizzaTracker = new PizzaTracker($pdo);
            
            $pizza_tracker_id = $pizzaTracker->createTracker(
                $business_id,
                $_POST['pizza_tracker_name'] ?: $_POST['name'] . ' Pizza Fund',
                $_POST['pizza_tracker_description'] ?: 'Pizza tracker for ' . $_POST['name'],
                (float)($_POST['pizza_cost'] ?? 25.00),
                (float)($_POST['revenue_goal'] ?? 500.00),
                'campaign',
                $campaign_id
            );
            
            if (!$pizza_tracker_id) {
                throw new Exception('Failed to create pizza tracker');
            }
        }
        
        // Handle spin wheel creation/assignment
        $spin_wheel_id = null;
        if (isset($_POST['spin_wheel_option'])) {
            if ($_POST['spin_wheel_option'] === 'create_new' && !empty($_POST['spin_wheel_name'])) {
                // Create new spin wheel
                $stmt = $pdo->prepare("
                    INSERT INTO spin_wheels (business_id, name, description, wheel_type, campaign_id, is_active)
                    VALUES (?, ?, ?, 'campaign', ?, 1)
                ");
                $stmt->execute([
                    $business_id,
                    $_POST['spin_wheel_name'],
                    $_POST['spin_wheel_description'] ?: 'Spin wheel for ' . $_POST['name'],
                    $campaign_id
                ]);
                $spin_wheel_id = $pdo->lastInsertId();
            } elseif ($_POST['spin_wheel_option'] === 'existing' && !empty($_POST['existing_spin_wheel_id'])) {
                // Use existing spin wheel
                $spin_wheel_id = (int)$_POST['existing_spin_wheel_id'];
                // Update the wheel to link to this campaign
                $stmt = $pdo->prepare("UPDATE spin_wheels SET campaign_id = ? WHERE id = ? AND business_id = ?");
                $stmt->execute([$campaign_id, $spin_wheel_id, $business_id]);
            }
        }
        
        // Attach selected list if any
        if (!empty($_POST['list_id'])) {
            $stmt = $pdo->prepare("
                INSERT INTO campaign_voting_lists (campaign_id, voting_list_id)
                VALUES (?, ?)
            ");
            $stmt->execute([$campaign_id, $_POST['list_id']]);
            
            // Update spin settings for the list if provided
            $stmt = $pdo->prepare("UPDATE voting_lists SET spin_enabled = ?, spin_trigger_count = ? WHERE id = ?");
            $stmt->execute([
                isset($_POST['spin_enabled']) ? 1 : 0,
                intval($_POST['spin_trigger_count'] ?? 3),
                intval($_POST['list_id'])
            ]);
            
            // If we created a spin wheel and have rewards, link them
            if ($spin_wheel_id) {
                $stmt = $pdo->prepare("UPDATE rewards SET spin_wheel_id = ? WHERE list_id = ?");
                $stmt->execute([$spin_wheel_id, $_POST['list_id']]);
            }
        }
        
        $pdo->commit();
        
        $success_message = "Campaign created successfully!";
        if ($pizza_tracker_id) {
            $success_message .= " Pizza tracker has been set up and linked to this campaign.";
        }
        
        $message = $success_message;
        $message_type = "success";
        
        // Redirect to campaign management
        header("Location: manage-campaigns.php");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error creating campaign: " . $e->getMessage());
        $message = "Error creating campaign. Please try again.";
        $message_type = "danger";
    }
}

// Get all items and lists for this business with proper table structure detection
$items = [];
$lists = [];
$existing_spin_wheels = [];

if ($business_id) {
    try {
        $table_structure = getListTableStructure($pdo);
        
        // Get existing spin wheels for this business
        $stmt = $pdo->prepare("
            SELECT id, name, description, wheel_type, campaign_id, machine_name, qr_code_id 
            FROM spin_wheels 
            WHERE business_id = ? AND is_active = 1
            ORDER BY created_at DESC
        ");
        $stmt->execute([$business_id]);
        $existing_spin_wheels = $stmt->fetchAll();
        
        if ($table_structure === 'voting_lists') {
            // Use new voting_lists table structure
            $stmt = $pdo->prepare("
                SELECT vli.*, vl.name as list_name
                FROM voting_list_items vli
                JOIN voting_lists vl ON vli.voting_list_id = vl.id
                WHERE vl.business_id = ?
                ORDER BY vli.item_name
            ");
            $stmt->execute([$business_id]);
            $items = $stmt->fetchAll();
            
            // Get all lists for this business
            $stmt = $pdo->prepare("
                SELECT vl.*, 
                       COUNT(vli.id) as item_count,
                       DATE_FORMAT(vl.created_at, '%Y-%m-%d %H:%i') as formatted_date
                FROM voting_lists vl
                LEFT JOIN voting_list_items vli ON vl.id = vli.voting_list_id
                WHERE vl.business_id = ?
                GROUP BY vl.id
                ORDER BY vl.name
            ");
            $stmt->execute([$business_id]);
            $lists = $stmt->fetchAll();
        } else {
            // Fallback to old machines/items table structure
            $stmt = $pdo->prepare("
                SELECT i.*, m.name as list_name
                FROM items i
                JOIN machines m ON i.machine_id = m.id
                WHERE m.business_id = ?
                ORDER BY i.name
            ");
            $stmt->execute([$business_id]);
            $items = $stmt->fetchAll();
            
            // Get all machines (lists) for this business
            $stmt = $pdo->prepare("
                SELECT m.id, m.name, 
                       COUNT(i.id) as item_count,
                       DATE_FORMAT(m.created_at, '%Y-%m-%d %H:%i') as formatted_date
                FROM machines m
                LEFT JOIN items i ON m.id = i.machine_id
                WHERE m.business_id = ?
                GROUP BY m.id
                ORDER BY m.name
            ");
            $stmt->execute([$business_id]);
            $lists = $stmt->fetchAll();
        }
    } catch (Exception $e) {
        error_log("Error fetching campaign data: " . $e->getMessage());
        $message = "Error loading data. Please refresh the page.";
        $message_type = "danger";
    }
}

require_once __DIR__ . '/../core/includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Create New Campaign</h1>
            <p class="text-muted">Set up a new marketing campaign</p>
        </div>
        <a href="manage-campaigns.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-2"></i>Back to Campaigns
        </a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo htmlspecialchars($message_type); ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <form method="POST" class="needs-validation" novalidate>
                <div class="row">
                    <div class="col-md-8">
                        <div class="mb-3">
                            <label class="form-label">Campaign Name</label>
                            <input type="text" class="form-control" name="name" required maxlength="255">
                            <div class="form-text">Give your campaign a descriptive name</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="3" maxlength="1000"></textarea>
                            <div class="form-text">Describe the purpose and goals of this campaign</div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Start Date</label>
                                    <input type="date" class="form-control" name="start_date">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">End Date</label>
                                    <input type="date" class="form-control" name="end_date">
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status" required>
                                <option value="draft">Draft</option>
                                <option value="active">Active</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Attach List (Optional)</label>
                            <select class="form-select" name="list_id">
                                <option value="">Select a list (optional)</option>
                                <?php foreach ($lists as $list): ?>
                                    <option value="<?php echo htmlspecialchars($list['id']); ?>">
                                        <?php echo htmlspecialchars($list['name']); ?> 
                                        (<?php echo intval($list['item_count']); ?> items)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Optionally select a list to attach to this campaign</div>
                        </div>

                        <!-- Spin Wheel Configuration Section -->
                        <div class="card mt-4">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="bi bi-stars me-2"></i>Spin Wheel Configuration</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label class="form-label">Spin Wheel Option</label>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="spin_wheel_option" id="no_spin_wheel" value="none" checked>
                                        <label class="form-check-label" for="no_spin_wheel">
                                            No Spin Wheel for this Campaign
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="spin_wheel_option" id="create_new_wheel" value="create_new">
                                        <label class="form-check-label" for="create_new_wheel">
                                            Create New Spin Wheel
                                        </label>
                                    </div>
                                    <?php if (!empty($existing_spin_wheels)): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="spin_wheel_option" id="use_existing_wheel" value="existing">
                                        <label class="form-check-label" for="use_existing_wheel">
                                            Use Existing Spin Wheel
                                        </label>
                                    </div>
                                    <?php endif; ?>
                                </div>

                                <div id="new-wheel-options" style="display:none;">
                                    <div class="mb-3">
                                        <label class="form-label">Spin Wheel Name</label>
                                        <input type="text" class="form-control" name="spin_wheel_name" placeholder="Enter spin wheel name">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Spin Wheel Description</label>
                                        <textarea class="form-control" name="spin_wheel_description" rows="2" placeholder="Optional description"></textarea>
                                    </div>
                                </div>

                                <?php if (!empty($existing_spin_wheels)): ?>
                                <div id="existing-wheel-options" style="display:none;">
                                    <div class="mb-3">
                                        <label class="form-label">Select Existing Spin Wheel</label>
                                        <select class="form-select" name="existing_spin_wheel_id">
                                            <option value="">Choose an existing spin wheel</option>
                                            <?php foreach ($existing_spin_wheels as $wheel): ?>
                                                <option value="<?php echo $wheel['id']; ?>">
                                                    <?php echo htmlspecialchars($wheel['name']); ?>
                                                    <?php if ($wheel['wheel_type']): ?>
                                                        (<?php echo ucfirst($wheel['wheel_type']); ?>)
                                                    <?php endif; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div id="spin-wheel-options" style="display:none;">
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="spin_enabled" id="spin_enabled" value="1">
                                    <label class="form-check-label" for="spin_enabled">Enable Spin Wheel for this Campaign</label>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Spin Trigger Count</label>
                                <input type="number" class="form-control" name="spin_trigger_count" value="3" min="1" max="10">
                                <div class="form-text">Number of votes/scans before spin is allowed</div>
                            </div>
                        </div>

                        <!-- Pizza Tracker Configuration Section -->
                        <div class="card mt-4">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="bi bi-pizza me-2 text-warning"></i>Pizza Tracker Configuration</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="create_pizza_tracker" id="create_pizza_tracker" value="1">
                                        <label class="form-check-label" for="create_pizza_tracker">
                                            <strong>Create Pizza Tracker for this Campaign</strong>
                                        </label>
                                        <div class="form-text">Track revenue progress and reward goals for team pizza celebrations</div>
                                    </div>
                                </div>

                                <div id="pizza-tracker-options" style="display:none;">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Pizza Tracker Name</label>
                                            <input type="text" class="form-control" name="pizza_tracker_name" 
                                                   placeholder="Campaign Name + Pizza Fund">
                                            <div class="form-text">Leave blank to auto-generate from campaign name</div>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Pizza Cost ($)</label>
                                            <input type="number" class="form-control" name="pizza_cost" 
                                                   step="0.01" min="0.01" value="25.00" placeholder="25.00">
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Revenue Goal ($)</label>
                                            <input type="number" class="form-control" name="revenue_goal" 
                                                   step="0.01" min="0.01" value="500.00" placeholder="500.00">
                                            <div class="form-text">Amount needed to earn a pizza</div>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Progress Preview</label>
                                            <div class="progress" style="height: 25px;">
                                                <div class="progress-bar bg-warning" style="width: 0%" id="pizza-progress-preview">
                                                    0% - $0 / $500
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Pizza Tracker Description</label>
                                        <textarea class="form-control" name="pizza_tracker_description" rows="2" 
                                                  placeholder="Track our progress toward earning a team pizza celebration!"></textarea>
                                        <div class="form-text">Optional custom description for the pizza tracker</div>
                                    </div>
                                    
                                    <div class="alert alert-info">
                                        <i class="bi bi-info-circle me-2"></i>
                                        <strong>How it works:</strong> Revenue from votes and sales will automatically contribute 
                                        to the pizza goal. When the goal is reached, the tracker resets for the next pizza!
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="text-end mt-4">
                    <a href="manage-campaigns.php" class="btn btn-outline-secondary me-2">Cancel</a>
                    <button type="submit" class="btn btn-primary">Create Campaign</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Form validation
(function() {
    'use strict';
    const forms = document.querySelectorAll('.needs-validation');
    Array.from(forms).forEach(form => {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });
})();

// Show spin wheel options only if a list is selected
const listSelect = document.querySelector('select[name="list_id"]');
const spinOptions = document.getElementById('spin-wheel-options');

if (listSelect && spinOptions) {
    listSelect.addEventListener('change', function() {
        if (this.value) {
            spinOptions.style.display = 'block';
        } else {
            spinOptions.style.display = 'none';
        }
    });
}

// Handle spin wheel option changes
const spinWheelOptions = document.querySelectorAll('input[name="spin_wheel_option"]');
const newWheelOptions = document.getElementById('new-wheel-options');
const existingWheelOptions = document.getElementById('existing-wheel-options');

spinWheelOptions.forEach(option => {
    option.addEventListener('change', function() {
        // Hide all option panels first
        if (newWheelOptions) newWheelOptions.style.display = 'none';
        if (existingWheelOptions) existingWheelOptions.style.display = 'none';
        
        // Show the relevant panel based on selection
        if (this.value === 'create_new' && newWheelOptions) {
            newWheelOptions.style.display = 'block';
        } else if (this.value === 'existing' && existingWheelOptions) {
            existingWheelOptions.style.display = 'block';
        }
    });
});

// Handle pizza tracker option changes
const pizzaTrackerCheckbox = document.getElementById('create_pizza_tracker');
const pizzaTrackerOptions = document.getElementById('pizza-tracker-options');

if (pizzaTrackerCheckbox && pizzaTrackerOptions) {
    pizzaTrackerCheckbox.addEventListener('change', function() {
        if (this.checked) {
            pizzaTrackerOptions.style.display = 'block';
            updatePizzaProgressPreview();
        } else {
            pizzaTrackerOptions.style.display = 'none';
        }
    });
}

// Update pizza progress preview
function updatePizzaProgressPreview() {
    const pizzaCost = document.querySelector('input[name="pizza_cost"]');
    const revenueGoal = document.querySelector('input[name="revenue_goal"]');
    const progressPreview = document.getElementById('pizza-progress-preview');
    
    if (pizzaCost && revenueGoal && progressPreview) {
        const cost = parseFloat(pizzaCost.value) || 25;
        const goal = parseFloat(revenueGoal.value) || 500;
        
        // Simulate some progress for preview (20% of goal)
        const currentRevenue = goal * 0.2;
        const percentage = Math.min(100, (currentRevenue / goal) * 100);
        
        progressPreview.style.width = percentage + '%';
        progressPreview.textContent = `${Math.round(percentage)}% - $${Math.round(currentRevenue)} / $${goal}`;
        
        // Update pizza cost display
        progressPreview.setAttribute('title', `Pizza costs $${cost} - Goal: $${goal}`);
    }
}

// Add event listeners for pizza tracker inputs
const pizzaCostInput = document.querySelector('input[name="pizza_cost"]');
const revenueGoalInput = document.querySelector('input[name="revenue_goal"]');

if (pizzaCostInput) {
    pizzaCostInput.addEventListener('input', updatePizzaProgressPreview);
}
if (revenueGoalInput) {
    revenueGoalInput.addEventListener('input', updatePizzaProgressPreview);
}
</script>

<?php require_once __DIR__ . '/../core/includes/footer.php'; ?> 