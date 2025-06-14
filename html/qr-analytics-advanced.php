<?php
session_start();
require_once 'core/config/database.php';
require_once 'core/includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Get business ID
$stmt = $pdo->prepare("SELECT business_id FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
$business_id = $user['business_id'];

try {
    // MASSIVE QR ANALYTICS - RESTORED FROM LOST WORK
    
    // 1. QR Performance Overview (30-day trend)
    $stmt = $pdo->prepare("
        SELECT 
            DATE(qcs.created_at) as scan_date,
            COUNT(DISTINCT qcs.id) as daily_scans,
            COUNT(DISTINCT qcs.qr_code_id) as unique_qrs_scanned,
            COUNT(DISTINCT v.id) as votes_generated,
            COUNT(DISTINCT qct.id) as transactions,
            COALESCE(SUM(qct.amount), 0) as daily_coin_value
        FROM qr_code_stats qcs
        LEFT JOIN qr_codes qr ON qcs.qr_code_id = qr.id
        LEFT JOIN votes v ON qr.id = v.qr_code_id AND DATE(v.created_at) = DATE(qcs.created_at)
        LEFT JOIN qr_coin_transactions qct ON qr.id = qct.qr_code_id AND DATE(qct.created_at) = DATE(qcs.created_at)
        WHERE (qr.business_id = ? OR 
               qr.campaign_id IN (SELECT id FROM campaigns WHERE business_id = ?) OR
               qr.machine_id IN (SELECT id FROM voting_lists WHERE business_id = ?))
        AND qcs.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY DATE(qcs.created_at)
        ORDER BY scan_date DESC
    ");
    $stmt->execute([$business_id, $business_id, $business_id]);
    $daily_performance = $stmt->fetchAll();

    // 2. QR Types Performance Analysis
    $stmt = $pdo->prepare("
        SELECT 
            qr.qr_type,
            COUNT(DISTINCT qr.id) as total_qrs,
            COUNT(DISTINCT qcs.id) as total_scans,
            COUNT(DISTINCT v.id) as total_votes,
            COUNT(DISTINCT qct.id) as total_transactions,
            COALESCE(SUM(qct.amount), 0) as total_coin_value,
            AVG(TIMESTAMPDIFF(HOUR, qr.created_at, qcs.created_at)) as avg_time_to_first_scan,
            ROUND(COUNT(DISTINCT qcs.id) / COUNT(DISTINCT qr.id), 2) as scans_per_qr,
            COUNT(CASE WHEN qcs.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as recent_scans,
            CASE 
                WHEN COUNT(DISTINCT qcs.id) / COUNT(DISTINCT qr.id) >= 20 THEN 'HIGH ENGAGEMENT'
                WHEN COUNT(DISTINCT qcs.id) / COUNT(DISTINCT qr.id) >= 10 THEN 'GOOD ENGAGEMENT'
                WHEN COUNT(DISTINCT qcs.id) / COUNT(DISTINCT qr.id) >= 5 THEN 'MODERATE ENGAGEMENT'
                ELSE 'LOW ENGAGEMENT'
            END as engagement_level
        FROM qr_codes qr
        LEFT JOIN qr_code_stats qcs ON qr.id = qcs.qr_code_id
        LEFT JOIN votes v ON qr.id = v.qr_code_id
        LEFT JOIN qr_coin_transactions qct ON qr.id = qct.qr_code_id
        WHERE (qr.business_id = ? OR 
               qr.campaign_id IN (SELECT id FROM campaigns WHERE business_id = ?) OR
               qr.machine_id IN (SELECT id FROM voting_lists WHERE business_id = ?))
        AND qr.status = 'active'
        GROUP BY qr.qr_type
        ORDER BY total_scans DESC
    ");
    $stmt->execute([$business_id, $business_id, $business_id]);
    $qr_types_performance = $stmt->fetchAll();

    // 3. Hourly Usage Patterns (Business Intelligence)
    $stmt = $pdo->prepare("
        SELECT 
            HOUR(qcs.created_at) as hour_of_day,
            COUNT(DISTINCT qcs.id) as scans,
            COUNT(DISTINCT v.id) as votes,
            AVG(CASE WHEN v.vote_type = 'vote_in' THEN 1 ELSE 0 END) as positive_vote_ratio
        FROM qr_code_stats qcs
        LEFT JOIN qr_codes qr ON qcs.qr_code_id = qr.id
        LEFT JOIN votes v ON qr.id = v.qr_code_id AND DATE(v.created_at) = DATE(qcs.created_at)
        WHERE (qr.business_id = ? OR 
               qr.campaign_id IN (SELECT id FROM campaigns WHERE business_id = ?) OR
               qr.machine_id IN (SELECT id FROM voting_lists WHERE business_id = ?))
        AND qcs.created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
        GROUP BY HOUR(qcs.created_at)
        ORDER BY hour_of_day
    ");
    $stmt->execute([$business_id, $business_id, $business_id]);
    $hourly_patterns = $stmt->fetchAll();

    // 4. Device & Browser Analytics
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(qcs.device_type, 'Unknown') as device,
            COALESCE(qcs.browser, 'Unknown') as browser,
            COALESCE(qcs.os, 'Unknown') as operating_system,
            COUNT(*) as scan_count,
            COUNT(DISTINCT qcs.qr_code_id) as unique_qrs,
            ROUND(COUNT(*) * 100.0 / SUM(COUNT(*)) OVER(), 2) as percentage
        FROM qr_code_stats qcs
        JOIN qr_codes qr ON qcs.qr_code_id = qr.id
        WHERE (qr.business_id = ? OR 
               qr.campaign_id IN (SELECT id FROM campaigns WHERE business_id = ?) OR
               qr.machine_id IN (SELECT id FROM voting_lists WHERE business_id = ?))
        AND qcs.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY device, browser, operating_system
        ORDER BY scan_count DESC
        LIMIT 20
    ");
    $stmt->execute([$business_id, $business_id, $business_id]);
    $device_analytics = $stmt->fetchAll();

    // 5. Geographic Analysis (IP-based)
    $stmt = $pdo->prepare("
        SELECT 
            SUBSTRING_INDEX(qcs.ip_address, '.', 2) as ip_range,
            COUNT(*) as scans,
            COUNT(DISTINCT qcs.qr_code_id) as different_qrs,
            COUNT(DISTINCT v.id) as votes_from_area
        FROM qr_code_stats qcs
        JOIN qr_codes qr ON qcs.qr_code_id = qr.id
        LEFT JOIN votes v ON qr.id = v.qr_code_id AND v.voter_ip = qcs.ip_address
        WHERE (qr.business_id = ? OR 
               qr.campaign_id IN (SELECT id FROM campaigns WHERE business_id = ?) OR
               qr.machine_id IN (SELECT id FROM voting_lists WHERE business_id = ?))
        AND qcs.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        AND qcs.ip_address IS NOT NULL
        GROUP BY ip_range
        HAVING scans >= 5
        ORDER BY scans DESC
        LIMIT 15
    ");
    $stmt->execute([$business_id, $business_id, $business_id]);
    $geographic_data = $stmt->fetchAll();

    // 6. Business Intelligence Insights
    $stmt = $pdo->prepare("
        SELECT 
            'QR Scan Conversion Rate' as metric,
            ROUND(
                (COUNT(DISTINCT v.id) * 100.0 / NULLIF(COUNT(DISTINCT qcs.id), 0)), 2
            ) as value,
            '%' as unit,
            CASE 
                WHEN (COUNT(DISTINCT v.id) * 100.0 / NULLIF(COUNT(DISTINCT qcs.id), 0)) >= 15 THEN 'EXCELLENT'
                WHEN (COUNT(DISTINCT v.id) * 100.0 / NULLIF(COUNT(DISTINCT qcs.id), 0)) >= 10 THEN 'GOOD'
                WHEN (COUNT(DISTINCT v.id) * 100.0 / NULLIF(COUNT(DISTINCT qcs.id), 0)) >= 5 THEN 'AVERAGE'
                ELSE 'NEEDS IMPROVEMENT'
            END as status
        FROM qr_code_stats qcs
        JOIN qr_codes qr ON qcs.qr_code_id = qr.id
        LEFT JOIN votes v ON qr.id = v.qr_code_id
        WHERE (qr.business_id = ? OR 
               qr.campaign_id IN (SELECT id FROM campaigns WHERE business_id = ?) OR
               qr.machine_id IN (SELECT id FROM voting_lists WHERE business_id = ?))
        AND qcs.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)

        UNION ALL

        SELECT 
            'Average Scans Per QR' as metric,
            ROUND(COUNT(DISTINCT qcs.id) / NULLIF(COUNT(DISTINCT qr.id), 0), 2) as value,
            'scans' as unit,
            CASE 
                WHEN (COUNT(DISTINCT qcs.id) / NULLIF(COUNT(DISTINCT qr.id), 0)) >= 25 THEN 'EXCELLENT'
                WHEN (COUNT(DISTINCT qcs.id) / NULLIF(COUNT(DISTINCT qr.id), 0)) >= 15 THEN 'GOOD'
                WHEN (COUNT(DISTINCT qcs.id) / NULLIF(COUNT(DISTINCT qr.id), 0)) >= 8 THEN 'AVERAGE'
                ELSE 'NEEDS IMPROVEMENT'
            END as status
        FROM qr_code_stats qcs
        JOIN qr_codes qr ON qcs.qr_code_id = qr.id
        WHERE (qr.business_id = ? OR 
               qr.campaign_id IN (SELECT id FROM campaigns WHERE business_id = ?) OR
               qr.machine_id IN (SELECT id FROM voting_lists WHERE business_id = ?))
        AND qcs.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)

        UNION ALL

        SELECT 
            'QR Code ROI Score' as metric,
            ROUND(
                (COUNT(DISTINCT qcs.id) + (COUNT(DISTINCT v.id) * 2) + 
                 COALESCE(SUM(qct.amount), 0) / 10) / NULLIF(COUNT(DISTINCT qr.id), 0), 2
            ) as value,
            'points' as unit,
            CASE 
                WHEN ((COUNT(DISTINCT qcs.id) + (COUNT(DISTINCT v.id) * 2) + 
                       COALESCE(SUM(qct.amount), 0) / 10) / NULLIF(COUNT(DISTINCT qr.id), 0)) >= 50 THEN 'EXCELLENT'
                WHEN ((COUNT(DISTINCT qcs.id) + (COUNT(DISTINCT v.id) * 2) + 
                       COALESCE(SUM(qct.amount), 0) / 10) / NULLIF(COUNT(DISTINCT qr.id), 0)) >= 30 THEN 'GOOD'
                WHEN ((COUNT(DISTINCT qcs.id) + (COUNT(DISTINCT v.id) * 2) + 
                       COALESCE(SUM(qct.amount), 0) / 10) / NULLIF(COUNT(DISTINCT qr.id), 0)) >= 15 THEN 'AVERAGE'
                ELSE 'NEEDS IMPROVEMENT'
            END as status
        FROM qr_code_stats qcs
        JOIN qr_codes qr ON qcs.qr_code_id = qr.id
        LEFT JOIN votes v ON qr.id = v.qr_code_id
        LEFT JOIN qr_coin_transactions qct ON qr.id = qct.qr_code_id
        WHERE (qr.business_id = ? OR 
               qr.campaign_id IN (SELECT id FROM campaigns WHERE business_id = ?) OR
               qr.machine_id IN (SELECT id FROM voting_lists WHERE business_id = ?))
        AND qcs.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $stmt->execute([
        $business_id, $business_id, $business_id,
        $business_id, $business_id, $business_id,
        $business_id, $business_id, $business_id
    ]);
    $business_insights = $stmt->fetchAll();

} catch (Exception $e) {
    $error_message = "Error loading analytics: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advanced QR Analytics - RevenueQR</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .glass-effect {
            background: rgba(255, 255, 255, 0.25);
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37);
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
            border-radius: 10px;
            border: 1px solid rgba(255, 255, 255, 0.18);
        }
        .metric-card {
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .metric-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
        }
        .chart-container {
            position: relative;
            height: 400px;
        }
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h1 class="display-6 text-white mb-0">
                            <i class="bi bi-graph-up-arrow"></i> Advanced QR Analytics
                        </h1>
                        <p class="text-white-50 mb-0">Complete business intelligence dashboard</p>
                    </div>
                    <div class="btn-group">
                        <a href="qr_manager.php" class="btn btn-outline-light">
                            <i class="bi bi-arrow-left"></i> Back to QR Manager
                        </a>
                        <button class="btn btn-light" onclick="exportAnalytics()">
                            <i class="bi bi-download"></i> Export Data
                        </button>
                    </div>
                </div>

                <!-- Business Intelligence Overview -->
                <?php if (!empty($business_insights)): ?>
                <div class="row mb-4">
                    <?php foreach ($business_insights as $insight): ?>
                    <div class="col-lg-4 col-md-6 mb-3">
                        <div class="card glass-effect metric-card h-100">
                            <div class="card-body text-center">
                                <h3 class="text-white mb-1"><?= $insight['value'] ?><?= $insight['unit'] ?></h3>
                                <p class="text-white-50 mb-2"><?= $insight['metric'] ?></p>
                                <span class="badge <?php
                                switch($insight['status']) {
                                    case 'EXCELLENT': echo 'bg-success'; break;
                                    case 'GOOD': echo 'bg-info'; break;
                                    case 'AVERAGE': echo 'bg-warning'; break;
                                    default: echo 'bg-danger';
                                }
                                ?>"><?= $insight['status'] ?></span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- Performance Trend Chart -->
                <?php if (!empty($daily_performance)): ?>
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card glass-effect">
                            <div class="card-header">
                                <h5 class="text-white mb-0">
                                    <i class="bi bi-graph-up"></i> 30-Day Performance Trend
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="performanceChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- QR Types & Hourly Patterns -->
                <div class="row mb-4">
                    <?php if (!empty($qr_types_performance)): ?>
                    <div class="col-lg-6 mb-3">
                        <div class="card glass-effect h-100">
                            <div class="card-header">
                                <h5 class="text-white mb-0">
                                    <i class="bi bi-pie-chart"></i> QR Types Performance
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container" style="height: 300px;">
                                    <canvas id="qrTypesChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($hourly_patterns)): ?>
                    <div class="col-lg-6 mb-3">
                        <div class="card glass-effect h-100">
                            <div class="card-header">
                                <h5 class="text-white mb-0">
                                    <i class="bi bi-clock"></i> Hourly Usage Patterns
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container" style="height: 300px;">
                                    <canvas id="hourlyChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Device Analytics & Geographic Data -->
                <div class="row mb-4">
                    <?php if (!empty($device_analytics)): ?>
                    <div class="col-lg-6 mb-3">
                        <div class="card glass-effect">
                            <div class="card-header">
                                <h5 class="text-white mb-0">
                                    <i class="bi bi-phone"></i> Device & Browser Analytics
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm text-white">
                                        <thead>
                                            <tr>
                                                <th>Device</th>
                                                <th>Browser</th>
                                                <th>OS</th>
                                                <th>Scans</th>
                                                <th>%</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach (array_slice($device_analytics, 0, 10) as $device): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($device['device']) ?></td>
                                                <td><?= htmlspecialchars($device['browser']) ?></td>
                                                <td><?= htmlspecialchars($device['operating_system']) ?></td>
                                                <td><span class="badge bg-info"><?= $device['scan_count'] ?></span></td>
                                                <td><?= $device['percentage'] ?>%</td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($geographic_data)): ?>
                    <div class="col-lg-6 mb-3">
                        <div class="card glass-effect">
                            <div class="card-header">
                                <h5 class="text-white mb-0">
                                    <i class="bi bi-geo-alt"></i> Geographic Analysis
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container" style="height: 300px;">
                                    <canvas id="geoChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // MASSIVE CHART.JS VISUALIZATIONS - RESTORED
    
    <?php if (!empty($daily_performance)): ?>
    // Performance Trend Chart
    const performanceCtx = document.getElementById('performanceChart').getContext('2d');
    new Chart(performanceCtx, {
        type: 'line',
        data: {
            labels: <?= json_encode(array_reverse(array_column($daily_performance, 'scan_date'))) ?>,
            datasets: [{
                label: 'Daily Scans',
                data: <?= json_encode(array_reverse(array_column($daily_performance, 'daily_scans'))) ?>,
                borderColor: 'rgb(75, 192, 192)',
                tension: 0.1,
                fill: false
            }, {
                label: 'Votes Generated',
                data: <?= json_encode(array_reverse(array_column($daily_performance, 'votes_generated'))) ?>,
                borderColor: 'rgb(255, 99, 132)',
                tension: 0.1,
                fill: false
            }, {
                label: 'Coin Value',
                data: <?= json_encode(array_reverse(array_column($daily_performance, 'daily_coin_value'))) ?>,
                borderColor: 'rgb(255, 205, 86)',
                tension: 0.1,
                fill: false
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { labels: { color: 'white' } }
            },
            scales: {
                x: { ticks: { color: 'white' } },
                y: { ticks: { color: 'white' } }
            }
        }
    });
    <?php endif; ?>

    <?php if (!empty($qr_types_performance)): ?>
    // QR Types Chart
    const qrTypesCtx = document.getElementById('qrTypesChart').getContext('2d');
    new Chart(qrTypesCtx, {
        type: 'doughnut',
        data: {
            labels: <?= json_encode(array_column($qr_types_performance, 'qr_type')) ?>,
            datasets: [{
                data: <?= json_encode(array_column($qr_types_performance, 'total_scans')) ?>,
                backgroundColor: [
                    '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', 
                    '#9966FF', '#FF9F40', '#FF6384', '#C9CBCF'
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { labels: { color: 'white' } }
            }
        }
    });
    <?php endif; ?>

    <?php if (!empty($hourly_patterns)): ?>
    // Hourly Patterns Chart
    const hourlyCtx = document.getElementById('hourlyChart').getContext('2d');
    new Chart(hourlyCtx, {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_map(function($h) { return $h['hour_of_day'] . ':00'; }, $hourly_patterns)) ?>,
            datasets: [{
                label: 'Scans by Hour',
                data: <?= json_encode(array_column($hourly_patterns, 'scans')) ?>,
                backgroundColor: 'rgba(54, 162, 235, 0.8)'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { labels: { color: 'white' } }
            },
            scales: {
                x: { ticks: { color: 'white' } },
                y: { ticks: { color: 'white' } }
            }
        }
    });
    <?php endif; ?>

    <?php if (!empty($geographic_data)): ?>
    // Geographic Chart
    const geoCtx = document.getElementById('geoChart').getContext('2d');
    new Chart(geoCtx, {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_column($geographic_data, 'ip_range')) ?>,
            datasets: [{
                label: 'Scans by Area',
                data: <?= json_encode(array_column($geographic_data, 'scans')) ?>,
                backgroundColor: 'rgba(255, 206, 86, 0.8)'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { labels: { color: 'white' } }
            },
            scales: {
                x: { ticks: { color: 'white' } },
                y: { ticks: { color: 'white' } }
            }
        }
    });
    <?php endif; ?>

    function exportAnalytics() {
        // Export analytics data as CSV
        const csvData = [
            ['Date', 'Scans', 'Votes', 'Coins'],
            <?php if (!empty($daily_performance)): ?>
            <?php foreach ($daily_performance as $day): ?>
            ['<?= $day['scan_date'] ?>', <?= $day['daily_scans'] ?>, <?= $day['votes_generated'] ?>, <?= $day['daily_coin_value'] ?>],
            <?php endforeach; ?>
            <?php endif; ?>
        ];
        
        const csvContent = csvData.map(row => row.join(',')).join('\n');
        const blob = new Blob([csvContent], { type: 'text/csv' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.setAttribute('hidden', '');
        a.setAttribute('href', url);
        a.setAttribute('download', 'qr-analytics-' + new Date().toISOString().split('T')[0] + '.csv');
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    }
    </script>
</body>
</html> 