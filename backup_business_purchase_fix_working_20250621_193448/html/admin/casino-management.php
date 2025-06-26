<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/qr_coin_manager.php';

// Require admin role
require_role('admin');

// Handle form submissions
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'update_global_settings':
            try {
                $global_jackpot_min = (int) $_POST['global_jackpot_min'];
                $global_house_edge = (float) $_POST['global_house_edge'];
                $max_bet_limit = (int) $_POST['max_bet_limit'];
                $min_bet_limit = (int) $_POST['min_bet_limit'];
                
                // Update global casino settings
                $stmt = $pdo->prepare("
                    INSERT INTO casino_global_settings 
                    (id, global_jackpot_min, global_house_edge, max_bet_limit, min_bet_limit, updated_at)
                    VALUES (1, ?, ?, ?, ?, NOW())
                    ON DUPLICATE KEY UPDATE 
                        global_jackpot_min = VALUES(global_jackpot_min),
                        global_house_edge = VALUES(global_house_edge),
                        max_bet_limit = VALUES(max_bet_limit),
                        min_bet_limit = VALUES(min_bet_limit),
                        updated_at = NOW()
                ");
                $stmt->execute([$global_jackpot_min, $global_house_edge, $max_bet_limit, $min_bet_limit]);
                
                $success = 'Global casino settings updated successfully!';
            } catch (Exception $e) {
                $error = 'Error updating settings: ' . $e->getMessage();
            }
            break;
            
        case 'create_prize_template':
            try {
                $prize_name = $_POST['prize_name'];
                $prize_type = $_POST['prize_type'];
                $prize_value = (int) $_POST['prize_value'];
                $win_probability = (float) $_POST['win_probability'];
                $multiplier_min = (int) $_POST['multiplier_min'];
                $multiplier_max = (int) $_POST['multiplier_max'];
                $is_jackpot = isset($_POST['is_jackpot']) ? 1 : 0;
                
                $stmt = $pdo->prepare("
                    INSERT INTO casino_prize_templates 
                    (prize_name, prize_type, prize_value, win_probability, multiplier_min, multiplier_max, is_jackpot, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$prize_name, $prize_type, $prize_value, $win_probability, $multiplier_min, $multiplier_max, $is_jackpot]);
                
                $success = 'Prize template created successfully!';
            } catch (Exception $e) {
                $error = 'Error creating prize template: ' . $e->getMessage();
            }
            break;
    }
}

// Fetch current global settings
$stmt = $pdo->query("SELECT * FROM casino_global_settings WHERE id = 1");
$global_settings = $stmt->fetch() ?: [
    'global_jackpot_min' => 1000,
    'global_house_edge' => 5.0,
    'max_bet_limit' => 100,
    'min_bet_limit' => 1
];

// Fetch casino metrics
$stmt = $pdo->query("
    SELECT 
        COUNT(*) as total_plays,
        SUM(bet_amount) as total_bets,
        SUM(win_amount) as total_winnings,
        AVG(bet_amount) as avg_bet,
        COUNT(DISTINCT user_id) as unique_players,
        COUNT(CASE WHEN is_jackpot = 1 THEN 1 END) as jackpot_wins
    FROM casino_plays 
    WHERE played_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
");
$casino_metrics = $stmt->fetch();

// Fetch business casino activity
$stmt = $pdo->query("
    SELECT 
        b.name as business_name,
        bcs.casino_enabled,
        bcs.max_daily_plays,
        bcs.jackpot_multiplier,
        COUNT(cp.id) as total_plays,
        SUM(cp.bet_amount) as total_bets,
        SUM(cp.win_amount) as total_winnings,
        cdl.plays_count as plays_today
    FROM businesses b
    LEFT JOIN business_casino_settings bcs ON b.id = bcs.business_id
    LEFT JOIN casino_plays cp ON b.id = cp.business_id AND cp.played_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    LEFT JOIN casino_daily_limits cdl ON b.id = cdl.business_id AND cdl.play_date = CURDATE()
    GROUP BY b.id, b.name, bcs.casino_enabled, bcs.max_daily_plays, bcs.jackpot_multiplier, cdl.plays_count
    ORDER BY total_plays DESC
");
$business_activity = $stmt->fetchAll();

// Fetch prize templates
$stmt = $pdo->query("SELECT * FROM casino_prize_templates ORDER BY created_at DESC");
$prize_templates = $stmt->fetchAll();

require_once __DIR__ . '/../core/includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <h1 class="mb-0"><i class="bi bi-dice-5-fill text-danger me-2"></i>Casino Management</h1>
            <p class="text-muted">Manage casino settings, prizes, win rates, and view analytics</p>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle me-2"></i><?php echo $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-triangle me-2"></i><?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Casino Overview Cards -->
    <div class="row g-4 mb-5">
        <div class="col-md-2">
            <div class="card text-center">
                <div class="card-body">
                    <i class="bi bi-controller display-4 text-primary mb-2"></i>
                    <h3 class="text-primary"><?php echo number_format($casino_metrics['total_plays'] ?? 0); ?></h3>
                    <p class="mb-0 small">Total Plays (30d)</p>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-center">
                <div class="card-body">
                    <i class="bi bi-coin display-4 text-warning mb-2"></i>
                    <h3 class="text-warning"><?php echo number_format($casino_metrics['total_bets'] ?? 0); ?></h3>
                    <p class="mb-0 small">Total Bets (30d)</p>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-center">
                <div class="card-body">
                    <i class="bi bi-trophy display-4 text-success mb-2"></i>
                    <h3 class="text-success"><?php echo number_format($casino_metrics['total_winnings'] ?? 0); ?></h3>
                    <p class="mb-0 small">Total Winnings (30d)</p>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-center">
                <div class="card-body">
                    <i class="bi bi-people display-4 text-info mb-2"></i>
                    <h3 class="text-info"><?php echo number_format($casino_metrics['unique_players'] ?? 0); ?></h3>
                    <p class="mb-0 small">Unique Players (30d)</p>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-center">
                <div class="card-body">
                    <i class="bi bi-star display-4 text-danger mb-2"></i>
                    <h3 class="text-danger"><?php echo number_format($casino_metrics['jackpot_wins'] ?? 0); ?></h3>
                    <p class="mb-0 small">Jackpot Wins (30d)</p>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-center">
                <div class="card-body">
                    <i class="bi bi-percent display-4 text-secondary mb-2"></i>
                    <?php 
                    $house_edge = 0;
                    if (($casino_metrics['total_bets'] ?? 0) > 0) {
                        $house_edge = (($casino_metrics['total_bets'] - $casino_metrics['total_winnings']) / $casino_metrics['total_bets']) * 100;
                    }
                    ?>
                    <h3 class="text-secondary"><?php echo number_format($house_edge, 1); ?>%</h3>
                    <p class="mb-0 small">Actual House Edge</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Global Casino Settings -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-gear me-2"></i>Global Casino Settings</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="update_global_settings">
                        
                        <div class="mb-3">
                            <label class="form-label">Global Jackpot Minimum</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-coin"></i></span>
                                <input type="number" class="form-control" name="global_jackpot_min" 
                                       value="<?php echo $global_settings['global_jackpot_min']; ?>" required>
                                <span class="input-group-text">QR Coins</span>
                            </div>
                            <small class="text-muted">Minimum jackpot amount across all businesses</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Target House Edge</label>
                            <div class="input-group">
                                <input type="number" class="form-control" name="global_house_edge" 
                                       value="<?php echo $global_settings['global_house_edge']; ?>" 
                                       step="0.1" min="0" max="20" required>
                                <span class="input-group-text">%</span>
                            </div>
                            <small class="text-muted">Platform profit margin (current: <?php echo number_format($house_edge, 1); ?>%)</small>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Min Bet Limit</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-coin"></i></span>
                                        <input type="number" class="form-control" name="min_bet_limit" 
                                               value="<?php echo $global_settings['min_bet_limit']; ?>" required>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Max Bet Limit</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-coin"></i></span>
                                        <input type="number" class="form-control" name="max_bet_limit" 
                                               value="<?php echo $global_settings['max_bet_limit']; ?>" required>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check me-1"></i>Update Global Settings
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Prize Template Management -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="bi bi-gift me-2"></i>Prize Templates</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="create_prize_template">
                        
                        <div class="mb-3">
                            <label class="form-label">Prize Name</label>
                            <input type="text" class="form-control" name="prize_name" 
                                   placeholder="e.g., Rare Avatar Combo" required>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Prize Type</label>
                                    <select class="form-select" name="prize_type" required>
                                        <option value="qr_coins">QR Coins</option>
                                        <option value="multiplier">Bet Multiplier</option>
                                        <option value="jackpot">Jackpot</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Base Value</label>
                                    <input type="number" class="form-control" name="prize_value" 
                                           placeholder="100" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Win Probability (%)</label>
                            <input type="number" class="form-control" name="win_probability" 
                                   step="0.01" min="0" max="100" placeholder="5.5" required>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Min Multiplier</label>
                                    <input type="number" class="form-control" name="multiplier_min" 
                                           value="1" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Max Multiplier</label>
                                    <input type="number" class="form-control" name="multiplier_max" 
                                           value="10" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_jackpot" id="isJackpot">
                                <label class="form-check-label" for="isJackpot">
                                    <strong>Jackpot Prize</strong> (rare occurrence)
                                </label>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-plus me-1"></i>Create Prize Template
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Business Casino Activity -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="bi bi-building me-2"></i>Business Casino Activity</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Business</th>
                                    <th>Status</th>
                                    <th>Daily Limit</th>
                                    <th>Jackpot Multiplier</th>
                                    <th>Plays (30d)</th>
                                    <th>Total Bets</th>
                                    <th>Total Winnings</th>
                                    <th>House Edge</th>
                                    <th>Today's Plays</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($business_activity as $business): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($business['business_name']); ?></strong></td>
                                    <td>
                                        <?php if ($business['casino_enabled']): ?>
                                            <span class="badge bg-success">Enabled</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Disabled</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $business['max_daily_plays'] ?? 'N/A'; ?></td>
                                    <td><?php echo $business['jackpot_multiplier'] ?? 'N/A'; ?>x</td>
                                    <td><?php echo number_format($business['total_plays'] ?? 0); ?></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <img src="../img/qrCoin.png" style="width: 1rem; height: 1rem;" class="me-1">
                                            <?php echo number_format($business['total_bets'] ?? 0); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <img src="../img/qrCoin.png" style="width: 1rem; height: 1rem;" class="me-1">
                                            <?php echo number_format($business['total_winnings'] ?? 0); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php 
                                        $biz_house_edge = 0;
                                        if (($business['total_bets'] ?? 0) > 0) {
                                            $biz_house_edge = (($business['total_bets'] - $business['total_winnings']) / $business['total_bets']) * 100;
                                        }
                                        ?>
                                        <span class="badge bg-<?php echo $biz_house_edge > 10 ? 'danger' : ($biz_house_edge > 5 ? 'warning' : 'success'); ?>">
                                            <?php echo number_format($biz_house_edge, 1); ?>%
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary"><?php echo $business['plays_today'] ?? 0; ?></span>
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

    <!-- Prize Templates List -->
    <?php if (!empty($prize_templates)): ?>
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0"><i class="bi bi-list me-2"></i>Existing Prize Templates</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Prize Name</th>
                                    <th>Type</th>
                                    <th>Value</th>
                                    <th>Probability</th>
                                    <th>Multiplier Range</th>
                                    <th>Jackpot</th>
                                    <th>Created</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($prize_templates as $template): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($template['prize_name']); ?></strong></td>
                                    <td><span class="badge bg-info"><?php echo ucfirst($template['prize_type']); ?></span></td>
                                    <td><?php echo number_format($template['prize_value']); ?></td>
                                    <td><?php echo $template['win_probability']; ?>%</td>
                                    <td><?php echo $template['multiplier_min']; ?>x - <?php echo $template['multiplier_max']; ?>x</td>
                                    <td>
                                        <?php if ($template['is_jackpot']): ?>
                                            <span class="badge bg-danger">Jackpot</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Regular</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($template['created_at'])); ?></td>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Add some animations and interactivity
    const cards = document.querySelectorAll('.card');
    cards.forEach((card, index) => {
        card.style.animationDelay = (index * 0.1) + 's';
        card.classList.add('animate__animated', 'animate__fadeInUp');
    });
});
</script>

<?php require_once __DIR__ . '/../core/includes/footer.php'; ?> 