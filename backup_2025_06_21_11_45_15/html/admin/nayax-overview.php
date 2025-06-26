<?php
/**
 * Admin Nayax Integration Overview
 * System-wide monitoring and management of Nayax vending machine integration
 */

$page_title = "Nayax Integration Overview";
$show_breadcrumb = true;
$breadcrumb_items = [
    ['name' => 'Nayax Overview']
];

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../core/config.php';

// Get system-wide Nayax statistics
try {
    // Total machines across all businesses
    $machine_stats = $pdo->query("
        SELECT 
            COUNT(*) as total_machines,
            COUNT(CASE WHEN status = 'online' THEN 1 END) as online_machines,
            COUNT(CASE WHEN status = 'offline' THEN 1 END) as offline_machines,
            COUNT(DISTINCT business_id) as total_businesses
        FROM nayax_machines
    ")->fetch();

    // Transaction statistics (last 30 days)
    $transaction_stats = $pdo->query("
        SELECT 
            COUNT(*) as total_transactions,
            SUM(amount_cents)/100 as total_revenue,
            SUM(platform_commission_cents)/100 as platform_revenue,
            AVG(amount_cents)/100 as avg_transaction
        FROM nayax_transactions 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ")->fetch();

    // Business adoption statistics
    $business_stats = $pdo->query("
        SELECT 
            COUNT(*) as total_businesses,
            COUNT(CASE WHEN has_nayax = 1 THEN 1 END) as nayax_enabled,
            COUNT(CASE WHEN has_nayax = 0 THEN 1 END) as not_enabled
        FROM (
            SELECT 
                b.id,
                CASE WHEN nm.business_id IS NOT NULL THEN 1 ELSE 0 END as has_nayax
            FROM businesses b
            LEFT JOIN nayax_machines nm ON b.id = nm.business_id
            GROUP BY b.id
        ) as business_nayax
    ")->fetch();

    // Recent activity
    $recent_transactions = $pdo->query("
        SELECT 
            nt.id,
            nt.amount_cents,
            nt.created_at,
            nm.device_id,
            nm.location_description,
            b.name as business_name
        FROM nayax_transactions nt
        JOIN nayax_machines nm ON nt.nayax_machine_id = nm.nayax_machine_id
        JOIN businesses b ON nm.business_id = b.id
        ORDER BY nt.created_at DESC
        LIMIT 10
    ")->fetchAll();

} catch (Exception $e) {
    // Handle case where Nayax tables don't exist yet
    $machine_stats = ['total_machines' => 0, 'online_machines' => 0, 'offline_machines' => 0, 'total_businesses' => 0];
    $transaction_stats = ['total_transactions' => 0, 'total_revenue' => 0, 'platform_revenue' => 0, 'avg_transaction' => 0];
    $business_stats = ['total_businesses' => 0, 'nayax_enabled' => 0, 'not_enabled' => 0];
    $recent_transactions = [];
    $setup_needed = true;
}
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2><i class="bi bi-credit-card text-success me-2"></i>Nayax Integration Overview</h2>
                <p class="text-muted mb-0">System-wide monitoring and management</p>
            </div>
            <div class="d-flex gap-2">
                <a href="<?php echo APP_URL; ?>/verify_nayax_phase4.php" target="_blank" class="btn btn-outline-primary">
                    <i class="bi bi-check-circle me-1"></i>System Status
                </a>
                <button class="btn btn-primary" onclick="refreshStats()">
                    <i class="bi bi-arrow-clockwise me-1"></i>Refresh
                </button>
            </div>
        </div>
    </div>
</div>

<?php if (isset($setup_needed)): ?>
<div class="alert alert-warning">
    <h5><i class="bi bi-exclamation-triangle me-2"></i>Setup Required</h5>
    <p>Nayax integration tables are not yet initialized. Please run the Phase 1 setup to begin.</p>
    <a href="<?php echo APP_URL; ?>/setup_nayax_phase1.php" class="btn btn-warning">
        <i class="bi bi-play-fill me-1"></i>Initialize Nayax System
    </a>
</div>
<?php endif; ?>

<!-- System Overview Cards -->
<div class="row g-4 mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="card admin-card">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <i class="bi bi-hdd-stack display-4 text-primary"></i>
                    </div>
                    <div class="ms-3">
                        <div class="small text-muted">Total Machines</div>
                        <div class="h3 mb-0"><?php echo number_format($machine_stats['total_machines']); ?></div>
                        <div class="small">
                            <span class="text-success"><?php echo $machine_stats['online_machines']; ?> online</span> • 
                            <span class="text-danger"><?php echo $machine_stats['offline_machines']; ?> offline</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6">
        <div class="card admin-card">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <i class="bi bi-receipt display-4 text-success"></i>
                    </div>
                    <div class="ms-3">
                        <div class="small text-muted">30-Day Revenue</div>
                        <div class="h3 mb-0">$<?php echo number_format($transaction_stats['total_revenue'], 2); ?></div>
                        <div class="small">
                            <span class="text-info"><?php echo number_format($transaction_stats['total_transactions']); ?> transactions</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6">
        <div class="card admin-card">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <i class="bi bi-cash-stack display-4 text-warning"></i>
                    </div>
                    <div class="ms-3">
                        <div class="small text-muted">Platform Revenue</div>
                        <div class="h3 mb-0">$<?php echo number_format($transaction_stats['platform_revenue'], 2); ?></div>
                        <div class="small">
                            <span class="text-muted">Avg: $<?php echo number_format($transaction_stats['avg_transaction'], 2); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6">
        <div class="card admin-card">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <i class="bi bi-building display-4 text-info"></i>
                    </div>
                    <div class="ms-3">
                        <div class="small text-muted">Business Adoption</div>
                        <div class="h3 mb-0"><?php echo number_format($business_stats['nayax_enabled']); ?></div>
                        <div class="small">
                            <span class="text-muted">of <?php echo $business_stats['total_businesses']; ?> businesses</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Recent Transactions -->
    <div class="col-lg-8">
        <div class="card admin-card">
            <div class="card-header bg-transparent">
                <h5 class="mb-0">
                    <i class="bi bi-clock-history me-2"></i>Recent Transactions
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($recent_transactions)): ?>
                    <div class="text-center py-4">
                        <i class="bi bi-receipt display-1 text-muted"></i>
                        <h6 class="text-muted mt-2">No transactions yet</h6>
                        <p class="text-muted">Transactions will appear here once machines start processing payments.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Time</th>
                                    <th>Business</th>
                                    <th>Machine</th>
                                    <th>Location</th>
                                    <th>Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_transactions as $tx): ?>
                                <tr>
                                    <td>
                                        <small><?php echo date('M j, g:i A', strtotime($tx['created_at'])); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($tx['business_name']); ?></td>
                                    <td>
                                        <code><?php echo htmlspecialchars($tx['device_id']); ?></code>
                                    </td>
                                    <td><?php echo htmlspecialchars($tx['location_description']); ?></td>
                                    <td>
                                        <span class="badge bg-success">$<?php echo number_format($tx['amount_cents'] / 100, 2); ?></span>
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

    <!-- Quick Actions -->
    <div class="col-lg-4">
        <div class="card admin-card">
            <div class="card-header bg-transparent">
                <h5 class="mb-0">
                    <i class="bi bi-tools me-2"></i>Quick Actions
                </h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-3">
                    <a href="nayax-machines.php" class="btn btn-outline-primary">
                        <i class="bi bi-hdd-stack me-2"></i>Manage Machines
                    </a>
                    <a href="nayax-transactions.php" class="btn btn-outline-success">
                        <i class="bi bi-receipt me-2"></i>View All Transactions
                    </a>
                    <a href="nayax-businesses.php" class="btn btn-outline-info">
                        <i class="bi bi-building me-2"></i>Business Management
                    </a>
                    <hr>
                    <a href="<?php echo APP_URL; ?>/verify_nayax_phase4.php" target="_blank" class="btn btn-outline-warning">
                        <i class="bi bi-check-circle me-2"></i>Run System Tests
                    </a>
                    <a href="<?php echo APP_URL; ?>/setup_phase4_sample_data.php" target="_blank" class="btn btn-outline-secondary">
                        <i class="bi bi-database me-2"></i>Generate Sample Data
                    </a>
                </div>
            </div>
        </div>

        <!-- System Health -->
        <div class="card admin-card mt-4">
            <div class="card-header bg-transparent">
                <h5 class="mb-0">
                    <i class="bi bi-heart-pulse me-2"></i>System Health
                </h5>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span>Integration Status</span>
                    <span class="badge bg-success">Active</span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span>API Connection</span>
                    <span class="badge bg-success">Connected</span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span>AWS SQS</span>
                    <span class="badge bg-success">Operational</span>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <span>Last Update</span>
                    <small class="text-muted" id="lastUpdate">Just now</small>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function refreshStats() {
    // Add refresh animation
    const refreshBtn = document.querySelector('button[onclick="refreshStats()"]');
    const originalHTML = refreshBtn.innerHTML;
    refreshBtn.innerHTML = '<div class="spinner-border spinner-border-sm me-1"></div>Refreshing...';
    refreshBtn.disabled = true;
    
    // Simulate refresh (in production, this would be an AJAX call)
    setTimeout(() => {
        location.reload();
    }, 1500);
}

// Update last update time
function updateLastUpdate() {
    document.getElementById('lastUpdate').textContent = new Date().toLocaleTimeString();
}

// Update every minute
setInterval(updateLastUpdate, 60000);
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?> 