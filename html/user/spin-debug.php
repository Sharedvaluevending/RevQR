<?php
/**
 * Spin Wheel Debug Tool
 * Tests if frontend wheel display matches backend prize selection
 */

require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';

// Require user role
require_role('user');

$user_id = $_SESSION['user_id'];

// Backend prize array (exactly as in spin.php)
$backend_rewards = [
    ['name' => 'Lord Pixel!', 'rarity_level' => 11, 'weight' => 1, 'special' => 'lord_pixel', 'points' => 0],
    ['name' => 'Try Again', 'rarity_level' => 2, 'weight' => 20, 'special' => 'spin_again', 'points' => 0],
    ['name' => 'Extra Vote', 'rarity_level' => 2, 'weight' => 15, 'points' => 0],
    ['name' => '50 QR Coins', 'rarity_level' => 3, 'weight' => 20, 'points' => 50],
    ['name' => '-20 QR Coins', 'rarity_level' => 5, 'weight' => 15, 'points' => -20],
    ['name' => '200 QR Coins', 'rarity_level' => 7, 'weight' => 12, 'points' => 200],
    ['name' => 'Lose All Votes', 'rarity_level' => 8, 'weight' => 10, 'points' => 0],
    ['name' => '500 QR Coins!', 'rarity_level' => 10, 'weight' => 7, 'points' => 500]
];

// Frontend prize array (exactly as in spin.php JavaScript)
$frontend_rewards = [
    ['name' => 'Lord Pixel!', 'rarity_level' => 11, 'colors' => ['#8A2BE2', '#FF1493', '#FFD700'], 'special' => 'lord_pixel'],
    ['name' => 'Try Again', 'rarity_level' => 2, 'colors' => ['#28a745', '#20c997'], 'special' => 'spin_again'],
    ['name' => 'Extra Vote', 'rarity_level' => 2, 'colors' => ['#17a2b8', '#20c997']],
    ['name' => '50 QR Coins', 'rarity_level' => 3, 'colors' => ['#007bff', '#6f42c1']],
    ['name' => '-20 QR Coins', 'rarity_level' => 5, 'colors' => ['#ffc107', '#fd7e14']],
    ['name' => '200 QR Coins', 'rarity_level' => 7, 'colors' => ['#dc3545', '#e83e8c']],
    ['name' => 'Lose All Votes', 'rarity_level' => 8, 'colors' => ['#343a40', '#6c757d']],
    ['name' => '500 QR Coins!', 'rarity_level' => 10, 'colors' => ['#fd7e14', '#ffd700', '#ff6b6b']]
];

// Test spin simulation
$test_results = [];
if ($_GET['test'] === 'simulate') {
    for ($i = 0; $i < 100; $i++) {
        // Simulate the exact same logic as spin.php
        $total_weight = array_sum(array_column($backend_rewards, 'weight'));
        $random = mt_rand(1, $total_weight);
        $current_weight = 0;
        
        foreach ($backend_rewards as $index => $reward) {
            $current_weight += $reward['weight'];
            if ($random <= $current_weight) {
                $test_results[] = [
                    'index' => $index,
                    'name' => $reward['name'],
                    'points' => $reward['points'],
                    'weight' => $reward['weight']
                ];
                break;
            }
        }
    }
}

require_once __DIR__ . '/../core/includes/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-warning text-dark">
                    <h4 class="mb-0"><i class="bi bi-bug-fill me-2"></i>Spin Wheel Debug Tool</h4>
                    <p class="mb-0">Comparing frontend display vs backend prize selection</p>
                </div>
                <div class="card-body">
                    
                    <!-- Array Comparison -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h5 class="text-primary">Backend Prize Array (PHP)</h5>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered">
                                    <thead class="table-primary">
                                        <tr>
                                            <th>Index</th>
                                            <th>Prize Name</th>
                                            <th>Points</th>
                                            <th>Weight</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($backend_rewards as $index => $reward): ?>
                                        <tr>
                                            <td><strong><?= $index ?></strong></td>
                                            <td><?= htmlspecialchars($reward['name']) ?></td>
                                            <td><?= $reward['points'] ?></td>
                                            <td><?= $reward['weight'] ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <h5 class="text-success">Frontend Display Array (JavaScript)</h5>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered">
                                    <thead class="table-success">
                                        <tr>
                                            <th>Index</th>
                                            <th>Prize Name</th>
                                            <th>Rarity</th>
                                            <th>Visual Position</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($frontend_rewards as $index => $reward): ?>
                                        <tr class="<?= ($backend_rewards[$index]['name'] !== $reward['name']) ? 'table-danger' : '' ?>">
                                            <td><strong><?= $index ?></strong></td>
                                            <td><?= htmlspecialchars($reward['name']) ?></td>
                                            <td><?= $reward['rarity_level'] ?></td>
                                            <td>
                                                <?php 
                                                $slice_angle = 360 / 8;
                                                $start_angle = $index * $slice_angle;
                                                $end_angle = $start_angle + $slice_angle;
                                                echo "{$start_angle}° - {$end_angle}°";
                                                ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Mismatch Detection -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <h5 class="text-danger">Mismatch Detection</h5>
                            <?php 
                            $mismatches = [];
                            for ($i = 0; $i < count($backend_rewards); $i++) {
                                if ($backend_rewards[$i]['name'] !== $frontend_rewards[$i]['name']) {
                                    $mismatches[] = [
                                        'index' => $i,
                                        'backend' => $backend_rewards[$i]['name'],
                                        'frontend' => $frontend_rewards[$i]['name']
                                    ];
                                }
                            }
                            ?>
                            
                            <?php if (empty($mismatches)): ?>
                                <div class="alert alert-success">
                                    <i class="bi bi-check-circle me-2"></i>
                                    <strong>No mismatches found!</strong> Frontend and backend arrays are perfectly aligned.
                                </div>
                            <?php else: ?>
                                <div class="alert alert-danger">
                                    <i class="bi bi-exclamation-triangle me-2"></i>
                                    <strong>MISMATCHES DETECTED!</strong> The following indices don't match:
                                    <ul class="mt-2 mb-0">
                                        <?php foreach ($mismatches as $mismatch): ?>
                                        <li>
                                            Index <?= $mismatch['index'] ?>: 
                                            Backend = "<?= htmlspecialchars($mismatch['backend']) ?>" | 
                                            Frontend = "<?= htmlspecialchars($mismatch['frontend']) ?>"
                                        </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Test Controls -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <h5 class="text-info">Testing Tools</h5>
                            <div class="btn-group mb-3">
                                <a href="?test=simulate" class="btn btn-primary">
                                    <i class="bi bi-play-circle me-1"></i>Run 100 Spin Simulation
                                </a>
                                <a href="spin.php" class="btn btn-success">
                                    <i class="bi bi-arrow-left me-1"></i>Back to Spin Wheel
                                </a>
                                <button onclick="testSpecificIndex()" class="btn btn-warning">
                                    <i class="bi bi-target me-1"></i>Test Specific Index
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Simulation Results -->
                    <?php if (!empty($test_results)): ?>
                    <div class="row">
                        <div class="col-12">
                            <h5 class="text-secondary">Simulation Results (100 spins)</h5>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead class="table-secondary">
                                        <tr>
                                            <th>Prize Name</th>
                                            <th>Count</th>
                                            <th>Percentage</th>
                                            <th>Expected %</th>
                                            <th>Difference</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $prize_counts = array_count_values(array_column($test_results, 'name'));
                                        $total_weight = array_sum(array_column($backend_rewards, 'weight'));
                                        
                                        foreach ($backend_rewards as $reward):
                                            $count = $prize_counts[$reward['name']] ?? 0;
                                            $actual_pct = ($count / 100) * 100;
                                            $expected_pct = ($reward['weight'] / $total_weight) * 100;
                                            $difference = $actual_pct - $expected_pct;
                                        ?>
                                        <tr>
                                            <td><?= htmlspecialchars($reward['name']) ?></td>
                                            <td><?= $count ?></td>
                                            <td><?= number_format($actual_pct, 1) ?>%</td>
                                            <td><?= number_format($expected_pct, 1) ?>%</td>
                                            <td class="<?= abs($difference) > 5 ? 'text-danger' : 'text-success' ?>">
                                                <?= ($difference >= 0 ? '+' : '') . number_format($difference, 1) ?>%
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>
</div>

<script>
function testSpecificIndex() {
    const index = prompt('Enter prize index to test (0-7):');
    if (index !== null && index >= 0 && index <= 7) {
        alert(`Testing index ${index}:\nBackend: "${<?= json_encode(array_column($backend_rewards, 'name')) ?>[index]}"\nFrontend: "${<?= json_encode(array_column($frontend_rewards, 'name')) ?>[index]}"`);
    }
}

// Log arrays for browser console debugging
console.log('Backend Rewards:', <?= json_encode($backend_rewards) ?>);
console.log('Frontend Rewards:', <?= json_encode($frontend_rewards) ?>);
</script>

<?php require_once __DIR__ . '/../core/includes/footer.php'; ?> 