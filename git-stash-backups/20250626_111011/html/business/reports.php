<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';

// Require business role
require_role('business');

// Get business details
$stmt = $pdo->prepare("SELECT b.id, b.name FROM businesses b JOIN users u ON b.id = u.business_id WHERE u.id = ?");
$stmt->execute([$_SESSION['user_id']]);
$business = $stmt->fetch();
$business_id = $business ? $business['id'] : 0;
$business_name = $business ? $business['name'] : 'Your Business';

// Get date range parameters
$start_date = $_GET['start_date'] ?? date('Y-m-01'); // Default to start of current month
$end_date = $_GET['end_date'] ?? date('Y-m-d'); // Default to today
$report_type = $_GET['report_type'] ?? 'overview'; // overview, sales, campaigns, qr_codes, inventory

require_once __DIR__ . '/../core/includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row mb-4 align-items-center">
        <div class="col">
            <h1 class="h3 mb-0">
                <i class="bi bi-file-earmark-text text-primary me-2"></i>
                Business Reports
            </h1>
            <p class="text-muted">Comprehensive analytics and reporting for <?php echo htmlspecialchars($business_name); ?></p>
        </div>
        <div class="col-auto">
                    <a href="dashboard_enhanced.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-2"></i>Back to Dashboard
        </a>
        </div>
    </div>

    <!-- Report Controls -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label">Report Type</label>
                            <select name="report_type" class="form-select">
                                <option value="overview" <?php echo $report_type === 'overview' ? 'selected' : ''; ?>>Business Overview</option>
                                <option value="sales" <?php echo $report_type === 'sales' ? 'selected' : ''; ?>>Sales Performance</option>
                                <option value="campaigns" <?php echo $report_type === 'campaigns' ? 'selected' : ''; ?>>Campaign Analytics</option>
                                <option value="qr_codes" <?php echo $report_type === 'qr_codes' ? 'selected' : ''; ?>>QR Code Performance</option>
                                <option value="inventory" <?php echo $report_type === 'inventory' ? 'selected' : ''; ?>>Inventory Analysis</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Start Date</label>
                            <input type="date" name="start_date" class="form-control" value="<?php echo $start_date; ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">End Date</label>
                            <input type="date" name="end_date" class="form-control" value="<?php echo $end_date; ?>">
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-bar-chart me-1"></i>Generate Report
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Report Content Based on Type -->
    <?php if ($report_type === 'overview'): ?>
        <!-- Business Overview Report -->
        <?php
        // Get key metrics for overview
        $overview_sql = "
            SELECT 
                (SELECT COUNT(*) FROM machines WHERE business_id = ?) as total_machines,
                (SELECT COUNT(*) FROM sales s JOIN machines m ON s.machine_id = m.id 
                 WHERE m.business_id = ? AND DATE(s.sale_time) BETWEEN ? AND ?) as total_sales,
                (SELECT SUM(s.sale_price * s.quantity) FROM sales s JOIN machines m ON s.machine_id = m.id 
                 WHERE m.business_id = ? AND DATE(s.sale_time) BETWEEN ? AND ?) as total_revenue,
                (SELECT COUNT(*) FROM campaigns WHERE business_id = ? AND status = 'active') as active_campaigns,
                (SELECT COUNT(*) FROM qr_codes WHERE business_id = ?) as total_qr_codes,
                (SELECT COUNT(*) FROM qr_codes WHERE business_id = ? AND status = 'active') as active_qr_codes
        ";
        $stmt = $pdo->prepare($overview_sql);
        $stmt->execute([$business_id, $business_id, $start_date, $end_date, $business_id, $start_date, $end_date, $business_id, $business_id, $business_id]);
        $overview = $stmt->fetch();
        ?>
        
        <div class="row g-4 mb-4">
            <div class="col-md-2">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="text-primary"><?php echo number_format($overview['total_machines'] ?? 0); ?></h3>
                        <small class="text-muted">Total Machines</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="text-success">$<?php echo number_format($overview['total_revenue'] ?? 0, 2); ?></h3>
                        <small class="text-muted">Revenue</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="text-info"><?php echo number_format($overview['total_sales'] ?? 0); ?></h3>
                        <small class="text-muted">Total Sales</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="text-warning"><?php echo number_format($overview['active_campaigns'] ?? 0); ?></h3>
                        <small class="text-muted">Active Campaigns</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="text-secondary"><?php echo number_format($overview['total_qr_codes'] ?? 0); ?></h3>
                        <small class="text-muted">QR Codes</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="text-success"><?php echo number_format($overview['active_qr_codes'] ?? 0); ?></h3>
                        <small class="text-muted">Active QR Codes</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row g-3">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Quick Reports</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="analytics/sales.php" class="btn btn-outline-primary">
                                <i class="bi bi-currency-dollar me-2"></i>Detailed Sales Analytics
                            </a>
                            <a href="campaign-analytics.php" class="btn btn-outline-info">
                                <i class="bi bi-graph-up me-2"></i>Campaign Performance
                            </a>
                            <a href="view-results.php" class="btn btn-outline-success">
                                <i class="bi bi-trophy me-2"></i>Campaign Results
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Management Tools</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="stock-management.php" class="btn btn-outline-warning">
                                <i class="bi bi-boxes me-2"></i>Inventory Management
                            </a>
                            <a href="qr_manager.php" class="btn btn-outline-secondary">
                                <i class="bi bi-qr-code me-2"></i>QR Code Manager
                            </a>
                            <a href="promotions.php" class="btn btn-outline-danger">
                                <i class="bi bi-star me-2"></i>Promotions & Discounts
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    <?php elseif ($report_type === 'sales'): ?>
        <!-- Sales Performance Report -->
        <?php
        // Get daily sales trends
        $daily_sales_sql = "
            SELECT 
                DATE(s.sale_time) as sale_date,
                COUNT(*) as transactions,
                SUM(s.sale_price * s.quantity) as revenue,
                AVG(s.sale_price * s.quantity) as avg_transaction
            FROM sales s
            JOIN machines m ON s.machine_id = m.id
            WHERE m.business_id = ? AND DATE(s.sale_time) BETWEEN ? AND ?
            GROUP BY DATE(s.sale_time)
            ORDER BY sale_date ASC
        ";
        $stmt = $pdo->prepare($daily_sales_sql);
        $stmt->execute([$business_id, $start_date, $end_date]);
        $daily_sales = $stmt->fetchAll();

        // Get top selling items
        $top_items_sql = "
            SELECT 
                i.name,
                SUM(s.quantity) as units_sold,
                SUM(s.sale_price * s.quantity) as revenue
            FROM sales s
            JOIN items i ON s.item_id = i.id
            JOIN machines m ON s.machine_id = m.id
            WHERE m.business_id = ? AND DATE(s.sale_time) BETWEEN ? AND ?
            GROUP BY i.id, i.name
            ORDER BY revenue DESC
            LIMIT 10
        ";
        $stmt = $pdo->prepare($top_items_sql);
        $stmt->execute([$business_id, $start_date, $end_date]);
        $top_items = $stmt->fetchAll();
        ?>

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Daily Sales Trends</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($daily_sales)): ?>
                            <div class="text-center py-4">
                                <i class="bi bi-graph-down text-muted display-4"></i>
                                <h5 class="text-muted mt-3">No Sales Data</h5>
                                <p class="text-muted">No sales found for the selected date range.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th class="text-end">Transactions</th>
                                            <th class="text-end">Revenue</th>
                                            <th class="text-end">Avg. Transaction</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($daily_sales as $day): ?>
                                            <tr>
                                                <td><?php echo date('M j, Y', strtotime($day['sale_date'])); ?></td>
                                                <td class="text-end"><?php echo number_format($day['transactions']); ?></td>
                                                <td class="text-end">$<?php echo number_format($day['revenue'], 2); ?></td>
                                                <td class="text-end">$<?php echo number_format($day['avg_transaction'], 2); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Top Selling Items</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($top_items)): ?>
                            <div class="text-center py-4">
                                <i class="bi bi-box text-muted display-6"></i>
                                <p class="text-muted mt-2">No items sold in this period</p>
                            </div>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($top_items as $index => $item): ?>
                                    <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                                        <div>
                                            <span class="badge bg-primary me-2"><?php echo $index + 1; ?></span>
                                            <strong><?php echo htmlspecialchars($item['name']); ?></strong>
                                            <br>
                                            <small class="text-muted"><?php echo number_format($item['units_sold']); ?> units sold</small>
                                        </div>
                                        <div class="text-end">
                                            <strong class="text-success">$<?php echo number_format($item['revenue'], 2); ?></strong>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

    <?php elseif ($report_type === 'campaigns'): ?>
        <!-- Campaign Analytics Report -->
        <?php
        // Get campaign performance
        $campaigns_sql = "
            SELECT 
                c.*,
                COUNT(DISTINCT v.id) as total_votes,
                COUNT(DISTINCT v.user_id) as unique_voters
            FROM campaigns c
            LEFT JOIN votes v ON c.id = v.campaign_id
            WHERE c.business_id = ? AND c.created_at BETWEEN ? AND ?
            GROUP BY c.id
            ORDER BY c.created_at DESC
        ";
        $stmt = $pdo->prepare($campaigns_sql);
        $stmt->execute([$business_id, $start_date . ' 00:00:00', $end_date . ' 23:59:59']);
        $campaigns = $stmt->fetchAll();
        ?>

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Campaign Performance</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($campaigns)): ?>
                            <div class="text-center py-4">
                                <i class="bi bi-megaphone text-muted display-4"></i>
                                <h5 class="text-muted mt-3">No Campaigns</h5>
                                <p class="text-muted">No campaigns found for the selected date range.</p>
                                <a href="create-campaign.php" class="btn btn-primary">Create New Campaign</a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Campaign Name</th>
                                            <th>Status</th>
                                            <th>Start Date</th>
                                            <th>End Date</th>
                                            <th class="text-end">Total Votes</th>
                                            <th class="text-end">Unique Voters</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($campaigns as $campaign): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($campaign['name']); ?></strong>
                                                    <?php if ($campaign['description']): ?>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars(substr($campaign['description'], 0, 50)) . '...'; ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php 
                                                        echo $campaign['status'] === 'active' ? 'success' : 
                                                             ($campaign['status'] === 'completed' ? 'primary' : 'secondary'); 
                                                    ?>">
                                                        <?php echo ucfirst($campaign['status']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('M j, Y', strtotime($campaign['start_date'])); ?></td>
                                                <td><?php echo date('M j, Y', strtotime($campaign['end_date'])); ?></td>
                                                <td class="text-end"><?php echo number_format($campaign['total_votes']); ?></td>
                                                <td class="text-end"><?php echo number_format($campaign['unique_voters']); ?></td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="view-results.php?id=<?php echo $campaign['id']; ?>" class="btn btn-outline-primary">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
                                                        <a href="edit-campaign.php?id=<?php echo $campaign['id']; ?>" class="btn btn-outline-secondary">
                                                            <i class="bi bi-pencil"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

    <?php elseif ($report_type === 'qr_codes'): ?>
        <!-- QR Code Performance Report -->
        <?php
        // Get QR code performance
        $qr_codes_sql = "
            SELECT 
                qr.*,
                COUNT(DISTINCT qs.id) as total_scans,
                COUNT(DISTINCT DATE(qs.scanned_at)) as active_days
            FROM qr_codes qr
            LEFT JOIN qr_scans qs ON qr.id = qs.qr_code_id AND DATE(qs.scanned_at) BETWEEN ? AND ?
            WHERE qr.business_id = ?
            GROUP BY qr.id
            ORDER BY total_scans DESC
        ";
        $stmt = $pdo->prepare($qr_codes_sql);
        $stmt->execute([$start_date, $end_date, $business_id]);
        $qr_codes = $stmt->fetchAll();
        ?>

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">QR Code Performance</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($qr_codes)): ?>
                            <div class="text-center py-4">
                                <i class="bi bi-qr-code text-muted display-4"></i>
                                <h5 class="text-muted mt-3">No QR Codes</h5>
                                <p class="text-muted">No QR codes found for your business.</p>
                                <a href="qr-generator.php" class="btn btn-primary">Create QR Code</a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>QR Code</th>
                                            <th>Type</th>
                                            <th>Machine</th>
                                            <th>Status</th>
                                            <th class="text-end">Total Scans</th>
                                            <th class="text-end">Active Days</th>
                                            <th>Created</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($qr_codes as $qr): ?>
                                            <tr>
                                                <td>
                                                    <code><?php echo htmlspecialchars($qr['qr_code']); ?></code>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info"><?php echo ucfirst(str_replace('_', ' ', $qr['qr_type'])); ?></span>
                                                </td>
                                                <td><?php echo htmlspecialchars($qr['machine_name'] ?? 'N/A'); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $qr['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                                        <?php echo ucfirst($qr['status']); ?>
                                                    </span>
                                                </td>
                                                <td class="text-end"><?php echo number_format($qr['total_scans']); ?></td>
                                                <td class="text-end"><?php echo number_format($qr['active_days']); ?></td>
                                                <td><?php echo date('M j, Y', strtotime($qr['created_at'])); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

    <?php else: ?>
        <!-- Default/Inventory Report -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Report Type: <?php echo ucfirst(str_replace('_', ' ', $report_type)); ?></h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <h6 class="alert-heading">Report in Development</h6>
                            <p class="mb-0">This report type is currently being developed. Please check back soon or contact support for assistance.</p>
                        </div>
                        <div class="text-center mt-4">
                            <a href="?report_type=overview&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="btn btn-primary">
                                View Business Overview Instead
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Export Options -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h6 class="card-title">Export Options</h6>
                    <p class="text-muted">Save your reports for external analysis or record keeping.</p>
                    <div class="btn-group">
                        <button class="btn btn-outline-success" onclick="window.print();">
                            <i class="bi bi-printer me-1"></i>Print Report
                        </button>
                        <button class="btn btn-outline-primary" onclick="alert('CSV export feature coming soon!');">
                            <i class="bi bi-filetype-csv me-1"></i>Export CSV
                        </button>
                        <button class="btn btn-outline-danger" onclick="alert('PDF export feature coming soon!');">
                            <i class="bi bi-filetype-pdf me-1"></i>Export PDF
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
@media print {
    .btn, .card-header, nav, .sidebar {
        display: none !important;
    }
    .card {
        border: none !important;
        box-shadow: none !important;
    }
}
</style>

<?php require_once __DIR__ . '/../core/includes/footer.php'; ?>