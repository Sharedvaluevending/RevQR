<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/business_utils.php';

// Require business role
require_role('business');

// Get business details
try {
    $business_id = getOrCreateBusinessId($pdo, $_SESSION['user_id']);
} catch (Exception $e) {
    $business_id = null;
    $error_message = "Error: " . $e->getMessage();
}

// Handle spin wheel creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_spin_wheel') {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO spin_wheels (business_id, name, description, wheel_type, is_active)
            VALUES (?, ?, ?, ?, 1)
        ");
        $stmt->execute([
            $business_id,
            $_POST['wheel_name'],
            $_POST['wheel_description'],
            $_POST['wheel_type']
        ]);
        $_SESSION['message'] = 'Spin wheel created successfully!';
        $_SESSION['message_type'] = 'success';
        header('Location: spin-wheel.php');
        exit;
    } catch (Exception $e) {
        $_SESSION['message'] = 'Error creating spin wheel: ' . $e->getMessage();
        $_SESSION['message_type'] = 'danger';
    }
}

// Get current spin wheel selection
$current_wheel_id = isset($_GET['wheel_id']) ? (int)$_GET['wheel_id'] : null;

// Get all spin wheels for this business
$spin_wheels = [];
if ($business_id) {
    $stmt = $pdo->prepare("
        SELECT sw.*, 
               c.name as campaign_name,
               qr.code as qr_code,
               COUNT(r.id) as reward_count
        FROM spin_wheels sw
        LEFT JOIN campaigns c ON sw.campaign_id = c.id
        LEFT JOIN qr_codes qr ON sw.qr_code_id = qr.id
        LEFT JOIN rewards r ON r.spin_wheel_id = sw.id AND r.active = 1
        WHERE sw.business_id = ? AND sw.is_active = 1
        GROUP BY sw.id
        ORDER BY sw.created_at DESC
    ");
    $stmt->execute([$business_id]);
    $spin_wheels = $stmt->fetchAll();
    
    // If no wheel is selected but we have wheels, select the first one
    if (!$current_wheel_id && !empty($spin_wheels)) {
        $current_wheel_id = $spin_wheels[0]['id'];
    }
}

// Get rewards for the selected wheel
$rewards = [];
if ($current_wheel_id) {
    $stmt = $pdo->prepare("SELECT * FROM rewards WHERE spin_wheel_id = ? AND active = 1 ORDER BY rarity_level DESC");
    $stmt->execute([$current_wheel_id]);
    $rewards = $stmt->fetchAll();
}

// Get all lists for this business (for adding rewards)
$lists = [];
if ($business_id) {
    try {
        // Use new voting_lists table structure
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
    } catch (Exception $e) {
        // Fallback to old lists table if voting_lists doesn't exist
        $stmt = $pdo->prepare("
            SELECT l.*, 
                   COUNT(i.id) as item_count,
                   DATE_FORMAT(l.created_at, '%Y-%m-%d %H:%i') as formatted_date
            FROM lists l
            LEFT JOIN items i ON l.id = i.list_id
            WHERE l.business_id = ?
            GROUP BY l.id
            ORDER BY l.name
        ");
        $stmt->execute([$business_id]);
        $lists = $stmt->fetchAll();
    }
}

// Get spin metrics - handle missing table gracefully
try {
    $total_spins = $pdo->query("SELECT COUNT(*) FROM spin_results")->fetchColumn();
    $total_big_wins = $pdo->query("SELECT COUNT(*) FROM spin_results WHERE is_big_win = 1")->fetchColumn();
    $prize_counts = $pdo->query("SELECT prize_won, COUNT(*) as count FROM spin_results GROUP BY prize_won ORDER BY count DESC")->fetchAll();
} catch (PDOException $e) {
    // If spin_results table doesn't exist, set defaults
    $total_spins = 0;
    $total_big_wins = 0;
    $prize_counts = [];
    error_log("Spin results table missing: " . $e->getMessage());
}

// Get business details
$stmt = $pdo->prepare("SELECT id FROM businesses WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$business = $stmt->fetch();
$business_id = $business ? $business['id'] : null;

// Fetch all lists/campaigns for this business
$lists = [];
if ($business_id) {
    try {
        $stmt = $pdo->prepare("SELECT id, name FROM voting_lists WHERE business_id = ? ORDER BY name");
        $stmt->execute([$business_id]);
        $lists = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // If voting_lists table doesn't exist, try lists table
        try {
            $stmt = $pdo->prepare("SELECT id, name FROM lists WHERE business_id = ? ORDER BY name");
            $stmt->execute([$business_id]);
            $lists = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $lists = [];
            error_log("Lists table issue: " . $e->getMessage());
        }
    }
}

// Handle reward activation toggle
if (isset($_POST['toggle_reward_id'])) {
    $reward_id = (int)$_POST['toggle_reward_id'];
    try {
        $stmt = $pdo->prepare("SELECT active FROM rewards WHERE id = ?");
        $stmt->execute([$reward_id]);
        $current = $stmt->fetchColumn();
        $pdo->prepare("UPDATE rewards SET active = ? WHERE id = ?")->execute([$current ? 0 : 1, $reward_id]);
        $_SESSION['message'] = 'Reward status updated successfully!';
        $_SESSION['message_type'] = 'success';
    } catch (PDOException $e) {
        $_SESSION['message'] = 'Error updating reward status: ' . $e->getMessage();
        $_SESSION['message_type'] = 'danger';
    }
    header('Location: spin-wheel.php');
    exit;
}

// Handle spin settings update
if (isset($_POST['action']) && $_POST['action'] === 'update_spin_settings') {
    $settings = [
        'spin_hoodie_probability' => floatval($_POST['hoodie_probability']),
        'spin_bogo_probability' => floatval($_POST['bogo_probability']),
        'spin_nothing_probability' => floatval($_POST['nothing_probability']),
        'spin_cooldown_hours' => intval($_POST['cooldown_hours'])
    ];
    $stmt = $pdo->prepare("UPDATE system_settings SET value = :value WHERE setting_key = :key");
    foreach ($settings as $key => $value) {
        $stmt->execute(['key' => $key, 'value' => $value]);
    }
    $_SESSION['message'] = 'Spin settings updated successfully!';
    $_SESSION['message_type'] = 'success';
    header('Location: spin-wheel.php');
    exit;
}

// Fetch current spin settings
$spin_settings = $pdo->query("SELECT setting_key, value FROM system_settings WHERE setting_key IN ('spin_hoodie_probability','spin_bogo_probability','spin_nothing_probability','spin_cooldown_hours')")->fetchAll(PDO::FETCH_KEY_PAIR);

// Handle reward add/edit/deactivate
if (isset($_POST['reward_action'])) {
    // Validate spin_wheel_id
    if (!isset($_POST['spin_wheel_id']) || !$_POST['spin_wheel_id']) {
        $_SESSION['message'] = 'Please select a spin wheel for this reward.';
        $_SESSION['message_type'] = 'danger';
        header('Location: spin-wheel.php');
        exit;
    }
    
    $spin_wheel_id = (int)$_POST['spin_wheel_id'];
    
    // Verify the spin wheel belongs to this business
    $stmt = $pdo->prepare("SELECT id FROM spin_wheels WHERE id = ? AND business_id = ?");
    $stmt->execute([$spin_wheel_id, $business_id]);
    if (!$stmt->fetch()) {
        $_SESSION['message'] = 'Invalid spin wheel selected.';
        $_SESSION['message_type'] = 'danger';
        header('Location: spin-wheel.php');
        exit;
    }
    
    $fields = [
        'spin_wheel_id' => $spin_wheel_id,
        'name' => $_POST['reward_name'],
        'description' => $_POST['reward_description'],
        'rarity_level' => intval($_POST['reward_rarity']),
        'image_url' => $_POST['reward_image_url'],
        'code' => $_POST['reward_code'],
        'link' => $_POST['reward_link'],
        'active' => isset($_POST['reward_active']) ? 1 : 0
    ];
    
    if ($_POST['reward_action'] === 'add') {
        $stmt = $pdo->prepare("INSERT INTO rewards (spin_wheel_id, name, description, rarity_level, image_url, code, link, active) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$fields['spin_wheel_id'], $fields['name'], $fields['description'], $fields['rarity_level'], $fields['image_url'], $fields['code'], $fields['link'], $fields['active']]);
        $_SESSION['message'] = 'Prize added successfully!';
        $_SESSION['message_type'] = 'success';
    } elseif ($_POST['reward_action'] === 'edit' && isset($_POST['reward_id'])) {
        $stmt = $pdo->prepare("UPDATE rewards SET spin_wheel_id=?, name=?, description=?, rarity_level=?, image_url=?, code=?, link=?, active=? WHERE id=? AND spin_wheel_id=?");
        $stmt->execute([$fields['spin_wheel_id'], $fields['name'], $fields['description'], $fields['rarity_level'], $fields['image_url'], $fields['code'], $fields['link'], $fields['active'], intval($_POST['reward_id']), $fields['spin_wheel_id']]);
        $_SESSION['message'] = 'Prize updated successfully!';
        $_SESSION['message_type'] = 'success';
    }
    
    header('Location: spin-wheel.php?wheel_id=' . $spin_wheel_id);
    exit;
}

require_once __DIR__ . '/../core/includes/header.php';
?>

<style>
/* Custom table styling to fix visibility issues */
#rewardsTable {
    background: rgba(255, 255, 255, 0.15) !important;
    backdrop-filter: blur(20px) !important;
    border-radius: 12px !important;
    overflow: hidden !important;
}

#rewardsTable thead th {
    background: rgba(30, 60, 114, 0.6) !important;
    color: #ffffff !important;
    font-weight: 600 !important;
    border-bottom: 2px solid rgba(255, 255, 255, 0.3) !important;
    padding: 1rem 0.75rem !important;
    position: sticky !important;
    top: 0 !important;
    z-index: 10 !important;
}

#rewardsTable tbody td {
    background: rgba(255, 255, 255, 0.12) !important;
    color: rgba(255, 255, 255, 0.95) !important;
    border-bottom: 1px solid rgba(255, 255, 255, 0.15) !important;
    padding: 0.875rem 0.75rem !important;
}

#rewardsTable tbody tr:hover td {
    background: rgba(255, 255, 255, 0.18) !important;
    color: #ffffff !important;
}

/* Fix form controls inside table */
#rewardsTable .form-control {
    background: rgba(255, 255, 255, 0.9) !important;
    color: #333333 !important;
    border: 1px solid rgba(255, 255, 255, 0.3) !important;
}

/* Badge styling improvements */
#rewardsTable .badge {
    font-weight: 500 !important;
    padding: 0.375rem 0.5rem !important;
}

/* Button styling inside table */
#rewardsTable .btn-primary,
#rewardsTable .btn-outline-danger,
#rewardsTable .btn-outline-success {
    background: rgba(255, 255, 255, 0.1) !important;
    border-color: rgba(255, 255, 255, 0.3) !important;
    color: rgba(255, 255, 255, 0.9) !important;
}

#rewardsTable .btn-primary:hover {
    background: rgba(13, 110, 253, 0.8) !important;
    color: #ffffff !important;
}

#rewardsTable .btn-outline-success:hover {
    background: rgba(25, 135, 84, 0.8) !important;
    color: #ffffff !important;
}

#rewardsTable .btn-outline-danger:hover {
    background: rgba(220, 53, 69, 0.8) !important;
    color: #ffffff !important;
}

/* Link styling improvements */
#rewardsTable a {
    color: #64b5f6 !important;
    text-decoration: underline !important;
}

#rewardsTable a:hover {
    color: #ffffff !important;
}

/* Image styling */
#rewardsTable img {
    border-radius: 4px !important;
    box-shadow: 0 2px 4px rgba(0,0,0,0.2) !important;
}

/* Star rating styling */
#rewardsTable .bi-star-fill {
    filter: drop-shadow(0 1px 2px rgba(0,0,0,0.3)) !important;
}

/* Enhanced Spin Wheel Styling */
.spin-wheel-container {
    position: relative;
    padding: 20px;
    background: transparent;
    border-radius: 50%;
    transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
}

.spin-wheel-container:hover {
    transform: scale(1.02) translateY(-2px);
}

#business-prize-wheel {
    border-radius: 50%;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4);
    transition: all 0.4s ease;
    border: 2px solid rgba(0, 0, 0, 0.6);
}

#business-prize-wheel:hover {
    box-shadow: 0 12px 32px rgba(0, 0, 0, 0.5);
}

/* Enhanced translucent glass effects for metrics */
.metrics-glass-card {
    background: rgba(255, 255, 255, 0.12) !important;
    backdrop-filter: blur(25px) !important;
    border: 1px solid rgba(255, 255, 255, 0.2) !important;
    border-radius: 16px !important;
    box-shadow: 
        0 8px 32px rgba(31, 38, 135, 0.37),
        inset 0 1px 0 rgba(255, 255, 255, 0.1) !important;
}

.metrics-glass-card .list-group-item {
    background: rgba(255, 255, 255, 0.08) !important;
    backdrop-filter: blur(15px) !important;
    border: 1px solid rgba(255, 255, 255, 0.1) !important;
    color: rgba(255, 255, 255, 0.95) !important;
    margin-bottom: 4px !important;
    border-radius: 8px !important;
    transition: all 0.3s ease !important;
}

.metrics-glass-card .list-group-item:hover {
    background: rgba(255, 255, 255, 0.15) !important;
    transform: translateX(4px) !important;
    border: 1px solid rgba(255, 255, 255, 0.2) !important;
}

.metrics-glass-card h4,
.metrics-glass-card h6 {
    color: rgba(255, 255, 255, 0.95) !important;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3) !important;
}

.metrics-glass-card .badge {
    background: rgba(13, 202, 240, 0.8) !important;
    backdrop-filter: blur(10px) !important;
    border: 1px solid rgba(255, 255, 255, 0.2) !important;
    color: rgba(255, 255, 255, 0.95) !important;
    font-weight: 600 !important;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3) !important;
}

.metrics-number {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.15), rgba(255, 255, 255, 0.05)) !important;
    backdrop-filter: blur(10px) !important;
    border: 1px solid rgba(255, 255, 255, 0.2) !important;
    border-radius: 12px !important;
    padding: 8px 16px !important;
    color: #ffffff !important;
    font-weight: 700 !important;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.4) !important;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2) !important;
}

/* Mobile responsive design */
@media (max-width: 768px) {
    .spin-wheel-container {
        max-width: 360px !important;
        padding: 10px !important;
        margin: 0 auto !important;
    }
    
    #business-prize-wheel {
        width: 340px !important;
        height: 340px !important;
    }
    
    .metrics-glass-card {
        margin-top: 20px !important;
    }
    
    .metrics-glass-card .list-group-item {
        padding: 12px 16px !important;
        font-size: 14px !important;
    }
    
    .metrics-number {
        font-size: 18px !important;
        padding: 6px 12px !important;
    }
    
    #test-spin-btn {
        width: 100% !important;
        margin-top: 16px !important;
        font-size: 16px !important;
        padding: 12px 24px !important;
    }
    
    .container {
        padding-left: 10px !important;
        padding-right: 10px !important;
    }
    
    .card {
        margin-bottom: 16px !important;
    }
    
    /* Stack columns on mobile */
    .col-lg-6 {
        flex: 0 0 100% !important;
        max-width: 100% !important;
    }
    
    /* Compact settings form on mobile */
    .form-control-sm {
        font-size: 0.8rem !important;
    }
    
    .form-label.small {
        font-size: 0.75rem !important;
        margin-bottom: 0.25rem !important;
    }
}

/* FIX: Desktop mode on mobile - ensure natural Bootstrap behavior */
@media (min-width: 768px) and (max-width: 1024px) {
    .container, .container-fluid {
        max-width: 1200px !important;
        width: 100% !important;
        margin: 0 auto !important;
        padding-left: 15px !important;
        padding-right: 15px !important;
    }
    
    /* Ensure Bootstrap grid behaves naturally */
    .row {
        width: 100% !important;
        margin-left: -15px !important;
        margin-right: -15px !important;
        display: flex !important;
        flex-wrap: wrap !important;
    }
    
    [class*="col-"] {
        position: relative !important;
        width: 100% !important;
        padding-left: 15px !important;
        padding-right: 15px !important;
    }
    
    .col-md-6 { 
        flex: 0 0 50% !important; 
        max-width: 50% !important;
    }
    
    .col-lg-6 {
        flex: 0 0 50% !important;
        max-width: 50% !important;
    }
    
    .card {
        width: 100% !important;
        margin-bottom: 1rem !important;
    }
}

@media (max-width: 576px) {
    .spin-wheel-container {
        max-width: 320px !important;
        padding: 8px !important;
    }
    
    #business-prize-wheel {
        width: 290px !important;
        height: 290px !important;
    }
    
    .metrics-glass-card h4 {
        font-size: 1.1rem !important;
    }
    
    .metrics-glass-card .list-group-item {
        padding: 10px 12px !important;
        font-size: 13px !important;
    }
    
    /* Smaller wheel container for mobile */
    .spin-wheel-container {
        max-width: 300px !important;
    }
}

/* Spinning animation class */
.spinning {
    animation: wheelSpin 0.1s linear infinite;
}

@keyframes wheelSpin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

/* Prize result animation */
.prize-result-enter {
    animation: prizeEnter 0.8s cubic-bezier(0.68, -0.55, 0.265, 1.55);
}

@keyframes prizeEnter {
    0% {
        opacity: 0;
        transform: scale(0.5) translateY(-20px);
    }
    60% {
        transform: scale(1.1) translateY(0);
    }
    100% {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
}

/* Glowing button effect */
#test-spin-btn {
    position: relative;
    overflow: hidden;
    background: linear-gradient(45deg, #28a745, #20c997, #28a745) !important;
    background-size: 200% 200%;
    animation: gradientShift 3s ease infinite;
    box-shadow: 0 4px 15px rgba(40, 167, 69, 0.4);
    transition: all 0.3s ease;
}

#test-spin-btn:hover {
    box-shadow: 0 6px 20px rgba(40, 167, 69, 0.6);
    transform: translateY(-2px);
}

#test-spin-btn:disabled {
    background: linear-gradient(45deg, #6c757d, #495057, #6c757d) !important;
    box-shadow: 0 2px 8px rgba(108, 117, 125, 0.3);
    transform: none;
}

@keyframes gradientShift {
    0% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
    100% { background-position: 0% 50%; }
}

/* Confetti animation */
@keyframes confettiFall {
    0% {
        transform: translateY(-100vh) rotate(0deg);
        opacity: 1;
    }
    100% {
        transform: translateY(100vh) rotate(720deg);
        opacity: 0;
    }
}

/* Enhanced prize display styling */
.prize-result-enter h5 {
    text-shadow: 2px 2px 4px rgba(0,0,0,0.1);
    background: linear-gradient(45deg, #155724, #0d4f1c);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}
</style>

<div class="container py-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="mb-0"><i class="bi bi-trophy-fill text-warning me-2"></i>Spin Wheel Management</h1>
                    <p class="text-muted">Create engaging spin wheels, set fair odds, and track customer engagement.</p>
                </div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createWheelModal">
                    <i class="bi bi-plus-circle me-2"></i>Create New Spin Wheel
                </button>
            </div>
        </div>
    </div>

    <!-- Quick Setup Guide -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="alert alert-info alert-dismissible fade show" role="alert">
                <h6><i class="bi bi-lightbulb me-2"></i>Quick Setup Guide:</h6>
                <div class="row">
                    <div class="col-md-3">
                        <strong>1. Create Wheel</strong><br>
                        <small>Set up your wheel with name and description</small>
                    </div>
                    <div class="col-md-3">
                        <strong>2. Add Rewards</strong><br>
                        <small>Add prizes with rarity levels (1=common, 10=legendary)</small>
                    </div>
                    <div class="col-md-3">
                        <strong>3. Generate QR</strong><br>
                        <small>Create QR code linking to your wheel</small>
                    </div>
                    <div class="col-md-3">
                        <strong>4. Test & Deploy</strong><br>
                        <small>Test the wheel and place QR codes</small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        </div>
    </div>

    <!-- Spin Wheel Selector -->
    <?php if (!empty($spin_wheels)): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="card gradient-card-primary">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Select Spin Wheel to Manage:</label>
                            <select class="form-select" id="wheelSelector" onchange="selectWheel(this.value)">
                                <option value="">Choose a spin wheel...</option>
                                <?php foreach ($spin_wheels as $wheel): ?>
                                    <option value="<?php echo $wheel['id']; ?>" <?php echo $current_wheel_id == $wheel['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($wheel['name']); ?>
                                        (<?php echo ucfirst($wheel['wheel_type']); ?> - <?php echo $wheel['reward_count']; ?> prizes)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php if ($current_wheel_id): ?>
                        <div class="col-md-6">
                            <?php 
                            $current_wheel = array_filter($spin_wheels, function($w) use ($current_wheel_id) { 
                                return $w['id'] == $current_wheel_id; 
                            });
                            $current_wheel = reset($current_wheel);
                            ?>
                            <div class="bg-white bg-opacity-10 p-3 rounded">
                                <h6 class="mb-1"><?php echo htmlspecialchars($current_wheel['name']); ?></h6>
                                <small class="text-muted">
                                    Type: <?php echo ucfirst($current_wheel['wheel_type']); ?> | 
                                    Prizes: <?php echo $current_wheel['reward_count']; ?> |
                                    <?php if ($current_wheel['campaign_name']): ?>
                                        Campaign: <?php echo htmlspecialchars($current_wheel['campaign_name']); ?>
                                    <?php elseif ($current_wheel['qr_code']): ?>
                                        QR Code: <?php echo htmlspecialchars($current_wheel['qr_code']); ?>
                                    <?php else: ?>
                                        Standalone Wheel
                                    <?php endif; ?>
                                </small>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($current_wheel_id): ?>
    <div class="row mb-4">
        <!-- Spin Wheel Column -->
        <div class="col-lg-6 mb-4">
            <div class="card gradient-card-primary shadow-lg h-100">
                <div class="card-body text-center">
                    <h4 class="mb-3"><i class="bi bi-trophy-fill text-warning me-2"></i>Live Wheel Preview</h4>
                    <div id="business-wheel-container" class="spin-wheel-container mb-3 mx-auto" style="max-width:450px;">
                        <canvas id="business-prize-wheel" width="420" height="420"></canvas>
                    </div>
                    <button type="button" class="btn btn-success btn-lg" id="test-spin-btn">
                        <i class="bi bi-play-circle me-2"></i>Test Spin (Simulate)
                    </button>
                    <div id="test-spin-result" class="mt-3"></div>
                </div>
            </div>
        </div>
        
        <!-- Metrics & Settings Column -->
        <div class="col-lg-6 mb-4">
            <div class="row h-100">
                <!-- Metrics Card -->
                <div class="col-12 mb-3">
                    <div class="card metrics-glass-card shadow-lg">
                        <div class="card-body">
                            <h5 class="mb-3"><i class="bi bi-bar-chart-fill text-primary me-2"></i>Spin Metrics</h5>
                            <div class="row text-center mb-3">
                                <div class="col-6">
                                    <div class="metrics-number bg-primary text-white p-2 rounded">
                                        <div class="h4 mb-0"><?php echo $total_spins; ?></div>
                                        <small>Total Spins</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="metrics-number bg-warning text-dark p-2 rounded">
                                        <div class="h4 mb-0"><?php echo $total_big_wins; ?></div>
                                        <small>Big Wins</small>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if (!empty($prize_counts)): ?>
                            <h6 class="mb-2">Top Prizes Won</h6>
                            <div style="max-height: 120px; overflow-y: auto;">
                                <?php foreach (array_slice($prize_counts, 0, 5) as $row): ?>
                                    <div class="d-flex justify-content-between align-items-center mb-1 p-1 border-bottom">
                                        <small><?php echo htmlspecialchars($row['prize_won']); ?></small>
                                        <span class="badge bg-info"><?php echo $row['count']; ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Settings Card -->
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="card-title mb-0"><i class="bi bi-gear me-2"></i>Spin Settings</h6>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <input type="hidden" name="action" value="update_spin_settings">
                                <div class="row">
                                    <div class="col-md-6 mb-2">
                                        <label class="form-label small">Main Prize (%)</label>
                                        <input type="number" class="form-control form-control-sm" name="hoodie_probability" value="<?php echo htmlspecialchars($spin_settings['spin_hoodie_probability'] ?? 5); ?>" min="0" max="100" step="0.1">
                                    </div>
                                    <div class="col-md-6 mb-2">
                                        <label class="form-label small">Secondary (%)</label>
                                        <input type="number" class="form-control form-control-sm" name="bogo_probability" value="<?php echo htmlspecialchars($spin_settings['spin_bogo_probability'] ?? 15); ?>" min="0" max="100" step="0.1">
                                    </div>
                                    <div class="col-md-6 mb-2">
                                        <label class="form-label small">Nothing (%)</label>
                                        <input type="number" class="form-control form-control-sm" name="nothing_probability" value="<?php echo htmlspecialchars($spin_settings['spin_nothing_probability'] ?? 80); ?>" min="0" max="100" step="0.1">
                                    </div>
                                    <div class="col-md-6 mb-2">
                                        <label class="form-label small">Cooldown (hrs)</label>
                                        <input type="number" class="form-control form-control-sm" name="cooldown_hours" value="<?php echo htmlspecialchars($spin_settings['spin_cooldown_hours'] ?? 24); ?>" min="1" max="168">
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary btn-sm mt-2">Update Settings</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="row mb-4">
        <div class="col-12">
            <div class="card gradient-card-primary shadow-lg">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="mb-0"><i class="bi bi-gift-fill text-success me-2"></i>Rewards Management</h4>
                    <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#rewardModal" onclick="openRewardModal('add')"><i class="bi bi-plus-circle me-1"></i>Add Prize</button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle" id="rewardsTable">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Description</th>
                                    <th>Rarity</th>
                                    <th>Image</th>
                                    <th>Code</th>
                                    <th>Link</th>
                                    <th>Active</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rewards as $reward): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($reward['name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($reward['description']); ?></td>
                                        <td><?php for ($i = 0; $i < $reward['rarity_level']; $i++): ?><i class="bi bi-star-fill text-warning"></i><?php endfor; ?></td>
                                        <td><?php if ($reward['image_url']): ?><img src="<?php echo htmlspecialchars($reward['image_url']); ?>" alt="Prize Image" style="max-width:40px;max-height:40px;"/><?php endif; ?></td>
                                        <td><?php echo htmlspecialchars($reward['code']); ?></td>
                                        <td><?php if ($reward['link']): ?><a href="<?php echo htmlspecialchars($reward['link']); ?>" target="_blank">Link</a><?php endif; ?></td>
                                        <td><span class="badge bg-<?php echo $reward['active'] ? 'success' : 'secondary'; ?>"><?php echo $reward['active'] ? 'Active' : 'Inactive'; ?></span></td>
                                        <td>
                                            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#rewardModal" onclick='openRewardModal("edit", <?php echo json_encode($reward); ?>)'>Edit</button>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="toggle_reward_id" value="<?php echo $reward['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-<?php echo $reward['active'] ? 'danger' : 'success'; ?> ms-1"><?php echo $reward['active'] ? 'Deactivate' : 'Activate'; ?></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
<!-- Reward Modal -->
<div class="modal fade" id="rewardModal" tabindex="-1" aria-labelledby="rewardModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" id="rewardForm">
        <div class="modal-header">
          <h5 class="modal-title" id="rewardModalLabel">Add/Edit Prize</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="reward_action" id="reward_action" value="add">
          <input type="hidden" name="reward_id" id="reward_id">
          <input type="hidden" name="spin_wheel_id" id="reward_spin_wheel_id" value="<?php echo $current_wheel_id; ?>">
          
          <div class="mb-3">
            <label class="form-label">Name</label>
            <input type="text" class="form-control" name="reward_name" id="reward_name" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Description</label>
            <textarea class="form-control" name="reward_description" id="reward_description" rows="2"></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label">Rarity Level (1-10)</label>
            <input type="number" class="form-control" name="reward_rarity" id="reward_rarity" min="1" max="10" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Image URL (Optional)</label>
            <input type="url" class="form-control" name="reward_image_url" id="reward_image_url">
          </div>
          <div class="mb-3">
            <label class="form-label">Code/Coupon (Optional)</label>
            <input type="text" class="form-control" name="reward_code" id="reward_code">
          </div>
          <div class="mb-3">
            <label class="form-label">Link (Optional)</label>
            <input type="url" class="form-control" name="reward_link" id="reward_link">
          </div>
          <div class="mb-3">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="reward_active" id="reward_active" value="1" checked>
              <label class="form-check-label" for="reward_active">Active Prize</label>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save Prize</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Create Spin Wheel Modal -->
<div class="modal fade" id="createWheelModal" tabindex="-1" aria-labelledby="createWheelModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" id="createWheelForm">
        <div class="modal-header">
          <h5 class="modal-title" id="createWheelModalLabel">Create New Spin Wheel</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="action" value="create_spin_wheel">
          
          <div class="mb-3">
            <label class="form-label">Spin Wheel Name</label>
            <input type="text" class="form-control" name="wheel_name" required placeholder="Enter wheel name">
          </div>
          <div class="mb-3">
            <label class="form-label">Description</label>
            <textarea class="form-control" name="wheel_description" rows="2" placeholder="Optional description"></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label">Wheel Type</label>
            <select class="form-select" name="wheel_type" required>
              <option value="campaign">Campaign Wheel</option>
              <option value="machine">Machine Wheel</option>
              <option value="qr_standalone">QR Standalone Wheel</option>
            </select>
            <div class="form-text">
              <strong>Campaign:</strong> For specific campaigns<br>
              <strong>Machine:</strong> For specific vending machines<br>
              <strong>QR Standalone:</strong> For standalone QR codes
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Create Spin Wheel</button>
        </div>
      </form>
    </div>
  </div>
</div>
<script>
// Function to handle spin wheel selection
function selectWheel(wheelId) {
    if (wheelId) {
        window.location.href = `spin-wheel.php?wheel_id=${wheelId}`;
    }
}

function openRewardModal(action, reward = null) {
    const modal = document.getElementById('rewardModal');
    const title = document.getElementById('rewardModalLabel');
    const form = document.getElementById('rewardForm');
    
    // Reset form
    form.reset();
    
    // Set spin wheel ID for the current selected wheel
    document.getElementById('reward_spin_wheel_id').value = '<?php echo $current_wheel_id; ?>';
    
    if (action === 'add') {
        title.textContent = 'Add New Prize';
        document.getElementById('reward_action').value = 'add';
        document.getElementById('reward_active').checked = true;
    } else if (action === 'edit' && reward) {
        title.textContent = 'Edit Prize';
        document.getElementById('reward_action').value = 'edit';
        document.getElementById('reward_id').value = reward.id;
        document.getElementById('reward_spin_wheel_id').value = reward.spin_wheel_id;
        document.getElementById('reward_name').value = reward.name;
        document.getElementById('reward_description').value = reward.description;
        document.getElementById('reward_rarity').value = reward.rarity_level;
        document.getElementById('reward_image_url').value = reward.image_url || '';
        document.getElementById('reward_code').value = reward.code || '';
        document.getElementById('reward_link').value = reward.link || '';
        document.getElementById('reward_active').checked = reward.active == 1;
    }
}

// Enhanced Business Prize Wheel Preview Logic
(function() {
    const rewards = <?php echo json_encode($rewards); ?>;
    
    // Enhanced darker gradient color palette for high-end look
    const gradientColors = [
        ['#1a1a2e', '#16213e', '#0f3460'], // Dark navy gradient
        ['#2d1b69', '#11998e', '#38ef7d'], // Purple to teal gradient
        ['#434343', '#000000', '#1e3c72'], // Black to dark blue gradient
        ['#8360c3', '#2ebf91', '#159957'], // Purple to green gradient
        ['#ff512f', '#dd2476', '#8e44ad'], // Red to purple gradient
        ['#667db6', '#0082c8', '#0052d4'], // Blue gradient
        ['#f093fb', '#f5576c', '#c44569'], // Pink gradient
        ['#ffecd2', '#fcb69f', '#ff8a80'], // Warm gradient
    ];
    
    const canvas = document.getElementById('business-prize-wheel');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    
    // Mobile responsive canvas sizing
    function setupCanvasSize() {
        const container = document.getElementById('business-wheel-container');
        const containerWidth = container.offsetWidth;
        let canvasSize;
        
        if (window.innerWidth <= 576) {
            // Small mobile phones - increased from 220 to 290
            canvasSize = Math.min(290, containerWidth - 40);
        } else if (window.innerWidth <= 768) {
            // Larger mobile phones and small tablets - increased from 260 to 340
            canvasSize = Math.min(340, containerWidth - 40);
        } else {
            // Desktop and large tablets - increased from 400 to 520
            canvasSize = Math.min(520, containerWidth - 40);
        }
        
        canvas.width = canvasSize;
        canvas.height = canvasSize;
        canvas.style.width = canvasSize + 'px';
        canvas.style.height = canvasSize + 'px';
        
        return {
            centerX: canvasSize / 2,
            centerY: canvasSize / 2,
            radius: (canvasSize / 2) - 20
        };
    }
    
    let dimensions = setupCanvasSize();
    let { centerX, centerY, radius } = dimensions;
    let rotation = 0, spinning = false;
    
    // Responsive font sizing
    function getResponsiveFontSize(baseFontSize) {
        const scaleFactor = canvas.width / 520; // Updated from 400 to 520 for new larger base size
        return Math.max(10, Math.floor(baseFontSize * scaleFactor));
    }
    
    function createGradient(startColor, middleColor, endColor, startAngle, endAngle) {
        const gradient = ctx.createRadialGradient(centerX, centerY, 40 * (canvas.width / 520), centerX, centerY, radius);
        gradient.addColorStop(0, startColor);
        gradient.addColorStop(0.6, middleColor);
        gradient.addColorStop(1, endColor);
        return gradient;
    }
    
    function drawWheel() {
        const sliceAngle = (2 * Math.PI) / rewards.length;
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        
        // Remove glow effects to prevent double circles
        ctx.shadowColor = 'transparent';
        ctx.shadowBlur = 0;
        ctx.shadowOffsetX = 0;
        ctx.shadowOffsetY = 0;
        
        rewards.forEach((reward, index) => {
            const startAngle = index * sliceAngle + rotation;
            const endAngle = startAngle + sliceAngle;
            const colorSet = gradientColors[index % gradientColors.length];
            
            // Create sophisticated gradient
            const gradient = createGradient(colorSet[0], colorSet[1], colorSet[2], startAngle, endAngle);
            
            ctx.beginPath();
            ctx.moveTo(centerX, centerY);
            ctx.arc(centerX, centerY, radius, startAngle, endAngle);
            ctx.closePath();
            ctx.fillStyle = gradient;
            ctx.fill();
            
            // Black divider lines for premium look
            ctx.strokeStyle = '#000000';
            ctx.lineWidth = Math.max(2, 3 * (canvas.width / 520));
            ctx.stroke();
            
            // Inner highlight for depth
            ctx.beginPath();
            ctx.moveTo(centerX, centerY);
            ctx.arc(centerX, centerY, radius - (5 * (canvas.width / 520)), startAngle, endAngle);
            ctx.closePath();
            ctx.strokeStyle = 'rgba(255, 255, 255, 0.3)';
            ctx.lineWidth = 1;
            ctx.stroke();
            
            // Enhanced text with better contrast
            ctx.save();
            ctx.translate(centerX, centerY);
            ctx.rotate(startAngle + sliceAngle / 2);
            ctx.textAlign = 'right';
            
            // Premium font selection based on rarity with responsive sizing
            let baseFontSize = 16;
            let fontFamily = 'Segoe UI, Tahoma, Geneva, Verdana, sans-serif';
            
            if (reward.rarity_level >= 8) {
                baseFontSize = 18;
                fontFamily = 'Impact, Arial Black, sans-serif';
            } else if (reward.rarity_level >= 5) {
                baseFontSize = 17;
                fontFamily = 'Trebuchet MS, Arial Black, sans-serif';
            }
            
            const fontSize = getResponsiveFontSize(baseFontSize);
            ctx.font = `bold ${fontSize}px ${fontFamily}`;
            
            // Responsive text positioning
            const textRadius = radius - (30 * (canvas.width / 520));
            
            // High contrast text outline
            ctx.strokeStyle = '#000000';
            ctx.lineWidth = Math.max(2, 3 * (canvas.width / 520));
            ctx.strokeText(reward.name, textRadius, 6);
            
            // White text fill
            ctx.fillStyle = '#ffffff';
            ctx.fillText(reward.name, textRadius, 6);
            
            // Premium sparkle effect for rare items with responsive sizing
            if (reward.rarity_level >= 7) {
                const starSize = getResponsiveFontSize(baseFontSize - 2);
                ctx.font = `${starSize}px serif`;
                ctx.fillStyle = '#FFD700';
                ctx.strokeStyle = '#B8860B';
                ctx.lineWidth = 1;
                const starOffset = 20 * (canvas.width / 520);
                ctx.strokeText('★', textRadius + starOffset, -8);
                ctx.fillText('★', textRadius + starOffset, -8);
                ctx.strokeText('★', textRadius + starOffset, 20);
                ctx.fillText('★', textRadius + starOffset, 20);
            }
            
            ctx.restore();
        });
        
        // Clean center hub with responsive sizing
        const centerRadius = 30 * (canvas.width / 520);
        const centerGradient = ctx.createRadialGradient(centerX, centerY, 0, centerX, centerY, centerRadius);
        centerGradient.addColorStop(0, '#ffffff');
        centerGradient.addColorStop(0.3, '#f8f9fa');
        centerGradient.addColorStop(0.7, '#dee2e6');
        centerGradient.addColorStop(1, '#495057');
        
        ctx.beginPath();
        ctx.arc(centerX, centerY, centerRadius, 0, 2 * Math.PI);
        ctx.fillStyle = centerGradient;
        ctx.fill();
        ctx.strokeStyle = '#000000';
        ctx.lineWidth = Math.max(2, 3 * (canvas.width / 520));
        ctx.stroke();
        
        // Premium arrow pointer with responsive sizing
        ctx.save();
        const arrowScale = canvas.width / 520;
        ctx.translate(centerX, centerY - radius - (15 * arrowScale));
        ctx.fillStyle = '#DC143C';
        ctx.strokeStyle = '#000000';
        ctx.lineWidth = Math.max(2, 3 * arrowScale);
        ctx.beginPath();
        ctx.moveTo(0, 0);
        ctx.lineTo(-20 * arrowScale, -25 * arrowScale);
        ctx.lineTo(20 * arrowScale, -25 * arrowScale);
        ctx.closePath();
        ctx.fill();
        ctx.stroke();
        
        // Arrow highlight
        ctx.fillStyle = 'rgba(255, 255, 255, 0.4)';
        ctx.beginPath();
        ctx.moveTo(0, -5 * arrowScale);
        ctx.lineTo(-15 * arrowScale, -25 * arrowScale);
        ctx.lineTo(15 * arrowScale, -25 * arrowScale);
        ctx.closePath();
        ctx.fill();
        ctx.restore();
    }
    
    // Handle window resize
    window.addEventListener('resize', function() {
        dimensions = setupCanvasSize();
        centerX = dimensions.centerX;
        centerY = dimensions.centerY;
        radius = dimensions.radius;
        drawWheel();
    });
    
    drawWheel();
    
    // Enhanced spin animation with particle effects
    document.getElementById('test-spin-btn').addEventListener('click', function() {
        if (spinning) return;
        spinning = true;
        this.disabled = true;
        this.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Spinning...';
        
        const spinDuration = 6000; // Increased from 4000 to 6000ms for longer spin
        const startTime = Date.now();
        const startRotation = rotation;
        const spinAngle = 8 + Math.random() * 6; // Reduced for more controlled spinning
        
        function animate() {
            const elapsed = Date.now() - startTime;
            const progress = Math.min(elapsed / spinDuration, 1);
            // More realistic deceleration curve (quintic ease-out)
            const easeOut = t => 1 - Math.pow(1 - t, 5);
            rotation = startRotation + (spinAngle * 2 * Math.PI * easeOut(progress));
            drawWheel();
            
            if (progress < 1) {
                requestAnimationFrame(animate);
            } else {
                spinning = false;
                document.getElementById('test-spin-btn').disabled = false;
                document.getElementById('test-spin-btn').innerHTML = '<i class="bi bi-play-circle me-2"></i>Test Spin (Simulate)';
                
                // Pick winner based on actual wheel position
                const sliceAngle = (2 * Math.PI) / rewards.length;
                // Normalize rotation to 0-2π range
                const normalizedRotation = ((rotation % (2 * Math.PI)) + (2 * Math.PI)) % (2 * Math.PI);
                // The arrow points up, so we need to find which slice is at the top (270 degrees / 3π/2)
                const arrowAngle = (3 * Math.PI) / 2;
                // Calculate which slice the arrow is pointing to
                const relativeAngle = (arrowAngle - normalizedRotation + (2 * Math.PI)) % (2 * Math.PI);
                const sliceIndex = Math.floor(relativeAngle / sliceAngle);
                // Ensure index is within bounds
                const selectedIndex = sliceIndex >= 0 && sliceIndex < rewards.length ? sliceIndex : 0;
                const selected = rewards[selectedIndex];
                
                if (selected) {
                    const rarityBadge = selected.rarity_level >= 7 ? 
                        '<span class="badge bg-warning text-dark ms-2 animate__animated animate__pulse animate__infinite">RARE! ✨</span>' : 
                        selected.rarity_level >= 4 ? 
                        '<span class="badge bg-info text-dark ms-2">GOOD! 🎯</span>' : 
                        '<span class="badge bg-success text-dark ms-2">WIN! 🎉</span>';
                    
                    const sparkles = selected.rarity_level >= 7 ? '✨🎊✨' : 
                                    selected.rarity_level >= 4 ? '🎉🎁' : '🎉';
                    
                    document.getElementById('test-spin-result').innerHTML = 
                        `<div class='alert alert-success mt-3 prize-result-enter' style='background: linear-gradient(45deg, #d4edda, #c3e6cb); border: 2px solid #28a745; border-radius: 15px; box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);'>
                            <div class='text-center'>
                                <h5 class='mb-2' style='color: #155724; font-weight: bold;'>
                                    ${sparkles} CONGRATULATIONS! ${sparkles}
                                </h5>
                                <p class='mb-2' style='font-size: 1.2em; color: #155724;'>
                                    <i class='bi bi-gift-fill me-2'></i>
                                    <strong>You Won: ${selected.name}</strong>
                                    ${rarityBadge}
                                </p>
                                ${selected.description ? `<p class='text-muted mb-2'><em>${selected.description}</em></p>` : ''}
                                ${selected.code ? `<div class='mt-3 p-2' style='background: rgba(255,255,255,0.8); border-radius: 8px;'><strong>Redeem Code:</strong> <code style='color: #d63384; font-size: 1.1em; font-weight: bold;'>${selected.code}</code></div>` : ''}
                            </div>
                        </div>`;
                    
                    // Add confetti effect for rare items
                    if (selected.rarity_level >= 7) {
                        setTimeout(() => {
                            // Simple confetti effect with emoji
                            const confettiContainer = document.createElement('div');
                            confettiContainer.style.cssText = 'position: fixed; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none; z-index: 9999;';
                            document.body.appendChild(confettiContainer);
                            
                            for (let i = 0; i < 50; i++) {
                                const confetti = document.createElement('div');
                                confetti.textContent = ['🎉', '🎊', '✨', '🎁', '⭐'][Math.floor(Math.random() * 5)];
                                confetti.style.cssText = `
                                    position: absolute;
                                    top: -20px;
                                    left: ${Math.random() * 100}%;
                                    font-size: ${Math.random() * 20 + 15}px;
                                    animation: confettiFall ${Math.random() * 3 + 2}s linear forwards;
                                `;
                                confettiContainer.appendChild(confetti);
                            }
                            
                            setTimeout(() => document.body.removeChild(confettiContainer), 5000);
                        }, 500);
                    }
                }
            }
        }
        animate();
    });
})();
</script>
<?php require_once __DIR__ . '/../core/includes/footer.php'; ?> 