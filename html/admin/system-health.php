<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';

// Require admin role (same as other admin pages)  
require_role('admin');

// Helper function
function formatBytes($size, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $power = $size > 0 ? floor(log($size, 1024)) : 0;
    return number_format($size / pow(1024, $power), $precision) . ' ' . $units[$power];
}

// Gather system metrics
$metrics = [];

try {
    // Database metrics
    $stmt = $pdo->query("SELECT 
        COUNT(*) as total_users,
        COUNT(CASE WHEN last_login >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as active_users_24h,
        COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as new_users_7d
        FROM users WHERE status = 'active'");
    $metrics['users'] = $stmt->fetch();

    $stmt = $pdo->query("SELECT 
        COUNT(*) as total_qr_codes,
        COUNT(CASE WHEN status = 'active' THEN 1 END) as active_qr_codes,
        COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as new_qr_24h
        FROM qr_codes");
    $metrics['qr_codes'] = $stmt->fetch();

    $stmt = $pdo->query("SELECT 
        COUNT(*) as total_votes,
        COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as votes_24h,
        COUNT(CASE WHEN vote_type = 'premium' THEN 1 END) as premium_votes
        FROM votes");
    $metrics['votes'] = $stmt->fetch();

    $stmt = $pdo->query("SELECT 
        COUNT(*) as total_scans,
        COUNT(CASE WHEN scan_time >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as scans_24h,
        COUNT(DISTINCT qr_code_id) as unique_qr_scanned
        FROM qr_code_stats");
    $metrics['scans'] = $stmt->fetch();

    // QR Coin system metrics
    $stmt = $pdo->query("SELECT 
        SUM(amount) as total_coins_circulation,
        COUNT(*) as total_transactions,
        COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as transactions_24h
        FROM qr_coin_transactions WHERE status = 'completed'");
    $metrics['qr_coins'] = $stmt->fetch();

    // System performance
    $metrics['system'] = [
        'php_version' => PHP_VERSION,
        'memory_usage' => memory_get_usage(true),
        'memory_peak' => memory_get_peak_usage(true),
        'disk_free' => disk_free_space('.'),
        'disk_total' => disk_total_space('.'),
        'server_load' => sys_getloadavg()[0] ?? 0
    ];

    // Database status
    $stmt = $pdo->query("SHOW TABLE STATUS");
    $tables = $stmt->fetchAll();
    $total_size = 0;
    $total_rows = 0;
    foreach ($tables as $table) {
        $total_size += $table['Data_length'] + $table['Index_length'];
        $total_rows += $table['Rows'];
    }
    $metrics['database'] = [
        'total_tables' => count($tables),
        'total_size' => $total_size,
        'total_rows' => $total_rows
    ];

} catch (Exception $e) {
    error_log("System Health Error: " . $e->getMessage());
    $metrics = ['error' => $e->getMessage()];
}

$page_title = "System Health Dashboard";
require_once __DIR__ . '/../core/includes/header.php';
?>

<div class="container py-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="mb-0">
                        <i class="bi bi-heart-fill me-2 text-success"></i>System Health Dashboard
                    </h1>
                    <p class="text-muted">Real-time platform monitoring and performance metrics</p>
                </div>
                <div>
                    <button type="button" class="btn btn-outline-primary" onclick="refreshMetrics()">
                        <i class="bi bi-arrow-clockwise me-1"></i>Refresh
                    </button>
                    <span class="badge bg-success ms-2">
                        <i class="bi bi-circle-fill me-1" style="font-size: 0.5em;"></i>ONLINE
                    </span>
                </div>
            </div>
        </div>
    </div>

    <?php if (isset($metrics['error'])): ?>
    <div class="row">
        <div class="col-12">
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle me-2"></i>
                <strong>System Error:</strong> <?php echo htmlspecialchars($metrics['error']); ?>
            </div>
        </div>
    </div>
    <?php else: ?>

    <!-- Key Performance Indicators -->
    <div class="row g-4 mb-4">
        <div class="col-lg-3 col-md-6">
            <div class="card admin-card stat-card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <i class="bi bi-people text-primary fs-1"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <div class="fw-bold fs-4"><?php echo number_format($metrics['users']['total_users']); ?></div>
                            <div class="text-muted small">Total Users</div>
                            <div class="text-success small">
                                <i class="bi bi-arrow-up"></i> <?php echo number_format($metrics['users']['active_users_24h']); ?> active (24h)
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6">
            <div class="card admin-card stat-card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <i class="bi bi-qr-code text-info fs-1"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <div class="fw-bold fs-4"><?php echo number_format($metrics['qr_codes']['total_qr_codes']); ?></div>
                            <div class="text-muted small">QR Codes</div>
                            <div class="text-info small">
                                <i class="bi bi-check-circle"></i> <?php echo number_format($metrics['qr_codes']['active_qr_codes']); ?> active
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6">
            <div class="card admin-card stat-card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <i class="bi bi-hand-thumbs-up text-success fs-1"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <div class="fw-bold fs-4"><?php echo number_format($metrics['votes']['total_votes']); ?></div>
                            <div class="text-muted small">Total Votes</div>
                            <div class="text-success small">
                                <i class="bi bi-arrow-up"></i> <?php echo number_format($metrics['votes']['votes_24h']); ?> today
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6">
            <div class="card admin-card stat-card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <i class="bi bi-eye text-warning fs-1"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <div class="fw-bold fs-4"><?php echo number_format($metrics['scans']['total_scans']); ?></div>
                            <div class="text-muted small">QR Scans</div>
                            <div class="text-warning small">
                                <i class="bi bi-arrow-up"></i> <?php echo number_format($metrics['scans']['scans_24h']); ?> today
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- System Status Grid -->
    <div class="row g-4">
        <!-- Server Performance -->
        <div class="col-lg-6">
            <div class="card admin-card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-cpu me-2"></i>Server Performance
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <div class="d-flex justify-content-between">
                                <span>CPU Load:</span>
                                <span class="fw-bold"><?php echo number_format($metrics['system']['server_load'], 2); ?></span>
                            </div>
                            <div class="progress mt-1" style="height: 5px;">
                                <div class="progress-bar <?php echo $metrics['system']['server_load'] > 2 ? 'bg-danger' : ($metrics['system']['server_load'] > 1 ? 'bg-warning' : 'bg-success'); ?>" 
                                     style="width: <?php echo min($metrics['system']['server_load'] * 25, 100); ?>%"></div>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="d-flex justify-content-between">
                                <span>Memory:</span>
                                <span class="fw-bold"><?php echo formatBytes($metrics['system']['memory_usage']); ?></span>
                            </div>
                            <div class="progress mt-1" style="height: 5px;">
                                <div class="progress-bar bg-info" 
                                     style="width: <?php echo ($metrics['system']['memory_usage'] / $metrics['system']['memory_peak']) * 100; ?>%"></div>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="d-flex justify-content-between">
                                <span>Disk Free:</span>
                                <span class="fw-bold"><?php echo formatBytes($metrics['system']['disk_free']); ?></span>
                            </div>
                            <div class="progress mt-1" style="height: 5px;">
                                <div class="progress-bar bg-success" 
                                     style="width: <?php echo ($metrics['system']['disk_free'] / $metrics['system']['disk_total']) * 100; ?>%"></div>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="d-flex justify-content-between">
                                <span>PHP Version:</span>
                                <span class="fw-bold"><?php echo $metrics['system']['php_version']; ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Database Status -->
        <div class="col-lg-6">
            <div class="card admin-card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-database me-2"></i>Database Status
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <div class="text-center">
                                <div class="fw-bold fs-4 text-primary"><?php echo number_format($metrics['database']['total_tables']); ?></div>
                                <div class="text-muted small">Tables</div>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="text-center">
                                <div class="fw-bold fs-4 text-info"><?php echo formatBytes($metrics['database']['total_size']); ?></div>
                                <div class="text-muted small">Database Size</div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="text-center">
                                <div class="fw-bold fs-5 text-success"><?php echo number_format($metrics['database']['total_rows']); ?></div>
                                <div class="text-muted small">Total Records</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Activity Timeline -->
        <div class="col-lg-8">
            <div class="card admin-card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-activity me-2"></i>Recent Activity (24 Hours)
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-4">
                        <div class="col-md-3 text-center">
                            <div class="fw-bold fs-3 text-primary"><?php echo number_format($metrics['scans']['scans_24h']); ?></div>
                            <div class="text-muted">QR Scans</div>
                        </div>
                        <div class="col-md-3 text-center">
                            <div class="fw-bold fs-3 text-success"><?php echo number_format($metrics['votes']['votes_24h']); ?></div>
                            <div class="text-muted">Votes Cast</div>
                        </div>
                        <div class="col-md-3 text-center">
                            <div class="fw-bold fs-3 text-info"><?php echo number_format($metrics['qr_codes']['new_qr_24h']); ?></div>
                            <div class="text-muted">New QR Codes</div>
                        </div>
                        <div class="col-md-3 text-center">
                            <div class="fw-bold fs-3 text-warning"><?php echo number_format($metrics['qr_coins']['transactions_24h'] ?? 0); ?></div>
                            <div class="text-muted">Coin Transactions</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- System Health Status -->
        <div class="col-lg-4">
            <div class="card admin-card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-shield-check me-2"></i>Health Checks
                    </h5>
                </div>
                <div class="card-body">
                    <div class="list-group list-group-flush">
                        <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <span>Database Connection</span>
                            <span class="badge bg-success">OK</span>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <span>QR Code System</span>
                            <span class="badge bg-success">Active</span>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <span>Voting System</span>
                            <span class="badge bg-success">Operational</span>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <span>QR Coin Economy</span>
                            <span class="badge bg-<?php echo ($metrics['qr_coins']['total_transactions'] ?? 0) > 0 ? 'success' : 'warning'; ?>">
                                <?php echo ($metrics['qr_coins']['total_transactions'] ?? 0) > 0 ? 'Active' : 'Inactive'; ?>
                            </span>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <span>File System</span>
                            <span class="badge bg-<?php echo $metrics['system']['disk_free'] > 1000000000 ? 'success' : 'warning'; ?>">
                                <?php echo $metrics['system']['disk_free'] > 1000000000 ? 'OK' : 'Low Space'; ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php endif; ?>
</div>

<script>
function refreshMetrics() {
    location.reload();
}

// Auto-refresh every 5 minutes
setInterval(refreshMetrics, 300000);

// Update timestamp
setInterval(function() {
    const now = new Date();
    document.title = 'System Health - ' + now.toLocaleTimeString();
}, 1000);
</script>

<?php require_once __DIR__ . '/../core/includes/footer.php'; ?> 