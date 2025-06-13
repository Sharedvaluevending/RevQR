<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/qr_coin_manager.php';
require_once __DIR__ . '/../core/store_manager.php';
require_once __DIR__ . '/../core/loot_box_manager.php';

// Require user role
require_role('user');

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_POST['action']) {
            case 'purchase':
                $item_id = (int)($_POST['item_id'] ?? 0);
                $store_type = $_POST['store_type'] ?? 'qr_store';
                
                if (!$item_id) {
                    throw new Exception('Invalid item ID');
                }
                
                if ($store_type === 'business_store') {
                    // Handle business store purchase
                    $result = StoreManager::purchaseBusinessItem($_SESSION['user_id'], $item_id);
                    echo json_encode($result);
                    exit;
                }
                
                // Get QR store item details
                $stmt = $pdo->prepare("SELECT * FROM qr_store_items WHERE id = ? AND is_active = 1");
                $stmt->execute([$item_id]);
                $item = $stmt->fetch();
                
                if (!$item) {
                    throw new Exception('Item not found or inactive');
                }
                
                // Check user balance
                $user_balance = QRCoinManager::getBalance($_SESSION['user_id']);
                if ($user_balance < $item['qr_coin_cost']) {
                    throw new Exception('Insufficient QR coins');
                }
                
                // Check if user already owns this item (for non-consumable items)
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) FROM user_qr_store_purchases 
                    WHERE user_id = ? AND qr_store_item_id = ? AND status = 'active'
                ");
                $stmt->execute([$_SESSION['user_id'], $item_id]);
                if ($stmt->fetchColumn() > 0 && !in_array($item['item_type'], ['spin_pack', 'slot_pack', 'loot_box'])) {
                    throw new Exception('You already own this item');
                }
                
                // Process purchase
                $pdo->beginTransaction();
                
                // Deduct QR coins
                QRCoinManager::addTransaction(
                    $_SESSION['user_id'],
                    'spending',
                    'qr_store_purchase',
                    -$item['qr_coin_cost'],
                    "Purchased: " . $item['item_name'],
                    ['item_id' => $item_id, 'item_type' => $item['item_type']]
                );
                
                // Add purchase record
                $expires_at = null;
                if (in_array($item['item_type'], ['spin_pack', 'slot_pack', 'vote_pack'])) {
                    $item_data = json_decode($item['item_data'], true);
                    $duration_days = $item_data['duration_days'] ?? 7;
                    $expires_at = date('Y-m-d H:i:s', strtotime("+{$duration_days} days"));
                }
                
                $stmt = $pdo->prepare("
                    INSERT INTO user_qr_store_purchases 
                    (user_id, qr_store_item_id, qr_coins_spent, item_data, expires_at) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $_SESSION['user_id'], 
                    $item_id, 
                    $item['qr_coin_cost'],
                    $item['item_data'],
                    $expires_at
                ]);
                
                $purchase_id = $pdo->lastInsertId();
                
                // Handle vote pack purchases - add votes to user's vote pack balance
                if ($item['item_type'] === 'vote_pack') {
                    $item_data = json_decode($item['item_data'], true);
                    $votes_to_add = $item_data['votes'] ?? 0;
                    
                    if ($votes_to_add > 0) {
                        $stmt = $pdo->prepare("
                            INSERT INTO user_vote_packs 
                            (user_id, purchase_id, votes_total, votes_used, votes_remaining, expires_at) 
                            VALUES (?, ?, ?, 0, ?, ?)
                        ");
                        $stmt->execute([
                            $_SESSION['user_id'],
                            $purchase_id,
                            $votes_to_add,
                            $votes_to_add,
                            $expires_at
                        ]);
                    }
                }
                
                $pdo->commit();
                
                // Emit purchase event for real-time updates
                $purchase_event_data = [
                    'itemType' => $item['item_type'],
                    'itemName' => $item['item_name'],
                    'purchaseId' => $purchase_id
                ];
                
                echo json_encode([
                    'success' => true, 
                    'message' => 'Purchase successful!',
                    'purchaseEvent' => $purchase_event_data
                ]);
                break;
                
            case 'get_purchase_history':
                $stmt = $pdo->prepare("
                    SELECT uqsp.*, qsi.item_name, qsi.item_type
                    FROM user_qr_store_purchases uqsp
                    JOIN qr_store_items qsi ON uqsp.qr_store_item_id = qsi.id
                    WHERE uqsp.user_id = ?
                    ORDER BY uqsp.created_at DESC
                    LIMIT 10
                ");
                $stmt->execute([$_SESSION['user_id']]);
                $purchases = $stmt->fetchAll();
                
                echo json_encode(['success' => true, 'purchases' => $purchases]);
                break;
                
            case 'open_loot_box':
                $purchase_id = (int)($_POST['purchase_id'] ?? 0);
                
                if (!$purchase_id) {
                    throw new Exception('Invalid purchase ID');
                }
                
                $result = LootBoxManager::openLootBox($_SESSION['user_id'], $purchase_id);
                echo json_encode($result);
                break;
                
            case 'get_unopened_loot_boxes':
                // Get user's unopened loot boxes
                $stmt = $pdo->prepare("
                    SELECT 
                        uqsp.id as purchase_id,
                        uqsp.created_at,
                        qsi.item_name,
                        qsi.rarity,
                        qsi.item_data
                    FROM user_qr_store_purchases uqsp
                    JOIN qr_store_items qsi ON uqsp.qr_store_item_id = qsi.id
                    WHERE uqsp.user_id = ? 
                    AND qsi.item_type = 'loot_box' 
                    AND uqsp.status = 'active'
                    AND uqsp.id NOT IN (SELECT purchase_id FROM loot_box_openings WHERE user_id = ?)
                    ORDER BY uqsp.created_at DESC
                ");
                $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
                $loot_boxes = $stmt->fetchAll();
                
                echo json_encode(['success' => true, 'loot_boxes' => $loot_boxes]);
                break;
                
            default:
                throw new Exception('Invalid action');
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
}

// Get user QR coin balance
$user_qr_balance = QRCoinManager::getBalance($_SESSION['user_id']);

// Get available QR store items
$qr_store_items = StoreManager::getQRStoreItems();

// Get available business store items (discounts)
$business_store_items = StoreManager::getAllBusinessStoreItems(true);

// Merge all items for display
$all_items = array_merge($qr_store_items, $business_store_items);

// Group by category for better display
$items_by_category = [];
foreach ($all_items as $item) {
    $category = $item['category'] ?? 'Other';
    $items_by_category[$category][] = $item;
}

require_once __DIR__ . '/../core/includes/header.php';

// Handle category filter
$category_filter = $_GET['category'] ?? 'all';
$filtered_items = $all_items;

if ($category_filter !== 'all') {
    $filtered_items = array_filter($all_items, function($item) use ($category_filter) {
        // Handle both QR store items (item_type) and business store items (category)
        $item_category = $item['item_type'] ?? $item['category'] ?? '';
        return $item_category === $category_filter || ($category_filter === 'discount' && isset($item['business_id']));
    });
}

// Count items by category
$category_counts = [];
foreach ($all_items as $item) {
    if (isset($item['business_id'])) {
        // Business store item
        $type = 'discount';
    } else {
        // QR store item
        $type = $item['item_type'];
    }
    $category_counts[$type] = ($category_counts[$type] ?? 0) + 1;
}
?>

<div class="container py-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="mb-0"><i class="bi bi-gem text-info me-2"></i>QR Store</h1>
                    <p class="text-muted mb-0">Premium avatars, boosts & exclusive features</p>
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

    <!-- Category Filter Navigation -->
    <div class="row mb-4">
        <div class="col-12">
            <nav class="navbar navbar-expand-lg navbar-light bg-light rounded">
                <div class="container-fluid">
                    <span class="navbar-brand mb-0 h1 small">Filter by Category:</span>
                    <div class="navbar-nav">
                        <a class="nav-link <?php echo $category_filter === 'all' ? 'active' : ''; ?>" 
                           href="?category=all">
                            <i class="bi bi-grid me-1"></i>All Items (<?php echo count($all_items); ?>)
                        </a>
                        <a class="nav-link <?php echo $category_filter === 'discount' ? 'active' : ''; ?>" 
                           href="?category=discount">
                            <i class="bi bi-percent me-1"></i>Business Discounts (<?php echo $category_counts['discount'] ?? 0; ?>)
                        </a>
                        <a class="nav-link <?php echo $category_filter === 'avatar' ? 'active' : ''; ?>" 
                           href="?category=avatar">
                            <i class="bi bi-person-circle me-1"></i>Avatars (<?php echo $category_counts['avatar'] ?? 0; ?>)
                        </a>
                        <a class="nav-link <?php echo $category_filter === 'spin_pack' ? 'active' : ''; ?>" 
                           href="?category=spin_pack">
                            <i class="bi bi-arrow-clockwise me-1"></i>Spin Wheel Packs (<?php echo $category_counts['spin_pack'] ?? 0; ?>)
                        </a>
                        <a class="nav-link <?php echo $category_filter === 'slot_pack' ? 'active text-danger fw-bold' : ''; ?>" 
                           href="?category=slot_pack">
                            <i class="bi bi-suit-diamond-fill me-1"></i>Casino Spins (<?php echo $category_counts['slot_pack'] ?? 0; ?>)
                        </a>
                        <?php if (($category_counts['vote_pack'] ?? 0) > 0): ?>
                        <a class="nav-link <?php echo $category_filter === 'vote_pack' ? 'active' : ''; ?>" 
                           href="?category=vote_pack">
                            <i class="bi bi-check2-square me-1"></i>Vote Packs (<?php echo $category_counts['vote_pack'] ?? 0; ?>)
                        </a>
                        <?php endif; ?>
                        <?php if (($category_counts['boost'] ?? 0) > 0): ?>
                        <a class="nav-link <?php echo $category_filter === 'boost' ? 'active' : ''; ?>" 
                           href="?category=boost">
                            <i class="bi bi-lightning me-1"></i>Boosts (<?php echo $category_counts['boost'] ?? 0; ?>)
                        </a>
                        <?php endif; ?>
                        <?php if (($category_counts['loot_box'] ?? 0) > 0): ?>
                        <a class="nav-link <?php echo $category_filter === 'loot_box' ? 'active text-warning fw-bold' : ''; ?>" 
                           href="?category=loot_box">
                            <i class="bi bi-gift me-1"></i>Loot Boxes (<?php echo $category_counts['loot_box'] ?? 0; ?>)
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </nav>
        </div>
    </div>

            <?php if (empty($all_items)): ?>
        <div class="row">
            <div class="col-12">
                <div class="card text-center py-5">
                    <div class="card-body">
                        <i class="bi bi-gem display-1 text-muted mb-3"></i>
                        <h3 class="text-muted mb-3">QR Store Coming Soon</h3>
                        <p class="text-muted mb-4">Premium items and features will be available soon! Keep earning QR coins by voting and spinning.</p>
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
            <?php foreach ($filtered_items as $item): ?>
                <div class="col-md-6 col-lg-4">
                    <?php 
                    $is_business_item = isset($item['business_id']);
                    $rarity = $item['rarity'] ?? 'common';
                    $border_color = $rarity === 'legendary' ? 'warning' : 
                                   ($rarity === 'epic' ? 'info' : 
                                    ($rarity === 'rare' ? 'success' : 'secondary'));
                    
                    // Override for business items
                    if ($is_business_item) {
                        $border_color = 'primary';
                    }
                    ?>
                    <div class="card h-100 shadow-sm border-<?php echo $border_color; ?>">
                        <div class="card-header bg-<?php echo $border_color; ?> text-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    <i class="bi bi-<?php 
                                        if ($is_business_item) {
                                            echo 'percent';
                                        } else {
                                            echo $item['item_type'] === 'avatar' ? 'person-circle' : 
                                                 ($item['item_type'] === 'spin_pack' ? 'arrow-clockwise' : 
                                                  ($item['item_type'] === 'slot_pack' ? 'suit-diamond-fill' : 
                                                   ($item['item_type'] === 'loot_box' ? 'gift-fill' : 'gem'))); 
                                        }
                                    ?> me-2"></i>
                                    <?php echo htmlspecialchars($item['item_name']); ?>
                                </h5>
                                <span class="badge bg-light text-dark">
                                    <?php echo $is_business_item ? 'Business Discount' : ucfirst($rarity); ?>
                                </span>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if ($is_business_item): ?>
                                <!-- Business Store Item Display -->
                                <div class="text-center mb-3">
                                    <div class="discount-preview bg-primary bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center" 
                                         style="width: 120px; height: 120px;">
                                        <div class="text-center">
                                            <div class="display-4 text-primary fw-bold"><?php echo $item['discount_percentage']; ?>%</div>
                                            <small class="text-muted">OFF</small>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <h6 class="text-muted small mb-2">BUSINESS:</h6>
                                    <div class="text-primary">
                                        <i class="bi bi-building me-1"></i>
                                        <?php echo htmlspecialchars($item['business_name'] ?? 'Business'); ?>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="row text-center">
                                        <div class="col-4">
                                            <div class="text-success">
                                                <i class="bi bi-cash-stack"></i>
                                                <br><strong>$<?php echo number_format($item['regular_price_cents'] / 100, 2); ?></strong>
                                                <br><small>Regular Price</small>
                                            </div>
                                        </div>
                                        <div class="col-4">
                                            <div class="text-primary">
                                                <i class="bi bi-percent"></i>
                                                <br><strong><?php echo $item['discount_percentage']; ?>%</strong>
                                                <br><small>Discount</small>
                                            </div>
                                        </div>
                                        <div class="col-4">
                                            <div class="text-warning">
                                                <i class="bi bi-piggy-bank"></i>
                                                <br><strong>$<?php echo number_format(($item['regular_price_cents'] * $item['discount_percentage']) / 10000, 2); ?></strong>
                                                <br><small>You Save</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <?php if ($item['max_per_user'] > 0): ?>
                                <div class="mb-3">
                                    <small class="text-muted">
                                        <i class="bi bi-info-circle me-1"></i>
                                        Limit: <?php echo $item['max_per_user']; ?> per customer
                                    </small>
                                </div>
                                <?php endif; ?>
                                
                            <?php elseif ($item['item_type'] === 'avatar' && $item['item_data']): 
                                // Handle both JSON string and already decoded array
                                $item_data = is_string($item['item_data']) ? json_decode($item['item_data'], true) : $item['item_data'];
                                $avatar_id = $item_data['avatar_id'] ?? '';
                                if ($avatar_id): ?>
                                    <div class="text-center mb-3">
                                        <div class="avatar-preview-container position-relative" data-rarity="<?php echo $item['rarity']; ?>" style="display: inline-block;">
                                            <img src="../assets/img/avatars/<?php echo htmlspecialchars($avatar_id); ?>.png" 
                                                 alt="<?php echo htmlspecialchars($item['item_name']); ?>" 
                                                 class="avatar-preview img-fluid rounded-circle border border-3"
                                                 style="width: 120px; height: 120px; object-fit: cover; 
                                                        border-color: <?php 
                                                            echo $item['rarity'] === 'legendary' ? '#ffc107' : 
                                                                 ($item['rarity'] === 'epic' ? '#0dcaf0' : 
                                                                  ($item['rarity'] === 'rare' ? '#198754' : '#6c757d')); 
                                                        ?> !important;
                                                        box-shadow: 0 0 20px rgba(<?php 
                                                            echo $item['rarity'] === 'legendary' ? '255, 193, 7' : 
                                                                 ($item['rarity'] === 'epic' ? '13, 202, 240' : 
                                                                  ($item['rarity'] === 'rare' ? '25, 135, 84' : '108, 117, 125')); 
                                                        ?>, 0.3);">
                                            <?php if (isset($item_data['special_effects']) && $item_data['special_effects']): ?>
                                                <div class="avatar-glow"></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endif; 
                            endif; ?>
                            
                            <?php if ($item['item_description']): ?>
                                <p class="text-muted mb-3"><?php echo htmlspecialchars($item['item_description']); ?></p>
                            <?php endif; ?>
                            
                            <?php if (isset($item['item_type']) && $item['item_type'] === 'slot_pack' && $item['item_data']): 
                                $slot_data = is_string($item['item_data']) ? json_decode($item['item_data'], true) : $item['item_data']; ?>
                                <!-- Slot Pack Image -->
                                <div class="text-center mb-3">
                                    <?php if (!empty($item['image_url'])): ?>
                                        <div class="slot-pack-preview position-relative d-inline-block">
                                            <img src="<?php echo htmlspecialchars($item['image_url']); ?>" 
                                                 alt="<?php echo htmlspecialchars($item['item_name']); ?>" 
                                                 class="img-fluid rounded spin-pack-image"
                                                 style="width: 120px; height: 120px; object-fit: contain; 
                                                        border: 3px solid <?php 
                                                            echo $item['rarity'] === 'legendary' ? '#ffc107' : 
                                                                 ($item['rarity'] === 'epic' ? '#dc3545' : 
                                                                  ($item['rarity'] === 'rare' ? '#198754' : '#6c757d')); 
                                                            ?>;
                                                            box-shadow: 0 0 20px rgba(<?php 
                                                                echo $item['rarity'] === 'legendary' ? '255, 193, 7, 0.6' : 
                                                                     ($item['rarity'] === 'epic' ? '220, 53, 69, 0.6' : 
                                                                      ($item['rarity'] === 'rare' ? '25, 135, 84, 0.6' : '108, 117, 125, 0.4')); 
                                                            ?>);
                                                            animation: <?php echo $item['rarity'] === 'legendary' ? 'slot-pack-glow 2s ease-in-out infinite alternate' : 'none'; ?>;">
                                            <?php if ($item['rarity'] === 'legendary'): ?>
                                                <div class="position-absolute top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center">
                                                    <i class="bi bi-star-fill text-warning position-absolute" style="font-size: 1.2rem; top: 5px; right: 5px;"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="row text-center">
                                        <div class="col-4">
                                            <div class="text-danger">
                                                <i class="bi bi-plus-circle-fill"></i>
                                                <br><strong><?php echo $slot_data['spins_per_day'] ?? 0; ?></strong>
                                                <br><small>Extra Spins/Day</small>
                                            </div>
                                        </div>
                                        <div class="col-4">
                                            <div class="text-primary">
                                                <i class="bi bi-calendar-week"></i>
                                                <br><strong><?php echo $slot_data['duration_days'] ?? 0; ?></strong>
                                                <br><small>Days</small>
                                            </div>
                                        </div>
                                        <div class="col-4">
                                            <div class="text-success">
                                                <i class="bi bi-building"></i>
                                                <br><strong>All</strong>
                                                <br><small>Casinos</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php elseif (isset($item['item_type']) && $item['item_type'] === 'spin_pack' && $item['item_data']): 
                                $spin_data = is_string($item['item_data']) ? json_decode($item['item_data'], true) : $item['item_data']; ?>
                                <!-- Spin Pack Image -->
                                <div class="text-center mb-3">
                                    <?php if (!empty($item['image_url'])): ?>
                                        <div class="spin-pack-preview position-relative d-inline-block">
                                            <img src="<?php echo htmlspecialchars($item['image_url']); ?>" 
                                                 alt="<?php echo htmlspecialchars($item['item_name']); ?>" 
                                                 class="img-fluid rounded spin-pack-image"
                                                 style="width: 120px; height: 120px; object-fit: contain; 
                                                        border: 3px solid <?php 
                                                            echo $item['rarity'] === 'legendary' ? '#ffc107' : 
                                                                 ($item['rarity'] === 'epic' ? '#0dcaf0' : 
                                                                  ($item['rarity'] === 'rare' ? '#198754' : '#6c757d')); 
                                                            ?>;
                                                            box-shadow: 0 0 20px rgba(<?php 
                                                                echo $item['rarity'] === 'legendary' ? '255, 193, 7, 0.6' : 
                                                                     ($item['rarity'] === 'epic' ? '13, 202, 240, 0.6' : 
                                                                      ($item['rarity'] === 'rare' ? '25, 135, 84, 0.6' : '108, 117, 125, 0.4')); 
                                                            ?>);
                                                            animation: <?php echo $item['rarity'] === 'legendary' ? 'spin-pack-glow 2s ease-in-out infinite alternate' : 'none'; ?>;">
                                            <?php if ($item['rarity'] === 'legendary'): ?>
                                                <div class="position-absolute top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center">
                                                    <i class="bi bi-star-fill text-warning position-absolute" style="font-size: 1.2rem; top: 5px; right: 5px;"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="row text-center">
                                        <div class="col-4">
                                            <div class="text-warning">
                                                <i class="bi bi-plus-circle-fill"></i>
                                                <br><strong><?php echo $spin_data['spins_per_day'] ?? 0; ?></strong>
                                                <br><small>Extra Spins/Day</small>
                                            </div>
                                        </div>
                                        <div class="col-4">
                                            <div class="text-primary">
                                                <i class="bi bi-calendar-week"></i>
                                                <br><strong><?php echo $spin_data['duration_days'] ?? 0; ?></strong>
                                                <br><small>Days</small>
                                            </div>
                                        </div>
                                        <div class="col-4">
                                            <div class="text-success">
                                                <i class="bi bi-arrow-clockwise"></i>
                                                <br><strong>Spin</strong>
                                                <br><small>Wheel</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php elseif (isset($item['item_type']) && $item['item_type'] === 'loot_box' && $item['item_data']): 
                                $loot_data = is_string($item['item_data']) ? json_decode($item['item_data'], true) : $item['item_data']; ?>
                                <!-- Loot Box Display -->
                                <div class="text-center mb-3">
                                    <div class="loot-box-preview position-relative d-inline-block">
                                        <img src="<?php echo $loot_data['image'] ?? '/assets/qrstore/commonlootad.png'; ?>" 
                                             alt="<?php echo htmlspecialchars($item['item_name']); ?>" 
                                             class="img-fluid rounded loot-box-image"
                                             style="width: 140px; height: 140px; object-fit: contain; 
                                                    border: 3px solid <?php 
                                                        echo $item['rarity'] === 'legendary' ? '#ffc107' : 
                                                             ($item['rarity'] === 'rare' ? '#198754' : '#6c757d'); 
                                                    ?>;
                                                    box-shadow: 0 0 25px rgba(<?php 
                                                        echo $item['rarity'] === 'legendary' ? '255, 193, 7, 0.8' : 
                                                             ($item['rarity'] === 'rare' ? '25, 135, 84, 0.6' : '108, 117, 125, 0.4'); 
                                                    ?>);
                                                    animation: <?php echo $item['rarity'] === 'legendary' ? 'loot-box-glow 2s ease-in-out infinite alternate' : 'none'; ?>;">
                                        <?php if ($item['rarity'] === 'legendary'): ?>
                                            <div class="position-absolute top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center">
                                                <i class="bi bi-star-fill text-warning position-absolute" style="font-size: 1.5rem; top: 5px; right: 5px;"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="row text-center">
                                        <div class="col-4">
                                            <div class="text-<?php echo $item['rarity'] === 'legendary' ? 'warning' : ($item['rarity'] === 'rare' ? 'success' : 'info'); ?>">
                                                <i class="bi bi-gift-fill"></i>
                                                <br><strong><?php echo $loot_data['min_rewards'] ?? 3; ?>-<?php echo $loot_data['max_rewards'] ?? 5; ?></strong>
                                                <br><small>Rewards</small>
                                            </div>
                                        </div>
                                        <div class="col-4">
                                            <div class="text-<?php echo $item['rarity'] === 'legendary' ? 'warning' : ($item['rarity'] === 'rare' ? 'success' : 'primary'); ?>">
                                                <i class="bi bi-gem"></i>
                                                <br><strong><?php echo ucfirst($item['rarity']); ?></strong>
                                                <br><small>Rarity</small>
                                            </div>
                                        </div>
                                        <div class="col-4">
                                            <div class="text-info">
                                                <i class="bi bi-magic"></i>
                                                <br><strong><?php echo $item['rarity'] === 'legendary' ? 'Epic' : ($item['rarity'] === 'rare' ? 'Great' : 'Good'); ?></strong>
                                                <br><small>Loot</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <?php if (isset($loot_data['possible_rewards'])): ?>
                                <div class="mb-3">
                                    <h6 class="text-muted small mb-2">POSSIBLE REWARDS:</h6>
                                    <div class="d-flex flex-wrap gap-1">
                                        <?php foreach (array_slice($loot_data['possible_rewards'], 0, 4) as $reward): ?>
                                            <span class="badge bg-<?php echo $item['rarity'] === 'legendary' ? 'warning' : ($item['rarity'] === 'rare' ? 'success' : 'secondary'); ?> text-dark">
                                                <i class="bi bi-<?php 
                                                    echo $reward === 'qr_coins' || $reward === 'massive_qr_coins' ? 'coin' : 
                                                         ($reward === 'spins' || $reward === 'premium_spins' ? 'arrow-repeat' : 
                                                          ($reward === 'votes' || $reward === 'premium_votes' ? 'hand-thumbs-up' : 'lightning')); 
                                                ?> me-1"></i>
                                                <?php echo ucfirst(str_replace(['_', 'qr_coins', 'massive_qr_coins'], [' ', 'QR Coins', 'QR Coins'], $reward)); ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                            <div class="mb-3">
                                <span class="badge bg-light text-dark">
                                    <i class="bi bi-tag me-1"></i>
                                    <?php 
                                    if ($is_business_item) {
                                        echo 'Business Discount';
                                    } else {
                                        echo ucfirst(str_replace('_', ' ', $item['item_type'] ?? 'item'));
                                    }
                                    ?>
                                </span>
                                <?php if (isset($item['category']) && $item['category']): ?>
                                    <span class="badge bg-outline-secondary ms-1"><?php echo htmlspecialchars($item['category']); ?></span>
                                <?php endif; ?>
                            </div>

                            <?php if (isset($item['effects']) && $item['effects']): ?>
                                <div class="mb-3">
                                    <h6 class="text-muted small mb-2">EFFECTS:</h6>
                                    <div class="text-success small">
                                        <i class="bi bi-magic me-1"></i>
                                        <?php echo htmlspecialchars($item['effects']); ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Flash Sale Timer -->
                            <?php if (isset($item['flash_sale']) && $item['flash_sale'] && isset($item['valid_until'])): ?>
                                <div class="flash-sale-timer mb-3">
                                    <div class="alert alert-warning p-2 mb-2">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <strong class="text-dark">
                                                <i class="bi bi-lightning-charge me-1"></i>FLASH SALE!
                                            </strong>
                                            <div class="countdown-timer" data-end-time="<?php echo strtotime($item['valid_until']); ?>">
                                                <small class="text-dark fw-bold">
                                                    <i class="bi bi-clock me-1"></i>
                                                    <span class="countdown-display">Loading...</span>
                                                </small>
                                            </div>
                                        </div>
                                        <?php if (isset($item['original_price'])): ?>
                                            <div class="price-comparison mt-1">
                                                <span class="text-muted text-decoration-line-through">
                                                    <?php echo number_format($item['original_price']); ?> QR
                                                </span>
                                                <span class="text-success fw-bold ms-2">
                                                    <?php echo number_format($item['qr_coin_cost']); ?> QR
                                                </span>
                                                <span class="badge bg-success ms-1">
                                                    <?php echo round((($item['original_price'] - $item['qr_coin_cost']) / $item['original_price']) * 100); ?>% OFF
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <div class="d-flex justify-content-between align-items-center mt-auto">
                                <div class="d-flex align-items-center">
                                    <img src="../img/qrCoin.png" alt="QR Coin" style="width: 1.5rem; height: 1.5rem;" class="me-1">
                                    <strong class="text-warning fs-5"><?php echo number_format($item['qr_coin_cost']); ?></strong>
                                </div>
                                <?php if ($user_qr_balance >= $item['qr_coin_cost']): ?>
                                    <button class="btn btn-<?php 
                                        if ($is_business_item) {
                                            echo 'primary';
                                        } else {
                                            echo $rarity === 'legendary' ? 'warning' : 
                                                 ($rarity === 'epic' ? 'info' : 
                                                  ($rarity === 'rare' ? 'success' : 'primary')); 
                                        }
                                    ?> btn-sm purchase-btn" 
                                            data-item-id="<?php echo $item['id']; ?>"
                                            data-item-name="<?php echo htmlspecialchars($item['item_name']); ?>"
                                            data-qr-cost="<?php echo $item['qr_coin_cost']; ?>"
                                            data-store-type="<?php echo $is_business_item ? 'business_store' : 'qr_store'; ?>"
                                            data-item-type="<?php echo $is_business_item ? 'discount' : ($item['item_type'] ?? 'item'); ?>">
                                        <i class="bi bi-cart-plus me-1"></i>
                                        <?php echo $is_business_item ? 'Buy Discount' : 'Purchase'; ?>
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

        <!-- My Loot Boxes -->
        <div class="row mt-5">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-gift-fill me-2 text-warning"></i>My Loot Boxes</h5>
                        <button class="btn btn-sm btn-outline-warning" onclick="loadLootBoxes()">
                            <i class="bi bi-arrow-clockwise me-1"></i>Refresh
                        </button>
                    </div>
                    <div class="card-body">
                        <div id="loot-boxes-container">
                            <div class="text-center py-3">
                                <div class="spinner-border spinner-border-sm text-warning" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                                <p class="text-muted mt-2 mb-0">Loading your loot boxes...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Purchase History -->
        <div class="row mt-5">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>Your QR Store Purchases</h5>
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
                    <i class="bi bi-gem me-2"></i>Confirm QR Store Purchase
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="text-center">
                    <i class="bi bi-question-circle display-4 text-warning mb-3"></i>
                    <h5 id="confirm-item-name"></h5>
                    <p class="text-muted mb-3">Are you sure you want to purchase this item?</p>
                    <div class="d-flex justify-content-center align-items-center gap-2 mb-3">
                        <img src="../img/qrCoin.png" alt="QR Coin" style="width: 2rem; height: 2rem;">
                        <h4 class="text-warning mb-0" id="confirm-qr-cost"></h4>
                        <span class="text-muted">QR Coins</span>
                    </div>
                    <div class="alert alert-info">
                        <small>
                            <i class="bi bi-info-circle me-1"></i>
                            This item will be added to your account immediately after purchase.
                        </small>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x me-1"></i>Cancel
                </button>
                <button type="button" class="btn btn-primary" id="confirm-purchase">
                    <i class="bi bi-gem me-1"></i>Confirm Purchase
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Loot Box Opening Modal -->
<div class="modal fade" id="lootBoxModal" tabindex="-1" aria-labelledby="lootBoxModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title" id="lootBoxModalLabel">
                    <i class="bi bi-gift-fill me-2"></i>Open Loot Box
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <div id="loot-box-opening-stage">
                    <!-- Stage 1: Confirmation -->
                    <div id="opening-confirm" class="opening-stage">
                        <i class="bi bi-gift display-1 text-warning mb-3"></i>
                        <h4 id="loot-box-name">Loot Box</h4>
                        <p class="text-muted mb-4">Ready to discover what's inside?</p>
                        <button class="btn btn-warning btn-lg" onclick="startOpening()">
                            <i class="bi bi-unlock me-2"></i>Open Loot Box!
                        </button>
                    </div>
                    
                    <!-- Stage 2: Opening Animation -->
                    <div id="opening-animation" class="opening-stage d-none">
                        <div class="loot-box-opening-animation mb-4">
                            <i class="bi bi-gift display-1 text-warning opening-box"></i>
                        </div>
                        <h4 class="text-warning">Opening...</h4>
                        <div class="progress">
                            <div class="progress-bar bg-warning progress-bar-striped progress-bar-animated" 
                                 role="progressbar" style="width: 0%" id="opening-progress"></div>
                        </div>
                    </div>
                    
                    <!-- Stage 3: Rewards Display -->
                    <div id="opening-rewards" class="opening-stage d-none">
                        <h4 class="text-success mb-4">
                            <i class="bi bi-check-circle-fill me-2"></i>Loot Box Opened!
                        </h4>
                        <div id="rewards-container" class="row g-3">
                            <!-- Rewards will be populated here -->
                        </div>
                        <div class="mt-4">
                            <button class="btn btn-success" data-bs-dismiss="modal">
                                <i class="bi bi-check me-2"></i>Awesome!
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
@keyframes loot-box-glow {
    from { box-shadow: 0 0 25px rgba(255, 193, 7, 0.8); }
    to { box-shadow: 0 0 35px rgba(255, 193, 7, 1), 0 0 50px rgba(255, 193, 7, 0.6); }
}

@keyframes opening-shake {
    0%, 100% { transform: translateX(0); }
    25% { transform: translateX(-5px) rotate(-2deg); }
    75% { transform: translateX(5px) rotate(2deg); }
}

.opening-box {
    animation: opening-shake 0.5s ease-in-out infinite;
}

.reward-item {
    background: linear-gradient(135deg, rgba(255,255,255,0.1), rgba(255,255,255,0.05));
    border: 2px solid rgba(255,255,255,0.2);
    border-radius: 15px;
    padding: 20px;
    text-align: center;
    backdrop-filter: blur(10px);
    transition: all 0.3s ease;
}

.reward-item:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.2);
}

.reward-common { border-color: #6c757d; }
.reward-rare { border-color: #198754; }
.reward-legendary { border-color: #ffc107; }

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Spin Pack and Slot Pack Animations */
@keyframes spin-pack-glow {
    0% { box-shadow: 0 0 20px rgba(255, 193, 7, 0.6); }
    100% { box-shadow: 0 0 35px rgba(255, 193, 7, 1); }
}

@keyframes slot-pack-glow {
    0% { box-shadow: 0 0 20px rgba(220, 53, 69, 0.6); }
    100% { box-shadow: 0 0 35px rgba(220, 53, 69, 1); }
}

/* Spin pack image hover effects */
.spin-pack-image:hover {
    transform: scale(1.05);
    transition: all 0.3s ease;
}

.slot-pack-image:hover {
    transform: scale(1.05);
    transition: all 0.3s ease;
}

/* Enhanced rarity border effects */
.spin-pack-preview, .slot-pack-preview {
    transition: all 0.3s ease;
}

.spin-pack-preview:hover, .slot-pack-preview:hover {

/* Flash Sale Styles */
.flash-sale-timer {
    position: relative;
}

.flash-sale-timer .alert-warning {
    background: linear-gradient(45deg, #fff3cd, #fef3bd);
    border: 2px solid #ffc107;
    animation: flash-pulse 2s ease-in-out infinite;
}

.flash-sale-timer .alert-danger {
    background: linear-gradient(45deg, #f8d7da, #f1aeb5);
    border: 2px solid #dc3545;
    animation: urgent-pulse 1s ease-in-out infinite;
}

@keyframes flash-pulse {
    0% { box-shadow: 0 0 10px rgba(255, 193, 7, 0.4); }
    50% { box-shadow: 0 0 20px rgba(255, 193, 7, 0.8); }
    100% { box-shadow: 0 0 10px rgba(255, 193, 7, 0.4); }
}

@keyframes urgent-pulse {
    0% { box-shadow: 0 0 10px rgba(220, 53, 69, 0.4); }
    50% { box-shadow: 0 0 25px rgba(220, 53, 69, 1); }
    100% { box-shadow: 0 0 10px rgba(220, 53, 69, 0.4); }
}

.countdown-timer {
    font-family: 'Courier New', monospace;
    font-weight: bold;
}

/* Flash sale card highlighting */
.card:has(.flash-sale-timer) {
    border: 2px solid #ffc107;
    box-shadow: 0 4px 20px rgba(255, 193, 7, 0.3);
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const purchaseModal = new bootstrap.Modal(document.getElementById('purchaseModal'));
    let currentItemId = null;
    let currentStoreType = 'qr_store';
    
    // Handle purchase button clicks
    document.querySelectorAll('.purchase-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            currentItemId = this.dataset.itemId;
            currentStoreType = this.dataset.storeType || 'qr_store';
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
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span>Processing...';
        
        // Make purchase request
        fetch('', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=purchase&item_id=' + currentItemId + '&store_type=' + currentStoreType
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Emit purchase event for real-time updates (e.g., slot machine)
                if (data.purchaseEvent) {
                    window.dispatchEvent(new CustomEvent('qrStorePurchase', {
                        detail: data.purchaseEvent
                    }));
                }
                
                location.reload(); // Refresh to show updated balance and status
            } else {
                alert('Purchase failed: ' + data.message);
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-gem me-1"></i>Confirm Purchase';
            }
        })
        .catch(error => {
            console.error('Purchase error:', error);
            alert('Purchase failed. Please try again.');
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-gem me-1"></i>Confirm Purchase';
        });
    });
    
    // Load purchase history
loadPurchaseHistory();

// Load loot boxes
loadLootBoxes();

// Initialize countdown timers
initCountdownTimers();
});

function loadPurchaseHistory() {
    fetch('', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=get_purchase_history'
    })
    .then(response => response.json())
    .then(data => {
        const historyDiv = document.getElementById('purchase-history');
        
        if (data.purchases && data.purchases.length > 0) {
            let html = '<div class="table-responsive"><table class="table table-sm"><thead><tr><th>Item</th><th>Cost</th><th>Date</th><th>Status</th></tr></thead><tbody>';
            
            data.purchases.forEach(purchase => {
                const date = new Date(purchase.created_at).toLocaleDateString();
                const statusColor = purchase.status === 'active' ? 'success' : 'secondary';
                html += `
                    <tr>
                        <td><strong>${purchase.item_name}</strong><br><small class="text-muted">${purchase.item_type.replace('_', ' ')}</small></td>
                        <td><img src="../img/qrCoin.png" style="width: 1rem; height: 1rem;" class="me-1">${Number(purchase.qr_coins_spent).toLocaleString()}</td>
                        <td>${date}</td>
                        <td><span class="badge bg-${statusColor}">${purchase.status}</span></td>
                    </tr>
                `;
            });
            
            html += '</tbody></table></div>';
            historyDiv.innerHTML = html;
        } else {
            historyDiv.innerHTML = '<div class="text-center py-3 text-muted">No purchases yet. Start by buying your first premium item!</div>';
        }
    })
    .catch(error => {
        console.error('Error loading purchase history:', error);
        document.getElementById('purchase-history').innerHTML = '<div class="text-center py-3 text-danger">Failed to load purchase history</div>';
    });
}

// Initialize countdown timers for flash sales
function initCountdownTimers() {
    document.querySelectorAll('.countdown-timer').forEach(timer => {
        const endTime = parseInt(timer.dataset.endTime) * 1000;
        const display = timer.querySelector('.countdown-display');
        
        function updateTimer() {
            const now = new Date().getTime();
            const distance = endTime - now;
            
            if (distance > 0) {
                const days = Math.floor(distance / (1000 * 60 * 60 * 24));
                const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((distance % (1000 * 60)) / 1000);
                
                let timeString = '';
                if (days > 0) timeString += days + 'd ';
                if (hours > 0) timeString += hours + 'h ';
                if (minutes > 0) timeString += minutes + 'm ';
                timeString += seconds + 's';
                
                display.textContent = timeString;
                
                // Add urgency styling for last hour
                if (distance < 3600000) { // Less than 1 hour
                    display.classList.add('text-danger');
                    timer.closest('.alert').classList.add('alert-danger');
                    timer.closest('.alert').classList.remove('alert-warning');
                }
            } else {
                display.textContent = 'EXPIRED';
                display.classList.add('text-danger');
                timer.closest('.alert').classList.add('alert-danger');
                timer.closest('.alert').classList.remove('alert-warning');
                
                // Disable purchase buttons for expired items
                const card = timer.closest('.card');
                if (card) {
                    const purchaseBtn = card.querySelector('.purchase-btn');
                    if (purchaseBtn) {
                        purchaseBtn.disabled = true;
                        purchaseBtn.innerHTML = '<i class="bi bi-clock-history me-1"></i>Expired';
                        purchaseBtn.classList.remove('btn-primary', 'btn-warning', 'btn-info', 'btn-success');
                        purchaseBtn.classList.add('btn-secondary');
                    }
                }
            }
        }
        
        updateTimer();
        setInterval(updateTimer, 1000);
    });
}

// Loot Box Functions
let currentLootBoxId = null;
const lootBoxModal = new bootstrap.Modal(document.getElementById('lootBoxModal'));

function loadLootBoxes() {
    fetch('', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=get_unopened_loot_boxes'
    })
    .then(response => response.json())
    .then(data => {
        const container = document.getElementById('loot-boxes-container');
        
        if (data.loot_boxes && data.loot_boxes.length > 0) {
            let html = '<div class="row g-3">';
            
            data.loot_boxes.forEach(lootBox => {
                const itemData = JSON.parse(lootBox.item_data);
                const rarityColor = lootBox.rarity === 'legendary' ? 'warning' : 
                                  (lootBox.rarity === 'rare' ? 'success' : 'secondary');
                
                html += `
                    <div class="col-md-4">
                        <div class="card border-${rarityColor} h-100">
                            <div class="card-body text-center">
                                                                 <img src="${itemData.image}" alt="${lootBox.item_name}" 
                                     class="img-fluid mb-3" style="width: 100px; height: 100px; object-fit: contain;">
                                <h6 class="card-title">${lootBox.item_name}</h6>
                                <span class="badge bg-${rarityColor} mb-3">${lootBox.rarity.toUpperCase()}</span>
                                <br>
                                <button class="btn btn-${rarityColor} btn-sm" onclick="openLootBox(${lootBox.purchase_id}, '${lootBox.item_name}')">
                                    <i class="bi bi-unlock me-1"></i>Open Now!
                                </button>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            html += '</div>';
            container.innerHTML = html;
        } else {
            container.innerHTML = `
                <div class="text-center py-4">
                    <i class="bi bi-gift display-4 text-muted mb-3"></i>
                    <h5 class="text-muted">No Loot Boxes</h5>
                    <p class="text-muted">Purchase loot boxes from the store to start collecting amazing rewards!</p>
                </div>
            `;
        }
    })
    .catch(error => {
        console.error('Error loading loot boxes:', error);
        document.getElementById('loot-boxes-container').innerHTML = 
            '<div class="text-center py-3 text-danger">Failed to load loot boxes</div>';
    });
}

function openLootBox(purchaseId, lootBoxName) {
    currentLootBoxId = purchaseId;
    document.getElementById('loot-box-name').textContent = lootBoxName;
    
    // Reset modal stages
    document.getElementById('opening-confirm').classList.remove('d-none');
    document.getElementById('opening-animation').classList.add('d-none');
    document.getElementById('opening-rewards').classList.add('d-none');
    
    lootBoxModal.show();
}

function startOpening() {
    // Show animation stage
    document.getElementById('opening-confirm').classList.add('d-none');
    document.getElementById('opening-animation').classList.remove('d-none');
    
    // Animate progress bar
    const progressBar = document.getElementById('opening-progress');
    let progress = 0;
    const interval = setInterval(() => {
        progress += 10;
        progressBar.style.width = progress + '%';
        
        if (progress >= 100) {
            clearInterval(interval);
            // Make the actual opening request
            performLootBoxOpening();
        }
    }, 200);
}

function performLootBoxOpening() {
    fetch('', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=open_loot_box&purchase_id=' + currentLootBoxId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayRewards(data.rewards, data.rarity);
            // Refresh loot boxes list
            setTimeout(() => loadLootBoxes(), 1000);
        } else {
            alert('Failed to open loot box: ' + data.message);
            lootBoxModal.hide();
        }
    })
    .catch(error => {
        console.error('Error opening loot box:', error);
        alert('Failed to open loot box. Please try again.');
        lootBoxModal.hide();
    });
}

function displayRewards(rewards, rarity) {
    // Hide animation, show rewards
    document.getElementById('opening-animation').classList.add('d-none');
    document.getElementById('opening-rewards').classList.remove('d-none');
    
    const container = document.getElementById('rewards-container');
    let html = '';
    
    rewards.forEach(reward => {
        const rarityClass = reward.rarity === 'legendary' ? 'reward-legendary' : 
                           (reward.rarity === 'rare' ? 'reward-rare' : 'reward-common');
        
        html += `
            <div class="col-md-6">
                <div class="reward-item ${rarityClass}">
                    <i class="bi bi-${reward.icon} display-6 mb-2 text-${reward.rarity === 'legendary' ? 'warning' : (reward.rarity === 'rare' ? 'success' : 'info')}"></i>
                    <h6>${reward.display}</h6>
                    <span class="badge bg-${reward.rarity === 'legendary' ? 'warning' : (reward.rarity === 'rare' ? 'success' : 'secondary')} text-dark">
                        ${reward.rarity.toUpperCase()}
                    </span>
                </div>
            </div>
        `;
    });
    
    container.innerHTML = html;
    
    // Add some celebration effects
    setTimeout(() => {
        container.querySelectorAll('.reward-item').forEach((item, index) => {
            setTimeout(() => {
                item.style.animation = 'fadeInUp 0.6s ease forwards';
            }, index * 200);
        });
    }, 100);
}
</script>

<?php
// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'purchase' && isset($_POST['item_id'])) {
        $result = StoreManager::purchaseQRStoreItem($_SESSION['user_id'], (int)$_POST['item_id']);
        echo json_encode($result);
        exit;
    }
    
    if ($_POST['action'] === 'get_purchase_history') {
        $purchases = StoreManager::getUserPurchaseHistory($_SESSION['user_id'], 'qr', 10);
        echo json_encode(['purchases' => $purchases]);
        exit;
    }
    
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}

require_once __DIR__ . '/../core/includes/footer.php';
?> 