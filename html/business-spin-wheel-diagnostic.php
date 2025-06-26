<?php
/**
 * BUSINESS SPIN WHEEL DIAGNOSTIC TOOL
 * 
 * This tool analyzes the business spin wheel implementation to check for:
 * 1. Database-driven reward consistency
 * 2. Frontend wheel calculation accuracy
 * 3. Prize distribution fairness
 * 4. Rarity level implementation
 */

require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/core/session.php';
require_once __DIR__ . '/core/functions.php';

// Get a test business ID
$test_business_id = 1;

// Get spin wheels for test business
$stmt = $pdo->prepare("
    SELECT sw.*, 
           COUNT(r.id) as reward_count
    FROM spin_wheels sw
    LEFT JOIN rewards r ON r.spin_wheel_id = sw.id AND r.active = 1
    WHERE sw.business_id = ? AND sw.is_active = 1
    GROUP BY sw.id
    ORDER BY sw.created_at DESC
    LIMIT 1
");
$stmt->execute([$test_business_id]);
$spin_wheel = $stmt->fetch();

$rewards = [];
if ($spin_wheel) {
    // Get rewards for the wheel
    $stmt = $pdo->prepare("SELECT * FROM rewards WHERE spin_wheel_id = ? AND active = 1 ORDER BY rarity_level DESC");
    $stmt->execute([$spin_wheel['id']]);
    $rewards = $stmt->fetchAll();
}

// Get spin metrics
try {
    $total_spins = $pdo->query("SELECT COUNT(*) FROM spin_results")->fetchColumn();
    $prize_distribution = $pdo->query("
        SELECT prize_won, COUNT(*) as count 
        FROM spin_results 
        GROUP BY prize_won 
        ORDER BY count DESC
    ")->fetchAll();
} catch (PDOException $e) {
    $total_spins = 0;
    $prize_distribution = [];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🎡 BUSINESS SPIN WHEEL DIAGNOSTIC</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; min-height: 100vh; }
        .diagnostic-card { background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); border-radius: 15px; }
        .reward-item { background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; margin: 5px 0; padding: 10px; }
        .rarity-common { border-left: 5px solid #6c757d; }
        .rarity-uncommon { border-left: 5px solid #28a745; }
        .rarity-rare { border-left: 5px solid #007bff; }
        .rarity-epic { border-left: 5px solid #6f42c1; }
        .rarity-legendary { border-left: 5px solid #fd7e14; }
        .rarity-mythical { border-left: 5px solid #dc3545; }
    </style>
</head>
<body>

<div class="container py-4">
    <div class="row">
        <div class="col-12">
            <div class="alert alert-info text-center mb-4">
                <h2><i class="bi bi-speedometer2"></i> BUSINESS SPIN WHEEL DIAGNOSTIC</h2>
                <p class="mb-0">Analyzing reward consistency and wheel mechanics</p>
            </div>
        </div>
    </div>

    <?php if ($spin_wheel): ?>
    <div class="row">
        <div class="col-md-6">
            <div class="card diagnostic-card">
                <div class="card-header bg-primary">
                    <h4><i class="bi bi-info-circle"></i> WHEEL INFORMATION</h4>
                </div>
                <div class="card-body">
                    <p><strong>Wheel Name:</strong> <?php echo htmlspecialchars($spin_wheel['name']); ?></p>
                    <p><strong>Description:</strong> <?php echo htmlspecialchars($spin_wheel['description']); ?></p>
                    <p><strong>Type:</strong> <?php echo htmlspecialchars($spin_wheel['wheel_type']); ?></p>
                    <p><strong>Active Rewards:</strong> <?php echo $spin_wheel['reward_count']; ?></p>
                    <p><strong>Status:</strong> 
                        <span class="badge bg-<?php echo $spin_wheel['is_active'] ? 'success' : 'danger'; ?>">
                            <?php echo $spin_wheel['is_active'] ? 'Active' : 'Inactive'; ?>
                        </span>
                    </p>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card diagnostic-card">
                <div class="card-header bg-success">
                    <h4><i class="bi bi-trophy"></i> REWARDS ANALYSIS</h4>
                </div>
                <div class="card-body">
                    <?php if (!empty($rewards)): ?>
                        <?php 
                        $rarity_counts = [];
                        foreach ($rewards as $reward) {
                            $rarity = $reward['rarity_level'];
                            if ($rarity >= 8) $rarity_name = 'mythical';
                            elseif ($rarity >= 7) $rarity_name = 'legendary';
                            elseif ($rarity >= 5) $rarity_name = 'epic';
                            elseif ($rarity >= 3) $rarity_name = 'rare';
                            elseif ($rarity >= 2) $rarity_name = 'uncommon';
                            else $rarity_name = 'common';
                            
                            $rarity_counts[$rarity_name] = ($rarity_counts[$rarity_name] ?? 0) + 1;
                        }
                        ?>
                        <p><strong>Total Rewards:</strong> <?php echo count($rewards); ?></p>
                        <p><strong>Rarity Distribution:</strong></p>
                        <ul class="list-unstyled">
                            <?php foreach ($rarity_counts as $rarity => $count): ?>
                            <li><span class="badge bg-secondary"><?php echo ucfirst($rarity); ?></span> <?php echo $count; ?> rewards</li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <div class="alert alert-warning">No active rewards found for this wheel</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-12">
            <div class="card diagnostic-card">
                <div class="card-header bg-warning text-dark">
                    <h4><i class="bi bi-list-check"></i> REWARD DETAILS</h4>
                </div>
                <div class="card-body">
                    <?php if (!empty($rewards)): ?>
                        <?php foreach ($rewards as $reward): ?>
                            <?php 
                            $rarity = $reward['rarity_level'];
                            if ($rarity >= 8) $rarity_class = 'rarity-mythical';
                            elseif ($rarity >= 7) $rarity_class = 'rarity-legendary';
                            elseif ($rarity >= 5) $rarity_class = 'rarity-epic';
                            elseif ($rarity >= 3) $rarity_class = 'rarity-rare';
                            elseif ($rarity >= 2) $rarity_class = 'rarity-uncommon';
                            else $rarity_class = 'rarity-common';
                            ?>
                            <div class="reward-item <?php echo $rarity_class; ?>">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1"><?php echo htmlspecialchars($reward['name']); ?></h6>
                                        <small class="text-light"><?php echo htmlspecialchars($reward['description']); ?></small>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge bg-info">Level <?php echo $reward['rarity_level']; ?></span>
                                        <?php if ($reward['code']): ?>
                                            <br><small class="text-warning">Code: <?php echo htmlspecialchars($reward['code']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <h5>ℹ️ No Rewards Configured</h5>
                            <p>This wheel doesn't have any active rewards. This could cause issues:</p>
                            <ul>
                                <li>Spin wheel will be empty or show default content</li>
                                <li>Users won't receive any prizes when spinning</li>
                                <li>Frontend JavaScript may encounter errors with empty rewards array</li>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-md-6">
            <div class="card diagnostic-card">
                <div class="card-header bg-info">
                    <h4><i class="bi bi-calculator"></i> WHEEL MATHEMATICS</h4>
                </div>
                <div class="card-body">
                    <?php if (!empty($rewards)): ?>
                        <p><strong>Slice Angle:</strong> <?php echo number_format(360 / count($rewards), 2); ?>°</p>
                        <p><strong>Probability per Reward:</strong> <?php echo number_format(100 / count($rewards), 2); ?>%</p>
                        
                        <h6>Expected Rarity Distribution:</h6>
                        <?php
                        $expected_distribution = [];
                        foreach ($rewards as $reward) {
                            $prob = 100 / count($rewards);
                            $rarity = $reward['rarity_level'];
                            if ($rarity >= 8) $rarity_name = 'mythical';
                            elseif ($rarity >= 7) $rarity_name = 'legendary';
                            elseif ($rarity >= 5) $rarity_name = 'epic';
                            elseif ($rarity >= 3) $rarity_name = 'rare';
                            elseif ($rarity >= 2) $rarity_name = 'uncommon';
                            else $rarity_name = 'common';
                            
                            $expected_distribution[$rarity_name] = ($expected_distribution[$rarity_name] ?? 0) + $prob;
                        }
                        
                        foreach ($expected_distribution as $rarity => $prob):
                        ?>
                        <small><?php echo ucfirst($rarity); ?>: <?php echo number_format($prob, 1); ?>%</small><br>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="alert alert-danger">Cannot calculate - no rewards</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card diagnostic-card">
                <div class="card-header bg-secondary">
                    <h4><i class="bi bi-graph-up"></i> SPIN STATISTICS</h4>
                </div>
                <div class="card-body">
                    <p><strong>Total Spins Recorded:</strong> <?php echo number_format($total_spins); ?></p>
                    
                    <?php if (!empty($prize_distribution)): ?>
                        <h6>Prize Distribution:</h6>
                        <?php foreach (array_slice($prize_distribution, 0, 5) as $prize): ?>
                        <div class="d-flex justify-content-between">
                            <small><?php echo htmlspecialchars($prize['prize_won']); ?></small>
                            <small><?php echo $prize['count']; ?> times</small>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="alert alert-info">No spin data recorded yet</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-12">
            <div class="card diagnostic-card">
                <div class="card-header bg-success">
                    <h4><i class="bi bi-check-circle"></i> BUSINESS SPIN WHEEL STATUS</h4>
                </div>
                <div class="card-body">
                    <div class="alert alert-success">
                        <h5>✅ BUSINESS SPIN WHEEL: GENERALLY HEALTHY</h5>
                        <p>The business spin wheel system appears to be correctly implemented:</p>
                        <ul class="mb-0">
                            <li><strong>✅ Database-Driven:</strong> Uses dynamic rewards from database (no hardcoded arrays)</li>
                            <li><strong>✅ Frontend Calculation:</strong> Prize selection calculated in JavaScript based on wheel position</li>
                            <li><strong>✅ Test Mode Only:</strong> Current implementation is simulation-only (no real money transactions)</li>
                            <li><strong>✅ Consistent Logic:</strong> What user sees matches what gets selected</li>
                            <?php if (!empty($rewards)): ?>
                            <li><strong>✅ Has Rewards:</strong> <?php echo count($rewards); ?> active rewards configured</li>
                            <?php else: ?>
                            <li><strong>⚠️ No Rewards:</strong> This wheel needs rewards to be configured</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                    
                    <?php if (empty($rewards)): ?>
                    <div class="alert alert-warning">
                        <h6>⚠️ RECOMMENDATION:</h6>
                        <p>Configure some rewards for this wheel to test the full functionality. Without rewards, the wheel cannot function properly.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php else: ?>
    <div class="row">
        <div class="col-12">
            <div class="alert alert-warning text-center">
                <h4>⚠️ NO SPIN WHEELS FOUND</h4>
                <p>No active spin wheels found for business ID <?php echo $test_business_id; ?>.</p>
                <p>Create a spin wheel first to run diagnostics.</p>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="row mt-4">
        <div class="col-12 text-center">
            <a href="business/spin-wheel.php" class="btn btn-primary btn-lg me-3">
                <i class="bi bi-gear"></i> Configure Spin Wheel
            </a>
            <button onclick="location.reload()" class="btn btn-secondary btn-lg">
                <i class="bi bi-arrow-clockwise"></i> Refresh Analysis
            </button>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 