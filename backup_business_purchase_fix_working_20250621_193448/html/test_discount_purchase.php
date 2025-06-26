<?php
require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/core/session.php';
require_once __DIR__ . '/core/auth.php';
require_once __DIR__ . '/core/qr_coin_manager.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: /html/user/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$balance = QRCoinManager::getBalance($user_id);

// Get available discount items
$stmt = $pdo->prepare("
    SELECT bsi.id, bsi.item_name, bsi.item_description, bsi.qr_coin_cost,
           bsi.discount_percent, b.name as business_name
    FROM business_store_items bsi
    LEFT JOIN businesses b ON bsi.business_id = b.id
    WHERE bsi.category = 'discount' AND bsi.is_active = 1
    ORDER BY bsi.qr_coin_cost ASC
    LIMIT 10
");
$stmt->execute();
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Discount Purchase Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-dark text-light">
    <div class="container mt-4">
        <h1 class="mb-4">🎯 Discount Purchase Test</h1>
        
        <div class="alert alert-info">
            <strong>User:</strong> <?= $_SESSION['username'] ?? 'User' ?> (ID: <?= $user_id ?>)<br>
            <strong>Balance:</strong> <?= number_format($balance) ?> QR coins
        </div>
        
        <?php if (empty($items)): ?>
            <div class="alert alert-warning">
                <h5>No Discount Items Available</h5>
                <p>There are no discount items in the database. You may need to add some through the business interface.</p>
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($items as $item): ?>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card bg-secondary text-light">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h5 class="card-title"><?= htmlspecialchars($item['item_name']) ?></h5>
                                <span class="badge bg-success"><?= $item['discount_percent'] ?>% OFF</span>
                            </div>
                            
                            <?php if ($item['item_description']): ?>
                            <p class="card-text text-muted"><?= htmlspecialchars($item['item_description']) ?></p>
                            <?php endif; ?>
                            
                            <?php if ($item['business_name']): ?>
                            <p class="text-muted mb-2">
                                <i class="bi bi-shop"></i> <?= htmlspecialchars($item['business_name']) ?>
                            </p>
                            <?php endif; ?>
                            
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="bi bi-coin text-warning"></i> 
                                    <strong><?= number_format($item['qr_coin_cost']) ?> coins</strong>
                                </div>
                                
                                <button class="btn btn-success purchase-btn" 
                                        data-item-id="<?= $item['id'] ?>"
                                        data-item-name="<?= htmlspecialchars($item['item_name']) ?>"
                                        data-item-cost="<?= $item['qr_coin_cost'] ?>"
                                        <?= ($balance < $item['qr_coin_cost']) ? 'disabled' : '' ?>>
                                    <?php if ($balance < $item['qr_coin_cost']): ?>
                                        <i class="bi bi-lock"></i> Need More Coins
                                    <?php else: ?>
                                        <i class="bi bi-cart-plus"></i> Purchase
                                    <?php endif; ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <!-- Results Area -->
        <div id="results" class="mt-4" style="display: none;">
            <div class="card bg-secondary">
                <div class="card-header">
                    <h5><i class="bi bi-info-circle"></i> Purchase Result</h5>
                </div>
                <div class="card-body">
                    <div id="resultContent"></div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const purchaseButtons = document.querySelectorAll('.purchase-btn');
            
            purchaseButtons.forEach(button => {
                button.addEventListener('click', function() {
                    if (this.disabled) return;
                    
                    const itemId = this.dataset.itemId;
                    const itemName = this.dataset.itemName;
                    const itemCost = this.dataset.itemCost;
                    
                    purchaseDiscount(itemId, itemName, itemCost, this);
                });
            });
        });
        
        async function purchaseDiscount(itemId, itemName, itemCost, button) {
            const originalText = button.innerHTML;
            const results = document.getElementById('results');
            const resultContent = document.getElementById('resultContent');
            
            button.disabled = true;
            button.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Purchasing...';
            
            try {
                console.log('🛒 Starting purchase:', { itemId, itemName, itemCost });
                
                const response = await fetch('/html/api/purchase-discount.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        item_id: parseInt(itemId),
                        machine_id: 'test',
                        source: 'web_test'
                    })
                });
                
                console.log('📡 Response status:', response.status);
                console.log('📡 Response headers:', [...response.headers.entries()]);
                
                let result;
                const responseText = await response.text();
                console.log('📡 Raw response:', responseText);
                
                try {
                    result = JSON.parse(responseText);
                } catch (e) {
                    throw new Error(`Invalid JSON response: ${responseText.substring(0, 200)}...`);
                }
                
                // Show results
                results.style.display = 'block';
                
                if (result.success) {
                    resultContent.innerHTML = `
                        <div class="alert alert-success">
                            <h6><i class="bi bi-check-circle"></i> Purchase Successful!</h6>
                            <p><strong>Item:</strong> ${result.item_name}</p>
                            <p><strong>Discount Code:</strong> <code class="bg-dark text-warning p-2 rounded">${result.discount_code}</code></p>
                            <p><strong>Discount:</strong> ${result.discount_percent}%</p>
                            <p><strong>Expires:</strong> ${new Date(result.expires_at).toLocaleDateString()}</p>
                            <p><strong>QR Coins Spent:</strong> ${result.qr_coins_spent}</p>
                            <p><strong>New Balance:</strong> ${result.new_balance} coins</p>
                        </div>
                    `;
                    
                    button.innerHTML = '<i class="bi bi-check"></i> Purchased';
                    button.classList.remove('btn-success');
                    button.classList.add('btn-outline-success');
                    
                    // Update balance display
                    setTimeout(() => location.reload(), 2000);
                    
                } else {
                    resultContent.innerHTML = `
                        <div class="alert alert-danger">
                            <h6><i class="bi bi-x-circle"></i> Purchase Failed</h6>
                            <p><strong>Error:</strong> ${result.error || 'Unknown error'}</p>
                            ${result.required ? `<p><strong>Required:</strong> ${result.required} coins</p>` : ''}
                            ${result.available ? `<p><strong>Available:</strong> ${result.available} coins</p>` : ''}
                        </div>
                    `;
                    
                    button.innerHTML = originalText;
                    button.disabled = false;
                }
                
            } catch (error) {
                console.error('❌ Purchase error:', error);
                
                results.style.display = 'block';
                resultContent.innerHTML = `
                    <div class="alert alert-danger">
                        <h6><i class="bi bi-exclamation-triangle"></i> Network Error</h6>
                        <p><strong>Error:</strong> ${error.message}</p>
                        <p>Check the console for more details.</p>
                    </div>
                `;
                
                button.innerHTML = originalText;
                button.disabled = false;
            }
        }
    </script>
</body>
</html> 