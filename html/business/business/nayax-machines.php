<?php
/**
 * Business Nayax Machines Management Dashboard
 * Allows businesses to manage their Nayax vending machines and QR code integration
 */

require_once __DIR__ . '/../html/core/config.php';
require_once __DIR__ . '/../html/core/session.php';
require_once __DIR__ . '/../html/core/auth.php';
require_once __DIR__ . '/../html/core/nayax_manager.php';
require_once __DIR__ . '/../html/core/nayax_qr_generator.php';
require_once __DIR__ . '/../core/nayax_discount_manager.php';

// Check authentication and role
require_login();
if (!has_role('business')) {
    header('Location: ' . APP_URL . '/login.php');
    exit;
}

// Get business ID
$business_id = get_business_id();
if (!$business_id) {
    header('Location: ' . APP_URL . '/business/profile.php?error=no_business');
    exit;
}

// Get business data
try {
    $stmt = $pdo->prepare("SELECT * FROM businesses WHERE id = ?");
    $stmt->execute([$business_id]);
    $business = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$business) {
        header('Location: ' . APP_URL . '/business/profile.php?error=business_not_found');
        exit;
    }
} catch (Exception $e) {
    error_log("Error fetching business data: " . $e->getMessage());
    $business = ['name' => 'Demo Business', 'id' => $business_id];
}

// Initialize managers
$nayax_manager = new NayaxManager($pdo);
$qr_generator = new NayaxQRGenerator();
$discount_manager = new NayaxDiscountManager($pdo);

// Get business machines
$machines = $nayax_manager->getBusinessMachines($business_id);

// Get machine analytics
$analytics = [];
foreach ($machines as $machine) {
    // Get machine-specific analytics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_transactions,
            SUM(amount_cents) as total_revenue_cents,
            SUM(qr_coins_awarded) as total_coins_awarded,
            COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) as transactions_today
        FROM nayax_transactions 
        WHERE nayax_machine_id = ? AND status = 'completed'
    ");
    $stmt->execute([$machine['nayax_machine_id']]);
    $machine_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get QR coin products for this machine
    $qr_products = $nayax_manager->getMachineQRCoinProducts($machine['nayax_machine_id']);
    
    // Check if QR code exists
    $qr_info = $qr_generator->getMachineQR($machine['nayax_machine_id']);
    
    $analytics[$machine['nayax_machine_id']] = [
        'stats' => $machine_stats,
        'qr_products' => $qr_products,
        'qr_code' => $qr_info
    ];
}

// Get business discount analytics
$discount_analytics = $discount_manager->getBusinessDiscountAnalytics($business_id, 30);

// Calculate totals
$total_revenue = array_sum(array_column(array_column($analytics, 'stats'), 'total_revenue_cents'));
$total_transactions = array_sum(array_column(array_column($analytics, 'stats'), 'total_transactions'));
$total_coins_awarded = array_sum(array_column(array_column($analytics, 'stats'), 'total_coins_awarded'));
$transactions_today = array_sum(array_column(array_column($analytics, 'stats'), 'transactions_today'));

// Handle actions
$action_result = null;
if ($_POST['action'] ?? '') {
    switch ($_POST['action']) {
        case 'generate_qr':
            $machine_id = $_POST['machine_id'] ?? '';
            if ($machine_id) {
                $machine = array_filter($machines, function($m) use ($machine_id) {
                    return $m['nayax_machine_id'] === $machine_id;
                });
                $machine = reset($machine);
                
                if ($machine) {
                    $result = $qr_generator->generateMachineQR(
                        $business_id,
                        $machine_id,
                        $machine['machine_name'] . " - " . $business['name']
                    );
                    $action_result = $result;
                }
            }
            break;
            
        case 'add_machine':
            $machine_id = $_POST['new_machine_id'] ?? '';
            $device_id = $_POST['new_device_id'] ?? '';
            $machine_name = $_POST['new_machine_name'] ?? '';
            
            if ($machine_id && $device_id && $machine_name) {
                $result = $nayax_manager->registerMachine($business_id, $machine_id, $device_id, $machine_name);
                $action_result = $result;
                
                if ($result['success']) {
                    // Refresh page to show new machine
                    header("Location: " . $_SERVER['REQUEST_URI']);
                    exit;
                }
            }
            break;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nayax Machines Dashboard | <?= htmlspecialchars($business['name']) ?></title>
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #4a90e2;
            --secondary-color: #f39c12;
            --success-color: #27ae60;
            --danger-color: #e74c3c;
            --warning-color: #f39c12;
            --info-color: #3498db;
            --light-bg: #f8f9fa;
            --shadow: 0 2px 15px rgba(0,0,0,0.1);
        }
        
        body {
            background-color: var(--light-bg);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .dashboard-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 30px 0;
            margin-bottom: 30px;
        }
        
        .business-info {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .business-logo {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 20px;
            font-size: 1.5rem;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            text-align: center;
            box-shadow: var(--shadow);
            transition: transform 0.2s;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 15px;
        }
        
        .stat-revenue .stat-icon { background: linear-gradient(45deg, var(--success-color), #2ecc71); }
        .stat-transactions .stat-icon { background: linear-gradient(45deg, var(--info-color), #5dade2); }
        .stat-coins .stat-icon { background: linear-gradient(45deg, var(--warning-color), #f7dc6f); }
        .stat-today .stat-icon { background: linear-gradient(45deg, var(--primary-color), #7fb3d3); }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: var(--primary-color);
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .machine-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: var(--shadow);
            border-left: 5px solid transparent;
        }
        
        .machine-card.online {
            border-left-color: var(--success-color);
        }
        
        .machine-card.offline {
            border-left-color: var(--danger-color);
        }
        
        .machine-card.maintenance {
            border-left-color: var(--warning-color);
        }
        
        .machine-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .machine-status {
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: bold;
        }
        
        .status-active {
            background: #d4edda;
            color: #155724;
        }
        
        .status-offline {
            background: #f8d7da;
            color: #721c24;
        }
        
        .status-maintenance {
            background: #fff3cd;
            color: #856404;
        }
        
        .machine-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        
        .mini-stat {
            text-align: center;
            padding: 15px;
            background: var(--light-bg);
            border-radius: 10px;
        }
        
        .mini-stat-number {
            font-size: 1.2rem;
            font-weight: bold;
            color: var(--primary-color);
        }
        
        .mini-stat-label {
            font-size: 0.8rem;
            color: #6c757d;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .btn-action {
            flex: 1;
            min-width: 120px;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 0.9rem;
            transition: all 0.3s;
        }
        
        .qr-code-preview {
            text-align: center;
            padding: 15px;
            background: var(--light-bg);
            border-radius: 10px;
            margin: 15px 0;
        }
        
        .qr-image {
            max-width: 150px;
            border-radius: 8px;
            margin-bottom: 10px;
        }
        
        .coin-products-list {
            margin-top: 15px;
        }
        
        .product-item {
            display: flex;
            justify-content: between;
            align-items: center;
            padding: 10px;
            background: var(--light-bg);
            border-radius: 8px;
            margin: 5px 0;
        }
        
        .add-machine-card {
            background: white;
            border: 2px dashed #dee2e6;
            border-radius: 15px;
            padding: 30px;
            text-align: center;
            margin-bottom: 20px;
            transition: all 0.3s;
        }
        
        .add-machine-card:hover {
            border-color: var(--primary-color);
            background: #f8f9fa;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }
        
        .empty-icon {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        
        @media (max-width: 768px) {
            .action-buttons {
                flex-direction: column;
            }
            
            .machine-stats {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <!-- Dashboard Header -->
    <div class="dashboard-header">
        <div class="container">
            <div class="business-info">
                <div class="business-logo">
                    <?php if (isset($business['logo_url']) && $business['logo_url']): ?>
                    <img src="<?= htmlspecialchars($business['logo_url']) ?>" alt="Logo" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                    <?php else: ?>
                    <i class="bi bi-shop"></i>
                    <?php endif; ?>
                </div>
                <div>
                    <h2 class="mb-1"><?= htmlspecialchars($business['name']) ?></h2>
                    <p class="mb-0">Nayax Machines Dashboard</p>
                </div>
            </div>
            
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="/html/business/dashboard.php" class="text-white-50">Dashboard</a></li>
                    <li class="breadcrumb-item active text-white">Nayax Machines</li>
                </ol>
            </nav>
        </div>
    </div>
    
    <div class="container">
        <!-- Action Result Alert -->
        <?php if ($action_result): ?>
        <div class="alert alert-<?= $action_result['success'] ? 'success' : 'danger' ?> alert-dismissible fade show">
            <i class="bi bi-<?= $action_result['success'] ? 'check-circle' : 'x-circle' ?>"></i>
            <?= htmlspecialchars($action_result['message'] ?? 'Action completed') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <!-- Overall Statistics -->
        <div class="stats-grid">
            <div class="stat-card stat-revenue">
                <div class="stat-icon text-white">
                    <i class="bi bi-currency-dollar"></i>
                </div>
                <div class="stat-number">$<?= number_format($total_revenue / 100, 2) ?></div>
                <div class="stat-label">Total Revenue</div>
            </div>
            
            <div class="stat-card stat-transactions">
                <div class="stat-icon text-white">
                    <i class="bi bi-receipt"></i>
                </div>
                <div class="stat-number"><?= number_format($total_transactions) ?></div>
                <div class="stat-label">Total Transactions</div>
            </div>
            
            <div class="stat-card stat-coins">
                <div class="stat-icon text-white">
                    <i class="bi bi-coin"></i>
                </div>
                <div class="stat-number"><?= number_format($total_coins_awarded) ?></div>
                <div class="stat-label">QR Coins Awarded</div>
            </div>
            
            <div class="stat-card stat-today">
                <div class="stat-icon text-white">
                    <i class="bi bi-calendar-day"></i>
                </div>
                <div class="stat-number"><?= number_format($transactions_today) ?></div>
                <div class="stat-label">Transactions Today</div>
            </div>
        </div>
        
        <!-- Machines Management -->
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h3>
                        <i class="bi bi-cpu"></i> Your Machines (<?= count($machines) ?>)
                    </h3>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addMachineModal">
                        <i class="bi bi-plus-circle"></i> Add Machine
                    </button>
                </div>
                
                <?php if (empty($machines)): ?>
                <!-- Empty State -->
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="bi bi-cpu"></i>
                    </div>
                    <h4>No Machines Registered</h4>
                    <p>Start by registering your first Nayax vending machine to begin accepting QR coin payments.</p>
                    <button class="btn btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#addMachineModal">
                        <i class="bi bi-plus-circle"></i> Register Your First Machine
                    </button>
                </div>
                
                <?php else: ?>
                
                <!-- Machine Cards -->
                <?php foreach ($machines as $machine): 
                    $machine_analytics = $analytics[$machine['nayax_machine_id']];
                    $stats = $machine_analytics['stats'];
                    $qr_products = $machine_analytics['qr_products'];
                    $qr_code = $machine_analytics['qr_code'];
                    
                    $status_class = $machine['status'] === 'active' ? 'online' : 
                                   ($machine['status'] === 'offline' ? 'offline' : 'maintenance');
                ?>
                <div class="machine-card <?= $status_class ?>">
                    <div class="machine-header">
                        <div class="d-flex align-items-center">
                            <div class="me-3">
                                <h5 class="mb-1"><?= htmlspecialchars($machine['machine_name']) ?></h5>
                                <small class="text-muted">ID: <?= htmlspecialchars($machine['nayax_machine_id']) ?></small>
                            </div>
                        </div>
                        <div>
                            <span class="machine-status status-<?= $machine['status'] ?>">
                                <?= ucfirst($machine['status']) ?>
                            </span>
                        </div>
                    </div>
                    
                    <!-- Machine Statistics -->
                    <div class="machine-stats">
                        <div class="mini-stat">
                            <div class="mini-stat-number">$<?= number_format($stats['total_revenue_cents'] / 100, 2) ?></div>
                            <div class="mini-stat-label">Revenue</div>
                        </div>
                        <div class="mini-stat">
                            <div class="mini-stat-number"><?= number_format($stats['total_transactions']) ?></div>
                            <div class="mini-stat-label">Transactions</div>
                        </div>
                        <div class="mini-stat">
                            <div class="mini-stat-number"><?= number_format($stats['total_coins_awarded']) ?></div>
                            <div class="mini-stat-label">Coins Awarded</div>
                        </div>
                        <div class="mini-stat">
                            <div class="mini-stat-number"><?= number_format($stats['transactions_today']) ?></div>
                            <div class="mini-stat-label">Today</div>
                        </div>
                    </div>
                    
                    <!-- QR Code Section -->
                    <?php if ($qr_code['exists']): ?>
                    <div class="qr-code-preview">
                        <img src="<?= htmlspecialchars($qr_code['web_path']) ?>" alt="QR Code" class="qr-image">
                        <div>
                            <small class="text-muted">
                                QR Code generated <?= $qr_code['created_at'] ?>
                            </small>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- QR Coin Products -->
                    <?php if (!empty($qr_products)): ?>
                    <div class="coin-products-list">
                        <h6 class="mb-2">
                            <i class="bi bi-coin"></i> QR Coin Products (<?= count($qr_products) ?>)
                        </h6>
                        <?php foreach ($qr_products as $product): ?>
                        <div class="product-item">
                            <div>
                                <strong><?= htmlspecialchars($product['product_name']) ?></strong><br>
                                <small class="text-muted"><?= number_format($product['qr_coin_amount']) ?> coins</small>
                            </div>
                            <div class="text-end">
                                <strong>$<?= number_format($product['price_cents'] / 100, 2) ?></strong>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Action Buttons -->
                    <div class="action-buttons mt-3">
                        <?php if (!$qr_code['exists']): ?>
                        <form method="post" class="d-inline">
                            <input type="hidden" name="action" value="generate_qr">
                            <input type="hidden" name="machine_id" value="<?= htmlspecialchars($machine['nayax_machine_id']) ?>">
                            <button type="submit" class="btn btn-primary btn-action">
                                <i class="bi bi-qr-code"></i> Generate QR Code
                            </button>
                        </form>
                        <?php else: ?>
                        <a href="<?= htmlspecialchars($qr_code['web_path']) ?>" class="btn btn-outline-primary btn-action" download>
                            <i class="bi bi-download"></i> Download QR
                        </a>
                        <?php endif; ?>
                        
                        <button class="btn btn-outline-secondary btn-action" onclick="viewMachineDetails('<?= htmlspecialchars($machine['nayax_machine_id']) ?>')">
                            <i class="bi bi-bar-chart"></i> Analytics
                        </button>
                        
                        <button class="btn btn-outline-info btn-action" onclick="manageCoinProducts('<?= htmlspecialchars($machine['nayax_machine_id']) ?>')">
                            <i class="bi bi-coin"></i> Coin Products
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Discount Analytics -->
        <?php if ($discount_analytics): ?>
        <div class="row mt-5">
            <div class="col-12">
                <h3 class="mb-4">
                    <i class="bi bi-ticket-perforated"></i> Discount Code Analytics (Last 30 Days)
                </h3>
                
                <div class="row">
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-number"><?= $discount_analytics['total_codes_sold'] ?></div>
                            <div class="stat-label">Codes Sold</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-number"><?= $discount_analytics['codes_redeemed'] ?></div>
                            <div class="stat-label">Codes Redeemed</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-number"><?= $discount_analytics['redemption_rate'] ?>%</div>
                            <div class="stat-label">Redemption Rate</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-number"><?= number_format($discount_analytics['total_coins_revenue']) ?></div>
                            <div class="stat-label">Coins Revenue</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Add Machine Modal -->
    <div class="modal fade" id="addMachineModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-plus-circle"></i> Register New Machine
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_machine">
                        
                        <div class="mb-3">
                            <label class="form-label">Machine ID *</label>
                            <input type="text" class="form-control" name="new_machine_id" required 
                                   placeholder="e.g., VM001, SNACK_MACHINE_01">
                            <small class="text-muted">Unique identifier from your Nayax system</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Device ID *</label>
                            <input type="text" class="form-control" name="new_device_id" required 
                                   placeholder="e.g., DEV123456">
                            <small class="text-muted">Physical device identifier</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Machine Name *</label>
                            <input type="text" class="form-control" name="new_machine_name" required 
                                   placeholder="e.g., Office Snack Machine, Lobby Drinks">
                            <small class="text-muted">Friendly name for identification</small>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i>
                            <strong>Next Steps:</strong> After registration, QR coin products will be automatically created for this machine.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-plus-circle"></i> Register Machine
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // View machine details
        function viewMachineDetails(machineId) {
            window.location.href = `/html/business/nayax-analytics.php?machine_id=${machineId}`;
        }
        
        // Manage coin products
        function manageCoinProducts(machineId) {
            window.location.href = `/html/business/nayax-products.php?machine_id=${machineId}`;
        }
        
        // Auto-refresh statistics
        function refreshStats() {
            // Implementation for live stats refresh
            console.log('Refreshing statistics...');
        }
        
        // Refresh every 5 minutes
        setInterval(refreshStats, 300000);
        
        // Track dashboard view
        fetch('/html/api/track-analytics.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                event: 'business_dashboard_view',
                data: {
                    business_id: <?= $business_id ?>,
                    machines_count: <?= count($machines) ?>,
                    total_revenue: <?= $total_revenue ?>,
                    total_transactions: <?= $total_transactions ?>
                }
            })
        }).catch(console.error);
    </script>
</body>
</html> 