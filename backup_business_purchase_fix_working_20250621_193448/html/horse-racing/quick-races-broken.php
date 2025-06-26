<?php
/**
 * Enhanced Quick Races - 10 Horses with Full Betting System
 * Win, Place, Show, Exacta, Quinella, Trifecta, Superfecta
 */

require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/qr_coin_manager.php';

$user_id = $_SESSION['user_id'] ?? null;

// Load enhanced configuration
$config_file = __DIR__ . '/quick_race_config.json';
if (file_exists($config_file)) {
    $config = json_decode(file_get_contents($config_file), true);
    $quick_race_horses = $config['horses'];
    $betting_odds = $config['betting_odds'];
} else {
    // Fallback to default 10 horses
    $quick_race_horses = [
        ['horse_name' => 'Thunder Bolt', 'jockey_name' => 'Lightning Larry', 'jockey_image' => '/horse-racing/assets/img/jockeys/bluejokeybluehorse.png', 'jockey_color' => '#007bff', 'specialty' => 'Speed demon', 'base_speed' => 85],
        ['horse_name' => 'Golden Arrow', 'jockey_name' => 'Swift Sarah', 'jockey_image' => '/horse-racing/assets/img/jockeys/brownjokeybrownhorse.png', 'jockey_color' => '#8B4513', 'specialty' => 'Consistent', 'base_speed' => 82],
        ['horse_name' => 'Emerald Flash', 'jockey_name' => 'Speedy Steve', 'jockey_image' => '/horse-racing/assets/img/jockeys/greenjokeybluehorse.png', 'jockey_color' => '#28a745', 'specialty' => 'Strong finisher', 'base_speed' => 88],
        ['horse_name' => 'Crimson Comet', 'jockey_name' => 'Rapid Rita', 'jockey_image' => '/horse-racing/assets/img/jockeys/redjockeybrownhorse.png', 'jockey_color' => '#dc3545', 'specialty' => 'Early leader', 'base_speed' => 90],
        ['horse_name' => 'Sunset Streak', 'jockey_name' => 'Turbo Tom', 'jockey_image' => '/horse-racing/assets/img/jockeys/greenjokeyorangehorse.png', 'jockey_color' => '#fd7e14', 'specialty' => 'Clutch performer', 'base_speed' => 86],
        ['horse_name' => 'Midnight Storm', 'jockey_name' => 'Flash Fiona', 'jockey_image' => '/horse-racing/assets/img/jockeys/bluejokeybluehorse.png', 'jockey_color' => '#6f42c1', 'specialty' => 'Night specialist', 'base_speed' => 84],
        ['horse_name' => 'Silver Bullet', 'jockey_name' => 'Quick Quinn', 'jockey_image' => '/horse-racing/assets/img/jockeys/brownjokeybluehorse.png', 'jockey_color' => '#6c757d', 'specialty' => 'Surprise finisher', 'base_speed' => 87],
        ['horse_name' => 'Royal Thunder', 'jockey_name' => 'Dynamic Dan', 'jockey_image' => '/horse-racing/assets/img/jockeys/redjokeyorangehorse.png', 'jockey_color' => '#e83e8c', 'specialty' => 'Track champion', 'base_speed' => 89],
        ['horse_name' => 'Diamond Dash', 'jockey_name' => 'Velocity Val', 'jockey_image' => '/horse-racing/assets/img/jockeys/greenjokeybluehorse.png', 'jockey_color' => '#17a2b8', 'specialty' => 'Distance runner', 'base_speed' => 83],
        ['horse_name' => 'Phoenix Fire', 'jockey_name' => 'Blazing Bob', 'jockey_image' => '/horse-racing/assets/img/jockeys/redjockeybrownhorse.png', 'jockey_color' => '#fd7e14', 'specialty' => 'Comeback king', 'base_speed' => 91]
    ];
    
    $betting_odds = [
        'win' => ['min' => 1.5, 'max' => 15.0, 'description' => 'Horse must finish 1st'],
        'place' => ['min' => 1.2, 'max' => 3.5, 'description' => 'Horse must finish 1st or 2nd'],
        'show' => ['min' => 1.1, 'max' => 2.5, 'description' => 'Horse must finish 1st, 2nd, or 3rd'],
        'exacta' => ['min' => 5.0, 'max' => 50.0, 'description' => 'Pick 1st and 2nd in exact order'],
        'quinella' => ['min' => 3.0, 'max' => 25.0, 'description' => 'Pick 1st and 2nd in any order'],
        'trifecta' => ['min' => 10.0, 'max' => 200.0, 'description' => 'Pick 1st, 2nd, and 3rd in exact order'],
        'superfecta' => ['min' => 50.0, 'max' => 1000.0, 'description' => 'Pick 1st, 2nd, 3rd, and 4th in exact order']
    ];
}

// Quick Race Schedule
$race_schedule = [
    ['time' => '09:35:00', 'name' => 'Morning Sprint', 'description' => 'Start your day with excitement!'],
    ['time' => '12:00:00', 'name' => 'Lunch Rush', 'description' => 'Midday racing action!'],
    ['time' => '18:10:00', 'name' => 'Evening Thunder', 'description' => 'After-work entertainment!'],
    ['time' => '21:05:00', 'name' => 'Night Lightning', 'description' => 'Prime time racing!'],
    ['time' => '02:10:00', 'name' => 'Midnight Express', 'description' => 'Late night thrills!'],
    ['time' => '05:10:00', 'name' => 'Dawn Dash', 'description' => 'Early bird special!']
];

// Calculate dynamic odds for each horse
foreach ($quick_race_horses as $index => &$horse) {
    $base_odds = 2.0 + ($horse['base_speed'] / 100) + (rand(-50, 50) / 100);
    $horse['win_odds'] = max(1.5, min(15.0, $base_odds));
    $horse['place_odds'] = max(1.2, min(3.5, $base_odds * 0.6));
    $horse['show_odds'] = max(1.1, min(2.5, $base_odds * 0.4));
}

// Get current time and race status
$current_time = new DateTime();
$current_date = $current_time->format('Y-m-d');

// Find current/next race
$current_race = null;
$next_race = null;
$race_status = 'waiting';

foreach ($race_schedule as $index => $race) {
    $race_datetime = new DateTime($current_date . ' ' . $race['time']);
    $race_end = clone $race_datetime;
    $race_end->add(new DateInterval('PT1M'));
    
    if ($current_time >= $race_datetime && $current_time <= $race_end) {
        $current_race = array_merge($race, ['index' => $index, 'start_time' => $race_datetime, 'end_time' => $race_end]);
        $race_status = 'live';
        break;
    } elseif ($current_time < $race_datetime) {
        $next_race = array_merge($race, ['index' => $index, 'start_time' => $race_datetime, 'end_time' => $race_end]);
        break;
    }
}

// Handle enhanced betting
$message = '';
$message_type = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_bet']) && $user_id) {
    $bet_type = $_POST['bet_type'] ?? 'win';
    $bet_amount = (int)$_POST['bet_amount'];
    $race_index = (int)$_POST['race_index'];
    $horse_selections = json_decode($_POST['horse_selections'] ?? '[]', true);
    
    if (!empty($horse_selections) && $bet_amount > 0 && $bet_amount <= 500) {
        // Validate selections based on bet type
        $valid_bet = false;
        switch ($bet_type) {
            case 'win':
            case 'place':
            case 'show':
                $valid_bet = count($horse_selections) == 1;
                break;
            case 'exacta':
            case 'quinella':
                $valid_bet = count($horse_selections) == 2;
                break;
            case 'trifecta':
                $valid_bet = count($horse_selections) == 3;
                break;
            case 'superfecta':
                $valid_bet = count($horse_selections) == 4;
                break;
        }
        
        if ($valid_bet) {
            // Check if user already bet on this race
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM quick_race_bets 
                WHERE user_id = ? AND race_date = ? AND race_index = ?
            ");
            $stmt->execute([$user_id, $current_date, $race_index]);
            
            if ($stmt->fetchColumn() == 0) {
                // Calculate odds multiplier
                $odds_range = $betting_odds[$bet_type];
                $multiplier = $odds_range['min'] + (rand(0, 100) / 100) * ($odds_range['max'] - $odds_range['min']);
                $potential_winnings = (int)($bet_amount * $multiplier);
                
                // Check user balance
                $user_balance = QRCoinManager::getBalance($user_id);
                if ($user_balance >= $bet_amount) {
                    try {
                        $pdo->beginTransaction();
                        
                        // Deduct coins
                        QRCoinManager::addTransaction(
                            $user_id,
                            'spending',
                            'quick_race_bet',
                            -$bet_amount,
                            "Quick race bet: {$bet_type}",
                            ['race_index' => $race_index, 'bet_type' => $bet_type]
                        );
                        
                        // Place the bet
                        $stmt = $pdo->prepare("
                            INSERT INTO quick_race_bets 
                            (user_id, race_date, race_index, horse_index, horse_name, jockey_name, 
                             bet_amount, potential_winnings, bet_type, horse_selections, odds_multiplier)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        
                        $primary_horse = $horse_selections[0];
                        $stmt->execute([
                            $user_id, $current_date, $race_index, $primary_horse,
                            $quick_race_horses[$primary_horse]['horse_name'],
                            $quick_race_horses[$primary_horse]['jockey_name'],
                            $bet_amount, $potential_winnings, $bet_type,
                            json_encode($horse_selections), $multiplier
                        ]);
                        
                        $pdo->commit();
                        $message = "Bet placed successfully! Good luck with your {$bet_type} bet!";
                        $message_type = 'success';
                    } catch (Exception $e) {
                        $pdo->rollback();
                        $message = "Error placing bet: " . $e->getMessage();
                        $message_type = 'danger';
                    }
                } else {
                    $message = "Insufficient QR coins!";
                    $message_type = 'danger';
                }
            } else {
                $message = "You've already bet on this race!";
                $message_type = 'warning';
            }
        } else {
            $message = "Invalid number of horses selected for {$bet_type} bet!";
            $message_type = 'danger';
        }
    }
}

// Get user's balance and bets
$user_balance = 0;
$user_bets = [];
if ($user_id) {
    $user_balance = QRCoinManager::getBalance($user_id);
    
    $stmt = $pdo->prepare("
        SELECT * FROM quick_race_bets 
        WHERE user_id = ? AND race_date = ?
        ORDER BY race_index ASC
    ");
    $stmt->execute([$user_id, $current_date]);
    $user_bets = $stmt->fetchAll();
}

require_once __DIR__ . '/../core/includes/header.php';
?>

<style>
.quick-race-container {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: 100vh;
    padding: 2rem 0;
}

.race-card {
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 20px;
    color: white;
    margin-bottom: 2rem;
}

.betting-type-card {
    background: rgba(255, 255, 255, 0.1);
    border: 2px solid rgba(255, 255, 255, 0.2);
    border-radius: 15px;
    padding: 1rem;
    margin-bottom: 1rem;
    cursor: pointer;
    transition: all 0.3s ease;
}

.betting-type-card:hover {
    background: rgba(255, 255, 255, 0.2);
    transform: translateY(-2px);
}

.betting-type-card.selected {
    border-color: #ffc107;
    background: rgba(255, 193, 7, 0.2);
}

.horse-card {
    background: rgba(255, 255, 255, 0.1);
    border: 2px solid rgba(255, 255, 255, 0.2);
    border-radius: 15px;
    padding: 1rem;
    margin-bottom: 1rem;
    cursor: pointer;
    transition: all 0.3s ease;
    position: relative;
}

.horse-card:hover {
    background: rgba(255, 255, 255, 0.2);
    transform: translateY(-2px);
}

.horse-card.selected {
    border-color: #28a745;
    background: rgba(40, 167, 69, 0.2);
}

.selection-number {
    position: absolute;
    top: -10px;
    right: -10px;
    background: #28a745;
    color: white;
    border-radius: 50%;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    display: none;
}

.horse-card.selected .selection-number {
    display: flex;
}

.jockey-avatar {
    width: 60px;
    height: 60px;
    background-size: cover;
    background-position: center;
    border-radius: 50%;
    border: 3px solid;
    margin: 0 auto 1rem;
}

.odds-display {
    background: rgba(0, 0, 0, 0.3);
    border-radius: 8px;
    padding: 0.5rem;
    text-align: center;
    margin-top: 0.5rem;
}
</style>

<div class="quick-race-container">
    <div class="container">
        <!-- Header -->
        <div class="text-center mb-4">
            <h1>⚡ Enhanced Quick Races</h1>
            <p class="lead">10 Horses • Full Betting System • 6 Daily Races</p>
            <?php if ($user_id): ?>
                <div class="d-inline-flex align-items-center bg-dark text-white px-3 py-2 rounded-pill">
                    <img src="<?php echo APP_URL; ?>/img/qrCoin.png" alt="QR Coin" style="width: 1.5rem; height: 1.5rem;" class="me-2">
                    <span><?php echo number_format($user_balance); ?> QR Coins</span>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?> text-center">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Next Race Betting -->
        <?php if ($next_race && !$current_race): ?>
            <div class="race-card">
                <div class="card-body">
                    <h3 class="text-center mb-3">🏇 Next Race: <?php echo $next_race['name']; ?></h3>
                    <p class="text-center"><?php echo $next_race['description']; ?></p>
                    
                    <div class="countdown-timer text-center mb-4" id="nextRaceTimer">
                        Race starts in: <span id="timeToStart"></span>
                    </div>

                    <div class="row">
                        <!-- Betting Types -->
                        <div class="col-lg-3">
                            <h5 class="mb-3">💰 Bet Types</h5>
                            <?php foreach ($betting_odds as $type => $odds): ?>
                                <div class="betting-type-card" data-bet-type="<?php echo $type; ?>" onclick="selectBetType('<?php echo $type; ?>')">
                                    <h6><?php echo strtoupper($type); ?></h6>
                                    <small><?php echo $odds['description']; ?></small>
                                    <div class="mt-2">
                                        <span class="badge bg-warning text-dark"><?php echo $odds['min']; ?>x - <?php echo $odds['max']; ?>x</span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Horse Selection -->
                        <div class="col-lg-6">
                            <h5 class="mb-3">🐎 Select Horses</h5>
                            <div id="betting-instructions" class="alert alert-info mb-3">
                                <i class="bi bi-info-circle"></i> Please select a betting type first.
                            </div>
                            
                            <div class="row">
                                <?php foreach ($quick_race_horses as $index => $horse): ?>
                                    <div class="col-md-6 col-lg-4">
                                        <div class="horse-card" data-horse-index="<?php echo $index; ?>" onclick="selectHorse(<?php echo $index; ?>)">
                                            <div class="selection-number"></div>
                                            <div class="jockey-avatar" 
                                                 style="background-image: url('<?php echo $horse['jockey_image']; ?>'); 
                                                        border-color: <?php echo $horse['jockey_color']; ?>;">
                                            </div>
                                            <h6 class="text-center mb-1"><?php echo $horse['horse_name']; ?></h6>
                                            <p class="text-center small mb-2"><?php echo $horse['jockey_name']; ?></p>
                                            <small class="text-center d-block text-white-50"><?php echo $horse['specialty']; ?></small>
                                            
                                            <div class="odds-display">
                                                <div class="row text-center">
                                                    <div class="col-4">
                                                        <small>Win</small><br>
                                                        <strong><?php echo number_format($horse['win_odds'], 1); ?>x</strong>
                                                    </div>
                                                    <div class="col-4">
                                                        <small>Place</small><br>
                                                        <strong><?php echo number_format($horse['place_odds'], 1); ?>x</strong>
                                                    </div>
                                                    <div class="col-4">
                                                        <small>Show</small><br>
                                                        <strong><?php echo number_format($horse['show_odds'], 1); ?>x</strong>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Bet Slip -->
                        <div class="col-lg-3">
                            <div class="bet-form" style="background: rgba(0, 0, 0, 0.3); border-radius: 15px; padding: 1.5rem;">
                                <h5 class="mb-3">🎫 Bet Slip</h5>
                                
                                <form method="post" id="betForm" style="display: none;">
                                    <input type="hidden" name="race_index" value="<?php echo $next_race['index']; ?>">
                                    <input type="hidden" name="bet_type" id="selectedBetType" value="">
                                    <input type="hidden" name="horse_selections" id="selectedHorses" value="">
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Bet Type:</label>
                                        <div id="betTypeDisplay" class="text-warning fw-bold">-</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Selections:</label>
                                        <div id="selectionsDisplay" class="text-info">-</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Bet Amount:</label>
                                        <select class="form-select" name="bet_amount" required onchange="updatePotentialWinnings()">
                                            <option value="">Choose amount</option>
                                            <option value="5">5 QR Coins</option>
                                            <option value="10">10 QR Coins</option>
                                            <option value="25">25 QR Coins</option>
                                            <option value="50">50 QR Coins</option>
                                            <option value="100">100 QR Coins</option>
                                            <option value="250">250 QR Coins</option>
                                            <option value="500">500 QR Coins</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <div id="potentialWinnings" class="text-success fw-bold">-</div>
                                    </div>
                                    
                                    <button type="submit" name="place_bet" class="btn btn-warning btn-lg w-100" id="placeBetBtn" disabled>
                                        Place Bet
                                    </button>
                                </form>
                                
                                <div id="betPlaceholder" class="text-center text-white-50">
                                    <p>Select bet type and horses to place a bet</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Current Race (Live) -->
        <?php if ($current_race): ?>
            <div class="race-card" style="background: linear-gradient(45deg, #ff6b6b, #ee5a24); animation: pulse 1s infinite;">
                <div class="card-body">
                    <h3 class="text-center mb-3">🏁 RACE IN PROGRESS!</h3>
                    <h4 class="text-center"><?php echo $current_race['name']; ?></h4>
                    <p class="text-center"><?php echo $current_race['description']; ?></p>
                    
                    <div class="countdown-timer text-center" id="raceTimer">
                        Race ends in: <span id="timeRemaining"></span>
                    </div>
                    
                    <div class="text-center">
                        <p class="mb-0">Betting is closed for this race</p>
                        <small>Results will be available when the race finishes</small>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- User's Today's Bets -->
        <?php if (!empty($user_bets)): ?>
            <div class="race-card">
                <div class="card-body">
                    <h4 class="mb-3">🎯 Your Bets Today</h4>
                    <div class="table-responsive">
                        <table class="table table-dark table-striped">
                            <thead>
                                <tr>
                                    <th>Race</th>
                                    <th>Bet Type</th>
                                    <th>Selections</th>
                                    <th>Amount</th>
                                    <th>Potential</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($user_bets as $bet): ?>
                                    <tr>
                                        <td>Race <?php echo $bet['race_index'] + 1; ?></td>
                                        <td><span class="badge bg-primary"><?php echo strtoupper($bet['bet_type'] ?? 'WIN'); ?></span></td>
                                        <td>
                                            <?php 
                                            $selections = json_decode($bet['horse_selections'] ?? '[]', true);
                                            if (!empty($selections)) {
                                                $horse_names = array_map(function($idx) use ($quick_race_horses) {
                                                    return $quick_race_horses[$idx]['horse_name'] ?? "Horse $idx";
                                                }, $selections);
                                                echo implode(' → ', $horse_names);
                                            } else {
                                                echo $bet['horse_name'];
                                            }
                                            ?>
                                        </td>
                                        <td><?php echo number_format($bet['bet_amount']); ?> coins</td>
                                        <td><?php echo number_format($bet['potential_winnings']); ?> coins</td>
                                        <td>
                                            <span class="badge bg-<?php echo $bet['status'] === 'won' ? 'success' : ($bet['status'] === 'lost' ? 'danger' : 'warning'); ?>">
                                                <?php echo ucfirst($bet['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
let currentBetType = '';
let selectedHorses = [];
let maxSelections = 1;
let betTypeRequirements = {
    'win': { min: 1, max: 1, description: 'Select 1 horse to win' },
    'place': { min: 1, max: 1, description: 'Select 1 horse to place (1st or 2nd)' },
    'show': { min: 1, max: 1, description: 'Select 1 horse to show (1st, 2nd, or 3rd)' },
    'exacta': { min: 2, max: 2, description: 'Select 2 horses in exact finishing order' },
    'quinella': { min: 2, max: 2, description: 'Select 2 horses to finish 1st and 2nd (any order)' },
    'trifecta': { min: 3, max: 3, description: 'Select 3 horses in exact finishing order' },
    'superfecta': { min: 4, max: 4, description: 'Select 4 horses in exact finishing order' }
};

function selectBetType(betType) {
    currentBetType = betType;
    maxSelections = betTypeRequirements[betType].max;
    
    // Clear previous selections
    document.querySelectorAll('.betting-type-card').forEach(card => card.classList.remove('selected'));
    document.querySelector(`[data-bet-type="${betType}"]`).classList.add('selected');
    
    // Reset horse selections
    selectedHorses = [];
    updateHorseDisplay();
    
    // Update UI
    document.getElementById('selectedBetType').value = betType;
    document.getElementById('betTypeDisplay').textContent = betType.toUpperCase();
    document.getElementById('betting-instructions').innerHTML = 
        `<i class="bi bi-info-circle"></i> ${betTypeRequirements[betType].description}`;
    
    updateBetSlip();
}

function selectHorse(horseIndex) {
    if (!currentBetType) {
        alert('Please select a betting type first!');
        return;
    }
    
    const existingIndex = selectedHorses.indexOf(horseIndex);
    
    if (existingIndex !== -1) {
        // Remove if already selected
        selectedHorses.splice(existingIndex, 1);
    } else {
        // Add if under limit
        if (selectedHorses.length < maxSelections) {
            selectedHorses.push(horseIndex);
        } else {
            if (maxSelections === 1) {
                selectedHorses = [horseIndex];
            } else {
                alert(`You can only select ${maxSelections} horses for ${currentBetType} bet.`);
                return;
            }
        }
    }
    
    updateHorseDisplay();
    updateBetSlip();
}

function updateHorseDisplay() {
    document.querySelectorAll('.horse-card').forEach((card, index) => {
        const selectionIndex = selectedHorses.indexOf(index);
        const selectionNumber = card.querySelector('.selection-number');
        
        if (selectionIndex !== -1) {
            card.classList.add('selected');
            selectionNumber.textContent = selectionIndex + 1;
        } else {
            card.classList.remove('selected');
        }
    });
}

function updateBetSlip() {
    const form = document.getElementById('betForm');
    const placeholder = document.getElementById('betPlaceholder');
    
    if (currentBetType && selectedHorses.length >= betTypeRequirements[currentBetType].min) {
        form.style.display = 'block';
        placeholder.style.display = 'none';
        
        document.getElementById('selectedHorses').value = JSON.stringify(selectedHorses);
        
        const horseNames = selectedHorses.map(index => {
            return `${index + 1}. ${<?php echo json_encode(array_column($quick_race_horses, 'horse_name')); ?>}[index]`;
        });
        document.getElementById('selectionsDisplay').innerHTML = horseNames.join('<br>');
        
        updatePotentialWinnings();
    } else {
        form.style.display = 'none';
        placeholder.style.display = 'block';
    }
}

function updatePotentialWinnings() {
    const betAmount = document.querySelector('select[name="bet_amount"]').value;
    if (!betAmount || !currentBetType) return;
    
    const odds = <?php echo json_encode($betting_odds); ?>;
    const minOdds = odds[currentBetType].min;
    const maxOdds = odds[currentBetType].max;
    const avgOdds = (minOdds + maxOdds) / 2;
    
    const potential = Math.floor(betAmount * avgOdds);
    document.getElementById('potentialWinnings').textContent = 
        `Potential: ${potential.toLocaleString()} QR Coins (Est.)`;
    
    document.getElementById('placeBetBtn').disabled = false;
}

// Countdown timers
<?php if ($next_race): ?>
function updateNextRaceTimer() {
    const startTime = new Date('<?php echo $next_race['start_time']->format('Y-m-d H:i:s'); ?>');
    const now = new Date();
    const diff = startTime - now;
    
    if (diff > 0) {
        const minutes = Math.floor(diff / 60000);
        const seconds = Math.floor((diff % 60000) / 1000);
        document.getElementById('timeToStart').textContent = 
            `${minutes}:${seconds.toString().padStart(2, '0')}`;
    } else {
        location.reload();
    }
}
setInterval(updateNextRaceTimer, 1000);
updateNextRaceTimer();
<?php endif; ?>

<?php if ($current_race): ?>
function updateRaceTimer() {
    const endTime = new Date('<?php echo $current_race['end_time']->format('Y-m-d H:i:s'); ?>');
    const now = new Date();
    const diff = endTime - now;
    
    if (diff > 0) {
        const seconds = Math.floor(diff / 1000);
        document.getElementById('timeRemaining').textContent = `${seconds}s`;
    } else {
        location.reload();
    }
}
setInterval(updateRaceTimer, 1000);
updateRaceTimer();
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/../core/includes/footer.php'; ?> 