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

// Get voting statistics
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_votes,
        SUM(CASE WHEN v.vote_type = 'vote_in' THEN 1 ELSE 0 END) as vote_in_count,
        SUM(CASE WHEN v.vote_type = 'vote_out' THEN 1 ELSE 0 END) as vote_out_count,
        COUNT(DISTINCT v.voter_ip) as unique_voters,
        COUNT(DISTINCT v.item_id) as items_voted_on
    FROM votes v
    JOIN machines m ON v.machine_id = m.id
    WHERE m.business_id = ? AND DATE(v.created_at) BETWEEN ? AND ?
");
$stmt->execute([$business_id, $start_date, $end_date]);
$stats = $stmt->fetch();

// Get daily voting trends
$stmt = $pdo->prepare("
    SELECT 
        DATE(v.created_at) as vote_date,
        COUNT(*) as total_votes,
        SUM(CASE WHEN v.vote_type = 'vote_in' THEN 1 ELSE 0 END) as vote_in,
        SUM(CASE WHEN v.vote_type = 'vote_out' THEN 1 ELSE 0 END) as vote_out
    FROM votes v
    JOIN machines m ON v.machine_id = m.id
    WHERE m.business_id = ? AND DATE(v.created_at) BETWEEN ? AND ?
    GROUP BY DATE(v.created_at)
    ORDER BY vote_date ASC
");
$stmt->execute([$business_id, $start_date, $end_date]);
$dailyTrends = $stmt->fetchAll();

// Get top voted items
$stmt = $pdo->prepare("
    SELECT 
        i.name,
        COUNT(*) as total_votes,
        SUM(CASE WHEN v.vote_type = 'vote_in' THEN 1 ELSE 0 END) as vote_in,
        SUM(CASE WHEN v.vote_type = 'vote_out' THEN 1 ELSE 0 END) as vote_out,
        ROUND((SUM(CASE WHEN v.vote_type = 'vote_in' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 1) as approval_rate
    FROM votes v
    JOIN items i ON v.item_id = i.id
    JOIN machines m ON v.machine_id = m.id
    WHERE m.business_id = ? AND DATE(v.created_at) BETWEEN ? AND ?
    GROUP BY i.id, i.name
    ORDER BY total_votes DESC
    LIMIT 15
");
$stmt->execute([$business_id, $start_date, $end_date]);
$topItems = $stmt->fetchAll();

// Get voting by machine
$stmt = $pdo->prepare("
    SELECT 
        m.name as machine_name,
        COUNT(*) as total_votes,
        SUM(CASE WHEN v.vote_type = 'vote_in' THEN 1 ELSE 0 END) as vote_in,
        SUM(CASE WHEN v.vote_type = 'vote_out' THEN 1 ELSE 0 END) as vote_out,
        COUNT(DISTINCT v.voter_ip) as unique_voters
    FROM votes v
    JOIN machines m ON v.machine_id = m.id
    WHERE m.business_id = ? AND DATE(v.created_at) BETWEEN ? AND ?
    GROUP BY m.id, m.name
    ORDER BY total_votes DESC
");
$stmt->execute([$business_id, $start_date, $end_date]);
$machineStats = $stmt->fetchAll();

// Get hourly voting patterns
$stmt = $pdo->prepare("
    SELECT 
        HOUR(v.created_at) as hour,
        COUNT(*) as votes
    FROM votes v
    JOIN machines m ON v.machine_id = m.id
    WHERE m.business_id = ? AND DATE(v.created_at) BETWEEN ? AND ?
    GROUP BY HOUR(v.created_at)
    ORDER BY hour
");
$stmt->execute([$business_id, $start_date, $end_date]);
$hourlyPattern = $stmt->fetchAll();

require_once __DIR__ . '/../../core/includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row mb-4 align-items-center">
        <div class="col">
            <h1 class="h3 mb-0">
                <i class="bi bi-bar-chart-fill text-primary me-2"></i>
                Voting Analytics
            </h1>
            <p class="text-muted">Detailed insights into voting patterns and item performance</p>
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
        <div class="col-md-2">
            <div class="card text-center">
                <div class="card-body">
                    <h3 class="text-primary"><?php echo number_format($stats['total_votes']); ?></h3>
                    <small class="text-muted">Total Votes</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-center">
                <div class="card-body">
                    <h3 class="text-success"><?php echo number_format($stats['vote_in_count']); ?></h3>
                    <small class="text-muted">Vote In</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-center">
                <div class="card-body">
                    <h3 class="text-danger"><?php echo number_format($stats['vote_out_count']); ?></h3>
                    <small class="text-muted">Vote Out</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-center">
                <div class="card-body">
                    <h3 class="text-info"><?php echo number_format($stats['unique_voters']); ?></h3>
                    <small class="text-muted">Unique Voters</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-center">
                <div class="card-body">
                    <h3 class="text-warning"><?php echo number_format($stats['items_voted_on']); ?></h3>
                    <small class="text-muted">Items Voted On</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-center">
                <div class="card-body">
                    <?php 
                    $approval_rate = $stats['total_votes'] > 0 ? round(($stats['vote_in_count'] / $stats['total_votes']) * 100, 1) : 0;
                    ?>
                    <h3 class="text-secondary"><?php echo $approval_rate; ?>%</h3>
                    <small class="text-muted">Approval Rate</small>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Daily Voting Trends -->
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Daily Voting Trends</h5>
                </div>
                <div class="card-body">
                    <canvas id="dailyTrendsChart" height="100"></canvas>
                </div>
            </div>
        </div>

        <!-- Vote Type Distribution -->
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Vote Distribution</h5>
                </div>
                <div class="card-body">
                    <canvas id="voteDistributionChart" height="200"></canvas>
                </div>
            </div>
        </div>

        <!-- Top Voted Items -->
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Top Voted Items</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th>Total Votes</th>
                                    <th>Vote In</th>
                                    <th>Vote Out</th>
                                    <th>Approval Rate</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($topItems as $item): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($item['name']); ?></td>
                                        <td><?php echo $item['total_votes']; ?></td>
                                        <td><span class="badge bg-success"><?php echo $item['vote_in']; ?></span></td>
                                        <td><span class="badge bg-danger"><?php echo $item['vote_out']; ?></span></td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="progress flex-grow-1 me-2" style="height: 8px;">
                                                    <div class="progress-bar bg-success" style="width: <?php echo $item['approval_rate']; ?>%"></div>
                                                </div>
                                                <small><?php echo $item['approval_rate']; ?>%</small>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Hourly Voting Pattern -->
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Hourly Voting Pattern</h5>
                </div>
                <div class="card-body">
                    <canvas id="hourlyPatternChart" height="200"></canvas>
                </div>
            </div>
        </div>

        <!-- Machine Performance -->
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Voting by Machine</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Machine</th>
                                    <th>Total Votes</th>
                                    <th>Vote In</th>
                                    <th>Vote Out</th>
                                    <th>Unique Voters</th>
                                    <th>Engagement Rate</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($machineStats as $machine): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($machine['machine_name']); ?></td>
                                        <td><?php echo $machine['total_votes']; ?></td>
                                        <td><span class="badge bg-success"><?php echo $machine['vote_in']; ?></span></td>
                                        <td><span class="badge bg-danger"><?php echo $machine['vote_out']; ?></span></td>
                                        <td><?php echo $machine['unique_voters']; ?></td>
                                        <td>
                                            <?php 
                                            $engagement = $machine['unique_voters'] > 0 ? round($machine['total_votes'] / $machine['unique_voters'], 1) : 0;
                                            echo $engagement . ' votes/voter';
                                            ?>
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
    const dates = dailyData.map(d => d.vote_date);
    const voteInData = dailyData.map(d => parseInt(d.vote_in));
    const voteOutData = dailyData.map(d => parseInt(d.vote_out));

    new Chart(document.getElementById('dailyTrendsChart'), {
        type: 'line',
        data: {
            labels: dates,
            datasets: [{
                label: 'Vote In',
                data: voteInData,
                borderColor: '#198754',
                backgroundColor: 'rgba(25, 135, 84, 0.1)',
                fill: true
            }, {
                label: 'Vote Out',
                data: voteOutData,
                borderColor: '#dc3545',
                backgroundColor: 'rgba(220, 53, 69, 0.1)',
                fill: true
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        color: 'rgba(255, 255, 255, 0.9)'
                    }
                },
                x: {
                    ticks: {
                        color: 'rgba(255, 255, 255, 0.9)'
                    }
                }
            },
            plugins: {
                legend: {
                    labels: {
                        color: 'rgba(255, 255, 255, 0.9)'
                    }
                }
            }
        }
    });

    // Vote Distribution Chart
    new Chart(document.getElementById('voteDistributionChart'), {
        type: 'doughnut',
        data: {
            labels: ['Vote In', 'Vote Out'],
            datasets: [{
                data: [<?php echo $stats['vote_in_count']; ?>, <?php echo $stats['vote_out_count']; ?>],
                backgroundColor: ['#198754', '#dc3545']
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        color: 'rgba(255, 255, 255, 0.9)'
                    }
                }
            }
        }
    });

    // Hourly Pattern Chart
    const hourlyData = <?php echo json_encode($hourlyPattern); ?>;
    const hours = Array.from({length: 24}, (_, i) => i);
    const hourlyVotes = hours.map(hour => {
        const found = hourlyData.find(d => parseInt(d.hour) === hour);
        return found ? parseInt(found.votes) : 0;
    });

    new Chart(document.getElementById('hourlyPatternChart'), {
        type: 'bar',
        data: {
            labels: hours.map(h => h + ':00'),
            datasets: [{
                label: 'Votes',
                data: hourlyVotes,
                backgroundColor: '#0d6efd'
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        color: 'rgba(255, 255, 255, 0.9)'
                    }
                },
                x: {
                    ticks: {
                        color: 'rgba(255, 255, 255, 0.9)'
                    }
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