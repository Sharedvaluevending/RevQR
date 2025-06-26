<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';

$message = '';
$message_type = '';
$machine = null;
$promotions = [];
$items = [];

// Get machine details
if (isset($_GET['machine'])) {
    $machine_name = $_GET['machine'];
    
    // Get machine info
    $stmt = $pdo->prepare("
        SELECT m.*, b.name as business_name
        FROM machines m
        JOIN businesses b ON m.business_id = b.id
        WHERE m.name = ? AND m.status = 'active'
    ");
    $stmt->execute([$machine_name]);
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
    }
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
                <h1 class="h3 mb-2"><?php echo htmlspecialchars($machine['name']); ?></h1>
                <p class="text-muted mb-0"><?php echo htmlspecialchars($machine['business_name']); ?></p>
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
                        <h5 class="card-title mb-3">Machine Information</h5>
                        <div class="row">
                            <div class="col-6 mb-2">
                                <small class="text-muted d-block">Location</small>
                                <strong><?php echo htmlspecialchars($machine['location']); ?></strong>
                            </div>
                            <div class="col-6 mb-2">
                                <small class="text-muted d-block">Last Updated</small>
                                <strong><?php echo date('M j, Y', strtotime($machine['updated_at'])); ?></strong>
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
                                        <div class="small text-muted">
                                            <strong>Promo Code:</strong> <?php echo htmlspecialchars($promo['promo_code']); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                <h5><i class="bi bi-info-circle me-2"></i>No Current Promotions</h5>
                <p class="mb-0">Check back soon for special offers and discounts!</p>
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
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items as $item): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($item['item_name']); ?></strong>
                                            <?php if ($item['high_margin']): ?>
                                                <span class="badge bg-success ms-1">High Margin</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($item['item_category']); ?></td>
                                        <td class="fw-bold">$<?php echo number_format($item['retail_price'], 2); ?></td>
                                        <td class="text-muted"><?php echo htmlspecialchars($item['list_name']); ?></td>
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
            Machine not found or is inactive.
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../core/includes/footer.php'; ?> 