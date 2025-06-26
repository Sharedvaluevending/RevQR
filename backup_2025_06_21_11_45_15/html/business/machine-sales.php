<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';

// Check if user is authenticated for business access
if (!isset($_SESSION['user_id'])) {
    // If not authenticated, redirect to the public version
    if (isset($_GET['machine'])) {
        $machine_param = urlencode($_GET['machine']);
        header("Location: " . APP_URL . "/public/machine-sales.php?machine=" . $machine_param);
        exit();
    } else {
        header("Location: " . APP_URL . "/public/machine-sales.php");
        exit();
    }
}

$message = '';
$message_type = '';
$machine = null;
$promotions = [];
$items = [];

// Get machine details
if (isset($_GET['machine'])) {
    $machine_name = $_GET['machine'];
    
    // Get machine info - only show machines belonging to the authenticated user
    $stmt = $pdo->prepare("
        SELECT m.*, b.name as business_name
        FROM machines m
        JOIN businesses b ON m.business_id = b.id
        WHERE m.name = ? AND b.user_id = ?
    ");
    $stmt->execute([$machine_name, $_SESSION['user_id']]);
    $machine = $stmt->fetch();
    
    if ($machine) {
        // Track engagement - record that someone scanned/viewed this machine's promotions
        $user_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $view_type = isset($_GET['view']) && $_GET['view'] === 'promotions' ? 'promotion_view' : 'machine_view';
        
        try {
            // Insert engagement tracking
            $stmt = $pdo->prepare("
                INSERT INTO machine_engagement (
                    machine_id, business_id, view_type, user_ip, user_agent, created_at
                ) VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$machine['id'], $machine['business_id'], $view_type, $user_ip, $user_agent]);
        } catch (Exception $e) {
            // If table doesn't exist, try to create it
            try {
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS machine_engagement (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        machine_id INT NOT NULL,
                        business_id INT NOT NULL,
                        view_type ENUM('machine_view', 'promotion_view') NOT NULL,
                        user_ip VARCHAR(45),
                        user_agent TEXT,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        INDEX idx_machine_engagement_machine (machine_id),
                        INDEX idx_machine_engagement_business (business_id),
                        INDEX idx_machine_engagement_type (view_type),
                        INDEX idx_machine_engagement_date (created_at)
                    )
                ");
                // Try again
                $stmt = $pdo->prepare("
                    INSERT INTO machine_engagement (
                        machine_id, business_id, view_type, user_ip, user_agent, created_at
                    ) VALUES (?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$machine['id'], $machine['business_id'], $view_type, $user_ip, $user_agent]);
            } catch (Exception $e2) {
                // Silently fail tracking if we can't create the table
                error_log("Machine engagement tracking failed: " . $e2->getMessage());
            }
        }
        
        // Get current promotions
        $stmt = $pdo->prepare("
            SELECT p.*, vli.item_name, vli.retail_price, vl.name as list_name
            FROM promotions p
            JOIN voting_list_items vli ON p.item_id = vli.id
            JOIN voting_lists vl ON p.list_id = vl.id
            WHERE p.business_id = ? 
            AND p.status = 'active'
            AND CURDATE() BETWEEN p.start_date AND p.end_date
            ORDER BY p.created_at DESC
        ");
        $stmt->execute([$machine['business_id']]);
        $promotions = $stmt->fetchAll();
        
        // Get machine items - using a fallback if machines table doesn't have items directly
        try {
            $stmt = $pdo->prepare("
                SELECT vli.*, vl.name as list_name
                FROM voting_list_items vli
                JOIN voting_lists vl ON vli.list_id = vl.id
                WHERE vl.business_id = ?
                AND vli.status = 'active'
                ORDER BY vl.name, vli.item_name
            ");
            $stmt->execute([$machine['business_id']]);
            $items = $stmt->fetchAll();
        } catch (Exception $e) {
            $items = [];
        }
    } else {
        // Machine not found or doesn't belong to this user, redirect to public version
        $machine_param = urlencode($_GET['machine']);
        header("Location: " . APP_URL . "/public/machine-sales.php?machine=" . $machine_param);
        exit();
    }
} else {
    // No machine specified, show error or redirect
    $message = 'No machine specified.';
    $message_type = 'warning';
}

// Check if we should show only promotions
$show_only_promotions = isset($_GET['view']) && $_GET['view'] === 'promotions';

require_once __DIR__ . '/../core/includes/header.php';
?>

<div class="container py-4">
    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if ($machine): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="h3 mb-2"><?php echo htmlspecialchars($machine['name']); ?></h1>
                        <p class="text-muted mb-0"><?php echo htmlspecialchars($machine['business_name']); ?></p>
                    </div>
                    <div>
                        <a href="manage-machine.php?id=<?php echo $machine['id']; ?>" class="btn btn-outline-primary">
                            <i class="bi bi-gear me-1"></i>Manage Machine
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <?php if (!empty($machine['header_image'])): ?>
            <div class="mb-4 text-center">
                <img src="<?php echo htmlspecialchars($machine['header_image']); ?>" alt="Header Image" class="img-fluid rounded" style="max-height:220px; width:100%; object-fit:cover;">
            </div>
        <?php endif; ?>

        <!-- Machine Information -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card bg-light">
                    <div class="card-body">
                        <h5 class="card-title mb-3">
                            <i class="bi bi-info-circle me-2"></i>Machine Information
                        </h5>
                        <div class="row">
                            <div class="col-md-6 mb-2">
                                <small class="text-muted d-block">Location</small>
                                <strong><?php echo htmlspecialchars($machine['location'] ?? 'Not specified'); ?></strong>
                            </div>
                            <div class="col-md-6 mb-2">
                                <small class="text-muted d-block">Created Date</small>
                                <strong><?php echo date('M j, Y', strtotime($machine['created_at'])); ?></strong>
                            </div>
                            <div class="col-md-6 mb-2">
                                <small class="text-muted d-block">Machine ID</small>
                                <strong>#<?php echo $machine['id']; ?></strong>
                            </div>
                            <div class="col-md-6 mb-2">
                                <small class="text-muted d-block">Description</small>
                                <strong><?php echo htmlspecialchars($machine['description'] ?? 'No description'); ?></strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title mb-3">
                            <i class="bi bi-lightning me-2"></i>Quick Actions
                        </h5>
                        <div class="row">
                            <div class="col-md-3 mb-2">
                                <a href="manual-sales.php?machine_id=<?php echo $machine['id']; ?>" class="btn btn-success w-100">
                                    <i class="bi bi-plus-circle me-1"></i>Record Sale
                                </a>
                            </div>
                            <div class="col-md-3 mb-2">
                                <a href="promotions.php?machine_id=<?php echo $machine['id']; ?>" class="btn btn-warning w-100">
                                    <i class="bi bi-star me-1"></i>Create Promotion
                                </a>
                            </div>
                            <div class="col-md-3 mb-2">
                                <a href="view-qr.php?machine_id=<?php echo $machine['id']; ?>" class="btn btn-info w-100">
                                    <i class="bi bi-qr-code me-1"></i>View QR Code
                                </a>
                            </div>
                            <div class="col-md-3 mb-2">
                                <a href="stock-management.php?machine_id=<?php echo $machine['id']; ?>" class="btn btn-secondary w-100">
                                    <i class="bi bi-box me-1"></i>Manage Stock
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Current Promotions -->
        <?php if (!empty($promotions)): ?>
            <div class="row mb-4">
                <div class="col-12">
                    <h4 class="mb-3">
                        <i class="bi bi-star text-warning me-2"></i>Current Promotions
                        <span class="badge bg-success ms-2"><?php echo count($promotions); ?> active</span>
                    </h4>
                    <div class="row">
                        <?php foreach ($promotions as $promo): ?>
                            <div class="col-md-6 col-lg-4 mb-3">
                                <div class="card h-100 border-success">
                                    <div class="card-body">
                                        <h5 class="card-title">
                                            <?php echo htmlspecialchars($promo['item_name']); ?>
                                            <span class="badge bg-success ms-2">Sale</span>
                                        </h5>
                                        <div class="mb-2">
                                            <?php if ($promo['discount_type'] === 'percentage'): ?>
                                                <span class="h4 text-success"><?php echo $promo['discount_value']; ?>% OFF</span>
                                            <?php else: ?>
                                                <span class="h4 text-success">$<?php echo number_format($promo['discount_value'], 2); ?> OFF</span>
                                            <?php endif; ?>
                                        </div>
                                        <p class="card-text">
                                            <span class="text-muted">Regular: </span>
                                            <del class="text-muted">$<?php echo number_format($promo['retail_price'], 2); ?></del><br>
                                            <span class="text-success fw-bold">
                                                Sale Price: $<?php 
                                                    if ($promo['discount_type'] === 'percentage') {
                                                        $sale_price = $promo['retail_price'] * (1 - $promo['discount_value'] / 100);
                                                    } else {
                                                        $sale_price = $promo['retail_price'] - $promo['discount_value'];
                                                    }
                                                    echo number_format(max(0, $sale_price), 2);
                                                ?>
                                            </span>
                                        </p>
                                        <?php if (!empty($promo['description'])): ?>
                                            <p class="card-text small text-muted">
                                                <?php echo htmlspecialchars($promo['description']); ?>
                                            </p>
                                        <?php endif; ?>
                                        <p class="card-text small">
                                            <span class="text-muted">Valid until:</span> 
                                            <strong><?php echo date('M j, Y', strtotime($promo['end_date'])); ?></strong>
                                        </p>
                                        <div class="small text-muted mb-2">
                                            <strong>Promo Code:</strong> <?php echo htmlspecialchars($promo['promo_code']); ?>
                                        </div>
                                        <div class="d-flex gap-2">
                                            <a href="edit-campaign.php?id=<?php echo $promo['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-pencil me-1"></i>Edit
                                            </a>
                                            <a href="promotions.php?delete=<?php echo $promo['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this promotion?')">
                                                <i class="bi bi-trash me-1"></i>Delete
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="row mb-4">
                <div class="col-12">
                    <div class="alert alert-info">
                        <h5><i class="bi bi-info-circle me-2"></i>No Current Promotions</h5>
                        <p class="mb-2">Create your first promotion to boost sales and customer engagement!</p>
                        <a href="promotions.php?machine_id=<?php echo $machine['id']; ?>" class="btn btn-warning">
                            <i class="bi bi-star me-1"></i>Create Promotion
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Available Items -->
        <?php if (!$show_only_promotions && !empty($items)): ?>
            <div class="row">
                <div class="col-12">
                    <h4 class="mb-3">
                        <i class="bi bi-grid me-2"></i>Available Items
                        <span class="badge bg-primary ms-2"><?php echo count($items); ?> items</span>
                    </h4>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Item</th>
                                    <th>Category</th>
                                    <th>Price</th>
                                    <th>List</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items as $item): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($item['item_name']); ?></strong>
                                            <?php if (isset($item['high_margin']) && $item['high_margin']): ?>
                                                <span class="badge bg-success ms-1">High Margin</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($item['item_category'] ?? 'Uncategorized'); ?></td>
                                        <td class="fw-bold">$<?php echo number_format($item['retail_price'], 2); ?></td>
                                        <td class="text-muted"><?php echo htmlspecialchars($item['list_name']); ?></td>
                                        <td>
                                            <div class="d-flex gap-1">
                                                <a href="manual-sales.php?machine_id=<?php echo $machine['id']; ?>&item_id=<?php echo $item['id']; ?>" class="btn btn-sm btn-success" title="Record Sale">
                                                    <i class="bi bi-plus-circle"></i>
                                                </a>
                                                <a href="promotions.php?machine_id=<?php echo $machine['id']; ?>&item_id=<?php echo $item['id']; ?>" class="btn btn-sm btn-warning" title="Create Promotion">
                                                    <i class="bi bi-star"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <div class="alert alert-warning">
            <h5><i class="bi bi-exclamation-triangle me-2"></i>Machine Not Found</h5>
            <p class="mb-2">The requested machine was not found or you don't have access to it.</p>
            <a href="dashboard.php" class="btn btn-primary">
                <i class="bi bi-house me-1"></i>Back to Dashboard
            </a>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../core/includes/footer.php'; ?> 