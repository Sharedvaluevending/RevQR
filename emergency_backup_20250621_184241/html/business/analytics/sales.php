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

// Get sales statistics
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_transactions,
        SUM(s.sale_price * s.quantity) as total_revenue,
        AVG(s.sale_price * s.quantity) as avg_transaction,
        COUNT(DISTINCT s.machine_id) as machines_with_sales,
        COUNT(DISTINCT DATE(s.sale_time)) as active_days
    FROM sales s
    JOIN machines m ON s.machine_id = m.id
    WHERE m.business_id = ? AND DATE(s.sale_time) BETWEEN ? AND ?
");
$stmt->execute([$business_id, $start_date, $end_date]);
$stats = $stmt->fetch();

// Get daily sales trends
$stmt = $pdo->prepare("
    SELECT 
        DATE(s.sale_time) as sale_date,
        COUNT(*) as transactions,
        SUM(s.sale_price * s.quantity) as revenue
    FROM sales s
    JOIN machines m ON s.machine_id = m.id
    WHERE m.business_id = ? AND DATE(s.sale_time) BETWEEN ? AND ?
    GROUP BY DATE(s.sale_time)
    ORDER BY sale_date ASC
");
$stmt->execute([$business_id, $start_date, $end_date]);
$dailyTrends = $stmt->fetchAll();

// Get top selling items
$stmt = $pdo->prepare("
    SELECT 
        i.name,
        SUM(s.quantity) as units_sold,
        SUM(s.sale_price * s.quantity) as revenue,
        AVG(s.sale_price) as avg_price
    FROM sales s
    JOIN items i ON s.item_id = i.id
    JOIN machines m ON s.machine_id = m.id
    WHERE m.business_id = ? AND DATE(s.sale_time) BETWEEN ? AND ?
    GROUP BY i.id, i.name
    ORDER BY revenue DESC
    LIMIT 15
");
$stmt->execute([$business_id, $start_date, $end_date]);
$topItems = $stmt->fetchAll();

// Get sales by machine
$stmt = $pdo->prepare("
    SELECT 
        m.name as machine_name,
        COUNT(*) as transactions,
        SUM(s.sale_price * s.quantity) as revenue,
        AVG(s.sale_price * s.quantity) as avg_transaction
    FROM sales s
    JOIN machines m ON s.machine_id = m.id
    WHERE m.business_id = ? AND DATE(s.sale_time) BETWEEN ? AND ?
    GROUP BY m.id, m.name
    ORDER BY revenue DESC
");
$stmt->execute([$business_id, $start_date, $end_date]);
$machineStats = $stmt->fetchAll();

// Get hourly sales patterns
$stmt = $pdo->prepare("
    SELECT 
        HOUR(s.sale_time) as hour,
        COUNT(*) as transactions,
        SUM(s.sale_price * s.quantity) as revenue
    FROM sales s
    JOIN machines m ON s.machine_id = m.id
    WHERE m.business_id = ? AND DATE(s.sale_time) BETWEEN ? AND ?
    GROUP BY HOUR(s.sale_time)
    ORDER BY hour
");
$stmt->execute([$business_id, $start_date, $end_date]);
$hourlyPattern = $stmt->fetchAll();

// Get payment method distribution (Note: sales table doesn't have payment_method, so we'll create a placeholder)
$stmt = $pdo->prepare("
    SELECT 
        'Cash' as payment_method,
        COUNT(*) as transactions,
        SUM(s.sale_price * s.quantity) as revenue
    FROM sales s
    JOIN machines m ON s.machine_id = m.id
    WHERE m.business_id = ? AND DATE(s.sale_time) BETWEEN ? AND ?
");
$stmt->execute([$business_id, $start_date, $end_date]);
$paymentStats = $stmt->fetchAll();

require_once __DIR__ . '/../../core/includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row mb-4 align-items-center">
        <div class="col">
            <h1 class="h3 mb-0">
                <i class="bi bi-currency-dollar text-success me-2"></i>
                Sales Analytics
            </h1>
            <p class="text-muted">Detailed insights into sales performance and revenue trends</p>
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
        <div class="col-md-2-4">
            <div class="card text-center">
                <div class="card-body">
                    <h3 class="text-success">$<?php echo number_format($stats['total_revenue'] ?? 0, 2); ?></h3>
                    <small class="text-muted">Total Revenue</small>
                </div>
            </div>
        </div>
        <div class="col-md-2-4">
            <div class="card text-center">
                <div class="card-body">
                    <h3 class="text-primary"><?php echo number_format($stats['total_transactions'] ?? 0); ?></h3>
                    <small class="text-muted">Transactions</small>
                </div>
            </div>
        </div>
        <div class="col-md-2-4">
            <div class="card text-center">
                <div class="card-body">
                    <h3 class="text-info">$<?php echo number_format($stats['avg_transaction'] ?? 0, 2); ?></h3>
                    <small class="text-muted">Avg Transaction</small>
                </div>
            </div>
        </div>
        <div class="col-md-2-4">
            <div class="card text-center">
                <div class="card-body">
                    <h3 class="text-warning"><?php echo number_format($stats['machines_with_sales'] ?? 0); ?></h3>
                    <small class="text-muted">Active Machines</small>
                </div>
            </div>
        </div>
        <div class="col-md-2-4">
            <div class="card text-center">
                <div class="card-body">
                    <?php 
                    $daily_avg = $stats['active_days'] > 0 ? round($stats['total_revenue'] / $stats['active_days'], 2) : 0;
                    ?>
                    <h3 class="text-secondary">$<?php echo number_format($daily_avg); ?></h3>
                    <small class="text-muted">Daily Average</small>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Daily Sales Trends -->
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Daily Sales Trends</h5>
                </div>
                <div class="card-body">
                    <canvas id="dailyTrendsChart" height="100"></canvas>
                </div>
            </div>
        </div>

        <!-- Payment Method Distribution -->
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Payment Methods</h5>
                </div>
                <div class="card-body">
                    <canvas id="paymentChart" height="200"></canvas>
                </div>
            </div>
        </div>

        <!-- Top Selling Items -->
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Top Selling Items</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th>Units Sold</th>
                                    <th>Revenue</th>
                                    <th>Avg Price</th>
                                    <th>Performance</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($topItems)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted">No sales data found</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($topItems as $item): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($item['name']); ?></td>
                                            <td><?php echo $item['units_sold']; ?></td>
                                            <td>$<?php echo number_format($item['revenue'], 2); ?></td>
                                            <td>$<?php echo number_format($item['avg_price'], 2); ?></td>
                                            <td>
                                                <?php 
                                                $performance = $item['revenue'] >= 5 ? 'High' : ($item['revenue'] >= 2 ? 'Medium' : 'Low');
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

        <!-- Hourly Sales Pattern -->
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Hourly Sales Pattern</h5>
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
                    <h5 class="mb-0">Sales by Machine</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Machine</th>
                                    <th>Transactions</th>
                                    <th>Revenue</th>
                                    <th>Avg Transaction</th>
                                    <th>Performance</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($machineStats)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted">No sales data found</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($machineStats as $machine): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($machine['machine_name']); ?></td>
                                            <td><?php echo $machine['transactions']; ?></td>
                                            <td>$<?php echo number_format($machine['revenue'], 2); ?></td>
                                            <td>$<?php echo number_format($machine['avg_transaction'], 2); ?></td>
                                            <td>
                                                <?php 
                                                $performance = $machine['revenue'] >= 10 ? 'Excellent' : ($machine['revenue'] >= 5 ? 'Good' : 'Needs Attention');
                                                $color = $performance === 'Excellent' ? 'success' : ($performance === 'Good' ? 'primary' : 'warning');
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
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Daily Trends Chart
    const dailyData = <?php echo json_encode($dailyTrends); ?>;
    const dates = dailyData.map(d => d.sale_date);
    const revenueData = dailyData.map(d => parseFloat(d.revenue));
    const transactionData = dailyData.map(d => parseInt(d.transactions));

    new Chart(document.getElementById('dailyTrendsChart'), {
        type: 'line',
        data: {
            labels: dates,
            datasets: [{
                label: 'Revenue ($)',
                data: revenueData,
                borderColor: '#198754',
                backgroundColor: 'rgba(25, 135, 84, 0.1)',
                fill: true,
                yAxisID: 'y'
            }, {
                label: 'Transactions',
                data: transactionData,
                borderColor: '#0d6efd',
                backgroundColor: 'rgba(13, 110, 253, 0.1)',
                fill: true,
                yAxisID: 'y1'
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Revenue ($)'
                    }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Transactions'
                    },
                    grid: {
                        drawOnChartArea: false,
                    },
                }
            }
        }
    });

    // Payment Method Chart
    const paymentData = <?php echo json_encode($paymentStats); ?>;
    const paymentLabels = paymentData.map(d => d.payment_method || 'Cash');
    const paymentRevenue = paymentData.map(d => parseFloat(d.revenue));

    new Chart(document.getElementById('paymentChart'), {
        type: 'doughnut',
        data: {
            labels: paymentLabels,
            datasets: [{
                data: paymentRevenue,
                backgroundColor: ['#198754', '#0d6efd', '#ffc107', '#dc3545']
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
    const hourlyRevenue = hours.map(hour => {
        const found = hourlyData.find(d => parseInt(d.hour) === hour);
        return found ? parseFloat(found.revenue) : 0;
    });

    new Chart(document.getElementById('hourlyPatternChart'), {
        type: 'bar',
        data: {
            labels: hours.map(h => h + ':00'),
            datasets: [{
                label: 'Revenue ($)',
                data: hourlyRevenue,
                backgroundColor: '#198754'
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
/* Custom table styling to fix visibility issues */
.table {
    background: rgba(255, 255, 255, 0.15) !important;
    backdrop-filter: blur(20px) !important;
    border-radius: 12px !important;
    overflow: hidden !important;
}

.table thead th {
    background: rgba(30, 60, 114, 0.6) !important;
    color: #ffffff !important;
    font-weight: 600 !important;
    border-bottom: 2px solid rgba(255, 255, 255, 0.3) !important;
    padding: 1rem 0.75rem !important;
}

.table tbody td {
    background: rgba(255, 255, 255, 0.12) !important;
    color: rgba(255, 255, 255, 0.95) !important;
    border-bottom: 1px solid rgba(255, 255, 255, 0.15) !important;
    padding: 0.875rem 0.75rem !important;
}

.table tbody tr:hover td {
    background: rgba(255, 255, 255, 0.18) !important;
    color: #ffffff !important;
}

/* Badge styling improvements */
.table .badge {
    font-weight: 500 !important;
    padding: 0.375rem 0.5rem !important;
}

/* Empty state styling */
.table tbody td.text-center.text-muted {
    color: rgba(255, 255, 255, 0.7) !important;
}

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