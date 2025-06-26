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

echo "<!-- DEBUG: Casino Index Debug Version -->\n";

// Get unified casino settings and participating businesses
$stmt = $pdo->query("SELECT * FROM casino_unified_settings WHERE id = 1");
$casino_settings = $stmt->fetch() ?: [
    'platform_name' => 'Revenue QR Casino',
    'base_daily_spins' => 10,
    'min_bet' => 1,
    'max_bet' => 50
];

echo "<!-- DEBUG: Casino settings loaded -->\n";

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

echo "<!-- DEBUG: Found " . count($casino_businesses) . " casino businesses -->\n";

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

echo "<!-- DEBUG: User balance: $user_balance, Spins remaining: $spins_remaining -->\n";

include '../core/includes/header.php';
?>

<!-- DEBUG: Layout Issue Investigation -->
<style>
/* Debug styles to identify layout issues */
.debug-section {
    border: 2px solid red;
    margin: 10px 0;
    padding: 10px;
    background: rgba(255, 0, 0, 0.1);
    position: relative;
}

.debug-section::before {
    content: attr(data-debug);
    position: absolute;
    top: -10px;
    left: 10px;
    background: red;
    color: white;
    padding: 2px 8px;
    font-size: 12px;
    font-weight: bold;
}

/* Ensure proper layout structure */
.casino-content {
    position: relative;
    z-index: 1;
    width: 100%;
    overflow: visible;
}

.casino-business-card {
    position: relative;
    z-index: 2;
    margin-bottom: 20px;
}

/* Clear any potential floating or positioning issues */
.clear-fix::after {
    content: "";
    display: table;
    clear: both;
}
</style>

<div class="container-fluid py-4 casino-content clear-fix">
    <div class="debug-section" data-debug="HEADER SECTION">
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
    </div>

    <div class="debug-section" data-debug="SPIN PACKS SECTION">
        <!-- Casino Spin Packs Section -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card bg-gradient text-white" style="background: linear-gradient(135deg, #dc3545 0%, #fd7e14 100%);">
                    <div class="card-body text-center">
                        <h4 class="mb-3">
                            <i class="bi bi-gem me-2"></i>Want More Casino Spins?
                        </h4>
                        <p class="mb-3">Purchase spin packs from the QR Store to play more slot machines daily!</p>
                        <div class="mt-3">
                            <a href="<?php echo APP_URL; ?>/user/qr-store.php?category=slot_pack" class="btn btn-light btn-lg">
                                <i class="bi bi-cart-plus me-2"></i>Browse Casino Spin Packs
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if (empty($casino_businesses)): ?>
        <div class="debug-section" data-debug="NO BUSINESSES SECTION">
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
        </div>
    <?php else: ?>
        <div class="debug-section" data-debug="BUSINESS CARDS SECTION">
            <!-- Available Casino Locations -->
            <div class="row g-4">
                <?php foreach ($casino_businesses as $business): ?>
                    <?php
                    $can_play = $spins_remaining > 0 && $user_balance >= $casino_settings['min_bet'];
                    $location_bonus = $business['location_bonus_multiplier'] > 1.0 ? 
                        round(($business['location_bonus_multiplier'] - 1) * 100) : 0;
                    ?>
                    <div class="col-lg-4 col-md-6">
                        <div class="card casino-business-card h-100 <?php echo $can_play ? 'border-success' : 'border-warning'; ?>" style="position: relative; z-index: 10;">
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
                                
                                <div class="mt-auto" style="position: relative; z-index: 15;">
                                    <?php if ($can_play): ?>
                                        <div class="row g-2 mb-3">
                                            <div class="col-6">
                                                <a href="<?php echo APP_URL; ?>/casino/slot-machine.php?location_id=<?php echo $business['id']; ?>" 
                                                   class="btn btn-danger w-100 casino-game-btn"
                                                   style="position: relative; z-index: 20;"
                                                   target="_blank">
                                                    <i class="bi bi-dice-5-fill me-1"></i>Slots
                                                </a>
                                            </div>
                                            <div class="col-6">
                                                <a href="<?php echo APP_URL; ?>/casino/blackjack.php?location_id=<?php echo $business['id']; ?>" 
                                                   class="btn btn-dark w-100 casino-game-btn"
                                                   style="position: relative; z-index: 20;"
                                                   target="_blank">
                                                    <i class="bi bi-person-badge me-1"></i>QR Cards
                                                </a>
                                            </div>
                                        </div>
                                        <div class="text-center">
                                            <small class="text-muted">
                                                <i class="bi bi-star text-warning"></i> New: Custom QR Avatar Blackjack!
                                            </small>
                                        </div>
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
        </div>
    <?php endif; ?>
    
    <div class="debug-section" data-debug="HELP SECTION">
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
                                <h6><i class="bi bi-2-circle text-success"></i> Choose Game</h6>
                                <p class="small">Slots with QR avatars or Blackjack with custom QR cards</p>
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

/* Ensure buttons are properly clickable and positioned */
.casino-game-btn {
    position: relative;
    z-index: 100 !important;
    pointer-events: auto;
}

.casino-game-btn:hover {
    transform: scale(1.05);
    z-index: 101 !important;
}

/* Debug info display */
.debug-info {
    position: fixed;
    top: 10px;
    right: 10px;
    background: rgba(0, 0, 0, 0.9);
    color: white;
    padding: 10px;
    border-radius: 5px;
    font-size: 12px;
    z-index: 9999;
    max-width: 300px;
}
</style>

<!-- Debug Info Panel -->
<div class="debug-info">
    <strong>🔧 Casino Index Debug</strong><br>
    User Balance: <?php echo number_format($user_balance); ?><br>
    Spins Remaining: <?php echo $spins_remaining; ?><br>
    Businesses: <?php echo count($casino_businesses); ?><br>
    <hr style="margin: 5px 0;">
    <small>Check for:</small><br>
    • Layout overlaps<br>
    • Button positioning<br>
    • Content stacking<br>
    • Game loading issues
</div>

<script>
console.log('🔧 Casino Index Debug Mode');
console.log('User Balance:', <?php echo $user_balance; ?>);
console.log('Spins Remaining:', <?php echo $spins_remaining; ?>);
console.log('Businesses Found:', <?php echo count($casino_businesses); ?>);

// Check for any overlapping elements
document.addEventListener('DOMContentLoaded', function() {
    const sections = document.querySelectorAll('.debug-section');
    sections.forEach((section, index) => {
        console.log(`Section ${index + 1}:`, section.dataset.debug, section.getBoundingClientRect());
    });
    
    // Check for any absolute positioned elements that might cause overlap
    const absoluteElements = document.querySelectorAll('[style*="position: absolute"], [style*="position: fixed"]');
    if (absoluteElements.length > 0) {
        console.warn('⚠️ Found absolutely positioned elements that might cause layout issues:', absoluteElements);
    }
    
    // Check for any hidden content that might be causing issues
    const hiddenElements = document.querySelectorAll('[style*="display: none"]');
    console.log('Hidden elements found:', hiddenElements.length);
});
</script>

<?php include '../core/includes/footer.php'; ?> 