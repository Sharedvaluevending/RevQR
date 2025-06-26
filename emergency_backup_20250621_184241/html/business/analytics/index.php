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

// Get quick overview stats for last 7 days
$start_date = date('Y-m-d', strtotime('-7 days'));
$end_date = date('Y-m-d');

// Get voting stats
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total_votes
    FROM votes v
    JOIN machines m ON v.machine_id = m.id
    WHERE m.business_id = ? AND DATE(v.created_at) BETWEEN ? AND ?
");
$stmt->execute([$business_id, $start_date, $end_date]);
$voting_stats = $stmt->fetch();

// Get engagement stats
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total_scans
    FROM machine_engagement me
    JOIN machines m ON me.machine_id = m.id
    WHERE m.business_id = ? AND DATE(me.created_at) BETWEEN ? AND ?
");
$stmt->execute([$business_id, $start_date, $end_date]);
$engagement_stats = $stmt->fetch();

// Get sales stats
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total_transactions, SUM(s.sale_price * s.quantity) as total_revenue
    FROM sales s
    JOIN machines m ON s.machine_id = m.id
    WHERE m.business_id = ? AND DATE(s.sale_time) BETWEEN ? AND ?
");
$stmt->execute([$business_id, $start_date, $end_date]);
$sales_stats = $stmt->fetch();

// Get spin stats
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total_spins, SUM(CASE WHEN sr.prize_won != 'No Prize' AND sr.prize_won != '' THEN 1 ELSE 0 END) as rewards_won
    FROM spin_results sr
    JOIN machines m ON sr.machine_id = m.id
    WHERE m.business_id = ? AND DATE(sr.spin_time) BETWEEN ? AND ?
");
$stmt->execute([$business_id, $start_date, $end_date]);
$spin_stats = $stmt->fetch();

// Get inventory stats
$stmt = $pdo->prepare("
    SELECT 
        SUM(inv.quantity) as total_stock,
        SUM(CASE WHEN inv.quantity <= 5 THEN 1 ELSE 0 END) as low_stock_items
    FROM inventory inv
    JOIN machines m ON inv.machine_id = m.id
    WHERE m.business_id = ?
");
$stmt->execute([$business_id]);
$inventory_stats = $stmt->fetch();

require_once __DIR__ . '/../../core/includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row mb-4 align-items-center">
        <div class="col">
            <h1 class="h3 mb-0">
                <i class="bi bi-graph-up text-primary me-2"></i>
                Analytics Hub
            </h1>
            <p class="text-muted">Comprehensive insights and analytics for your vending business</p>
        </div>
        <div class="col-auto">
            <a href="../dashboard_enhanced.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-2"></i>Back to Dashboard
            </a>
        </div>
    </div>

    <!-- Quick Overview Stats -->
    <div class="row g-4 mb-5">
        <div class="col-md-3">
            <div class="card text-center border-primary">
                <div class="card-body">
                    <h3 class="text-primary"><?php echo number_format($voting_stats['total_votes'] ?? 0); ?></h3>
                    <small class="text-muted">Votes (Last 7 Days)</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center border-info">
                <div class="card-body">
                    <h3 class="text-info"><?php echo number_format($engagement_stats['total_scans'] ?? 0); ?></h3>
                    <small class="text-muted">QR Scans (Last 7 Days)</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center border-success">
                <div class="card-body">
                    <h3 class="text-success">$<?php echo number_format($sales_stats['total_revenue'] ?? 0, 2); ?></h3>
                    <small class="text-muted">Sales Revenue (Last 7 Days)</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center border-warning">
                <div class="card-body">
                    <h3 class="text-warning"><?php echo number_format($spin_stats['rewards_won'] ?? 0); ?></h3>
                    <small class="text-muted">Spin Rewards (Last 7 Days)</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Analytics Navigation Cards -->
    <div class="row g-4">
        <!-- Voting Analytics -->
        <div class="col-lg-4">
            <div class="card h-100 shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="bi bi-bar-chart-fill me-2"></i>
                        Voting Analytics
                    </h5>
                </div>
                <div class="card-body">
                    <p class="card-text">Detailed insights into voting patterns, item performance, and approval rates.</p>
                    <ul class="list-unstyled">
                        <li><i class="bi bi-check-circle text-success me-2"></i>Daily voting trends</li>
                        <li><i class="bi bi-check-circle text-success me-2"></i>Top voted items</li>
                        <li><i class="bi bi-check-circle text-success me-2"></i>Machine performance</li>
                        <li><i class="bi bi-check-circle text-success me-2"></i>Hourly patterns</li>
                    </ul>
                </div>
                <div class="card-footer">
                    <a href="voting.php" class="btn btn-primary w-100">
                        <i class="bi bi-arrow-right me-2"></i>View Voting Analytics
                    </a>
                </div>
            </div>
        </div>

        <!-- Engagement Analytics -->
        <div class="col-lg-4">
            <div class="card h-100 shadow-sm">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">
                        <i class="bi bi-qr-code-scan me-2"></i>
                        Engagement Analytics
                    </h5>
                </div>
                <div class="card-body">
                    <p class="card-text">Track QR scan patterns, user engagement, and device usage statistics.</p>
                    <ul class="list-unstyled">
                        <li><i class="bi bi-check-circle text-success me-2"></i>Daily engagement trends</li>
                        <li><i class="bi bi-check-circle text-success me-2"></i>Device type distribution</li>
                        <li><i class="bi bi-check-circle text-success me-2"></i>Top engaged users</li>
                        <li><i class="bi bi-check-circle text-success me-2"></i>Machine engagement rates</li>
                    </ul>
                </div>
                <div class="card-footer">
                    <a href="engagement.php" class="btn btn-info w-100">
                        <i class="bi bi-arrow-right me-2"></i>View Engagement Analytics
                    </a>
                </div>
            </div>
        </div>

        <!-- Sales Analytics -->
        <div class="col-lg-4">
            <div class="card h-100 shadow-sm">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">
                        <i class="bi bi-currency-dollar me-2"></i>
                        Sales Analytics
                    </h5>
                </div>
                <div class="card-body">
                    <p class="card-text">Monitor sales performance, revenue trends, and payment method preferences.</p>
                    <ul class="list-unstyled">
                        <li><i class="bi bi-check-circle text-success me-2"></i>Daily sales trends</li>
                        <li><i class="bi bi-check-circle text-success me-2"></i>Top selling items</li>
                        <li><i class="bi bi-check-circle text-success me-2"></i>Payment method analysis</li>
                        <li><i class="bi bi-check-circle text-success me-2"></i>Machine revenue comparison</li>
                    </ul>
                </div>
                <div class="card-footer">
                    <a href="sales.php" class="btn btn-success w-100">
                        <i class="bi bi-arrow-right me-2"></i>View Sales Analytics
                    </a>
                </div>
            </div>
        </div>

        <!-- Spin & Rewards Analytics -->
        <div class="col-lg-4">
            <div class="card h-100 shadow-sm">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0">
                        <i class="bi bi-arrow-repeat me-2"></i>
                        Spin & Rewards Analytics
                    </h5>
                </div>
                <div class="card-body">
                    <p class="card-text">Analyze spin wheel performance, reward distribution, and user engagement.</p>
                    <ul class="list-unstyled">
                        <li><i class="bi bi-check-circle text-success me-2"></i>Daily spin trends</li>
                        <li><i class="bi bi-check-circle text-success me-2"></i>Reward distribution</li>
                        <li><i class="bi bi-check-circle text-success me-2"></i>Top spinners</li>
                        <li><i class="bi bi-check-circle text-success me-2"></i>Success rate analysis</li>
                    </ul>
                </div>
                <div class="card-footer">
                    <a href="rewards.php" class="btn btn-warning w-100">
                        <i class="bi bi-arrow-right me-2"></i>View Rewards Analytics
                    </a>
                </div>
            </div>
        </div>

        <!-- Inventory Analytics -->
        <div class="col-lg-4">
            <div class="card h-100 shadow-sm">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0">
                        <i class="bi bi-boxes me-2"></i>
                        Inventory Analytics
                    </h5>
                </div>
                <div class="card-body">
                    <p class="card-text">Monitor stock levels, track inventory distribution, and manage restocking needs.</p>
                    <ul class="list-unstyled">
                        <li><i class="bi bi-check-circle text-success me-2"></i>Stock level distribution</li>
                        <li><i class="bi bi-check-circle text-success me-2"></i>Low stock alerts</li>
                        <li><i class="bi bi-check-circle text-success me-2"></i>Category analysis</li>
                        <li><i class="bi bi-check-circle text-success me-2"></i>Machine inventory status</li>
                    </ul>
                    <div class="mt-3">
                        <small class="text-muted">
                            <i class="bi bi-exclamation-triangle text-warning me-1"></i>
                            <?php echo $inventory_stats['low_stock_items'] ?? 0; ?> items need restocking
                        </small>
                    </div>
                </div>
                <div class="card-footer">
                    <a href="inventory.php" class="btn btn-secondary w-100">
                        <i class="bi bi-arrow-right me-2"></i>View Inventory Analytics
                    </a>
                </div>
            </div>
        </div>

        <!-- Combined Analytics -->
        <div class="col-lg-4">
            <div class="card h-100 shadow-sm">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0">
                        <i class="bi bi-graph-up-arrow me-2"></i>
                        Combined Analytics
                    </h5>
                </div>
                <div class="card-body">
                    <p class="card-text">Cross-reference data and view comprehensive business performance metrics.</p>
                    <ul class="list-unstyled">
                        <li><i class="bi bi-check-circle text-success me-2"></i>Multi-metric dashboards</li>
                        <li><i class="bi bi-check-circle text-success me-2"></i>Correlation analysis</li>
                        <li><i class="bi bi-check-circle text-success me-2"></i>Performance comparisons</li>
                        <li><i class="bi bi-check-circle text-success me-2"></i>Business insights</li>
                    </ul>
                </div>
                <div class="card-footer">
                    <a href="../dashboard_modular.php" class="btn btn-dark w-100">
                        <i class="bi bi-arrow-right me-2"></i>View Combined Analytics
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="row mt-5">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <a href="../dashboard_modular.php" class="btn btn-outline-primary w-100">
                                <i class="bi bi-speedometer2 me-2"></i>Main Dashboard
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="voting.php?days=7" class="btn btn-outline-info w-100">
                                <i class="bi bi-calendar-week me-2"></i>Weekly Report
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="sales.php?days=30" class="btn btn-outline-success w-100">
                                <i class="bi bi-calendar-month me-2"></i>Monthly Sales
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="inventory.php" class="btn btn-outline-warning w-100">
                                <i class="bi bi-exclamation-triangle me-2"></i>Stock Alerts
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../core/includes/footer.php'; ?> 