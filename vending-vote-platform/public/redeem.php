<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/functions.php';

$message = '';
$message_type = '';
$promotion = null;

// Get promotion code from URL
$promo_code = $_GET['code'] ?? '';

if ($promo_code) {
    // Validate and get promotion details
    $stmt = $pdo->prepare("
        SELECT p.*, i.name as item_name, i.retail_price, b.name as business_name
        FROM promotions p
        JOIN items i ON p.item_id = i.id
        JOIN businesses b ON p.business_id = b.id
        WHERE p.promo_code = ? 
        AND p.status = 'active'
        AND CURDATE() BETWEEN p.start_date AND p.end_date
    ");
    $stmt->execute([$promo_code]);
    $promotion = $stmt->fetch();
    
    if ($promotion) {
        // Check if user has already redeemed this promotion
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM promotion_redemptions 
            WHERE promotion_id = ? AND user_ip = ?
        ");
        $stmt->execute([$promotion['id'], get_client_ip()]);
        
        if ($stmt->fetchColumn() > 0) {
            $message = "You have already redeemed this promotion.";
            $message_type = "warning";
            $promotion = null;
        }
    } else {
        $message = "Invalid or expired promotion code.";
        $message_type = "danger";
    }
}

// Handle redemption
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $promotion) {
    $stmt = $pdo->prepare("
        INSERT INTO promotion_redemptions (
            promotion_id, business_id, user_ip, discount_value
        ) VALUES (?, ?, ?, ?)
    ");
    
    $discount_value = $promotion['discount_type'] === 'percentage' 
        ? ($promotion['retail_price'] * $promotion['discount_value'] / 100)
        : $promotion['discount_value'];
    
    if ($stmt->execute([
        $promotion['id'],
        $promotion['business_id'],
        get_client_ip(),
        $discount_value
    ])) {
        $message = "Promotion redeemed successfully!";
        $message_type = "success";
        $promotion = null;
    } else {
        $message = "Error redeeming promotion. Please try again.";
        $message_type = "danger";
    }
}

require_once __DIR__ . '/../core/includes/header.php';
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($promotion): ?>
                <div class="card">
                    <div class="card-body">
                        <div class="text-center mb-4">
                            <i class="bi bi-tag display-1 text-primary"></i>
                            <h2 class="mt-3">Promotion Available!</h2>
                        </div>
                        
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <h5>Item Details</h5>
                                <p class="mb-1">
                                    <strong><?php echo htmlspecialchars($promotion['item_name']); ?></strong>
                                </p>
                                <p class="text-muted mb-0">
                                    <?php echo htmlspecialchars($promotion['business_name']); ?>
                                </p>
                            </div>
                            <div class="col-md-6 text-md-end">
                                <h5>Discount</h5>
                                <p class="mb-1">
                                    <?php if ($promotion['discount_type'] === 'percentage'): ?>
                                        <?php echo $promotion['discount_value']; ?>% off
                                    <?php else: ?>
                                        $<?php echo number_format($promotion['discount_value'], 2); ?> off
                                    <?php endif; ?>
                                </p>
                                <p class="text-muted mb-0">
                                    Original Price: $<?php echo number_format($promotion['retail_price'], 2); ?>
                                </p>
                            </div>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            This promotion is valid until <?php echo date('F j, Y', strtotime($promotion['end_date'])); ?>
                        </div>
                        
                        <form method="POST" class="text-center">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="bi bi-check-circle me-2"></i>Redeem Now
                            </button>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <div class="card">
                    <div class="card-body text-center">
                        <i class="bi bi-tag display-1 text-muted mb-3"></i>
                        <h3>Promotion Not Found</h3>
                        <p class="text-muted">The promotion code you entered is invalid or has expired.</p>
                        <a href="<?php echo APP_URL; ?>/user/dashboard.php" class="btn btn-primary">
                            <i class="bi bi-house me-2"></i>Return to Dashboard
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../core/includes/footer.php'; ?> 