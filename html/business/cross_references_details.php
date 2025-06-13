<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';

// Require business role
require_role('business');

// Get business ID using consistent method
$stmt = $pdo->prepare("SELECT b.id FROM businesses b JOIN users u ON b.id = u.business_id WHERE u.id = ?");
$stmt->execute([$_SESSION['user_id']]);
$business = $stmt->fetch();
$business_id = $business ? $business['id'] : 0;

// Fetch cross-referenced data
try {
    // Votes vs Sales correlation by date
    $stmt = $pdo->prepare("
        SELECT 
            DATE(v.created_at) as date,
            COUNT(v.id) as votes,
            COALESCE(s.sales_count, 0) as sales,
            COALESCE(s.revenue, 0) as revenue
        FROM votes v
        JOIN machines m ON v.machine_id = m.id
        LEFT JOIN (
            SELECT 
                DATE(sale_time) as date,
                COUNT(*) as sales_count,
                SUM(quantity * sale_price) as revenue
            FROM sales 
            WHERE business_id = ?
            AND sale_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY DATE(sale_time)
        ) s ON DATE(v.created_at) = s.date
        WHERE m.business_id = ?
        AND v.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY DATE(v.created_at)
        ORDER BY date DESC
        LIMIT 30
    ");
    $stmt->execute([$business_id, $business_id]);
    $votes_sales_data = $stmt->fetchAll();

    // Machine/Location performance correlation
    $stmt = $pdo->prepare("
        SELECT 
            m.name as machine_name,
            COUNT(DISTINCT v.id) as total_votes,
            COUNT(DISTINCT s.id) as total_sales,
            COALESCE(SUM(s.quantity * s.sale_price), 0) as total_revenue,
            ROUND(
                CASE 
                    WHEN COUNT(DISTINCT v.id) > 0 
                    THEN (COUNT(DISTINCT s.id) * 100.0 / COUNT(DISTINCT v.id))
                    ELSE 0 
                END, 2
            ) as conversion_rate
        FROM machines m
        LEFT JOIN votes v ON m.id = v.machine_id AND v.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        LEFT JOIN sales s ON s.business_id = m.business_id AND s.sale_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        WHERE m.business_id = ?
        GROUP BY m.id, m.name
        HAVING total_votes > 0 OR total_sales > 0
        ORDER BY total_revenue DESC, conversion_rate DESC
    ");
    $stmt->execute([$business_id]);
    $machine_performance = $stmt->fetchAll();

    // Item performance analysis - using voting_list_items since that's what sales references
    $stmt = $pdo->prepare("
        SELECT 
            vli.item_name,
            COUNT(DISTINCT v.id) as votes_received,
            COUNT(DISTINCT s.id) as times_sold,
            COALESCE(SUM(s.quantity * s.sale_price), 0) as revenue,
            ROUND(
                CASE 
                    WHEN COUNT(DISTINCT v.id) > 0 
                    THEN (COUNT(DISTINCT s.id) * 100.0 / COUNT(DISTINCT v.id))
                    ELSE 0 
                END, 2
            ) as conversion_rate
        FROM voting_list_items vli
        JOIN voting_lists vl ON vli.voting_list_id = vl.id
        LEFT JOIN items i ON vli.master_item_id = i.master_item_id
        LEFT JOIN votes v ON i.id = v.item_id AND v.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        LEFT JOIN sales s ON vli.id = s.item_id AND s.sale_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        WHERE vl.business_id = ?
        GROUP BY vli.id, vli.item_name
        HAVING votes_received > 0 OR times_sold > 0
        ORDER BY conversion_rate DESC, revenue DESC
        LIMIT 10
    ");
    $stmt->execute([$business_id]);
    $item_correlations = $stmt->fetchAll();

    // Summary statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT v.id) as total_votes,
            COUNT(DISTINCT s.id) as total_sales,
            COALESCE(SUM(s.quantity * s.sale_price), 0) as total_revenue,
            COUNT(DISTINCT m.id) as active_machines,
            ROUND(
                CASE 
                    WHEN COUNT(DISTINCT v.id) > 0 
                    THEN (COUNT(DISTINCT s.id) * 100.0 / COUNT(DISTINCT v.id))
                    ELSE 0 
                END, 1
            ) as overall_conversion
        FROM votes v
        JOIN machines m ON v.machine_id = m.id
        LEFT JOIN sales s ON s.business_id = m.business_id 
            AND DATE(v.created_at) = DATE(s.sale_time)
        WHERE m.business_id = ?
        AND v.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $stmt->execute([$business_id]);
    $summary = $stmt->fetch();

} catch (Exception $e) {
    error_log("Cross-references error: " . $e->getMessage());
    $votes_sales_data = [];
    $machine_performance = [];
    $item_correlations = [];
    $summary = ['total_votes' => 0, 'total_sales' => 0, 'total_revenue' => 0, 'active_machines' => 0, 'overall_conversion' => 0];
}

require_once __DIR__ . '/../core/includes/header.php';
?>

<style>
.business-machine-table, .business-conversion-table {
    background: rgba(255, 255, 255, 0.12) !important;
    backdrop-filter: blur(20px) !important;
    border: 1px solid rgba(255, 255, 255, 0.15) !important;
    border-radius: 12px !important;
    color: #ffffff;
}

.business-machine-table th, .business-conversion-table th {
    background: rgba(255, 255, 255, 0.1) !important;
    border: none !important;
    color: #ffffff !important;
    font-weight: 600;
}

.business-machine-table td, .business-conversion-table td {
    background: transparent !important;
    border: 1px solid rgba(255, 255, 255, 0.1) !important;
    color: #ffffff !important;
}
</style>

<div class="container py-4">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">Cross-Referenced Insights</li>
                </ol>
            </nav>
            <h1 class="mb-2">Cross-Referenced Insights</h1>
            <p class="text-muted">Correlation analysis between votes, sales, engagement, and performance metrics</p>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <h5 class="card-title text-primary">Total Votes</h5>
                    <h2 class="text-primary"><?php echo number_format($summary['total_votes']); ?></h2>
                    <small class="text-muted">Last 30 days</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <h5 class="card-title text-success">Total Sales</h5>
                    <h2 class="text-success"><?php echo number_format($summary['total_sales']); ?></h2>
                    <small class="text-muted">Last 30 days</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <h5 class="card-title text-warning">Revenue</h5>
                    <h2 class="text-warning">$<?php echo number_format($summary['total_revenue'], 2); ?></h2>
                    <small class="text-muted">Last 30 days</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <h5 class="card-title text-info">Active Machines</h5>
                    <h2 class="text-info"><?php echo number_format($summary['active_machines']); ?></h2>
                    <small class="text-muted">With activity</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row g-4 mb-4">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Votes vs Sales Correlation (30 Days)</h5>
                </div>
                <div class="card-body">
                    <canvas id="correlationChart" height="300"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Conversion Rate Overview</h5>
                </div>
                <div class="card-body">
                    <canvas id="conversionChart" height="300"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Machine Performance Table -->
    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Machine Performance Analysis</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table business-machine-table">
                            <thead>
                                <tr>
                                    <th>Machine</th>
                                    <th>Votes</th>
                                    <th>Sales</th>
                                    <th>Revenue</th>
                                    <th>Engagement</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($machine_performance as $machine): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($machine['machine_name']); ?></td>
                                    <td><?php echo number_format($machine['total_votes']); ?></td>
                                    <td><?php echo number_format($machine['total_sales']); ?></td>
                                    <td>$<?php echo number_format($machine['total_revenue'], 2); ?></td>
                                    <td>
                                        <div class="progress" style="height: 20px;">
                                            <div class="progress-bar" role="progressbar" 
                                                 style="width: <?php echo min(100, $machine['conversion_rate']); ?>%">
                                                <?php echo number_format($machine['conversion_rate'], 1); ?>%
                                            </div>
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

        <!-- Item Conversion Analysis -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Top Item Conversion Rates</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table business-conversion-table">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th>Votes</th>
                                    <th>Sales</th>
                                    <th>Conversion</th>
                                    <th>Revenue</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($item_correlations as $item): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                                    <td><?php echo number_format($item['votes_received']); ?></td>
                                    <td><?php echo number_format($item['times_sold']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $item['conversion_rate'] > 50 ? 'success' : ($item['conversion_rate'] > 25 ? 'warning' : 'secondary'); ?>">
                                            <?php echo $item['conversion_rate']; ?>%
                                        </span>
                                    </td>
                                    <td>$<?php echo number_format($item['revenue'], 2); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Insights & Recommendations -->
    <div class="row g-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">📊 Key Insights & Recommendations</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>🎯 High Conversion Items</h6>
                            <ul class="list-unstyled">
                                <?php 
                                $highConversion = array_filter($item_correlations, function($item) { 
                                    return $item['conversion_rate'] > 50; 
                                });
                                if (empty($highConversion)): ?>
                                    <li class="text-muted">No high-conversion items identified</li>
                                <?php else: ?>
                                    <?php foreach (array_slice($highConversion, 0, 3) as $item): ?>
                                        <li class="text-success">✓ <?php echo htmlspecialchars($item['item_name']); ?> (<?php echo $item['conversion_rate']; ?>%)</li>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6>⚠️ Low Conversion Items</h6>
                            <ul class="list-unstyled">
                                <?php 
                                $lowConversion = array_filter($item_correlations, function($item) { 
                                    return $item['conversion_rate'] < 25 && $item['votes_received'] > 0; 
                                });
                                if (empty($lowConversion)): ?>
                                    <li class="text-muted">All items performing well</li>
                                <?php else: ?>
                                    <?php foreach (array_slice($lowConversion, 0, 3) as $item): ?>
                                        <li class="text-warning">⚡ <?php echo htmlspecialchars($item['item_name']); ?> (<?php echo $item['conversion_rate']; ?>%)</li>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Votes vs Sales Correlation Chart
    const correlationData = <?php echo json_encode($votes_sales_data); ?>;
    
    new Chart(document.getElementById('correlationChart').getContext('2d'), {
        type: 'line',
        data: {
            labels: correlationData.map(d => new Date(d.date).toLocaleDateString()),
            datasets: [{
                label: 'Votes',
                data: correlationData.map(d => d.votes),
                borderColor: '#007bff',
                backgroundColor: 'rgba(0, 123, 255, 0.1)',
                yAxisID: 'y'
            }, {
                label: 'Sales',
                data: correlationData.map(d => d.sales),
                borderColor: '#28a745',
                backgroundColor: 'rgba(40, 167, 69, 0.1)',
                yAxisID: 'y1'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    title: { display: true, text: 'Votes' }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    title: { display: true, text: 'Sales' },
                    grid: { drawOnChartArea: false }
                }
            },
            plugins: {
                title: { display: true, text: 'Daily Votes vs Sales' }
            }
        }
    });

    // Conversion Rate Pie Chart
    const conversionData = <?php echo json_encode($item_correlations); ?>;
    const highConversion = conversionData.filter(item => item.conversion_rate > 50).length;
    const mediumConversion = conversionData.filter(item => item.conversion_rate >= 25 && item.conversion_rate <= 50).length;
    const lowConversion = conversionData.filter(item => item.conversion_rate < 25).length;

    new Chart(document.getElementById('conversionChart').getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: ['High (>50%)', 'Medium (25-50%)', 'Low (<25%)'],
            datasets: [{
                data: [highConversion, mediumConversion, lowConversion],
                backgroundColor: ['#28a745', '#ffc107', '#dc3545']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: { display: true, text: 'Conversion Rate Distribution' }
            }
        }
    });
});
</script>

<?php require_once __DIR__ . '/../core/includes/footer.php'; ?> 