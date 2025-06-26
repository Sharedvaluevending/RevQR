<?php
/**
 * Simple Blackjack Test Page - No Authentication Required
 * This helps diagnose the blank page issue
 */

// Basic error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Only include essential config
try {
    require_once __DIR__ . '/../core/config.php';
} catch (Exception $e) {
    die("Config error: " . $e->getMessage());
}

// Mock user data for testing
$current_balance = 1000;
$business_id = 1;
$business_name = "Test Casino";
$app_url = APP_URL;
$jackpot_multiplier = 50;
$min_bet = 1;
$max_bet = 100;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simple Blackjack Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
</head>
<body style="background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%); min-height: 100vh;">

<div class="container py-4">
    <div class="alert alert-info">
        <h4>🧪 Simple Blackjack Test</h4>
        <p>This is a test version to diagnose the blank page issue. No login required.</p>
    </div>

    <div class="row justify-content-center">
        <div class="col-lg-10">
            <!-- Header -->
            <div class="card bg-dark text-white mb-4">
                <div class="card-body text-center py-4">
                    <h1 class="mb-3">🃏 Simple QR Blackjack 🃏</h1>
                    <div class="d-flex justify-content-center align-items-center gap-4">
                        <span class="badge bg-success fs-5 px-4 py-2">
                            <i class="bi bi-coin me-2"></i>Balance: <span id="userBalance"><?php echo number_format($current_balance); ?></span> QR Coins
                        </span>
                    </div>
                </div>
            </div>

            <!-- Game Area -->
            <div class="row">
                <div class="col-lg-8">
                    <div class="card bg-success text-white">
                        <div class="card-header text-center">
                            <h4>🎰 Blackjack Table 🎰</h4>
                        </div>
                        <div class="card-body">
                            <!-- Dealer Area -->
                            <div class="dealer-area mb-4 p-3 bg-dark rounded">
                                <div class="text-center mb-3">
                                    <h5>🎩 Dealer</h5>
                                    <div>Score: <span id="dealerScore" class="badge bg-warning text-dark">0</span></div>
                                </div>
                                <div class="dealer-cards d-flex justify-content-center gap-2" id="dealerCards">
                                    <!-- Dealer cards -->
                                </div>
                            </div>

                            <!-- Player Area -->
                            <div class="player-area mb-4 p-3 bg-dark rounded">
                                <div class="text-center mb-3">
                                    <h5>🎯 Your Hand</h5>
                                    <div>Score: <span id="playerScore" class="badge bg-info">0</span></div>
                                </div>
                                <div class="player-cards d-flex justify-content-center gap-2" id="playerCards">
                                    <!-- Player cards -->
                                </div>
                            </div>

                            <!-- Game Controls -->
                            <div class="text-center">
                                <button type="button" class="btn btn-warning btn-lg me-2" id="hitBtn" disabled>
                                    <i class="bi bi-plus-circle me-2"></i>Hit
                                </button>
                                <button type="button" class="btn btn-danger btn-lg me-2" id="standBtn" disabled>
                                    <i class="bi bi-hand-thumbs-up me-2"></i>Stand
                                </button>
                                <button type="button" class="btn btn-primary btn-lg" id="newGameBtn">
                                    <i class="bi bi-arrow-clockwise me-2"></i>New Game
                                </button>
                            </div>

                            <!-- Game Status -->
                            <div class="text-center mt-4" id="gameStatus">
                                <h4>Click "New Game" to start playing!</h4>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Betting Panel -->
                <div class="col-lg-4">
                    <div class="card bg-dark text-white">
                        <div class="card-header text-center">
                            <h5><i class="bi bi-currency-exchange me-2"></i>Betting</h5>
                        </div>
                        <div class="card-body">
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
                            </div>
                            <div class="current-bet mt-3 p-2 bg-success rounded text-center">
                                <small>Current Bet:</small><br>
                                <strong id="currentBet">1 QR Coin</strong>
                            </div>
                        </div>
                    </div>

                    <!-- Test Info -->
                    <div class="card bg-info text-white mt-3">
                        <div class="card-header text-center">
                            <h6>🧪 Test Mode</h6>
                        </div>
                        <div class="card-body">
                            <small>
                                <ul class="list-unstyled mb-0">
                                    <li>✅ No login required</li>
                                    <li>✅ Mock balance: 1000 coins</li>
                                    <li>✅ All game features active</li>
                                    <li>✅ Helps diagnose issues</li>
                                </ul>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript Data -->
<script>
window.blackjackData = {
    userBalance: <?php echo $current_balance; ?>,
    businessId: <?php echo $business_id; ?>,
    jackpotMultiplier: <?php echo $jackpot_multiplier; ?>,
    minBet: <?php echo $min_bet; ?>,
    maxBet: <?php echo $max_bet; ?>,
    appUrl: '<?php echo $app_url; ?>'
};

// Simple console log for debugging
console.log('🃏 Simple Blackjack Test Loaded');
console.log('Blackjack Data:', window.blackjackData);
</script>

<!-- Card Styles -->
<style>
.card-element {
    width: 70px;
    height: 100px;
    border-radius: 8px;
    border: 2px solid #ffd700;
    background: white;
    display: inline-flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    margin: 2px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.3);
    transition: transform 0.2s;
}

.card-element:hover {
    transform: translateY(-2px);
}

.card-back {
    background: linear-gradient(135deg, #1a1a1a, #2d2d2d);
    color: white;
}

.card-face {
    background: white;
    color: #333;
}

.card-face.red-suit {
    color: #dc3545;
}

.card-value {
    font-size: 1rem;
    font-weight: bold;
}

.card-suit {
    font-size: 1.2rem;
}
</style>

<!-- Try to load the main blackjack JavaScript -->
<script>
try {
    // Test if we can access the blackjack JavaScript
    console.log('🔍 Testing JavaScript load...');
} catch (error) {
    console.error('❌ JavaScript error:', error);
    alert('JavaScript loading failed: ' + error.message);
}
</script>

<script src="js/blackjack.js"></script>

<script>
// Fallback if main script fails
setTimeout(function() {
    if (typeof QRBlackjack === 'undefined') {
        console.warn('⚠️ Main blackjack script failed to load, using fallback');
        document.getElementById('gameStatus').innerHTML = '<div class="alert alert-warning">⚠️ JavaScript failed to load. Check browser console for errors.</div>';
    } else {
        console.log('✅ Blackjack script loaded successfully');
    }
}, 1000);
</script>

</body>
</html> 