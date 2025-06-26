<?php
/**
 * Customer Analytics and Segmentation Dashboard
 * Advanced customer insights for Nayax integration
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
$segment = $_GET['segment'] ?? 'all';

// Initialize analytics engine
$analytics_engine = new NayaxAnalyticsEngine($pdo);

// Get customer analytics
$analytics = $analytics_engine->getBusinessAnalytics($business_id, $days, true);

// Get detailed customer data (with fallback for missing tables)
$customers = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            u.id,
            u.username,
            u.email,
            u.created_at as user_since,
            COUNT(nt.id) as total_purchases,
            SUM(nt.amount_cents) as total_spent_cents,
            COUNT(CASE WHEN nt.status = 'completed' THEN 1 END) as completed_purchases,
            MAX(nt.created_at) as last_purchase,
            MIN(nt.created_at) as first_purchase,
            AVG(nt.amount_cents) as avg_spend_per_purchase,
            CASE 
                WHEN SUM(nt.amount_cents) >= 100000 THEN 'High Value'
                WHEN SUM(nt.amount_cents) >= 50000 THEN 'Medium Value'
                WHEN SUM(nt.amount_cents) >= 10000 THEN 'Low Value'
                ELSE 'Trial'
            END as customer_segment,
            DATEDIFF(NOW(), MAX(nt.created_at)) as days_since_last_purchase
        FROM users u
        JOIN nayax_transactions nt ON u.id = nt.user_id
        JOIN nayax_machines nm ON nt.nayax_machine_id = nm.nayax_machine_id
        WHERE nm.business_id = ? 
        AND nt.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        AND nt.status = 'completed'
        GROUP BY u.id, u.username, u.email, u.created_at
        HAVING total_purchases > 0
        ORDER BY total_spent_cents DESC
    ");
    $stmt->execute([$business_id, $days]);
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching customer data: " . $e->getMessage());
    // Use demo data if tables don't exist
    $customers = [
        [
            'id' => 1,
            'username' => 'demo_customer_1',
            'email' => 'customer1@example.com',
            'user_since' => date('Y-m-d H:i:s', strtotime('-6 months')),
            'total_purchases' => 25,
            'total_spent_cents' => 150000,
            'completed_purchases' => 23,
            'last_purchase' => date('Y-m-d H:i:s', strtotime('-2 days')),
            'first_purchase' => date('Y-m-d H:i:s', strtotime('-5 months')),
            'avg_spend_per_purchase' => 6000,
            'customer_segment' => 'High Value',
            'days_since_last_purchase' => 2
        ],
        [
            'id' => 2,
            'username' => 'demo_customer_2',
            'email' => 'customer2@example.com',
            'user_since' => date('Y-m-d H:i:s', strtotime('-3 months')),
            'total_purchases' => 12,
            'total_spent_cents' => 72000,
            'completed_purchases' => 11,
            'last_purchase' => date('Y-m-d H:i:s', strtotime('-1 week')),
            'first_purchase' => date('Y-m-d H:i:s', strtotime('-2 months')),
            'avg_spend_per_purchase' => 6000,
            'customer_segment' => 'Medium Value',
            'days_since_last_purchase' => 7
        ]
    ];
}

// Filter by segment if specified
if ($segment !== 'all') {
    $customers = array_filter($customers, function($customer) use ($segment) {
        return strtolower(str_replace(' ', '_', $customer['customer_segment'])) === $segment;
    });
}

// Calculate customer lifecycle metrics
$lifecycle_metrics = [];
foreach ($customers as $customer) {
    $days_active = max(1, (strtotime($customer['last_purchase']) - strtotime($customer['first_purchase'])) / 86400);
    $purchase_frequency = $customer['total_purchases'] / max(1, $days_active / 7); // purchases per week
    
    $lifecycle_stage = 'new';
    if ($customer['days_since_last_purchase'] > 30) {
        $lifecycle_stage = 'at_risk';
    } elseif ($customer['days_since_last_purchase'] > 14) {
        $lifecycle_stage = 'declining';
    } elseif ($customer['total_purchases'] >= 5) {
        $lifecycle_stage = 'loyal';
    } elseif ($customer['total_purchases'] >= 2) {
        $lifecycle_stage = 'repeat';
    }
    
    $lifecycle_metrics[] = [
        'customer_id' => $customer['id'],
        'lifecycle_stage' => $lifecycle_stage,
        'purchase_frequency' => round($purchase_frequency, 2),
        'days_active' => round($days_active),
        'engagement_score' => min(100, ($customer['codes_used'] / max(1, $customer['total_purchases'])) * 100)
    ];
}

$page_title = "Customer Analytics - " . $business['name'];
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
    <link href="https://cdn.datatables.net/1.12.1/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    
    <!-- Chart.js for visualizations -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    
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
        
        .customer-header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 30px 0;
            margin-bottom: 30px;
        }
        
        .segment-filter {
            background: white;
            border-radius: var(--border-radius);
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: var(--shadow);
        }
        
        .customer-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: var(--shadow);
            transition: transform 0.2s, box-shadow 0.3s;
        }
        
        .customer-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 25px rgba(0,0,0,0.15);
        }
        
        .segment-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .segment-high-value {
            background: var(--success-color);
            color: white;
        }
        
        .segment-medium-value {
            background: var(--info-color);
            color: white;
        }
        
        .segment-low-value {
            background: var(--warning-color);
            color: white;
        }
        
        .segment-trial {
            background: #6c757d;
            color: white;
        }
        
        .lifecycle-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 500;
            margin-left: 10px;
        }
        
        .lifecycle-new { background: #e8f5e8; color: var(--success-color); }
        .lifecycle-repeat { background: #e3f2fd; color: var(--info-color); }
        .lifecycle-loyal { background: #fff3e0; color: var(--warning-color); }
        .lifecycle-declining { background: #ffebee; color: var(--danger-color); }
        .lifecycle-at_risk { background: #ffcdd2; color: var(--danger-color); font-weight: bold; }
        
        .customer-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 1.2rem;
        }
        
        .customer-stats {
            display: flex;
            gap: 20px;
            margin-top: 15px;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-value {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--primary-color);
        }
        
        .stat-label {
            color: #6c757d;
            font-size: 0.8rem;
        }
        
        .engagement-bar {
            width: 100%;
            height: 8px;
            background: #e9ecef;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 10px;
        }
        
        .engagement-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--danger-color), var(--warning-color), var(--success-color));
            transition: width 0.3s ease;
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
            height: 300px;
            width: 100%;
        }
        
        .customer-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        .action-btn {
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .action-email {
            background: var(--info-color);
            color: white;
        }
        
        .action-promote {
            background: var(--warning-color);
            color: white;
        }
        
        .action-retain {
            background: var(--danger-color);
            color: white;
        }
        
        .customer-journey {
            display: flex;
            align-items: center;
            margin-top: 15px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
        }
        
        .journey-step {
            flex: 1;
            text-align: center;
            position: relative;
        }
        
        .journey-step:not(:last-child)::after {
            content: '';
            position: absolute;
            right: -50%;
            top: 50%;
            width: 100%;
            height: 2px;
            background: #dee2e6;
            transform: translateY(-50%);
        }
        
        .journey-step.active::after {
            background: var(--primary-color);
        }
        
        .journey-icon {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #dee2e6;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 5px;
            color: #6c757d;
        }
        
        .journey-step.active .journey-icon {
            background: var(--primary-color);
            color: white;
        }
        
        .journey-label {
            font-size: 0.75rem;
            color: #6c757d;
        }
        
        .journey-step.active .journey-label {
            color: var(--primary-color);
            font-weight: bold;
        }
        
        .cohort-analysis {
            overflow-x: auto;
        }
        
        .cohort-table {
            min-width: 600px;
        }
        
        .cohort-cell {
            text-align: center;
            padding: 8px;
            border-radius: 4px;
            color: white;
            font-weight: bold;
            font-size: 0.9rem;
        }
        
        .retention-high { background: var(--success-color); }
        .retention-medium { background: var(--warning-color); }
        .retention-low { background: var(--danger-color); }
        .retention-none { background: #dee2e6; color: #6c757d; }
        
        @media (max-width: 768px) {
            .customer-stats {
                flex-direction: column;
                gap: 10px;
            }
            
            .customer-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <!-- Customer Header -->
    <div class="customer-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="mb-2">
                        <i class="bi bi-people"></i> Customer Analytics
                    </h1>
                    <p class="mb-0"><?= htmlspecialchars($business['name']) ?> - Customer Insights & Segmentation</p>
                </div>
                <div class="col-md-4 text-end">
                    <div class="text-white">
                        <strong><?= count($customers) ?></strong> Active Customers<br>
                        <small>in the last <?= $days ?> days</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="container-fluid">
        <!-- Segment Filter -->
        <div class="segment-filter">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h5 class="mb-0">
                        <i class="bi bi-funnel"></i> Customer Segments
                    </h5>
                    <p class="text-muted mb-0">Filter by customer value and behavior</p>
                </div>
                <div class="col-md-6">
                    <div class="btn-group w-100" role="group">
                        <a href="?days=<?= $days ?>&segment=all" class="btn btn-<?= $segment === 'all' ? 'primary' : 'outline-primary' ?>">All</a>
                        <a href="?days=<?= $days ?>&segment=high_value" class="btn btn-<?= $segment === 'high_value' ? 'primary' : 'outline-primary' ?>">High Value</a>
                        <a href="?days=<?= $days ?>&segment=medium_value" class="btn btn-<?= $segment === 'medium_value' ? 'primary' : 'outline-primary' ?>">Medium</a>
                        <a href="?days=<?= $days ?>&segment=low_value" class="btn btn-<?= $segment === 'low_value' ? 'primary' : 'outline-primary' ?>">Low Value</a>
                        <a href="?days=<?= $days ?>&segment=trial" class="btn btn-<?= $segment === 'trial' ? 'primary' : 'outline-primary' ?>">Trial</a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Customer Segmentation Overview -->
        <div class="row">
            <div class="col-xl-6 col-lg-12">
                <div class="chart-container">
                    <div class="chart-title">
                        <i class="bi bi-pie-chart"></i> Customer Segmentation
                    </div>
                    <div class="chart-wrapper">
                        <canvas id="segmentationChart"></canvas>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-6 col-lg-12">
                <div class="chart-container">
                    <div class="chart-title">
                        <i class="bi bi-graph-up"></i> Customer Lifecycle Stages
                    </div>
                    <div class="chart-wrapper">
                        <canvas id="lifecycleChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Customer Journey Analysis -->
        <div class="row">
            <div class="col-12">
                <div class="chart-container">
                    <div class="chart-title">
                        <i class="bi bi-arrow-right-circle"></i> Customer Journey Mapping
                    </div>
                    
                    <div class="row">
                        <div class="col-lg-8">
                            <div class="customer-journey">
                                <div class="journey-step active">
                                    <div class="journey-icon">
                                        <i class="bi bi-person-plus"></i>
                                    </div>
                                    <div class="journey-label">Discovery</div>
                                </div>
                                <div class="journey-step active">
                                    <div class="journey-icon">
                                        <i class="bi bi-qr-code"></i>
                                    </div>
                                    <div class="journey-label">First QR Scan</div>
                                </div>
                                <div class="journey-step active">
                                    <div class="journey-icon">
                                        <i class="bi bi-coin"></i>
                                    </div>
                                    <div class="journey-label">Coin Purchase</div>
                                </div>
                                <div class="journey-step active">
                                    <div class="journey-icon">
                                        <i class="bi bi-percent"></i>
                                    </div>
                                    <div class="journey-label">Discount Use</div>
                                </div>
                                <div class="journey-step active">
                                    <div class="journey-icon">
                                        <i class="bi bi-arrow-repeat"></i>
                                    </div>
                                    <div class="journey-label">Repeat Customer</div>
                                </div>
                                <div class="journey-step">
                                    <div class="journey-icon">
                                        <i class="bi bi-star"></i>
                                    </div>
                                    <div class="journey-label">Loyalty Program</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-lg-4">
                            <div class="row">
                                <?php
                                $journey_stats = [
                                    'Discovery Rate' => '85%',
                                    'First Purchase' => '42%',
                                    'Repeat Rate' => '28%',
                                    'Loyalty Rate' => '15%'
                                ];
                                foreach ($journey_stats as $stage => $rate):
                                ?>
                                <div class="col-6 mb-3">
                                    <div class="text-center">
                                        <div class="stat-value"><?= $rate ?></div>
                                        <div class="stat-label"><?= $stage ?></div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Customer List -->
        <div class="row">
            <div class="col-12">
                <div class="chart-container">
                    <div class="chart-title">
                        <i class="bi bi-list"></i> Customer Details
                        <small class="text-muted ms-2">(<?= count($customers) ?> customers)</small>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-hover" id="customersTable">
                            <thead>
                                <tr>
                                    <th>Customer</th>
                                    <th>Segment</th>
                                    <th>Total Spent</th>
                                    <th>Purchases</th>
                                    <th>Engagement</th>
                                    <th>Last Activity</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($customers as $index => $customer): ?>
                                <?php 
                                $lifecycle_metric = $lifecycle_metrics[$index] ?? ['lifecycle_stage' => 'new', 'engagement_score' => 0];
                                $avatar_letter = strtoupper($customer['username'][0] ?? 'U');
                                ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="customer-avatar me-3">
                                                <?= $avatar_letter ?>
                                            </div>
                                            <div>
                                                <strong><?= htmlspecialchars($customer['username']) ?></strong>
                                                <span class="lifecycle-badge lifecycle-<?= $lifecycle_metric['lifecycle_stage'] ?>">
                                                    <?= ucfirst(str_replace('_', ' ', $lifecycle_metric['lifecycle_stage'])) ?>
                                                </span>
                                                <br>
                                                <small class="text-muted"><?= htmlspecialchars($customer['email']) ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="segment-badge segment-<?= strtolower(str_replace(' ', '-', $customer['customer_segment'])) ?>">
                                            <?= $customer['customer_segment'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <strong><?= number_format($customer['total_coins_spent']) ?> coins</strong><br>
                                        <small class="text-muted">Avg: <?= number_format($customer['avg_coins_per_purchase']) ?> per purchase</small>
                                    </td>
                                    <td>
                                        <strong><?= $customer['total_purchases'] ?></strong> total<br>
                                        <small class="text-muted"><?= $customer['codes_used'] ?> codes used</small>
                                    </td>
                                    <td>
                                        <div class="engagement-bar">
                                            <div class="engagement-fill" style="width: <?= $lifecycle_metric['engagement_score'] ?>%"></div>
                                        </div>
                                        <small class="text-muted"><?= number_format($lifecycle_metric['engagement_score']) ?>% engaged</small>
                                    </td>
                                    <td>
                                        <?= date('M j, Y', strtotime($customer['last_purchase'])) ?><br>
                                        <small class="text-muted"><?= $customer['days_since_last_purchase'] ?> days ago</small>
                                    </td>
                                    <td>
                                        <div class="customer-actions">
                                            <?php if ($lifecycle_metric['lifecycle_stage'] === 'at_risk'): ?>
                                            <button class="action-btn action-retain" onclick="retainCustomer(<?= $customer['id'] ?>)">
                                                <i class="bi bi-heart"></i> Retain
                                            </button>
                                            <?php elseif ($customer['customer_segment'] === 'High Value'): ?>
                                            <button class="action-btn action-promote" onclick="promoteCustomer(<?= $customer['id'] ?>)">
                                                <i class="bi bi-star"></i> VIP
                                            </button>
                                            <?php else: ?>
                                            <button class="action-btn action-email" onclick="emailCustomer(<?= $customer['id'] ?>)">
                                                <i class="bi bi-envelope"></i> Email
                                            </button>
                                            <?php endif; ?>
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
    </div>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.12.1/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.12.1/js/dataTables.bootstrap5.min.js"></script>
    
    <script>
        // Customer data from PHP
        const segmentData = <?= json_encode($analytics['customers']['segments']) ?>;
        const lifecycleData = <?= json_encode(array_count_values(array_column($lifecycle_metrics, 'lifecycle_stage'))) ?>;
        
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
        
        // Customer Segmentation Chart
        const segmentationCtx = document.getElementById('segmentationChart').getContext('2d');
        new Chart(segmentationCtx, {
            type: 'doughnut',
            data: {
                labels: segmentData.map(s => s.customer_segment),
                datasets: [{
                    data: segmentData.map(s => s.customer_count),
                    backgroundColor: [
                        colors.success,
                        colors.info,
                        colors.warning,
                        '#6c757d'
                    ],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const total = segmentData.reduce((sum, s) => sum + s.customer_count, 0);
                                const percentage = ((context.parsed / total) * 100).toFixed(1);
                                return `${context.label}: ${context.parsed} customers (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
        
        // Customer Lifecycle Chart
        const lifecycleCtx = document.getElementById('lifecycleChart').getContext('2d');
        new Chart(lifecycleCtx, {
            type: 'bar',
            data: {
                labels: ['New', 'Repeat', 'Loyal', 'Declining', 'At Risk'],
                datasets: [{
                    label: 'Customers',
                    data: [
                        lifecycleData.new || 0,
                        lifecycleData.repeat || 0,
                        lifecycleData.loyal || 0,
                        lifecycleData.declining || 0,
                        lifecycleData.at_risk || 0
                    ],
                    backgroundColor: [
                        colors.success + '80',
                        colors.info + '80',
                        colors.warning + '80',
                        colors.secondary + '80',
                        colors.danger + '80'
                    ],
                    borderColor: [
                        colors.success,
                        colors.info,
                        colors.warning,
                        colors.secondary,
                        colors.danger
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                },
                plugins: {
                    legend: { display: false }
                }
            }
        });
        
        // Initialize DataTable
        $(document).ready(function() {
            $('#customersTable').DataTable({
                pageLength: 25,
                order: [[2, 'desc']], // Sort by coins spent
                responsive: true,
                columnDefs: [
                    { orderable: false, targets: [6] } // Actions column
                ]
            });
        });
        
        // Customer action functions
        function emailCustomer(customerId) {
            // Implement email customer functionality
            console.log('Emailing customer:', customerId);
            alert('Email campaign feature coming soon!');
        }
        
        function promoteCustomer(customerId) {
            // Implement VIP promotion functionality
            console.log('Promoting customer to VIP:', customerId);
            alert('VIP promotion feature coming soon!');
        }
        
        function retainCustomer(customerId) {
            // Implement customer retention functionality
            console.log('Starting retention campaign for customer:', customerId);
            alert('Retention campaign feature coming soon!');
        }
        
        // Track customer analytics view
        fetch('/html/api/track-analytics.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                event: 'customer_analytics_viewed',
                data: {
                    business_id: <?= $business_id ?>,
                    segment_filter: '<?= $segment ?>',
                    period_days: <?= $days ?>,
                    total_customers: <?= count($customers) ?>
                }
            })
        }).catch(console.error);
    </script>
</body>
</html> 