<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/functions.php';
require_once __DIR__ . '/../core/qr_coin_manager.php';

// Ensure user is logged in
if (!is_logged_in()) {
    redirect(APP_URL . '/login.php');
}

// Get user data
$stmt = $pdo->prepare("SELECT username, role, created_at FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user_data = $stmt->fetch();

// Get user's current points and stats first (using same system as dashboard)
$user_id = $_SESSION['user_id']; // Use logged-in user ID instead of just IP

// Get QR Coin balance (NEW SYSTEM - consistent with dashboard)
$user_points = QRCoinManager::getBalance($user_id);

// Get comprehensive stats for other metrics
$stats = getUserStats($user_id, get_client_ip());
$voting_stats = $stats['voting_stats'];
$spin_stats = $stats['spin_stats'];

// Get QR spending stats for Posty unlock (fixed calculation)
$qr_stats = ['total_spent' => 0];
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(ABS(amount)), 0) as total_spent
    FROM qr_coin_transactions 
    WHERE user_id = ? AND transaction_type = 'spending'
");
$stmt->execute([$user_id]);
$qr_stats['total_spent'] = $stmt->fetchColumn();

// Get user's unlocked avatars from database
$unlocked_avatars = [];
$stmt = $pdo->prepare("SELECT avatar_id FROM user_avatars WHERE user_id = ?");
$stmt->execute([$user_id]);
while ($row = $stmt->fetch()) {
    $unlocked_avatars[] = $row['avatar_id'];
}

// Auto-unlock milestone avatars that meet requirements
$milestone_avatars_to_check = [
    8 => ['type' => 'votes', 'requirement' => 200],   // QR ED
    10 => ['type' => 'votes', 'requirement' => 500],  // QR NED  
    15 => ['type' => 'triple', 'requirements' => ['votes' => 420, 'spins' => 420, 'points' => 420]], // QR Easybake
    16 => ['type' => 'spending', 'requirement' => 50000] // Posty
];

foreach ($milestone_avatars_to_check as $avatar_id => $requirements) {
    if (!in_array($avatar_id, $unlocked_avatars)) {
        $should_unlock = false;
        
        if ($requirements['type'] === 'votes') {
            $should_unlock = ($voting_stats['total_votes'] >= $requirements['requirement']);
        } elseif ($requirements['type'] === 'triple') {
            $should_unlock = (
                $voting_stats['total_votes'] >= $requirements['requirements']['votes'] &&
                $spin_stats['total_spins'] >= $requirements['requirements']['spins'] &&
                $user_points >= $requirements['requirements']['points']
            );
        } elseif ($requirements['type'] === 'spending') {
            $should_unlock = ($qr_stats['total_spent'] >= $requirements['requirement']);
        }
        
        if ($should_unlock) {
            try {
                $stmt_auto_unlock = $pdo->prepare("INSERT INTO user_avatars (user_id, avatar_id, unlocked_at) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE unlocked_at = NOW()");
                $stmt_auto_unlock->execute([$user_id, $avatar_id]);
                $unlocked_avatars[] = $avatar_id;
                error_log("Auto-unlocked milestone avatar {$avatar_id} for user {$user_id}");
            } catch (Exception $e) {
                error_log("Failed to auto-unlock avatar {$avatar_id}: " . $e->getMessage());
            }
        }
    }
}

// Define available avatars with stats available
$available_avatars = [
    // Common Avatars - Free for everyone
    [
        'id' => 1,
        'name' => 'QR Ted',
        'filename' => 'qrted.png',
        'description' => 'Classic Ted QR code avatar - Free starter avatar',
        'cost' => 0,
        'rarity' => 'common',
        'unlocked' => true,
        'special_perk' => null
    ],
    [
        'id' => 12,
        'name' => 'QR Steve',
        'filename' => 'qrsteve.png',
        'description' => 'Classic Steve QR code avatar - Available for everyone',
        'cost' => 0,
        'rarity' => 'common',
        'unlocked' => true,
        'special_perk' => null
    ],
    [
        'id' => 13,
        'name' => 'QR Bob',
        'filename' => 'qrbob.png',
        'description' => 'Classic Bob QR code avatar - Available for everyone',
        'cost' => 0,
        'rarity' => 'common',
        'unlocked' => true,
        'special_perk' => null
    ],
    
    // Rare Avatars
    [
        'id' => 2,
        'name' => 'QR James',
        'filename' => 'qrjames.png',
        'description' => 'Cool James QR code avatar with spin protection',
        'cost' => 500,
        'rarity' => 'rare',
        'unlocked' => in_array(2, $unlocked_avatars),
        'special_perk' => 'Vote protection (immune to "Lose All Votes")'
    ],
    
    // Epic Avatars
    [
        'id' => 3,
        'name' => 'QR Mike',
        'filename' => 'qrmike.png',
        'description' => 'Awesome Mike QR code avatar with vote bonus',
        'cost' => 600,
        'rarity' => 'rare',
        'unlocked' => in_array(3, $unlocked_avatars),
        'special_perk' => '+5 QR coins per vote (base vote reward: 5→10)'
    ],
    [
        'id' => 4,
        'name' => 'QR Kevin',
        'filename' => 'qrkevin.png',
        'description' => 'Elite Kevin QR code avatar with spin power',
        'cost' => 1200,
        'rarity' => 'epic',
        'unlocked' => in_array(4, $unlocked_avatars),
        'special_perk' => '+10 QR coins per spin (base spin reward: 15→25)'
    ],
    [
        'id' => 8,
        'name' => 'QR ED',
        'filename' => 'qred.png',
        'description' => 'Elite QR ED avatar - Unlocked for dedicated voters!',
        'cost' => 0, // Unlocked through votes
        'rarity' => 'epic',
        'unlocked' => $voting_stats['total_votes'] >= 200,
        'special_perk' => '+15 QR coins per vote (base vote reward: 5→20)',
        'unlock_method' => 'vote_milestone',
        'unlock_requirement' => 200
    ],
    
    // Legendary Avatars
    [
        'id' => 5,
        'name' => 'QR Tim',
        'filename' => 'qrtim.png',
        'description' => 'Legendary Tim QR code avatar with daily bonus boost',
        'cost' => 2500,
        'rarity' => 'epic',
        'unlocked' => in_array(5, $unlocked_avatars),
        'special_perk' => '+20% daily bonus multiplier (Vote bonus: 25→30, Spin bonus: 50→60)'
    ],
    [
        'id' => 6,
        'name' => 'QR Bush',
        'filename' => 'qrbush.png',
        'description' => 'Mythical Bush QR code avatar with ultimate luck',
        'cost' => 3000,
        'rarity' => 'legendary',
        'unlocked' => in_array(6, $unlocked_avatars),
        'special_perk' => '+10% better spin prizes (50→55, 200→220, 500→550)'
    ],
    [
        'id' => 7,
        'name' => 'QR Terry',
        'filename' => 'qrterry.png',
        'description' => 'Godlike Terry QR code avatar - The ultimate avatar',
        'cost' => 5000,
        'rarity' => 'legendary',
        'unlocked' => in_array(7, $unlocked_avatars),
        'special_perk' => 'Combined: +5 per vote, +10 per spin, vote protection'
    ],
    [
        'id' => 10,
        'name' => 'QR NED',
        'filename' => 'qrned.png', // QR NED as Pixel Master
        'description' => 'Legendary Pixel Master avatar - For the ultimate voters!',
        'cost' => 0, // Unlocked through votes
        'rarity' => 'legendary',
        'unlocked' => $voting_stats['total_votes'] >= 500,
        'special_perk' => '+25 QR coins per vote (base vote reward: 5→30)',
        'unlock_method' => 'vote_milestone',
        'unlock_requirement' => 500
    ],
    
    // Ultra Rare Avatars
    [
        'id' => 9,
        'name' => 'Lord Pixel',
        'filename' => 'qrLordPixel.png',
        'description' => 'Ultra-rare Lord Pixel avatar - Only obtainable through the spin wheel!',
        'cost' => 0, // Cannot be purchased
        'rarity' => 'ultra_rare',
        'unlocked' => in_array(9, $unlocked_avatars),
        'special_perk' => 'Immune to spin penalties + extra spin chance',
        'unlock_method' => 'spin_wheel'
    ],
    
    // Mythical Avatars
    [
        'id' => 11,
        'name' => 'QR Clayton',
        'filename' => 'qrClayton.png',
        'description' => 'Mythical Clayton QR code avatar - The ultimate weekend warrior!',
        'cost' => 10000,
        'rarity' => 'mythical',
        'unlocked' => in_array(11, $unlocked_avatars),
        'special_perk' => 'Weekend warrior: 5 spins on weekends + double weekend earnings'
    ],
    
    // Special Avatars - For Sale
    [
        'id' => 14,
        'name' => 'QR Ryan',
        'filename' => 'qrRyan.png',
        'description' => 'Premium QR Ryan avatar - Double activity boost!',
        'cost' => 0,
        'rarity' => 'special',
        'unlocked' => in_array(14, $unlocked_avatars),
        'special_perk' => 'Double points on all activity and spins for 2 days',
        'for_sale' => true,
        'sale_status' => 'For Sale'
    ],
    
    // 420 Milestone Avatar
    [
        'id' => 15,
        'name' => 'QR Easybake',
        'filename' => 'qrEasybake.png',
        'description' => 'Epic 420 milestone avatar',
        'cost' => 0, // Unlocked through milestones
        'rarity' => 'ultra_rare',
        'unlocked' => ($voting_stats['total_votes'] >= 420 && $spin_stats['total_spins'] >= 420 && $user_points >= 420),
        'special_perk' => '+15 per vote, +25 per spin, monthly super spin (guarantees 420 bonus)',
        'unlock_method' => 'triple_milestone',
        'unlock_requirements' => [
            'votes' => 420,
            'spins' => 420, 
            'points' => 420
        ]
    ],
    
    // Spending Milestone Avatar - Posty
    [
        'id' => 16,
        'name' => 'Posty',
        'filename' => 'posty.png',
        'description' => 'Legendary Posty avatar - Unlocked after spending 50,000 QR coins!',
        'cost' => 0, // Unlocked through spending milestone
        'rarity' => 'legendary',
        'unlocked' => ($qr_stats['total_spent'] >= 50000),
        'special_perk' => '5% cashback on all spin wheel and casino losses',
        'unlock_method' => 'milestone',
        'unlock_requirement' => 50000,
        'perk_data' => ['loss_cashback_percentage' => 5]
    ]
];

// Database queries already performed above

// Handle avatar actions
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $avatar_id = (int)($_POST['avatar_id'] ?? 0);
    
    // Find the requested avatar
    $requested_avatar = null;
    foreach ($available_avatars as $avatar) {
        if ($avatar['id'] === $avatar_id) {
            $requested_avatar = $avatar;
            break;
        }
    }
    
    if ($action === 'equip' && $requested_avatar && $requested_avatar['unlocked']) {
        // Update both session and database
        $_SESSION['equipped_avatar'] = $avatar_id;
        
        // Update database if user is logged in
        if (isset($_SESSION['user_id'])) {
            $stmt = $pdo->prepare("UPDATE users SET equipped_avatar = ? WHERE id = ?");
            $stmt->execute([$avatar_id, $_SESSION['user_id']]);
        }
        
        $message = 'Avatar "' . $requested_avatar['name'] . '" equipped successfully!';
        $message_type = 'success';
    } elseif ($action === 'unlock' && $requested_avatar && !$requested_avatar['unlocked']) {
        if ($user_points >= $requested_avatar['cost']) {
            try {
                // 1. Deduct QR coins using QRCoinManager
                QRCoinManager::addTransaction(
                    $_SESSION['user_id'],
                    'spending',
                    'avatar',
                    $requested_avatar['cost'],
                    "Avatar purchase: {$requested_avatar['name']}",
                    [
                        'avatar_id' => $avatar_id,
                        'avatar_name' => $requested_avatar['name'],
                        'rarity' => $requested_avatar['rarity']
                    ],
                    $avatar_id,
                    'avatar'
                );
                
                // 2. Save avatar unlock to database
                $stmt = $pdo->prepare("INSERT INTO user_avatars (user_id, avatar_id, unlocked_at) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE unlocked_at = NOW()");
                $stmt->execute([$_SESSION['user_id'], $avatar_id]);
                
                // 3. Update user's QR coin balance for display
                $user_points = QRCoinManager::getBalance($_SESSION['user_id']);
                
                // 4. Automatically equip the newly purchased avatar (only for purchased avatars, not milestones)
                if ($requested_avatar['cost'] > 0) {
                    $_SESSION['equipped_avatar'] = $avatar_id;
                    $stmt = $pdo->prepare("UPDATE users SET equipped_avatar = ? WHERE id = ?");
                    $stmt->execute([$avatar_id, $_SESSION['user_id']]);
                }
                
                $message = 'Avatar "' . $requested_avatar['name'] . '" unlocked and equipped successfully! ' . number_format($requested_avatar['cost']) . ' QR coins deducted.';
                $message_type = 'success';
                
                // Mark avatar as unlocked for immediate display
                foreach ($available_avatars as &$avatar) {
                    if ($avatar['id'] === $avatar_id) {
                        $avatar['unlocked'] = true;
                        break;
                    }
                }
                
            } catch (Exception $e) {
                $message = 'Error unlocking avatar: ' . $e->getMessage();
                $message_type = 'danger';
            }
        } else {
            $message = 'Not enough QR coins to unlock "' . $requested_avatar['name'] . '"! You need ' . number_format($requested_avatar['cost'] - $user_points) . ' more QR coins.';
            $message_type = 'warning';
        }
    } else {
        $message = 'Invalid action or avatar not found.';
        $message_type = 'danger';
    }
}

// Get currently equipped avatar
$equipped_avatar_id = getUserEquippedAvatar();

include '../core/includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <h1 class="h3 mb-0">
                <i class="bi bi-person-badge text-primary me-2"></i>
                QR Code Avatars
            </h1>
            <p class="text-muted">Customize your profile with unique QR code avatars</p>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <i class="bi bi-<?php echo $message_type === 'success' ? 'check-circle' : 'info-circle'; ?> me-2"></i>
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- User Stats -->
    <div class="row mb-4">
        <div class="col-sm-6 col-lg-3">
            <div class="card bg-primary text-white">
                <div class="card-body text-center">
                    <img src="../img/qrCoin.png" alt="QR Coin" class="mb-2" style="width: 4rem; height: 4rem;">
                    <h2 class="mb-0"><?php echo number_format($user_points); ?></h2>
                    <p class="mb-0">Available QR Coins</p>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card bg-success text-white">
                <div class="card-body text-center">
                    <i class="bi bi-collection display-4 mb-2"></i>
                    <h2 class="mb-0"><?php echo count(array_filter($available_avatars, fn($a) => $a['unlocked'])); ?></h2>
                    <p class="mb-0">Avatars Owned</p>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card bg-info text-white">
                <div class="card-body text-center">
                    <i class="bi bi-trophy display-4 mb-2"></i>
                    <h2 class="mb-0"><?php echo count($available_avatars); ?></h2>
                    <p class="mb-0">Total Avatars</p>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card bg-warning text-white">
                <div class="card-body text-center">
                    <i class="bi bi-star-fill display-4 mb-2"></i>
                    <h2 class="mb-0"><?php echo count(array_filter($available_avatars, fn($a) => $a['rarity'] === 'mythical' || $a['rarity'] === 'ultra_rare')); ?></h2>
                    <p class="mb-0">Ultra Rare+</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Currently Equipped -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-star-fill text-warning me-2"></i>
                        Currently Equipped
                    </h5>
                </div>
                <div class="card-body">
                                    <?php 
                $filtered_avatars = array_filter($available_avatars, fn($a) => $a['id'] === $equipped_avatar_id);
                $equipped = !empty($filtered_avatars) ? array_values($filtered_avatars)[0] : $available_avatars[0];
                ?>
                    <div class="row align-items-center">
                        <div class="col-md-2 text-center">
                            <div class="avatar-preview mb-3">
                                <img src="../assets/img/avatars/<?php echo $equipped['filename']; ?>" 
                                     alt="<?php echo htmlspecialchars($equipped['name']); ?>"
                                     class="img-fluid rounded border"
                                     style="max-width: 80px; height: 80px; object-fit: cover;"
                                     onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iODAiIGhlaWdodD0iODAiIHZpZXdCb3g9IjAgMCA4MCA4MCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHJlY3Qgd2lkdGg9IjgwIiBoZWlnaHQ9IjgwIiBmaWxsPSIjZTllY2VmIi8+Cjx0ZXh0IHg9IjUwJSIgeT0iNTAlIiBkb21pbmFudC1iYXNlbGluZT0ibWlkZGxlIiB0ZXh0LWFuY2hvcj0ibWlkZGxlIiBmaWxsPSIjNmM3NTdkIj5RUjwvdGV4dD4KPHN2Zz4=';">
                            </div>
                        </div>
                        <div class="col-md-10">
                            <h6 class="mb-1"><?php echo htmlspecialchars($equipped['name']); ?></h6>
                            <p class="text-muted mb-1"><?php echo htmlspecialchars($equipped['description']); ?></p>
                            <?php if ($equipped['special_perk']): ?>
                                <span class="badge bg-info">
                                    <i class="bi bi-lightning-charge me-1"></i>
                                    <?php echo htmlspecialchars($equipped['special_perk']); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Available Avatars -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-grid-3x3 me-2"></i>
                        Avatar Collection
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($available_avatars as $avatar): ?>
                            <div class="col-sm-6 col-md-4 col-lg-3 col-xl-2 mb-4">
                                <div class="card h-100 <?php echo $avatar['unlocked'] ? '' : 'bg-light'; ?>">
                                    <div class="card-body text-center">
                                        <div class="avatar-preview mb-3">
                                            <img src="../assets/img/avatars/<?php echo $avatar['filename']; ?>" 
                                                 alt="<?php echo htmlspecialchars($avatar['name']); ?>"
                                                 class="img-fluid rounded border <?php echo $avatar['unlocked'] ? '' : 'opacity-50'; ?>"
                                                 style="max-width: 100px; height: 100px; object-fit: cover;"
                                                 onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgdmlld0JveD0iMCAwIDEwMCAxMDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIxMDAiIGhlaWdodD0iMTAwIiBmaWxsPSIjZTllY2VmIi8+Cjx0ZXh0IHg9IjUwJSIgeT0iNTAlIiBkb21pbmFudC1iYXNlbGluZT0ibWlkZGxlIiB0ZXh0LWFuY2hvcj0ibWlkZGxlIiBmaWxsPSIjNmM3NTdkIj5RUjwvdGV4dD4KPHN2Zz4=';">
                                            <?php if (!$avatar['unlocked']): ?>
                                                <div class="position-absolute top-50 start-50 translate-middle">
                                                    <i class="bi bi-lock-fill text-secondary" style="font-size: 2rem;"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <h6 class="card-title"><?php echo htmlspecialchars($avatar['name']); ?></h6>
                                        <p class="card-text small text-muted"><?php echo htmlspecialchars($avatar['description']); ?></p>
                                        
                                        <!-- Rarity Badge -->
                                                                <span class="badge mb-2 <?php 
                            echo match($avatar['rarity']) {
                                'common' => 'bg-secondary',
                                'rare' => 'bg-primary',
                                'epic' => 'bg-warning text-dark',
                                'legendary' => 'bg-danger',
                                'ultra_rare' => 'bg-gradient text-white',
                                'mythical' => 'bg-gradient text-white',
                                'special' => 'bg-gradient text-white',
                                default => 'bg-secondary'
                            };
                        ?>" <?php if ($avatar['rarity'] === 'ultra_rare'): ?>style="background: linear-gradient(45deg, #8A2BE2, #FF1493, #FFD700) !important;"<?php elseif ($avatar['rarity'] === 'mythical'): ?>style="background: linear-gradient(45deg, #4B0082, #8B0000, #FF4500, #FFD700) !important;"<?php elseif ($avatar['rarity'] === 'special'): ?>style="background: linear-gradient(45deg, #FF6B35, #F7931E, #FFD700, #87CEEB) !important;"<?php endif; ?>>
                            <?php echo $avatar['rarity'] === 'ultra_rare' ? 'ULTRA RARE' : ($avatar['rarity'] === 'mythical' ? 'MYTHICAL' : ($avatar['rarity'] === 'special' ? 'FOR SALE' : ucfirst($avatar['rarity']))); ?>
                        </span>
                                        
                                        <?php if ($avatar['special_perk']): ?>
                                            <div class="mb-2">
                                                <small class="text-info">
                                                    <i class="bi bi-lightning-charge me-1"></i>
                                                    <?php echo htmlspecialchars($avatar['special_perk']); ?>
                                                </small>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <!-- Action Buttons -->
                                        <?php if ($avatar['unlocked']): ?>
                                            <?php if ($avatar['id'] === $equipped_avatar_id): ?>
                                                <span class="badge bg-success">
                                                    <i class="bi bi-check-circle me-1"></i>Equipped
                                                </span>
                                            <?php else: ?>
                                                <form method="post" class="d-inline">
                                                    <input type="hidden" name="action" value="equip">
                                                    <input type="hidden" name="avatar_id" value="<?php echo $avatar['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-primary">
                                                        <i class="bi bi-check2-square me-1"></i>Equip
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <?php if (isset($avatar['unlock_method']) && $avatar['unlock_method'] === 'spin_wheel'): ?>
                                                <div class="mb-2">
                                                    <strong class="text-info">
                                                        <i class="bi bi-trophy-fill me-1"></i>
                                                        Spin Wheel Only!
                                                    </strong>
                                                </div>
                                                <div class="text-center">
                                                    <p class="small text-muted mb-2">Only obtainable by winning on the spin wheel!</p>
                                                    <a href="spin.php" class="btn btn-sm btn-info">
                                                        <i class="bi bi-arrow-right-circle me-1"></i>Go to Spin Wheel
                                                    </a>
                                                </div>
                                            <?php elseif (isset($avatar['unlock_method']) && $avatar['unlock_method'] === 'vote_milestone'): ?>
                                                <div class="mb-2">
                                                    <strong class="text-success">
                                                        <i class="bi bi-check-square me-1"></i>
                                                        <?php echo $avatar['unlock_requirement']; ?> Votes Required
                                                    </strong>
                                                </div>
                                                <div class="text-center">
                                                    <p class="small text-muted mb-2">
                                                        Progress: <?php echo $voting_stats['total_votes']; ?> / <?php echo $avatar['unlock_requirement']; ?> votes
                                                    </p>
                                                    <div class="progress mb-2" style="height: 8px;">
                                                        <div class="progress-bar bg-success" style="width: <?php echo min(100, ($voting_stats['total_votes'] / $avatar['unlock_requirement']) * 100); ?>%"></div>
                                                    </div>
                                                    <?php if ($voting_stats['total_votes'] >= $avatar['unlock_requirement']): ?>
                                                        <span class="badge bg-success">
                                                            <i class="bi bi-check-circle me-1"></i>Unlocked!
                                                        </span>
                                                    <?php else: ?>
                                                        <small class="text-muted">
                                                            <?php echo $avatar['unlock_requirement'] - $voting_stats['total_votes']; ?> more votes needed
                                                        </small>
                                                        <br>
                                                        <a href="vote.php" class="btn btn-sm btn-success mt-1">
                                                            <i class="bi bi-arrow-right-circle me-1"></i>Start Voting
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                                                        <?php elseif (isset($avatar['unlock_method']) && $avatar['unlock_method'] === 'milestone'): ?>
                                                <div class="mb-2">
                                                    <strong class="text-warning">
                                                        <i class="bi bi-coin me-1"></i>
                                                        <?php echo number_format($avatar['unlock_requirement']); ?> QR Coins Spending Milestone
                                                    </strong>
                                                </div>
                                                <div class="text-center">
                                                    <p class="small text-muted mb-2">
                                                        Progress: <?php echo number_format($qr_stats['total_spent']); ?> / <?php echo number_format($avatar['unlock_requirement']); ?> coins spent
                                                    </p>
                                                    <div class="progress mb-2" style="height: 8px;">
                                                        <div class="progress-bar bg-warning" style="width: <?php echo min(100, ($qr_stats['total_spent'] / $avatar['unlock_requirement']) * 100); ?>%"></div>
                                                    </div>
                                                    <?php if ($qr_stats['total_spent'] >= $avatar['unlock_requirement']): ?>
                                                        <span class="badge bg-success">
                                                            <i class="bi bi-check-circle me-1"></i>Unlocked!
                                                        </span>
                                                    <?php else: ?>
                                                        <small class="text-muted">
                                                            <?php echo number_format($avatar['unlock_requirement'] - $qr_stats['total_spent']); ?> more coins needed
                                                        </small>
                                                        <br>
                                                        <a href="../user/dashboard.php" class="btn btn-sm btn-warning mt-1">
                                                            <i class="bi bi-arrow-right-circle me-1"></i>Earn More Coins
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            <?php elseif (isset($avatar['unlock_method']) && $avatar['unlock_method'] === 'triple_milestone'): ?>
                                <div class="mb-2">
                                    <strong class="text-warning">
                                        <i class="bi bi-trophy-fill me-1"></i>
                                        420 Milestone Challenge
                                    </strong>
                                </div>
                                <div class="text-center">
                                    <div class="row text-center mb-2">
                                        <div class="col-4">
                                            <small class="text-muted">Votes</small><br>
                                            <span class="badge bg-<?php echo $voting_stats['total_votes'] >= 420 ? 'success' : 'secondary'; ?>">
                                                <?php echo $voting_stats['total_votes']; ?>/420
                                            </span>
                                        </div>
                                        <div class="col-4">
                                            <small class="text-muted">Spins</small><br>
                                            <span class="badge bg-<?php echo $spin_stats['total_spins'] >= 420 ? 'success' : 'secondary'; ?>">
                                                <?php echo $spin_stats['total_spins']; ?>/420
                                            </span>
                                        </div>
                                        <div class="col-4">
                                            <small class="text-muted">QR Coins</small><br>
                                            <span class="badge bg-<?php echo $user_points >= 420 ? 'success' : 'secondary'; ?>">
                                                <?php echo $user_points >= 420 ? '✓' : $user_points . '/420'; ?>
                                            </span>
                                        </div>
                                    </div>
                                    <?php
                                    $completed_milestones = 0;
                                    if ($voting_stats['total_votes'] >= 420) $completed_milestones++;
                                    if ($spin_stats['total_spins'] >= 420) $completed_milestones++;
                                    if ($user_points >= 420) $completed_milestones++;
                                    ?>
                                    <div class="progress mb-2" style="height: 10px;">
                                        <div class="progress-bar bg-warning" style="width: <?php echo ($completed_milestones / 3) * 100; ?>%"></div>
                                    </div>
                                    <?php if ($avatar['unlocked']): ?>
                                        <span class="badge bg-success">
                                            <i class="bi bi-check-circle me-1"></i>420 Achieved!
                                        </span>
                                    <?php else: ?>
                                        <small class="text-muted">
                                            <?php echo 3 - $completed_milestones; ?> milestone<?php echo (3 - $completed_milestones) !== 1 ? 's' : ''; ?> remaining
                                        </small>
                                        <br>
                                        <a href="vote.php" class="btn btn-sm btn-warning mt-1">
                                            <i class="bi bi-arrow-right-circle me-1"></i>Keep Grinding
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php elseif (isset($avatar['for_sale']) && $avatar['for_sale']): ?>
                                <div class="mb-2">
                                    <strong class="text-info">
                                        <i class="bi bi-tag-fill me-1"></i>
                                        <?php echo $avatar['sale_status']; ?>
                                    </strong>
                                </div>
                                <div class="text-center">
                                    <p class="small text-muted mb-2">Premium avatar available for purchase!</p>
                                    <button class="btn btn-sm btn-info" disabled>
                                        <i class="bi bi-cart-plus me-1"></i>Coming Soon
                                    </button>
                                </div>
                                            <?php else: ?>
                                                <div class="mb-2">
                                                    <strong class="text-warning">
                                                        <img src="../img/qrCoin.png" alt="QR Coin" class="me-1" style="width: 1em; height: 1em;">
                                                        <?php echo number_format($avatar['cost']); ?> QR Coins
                                                    </strong>
                                                </div>
                                                <form method="post" class="d-inline">
                                                    <input type="hidden" name="action" value="unlock">
                                                    <input type="hidden" name="avatar_id" value="<?php echo $avatar['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-warning" 
                                                            <?php echo $user_points < $avatar['cost'] ? 'disabled' : ''; ?>>
                                                        <i class="bi bi-unlock me-1"></i>
                                                        <?php echo $user_points >= $avatar['cost'] ? 'Unlock' : 'Not Enough Points'; ?>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Premium Avatar Features -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card border-warning">
                <div class="card-header bg-warning text-dark">
                    <h6 class="mb-0"><i class="bi bi-star-fill me-2"></i>Premium Avatar Perks - Game-Changing Abilities!</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="text-primary"><i class="bi bi-shield-check me-1"></i>Protection Perks</h6>
                            <ul class="small">
                                <li><strong>QR James (4,500 pts):</strong> Safe from "Lose All Votes" penalty - never lose your voting progress!</li>
                            </ul>
                            
                            <h6 class="text-success"><i class="bi bi-plus-circle me-1"></i>Bonus Perks</h6>
                            <ul class="small">
                                <li><strong>QR Mike (8,000 pts):</strong> +1 Spin per day - spin twice daily instead of once!</li>
                                <li><strong>QR Kevin (12,500 pts):</strong> Double voting points - earn 20 points per vote instead of 10!</li>
                                <li><strong>QR Tim (18,000 pts):</strong> +50% streak bonus - massive boost to consecutive day bonuses!</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-warning"><i class="bi bi-trophy me-1"></i>Elite Perks</h6>
                            <ul class="small">
                                <li><strong>QR Bush (25,000 pts):</strong> +25% better prizes - increased chance of rare spin wheel rewards!</li>
                                <li><strong>QR Terry (37,000 pts):</strong> ALL BONUSES COMBINED + VIP status - the ultimate avatar!</li>
                            </ul>
                            
                            <h6 class="text-success"><i class="bi bi-check-square me-1"></i>Vote Milestone Avatars</h6>
                            <ul class="small">
                                <li><strong>QR ED (200 votes):</strong> Triple voting points (30 per vote) + Vote streak protection!</li>
                                <li><strong>QR Pixel Master (500 votes):</strong> Mega vote bonus (50 points per vote) + Daily vote multiplier!</li>
                            </ul>
                            
                            <h6 class="text-danger"><i class="bi bi-star-fill me-1"></i>Ultra Rare - Spin Wheel Exclusive</h6>
                            <ul class="small">
                                <li><strong>Lord Pixel (Spin Wheel Only!):</strong> Pixel master powers + Immune to ALL spin wheel penalties - the rarest avatar!</li>
                            </ul>
                            
                            <h6 class="text-dark"><i class="bi bi-crown-fill me-1"></i>Mythical Tier - Ultimate Power</h6>
                            <ul class="small">
                                <li><strong>QR Clayton (150,000 pts):</strong> 10 spins in a day once a week + Triple points on weekends - the weekend warrior!</li>
                            </ul>
                            
                            <h6 class="text-info"><i class="bi bi-tag-fill me-1"></i>Special Edition - For Sale</h6>
                            <ul class="small">
                                <li><strong>QR Ryan (For Sale):</strong> Double points on all activity and spins for 2 days - temporary power boost!</li>
                            </ul>
                            
                            <h6 class="text-warning"><i class="bi bi-trophy-fill me-1"></i>420 Milestone Challenge</h6>
                            <ul class="small">
                                <li><strong>QR Easybake (420 Triple):</strong> 4x Points + 2x Spin Luck + Monthly super spin with 4×2×0 multiplier - the ultimate grind reward!</li>
                            </ul>
                            
                            <div class="alert alert-warning small mt-3 mb-0">
                                <i class="bi bi-exclamation-triangle me-1"></i>
                                <strong>Pro Tip:</strong> Earn points through daily activities:
                                <br>• 10 points per vote (+50 bonus per voting day)
                                <br>• 25 points per spin (+100 bonus per spin day)
                                <br>• Level up every 1000+ points (scales with level)
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Instructions -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="alert alert-info">
                <h6><i class="bi bi-info-circle me-2"></i>How to Earn Avatars</h6>
                <ul class="mb-0">
                    <li><strong>Vote & Spin Daily:</strong> Earn 10+ points per vote and 25+ points per spin</li>
                    <li><strong>Build Streaks:</strong> Get massive bonus points for consecutive voting and spinning days</li>
                    <li><strong>Save Your Points:</strong> Higher-tier avatars have incredible perks worth the investment</li>
                    <li><strong>Strategic Unlocking:</strong> Consider which perks best match your playstyle</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<style>
.avatar-preview {
    position: relative;
    display: inline-block;
}
</style>

<?php include '../core/includes/footer.php'; ?> 