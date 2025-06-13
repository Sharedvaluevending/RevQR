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

// Get engagement statistics
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_scans,
        COUNT(DISTINCT me.ip_address) as unique_scanners,
        COUNT(DISTINCT me.machine_id) as machines_scanned,
        COUNT(DISTINCT DATE(me.created_at)) as active_days
    FROM machine_engagement me
    JOIN machines m ON me.machine_id = m.id
    WHERE m.business_id = ? AND DATE(me.created_at) BETWEEN ? AND ?
");
$stmt->execute([$business_id, $start_date, $end_date]);
$stats = $stmt->fetch();

// Get daily engagement trends
$stmt = $pdo->prepare("
    SELECT 
        DATE(me.created_at) as scan_date,
        COUNT(*) as total_scans,
        COUNT(DISTINCT me.ip_address) as unique_scanners
    FROM machine_engagement me
    JOIN machines m ON me.machine_id = m.id
    WHERE m.business_id = ? AND DATE(me.created_at) BETWEEN ? AND ?
    GROUP BY DATE(me.created_at)
    ORDER BY scan_date ASC
");
$stmt->execute([$business_id, $start_date, $end_date]);
$dailyTrends = $stmt->fetchAll();

// Get engagement by machine
$stmt = $pdo->prepare("
    SELECT 
        m.name as machine_name,
        COUNT(*) as total_scans,
        COUNT(DISTINCT me.ip_address) as unique_scanners,
        ROUND(COUNT(*) / COUNT(DISTINCT me.ip_address), 1) as scans_per_user
    FROM machine_engagement me
    JOIN machines m ON me.machine_id = m.id
    WHERE m.business_id = ? AND DATE(me.created_at) BETWEEN ? AND ?
    GROUP BY m.id, m.name
    ORDER BY total_scans DESC
");
$stmt->execute([$business_id, $start_date, $end_date]);
$machineStats = $stmt->fetchAll();

// Get hourly engagement patterns
$stmt = $pdo->prepare("
    SELECT 
        HOUR(me.created_at) as hour,
        COUNT(*) as scans
    FROM machine_engagement me
    JOIN machines m ON me.machine_id = m.id
    WHERE m.business_id = ? AND DATE(me.created_at) BETWEEN ? AND ?
    GROUP BY HOUR(me.created_at)
    ORDER BY hour
");
$stmt->execute([$business_id, $start_date, $end_date]);
$hourlyPattern = $stmt->fetchAll();

// Get top user agents (devices/browsers)
$stmt = $pdo->prepare("
    SELECT 
        CASE 
            WHEN me.user_agent LIKE '%Mobile%' OR me.user_agent LIKE '%Android%' OR me.user_agent LIKE '%iPhone%' THEN 'Mobile'
            WHEN me.user_agent LIKE '%Tablet%' OR me.user_agent LIKE '%iPad%' THEN 'Tablet'
            ELSE 'Desktop'
        END as device_type,
        COUNT(*) as scans
    FROM machine_engagement me
    JOIN machines m ON me.machine_id = m.id
    WHERE m.business_id = ? AND DATE(me.created_at) BETWEEN ? AND ?
    GROUP BY device_type
    ORDER BY scans DESC
");
$stmt->execute([$business_id, $start_date, $end_date]);
$deviceStats = $stmt->fetchAll();

// Get repeat vs new scanners
$stmt = $pdo->prepare("
    SELECT 
        me.ip_address,
        COUNT(*) as scan_count,
        MIN(me.created_at) as first_scan,
        MAX(me.created_at) as last_scan
    FROM machine_engagement me
    JOIN machines m ON me.machine_id = m.id
    WHERE m.business_id = ? AND DATE(me.created_at) BETWEEN ? AND ?
    GROUP BY me.ip_address
    ORDER BY scan_count DESC
    LIMIT 20
");
$stmt->execute([$business_id, $start_date, $end_date]);
$userEngagement = $stmt->fetchAll();

require_once __DIR__ . '/../../core/includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row mb-4 align-items-center">
        <div class="col">
            <h1 class="h3 mb-0">
                <i class="bi bi-qr-code-scan text-info me-2"></i>
                Engagement Analytics
            </h1>
            <p class="text-muted">Detailed insights into QR scan patterns and user engagement</p>
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

    <!-- Key Metrics -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h3 class="text-info"><?php echo number_format($stats['total_scans']); ?></h3>
                    <small class="text-muted">Total Scans</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h3 class="text-primary"><?php echo number_format($stats['unique_scanners']); ?></h3>
                    <small class="text-muted">Unique Scanners</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h3 class="text-success"><?php echo number_format($stats['machines_scanned']); ?></h3>
                    <small class="text-muted">Machines Scanned</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <?php 
                    $avg_scans = $stats['unique_scanners'] > 0 ? round($stats['total_scans'] / $stats['unique_scanners'], 1) : 0;
                    ?>
                    <h3 class="text-warning"><?php echo $avg_scans; ?></h3>
                    <small class="text-muted">Avg Scans/User</small>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Daily Engagement Trends -->
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Daily Engagement Trends</h5>
                </div>
                <div class="card-body">
                    <canvas id="dailyTrendsChart" height="100"></canvas>
                </div>
            </div>
        </div>

        <!-- Device Type Distribution -->
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Device Types</h5>
                </div>
                <div class="card-body">
                    <canvas id="deviceChart" height="200"></canvas>
                </div>
            </div>
        </div>

        <!-- Machine Performance -->
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Machine Engagement Performance</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Machine</th>
                                    <th>Total Scans</th>
                                    <th>Unique Scanners</th>
                                    <th>Scans per User</th>
                                    <th>Engagement Rate</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($machineStats as $machine): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($machine['machine_name']); ?></td>
                                        <td><?php echo $machine['total_scans']; ?></td>
                                        <td><?php echo $machine['unique_scanners']; ?></td>
                                        <td><?php echo $machine['scans_per_user']; ?></td>
                                        <td>
                                            <?php 
                                            $engagement_rate = $machine['scans_per_user'];
                                            $color = $engagement_rate >= 2 ? 'success' : ($engagement_rate >= 1.5 ? 'warning' : 'danger');
                                            ?>
                                            <span class="badge bg-<?php echo $color; ?>"><?php echo $engagement_rate; ?>x</span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Hourly Engagement Pattern -->
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Hourly Engagement Pattern</h5>
                </div>
                <div class="card-body">
                    <canvas id="hourlyPatternChart" height="200"></canvas>
                </div>
            </div>
        </div>

        <!-- Top Engaged Users -->
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Top Engaged Users (Last <?php echo $days; ?> Days)</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>User (IP)</th>
                                    <th>Total Scans</th>
                                    <th>First Scan</th>
                                    <th>Last Scan</th>
                                    <th>Engagement Level</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($userEngagement as $user): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars(substr($user['ip_address'], 0, -2) . 'xx'); ?></td>
                                        <td><?php echo $user['scan_count']; ?></td>
                                        <td><?php echo date('M d, H:i', strtotime($user['first_scan'])); ?></td>
                                        <td><?php echo date('M d, H:i', strtotime($user['last_scan'])); ?></td>
                                        <td>
                                            <?php 
                                            $level = $user['scan_count'] >= 10 ? 'High' : ($user['scan_count'] >= 5 ? 'Medium' : 'Low');
                                            $color = $level === 'High' ? 'success' : ($level === 'Medium' ? 'warning' : 'secondary');
                                            ?>
                                            <span class="badge bg-<?php echo $color; ?>"><?php echo $level; ?></span>
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
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Daily Trends Chart
    const dailyData = <?php echo json_encode($dailyTrends); ?>;
    const dates = dailyData.map(d => d.scan_date);
    const scansData = dailyData.map(d => parseInt(d.total_scans));
    const uniqueData = dailyData.map(d => parseInt(d.unique_scanners));

    new Chart(document.getElementById('dailyTrendsChart'), {
        type: 'line',
        data: {
            labels: dates,
            datasets: [{
                label: 'Total Scans',
                data: scansData,
                borderColor: '#17a2b8',
                backgroundColor: 'rgba(23, 162, 184, 0.1)',
                fill: true
            }, {
                label: 'Unique Scanners',
                data: uniqueData,
                borderColor: '#007bff',
                backgroundColor: 'rgba(0, 123, 255, 0.1)',
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

    // Device Distribution Chart
    const deviceData = <?php echo json_encode($deviceStats); ?>;
    const deviceLabels = deviceData.map(d => d.device_type);
    const deviceCounts = deviceData.map(d => parseInt(d.scans));

    new Chart(document.getElementById('deviceChart'), {
        type: 'doughnut',
        data: {
            labels: deviceLabels,
            datasets: [{
                data: deviceCounts,
                backgroundColor: ['#28a745', '#ffc107', '#dc3545']
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
    const hourlyScans = hours.map(hour => {
        const found = hourlyData.find(d => parseInt(d.hour) === hour);
        return found ? parseInt(found.scans) : 0;
    });

    new Chart(document.getElementById('hourlyPatternChart'), {
        type: 'bar',
        data: {
            labels: hours.map(h => h + ':00'),
            datasets: [{
                label: 'Scans',
                data: hourlyScans,
                backgroundColor: '#17a2b8'
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

<?php require_once __DIR__ . '/../../core/includes/footer.php'; ?> 