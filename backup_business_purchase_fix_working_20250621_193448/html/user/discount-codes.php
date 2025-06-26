<?php
/**
 * User Discount Codes Management Page
 * Displays and manages user's purchased discount codes
 */

require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/nayax_discount_manager.php';

// Check if user is logged in
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    header("Location: /html/auth/login.php?redirect=" . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

// Get user info
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Get discount manager
$discount_manager = new NayaxDiscountManager($pdo);

// Get user's discount codes
$active_codes = $discount_manager->getUserDiscountCodes($user_id, true);
$all_codes = $discount_manager->getUserDiscountCodes($user_id, false);

// Get user's QR coin balance
$user_balance = QRCoinManager::getBalance($user_id);

// Calculate statistics
$total_codes = count($all_codes);
$active_count = count($active_codes);
$used_count = count(array_filter($all_codes, function($code) {
    return $code['status'] === 'used' || $code['uses_count'] >= $code['max_uses'];
}));
$expired_count = count(array_filter($all_codes, function($code) {
    return $code['status'] === 'expired' || strtotime($code['expires_at']) < time();
}));

$total_spent = array_sum(array_column($all_codes, 'qr_coins_spent'));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Discount Codes | RevenueQR</title>
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #4a90e2;
            --secondary-color: #f39c12;
            --success-color: #27ae60;
            --danger-color: #e74c3c;
            --warning-color: #f39c12;
            --info-color: #3498db;
            --light-bg: #f8f9fa;
            --shadow: 0 2px 15px rgba(0,0,0,0.1);
        }
        
        body {
            background-color: var(--light-bg);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .main-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .page-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .stats-row {
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            box-shadow: var(--shadow);
            transition: transform 0.2s;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .stat-card.active .stat-number { color: var(--success-color); }
        .stat-card.used .stat-number { color: var(--info-color); }
        .stat-card.expired .stat-number { color: var(--danger-color); }
        .stat-card.total .stat-number { color: var(--primary-color); }
        
        .balance-card {
            background: linear-gradient(45deg, var(--success-color), #2ecc71);
            color: white;
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            margin-bottom: 30px;
        }
        
        .balance-amount {
            font-size: 2rem;
            font-weight: bold;
            margin: 10px 0;
        }
        
        .code-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: var(--shadow);
            border-left: 5px solid transparent;
            transition: all 0.3s;
        }
        
        .code-card.active {
            border-left-color: var(--success-color);
        }
        
        .code-card.used {
            border-left-color: var(--info-color);
            opacity: 0.8;
        }
        
        .code-card.expired {
            border-left-color: var(--danger-color);
            opacity: 0.6;
        }
        
        .code-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .discount-badge {
            background: var(--success-color);
            color: white;
            border-radius: 20px;
            padding: 5px 15px;
            font-size: 0.9rem;
            font-weight: bold;
        }
        
        .status-badge {
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: bold;
        }
        
        .status-active {
            background: #d4edda;
            color: #155724;
        }
        
        .status-used {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .status-expired {
            background: #f8d7da;
            color: #721c24;
        }
        
        .discount-code-display {
            background: #f8f9fa;
            border: 2px dashed #dee2e6;
            border-radius: 10px;
            padding: 15px;
            text-align: center;
            margin: 15px 0;
            position: relative;
        }
        
        .code-text {
            font-family: 'Courier New', monospace;
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--primary-color);
            letter-spacing: 2px;
        }
        
        .copy-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 5px;
            padding: 5px 10px;
            font-size: 0.8rem;
            cursor: pointer;
        }
        
        .code-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-top: 15px;
        }
        
        .detail-item {
            display: flex;
            align-items: center;
            font-size: 0.9rem;
            color: #6c757d;
        }
        
        .detail-icon {
            margin-right: 8px;
            width: 16px;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }
        
        .empty-icon {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        
        .filter-tabs {
            background: white;
            border-radius: 15px;
            padding: 5px;
            margin-bottom: 30px;
            box-shadow: var(--shadow);
        }
        
        .filter-tab {
            background: transparent;
            border: none;
            padding: 10px 20px;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s;
            color: #6c757d;
        }
        
        .filter-tab.active {
            background: var(--primary-color);
            color: white;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        .btn-action {
            flex: 1;
            padding: 8px 16px;
            border-radius: 8px;
            border: none;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-primary-action {
            background: var(--primary-color);
            color: white;
        }
        
        .btn-secondary-action {
            background: var(--light-bg);
            color: var(--primary-color);
            border: 1px solid var(--primary-color);
        }
        
        @media (max-width: 768px) {
            .main-container {
                padding: 15px;
            }
            
            .code-details {
                grid-template-columns: 1fr;
                gap: 10px;
            }
            
            .action-buttons {
                flex-direction: column;
            }
        }
        
        .qr-code-modal .modal-dialog {
            max-width: 400px;
        }
        
        .qr-code-display {
            text-align: center;
            padding: 20px;
        }
        
        .usage-progress {
            margin-top: 10px;
        }
        
        .progress-bar-custom {
            height: 6px;
            border-radius: 3px;
            background: var(--success-color);
        }
    </style>
</head>
<body>
    <div class="main-container">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="mb-2">
                <i class="bi bi-ticket-perforated"></i> My Discount Codes
            </h1>
            <p class="mb-0">Manage your purchased discount codes and savings</p>
        </div>
        
        <!-- Current Balance -->
        <div class="balance-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-1">Current QR Coin Balance</h5>
                    <div class="balance-amount"><?= number_format($user_balance) ?> QR Coins</div>
                </div>
                <div>
                    <a href="/html/nayax/coin-packs.php" class="btn btn-light">
                        <i class="bi bi-plus-circle"></i> Buy More
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Statistics -->
        <div class="stats-row">
            <div class="row">
                <div class="col-6 col-md-3">
                    <div class="stat-card active">
                        <div class="stat-number"><?= $active_count ?></div>
                        <div class="stat-label">Active Codes</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-card used">
                        <div class="stat-number"><?= $used_count ?></div>
                        <div class="stat-label">Used Codes</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-card expired">
                        <div class="stat-number"><?= $expired_count ?></div>
                        <div class="stat-label">Expired</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-card total">
                        <div class="stat-number"><?= number_format($total_spent) ?></div>
                        <div class="stat-label">Coins Spent</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Filter Tabs -->
        <div class="filter-tabs d-flex">
            <button class="filter-tab active" onclick="filterCodes('all')">
                All Codes (<?= $total_codes ?>)
            </button>
            <button class="filter-tab" onclick="filterCodes('active')">
                Active (<?= $active_count ?>)
            </button>
            <button class="filter-tab" onclick="filterCodes('used')">
                Used (<?= $used_count ?>)
            </button>
            <button class="filter-tab" onclick="filterCodes('expired')">
                Expired (<?= $expired_count ?>)
            </button>
        </div>
        
        <!-- Discount Codes -->
        <div id="codes-container">
            <?php if (empty($all_codes)): ?>
            <div class="empty-state">
                <div class="empty-icon">
                    <i class="bi bi-ticket-perforated"></i>
                </div>
                <h4>No Discount Codes Yet</h4>
                <p>Purchase discount codes with QR coins to start saving at vending machines!</p>
                <a href="/html/nayax/discount-store.php" class="btn btn-primary btn-lg">
                    <i class="bi bi-shop"></i> Browse Discount Store
                </a>
            </div>
            
            <?php else: ?>
            
            <?php foreach ($all_codes as $code): 
                $is_expired = strtotime($code['expires_at']) < time();
                $is_used = $code['uses_count'] >= $code['max_uses'];
                $is_active = !$is_expired && !$is_used && $code['status'] === 'active';
                
                $status_class = $is_active ? 'active' : ($is_used ? 'used' : 'expired');
                $status_text = $is_active ? 'Active' : ($is_used ? 'Used' : 'Expired');
                $status_badge_class = $is_active ? 'status-active' : ($is_used ? 'status-used' : 'status-expired');
            ?>
            <div class="code-card <?= $status_class ?>" data-status="<?= $status_class ?>">
                <div class="code-header">
                    <div class="d-flex align-items-center">
                        <div class="discount-badge me-3">
                            <?= $code['discount_percent'] ?>% OFF
                        </div>
                        <div>
                            <h5 class="mb-1"><?= htmlspecialchars($code['item_name']) ?></h5>
                            <?php if ($code['business_name']): ?>
                            <small class="text-muted">
                                <i class="bi bi-shop"></i> <?= htmlspecialchars($code['business_name']) ?>
                            </small>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="status-badge <?= $status_badge_class ?>">
                        <?= $status_text ?>
                    </div>
                </div>
                
                <div class="discount-code-display">
                    <div class="code-text"><?= htmlspecialchars($code['discount_code']) ?></div>
                    <button class="copy-btn" onclick="copyCode('<?= htmlspecialchars($code['discount_code']) ?>')">
                        <i class="bi bi-clipboard"></i> Copy
                    </button>
                </div>
                
                <div class="code-details">
                    <div class="detail-item">
                        <i class="bi bi-calendar detail-icon"></i>
                        <div>
                            <strong>Expires:</strong><br>
                            <?= date('M j, Y g:i A', strtotime($code['expires_at'])) ?>
                        </div>
                    </div>
                    <div class="detail-item">
                        <i class="bi bi-coin detail-icon"></i>
                        <div>
                            <strong>Cost:</strong><br>
                            <?= number_format($code['qr_coins_spent']) ?> QR Coins
                        </div>
                    </div>
                    <div class="detail-item">
                        <i class="bi bi-clock detail-icon"></i>
                        <div>
                            <strong>Purchased:</strong><br>
                            <?= date('M j, Y', strtotime($code['created_at'])) ?>
                        </div>
                    </div>
                    <div class="detail-item">
                        <i class="bi bi-arrow-repeat detail-icon"></i>
                        <div>
                            <strong>Usage:</strong><br>
                            <?= $code['uses_count'] ?> / <?= $code['max_uses'] ?> uses
                        </div>
                    </div>
                </div>
                
                <?php if ($code['uses_count'] > 0): ?>
                <div class="usage-progress">
                    <div class="progress">
                        <div class="progress-bar progress-bar-custom" 
                             style="width: <?= ($code['uses_count'] / $code['max_uses']) * 100 ?>%">
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="action-buttons">
                    <?php if ($is_active): ?>
                    <button class="btn-action btn-primary-action" onclick="showQRCode('<?= htmlspecialchars($code['discount_code']) ?>')">
                        <i class="bi bi-qr-code"></i> Show QR
                    </button>
                    <button class="btn-action btn-secondary-action" onclick="shareCode('<?= htmlspecialchars($code['discount_code']) ?>')">
                        <i class="bi bi-share"></i> Share
                    </button>
                    <?php else: ?>
                    <button class="btn-action btn-secondary-action" disabled>
                        <i class="bi bi-<?= $is_used ? 'check-circle' : 'x-circle' ?>"></i> 
                        <?= $is_used ? 'Used' : 'Expired' ?>
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
            
            <?php endif; ?>
        </div>
        
        <!-- Action Buttons -->
        <div class="text-center mt-4">
            <a href="/html/nayax/discount-store.php" class="btn btn-primary btn-lg me-3">
                <i class="bi bi-shop"></i> Buy More Codes
            </a>
            <a href="/html/nayax/machine-finder.php" class="btn btn-outline-primary btn-lg">
                <i class="bi bi-geo-alt"></i> Find Machines
            </a>
        </div>
    </div>
    
    <!-- QR Code Modal -->
    <div class="modal fade qr-code-modal" id="qrModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Discount Code QR</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="qr-code-display">
                        <div id="qrCodeContainer"></div>
                        <p class="mt-3 text-muted">Show this QR code at the vending machine or enter the code manually.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
    
    <script>
        // Filter codes by status
        function filterCodes(status) {
            const cards = document.querySelectorAll('.code-card');
            const tabs = document.querySelectorAll('.filter-tab');
            
            // Update active tab
            tabs.forEach(tab => tab.classList.remove('active'));
            event.target.classList.add('active');
            
            // Show/hide cards
            cards.forEach(card => {
                if (status === 'all' || card.dataset.status === status) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        }
        
        // Copy discount code to clipboard
        async function copyCode(code) {
            try {
                await navigator.clipboard.writeText(code);
                
                // Show feedback
                const button = event.target.closest('.copy-btn');
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="bi bi-check"></i> Copied!';
                button.style.background = '#27ae60';
                
                setTimeout(() => {
                    button.innerHTML = originalText;
                    button.style.background = '#4a90e2';
                }, 2000);
                
            } catch (err) {
                // Fallback for older browsers
                const textArea = document.createElement('textarea');
                textArea.value = code;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
                
                alert('Code copied: ' + code);
            }
        }
        
        // Show QR code modal
        function showQRCode(code) {
            const container = document.getElementById('qrCodeContainer');
            container.innerHTML = '';
            
            // Generate QR code
            QRCode.toCanvas(container, code, {
                width: 200,
                margin: 2,
                color: {
                    dark: '#000000',
                    light: '#ffffff'
                }
            }, function (error) {
                if (error) {
                    container.innerHTML = '<p class="text-danger">Error generating QR code</p>';
                }
            });
            
            // Show modal
            new bootstrap.Modal(document.getElementById('qrModal')).show();
        }
        
        // Share discount code
        function shareCode(code) {
            if (navigator.share) {
                navigator.share({
                    title: 'RevenueQR Discount Code',
                    text: `Use this discount code at vending machines: ${code}`,
                    url: window.location.href
                });
            } else {
                // Fallback
                copyCode(code);
                alert('Discount code copied to clipboard!');
            }
        }
        
        // Auto-refresh balance
        function updateBalance() {
            fetch('/html/api/user-balance.php')
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        document.querySelector('.balance-amount').textContent = 
                            result.formatted_balance + ' QR Coins';
                    }
                })
                .catch(console.error);
        }
        
        // Refresh balance every 60 seconds
        setInterval(updateBalance, 60000);
        
        // Check for expired codes and update display
        function checkExpiredCodes() {
            const now = new Date().getTime();
            document.querySelectorAll('.code-card').forEach(card => {
                const expiryText = card.querySelector('.detail-item:first-child div').textContent;
                // Implementation for real-time expiry checking would go here
            });
        }
        
        // Track page analytics
        fetch('/html/api/track-analytics.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                event: 'discount_codes_viewed',
                data: {
                    total_codes: <?= $total_codes ?>,
                    active_codes: <?= $active_count ?>,
                    total_spent: <?= $total_spent ?>
                }
            })
        }).catch(console.error);
    </script>
</body>
</html> 