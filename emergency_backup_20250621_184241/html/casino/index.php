<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/functions.php';

// Smart routing based on user status
if (!is_logged_in()) {
    // Not logged in - redirect to registration with casino incentive
    $_SESSION['casino_signup_incentive'] = true;
    $_SESSION['redirect_after_login'] = '/casino/index.php';
    header('Location: ' . APP_URL . '/register.php?ref=casino');
    exit;
}

// Get unified casino settings and participating businesses
$stmt = $pdo->query("SELECT * FROM casino_unified_settings WHERE id = 1");
$casino_settings = $stmt->fetch() ?: [
    'platform_name' => 'Revenue QR Casino',
    'base_daily_spins' => 10,
    'min_bet' => 1,
    'max_bet' => 50
];

// Get businesses participating in unified casino
$stmt = $pdo->prepare("
    SELECT b.id, b.name, b.logo_path, 
           bcp.revenue_share_percentage, bcp.featured_promotion, bcp.location_bonus_multiplier,
           COALESCE(bcr.total_plays_at_location, 0) as plays_today,
           COALESCE(bcr.revenue_share_earned, 0) as revenue_today
    FROM business_casino_participation bcp
    JOIN businesses b ON bcp.business_id = b.id  
    LEFT JOIN business_casino_revenue bcr ON b.id = bcr.business_id AND bcr.date_period = CURDATE()
    WHERE bcp.casino_enabled = 1
    ORDER BY b.name
");
$stmt->execute();
$casino_businesses = $stmt->fetchAll();

// Get user's QR coin balance
require_once __DIR__ . '/../core/qr_coin_manager.php';
require_once __DIR__ . '/../core/casino_spin_manager.php';
$user_balance = QRCoinManager::getBalance($_SESSION['user_id']);

// Get user's total casino spins today (unified across all locations)
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total_plays_today
    FROM casino_plays 
    WHERE user_id = ? AND DATE(played_at) = CURDATE()
");
$stmt->execute([$_SESSION['user_id']]);
$total_plays_today = $stmt->fetchColumn() ?: 0;

// Get unified spin information (same for all locations)
$unified_spin_info = CasinoSpinManager::getAvailableSpins($_SESSION['user_id'], null); // null = unified system
$spins_remaining = max(0, $casino_settings['base_daily_spins'] + $unified_spin_info['bonus_spins'] - $total_plays_today);

include '../core/includes/header.php';
?>

<div class="container-fluid py-4">
    <!-- Casino Header -->
    <div class="row mb-4">
        <div class="col-12 text-center">
            <h1 class="display-4 mb-3">
                🎰 <span class="text-gradient-casino"><?php echo htmlspecialchars($casino_settings['platform_name']); ?></span>
            </h1>
            <p class="lead text-muted">Unified casino experience • Same rules everywhere • Support local businesses</p>
            
            <!-- User Balance & Spins Display -->
            <div class="d-flex justify-content-center gap-3 flex-wrap">
                <div class="d-inline-flex align-items-center bg-dark text-white px-4 py-2 rounded-pill">
                    <img src="<?php echo APP_URL; ?>/img/qrCoin.png" alt="QR Coin" style="width: 2rem; height: 2rem;" class="me-2">
                    <h3 class="mb-0"><?php echo number_format($user_balance); ?></h3>
                    <small class="ms-2 text-warning">QR Coins</small>
                </div>
                <div class="d-inline-flex align-items-center bg-primary text-white px-4 py-2 rounded-pill">
                    <i class="bi bi-dice-5 me-2" style="font-size: 1.5rem;"></i>
                    <h4 class="mb-0"><?php echo $spins_remaining; ?></h4>
                    <small class="ms-2">Spins Left Today</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Casino Spin Packs Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card bg-gradient text-white" style="background: linear-gradient(135deg, #dc3545 0%, #fd7e14 100%);">
                <div class="card-body text-center">
                    <h4 class="mb-3">
                        <i class="bi bi-gem me-2"></i>Want More Casino Spins?
                    </h4>
                    <p class="mb-3">Purchase spin packs from the QR Store to play more slot machines daily!</p>
                    <div class="row justify-content-center">
                        <div class="col-md-8">
                            <div class="row g-2">
                                <div class="col-6 col-md-3">
                                    <div class="bg-white bg-opacity-10 rounded p-2">
                                        <div class="text-warning"><i class="bi bi-plus-circle-fill"></i></div>
                                        <small>+2-20 Spins/Day</small>
                                    </div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="bg-white bg-opacity-10 rounded p-2">
                                        <div class="text-warning"><i class="bi bi-calendar-week"></i></div>
                                        <small>3-30 Days</small>
                                    </div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="bg-white bg-opacity-10 rounded p-2">
                                        <div class="text-warning"><i class="bi bi-building-fill"></i></div>
                                        <small>All Casinos</small>
                                    </div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="bg-white bg-opacity-10 rounded p-2">
                                        <div class="text-warning"><i class="bi bi-coin"></i></div>
                                        <small>300-5000 Coins</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="mt-3">
                        <a href="<?php echo APP_URL; ?>/user/qr-store.php?category=slot_pack" class="btn btn-light btn-lg">
                            <i class="bi bi-cart-plus me-2"></i>Browse Casino Spin Packs
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if (empty($casino_businesses)): ?>
        <!-- No Casino Businesses Available -->
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card bg-gradient-warning text-dark">
                    <div class="card-body text-center p-5">
                        <i class="bi bi-building-exclamation display-1 mb-3"></i>
                        <h3>No Casinos Available Yet</h3>
                        <p class="lead mb-4">
                            None of the businesses in your area have enabled their casino yet. 
                            But don't worry - you can encourage them to join the fun!
                        </p>
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="card bg-white">
                                    <div class="card-body">
                                        <h5><i class="bi bi-chat-dots text-primary"></i> Tell Businesses</h5>
                                        <p class="small">Ask your favorite local businesses to enable their QR casino!</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card bg-white">
                                    <div class="card-body">
                                        <h5><i class="bi bi-gift text-success"></i> Earn More Coins</h5>
                                        <p class="small">Keep voting and spinning to build up your QR coin balance!</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <a href="<?php echo APP_URL; ?>/user/vote.php" class="btn btn-primary me-2">
                                <i class="bi bi-hand-thumbs-up me-1"></i>Go Vote
                            </a>
                            <a href="<?php echo APP_URL; ?>/user/spin.php" class="btn btn-success">
                                <i class="bi bi-arrow-clockwise me-1"></i>Spin Wheel
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <!-- Available Casino Locations -->
        <div class="row g-4">
            <?php foreach ($casino_businesses as $business): ?>
                <?php
                $can_play = $spins_remaining > 0 && $user_balance >= $casino_settings['min_bet'];
                $location_bonus = $business['location_bonus_multiplier'] > 1.0 ? 
                    round(($business['location_bonus_multiplier'] - 1) * 100) : 0;
                ?>
                <div class="col-lg-4 col-md-6">
                    <div class="card casino-location-card h-100 <?php echo $can_play ? 'border-success' : 'border-warning'; ?>">
                        <?php if ($business['logo_path']): ?>
                            <div class="card-img-top bg-light p-3 text-center">
                                <img src="<?php echo APP_URL . '/' . htmlspecialchars($business['logo_path']); ?>" 
                                     alt="<?php echo htmlspecialchars($business['name']); ?>" 
                                     style="max-height: 80px; max-width: 100%; object-fit: contain;">
                            </div>
                        <?php endif; ?>
                        
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title">
                                <?php echo htmlspecialchars($business['name']); ?>
                                <?php if ($can_play): ?>
                                    <span class="badge bg-success ms-1">Available</span>
                                    <?php if ($location_bonus > 0): ?>
                                        <span class="badge bg-warning ms-1">+<?php echo $location_bonus; ?>% Bonus</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark ms-1"><?php echo $spins_remaining <= 0 ? 'No Spins Left' : 'Need More Coins'; ?></span>
                                <?php endif; ?>
                            </h5>
                            
                            <?php if ($business['featured_promotion']): ?>
                                <div class="alert alert-info py-2 mb-3">
                                    <i class="bi bi-megaphone me-1"></i>
                                    <small><?php echo htmlspecialchars($business['featured_promotion']); ?></small>
                                </div>
                            <?php endif; ?>
                            
                            <div class="mb-3">
                                <small class="text-muted">
                                    <i class="bi bi-geo-alt me-1"></i>Visit this location to play<br>
                                    <i class="bi bi-percent me-1"></i><?php echo $business['revenue_share_percentage']; ?>% revenue goes to business<br>
                                    <i class="bi bi-calendar3 me-1"></i><?php echo $business['plays_today']; ?> plays here today
                                    <?php if ($location_bonus > 0): ?>
                                        <br><i class="bi bi-star me-1 text-warning"></i>+<?php echo $location_bonus; ?>% location bonus on all wins!
                                    <?php endif; ?>
                                </small>
                            </div>
                            
                            <div class="mt-auto">
                                <?php if ($can_play): ?>
                                    <a href="<?php echo APP_URL; ?>/casino/slot-machine.php?location_id=<?php echo $business['id']; ?>" 
                                       class="btn btn-danger btn-lg w-100">
                                        <i class="bi bi-dice-5-fill me-2"></i>Play at This Location
                                    </a>
                                <?php elseif ($spins_remaining <= 0): ?>
                                    <button class="btn btn-warning btn-lg w-100" disabled>
                                        <i class="bi bi-hourglass me-2"></i>No Spins Remaining
                                    </button>
                                    <div class="text-center mt-2">
                                        <a href="<?php echo APP_URL; ?>/user/qr-store.php?category=slot_pack" class="btn btn-sm btn-outline-danger">
                                            <i class="bi bi-gem me-1"></i>Buy More Spins
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <button class="btn btn-secondary btn-lg w-100" disabled>
                                        <i class="bi bi-coin me-2"></i>Need More QR Coins
                                    </button>
                                    <div class="text-center mt-2">
                                        <a href="<?php echo APP_URL; ?>/user/vote.php" class="btn btn-sm btn-outline-primary">
                                            Earn Coins
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    
    <!-- Casino Help Section -->
    <div class="row mt-5">
        <div class="col-12">
            <div class="card bg-dark text-white">
                <div class="card-body">
                    <h5><i class="bi bi-question-circle me-2"></i>How to Play</h5>
                    <div class="row">
                        <div class="col-md-3">
                            <h6><i class="bi bi-1-circle text-primary"></i> Choose Business</h6>
                            <p class="small">Select a casino-enabled business to support</p>
                        </div>
                        <div class="col-md-3">
                            <h6><i class="bi bi-2-circle text-success"></i> Play Slots</h6>
                            <p class="small">Use your QR avatar symbols in the slot machine</p>
                        </div>
                        <div class="col-md-3">
                            <h6><i class="bi bi-3-circle text-warning"></i> Win Prizes</h6>
                            <p class="small">Win QR coins or real business rewards</p>
                        </div>
                        <div class="col-md-3">
                            <h6><i class="bi bi-4-circle text-danger"></i> Support Local</h6>
                            <p class="small">Your play helps support local businesses</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.text-gradient-casino {
    background: linear-gradient(45deg, #dc3545, #fd7e14, #ffc107);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.casino-business-card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.casino-business-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}
</style>

<?php include '../core/includes/footer.php'; ?> 