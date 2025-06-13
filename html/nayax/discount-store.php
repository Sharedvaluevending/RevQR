<?php
/**
 * Nayax Discount Store - Mobile Landing Page
 * Mobile-optimized interface for QR coin discount purchases
 */

require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/nayax_manager.php';
require_once __DIR__ . '/../core/nayax_discount_manager.php';

// Get URL parameters
$source = $_GET['source'] ?? 'direct';
$business_id = $_GET['business_id'] ?? null;
$machine_id = $_GET['machine_id'] ?? null;
$timestamp = $_GET['timestamp'] ?? null;

// Check if user is logged in
$user_id = $_SESSION['user_id'] ?? null;
$user_logged_in = !empty($user_id);

// Get business and machine info if specified
$business_info = null;
$machine_info = null;

if ($business_id) {
    $stmt = $pdo->prepare("SELECT * FROM businesses WHERE id = ?");
    $stmt->execute([$business_id]);
    $business_info = $stmt->fetch(PDO::FETCH_ASSOC);
}

if ($machine_id) {
    $nayax_manager = new NayaxManager($pdo);
    $machine_info = $nayax_manager->getMachine($machine_id);
}

// Get available discount items for this business/machine
$where_conditions = ["qsi.nayax_compatible = 1", "qsi.is_active = 1"];
$params = [];

if ($machine_id) {
    $where_conditions[] = "(bsi.nayax_machine_id = ? OR bsi.nayax_machine_id IS NULL)";
    $params[] = $machine_id;
} elseif ($business_id) {
    $where_conditions[] = "bsi.business_id = ?";
    $params[] = $business_id;
}

$where_clause = "WHERE " . implode(" AND ", $where_conditions);

$stmt = $pdo->prepare("
    SELECT qsi.*, bsi.discount_percent, bsi.item_description,
           b.id as business_id, b.name as business_name, b.logo_url
    FROM qr_store_items qsi
    LEFT JOIN business_store_items bsi ON qsi.business_store_item_id = bsi.id
    LEFT JOIN businesses b ON bsi.business_id = b.id
    {$where_clause}
    ORDER BY qsi.qr_coin_price ASC
");
$stmt->execute($params);
$discount_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user's QR coin balance if logged in
$user_balance = 0;
if ($user_logged_in) {
    $user_balance = QRCoinManager::getBalance($user_id);
}

// Track QR code scan analytics
if ($source === 'nayax_qr' && $machine_id) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO qr_analytics 
            (source_type, source_id, business_id, user_id, scan_timestamp, user_agent, ip_address)
            VALUES ('nayax_machine', ?, ?, ?, NOW(), ?, ?)
        ");
        $stmt->execute([
            $machine_id,
            $business_id,
            $user_id,
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            $_SERVER['REMOTE_ADDR'] ?? ''
        ]);
    } catch (Exception $e) {
        error_log("Failed to track QR scan: " . $e->getMessage());
    }
}

$page_title = "QR Coin Discount Store";
if ($machine_info) {
    $page_title = "Discounts for " . $machine_info['machine_name'];
} elseif ($business_info) {
    $page_title = $business_info['name'] . " - Discount Store";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> | RevenueQR</title>
    
    <!-- Optimized for mobile -->
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #4a90e2;
            --secondary-color: #f39c12;
            --success-color: #27ae60;
            --danger-color: #e74c3c;
            --dark-color: #2c3e50;
            --light-bg: #f8f9fa;
            --shadow: 0 2px 15px rgba(0,0,0,0.1);
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            margin: 0;
            padding: 0;
        }
        
        .mobile-container {
            max-width: 480px;
            margin: 0 auto;
            background: white;
            min-height: 100vh;
            position: relative;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        
        .header-section {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 20px;
            text-align: center;
            position: relative;
        }
        
        .header-icon {
            width: 60px;
            height: 60px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 10px;
            font-size: 24px;
        }
        
        .machine-info {
            background: rgba(255,255,255,0.1);
            border-radius: 10px;
            padding: 15px;
            margin-top: 15px;
            text-align: left;
        }
        
        .balance-card {
            background: var(--light-bg);
            border-radius: 15px;
            padding: 20px;
            margin: 20px;
            text-align: center;
            box-shadow: var(--shadow);
        }
        
        .balance-amount {
            font-size: 2.5rem;
            font-weight: bold;
            color: var(--primary-color);
            margin: 10px 0;
        }
        
        .qr-coin-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(45deg, #f39c12, #e67e22);
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            margin-right: 10px;
        }
        
        .discount-item {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin: 15px 20px;
            box-shadow: var(--shadow);
            border: 1px solid #e9ecef;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .discount-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        }
        
        .discount-badge {
            background: var(--success-color);
            color: white;
            border-radius: 20px;
            padding: 5px 15px;
            font-size: 0.9rem;
            font-weight: bold;
            display: inline-block;
            margin-bottom: 10px;
        }
        
        .price-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 15px;
        }
        
        .qr-price {
            display: flex;
            align-items: center;
            font-size: 1.2rem;
            font-weight: bold;
            color: var(--dark-color);
        }
        
        .purchase-btn {
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            color: white;
            border: none;
            border-radius: 25px;
            padding: 10px 25px;
            font-weight: bold;
            transition: all 0.3s;
            cursor: pointer;
        }
        
        .purchase-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        
        .purchase-btn:disabled {
            background: #6c757d;
            cursor: not-allowed;
            transform: none;
        }
        
        .login-prompt {
            background: var(--light-bg);
            border-radius: 15px;
            padding: 20px;
            margin: 20px;
            text-align: center;
            border: 2px dashed #dee2e6;
        }
        
        .login-btn {
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 25px;
            padding: 12px 30px;
            font-weight: bold;
            text-decoration: none;
            display: inline-block;
            margin-top: 15px;
        }
        
        .floating-action {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: var(--secondary-color);
            color: white;
            border: none;
            border-radius: 50%;
            width: 60px;
            height: 60px;
            font-size: 24px;
            box-shadow: var(--shadow);
            z-index: 1000;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #6c757d;
        }
        
        .empty-icon {
            font-size: 3rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        
        @media (max-width: 576px) {
            .mobile-container {
                margin: 0;
                border-radius: 0;
            }
            
            .discount-item {
                margin: 10px 15px;
                padding: 15px;
            }
        }
        
        .loading-spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid var(--primary-color);
            border-radius: 50%;
            width: 30px;
            height: 30px;
            animation: spin 1s linear infinite;
            margin: 0 auto;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="mobile-container">
        <!-- Header Section -->
        <div class="header-section">
            <div class="header-icon">
                <i class="bi bi-qr-code"></i>
            </div>
            <h2 class="mb-2"><?= htmlspecialchars($page_title) ?></h2>
            <p class="mb-0">Scan QR codes, earn coins, get discounts!</p>
            
            <?php if ($machine_info): ?>
            <div class="machine-info">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-1"><?= htmlspecialchars($machine_info['machine_name']) ?></h6>
                        <small><?= htmlspecialchars($machine_info['business_name'] ?? 'Business') ?></small>
                    </div>
                    <div class="text-end">
                        <span class="badge bg-success">Active</span>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <?php if ($user_logged_in): ?>
        <!-- User Balance Section -->
        <div class="balance-card">
            <div class="d-flex align-items-center justify-content-center mb-2">
                <div class="qr-coin-icon">QR</div>
                <h5 class="mb-0">Your QR Coin Balance</h5>
            </div>
            <div class="balance-amount"><?= number_format($user_balance) ?></div>
            <small class="text-muted">Available for discounts</small>
            <div class="mt-3">
                <a href="/html/user/qr-wallet.php" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-wallet2"></i> View Wallet
                </a>
                <a href="/html/nayax/coin-packs.php" class="btn btn-primary btn-sm ms-2">
                    <i class="bi bi-plus-circle"></i> Buy More Coins
                </a>
            </div>
        </div>
        
        <!-- Discount Items -->
        <div class="discount-items">
            <?php if (empty($discount_items)): ?>
            <div class="empty-state">
                <div class="empty-icon">
                    <i class="bi bi-shop"></i>
                </div>
                <h5>No Discounts Available</h5>
                <p>Check back soon for new discount opportunities!</p>
                <?php if (!$business_id): ?>
                <a href="/html/business/register.php" class="btn btn-primary">
                    Register Your Business
                </a>
                <?php endif; ?>
            </div>
            <?php else: ?>
            
            <?php foreach ($discount_items as $item): ?>
            <div class="discount-item" data-item-id="<?= $item['id'] ?>">
                <div class="discount-badge">
                    <?= $item['discount_percent'] ?>% OFF
                </div>
                
                <h5 class="fw-bold"><?= htmlspecialchars($item['item_name']) ?></h5>
                
                <?php if ($item['item_description']): ?>
                <p class="text-muted mb-2"><?= htmlspecialchars($item['item_description']) ?></p>
                <?php endif; ?>
                
                <div class="price-section">
                    <div class="qr-price">
                        <div class="qr-coin-icon me-2" style="width: 30px; height: 30px; font-size: 0.8rem;">QR</div>
                        <?= number_format($item['qr_coin_price']) ?> coins
                    </div>
                    
                    <button class="purchase-btn" 
                            onclick="purchaseDiscount(<?= $item['id'] ?>, '<?= htmlspecialchars($item['item_name']) ?>', <?= $item['qr_coin_price'] ?>)"
                            <?= ($user_balance < $item['qr_coin_price']) ? 'disabled' : '' ?>>
                        <?php if ($user_balance < $item['qr_coin_price']): ?>
                            <i class="bi bi-lock"></i> Need More Coins
                        <?php else: ?>
                            <i class="bi bi-cart-plus"></i> Purchase
                        <?php endif; ?>
                    </button>
                </div>
                
                <?php if ($item['business_name']): ?>
                <div class="mt-2">
                    <small class="text-muted">
                        <i class="bi bi-shop"></i> <?= htmlspecialchars($item['business_name']) ?>
                    </small>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            
            <?php endif; ?>
        </div>
        
        <?php else: ?>
        <!-- Login Prompt -->
        <div class="login-prompt">
            <i class="bi bi-person-circle" style="font-size: 3rem; color: var(--primary-color);"></i>
            <h5 class="mt-3">Login to Access Discounts</h5>
            <p class="text-muted">Create an account or login to purchase discount codes with QR coins.</p>
            <div class="d-grid gap-2">
                <a href="/html/auth/login.php?redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>" class="login-btn">
                    <i class="bi bi-box-arrow-in-right"></i> Login
                </a>
                <a href="/html/auth/register.php?redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>" class="btn btn-outline-primary">
                    <i class="bi bi-person-plus"></i> Create Account
                </a>
            </div>
            
            <div class="mt-4">
                <h6>Why create an account?</h6>
                <ul class="text-start">
                    <li>Earn QR coins from purchases</li>
                    <li>Access exclusive discount codes</li>
                    <li>Track your savings and rewards</li>
                    <li>Quick checkout on future visits</li>
                </ul>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Floating Action Button -->
    <button class="floating-action" onclick="showQRScanner()" title="Scan QR Code">
        <i class="bi bi-qr-code-scan"></i>
    </button>
    
    <!-- Purchase Success Modal -->
    <div class="modal fade" id="purchaseModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-check-circle"></i> Purchase Successful!
                    </h5>
                </div>
                <div class="modal-body text-center">
                    <div id="purchaseResult"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Continue Shopping</button>
                    <a href="/html/user/discount-codes.php" class="btn btn-outline-primary">View My Codes</a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Purchase discount code
        async function purchaseDiscount(itemId, itemName, price) {
            const button = event.target;
            const originalText = button.innerHTML;
            
            // Show loading state
            button.innerHTML = '<div class="loading-spinner"></div>';
            button.disabled = true;
            
            try {
                const response = await fetch('/html/api/purchase-discount.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        item_id: itemId,
                        machine_id: '<?= $machine_id ?>',
                        source: '<?= $source ?>'
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Show success modal
                    showPurchaseSuccess(result);
                    
                    // Update balance
                    updateUserBalance();
                    
                    // Disable this button
                    button.innerHTML = '<i class="bi bi-check"></i> Purchased';
                    button.classList.add('btn-success');
                    button.disabled = true;
                } else {
                    throw new Error(result.message || 'Purchase failed');
                }
                
            } catch (error) {
                console.error('Purchase error:', error);
                alert('Purchase failed: ' + error.message);
                
                // Restore button
                button.innerHTML = originalText;
                button.disabled = false;
            }
        }
        
        // Show purchase success modal
        function showPurchaseSuccess(result) {
            const modalContent = `
                <div class="text-center">
                    <div class="mb-3">
                        <i class="bi bi-ticket-perforated" style="font-size: 3rem; color: var(--success-color);"></i>
                    </div>
                    <h5>${result.item_name}</h5>
                    <div class="discount-code-display bg-light p-3 rounded mt-3">
                        <h4 class="fw-bold text-primary">${result.discount_code}</h4>
                        <p class="mb-0">Show this code at the machine</p>
                    </div>
                    <div class="row mt-3">
                        <div class="col-6">
                            <strong>${result.discount_percent}%</strong><br>
                            <small>Discount</small>
                        </div>
                        <div class="col-6">
                            <strong>${new Date(result.expires_at).toLocaleDateString()}</strong><br>
                            <small>Expires</small>
                        </div>
                    </div>
                </div>
            `;
            
            document.getElementById('purchaseResult').innerHTML = modalContent;
            new bootstrap.Modal(document.getElementById('purchaseModal')).show();
        }
        
        // Update user balance
        async function updateUserBalance() {
            try {
                const response = await fetch('/html/api/user-balance.php');
                const result = await response.json();
                
                if (result.success) {
                    const balanceElement = document.querySelector('.balance-amount');
                    if (balanceElement) {
                        balanceElement.textContent = result.balance.toLocaleString();
                    }
                }
            } catch (error) {
                console.error('Balance update error:', error);
            }
        }
        
        // Show QR scanner (placeholder for future implementation)
        function showQRScanner() {
            alert('QR Scanner feature coming soon! For now, manually enter the store URL.');
        }
        
        // Auto-refresh balance every 30 seconds
        <?php if ($user_logged_in): ?>
        setInterval(updateUserBalance, 30000);
        <?php endif; ?>
        
        // Track page view analytics
        if ('<?= $source ?>' === 'nayax_qr') {
            fetch('/html/api/track-analytics.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    event: 'page_view',
                    source: '<?= $source ?>',
                    machine_id: '<?= $machine_id ?>',
                    business_id: '<?= $business_id ?>'
                })
            }).catch(console.error);
        }
    </script>
</body>
</html> 