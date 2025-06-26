<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Start output buffering to prevent header issues
ob_start();

require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/functions.php';
require_once __DIR__ . '/../core/qr_coin_manager.php';

// Check if user is logged in using the existing session system
if (!is_logged_in()) {
    // Debug: log why user is being redirected
    error_log("Blackjack: User not logged in, redirecting to login");
    
    // Preserve the URL with location_id for after login
    $current_url = $_SERVER['REQUEST_URI'] ?? '/casino/blackjack.php';
    $encoded_url = urlencode('https://revenueqr.sharedvaluevending.com' . $current_url);
    
    header('Location: ../user/login.php?redirect=' . $encoded_url);
    exit();
}

$user_id = $_SESSION['user_id'];

// Get location_id or business_id from URL parameter  
$business_id = $_GET['business_id'] ?? $_GET['location_id'] ?? null;

// If no business_id provided, try to get the default/first available casino business
if (!$business_id) {
    $stmt = $pdo->query("
        SELECT b.id 
        FROM businesses b 
        JOIN business_casino_participation bcp ON b.id = bcp.business_id 
        WHERE bcp.casino_enabled = 1 
        ORDER BY b.id ASC 
        LIMIT 1
    ");
    $default_business = $stmt->fetch();
    if ($default_business) {
        $business_id = $default_business['id'];
        error_log("BLACKJACK: No business_id provided, using default business ID: $business_id");
    }
}

// Debug logging
error_log("Blackjack: User ID: " . $user_id . ", Business ID: " . ($business_id ?: 'none'));

try {
    // Get user's current balance using QRCoinManager (same as other parts of the system)
    $current_balance = QRCoinManager::getBalance($user_id);
    
    // Get user's business info
    $stmt = $pdo->prepare("
        SELECT u.business_id, b.name as business_name
        FROM users u 
        LEFT JOIN businesses b ON u.business_id = b.id 
        WHERE u.id = ?
    ");
    $stmt->execute([$user_id]);
    $user_data = $stmt->fetch();

    if (!$user_data) {
        throw new Exception('User not found in database');
    }

    // If business_id is provided, use that for casino business info
    if ($business_id) {
        $stmt = $pdo->prepare("
            SELECT b.id, b.name as business_name, bcp.casino_enabled as participation_enabled
            FROM businesses b
            LEFT JOIN business_casino_participation bcp ON b.id = bcp.business_id
            WHERE b.id = ?
        ");
        $stmt->execute([$business_id]);
        $casino_business = $stmt->fetch();
        
        if (!$casino_business) {
            error_log("Blackjack: Casino business not found for business_id: " . $business_id);
            
            // Check if business exists at all
            $stmt = $pdo->prepare("SELECT id, name as business_name FROM businesses WHERE id = ?");
            $stmt->execute([$business_id]);
            $business_check = $stmt->fetch();
            
            if ($business_check) {
                error_log("Blackjack: Business exists but casino business record not found: " . $business_check['business_name']);
                throw new Exception('Casino not available for this location: ' . $business_check['business_name']);
            } else {
                error_log("Blackjack: Business does not exist: " . $business_id);
                throw new Exception('Business location not found: ' . $business_id);
            }
        }
        
        error_log("Blackjack: Successfully loaded casino business: " . $casino_business['business_name']);
        
        $business_name = $casino_business['business_name'] ?? 'QR Casino';
        $app_url = APP_URL; // Use default app URL
        
        // Use default casino settings
        $casino_settings = [
            'jackpot_multiplier' => 50,
            'min_bet' => 1,
            'max_bet' => 100
        ];
    } else {
        // No business ID available - throw error
        throw new Exception('No casino business available. Please contact support.');
    }

    $jackpot_multiplier = $casino_settings['jackpot_multiplier'] ?? 50;
    $min_bet = $casino_settings['min_bet'] ?? 1;
    $max_bet = $casino_settings['max_bet'] ?? 100;

} catch (Exception $e) {
    error_log("Blackjack Error: " . $e->getMessage());
    include '../core/includes/header.php';
    ?>
    <div class="container py-4">
        <div class="alert alert-danger">
            <h4>Blackjack Unavailable</h4>
            <p><?php echo htmlspecialchars($e->getMessage()); ?></p>
            <a href="<?php echo APP_URL; ?>/casino/" class="btn btn-primary">Back to Casino</a>
        </div>
    </div>
    <?php
    include '../core/includes/footer.php';
    exit();
}

include '../core/includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-lg-12">
            <!-- Header Card -->
            <div class="card bg-dark text-white mb-4" style="background: linear-gradient(135deg, #1a1a1a, #2d2d2d); border: none; box-shadow: 0 8px 32px rgba(0,0,0,0.3);">
                <div class="card-body text-center py-4">
                    <h1 class="mb-3" style="font-size: 2.5rem; font-weight: 700; text-shadow: 2px 2px 4px rgba(0,0,0,0.5);">
                        🃏 QR Blackjack 🃏
                    </h1>
                    <p class="lead mb-3">Premium Blackjack featuring QR Easybake on the Aces</p>
                    <div class="d-flex justify-content-center align-items-center gap-4 flex-wrap">
                        <div class="balance-display">
                            <span class="badge bg-success fs-5 px-4 py-2">
                                <i class="bi bi-coin me-2"></i>Balance: <span id="userBalance"><?php echo number_format($current_balance); ?></span> QR Coins
                            </span>
                        </div>
                        <div class="game-stats">
                            <span class="badge bg-info fs-6 px-3 py-2">
                                <i class="bi bi-trophy me-1"></i>House Edge: 5%
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Game Area -->
            <div class="row">
                <!-- Game Table -->
                <div class="col-lg-8">
                    <div class="card bg-success text-white" style="background: linear-gradient(135deg, #0d5f33, #1a7f4a) !important; border: 3px solid #ffd700; box-shadow: 0 12px 40px rgba(0,0,0,0.4);">
                        <div class="card-header bg-transparent border-bottom-0 text-center py-3">
                            <h4 class="mb-0">🎰 Blackjack Table 🎰</h4>
                            <small class="text-light">Get as close to 21 as possible without going over</small>
                        </div>
                        <div class="card-body p-4">
                            <!-- Dealer Area -->
                            <div class="dealer-area mb-4 p-3" style="background: rgba(0,0,0,0.2); border-radius: 15px; border: 2px solid rgba(255,215,0,0.3);">
                                <div class="text-center mb-3">
                                    <h5 class="mb-2">🎩 Dealer</h5>
                                    <div class="dealer-score">
                                        Score: <span id="dealerScore" class="badge bg-warning text-dark fs-6">0</span>
                                    </div>
                                </div>
                                <div class="dealer-cards d-flex justify-content-center flex-wrap gap-2" id="dealerCards">
                                    <!-- Dealer cards will be added here -->
                                </div>
                            </div>

                            <!-- Player Area -->
                            <div class="player-area mb-4 p-3" style="background: rgba(0,0,0,0.2); border-radius: 15px; border: 2px solid rgba(255,215,0,0.3);">
                                <div class="text-center mb-3">
                                    <h5 class="mb-2">🎯 Your Hand</h5>
                                    <div class="player-score">
                                        Score: <span id="playerScore" class="badge bg-info fs-6">0</span>
                                    </div>
                                </div>
                                <div class="player-cards d-flex justify-content-center flex-wrap gap-2" id="playerCards">
                                    <!-- Player cards will be added here -->
                                </div>
                            </div>

                            <!-- Game Controls -->
                            <div class="game-controls text-center">
                                <div class="btn-group mb-3" role="group">
                                    <button type="button" class="btn btn-warning btn-lg" id="hitBtn" disabled>
                                        <i class="bi bi-plus-circle me-2"></i>Hit
                                    </button>
                                    <button type="button" class="btn btn-danger btn-lg" id="standBtn" disabled>
                                        <i class="bi bi-hand-thumbs-up me-2"></i>Stand
                                    </button>
                                    <button type="button" class="btn btn-primary btn-lg" id="newGameBtn">
                                        <i class="bi bi-arrow-clockwise me-2"></i>New Game
                                    </button>
                                </div>
                            </div>

                            <!-- Game Status -->
                            <div class="game-status text-center" id="gameStatus">
                                <h4 class="mb-3">Place your bet and start playing!</h4>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Betting & Info Panel -->
                <div class="col-lg-4">
                    <!-- Betting Panel -->
                    <div class="card bg-dark text-white mb-4" style="border: 2px solid #ffd700; box-shadow: 0 8px 32px rgba(0,0,0,0.3);">
                        <div class="card-header bg-transparent text-center">
                            <h5 class="mb-0"><i class="bi bi-currency-exchange me-2"></i>Betting</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label">Bet Amount:</label>
                                <div class="bet-options">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="betAmount" id="bet1" value="1" checked>
                                        <label class="form-check-label" for="bet1">1 QR Coin</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="betAmount" id="bet5" value="5">
                                        <label class="form-check-label" for="bet5">5 QR Coins</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="betAmount" id="bet10" value="10">
                                        <label class="form-check-label" for="bet10">10 QR Coins</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="betAmount" id="bet25" value="25">
                                        <label class="form-check-label" for="bet25">25 QR Coins</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="betAmount" id="bet50" value="50">
                                        <label class="form-check-label" for="bet50">50 QR Coins</label>
                                    </div>
                                </div>
                            </div>
                            <div class="current-bet mb-3 p-2 bg-success rounded text-center">
                                <small>Current Bet:</small><br>
                                <strong id="currentBet">1 QR Coin</strong>
                            </div>
                        </div>
                    </div>

                    <!-- Card Showcase -->
                    <div class="card bg-dark text-white mb-4" style="border: 2px solid #ffd700;">
                        <div class="card-header bg-transparent text-center">
                            <h5 class="mb-0"><i class="bi bi-person-badge me-2"></i>Custom QR Cards</h5>
                        </div>
                        <div class="card-body text-center">
                            <div class="card-showcase mb-3">
                                <h6 class="text-warning mb-2">🃏 Special Ace Feature:</h6>
                                <div class="avatar-preview-row mb-2">
                                    <img src="<?php echo $app_url; ?>/assets/img/avatars/qrEasybake.png" 
                                         alt="Ace" title="Ace - QR Easybake"
                                         style="width: 60px; height: 60px; border-radius: 50%; margin: 2px; border: 3px solid #ffd700;">
                                </div>
                                <small class="text-muted">All Aces feature QR Easybake avatar</small>
                            </div>
                            <div class="qr-info">
                                <h6 class="text-info mb-2">📱 QR Card Backs</h6>
                                <p class="small text-muted">Every card features a unique QR code design on the back, maintaining the mystery until revealed!</p>
                            </div>
                        </div>
                    </div>

                    <!-- Rules -->
                    <div class="card bg-dark text-white" style="border: 2px solid #ffd700;">
                        <div class="card-header bg-transparent text-center">
                            <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i>How to Play</h5>
                        </div>
                        <div class="card-body">
                            <ul class="list-unstyled small">
                                <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>Get as close to 21 as possible</li>
                                <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>Face cards = 10 points</li>
                                <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>Aces = 1 or 11 points</li>
                                <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>Blackjack pays 3:2</li>
                                <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>Dealer stands on 17</li>
                                <li class="mb-2"><i class="bi bi-exclamation-triangle text-warning me-2"></i>Going over 21 = Bust</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Hidden data for JavaScript -->
<script>
window.blackjackData = {
    userBalance: <?php echo $current_balance ?? 0; ?>,
    businessId: <?php echo $business_id ? (int)$business_id : 'null'; ?>,
    jackpotMultiplier: <?php echo $jackpot_multiplier ?? 50; ?>,
    minBet: <?php echo $min_bet ?? 1; ?>,
    maxBet: <?php echo $max_bet ?? 100; ?>,
    appUrl: '<?php echo $app_url ?? APP_URL; ?>'
};

// Debug logging
console.log('🃏 Blackjack Data Loaded:', window.blackjackData);

// Validate business ID is properly set
if (!window.blackjackData.businessId) {
    console.error('🚨 Critical Error: businessId is missing in blackjack!');
    console.error('URL:', window.location.href);
    console.error('URL Parameters:', new URLSearchParams(window.location.search));
    
    // Try to extract businessId from URL
    const urlParams = new URLSearchParams(window.location.search);
    const fallbackBusinessId = urlParams.get('business_id') || urlParams.get('location_id');
    
    if (fallbackBusinessId) {
        console.log('🔧 Using fallback businessId from URL:', fallbackBusinessId);
        window.blackjackData.businessId = parseInt(fallbackBusinessId);
    } else {
        // Create a user-friendly error message
        let errorMessage = 'Blackjack configuration error: Business ID is missing.\\n';
        errorMessage += `URL: ${window.location.pathname}${window.location.search}\\n`;
        errorMessage += 'This usually happens when accessing blackjack without proper parameters.';
        
        alert(errorMessage);
        
        // Redirect to casino selection if no business ID can be found
        window.location.href = '<?php echo APP_URL; ?>/casino/';
    }
} else {
    console.log('✅ Blackjack configuration valid - businessId:', window.blackjackData.businessId);
}
</script>

<!-- Custom CSS -->
<style>
.card-element {
    width: 80px;
    height: 120px;
    border-radius: 12px;
    border: 2px solid #ffd700;
    background: white;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    position: relative;
    box-shadow: 0 4px 15px rgba(0,0,0,0.3);
    transition: all 0.3s ease;
    cursor: pointer;
}

.card-element:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(255,215,0,0.4);
}

.card-back {
    background: linear-gradient(135deg, #1a1a1a, #2d2d2d);
    color: white;
    border: 2px solid #ffd700;
}

.card-face {
    background: white;
    color: #333;
}

.card-face.red-suit {
    color: #dc3545;
}

.card-face.black-suit {
    color: #333;
}

.face-card-avatar {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    border: 2px solid #ffd700;
    object-fit: cover;
}

.card-value {
    font-size: 1.2rem;
    font-weight: bold;
    text-align: center;
    color: inherit;
    margin-bottom: 5px;
}

.card-suit {
    font-size: 1.5rem;
    text-align: center;
    color: inherit;
}

.qr-pattern {
    width: 40px;
    height: 40px;
    background: 
        radial-gradient(circle at 25% 25%, #ffd700 2px, transparent 2px),
        radial-gradient(circle at 75% 25%, #ffd700 2px, transparent 2px),
        radial-gradient(circle at 25% 75%, #ffd700 2px, transparent 2px),
        radial-gradient(circle at 75% 75%, #ffd700 2px, transparent 2px);
    background-size: 8px 8px;
    opacity: 0.7;
}

/* Card flip animation */
@keyframes cardFlip {
    0% { transform: rotateY(0deg); }
    50% { transform: rotateY(90deg); }
    100% { transform: rotateY(0deg); }
}

.card-flip {
    animation: cardFlip 0.6s ease-in-out;
}

/* Winning glow effect */
@keyframes winGlow {
    0%, 100% { box-shadow: 0 0 5px rgba(255,215,0,0.5); }
    50% { box-shadow: 0 0 25px rgba(255,215,0,0.8), 0 0 35px rgba(255,215,0,0.6); }
}

.win-glow {
    animation: winGlow 1s ease-in-out infinite;
}

/* Game status animations */
.status-win {
    color: #28a745;
    text-shadow: 0 0 10px rgba(40,167,69,0.5);
}

.status-lose {
    color: #dc3545;
    text-shadow: 0 0 10px rgba(220,53,69,0.5);
}

.status-push {
    color: #ffc107;
    text-shadow: 0 0 10px rgba(255,193,7,0.5);
}

/* Mobile Responsiveness Improvements */
@media (max-width: 768px) {
    .container-fluid {
        padding-left: 10px;
        padding-right: 10px;
    }
    
    /* Stack layout on mobile */
    .row > .col-lg-8,
    .row > .col-lg-4 {
        flex: 0 0 100%;
        max-width: 100%;
        margin-bottom: 20px;
    }
    
    /* Smaller cards on mobile */
    .card-element {
        width: 60px;
        height: 90px;
        font-size: 0.8rem;
    }
    
    /* Compact game controls */
    .btn-group .btn-lg {
        padding: 0.5rem 1rem;
        font-size: 1rem;
    }
    
    /* Adjust header text */
    h1 {
        font-size: 1.8rem !important;
    }
    
    .card-header h4 {
        font-size: 1.2rem;
    }
    
    /* Compact betting panel */
    .card-body {
        padding: 1rem;
    }
}

@media (max-width: 480px) {
    /* Even smaller cards for tiny screens */
    .card-element {
        width: 50px;
        height: 75px;
        margin: 2px;
    }
    
    /* Compact buttons */
    .btn-group {
        flex-direction: column;
        width: 100%;
    }
    
    .btn-group .btn {
        margin-bottom: 5px;
        border-radius: 5px !important;
    }
    
    /* Mobile-first game status */
    #gameStatus h4 {
        font-size: 1rem;
    }
    
    /* Compact badges */
    .badge.fs-5 {
        font-size: 1rem !important;
    }
    
    .badge.fs-6 {
        font-size: 0.8rem !important;
    }
}
</style>

<script>
// Debug: Check if elements exist
console.log('🐛 Debug - Checking elements...');
document.addEventListener('DOMContentLoaded', () => {
    console.log('🐛 DOM loaded');
    console.log('🐛 newGameBtn:', document.getElementById('newGameBtn'));
    console.log('🐛 hitBtn:', document.getElementById('hitBtn'));
    console.log('🐛 standBtn:', document.getElementById('standBtn'));
    console.log('🐛 blackjackData:', window.blackjackData);
});
</script>
<script src="js/blackjack.js?v=<?php echo time(); ?>"></script>

<?php 
include '../core/includes/footer.php'; 

// Flush output buffer
ob_end_flush();
?>
</rewritten_file>