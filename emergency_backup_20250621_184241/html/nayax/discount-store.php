<?php
/**
 * FIXED VERSION - Discount Store with Modal Issue Resolution
 * This fixes the "page goes dark and can't click" issue
 */

// Copy the original file content but with fixes
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/qr_coin_manager.php';

// Get parameters
$machine_id = $_GET['machine_id'] ?? null;
$business_id = $_GET['business_id'] ?? null;
$source = $_GET['source'] ?? 'direct';

$user_logged_in = isset($_SESSION['user_id']);
$user_id = $_SESSION['user_id'] ?? null;
$user_balance = 0;

if ($user_logged_in) {
    try {
        $user_balance = QRCoinManager::getBalance($user_id);
    } catch (Exception $e) {
        $user_balance = 0;
    }
}

// Get available discount items
$discount_items = [];
try {
    $stmt = $pdo->prepare("
        SELECT qsi.*, bsi.business_id, b.name as business_name,
               bsi.discount_percent, bsi.item_name, bsi.item_description
        FROM qr_store_items qsi
        LEFT JOIN business_store_items bsi ON qsi.business_store_item_id = bsi.id
        LEFT JOIN businesses b ON bsi.business_id = b.id
        WHERE qsi.item_type = 'discount' AND qsi.is_active = 1
        ORDER BY qsi.qr_coin_cost ASC
    ");
    $stmt->execute();
    $discount_items = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching discount items: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Discount Store</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2c3e50;
            --success-color: #27ae60;
            --warning-color: #f39c12;
            --danger-color: #e74c3c;
        }
        
        .store-header {
            background: linear-gradient(135deg, var(--primary-color), #34495e);
            color: white;
            padding: 2rem 0;
        }
        
        .discount-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            height: 100%;
        }
        
        .discount-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .discount-badge {
            position: absolute;
            top: -10px;
            right: -10px;
            background: var(--success-color);
            color: white;
            border-radius: 50%;
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 0.9rem;
        }
        
        .purchase-btn {
            background: var(--success-color);
            border: none;
            border-radius: 25px;
            padding: 12px 24px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .purchase-btn:hover:not(:disabled) {
            background: #219a52;
            transform: translateY(-2px);
        }
        
        .purchase-btn:disabled {
            background: #95a5a6;
            cursor: not-allowed;
        }
        
        .balance-display {
            background: linear-gradient(45deg, #f8f9fa, #e9ecef);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .qr-coin-icon {
            background: linear-gradient(45deg, #f39c12, #e67e22);
            color: white;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 0.9rem;
        }
        
        .loading-spinner {
            width: 20px;
            height: 20px;
            border: 2px solid #ffffff;
            border-top: 2px solid transparent;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* CRITICAL FIX: Ensure modal backdrop doesn't get stuck */
        .modal-backdrop {
            z-index: 1040 !important;
        }
        
        .modal {
            z-index: 1050 !important;
        }
        
        /* Emergency modal close button */
        .emergency-close {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            font-size: 1.5rem;
            display: none;
        }
        
        .emergency-close.show {
            display: block;
        }
    </style>
</head>
<body>
    <!-- Emergency Close Button (appears when modal gets stuck) -->
    <button id="emergencyClose" class="emergency-close" onclick="forceCloseModal()">
        <i class="bi bi-x"></i>
    </button>
    
    <div class="store-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1><i class="bi bi-shop"></i> QR Discount Store</h1>
                    <p class="mb-0">Purchase discount codes with your QR coins</p>
                </div>
                <div class="col-md-4 text-end">
                    <?php if ($user_logged_in): ?>
                        <div class="balance-display bg-white text-dark">
                            <div class="d-flex align-items-center justify-content-center">
                                <div class="qr-coin-icon me-2">QR</div>
                                <div>
                                    <div class="balance-amount fs-4 fw-bold"><?= number_format($user_balance) ?></div>
                                    <small>QR Coins</small>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="container mt-4">
        <?php if (!$user_logged_in): ?>
            <div class="alert alert-warning text-center">
                <h5><i class="bi bi-exclamation-triangle"></i> Login Required</h5>
                <p>You need to log in to purchase discount codes.</p>
                <a href="/html/user/login.php" class="btn btn-primary">
                    <i class="bi bi-box-arrow-in-right"></i> Login Now
                </a>
            </div>
        <?php else: ?>
            
            <!-- Discount Items -->
            <div class="row">
                <?php if (empty($discount_items)): ?>
                    <div class="col-12">
                        <div class="alert alert-info text-center">
                            <h5><i class="bi bi-info-circle"></i> No Discounts Available</h5>
                            <p>There are currently no discount codes available for purchase.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($discount_items as $item): ?>
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card discount-card position-relative">
                                <div class="discount-badge">
                                    <?= $item['discount_percent'] ?? 10 ?>%<br>
                                    <small style="font-size: 0.7rem;">OFF</small>
                                </div>
                                
                                <div class="card-body p-4">
                                    <h5 class="card-title"><?= htmlspecialchars($item['item_name']) ?></h5>
                                    <p class="card-text text-muted">
                                        <?= htmlspecialchars($item['item_description'] ?? 'Discount code for participating businesses') ?>
                                    </p>
                                    
                                    <div class="price-section">
                                        <div class="qr-price">
                                            <div class="qr-coin-icon me-2" style="width: 30px; height: 30px; font-size: 0.8rem;">QR</div>
                                            <?= number_format($item['qr_coin_cost']) ?> coins
                                        </div>
                                        
                                        <button class="purchase-btn btn btn-success w-100 mt-3" 
                                                onclick="purchaseDiscount(<?= $item['id'] ?>, '<?= htmlspecialchars($item['item_name']) ?>', <?= $item['qr_coin_cost'] ?>)"
                                                <?= ($user_balance < $item['qr_coin_cost']) ? 'disabled' : '' ?>>
                                            <?php if ($user_balance < $item['qr_coin_cost']): ?>
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
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- FIXED Purchase Success Modal -->
    <div class="modal fade" id="purchaseModal" tabindex="-1" aria-labelledby="purchaseModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="purchaseModalLabel">
                        <i class="bi bi-check-circle"></i> Purchase Successful!
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <div id="purchaseResult"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Continue Shopping</button>
                    <a href="/html/user/discount-codes.php" class="btn btn-outline-primary">View My Codes</a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // CRITICAL FIX: Enhanced modal management with error recovery
        let currentModal = null;
        let modalTimeout = null;
        
        // Force close any stuck modals
        function forceCloseModal() {
            console.log('🚨 Force closing stuck modal');
            
            // Hide emergency button
            document.getElementById('emergencyClose').classList.remove('show');
            
            // Close any Bootstrap modals
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                const bsModal = bootstrap.Modal.getInstance(modal);
                if (bsModal) {
                    bsModal.hide();
                }
            });
            
            // Remove any remaining backdrops
            const backdrops = document.querySelectorAll('.modal-backdrop');
            backdrops.forEach(backdrop => backdrop.remove());
            
            // Remove modal-open class from body
            document.body.classList.remove('modal-open');
            document.body.style.overflow = '';
            document.body.style.paddingRight = '';
            
            // Clear timeout
            if (modalTimeout) {
                clearTimeout(modalTimeout);
                modalTimeout = null;
            }
            
            currentModal = null;
        }
        
        // Enhanced purchase function with better error handling
        async function purchaseDiscount(itemId, itemName, price) {
            const button = event.target.closest('.purchase-btn');
            if (!button || button.disabled) {
                console.log('Button not available for purchase');
                return;
            }
            
            const originalText = button.innerHTML;
            button.innerHTML = '<div class="loading-spinner"></div>';
            button.disabled = true;
            
            try {
                console.log('🛒 Starting purchase:', { itemId, itemName, price });
                
                const response = await fetch('/html/api/purchase-discount.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        item_id: itemId,
                        machine_id: '<?= $machine_id ?>',
                        source: '<?= $source ?>'
                    })
                });
                
                if (!response.ok) {
                    throw new Error(`Server error: ${response.status}`);
                }
                
                const result = await response.json();
                console.log('💰 Purchase result:', result);
                
                if (result.success) {
                    showPurchaseSuccess(result);
                    updateUserBalance();
                    
                    // Mark button as purchased
                    button.innerHTML = '<i class="bi bi-check"></i> Purchased';
                    button.classList.remove('btn-success');
                    button.classList.add('btn-outline-success');
                    button.disabled = true;
                } else {
                    throw new Error(result.error || 'Purchase failed');
                }
                
            } catch (error) {
                console.error('❌ Purchase error:', error);
                
                // Show user-friendly error
                let errorMessage = 'Purchase failed. ';
                if (error.message.includes('Server error')) {
                    errorMessage += 'Please try again later.';
                } else if (error.message.includes('Insufficient')) {
                    errorMessage += 'You need more QR coins.';
                } else if (error.message.includes('not authenticated')) {
                    errorMessage += 'Please log in first.';
                } else {
                    errorMessage += error.message;
                }
                
                // Use alert instead of modal for errors (more reliable)
                alert('⚠️ ' + errorMessage);
                
                // Restore button
                button.innerHTML = originalText;
                button.disabled = false;
            }
        }
        
        // FIXED: Show purchase success modal with timeout protection
        function showPurchaseSuccess(result) {
            try {
                const modalContent = `
                    <div class="text-center">
                        <div class="mb-3">
                            <i class="bi bi-ticket-perforated text-success" style="font-size: 3rem;"></i>
                        </div>
                        <h5>${result.item_name}</h5>
                        <div class="bg-light p-3 rounded mt-3">
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
                
                // Show modal with error protection
                const modalElement = document.getElementById('purchaseModal');
                currentModal = new bootstrap.Modal(modalElement, {
                    backdrop: 'static',
                    keyboard: true
                });
                
                // Show emergency close button after 2 seconds
                modalTimeout = setTimeout(() => {
                    document.getElementById('emergencyClose').classList.add('show');
                }, 2000);
                
                // Clear emergency button when modal closes
                modalElement.addEventListener('hidden.bs.modal', function() {
                    document.getElementById('emergencyClose').classList.remove('show');
                    if (modalTimeout) {
                        clearTimeout(modalTimeout);
                        modalTimeout = null;
                    }
                    currentModal = null;
                });
                
                currentModal.show();
                
            } catch (error) {
                console.error('❌ Modal error:', error);
                // Fallback to alert if modal fails
                alert(`✅ Purchase Successful!\n\nDiscount Code: ${result.discount_code}\nDiscount: ${result.discount_percent}%\nExpires: ${new Date(result.expires_at).toLocaleDateString()}`);
            }
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
        
        // Keyboard shortcut to force close modal (ESC key)
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && currentModal) {
                forceCloseModal();
            }
        });
        
        // Auto-cleanup on page unload
        window.addEventListener('beforeunload', function() {
            if (currentModal) {
                forceCloseModal();
            }
        });
        
        console.log('🔧 Discount Store - FIXED VERSION loaded');
    </script>
</body>
</html> 