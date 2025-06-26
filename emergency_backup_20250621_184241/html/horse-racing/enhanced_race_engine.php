<?php
/**
 * Enhanced Quick Race Engine - Dynamic Horse System Integration
 * Simulates races using persistent horses with evolving performance
 */

require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/dynamic_horses.php';

// Initialize the dynamic horse system
$horseSystem = new DynamicHorseSystem($pdo);

// Race schedule
$race_schedule = [
    ['time' => '09:35:00', 'name' => 'Morning Sprint', 'conditions' => ['morning', 'dry']],
    ['time' => '12:00:00', 'name' => 'Lunch Rush', 'conditions' => ['midday']],
    ['time' => '18:10:00', 'name' => 'Evening Thunder', 'conditions' => ['evening']],
    ['time' => '21:05:00', 'name' => 'Night Lightning', 'conditions' => ['night']],
    ['time' => '02:10:00', 'name' => 'Midnight Express', 'conditions' => ['night', 'late']],
    ['time' => '05:10:00', 'name' => 'Dawn Dash', 'conditions' => ['early', 'morning']]
];

function log_message($message) {
    $timestamp = date('Y-m-d H:i:s');
    echo "[$timestamp] $message\n";
    
    // Also log to file
    $log_file = __DIR__ . '/../logs/enhanced_races.log';
    file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND | LOCK_EX);
}

function create_enhanced_race_tables($pdo) {
    try {
        // Enhanced quick race bets table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS quick_race_bets (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                race_date DATE NOT NULL,
                race_index INT NOT NULL,
                horse_index INT NOT NULL,
                horse_id INT NOT NULL,
                horse_name VARCHAR(100) NOT NULL,
                jockey_name VARCHAR(100) NOT NULL,
                bet_amount INT NOT NULL,
                potential_winnings INT NOT NULL,
                actual_winnings INT DEFAULT 0,
                race_result JSON,
                status ENUM('pending', 'won', 'lost') DEFAULT 'pending',
                bet_type ENUM('win', 'place', 'show') DEFAULT 'win',
                odds_multiplier DECIMAL(8,2) DEFAULT 2.0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_user_date (user_id, race_date),
                INDEX idx_race_date_index (race_date, race_index),
                INDEX idx_horse_id (horse_id)
            )
        ");
        
        // Enhanced quick race results table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS quick_race_results (
                id INT AUTO_INCREMENT PRIMARY KEY,
                race_date DATE NOT NULL,
                race_index INT NOT NULL,
                race_name VARCHAR(100) NOT NULL,
                race_start_time DATETIME NOT NULL,
                race_end_time DATETIME NOT NULL,
                race_conditions JSON,
                winning_horse_id INT NOT NULL,
                winning_horse_name VARCHAR(100) NOT NULL,
                winning_jockey_name VARCHAR(100) NOT NULL,
                race_results JSON NOT NULL,
                total_bets INT DEFAULT 0,
                total_bet_amount INT DEFAULT 0,
                total_payouts INT DEFAULT 0,
                weather_conditions VARCHAR(100),
                track_condition VARCHAR(50),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_race (race_date, race_index),
                INDEX idx_race_date (race_date),
                INDEX idx_winning_horse (winning_horse_id)
            )
        ");
        
        log_message("Enhanced race tables created/verified");
        return true;
    } catch (Exception $e) {
        log_message("Error creating tables: " . $e->getMessage());
        return false;
    }
}

function determine_race_conditions($race_time, $race_info) {
    $conditions = $race_info['conditions'] ?? [];
    
    // Add weather simulation
    $weather_chance = rand(1, 10);
    if ($weather_chance <= 2) {
        $conditions[] = 'wet';
    } elseif ($weather_chance == 1) {
        $conditions[] = 'stormy';
    }
    
    // Add track condition
    $track_conditions = ['fast', 'good', 'soft', 'heavy'];
    $track_condition = $track_conditions[array_rand($track_conditions)];
    
    return [
        'conditions' => $conditions,
        'track_condition' => $track_condition,
        'weather' => in_array('wet', $conditions) ? 'rainy' : (in_array('stormy', $conditions) ? 'stormy' : 'clear')
    ];
}

function process_enhanced_race_bets($pdo, $race_date, $race_index, $race_results) {
    $total_payouts = 0;
    
    try {
        // Get all bets for this race
        $stmt = $pdo->prepare("
            SELECT * FROM quick_race_bets 
            WHERE race_date = ? AND race_index = ? AND status = 'pending'
        ");
        $stmt->execute([$race_date, $race_index]);
        $bets = $stmt->fetchAll();
        
        foreach ($bets as $bet) {
            $horse_position = null;
            
            // Find horse position in results
            foreach ($race_results as $result) {
                if ($result['horse_id'] == $bet['horse_id']) {
                    $horse_position = $result['position'];
                    break;
                }
            }
            
            $winnings = 0;
            $status = 'lost';
            
            // Determine winnings based on bet type
            switch ($bet['bet_type']) {
                case 'win':
                    if ($horse_position == 1) {
                        $winnings = $bet['potential_winnings'];
                        $status = 'won';
                    }
                    break;
                case 'place':
                    if ($horse_position <= 2) {
                        $winnings = round($bet['potential_winnings'] * 0.6); // Reduced payout for place
                        $status = 'won';
                    }
                    break;
                case 'show':
                    if ($horse_position <= 3) {
                        $winnings = round($bet['potential_winnings'] * 0.4); // Reduced payout for show
                        $status = 'won';
                    }
                    break;
            }
            
            // Update bet record
            $stmt = $pdo->prepare("
                UPDATE quick_race_bets 
                SET actual_winnings = ?, status = ?, race_result = ?
                WHERE id = ?
            ");
            $stmt->execute([$winnings, $status, json_encode($race_results), $bet['id']]);
            
            // Pay out winnings
            if ($winnings > 0) {
                $stmt = $pdo->prepare("
                    UPDATE users SET qr_coins = qr_coins + ? WHERE id = ?
                ");
                $stmt->execute([$winnings, $bet['user_id']]);
                $total_payouts += $winnings;
                
                log_message("Paid {$winnings} QR coins to user {$bet['user_id']} for {$bet['horse_name']}");
            }
        }
        
        log_message("Processed " . count($bets) . " bets, total payouts: {$total_payouts}");
        return $total_payouts;
        
    } catch (Exception $e) {
        log_message("Error processing bets: " . $e->getMessage());
        return 0;
    }
}

function save_enhanced_race_results($pdo, $race_date, $race_index, $race_name, $race_start, $race_end, $race_results, $race_conditions, $total_bets, $total_bet_amount, $total_payouts) {
    try {
        $winning_result = $race_results[0]; // First place
        
        $stmt = $pdo->prepare("
            INSERT INTO quick_race_results 
            (race_date, race_index, race_name, race_start_time, race_end_time, 
             race_conditions, winning_horse_id, winning_horse_name, winning_jockey_name, 
             race_results, total_bets, total_bet_amount, total_payouts,
             weather_conditions, track_condition)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
            race_results = VALUES(race_results),
            total_bets = VALUES(total_bets),
            total_bet_amount = VALUES(total_bet_amount),
            total_payouts = VALUES(total_payouts)
        ");
        
        $stmt->execute([
            $race_date, $race_index, $race_name, $race_start, $race_end,
            json_encode($race_conditions['conditions']), 
            $winning_result['horse_id'], $winning_result['horse_name'], $winning_result['jockey_name'],
            json_encode($race_results), $total_bets, $total_bet_amount, $total_payouts,
            $race_conditions['weather'], $race_conditions['track_condition']
        ]);
        
        log_message("Enhanced race results saved for {$race_name}");
        return true;
    } catch (Exception $e) {
        log_message("Error saving race results: " . $e->getMessage());
        return false;
    }
}

// Main execution
try {
    log_message("Enhanced Quick Race Engine started");
    
    // Create tables if needed
    if (!create_enhanced_race_tables($pdo)) {
        exit(1);
    }
    
    $current_time = new DateTime();
    $current_date = $current_time->format('Y-m-d');
    $current_time_str = $current_time->format('H:i:s');
    
    // Check if any race should be running now
    $race_found = false;
    
    foreach ($race_schedule as $index => $race) {
        $race_start = new DateTime($current_date . ' ' . $race['time']);
        $race_end = clone $race_start;
        $race_end->add(new DateInterval('PT1M')); // Add 1 minute
        
        // Check if race should be ending now (within 10 seconds of end time)
        $time_to_end = $race_end->getTimestamp() - $current_time->getTimestamp();
        
        if ($time_to_end >= -10 && $time_to_end <= 10) {
            $race_found = true;
            log_message("Processing enhanced race: {$race['name']} (Index: {$index})");
            
            // Check if this race has already been processed
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM quick_race_results 
                WHERE race_date = ? AND race_index = ?
            ");
            $stmt->execute([$current_date, $index]);
            
            if ($stmt->fetchColumn() > 0) {
                log_message("Race already processed, skipping");
                continue;
            }
            
            // Determine race conditions
            $race_conditions = determine_race_conditions($race_start, $race);
            log_message("Race conditions: " . implode(', ', $race_conditions['conditions']) . 
                       " | Weather: {$race_conditions['weather']} | Track: {$race_conditions['track_condition']}");
            
            // Get all horses with current stats
            $horses = $horseSystem->getAllHorsesWithStats();
            
            // Simulate the race using dynamic horse system
            $race_results = $horseSystem->simulateRace($horses, $race_conditions['conditions']);
            
            log_message("Race winner: {$race_results[0]['horse_name']} (ID: {$race_results[0]['horse_id']})");
            log_message("Full results: " . implode(', ', array_map(function($r) {
                return "{$r['position']}. {$r['nickname']} ({$r['finish_time']}s)";
            }, $race_results)));
            
            // Update horse performance in the dynamic system
            $horseSystem->updateHorsePerformance($race_results, $current_date, $index, $race_conditions['conditions']);
            
            // Get bet statistics
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as total_bets, COALESCE(SUM(bet_amount), 0) as total_amount
                FROM quick_race_bets 
                WHERE race_date = ? AND race_index = ?
            ");
            $stmt->execute([$current_date, $index]);
            $bet_stats = $stmt->fetch();
            
            // Process bets and payouts
            $total_payouts = process_enhanced_race_bets($pdo, $current_date, $index, $race_results);
            
            // Save race results
            save_enhanced_race_results(
                $pdo, $current_date, $index, $race['name'], 
                $race_start->format('Y-m-d H:i:s'), $race_end->format('Y-m-d H:i:s'),
                $race_results, $race_conditions, 
                $bet_stats['total_bets'], $bet_stats['total_amount'], $total_payouts
            );
            
            log_message("Enhanced race processing complete. Bets: {$bet_stats['total_bets']}, Amount: {$bet_stats['total_amount']}, Payouts: {$total_payouts}");
        }
    }
    
    if (!$race_found) {
        log_message("No races to process at this time");
        
        // Run daily recovery if it's early morning and hasn't been run today
        $hour = (int)date('H');
        if ($hour >= 4 && $hour <= 6) {
            $recovery_file = __DIR__ . '/../logs/last_recovery.txt';
            $last_recovery = file_exists($recovery_file) ? file_get_contents($recovery_file) : '';
            
            if ($last_recovery !== $current_date) {
                log_message("Running daily horse recovery");
                $horseSystem->dailyRecovery();
                file_put_contents($recovery_file, $current_date);
                log_message("Daily recovery completed");
            }
        }
    }
    
    log_message("Enhanced Quick Race Engine completed");
    
} catch (Exception $e) {
    log_message("Fatal error: " . $e->getMessage());
    exit(1);
}
?> 