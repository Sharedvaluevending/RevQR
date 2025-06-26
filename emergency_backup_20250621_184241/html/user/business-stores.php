<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/qr_coin_manager.php';
require_once __DIR__ . '/../core/store_manager.php';

// Require user role
require_role('user');

// Get user QR coin balance
$user_qr_balance = QRCoinManager::getBalance($_SESSION['user_id']);

// Get available business store items
$business_stores = StoreManager::getAllBusinessStoreItems();

// Group by business for better display
$stores_by_business = [];
foreach ($business_stores as $item) {
    $stores_by_business[$item['business_id']][] = $item;
}

// Get business names
$business_names = [];
if (!empty($stores_by_business)) {
    $business_ids = array_keys($stores_by_business);
    $placeholders = str_repeat('?,', count($business_ids) - 1) . '?';
    $stmt = $pdo->prepare("SELECT id, name as business_name FROM businesses WHERE id IN ($placeholders)");
    $stmt->execute($business_ids);
    $business_names = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
}

require_once __DIR__ . '/../core/includes/header.php';
?>

<div class="container py-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="mb-0"><i class="bi bi-building-fill text-warning me-2"></i>Business Discount Stores</h1>
                    <p class="text-muted mb-0">Use your QR coins to get discounts at local vending machines</p>
                </div>
                <div class="text-end">
                    <div class="d-flex align-items-center">
                        <img src="../img/qrCoin.png" alt="QR Coin" style="width: 2rem; height: 2rem;" class="me-2">
                        <h4 class="text-warning mb-0"><?php echo number_format($user_qr_balance); ?></h4>
                    </div>
                    <small class="text-muted">Your QR Coin Balance</small>
                </div>
            </div>
        </div>
    </div>

    <?php if (empty($stores_by_business)): ?>
        <div class="row">
            <div class="col-12">
                <div class="card text-center py-5">
                    <div class="card-body">
                        <i class="bi bi-shop display-1 text-muted mb-3"></i>
                        <h3 class="text-muted mb-3">No Business Stores Available</h3>
                        <p class="text-muted mb-4">Business discount stores are coming soon! Keep earning QR coins by voting and spinning.</p>
                        <div class="d-flex justify-content-center gap-3">
                            <a href="vote.php" class="btn btn-primary">
                                <i class="bi bi-check2-square me-1"></i>Vote for Items
                            </a>
                            <a href="spin.php" class="btn btn-warning">
                                <i class="bi bi-trophy me-1"></i>Spin to Win
                            </a>
                            <a href="dashboard.php" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left me-1"></i>Back to Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($stores_by_business as $business_id => $items): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 shadow-sm">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">
                                <i class="bi bi-building me-2"></i>
                                <?php echo htmlspecialchars($business_names[$business_id] ?? 'Business #' . $business_id); ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <?php foreach ($items as $item): ?>
                                    <div class="col-12">
                                        <div class="card border-0 bg-light">
                                            <div class="card-body p-3">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div class="flex-grow-1">
                                                        <h6 class="mb-2"><?php echo htmlspecialchars($item['item_name']); ?></h6>
                                                        <?php if ($item['item_description']): ?>
                                                            <p class="text-muted small mb-2"><?php echo htmlspecialchars($item['item_description']); ?></p>
                                                        <?php endif; ?>
                                                        <div class="d-flex align-items-center gap-2 mb-2">
                                                            <span class="badge bg-success"><?php echo $item['discount_percentage']; ?>% OFF</span>
                                                            <small class="text-muted">
                                                                Save $<?php echo number_format(($item['regular_price_cents'] * $item['discount_percentage'] / 100) / 100, 2); ?>
                                                            </small>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="d-flex justify-content-between align-items-center mt-3">
                                                    <div class="d-flex align-items-center">
                                                        <img src="../img/qrCoin.png" alt="QR Coin" style="width: 1.5rem; height: 1.5rem;" class="me-1">
                                                        <strong class="text-warning"><?php echo number_format($item['qr_coin_cost']); ?></strong>
                                                    </div>
                                                    <?php if ($user_qr_balance >= $item['qr_coin_cost']): ?>
                                                        <button class="btn btn-primary btn-sm purchase-btn" 
                                                                data-item-id="<?php echo $item['id']; ?>"
                                                                data-item-name="<?php echo htmlspecialchars($item['item_name']); ?>"
                                                                data-qr-cost="<?php echo $item['qr_coin_cost']; ?>">
                                                            <i class="bi bi-cart-plus me-1"></i>Purchase
                                                        </button>
                                                    <?php else: ?>
                                                        <button class="btn btn-outline-secondary btn-sm" disabled>
                                                            <i class="bi bi-wallet me-1"></i>Need <?php echo number_format($item['qr_coin_cost'] - $user_qr_balance); ?> more
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Purchase History -->
        <div class="row mt-5">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>Your Purchase History</h5>
                        <a href="qr-transactions.php" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-list me-1"></i>View All Transactions
                        </a>
                    </div>
                    <div class="card-body">
                        <div id="purchase-history">
                            <div class="text-center py-3">
                                <div class="spinner-border spinner-border-sm text-primary" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                                <p class="text-muted mt-2 mb-0">Loading purchase history...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Purchase Confirmation Modal -->
<div class="modal fade" id="purchaseModal" tabindex="-1" aria-labelledby="purchaseModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="purchaseModalLabel">
                    <i class="bi bi-cart-check me-2"></i>Confirm Purchase
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="text-center">
                    <i class="bi bi-question-circle display-4 text-warning mb-3"></i>
                    <h5 id="confirm-item-name"></h5>
                    <p class="text-muted mb-3">Are you sure you want to purchase this discount?</p>
                    <div class="d-flex justify-content-center align-items-center gap-2 mb-3">
                        <img src="../img/qrCoin.png" alt="QR Coin" style="width: 2rem; height: 2rem;">
                        <h4 class="text-warning mb-0" id="confirm-qr-cost"></h4>
                        <span class="text-muted">QR Coins</span>
                    </div>
                    <div class="alert alert-info">
                        <small>
                            <i class="bi bi-info-circle me-1"></i>
                            You'll receive a unique code to redeem this discount at the vending machine.
                        </small>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x me-1"></i>Cancel
                </button>
                <button type="button" class="btn btn-primary" id="confirm-purchase">
                    <i class="bi bi-cart-check me-1"></i>Confirm Purchase
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const purchaseModal = new bootstrap.Modal(document.getElementById('purchaseModal'));
    let currentItemId = null;
    
    // Handle purchase button clicks
    document.querySelectorAll('.purchase-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            currentItemId = this.dataset.itemId;
            document.getElementById('confirm-item-name').textContent = this.dataset.itemName;
            document.getElementById('confirm-qr-cost').textContent = Number(this.dataset.qrCost).toLocaleString();
            purchaseModal.show();
        });
    });
    
    // Handle purchase confirmation
    document.getElementById('confirm-purchase').addEventListener('click', function() {
        if (!currentItemId) return;
        
        const btn = this;
        btn.disabled = true;
        btn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Processing...';
        
        fetch('purchase-business-item.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                item_id: currentItemId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                purchaseModal.hide();
                
                // Show success message
                const alert = document.createElement('div');
                alert.className = 'alert alert-success alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-3';
                alert.style.zIndex = '9999';
                alert.innerHTML = `
                    <i class="bi bi-check-circle me-2"></i>
                    <strong>Purchase Successful!</strong> Your redemption code: <strong>${data.purchase_code}</strong>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                `;
                document.body.appendChild(alert);
                
                // Refresh page after 3 seconds to update balance
                setTimeout(() => {
                    window.location.reload();
                }, 3000);
            } else {
                alert('Purchase failed: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Purchase error:', error);
            alert('Purchase failed. Please try again.');
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-cart-check me-1"></i>Confirm Purchase';
        });
    });
    
    // Load purchase history
    loadPurchaseHistory();
});

function loadPurchaseHistory() {
    fetch('get-purchase-history.php?type=business&limit=5')
        .then(response => response.json())
        .then(data => {
            const container = document.getElementById('purchase-history');
            
            if (data.success && data.purchases.length > 0) {
                container.innerHTML = data.purchases.map(purchase => `
                    <div class="d-flex justify-content-between align-items-center p-3 border-bottom">
                        <div>
                            <h6 class="mb-1">${purchase.item_name}</h6>
                            <small class="text-muted">${purchase.business_name} • ${purchase.created_at}</small>
                            <div>
                                <span class="badge bg-${purchase.status === 'pending' ? 'warning' : purchase.status === 'redeemed' ? 'success' : 'secondary'}">
                                    ${purchase.status}
                                </span>
                                ${purchase.status === 'pending' ? `<code class="ms-2">${purchase.purchase_code}</code>` : ''}
                            </div>
                        </div>
                        <div class="text-end">
                            <div class="d-flex align-items-center">
                                <img src="../img/qrCoin.png" alt="QR Coin" style="width: 1.5rem; height: 1.5rem;" class="me-1">
                                <strong class="text-warning">${Number(purchase.qr_coins_spent).toLocaleString()}</strong>
                            </div>
                        </div>
                    </div>
                `).join('');
            } else {
                container.innerHTML = `
                    <div class="text-center py-4">
                        <i class="bi bi-cart display-4 text-muted mb-2"></i>
                        <p class="text-muted mb-0">No purchases yet. Start shopping to see your history here!</p>
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error loading history:', error);
            document.getElementById('purchase-history').innerHTML = `
                <div class="text-center py-4">
                    <i class="bi bi-exclamation-circle display-4 text-danger mb-2"></i>
                    <p class="text-danger mb-0">Error loading purchase history</p>
                </div>
            `;
        });
}
</script>

<?php require_once __DIR__ . '/../core/includes/footer.php'; ?> 