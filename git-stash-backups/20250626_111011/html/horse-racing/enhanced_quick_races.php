<?php
/**
 * Enhanced Quick Races - Dynamic Horse System with List View
 * Features: 10 persistent horses, evolving performance, detailed betting info
 */

require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/dynamic_horses.php';

$user_id = $_SESSION['user_id'] ?? null;

// Initialize the dynamic horse system
$horseSystem = new DynamicHorseSystem($pdo);

// Get all horses with current stats
$horses = $horseSystem->getAllHorsesWithStats();

// Determine current race conditions
$current_hour = (int)date('H');
$race_conditions = [];

if ($current_hour >= 6 && $current_hour <= 12) {
    $race_conditions[] = 'morning';
} elseif ($current_hour >= 18 || $current_hour <= 6) {
    $race_conditions[] = 'evening';
}

// Add weather conditions (simulate for demo)
$weather_chance = rand(1, 10);
if ($weather_chance <= 2) {
    $race_conditions[] = 'wet';
} elseif ($weather_chance <= 1) {
    $race_conditions[] = 'stormy';
}

// Calculate current odds
$current_odds = $horseSystem->calculateRaceOdds($horses, $race_conditions);

// Quick Race Schedule
$race_schedule = [
    ['time' => '09:35:00', 'name' => 'Morning Sprint', 'description' => 'Perfect for early birds!'],
    ['time' => '12:00:00', 'name' => 'Lunch Rush', 'description' => 'Midday excitement!'],
    ['time' => '18:10:00', 'name' => 'Evening Thunder', 'description' => 'After-work thrills!'],
    ['time' => '21:05:00', 'name' => 'Night Lightning', 'description' => 'Prime time racing!'],
    ['time' => '02:10:00', 'name' => 'Midnight Express', 'description' => 'Late night action!'],
    ['time' => '05:10:00', 'name' => 'Dawn Dash', 'description' => 'Early morning special!']
];

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

// Handle betting
$message = '';
$message_type = 'info';

if ($_POST && $user_id && $next_race) {
    $horse_id = (int)$_POST['horse_id'];
    $bet_amount = (int)$_POST['bet_amount'];
    
    if ($horse_id >= 1 && $horse_id <= 10 && $bet_amount >= 10 && $bet_amount <= 1000) {
        // Check user balance
        $stmt = $pdo->prepare("SELECT qr_coins FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user_balance = $stmt->fetchColumn() ?: 0;
        
        if ($user_balance >= $bet_amount) {
            // Check if user already bet on this race
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM quick_race_bets 
                WHERE user_id = ? AND race_date = ? AND race_index = ?
            ");
            $stmt->execute([$user_id, $current_date, $next_race['index']]);
            
            if ($stmt->fetchColumn() == 0) {
                // Place bet
                $horse = $horses[$horse_id - 1];
                $odds = $current_odds[$horse_id]['decimal_odds'];
                $potential_winnings = round($bet_amount * $odds);
                
                $stmt = $pdo->prepare("
                    INSERT INTO quick_race_bets 
                    (user_id, race_date, race_index, horse_index, horse_name, jockey_name, 
                     bet_amount, potential_winnings, odds_multiplier)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $user_id, $current_date, $next_race['index'], $horse_id - 1,
                    $horse['name'], $horse['jockey_name'], $bet_amount, $potential_winnings, $odds
                ]);
                
                // Deduct from user balance
                $stmt = $pdo->prepare("UPDATE users SET qr_coins = qr_coins - ? WHERE id = ?");
                $stmt->execute([$bet_amount, $user_id]);
                
                $message = "Bet placed successfully! {$bet_amount} QR coins on {$horse['name']}";
                $message_type = 'success';
            } else {
                $message = "You've already bet on this race!";
                $message_type = 'warning';
            }
        } else {
            $message = "Insufficient QR coins!";
            $message_type = 'danger';
        }
    } else {
        $message = "Invalid bet amount or horse selection!";
        $message_type = 'danger';
    }
}

// Get user's current balance
$user_balance = 0;
if ($user_id) {
    $stmt = $pdo->prepare("SELECT qr_coins FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user_balance = $stmt->fetchColumn() ?: 0;
}

require_once __DIR__ . '/../core/includes/header.php';
?>

<style>
.enhanced-racing-container {
    background: linear-gradient(135deg, #2C5530 0%, #1a4c96 50%, #8B4513 100%);
    min-height: 100vh;
    padding: 2rem 0;
}

.race-header {
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 20px;
    color: white;
    padding: 2rem;
    margin-bottom: 2rem;
    text-align: center;
}

.conditions-banner {
    background: linear-gradient(45deg, #FF6B35, #F7931E);
    color: white;
    padding: 1rem;
    border-radius: 10px;
    margin-bottom: 2rem;
    text-align: center;
}

.horse-list {
    background: rgba(255, 255, 255, 0.95);
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
}

.horse-item {
    border-bottom: 1px solid #eee;
    padding: 1.5rem;
    transition: all 0.3s ease;
    cursor: pointer;
}

.horse-item:hover {
    background: #f8f9fa;
    transform: translateX(5px);
}

.horse-item:last-child {
    border-bottom: none;
}

.horse-number {
    background: linear-gradient(45deg, #667eea, #764ba2);
    color: white;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 1.2rem;
}

.horse-name {
    font-size: 1.3rem;
    font-weight: bold;
    color: #2c3e50;
    margin-bottom: 0.5rem;
}

.horse-nickname {
    color: #7f8c8d;
    font-style: italic;
    margin-bottom: 0.5rem;
}

.horse-stats {
    display: flex;
    gap: 1rem;
    margin: 0.5rem 0;
}

.stat-item {
    background: #ecf0f1;
    padding: 0.3rem 0.8rem;
    border-radius: 15px;
    font-size: 0.9rem;
}

.form-indicator {
    display: flex;
    gap: 2px;
    margin: 0.5rem 0;
}

.form-dot {
    width: 12px;
    height: 12px;
    border-radius: 50%;
}

.form-1 { background: #27ae60; }
.form-2 { background: #f39c12; }
.form-3 { background: #e67e22; }
.form-4 { background: #e74c3c; }

.streak-indicator {
    padding: 0.2rem 0.6rem;
    border-radius: 10px;
    font-size: 0.8rem;
    font-weight: bold;
}

.streak-winning {
    background: #d4edda;
    color: #155724;
}

.streak-losing {
    background: #f8d7da;
    color: #721c24;
}

.odds-display {
    background: linear-gradient(45deg, #28a745, #20c997);
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-weight: bold;
    text-align: center;
    min-width: 80px;
}

.personality-badge {
    background: #6c757d;
    color: white;
    padding: 0.2rem 0.6rem;
    border-radius: 10px;
    font-size: 0.8rem;
}

.confidence-bar {
    width: 100%;
    height: 8px;
    background: #ecf0f1;
    border-radius: 4px;
    overflow: hidden;
    margin: 0.5rem 0;
}

.confidence-fill {
    height: 100%;
    background: linear-gradient(90deg, #e74c3c, #f39c12, #27ae60);
    transition: width 0.3s ease;
}

.fatigue-indicator {
    font-size: 0.9rem;
    color: #7f8c8d;
}

.betting-panel {
    background: rgba(255, 255, 255, 0.95);
    border-radius: 15px;
    padding: 2rem;
    margin-top: 2rem;
}

.bet-button {
    background: linear-gradient(45deg, #28a745, #20c997);
    border: none;
    color: white;
    padding: 0.8rem 2rem;
    border-radius: 25px;
    font-weight: bold;
    transition: all 0.3s ease;
}

.bet-button:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(40, 167, 69, 0.4);
}

.race-countdown {
    background: linear-gradient(45deg, #dc3545, #fd7e14);
    color: white;
    padding: 1rem;
    border-radius: 10px;
    text-align: center;
    font-size: 1.2rem;
    font-weight: bold;
    margin-bottom: 2rem;
}

@media (max-width: 768px) {
    .horse-stats {
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .horse-item {
        padding: 1rem;
    }
}
</style>

<div class="enhanced-racing-container">
    <div class="container">
        <!-- Header -->
        <div class="race-header">
            <h1><i class="bi bi-trophy-fill text-warning me-2"></i>Dynamic Horse Racing</h1>
            <p class="mb-0">10 Persistent Horses • Evolving Performance • Real Betting Intelligence</p>
            <div class="mt-3">
                <span class="badge bg-primary me-2">Your Balance: <?php echo number_format($user_balance); ?> QR Coins</span>
                <span class="badge bg-info">Live Odds Updated</span>
            </div>
        </div>

        <!-- Current Conditions -->
        <?php if (!empty($race_conditions)): ?>
        <div class="conditions-banner">
            <h5><i class="bi bi-cloud-sun me-2"></i>Current Race Conditions</h5>
            <div class="d-flex justify-content-center gap-3 flex-wrap">
                <?php foreach ($race_conditions as $condition): ?>
                    <span class="badge bg-light text-dark"><?php echo ucfirst($condition); ?></span>
                <?php endforeach; ?>
            </div>
            <small>These conditions affect horse performance and odds</small>
        </div>
        <?php endif; ?>

        <!-- Race Status -->
        <?php if ($current_race): ?>
            <div class="race-countdown">
                🏁 RACE IN PROGRESS: <?php echo $current_race['name']; ?>
                <br><small>Betting closed - Results coming soon!</small>
            </div>
        <?php elseif ($next_race): ?>
            <div class="race-countdown" id="raceCountdown">
                ⏰ Next Race: <?php echo $next_race['name']; ?>
                <br><span id="timeRemaining">Calculating...</span>
            </div>
        <?php endif; ?>

        <!-- Messages -->
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Horse List -->
        <div class="horse-list">
            <div class="p-3 bg-primary text-white">
                <h4 class="mb-0"><i class="bi bi-list-ul me-2"></i>Today's Runners</h4>
                <small>Click on a horse to place your bet</small>
            </div>
            
            <?php foreach ($horses as $index => $horse): ?>
                <div class="horse-item" onclick="selectHorse(<?php echo $horse['id']; ?>)" data-horse-id="<?php echo $horse['id']; ?>">
                    <div class="row align-items-center">
                        <!-- Horse Number -->
                        <div class="col-auto">
                            <div class="horse-number"><?php echo $horse['id']; ?></div>
                        </div>
                        
                        <!-- Horse Info -->
                        <div class="col">
                            <div class="horse-name"><?php echo $horse['name']; ?></div>
                            <div class="horse-nickname">"<?php echo $horse['nickname']; ?>"</div>
                            <div class="text-muted small mb-2"><?php echo $horse['description']; ?></div>
                            
                            <!-- Stats Row -->
                            <div class="horse-stats">
                                <div class="stat-item">
                                    <i class="bi bi-speedometer2 text-danger"></i>
                                    Speed: <?php echo $horse['current_speed'] ?? $horse['base_speed']; ?>
                                </div>
                                <div class="stat-item">
                                    <i class="bi bi-heart-fill text-success"></i>
                                    Stamina: <?php echo $horse['current_stamina'] ?? $horse['base_stamina']; ?>
                                </div>
                                <div class="stat-item">
                                    <i class="bi bi-graph-up text-info"></i>
                                    Consistency: <?php echo $horse['current_consistency'] ?? $horse['base_consistency']; ?>
                                </div>
                            </div>
                            
                            <!-- Performance Indicators -->
                            <div class="d-flex align-items-center gap-3 mt-2">
                                <!-- Form -->
                                <div>
                                    <small class="text-muted">Form:</small>
                                    <div class="form-indicator">
                                        <?php 
                                        $form = $horse['form_string'] ?? 'NEW';
                                        for ($i = 0; $i < min(5, strlen($form)); $i++) {
                                            $class = 'form-' . $form[$i];
                                            echo "<div class='form-dot $class'></div>";
                                        }
                                        ?>
                                    </div>
                                </div>
                                
                                <!-- Streak -->
                                <?php if (($horse['current_streak_count'] ?? 0) > 0): ?>
                                <div class="streak-indicator streak-<?php echo $horse['current_streak_type']; ?>">
                                    <?php echo ucfirst($horse['current_streak_type']); ?> <?php echo $horse['current_streak_count']; ?>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Personality -->
                                <div class="personality-badge">
                                    <?php echo ucfirst(str_replace('_', ' ', $horse['personality'])); ?>
                                </div>
                            </div>
                            
                            <!-- Confidence Bar -->
                            <div class="mt-2">
                                <small class="text-muted">Confidence: <?php echo $horse['confidence_level'] ?? 50; ?>%</small>
                                <div class="confidence-bar">
                                    <div class="confidence-fill" style="width: <?php echo $horse['confidence_level'] ?? 50; ?>%"></div>
                                </div>
                            </div>
                            
                            <!-- Additional Info -->
                            <div class="d-flex justify-content-between align-items-center mt-2">
                                <div class="small text-muted">
                                    <i class="bi bi-trophy"></i> 
                                    <?php echo $horse['total_races'] ?? 0; ?> races, 
                                    <?php echo $horse['total_wins'] ?? 0; ?> wins 
                                    (<?php echo $horse['win_percentage'] ?? 0; ?>%)
                                </div>
                                
                                <?php if (($horse['fatigue_level'] ?? 0) > 20): ?>
                                <div class="fatigue-indicator">
                                    <i class="bi bi-battery-half text-warning"></i>
                                    Tired (<?php echo $horse['fatigue_level']; ?>%)
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Odds -->
                        <div class="col-auto">
                            <div class="odds-display">
                                <?php echo $current_odds[$horse['id']]['decimal_odds']; ?>x
                                <br><small><?php echo $current_odds[$horse['id']]['win_percentage']; ?>%</small>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Betting Panel -->
        <?php if ($next_race && $user_id): ?>
        <div class="betting-panel">
            <h4><i class="bi bi-cash-coin me-2"></i>Place Your Bet</h4>
            <p class="text-muted">Next Race: <strong><?php echo $next_race['name']; ?></strong> at <?php echo date('g:i A', strtotime($next_race['time'])); ?></p>
            
            <form method="POST" id="bettingForm">
                <div class="row">
                    <div class="col-md-4">
                        <label class="form-label">Selected Horse</label>
                        <input type="text" class="form-control" id="selectedHorseName" readonly placeholder="Click a horse above">
                        <input type="hidden" name="horse_id" id="selectedHorseId">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Bet Amount (QR Coins)</label>
                        <select name="bet_amount" class="form-select" required>
                            <option value="">Select amount</option>
                            <option value="10">10 QR Coins</option>
                            <option value="25">25 QR Coins</option>
                            <option value="50">50 QR Coins</option>
                            <option value="100">100 QR Coins</option>
                            <option value="250">250 QR Coins</option>
                            <option value="500">500 QR Coins</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Potential Winnings</label>
                        <input type="text" class="form-control" id="potentialWinnings" readonly placeholder="Select horse & amount">
                    </div>
                </div>
                <div class="mt-3">
                    <button type="submit" class="bet-button" id="placeBetBtn" disabled>
                        <i class="bi bi-lightning-fill me-2"></i>Place Bet
                    </button>
                </div>
            </form>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
const horses = <?php echo json_encode($horses); ?>;
const odds = <?php echo json_encode($current_odds); ?>;
let selectedHorseId = null;

function selectHorse(horseId) {
    selectedHorseId = horseId;
    const horse = horses.find(h => h.id == horseId);
    
    // Update UI
    document.querySelectorAll('.horse-item').forEach(item => {
        item.classList.remove('bg-light');
    });
    document.querySelector(`[data-horse-id="${horseId}"]`).classList.add('bg-light');
    
    // Update form
    document.getElementById('selectedHorseName').value = horse.name + ' (' + horse.nickname + ')';
    document.getElementById('selectedHorseId').value = horseId;
    document.getElementById('placeBetBtn').disabled = false;
    
    updatePotentialWinnings();
}

function updatePotentialWinnings() {
    const betAmount = document.querySelector('select[name="bet_amount"]').value;
    const winningsField = document.getElementById('potentialWinnings');
    
    if (selectedHorseId && betAmount) {
        const horseOdds = odds[selectedHorseId].decimal_odds;
        const potential = Math.round(betAmount * horseOdds);
        winningsField.value = potential + ' QR Coins';
    } else {
        winningsField.value = '';
    }
}

// Update potential winnings when bet amount changes
document.querySelector('select[name="bet_amount"]').addEventListener('change', updatePotentialWinnings);

// Countdown timer
<?php if ($next_race): ?>
function updateCountdown() {
    const raceTime = new Date('<?php echo $next_race['start_time']->format('Y-m-d H:i:s'); ?>');
    const now = new Date();
    const diff = raceTime - now;
    
    if (diff > 0) {
        const minutes = Math.floor(diff / 60000);
        const seconds = Math.floor((diff % 60000) / 1000);
        document.getElementById('timeRemaining').textContent = 
            `Starts in ${minutes}m ${seconds}s`;
    } else {
        document.getElementById('timeRemaining').textContent = 'Race starting now!';
        setTimeout(() => location.reload(), 2000);
    }
}

setInterval(updateCountdown, 1000);
updateCountdown();
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/../core/includes/footer.php'; ?> 