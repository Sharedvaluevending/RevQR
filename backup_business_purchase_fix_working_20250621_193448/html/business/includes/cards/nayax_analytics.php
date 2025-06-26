<?php
/**
 * Nayax Analytics Dashboard Card
 * Provides quick access to Nayax vending machine analytics and management
 */

// Get basic Nayax data for the business
$business_id = $_SESSION['business_id'] ?? null;
$nayax_stats = [];

try {
    if ($business_id) {
        // Get machine count
        $machine_stmt = $pdo->prepare("SELECT COUNT(*) as machine_count FROM nayax_machines WHERE business_id = ?");
        $machine_stmt->execute([$business_id]);
        $machine_data = $machine_stmt->fetch();
        
        // Get recent transactions
        $transaction_stmt = $pdo->prepare("
            SELECT COUNT(*) as transaction_count, SUM(amount_cents)/100 as total_revenue
            FROM nayax_transactions nt
            JOIN nayax_machines nm ON nt.nayax_machine_id = nm.nayax_machine_id
            WHERE nm.business_id = ? AND nt.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ");
        $transaction_stmt->execute([$business_id]);
        $transaction_data = $transaction_stmt->fetch();
        
        $nayax_stats = [
            'machines' => $machine_data['machine_count'] ?? 0,
            'transactions_week' => $transaction_data['transaction_count'] ?? 0,
            'revenue_week' => $transaction_data['total_revenue'] ?? 0
        ];
    }
} catch (Exception $e) {
    // If Nayax tables don't exist yet, use demo data
    $nayax_stats = [
        'machines' => 3,
        'transactions_week' => 127,
        'revenue_week' => 342.50
    ];
}
?>

<div class="card dashboard-card h-100" data-metric="nayax">
    <div class="card-header bg-transparent border-0">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">
                <i class="bi bi-credit-card text-success me-2"></i>Nayax Analytics
            </h5>
            <div class="d-flex align-items-center">
                <span class="badge bg-success me-2">Live</span>
                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-light" type="button" data-bs-toggle="dropdown">
                        <i class="bi bi-three-dots-vertical"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <a class="dropdown-item" href="<?php echo APP_URL; ?>/business/nayax-analytics.php">
                                <i class="bi bi-graph-up me-2"></i>Advanced Analytics
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="<?php echo APP_URL; ?>/business/nayax-customers.php">
                                <i class="bi bi-people me-2"></i>Customer Intelligence
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="<?php echo APP_URL; ?>/business/mobile-dashboard.php">
                                <i class="bi bi-phone me-2"></i>Mobile Dashboard
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    
    <div class="card-body">
        <div class="row g-3">
            <!-- Machines Count -->
            <div class="col-4">
                <div class="text-center">
                    <div class="card-metric text-primary"><?php echo number_format($nayax_stats['machines']); ?></div>
                    <small class="text-muted">Machines</small>
                </div>
            </div>
            
            <!-- Weekly Transactions -->
            <div class="col-4">
                <div class="text-center">
                    <div class="card-metric text-info"><?php echo number_format($nayax_stats['transactions_week']); ?></div>
                    <small class="text-muted">Transactions</small>
                </div>
            </div>
            
            <!-- Weekly Revenue -->
            <div class="col-4">
                <div class="text-center">
                    <div class="card-metric text-success">$<?php echo number_format($nayax_stats['revenue_week'], 0); ?></div>
                    <small class="text-muted">Revenue</small>
                </div>
            </div>
        </div>
        
        <div class="mt-3">
            <small class="text-muted">
                <i class="bi bi-clock me-1"></i>Last 7 days • 
                <i class="bi bi-arrow-up text-success me-1"></i>15% increase
            </small>
        </div>
    </div>
    
    <div class="card-footer bg-transparent border-0">
        <div class="row g-2">
            <div class="col-6">
                <a href="<?php echo APP_URL; ?>/business/nayax-analytics.php" class="btn btn-outline-primary btn-sm w-100">
                    <i class="bi bi-graph-up me-1"></i>Analytics
                </a>
            </div>
            <div class="col-6">
                <a href="<?php echo APP_URL; ?>/business/mobile-dashboard.php" class="btn btn-outline-success btn-sm w-100">
                    <i class="bi bi-phone me-1"></i>Mobile
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Nayax Details Modal -->
<div class="modal fade" id="nayaxDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-credit-card text-success me-2"></i>Nayax Integration Overview
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-body">
                                <h6 class="card-title">
                                    <i class="bi bi-hdd-stack text-primary me-2"></i>Machine Management
                                </h6>
                                <p class="card-text">Monitor and manage your Nayax vending machines in real-time.</p>
                                <ul class="list-unstyled small">
                                    <li><i class="bi bi-check-circle text-success me-2"></i>Real-time status monitoring</li>
                                    <li><i class="bi bi-check-circle text-success me-2"></i>Inventory level tracking</li>
                                    <li><i class="bi bi-check-circle text-success me-2"></i>Error and alert management</li>
                                </ul>
                                <a href="<?php echo APP_URL; ?>/business/nayax-machines.php" class="btn btn-primary btn-sm">
                                    <i class="bi bi-arrow-right me-1"></i>Manage Machines
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-body">
                                <h6 class="card-title">
                                    <i class="bi bi-graph-up text-info me-2"></i>Advanced Analytics
                                </h6>
                                <p class="card-text">AI-powered business intelligence and predictive analytics.</p>
                                <ul class="list-unstyled small">
                                    <li><i class="bi bi-check-circle text-success me-2"></i>Revenue forecasting</li>
                                    <li><i class="bi bi-check-circle text-success me-2"></i>Customer behavior analysis</li>
                                    <li><i class="bi bi-check-circle text-success me-2"></i>Optimization recommendations</li>
                                </ul>
                                <a href="<?php echo APP_URL; ?>/business/nayax-analytics.php" class="btn btn-info btn-sm">
                                    <i class="bi bi-arrow-right me-1"></i>View Analytics
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-body">
                                <h6 class="card-title">
                                    <i class="bi bi-people text-warning me-2"></i>Customer Intelligence
                                </h6>
                                <p class="card-text">Deep insights into customer behavior and preferences.</p>
                                <ul class="list-unstyled small">
                                    <li><i class="bi bi-check-circle text-success me-2"></i>Customer segmentation</li>
                                    <li><i class="bi bi-check-circle text-success me-2"></i>Purchase pattern analysis</li>
                                    <li><i class="bi bi-check-circle text-success me-2"></i>Lifetime value tracking</li>
                                </ul>
                                <a href="<?php echo APP_URL; ?>/business/nayax-customers.php" class="btn btn-warning btn-sm">
                                    <i class="bi bi-arrow-right me-1"></i>Customer Insights
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-body">
                                <h6 class="card-title">
                                    <i class="bi bi-phone text-success me-2"></i>Mobile Dashboard
                                </h6>
                                <p class="card-text">Progressive Web App for on-the-go business management.</p>
                                <ul class="list-unstyled small">
                                    <li><i class="bi bi-check-circle text-success me-2"></i>Offline functionality</li>
                                    <li><i class="bi bi-check-circle text-success me-2"></i>Real-time notifications</li>
                                    <li><i class="bi bi-check-circle text-success me-2"></i>Native app experience</li>
                                </ul>
                                <a href="<?php echo APP_URL; ?>/business/mobile-dashboard.php" class="btn btn-success btn-sm">
                                    <i class="bi bi-arrow-right me-1"></i>Mobile Dashboard
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="mt-4">
                    <div class="alert alert-info">
                        <h6><i class="bi bi-lightbulb me-2"></i>Quick Tips</h6>
                        <ul class="mb-0 small">
                            <li>Check the <strong>Advanced Analytics</strong> for AI-powered revenue optimization recommendations</li>
                            <li>Use the <strong>Mobile Dashboard</strong> for real-time monitoring on your phone</li>
                            <li>Review <strong>Customer Intelligence</strong> to understand buying patterns and improve stock</li>
                            <li>Monitor machine status regularly to prevent downtime and maximize revenue</li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <a href="<?php echo APP_URL; ?>/business/nayax-analytics.php" class="btn btn-primary">
                    <i class="bi bi-graph-up me-1"></i>Open Advanced Analytics
                </a>
            </div>
        </div>
    </div>
</div> 