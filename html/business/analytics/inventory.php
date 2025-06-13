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

// Get inventory statistics (using a default threshold of 5 for low stock)
$low_stock_threshold = 5;
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_items,
        SUM(inv.quantity) as total_stock,
        SUM(CASE WHEN inv.quantity <= ? THEN 1 ELSE 0 END) as low_stock_items,
        SUM(CASE WHEN inv.quantity = 0 THEN 1 ELSE 0 END) as out_of_stock_items,
        COUNT(DISTINCT inv.machine_id) as machines_with_inventory
    FROM inventory inv
    JOIN machines m ON inv.machine_id = m.id
    WHERE m.business_id = ?
");
$stmt->execute([$low_stock_threshold, $business_id]);
$stats = $stmt->fetch();

// Get inventory by machine
$stmt = $pdo->prepare("
    SELECT 
        m.name as machine_name,
        COUNT(*) as item_count,
        SUM(inv.quantity) as total_stock,
        SUM(CASE WHEN inv.quantity <= ? THEN 1 ELSE 0 END) as low_stock_count,
        SUM(CASE WHEN inv.quantity = 0 THEN 1 ELSE 0 END) as out_of_stock_count,
        AVG(inv.quantity) as avg_stock_per_item
    FROM inventory inv
    JOIN machines m ON inv.machine_id = m.id
    WHERE m.business_id = ?
    GROUP BY m.id, m.name
    ORDER BY total_stock DESC
");
$stmt->execute([$low_stock_threshold, $business_id]);
$machineInventory = $stmt->fetchAll();

// Get low stock items
$stmt = $pdo->prepare("
    SELECT 
        i.name as item_name,
        m.name as machine_name,
        inv.quantity,
        ? as low_stock_threshold,
        inv.last_updated
    FROM inventory inv
    JOIN items i ON inv.item_id = i.id
    JOIN machines m ON inv.machine_id = m.id
    WHERE m.business_id = ? AND inv.quantity <= ?
    ORDER BY inv.quantity ASC
");
$stmt->execute([$low_stock_threshold, $business_id, $low_stock_threshold]);
$lowStockItems = $stmt->fetchAll();

// Get stock distribution by category
$stmt = $pdo->prepare("
    SELECT 
        COALESCE(i.type, 'Uncategorized') as category,
        COUNT(*) as item_count,
        SUM(inv.quantity) as total_stock,
        AVG(inv.quantity) as avg_stock
    FROM inventory inv
    JOIN items i ON inv.item_id = i.id
    JOIN machines m ON inv.machine_id = m.id
    WHERE m.business_id = ?
    GROUP BY i.type
    ORDER BY total_stock DESC
");
$stmt->execute([$business_id]);
$categoryStats = $stmt->fetchAll();

// Get top stocked items
$stmt = $pdo->prepare("
    SELECT 
        i.name as item_name,
        SUM(inv.quantity) as total_quantity,
        COUNT(DISTINCT inv.machine_id) as machine_count,
        AVG(inv.quantity) as avg_per_machine
    FROM inventory inv
    JOIN items i ON inv.item_id = i.id
    JOIN machines m ON inv.machine_id = m.id
    WHERE m.business_id = ?
    GROUP BY i.id, i.name
    ORDER BY total_quantity DESC
    LIMIT 15
");
$stmt->execute([$business_id]);
$topStockedItems = $stmt->fetchAll();

// Get recent inventory updates
$stmt = $pdo->prepare("
    SELECT 
        i.name as item_name,
        m.name as machine_name,
        inv.quantity,
        inv.last_updated
    FROM inventory inv
    JOIN items i ON inv.item_id = i.id
    JOIN machines m ON inv.machine_id = m.id
    WHERE m.business_id = ?
    ORDER BY inv.last_updated DESC
    LIMIT 20
");
$stmt->execute([$business_id]);
$recentUpdates = $stmt->fetchAll();

// Get stock level distribution
$stmt = $pdo->prepare("
    SELECT 
        CASE 
            WHEN inv.quantity = 0 THEN 'Out of Stock'
            WHEN inv.quantity <= ? THEN 'Low Stock'
            WHEN inv.quantity <= ? THEN 'Medium Stock'
            ELSE 'Well Stocked'
        END as stock_level,
        COUNT(*) as count
    FROM inventory inv
    JOIN machines m ON inv.machine_id = m.id
    WHERE m.business_id = ?
    GROUP BY stock_level
    ORDER BY count DESC
");
$stmt->execute([$low_stock_threshold, $low_stock_threshold * 2, $business_id]);
$stockLevels = $stmt->fetchAll();

require_once __DIR__ . '/../../core/includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row mb-4 align-items-center">
        <div class="col">
            <h1 class="h3 mb-0">
                <i class="bi bi-boxes text-info me-2"></i>
                Inventory Analytics
            </h1>
            <p class="text-muted">Detailed insights into stock levels, inventory distribution, and restocking needs</p>
            <small class="text-muted">Low stock threshold: <?php echo $low_stock_threshold; ?> items</small>
        </div>
        <div class="col-auto">
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
                    <h3 class="text-info"><?php echo number_format($stats['total_items'] ?? 0); ?></h3>
                    <small class="text-muted">Total Items</small>
                </div>
            </div>
        </div>
        <div class="col-md-2-4">
            <div class="card text-center">
                <div class="card-body">
                    <h3 class="text-success"><?php echo number_format($stats['total_stock'] ?? 0); ?></h3>
                    <small class="text-muted">Total Stock</small>
                </div>
            </div>
        </div>
        <div class="col-md-2-4">
            <div class="card text-center">
                <div class="card-body">
                    <h3 class="text-warning"><?php echo number_format($stats['low_stock_items'] ?? 0); ?></h3>
                    <small class="text-muted">Low Stock Items</small>
                </div>
            </div>
        </div>
        <div class="col-md-2-4">
            <div class="card text-center">
                <div class="card-body">
                    <h3 class="text-danger"><?php echo number_format($stats['out_of_stock_items'] ?? 0); ?></h3>
                    <small class="text-muted">Out of Stock</small>
                </div>
            </div>
        </div>
        <div class="col-md-2-4">
            <div class="card text-center">
                <div class="card-body">
                    <h3 class="text-primary"><?php echo number_format($stats['machines_with_inventory'] ?? 0); ?></h3>
                    <small class="text-muted">Active Machines</small>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Stock Level Distribution -->
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Stock Level Distribution</h5>
                </div>
                <div class="card-body">
                    <canvas id="stockLevelChart" height="200"></canvas>
                </div>
            </div>
        </div>

        <!-- Category Distribution -->
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Stock by Category</h5>
                </div>
                <div class="card-body">
                    <canvas id="categoryChart" height="100"></canvas>
                </div>
            </div>
        </div>

        <!-- Low Stock Alerts -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Low Stock Alerts</h5>
                    <span class="badge bg-warning"><?php echo count($lowStockItems); ?> items</span>
                </div>
                <div class="card-body">
                    <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th>Machine</th>
                                    <th>Current</th>
                                    <th>Threshold</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($lowStockItems)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted">No low stock items found</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($lowStockItems as $item): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                                            <td><?php echo htmlspecialchars($item['machine_name']); ?></td>
                                            <td><?php echo $item['quantity']; ?></td>
                                            <td><?php echo $item['low_stock_threshold']; ?></td>
                                            <td>
                                                <?php 
                                                $status = $item['quantity'] == 0 ? 'Out of Stock' : 'Low Stock';
                                                $color = $item['quantity'] == 0 ? 'danger' : 'warning';
                                                ?>
                                                <span class="badge bg-<?php echo $color; ?>"><?php echo $status; ?></span>
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

        <!-- Machine Inventory Overview -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Inventory by Machine</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Machine</th>
                                    <th>Items</th>
                                    <th>Total Stock</th>
                                    <th>Low Stock</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($machineInventory)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted">No inventory data found</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($machineInventory as $machine): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($machine['machine_name']); ?></td>
                                            <td><?php echo $machine['item_count']; ?></td>
                                            <td><?php echo $machine['total_stock']; ?></td>
                                            <td><?php echo $machine['low_stock_count']; ?></td>
                                            <td>
                                                <?php 
                                                $status = $machine['out_of_stock_count'] > 0 ? 'Critical' : 
                                                         ($machine['low_stock_count'] > 0 ? 'Attention' : 'Good');
                                                $color = $status === 'Critical' ? 'danger' : 
                                                        ($status === 'Attention' ? 'warning' : 'success');
                                                ?>
                                                <span class="badge bg-<?php echo $color; ?>"><?php echo $status; ?></span>
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

        <!-- Top Stocked Items -->
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Top Stocked Items</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th>Total Quantity</th>
                                    <th>Machines</th>
                                    <th>Avg per Machine</th>
                                    <th>Stock Level</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($topStockedItems)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted">No inventory data found</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($topStockedItems as $item): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                                            <td><?php echo $item['total_quantity']; ?></td>
                                            <td><?php echo $item['machine_count']; ?></td>
                                            <td><?php echo round($item['avg_per_machine'], 1); ?></td>
                                            <td>
                                                <div class="progress" style="height: 8px;">
                                                    <?php 
                                                    $percentage = min(100, ($item['total_quantity'] / 50) * 100);
                                                    $color = $percentage >= 70 ? 'success' : ($percentage >= 30 ? 'warning' : 'danger');
                                                    ?>
                                                    <div class="progress-bar bg-<?php echo $color; ?>" style="width: <?php echo $percentage; ?>%"></div>
                                                </div>
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

        <!-- Recent Updates -->
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Recent Updates</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th>Machine</th>
                                    <th>Qty</th>
                                    <th>Updated</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recentUpdates)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-muted">No recent updates</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recentUpdates as $update): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars(substr($update['item_name'], 0, 15)); ?></td>
                                            <td><?php echo htmlspecialchars(substr($update['machine_name'], 0, 10)); ?></td>
                                            <td><?php echo $update['quantity']; ?></td>
                                            <td><?php echo date('M d', strtotime($update['last_updated'])); ?></td>
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
    // Stock Level Distribution Chart
    const stockData = <?php echo json_encode($stockLevels); ?>;
    const stockLabels = stockData.map(d => d.stock_level);
    const stockCounts = stockData.map(d => parseInt(d.count));

    new Chart(document.getElementById('stockLevelChart'), {
        type: 'doughnut',
        data: {
            labels: stockLabels,
            datasets: [{
                data: stockCounts,
                backgroundColor: ['#dc3545', '#ffc107', '#fd7e14', '#198754']
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

    // Category Distribution Chart
    const categoryData = <?php echo json_encode($categoryStats); ?>;
    const categoryLabels = categoryData.map(d => d.category || 'Uncategorized');
    const categoryStock = categoryData.map(d => parseInt(d.total_stock));

    new Chart(document.getElementById('categoryChart'), {
        type: 'bar',
        data: {
            labels: categoryLabels,
            datasets: [{
                label: 'Total Stock',
                data: categoryStock,
                backgroundColor: '#17a2b8'
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