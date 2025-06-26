<?php
/**
 * Live Horse Race Viewer
 * Real-time racing animation based on vending machine performance data
 */

require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/qr_coin_manager.php';

// Require user to be logged in
require_login();

$race_id = intval($_GET['id'] ?? 0);
$user_id = $_SESSION['user_id'];
$user_balance = QRCoinManager::getBalance($user_id);

if (!$race_id) {
    header('Location: index.php');
    exit;
}

// Get race details
$stmt = $pdo->prepare("
    SELECT br.*, b.name as business_name, b.logo_path,
           CASE 
               WHEN br.start_time <= NOW() AND br.end_time >= NOW() THEN 'LIVE'
               WHEN br.start_time > NOW() THEN 'UPCOMING'
               ELSE 'FINISHED'
           END as race_status,
           TIMESTAMPDIFF(SECOND, NOW(), br.end_time) as time_remaining
    FROM business_races br
    JOIN businesses b ON br.business_id = b.id
    WHERE br.id = ? AND br.status IN ('approved', 'active', 'completed')
");
$stmt->execute([$race_id]);
$race = $stmt->fetch();

if (!$race) {
    header('Location: index.php?error=race_not_found');
    exit;
}

// Get horses with enhanced Nayax sales data and performance metrics
$stmt = $pdo->prepare("
    SELECT rh.*, vli.item_name, vli.retail_price, vli.cost_price, vli.inventory, vli.item_category,
           COALESCE(ija.custom_jockey_name, ja.jockey_name, 'Wild Card Willie') as jockey_name,
           COALESCE(ija.custom_jockey_avatar_url, ja.jockey_avatar_url, '/horse-racing/assets/img/jockeys/jockey-other.png') as jockey_avatar_url,
           COALESCE(ija.custom_jockey_color, ja.jockey_color, '#6f42c1') as jockey_color,
           COALESCE(hpc.performance_score, 0) as performance_score,
           COALESCE(hpc.units_sold_24h, 0) as sales_24h,
           COALESCE(hpc.profit_per_unit, 0) as profit_margin,
           COALESCE(hpc.nayax_sales_24h, 0) as nayax_sales_24h,
           COALESCE(hpc.combined_revenue, 0) as total_revenue_24h,
           COALESCE(hpc.units_per_hour, 0) as units_per_hour,
           COALESCE(hpc.trend_delta, 0) as trend_delta,
           COALESCE(rr.finish_position, 999) as finish_position,
           -- Betting odds calculation
           CASE 
               WHEN rh.current_odds > 0 THEN rh.current_odds
               ELSE GREATEST(1.5, 10.0 - (COALESCE(hpc.performance_score, 50) / 10.0))
           END as current_odds,
           -- Form rating based on recent performance  
           CASE 
               WHEN COALESCE(hpc.performance_score, 0) >= 80 THEN 'Excellent'
               WHEN COALESCE(hpc.performance_score, 0) >= 60 THEN 'Good'
               WHEN COALESCE(hpc.performance_score, 0) >= 40 THEN 'Fair'
               ELSE 'Poor'
           END as form_rating
    FROM race_horses rh
    JOIN voting_list_items vli ON rh.item_id = vli.id
    LEFT JOIN horse_performance_cache hpc ON vli.id = hpc.item_id 
        AND hpc.cache_date = CURDATE()
    LEFT JOIN race_results rr ON rh.id = rr.horse_id
    LEFT JOIN item_jockey_assignments ija ON vli.id = ija.item_id 
        AND ija.business_id = ?
    LEFT JOIN jockey_assignments ja ON LOWER(vli.item_category) = ja.item_type
    WHERE rh.race_id = ?
    ORDER BY finish_position ASC, rh.id ASC
");
$stmt->execute([$race['business_id'], $race_id]);
$horses = $stmt->fetchAll();

// Get user's bets on this race
$stmt = $pdo->prepare("
    SELECT rb.*, rh.horse_name
    FROM race_bets rb
    JOIN race_horses rh ON rb.horse_id = rh.id
    WHERE rb.user_id = ? AND rb.race_id = ?
    ORDER BY rb.bet_placed_at DESC
");
$stmt->execute([$user_id, $race_id]);
$user_bets = $stmt->fetchAll();

// Calculate race progress (for animation timing)
$race_progress = 0;
if ($race['race_status'] === 'LIVE') {
    $total_duration = strtotime($race['end_time']) - strtotime($race['start_time']);
    $elapsed = time() - strtotime($race['start_time']);
    $race_progress = min(100, ($elapsed / $total_duration) * 100);
}

require_once __DIR__ . '/../core/includes/header.php';
?>

<style>
.race-track {
    background: linear-gradient(to bottom, #4a90e2 0%, #7db46c 20%, #8bc34a 100%);
    min-height: 400px;
    border-radius: 20px;
    position: relative;
    overflow: hidden;
    margin: 2rem 0;
}

.track-lanes {
    position: absolute;
    top: 50px;
    left: 0;
    right: 0;
    bottom: 50px;
}

.horse-lane {
    height: calc(100% / var(--total-horses));
    border-bottom: 2px dashed rgba(255,255,255,0.3);
    position: relative;
    display: flex;
    align-items: center;
    padding: 0 20px;
}

.horse-container {
    position: absolute;
    left: 0;
    display: flex;
    align-items: center;
    transition: left 2s ease-in-out;
    z-index: 10;
}

/* Removed horse icon, made jockey 2x bigger without square border */
.jockey {
    width: 80px;
    height: 80px;
    background-size: cover;
    background-position: center;
    position: relative;
    animation: gallop 0.5s infinite;
}

.horse-info {
    position: absolute;
    left: 10px;
    top: 50%;
    transform: translateY(-50%);
    background: rgba(0,0,0,0.8);
    color: white;
    padding: 0.5rem;
    border-radius: 10px;
    font-size: 0.8rem;
    z-index: 5;
}

.finish-line {
    position: absolute;
    right: 20px;
    top: 0;
    bottom: 0;
    width: 4px;
    background: repeating-linear-gradient(
        to bottom,
        white 0px,
        white 10px,
        black 10px,
        black 20px
    );
    z-index: 5;
}

.finish-line::before {
    content: "🏁";
    position: absolute;
    top: -30px;
    left: -15px;
    font-size: 2rem;
}

@keyframes gallop {
    0%, 100% { transform: translateY(0px); }
    50% { transform: translateY(-4px); }
}

.race-winner {
    animation: winner-celebration 1s infinite;
}

@keyframes winner-celebration {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.1); }
}

.live-badge {
    background: linear-gradient(45deg, #ff6b6b, #ee5a5a);
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-weight: bold;
    animation: pulse 2s infinite;
}

/* Race Program Styling - Like horse racing track programs */
.race-program {
    background: rgba(255, 255, 255, 0.12) !important;
    backdrop-filter: blur(20px) !important;
    border: 1px solid rgba(255, 255, 255, 0.15) !important;
    color: #ffffff !important;
    border-radius: 16px;
    margin-top: 2rem;
    padding: 1.5rem;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
}

.program-header {
    text-align: center;
    border-bottom: 2px solid rgba(255,255,255,0.3);
    padding-bottom: 1rem;
    margin-bottom: 1.5rem;
}

.horse-program-entry {
    background: rgba(255, 255, 255, 0.08);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    padding: 1rem;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    transition: all 0.3s ease;
}

.horse-program-entry:hover {
    background: rgba(255, 255, 255, 0.15);
    transform: translateY(-2px);
}

.program-horse-number {
    background: linear-gradient(45deg, #007bff, #0056b3);
    color: white;
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 1.2rem;
}

.program-jockey-avatar {
    width: 60px;
    height: 60px;
    background-size: cover;
    background-position: center;
}

.program-horse-details {
    flex: 1;
}

.program-horse-name {
    font-size: 1.1rem;
    font-weight: bold;
    margin-bottom: 0.25rem;
}

.program-jockey-name {
    font-size: 0.9rem;
    margin-bottom: 0.5rem;
}

.program-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 0.5rem;
    font-size: 0.85rem;
}

.program-stat {
    background: rgba(0,0,0,0.2);
    padding: 0.25rem 0.5rem;
    border-radius: 6px;
    text-align: center;
}

.program-odds {
    text-align: center;
    min-width: 80px;
}

.program-odds-value {
    font-size: 1.2rem;
    font-weight: bold;
    color: #ffd700;
}

.form-rating {
    display: inline-block;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.8rem;
    font-weight: bold;
}

.form-excellent { background-color: #28a745; color: white; }
.form-good { background-color: #17a2b8; color: white; }
.form-fair { background-color: #ffc107; color: black; }
.form-poor { background-color: #dc3545; color: white; }

.betting-summary {
    background: rgba(255, 255, 255, 0.12) !important;
    backdrop-filter: blur(20px) !important;
    border: 1px solid rgba(255, 255, 255, 0.15) !important;
    color: white;
    border-radius: 16px;
    padding: 1.5rem;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
}

.performance-meter {
    height: 8px;
    background: rgba(255,255,255,0.2);
    border-radius: 4px;
    overflow: hidden;
}

.performance-fill {
    height: 100%;
    background: linear-gradient(45deg, #28a745, #20c997);
    transition: width 1s ease;
}
</style>

<div class="container-fluid">
    <!-- Race Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0" style="background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%); color: white;">
                <div class="card-body text-center py-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h1 class="display-5 mb-2"><?php echo htmlspecialchars($race['race_name']); ?></h1>
                            <p class="lead mb-0">
                                <i class="bi bi-building"></i> 
                                <?php echo htmlspecialchars($race['business_name']); ?>
                            </p>
                        </div>
                        <div class="text-end">
                            <?php if ($race['race_status'] === 'LIVE'): ?>
                                <div class="live-badge mb-2">🔴 LIVE RACE</div>
                                <div>
                                    <small>Time Remaining:</small><br>
                                    <span id="countdown" class="h4">
                                        <?php echo gmdate("H:i:s", $race['time_remaining']); ?>
                                    </span>
                                </div>
                            <?php elseif ($race['race_status'] === 'FINISHED'): ?>
                                <div class="badge bg-success p-2 mb-2">🏁 RACE FINISHED</div>
                            <?php else: ?>
                                <div class="badge bg-info p-2 mb-2">⏳ UPCOMING</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Main Race Track -->
        <div class="col-lg-8">
            <div class="race-track" style="--total-horses: <?php echo count($horses); ?>">
                <div class="track-lanes">
                    <?php foreach ($horses as $index => $horse): ?>
                        <div class="horse-lane">                            
                            <div class="horse-container" id="horse-<?php echo $horse['id']; ?>" 
                                 data-performance="<?php echo $horse['performance_score']; ?>"
                                 data-position="<?php echo $horse['finish_position']; ?>">
                                <!-- Just the jockey image, 2x bigger, no square -->
                                <div class="jockey" 
                                     style="background-image: url('<?php echo $horse['jockey_avatar_url'] ?? '../assets/img/jockey-default.png'; ?>');">
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="finish-line"></div>
                
                <!-- Race Progress Indicator -->
                <div style="position: absolute; bottom: 10px; left: 20px; right: 20px;">
                    <div class="progress" style="height: 8px;">
                        <div class="progress-bar bg-warning" id="raceProgress" 
                             style="width: <?php echo $race_progress; ?>%"></div>
                    </div>
                    <small class="text-white">Race Progress: <?php echo round($race_progress); ?>%</small>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- User's Bets -->
            <div class="betting-summary mb-4">
                <h5 class="mb-3">🎯 Your Bets</h5>
                
                <?php if (empty($user_bets)): ?>
                    <div class="text-center">
                        <p>No bets placed on this race</p>
                        <?php if ($race['race_status'] === 'LIVE' || $race['race_status'] === 'UPCOMING'): ?>
                            <a href="betting.php?race_id=<?php echo $race_id; ?>" class="btn btn-light">
                                Place Bet Now
                            </a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <?php foreach ($user_bets as $bet): ?>
                        <div class="card mb-2" style="background: rgba(255, 255, 255, 0.1) !important; backdrop-filter: blur(10px) !important; border: 1px solid rgba(255, 255, 255, 0.15) !important; color: #fff !important;">
                            <div class="card-body p-2">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><?php echo htmlspecialchars($bet['horse_name']); ?></strong><br>
                                        <small>Bet: <?php echo number_format($bet['bet_amount_qr_coins']); ?> coins</small>
                                    </div>
                                    <div class="text-end">
                                        <?php if ($bet['status'] === 'won'): ?>
                                            <span class="badge bg-success">Won!</span>
                                            <div class="small text-success">
                                                +<?php echo number_format($bet['actual_winnings']); ?>
                                            </div>
                                        <?php elseif ($bet['status'] === 'lost'): ?>
                                            <span class="badge bg-danger">Lost</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning">Pending</span>
                                            <div class="small text-muted">
                                                Potential: <?php echo number_format($bet['potential_winnings']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Race Program - Like at the Horse Track -->
    <div class="row">
        <div class="col-12">
            <div class="race-program">
                <div class="program-header">
                    <h3 class="mb-2">🏇 OFFICIAL RACE PROGRAM</h3>
                    <div class="row text-center">
                        <div class="col-md-3">
                            <strong><?php echo count($horses); ?></strong><br>
                            <small>Runners</small>
                        </div>
                        <div class="col-md-3">
                            <strong><?php echo number_format($race['prize_pool_qr_coins']); ?></strong><br>
                            <small>Prize Pool</small>
                        </div>
                        <div class="col-md-3">
                            <strong><?php echo ucfirst($race['race_type']); ?></strong><br>
                            <small>Race Type</small>
                        </div>
                        <div class="col-md-3">
                            <strong><?php echo $race['race_status']; ?></strong><br>
                            <small>Status</small>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <?php foreach ($horses as $index => $horse): ?>
                        <div class="col-lg-6 mb-3">
                            <div class="horse-program-entry">
                                <div class="program-horse-number">
                                    <?php echo $index + 1; ?>
                                </div>
                                
                                <div class="program-jockey-avatar" 
                                     style="background-image: url('<?php echo $horse['jockey_avatar_url']; ?>');">
                                </div>
                                
                                <div class="program-horse-details">
                                    <div class="program-horse-name" style="color: <?php echo $horse['jockey_color']; ?>">
                                        <?php echo htmlspecialchars($horse['horse_name']); ?>
                                        <?php if ($horse['finish_position'] < 999): ?>
                                            <span class="badge bg-warning text-dark ms-1">#<?php echo $horse['finish_position']; ?></span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="program-jockey-name" style="color: <?php echo $horse['jockey_color']; ?>">
                                        Jockey: <?php echo htmlspecialchars($horse['jockey_name']); ?>
                                    </div>
                                    
                                                                        <div class="form-rating form-<?php echo strtolower($horse['form_rating']); ?> mb-2">
                                        <?php echo $horse['form_rating']; ?> Form
                                    </div>
                                    
                                    <div class="program-stats">
                                        <div class="program-stat">
                                            <div><strong><?php echo $horse['sales_24h'] + $horse['nayax_sales_24h']; ?></strong></div>
                                            <div>Power</div>
                                        </div>
                                        <div class="program-stat">
                                            <div><strong><?php echo number_format($horse['total_revenue_24h'], 0); ?></strong></div>
                                            <div>Stamina</div>
                                        </div>
                                        <div class="program-stat">
                                            <div><strong><?php echo round($horse['performance_score']); ?></strong></div>
                                            <div>Performance</div>
                                        </div>
                                        <div class="program-stat">
                                            <div><strong><?php echo round($horse['units_per_hour'], 1); ?></strong></div>
                                            <div>Speed</div>
                                        </div>
                                        <?php if ($horse['nayax_sales_24h'] > 0): ?>
                                            <div class="program-stat">
                                                <div><strong><?php echo $horse['nayax_sales_24h']; ?></strong></div>
                                                <div>Nayax Sales</div>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($horse['trend_delta'] != 0): ?>
                                            <div class="program-stat">
                                                <div><strong><?php echo $horse['trend_delta'] > 0 ? '+' : ''; ?><?php echo round($horse['trend_delta'], 1); ?></strong></div>
                                                <div>Trend</div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="program-odds">
                                    <div class="program-odds-value">
                                        <?php echo number_format($horse['current_odds'], 1); ?>:1
                                    </div>
                                    <div class="small">Odds</div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Racing Animation Engine
class HorseRace {
    constructor(horses, raceStatus, raceProgress, raceStartTime, raceEndTime) {
        this.horses = horses;
        this.raceStatus = raceStatus;
        this.raceProgress = raceProgress;
        this.raceStartTime = new Date(raceStartTime);
        this.raceEndTime = new Date(raceEndTime);
        this.animationSpeed = 3000; // 3 seconds per update
        this.maxTrackWidth = 85; // 85% of track width
        
        // Sort horses by current performance for positioning
        this.horses.sort((a, b) => {
            const aSales = (parseFloat(a.sales_24h) || 0) + (parseFloat(a.nayax_sales_24h) || 0);
            const bSales = (parseFloat(b.sales_24h) || 0) + (parseFloat(b.nayax_sales_24h) || 0);
            return bSales - aSales; // Highest sales first
        });
        
        this.initializeRace();
    }
    
    initializeRace() {
        if (this.raceStatus === 'FINISHED') {
            this.showFinalPositions();
        } else {
            this.startLiveRace();
        }
    }
    
    calculateHorsePosition(horse, raceTimeProgress) {
        // Get current sales performance (24h sales + nayax sales)
        const totalSales = (parseFloat(horse.sales_24h) || 0) + (parseFloat(horse.nayax_sales_24h) || 0);
        const revenuePerHour = parseFloat(horse.units_per_hour) || 0;
        
        // Base speed calculation using multiple factors
        let baseSpeed = 0;
        
        // Factor 1: Total sales (weighted 40%)
        const salesScore = Math.min(100, totalSales * 2); // Scale sales to 0-100
        baseSpeed += salesScore * 0.4;
        
        // Factor 2: Revenue per hour (weighted 30%)  
        const hourlyScore = Math.min(100, revenuePerHour * 10); // Scale hourly to 0-100
        baseSpeed += hourlyScore * 0.3;
        
        // Factor 3: Performance score (weighted 30%)
        const perfScore = parseFloat(horse.performance_score) || 50;
        baseSpeed += perfScore * 0.3;
        
        // Add race time progress (horses move consistently based on time)
        const timeProgress = raceTimeProgress / 100;
        let position = (baseSpeed / 100) * timeProgress * this.maxTrackWidth;
        
        // Add small random variations for excitement (±2%)
        const randomVariation = (Math.random() - 0.5) * 4;
        position += randomVariation;
        
        // If race is finished, use actual finish positions
        if (this.raceStatus === 'FINISHED' && horse.finish_position < 999) {
            return this.maxTrackWidth;
        }
        
        return Math.max(0, Math.min(position, this.maxTrackWidth));
    }
    
    getRaceTimeProgress() {
        const now = new Date();
        const totalDuration = this.raceEndTime - this.raceStartTime;
        const elapsed = now - this.raceStartTime;
        
        if (elapsed < 0) return 0; // Race hasn't started
        if (elapsed > totalDuration) return 100; // Race is over
        
        return Math.max(0, Math.min(100, (elapsed / totalDuration) * 100));
    }
    
    updateHorsePositions() {
        const timeProgress = this.getRaceTimeProgress();
        
        this.horses.forEach(horse => {
            const horseElement = document.getElementById(`horse-${horse.id}`);
            if (horseElement) {
                const position = this.calculateHorsePosition(horse, timeProgress);
                horseElement.style.left = `${position}%`;
                
                // Add winner animation if finished
                if (this.raceStatus === 'FINISHED' && horse.finish_position === 1) {
                    horseElement.classList.add('race-winner');
                }
            }
        });
        
        // Update progress bar based on time
        const progressBar = document.getElementById('raceProgress');
        if (progressBar) {
            progressBar.style.width = `${timeProgress}%`;
        }
    }
    
    startLiveRace() {
        // Set initial positions based on current performance snapshot
        this.updateHorsePositions();
        
        // Update positions during race based on time and performance
        this.raceInterval = setInterval(() => {
            this.updateHorsePositions();
            
            // Check if race should finish
            const timeProgress = this.getRaceTimeProgress();
            if (timeProgress >= 100) {
                this.finishRace();
            }
        }, this.animationSpeed);
    }
    
    finishRace() {
        if (this.raceInterval) {
            clearInterval(this.raceInterval);
        }
        
        // Move all horses to finish line
        this.horses.forEach(horse => {
            const horseElement = document.getElementById(`horse-${horse.id}`);
            if (horseElement) {
                horseElement.style.left = this.maxTrackWidth + '%';
            }
        });
        
        // Show final results after a delay
        setTimeout(() => location.reload(), 3000); // Reload to show results
    }
    
    showFinalPositions() {
        this.updateHorsePositions();
    }
}

// Initialize race when page loads
document.addEventListener('DOMContentLoaded', function() {
    const horses = <?php echo json_encode($horses); ?>;
    const raceStatus = '<?php echo $race['race_status']; ?>';
    const raceProgress = <?php echo $race_progress; ?>;
    const raceStartTime = '<?php echo $race['race_start_time']; ?>';
    const raceEndTime = '<?php echo $race['race_end_time']; ?>';
    
    new HorseRace(horses, raceStatus, raceProgress, raceStartTime, raceEndTime);
});

// Countdown timer for live races
<?php if ($race['race_status'] === 'LIVE'): ?>
let timeRemaining = <?php echo $race['time_remaining']; ?>;
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
        location.reload(); // Reload when race ends
    }
}, 1000);
<?php endif; ?>

// Auto-refresh for live races
<?php if ($race['race_status'] === 'LIVE'): ?>
setInterval(function() {
    // Refresh page every 2 minutes to get latest data
    location.reload();
}, 120000);
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/../core/includes/footer.php'; ?> 