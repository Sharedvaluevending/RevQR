<?php
require_once __DIR__ . '/../../core/config.php';
require_once __DIR__ . '/../../core/session.php';
require_once __DIR__ . '/../../core/auth.php';

// Require business role
require_role('business');

// Get business details
$stmt = $pdo->prepare("SELECT b.id FROM businesses b JOIN users u ON b.id = u.business_id WHERE u.id = ?");
$stmt->execute([$_SESSION['user_id']]);
$business = $stmt->fetch();
$business_id = $business ? $business['id'] : 0;

// Get date range from query params (default to last 30 days)
$days = isset($_GET['days']) ? max(1, min(365, intval($_GET['days']))) : 30;
$start_date = date('Y-m-d', strtotime("-{$days} days"));
$end_date = date('Y-m-d');

// Get spin statistics - UPDATED TO HANDLE BOTH SPIN SYSTEMS
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_spins,
        SUM(CASE WHEN sr.business_id = ? AND sr.machine_id IS NOT NULL THEN 1 ELSE 0 END) as business_spins,
        SUM(CASE WHEN sr.business_id IS NULL AND sr.machine_id IS NULL THEN 1 ELSE 0 END) as user_nav_spins,
        COUNT(DISTINCT sr.machine_id) as machines_with_spins,
        COUNT(DISTINCT sr.user_ip) as unique_spinners,
        SUM(CASE WHEN sr.prize_won != 'No Prize' AND sr.prize_won != '' THEN 1 ELSE 0 END) as successful_spins,
        SUM(CASE WHEN sr.is_big_win = 1 THEN 1 ELSE 0 END) as big_wins,
        COUNT(DISTINCT DATE(sr.spin_time)) as active_days
    FROM spin_results sr
    WHERE (
        (sr.business_id = ? AND sr.machine_id IS NOT NULL) OR 
        (sr.business_id IS NULL AND sr.machine_id IS NULL)
    )
    AND DATE(sr.spin_time) BETWEEN ? AND ?
");
$stmt->execute([$business_id, $business_id, $start_date, $end_date]);
$stats = $stmt->fetch();

// Get daily spin trends - UPDATED FOR BOTH SYSTEMS
$stmt = $pdo->prepare("
    SELECT 
        DATE(sr.spin_time) as spin_date,
        COUNT(*) as total_spins,
        SUM(CASE WHEN sr.business_id = ? AND sr.machine_id IS NOT NULL THEN 1 ELSE 0 END) as business_spins,
        SUM(CASE WHEN sr.business_id IS NULL AND sr.machine_id IS NULL THEN 1 ELSE 0 END) as user_nav_spins,
        SUM(CASE WHEN sr.prize_won != 'No Prize' AND sr.prize_won != '' THEN 1 ELSE 0 END) as successful_spins,
        SUM(CASE WHEN sr.is_big_win = 1 THEN 1 ELSE 0 END) as big_wins
    FROM spin_results sr
    WHERE (
        (sr.business_id = ? AND sr.machine_id IS NOT NULL) OR 
        (sr.business_id IS NULL AND sr.machine_id IS NULL)
    )
    AND DATE(sr.spin_time) BETWEEN ? AND ?
    GROUP BY DATE(sr.spin_time)
    ORDER BY spin_date ASC
");
$stmt->execute([$business_id, $business_id, $start_date, $end_date]);
$dailyTrends = $stmt->fetchAll();

// Get reward type distribution
$stmt = $pdo->prepare("
    SELECT 
        CASE 
            WHEN sr.prize_won = 'No Prize' OR sr.prize_won = '' THEN 'No Prize'
            WHEN sr.is_big_win = 1 THEN 'Big Win'
            ELSE 'Regular Prize'
        END as reward_type,
        COUNT(*) as count,
        ROUND((COUNT(*) * 100.0 / (SELECT COUNT(*) FROM spin_results sr2 JOIN machines m2 ON sr2.machine_id = m2.id WHERE m2.business_id = ? AND DATE(sr2.spin_time) BETWEEN ? AND ?)), 1) as percentage
    FROM spin_results sr
    JOIN machines m ON sr.machine_id = m.id
    WHERE m.business_id = ? AND DATE(sr.spin_time) BETWEEN ? AND ?
    GROUP BY reward_type
    ORDER BY count DESC
");
$stmt->execute([$business_id, $start_date, $end_date, $business_id, $start_date, $end_date]);
$rewardDistribution = $stmt->fetchAll();

// Get spins by machine
$stmt = $pdo->prepare("
    SELECT 
        m.name as machine_name,
        COUNT(*) as total_spins,
        SUM(CASE WHEN sr.prize_won != 'No Prize' AND sr.prize_won != '' THEN 1 ELSE 0 END) as successful_spins,
        COUNT(DISTINCT sr.user_ip) as unique_spinners,
        ROUND((SUM(CASE WHEN sr.prize_won != 'No Prize' AND sr.prize_won != '' THEN 1 ELSE 0 END) * 100.0 / COUNT(*)), 1) as success_rate
    FROM spin_results sr
    JOIN machines m ON sr.machine_id = m.id
    WHERE m.business_id = ? AND DATE(sr.spin_time) BETWEEN ? AND ?
    GROUP BY m.id, m.name
    ORDER BY total_spins DESC
");
$stmt->execute([$business_id, $start_date, $end_date]);
$machineStats = $stmt->fetchAll();

// Get hourly spin patterns
$stmt = $pdo->prepare("
    SELECT 
        HOUR(sr.spin_time) as hour,
        COUNT(*) as spins
    FROM spin_results sr
    JOIN machines m ON sr.machine_id = m.id
    WHERE m.business_id = ? AND DATE(sr.spin_time) BETWEEN ? AND ?
    GROUP BY HOUR(sr.spin_time)
    ORDER BY hour
");
$stmt->execute([$business_id, $start_date, $end_date]);
$hourlyPattern = $stmt->fetchAll();

// Get top spinners
$stmt = $pdo->prepare("
    SELECT 
        sr.user_ip,
        COUNT(*) as spin_count,
        SUM(CASE WHEN sr.prize_won != 'No Prize' AND sr.prize_won != '' THEN 1 ELSE 0 END) as rewards_won,
        MIN(sr.spin_time) as first_spin,
        MAX(sr.spin_time) as last_spin
    FROM spin_results sr
    JOIN machines m ON sr.machine_id = m.id
    WHERE m.business_id = ? AND DATE(sr.spin_time) BETWEEN ? AND ?
    GROUP BY sr.user_ip
    ORDER BY spin_count DESC
    LIMIT 20
");
$stmt->execute([$business_id, $start_date, $end_date]);
$topSpinners = $stmt->fetchAll();

// Get recent rewards
$stmt = $pdo->prepare("
    SELECT 
        sr.prize_won,
        sr.is_big_win,
        m.name as machine_name,
        sr.spin_time,
        sr.user_ip
    FROM spin_results sr
    JOIN machines m ON sr.machine_id = m.id
    WHERE m.business_id = ? AND sr.prize_won != 'No Prize' AND sr.prize_won != '' AND DATE(sr.spin_time) BETWEEN ? AND ?
    ORDER BY sr.spin_time DESC
    LIMIT 50
");
$stmt->execute([$business_id, $start_date, $end_date]);
$recentRewards = $stmt->fetchAll();

require_once __DIR__ . '/../../core/includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row mb-4 align-items-center">
        <div class="col">
            <h1 class="h3 mb-0">
                <i class="bi bi-arrow-repeat text-warning me-2"></i>
                Spin & Rewards Analytics
            </h1>
            <p class="text-muted">Detailed insights into spin wheel performance and reward distribution</p>
        </div>
        <div class="col-auto">
            <div class="btn-group me-2">
                <a href="?days=7" class="btn btn-outline-secondary <?php echo $days == 7 ? 'active' : ''; ?>">7 Days</a>
                <a href="?days=30" class="btn btn-outline-secondary <?php echo $days == 30 ? 'active' : ''; ?>">30 Days</a>
                <a href="?days=90" class="btn btn-outline-secondary <?php echo $days == 90 ? 'active' : ''; ?>">90 Days</a>
            </div>
            <a href="../dashboard_enhanced.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-2"></i>Back to Dashboard
            </a>
        </div>
    </div>

    <!-- Key Metrics - UPDATED FOR DUAL SYSTEM -->
    <div class="row g-4 mb-4">
        <div class="col-md-2">
            <div class="card text-center">
                <div class="card-body">
                    <h3 class="text-warning"><?php echo number_format($stats['total_spins'] ?? 0); ?></h3>
                    <small class="text-muted">Total Spins</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-center">
                <div class="card-body">
                    <h3 class="text-primary"><?php echo number_format($stats['business_spins'] ?? 0); ?></h3>
                    <small class="text-muted">QR Code Spins</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-center">
                <div class="card-body">
                    <h3 class="text-info"><?php echo number_format($stats['user_nav_spins'] ?? 0); ?></h3>
                    <small class="text-muted">Navigation Spins</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-center">
                <div class="card-body">
                    <h3 class="text-success"><?php echo number_format($stats['big_wins'] ?? 0); ?></h3>
                    <small class="text-muted">Big Wins</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-center">
                <div class="card-body">
                    <?php 
                    $success_rate = $stats['total_spins'] > 0 ? round(($stats['successful_spins'] / $stats['total_spins']) * 100, 1) : 0;
                    ?>
                    <h3 class="text-warning"><?php echo $success_rate; ?>%</h3>
                    <small class="text-muted">Success Rate</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-center">
                <div class="card-body">
                    <h3 class="text-secondary"><?php echo number_format($stats['unique_spinners'] ?? 0); ?></h3>
                    <small class="text-muted">Unique Spinners</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Spin System Breakdown -->
    <div class="row g-4 mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-pie-chart me-2"></i>Spin System Analysis
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="text-primary mb-3">
                                <i class="bi bi-qr-code me-2"></i>QR Code Business Spins
                            </h6>
                            <p class="text-muted">Spins from customers scanning QR codes at your machines</p>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Total QR Spins:</span>
                                <strong class="text-primary"><?php echo number_format($stats['business_spins'] ?? 0); ?></strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Percentage of Total:</span>
                                <strong class="text-primary">
                                    <?php 
                                    $qr_percentage = $stats['total_spins'] > 0 ? round(($stats['business_spins'] / $stats['total_spins']) * 100, 1) : 0;
                                    echo $qr_percentage; 
                                    ?>%
                                </strong>
                            </div>
                            <?php if ($stats['business_spins'] > 0): ?>
                                <div class="progress mb-3" style="height: 8px;">
                                    <div class="progress-bar bg-primary" style="width: <?php echo $qr_percentage; ?>%"></div>
                                </div>
                                <small class="text-success">
                                    <i class="bi bi-check-circle me-1"></i>
                                    QR code engagement is active
                                </small>
                            <?php else: ?>
                                <small class="text-warning">
                                    <i class="bi bi-exclamation-triangle me-1"></i>
                                    No QR code spins yet - promote your QR codes!
                                </small>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-md-6">
                            <h6 class="text-info mb-3">
                                <i class="bi bi-compass me-2"></i>User Navigation Spins
                            </h6>
                            <p class="text-muted">General spins from users browsing the platform</p>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Total Nav Spins:</span>
                                <strong class="text-info"><?php echo number_format($stats['user_nav_spins'] ?? 0); ?></strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Percentage of Total:</span>
                                <strong class="text-info">
                                    <?php 
                                    $nav_percentage = $stats['total_spins'] > 0 ? round(($stats['user_nav_spins'] / $stats['total_spins']) * 100, 1) : 0;
                                    echo $nav_percentage; 
                                    ?>%
                                </strong>
                            </div>
                            <?php if ($stats['user_nav_spins'] > 0): ?>
                                <div class="progress mb-3" style="height: 8px;">
                                    <div class="progress-bar bg-info" style="width: <?php echo $nav_percentage; ?>%"></div>
                                </div>
                                <small class="text-success">
                                    <i class="bi bi-check-circle me-1"></i>
                                    Platform engagement is active
                                </small>
                            <?php else: ?>
                                <small class="text-secondary">
                                    <i class="bi bi-info-circle me-1"></i>
                                    No navigation spins in this period
                                </small>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php if ($stats['total_spins'] > 0): ?>
                    <div class="mt-4 p-3 bg-light rounded">
                        <h6 class="text-dark mb-2">
                            <i class="bi bi-lightbulb me-2"></i>Insights & Recommendations
                        </h6>
                        <?php if ($stats['business_spins'] > 0 && $stats['user_nav_spins'] > 0): ?>
                            <div class="alert alert-success mb-2">
                                <i class="bi bi-check-circle me-2"></i>
                                <strong>Excellent!</strong> Both QR code and navigation spins are active. Your multi-channel engagement strategy is working well.
                            </div>
                        <?php elseif ($stats['business_spins'] > $stats['user_nav_spins'] * 2): ?>
                            <div class="alert alert-info mb-2">
                                <i class="bi bi-qr-code me-2"></i>
                                <strong>QR-Focused:</strong> Most spins come from QR codes. Consider expanding your digital presence to capture more navigation spins.
                            </div>
                        <?php elseif ($stats['user_nav_spins'] > $stats['business_spins'] * 2): ?>
                            <div class="alert alert-warning mb-2">
                                <i class="bi bi-compass me-2"></i>
                                <strong>Navigation-Heavy:</strong> Most spins are from general browsing. Promote your QR codes more to drive business-specific engagement.
                            </div>
                        <?php else: ?>
                            <div class="alert alert-primary mb-2">
                                <i class="bi bi-balance-scale me-2"></i>
                                <strong>Balanced:</strong> Good mix of QR code and navigation spins. Keep promoting both channels.
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Daily Spin Trends -->
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Daily Spin Trends</h5>
                </div>
                <div class="card-body">
                    <canvas id="dailyTrendsChart" height="100"></canvas>
                </div>
            </div>
        </div>

        <!-- Reward Distribution -->
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Reward Distribution</h5>
                </div>
                <div class="card-body">
                    <canvas id="rewardChart" height="200"></canvas>
                </div>
            </div>
        </div>

        <!-- Machine Performance -->
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Machine Spin Performance</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Machine</th>
                                    <th>Total Spins</th>
                                    <th>Rewards Won</th>
                                    <th>Success Rate</th>
                                    <th>Unique Spinners</th>
                                    <th>Performance</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($machineStats)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted">No spin data found</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($machineStats as $machine): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($machine['machine_name']); ?></td>
                                            <td><?php echo $machine['total_spins']; ?></td>
                                            <td><span class="badge bg-success"><?php echo $machine['successful_spins']; ?></span></td>
                                            <td><?php echo $machine['success_rate']; ?>%</td>
                                            <td><?php echo $machine['unique_spinners']; ?></td>
                                            <td>
                                                <?php 
                                                $performance = $machine['total_spins'] >= 10 ? 'High' : ($machine['total_spins'] >= 5 ? 'Medium' : 'Low');
                                                $color = $performance === 'High' ? 'success' : ($performance === 'Medium' ? 'warning' : 'secondary');
                                                ?>
                                                <span class="badge bg-<?php echo $color; ?>"><?php echo $performance; ?></span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Hourly Spin Pattern -->
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Hourly Spin Pattern</h5>
                </div>
                <div class="card-body">
                    <canvas id="hourlyPatternChart" height="200"></canvas>
                </div>
            </div>
        </div>

        <!-- Top Spinners -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Top Spinners (Last <?php echo $days; ?> Days)</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>User (IP)</th>
                                    <th>Spins</th>
                                    <th>Rewards</th>
                                    <th>Success Rate</th>
                                    <th>Activity Level</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($topSpinners)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted">No spinner data found</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($topSpinners as $spinner): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars(substr($spinner['user_ip'], 0, -2) . 'xx'); ?></td>
                                            <td><?php echo $spinner['spin_count']; ?></td>
                                            <td><?php echo $spinner['rewards_won']; ?></td>
                                            <td><?php echo $spinner['spin_count'] > 0 ? round(($spinner['rewards_won'] / $spinner['spin_count']) * 100, 1) : 0; ?>%</td>
                                            <td>
                                                <?php 
                                                $level = $spinner['spin_count'] >= 20 ? 'High' : ($spinner['spin_count'] >= 10 ? 'Medium' : 'Low');
                                                $color = $level === 'High' ? 'success' : ($level === 'Medium' ? 'warning' : 'secondary');
                                                ?>
                                                <span class="badge bg-<?php echo $color; ?>"><?php echo $level; ?></span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Rewards -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Recent Rewards</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Reward</th>
                                    <th>Type</th>
                                    <th>Machine</th>
                                    <th>Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recentRewards)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-muted">No recent rewards found</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recentRewards as $reward): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($reward['prize_won']); ?></td>
                                            <td>
                                                <?php 
                                                $type = $reward['is_big_win'] ? 'Big Win' : 'Regular';
                                                $color = $reward['is_big_win'] ? 'success' : 'info';
                                                ?>
                                                <span class="badge bg-<?php echo $color; ?>"><?php echo $type; ?></span>
                                            </td>
                                            <td><?php echo htmlspecialchars($reward['machine_name']); ?></td>
                                            <td><?php echo date('M d, H:i', strtotime($reward['spin_time'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Daily Trends Chart
    const dailyData = <?php echo json_encode($dailyTrends); ?>;
    const dates = dailyData.map(d => d.spin_date);
    const spinsData = dailyData.map(d => parseInt(d.total_spins));
    const successData = dailyData.map(d => parseInt(d.successful_spins));

    new Chart(document.getElementById('dailyTrendsChart'), {
        type: 'line',
        data: {
            labels: dates,
            datasets: [{
                label: 'Total Spins',
                data: spinsData,
                borderColor: '#ffc107',
                backgroundColor: 'rgba(255, 193, 7, 0.1)',
                fill: true
            }, {
                label: 'Successful Spins',
                data: successData,
                borderColor: '#198754',
                backgroundColor: 'rgba(25, 135, 84, 0.1)',
                fill: true
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });

    // Reward Distribution Chart
    const rewardData = <?php echo json_encode($rewardDistribution); ?>;
    const rewardLabels = rewardData.map(d => d.reward_type);
    const rewardCounts = rewardData.map(d => parseInt(d.count));

    new Chart(document.getElementById('rewardChart'), {
        type: 'doughnut',
        data: {
            labels: rewardLabels,
            datasets: [{
                data: rewardCounts,
                backgroundColor: ['#dc3545', '#198754', '#ffc107', '#0d6efd', '#6f42c1']
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });

    // Hourly Pattern Chart
    const hourlyData = <?php echo json_encode($hourlyPattern); ?>;
    const hours = Array.from({length: 24}, (_, i) => i);
    const hourlySpins = hours.map(hour => {
        const found = hourlyData.find(d => parseInt(d.hour) === hour);
        return found ? parseInt(found.spins) : 0;
    });

    new Chart(document.getElementById('hourlyPatternChart'), {
        type: 'bar',
        data: {
            labels: hours.map(h => h + ':00'),
            datasets: [{
                label: 'Spins',
                data: hourlySpins,
                backgroundColor: '#ffc107'
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true
                }
            },
            plugins: {
                legend: {
                    display: false
                }
            }
        }
    });
});
</script>

<style>
.col-md-2-4 {
    flex: 0 0 auto;
    width: 20%;
}
@media (max-width: 768px) {
    .col-md-2-4 {
        width: 50%;
    }
}
</style>

<?php require_once __DIR__ . '/../../core/includes/footer.php'; ?> 