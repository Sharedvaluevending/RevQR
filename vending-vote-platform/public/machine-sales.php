<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/functions.php';

$machine_id = $_GET['machine'] ?? null;
$machine = null;
$sales = [];

if ($machine_id) {
    // Get machine details
    $stmt = $pdo->prepare("
        SELECT m.*, b.name as business_name
        FROM machines m
        JOIN businesses b ON m.business_id = b.id
        WHERE m.id = ?
    ");
    $stmt->execute([$machine_id]);
    $machine = $stmt->fetch();
    
    if ($machine) {
        // Get active sales for this machine
        $stmt = $pdo->prepare("
            SELECT ms.*, i.name as item_name, i.retail_price
            FROM machine_sales ms
            JOIN items i ON ms.item_id = i.id
            WHERE ms.machine_id = ?
            AND ms.status = 'active'
            AND CURDATE() BETWEEN ms.start_date AND ms.end_date
            ORDER BY ms.sale_price ASC
        ");
        $stmt->execute([$machine_id]);
        $sales = $stmt->fetchAll();
    }
}

require_once __DIR__ . '/../core/includes/header.php';
?>

<div class="container py-4">
    <?php if ($machine): ?>
        <div class="row justify-content-center">
            <div class="col-md-8">
                <!-- Machine Info -->
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <i class="bi bi-cart display-4 text-primary me-3"></i>
                            <div>
                                <h2 class="mb-1"><?php echo htmlspecialchars($machine['name']); ?></h2>
                                <p class="text-muted mb-0">
                                    <?php echo htmlspecialchars($machine['business_name']); ?> • 
                                    <?php echo htmlspecialchars($machine['location']); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Current Sales -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Current Sales</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($sales)): ?>
                            <div class="text-center py-4">
                                <i class="bi bi-tag display-1 text-muted mb-3"></i>
                                <h4>No Active Sales</h4>
                                <p class="text-muted">Check back later for new deals!</p>
                            </div>
                        <?php else: ?>
                            <div class="row g-4">
                                <?php foreach ($sales as $sale): ?>
                                    <div class="col-md-6">
                                        <div class="card h-100 border-success">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                    <h5 class="card-title mb-0">
                                                        <?php echo htmlspecialchars($sale['item_name']); ?>
                                                    </h5>
                                                    <span class="badge bg-success">
                                                        $<?php echo number_format($sale['sale_price'], 2); ?>
                                                    </span>
                                                </div>
                                                <p class="text-muted mb-0">
                                                    <del>$<?php echo number_format($sale['retail_price'], 2); ?></del>
                                                    <span class="text-success ms-2">
                                                        Save $<?php echo number_format($sale['retail_price'] - $sale['sale_price'], 2); ?>
                                                    </span>
                                                </p>
                                                <div class="mt-3">
                                                    <small class="text-muted">
                                                        <i class="bi bi-calendar me-1"></i>
                                                        Valid until <?php echo date('M d, Y', strtotime($sale['end_date'])); ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-body text-center">
                        <i class="bi bi-exclamation-circle display-1 text-muted mb-3"></i>
                        <h3>Machine Not Found</h3>
                        <p class="text-muted">The machine you're looking for doesn't exist or has been removed.</p>
                        <a href="<?php echo APP_URL; ?>/user/dashboard.php" class="btn btn-primary">
                            <i class="bi bi-house me-2"></i>Return to Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../core/includes/footer.php'; ?> 