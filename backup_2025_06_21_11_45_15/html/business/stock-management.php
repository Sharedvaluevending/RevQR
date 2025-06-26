<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/csrf.php';

// Require business role
require_role('business');

// Generate CSRF token
$csrf_token = generate_csrf_token();

$message = '';
$message_type = '';

// Get business details
$stmt = $pdo->prepare("SELECT b.id FROM businesses b JOIN users u ON b.id = u.business_id WHERE u.id = ?");
$stmt->execute([$_SESSION['user_id']]);
$business = $stmt->fetch();
$business_id = $business ? $business['id'] : 0;

if (!$business) {
    header('Location: /business/dashboard.php?error=no_business');
    exit;
}

// Get location filter parameter
$location_type = $_GET['location'] ?? 'machines'; // 'machines', 'warehouse', 'all'

// Get stock levels based on location type
try {
    if ($location_type === 'warehouse') {
        // Warehouse/Storage inventory query - use warehouse_inventory table
        $stockQuery = "
            SELECT 
                mi.id,
                mi.name as item_name,
                mi.category,
                mi.brand,
                mi.suggested_price,
                mi.suggested_cost,
                -- Warehouse stock from warehouse_inventory table
                COALESCE(wi.quantity, 0) as current_stock,
                'warehouse' as location_type,
                wi.location_name as storage_location,
                -- Sales data from last 7 days
                COALESCE(SUM(CASE WHEN s.sale_time >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN s.quantity ELSE 0 END), 0) as sales_7d,
                -- Sales data from last 30 days  
                COALESCE(SUM(CASE WHEN s.sale_time >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN s.quantity ELSE 0 END), 0) as sales_30d,
                -- Stock alerts
                CASE 
                    WHEN wi.quantity <= wi.minimum_stock THEN 'critical'
                    WHEN wi.quantity <= (wi.minimum_stock * 2) THEN 'low'
                    WHEN wi.quantity <= (wi.minimum_stock * 4) THEN 'medium'
                    ELSE 'good'
                END as stock_status,
                -- Sales velocity (items per day)
                CASE 
                    WHEN SUM(CASE WHEN s.sale_time >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN s.quantity ELSE 0 END) > 0 
                    THEN SUM(CASE WHEN s.sale_time >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN s.quantity ELSE 0 END) / 7.0
                    ELSE 0
                END as daily_velocity,
                -- Days until out of stock
                CASE 
                    WHEN SUM(CASE WHEN s.sale_time >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN s.quantity ELSE 0 END) > 0 
                    THEN wi.quantity / (SUM(CASE WHEN s.sale_time >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN s.quantity ELSE 0 END) / 7.0)
                    ELSE NULL
                END as days_until_empty
            FROM master_items mi
            JOIN warehouse_inventory wi ON mi.id = wi.master_item_id 
                AND wi.business_id = ?
            LEFT JOIN sales s ON mi.id = s.item_id 
                AND s.business_id = ? 
                AND s.sale_time >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            WHERE wi.quantity > 0
            GROUP BY mi.id, mi.name, mi.category, mi.brand, mi.suggested_price, mi.suggested_cost, wi.quantity, wi.location_name, wi.minimum_stock
            ORDER BY 
                CASE 
                    WHEN wi.quantity <= wi.minimum_stock THEN 1
                    WHEN wi.quantity <= (wi.minimum_stock * 2) THEN 2
                    WHEN wi.quantity <= (wi.minimum_stock * 4) THEN 3
                    ELSE 4
                END ASC,
                mi.name ASC
        ";
        
        $stmt = $pdo->prepare($stockQuery);
        $stmt->execute([$business_id, $business_id]);
        $stockItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } else {
        // Machine inventory query - use voting_list_items table
    $stockQuery = "
        SELECT 
            mi.id,
            mi.name as item_name,
            mi.category,
            mi.brand,
            mi.suggested_price,
            mi.suggested_cost,
            -- Current stock levels from voting_list_items table
            COALESCE(SUM(vli.inventory), 0) as current_stock,
            COUNT(DISTINCT vli.voting_list_id) as machines_stocking,
                'machines' as location_type,
                GROUP_CONCAT(DISTINCT vl.name SEPARATOR ', ') as machine_names,
            -- Sales data from last 7 days
            COALESCE(SUM(CASE WHEN s.sale_time >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN s.quantity ELSE 0 END), 0) as sales_7d,
            -- Sales data from last 30 days  
            COALESCE(SUM(CASE WHEN s.sale_time >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN s.quantity ELSE 0 END), 0) as sales_30d,
            -- Stock alerts
            CASE 
                WHEN SUM(vli.inventory) <= 5 THEN 'critical'
                WHEN SUM(vli.inventory) <= 20 THEN 'low'
                WHEN SUM(vli.inventory) <= 50 THEN 'medium'
                ELSE 'good'
            END as stock_status,
            -- Sales velocity (items per day)
            CASE 
                WHEN SUM(CASE WHEN s.sale_time >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN s.quantity ELSE 0 END) > 0 
                THEN SUM(CASE WHEN s.sale_time >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN s.quantity ELSE 0 END) / 7.0
                ELSE 0
            END as daily_velocity,
            -- Days until out of stock
            CASE 
                WHEN SUM(CASE WHEN s.sale_time >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN s.quantity ELSE 0 END) > 0 
                THEN SUM(vli.inventory) / (SUM(CASE WHEN s.sale_time >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN s.quantity ELSE 0 END) / 7.0)
                ELSE NULL
            END as days_until_empty
        FROM master_items mi
        LEFT JOIN voting_list_items vli ON mi.id = vli.master_item_id 
            AND vli.voting_list_id IN (
                SELECT id FROM voting_lists WHERE business_id = ?
            )
            LEFT JOIN voting_lists vl ON vli.voting_list_id = vl.id
            LEFT JOIN sales s ON mi.id = s.item_id 
            AND s.business_id = ? 
            AND s.sale_time >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY mi.id, mi.name, mi.category, mi.brand, mi.suggested_price, mi.suggested_cost
        HAVING current_stock > 0 OR sales_30d > 0
        ORDER BY 
            CASE 
                WHEN SUM(vli.inventory) <= 5 THEN 1
                WHEN SUM(vli.inventory) <= 20 THEN 2
                WHEN SUM(vli.inventory) <= 50 THEN 3
                ELSE 4
            END ASC,
            sales_30d DESC
    ";
    
    $stmt = $pdo->prepare($stockQuery);
    $stmt->execute([$business_id, $business_id]);
    $stockItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log("Error fetching stock data: " . $e->getMessage());
    $stockItems = [];
    $message = "Error loading stock data. Please try again.";
    $message_type = "danger";
}

// Calculate summary statistics
$totalItems = count($stockItems);
$criticalItems = count(array_filter($stockItems, function($item) { return $item['stock_status'] === 'critical'; }));
$lowStockItems = count(array_filter($stockItems, function($item) { return $item['stock_status'] === 'low'; }));
$totalStock = array_sum(array_column($stockItems, 'current_stock'));

if ($location_type === 'machines') {
$totalMachines = count(array_unique(array_filter(array_column($stockItems, 'machines_stocking'))));
} else {
    $totalLocations = count(array_unique(array_filter(array_column($stockItems, 'storage_location'))));
}

require_once __DIR__ . '/../core/includes/header.php';
?>

<style>
/* Custom table styling to fix visibility issues */
#stockTable {
    background: rgba(255, 255, 255, 0.15) !important;
    backdrop-filter: blur(20px) !important;
    border-radius: 12px !important;
    overflow: hidden !important;
}

#stockTable thead th {
    background: rgba(30, 60, 114, 0.6) !important;
    color: #ffffff !important;
    font-weight: 600 !important;
    border-bottom: 2px solid rgba(255, 255, 255, 0.3) !important;
    padding: 1rem 0.75rem !important;
    position: sticky !important;
    top: 0 !important;
    z-index: 10 !important;
}

#stockTable tbody td {
    background: rgba(255, 255, 255, 0.12) !important;
    color: rgba(255, 255, 255, 0.95) !important;
    border-bottom: 1px solid rgba(255, 255, 255, 0.15) !important;
    padding: 0.875rem 0.75rem !important;
}

#stockTable tbody tr:hover td {
    background: rgba(255, 255, 255, 0.18) !important;
    color: #ffffff !important;
}

/* Fix form controls inside table */
#stockTable .form-control,
#stockTable .form-select {
    background: rgba(255, 255, 255, 0.9) !important;
    color: #333333 !important;
    border: 1px solid rgba(255, 255, 255, 0.3) !important;
}

/* Badge styling improvements */
#stockTable .badge {
    font-weight: 500 !important;
    padding: 0.375rem 0.5rem !important;
}

/* Button styling inside table */
#stockTable .btn-outline-primary,
#stockTable .btn-outline-success,
#stockTable .btn-outline-warning {
    background: rgba(255, 255, 255, 0.1) !important;
    border-color: rgba(255, 255, 255, 0.3) !important;
    color: rgba(255, 255, 255, 0.9) !important;
}

#stockTable .btn-outline-primary:hover {
    background: rgba(13, 110, 253, 0.8) !important;
    color: #ffffff !important;
}

#stockTable .btn-outline-success:hover {
    background: rgba(25, 135, 84, 0.8) !important;
    color: #ffffff !important;
}

#stockTable .btn-outline-warning:hover {
    background: rgba(255, 193, 7, 0.8) !important;
    color: #000000 !important;
}

/* Fix sticky positioning issues */
.sticky-top {
    position: sticky !important;
    top: 0 !important;
    z-index: 10 !important;
}

.badge {
    font-size: 0.75rem;
}

.btn-group-sm .btn {
    padding: 0.25rem 0.5rem;
}

.machine-stock-item {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 0.375rem;
    padding: 1rem;
    margin-bottom: 0.75rem;
}

.machine-stock-item:last-child {
    margin-bottom: 0;
}

/* Fix any navbar overlap issues */
body {
    padding-top: 0;
}

.container-fluid {
    margin-top: 0;
}

/* Ensure modals appear above everything */
.modal {
    z-index: 1055;
}

.modal-backdrop {
    z-index: 1054;
}

/* Custom darker colors for stock management table */
.table .text-success {
    color: #2e7d32 !important; /* Darker green for sales and revenue */
}

.table .text-primary {
    color: #1565c0 !important; /* Darker blue for sales numbers */
}

.table .text-info {
    color: #01579b !important; /* Darker blue for velocity and warehouse indicators */
}

/* Location toggle styling */
.btn-group .btn.btn-info,
.btn-group .btn.btn-primary {
    font-weight: 600;
}

/* Enhanced warehouse indicator styling */
.badge.bg-info {
    background-color: #0dcaf0 !important;
    color: #000 !important;
    font-weight: 500;
}

.badge.bg-primary {
    background-color: #0d6efd !important;
    font-weight: 500;
}

/* Improved dropdown styling for inventory modals */
.modal select[multiple],
.modal select[size] {
    max-height: 200px;
    overflow-y: auto;
}

.modal select option {
    padding: 0.375rem 0.75rem;
}

.modal select optgroup {
    font-weight: 600;
    color: #495057;
    background-color: #f8f9fa;
}

.modal select optgroup option {
    font-weight: 400;
    color: #212529;
    background-color: #ffffff;
    padding-left: 1rem;
}

/* Custom scrollbar for dropdowns */
.modal select::-webkit-scrollbar {
    width: 8px;
}

.modal select::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}

.modal select::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 4px;
}

.modal select::-webkit-scrollbar-thumb:hover {
    background: #a8a8a8;
}

/* Search input styling */
#inventory_item_search {
    border: 2px solid #e9ecef;
    transition: border-color 0.15s ease-in-out;
}

#inventory_item_search:focus {
    border-color: #86b7fe;
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
}
</style>

<div class="container-fluid px-0">
    <!-- Header Section -->
    <div class="bg-white border-bottom">
        <div class="container py-4">
            <div class="row align-items-center">
                <div class="col">
                    <h1 class="h3 mb-1">
                        <i class="bi bi-boxes me-2"></i>Stock Management
                        <?php if ($location_type === 'machines'): ?>
                            <span class="badge bg-primary ms-2">Machine Inventory</span>
                        <?php else: ?>
                            <span class="badge bg-info ms-2">Warehouse Inventory</span>
                        <?php endif; ?>
                    </h1>
                    <p class="text-muted mb-0">
                        <?php if ($location_type === 'machines'): ?>
                            Monitor inventory levels in vending machines, track stock movement, and manage restocking alerts
                        <?php else: ?>
                            Monitor warehouse and storage inventory levels, track supply levels for restocking machines
                        <?php endif; ?>
                    </p>
                </div>
                <div class="col-auto">
                    <!-- Location Toggle -->
                    <div class="btn-group me-3" role="group" aria-label="Inventory Location Toggle">
                        <a href="?location=machines" class="btn <?php echo $location_type === 'machines' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                            <i class="bi bi-cpu me-1"></i>Machines
                        </a>
                        <a href="?location=warehouse" class="btn <?php echo $location_type === 'warehouse' ? 'btn-info' : 'btn-outline-info'; ?>">
                            <i class="bi bi-building me-1"></i>Warehouse
                        </a>
                    </div>
                    
                    <!-- Stats Cards -->
                    <div class="row g-3 text-center">
                        <div class="col">
                            <div class="card border-0 bg-light">
                                <div class="card-body py-2 px-3">
                                    <h4 class="h5 mb-0 text-primary"><?php echo number_format($totalItems); ?></h4>
                                    <small class="text-muted">Items Tracked</small>
                                </div>
                            </div>
                        </div>
                        <div class="col">
                            <div class="card border-0 bg-light">
                                <div class="card-body py-2 px-3">
                                    <h4 class="h5 mb-0 text-danger"><?php echo $criticalItems; ?></h4>
                                    <small class="text-muted">Critical Stock</small>
                                </div>
                            </div>
                        </div>
                        <div class="col">
                            <div class="card border-0 bg-light">
                                <div class="card-body py-2 px-3">
                                    <h4 class="h5 mb-0 text-warning"><?php echo $lowStockItems; ?></h4>
                                    <small class="text-muted">Low Stock</small>
                                </div>
                            </div>
                        </div>
                        <div class="col">
                            <div class="card border-0 bg-light">
                                <div class="card-body py-2 px-3">
                                    <h4 class="h5 mb-0 text-success"><?php echo number_format($totalStock); ?></h4>
                                    <small class="text-muted">Total Units</small>
                                </div>
                            </div>
                        </div>
                        <?php if ($location_type === 'machines'): ?>
                        <div class="col">
                            <div class="card border-0 bg-light">
                                <div class="card-body py-2 px-3">
                                    <h4 class="h5 mb-0 text-info"><?php echo $totalMachines ?? 0; ?></h4>
                                    <small class="text-muted">Machines</small>
                                </div>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="col">
                            <div class="card border-0 bg-light">
                                <div class="card-body py-2 px-3">
                                    <h4 class="h5 mb-0 text-info"><?php echo $totalLocations ?? 0; ?></h4>
                                    <small class="text-muted">Locations</small>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show mx-4 mt-4" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="container-fluid py-4">
        <!-- Stock Alerts -->
        <?php if ($criticalItems > 0 || $lowStockItems > 0): ?>
            <div class="row mb-4">
                <div class="col-12">
                    <div class="alert alert-warning">
                        <h5 class="alert-heading">
                            <i class="bi bi-exclamation-triangle me-2"></i>Stock Alerts
                        </h5>
                        <p class="mb-0">
                            <?php if ($criticalItems > 0): ?>
                                <strong><?php echo $criticalItems; ?> items</strong> are critically low (≤5 units).
                            <?php endif; ?>
                            <?php if ($lowStockItems > 0): ?>
                                <strong><?php echo $lowStockItems; ?> items</strong> are running low (≤20 units).
                            <?php endif; ?>
                            Consider restocking soon to avoid stockouts.
                        </p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-header bg-white py-3">
                        <div class="row align-items-center">
                            <div class="col">
                                <h5 class="mb-0">
                                    <i class="bi bi-list-ul me-2"></i>Inventory Overview
                                </h5>
                                <small class="text-muted">Real-time stock levels and sales velocity</small>
                            </div>
                            <div class="col-auto">
                                <div class="btn-group">
                                    <?php if ($location_type === 'warehouse'): ?>
                                        <button type="button" class="btn btn-success" onclick="showAddInventoryModal()">
                                            <i class="bi bi-plus-lg me-1"></i>Add Inventory to List
                                        </button>
                                    <?php endif; ?>
                                    <button type="button" class="btn btn-outline-primary" onclick="exportStockReport()">
                                        <i class="bi bi-download me-1"></i>Export Report
                                    </button>
                                    <button type="button" class="btn btn-primary" onclick="refreshStockData()">
                                        <i class="bi bi-arrow-clockwise me-1"></i>Refresh
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card-body p-0">
                        <?php if (empty($stockItems)): ?>
                            <div class="text-center py-5">
                                <i class="bi bi-inbox display-1 text-muted"></i>
                                <h4 class="mt-3">No Stock Data Available</h4>
                                <p class="text-muted">No items with current stock or recent sales found.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0" id="stockTable">
                                    <thead class="bg-light sticky-top">
                                        <tr>
                                            <th class="ps-4">Item</th>
                                            <th>Current Stock</th>
                                            <th>Stock Status</th>
                                            <?php if ($location_type === 'machines'): ?>
                                            <th>Machines</th>
                                            <?php else: ?>
                                                <th>Location</th>
                                            <?php endif; ?>
                                            <th>Sales (7d)</th>
                                            <th>Sales (30d)</th>
                                            <th>Daily Velocity</th>
                                            <th>Days Left</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($stockItems as $item): ?>
                                            <tr>
                                                <td class="ps-4">
                                                    <div>
                                                        <div class="fw-medium"><?php echo htmlspecialchars($item['item_name']); ?></div>
                                                        <small class="text-muted">
                                                            <?php echo htmlspecialchars($item['category']); ?>
                                                            <?php if (!empty($item['brand'])): ?>
                                                                • <?php echo htmlspecialchars($item['brand']); ?>
                                                            <?php endif; ?>
                                                        </small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="fw-bold"><?php echo $item['current_stock']; ?></span> units
                                                    <?php if ($location_type === 'warehouse'): ?>
                                                        <br><small class="text-info">Warehouse Stock</small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    $statusColors = [
                                                        'critical' => 'danger',
                                                        'low' => 'warning',
                                                        'medium' => 'info',
                                                        'good' => 'success'
                                                    ];
                                                    $statusColor = $statusColors[$item['stock_status']] ?? 'secondary';
                                                    ?>
                                                    <span class="badge bg-<?php echo $statusColor; ?>">
                                                        <?php echo ucfirst($item['stock_status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($location_type === 'machines'): ?>
                                                        <div class="text-center">
                                                            <span class="badge bg-primary"><?php echo $item['machines_stocking'] ?? 0; ?></span>
                                                            <?php if (!empty($item['machine_names'])): ?>
                                                                <br><small class="text-muted" title="<?php echo htmlspecialchars($item['machine_names']); ?>">
                                                                    <?php echo htmlspecialchars(substr($item['machine_names'], 0, 20)); ?><?php echo strlen($item['machine_names']) > 20 ? '...' : ''; ?>
                                                                </small>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="text-center">
                                                            <span class="badge bg-info">
                                                                <?php echo htmlspecialchars($item['storage_location'] ?: 'Unknown'); ?>
                                                            </span>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <span class="fw-bold text-primary"><?php echo $item['sales_7d']; ?></span>
                                                </td>
                                                <td class="text-center">
                                                    <span class="fw-bold text-success"><?php echo $item['sales_30d']; ?></span>
                                                </td>
                                                <td class="text-center">
                                                    <span class="fw-bold text-info"><?php echo number_format($item['daily_velocity'], 1); ?>/day</span>
                                                </td>
                                                <td class="text-center">
                                                    <?php if ($item['days_until_empty'] !== null): ?>
                                                        <?php 
                                                        $days = floor($item['days_until_empty']);
                                                        if ($days <= 7): ?>
                                                            <span class="badge bg-danger"><?php echo $days; ?> days</span>
                                                        <?php elseif ($days <= 14): ?>
                                                            <span class="badge bg-warning text-dark"><?php echo $days; ?> days</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-success"><?php echo $days; ?> days</span>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">∞</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <button type="button" class="btn btn-outline-primary" 
                                                                onclick="viewItemDetails(<?php echo $item['id']; ?>)"
                                                                title="View Details">
                                                            <i class="bi bi-eye"></i>
                                                        </button>
                                                        <?php if ($location_type === 'machines'): ?>
                                                        <button type="button" class="btn btn-outline-success" 
                                                                    onclick="restockMachine(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars($item['item_name']); ?>')"
                                                                    title="Restock Machines">
                                                                <i class="bi bi-arrow-up-circle"></i>
                                                            </button>
                                                        <?php else: ?>
                                                            <button type="button" class="btn btn-outline-success" 
                                                                    onclick="restockWarehouse(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars($item['item_name']); ?>')"
                                                                    title="Add to Warehouse">
                                                            <i class="bi bi-plus-lg"></i>
                                                        </button>
                                                            <button type="button" class="btn btn-outline-info" 
                                                                    onclick="transferToMachine(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars($item['item_name']); ?>')"
                                                                    title="Transfer to Machine">
                                                                <i class="bi bi-arrow-right-circle"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                        <button type="button" class="btn btn-outline-warning" 
                                                                onclick="adjustStock(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars($item['item_name']); ?>')"
                                                                title="Adjust Stock">
                                                            <i class="bi bi-pencil"></i>
                                                        </button>
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
    </div>
</div>

<!-- Stock Management Modals -->
<!-- Restock Modal -->
<div class="modal fade" id="restockModal" tabindex="-1" aria-labelledby="restockModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="restockModalLabel">
                    <i class="bi bi-plus-circle me-2"></i>Restock Item
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="restockForm">
                <div class="modal-body">
                    <input type="hidden" id="restock_item_id" name="item_id">
                    <input type="hidden" name="action" value="restock">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Item</label>
                        <p class="form-control-plaintext fw-bold" id="restock_item_name"></p>
                    </div>
                    
                    <div class="mb-3">
                        <label for="machine_select" class="form-label">Select Machine</label>
                        <select class="form-select" id="machine_select" name="machine_id" required>
                            <option value="">Choose a machine...</option>
                            <!-- Will be populated by JavaScript -->
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="restock_quantity" class="form-label">Quantity to Add</label>
                        <input type="number" class="form-control" id="restock_quantity" name="quantity" 
                               min="1" max="100" required>
                        <div class="form-text">Enter the number of units to add to this machine</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="restock_notes" class="form-label">Notes (Optional)</label>
                        <textarea class="form-control" id="restock_notes" name="notes" rows="2" 
                                  placeholder="Restock notes, batch info, etc."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-plus-lg me-1"></i>Add Stock
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Adjust Stock Modal -->
<div class="modal fade" id="adjustStockModal" tabindex="-1" aria-labelledby="adjustStockModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="adjustStockModalLabel">
                    <i class="bi bi-pencil me-2"></i>Adjust Stock Levels
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="adjustStockForm">
                <div class="modal-body">
                    <input type="hidden" id="adjust_item_id" name="item_id">
                    <input type="hidden" name="action" value="adjust_stock">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Item</label>
                        <p class="form-control-plaintext fw-bold" id="adjust_item_name"></p>
                    </div>
                    
                    <div id="machine_stock_list">
                        <!-- Will be populated by JavaScript with current stock levels per machine -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-check-lg me-1"></i>Update Stock
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add New Inventory Item Modal -->
<div class="modal fade" id="addInventoryModal" tabindex="-1" aria-labelledby="addInventoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addInventoryModalLabel">
                    <i class="bi bi-plus-circle me-2"></i>Add New Inventory Item
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="addInventoryForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_inventory_item">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label for="inventory_item_search" class="form-label">Search Items</label>
                                <input type="text" class="form-control" id="inventory_item_search" 
                                       placeholder="Type to search items by name, category, or brand...">
                                <div class="form-text">Search to quickly find items across all categories</div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="inventory_category_select" class="form-label">Select Category <span class="text-danger">*</span></label>
                                        <select class="form-select" id="inventory_category_select" required>
                                            <option value="">Choose a category...</option>
                                            <!-- Will be populated by JavaScript -->
                                        </select>
                                        <div class="form-text">Select item category first</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="inventory_item_select" class="form-label">Select Item <span class="text-danger">*</span></label>
                                        <select class="form-select" id="inventory_item_select" name="master_item_id" required disabled>
                                            <option value="">Select category first...</option>
                                        </select>
                                        <div class="form-text">Choose from items in selected category</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3" style="margin-top: 1.875rem;">
                                <label for="inventory_location_type" class="form-label">Location Type <span class="text-danger">*</span></label>
                                <select class="form-select" id="inventory_location_type" name="location_type" required>
                                    <option value="warehouse">Warehouse</option>
                                    <option value="storage">Storage</option>
                                    <option value="home">Home</option>
                                    <option value="supplier">Supplier</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="inventory_location_name" class="form-label">Location Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="inventory_location_name" name="location_name" 
                                       value="Main Warehouse" required>
                                <div class="form-text">Specific location or storage area name</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="inventory_quantity" class="form-label">Initial Quantity <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="inventory_quantity" name="quantity" 
                                           min="0" step="1" required>
                                    <span class="input-group-text">units</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="inventory_minimum_stock" class="form-label">Minimum Stock</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="inventory_minimum_stock" name="minimum_stock" 
                                           min="0" step="1" value="20">
                                    <span class="input-group-text">units</span>
                                </div>
                                <div class="form-text">Alert when stock falls below this level</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="inventory_maximum_stock" class="form-label">Maximum Stock</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="inventory_maximum_stock" name="maximum_stock" 
                                           min="0" step="1" value="500">
                                    <span class="input-group-text">units</span>
                                </div>
                                <div class="form-text">Maximum storage capacity</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="inventory_cost_per_unit" class="form-label">Cost per Unit</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control" id="inventory_cost_per_unit" name="cost_per_unit" 
                                           min="0" step="0.01">
                                </div>
                                <div class="form-text">Purchase cost per unit</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="inventory_supplier_info" class="form-label">Supplier Information</label>
                                <input type="text" class="form-control" id="inventory_supplier_info" name="supplier_info" 
                                       placeholder="Supplier name, contact, or reference">
                                <div class="form-text">Optional supplier details</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="inventory_expiry_date" class="form-label">Expiry Date</label>
                                <input type="date" class="form-control" id="inventory_expiry_date" name="expiry_date">
                                <div class="form-text">Optional expiration date for perishable items</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="inventory_notes" class="form-label">Notes</label>
                        <textarea class="form-control" id="inventory_notes" name="notes" rows="3" 
                                  placeholder="Additional notes about this inventory item (batch info, special handling instructions, etc.)"></textarea>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        <strong>Note:</strong> This will add a new inventory record for the selected item at the specified location. 
                        If the item already exists at this location, the quantities will be added together.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-plus-lg me-1"></i>Add to Inventory
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize modals
    const restockModal = new bootstrap.Modal(document.getElementById('restockModal'));
    const adjustStockModal = new bootstrap.Modal(document.getElementById('adjustStockModal'));
    const addInventoryModal = new bootstrap.Modal(document.getElementById('addInventoryModal'));
    
    // Load machines data
    let machines = [];
    let masterItems = [];
    
    // Fetch machines for the business
    async function loadMachines() {
        try {
            const response = await fetch('get_machines.php');
            const data = await response.json();
            if (data.success) {
                machines = data.machines;
                updateMachineSelect();
            }
        } catch (error) {
            console.error('Error loading machines:', error);
        }
    }
    
    // Fetch master items for the add inventory modal
    async function loadMasterItems() {
        try {
            const response = await fetch('get_master_items.php');
            const data = await response.json();
            if (data.success) {
                masterItems = data.items;
                updateMasterItemSelect();
            }
        } catch (error) {
            console.error('Error loading master items:', error);
        }
    }
    
    function updateMachineSelect() {
        const select = document.getElementById('machine_select');
        select.innerHTML = '<option value="">Choose a machine...</option>';
        
        machines.forEach(machine => {
            const option = document.createElement('option');
            option.value = machine.id;
            option.textContent = `${machine.name} - ${machine.location || 'No location'}`;
            select.appendChild(option);
        });
    }
    
    function updateMasterItemSelect() {
        // This function now updates the category dropdown
        const categorySelect = document.getElementById('inventory_category_select');
        const itemSelect = document.getElementById('inventory_item_select');
        
        categorySelect.innerHTML = '<option value="">Choose a category...</option>';
        itemSelect.innerHTML = '<option value="">Select category first...</option>';
        itemSelect.disabled = true;
        
        // Group items by category for better organization
        const itemsByCategory = {};
        masterItems.forEach(item => {
            const category = item.category || 'Uncategorized';
            if (!itemsByCategory[category]) {
                itemsByCategory[category] = [];
            }
            itemsByCategory[category].push(item);
        });
        
        // Sort categories and add them to the category select
        const sortedCategories = Object.keys(itemsByCategory).sort();
        sortedCategories.forEach(category => {
            const option = document.createElement('option');
            option.value = category;
            option.textContent = `${category} (${itemsByCategory[category].length} items)`;
            categorySelect.appendChild(option);
        });
        
        // Store items by category for later use
        window.itemsByCategory = itemsByCategory;
    }
    
    // Function to populate items based on selected category
    function populateItemsForCategory(category) {
        const itemSelect = document.getElementById('inventory_item_select');
        itemSelect.innerHTML = '<option value="">Choose an item...</option>';
        
        if (!category || !window.itemsByCategory || !window.itemsByCategory[category]) {
            itemSelect.disabled = true;
            return;
        }
        
        const items = window.itemsByCategory[category];
        
        // Add info option showing item count
        const infoOption = document.createElement('option');
        infoOption.disabled = true;
        infoOption.value = '';
        infoOption.textContent = `📦 ${items.length} items available in ${category}`;
        infoOption.style.fontStyle = 'italic';
        infoOption.style.backgroundColor = '#f8f9fa';
        itemSelect.appendChild(infoOption);
        
        // Limit to 25 items visible at once, enable scrolling
        itemSelect.style.maxHeight = '200px';
        itemSelect.style.overflowY = 'auto';
        
        items.forEach(item => {
            const option = document.createElement('option');
            option.value = item.id;
            option.textContent = `${item.name}${item.brand ? ` (${item.brand})` : ''}`;
            option.dataset.suggestedCost = item.suggested_cost || '';
            option.dataset.category = item.category || '';
            option.title = `Category: ${item.category || 'Uncategorized'}${item.brand ? ` | Brand: ${item.brand}` : ''}${item.suggested_cost ? ` | Cost: $${item.suggested_cost}` : ''}`;
            itemSelect.appendChild(option);
        });
        
        itemSelect.disabled = false;
    }
    
    // Handle category selection
    document.getElementById('inventory_category_select').addEventListener('change', function() {
        const selectedCategory = this.value;
        populateItemsForCategory(selectedCategory);
        
        // Clear item selection when category changes
        document.getElementById('inventory_item_select').value = '';
        document.getElementById('inventory_cost_per_unit').value = '';
    });
    
    // Update location name based on location type
    document.getElementById('inventory_location_type').addEventListener('change', function() {
        const locationName = document.getElementById('inventory_location_name');
        const locationType = this.value;
        
        const defaultNames = {
            'warehouse': 'Main Warehouse',
            'storage': 'Storage Room',
            'home': 'Home Storage',
            'supplier': 'Supplier Location'
        };
        
        locationName.value = defaultNames[locationType] || 'Main Warehouse';
    });
    
    // Auto-fill cost when item is selected
    document.getElementById('inventory_item_select').addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        const suggestedCost = selectedOption.dataset.suggestedCost;
        
        if (suggestedCost) {
            document.getElementById('inventory_cost_per_unit').value = suggestedCost;
        }
    });
    
    // Search functionality for inventory items
    document.getElementById('inventory_item_search').addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        const categorySelect = document.getElementById('inventory_category_select');
        const itemSelect = document.getElementById('inventory_item_select');
        
        if (searchTerm.length === 0) {
            // Reset to normal category/item flow when search is empty
            categorySelect.disabled = false;
            itemSelect.disabled = true;
            itemSelect.innerHTML = '<option value="">Select category first...</option>';
            
            // Reset category dropdown to show all categories
            updateMasterItemSelect();
            return;
        }
        
        // Disable category selection when searching
        categorySelect.disabled = true;
        itemSelect.disabled = false;
        
        // Clear current options
        itemSelect.innerHTML = '<option value="">Choose an item...</option>';
        
        // Filter items based on search term
        const filteredItems = masterItems.filter(item => {
            return item.name.toLowerCase().includes(searchTerm) ||
                   (item.category && item.category.toLowerCase().includes(searchTerm)) ||
                   (item.brand && item.brand.toLowerCase().includes(searchTerm));
        });
        
        // Limit to 25 items for better performance and UX
        const limitedItems = filteredItems.slice(0, 25);
        
        // Group filtered items by category for organization
        const itemsByCategory = {};
        limitedItems.forEach(item => {
            const category = item.category || 'Uncategorized';
            if (!itemsByCategory[category]) {
                itemsByCategory[category] = [];
            }
            itemsByCategory[category].push(item);
        });
        
        // Add filtered items to select with category grouping
        Object.keys(itemsByCategory).sort().forEach(category => {
            const optgroup = document.createElement('optgroup');
            optgroup.label = category;
            
            itemsByCategory[category].forEach(item => {
                const option = document.createElement('option');
                option.value = item.id;
                option.textContent = `${item.name}${item.brand ? ` (${item.brand})` : ''}`;
                option.dataset.suggestedCost = item.suggested_cost || '';
                option.dataset.category = item.category || '';
                optgroup.appendChild(option);
            });
            
            itemSelect.appendChild(optgroup);
        });
        
        // Show search results info
        if (limitedItems.length === 0) {
            const option = document.createElement('option');
            option.disabled = true;
            option.textContent = 'No items found matching your search';
            itemSelect.appendChild(option);
        } else if (filteredItems.length > 25) {
            const option = document.createElement('option');
            option.disabled = true;
            option.textContent = `Showing first 25 of ${filteredItems.length} results - refine search for more`;
            itemSelect.insertBefore(option, itemSelect.children[1]);
        }
        
        // Enable scrolling for search results
        itemSelect.style.maxHeight = '200px';
        itemSelect.style.overflowY = 'auto';
    });
    
    // Global function to show add inventory modal
    window.showAddInventoryModal = function() {
        // Reset form
        document.getElementById('addInventoryForm').reset();
        document.getElementById('inventory_location_name').value = 'Main Warehouse';
        document.getElementById('inventory_minimum_stock').value = '20';
        document.getElementById('inventory_maximum_stock').value = '500';
        
        // Reset search and dropdowns
        document.getElementById('inventory_item_search').value = '';
        document.getElementById('inventory_category_select').disabled = false;
        document.getElementById('inventory_item_select').disabled = true;
        document.getElementById('inventory_item_select').innerHTML = '<option value="">Select category first...</option>';
        
        // Load master items if not loaded
        if (masterItems.length === 0) {
            loadMasterItems();
        } else {
            // Update category dropdown if items are already loaded
            updateMasterItemSelect();
        }
        
        addInventoryModal.show();
    };
    
    // Handle add inventory form submission
    document.getElementById('addInventoryForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Adding...';
        
        try {
            const response = await fetch('update_stock.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                addInventoryModal.hide();
                showAlert('success', data.message || 'Inventory item added successfully!');
                setTimeout(() => location.reload(), 1000);
            } else {
                showAlert('danger', data.message || 'Error adding inventory item.');
            }
        } catch (error) {
            console.error('Error:', error);
            showAlert('danger', 'Error adding inventory item. Please try again.');
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    });
    
    // Global functions for stock management
    window.viewItemDetails = function(itemId) {
        // Placeholder for item details modal
        alert('Item details view would be implemented here for item ID: ' + itemId);
    };

    window.restockItem = function(itemId, itemName) {
        document.getElementById('restock_item_id').value = itemId;
        document.getElementById('restock_item_name').textContent = itemName;
        document.getElementById('restock_quantity').value = '';
        document.getElementById('restock_notes').value = '';
        
        if (machines.length === 0) {
            loadMachines();
        }
        
        restockModal.show();
    };

    window.adjustStock = function(itemId, itemName) {
        document.getElementById('adjust_item_id').value = itemId;
        document.getElementById('adjust_item_name').textContent = itemName;
        
        // Load current stock levels for this item
        loadCurrentStock(itemId);
        
        adjustStockModal.show();
    };
    
    async function loadCurrentStock(itemId) {
        try {
            const response = await fetch(`get_item_stock.php?item_id=${itemId}`);
            const data = await response.json();
            
            const container = document.getElementById('machine_stock_list');
            container.innerHTML = '';
            
            if (data.success && data.stock.length > 0) {
                data.stock.forEach(stock => {
                    const stockItem = document.createElement('div');
                    stockItem.className = 'machine-stock-item';
                    stockItem.innerHTML = `
                        <div class="row align-items-center">
                            <div class="col">
                                <h6 class="mb-1">${stock.machine_name}</h6>
                                <small class="text-muted">${stock.location || 'No location'}</small>
                            </div>
                            <div class="col-auto">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">Stock:</span>
                                    <input type="number" class="form-control" name="stock[${stock.machine_id}]" 
                                           value="${stock.current_stock}" min="0" max="100" style="width: 80px;">
                                    <span class="input-group-text">units</span>
                                </div>
                            </div>
                        </div>
                    `;
                    container.appendChild(stockItem);
                });
            } else {
                container.innerHTML = '<div class="text-muted text-center py-3">No stock records found for this item.</div>';
            }
        } catch (error) {
            console.error('Error loading stock:', error);
            document.getElementById('machine_stock_list').innerHTML = 
                '<div class="text-danger text-center py-3">Error loading stock data.</div>';
        }
    }
    
    // Handle restock form submission
    document.getElementById('restockForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Adding...';
        
        try {
            const response = await fetch('update_stock.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                restockModal.hide();
                showAlert('success', data.message || 'Stock updated successfully!');
                setTimeout(() => location.reload(), 1000);
            } else {
                showAlert('danger', data.message || 'Error updating stock.');
            }
        } catch (error) {
            console.error('Error:', error);
            showAlert('danger', 'Error updating stock. Please try again.');
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    });
    
    // Handle adjust stock form submission
    document.getElementById('adjustStockForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Updating...';
        
        try {
            const response = await fetch('update_stock.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                adjustStockModal.hide();
                showAlert('success', data.message || 'Stock levels updated successfully!');
                setTimeout(() => location.reload(), 1000);
            } else {
                showAlert('danger', data.message || 'Error updating stock levels.');
            }
        } catch (error) {
            console.error('Error:', error);
            showAlert('danger', 'Error updating stock levels. Please try again.');
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    });

    window.exportStockReport = function() {
        // Create CSV export of current stock data
        const table = document.querySelector('table');
        if (!table) return;
        
        const rows = Array.from(table.querySelectorAll('tr'));
        const csvData = rows.map(row => {
            const cells = Array.from(row.querySelectorAll('th, td'));
            return cells.map(cell => `"${cell.textContent.trim()}"`).join(',');
        }).join('\n');
        
        const blob = new Blob([csvData], { type: 'text/csv' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `stock-report-${new Date().toISOString().split('T')[0]}.csv`;
        a.click();
        window.URL.revokeObjectURL(url);
    };

    window.refreshStockData = function() {
        location.reload();
    };
    
    function showAlert(type, message) {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show mx-4 mt-4`;
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        const container = document.querySelector('.container-fluid');
        const firstChild = container.firstElementChild;
        container.insertBefore(alertDiv, firstChild.nextSibling);
        
        setTimeout(() => {
            const alert = new bootstrap.Alert(alertDiv);
            alert.close();
        }, 5000);
    }
    
    // Load machines on page load
    loadMachines();

    // Separate function for machine restocking  
    window.restockMachine = function(itemId, itemName) {
        window.restockItem(itemId, itemName);
    };
    
    // New function for warehouse restocking
    window.restockWarehouse = function(itemId, itemName) {
        // For warehouse restocking, we'll use a simplified approach
        const quantity = prompt(`How many units of "${itemName}" do you want to add to warehouse?`, '10');
        if (quantity && !isNaN(quantity) && parseInt(quantity) > 0) {
            addWarehouseStock(itemId, parseInt(quantity));
        }
    };
    
    // New function for transferring stock from warehouse to machine
    window.transferToMachine = function(itemId, itemName) {
        // First show machine selection, then quantity
        if (machines.length === 0) {
            alert('No machines available for transfer');
            return;
        }
        
        let machineOptions = machines.map(m => `${m.id}: ${m.name}`).join('\n');
        let machineId = prompt(`Select machine for transfer:\n${machineOptions}\n\nEnter machine ID:`);
        
        if (machineId && !isNaN(machineId)) {
            let quantity = prompt(`How many units of "${itemName}" do you want to transfer to the machine?`, '5');
            if (quantity && !isNaN(quantity) && parseInt(quantity) > 0) {
                transferStock(itemId, parseInt(machineId), parseInt(quantity));
            }
        }
    };
    
    // Helper function to add warehouse stock
    async function addWarehouseStock(itemId, quantity) {
        try {
            const formData = new FormData();
            formData.append('action', 'add_warehouse_stock');
            formData.append('item_id', itemId);
            formData.append('quantity', quantity);
            formData.append('csrf_token', '<?php echo $csrf_token; ?>');
            
            const response = await fetch('update_stock.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                showAlert('success', 'Warehouse stock updated successfully!');
                setTimeout(() => location.reload(), 1000);
            } else {
                showAlert('danger', data.message || 'Error updating warehouse stock.');
            }
        } catch (error) {
            console.error('Error:', error);
            showAlert('danger', 'Error updating warehouse stock. Please try again.');
        }
    }
    
    // Helper function to transfer stock
    async function transferStock(itemId, machineId, quantity) {
        try {
            const formData = new FormData();
            formData.append('action', 'transfer_stock');
            formData.append('item_id', itemId);
            formData.append('machine_id', machineId);
            formData.append('quantity', quantity);
            formData.append('csrf_token', '<?php echo $csrf_token; ?>');
            
            const response = await fetch('update_stock.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                showAlert('success', 'Stock transferred successfully!');
                setTimeout(() => location.reload(), 1000);
            } else {
                showAlert('danger', data.message || 'Error transferring stock.');
            }
        } catch (error) {
            console.error('Error:', error);
            showAlert('danger', 'Error transferring stock. Please try again.');
        }
    }
});
</script>

<?php require_once __DIR__ . '/../core/includes/footer.php'; ?> 