<?php
/**
 * Unified Sales Analytics Card
 * Combines manual and nayax sales data with source breakdown
 */

// Get business ID
$stmt = $pdo->prepare("SELECT b.id FROM businesses b JOIN users u ON b.id = u.business_id WHERE u.id = ?");
$stmt->execute([$_SESSION['user_id']]);
$business = $stmt->fetch();
$business_id = $business ? $business['id'] : 0;

// Initialize data arrays
$manual_sales = ['total' => 0, 'transactions' => 0, 'avg_price' => 0];
$nayax_sales = ['total' => 0, 'transactions' => 0, 'avg_price' => 0];

// Get manual sales data (last 7 days)
try {
    $stmt = $pdo->prepare("
        SELECT 
            SUM(s.quantity * s.sale_price) as total_sales,
            COUNT(*) as transaction_count,
            AVG(s.sale_price) as avg_price
        FROM sales s
        WHERE s.business_id = ? AND s.sale_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    $stmt->execute([$business_id]);
    $manual_data = $stmt->fetch();
    
    $manual_sales = [
        'total' => $manual_data['total_sales'] ?? 0,
        'transactions' => $manual_data['transaction_count'] ?? 0,
        'avg_price' => $manual_data['avg_price'] ?? 0
    ];
} catch (Exception $e) {
    // Keep default values if table doesn't exist
}

// Get nayax sales data (last 7 days)
try {
    $stmt = $pdo->prepare("
        SELECT 
            SUM(nt.amount_cents)/100 as total_sales,
            COUNT(*) as transaction_count,
            AVG(nt.amount_cents)/100 as avg_price
        FROM nayax_transactions nt
        JOIN nayax_machines nm ON nt.nayax_machine_id = nm.nayax_machine_id
        WHERE nm.business_id = ? AND nt.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    $stmt->execute([$business_id]);
    $nayax_data = $stmt->fetch();
    
    $nayax_sales = [
        'total' => $nayax_data['total_sales'] ?? 0,
        'transactions' => $nayax_data['transaction_count'] ?? 0,
        'avg_price' => $nayax_data['avg_price'] ?? 0
    ];
} catch (Exception $e) {
    // Keep default values if table doesn't exist
}

// Calculate combined totals
$total_sales = $manual_sales['total'] + $nayax_sales['total'];
$total_transactions = $manual_sales['transactions'] + $nayax_sales['transactions'];
$combined_avg_price = $total_transactions > 0 ? $total_sales / $total_transactions : 0;

// Calculate growth (previous week combined)
$previous_total = 0;
try {
    // Manual previous week
    $stmt = $pdo->prepare("
        SELECT SUM(s.quantity * s.sale_price) as previous_sales
        FROM sales s
        WHERE s.business_id = ? 
        AND s.sale_time >= DATE_SUB(NOW(), INTERVAL 14 DAY) 
        AND s.sale_time < DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    $stmt->execute([$business_id]);
    $manual_previous = $stmt->fetch()['previous_sales'] ?? 0;
    
    // Nayax previous week
    $stmt = $pdo->prepare("
        SELECT SUM(nt.amount_cents)/100 as previous_sales
        FROM nayax_transactions nt
        JOIN nayax_machines nm ON nt.nayax_machine_id = nm.nayax_machine_id
        WHERE nm.business_id = ? 
        AND nt.created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY) 
        AND nt.created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    $stmt->execute([$business_id]);
    $nayax_previous = $stmt->fetch()['previous_sales'] ?? 0;
    
    $previous_total = $manual_previous + $nayax_previous;
} catch (Exception $e) {
    // Keep default if tables don't exist
}

$growth_percent = $previous_total > 0 ? (($total_sales - $previous_total) / $previous_total) * 100 : 0;

// Calculate system contributions
$manual_percentage = $total_sales > 0 ? ($manual_sales['total'] / $total_sales) * 100 : 0;
$nayax_percentage = $total_sales > 0 ? ($nayax_sales['total'] / $total_sales) * 100 : 0;
?>

<div class="card dashboard-card h-100" data-metric="unified-sales">
    <div class="card-header bg-transparent border-0">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">
                <i class="bi bi-pie-chart text-warning me-2"></i>Unified Sales
            </h5>
            <div class="d-flex align-items-center gap-1">
                <?php if ($manual_sales['total'] > 0): ?>
                    <span class="badge bg-primary" title="Manual System Contribution">M</span>
                <?php endif; ?>
                <?php if ($nayax_sales['total'] > 0): ?>
                    <span class="badge bg-success" title="Nayax System Contribution">N</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="card-body">
        <div class="text-center mb-3">
            <div class="card-metric">$<?php echo number_format($total_sales, 2); ?></div>
            <div class="small text-muted">Combined 7-day sales</div>
        </div>
        
        <div class="row text-center mb-3">
            <div class="col-4">
                <div class="small text-muted">Transactions</div>
                <div class="fw-bold text-info"><?php echo $total_transactions; ?></div>
            </div>
            <div class="col-4">
                <div class="small text-muted">Avg Sale</div>
                <div class="fw-bold text-warning">$<?php echo number_format($combined_avg_price, 2); ?></div>
            </div>
            <div class="col-4">
                <div class="small text-muted">Systems</div>
                <div class="fw-bold text-primary">
                    <?php 
                    $active_systems = 0;
                    if ($manual_sales['total'] > 0) $active_systems++;
                    if ($nayax_sales['total'] > 0) $active_systems++;
                    echo $active_systems;
                    ?>
                </div>
            </div>
        </div>
        
        <!-- System Breakdown -->
        <?php if ($total_sales > 0): ?>
        <div class="mb-3">
            <div class="d-flex justify-content-between small text-muted mb-1">
                <span>Manual vs Nayax</span>
                <span>$<?php echo number_format($total_sales, 0); ?></span>
            </div>
            <div class="progress" style="height: 8px;">
                <?php if ($manual_percentage > 0): ?>
                    <div class="progress-bar bg-primary" role="progressbar" style="width: <?php echo $manual_percentage; ?>%" title="Manual: $<?php echo number_format($manual_sales['total'], 2); ?>"></div>
                <?php endif; ?>
                <?php if ($nayax_percentage > 0): ?>
                    <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $nayax_percentage; ?>%" title="Nayax: $<?php echo number_format($nayax_sales['total'], 2); ?>"></div>
                <?php endif; ?>
            </div>
            <div class="d-flex justify-content-between small text-muted mt-1">
                <span><span class="badge bg-primary me-1"></span>Manual: $<?php echo number_format($manual_sales['total'], 0); ?></span>
                <span>Nayax: $<?php echo number_format($nayax_sales['total'], 0); ?><span class="badge bg-success ms-1"></span></span>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($total_sales > 0): ?>
        <div class="mt-3">
            <canvas id="unifiedSalesChart" height="50"></canvas>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="card-footer bg-transparent border-0">
        <div class="d-flex gap-2">
            <button class="btn btn-outline-warning btn-sm flex-fill" data-bs-toggle="modal" data-bs-target="#unifiedSalesModal">
                <i class="bi bi-bar-chart me-1"></i>Details
            </button>
            <a href="<?php echo APP_URL; ?>/business/analytics/unified-sales.php" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-graph-up me-1"></i>Analytics
            </a>
        </div>
    </div>
</div>

<!-- Unified Sales Modal -->
<div class="modal fade" id="unifiedSalesModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-pie-chart text-warning me-2"></i>Unified Sales Analytics (Last 7 Days)
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <ul class="nav nav-tabs" id="salesTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="combined-tab" data-bs-toggle="tab" data-bs-target="#combined" type="button" role="tab">
                            <i class="bi bi-pie-chart me-1"></i>Combined View
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="manual-tab" data-bs-toggle="tab" data-bs-target="#manual" type="button" role="tab">
                            <i class="bi bi-person me-1"></i>Manual System
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="nayax-tab" data-bs-toggle="tab" data-bs-target="#nayax" type="button" role="tab">
                            <i class="bi bi-credit-card me-1"></i>Nayax System
                        </button>
                    </li>
                </ul>
                
                <div class="tab-content mt-3" id="salesTabsContent">
                    <!-- Combined View -->
                    <div class="tab-pane fade show active" id="combined" role="tabpanel">
                        <div class="row g-4">
                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-body text-center">
                                        <h3 class="text-warning">$<?php echo number_format($total_sales, 2); ?></h3>
                                        <p class="text-muted mb-0">Total Sales</p>
                                        <small class="text-success">
                                            <i class="bi bi-arrow-up"></i>
                                            <?php echo number_format($growth_percent, 1); ?>% vs last week
                                        </small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-body text-center">
                                        <h3 class="text-info"><?php echo $total_transactions; ?></h3>
                                        <p class="text-muted mb-0">Total Transactions</p>
                                        <small class="text-muted">
                                            Manual: <?php echo $manual_sales['transactions']; ?> | 
                                            Nayax: <?php echo $nayax_sales['transactions']; ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-body text-center">
                                        <h3 class="text-primary">$<?php echo number_format($combined_avg_price, 2); ?></h3>
                                        <p class="text-muted mb-0">Average Sale</p>
                                        <small class="text-muted">Combined average</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- System Comparison Chart -->
                        <div class="mt-4">
                            <h6>System Performance Comparison</h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <canvas id="systemComparisonChart" height="200"></canvas>
                                </div>
                                <div class="col-md-6">
                                    <div class="d-flex justify-content-center align-items-center h-100">
                                        <div class="text-center">
                                            <div class="mb-3">
                                                <span class="badge bg-primary me-2">Manual</span>
                                                <strong>$<?php echo number_format($manual_sales['total'], 2); ?></strong>
                                                <small class="text-muted d-block"><?php echo number_format($manual_percentage, 1); ?>% of total</small>
                                            </div>
                                            <div class="mb-3">
                                                <span class="badge bg-success me-2">Nayax</span>
                                                <strong>$<?php echo number_format($nayax_sales['total'], 2); ?></strong>
                                                <small class="text-muted d-block"><?php echo number_format($nayax_percentage, 1); ?>% of total</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Manual System Tab -->
                    <div class="tab-pane fade" id="manual" role="tabpanel">
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            <strong>Manual System Sales</strong> - Sales recorded through the manual tracking system
                        </div>
                        <div class="row g-4">
                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-body text-center">
                                        <h3 class="text-primary">$<?php echo number_format($manual_sales['total'], 2); ?></h3>
                                        <p class="text-muted mb-0">Manual Sales</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-body text-center">
                                        <h3 class="text-info"><?php echo $manual_sales['transactions']; ?></h3>
                                        <p class="text-muted mb-0">Transactions</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-body text-center">
                                        <h3 class="text-warning">$<?php echo number_format($manual_sales['avg_price'], 2); ?></h3>
                                        <p class="text-muted mb-0">Average Sale</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Nayax System Tab -->
                    <div class="tab-pane fade" id="nayax" role="tabpanel">
                        <div class="alert alert-success">
                            <i class="bi bi-wifi me-2"></i>
                            <strong>Nayax System Sales</strong> - Automated sales from connected vending machines
                        </div>
                        <div class="row g-4">
                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-body text-center">
                                        <h3 class="text-success">$<?php echo number_format($nayax_sales['total'], 2); ?></h3>
                                        <p class="text-muted mb-0">Nayax Sales</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-body text-center">
                                        <h3 class="text-info"><?php echo $nayax_sales['transactions']; ?></h3>
                                        <p class="text-muted mb-0">Transactions</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-body text-center">
                                        <h3 class="text-warning">$<?php echo number_format($nayax_sales['avg_price'], 2); ?></h3>
                                        <p class="text-muted mb-0">Average Sale</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <a href="<?php echo APP_URL; ?>/business/analytics/sales.php" class="btn btn-primary">
                    <i class="bi bi-graph-up me-1"></i>Manual Analytics
                </a>
                <a href="<?php echo APP_URL; ?>/business/nayax-analytics.php" class="btn btn-success">
                    <i class="bi bi-credit-card me-1"></i>Nayax Analytics
                </a>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Main card chart
    const chartCanvas = document.getElementById('unifiedSalesChart');
    if (window.Chart && chartCanvas && <?php echo $total_sales; ?> > 0) {
        try {
            new Chart(chartCanvas.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: ['Manual', 'Nayax'],
                    datasets: [{
                        data: [<?php echo $manual_sales['total']; ?>, <?php echo $nayax_sales['total']; ?>],
                        backgroundColor: ['#0d6efd', '#198754'],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
        } catch (error) {
            console.log('Chart initialization failed:', error);
        }
    }
    
    // Modal comparison chart
    const comparisonCanvas = document.getElementById('systemComparisonChart');
    if (window.Chart && comparisonCanvas) {
        try {
            new Chart(comparisonCanvas.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: ['Sales Revenue', 'Transactions', 'Avg Sale'],
                    datasets: [
                        {
                            label: 'Manual',
                            data: [<?php echo $manual_sales['total']; ?>, <?php echo $manual_sales['transactions']; ?>, <?php echo $manual_sales['avg_price']; ?>],
                            backgroundColor: '#0d6efd'
                        },
                        {
                            label: 'Nayax',
                            data: [<?php echo $nayax_sales['total']; ?>, <?php echo $nayax_sales['transactions']; ?>, <?php echo $nayax_sales['avg_price']; ?>],
                            backgroundColor: '#198754'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        } catch (error) {
            console.log('Comparison chart failed:', error);
        }
    }
});
</script> 