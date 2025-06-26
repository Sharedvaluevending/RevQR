<?php
/**
 * Quick Races - 6 Simulated 1-Minute Races Per Day
 * Times: 9:35 AM, 12:00 PM, 6:10 PM, 9:05 PM, 2:10 AM, 5:10 AM
 */

require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';

$user_id = $_SESSION['user_id'] ?? null;

// Quick Race Schedule (6 races per day)
$race_schedule = [
    ['time' => '09:35:00', 'name' => 'Morning Sprint', 'description' => 'Start your day with excitement!'],
    ['time' => '12:00:00', 'name' => 'Lunch Rush', 'description' => 'Midday racing action!'],
    ['time' => '18:10:00', 'name' => 'Evening Thunder', 'description' => 'After-work entertainment!'],
    ['time' => '21:05:00', 'name' => 'Night Lightning', 'description' => 'Prime time racing!'],
    ['time' => '02:10:00', 'name' => 'Midnight Express', 'description' => 'Late night thrills!'],
    ['time' => '05:10:00', 'name' => 'Dawn Dash', 'description' => 'Early bird special!']
];

// Quick Race Horses & Jockeys (using existing jockey images)
$quick_race_horses = [
    [
        'horse_name' => 'Thunder Bolt',
        'jockey_name' => 'Lightning Larry',
        'jockey_image' => '/horse-racing/assets/img/jockeys/bluejokeybluehorse.png',
        'jockey_color' => '#007bff',
        'specialty' => 'Speed demon on short tracks'
    ],
    [
        'horse_name' => 'Golden Arrow',
        'jockey_name' => 'Swift Sarah',
        'jockey_image' => '/horse-racing/assets/img/jockeys/brownjokeybrownhorse.png',
        'jockey_color' => '#8B4513',
        'specialty' => 'Consistent performer'
    ],
    [
        'horse_name' => 'Emerald Flash',
        'jockey_name' => 'Speedy Steve',
        'jockey_image' => '/horse-racing/assets/img/jockeys/greenjokeybluehorse.png',
        'jockey_color' => '#28a745',
        'specialty' => 'Strong finisher'
    ],
    [
        'horse_name' => 'Crimson Comet',
        'jockey_name' => 'Rapid Rita',
        'jockey_image' => '/horse-racing/assets/img/jockeys/redjockeybrownhorse.png',
        'jockey_color' => '#dc3545',
        'specialty' => 'Early leader'
    ],
    [
        'horse_name' => 'Sunset Streak',
        'jockey_name' => 'Turbo Tom',
        'jockey_image' => '/horse-racing/assets/img/jockeys/greenjokeyorangehorse.png',
        'jockey_color' => '#fd7e14',
        'specialty' => 'Clutch performer'
    ],
    [
        'horse_name' => 'Midnight Storm',
        'jockey_name' => 'Flash Fiona',
        'jockey_image' => '/horse-racing/assets/img/jockeys/bluejokeybluehorse.png',
        'jockey_color' => '#6f42c1',
        'specialty' => 'Night race specialist'
    ],
    [
        'horse_name' => 'Silver Bullet',
        'jockey_name' => 'Quick Quinn',
        'jockey_image' => '/horse-racing/assets/img/jockeys/brownjokeybluehorse.png',
        'jockey_color' => '#6c757d',
        'specialty' => 'Surprise finisher'
    ],
    [
        'horse_name' => 'Royal Thunder',
        'jockey_name' => 'Dynamic Dan',
        'jockey_image' => '/horse-racing/assets/img/jockeys/redjokeyorangehorse.png',
        'jockey_color' => '#e83e8c',
        'specialty' => 'Track champion'
    ],
    [
        'horse_name' => 'Diamond Dash',
        'jockey_name' => 'Velocity Val',
        'jockey_image' => '/horse-racing/assets/img/jockeys/greenjokeybluehorse.png',
        'jockey_color' => '#17a2b8',
        'specialty' => 'Distance runner'
    ],
    [
        'horse_name' => 'Phoenix Fire',
        'jockey_name' => 'Blazing Bob',
        'jockey_image' => '/horse-racing/assets/img/jockeys/redjockeybrownhorse.png',
        'jockey_color' => '#fd7e14',
        'specialty' => 'Comeback king'
    ]
];

// Get current time and determine race status
$current_time = new DateTime();
$current_date = $current_time->format('Y-m-d');

// Find current/next race
$current_race = null;
$next_race = null;
$race_status = 'waiting';

foreach ($race_schedule as $index => $race) {
    $race_datetime = new DateTime($current_date . ' ' . $race['time']);
    $race_end = clone $race_datetime;
    $race_end->add(new DateInterval('PT1M')); // Add 1 minute
    
    if ($current_time >= $race_datetime && $current_time <= $race_end) {
        $current_race = array_merge($race, ['index' => $index, 'start_time' => $race_datetime, 'end_time' => $race_end]);
        $race_status = 'live';
        break;
    } elseif ($current_time < $race_datetime) {
        $next_race = array_merge($race, ['index' => $index, 'start_time' => $race_datetime, 'end_time' => $race_end]);
        break;
    }
}

// If no next race today, get first race tomorrow
if (!$next_race && !$current_race) {
    $tomorrow = new DateTime('tomorrow');
    $next_race = array_merge($race_schedule[0], [
        'index' => 0, 
        'start_time' => new DateTime($tomorrow->format('Y-m-d') . ' ' . $race_schedule[0]['time']),
        'end_time' => new DateTime($tomorrow->format('Y-m-d') . ' ' . $race_schedule[0]['time'])
    ]);
    $next_race['end_time']->add(new DateInterval('PT1M'));
}

// Handle race betting
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
            // Create quick race tables if they don't exist
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS quick_race_bets (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    race_date DATE NOT NULL,
                    race_index INT NOT NULL,
                    horse_index INT NOT NULL,
                    horse_name VARCHAR(100) NOT NULL,
                    jockey_name VARCHAR(100) NOT NULL,
                    bet_amount INT NOT NULL,
                    potential_winnings INT NOT NULL,
                    actual_winnings INT DEFAULT 0,
                    race_result JSON,
                    status ENUM('pending', 'won', 'lost') DEFAULT 'pending',
                    bet_type ENUM('win', 'place', 'show', 'exacta', 'quinella', 'trifecta', 'superfecta') DEFAULT 'win',
                    horse_selections JSON,
                    odds_multiplier DECIMAL(8,2) DEFAULT 2.0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_user_date (user_id, race_date),
                    INDEX idx_race_date_index (race_date, race_index)
                )
            ");
            
            // Check if user already bet on this race
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM quick_race_bets 
                WHERE user_id = ? AND race_date = ? AND race_index = ?
            ");
            $stmt->execute([$user_id, $current_date, $race_index]);
            
            if ($stmt->fetchColumn() == 0) {
                // Calculate odds multiplier based on bet type
                $betting_odds = [
                    'win' => ['min' => 1.5, 'max' => 15.0],
                    'place' => ['min' => 1.2, 'max' => 3.5],
                    'show' => ['min' => 1.1, 'max' => 2.5],
                    'exacta' => ['min' => 5.0, 'max' => 50.0],
                    'quinella' => ['min' => 3.0, 'max' => 25.0],
                    'trifecta' => ['min' => 10.0, 'max' => 200.0],
                    'superfecta' => ['min' => 50.0, 'max' => 1000.0]
                ];
                
                $odds_range = $betting_odds[$bet_type];
                $multiplier = $odds_range['min'] + (rand(0, 100) / 100) * ($odds_range['max'] - $odds_range['min']);
                $potential_winnings = (int)($bet_amount * $multiplier);
                
                // Check user balance
                $stmt = $pdo->prepare("SELECT qr_coins FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $user_balance = $stmt->fetchColumn() ?: 0;
                
                if ($user_balance >= $bet_amount) {
                    try {
                        $pdo->beginTransaction();
                        
                        // Deduct coins
                        $stmt = $pdo->prepare("UPDATE users SET qr_coins = qr_coins - ? WHERE id = ?");
                        $stmt->execute([$bet_amount, $user_id]);
                        
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
    } else {
        $message = "Invalid bet amount or horse selection!";
        $message_type = 'danger';
    }
}

// Get user's current bets for today
$user_bets = [];
if ($user_id) {
    $stmt = $pdo->prepare("
        SELECT * FROM quick_race_bets 
        WHERE user_id = ? AND race_date = ?
        ORDER BY race_index ASC
    ");
    $stmt->execute([$user_id, $current_date]);
    $user_bets = $stmt->fetchAll();
    
    // Get user's QR coin balance
    $stmt = $pdo->prepare("SELECT qr_coins FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user_balance = $stmt->fetchColumn() ?: 0;
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

.race-track-mini {
    background: linear-gradient(to right, #4a90e2 0%, #7db46c 50%, #8bc34a 100%);
    height: 60px;
    border-radius: 10px;
    position: relative;
    overflow: hidden;
    margin: 1rem 0;
}

.horse-mini {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    width: 40px;
    height: 40px;
    background-size: cover;
    background-position: center;
    border-radius: 50%;
    border: 2px solid white;
    animation: gallop-mini 0.5s infinite;
}

@keyframes gallop-mini {
    0%, 100% { transform: translateY(-50%) scale(1); }
    50% { transform: translateY(-55%) scale(1.05); }
}

.horse-card {
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 15px;
    padding: 1rem;
    margin-bottom: 1rem;
    transition: all 0.3s ease;
}

.horse-card:hover {
    background: rgba(255, 255, 255, 0.2);
    transform: translateY(-2px);
}

.horse-card-compact {
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 10px;
    padding: 0.75rem;
    margin-bottom: 0.5rem;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
}

.horse-card-compact:hover {
    background: rgba(255, 255, 255, 0.15);
}

.jockey-avatar-compact {
    width: 50px;
    height: 50px;
    background-size: cover;
    background-position: center;
    border-radius: 50%;
    border: 2px solid;
    margin-right: 0.75rem;
    flex-shrink: 0;
}

.horse-info {
    flex-grow: 1;
    color: white;
}

.jockey-avatar-large {
    width: 80px;
    height: 80px;
    background-size: cover;
    background-position: center;
    border-radius: 50%;
    border: 3px solid;
    margin: 0 auto 1rem;
}

.countdown-timer {
    font-size: 2rem;
    font-weight: bold;
    text-align: center;
    margin: 1rem 0;
}

.race-status-live {
    background: linear-gradient(45deg, #ff6b6b, #ee5a24);
    animation: pulse 1s infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.7; }
}

.bet-form {
    background: rgba(0, 0, 0, 0.3);
    border-radius: 10px;
    padding: 1rem;
    margin-top: 1rem;
}

.bet-type-card {
    background: rgba(255, 255, 255, 0.1);
    border: 2px solid rgba(255, 255, 255, 0.2);
    border-radius: 8px;
    padding: 0.5rem;
    margin-bottom: 0.5rem;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
    color: white;
}

.bet-type-card:hover {
    background: rgba(255, 255, 255, 0.2);
    border-color: #ffc107;
}

.bet-type-card.selected {
    background: rgba(255, 193, 7, 0.3);
    border-color: #ffc107;
    color: #ffc107;
}

.horse-selection-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 0.5rem;
    margin-bottom: 1rem;
}

.horse-selection-item {
    background: rgba(255, 255, 255, 0.1);
    border: 2px solid rgba(255, 255, 255, 0.2);
    border-radius: 8px;
    padding: 0.5rem;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
    position: relative;
    color: white;
}

.horse-selection-item:hover {
    background: rgba(255, 255, 255, 0.2);
    border-color: #ffc107;
}

.horse-selection-item.selected {
    background: rgba(40, 167, 69, 0.3);
    border-color: #28a745;
}

.horse-number {
    font-weight: bold;
    font-size: 1.2rem;
}

.horse-name {
    font-size: 0.8rem;
    margin-top: 0.2rem;
}

.selection-order {
    position: absolute;
    top: -8px;
    right: -8px;
    background: #ffc107;
    color: #000;
    border-radius: 50%;
    width: 20px;
    height: 20px;
    font-size: 0.8rem;
    font-weight: bold;
    display: flex;
    align-items: center;
    justify-content: center;
}

.race-results {
    background: rgba(0, 0, 0, 0.4);
    border-radius: 15px;
    padding: 1.5rem;
    margin-top: 2rem;
}
</style>

<div class="quick-race-container">
    <div class="container">
        <div class="text-center mb-4">
            <h1 class="text-white mb-2">⚡ Quick Races</h1>
            <p class="text-white-50">10 Horses • Enhanced Betting • 1-Minute Races</p>
            <?php if ($user_id): ?>
                <div class="badge bg-warning text-dark fs-6">
                    💰 Your Balance: <?php echo number_format($user_balance); ?> QR Coins
                </div>
            <?php endif; ?>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Current/Next Race -->
        <?php if ($current_race): ?>
            <div class="race-card race-status-live">
                <div class="card-body">
                    <h3 class="text-center mb-3">🏁 RACE IN PROGRESS!</h3>
                    <h4 class="text-center"><?php echo $current_race['name']; ?></h4>
                    <p class="text-center"><?php echo $current_race['description']; ?></p>
                    
                    <div class="countdown-timer" id="raceTimer">
                        Race ends in: <span id="timeRemaining"></span>
                    </div>
                    
                    <!-- Live Race Animation -->
                    <div class="race-track-mini">
                        <?php foreach ($quick_race_horses as $index => $horse): ?>
                            <div class="horse-mini" 
                                 style="background-image: url('<?php echo $horse['jockey_image']; ?>'); 
                                        left: <?php echo 5 + ($index * 9); ?>%; 
                                        animation-delay: <?php echo $index * 0.1; ?>s;">
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="text-center">
                        <p class="mb-0">Betting is closed for this race</p>
                        <small>Results will be available when the race finishes</small>
                    </div>
                </div>
            </div>
        <?php elseif ($next_race): ?>
            <div class="race-card">
                <div class="card-body">
                    <h3 class="text-center mb-3">🏇 Next Race</h3>
                    <h4 class="text-center"><?php echo $next_race['name']; ?></h4>
                    <p class="text-center"><?php echo $next_race['description']; ?></p>
                    
                    <div class="countdown-timer" id="nextRaceTimer">
                        Starts in: <span id="timeToStart"></span>
                    </div>
                    
                    <!-- Betting Section -->
                    <?php if ($user_id): ?>
                        <div class="row">
                            <div class="col-md-8">
                                <h5 class="mb-3">🐎 All 10 Horses</h5>
                                <div class="row">
                                    <?php foreach ($quick_race_horses as $index => $horse): ?>
                                        <div class="col-md-4 col-sm-6 mb-2">
                                            <div class="horse-card-compact">
                                                <div class="jockey-avatar-compact" 
                                                     style="background-image: url('<?php echo $horse['jockey_image']; ?>'); 
                                                            border-color: <?php echo $horse['jockey_color']; ?>;">
                                                </div>
                                                <div class="horse-info">
                                                    <h6 class="mb-1"><?php echo $horse['horse_name']; ?></h6>
                                                    <p class="small mb-1">Jockey: <?php echo $horse['jockey_name']; ?></p>
                                                    <p class="small text-white-50 mb-1"><?php echo $horse['specialty']; ?></p>
                                                    <span class="badge" style="background-color: <?php echo $horse['jockey_color']; ?>">
                                                        #<?php echo $index + 1; ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="bet-form">
                                    <h5 class="mb-3">💰 Enhanced Betting</h5>
                                    <form method="post" id="betForm">
                                        <input type="hidden" name="race_index" value="<?php echo $next_race['index']; ?>">
                                        <input type="hidden" name="horse_selections" id="horseSelections" value="">
                                        
                                        <!-- Bet Type Selection -->
                                        <div class="mb-3">
                                            <label class="form-label">Bet Type:</label>
                                            <div class="row">
                                                <div class="col-6">
                                                    <div class="bet-type-card" onclick="selectBetType('win')" data-type="win">
                                                        <strong>WIN</strong><br>
                                                        <small>1st place</small>
                                                    </div>
                                                </div>
                                                <div class="col-6">
                                                    <div class="bet-type-card" onclick="selectBetType('place')" data-type="place">
                                                        <strong>PLACE</strong><br>
                                                        <small>1st or 2nd</small>
                                                    </div>
                                                </div>
                                                <div class="col-6">
                                                    <div class="bet-type-card" onclick="selectBetType('show')" data-type="show">
                                                        <strong>SHOW</strong><br>
                                                        <small>1st, 2nd, or 3rd</small>
                                                    </div>
                                                </div>
                                                <div class="col-6">
                                                    <div class="bet-type-card" onclick="selectBetType('exacta')" data-type="exacta">
                                                        <strong>EXACTA</strong><br>
                                                        <small>1st & 2nd exact</small>
                                                    </div>
                                                </div>
                                                <div class="col-6">
                                                    <div class="bet-type-card" onclick="selectBetType('quinella')" data-type="quinella">
                                                        <strong>QUINELLA</strong><br>
                                                        <small>1st & 2nd any order</small>
                                                    </div>
                                                </div>
                                                <div class="col-6">
                                                    <div class="bet-type-card" onclick="selectBetType('trifecta')" data-type="trifecta">
                                                        <strong>TRIFECTA</strong><br>
                                                        <small>1st, 2nd, 3rd exact</small>
                                                    </div>
                                                </div>
                                            </div>
                                            <input type="hidden" name="bet_type" id="selectedBetType" value="">
                                        </div>
                                        
                                        <!-- Horse Selection Grid -->
                                        <div class="mb-3">
                                            <label class="form-label">Select Horses:</label>
                                            <div id="betInstructions" class="small text-info mb-2">
                                                Choose a bet type first
                                            </div>
                                            <div class="horse-selection-grid">
                                                <?php foreach ($quick_race_horses as $index => $horse): ?>
                                                    <div class="horse-selection-item" 
                                                         onclick="selectHorse(<?php echo $index; ?>)" 
                                                         data-horse="<?php echo $index; ?>">
                                                        <div class="horse-number"><?php echo $index + 1; ?></div>
                                                        <div class="horse-name"><?php echo substr($horse['horse_name'], 0, 8); ?></div>
                                                        <div class="selection-order" style="display: none;"></div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                        
                                        <!-- Selected Horses Display -->
                                        <div class="mb-3">
                                            <div id="selectedHorsesDisplay" class="text-warning">
                                                No horses selected
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Bet Amount (QR Coins):</label>
                                            <select class="form-select" name="bet_amount" required>
                                                <option value="">Choose amount</option>
                                                <option value="5">5 QR Coins</option>
                                                <option value="10">10 QR Coins</option>
                                                <option value="25">25 QR Coins</option>
                                                <option value="50">50 QR Coins</option>
                                                <option value="100">100 QR Coins</option>
                                            </select>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <div id="potentialWinnings" class="text-success"></div>
                                        </div>
                                        
                                        <button type="submit" name="place_bet" class="btn btn-warning w-100" disabled id="betButton">
                                            Place Bet
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="text-center">
                            <p>Please <a href="/login.php" class="text-warning">login</a> to place bets</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Today's Race Schedule -->
        <div class="race-card">
            <div class="card-body">
                <h3 class="text-center mb-4">📅 Today's Race Schedule</h3>
                <div class="row">
                    <?php foreach ($race_schedule as $index => $race): ?>
                        <?php 
                        $race_time = new DateTime($current_date . ' ' . $race['time']);
                        $is_current = $current_race && $current_race['index'] == $index;
                        $is_finished = $current_time > $race_time->add(new DateInterval('PT1M'));
                        $user_bet = null;
                        foreach ($user_bets as $bet) {
                            if ($bet['race_index'] == $index) {
                                $user_bet = $bet;
                                break;
                            }
                        }
                        ?>
                        <div class="col-md-4 mb-3">
                            <div class="card <?php echo $is_current ? 'border-warning' : ($is_finished ? 'border-success' : 'border-secondary'); ?>" 
                                 style="background: rgba(255,255,255,0.1);">
                                <div class="card-body text-center">
                                    <h6 class="text-white"><?php echo $race['name']; ?></h6>
                                    <p class="small text-white-50 mb-2"><?php echo $race['description']; ?></p>
                                    <div class="badge <?php echo $is_current ? 'bg-warning text-dark' : ($is_finished ? 'bg-success' : 'bg-secondary'); ?>">
                                        <?php echo date('g:i A', strtotime($race['time'])); ?>
                                    </div>
                                    
                                    <?php if ($user_bet): ?>
                                        <div class="mt-2">
                                            <small class="text-info">
                                                Your bet: <?php echo $user_bet['horse_name']; ?><br>
                                                Amount: <?php echo $user_bet['bet_amount']; ?> QR Coins
                                            </small>
                                            <?php if ($user_bet['status'] !== 'pending'): ?>
                                                <div class="badge <?php echo $user_bet['status'] === 'won' ? 'bg-success' : 'bg-danger'; ?>">
                                                    <?php echo strtoupper($user_bet['status']); ?>
                                                    <?php if ($user_bet['status'] === 'won'): ?>
                                                        (+<?php echo $user_bet['actual_winnings']; ?> coins)
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Navigation Links -->
        <div class="text-center mt-4">
            <a href="quick-race-results.php" class="btn btn-info btn-lg me-3">
                📊 View Past Results
            </a>
            <a href="index.php" class="btn btn-outline-light btn-lg">
                🏇 View Regular Horse Racing
            </a>
            <p class="text-white-50 mt-2">
                Win/Place/Show/Exacta/Quinella/Trifecta betting with 10 horses
            </p>
        </div>
    </div>
</div>

<script>
// Enhanced betting system variables
let selectedBetType = '';
let selectedHorses = [];
let maxSelections = 1;
const horses = <?php echo json_encode($quick_race_horses); ?>;

// Bet type configuration
const betTypeConfig = {
    win: { horses: 1, description: 'Horse must finish 1st', oddsRange: [1.5, 15.0] },
    place: { horses: 1, description: 'Horse must finish 1st or 2nd', oddsRange: [1.2, 3.5] },
    show: { horses: 1, description: 'Horse must finish 1st, 2nd, or 3rd', oddsRange: [1.1, 2.5] },
    exacta: { horses: 2, description: 'Pick 1st and 2nd in exact order', oddsRange: [5.0, 50.0] },
    quinella: { horses: 2, description: 'Pick 1st and 2nd in any order', oddsRange: [3.0, 25.0] },
    trifecta: { horses: 3, description: 'Pick 1st, 2nd, and 3rd in exact order', oddsRange: [10.0, 200.0] }
};

function selectBetType(type) {
    selectedBetType = type;
    maxSelections = betTypeConfig[type].horses;
    selectedHorses = [];
    
    // Update UI
    document.querySelectorAll('.bet-type-card').forEach(card => {
        card.classList.remove('selected');
    });
    document.querySelector(`[data-type="${type}"]`).classList.add('selected');
    document.getElementById('selectedBetType').value = type;
    
    // Update instructions
    document.getElementById('betInstructions').innerHTML = 
        `<strong>${type.toUpperCase()}:</strong> ${betTypeConfig[type].description}<br>
         <small>Select ${maxSelections} horse${maxSelections > 1 ? 's' : ''}</small>`;
    
    // Clear horse selections
    document.querySelectorAll('.horse-selection-item').forEach(item => {
        item.classList.remove('selected');
        item.querySelector('.selection-order').style.display = 'none';
    });
    
    updateSelectedHorsesDisplay();
    updateBetButton();
    updatePotentialWinnings();
}

function selectHorse(horseIndex) {
    if (!selectedBetType) {
        alert('Please select a bet type first!');
        return;
    }
    
    const horseItem = document.querySelector(`[data-horse="${horseIndex}"]`);
    const orderDiv = horseItem.querySelector('.selection-order');
    
    // Check if horse is already selected
    const existingIndex = selectedHorses.indexOf(horseIndex);
    
    if (existingIndex >= 0) {
        // Remove selection
        selectedHorses.splice(existingIndex, 1);
        horseItem.classList.remove('selected');
        orderDiv.style.display = 'none';
        
        // Update order numbers for remaining selections
        selectedHorses.forEach((horse, index) => {
            const item = document.querySelector(`[data-horse="${horse}"]`);
            const orderDiv = item.querySelector('.selection-order');
            orderDiv.textContent = index + 1;
        });
    } else if (selectedHorses.length < maxSelections) {
        // Add selection
        selectedHorses.push(horseIndex);
        horseItem.classList.add('selected');
        orderDiv.textContent = selectedHorses.length;
        orderDiv.style.display = 'flex';
    } else {
        alert(`You can only select ${maxSelections} horse${maxSelections > 1 ? 's' : ''} for ${selectedBetType} bet!`);
        return;
    }
    
    // Update hidden field
    document.getElementById('horseSelections').value = JSON.stringify(selectedHorses);
    
    updateSelectedHorsesDisplay();
    updateBetButton();
    updatePotentialWinnings();
}

function updateSelectedHorsesDisplay() {
    const display = document.getElementById('selectedHorsesDisplay');
    
    if (selectedHorses.length === 0) {
        display.innerHTML = 'No horses selected';
        display.className = 'text-warning';
        return;
    }
    
    let html = '<strong>Selected:</strong><br>';
    selectedHorses.forEach((horseIndex, index) => {
        const horse = horses[horseIndex];
        if (['exacta', 'trifecta'].includes(selectedBetType)) {
            html += `${index + 1}. ${horse.horse_name}<br>`;
        } else {
            html += `• ${horse.horse_name}<br>`;
        }
    });
    
    display.innerHTML = html;
    display.className = 'text-success';
}

function updateBetButton() {
    const betButton = document.getElementById('betButton');
    const betAmount = document.querySelector('select[name="bet_amount"]').value;
    
    const isValid = selectedBetType && 
                   selectedHorses.length === maxSelections && 
                   betAmount;
    
    betButton.disabled = !isValid;
}

function updatePotentialWinnings() {
    const betAmount = document.querySelector('select[name="bet_amount"]').value;
    const winningsDiv = document.getElementById('potentialWinnings');
    
    if (selectedBetType && selectedHorses.length === maxSelections && betAmount) {
        const config = betTypeConfig[selectedBetType];
        const minOdds = config.oddsRange[0];
        const maxOdds = config.oddsRange[1];
        
        // Calculate average odds for display
        const avgOdds = (minOdds + maxOdds) / 2;
        const potential = Math.floor(betAmount * avgOdds);
        
        winningsDiv.innerHTML = 
            `<strong>Potential winnings:</strong><br>
             ${Math.floor(betAmount * minOdds)} - ${Math.floor(betAmount * maxOdds)} QR Coins<br>
             <small>(Odds: ${minOdds}x - ${maxOdds}x)</small>`;
    } else {
        winningsDiv.innerHTML = '';
    }
}

// Event listeners
document.querySelector('select[name="bet_amount"]').addEventListener('change', function() {
    updateBetButton();
    updatePotentialWinnings();
});

// Countdown timers
<?php if ($current_race): ?>
function updateRaceTimer() {
    const endTime = new Date('<?php echo $current_race['end_time']->format('Y-m-d H:i:s'); ?>');
    const now = new Date();
    const diff = endTime - now;
    
    if (diff > 0) {
        const seconds = Math.floor(diff / 1000);
        document.getElementById('timeRemaining').textContent = seconds + 's';
    } else {
        document.getElementById('timeRemaining').textContent = 'Finished!';
        setTimeout(() => location.reload(), 2000);
    }
}
setInterval(updateRaceTimer, 1000);
updateRaceTimer();
<?php endif; ?>

<?php if ($next_race): ?>
function updateNextRaceTimer() {
    const startTime = new Date('<?php echo $next_race['start_time']->format('Y-m-d H:i:s'); ?>');
    const now = new Date();
    const diff = startTime - now;
    
    if (diff > 0) {
        const hours = Math.floor(diff / (1000 * 60 * 60));
        const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((diff % (1000 * 60)) / 1000);
        
        let timeString = '';
        if (hours > 0) timeString += hours + 'h ';
        if (minutes > 0) timeString += minutes + 'm ';
        timeString += seconds + 's';
        
        document.getElementById('timeToStart').textContent = timeString;
    } else {
        document.getElementById('timeToStart').textContent = 'Starting now!';
        setTimeout(() => location.reload(), 1000);
    }
}
setInterval(updateNextRaceTimer, 1000);
updateNextRaceTimer();
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/../core/includes/footer.php'; ?> 