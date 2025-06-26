<?php
/**
 * Comprehensive Machine Management Dashboard
 * Health monitoring, analytics, sales data, votes, AI metrics, and sync status
 */

require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/auth.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'business') {
    header('Location: /login.php');
    exit;
}

$business_id = $_SESSION['business_id'];
$selected_machine = $_GET['machine_id'] ?? null;

// Get business info
$stmt = $pdo->prepare("SELECT * FROM businesses WHERE id = ?");
$stmt->execute([$business_id]);
$business = $stmt->fetch(PDO::FETCH_ASSOC);

// Check Nayax integration status
$stmt = $pdo->prepare("
    SELECT api_url, is_active, last_sync_at, total_machines
    FROM business_nayax_credentials 
    WHERE business_id = ? AND is_active = 1
");
$stmt->execute([$business_id]);
$nayax_integration = $stmt->fetch(PDO::FETCH_ASSOC);

// Get all business machines with comprehensive data
$stmt = $pdo->prepare("
    SELECT 
        nm.*,
        nmi.inventory_data,
        nmi.product_count,
        nmi.last_updated as inventory_updated,
        TIMESTAMPDIFF(MINUTE, nm.last_sync_at, NOW()) as sync_minutes_ago,
        TIMESTAMPDIFF(HOUR, nmi.last_updated, NOW()) as inventory_hours_old,
        COUNT(DISTINCT bsi.id) as discount_count,
        COUNT(DISTINCT vl.id) as voting_lists_count,
        COUNT(DISTINCT nt.id) as transaction_count_30d,
        SUM(nt.amount_cents) as revenue_30d_cents,
        SUM(nt.qr_coins_awarded) as coins_awarded_30d,
        AVG(CASE WHEN nt.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAYS) THEN nt.amount_cents END) as avg_transaction_7d,
        CASE WHEN nm.device_info IS NOT NULL THEN JSON_EXTRACT(nm.device_info, '$.Name') ELSE nm.machine_name END as display_name,
        -- Health indicators
        CASE 
            WHEN nm.last_sync_at IS NULL THEN 'never_synced'
            WHEN TIMESTAMPDIFF(HOUR, nm.last_sync_at, NOW()) > 24 THEN 'stale'
            WHEN TIMESTAMPDIFF(HOUR, nm.last_sync_at, NOW()) > 6 THEN 'warning'
            ELSE 'healthy'
        END as sync_health,
        CASE 
            WHEN nmi.last_updated IS NULL THEN 'no_inventory'
            WHEN TIMESTAMPDIFF(HOUR, nmi.last_updated, NOW()) > 12 THEN 'stale'
            WHEN TIMESTAMPDIFF(HOUR, nmi.last_updated, NOW()) > 3 THEN 'warning'
            ELSE 'fresh'
        END as inventory_health
    FROM nayax_machines nm
    LEFT JOIN nayax_machine_inventory nmi ON nm.nayax_machine_id = nmi.machine_id
    LEFT JOIN business_store_items bsi ON nm.nayax_machine_id = bsi.nayax_machine_id AND bsi.is_active = 1
    LEFT JOIN voting_lists vl ON nm.platform_machine_id = vl.id
    LEFT JOIN nayax_transactions nt ON nm.nayax_machine_id = nt.nayax_machine_id 
                                    AND nt.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                                    AND nt.status = 'completed'
    WHERE nm.business_id = ?
    GROUP BY nm.id
    ORDER BY nm.machine_name
");
$stmt->execute([$business_id]);
$machines = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get detailed data for selected machine
$machine_details = null;
$machine_votes = [];
$machine_ai_insights = [];
$machine_sales_trends = [];

if ($selected_machine) {
    // Get machine details
    $machine_details = array_filter($machines, function($m) use ($selected_machine) {
        return $m['nayax_machine_id'] === $selected_machine;
    });
    $machine_details = reset($machine_details);
    
    if ($machine_details) {
        // Get voting data if linked to voting system
        if ($machine_details['platform_machine_id']) {
            $stmt = $pdo->prepare("
                SELECT v.*, u.username, vi.item_name,
                       DATE(v.created_at) as vote_date,
                       COUNT(*) OVER (PARTITION BY DATE(v.created_at)) as daily_votes
                FROM votes v
                LEFT JOIN users u ON v.user_id = u.id
                LEFT JOIN voting_items vi ON v.voting_item_id = vi.id
                WHERE v.voting_list_id = ?
                ORDER BY v.created_at DESC
                LIMIT 100
            ");
            $stmt->execute([$machine_details['platform_machine_id']]);
            $machine_votes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        // Get sales trends (last 30 days)
        $stmt = $pdo->prepare("
            SELECT 
                DATE(created_at) as sale_date,
                COUNT(*) as transaction_count,
                SUM(amount_cents) as daily_revenue_cents,
                SUM(qr_coins_awarded) as daily_coins_awarded,
                AVG(amount_cents) as avg_transaction_cents
            FROM nayax_transactions
            WHERE nayax_machine_id = ? 
            AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            AND status = 'completed'
            GROUP BY DATE(created_at)
            ORDER BY sale_date DESC
        ");
        $stmt->execute([$selected_machine]);
        $machine_sales_trends = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Generate AI insights based on data
        $machine_ai_insights = generateMachineAIInsights($machine_details, $machine_votes, $machine_sales_trends);
    }
}

// Calculate overall business metrics
$overall_stats = [
    'total_machines' => count($machines),
    'healthy_machines' => count(array_filter($machines, fn($m) => $m['sync_health'] === 'healthy')),
    'total_revenue_30d' => array_sum(array_column($machines, 'revenue_30d_cents')),
    'total_transactions_30d' => array_sum(array_column($machines, 'transaction_count_30d')),
    'total_discounts' => array_sum(array_column($machines, 'discount_count')),
    'machines_with_voting' => count(array_filter($machines, fn($m) => $m['voting_lists_count'] > 0))
];

/**
 * Generate AI insights for machine performance
 */
function generateMachineAIInsights($machine, $votes, $sales) {
    $insights = [];
    
    // Performance insights
    if ($machine['revenue_30d_cents'] > 0) {
        $daily_avg = $machine['revenue_30d_cents'] / 30;
        if ($daily_avg > 2000) { // $20/day
            $insights[] = [
                'type' => 'success',
                'title' => 'High Performance',
                'message' => 'This machine is generating $' . number_format($daily_avg/100, 2) . '/day on average - excellent performance!'
            ];
        } elseif ($daily_avg < 500) { // $5/day
            $insights[] = [
                'type' => 'warning',
                'title' => 'Low Revenue',
                'message' => 'Daily revenue is only $' . number_format($daily_avg/100, 2) . '. Consider restocking popular items or adjusting pricing.'
            ];
        }
    }
    
    // Inventory insights
    if ($machine['inventory_health'] === 'stale') {
        $insights[] = [
            'type' => 'danger',
            'title' => 'Inventory Data Stale',
            'message' => 'Inventory hasn\'t been updated in ' . $machine['inventory_hours_old'] . ' hours. Sync needed for accurate discount creation.'
        ];
    }
    
    // Voting insights
    if (!empty($votes)) {
        $recent_votes = array_filter($votes, fn($v) => strtotime($v['created_at']) > strtotime('-7 days'));
        if (count($recent_votes) > 20) {
            $insights[] = [
                'type' => 'info',
                'title' => 'High Engagement',
                'message' => count($recent_votes) . ' votes in the last 7 days shows strong customer engagement!'
            ];
        }
    }
    
    // Discount optimization
    if ($machine['discount_count'] === 0 && $machine['revenue_30d_cents'] > 1000) {
        $insights[] = [
            'type' => 'info',
            'title' => 'Discount Opportunity',
            'message' => 'This machine has good sales but no discounts. Consider adding QR coin discounts to boost customer loyalty.'
        ];
    }
    
    // Sales trend analysis
    if (count($sales) >= 7) {
        $recent_sales = array_slice($sales, 0, 7);
        $older_sales = array_slice($sales, 7, 7);
        
        $recent_avg = array_sum(array_column($recent_sales, 'daily_revenue_cents')) / count($recent_sales);
        $older_avg = array_sum(array_column($older_sales, 'daily_revenue_cents')) / count($older_sales);
        
        if ($recent_avg > $older_avg * 1.2) {
            $insights[] = [
                'type' => 'success',
                'title' => 'Revenue Trending Up',
                'message' => 'Sales have increased ' . round((($recent_avg - $older_avg) / $older_avg) * 100, 1) . '% in the last week!'
            ];
        } elseif ($recent_avg < $older_avg * 0.8) {
            $insights[] = [
                'type' => 'warning',
                'title' => 'Revenue Declining',
                'message' => 'Sales have decreased ' . round((($older_avg - $recent_avg) / $older_avg) * 100, 1) . '% in the last week. Check inventory levels.'
            ];
        }
    }
    
    return $insights;
}

include __DIR__ . '/../templates/header.php';
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1><i class="fas fa-desktop"></i> Machine Management Dashboard</h1>
                    <p class="text-muted">Monitor health, analytics, sales, and performance across all machines</p>
                </div>
                <div>
                    <a href="nayax-settings.php" class="btn btn-outline-primary me-2">
                        <i class="fas fa-cog"></i> Nayax Settings
                    </a>
                    <a href="discount-store.php" class="btn btn-primary">
                        <i class="fas fa-tag"></i> Create Discounts
                    </a>
                </div>
            </div>
            
            <!-- Business Overview Stats -->
            <div class="row mb-4">
                <div class="col-md-2">
                    <div class="card bg-primary text-white">
                        <div class="card-body text-center">
                            <h3 class="mb-0"><?= $overall_stats['total_machines'] ?></h3>
                            <small>Total Machines</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card bg-success text-white">
                        <div class="card-body text-center">
                            <h3 class="mb-0"><?= $overall_stats['healthy_machines'] ?></h3>
                            <small>Healthy Sync</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body text-center">
                            <h3 class="mb-0">$<?= number_format($overall_stats['total_revenue_30d'] / 100, 2) ?></h3>
                            <small>30-Day Revenue</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card bg-warning text-white">
                        <div class="card-body text-center">
                            <h3 class="mb-0"><?= $overall_stats['total_discounts'] ?></h3>
                            <small>Active Discounts</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-secondary text-white">
                        <div class="card-body text-center">
                            <h3 class="mb-0"><?= $overall_stats['machines_with_voting'] ?></h3>
                            <small>Voting Integration</small>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php if (!$nayax_integration): ?>
                <div class="alert alert-warning">
                    <h5><i class="fas fa-exclamation-triangle"></i> Nayax Integration Required</h5>
                    <p>Connect your Nayax account to enable comprehensive machine monitoring and real-time data sync.</p>
                    <a href="nayax-settings.php" class="btn btn-primary">Configure Integration</a>
                </div>
            <?php endif; ?>
            
            <div class="row">
                <!-- Machine List -->
                <div class="col-lg-<?= $selected_machine ? '4' : '12' ?>">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h4><i class="fas fa-list"></i> Machine Health & Status</h4>
                            <?php if ($nayax_integration): ?>
                                <form method="POST" action="nayax-settings.php" class="d-inline">
                                    <input type="hidden" name="action" value="sync_inventory">
                                    <button type="submit" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-sync"></i> Sync All
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Machine</th>
                                            <th>Health</th>
                                            <th>Revenue (30d)</th>
                                            <th>Discounts</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($machines as $machine): ?>
                                        <tr class="<?= $selected_machine === $machine['nayax_machine_id'] ? 'table-primary' : '' ?>">
                                            <td>
                                                <div>
                                                    <strong><?= htmlspecialchars($machine['display_name'] ?? $machine['machine_name']) ?></strong>
                                                    <br>
                                                    <small class="text-muted">
                                                        ID: <?= htmlspecialchars($machine['nayax_machine_id']) ?>
                                                        <?php if ($machine['location']): ?>
                                                            | <?= htmlspecialchars($machine['location']) ?>
                                                        <?php endif; ?>
                                                    </small>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="d-flex flex-column">
                                                    <!-- Sync Health -->
                                                    <span class="badge bg-<?= 
                                                        $machine['sync_health'] === 'healthy' ? 'success' : 
                                                        ($machine['sync_health'] === 'warning' ? 'warning' : 'danger') 
                                                    ?> mb-1">
                                                        <?php if ($machine['sync_health'] === 'healthy'): ?>
                                                            <i class="fas fa-check"></i> Synced
                                                        <?php elseif ($machine['sync_health'] === 'warning'): ?>
                                                            <i class="fas fa-exclamation-triangle"></i> Stale
                                                        <?php elseif ($machine['sync_health'] === 'stale'): ?>
                                                            <i class="fas fa-times"></i> Old
                                                        <?php else: ?>
                                                            <i class="fas fa-question"></i> Never
                                                        <?php endif; ?>
                                                    </span>
                                                    
                                                    <!-- Inventory Health -->
                                                    <span class="badge bg-<?= 
                                                        $machine['inventory_health'] === 'fresh' ? 'success' : 
                                                        ($machine['inventory_health'] === 'warning' ? 'warning' : 'secondary') 
                                                    ?>">
                                                        <i class="fas fa-boxes"></i> 
                                                        <?= ucfirst($machine['inventory_health']) ?>
                                                        <?php if ($machine['product_count']): ?>
                                                            (<?= $machine['product_count'] ?>)
                                                        <?php endif; ?>
                                                    </span>
                                                </div>
                                            </td>
                                            <td>
                                                <div>
                                                    <strong>$<?= number_format(($machine['revenue_30d_cents'] ?: 0) / 100, 2) ?></strong>
                                                    <?php if ($machine['transaction_count_30d']): ?>
                                                        <br><small class="text-muted"><?= $machine['transaction_count_30d'] ?> transactions</small>
                                                    <?php endif; ?>
                                                    <?php if ($machine['avg_transaction_7d']): ?>
                                                        <br><small class="text-info">Avg: $<?= number_format($machine['avg_transaction_7d'] / 100, 2) ?></small>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-primary"><?= $machine['discount_count'] ?> active</span>
                                                <?php if ($machine['voting_lists_count'] > 0): ?>
                                                    <br><span class="badge bg-info mt-1"><i class="fas fa-vote-yea"></i> Voting</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="?machine_id=<?= urlencode($machine['nayax_machine_id']) ?>" 
                                                   class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-eye"></i> Details
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <?php if (empty($machines)): ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-desktop fa-3x text-muted mb-3"></i>
                                    <p>No machines found. Connect your Nayax account to sync your machines.</p>
                                    <a href="nayax-settings.php" class="btn btn-primary">Configure Integration</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Detailed Machine View -->
                <?php if ($selected_machine && $machine_details): ?>
                <div class="col-lg-8">
                    <div class="row">
                        <!-- Machine Info Card -->
                        <div class="col-12 mb-4">
                            <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h4><i class="fas fa-info-circle"></i> <?= htmlspecialchars($machine_details['display_name']) ?></h4>
                                    <div>
                                        <a href="discount-store.php?machine_id=<?= urlencode($selected_machine) ?>" 
                                           class="btn btn-sm btn-primary">
                                            <i class="fas fa-tag"></i> Create Discounts
                                        </a>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <h6>Machine Details</h6>
                                            <ul class="list-unstyled">
                                                <li><strong>Machine ID:</strong> <?= htmlspecialchars($machine_details['nayax_machine_id']) ?></li>
                                                <li><strong>Device ID:</strong> <?= htmlspecialchars($machine_details['nayax_device_id']) ?></li>
                                                <li><strong>Location:</strong> <?= htmlspecialchars($machine_details['location'] ?: 'Not specified') ?></li>
                                                <li><strong>Status:</strong> 
                                                    <span class="badge bg-<?= $machine_details['status'] === 'active' ? 'success' : 'warning' ?>">
                                                        <?= ucfirst($machine_details['status']) ?>
                                                    </span>
                                                </li>
                                                <li><strong>Last Sync:</strong> 
                                                    <?php if ($machine_details['last_sync_at']): ?>
                                                        <?= date('M j, Y H:i', strtotime($machine_details['last_sync_at'])) ?>
                                                        <small class="text-muted">(<?= $machine_details['sync_minutes_ago'] ?> min ago)</small>
                                                    <?php else: ?>
                                                        <span class="text-muted">Never synced</span>
                                                    <?php endif; ?>
                                                </li>
                                            </ul>
                                        </div>
                                        <div class="col-md-6">
                                            <h6>Performance (30 days)</h6>
                                            <ul class="list-unstyled">
                                                <li><strong>Revenue:</strong> $<?= number_format(($machine_details['revenue_30d_cents'] ?: 0) / 100, 2) ?></li>
                                                <li><strong>Transactions:</strong> <?= $machine_details['transaction_count_30d'] ?: 0 ?></li>
                                                <li><strong>QR Coins Awarded:</strong> <?= number_format($machine_details['coins_awarded_30d'] ?: 0) ?></li>
                                                <li><strong>Active Discounts:</strong> <?= $machine_details['discount_count'] ?></li>
                                                <li><strong>Voting Integration:</strong> 
                                                    <?php if ($machine_details['voting_lists_count'] > 0): ?>
                                                        <span class="badge bg-success">Enabled</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Not linked</span>
                                                    <?php endif; ?>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- AI Insights -->
                        <?php if (!empty($machine_ai_insights)): ?>
                        <div class="col-12 mb-4">
                            <div class="card">
                                <div class="card-header">
                                    <h5><i class="fas fa-brain"></i> AI Insights & Recommendations</h5>
                                </div>
                                <div class="card-body">
                                    <?php foreach ($machine_ai_insights as $insight): ?>
                                        <div class="alert alert-<?= $insight['type'] ?> mb-3">
                                            <h6 class="alert-heading mb-1"><?= $insight['title'] ?></h6>
                                            <p class="mb-0"><?= $insight['message'] ?></p>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Sales Trends -->
                        <?php if (!empty($machine_sales_trends)): ?>
                        <div class="col-md-8 mb-4">
                            <div class="card">
                                <div class="card-header">
                                    <h5><i class="fas fa-chart-line"></i> Sales Trends (Last 30 Days)</h5>
                                </div>
                                <div class="card-body">
                                    <canvas id="salesChart" height="100"></canvas>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Voting Data -->
                        <?php if (!empty($machine_votes)): ?>
                        <div class="col-md-4 mb-4">
                            <div class="card">
                                <div class="card-header">
                                    <h5><i class="fas fa-vote-yea"></i> Recent Votes</h5>
                                </div>
                                <div class="card-body">
                                    <div class="list-group list-group-flush">
                                        <?php foreach (array_slice($machine_votes, 0, 10) as $vote): ?>
                                        <div class="list-group-item px-0 py-2">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div class="flex-grow-1">
                                                    <strong><?= htmlspecialchars($vote['item_name'] ?: 'Unknown Item') ?></strong>
                                                    <br>
                                                    <small class="text-muted">
                                                        by <?= htmlspecialchars($vote['username'] ?: 'Anonymous') ?>
                                                        on <?= date('M j', strtotime($vote['created_at'])) ?>
                                                    </small>
                                                </div>
                                                <span class="badge bg-primary"><?= $vote['daily_votes'] ?></span>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Machine Inventory -->
                        <?php if ($machine_details['inventory_data']): ?>
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5><i class="fas fa-boxes"></i> Current Inventory</h5>
                                    <small class="text-muted">
                                        Last updated: <?= $machine_details['inventory_updated'] ? date('M j, H:i', strtotime($machine_details['inventory_updated'])) : 'Never' ?>
                                    </small>
                                </div>
                                <div class="card-body">
                                    <?php 
                                    $inventory = json_decode($machine_details['inventory_data'], true);
                                    if ($inventory && is_array($inventory)):
                                    ?>
                                        <div class="table-responsive">
                                            <table class="table table-sm">
                                                <thead>
                                                    <tr>
                                                        <th>Selection</th>
                                                        <th>Product</th>
                                                        <th>Price</th>
                                                        <th>Stock</th>
                                                        <th>Status</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($inventory as $item): ?>
                                                    <tr>
                                                        <td><span class="badge bg-secondary"><?= htmlspecialchars($item['Selection'] ?? $item['selection'] ?? 'N/A') ?></span></td>
                                                        <td><?= htmlspecialchars($item['ProductName'] ?? $item['name'] ?? 'Unknown') ?></td>
                                                        <td>$<?= number_format(($item['Price'] ?? $item['price'] ?? 0) / 100, 2) ?></td>
                                                        <td>
                                                            <?php $qty = $item['Quantity'] ?? $item['quantity'] ?? 0; ?>
                                                            <span class="badge bg-<?= $qty > 5 ? 'success' : ($qty > 0 ? 'warning' : 'danger') ?>">
                                                                <?= $qty ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <?php if ($qty > 0): ?>
                                                                <span class="text-success"><i class="fas fa-check"></i> Available</span>
                                                            <?php else: ?>
                                                                <span class="text-danger"><i class="fas fa-times"></i> Empty</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php if ($qty > 0): ?>
                                                                <a href="discount-store.php?machine_id=<?= urlencode($selected_machine) ?>&item=<?= urlencode($item['Selection'] ?? '') ?>" 
                                                                   class="btn btn-xs btn-outline-primary">
                                                                    <i class="fas fa-tag"></i> Discount
                                                                </a>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                        <p class="text-muted">No inventory data available. Sync machine to get current stock levels.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js for sales trends -->
<?php if (!empty($machine_sales_trends)): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const salesData = <?= json_encode(array_reverse($machine_sales_trends)) ?>;
const ctx = document.getElementById('salesChart').getContext('2d');

new Chart(ctx, {
    type: 'line',
    data: {
        labels: salesData.map(d => new Date(d.sale_date).toLocaleDateString()),
        datasets: [{
            label: 'Daily Revenue ($)',
            data: salesData.map(d => (d.daily_revenue_cents / 100).toFixed(2)),
            borderColor: '#4a90e2',
            backgroundColor: 'rgba(74, 144, 226, 0.1)',
            fill: true,
            tension: 0.4
        }, {
            label: 'Transactions',
            data: salesData.map(d => d.transaction_count),
            borderColor: '#f39c12',
            backgroundColor: 'rgba(243, 156, 18, 0.1)',
            fill: false,
            yAxisID: 'y1'
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true,
                position: 'left'
            },
            y1: {
                type: 'linear',
                display: true,
                position: 'right',
                grid: {
                    drawOnChartArea: false,
                },
                beginAtZero: true
            }
        },
        plugins: {
            legend: {
                display: true
            }
        }
    }
});
</script>
<?php endif; ?>

<style>
.btn-xs {
    padding: 0.125rem 0.25rem;
    font-size: 0.75rem;
}

.card {
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    border: none;
}

.badge {
    font-size: 0.7rem;
}

.table-responsive {
    border-radius: 0.375rem;
}

.alert-heading {
    font-size: 1rem;
}

@media (max-width: 768px) {
    .col-lg-4, .col-lg-8 {
        flex: 0 0 100%;
        max-width: 100%;
    }
}
</style>

<?php include __DIR__ . '/../templates/footer.php'; ?> 