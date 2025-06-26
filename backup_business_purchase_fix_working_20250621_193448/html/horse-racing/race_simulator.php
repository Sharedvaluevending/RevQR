<?php
/**
 * Quick Race Simulator
 * Simulates live races and generates results when races are active
 */

require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/qr_coin_manager.php';

class QuickRaceSimulator {
    private $pdo;
    private $quick_race_horses;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->initializeHorses();
        $this->createTables();
    }
    
    private function initializeHorses() {
        $this->quick_race_horses = [
            ['horse_name' => 'Thunder Bolt', 'jockey_name' => 'Lightning Larry', 'jockey_color' => '#007bff'],
            ['horse_name' => 'Golden Arrow', 'jockey_name' => 'Swift Sarah', 'jockey_color' => '#8B4513'],
            ['horse_name' => 'Emerald Flash', 'jockey_name' => 'Speedy Steve', 'jockey_color' => '#28a745'],
            ['horse_name' => 'Crimson Comet', 'jockey_name' => 'Rapid Rita', 'jockey_color' => '#dc3545'],
            ['horse_name' => 'Sunset Streak', 'jockey_name' => 'Turbo Tom', 'jockey_color' => '#fd7e14'],
            ['horse_name' => 'Midnight Storm', 'jockey_name' => 'Flash Fiona', 'jockey_color' => '#6f42c1'],
            ['horse_name' => 'Silver Bullet', 'jockey_name' => 'Quick Quinn', 'jockey_color' => '#6c757d'],
            ['horse_name' => 'Royal Thunder', 'jockey_name' => 'Dynamic Dan', 'jockey_color' => '#e83e8c'],
            ['horse_name' => 'Diamond Dash', 'jockey_name' => 'Velocity Val', 'jockey_color' => '#17a2b8'],
            ['horse_name' => 'Phoenix Fire', 'jockey_name' => 'Blazing Bob', 'jockey_color' => '#fd7e14']
        ];
    }
    
    private function createTables() {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS quick_race_results (
                id INT AUTO_INCREMENT PRIMARY KEY,
                race_date DATE NOT NULL,
                race_index INT NOT NULL,
                race_name VARCHAR(100) NOT NULL,
                race_time TIME NOT NULL,
                first_place INT NOT NULL,
                second_place INT NOT NULL,
                third_place INT NOT NULL,
                fourth_place INT NOT NULL,
                fifth_place INT NOT NULL,
                race_results JSON,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_race (race_date, race_index),
                INDEX idx_race_date (race_date)
            )
        ");
    }
    
    /**
     * Check for active races and simulate them
     */
    public function checkAndSimulateActiveRaces() {
        $race_schedule = [
            ['time' => '09:35:00', 'name' => 'Morning Sprint'],
            ['time' => '12:00:00', 'name' => 'Lunch Rush'],
            ['time' => '18:10:00', 'name' => 'Evening Thunder'],
            ['time' => '21:05:00', 'name' => 'Night Lightning'],
            ['time' => '02:10:00', 'name' => 'Midnight Express'],
            ['time' => '05:10:00', 'name' => 'Dawn Dash']
        ];
        
        $current_time = new DateTime();
        $current_date = $current_time->format('Y-m-d');
        
        foreach ($race_schedule as $index => $race) {
            $race_datetime = new DateTime($current_date . ' ' . $race['time']);
            $race_end = clone $race_datetime;
            $race_end->add(new DateInterval('PT1M'));
            
            // Check if race is currently active
            if ($current_time >= $race_datetime && $current_time <= $race_end) {
                // Check if we already have results for this race
                $stmt = $this->pdo->prepare("
                    SELECT id FROM quick_race_results 
                    WHERE race_date = ? AND race_index = ?
                ");
                $stmt->execute([$current_date, $index]);
                
                if (!$stmt->fetch()) {
                    // Generate race results
                    $this->generateRaceResults($current_date, $index, $race);
                    echo "Generated results for {$race['name']}\n";
                }
            }
            
            // Check if race just finished (within 30 seconds) - process bets
            $race_finish_window = clone $race_end;
            $race_finish_window->add(new DateInterval('PT30S'));
            
            if ($current_time >= $race_end && $current_time <= $race_finish_window) {
                $this->processBetsForRace($current_date, $index);
            }
        }
    }
    
    /**
     * Generate realistic race results
     */
    private function generateRaceResults($race_date, $race_index, $race_info) {
        // Create realistic race simulation
        $horses = [];
        for ($i = 0; $i < 10; $i++) {
            $horses[] = [
                'index' => $i,
                'horse_name' => $this->quick_race_horses[$i]['horse_name'],
                'jockey_name' => $this->quick_race_horses[$i]['jockey_name'],
                'speed' => rand(80, 120), // Base speed
                'luck' => rand(-20, 30),  // Luck factor
                'final_time' => 0
            ];
        }
        
        // Calculate race times (simulate 1-minute race with different finishing times)
        foreach ($horses as &$horse) {
            $base_time = 60; // 60 seconds base
            $speed_modifier = (120 - $horse['speed']) * 0.5; // Slower horses take longer
            $luck_modifier = $horse['luck'] * 0.1;
            
            $horse['final_time'] = $base_time + $speed_modifier + $luck_modifier + (rand(0, 100) / 100);
        }
        unset($horse);
        
        // Sort by final time (fastest first)
        usort($horses, function($a, $b) {
            return $a['final_time'] <=> $b['final_time'];
        });
        
        // Extract finishing positions
        $first_place = $horses[0]['index'];
        $second_place = $horses[1]['index'];
        $third_place = $horses[2]['index'];
        $fourth_place = $horses[3]['index'];
        $fifth_place = $horses[4]['index'];
        
        // Store results
        $stmt = $this->pdo->prepare("
            INSERT INTO quick_race_results 
            (race_date, race_index, race_name, race_time, first_place, second_place, 
             third_place, fourth_place, fifth_place, race_results)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $race_date,
            $race_index,
            $race_info['name'],
            $race_info['time'],
            $first_place,
            $second_place,
            $third_place,
            $fourth_place,
            $fifth_place,
            json_encode($horses)
        ]);
    }
    
    /**
     * Process bets for completed race
     */
    private function processBetsForRace($race_date, $race_index) {
        // Get race results
        $stmt = $this->pdo->prepare("
            SELECT * FROM quick_race_results 
            WHERE race_date = ? AND race_index = ?
        ");
        $stmt->execute([$race_date, $race_index]);
        $race_result = $stmt->fetch();
        
        if (!$race_result) return;
        
        // Get all bets for this race
        $stmt = $this->pdo->prepare("
            SELECT * FROM quick_race_bets 
            WHERE race_date = ? AND race_index = ? AND status = 'pending'
        ");
        $stmt->execute([$race_date, $race_index]);
        $bets = $stmt->fetchAll();
        
        foreach ($bets as $bet) {
            $won = false;
            $winnings = 0;
            
            $selections = json_decode($bet['horse_selections'], true);
            
            switch ($bet['bet_type']) {
                case 'win':
                    $won = ($selections[0] == $race_result['first_place']);
                    break;
                    
                case 'place':
                    $won = in_array($selections[0], [$race_result['first_place'], $race_result['second_place']]);
                    break;
                    
                case 'show':
                    $won = in_array($selections[0], [
                        $race_result['first_place'], 
                        $race_result['second_place'], 
                        $race_result['third_place']
                    ]);
                    break;
                    
                case 'exacta':
                    $won = ($selections[0] == $race_result['first_place'] && 
                           $selections[1] == $race_result['second_place']);
                    break;
                    
                case 'quinella':
                    $won = (in_array($selections[0], [$race_result['first_place'], $race_result['second_place']]) &&
                           in_array($selections[1], [$race_result['first_place'], $race_result['second_place']]) &&
                           $selections[0] != $selections[1]);
                    break;
                    
                case 'trifecta':
                    $won = ($selections[0] == $race_result['first_place'] && 
                           $selections[1] == $race_result['second_place'] &&
                           $selections[2] == $race_result['third_place']);
                    break;
            }
            
            if ($won) {
                $winnings = $bet['potential_winnings'];
                
                // Credit user account using QRCoinManager instead of direct database update
                $result = QRCoinManager::addTransaction(
                    $bet['user_id'],
                    'earning',
                    'horse_racing_win',
                    $winnings,
                    "Horse racing winnings - {$bet['bet_type']} bet",
                    [
                        'race_date' => $race_date,
                        'race_index' => $race_index,
                        'bet_type' => $bet['bet_type'],
                        'horse_selections' => $bet['horse_selections'],
                        'bet_id' => $bet['id']
                    ]
                );
                
                if (!$result['success']) {
                    error_log("Failed to credit horse racing winnings: " . $result['error']);
                }
            }
            
            // Update bet status
            $stmt = $this->pdo->prepare("
                UPDATE quick_race_bets 
                SET status = ?, actual_winnings = ?, race_result = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $won ? 'won' : 'lost',
                $winnings,
                json_encode($race_result),
                $bet['id']
            ]);
        }
        
        echo "Processed " . count($bets) . " bets for race $race_index\n";
    }
    
    /**
     * Get live race progress (for animation)
     */
    public function getLiveRaceProgress($race_date, $race_index) {
        $current_time = new DateTime();
        
        // Calculate race progress (0-100%)
        $race_schedule = [
            ['time' => '09:35:00'],
            ['time' => '12:00:00'],
            ['time' => '18:10:00'],
            ['time' => '21:05:00'],
            ['time' => '02:10:00'],
            ['time' => '05:10:00']
        ];
        
        $race_start = new DateTime($race_date . ' ' . $race_schedule[$race_index]['time']);
        $race_end = clone $race_start;
        $race_end->add(new DateInterval('PT1M'));
        
        if ($current_time < $race_start) {
            return ['progress' => 0, 'status' => 'waiting'];
        } elseif ($current_time > $race_end) {
            return ['progress' => 100, 'status' => 'finished'];
        } else {
            $elapsed = $current_time->diff($race_start)->s;
            $progress = min(100, ($elapsed / 60) * 100);
            return ['progress' => $progress, 'status' => 'racing'];
        }
    }
}

// Auto-run when called directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $simulator = new QuickRaceSimulator($pdo);
    $simulator->checkAndSimulateActiveRaces();
}
?> 