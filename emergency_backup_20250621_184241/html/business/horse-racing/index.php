<?php
/**
 * Business Horse Racing Dashboard
 * Where businesses create and manage horse races
 */

require_once __DIR__ . '/../../core/config.php';
require_once __DIR__ . '/../../core/session.php';
require_once __DIR__ . '/../../core/auth.php';

// Require business role
require_role('business');

$business_id = $_SESSION['business_id'];

// Handle race creation form
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_race'])) {
    $race_name = trim($_POST['race_name']);
    $race_type = $_POST['race_type'];
    $machine_id = $_POST['machine_id'];
    $selected_items = $_POST['selected_items'] ?? [];
    $prize_pool = intval($_POST['prize_pool']);
    $min_bet = intval($_POST['min_bet']);
    $max_bet = intval($_POST['max_bet']);
    
    if ($race_name && $race_type && $machine_id && !empty($selected_items) && $prize_pool > 0) {
        try {
            $pdo->beginTransaction();
            
            // Calculate race duration
            $start_time = new DateTime();
            $end_time = clone $start_time;
            
            switch ($race_type) {
                case 'daily':
                    $end_time->add(new DateInterval('P1D'));
                    break;
                case '3day':
                    $end_time->add(new DateInterval('P3D'));
                    break;
                case 'weekly':
                    $end_time->add(new DateInterval('P7D'));
                    break;
            }
            
            // Create race
            $stmt = $pdo->prepare("
                INSERT INTO business_races 
                (business_id, race_name, race_type, machine_id, start_time, end_time, 
                 prize_pool_qr_coins, min_bet_amount, max_bet_amount, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
            ");
            $stmt->execute([
                $business_id, $race_name, $race_type, $machine_id,
                $start_time->format('Y-m-d H:i:s'), $end_time->format('Y-m-d H:i:s'),
                $prize_pool, $min_bet, $max_bet
            ]);
            
            $race_id = $pdo->lastInsertId();
            
            // Add selected items as horses with custom names if available
            foreach ($selected_items as $item_id) {
                // Get item details and custom horse assignment
                $stmt = $pdo->prepare("
                    SELECT vli.*, vl.name as machine_name,
                           cha.custom_horse_name, cha.custom_horse_color
                    FROM voting_list_items vli 
                    JOIN voting_lists vl ON vli.voting_list_id = vl.id 
                    LEFT JOIN custom_horse_assignments cha ON vli.id = cha.item_id AND cha.business_id = ?
                    WHERE vli.id = ? AND vl.business_id = ?
                ");
                $stmt->execute([$business_id, $item_id, $business_id]);
                $item = $stmt->fetch();
                
                if ($item) {
                    // Use custom horse name if available, otherwise generate default
                    $horse_name = $item['custom_horse_name'] ?? ("Horse " . $item['item_name']);
                    $slot_position = "SLOT_" . $item['id']; // You can enhance this with actual slot mapping
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO race_horses 
                        (race_id, item_id, horse_name, slot_position, performance_weight, current_odds)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $race_id, $item_id, $horse_name, $slot_position, 1.00, 2.50
                    ]);
                }
            }
            
            $pdo->commit();
            $message = "Race '{$race_name}' created successfully! Waiting for admin approval.";
            $message_type = 'success';
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "Error creating race: " . $e->getMessage();
            $message_type = 'danger';
        }
    } else {
        $message = "Please fill in all required fields and select at least one item.";
        $message_type = 'danger';
    }
}

// Get business machines
$stmt = $pdo->prepare("
    SELECT * FROM voting_lists WHERE business_id = ? ORDER BY name
");
$stmt->execute([$business_id]);
$machines = $stmt->fetchAll();

// Get business races
$stmt = $pdo->prepare("
    SELECT br.*, COUNT(rh.id) as horse_count,
           CASE 
               WHEN br.status = 'pending' THEN 'Pending Approval'
               WHEN br.status = 'approved' AND br.start_time > NOW() THEN 'Scheduled'
               WHEN br.status = 'active' THEN 'Running'
               WHEN br.status = 'completed' THEN 'Finished'
               ELSE 'Unknown'
           END as status_display
    FROM business_races br
    LEFT JOIN race_horses rh ON br.id = rh.race_id
    WHERE br.business_id = ?
    GROUP BY br.id
    ORDER BY br.created_at DESC
");
$stmt->execute([$business_id]);
$races = $stmt->fetchAll();

require_once __DIR__ . '/../../core/includes/header.php';
?>

<style>
.racing-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 2rem 0;
    margin-bottom: 2rem;
}

.race-wizard {
    background: rgba(255, 255, 255, 0.12) !important;
    backdrop-filter: blur(20px) !important;
    border: 1px solid rgba(255, 255, 255, 0.15) !important;
    border-radius: 16px !important;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3) !important;
    padding: 2rem;
    transition: all 0.3s ease;
}

.machine-card {
    background: rgba(255, 255, 255, 0.12) !important;
    backdrop-filter: blur(20px) !important;
    border: 1px solid rgba(255, 255, 255, 0.15) !important;
    border-radius: 12px !important;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.2) !important;
    transition: all 0.3s ease;
    cursor: pointer;
    color: #ffffff;
}

.machine-card:hover {
    transform: translateY(-2px) !important;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3) !important;
    border: 1px solid rgba(255, 255, 255, 0.25) !important;
    color: #ffffff;
}

.machine-card.selected {
    border: 1px solid rgba(0, 123, 255, 0.5) !important;
    background: rgba(0, 123, 255, 0.2) !important;
    color: #ffffff;
}

.machine-card h6 {
    color: #ffffff !important;
    font-weight: 600;
}

.machine-card small {
    color: rgba(255, 255, 255, 0.8) !important;
}

.machine-card .text-muted {
    color: rgba(255, 255, 255, 0.8) !important;
}

.item-checkbox {
    transform: scale(1.2);
}

.prize-pool-slider {
    background: rgba(255, 215, 0, 0.15) !important;
    backdrop-filter: blur(20px) !important;
    border: 1px solid rgba(255, 215, 0, 0.3) !important;
    border-radius: 12px;
    padding: 1rem;
}

.race-card {
    background: rgba(255, 255, 255, 0.12) !important;
    backdrop-filter: blur(20px) !important;
    border: 1px solid rgba(255, 255, 255, 0.15) !important;
    border-left: 4px solid #007bff !important;
    border-radius: 12px !important;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.2) !important;
    transition: all 0.3s ease;
    color: #ffffff;
}

.status-pending { color: #ffc107; }
.status-approved { color: #28a745; }
.status-active { color: #dc3545; }
.status-completed { color: #6c757d; }

/* Translucent app styling with white text */
.card, .card-body {
    background: rgba(255, 255, 255, 0.12) !important;
    backdrop-filter: blur(20px) !important;
    border: 1px solid rgba(255, 255, 255, 0.15) !important;
    color: #ffffff;
}

.race-card h6, .race-card small, .race-card .text-muted {
    color: #ffffff !important;
}

.race-card .badge {
    color: #ffffff !important;
}

.table {
    background: rgba(255, 255, 255, 0.12) !important;
    backdrop-filter: blur(20px) !important;
    border: 1px solid rgba(255, 255, 255, 0.15) !important;
    border-radius: 12px !important;
    color: #ffffff;
}

.table th {
    background: rgba(255, 255, 255, 0.1) !important;
    border: none !important;
    color: #ffffff !important;
    font-weight: 600;
}

.table td {
    background: transparent !important;
    border: 1px solid rgba(255, 255, 255, 0.1) !important;
    color: #ffffff !important;
}

/* Fix all form labels and text - WHITE TEXT */
.form-label {
    color: #ffffff !important;
    font-weight: 500;
}

h5 {
    color: #ffffff !important;
    font-weight: 600;
}

.text-muted {
    color: rgba(255, 255, 255, 0.8) !important;
}

/* Race wizard text styling */
.race-wizard h5, .race-wizard p, .race-wizard label {
    color: #ffffff !important;
}

.race-wizard .text-muted {
    color: rgba(255, 255, 255, 0.8) !important;
}

/* Form inputs on translucent background */
.form-control, .form-select {
    background: rgba(255, 255, 255, 0.1) !important;
    border: 1px solid rgba(255, 255, 255, 0.2) !important;
    color: #ffffff !important;
}

.form-control::placeholder {
    color: rgba(255, 255, 255, 0.6) !important;
}

.form-control:focus, .form-select:focus {
    background: rgba(255, 255, 255, 0.15) !important;
    border-color: rgba(0, 123, 255, 0.5) !important;
    box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25) !important;
    color: #ffffff !important;
}

/* Racing header styling */
.racing-header {
    background: rgba(255, 255, 255, 0.15);
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 2rem;
    box-shadow: 0 4px 16px rgba(0,0,0,0.2);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.racing-header h1 {
    color: #ffffff !important;
    font-weight: 600;
}

.racing-header .lead {
    color: rgba(255, 255, 255, 0.8) !important;
}
</style>

<div class="racing-header text-center">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h1 class="display-4 mb-0">🏇 Horse Racing Management</h1>
                <p class="lead mb-0">Create exciting races based on your vending machine data</p>
            </div>
            <div>
                <a href="jockey-assignments.php" class="btn btn-outline-light">
                    <i class="fas fa-user-friends"></i> Manage Jockeys
                </a>
            </div>
        </div>
    </div>
</div>

<div class="container">
    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show">
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Race Creation Wizard -->
        <div class="col-lg-8">
            <div class="race-wizard">
                <h3 class="mb-4">🎯 Create New Race</h3>
                
                <form method="POST" id="raceForm">
                    <!-- Step 1: Basic Setup -->
                    <div class="mb-4">
                        <h5>📅 Step 1: Race Configuration</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <label class="form-label">Race Name</label>
                                <input type="text" class="form-control" name="race_name" 
                                       placeholder="e.g., Campus Snack Derby" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Race Duration</label>
                                <select class="form-select" name="race_type" required>
                                    <option value="">Select Duration</option>
                                    <option value="daily">Daily Race (24 hours)</option>
                                    <option value="3day">3-Day Championship</option>
                                    <option value="weekly">Weekly Tournament</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Step 2: Machine Selection -->
                    <div class="mb-4">
                        <h5>🏪 Step 2: Select Machine</h5>
                        <div class="row" id="machineSelection">
                            <?php foreach ($machines as $machine): ?>
                                <div class="col-md-4 mb-3">
                                    <div class="machine-card p-3" onclick="selectMachine(<?php echo $machine['id']; ?>)">
                                        <input type="radio" name="machine_id" value="<?php echo $machine['id']; ?>" 
                                               class="d-none machine-radio" required>
                                        <h6 class="mb-2"><?php echo htmlspecialchars($machine['name']); ?></h6>
                                        <small class="text-muted">
                                            <i class="bi bi-geo-alt"></i> 
                                            <?php echo htmlspecialchars($machine['location'] ?? 'No location set'); ?>
                                        </small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Step 3: Item Selection (populated via AJAX) -->
                    <div class="mb-4" id="itemSelection" style="display: none;">
                        <h5>🍫 Step 3: Select Items (Horses)</h5>
                        <p class="text-muted">Choose which items will compete as horses in your race</p>
                        <div id="itemsList"></div>
                    </div>

                    <!-- Step 4: Prize Configuration -->
                    <div class="mb-4">
                        <h5>🏆 Step 4: Prize Pool Setup</h5>
                        <div class="prize-pool-slider">
                            <div class="row">
                                <div class="col-md-4">
                                    <label class="form-label">Prize Pool (QR Coins)</label>
                                    <input type="number" class="form-control" name="prize_pool" 
                                           min="100" max="10000" value="1000" required>
                                    <small class="text-muted">Your investment in customer engagement</small>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Minimum Bet</label>
                                    <input type="number" class="form-control" name="min_bet" 
                                           min="5" max="100" value="10" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Maximum Bet</label>
                                    <input type="number" class="form-control" name="max_bet" 
                                           min="50" max="1000" value="500" required>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="text-center">
                        <button type="submit" name="create_race" class="btn btn-primary btn-lg">
                            <i class="bi bi-plus-circle"></i> Create Race
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Your Active Races -->
        <div class="col-lg-4">
            <h4 class="mb-4">🏁 Your Races</h4>
            
            <?php if (empty($races)): ?>
                <div class="text-center py-4">
                    <img src="/horse-racing/assets/img/racetrophy.png" alt="Race Trophy" style="width: 80px; height: 80px; opacity: 0.6;">
                    <p class="text-muted mt-3">No races created yet</p>
                    <small>Create your first race to engage customers!</small>
                </div>
            <?php else: ?>
                <?php foreach ($races as $race): ?>
                    <div class="race-card p-3 mb-3">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="mb-1"><?php echo htmlspecialchars($race['race_name']); ?></h6>
                                <small class="text-muted">
                                    <?php echo ucfirst($race['race_type']); ?> | 
                                    <?php echo $race['horse_count']; ?> horses
                                </small>
                            </div>
                            <span class="badge status-<?php echo strtolower($race['status']); ?>">
                                <?php echo $race['status_display']; ?>
                            </span>
                        </div>
                        
                        <div class="mt-2">
                            <small class="text-muted">Prize Pool:</small>
                            <span class="fw-bold text-warning">
                                <?php echo number_format($race['prize_pool_qr_coins']); ?> coins
                            </span>
                        </div>

                        <?php if ($race['status'] === 'active'): ?>
                            <div class="mt-2">
                                <a href="../horse-racing/race-live.php?id=<?php echo $race['id']; ?>" 
                                   class="btn btn-sm btn-danger me-2">
                                    <i class="bi bi-broadcast"></i> Watch Live
                                </a>
                                <button onclick="stopRace(<?php echo $race['id']; ?>, '<?php echo htmlspecialchars($race['race_name'], ENT_QUOTES); ?>')" 
                                        class="btn btn-sm btn-warning">
                                    <i class="bi bi-stop-circle"></i> Stop Race
                                </button>
                            </div>
                        <?php elseif ($race['status'] === 'upcoming'): ?>
                            <div class="mt-2">
                                <button onclick="stopRace(<?php echo $race['id']; ?>, '<?php echo htmlspecialchars($race['race_name'], ENT_QUOTES); ?>')" 
                                        class="btn btn-sm btn-warning">
                                    <i class="bi bi-x-circle"></i> Cancel Race
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function selectMachine(machineId) {
    // Clear previous selections
    document.querySelectorAll('.machine-card').forEach(card => {
        card.classList.remove('selected');
    });
    
    // Select the clicked machine
    event.currentTarget.classList.add('selected');
    document.querySelector(`input[value="${machineId}"]`).checked = true;
    
    // Load items for this machine
    loadMachineItems(machineId);
}

function loadMachineItems(machineId) {
    const itemsList = document.getElementById('itemsList');
    const itemSelection = document.getElementById('itemSelection');
    
    // Show loading
    itemsList.innerHTML = '<div class="text-center"><div class="spinner-border" role="status"></div></div>';
    itemSelection.style.display = 'block';
    
    // Fetch items via AJAX
    fetch(`../../api/horse-racing/get-machine-items.php?machine_id=${machineId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                let html = '<div class="row">';
                data.items.forEach(item => {
                                                    html += `
                        <div class="col-md-6 mb-3">
                            <div class="card h-100">
                                <div class="card-body">
                                    <div class="form-check">
                                        <input class="form-check-input item-checkbox" type="checkbox" 
                                               name="selected_items[]" value="${item.id}" id="item_${item.id}">
                                        <label class="form-check-label" for="item_${item.id}">
                                            <strong>${item.item_name}</strong>
                                        </label>
                                    </div>
                                    <small class="text-muted d-block mt-1">
                                        Price: $${parseFloat(item.retail_price).toFixed(2)} | 
                                        Stock: ${item.inventory}
                                    </small>
                                    <div class="mt-2">
                                        <span class="badge bg-info">
                                            Sales Potential: ${item.inventory > 10 ? 'High' : 'Medium'}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                });
                html += '</div>';
                html += '<p class="mt-3 text-info"><i class="bi bi-info-circle"></i> Select 3-8 items for optimal racing experience</p>';
                
                itemsList.innerHTML = html;
            } else {
                itemsList.innerHTML = '<div class="alert alert-warning">No items found for this machine</div>';
            }
        })
        .catch(error => {
            itemsList.innerHTML = '<div class="alert alert-danger">Error loading items</div>';
        });
}

function stopRace(raceId, raceName) {
    if (!confirm(`Are you sure you want to stop/cancel the race "${raceName}"?\n\nThis will:\n• Refund all bets to customers\n• Return the prize pool to your account\n• Mark the race as cancelled\n\nThis action cannot be undone.`)) {
        return;
    }
    
    // Show loading state
    const button = event.target;
    const originalText = button.innerHTML;
    button.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Cancelling...';
    button.disabled = true;
    
    // Create form data
    const formData = new FormData();
    formData.append('race_id', raceId);
    
    fetch('../../api/horse-racing/cancel-race.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Show success message
            alert(`✅ ${data.message}`);
            // Reload page to show updated race status
            location.reload();
        } else {
            // Show error message
            alert(`❌ Error: ${data.message}`);
            // Restore button
            button.innerHTML = originalText;
            button.disabled = false;
        }
    })
    .catch(error => {
        alert('❌ Network error occurred. Please try again.');
        // Restore button
        button.innerHTML = originalText;
        button.disabled = false;
    });
}
</script>

<?php require_once __DIR__ . '/../../core/includes/footer.php'; ?> 