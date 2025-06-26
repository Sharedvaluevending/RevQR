<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/qr_coin_manager.php';
require_once __DIR__ . '/../core/qr_code_manager.php';
require_once __DIR__ . '/../core/business_wallet_manager.php';

// Require user role
require_role('user');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Business Purchase Debug</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-4">
    <div class="row">
        <div class="col-12">
            <h1><i class="bi bi-bug"></i> Business Purchase Debug</h1>
            <p class="text-muted">Test and debug business discount purchases</p>

            <!-- User Info -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5><i class="bi bi-person"></i> User Information</h5>
                </div>
                <div class="card-body">
                    <p><strong>User ID:</strong> <?php echo $_SESSION['user_id']; ?></p>
                    <p><strong>Username:</strong> <?php echo $_SESSION['username'] ?? 'N/A'; ?></p>
                    <p><strong>QR Balance:</strong> <?php echo number_format(QRCoinManager::getBalance($_SESSION['user_id'])); ?> coins</p>
                </div>
            </div>

            <!-- System Status -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5><i class="bi bi-gear"></i> System Status</h5>
                </div>
                <div class="card-body">
                    <?php
                    $status = [];
                    $status['QRCodeManager'] = class_exists('QRCodeManager') ? '✅' : '❌';
                    $status['BusinessWalletManager'] = class_exists('BusinessWalletManager') ? '✅' : '❌';
                    $status['QRCoinManager'] = class_exists('QRCoinManager') ? '✅' : '❌';
                    
                    foreach ($status as $class => $check) {
                        echo "<p><strong>$class:</strong> $check</p>";
                    }
                    ?>
                </div>
            </div>

            <!-- Available Items -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5><i class="bi bi-shop"></i> Available Business Items</h5>
                </div>
                <div class="card-body">
                    <?php
                    $user_balance = QRCoinManager::getBalance($_SESSION['user_id']);
                    $stmt = $pdo->prepare("
                        SELECT bsi.*, b.name as business_name
                        FROM business_store_items bsi
                        JOIN businesses b ON bsi.business_id = b.id
                        WHERE bsi.is_active = 1
                        ORDER BY bsi.qr_coin_cost ASC
                        LIMIT 5
                    ");
                    $stmt->execute();
                    $items = $stmt->fetchAll();
                    ?>
                    
                    <?php if (empty($items)): ?>
                        <p class="text-muted">No business items available.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Item</th>
                                        <th>Business</th>
                                        <th>Cost</th>
                                        <th>Discount</th>
                                        <th>Affordable</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($items as $item): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                                            <td><?php echo htmlspecialchars($item['business_name']); ?></td>
                                            <td><?php echo number_format($item['qr_coin_cost']); ?> coins</td>
                                            <td><?php echo $item['discount_percentage']; ?>%</td>
                                            <td>
                                                <?php if ($user_balance >= $item['qr_coin_cost']): ?>
                                                    <span class="badge bg-success">✅ Yes</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">❌ No</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-primary test-purchase-btn"
                                                        data-item-id="<?php echo $item['id']; ?>"
                                                        data-item-name="<?php echo htmlspecialchars($item['item_name']); ?>"
                                                        <?php echo $user_balance < $item['qr_coin_cost'] ? 'disabled' : ''; ?>>
                                                    Test Purchase
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Test Results -->
            <div class="card">
                <div class="card-header">
                    <h5><i class="bi bi-clipboard-data"></i> Test Results</h5>
                </div>
                <div class="card-body">
                    <div id="test-results">
                        <p class="text-muted">Click "Test Purchase" on any item above to test the purchase flow.</p>
                    </div>
                </div>
            </div>

            <div class="mt-4">
                <a href="business-stores.php" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-left"></i> Back to Business Stores
                </a>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle test purchase buttons
    document.querySelectorAll('.test-purchase-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const itemId = this.dataset.itemId;
            const itemName = this.dataset.itemName;
            const originalText = this.innerHTML;
            
            // Disable button and show loading
            this.disabled = true;
            this.innerHTML = '<i class="bi bi-hourglass-split"></i> Testing...';
            
            // Clear previous results
            const resultsDiv = document.getElementById('test-results');
            resultsDiv.innerHTML = '<div class="text-muted">Testing purchase...</div>';
            
            // Make the purchase request
            fetch('purchase-business-item.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    item_id: itemId
                })
            })
            .then(response => {
                console.log('Response status:', response.status);
                console.log('Response headers:', response.headers);
                return response.text().then(text => {
                    console.log('Raw response:', text);
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        throw new Error('Invalid JSON response: ' + text);
                    }
                });
            })
            .then(data => {
                console.log('Parsed response:', data);
                
                let resultHtml = `
                    <div class="alert alert-${data.success ? 'success' : 'danger'}">
                        <h6><i class="bi bi-${data.success ? 'check-circle' : 'exclamation-circle'}"></i> 
                            Test Result: ${data.success ? 'SUCCESS' : 'FAILED'}</h6>
                        <p><strong>Item:</strong> ${itemName}</p>
                `;
                
                if (data.success) {
                    resultHtml += `
                        <p><strong>Purchase Code:</strong> <code>${data.purchase_code}</code></p>
                        <p><strong>Business:</strong> ${data.business_name}</p>
                        <p><strong>Discount:</strong> ${data.discount_percentage}% OFF</p>
                        <p><strong>QR Code Generated:</strong> ${data.qr_code_generated ? '✅ Yes' : '❌ No'}</p>
                        <p><strong>Expires In:</strong> ${data.expires_in_days} days</p>
                        <p><strong>QR Coins Spent:</strong> ${data.qr_coins_spent}</p>
                        ${data.qr_message ? `<p><strong>QR Message:</strong> ${data.qr_message}</p>` : ''}
                    `;
                } else {
                    resultHtml += `
                        <p><strong>Error:</strong> ${data.message}</p>
                        ${data.debug_info ? `<p><strong>Debug Info:</strong> <pre>${JSON.stringify(data.debug_info, null, 2)}</pre></p>` : ''}
                    `;
                }
                
                resultHtml += '</div>';
                resultsDiv.innerHTML = resultHtml;
                
                if (data.success) {
                    // Refresh page after a delay to show updated balance
                    setTimeout(() => {
                        window.location.reload();
                    }, 3000);
                }
            })
            .catch(error => {
                console.error('Purchase error:', error);
                const resultsDiv = document.getElementById('test-results');
                resultsDiv.innerHTML = `
                    <div class="alert alert-danger">
                        <h6><i class="bi bi-exclamation-circle"></i> Connection Error</h6>
                        <p><strong>Error:</strong> ${error.message}</p>
                        <p>Check the browser console for more details.</p>
                    </div>
                `;
            })
            .finally(() => {
                // Re-enable button
                this.disabled = false;
                this.innerHTML = originalText;
            });
        });
    });
});
</script>
</body>
</html> 