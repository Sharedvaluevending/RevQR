<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/qr_coin_manager.php';
require_once __DIR__ . '/../core/business_qr_manager.php';
require_once __DIR__ . '/../core/config_manager.php';
require_once __DIR__ . '/../core/store_manager.php';

// Require admin role
require_role('admin');

// Get QR Coin Economy Overview
$economy_overview = QRCoinManager::getEconomyOverview();
$business_subscriptions = BusinessQRManager::getAllSubscriptions();
$qr_store_stats = StoreManager::getQRStoreStats();
$business_store_stats = StoreManager::getAllBusinessStoreStats();

// Calculate key metrics
$total_qr_coins_issued = $economy_overview['total_coins_issued'] ?? 0;
$total_qr_coins_spent = $economy_overview['total_coins_spent'] ?? 0;
$active_users = $economy_overview['active_users'] ?? 0;
$total_businesses = count($business_subscriptions);

// Revenue calculations
$monthly_revenue = 0;
$trial_businesses = 0;
$active_subscriptions = 0;
foreach ($business_subscriptions as $sub) {
    if ($sub['status'] === 'active') {
        $monthly_revenue += $sub['monthly_price_cents'];
        $active_subscriptions++;
    } elseif ($sub['status'] === 'trial') {
        $trial_businesses++;
    }
}

require_once __DIR__ . '/../core/includes/header.php';
?>

<div class="container py-4">
    <div class="row mb-4">
        <div class="col-12">
            <h1 class="mb-0"><i class="bi bi-speedometer2 text-primary me-2"></i>Admin Dashboard</h1>
            <p class="text-muted">QR Coin Economy & Platform Overview</p>
        </div>
    </div>



    <!-- Economy Overview Cards -->
    <div class="row g-4 mb-5">
        <div class="col-md-3">
            <div class="card h-100 shadow-sm">
                <div class="card-body text-center">
                    <div class="d-flex justify-content-center mb-3">
                        <img src="../img/qrCoin.png" alt="QR Coin" style="width: 3rem; height: 3rem;">
                    </div>
                    <h2 class="text-warning mb-0"><?php echo number_format($total_qr_coins_issued); ?></h2>
                    <p class="text-muted mb-0">Total QR Coins Issued</p>
                    <small class="text-success">
                        <i class="bi bi-arrow-up me-1"></i><?php echo number_format($total_qr_coins_issued - $total_qr_coins_spent); ?> in circulation
                    </small>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card h-100 shadow-sm">
                <div class="card-body text-center">
                    <i class="bi bi-people-fill display-4 text-primary mb-3"></i>
                    <h2 class="text-primary mb-0"><?php echo number_format($active_users); ?></h2>
                    <p class="text-muted mb-0">Active Users</p>
                    <small class="text-info">
                        <i class="bi bi-graph-up me-1"></i>Earning & spending QR coins
                    </small>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card h-100 shadow-sm">
                <div class="card-body text-center">
                    <i class="bi bi-building-fill display-4 text-success mb-3"></i>
                    <h2 class="text-success mb-0"><?php echo $active_subscriptions; ?></h2>
                    <p class="text-muted mb-0">Paying Businesses</p>
                    <small class="text-warning">
                        <i class="bi bi-clock me-1"></i><?php echo $trial_businesses; ?> on trial
                    </small>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card h-100 shadow-sm">
                <div class="card-body text-center">
                    <i class="bi bi-currency-dollar display-4 text-success mb-3"></i>
                    <h2 class="text-success mb-0">$<?php echo number_format($monthly_revenue / 100, 0); ?></h2>
                    <p class="text-muted mb-0">Monthly Revenue</p>
                    <small class="text-success">
                        <i class="bi bi-arrow-up me-1"></i>MRR from subscriptions
                    </small>
                </div>
            </div>
        </div>
    </div>

    <!-- QR Coin Economy Health -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0"><i class="bi bi-graph-up me-2"></i>QR Coin Economy Health</h5>
                </div>
                <div class="card-body">
                    <div class="row g-4">
                        <div class="col-md-4">
                            <h6>Circulation Rate</h6>
                            <?php 
                            $circulation_rate = $total_qr_coins_issued > 0 ? (($total_qr_coins_issued - $total_qr_coins_spent) / $total_qr_coins_issued) * 100 : 0;
                            ?>
                            <div class="progress mb-2" style="height: 12px;">
                                <div class="progress-bar bg-warning" role="progressbar" style="width: <?php echo $circulation_rate; ?>%"></div>
                            </div>
                            <small class="text-muted"><?php echo number_format($circulation_rate, 1); ?>% of coins still in circulation</small>
                        </div>
                        
                        <div class="col-md-4">
                            <h6>Average User Balance</h6>
                            <?php 
                            $avg_balance = $active_users > 0 ? ($total_qr_coins_issued - $total_qr_coins_spent) / $active_users : 0;
                            ?>
                            <h4 class="text-primary"><?php echo number_format($avg_balance); ?></h4>
                            <small class="text-muted">QR coins per active user</small>
                        </div>
                        
                        <div class="col-md-4">
                            <h6>Economy Status</h6>
                            <?php 
                            $economy_status = 'Healthy';
                            $status_color = 'success';
                            if ($circulation_rate > 90) {
                                $economy_status = 'High Inflation Risk';
                                $status_color = 'danger';
                            } elseif ($circulation_rate > 80) {
                                $economy_status = 'Monitor Closely';
                                $status_color = 'warning';
                            }
                            ?>
                            <span class="badge bg-<?php echo $status_color; ?> fs-6"><?php echo $economy_status; ?></span>
                            <br><small class="text-muted">Based on circulation analysis</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Business Subscriptions Overview -->
    <div class="row mb-4">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-building me-2"></i>Business Subscriptions</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Business</th>
                                    <th>Tier</th>
                                    <th>Status</th>
                                    <th>QR Coins Used</th>
                                    <th>Revenue</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($business_subscriptions, 0, 10) as $sub): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($sub['business_name'] ?? 'Business #' . $sub['business_id']); ?></strong>
                                    </td>
                                    <td>
                                        <span class="badge bg-info"><?php echo ucfirst($sub['tier']); ?></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $sub['status'] === 'active' ? 'success' : ($sub['status'] === 'trial' ? 'warning' : 'secondary'); ?>">
                                            <?php echo ucfirst($sub['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <img src="../img/qrCoin.png" alt="QR Coin" style="width: 1rem; height: 1rem;" class="me-1">
                                            <small><?php echo number_format($sub['qr_coins_used']); ?> / <?php echo number_format($sub['qr_coin_allowance']); ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <strong class="text-success">$<?php echo number_format($sub['monthly_price_cents'] / 100, 0); ?></strong>
                                        <small class="text-muted">/mo</small>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="text-center mt-3">
                        <a href="manage-businesses.php" class="btn btn-primary">
                            <i class="bi bi-list me-1"></i>View All Businesses
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="bi bi-shop me-2"></i>Store Activity</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <h6>Business Stores</h6>
                        <div class="d-flex justify-content-between">
                            <span>Total Items:</span>
                            <strong><?php echo number_format($business_store_stats['total_items'] ?? 0); ?></strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>Total Sales:</span>
                            <strong><?php echo number_format($business_store_stats['total_sales'] ?? 0); ?></strong>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <h6>QR Store</h6>
                        <div class="d-flex justify-content-between">
                            <span>Premium Items:</span>
                            <strong><?php echo number_format($qr_store_stats['total_items'] ?? 0); ?></strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>Purchases:</span>
                            <strong><?php echo number_format($qr_store_stats['total_purchases'] ?? 0); ?></strong>
                        </div>
                    </div>
                    
                    <div class="text-center">
                        <a href="store-analytics.php" class="btn btn-info btn-sm">
                            <i class="bi bi-graph-up me-1"></i>Store Analytics
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Economy Controls -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0"><i class="bi bi-gear me-2"></i>Economy Controls</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <button class="btn btn-warning btn-sm w-100" data-bs-toggle="modal" data-bs-target="#economySettingsModal">
                                <i class="bi bi-sliders me-1"></i>Adjust Earning Rates
                            </button>
                        </div>
                        <div class="col-md-3">
                            <button class="btn btn-info btn-sm w-100" data-bs-toggle="modal" data-bs-target="#storeSettingsModal">
                                <i class="bi bi-shop me-1"></i>Store Settings
                            </button>
                        </div>
                        <div class="col-md-3">
                            <button class="btn btn-success btn-sm w-100" data-bs-toggle="modal" data-bs-target="#subscriptionModal">
                                <i class="bi bi-credit-card me-1"></i>Subscription Tiers
                            </button>
                        </div>
                        <div class="col-md-3">
                            <a href="reports.php" class="btn btn-primary btn-sm w-100">
                                <i class="bi bi-file-earmark-text me-1"></i>Generate Reports
                            </a>
                        </div>
                    </div>
                    
                    <div class="alert alert-warning mt-3">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <strong>Economy Management:</strong> Changes to earning rates and pricing affect all users immediately. Monitor circulation rates after adjustments.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Economy Settings Modal -->
<div class="modal fade" id="economySettingsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-sliders me-2"></i>Economy Settings</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="economySettingsForm">
                    <div class="mb-3">
                        <label class="form-label">Vote Base Reward</label>
                        <input type="number" class="form-control" name="qr_coin_vote_base" value="<?php echo ConfigManager::get('qr_coin_vote_base', 5); ?>">
                        <small class="text-muted">QR coins earned per vote</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Spin Base Reward</label>
                        <input type="number" class="form-control" name="qr_coin_spin_base" value="<?php echo ConfigManager::get('qr_coin_spin_base', 15); ?>">
                        <small class="text-muted">QR coins earned per spin</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Daily Vote Bonus</label>
                        <input type="number" class="form-control" name="qr_coin_vote_bonus" value="<?php echo ConfigManager::get('qr_coin_vote_bonus', 25); ?>">
                        <small class="text-muted">Extra QR coins for first vote of day</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Daily Spin Bonus</label>
                        <input type="number" class="form-control" name="qr_coin_spin_bonus" value="<?php echo ConfigManager::get('qr_coin_spin_bonus', 50); ?>">
                        <small class="text-muted">Extra QR coins for first spin of day</small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning" onclick="saveEconomySettings()">Save Changes</button>
            </div>
        </div>
    </div>
</div>

<script>
function saveEconomySettings() {
    const formData = new FormData(document.getElementById('economySettingsForm'));
    
    fetch('update-economy-settings.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Economy settings updated successfully!');
            location.reload();
        } else {
            alert('Error updating settings: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error updating settings');
    });
}
</script>

<!-- Dark theme fixes for dashboard_modular - Updated with high specificity approach -->
<style>
/* High specificity table styling (same approach that fixed manage-businesses) */
.card .card-body .table-responsive .table {
    background: rgba(255, 255, 255, 0.08) !important;
    backdrop-filter: blur(10px) !important;
    border-radius: 8px !important;
    overflow: hidden !important;
}

.card .card-body .table-responsive .table thead th {
    background: rgba(255, 255, 255, 0.15) !important;
    color: #ffffff !important;
    border-bottom: 1px solid rgba(255, 255, 255, 0.2) !important;
    font-weight: 600 !important;
}

.card .card-body .table-responsive .table tbody tr {
    border-bottom: 1px solid rgba(255, 255, 255, 0.1) !important;
}

.card .card-body .table-responsive .table tbody tr:hover {
    background: rgba(255, 255, 255, 0.12) !important;
    transform: translateX(2px) !important;
    transition: all 0.2s ease !important;
}

/* Critical: Force white text with high specificity */
.card .card-body .table-responsive .table td,
.card .card-body .table-responsive .table th {
    color: #ffffff !important;
    border-color: rgba(255, 255, 255, 0.1) !important;
    padding: 12px !important;
}

/* Fix nested elements and .text-muted with ultra-specific targeting */
.card .card-body .table-responsive .table td div,
.card .card-body .table-responsive .table td small,
.card .card-body .table-responsive .table td span,
.card .card-body .table-responsive .table td strong {
    color: #ffffff !important;
}

.card .card-body .table-responsive .table .text-muted,
.card .card-body .table-responsive .table td small.text-muted {
    color: rgba(255, 255, 255, 0.75) !important;
}

/* NUCLEAR: Fix Business Subscriptions table - Force all elements visible */
.card .card-body .table-responsive .table tbody tr td strong {
    color: #ffffff !important;
    background: transparent !important;
}

/* Force badges to be visible with contrast backgrounds */
.card .card-body .table-responsive .table tbody tr td span.badge.bg-info {
    background: #0288d1 !important;
    color: #ffffff !important;
}

.card .card-body .table-responsive .table tbody tr td span.badge.bg-success {
    background: #2e7d32 !important;
    color: #ffffff !important;
}

.card .card-body .table-responsive .table tbody tr td span.badge.bg-warning {
    background: #f57c00 !important;
    color: #ffffff !important;
}

.card .card-body .table-responsive .table tbody tr td span.badge.bg-secondary {
    background: #424242 !important;
    color: #ffffff !important;
}

/* Force QR coin numbers to be white */
.card .card-body .table-responsive .table tbody tr td .d-flex.align-items-center small {
    color: #ffffff !important;
    background: transparent !important;
}

/* Force revenue text to be visible */
.card .card-body .table-responsive .table tbody tr td strong.text-success {
    color: #4caf50 !important;
    background: transparent !important;
}

.card .card-body .table-responsive .table tbody tr td small.text-muted {
    color: rgba(255, 255, 255, 0.8) !important;
    background: transparent !important;
}

/* NUCLEAR: Force ALL table content to be visible */
.card .card-body .table-responsive .table tbody tr td {
    color: #ffffff !important;
    background: transparent !important;
}

.card .card-body .table-responsive .table tbody tr td * {
    color: #ffffff !important;
    background: transparent !important;
}

/* Emergency override for any remaining invisible elements */
.card .card-body .table-responsive .table td * {
    color: inherit !important;
}

/* Fix text in cards and content */
.text-muted {
    color: rgba(255, 255, 255, 0.8) !important;
}

.card-body h6 {
    color: rgba(255, 255, 255, 0.9) !important;
}

.card-body h4 {
    color: #ffffff !important;
}

.card-body small {
    color: rgba(255, 255, 255, 0.8) !important;
}

/* Fix badge text contrast */
.badge {
    color: #ffffff !important;
}

/* Fix revenue text with proper success color */
.card .card-body .table-responsive .table .text-success strong {
    color: #4caf50 !important;
}

/* QR Coin images need proper spacing */
.d-flex.align-items-center img {
    filter: none !important;
}
</style>

<?php require_once __DIR__ . '/../core/includes/footer.php'; ?> 
<?php require_once __DIR__ . '/../core/includes/footer.php'; ?> 