<?php
/**
 * Nayax QR Coin Packs Purchase Interface
 * Mobile-optimized page for purchasing QR coin packs at vending machines
 */

require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/nayax_manager.php';

// Check if user is logged in
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    header("Location: /html/auth/login.php?redirect=" . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

// Get URL parameters
$machine_id = $_GET['machine_id'] ?? null;
$business_id = $_GET['business_id'] ?? null;

// Get user info and balance
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$user_balance = QRCoinManager::getBalance($user_id);

// Get available QR coin products
$nayax_manager = new NayaxManager($pdo);
$where_conditions = ["is_active = 1"];
$params = [];

if ($machine_id) {
    $where_conditions[] = "nayax_machine_id = ?";
    $params[] = $machine_id;
    
    // Get machine info
    $machine_info = $nayax_manager->getMachine($machine_id);
} elseif ($business_id) {
    $where_conditions[] = "business_id = ?";
    $params[] = $business_id;
}

$where_clause = "WHERE " . implode(" AND ", $where_conditions);

$stmt = $pdo->prepare("
    SELECT nqcp.*, nm.machine_name, b.name as business_name
    FROM nayax_qr_coin_products nqcp
    LEFT JOIN nayax_machines nm ON nqcp.nayax_machine_id = nm.nayax_machine_id
    LEFT JOIN businesses b ON nqcp.business_id = b.id
    {$where_clause}
    ORDER BY nqcp.price_cents ASC
");
$stmt->execute($params);
$coin_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user's recent transactions
$stmt = $pdo->prepare("
    SELECT * FROM qr_coin_transactions 
    WHERE user_id = ? AND transaction_type = 'earning' AND reference_type = 'nayax_purchase'
    ORDER BY created_at DESC 
    LIMIT 5
");
$stmt->execute([$user_id]);
$recent_purchases = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = "QR Coin Packs";
if (isset($machine_info)) {
    $page_title = "Buy Coins - " . $machine_info['machine_name'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> | RevenueQR</title>
    
    <!-- Mobile optimization -->
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #4a90e2;
            --secondary-color: #f39c12;
            --success-color: #27ae60;
            --gold-color: #ffd700;
            --platinum-color: #e5e4e2;
            --shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            margin: 0;
        }
        
        .mobile-container {
            max-width: 480px;
            margin: 0 auto;
            background: white;
            min-height: 100vh;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        
        .header-section {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 20px;
            text-align: center;
        }
        
        .back-button {
            position: absolute;
            left: 20px;
            top: 20px;
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .balance-section {
            background: var(--success-color);
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .current-balance {
            font-size: 1.5rem;
            font-weight: bold;
        }
        
        .coin-pack-card {
            background: white;
            border-radius: 20px;
            margin: 15px 20px;
            padding: 20px;
            box-shadow: var(--shadow);
            border: 2px solid transparent;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .coin-pack-card.popular {
            border-color: var(--gold-color);
            transform: scale(1.02);
        }
        
        .coin-pack-card.best-value {
            border-color: var(--success-color);
            transform: scale(1.02);
        }
        
        .pack-badge {
            position: absolute;
            top: -5px;
            right: 15px;
            padding: 5px 15px;
            border-radius: 0 0 10px 10px;
            font-size: 0.8rem;
            font-weight: bold;
            color: white;
        }
        
        .pack-badge.popular {
            background: var(--gold-color);
            color: #333;
        }
        
        .pack-badge.best-value {
            background: var(--success-color);
        }
        
        .pack-header {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .pack-title {
            font-size: 1.3rem;
            font-weight: bold;
            color: var(--primary-color);
            margin-bottom: 5px;
        }
        
        .pack-description {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .coin-amount {
            text-align: center;
            margin: 20px 0;
        }
        
        .coin-number {
            font-size: 3rem;
            font-weight: bold;
            color: var(--secondary-color);
            line-height: 1;
        }
        
        .coin-label {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .bonus-section {
            background: linear-gradient(45deg, #e8f5e8, #f0f8f0);
            border-radius: 10px;
            padding: 10px;
            text-align: center;
            margin: 15px 0;
        }
        
        .bonus-text {
            color: var(--success-color);
            font-weight: bold;
            font-size: 0.9rem;
        }
        
        .price-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 20px;
        }
        
        .price-display {
            text-align: left;
        }
        
        .price-amount {
            font-size: 1.8rem;
            font-weight: bold;
            color: var(--primary-color);
        }
        
        .price-per-coin {
            font-size: 0.8rem;
            color: #6c757d;
        }
        
        .purchase-btn {
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            color: white;
            border: none;
            border-radius: 25px;
            padding: 12px 25px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .purchase-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.15);
        }
        
        .machine-selection {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 20px;
            margin: 20px;
        }
        
        .machine-card {
            background: white;
            border-radius: 10px;
            padding: 15px;
            margin: 10px 0;
            border: 2px solid transparent;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .machine-card:hover,
        .machine-card.selected {
            border-color: var(--primary-color);
            box-shadow: var(--shadow);
        }
        
        .machine-status {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: bold;
        }
        
        .status-active {
            background: #d4edda;
            color: #155724;
        }
        
        .status-offline {
            background: #f8d7da;
            color: #721c24;
        }
        
        .instructions-section {
            background: #e3f2fd;
            border-radius: 15px;
            padding: 20px;
            margin: 20px;
        }
        
        .step-list {
            list-style: none;
            padding: 0;
        }
        
        .step-item {
            display: flex;
            align-items: center;
            margin: 10px 0;
            padding: 10px;
            background: white;
            border-radius: 8px;
        }
        
        .step-number {
            background: var(--primary-color);
            color: white;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 15px;
        }
        
        .recent-purchases {
            margin: 20px;
        }
        
        .purchase-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        
        .floating-total {
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            background: var(--success-color);
            color: white;
            padding: 15px 30px;
            border-radius: 25px;
            box-shadow: var(--shadow);
            display: none;
        }
        
        @media (max-width: 576px) {
            .mobile-container {
                margin: 0;
                border-radius: 0;
            }
            
            .coin-pack-card {
                margin: 10px 15px;
            }
        }
    </style>
</head>
<body>
    <div class="mobile-container">
        <!-- Header Section -->
        <div class="header-section position-relative">
            <button class="back-button" onclick="history.back()">
                <i class="bi bi-arrow-left"></i>
            </button>
            <h2 class="mb-2"><?= htmlspecialchars($page_title) ?></h2>
            <p class="mb-0">Purchase QR coins at vending machines</p>
        </div>
        
        <!-- Current Balance -->
        <div class="balance-section">
            <div>
                <small>Current Balance</small>
                <div class="current-balance"><?= number_format($user_balance) ?> QR Coins</div>
            </div>
            <div>
                <i class="bi bi-wallet2" style="font-size: 2rem;"></i>
            </div>
        </div>
        
        <?php if (empty($coin_products)): ?>
        <!-- No Products Available -->
        <div class="text-center p-5">
            <i class="bi bi-shop" style="font-size: 3rem; color: #6c757d;"></i>
            <h5 class="mt-3">No Coin Packs Available</h5>
            <p class="text-muted">Contact your machine operator to set up QR coin sales.</p>
        </div>
        
        <?php else: ?>
        
        <!-- QR Coin Packs -->
        <div class="coin-packs-section">
            <?php 
            $pack_count = count($coin_products);
            foreach ($coin_products as $index => $product): 
                $price_dollars = $product['price_cents'] / 100;
                $price_per_coin = $product['price_cents'] / $product['qr_coin_amount'];
                $bonus_percentage = $product['bonus_percentage'] ?? 0;
                
                // Determine pack type
                $pack_class = '';
                $pack_badge = '';
                if ($pack_count > 1) {
                    if ($index === 1) { // Middle pack is popular
                        $pack_class = 'popular';
                        $pack_badge = 'Most Popular';
                    } elseif ($index === $pack_count - 1) { // Last pack is best value
                        $pack_class = 'best-value';
                        $pack_badge = 'Best Value';
                    }
                }
            ?>
            <div class="coin-pack-card <?= $pack_class ?>" data-product-id="<?= $product['id'] ?>">
                <?php if ($pack_badge): ?>
                <div class="pack-badge <?= $pack_class ?>"><?= $pack_badge ?></div>
                <?php endif; ?>
                
                <div class="pack-header">
                    <div class="pack-title"><?= htmlspecialchars($product['product_name']) ?></div>
                    <?php if ($product['product_description']): ?>
                    <div class="pack-description"><?= htmlspecialchars($product['product_description']) ?></div>
                    <?php endif; ?>
                </div>
                
                <div class="coin-amount">
                    <div class="coin-number"><?= number_format($product['qr_coin_amount']) ?></div>
                    <div class="coin-label">QR Coins</div>
                </div>
                
                <?php if ($bonus_percentage > 0): ?>
                <div class="bonus-section">
                    <div class="bonus-text">
                        <i class="bi bi-gift"></i> +<?= round($bonus_percentage) ?>% Bonus Coins!
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="price-section">
                    <div class="price-display">
                        <div class="price-amount">$<?= number_format($price_dollars, 2) ?></div>
                        <div class="price-per-coin"><?= number_format($price_per_coin * 100, 2) ?>¢ per coin</div>
                    </div>
                    
                    <button class="purchase-btn" onclick="selectPack(<?= $product['id'] ?>, '<?= htmlspecialchars($product['product_name']) ?>', <?= $price_dollars ?>)">
                        <i class="bi bi-cart-plus"></i> Select
                    </button>
                </div>
                
                <?php if ($product['machine_name']): ?>
                <div class="mt-3 text-center">
                    <small class="text-muted">
                        <i class="bi bi-pin-map"></i> Available at <?= htmlspecialchars($product['machine_name']) ?>
                    </small>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        
        <?php endif; ?>
        
        <!-- Instructions -->
        <div class="instructions-section">
            <h5 class="mb-3">
                <i class="bi bi-info-circle"></i> How to Purchase
            </h5>
            <ol class="step-list">
                <li class="step-item">
                    <div class="step-number">1</div>
                    <div>Select your preferred QR coin pack above</div>
                </li>
                <li class="step-item">
                    <div class="step-number">2</div>
                    <div>Go to any participating vending machine</div>
                </li>
                <li class="step-item">
                    <div class="step-number">3</div>
                    <div>Select the QR coin pack on the machine display</div>
                </li>
                <li class="step-item">
                    <div class="step-number">4</div>
                    <div>Pay with your card - coins are added automatically!</div>
                </li>
            </ol>
            
            <div class="alert alert-info mt-3">
                <i class="bi bi-lightbulb"></i>
                <strong>Tip:</strong> Link your payment card in settings for automatic coin crediting!
            </div>
        </div>
        
        <?php if (!empty($recent_purchases)): ?>
        <!-- Recent Purchases -->
        <div class="recent-purchases">
            <h5 class="mb-3">Recent QR Coin Purchases</h5>
            <?php foreach ($recent_purchases as $purchase): ?>
            <div class="purchase-item">
                <div>
                    <div class="fw-bold">+<?= number_format($purchase['amount']) ?> QR Coins</div>
                    <small class="text-muted"><?= date('M j, Y', strtotime($purchase['created_at'])) ?></small>
                </div>
                <div class="text-success">
                    <i class="bi bi-check-circle"></i>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Selection Modal -->
    <div class="modal fade" id="selectionModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Ready to Purchase</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="selectionDetails"></div>
                    
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle"></i>
                        <strong>Next Step:</strong> Go to a participating vending machine to complete your purchase.
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button class="btn btn-primary" onclick="findNearestMachine()">
                            <i class="bi bi-geo-alt"></i> Find Nearest Machine
                        </button>
                        <button class="btn btn-outline-primary" onclick="addToWishlist()">
                            <i class="bi bi-bookmark"></i> Save for Later
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        let selectedPack = null;
        
        // Select a QR coin pack
        function selectPack(productId, productName, price) {
            selectedPack = {
                id: productId,
                name: productName,
                price: price
            };
            
            // Update selection details
            const detailsHTML = `
                <div class="text-center">
                    <h4 class="text-primary">${productName}</h4>
                    <div class="h2 text-success">$${price.toFixed(2)}</div>
                    <p class="text-muted">Purchase at any participating vending machine</p>
                </div>
            `;
            
            document.getElementById('selectionDetails').innerHTML = detailsHTML;
            
            // Show modal
            new bootstrap.Modal(document.getElementById('selectionModal')).show();
            
            // Track selection
            trackEvent('pack_selected', {
                product_id: productId,
                product_name: productName,
                price: price
            });
        }
        
        // Find nearest machine
        function findNearestMachine() {
            // Check if geolocation is available
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    position => {
                        const lat = position.coords.latitude;
                        const lng = position.coords.longitude;
                        
                        // Redirect to machine finder with location
                        window.location.href = `/html/nayax/machine-finder.php?lat=${lat}&lng=${lng}&pack=${selectedPack.id}`;
                    },
                    error => {
                        // Fallback - show all machines
                        window.location.href = '/html/nayax/machine-finder.php';
                    }
                );
            } else {
                // Geolocation not supported
                window.location.href = '/html/nayax/machine-finder.php';
            }
        }
        
        // Add to wishlist
        function addToWishlist() {
            if (!selectedPack) return;
            
            // Save to localStorage for now
            let wishlist = JSON.parse(localStorage.getItem('qr_coin_wishlist') || '[]');
            
            // Check if already in wishlist
            const exists = wishlist.find(item => item.id === selectedPack.id);
            if (!exists) {
                wishlist.push({
                    ...selectedPack,
                    added_at: new Date().toISOString()
                });
                localStorage.setItem('qr_coin_wishlist', JSON.stringify(wishlist));
                
                // Show success message
                alert('Added to your wishlist! View it from your profile.');
            } else {
                alert('This pack is already in your wishlist.');
            }
            
            // Close modal
            bootstrap.Modal.getInstance(document.getElementById('selectionModal')).hide();
        }
        
        // Track events
        function trackEvent(eventName, data) {
            fetch('/html/api/track-analytics.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    event: eventName,
                    data: data,
                    page: 'coin_packs'
                })
            }).catch(console.error);
        }
        
        // Auto-refresh balance
        function updateBalance() {
            fetch('/html/api/user-balance.php')
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        document.querySelector('.current-balance').textContent = 
                            result.balance.toLocaleString() + ' QR Coins';
                    }
                })
                .catch(console.error);
        }
        
        // Refresh balance every 30 seconds
        setInterval(updateBalance, 30000);
        
        // Track page view
        trackEvent('page_view', {
            machine_id: '<?= $machine_id ?>',
            business_id: '<?= $business_id ?>',
            packs_available: <?= count($coin_products) ?>
        });
    </script>
</body>
</html> 