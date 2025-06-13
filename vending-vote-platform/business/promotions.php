<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/functions.php';

// Ensure business access
require_role('business');

// Get business ID from session or database - ensure compatibility with main system
$business_id = null;
if (isset($_SESSION['business_id'])) {
    $business_id = $_SESSION['business_id'];
} else {
    // Fallback: get business_id from user_id like main system
    $stmt = $pdo->prepare("
        SELECT b.id 
        FROM businesses b 
        JOIN users u ON b.id = u.business_id 
        WHERE u.id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $business = $stmt->fetch();
    $business_id = $business ? $business['id'] : null;
    
    // Store in session for future use
    if ($business_id) {
        $_SESSION['business_id'] = $business_id;
    }
}

if (!$business_id) {
    die('Business not found.');
}

$message = '';
$message_type = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create_promotion':
            $item_id = $_POST['item_id'] ?? null;
            $discount_type = $_POST['discount_type'] ?? null;
            $discount_value = $_POST['discount_value'] ?? null;
            $start_date = $_POST['start_date'] ?? null;
            $end_date = $_POST['end_date'] ?? null;
            $promo_code = generate_promo_code();
            
            if ($item_id && $discount_type && $discount_value && $start_date && $end_date) {
                $stmt = $pdo->prepare("
                    INSERT INTO promotions (
                        item_id, business_id, discount_type, discount_value,
                        start_date, end_date, promo_code, status
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, 'active')
                ");
                
                if ($stmt->execute([$item_id, $business_id, $discount_type, $discount_value, $start_date, $end_date, $promo_code])) {
                    $message = "Promotion created successfully!";
                    $message_type = "success";
                } else {
                    $message = "Error creating promotion.";
                    $message_type = "danger";
                }
            }
            break;
            
        case 'update_promotion':
            $promo_id = $_POST['promo_id'] ?? null;
            $status = $_POST['status'] ?? null;
            
            if ($promo_id && $status) {
                $stmt = $pdo->prepare("
                    UPDATE promotions 
                    SET status = ? 
                    WHERE id = ? AND business_id = ?
                ");
                
                if ($stmt->execute([$status, $promo_id, $business_id])) {
                    $message = "Promotion updated successfully!";
                    $message_type = "success";
                } else {
                    $message = "Error updating promotion.";
                    $message_type = "danger";
                }
            }
            break;
    }
}

// Get active promotions
$stmt = $pdo->prepare("
    SELECT p.*, i.name as item_name, i.retail_price
    FROM promotions p
    JOIN items i ON p.item_id = i.id
    WHERE p.business_id = ?
    ORDER BY p.start_date DESC
");
$stmt->execute([$business_id]);
$promotions = $stmt->fetchAll();

// Get available items for new promotions
$stmt = $pdo->prepare("
    SELECT id, name, retail_price
    FROM items
    WHERE business_id = ? AND status = 'active'
    ORDER BY name
");
$stmt->execute([$business_id]);
$items = $stmt->fetchAll();

require_once __DIR__ . '/../core/includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Promotions Management</h1>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newPromotionModal">
            <i class="bi bi-plus-circle me-2"></i>New Promotion
        </button>
    </div>
    
    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <!-- Active Promotions -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0">Active Promotions</h5>
        </div>
        <div class="card-body">
            <?php if (empty($promotions)): ?>
                <p class="text-muted text-center mb-0">No active promotions</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Discount</th>
                                <th>Promo Code</th>
                                <th>Duration</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($promotions as $promo): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div>
                                                <h6 class="mb-0"><?php echo htmlspecialchars($promo['item_name']); ?></h6>
                                                <small class="text-muted">$<?php echo number_format($promo['retail_price'], 2); ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($promo['discount_type'] === 'percentage'): ?>
                                            <?php echo $promo['discount_value']; ?>% off
                                        <?php else: ?>
                                            $<?php echo number_format($promo['discount_value'], 2); ?> off
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <code><?php echo $promo['promo_code']; ?></code>
                                    </td>
                                    <td>
                                        <?php echo date('M d, Y', strtotime($promo['start_date'])); ?> -
                                        <?php echo date('M d, Y', strtotime($promo['end_date'])); ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $promo['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                            <?php echo ucfirst($promo['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <button type="button" class="btn btn-sm btn-outline-primary" 
                                                    onclick="generateQR('<?php echo $promo['promo_code']; ?>')">
                                                <i class="bi bi-qr-code"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" 
                                                    onclick="togglePromotion(<?php echo $promo['id']; ?>, '<?php echo $promo['status']; ?>')">
                                                <i class="bi bi-toggle-on"></i>
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
    
    <!-- Promotion Stats -->
    <div class="row">
        <div class="col-md-4">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h6 class="card-title">Active Promotions</h6>
                    <h2 class="mb-0">
                        <?php echo count(array_filter($promotions, function($p) { return $p['status'] === 'active'; })); ?>
                    </h2>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h6 class="card-title">Total Redemptions</h6>
                    <h2 class="mb-0">
                        <?php
                        $stmt = $pdo->prepare("
                            SELECT COUNT(*) FROM promotion_redemptions 
                            WHERE business_id = ?
                        ");
                        $stmt->execute([$business_id]);
                        echo $stmt->fetchColumn();
                        ?>
                    </h2>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h6 class="card-title">Revenue Impact</h6>
                    <h2 class="mb-0">
                        <?php
                        $stmt = $pdo->prepare("
                            SELECT SUM(discount_value) FROM promotion_redemptions 
                            WHERE business_id = ?
                        ");
                        $stmt->execute([$business_id]);
                        echo '$' . number_format($stmt->fetchColumn() ?? 0, 2);
                        ?>
                    </h2>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- New Promotion Modal -->
<div class="modal fade" id="newPromotionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="">
                <input type="hidden" name="action" value="create_promotion">
                
                <div class="modal-header">
                    <h5 class="modal-title">Create New Promotion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Item</label>
                        <select class="form-select" name="item_id" required>
                            <option value="">Select an item</option>
                            <?php foreach ($items as $item): ?>
                                <option value="<?php echo $item['id']; ?>">
                                    <?php echo htmlspecialchars($item['name']); ?> 
                                    ($<?php echo number_format($item['retail_price'], 2); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Discount Type</label>
                        <select class="form-select" name="discount_type" required>
                            <option value="percentage">Percentage</option>
                            <option value="fixed">Fixed Amount</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Discount Value</label>
                        <div class="input-group">
                            <span class="input-group-text" id="discountPrefix">%</span>
                            <input type="number" class="form-control" name="discount_value" 
                                   min="0" max="100" step="0.01" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Start Date</label>
                                <input type="date" class="form-control" name="start_date" 
                                       min="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">End Date</label>
                                <input type="date" class="form-control" name="end_date" 
                                       min="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Promotion</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- QR Code Modal -->
<div class="modal fade" id="qrCodeModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Promotion QR Code</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <div id="qrcode" class="mb-3"></div>
                <p class="text-muted small mb-0">Scan to redeem promotion</p>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
// Handle discount type change
document.querySelector('select[name="discount_type"]').addEventListener('change', function() {
    const prefix = this.value === 'percentage' ? '%' : '$';
    document.getElementById('discountPrefix').textContent = prefix;
    
    const input = document.querySelector('input[name="discount_value"]');
    if (this.value === 'percentage') {
        input.max = '100';
    } else {
        input.removeAttribute('max');
    }
});

// Generate QR Code
function generateQR(promoCode) {
    const modal = new bootstrap.Modal(document.getElementById('qrCodeModal'));
    const qrContainer = document.getElementById('qrcode');
    qrContainer.innerHTML = '';
    
    new QRCode(qrContainer, {
        text: `https://revenueqr.sharedvaluevending.com/redeem.php?code=${promoCode}`,
        width: 200,
        height: 200
    });
    
    modal.show();
}

// Toggle promotion status
function togglePromotion(promoId, currentStatus) {
    const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
    
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `
        <input type="hidden" name="action" value="update_promotion">
        <input type="hidden" name="promo_id" value="${promoId}">
        <input type="hidden" name="status" value="${newStatus}">
    `;
    
    document.body.appendChild(form);
    form.submit();
}
</script>

<?php require_once __DIR__ . '/../core/includes/footer.php'; ?> 