<?php
/**
 * Dynamic Horse Racing System
 * 10 Persistent Horses with Evolving Performance, Streaks, and Detailed Stats
 */

require_once __DIR__ . '/../core/config.php';

class DynamicHorseSystem {
    private $pdo;
    private $horses;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->initializeHorses();
        $this->createTables();
    }
    
    private function initializeHorses() {
        // 10 Horses with funny, realistic racing names and personalities
        $this->horses = [
            [
                'id' => 1,
                'name' => 'Thunderbolt McGillicuddy',
                'nickname' => 'Thunder',
                'personality' => 'speed_demon',
                'specialty' => 'Early sprints and short bursts',
                'base_speed' => 88,
                'base_stamina' => 75,
                'base_consistency' => 70,
                'preferred_conditions' => ['morning', 'dry'],
                'jockey_name' => 'Lightning Larry',
                'jockey_color' => '#FF6B35',
                'description' => 'A fiery stallion who loves to lead from the front but sometimes burns out',
                'fun_fact' => 'Once ate an entire apple tree and still won the race'
            ],
            [
                'id' => 2,
                'name' => 'Sir Gallops-a-Lot',
                'nickname' => 'Gallops',
                'personality' => 'consistent',
                'specialty' => 'Steady performance across all conditions',
                'base_speed' => 82,
                'base_stamina' => 90,
                'base_consistency' => 95,
                'preferred_conditions' => ['any'],
                'jockey_name' => 'Steady Eddie',
                'jockey_color' => '#4ECDC4',
                'description' => 'The most reliable horse in the stable - never flashy, always solid',
                'fun_fact' => 'Has finished in the top 3 in 78% of all races'
            ],
            [
                'id' => 3,
                'name' => 'Buttercup Bonanza',
                'nickname' => 'Buttercup',
                'personality' => 'comeback_queen',
                'specialty' => 'Strong finishes from behind',
                'base_speed' => 85,
                'base_stamina' => 88,
                'base_consistency' => 65,
                'preferred_conditions' => ['evening', 'wet'],
                'jockey_name' => 'Comeback Katie',
                'jockey_color' => '#FFE66D',
                'description' => 'Loves to make dramatic late charges that leave crowds gasping',
                'fun_fact' => 'Once came from dead last to win by a nose'
            ],
            [
                'id' => 4,
                'name' => 'Disco Inferno Dan',
                'nickname' => 'Disco',
                'personality' => 'night_owl',
                'specialty' => 'Exceptional in evening and night races',
                'base_speed' => 90,
                'base_stamina' => 80,
                'base_consistency' => 60,
                'preferred_conditions' => ['night', 'evening'],
                'jockey_name' => 'Moonlight Mike',
                'jockey_color' => '#B19CD9',
                'description' => 'Comes alive when the sun goes down - a true night warrior',
                'fun_fact' => 'Sleeps 18 hours a day but is unstoppable after dark'
            ],
            [
                'id' => 5,
                'name' => 'Princess Prancealot',
                'nickname' => 'Princess',
                'personality' => 'diva',
                'specialty' => 'Performs best when conditions are perfect',
                'base_speed' => 92,
                'base_stamina' => 70,
                'base_consistency' => 50,
                'preferred_conditions' => ['morning', 'perfect'],
                'jockey_name' => 'Fancy Nancy',
                'jockey_color' => '#FF69B4',
                'description' => 'Incredibly talented but very particular about racing conditions',
                'fun_fact' => 'Refuses to race if her mane isn\'t perfectly braided'
            ],
            [
                'id' => 6,
                'name' => 'Mudslinger Murphy',
                'nickname' => 'Muddy',
                'personality' => 'weather_warrior',
                'specialty' => 'Thrives in poor weather conditions',
                'base_speed' => 78,
                'base_stamina' => 85,
                'base_consistency' => 80,
                'preferred_conditions' => ['wet', 'stormy'],
                'jockey_name' => 'Rainy Rita',
                'jockey_color' => '#8B4513',
                'description' => 'The worse the weather, the better this horse performs',
                'fun_fact' => 'Has never lost a race in the rain'
            ],
            [
                'id' => 7,
                'name' => 'Rocket Fuel Rodriguez',
                'nickname' => 'Rocket',
                'personality' => 'explosive',
                'specialty' => 'Incredible bursts of speed but unpredictable',
                'base_speed' => 95,
                'base_stamina' => 60,
                'base_consistency' => 40,
                'preferred_conditions' => ['any'],
                'jockey_name' => 'Turbo Tom',
                'jockey_color' => '#FF4444',
                'description' => 'Either wins by 10 lengths or finishes dead last - no in between',
                'fun_fact' => 'Holds the track record but also the record for most last-place finishes'
            ],
            [
                'id' => 8,
                'name' => 'Zen Master Zippy',
                'nickname' => 'Zen',
                'personality' => 'balanced',
                'specialty' => 'Adapts strategy based on competition',
                'base_speed' => 84,
                'base_stamina' => 84,
                'base_consistency' => 84,
                'preferred_conditions' => ['calm'],
                'jockey_name' => 'Peaceful Pete',
                'jockey_color' => '#90EE90',
                'description' => 'A perfectly balanced horse who reads the race and adapts accordingly',
                'fun_fact' => 'Meditates for 30 minutes before every race'
            ],
            [
                'id' => 9,
                'name' => 'Caffeine Crash Charlie',
                'nickname' => 'Charlie',
                'personality' => 'morning_glory',
                'specialty' => 'Unstoppable in morning races, sluggish later',
                'base_speed' => 89,
                'base_stamina' => 75,
                'base_consistency' => 70,
                'preferred_conditions' => ['morning', 'early'],
                'jockey_name' => 'Early Bird Emma',
                'jockey_color' => '#DEB887',
                'description' => 'Powered by morning energy but crashes hard in afternoon races',
                'fun_fact' => 'Drinks 3 cups of oat coffee before morning races'
            ],
            [
                'id' => 10,
                'name' => 'Lucky Charm Louie',
                'nickname' => 'Lucky',
                'personality' => 'lucky',
                'specialty' => 'Somehow always finds a way to place well',
                'base_speed' => 80,
                'base_stamina' => 82,
                'base_consistency' => 75,
                'preferred_conditions' => ['any'],
                'jockey_name' => 'Fortune Fiona',
                'jockey_color' => '#32CD32',
                'description' => 'Not the fastest or strongest, but has an uncanny ability to avoid trouble',
                'fun_fact' => 'Has a collection of 47 lucky horseshoes'
            ]
        ];
    }
    
    private function createTables() {
        // Create horse performance tracking table
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS horse_performance (
                id INT AUTO_INCREMENT PRIMARY KEY,
                horse_id INT NOT NULL,
                race_date DATE NOT NULL,
                race_index INT NOT NULL,
                position INT NOT NULL,
                finish_time DECIMAL(5,2) NOT NULL,
                speed_rating INT NOT NULL,
                stamina_used INT NOT NULL,
                conditions VARCHAR(50),
                performance_modifier INT DEFAULT 0,
                streak_type ENUM('winning', 'losing', 'none') DEFAULT 'none',
                streak_count INT DEFAULT 0,
                fatigue_level INT DEFAULT 0,
                confidence_level INT DEFAULT 50,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_horse_date (horse_id, race_date),
                INDEX idx_race_date_index (race_date, race_index)
            )
        ");
        
        // Create horse current stats table
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS horse_current_stats (
                horse_id INT PRIMARY KEY,
                current_speed INT NOT NULL,
                current_stamina INT NOT NULL,
                current_consistency INT NOT NULL,
                total_races INT DEFAULT 0,
                total_wins INT DEFAULT 0,
                total_places INT DEFAULT 0,
                total_shows INT DEFAULT 0,
                current_streak_type ENUM('winning', 'losing', 'none') DEFAULT 'none',
                current_streak_count INT DEFAULT 0,
                fatigue_level INT DEFAULT 0,
                confidence_level INT DEFAULT 50,
                last_race_date DATE,
                form_rating VARCHAR(10) DEFAULT '',
                recent_performance TEXT,
                last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ");
        
        // Initialize horse stats if not exists
        foreach ($this->horses as $horse) {
            $stmt = $this->pdo->prepare("
                INSERT IGNORE INTO horse_current_stats 
                (horse_id, current_speed, current_stamina, current_consistency, confidence_level)
                VALUES (?, ?, ?, ?, 50)
            ");
            $stmt->execute([
                $horse['id'], 
                $horse['base_speed'], 
                $horse['base_stamina'], 
                $horse['base_consistency']
            ]);
        }
    }
    
    public function getHorseWithStats($horse_id) {
        // Get base horse data
        $horse = $this->horses[$horse_id - 1];
        
        // Get current stats
        $stmt = $this->pdo->prepare("SELECT * FROM horse_current_stats WHERE horse_id = ?");
        $stmt->execute([$horse_id]);
        $stats = $stmt->fetch() ?: [];
        
        // Get recent performance (last 5 races)
        $stmt = $this->pdo->prepare("
            SELECT position, race_date, conditions, performance_modifier 
            FROM horse_performance 
            WHERE horse_id = ? 
            ORDER BY race_date DESC, race_index DESC 
            LIMIT 5
        ");
        $stmt->execute([$horse_id]);
        $recent_races = $stmt->fetchAll();
        
        // Calculate form rating (last 5 races)
        $form_string = '';
        $form_score = 0;
        foreach ($recent_races as $race) {
            if ($race['position'] == 1) {
                $form_string .= '1';
                $form_score += 5;
            } elseif ($race['position'] <= 3) {
                $form_string .= '2';
                $form_score += 3;
            } elseif ($race['position'] <= 6) {
                $form_string .= '3';
                $form_score += 1;
            } else {
                $form_string .= '4';
                $form_score -= 1;
            }
        }
        
        // Merge all data
        return array_merge($horse, $stats, [
            'recent_races' => $recent_races,
            'form_string' => $form_string ?: 'NEW',
            'form_score' => $form_score,
            'win_percentage' => $stats['total_races'] > 0 ? round(($stats['total_wins'] / $stats['total_races']) * 100, 1) : 0,
            'place_percentage' => $stats['total_races'] > 0 ? round((($stats['total_wins'] + $stats['total_places']) / $stats['total_races']) * 100, 1) : 0
        ]);
    }
    
    public function getAllHorsesWithStats() {
        $horses_with_stats = [];
        for ($i = 1; $i <= 10; $i++) {
            $horses_with_stats[] = $this->getHorseWithStats($i);
        }
        return $horses_with_stats;
    }
    
    public function calculateRaceOdds($horses_with_stats, $race_conditions = []) {
        $odds = [];
        $total_probability = 0;
        
        foreach ($horses_with_stats as $horse) {
            $base_probability = $horse['current_speed'] + $horse['current_stamina'] + $horse['current_consistency'];
            
            // Apply condition modifiers
            $condition_modifier = 0;
            foreach ($race_conditions as $condition) {
                if (in_array($condition, $horse['preferred_conditions']) || in_array('any', $horse['preferred_conditions'])) {
                    $condition_modifier += 10;
                }
            }
            
            // Apply streak modifiers
            $streak_modifier = 0;
            if ($horse['current_streak_type'] == 'winning') {
                $streak_modifier = min(15, $horse['current_streak_count'] * 3);
            } elseif ($horse['current_streak_type'] == 'losing') {
                $streak_modifier = -min(15, $horse['current_streak_count'] * 2);
            }
            
            // Apply fatigue penalty
            $fatigue_penalty = $horse['fatigue_level'] * -2;
            
            // Apply confidence bonus
            $confidence_bonus = ($horse['confidence_level'] - 50) / 5;
            
            $final_probability = $base_probability + $condition_modifier + $streak_modifier + $fatigue_penalty + $confidence_bonus;
            $final_probability = max(10, $final_probability); // Minimum probability
            
            $odds[$horse['id']] = $final_probability;
            $total_probability += $final_probability;
        }
        
        // Convert to betting odds
        $betting_odds = [];
        foreach ($odds as $horse_id => $probability) {
            $win_percentage = ($probability / $total_probability) * 100;
            $decimal_odds = max(1.1, min(50.0, 100 / $win_percentage));
            
            $betting_odds[$horse_id] = [
                'win_percentage' => round($win_percentage, 1),
                'decimal_odds' => round($decimal_odds, 2),
                'probability_score' => $probability
            ];
        }
        
        return $betting_odds;
    }
    
    public function simulateRace($horses_with_stats, $race_conditions = []) {
        $race_results = [];
        
        foreach ($horses_with_stats as $horse) {
            $performance_score = $horse['current_speed'];
            
            // Apply personality-based modifiers
            switch ($horse['personality']) {
                case 'speed_demon':
                    $performance_score += rand(5, 15) - rand(0, 10); // High variance
                    break;
                case 'consistent':
                    $performance_score += rand(-3, 3); // Low variance
                    break;
                case 'comeback_queen':
                    $performance_score += rand(-10, 20); // Can start slow but finish strong
                    break;
                case 'night_owl':
                    $hour = (int)date('H');
                    if ($hour >= 18 || $hour <= 6) {
                        $performance_score += 15;
                    } else {
                        $performance_score -= 10;
                    }
                    break;
                case 'morning_glory':
                    $hour = (int)date('H');
                    if ($hour >= 6 && $hour <= 12) {
                        $performance_score += 15;
                    } else {
                        $performance_score -= 10;
                    }
                    break;
                case 'diva':
                    if (count($race_conditions) == 0 || in_array('perfect', $race_conditions)) {
                        $performance_score += 20;
                    } else {
                        $performance_score -= 15;
                    }
                    break;
                case 'weather_warrior':
                    if (in_array('wet', $race_conditions) || in_array('stormy', $race_conditions)) {
                        $performance_score += 18;
                    }
                    break;
                case 'explosive':
                    $performance_score += rand(-25, 25); // Extremely high variance
                    break;
                case 'lucky':
                    if (rand(1, 10) <= 3) { // 30% chance of luck boost
                        $performance_score += 12;
                    }
                    break;
            }
            
            // Apply current form modifiers
            if ($horse['current_streak_type'] == 'winning') {
                $performance_score += min(10, $horse['current_streak_count'] * 2);
            } elseif ($horse['current_streak_type'] == 'losing') {
                $performance_score -= min(10, $horse['current_streak_count'] * 1.5);
            }
            
            // Apply fatigue
            $performance_score -= $horse['fatigue_level'];
            
            // Apply confidence
            $performance_score += ($horse['confidence_level'] - 50) / 10;
            
            // Calculate finish time (lower is better)
            $finish_time = 60.0 - ($performance_score / 10.0) + (rand(0, 300) / 100.0);
            
            $race_results[] = [
                'horse_id' => $horse['id'],
                'horse_name' => $horse['name'],
                'nickname' => $horse['nickname'],
                'jockey_name' => $horse['jockey_name'],
                'performance_score' => $performance_score,
                'finish_time' => round($finish_time, 2),
                'position' => 0
            ];
        }
        
        // Sort by finish time
        usort($race_results, function($a, $b) {
            return $a['finish_time'] <=> $b['finish_time'];
        });
        
        // Set positions
        foreach ($race_results as $index => &$result) {
            $result['position'] = $index + 1;
        }
        
        return $race_results;
    }
    
    public function updateHorsePerformance($race_results, $race_date, $race_index, $conditions = []) {
        foreach ($race_results as $result) {
            $horse_id = $result['horse_id'];
            $position = $result['position'];
            
            // Record performance
            $stmt = $this->pdo->prepare("
                INSERT INTO horse_performance 
                (horse_id, race_date, race_index, position, finish_time, speed_rating, stamina_used, conditions)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $horse_id, $race_date, $race_index, $position, 
                $result['finish_time'], $result['performance_score'], 
                rand(60, 90), implode(',', $conditions)
            ]);
            
            // Update current stats
            $this->updateHorseCurrentStats($horse_id, $position);
        }
    }
    
    private function updateHorseCurrentStats($horse_id, $position) {
        // Get current stats
        $stmt = $this->pdo->prepare("SELECT * FROM horse_current_stats WHERE horse_id = ?");
        $stmt->execute([$horse_id]);
        $stats = $stmt->fetch();
        
        if (!$stats) return;
        
        // Update race counts
        $total_races = $stats['total_races'] + 1;
        $total_wins = $stats['total_wins'] + ($position == 1 ? 1 : 0);
        $total_places = $stats['total_places'] + ($position == 2 ? 1 : 0);
        $total_shows = $stats['total_shows'] + ($position == 3 ? 1 : 0);
        
        // Update streak
        $new_streak_type = $stats['current_streak_type'];
        $new_streak_count = $stats['current_streak_count'];
        
        if ($position <= 3) { // Good finish
            if ($new_streak_type == 'winning') {
                $new_streak_count++;
            } else {
                $new_streak_type = 'winning';
                $new_streak_count = 1;
            }
        } else { // Poor finish
            if ($new_streak_type == 'losing') {
                $new_streak_count++;
            } else {
                $new_streak_type = 'losing';
                $new_streak_count = 1;
            }
        }
        
        // Update confidence based on performance
        $confidence_change = 0;
        if ($position == 1) $confidence_change = 8;
        elseif ($position <= 3) $confidence_change = 3;
        elseif ($position <= 6) $confidence_change = -1;
        else $confidence_change = -5;
        
        $new_confidence = max(10, min(90, $stats['confidence_level'] + $confidence_change));
        
        // Update fatigue (increases with each race, decreases over time)
        $new_fatigue = min(50, $stats['fatigue_level'] + rand(3, 8));
        
        // Slight stat evolution based on performance
        $speed_change = ($position <= 3) ? rand(0, 1) : rand(-1, 0);
        $stamina_change = ($position <= 5) ? rand(0, 1) : rand(-1, 0);
        $consistency_change = ($position <= 4) ? rand(0, 1) : rand(-1, 0);
        
        $new_speed = max(60, min(100, $stats['current_speed'] + $speed_change));
        $new_stamina = max(60, min(100, $stats['current_stamina'] + $stamina_change));
        $new_consistency = max(40, min(100, $stats['current_consistency'] + $consistency_change));
        
        // Update database
        $stmt = $this->pdo->prepare("
            UPDATE horse_current_stats SET
                current_speed = ?, current_stamina = ?, current_consistency = ?,
                total_races = ?, total_wins = ?, total_places = ?, total_shows = ?,
                current_streak_type = ?, current_streak_count = ?,
                fatigue_level = ?, confidence_level = ?,
                last_race_date = CURDATE()
            WHERE horse_id = ?
        ");
        $stmt->execute([
            $new_speed, $new_stamina, $new_consistency,
            $total_races, $total_wins, $total_places, $total_shows,
            $new_streak_type, $new_streak_count,
            $new_fatigue, $new_confidence,
            $horse_id
        ]);
    }
    
    public function dailyRecovery() {
        // Reduce fatigue for all horses daily
        $this->pdo->exec("
            UPDATE horse_current_stats 
            SET fatigue_level = GREATEST(0, fatigue_level - 5)
            WHERE last_race_date < CURDATE()
        ");
    }
}

// Initialize the system
$horseSystem = new DynamicHorseSystem($pdo);

// Daily recovery (run this once per day)
if (isset($_GET['daily_recovery'])) {
    $horseSystem->dailyRecovery();
    echo "Daily recovery completed!";
    exit;
}

?>
