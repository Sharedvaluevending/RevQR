<?php
/**
 * Test Spin Reward Logic
 * Simulates exact spin.php logic to trace reward calculations
 */

require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/config_manager.php';

// Require user role
require_role('user');

$user_id = $_SESSION['user_id'];

// Simulate the exact reward array from spin.php
$specific_rewards = [
    ['name' => 'Lord Pixel!', 'rarity_level' => 11, 'weight' => 1, 'special' => 'lord_pixel', 'points' => 0],
    ['name' => 'Try Again', 'rarity_level' => 2, 'weight' => 20, 'special' => 'spin_again', 'points' => 0],
    ['name' => 'Extra Vote', 'rarity_level' => 2, 'weight' => 15, 'points' => 0],
    ['name' => '50 QR Coins', 'rarity_level' => 3, 'weight' => 20, 'points' => 50],
    ['name' => '-20 QR Coins', 'rarity_level' => 5, 'weight' => 15, 'points' => -20],
    ['name' => '200 QR Coins', 'rarity_level' => 7, 'weight' => 12, 'points' => 200],
    ['name' => 'Lose All Votes', 'rarity_level' => 8, 'weight' => 10, 'points' => 0],
    ['name' => '500 QR Coins!', 'rarity_level' => 10, 'weight' => 7, 'points' => 500]
];

// Get economic settings (like in spin.php)
$economic_settings = ConfigManager::getEconomicSettings();
$base_spin_amount = $economic_settings['qr_coin_spin_base'] ?? 15;
$bonus_amount = $economic_settings['qr_coin_spin_bonus'] ?? 50;

// Simulation
$simulation_results = [];
$test_index = isset($_GET['test_index']) ? (int)$_GET['test_index'] : null;

if (isset($_GET['simulate_prize']) && $test_index !== null && isset($specific_rewards[$test_index])) {
    $selected_reward = $specific_rewards[$test_index];
    
    // Simulate reward calculation
    $prize_points = $selected_reward['points'] ?? 0;
    $total_base_reward = $base_spin_amount + $bonus_amount; // Assuming first spin of day
    
    $simulation_results = [
        'selected_prize' => $selected_reward['name'],
        'prize_points' => $prize_points,
        'base_spin_amount' => $base_spin_amount,
        'daily_bonus' => $bonus_amount,
        'total_base_reward' => $total_base_reward,
        'total_coins_received' => $total_base_reward + max(0, $prize_points), // Only add positive prize points
        'special_effect' => isset($selected_reward['special']) ? $selected_reward['special'] : 'none'
    ];
}

require_once __DIR__ . '/../core/includes/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h4 class="mb-0"><i class="bi bi-calculator me-2"></i>Test Spin Reward Logic</h4>
                    <p class="mb-0">Simulate what happens when you land on each prize</p>
                </div>
                <div class="card-body">
                    
                    <!-- Current Economic Settings -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h5 class="text-primary">Current Economic Settings</h5>
                            <ul class="list-group">
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Base Spin Reward
                                    <span class="badge bg-primary rounded-pill"><?= $base_spin_amount ?> coins</span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Daily Bonus (First Spin)
                                    <span class="badge bg-success rounded-pill"><?= $bonus_amount ?> coins</span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <strong>Total Base Reward (First Spin)</strong>
                                    <span class="badge bg-warning rounded-pill"><?= $base_spin_amount + $bonus_amount ?> coins</span>
                                </li>
                            </ul>
                        </div>
                        
                        <div class="col-md-6">
                            <h5 class="text-success">Key Points</h5>
                            <div class="alert alert-info">
                                <ul class="mb-0">
                                    <li><strong>Every spin</strong> gets base reward (<?= $base_spin_amount ?> coins)</li>
                                    <li><strong>First spin daily</strong> gets bonus (+<?= $bonus_amount ?> coins)</li>
                                    <li><strong>Prize coins</strong> are ADDED to base reward</li>
                                    <li><strong>"Extra Vote"</strong> gives 0 prize coins</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Prize Testing -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <h5 class="text-warning">Test Each Prize</h5>
                            <p class="text-muted">Click any prize to see the exact reward calculation:</p>
                            
                            <div class="row">
                                <?php foreach ($specific_rewards as $index => $reward): ?>
                                <div class="col-md-6 col-lg-4 mb-3">
                                    <div class="card h-100 <?= ($reward['points'] > 0) ? 'border-success' : (($reward['points'] < 0) ? 'border-danger' : 'border-secondary') ?>">
                                        <div class="card-body text-center">
                                            <h6 class="card-title"><?= htmlspecialchars($reward['name']) ?></h6>
                                            <p class="card-text">
                                                <span class="badge bg-secondary">Index: <?= $index ?></span><br>
                                                <span class="badge bg-<?= ($reward['points'] > 0) ? 'success' : (($reward['points'] < 0) ? 'danger' : 'warning') ?>">
                                                    <?= $reward['points'] ?> prize coins
                                                </span>
                                            </p>
                                            <a href="?simulate_prize=1&test_index=<?= $index ?>" class="btn btn-primary btn-sm">
                                                Test This Prize
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Simulation Results -->
                    <?php if (!empty($simulation_results)): ?>
                    <div class="row">
                        <div class="col-12">
                            <h5 class="text-success">Simulation Results</h5>
                            <div class="card border-success">
                                <div class="card-header bg-success text-white">
                                    <h6 class="mb-0">You landed on: <strong><?= htmlspecialchars($simulation_results['selected_prize']) ?></strong></h6>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <h6 class="text-primary">Reward Breakdown:</h6>
                                            <table class="table table-sm">
                                                <tr>
                                                    <td>Base Spin Reward:</td>
                                                    <td><span class="badge bg-secondary">+<?= $simulation_results['base_spin_amount'] ?> coins</span></td>
                                                </tr>
                                                <tr>
                                                    <td>Daily Bonus (First Spin):</td>
                                                    <td><span class="badge bg-success">+<?= $simulation_results['daily_bonus'] ?> coins</span></td>
                                                </tr>
                                                <tr>
                                                    <td>Prize Coins:</td>
                                                    <td>
                                                        <span class="badge bg-<?= ($simulation_results['prize_points'] > 0) ? 'success' : (($simulation_results['prize_points'] < 0) ? 'danger' : 'warning') ?>">
                                                            <?= ($simulation_results['prize_points'] >= 0 ? '+' : '') . $simulation_results['prize_points'] ?> coins
                                                        </span>
                                                    </td>
                                                </tr>
                                                <tr class="table-warning">
                                                    <td><strong>Total Coins Received:</strong></td>
                                                    <td><span class="badge bg-warning fs-6">+<?= $simulation_results['total_coins_received'] ?> coins</span></td>
                                                </tr>
                                            </table>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <h6 class="text-info">Special Effects:</h6>
                                            <?php if ($simulation_results['special_effect'] !== 'none'): ?>
                                                <div class="alert alert-info">
                                                    <strong>Special Effect:</strong> <?= htmlspecialchars($simulation_results['special_effect']) ?>
                                                    
                                                    <?php if ($simulation_results['special_effect'] === 'lord_pixel'): ?>
                                                        <br><small>Unlocks Lord Pixel avatar + spin again</small>
                                                    <?php elseif ($simulation_results['special_effect'] === 'spin_again'): ?>
                                                        <br><small>Doesn't count against daily limit + spin again</small>
                                                    <?php endif; ?>
                                                </div>
                                            <?php else: ?>
                                                <div class="alert alert-secondary">
                                                    No special effects for this prize.
                                                    
                                                    <?php if ($simulation_results['selected_prize'] === 'Extra Vote'): ?>
                                                        <br><strong>Note:</strong> Extra Vote grants +1 weekly vote limit (not coins)
                                                    <?php elseif ($simulation_results['selected_prize'] === 'Lose All Votes'): ?>
                                                        <br><strong>Note:</strong> Blocks voting for the rest of the week
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <!-- Analysis for "Extra Vote" specifically -->
                                    <?php if ($simulation_results['selected_prize'] === 'Extra Vote'): ?>
                                    <div class="alert alert-warning mt-3">
                                        <h6 class="alert-heading">🗳️ "Extra Vote" Analysis</h6>
                                        <p class="mb-2">If you're getting <strong>200 coins</strong> when landing on "Extra Vote", here's why:</p>
                                        <ul class="mb-0">
                                            <li><strong>Extra Vote prize:</strong> 0 coins (gives voting privilege instead)</li>
                                            <li><strong>Base reward:</strong> <?= $simulation_results['base_spin_amount'] ?> coins (always given)</li>
                                            <li><strong>Daily bonus:</strong> <?= $simulation_results['daily_bonus'] ?> coins (first spin only)</li>
                                            <li><strong>Total from spin:</strong> <?= $simulation_results['total_coins_received'] ?> coins</li>
                                            <li><strong>If you see 200 coins:</strong> This might be from landing on the "200 QR Coins" prize instead</li>
                                        </ul>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Back Button -->
                    <div class="row mt-4">
                        <div class="col-12 text-center">
                            <a href="spin.php" class="btn btn-success">
                                <i class="bi bi-arrow-left me-1"></i>Back to Spin Wheel
                            </a>
                            <a href="spin-debug.php" class="btn btn-info ms-2">
                                <i class="bi bi-bug me-1"></i>Full Debug Tool
                            </a>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../core/includes/footer.php'; ?> 