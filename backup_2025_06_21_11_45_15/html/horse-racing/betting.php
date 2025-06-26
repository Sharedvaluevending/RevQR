<?php
/**
 * Horse Racing Betting System
 * Comprehensive betting with all traditional horse track betting types
 */

require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/qr_coin_manager.php';

// Require user to be logged in
require_login();

$race_id = intval($_GET['race_id'] ?? 0);
$user_id = $_SESSION['user_id'];
$user_balance = QRCoinManager::getBalance($user_id);

if (!$race_id) {
    header('Location: index.php');
    exit;
}

// Betting type definitions
$bet_types = [
    'win' => [
        'name' => 'Win',
        'description' => 'Horse must finish 1st',
        'min_selections' => 1,
        'max_selections' => 1,
        'min_bet' => 10,
        'base_odds' => 'Variable'
    ],
    'place' => [
        'name' => 'Place',
        'description' => 'Horse must finish 1st or 2nd',
        'min_selections' => 1,
        'max_selections' => 1,
        'min_bet' => 10,
        'base_odds' => '1.8x'
    ],
    'show' => [
        'name' => 'Show',
        'description' => 'Horse must finish 1st, 2nd, or 3rd',
        'min_selections' => 1,
        'max_selections' => 1,
        'min_bet' => 10,
        'base_odds' => '1.4x'
    ],
    'exacta' => [
        'name' => 'Exacta',
        'description' => 'Pick 1st and 2nd in exact order',
        'min_selections' => 2,
        'max_selections' => 2,
        'min_bet' => 20,
        'base_odds' => '8x'
    ],
    'quinella' => [
        'name' => 'Quinella',
        'description' => 'Pick 1st and 2nd in any order',
        'min_selections' => 2,
        'max_selections' => 2,
        'min_bet' => 15,
        'base_odds' => '5x'
    ],
    'trifecta' => [
        'name' => 'Trifecta',
        'description' => 'Pick 1st, 2nd, and 3rd in exact order',
        'min_selections' => 3,
        'max_selections' => 3,
        'min_bet' => 50,
        'base_odds' => '25x'
    ],
    'superfecta' => [
        'name' => 'Superfecta',
        'description' => 'Pick 1st, 2nd, 3rd, and 4th in exact order',
        'min_selections' => 4,
        'max_selections' => 4,
        'min_bet' => 100,
        'base_odds' => '100x'
    ],
    'daily_double' => [
        'name' => 'Daily Double',
        'description' => 'Pick winners of two consecutive races',
        'min_selections' => 1,
        'max_selections' => 1,
        'min_bet' => 25,
        'base_odds' => '12x'
    ]
];

function calculateOdds($bet_type) {
    $odds = [
        'win' => 3.0,
        'place' => 1.8,
        'show' => 1.4,
        'exacta' => 8.0,
        'quinella' => 5.0,
        'trifecta' => 25.0,
        'superfecta' => 100.0,
        'daily_double' => 12.0
    ];
    
    return $odds[$bet_type] ?? 2.0;
}

// Handle bet placement
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bet_type = $_POST['bet_type'] ?? '';
    $amount = intval($_POST['amount'] ?? 0);
    $selections = json_decode($_POST['selections'] ?? '[]', true);
    
    if ($amount > 0 && $amount <= $user_balance && !empty($selections) && isset($bet_types[$bet_type])) {
        try {
            $pdo->beginTransaction();
            
            // Calculate potential winnings based on bet type
            $odds_multiplier = calculateOdds($bet_type);
            $potential_winnings = $amount * $odds_multiplier;
            
            // Deduct QR coins from user
            QRCoinManager::deductBalance($user_id, $amount, 'horse_racing_bet', 
                "Bet on race: $bet_type");
            
            // Insert bet record - UPDATE race_bets table structure if needed
            $stmt = $pdo->prepare("
                INSERT INTO race_bets (race_id, user_id, horse_id, bet_amount_qr_coins, 
                                     potential_winnings, status, bet_placed_at, bet_type, horse_selections)
                VALUES (?, ?, ?, ?, ?, 'pending', NOW(), ?, ?)
            ");
            
            // For simple bets, use first selection as horse_id
            $horse_id = is_array($selections) && !empty($selections) ? $selections[0] : null;
            
            $stmt->execute([
                $race_id, $user_id, $horse_id, $amount, 
                $potential_winnings, $bet_type, json_encode($selections)
            ]);
            
            $pdo->commit();
            $success_message = "Bet placed successfully! Good luck!";
            
        } catch (Exception $e) {
            $pdo->rollback();
            $error_message = "Error placing bet: " . $e->getMessage();
        }
    } else {
        $error_message = "Invalid bet amount or selections.";
    }
}

// Get race details
$stmt = $pdo->prepare("
    SELECT br.*, b.name as business_name,
           CASE 
               WHEN br.start_time <= NOW() AND br.end_time >= NOW() THEN 'LIVE'
               WHEN br.start_time > NOW() THEN 'UPCOMING'
               ELSE 'FINISHED'
           END as race_status,
           TIMESTAMPDIFF(SECOND, NOW(), br.start_time) as time_to_start
    FROM business_races br
    JOIN businesses b ON br.business_id = b.id
    WHERE br.id = ? AND br.status IN ('approved', 'active')
");
$stmt->execute([$race_id]);
$race = $stmt->fetch();

if (!$race || $race['race_status'] !== 'UPCOMING') {
    header('Location: index.php?error=betting_closed');
    exit;
}

// Get horses in this race with custom or default jockey assignments
$stmt = $pdo->prepare("
    SELECT rh.*, 
           COALESCE(vli.item_name, 'Unknown Item') as item_name, 
           COALESCE(vli.retail_price, 0) as retail_price, 
           COALESCE(vli.cost_price, 0) as cost_price,
           COALESCE(ija.custom_jockey_name, ja.jockey_name, 'Wild Card Willie') as jockey_name,
           COALESCE(ija.custom_jockey_avatar_url, ja.jockey_avatar_url, '/horse-racing/assets/img/jockeys/jockey-other.png') as jockey_avatar_url,
           COALESCE(ija.custom_jockey_color, ja.jockey_color, '#6f42c1') as jockey_color,
           COALESCE(hpc.performance_score, 50) as performance_score,
           COALESCE(hpc.units_sold_24h, 0) as sales_24h,
           COALESCE(hpc.profit_per_unit, 0) as profit_margin
    FROM race_horses rh
    JOIN voting_list_items vli ON rh.item_id = vli.id
    LEFT JOIN horse_performance_cache hpc ON vli.id = hpc.item_id 
        AND hpc.cache_date = CURDATE()
    LEFT JOIN item_jockey_assignments ija ON vli.id = ija.item_id 
        AND ija.business_id = (SELECT business_id FROM business_races WHERE id = ?)
    LEFT JOIN jockey_assignments ja ON LOWER(vli.item_category) = ja.item_type
    WHERE rh.race_id = ?
    ORDER BY rh.id ASC
");
$stmt->execute([$race_id, $race_id]);
$horses = $stmt->fetchAll();

// Calculate current odds for each horse
foreach ($horses as &$horse) {
    $performance = $horse['performance_score'];
    $base_odds = max(1.2, min(20, (100 - $performance) / 10));
    $horse['current_odds'] = number_format($base_odds, 1);
}

// Get user's existing bets
$stmt = $pdo->prepare("
    SELECT rb.*, rh.horse_name 
    FROM race_bets rb 
    LEFT JOIN race_horses rh ON rb.horse_id = rh.id
    WHERE rb.user_id = ? AND rb.race_id = ? 
    ORDER BY rb.bet_placed_at DESC
");
$stmt->execute([$user_id, $race_id]);
$user_bets = $stmt->fetchAll();

require_once __DIR__ . '/../core/includes/header.php';
?>

<style>
.betting-header {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    color: white;
    padding: 2rem 0;
    margin-bottom: 2rem;
}

.horse-card {
    background: rgba(255, 255, 255, 0.12) !important;
    backdrop-filter: blur(20px) !important;
    border: 1px solid rgba(255, 255, 255, 0.15) !important;
    border-radius: 16px !important;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3) !important;
    transition: all 0.3s ease;
    color: #ffffff;
    cursor: pointer;
}

.horse-card:hover {
    transform: translateY(-4px) !important;
    box-shadow: 0 15px 40px rgba(0, 0, 0, 0.4) !important;
    border: 1px solid rgba(255, 255, 255, 0.25) !important;
}

.horse-card.selected {
    border: 2px solid #28a745 !important;
    background: rgba(40, 167, 69, 0.2) !important;
}

.bet-type-card {
    background: rgba(255, 255, 255, 0.12) !important;
    backdrop-filter: blur(20px) !important;
    border: 1px solid rgba(255, 255, 255, 0.15) !important;
    border-radius: 16px !important;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3) !important;
    transition: all 0.3s ease;
    color: #ffffff;
    cursor: pointer;
}

.bet-type-card:hover {
    transform: translateY(-2px);
    border: 1px solid rgba(255, 255, 255, 0.25) !important;
}

.bet-type-card.active {
    border: 2px solid #007bff !important;
    background: rgba(0, 123, 255, 0.2) !important;
}

.betting-form {
    background: rgba(255, 255, 255, 0.12) !important;
    backdrop-filter: blur(20px) !important;
    border: 1px solid rgba(255, 255, 255, 0.15) !important;
    border-radius: 16px !important;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3) !important;
    color: #ffffff;
}

.form-control {
    background: rgba(255, 255, 255, 0.1) !important;
    border: 1px solid rgba(255, 255, 255, 0.2) !important;
    color: #ffffff !important;
    border-radius: 8px !important;
}

.form-control:focus {
    background: rgba(255, 255, 255, 0.15) !important;
    border: 1px solid rgba(0, 123, 255, 0.5) !important;
    box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25) !important;
    color: #ffffff !important;
}

.form-control::placeholder {
    color: rgba(255, 255, 255, 0.6) !important;
}

.bet-history {
    background: rgba(255, 255, 255, 0.12) !important;
    backdrop-filter: blur(20px) !important;
    border: 1px solid rgba(255, 255, 255, 0.15) !important;
    border-radius: 16px !important;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3) !important;
    color: #ffffff;
}

.betting-history-table {
    background: rgba(255, 255, 255, 0.12) !important;
    backdrop-filter: blur(20px) !important;
    border: 1px solid rgba(255, 255, 255, 0.15) !important;
    border-radius: 12px !important;
    color: #ffffff;
}

.betting-history-table th {
    background: rgba(255, 255, 255, 0.1) !important;
    border: none !important;
    color: #ffffff !important;
    font-weight: 600;
}

.betting-history-table td {
    background: transparent !important;
    border: 1px solid rgba(255, 255, 255, 0.1) !important;
    color: #ffffff !important;
}

.odds-display {
    background: linear-gradient(45deg, #ffd700, #ffed4a);
    color: #333;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-weight: bold;
    font-size: 0.9rem;
}

.performance-bar {
    height: 8px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 4px;
    overflow: hidden;
}

.performance-fill {
    height: 100%;
    background: linear-gradient(45deg, #28a745, #20c997);
    transition: width 1s ease;
}

.bet-slip {
    position: sticky;
    top: 20px;
}

.selection-order {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 24px;
    height: 24px;
    background: #28a745;
    color: white;
    border-radius: 50%;
    font-size: 0.8rem;
    font-weight: bold;
    margin-left: 8px;
}
</style>

<div class="betting-header text-center">
    <div class="container">
        <h1 class="display-4 mb-3">🎯 Betting Arena</h1>
        <p class="lead"><?php echo htmlspecialchars($race['race_name']); ?></p>
        <div class="d-inline-flex align-items-center gap-3">
            <div class="d-flex align-items-center">
                <img src="../img/qrCoin.png" alt="QR Coin" style="width: 20px; height: 20px;" class="me-2">
                Your Balance: <?php echo number_format($user_balance); ?> QR Coins
            </div>
            <div class="badge bg-warning text-dark p-2">
                Race starts in: <span id="countdown"><?php echo gmdate("H:i:s", $race['time_to_start']); ?></span>
            </div>
        </div>
    </div>
</div>

<div class="container">
    <?php if (isset($success_message)): ?>
        <div class="alert alert-success alert-dismissible fade show" style="background: rgba(40, 167, 69, 0.2) !important; backdrop-filter: blur(10px) !important; border: 1px solid rgba(40, 167, 69, 0.3) !important; color: #fff !important;">
            <i class="bi bi-check-circle"></i> <?php echo $success_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show" style="background: rgba(220, 53, 69, 0.2) !important; backdrop-filter: blur(10px) !important; border: 1px solid rgba(220, 53, 69, 0.3) !important; color: #fff !important;">
            <i class="bi bi-exclamation-triangle"></i> <?php echo $error_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Betting Types -->
    <div class="row mb-4">
        <div class="col-md-12">
            <h3 style="color: #fff;" class="mb-4">📋 Select Betting Type</h3>
            <div class="row g-3">
                <?php foreach ($bet_types as $type_key => $type_info): ?>
                    <div class="col-md-3 col-sm-6">
                        <div class="bet-type-card p-3 text-center" data-bet-type="<?php echo $type_key; ?>">
                            <h5 class="mb-2"><?php echo $type_info['name']; ?></h5>
                            <p class="small mb-2"><?php echo $type_info['description']; ?></p>
                            <div class="d-flex justify-content-between align-items-center">
                                <small>Min: <?php echo $type_info['min_bet']; ?> coins</small>
                                <span class="odds-display"><?php echo $type_info['base_odds']; ?></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Horse Selection -->
        <div class="col-lg-8">
            <h3 style="color: #fff;" class="mb-4">🐎 Select Your Horses</h3>
            <div id="bet-instructions" class="alert alert-info mb-4" style="background: rgba(13, 202, 240, 0.2) !important; backdrop-filter: blur(10px) !important; border: 1px solid rgba(13, 202, 240, 0.3) !important; color: #fff !important;">
                <i class="bi bi-info-circle"></i> Please select a betting type first.
            </div>
            
            <div class="row g-3" id="horse-grid">
                <?php foreach ($horses as $index => $horse): ?>
                    <div class="col-md-6">
                        <div class="horse-card p-3" data-horse-id="<?php echo $horse['id']; ?>" data-horse-name="<?php echo htmlspecialchars($horse['horse_name']); ?>">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <h5 class="mb-1">
                                        <?php echo htmlspecialchars($horse['horse_name']); ?>
                                        <span class="selection-order" style="display: none;"></span>
                                    </h5>
                                    <small class="text-muted">
                                        Jockey: <?php echo htmlspecialchars($horse['jockey_name'] ?? 'TBD'); ?>
                                    </small>
                                </div>
                                <div class="text-end">
                                    <div class="odds-display mb-2"><?php echo $horse['current_odds']; ?>:1</div>
                                    <small>Position <?php echo $index + 1; ?></small>
                                </div>
                            </div>
                            
                            <div class="row mb-2">
                                <div class="col-6">
                                    <small>24h Sales</small>
                                    <div class="fw-bold"><?php echo $horse['sales_24h']; ?> units</div>
                                </div>
                                <div class="col-6">
                                    <small>Performance</small>
                                    <div class="fw-bold"><?php echo round($horse['performance_score']); ?>%</div>
                                </div>
                            </div>
                            
                            <div class="performance-bar">
                                <div class="performance-fill" 
                                     style="width: <?php echo min(100, $horse['performance_score']); ?>%"></div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Bet Slip -->
        <div class="col-lg-4">
            <div class="bet-slip">
                <div class="betting-form p-4">
                    <h4 class="mb-4">🎫 Bet Slip</h4>
                    
                    <form id="betting-form" method="POST" style="display: none;">
                        <input type="hidden" name="bet_type" id="selected-bet-type">
                        <input type="hidden" name="selections" id="selected-horses">
                        
                        <div class="mb-3">
                            <label class="form-label">Bet Type</label>
                            <div id="bet-type-display" class="form-control-plaintext text-white">
                                Select a bet type
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Selected Horses</label>
                            <div id="selections-display" class="form-control-plaintext text-white">
                                No selections
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="bet-amount" class="form-label">Bet Amount (QR Coins)</label>
                            <input type="number" class="form-control" id="bet-amount" name="amount" 
                                   min="1" max="<?php echo $user_balance; ?>" placeholder="Enter amount">
                            <div class="form-text text-white-50">
                                Balance: <?php echo number_format($user_balance); ?> coins
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="d-flex justify-content-between">
                                <span>Potential Winnings:</span>
                                <span id="potential-winnings" class="fw-bold text-warning">-</span>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-success w-100" id="place-bet-btn" disabled>
                            <i class="bi bi-trophy"></i> Place Bet
                        </button>
                    </form>
                    
                    <div id="bet-placeholder" class="text-center py-4">
                        <i class="bi bi-ticket display-1 text-muted mb-3"></i>
                        <p class="text-muted">Select a betting type and horses to build your bet slip.</p>
                    </div>
                </div>

                <!-- Quick Bet Amounts -->
                <div class="betting-form p-3 mt-3">
                    <h6 class="mb-3">⚡ Quick Amounts</h6>
                    <div class="d-grid gap-2">
                        <button class="btn btn-outline-light btn-sm quick-bet" data-amount="10">10 Coins</button>
                        <button class="btn btn-outline-light btn-sm quick-bet" data-amount="25">25 Coins</button>
                        <button class="btn btn-outline-light btn-sm quick-bet" data-amount="50">50 Coins</button>
                        <button class="btn btn-outline-light btn-sm quick-bet" data-amount="100">100 Coins</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

                <!-- Betting History -->
    <?php if (!empty($user_bets)): ?>
        <div class="row mt-5">
            <div class="col-12">
                <div class="bet-history p-4">
                    <h4 class="mb-4">📊 Your Bets on This Race</h4>
                    <div class="table-responsive">
                        <table class="table betting-history-table mb-0">
                            <thead>
                                <tr>
                                    <th>Bet Type</th>
                                    <th>Horse</th>
                                    <th>Amount</th>
                                    <th>Potential Win</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($user_bets as $bet): ?>
                                    <tr>
                                        <td><span class="badge bg-secondary"><?php echo ucfirst($bet['bet_type'] ?? 'Win'); ?></span></td>
                                        <td><?php echo htmlspecialchars($bet['horse_name'] ?? 'Horse Selection'); ?></td>
                                        <td><?php echo number_format($bet['bet_amount_qr_coins']); ?> coins</td>
                                        <td class="text-warning"><?php echo number_format($bet['potential_winnings']); ?> coins</td>
                                        <td><span class="badge bg-warning">Pending</span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
// Betting System JavaScript
let currentBetType = null;
let selectedHorses = [];
let betTypeInfo = <?php echo json_encode($bet_types); ?>;
let maxSelections = 0;
let minSelections = 0;
let minBet = 0;

// Bet type selection
document.querySelectorAll('.bet-type-card').forEach(card => {
    card.addEventListener('click', function() {
        // Remove active class from all cards
        document.querySelectorAll('.bet-type-card').forEach(c => c.classList.remove('active'));
        
        // Add active class to selected card
        this.classList.add('active');
        
        // Update current bet type
        currentBetType = this.dataset.betType;
        const typeInfo = betTypeInfo[currentBetType];
        
        maxSelections = typeInfo.max_selections;
        minSelections = typeInfo.min_selections;
        minBet = typeInfo.min_bet;
        
        // Update UI
        document.getElementById('selected-bet-type').value = currentBetType;
        document.getElementById('bet-type-display').textContent = typeInfo.name;
        document.getElementById('bet-amount').min = minBet;
        document.getElementById('bet-amount').placeholder = `Min: ${minBet} coins`;
        
        // Update instructions
        let instructions = `Select ${minSelections}`;
        if (maxSelections > minSelections) {
            instructions += ` to ${maxSelections}`;
        }
        instructions += ` horse${maxSelections > 1 ? 's' : ''}`;
        
        if (currentBetType === 'exacta' || currentBetType === 'trifecta' || currentBetType === 'superfecta') {
            instructions += ' in order of finish';
        }
        
        document.getElementById('bet-instructions').innerHTML = 
            `<i class="bi bi-info-circle"></i> ${instructions}. ${typeInfo.description}`;
        
        // Reset selections
        selectedHorses = [];
        updateHorseSelection();
        updateBetSlip();
    });
});

// Horse selection
document.querySelectorAll('.horse-card').forEach(card => {
    card.addEventListener('click', function() {
        if (!currentBetType) {
            alert('Please select a betting type first!');
            return;
        }
        
        const horseId = this.dataset.horseId;
        const horseName = this.dataset.horseName;
        
        // Check if horse is already selected
        const existingIndex = selectedHorses.findIndex(h => h.id === horseId);
        
        if (existingIndex !== -1) {
            // Remove horse if already selected
            selectedHorses.splice(existingIndex, 1);
        } else {
            // Add horse if not at max selections
            if (selectedHorses.length < maxSelections) {
                selectedHorses.push({id: horseId, name: horseName});
            } else {
                // For single selection bets, replace
                if (maxSelections === 1) {
                    selectedHorses = [{id: horseId, name: horseName}];
                } else {
                    alert(`You can only select ${maxSelections} horses for this bet type.`);
                    return;
                }
            }
        }
        
        updateHorseSelection();
        updateBetSlip();
    });
});

// Update horse selection display
function updateHorseSelection() {
    document.querySelectorAll('.horse-card').forEach(card => {
        const horseId = card.dataset.horseId;
        const selectionOrder = card.querySelector('.selection-order');
        const index = selectedHorses.findIndex(h => h.id === horseId);
        
        if (index !== -1) {
            card.classList.add('selected');
            selectionOrder.textContent = index + 1;
            selectionOrder.style.display = 'inline-flex';
        } else {
            card.classList.remove('selected');
            selectionOrder.style.display = 'none';
        }
    });
}

// Update bet slip
function updateBetSlip() {
    const form = document.getElementById('betting-form');
    const placeholder = document.getElementById('bet-placeholder');
    
    if (currentBetType && selectedHorses.length >= minSelections) {
        form.style.display = 'block';
        placeholder.style.display = 'none';
        
        // Update selections display
        let selectionsText = selectedHorses.map((horse, index) => {
            if (currentBetType === 'exacta' || currentBetType === 'trifecta' || currentBetType === 'superfecta') {
                return `${index + 1}. ${horse.name}`;
            }
            return horse.name;
        }).join(', ');
        
        document.getElementById('selections-display').textContent = selectionsText;
        document.getElementById('selected-horses').value = JSON.stringify(selectedHorses.map(h => h.id));
        
        updatePotentialWinnings();
        updatePlaceBetButton();
    } else {
        form.style.display = 'none';
        placeholder.style.display = 'block';
    }
}

// Update potential winnings
function updatePotentialWinnings() {
    const amount = parseInt(document.getElementById('bet-amount').value) || 0;
    const multiplier = calculateOddsMultiplier(currentBetType);
    const winnings = amount * multiplier;
    
    document.getElementById('potential-winnings').textContent = 
        amount > 0 ? `${winnings.toLocaleString()} coins` : '-';
}

// Calculate odds multiplier for JavaScript
function calculateOddsMultiplier(betType) {
    const odds = {
        'win': 3.0,
        'place': 1.8,
        'show': 1.4,
        'exacta': 8.0,
        'quinella': 5.0,
        'trifecta': 25.0,
        'superfecta': 100.0,
        'daily_double': 12.0
    };
    return odds[betType] || 2.0;
}

// Update place bet button
function updatePlaceBetButton() {
    const amount = parseInt(document.getElementById('bet-amount').value) || 0;
    const button = document.getElementById('place-bet-btn');
    
    const isValid = currentBetType && 
                   selectedHorses.length >= minSelections && 
                   selectedHorses.length <= maxSelections &&
                   amount >= minBet &&
                   amount <= <?php echo $user_balance; ?>;
    
    button.disabled = !isValid;
}

// Event listeners
document.getElementById('bet-amount').addEventListener('input', function() {
    updatePotentialWinnings();
    updatePlaceBetButton();
});

// Quick bet amounts
document.querySelectorAll('.quick-bet').forEach(btn => {
    btn.addEventListener('click', function() {
        document.getElementById('bet-amount').value = this.dataset.amount;
        updatePotentialWinnings();
        updatePlaceBetButton();
    });
});

// Countdown timer
let timeRemaining = <?php echo $race['time_to_start']; ?>;
const countdownElement = document.getElementById('countdown');

setInterval(function() {
    if (timeRemaining > 0) {
        timeRemaining--;
        
        const hours = Math.floor(timeRemaining / 3600);
        const minutes = Math.floor((timeRemaining % 3600) / 60);
        const seconds = timeRemaining % 60;
        
        countdownElement.textContent = 
            `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
    } else {
        window.location.href = 'index.php?error=betting_closed';
    }
}, 1000);

// Form validation
document.getElementById('betting-form').addEventListener('submit', function(e) {
    const amount = parseInt(document.getElementById('bet-amount').value) || 0;
    
    if (amount < minBet) {
        e.preventDefault();
        alert(`Minimum bet for ${currentBetType} is ${minBet} coins.`);
        return;
    }
    
    if (amount > <?php echo $user_balance; ?>) {
        e.preventDefault();
        alert('Insufficient balance!');
        return;
    }
    
    if (selectedHorses.length < minSelections || selectedHorses.length > maxSelections) {
        e.preventDefault();
        alert(`Please select ${minSelections}${maxSelections > minSelections ? ` to ${maxSelections}` : ''} horses.`);
        return;
    }
    
    return confirm(`Place ${currentBetType} bet of ${amount} coins?`);
});
</script>

<?php require_once __DIR__ . '/../core/includes/footer.php'; ?> 