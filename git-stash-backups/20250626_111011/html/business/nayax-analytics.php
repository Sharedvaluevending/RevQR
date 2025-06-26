<?php
/**
 * Advanced Nayax Analytics Dashboard
 * Comprehensive business intelligence and reporting for Nayax integration
 */

require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/nayax_analytics_engine.php';

// Check authentication and role
require_login();
if (!has_role('business')) {
    header('Location: ' . APP_URL . '/login.php');
    exit;
}

// Get business ID
$business_id = get_business_id();
if (!$business_id) {
    header('Location: ' . APP_URL . '/business/profile.php?error=no_business');
    exit;
}

// Get business data
try {
    $stmt = $pdo->prepare("SELECT * FROM businesses WHERE id = ?");
    $stmt->execute([$business_id]);
    $business = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$business) {
        header('Location: ' . APP_URL . '/business/profile.php?error=business_not_found');
        exit;
    }
} catch (Exception $e) {
    error_log("Error fetching business data: " . $e->getMessage());
    $business = ['name' => 'Demo Business', 'id' => $business_id];
}

// Get parameters
$days = $_GET['days'] ?? 30;
$machine_id = $_GET['machine_id'] ?? null;
$export = $_GET['export'] ?? null;

// Initialize analytics engine
$analytics_engine = new NayaxAnalyticsEngine($pdo);

// Get comprehensive analytics
$analytics = $analytics_engine->getBusinessAnalytics($business_id, $days, true);

// Handle export requests
if ($export === 'json') {
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="nayax_analytics_' . date('Y-m-d') . '.json"');
    echo json_encode($analytics, JSON_PRETTY_PRINT);
    exit;
}

if ($export === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="nayax_analytics_' . date('Y-m-d') . '.csv"');
    
    $csv = fopen('php://output', 'w');
    
    // Revenue data
    fputcsv($csv, ['Date', 'Revenue', 'Transactions', 'Coins Awarded']);
    foreach ($analytics['revenue']['daily_breakdown'] as $day) {
        fputcsv($csv, [
            $day['date'],
            $day['total_revenue_cents'] / 100,
            $day['transaction_count'],
            $day['coins_awarded']
        ]);
    }
    
    fclose($csv);
    exit;
}

$page_title = "Advanced Analytics - " . $business['name'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> | RevenueQR</title>
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- Chart.js for advanced visualizations -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/date-fns@2.29.3/index.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@2.0.0/dist/chartjs-adapter-date-fns.bundle.min.js"></script>
    
    <style>
        :root {
            --primary-color: #4a90e2;
            --secondary-color: #f39c12;
            --success-color: #27ae60;
            --danger-color: #e74c3c;
            --warning-color: #f39c12;
            --info-color: #3498db;
            --light-bg: #f8f9fa;
            --shadow: 0 2px 15px rgba(0,0,0,0.1);
            --border-radius: 15px;
        }
        
        body {
            background-color: var(--light-bg);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .analytics-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 30px 0;
            margin-bottom: 30px;
        }
        
        .time-period-selector {
            background: white;
            border-radius: var(--border-radius);
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: var(--shadow);
        }
        
        .metric-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: var(--shadow);
            transition: transform 0.2s, box-shadow 0.3s;
            border-left: 5px solid transparent;
        }
        
        .metric-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 25px rgba(0,0,0,0.15);
        }
        
        .metric-card.revenue { border-left-color: var(--success-color); }
        .metric-card.transactions { border-left-color: var(--info-color); }
        .metric-card.growth { border-left-color: var(--warning-color); }
        .metric-card.performance { border-left-color: var(--primary-color); }
        
        .metric-value {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .metric-label {
            color: #6c757d;
            font-size: 0.9rem;
            margin-bottom: 15px;
        }
        
        .metric-change {
            display: flex;
            align-items: center;
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .metric-change.positive {
            color: var(--success-color);
        }
        
        .metric-change.negative {
            color: var(--danger-color);
        }
        
        .metric-change.neutral {
            color: #6c757d;
        }
        
        .chart-container {
            background: white;
            border-radius: var(--border-radius);
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: var(--shadow);
        }
        
        .chart-title {
            font-size: 1.2rem;
            font-weight: bold;
            margin-bottom: 20px;
            color: var(--primary-color);
        }
        
        .chart-wrapper {
            position: relative;
            height: 400px;
            width: 100%;
        }
        
        .chart-wrapper.small {
            height: 300px;
        }
        
        .insight-card {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border-radius: var(--border-radius);
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: var(--shadow);
        }
        
        .insight-icon {
            font-size: 2rem;
            margin-bottom: 15px;
            opacity: 0.9;
        }
        
        .prediction-card {
            background: linear-gradient(135deg, #ff9a56, #ff6b95);
            color: white;
            border-radius: var(--border-radius);
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: var(--shadow);
        }
        
        .recommendation-item {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            border-left: 4px solid var(--info-color);
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .recommendation-item.high-priority {
            border-left-color: var(--danger-color);
        }
        
        .recommendation-item.medium-priority {
            border-left-color: var(--warning-color);
        }
        
        .recommendation-item.low-priority {
            border-left-color: var(--success-color);
        }
        
        .priority-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .priority-high {
            background: var(--danger-color);
            color: white;
        }
        
        .priority-medium {
            background: var(--warning-color);
            color: white;
        }
        
        .priority-low {
            background: var(--success-color);
            color: white;
        }
        
        .customer-segment {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            text-align: center;
            box-shadow: var(--shadow);
        }
        
        .segment-value {
            font-size: 1.8rem;
            font-weight: bold;
            color: var(--primary-color);
        }
        
        .segment-label {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .export-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .refresh-indicator {
            position: fixed;
            top: 20px;
            right: 20px;
            background: var(--success-color);
            color: white;
            padding: 10px 20px;
            border-radius: 25px;
            display: none;
            z-index: 1000;
        }
        
        .loading-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255,255,255,0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: var(--border-radius);
            z-index: 10;
        }
        
        .spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid var(--primary-color);
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .forecast-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid rgba(255,255,255,0.2);
        }
        
        .forecast-item:last-child {
            border-bottom: none;
        }
        
        .confidence-bar {
            width: 100px;
            height: 6px;
            background: rgba(255,255,255,0.3);
            border-radius: 3px;
            overflow: hidden;
        }
        
        .confidence-fill {
            height: 100%;
            background: rgba(255,255,255,0.8);
            transition: width 0.3s ease;
        }
        
        @media (max-width: 768px) {
            .export-buttons {
                flex-direction: column;
            }
            
            .chart-wrapper {
                height: 300px;
            }
            
            .metric-value {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <!-- Analytics Header -->
    <div class="analytics-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="mb-2">
                        <i class="bi bi-graph-up"></i> Advanced Analytics
                    </h1>
                    <p class="mb-0"><?= htmlspecialchars($business['name']) ?> - Nayax Integration Performance</p>
                </div>
                <div class="col-md-4 text-end">
                    <div class="export-buttons">
                        <a href="?days=<?= $days ?>&export=json" class="btn btn-light btn-sm">
                            <i class="bi bi-download"></i> JSON
                        </a>
                        <a href="?days=<?= $days ?>&export=csv" class="btn btn-light btn-sm">
                            <i class="bi bi-table"></i> CSV
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="container-fluid">
        <!-- Time Period Selector -->
        <div class="time-period-selector">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h5 class="mb-0">
                        <i class="bi bi-calendar-range"></i> Analysis Period
                    </h5>
                    <p class="text-muted mb-0">Data from <?= date('M j, Y', strtotime("-{$days} days")) ?> to <?= date('M j, Y') ?></p>
                </div>
                <div class="col-md-6">
                    <div class="btn-group w-100" role="group">
                        <a href="?days=7" class="btn btn-<?= $days == 7 ? 'primary' : 'outline-primary' ?>">7 Days</a>
                        <a href="?days=30" class="btn btn-<?= $days == 30 ? 'primary' : 'outline-primary' ?>">30 Days</a>
                        <a href="?days=90" class="btn btn-<?= $days == 90 ? 'primary' : 'outline-primary' ?>">90 Days</a>
                        <a href="?days=365" class="btn btn-<?= $days == 365 ? 'primary' : 'outline-primary' ?>">1 Year</a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Key Performance Metrics -->
        <div class="row">
            <div class="col-lg-3 col-md-6">
                <div class="metric-card revenue">
                    <div class="metric-value text-success">
                        $<?= number_format($analytics['revenue']['total_revenue_dollars'], 2) ?>
                    </div>
                    <div class="metric-label">Total Revenue</div>
                    <div class="metric-change <?= $analytics['revenue']['growth_rate'] >= 0 ? 'positive' : 'negative' ?>">
                        <i class="bi bi-<?= $analytics['revenue']['growth_rate'] >= 0 ? 'arrow-up' : 'arrow-down' ?> me-1"></i>
                        <?= abs($analytics['revenue']['growth_rate']) ?>% vs previous period
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6">
                <div class="metric-card transactions">
                    <div class="metric-value text-info">
                        <?= number_format($analytics['revenue']['total_transactions']) ?>
                    </div>
                    <div class="metric-label">Total Transactions</div>
                    <div class="metric-change neutral">
                        <i class="bi bi-activity me-1"></i>
                        $<?= number_format($analytics['revenue']['avg_transaction_value'], 2) ?> avg value
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6">
                <div class="metric-card growth">
                    <div class="metric-value text-warning">
                        <?= number_format($analytics['qr_coins']['total_coins_sold']) ?>
                    </div>
                    <div class="metric-label">QR Coins Sold</div>
                    <div class="metric-change neutral">
                        <i class="bi bi-coin me-1"></i>
                        <?= $analytics['qr_coins']['circulation_rate'] ?>% circulation rate
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6">
                <div class="metric-card performance">
                    <div class="metric-value text-primary">
                        <?= $analytics['performance']['qr_adoption_rate'] ?>%
                    </div>
                    <div class="metric-label">QR Adoption Rate</div>
                    <div class="metric-change neutral">
                        <i class="bi bi-people me-1"></i>
                        <?= $analytics['customers']['overview']['unique_customers'] ?? 0 ?> unique customers
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Charts Row -->
        <div class="row">
            <!-- Revenue Trend Chart -->
            <div class="col-xl-8 col-lg-12">
                <div class="chart-container">
                    <div class="chart-title">
                        <i class="bi bi-graph-up"></i> Revenue Trend Analysis
                        <small class="text-muted ms-2">(<?= $analytics['trends']['revenue_trend'] ?> trend)</small>
                    </div>
                    <div class="chart-wrapper">
                        <canvas id="revenueTrendChart"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- QR Coin Economy -->
            <div class="col-xl-4 col-lg-12">
                <div class="chart-container">
                    <div class="chart-title">
                        <i class="bi bi-coin"></i> QR Coin Economy Health
                        <span class="badge bg-<?= $analytics['qr_coins']['economy_health'] === 'excellent' ? 'success' : ($analytics['qr_coins']['economy_health'] === 'good' ? 'primary' : 'warning') ?> ms-2">
                            <?= ucfirst($analytics['qr_coins']['economy_health']) ?>
                        </span>
                    </div>
                    <div class="chart-wrapper small">
                        <canvas id="coinEconomyChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Transaction Patterns -->
        <div class="row">
            <div class="col-xl-6 col-lg-12">
                <div class="chart-container">
                    <div class="chart-title">
                        <i class="bi bi-clock"></i> Hourly Transaction Patterns
                        <small class="text-muted ms-2">Peak: <?= $analytics['transactions']['peak_hour'] ?></small>
                    </div>
                    <div class="chart-wrapper small">
                        <canvas id="hourlyPatternsChart"></canvas>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-6 col-lg-12">
                <div class="chart-container">
                    <div class="chart-title">
                        <i class="bi bi-calendar-week"></i> Weekly Transaction Patterns
                        <small class="text-muted ms-2">Best: <?= $analytics['trends']['seasonality']['strongest_day'] ?></small>
                    </div>
                    <div class="chart-wrapper small">
                        <canvas id="weeklyPatternsChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Machine Performance -->
        <div class="row">
            <div class="col-xl-8 col-lg-12">
                <div class="chart-container">
                    <div class="chart-title">
                        <i class="bi bi-cpu"></i> Machine Performance Comparison
                        <small class="text-muted ms-2"><?= $analytics['machines']['active_machines'] ?>/<?= $analytics['machines']['total_machines'] ?> active</small>
                    </div>
                    <div class="chart-wrapper">
                        <canvas id="machinePerformanceChart"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Customer Segmentation -->
            <div class="col-xl-4 col-lg-12">
                <div class="chart-container">
                    <div class="chart-title">
                        <i class="bi bi-people"></i> Customer Segmentation
                    </div>
                    <div class="row">
                        <?php foreach ($analytics['customers']['segments'] as $segment): ?>
                        <div class="col-6 mb-3">
                            <div class="customer-segment">
                                <div class="segment-value"><?= $segment['customer_count'] ?></div>
                                <div class="segment-label"><?= $segment['customer_segment'] ?></div>
                                <small class="text-muted">Avg: <?= number_format($segment['avg_coins_spent']) ?> coins</small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Insights and Predictions -->
        <div class="row">
            <!-- Business Insights -->
            <div class="col-xl-6 col-lg-12">
                <div class="insight-card">
                    <div class="insight-icon">
                        <i class="bi bi-lightbulb"></i>
                    </div>
                    <h4>Key Business Insights</h4>
                    <ul class="list-unstyled">
                        <?php foreach ($analytics['transactions']['insights'] as $insight): ?>
                        <li class="mb-2">
                            <i class="bi bi-check-circle me-2"></i>
                            <?= htmlspecialchars($insight) ?>
                        </li>
                        <?php endforeach; ?>
                        
                        <?php foreach ($analytics['machines']['machine_insights'] as $insight): ?>
                        <li class="mb-2">
                            <i class="bi bi-info-circle me-2"></i>
                            <?= htmlspecialchars($insight) ?>
                        </li>
                        <?php endforeach; ?>
                        
                        <?php foreach ($analytics['customers']['customer_insights'] as $insight): ?>
                        <li class="mb-2">
                            <i class="bi bi-person-check me-2"></i>
                            <?= htmlspecialchars($insight) ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            
            <!-- Predictive Analytics -->
            <div class="col-xl-6 col-lg-12">
                <div class="prediction-card">
                    <div class="insight-icon">
                        <i class="bi bi-graph-up-arrow"></i>
                    </div>
                    <h4>Revenue Forecast</h4>
                    
                    <?php if ($analytics['predictions']['revenue_forecast']): ?>
                    <div class="mb-3">
                        <strong>Next Week Projection: $<?= number_format($analytics['predictions']['next_week_revenue'], 2) ?></strong>
                        <br>
                        <small>Confidence: <?= ucfirst($analytics['predictions']['confidence']) ?></small>
                    </div>
                    
                    <div class="forecast-list">
                        <?php foreach (array_slice($analytics['predictions']['revenue_forecast'], 0, 5) as $forecast): ?>
                        <div class="forecast-item">
                            <div>
                                <strong><?= date('M j', strtotime($forecast['date'])) ?></strong><br>
                                <small>$<?= number_format($forecast['predicted_revenue_cents'] / 100, 2) ?></small>
                            </div>
                            <div class="confidence-bar">
                                <div class="confidence-fill" style="width: <?= $forecast['confidence'] ?>%"></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php if ($analytics['predictions']['growth_prediction']): ?>
                    <div class="mt-3 pt-3" style="border-top: 1px solid rgba(255,255,255,0.2);">
                        <strong>Growth Outlook:</strong>
                        <br>
                        Weekly: <?= $analytics['predictions']['growth_prediction']['weekly_growth_rate'] ?>%
                        <br>
                        Monthly: <?= $analytics['predictions']['growth_prediction']['projected_monthly'] ?>%
                    </div>
                    <?php endif; ?>
                    
                    <?php else: ?>
                    <p>Insufficient data for accurate predictions. Continue collecting data for better forecasting.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Recommendations -->
        <div class="row">
            <div class="col-12">
                <div class="chart-container">
                    <div class="chart-title">
                        <i class="bi bi-lightbulb"></i> Optimization Recommendations
                    </div>
                    
                    <?php if (!empty($analytics['recommendations'])): ?>
                    <div class="row">
                        <?php foreach ($analytics['recommendations'] as $rec): ?>
                        <div class="col-lg-6 col-xl-4">
                            <div class="recommendation-item <?= $rec['priority'] ?>-priority">
                                <div class="priority-badge priority-<?= $rec['priority'] ?>">
                                    <?= ucfirst($rec['priority']) ?> Priority
                                </div>
                                <h6><?= htmlspecialchars($rec['title']) ?></h6>
                                <p class="mb-2"><?= htmlspecialchars($rec['description']) ?></p>
                                <div class="d-flex justify-content-between">
                                    <small><strong>Impact:</strong> <?= ucfirst($rec['impact']) ?></small>
                                    <small><strong>Effort:</strong> <?= ucfirst($rec['effort']) ?></small>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="text-center p-5">
                        <i class="bi bi-check-circle text-success" style="font-size: 3rem;"></i>
                        <h5 class="mt-3">Everything looks great!</h5>
                        <p class="text-muted">Your Nayax integration is performing optimally. Keep up the good work!</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Refresh Indicator -->
    <div class="refresh-indicator" id="refreshIndicator">
        <i class="bi bi-check-circle me-2"></i>
        Data refreshed successfully
    </div>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Analytics data from PHP
        const analyticsData = <?= json_encode($analytics) ?>;
        
        // Chart.js default configuration
        Chart.defaults.responsive = true;
        Chart.defaults.maintainAspectRatio = false;
        Chart.defaults.plugins.legend.display = true;
        Chart.defaults.plugins.tooltip.mode = 'index';
        Chart.defaults.plugins.tooltip.intersect = false;
        
        // Color scheme
        const colors = {
            primary: '#4a90e2',
            secondary: '#f39c12',
            success: '#27ae60',
            danger: '#e74c3c',
            warning: '#f39c12',
            info: '#3498db',
            light: '#f8f9fa',
            dark: '#2c3e50'
        };
        
        // Revenue Trend Chart
        const revenueTrendCtx = document.getElementById('revenueTrendChart').getContext('2d');
        new Chart(revenueTrendCtx, {
            type: 'line',
            data: {
                labels: analyticsData.revenue.daily_breakdown.map(d => d.date),
                datasets: [{
                    label: 'Revenue ($)',
                    data: analyticsData.revenue.daily_breakdown.map(d => d.total_revenue_cents / 100),
                    borderColor: colors.success,
                    backgroundColor: colors.success + '20',
                    fill: true,
                    tension: 0.4
                }, {
                    label: 'Transactions',
                    data: analyticsData.revenue.daily_breakdown.map(d => d.transaction_count),
                    borderColor: colors.info,
                    backgroundColor: colors.info + '20',
                    fill: false,
                    yAxisID: 'y1'
                }]
            },
            options: {
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: { display: true, text: 'Revenue ($)' }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: { display: true, text: 'Transactions' },
                        grid: { drawOnChartArea: false }
                    }
                },
                plugins: {
                    title: { display: false },
                    legend: { position: 'top' }
                }
            }
        });
        
        // QR Coin Economy Chart
        const coinEconomyCtx = document.getElementById('coinEconomyChart').getContext('2d');
        new Chart(coinEconomyCtx, {
            type: 'doughnut',
            data: {
                labels: ['Coins Redeemed', 'Coins in Circulation'],
                datasets: [{
                    data: [
                        analyticsData.qr_coins.total_coins_redeemed,
                        analyticsData.qr_coins.coins_in_circulation
                    ],
                    backgroundColor: [colors.success, colors.light],
                    borderColor: [colors.success, colors.light],
                    borderWidth: 2
                }]
            },
            options: {
                plugins: {
                    legend: { position: 'bottom' },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const total = analyticsData.qr_coins.total_coins_sold;
                                const percentage = ((context.parsed / total) * 100).toFixed(1);
                                return `${context.label}: ${context.parsed.toLocaleString()} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
        
        // Hourly Patterns Chart
        const hourlyPatternsCtx = document.getElementById('hourlyPatternsChart').getContext('2d');
        new Chart(hourlyPatternsCtx, {
            type: 'bar',
            data: {
                labels: analyticsData.transactions.hourly_patterns.map(h => h.hour + ':00'),
                datasets: [{
                    label: 'Transactions',
                    data: analyticsData.transactions.hourly_patterns.map(h => h.transaction_count),
                    backgroundColor: colors.primary + '80',
                    borderColor: colors.primary,
                    borderWidth: 1
                }]
            },
            options: {
                scales: {
                    y: { beginAtZero: true }
                },
                plugins: {
                    legend: { display: false }
                }
            }
        });
        
        // Weekly Patterns Chart
        const weeklyPatternsCtx = document.getElementById('weeklyPatternsChart').getContext('2d');
        new Chart(weeklyPatternsCtx, {
            type: 'bar',
            data: {
                labels: analyticsData.transactions.weekly_patterns.map(w => w.day_name),
                datasets: [{
                    label: 'Transactions',
                    data: analyticsData.transactions.weekly_patterns.map(w => w.transaction_count),
                    backgroundColor: colors.secondary + '80',
                    borderColor: colors.secondary,
                    borderWidth: 1
                }]
            },
            options: {
                scales: {
                    y: { beginAtZero: true }
                },
                plugins: {
                    legend: { display: false }
                }
            }
        });
        
        // Machine Performance Chart
        const machinePerformanceCtx = document.getElementById('machinePerformanceChart').getContext('2d');
        new Chart(machinePerformanceCtx, {
            type: 'bar',
            data: {
                labels: analyticsData.machines.machine_performance.map(m => m.machine_name),
                datasets: [{
                    label: 'Revenue ($)',
                    data: analyticsData.machines.machine_performance.map(m => m.total_revenue_cents / 100),
                    backgroundColor: colors.success + '80',
                    borderColor: colors.success,
                    borderWidth: 1
                }, {
                    label: 'QR Adoption Rate (%)',
                    data: analyticsData.machines.machine_performance.map(m => m.qr_coin_adoption_rate),
                    backgroundColor: colors.warning + '80',
                    borderColor: colors.warning,
                    borderWidth: 1,
                    yAxisID: 'y1'
                }]
            },
            options: {
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: { display: true, text: 'Revenue ($)' }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: { display: true, text: 'QR Adoption (%)' },
                        grid: { drawOnChartArea: false }
                    }
                },
                plugins: {
                    legend: { position: 'top' }
                }
            }
        });
        
        // Auto-refresh functionality
        function refreshData() {
            console.log('Refreshing analytics data...');
            
            // Show refresh indicator
            const indicator = document.getElementById('refreshIndicator');
            indicator.style.display = 'block';
            
            setTimeout(() => {
                indicator.style.display = 'none';
            }, 3000);
            
            // In a real implementation, you would fetch new data here
            // and update the charts accordingly
        }
        
        // Refresh every 5 minutes
        setInterval(refreshData, 300000);
        
        // Track analytics view
        fetch('/html/api/track-analytics.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                event: 'advanced_analytics_viewed',
                data: {
                    business_id: <?= $business_id ?>,
                    period_days: <?= $days ?>,
                    total_revenue: analyticsData.revenue.total_revenue_dollars,
                    qr_adoption_rate: analyticsData.performance.qr_adoption_rate
                }
            })
        }).catch(console.error);
        
        // Performance monitoring
        window.addEventListener('load', function() {
            console.log('Advanced Analytics Dashboard loaded successfully');
        });
    </script>
</body>
</html> 