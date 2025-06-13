<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/store_manager.php';

// Require admin role
require_role('admin');

// Get comprehensive store analytics
$qr_store_stats = StoreManager::getQRStoreStats();
$business_store_stats = StoreManager::getAllBusinessStoreStats();

// Get QR Store analytics
$total_qr_store_items = $qr_store_stats['total_items'] ?? 0;
$total_qr_sales_revenue = ($qr_store_stats['total_coins_spent'] ?? 0) * 0.01; // Assuming 1 QR coin = $0.01
$total_qr_transactions = $qr_store_stats['total_purchases'] ?? 0;
$active_qr_stores = 1;

// Get Business Store analytics - Fixed: getAllBusinessStoreStats returns overall stats, not per-business
$total_business_stores = 1; // Overall business store system
$total_business_revenue = ($business_store_stats['total_discount_value_cents'] ?? 0) / 100;
$total_business_transactions = $business_store_stats['total_sales'] ?? 0;

// Get individual business store stats for the table
try {
    $stmt = $pdo->query("
        SELECT DISTINCT 
            b.id as business_id,
            b.name as business_name,
            COUNT(bsi.id) as total_items,
            COALESCE(purchase_stats.total_sales, 0) as total_sales,
            COALESCE(purchase_stats.total_discount_value, 0) as total_discount_value,
            COALESCE(purchase_stats.total_coins_spent, 0) as total_coins_spent
        FROM businesses b
        LEFT JOIN business_store_items bsi ON b.id = bsi.business_id AND bsi.is_active = 1
        LEFT JOIN (
            SELECT 
                bsi2.business_id,
                COUNT(usp.id) as total_sales,
                COALESCE(SUM(usp.discount_amount_cents), 0) as total_discount_value,
                COALESCE(SUM(usp.qr_coins_spent), 0) as total_coins_spent
            FROM user_store_purchases usp
            JOIN business_store_items bsi2 ON usp.store_item_id = bsi2.id
            WHERE usp.status != 'cancelled'
            GROUP BY bsi2.business_id
        ) purchase_stats ON b.id = purchase_stats.business_id
        WHERE EXISTS (SELECT 1 FROM business_store_items WHERE business_id = b.id)
        GROUP BY b.id
        ORDER BY total_sales DESC
    ");
    $individual_business_stats = $stmt->fetchAll();
} catch (Exception $e) {
    $individual_business_stats = [];
}

// Get recent transactions
try {
    $stmt = $pdo->query("
        SELECT 
            'QR Store' as store_type,
            qr_coins_spent as amount,
            created_at,
            'QR Coins' as currency
        FROM user_qr_store_purchases 
        WHERE status != 'cancelled'
        ORDER BY created_at DESC 
        LIMIT 5
        
        UNION ALL
        
        SELECT 
            'Business Store' as store_type,
            qr_coins_spent as amount,
            created_at,
            'QR Coins' as currency
        FROM user_store_purchases 
        WHERE status != 'cancelled'
        ORDER BY created_at DESC 
        LIMIT 5
        
        ORDER BY created_at DESC
        LIMIT 10
    ");
    $recent_transactions = $stmt->fetchAll();
} catch (Exception $e) {
    $recent_transactions = [];
}

// Get top selling items from businesses
try {
    $stmt = $pdo->query("
        SELECT 
            mi.name as item_name,
            mi.category,
            COUNT(s.id) as sales_count,
            SUM(s.price) as total_revenue,
            AVG(s.price) as avg_price
        FROM sales s
        JOIN machine_items mi_link ON s.item_id = mi_link.item_id
        JOIN master_items mi ON mi_link.master_item_id = mi.id
        WHERE s.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY mi.id
        ORDER BY sales_count DESC
        LIMIT 10
    ");
    $top_selling_items = $stmt->fetchAll();
} catch (Exception $e) {
    $top_selling_items = [];
}

// Get daily sales trends
try {
    $stmt = $pdo->query("
        SELECT 
            DATE(created_at) as sale_date,
            COUNT(*) as transaction_count,
            SUM(price) as daily_revenue
        FROM sales 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY DATE(created_at)
        ORDER BY sale_date ASC
    ");
    $daily_trends = $stmt->fetchAll();
} catch (Exception $e) {
    $daily_trends = [];
}

require_once __DIR__ . '/../core/includes/header.php';
?>

<div class="container py-4">
    <div class="row mb-4">
        <div class="col-12">
            <h1 class="mb-0"><i class="bi bi-shop text-primary me-2"></i>Store Analytics</h1>
            <p class="text-muted">Comprehensive analytics for QR Store and Business Store systems</p>
        </div>
    </div>

    <!-- Admin Navigation -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body py-2">
                    <nav class="navbar navbar-expand-lg navbar-light p-0">
                        <button class="navbar-toggler d-lg-none" type="button" data-bs-toggle="collapse" data-bs-target="#adminNavLinks">
                            <span class="navbar-toggler-icon"></span>
                        </button>
                        <div class="collapse navbar-collapse" id="adminNavLinks">
                            <div class="d-flex flex-wrap gap-2 w-100">
                                <a href="dashboard_modular.php" class="nav-link btn btn-outline-primary btn-sm">
                                    <i class="bi bi-speedometer2 me-1"></i><span class="d-none d-sm-inline">Dashboard</span>
                                </a>
                                <a href="manage-users.php" class="nav-link btn btn-outline-info btn-sm">
                                    <i class="bi bi-people me-1"></i><span class="d-none d-sm-inline">Manage Users</span>
                                </a>
                                <a href="manage-businesses.php" class="nav-link btn btn-outline-success btn-sm">
                                    <i class="bi bi-building me-1"></i><span class="d-none d-sm-inline">Manage Businesses</span>
                                </a>
                                <a href="casino-management.php" class="nav-link btn btn-outline-danger btn-sm">
                                    <i class="bi bi-dice-5-fill me-1"></i><span class="d-none d-sm-inline">Casino Management</span>
                                </a>
                                <a href="store-analytics.php" class="nav-link btn btn-warning btn-sm">
                                    <i class="bi bi-shop me-1"></i><span class="d-none d-sm-inline">Store Analytics</span>
                                </a>
                                <a href="reports.php" class="nav-link btn btn-outline-warning btn-sm">
                                    <i class="bi bi-graph-up me-1"></i><span class="d-none d-sm-inline">Reports</span>
                                </a>
                                <a href="system-monitor.php" class="nav-link btn btn-outline-secondary btn-sm">
                                    <i class="bi bi-cpu me-1"></i><span class="d-none d-sm-inline">System Monitor</span>
                                </a>
                                <a href="settings.php" class="nav-link btn btn-outline-dark btn-sm">
                                    <i class="bi bi-gear me-1"></i><span class="d-none d-sm-inline">Settings</span>
                                </a>
                            </div>
                        </div>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    <!-- Store Overview Cards -->
    <div class="row g-4 mb-5">
        <div class="col-md-3">
            <div class="card h-100 shadow-sm bg-primary text-white">
                <div class="card-body text-center">
                    <i class="bi bi-coin display-4 mb-3"></i>
                    <h2 class="mb-0"><?php echo number_format($total_qr_store_items); ?></h2>
                    <p class="mb-0 opacity-75">QR Store Items</p>
                    <small class="opacity-75">Available for purchase</small>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card h-100 shadow-sm bg-success text-white">
                <div class="card-body text-center">
                    <i class="bi bi-building-fill display-4 mb-3"></i>
                    <h2 class="mb-0"><?php echo number_format($business_store_stats['total_items'] ?? 0); ?></h2>
                    <p class="mb-0 opacity-75">Business Store Items</p>
                    <small class="opacity-75">Across all businesses</small>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card h-100 shadow-sm bg-warning text-dark">
                <div class="card-body text-center">
                    <i class="bi bi-currency-dollar display-4 mb-3"></i>
                    <h2 class="mb-0">$<?php echo number_format($total_business_revenue, 2); ?></h2>
                    <p class="mb-0">Total Discount Value</p>
                    <small>Provided to customers</small>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card h-100 shadow-sm bg-info text-white">
                <div class="card-body text-center">
                    <i class="bi bi-graph-up display-4 mb-3"></i>
                    <h2 class="mb-0"><?php echo number_format($total_business_transactions); ?></h2>
                    <p class="mb-0 opacity-75">Total Transactions</p>
                    <small class="opacity-75">Store purchases</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Store Performance Charts -->
    <div class="row mb-4">
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-graph-up me-2"></i>Daily Sales Trends (Last 30 Days)</h5>
                </div>
                <div class="card-body">
                    <canvas id="dailySalesChart" height="120"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-pie-chart me-2"></i>Revenue Distribution</h5>
                </div>
                <div class="card-body">
                    <canvas id="revenueDistributionChart" height="240"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Top Selling Items -->
    <div class="row mb-4">
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-trophy me-2"></i>Top Selling Items (Last 30 Days)</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Item Name</th>
                                    <th>Category</th>
                                    <th>Sales Count</th>
                                    <th>Total Revenue</th>
                                    <th>Avg Price</th>
                                    <th>Performance</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($top_selling_items)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted">No sales data found</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($top_selling_items as $item): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($item['item_name']); ?></strong></td>
                                            <td><span class="badge bg-secondary"><?php echo htmlspecialchars($item['category'] ?? 'N/A'); ?></span></td>
                                            <td><?php echo $item['sales_count']; ?></td>
                                            <td>$<?php echo number_format($item['total_revenue'], 2); ?></td>
                                            <td>$<?php echo number_format($item['avg_price'], 2); ?></td>
                                            <td>
                                                <?php 
                                                $performance = $item['sales_count'] >= 10 ? 'Excellent' : ($item['sales_count'] >= 5 ? 'Good' : 'Average');
                                                $color = $performance === 'Excellent' ? 'success' : ($performance === 'Good' ? 'primary' : 'secondary');
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
        
        <!-- Recent Transactions -->
        <div class="col-lg-4">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>Recent Transactions</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_transactions)): ?>
                        <p class="text-muted text-center">No recent transactions</p>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($recent_transactions as $transaction): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                                    <div>
                                        <div class="fw-semibold"><?php echo $transaction['store_type']; ?></div>
                                        <small class="text-muted"><?php echo date('M d, H:i', strtotime($transaction['created_at'])); ?></small>
                                    </div>
                                    <span class="badge bg-primary rounded-pill">
                                        <?php echo $transaction['amount']; ?> <?php echo $transaction['currency']; ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Business Store Stats -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-building me-2"></i>Business Store Performance</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Business</th>
                                    <th>Store Items</th>
                                    <th>Total Sales</th>
                                    <th>Discount Value</th>
                                    <th>QR Coins Earned</th>
                                    <th>Performance Rating</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($individual_business_stats)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted">No business store data found</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($individual_business_stats as $business_stat): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($business_stat['business_name'] ?? 'Business #' . $business_stat['business_id']); ?></strong></td>
                                            <td><?php echo number_format($business_stat['total_items'] ?? 0); ?></td>
                                            <td><?php echo number_format($business_stat['total_sales'] ?? 0); ?></td>
                                            <td>$<?php echo number_format(($business_stat['total_discount_value'] ?? 0) / 100, 2); ?></td>
                                            <td><?php echo number_format($business_stat['total_coins_spent'] ?? 0); ?></td>
                                            <td>
                                                <?php 
                                                $total_discount = ($business_stat['total_discount_value'] ?? 0) / 100;
                                                $rating = $total_discount >= 100 ? 'Excellent' : 
                                                         ($total_discount >= 50 ? 'Good' : 'Average');
                                                $color = $rating === 'Excellent' ? 'success' : ($rating === 'Good' ? 'primary' : 'secondary');
                                                ?>
                                                <span class="badge bg-<?php echo $color; ?>"><?php echo $rating; ?></span>
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
    // Daily Sales Trends Chart
    const dailyData = <?php echo json_encode($daily_trends); ?>;
    const dates = dailyData.map(d => d.sale_date);
    const revenueData = dailyData.map(d => parseFloat(d.daily_revenue));
    const transactionData = dailyData.map(d => parseInt(d.transaction_count));

    new Chart(document.getElementById('dailySalesChart'), {
        type: 'line',
        data: {
            labels: dates,
            datasets: [{
                label: 'Daily Revenue ($)',
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
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    grid: {
                        drawOnChartArea: false,
                    },
                }
            }
        }
    });

    // Revenue Distribution Chart
    new Chart(document.getElementById('revenueDistributionChart'), {
        type: 'doughnut',
        data: {
            labels: ['Business Store Discounts', 'QR Store', 'Other'],
            datasets: [{
                data: [<?php echo $total_business_revenue; ?>, <?php echo $total_qr_sales_revenue; ?>, 0],
                backgroundColor: ['#198754', '#ffc107', '#6c757d']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false
        }
    });
});
</script>

<?php require_once __DIR__ . '/../core/includes/footer.php'; ?> 