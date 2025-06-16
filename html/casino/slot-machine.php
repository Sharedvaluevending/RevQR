<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/functions.php';

// Require login
if (!is_logged_in()) {
    header('Location: ' . APP_URL . '/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

// Accept both business_id and location_id for compatibility
$business_id = $_GET['business_id'] ?? $_GET['location_id'] ?? null;
if (!$business_id) {
    $_SESSION['error'] = 'Business/Location ID required';
    header('Location: ' . APP_URL . '/casino/');
    exit;
}

// Get business and casino settings
$stmt = $pdo->prepare("
    SELECT b.*, bcp.*, 
           COUNT(cp.id) as daily_plays,
           GROUP_CONCAT(DISTINCT pr.prize_name) as available_prizes
    FROM businesses b
    JOIN business_casino_participation bcp ON b.id = bcp.business_id
    LEFT JOIN casino_plays cp ON b.id = cp.business_id AND cp.user_id = ? AND DATE(cp.played_at) = CURDATE()
    LEFT JOIN casino_prizes pr ON b.id = pr.business_id AND pr.is_active = 1
    WHERE b.id = ? AND bcp.casino_enabled = 1
    GROUP BY b.id
");
$stmt->execute([$_SESSION['user_id'], $business_id]);
$business = $stmt->fetch();

if (!$business) {
    $_SESSION['error'] = 'Casino not available for this business';
    header('Location: ' . APP_URL . '/casino/');
    exit;
}

// Get user's QR coin balance and check spin availability
require_once __DIR__ . '/../core/qr_coin_manager.php';
require_once __DIR__ . '/../core/casino_spin_manager.php';
$user_balance = QRCoinManager::getBalance($_SESSION['user_id']);

// Check casino spin availability (including spin packs) - this replaces the old daily limit check
$spin_info = CasinoSpinManager::getAvailableSpins($_SESSION['user_id'], $business_id);

// DEBUG: Log spin info for troubleshooting
error_log("SLOT MACHINE DEBUG - User {$_SESSION['user_id']}, Business $business_id: " . json_encode($spin_info));

if ($spin_info['spins_remaining'] <= 0) {
    $_SESSION['error'] = 'No casino spins remaining today. Purchase spin packs from the QR Store for more spins!';
    error_log("SLOT MACHINE BLOCKED - User {$_SESSION['user_id']} blocked from casino, spins remaining: {$spin_info['spins_remaining']}");
    header('Location: ' . APP_URL . '/casino/');
    exit;
}

if ($user_balance < 1) {
    $_SESSION['error'] = 'Insufficient QR coins. You need at least 1 coin to play.';
    header('Location: ' . APP_URL . '/casino/');
    exit;
}

// Get user's unlocked avatars for slot symbols
$stmt = $pdo->prepare("SELECT avatar_id FROM user_avatars WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$unlocked_avatar_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Add default avatars that everyone has access to
$default_avatars = [1, 12, 13]; // QR Ted, QR Steve, QR Bob
$all_available_avatars = array_unique(array_merge($default_avatars, $unlocked_avatar_ids));

// Convert to the format expected by the slot machine
$user_avatars = [];
foreach ($all_available_avatars as $avatar_id) {
    $user_avatars[] = [
        'avatar_path' => 'assets/img/avatars/' . getAvatarFilename($avatar_id),
        'level' => 1, // Default level for now
        'avatar_id' => $avatar_id
    ];
}

// Limit to 9 avatars for slot machine
$user_avatars = array_slice($user_avatars, 0, 9);

// Default avatars if somehow none are available
if (empty($user_avatars)) {
    $user_avatars = [
        ['avatar_path' => 'assets/img/avatars/qrted.png', 'level' => 1, 'avatar_id' => 1],
        ['avatar_path' => 'assets/img/avatars/qrsteve.png', 'level' => 1, 'avatar_id' => 12],
        ['avatar_path' => 'assets/img/avatars/qrbob.png', 'level' => 1, 'avatar_id' => 13],
    ];
}

include '../core/includes/header.php';
?>

<div class="container-fluid py-4">
    <!-- Casino Header -->
    <div class="row mb-4">
        <div class="col-12 text-center">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <a href="<?php echo APP_URL; ?>/casino/" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i>Back to Casino
                </a>
                
                <h1 class="mb-0">
                    🎰 <span class="text-gradient-casino"><?php echo htmlspecialchars($business['name']); ?> Slots</span>
                </h1>
                
                <div class="d-flex align-items-center bg-dark text-white px-3 py-2 rounded-pill">
                    <img src="<?php echo APP_URL; ?>/img/qrCoin.png" alt="QR Coin" style="width: 1.5rem; height: 1.5rem;" class="me-2">
                    <span id="userBalance"><?php echo number_format($user_balance); ?></span>
                </div>
            </div>
            
            <!-- Business Logo -->
            <?php if ($business['logo_path']): ?>
                <div class="mb-3">
                    <img src="<?php echo APP_URL . '/' . htmlspecialchars($business['logo_path']); ?>" 
                         alt="<?php echo htmlspecialchars($business['name']); ?>" 
                         style="max-height: 60px;" class="img-fluid">
                </div>
            <?php endif; ?>
            
            <p class="text-muted">
                Play with your QR avatars • Win prizes • Support <?php echo htmlspecialchars($business['name']); ?>
                <br><small id="spinCountDisplay">Spins: <span id="spinsUsed"><?php echo $spin_info['spins_used']; ?></span>/<span id="totalSpins"><?php echo $spin_info['total_spins']; ?></span> used today 
                <span id="bonusSpinsText"><?php if ($spin_info['bonus_spins'] > 0): ?>
                    (<span id="bonusSpins"><?php echo $spin_info['bonus_spins']; ?></span> bonus from spin packs!)
                <?php endif; ?></span>
                </small>
            </p>
            
            <div id="spinPackAlert" <?php if ($spin_info['bonus_spins'] <= 0): ?>style="display: none;"<?php endif; ?> class="alert alert-info d-inline-block mb-3">
                <i class="bi bi-gift me-1"></i>
                <strong>Spin Pack Active!</strong> You have <span id="extraSpinsDisplay"><?php echo $spin_info['bonus_spins']; ?></span> extra spins today
                <div id="activePacksList">
                    <?php if (!empty($spin_info['active_packs'])): ?>
                        <br><small>
                            <?php foreach ($spin_info['active_packs'] as $pack): ?>
                                • <?php echo htmlspecialchars($pack['name']); ?> 
                                <?php if ($pack['expires_at']): ?>
                                    (expires <?php echo date('M j', strtotime($pack['expires_at'])); ?>)
                                <?php endif; ?>
                                <br>
                            <?php endforeach; ?>
                        </small>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Jackpot Display -->
    <div class="row">
        <div class="col-12">
            <div class="jackpot-display text-center mb-4">
                <h3 class="mb-0" style="color: #000; font-weight: bold; text-shadow: 2px 2px 4px rgba(255,255,255,0.8);">
                    <i class="bi bi-star-fill" style="color: #ffd700;"></i>
                    JACKPOT: <span id="jackpotAmount"><?php echo number_format(500); ?></span> QR Coins
                    <i class="bi bi-star-fill" style="color: #ffd700;"></i>
                </h3>
            </div>
        </div>
    </div>

    <!-- Slot Machine Game -->
    <div class="row">
        <div class="col-12">
            <div class="slot-machine-full-width">
                    <!-- Loading Indicator -->
                    <div id="loadingIndicator" class="text-center py-4">
                        <div class="d-flex align-items-center justify-content-center">
                            <div class="spinner-border text-warning me-3" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <div>
                                <h5 class="mb-1">Loading Casino Assets...</h5>
                                <small class="text-muted">Preparing high-quality avatars for smooth gameplay</small>
                            </div>
                        </div>
                        <div class="progress mt-3" style="height: 8px;">
                            <div class="progress-bar bg-warning progress-bar-striped progress-bar-animated" 
                                 role="progressbar" style="width: 100%"></div>
                        </div>
                    </div>

                    <!-- Slot Machine Container -->
                    <div id="slotMachine" class="slot-machine-container" style="display: none;">
                        
                        <!-- Slot Reels -->
                        <div class="slot-machine-wrapper">
                            <div class="slot-reels-container">
                                <div class="slot-reel" id="reel1">
                                    <div class="slot-symbol-container">
                                        <!-- Symbols will be dynamically generated -->
                                    </div>
                                </div>
                                <div class="slot-reel" id="reel2">
                                    <div class="slot-symbol-container">
                                        <!-- Symbols will be dynamically generated -->
                                    </div>
                                </div>
                                <div class="slot-reel" id="reel3">
                                    <div class="slot-symbol-container">
                                        <!-- Symbols will be dynamically generated -->
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Betting Controls -->
                        <div class="betting-controls text-center">
                            <div class="bet-amount-section mb-3">
                                <label class="form-label">Bet Amount:</label>
                                <div class="btn-group" role="group">
                                    <input type="radio" class="btn-check" name="betAmount" id="bet1" value="1" checked>
                                    <label class="btn btn-outline-primary" for="bet1" style="padding: 10px 20px; font-size: 1.1em;">1 Coin</label>
                                    
                                    <input type="radio" class="btn-check" name="betAmount" id="bet5" value="5">
                                    <label class="btn btn-outline-primary" for="bet5" style="padding: 10px 20px; font-size: 1.1em;">5 Coins</label>
                                    
                                    <input type="radio" class="btn-check" name="betAmount" id="bet10" value="10">
                                    <label class="btn btn-outline-primary" for="bet10" style="padding: 10px 20px; font-size: 1.1em;">10 Coins</label>
                                </div>
                            </div>
                            
                            <button id="spinButton" class="btn btn-danger btn-lg px-5" style="font-size: 1.3em; padding: 12px 40px;">
                                <i class="bi bi-dice-5-fill me-2"></i>SPIN
                            </button>
                        </div>
                        
                        <!-- Win Display -->
                        <div id="winDisplay" class="win-display text-center mt-4" style="display: none;">
                            <h2 class="text-success mb-2">
                                <i class="bi bi-trophy-fill"></i>
                                <span id="winMessage">YOU WIN!</span>
                            </h2>
                            <h3 class="text-warning">
                                <span id="winAmount">0</span> QR Coins
                            </h3>
                        </div>
                    </div>
            </div>
        </div>
    </div>
    
    <!-- Game Rules -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card" style="font-size: 1.1em;">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0"><i class="bi bi-info-circle me-2"></i>How to Win</h4>
                </div>
                <div class="card-body p-4">
                    <div class="row">
                        <div class="col-md-6">
                            <h5 class="text-primary mb-3">🎯 Winning Combinations:</h5>
                            <ul class="list-unstyled" style="font-size: 1.0em;">
                                <li class="mb-2">
                                    <span class="badge bg-warning text-dark px-3 py-2">🔥 STRAIGHT LINE (3 Across)</span> 
                                    <span class="ms-2 fw-bold">= Level × 3x bet</span>
                                </li>
                                <li class="mb-2">
                                    <span class="badge bg-info px-3 py-2">💫 DIAGONAL (Corners Match)</span> 
                                    <span class="ms-2 fw-bold">= 5x - 12x bet</span>
                                </li>
                                <li class="mb-2">
                                    <span class="badge bg-success px-3 py-2">🏆 RARITY LINE (Same Rarity)</span> 
                                    <span class="ms-2 fw-bold">= 4x - 10x bet</span>
                                </li>
                                <li class="mb-2">
                                    <span class="badge bg-gradient text-white px-3 py-2" style="background: linear-gradient(45deg, #ff6b6b, #feca57);">🌟 WILD COMBINATIONS</span> 
                                    <span class="ms-2 fw-bold">= Extra bonuses!</span>
                                </li>
                                <li class="mb-2">
                                    <span class="badge bg-danger px-3 py-2">💎 MYTHICAL JACKPOT</span> 
                                    <span class="ms-2 fw-bold">= 25x bet</span>
                                </li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h5 class="text-primary mb-3">🎮 Avatar Slots & Wild Symbol:</h5>
                            <div class="avatar-collection mb-3">
                                <?php 
                                $rare_avatars = [
                                    ['name' => 'Lord Pixel', 'file' => 'qrLordPixel.png', 'rarity' => 'mythical'],
                                    ['name' => 'QR Clayton', 'file' => 'qrClayton.png', 'rarity' => 'legendary'],
                                    ['name' => 'QR Easybake', 'file' => 'qrEasybake.png', 'rarity' => 'wild', 'isWild' => true],
                                    ['name' => 'QR Ned', 'file' => 'qrned.png', 'rarity' => 'epic'],
                                    ['name' => 'QR Ed', 'file' => 'qred.png', 'rarity' => 'rare'],
                                    ['name' => 'QR Mike', 'file' => 'qrmike.png', 'rarity' => 'rare']
                                ];
                                $rarity_colors = [
                                    'mythical' => '#8A2BE2',
                                    'legendary' => '#FF6B35', 
                                    'wild' => '#00FF00',
                                    'epic' => '#9932CC',
                                    'rare' => '#1E90FF'
                                ];
                                ?>
                                <?php foreach ($rare_avatars as $avatar): ?>
                                    <img src="<?php echo APP_URL; ?>/assets/img/avatars/<?php echo $avatar['file']; ?>" 
                                         alt="<?php echo $avatar['name']; ?>" 
                                         title="<?php echo $avatar['name']; ?> (<?php echo ucfirst($avatar['rarity']); ?>)<?php echo isset($avatar['isWild']) ? ' - WILD!' : ''; ?>"
                                         style="width: 50px; height: 50px; border-radius: 50%; margin: 4px; border: 3px solid <?php echo $rarity_colors[$avatar['rarity']]; ?>; box-shadow: 0 0 12px <?php echo $rarity_colors[$avatar['rarity']]; ?>70; <?php echo isset($avatar['isWild']) ? 'animation: wildPulse 2s ease-in-out infinite;' : ''; ?>">
                                <?php endforeach; ?>
                            </div>
                            <div class="wild-info mb-3 p-3" style="background: linear-gradient(45deg, rgba(0,255,0,0.1), rgba(255,215,0,0.1)); border-radius: 8px; border-left: 4px solid #00FF00;">
                                <h6 class="text-success mb-2">🌟 QR Easybake is WILD!</h6>
                                <p class="mb-1 small">• Substitutes for any symbol</p>
                                <p class="mb-1 small">• Adds bonus multipliers to wins</p>
                                <p class="mb-0 small">• Triple wilds = MEGA JACKPOT!</p>
                            </div>
                            <div class="winning-patterns mt-3 p-3" style="background: rgba(0,123,255,0.1); border-radius: 8px; border-left: 4px solid #007bff;">
                                <h6 class="text-primary mb-2">📐 5 Ways to Win:</h6>
                                <div class="row text-center" style="font-size: 0.9em;">
                                    <div class="col-4">
                                        <strong>STRAIGHT:</strong><br>
                                        <span style="font-family: monospace; color: #28a745;">🎯 🎯 🎯</span><br>
                                        <small class="text-success">3 identical across</small>
                                    </div>
                                    <div class="col-4">
                                        <strong>DIAGONAL:</strong><br>
                                        <span style="font-family: monospace; color: #17a2b8;">🔥 ⭐ 🔥</span><br>
                                        <small class="text-info">Corner matches</small>
                                    </div>
                                    <div class="col-4">
                                        <strong>WILDS:</strong><br>
                                        <span style="font-family: monospace; color: #ffc107;">🌟 🎯 🌟</span><br>
                                        <small class="text-warning">Any + wilds</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Pass data to JavaScript -->
<script>
window.casinoData = {
    businessId: <?php echo $business_id; ?>,
    userBalance: <?php echo $user_balance; ?>,
    jackpotMultiplier: 6,
    avatars: <?php echo json_encode($user_avatars); ?>,
    appUrl: '<?php echo APP_URL; ?>',
    spinInfo: <?php echo json_encode($spin_info); ?>
};
</script>

<style>
.text-gradient-casino {
    background: linear-gradient(45deg, #dc3545, #fd7e14, #ffc107);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.slot-machine-full-width {
    background: transparent;
    color: white;
    padding: 20px 0;
}

.slot-machine-wrapper {
    display: flex;
    justify-content: center;
    align-items: center;
    margin: 30px 0 50px 0; /* Added bottom margin to prevent overlap */
    position: relative;
    height: 650px; /* Increased height for much bigger slot machine */
}

.slot-machine-wrapper::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 850px;
    height: 850px;
    background-image: url('<?php echo APP_URL; ?>/casino/slotassests/slotsqr.png');
    background-size: contain;
    background-repeat: no-repeat;
    background-position: center;
    z-index: 1;
    pointer-events: none;
}

.slot-reels-container {
    display: flex;
    justify-content: center;
    gap: 11px; /* Reduced gap to bring outer reels inward more */
    position: relative;
    z-index: 2;
    margin-top: -230px; /* Adjusted to better fit slot machine frame */
    padding: 0 10px;
}

.slot-reel {
    width: 140px;
    height: 175px;
    background: transparent;
    border: none;
    border-radius: 8px;
    overflow: hidden;
    position: relative;
    box-shadow: inset 0 0 10px rgba(0,0,0,0.4);
}

.slot-symbol-container {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    transition: none;
    /* GSAP Performance Optimizations */
    transform: translateZ(0);
    backface-visibility: hidden;
    will-change: transform;
    contain: layout style paint;
}

.slot-symbol {
    width: 100%;
    height: 58px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-bottom: 1px solid rgba(255,255,255,0.1);
    position: relative;
}

.slot-symbol img {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    border: 3px solid #ffd700;
    box-shadow: 0 3px 15px rgba(0,0,0,0.4);
    transition: none; /* Remove CSS transitions for GSAP control */
    /* Image optimizations */
    image-rendering: -webkit-optimize-contrast;
    image-rendering: optimize-contrast;
    transform: translateZ(0);
    backface-visibility: hidden;
    will-change: transform, border-color, box-shadow;
}

.winning-symbol {
    background: radial-gradient(circle, rgba(255,193,7,0.2) 0%, transparent 70%);
}

.winning-symbol img {
    border-color: #ffd700;
    box-shadow: 0 0 15px rgba(255, 193, 7, 0.8);
}

.jackpot-display {
    background: linear-gradient(45deg, #ff6b6b, #feca57);
    padding: 25px;
    border-radius: 20px;
    box-shadow: 0 10px 30px rgba(255,107,107,0.5);
    animation: jackpotGlow 2s ease-in-out infinite alternate;
    font-size: 1.4em;
    margin: 20px 0 40px 0;
    width: 100%;
    max-width: 800px;
    margin-left: auto;
    margin-right: auto;
}

@keyframes jackpotGlow {
    from { box-shadow: 0 5px 15px rgba(255,107,107,0.3); }
    to { box-shadow: 0 5px 25px rgba(255,107,107,0.6); }
}

.betting-controls {
    background: rgba(255,255,255,0.15);
    padding: 30px;
    border-radius: 15px;
    backdrop-filter: blur(8px);
    margin-top: 40px; /* Add space from slot machine */
    position: relative;
    z-index: 3;
    font-size: 1.1em;
}

.win-display {
    background: linear-gradient(45deg, #28a745, #20c997);
    padding: 20px;
    border-radius: 15px;
    box-shadow: 0 10px 30px rgba(40,167,69,0.3);
    animation: winPulse 0.5s ease-in-out;
}

@keyframes winPulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
}

.spinning {
    box-shadow: 0 0 30px rgba(255, 193, 7, 0.6) !important;
    animation: reelGlow 0.1s ease-in-out infinite alternate;
}

@keyframes reelGlow {
    from { 
        box-shadow: 0 0 30px rgba(255, 193, 7, 0.6);
        border-color: #ffc107;
    }
    to { 
        box-shadow: 0 0 40px rgba(255, 193, 7, 0.8);
        border-color: #ffd700;
    }
}

@keyframes winCelebration {
    0% { 
        transform: scale(0.8);
        opacity: 0;
    }
    50% { 
        transform: scale(1.1);
        opacity: 1;
    }
    100% { 
        transform: scale(1);
        opacity: 1;
    }
}

@keyframes winPulse {
    from { 
        transform: scale(1);
        filter: brightness(1);
    }
    to { 
        transform: scale(1.1);
        filter: brightness(1.3);
    }
}

@keyframes sparkleAnimation {
    0% { 
        transform: translateY(0) scale(0);
        opacity: 1;
    }
    50% {
        transform: translateY(-20px) scale(1);
        opacity: 1;
    }
    100% { 
        transform: translateY(-40px) scale(0);
        opacity: 0;
    }
}

@keyframes jackpotGlow {
    0% { 
        box-shadow: inset 0 0 20px rgba(0,0,0,0.2);
        border-color: #ffc107;
    }
    50% { 
        box-shadow: 0 0 40px rgba(255, 0, 0, 0.8);
        border-color: #ff0000;
    }
    100% { 
        box-shadow: inset 0 0 20px rgba(0,0,0,0.2);
        border-color: #ffc107;
    }
}

#spinButton:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

#spinButton:not(:disabled):hover {
    transform: scale(1.05);
    box-shadow: 0 5px 15px rgba(220, 53, 69, 0.4);
}

.slot-reels-container {
    position: relative;
    overflow: visible;
}

/* Enhanced visual effects for better slot machine integration */
.slot-machine-wrapper {
    filter: drop-shadow(0 10px 25px rgba(0,0,0,0.3));
}

.slot-reel::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(
        180deg, 
        rgba(255,255,255,0.1) 0%, 
        rgba(0,0,0,0.05) 50%, 
        rgba(255,255,255,0.1) 100%
    );
    pointer-events: none;
    z-index: 1;
}

.slot-symbol-container {
    position: relative;
    z-index: 2;
}

/* Winning symbol glow effect */
.winning-symbol {
    background: radial-gradient(circle, rgba(255,215,0,0.3) 0%, transparent 70%);
    animation: winGlow 1s ease-in-out infinite alternate;
}

@keyframes winGlow {
    from { 
        background: radial-gradient(circle, rgba(255,215,0,0.3) 0%, transparent 70%);
    }
    to { 
        background: radial-gradient(circle, rgba(255,215,0,0.6) 0%, transparent 70%);
    }
}

.winning-symbol img {
    border-color: #ffd700;
    box-shadow: 0 0 20px rgba(255, 215, 0, 0.8);
    filter: brightness(1.2);
}

/* Wild Symbol Styles */
.wild-symbol {
    background: radial-gradient(circle, rgba(0,255,0,0.2) 0%, rgba(255,215,0,0.1) 70%, transparent 100%);
    border-radius: 8px;
    animation: wildShimmer 3s ease-in-out infinite;
}

@keyframes wildShimmer {
    0%, 100% { 
        background: radial-gradient(circle, rgba(0,255,0,0.2) 0%, rgba(255,215,0,0.1) 70%, transparent 100%);
        box-shadow: 0 0 10px rgba(0,255,0,0.3);
    }
    50% { 
        background: radial-gradient(circle, rgba(0,255,0,0.4) 0%, rgba(255,215,0,0.2) 70%, transparent 100%);
        box-shadow: 0 0 15px rgba(0,255,0,0.5);
    }
}

.wild-symbol img {
    border-color: #00FF00 !important;
    box-shadow: 0 0 15px rgba(0, 255, 0, 0.8) !important;
    filter: brightness(1.3) saturate(1.2);
}

/* Wild Symbol Pulse Animation for Rules */
@keyframes wildPulse {
    0%, 100% { 
        box-shadow: 0 0 12px #00FF0070;
        transform: scale(1);
    }
    50% { 
        box-shadow: 0 0 20px #00FF00AA;
        transform: scale(1.05);
    }
}

/* Enhanced Wild + Winning Combination */
.wild-symbol.winning-symbol {
    background: radial-gradient(circle, rgba(0,255,0,0.4) 0%, rgba(255,215,0,0.3) 50%, transparent 100%);
    animation: wildWinGlow 0.8s ease-in-out infinite alternate;
}

@keyframes wildWinGlow {
    from { 
        background: radial-gradient(circle, rgba(0,255,0,0.4) 0%, rgba(255,215,0,0.3) 50%, transparent 100%);
        box-shadow: 0 0 20px rgba(0,255,0,0.6);
    }
    to { 
        background: radial-gradient(circle, rgba(0,255,0,0.6) 0%, rgba(255,215,0,0.5) 50%, transparent 100%);
        box-shadow: 0 0 30px rgba(0,255,0,0.9);
    }
}

.wild-symbol.winning-symbol img {
    border-color: #00FF00 !important;
    box-shadow: 0 0 25px rgba(0, 255, 0, 1.0) !important;
    filter: brightness(1.5) saturate(1.5);
}

/* Responsive adjustments for mobile */
@media (max-width: 768px) {
    .slot-machine-wrapper {
        height: 520px;
    }
    
    .slot-machine-wrapper::before {
        width: 650px;
        height: 650px;
    }
    
.slot-reels-container {
        gap: 9px;
        margin-top: -170px; /* Adjusted for tablet positioning */
    }
    
    .slot-reel {
        width: 110px;
        height: 140px;
    }
    
    .slot-symbol img {
        width: 42px;
        height: 42px;
        border: 2px solid #ffd700;
    }
    
    .slot-symbol {
        height: 48px;
    }
}

@media (max-width: 480px) {
    .slot-machine-wrapper {
        height: 420px;
    }
    
    .slot-machine-wrapper::before {
        width: 520px;
        height: 520px;
    }
    
.slot-reels-container {
        gap: 7px;
        margin-top: -135px; /* Adjusted for mobile positioning */
    }
    
    .slot-reel {
        width: 95px;
        height: 120px;
    }
    
    .slot-symbol img {
        width: 36px;
        height: 36px;
        border: 2px solid #ffd700;
    }
    
    .slot-symbol {
        height: 40px;
    }
}

/* Full width responsive adjustments */
@media (max-width: 768px) {
    .betting-controls {
        padding: 25px;
        font-size: 1.05em;
    }
    
    .jackpot-display {
        font-size: 1.2em;
        padding: 20px;
        margin: 15px 0 30px 0;
    }
}

@media (max-width: 480px) {
    .betting-controls {
        padding: 20px;
        font-size: 1em;
    }
    
    .jackpot-display {
        font-size: 1.1em;
        padding: 18px;
        margin: 10px 0 25px 0;
    }
}
</style>

<!-- Include GSAP Animation Library -->
<script src="https://cdn.jsdelivr.net/npm/gsap@3.12.2/dist/gsap.min.js"></script>

<!-- Include Slot Machine JavaScript -->
<script src="<?php echo APP_URL; ?>/casino/js/slot-machine.js?v=<?php echo time(); ?>"></script>

<?php include '../core/includes/footer.php'; ?> 