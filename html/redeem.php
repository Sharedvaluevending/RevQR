<?php
require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/core/session.php';
require_once __DIR__ . '/core/functions.php';

$message = '';
$message_type = '';
$promotion = null;

// Get client IP address
function getClientIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $_SERVER['REMOTE_ADDR'];
    }
}

// Get promotion code from URL
$promo_code = $_GET['code'] ?? '';

if ($promo_code) {
    // Validate and get promotion details
    $stmt = $pdo->prepare("
        SELECT p.*, vli.name as item_name, vli.retail_price, 
               vl.name as list_name, b.name as business_name
        FROM promotions p
        JOIN voting_list_items vli ON p.item_id = vli.id
        JOIN voting_lists vl ON p.list_id = vl.id
        JOIN businesses b ON p.business_id = b.id
        WHERE p.promo_code = ? 
        AND p.status = 'active'
        AND CURDATE() BETWEEN p.start_date AND p.end_date
    ");
    $stmt->execute([$promo_code]);
    $promotion = $stmt->fetch();
    
    if ($promotion) {
        // Check if user has already redeemed this promotion
        $user_ip = getClientIP();
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM promotion_redemptions 
            WHERE promotion_id = ? AND user_ip = ?
        ");
        $stmt->execute([$promotion['id'], $user_ip]);
        
        if ($stmt->fetchColumn() > 0) {
            $message = "You have already redeemed this promotion.";
            $message_type = "warning";
            $promotion = null;
        }
    } else {
        $message = "Invalid, expired, or inactive promotion code.";
        $message_type = "danger";
    }
}

// Handle redemption
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $promotion && isset($_POST['redeem'])) {
    $user_ip = getClientIP();
    
    // Calculate discount amount
    if ($promotion['discount_type'] === 'percentage') {
        $discount_amount = ($promotion['retail_price'] * $promotion['discount_value']) / 100;
    } else {
        $discount_amount = $promotion['discount_value'];
    }
    
    // Calculate final price
    $final_price = max(0, $promotion['retail_price'] - $discount_amount);
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO promotion_redemptions (
                promotion_id, business_id, user_ip, discount_amount
            ) VALUES (?, ?, ?, ?)
        ");
        
        if ($stmt->execute([
            $promotion['id'],
            $promotion['business_id'],
            $user_ip,
            $discount_amount
        ])) {
            $message = "Promotion redeemed successfully! Show this page to the business to claim your discount.";
            $message_type = "success";
            
            // Mark as redeemed but keep promotion data for display
            $promotion['redeemed'] = true;
            $promotion['discount_amount'] = $discount_amount;
            $promotion['final_price'] = $final_price;
        } else {
            $message = "Error redeeming promotion. Please try again.";
            $message_type = "danger";
        }
    } catch (Exception $e) {
        error_log("Redemption error: " . $e->getMessage());
        $message = "Error redeeming promotion. Please try again.";
        $message_type = "danger";
    }
}

require_once __DIR__ . '/core/includes/header.php';
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($promotion): ?>
                <div class="card">
                    <div class="card-header bg-<?php echo isset($promotion['redeemed']) ? 'success' : 'primary'; ?> text-white">
                        <div class="text-center">
                            <i class="bi bi-tag-fill display-1 mb-3"></i>
                            <h2 class="mb-0">
                                <?php echo isset($promotion['redeemed']) ? 'Promotion Redeemed!' : 'Promotion Available!'; ?>
                            </h2>
                        </div>
                    </div>
                    
                    <div class="card-body">
                        <div class="text-center mb-4">
                            <h4 class="text-primary"><?php echo htmlspecialchars($promotion['business_name']); ?></h4>
                            <p class="text-muted"><?php echo htmlspecialchars($promotion['list_name']); ?></p>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <h5>Item</h5>
                                <p class="mb-1">
                                    <strong><?php echo htmlspecialchars($promotion['item_name']); ?></strong>
                                </p>
                                <?php if (!empty($promotion['description'])): ?>
                                    <p class="text-muted small mb-0">
                                        <?php echo htmlspecialchars($promotion['description']); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                            
                            <div class="col-md-6 mb-3 text-md-end">
                                <h5>Pricing</h5>
                                <p class="mb-1">
                                    <span class="text-decoration-line-through text-muted">
                                        $<?php echo number_format($promotion['retail_price'], 2); ?>
                                    </span>
                                </p>
                                <p class="mb-1">
                                    <strong class="text-success h5">
                                        <?php if (isset($promotion['final_price'])): ?>
                                            $<?php echo number_format($promotion['final_price'], 2); ?>
                                        <?php else: ?>
                                            <?php
                                            if ($promotion['discount_type'] === 'percentage') {
                                                $discount = ($promotion['retail_price'] * $promotion['discount_value']) / 100;
                                            } else {
                                                $discount = $promotion['discount_value'];
                                            }
                                            echo '$' . number_format(max(0, $promotion['retail_price'] - $discount), 2);
                                            ?>
                                        <?php endif; ?>
                                    </strong>
                                </p>
                            </div>
                        </div>
                        
                        <div class="alert alert-light border">
                            <div class="row align-items-center">
                                <div class="col">
                                    <h6 class="mb-1">Your Discount</h6>
                                    <p class="mb-0">
                                        <?php if ($promotion['discount_type'] === 'percentage'): ?>
                                            <?php echo $promotion['discount_value']; ?>% off
                                        <?php else: ?>
                                            $<?php echo number_format($promotion['discount_value'], 2); ?> off
                                        <?php endif; ?>
                                    </p>
                                </div>
                                <div class="col-auto">
                                    <span class="badge bg-success p-3">
                                        Save: $<?php 
                                        if (isset($promotion['discount_amount'])) {
                                            echo number_format($promotion['discount_amount'], 2);
                                        } else {
                                            if ($promotion['discount_type'] === 'percentage') {
                                                echo number_format(($promotion['retail_price'] * $promotion['discount_value']) / 100, 2);
                                            } else {
                                                echo number_format($promotion['discount_value'], 2);
                                            }
                                        }
                                        ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            This promotion is valid until <?php echo date('F j, Y', strtotime($promotion['end_date'])); ?>
                        </div>
                        
                        <?php if (!isset($promotion['redeemed'])): ?>
                            <form method="POST" class="text-center">
                                <button type="submit" name="redeem" class="btn btn-success btn-lg">
                                    <i class="bi bi-check-circle me-2"></i>Redeem This Promotion
                                </button>
                                <p class="text-muted small mt-2">
                                    Click to redeem and show this page to the business
                                </p>
                            </form>
                        <?php else: ?>
                            <div class="text-center">
                                <div class="alert alert-success">
                                    <i class="bi bi-check-circle-fill me-2"></i>
                                    <strong>Redemption Successful!</strong>
                                    <br>Show this page to the business to claim your discount.
                                </div>
                                
                                <div class="mt-3">
                                    <p class="text-muted">
                                        <i class="bi bi-clock me-1"></i>
                                        Redeemed on <?php echo date('F j, Y \a\t g:i A'); ?>
                                    </p>
                                    <p class="text-muted small">
                                        Promo Code: <code><?php echo $promotion['promo_code']; ?></code>
                                    </p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php elseif (!empty($promo_code)): ?>
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="bi bi-exclamation-triangle text-warning" style="font-size: 3rem;"></i>
                        <h4 class="mt-3">Promotion Not Found</h4>
                        <p class="text-muted">
                            The promotion code you scanned is either invalid, expired, or has already been used.
                        </p>
                        <a href="/" class="btn btn-primary">
                            <i class="bi bi-house me-1"></i>Return Home
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="bi bi-qr-code-scan text-primary" style="font-size: 3rem;"></i>
                        <h4 class="mt-3">Scan a Promotion QR Code</h4>
                        <p class="text-muted">
                            Use your phone's camera to scan a promotion QR code to get started.
                        </p>
                        <div class="mt-4">
                            <form method="GET" class="d-inline-flex">
                                <input type="text" class="form-control me-2" name="code" 
                                       placeholder="Or enter promo code manually" style="max-width: 200px;">
                                <button type="submit" class="btn btn-outline-primary">
                                    <i class="bi bi-arrow-right"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/core/includes/footer.php'; ?> 