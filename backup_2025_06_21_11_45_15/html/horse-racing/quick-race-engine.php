<?php
/**
 * Quick Race Engine - Simulates and processes 1-minute races
 * This script should be run every minute via cron job
 */

require_once __DIR__ . '/../core/config.php';

// Quick Race Schedule (6 races per day)
$race_schedule = [
    ['time' => '09:35:00', 'name' => 'Morning Sprint'],
    ['time' => '12:00:00', 'name' => 'Lunch Rush'],
    ['time' => '18:10:00', 'name' => 'Evening Thunder'],
    ['time' => '21:05:00', 'name' => 'Night Lightning'],
    ['time' => '02:10:00', 'name' => 'Midnight Express'],
    ['time' => '05:10:00', 'name' => 'Dawn Dash']
];

// Quick Race Horses & Jockeys
$quick_race_horses = [
    [
        'horse_name' => 'Thunder Bolt',
        'jockey_name' => 'Lightning Larry',
        'jockey_image' => '/horse-racing/assets/img/jockeys/bluejokeybluehorse.png',
        'jockey_color' => '#007bff',
        'base_speed' => 85
    ],
    [
        'horse_name' => 'Golden Arrow',
        'jockey_name' => 'Swift Sarah',
        'jockey_image' => '/horse-racing/assets/img/jockeys/brownjokeybrownhorse.png',
        'jockey_color' => '#8B4513',
        'base_speed' => 82
    ],
    [
        'horse_name' => 'Emerald Flash',
        'jockey_name' => 'Speedy Steve',
        'jockey_image' => '/horse-racing/assets/img/jockeys/greenjokeybluehorse.png',
        'jockey_color' => '#28a745',
        'base_speed' => 88
    ],
    [
        'horse_name' => 'Crimson Comet',
        'jockey_name' => 'Rapid Rita',
        'jockey_image' => '/horse-racing/assets/img/jockeys/redjockeybrownhorse.png',
        'jockey_color' => '#dc3545',
        'base_speed' => 90
    ],
    [
        'horse_name' => 'Sunset Streak',
        'jockey_name' => 'Turbo Tom',
        'jockey_image' => '/horse-racing/assets/img/jockeys/greenjokeyorangehorse.png',
        'jockey_color' => '#fd7e14',
        'base_speed' => 86
    ],
    [
        'horse_name' => 'Midnight Storm',
        'jockey_name' => 'Flash Fiona',
        'jockey_image' => '/horse-racing/assets/img/jockeys/bluejokeybluehorse.png',
        'jockey_color' => '#6f42c1',
        'base_speed' => 84
    ]
];

function log_message($message) {
    $timestamp = date('Y-m-d H:i:s');
    echo "[$timestamp] $message\n";
    
    // Also log to file
    $log_file = __DIR__ . '/../logs/quick_races.log';
    file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND | LOCK_EX);
}

function create_quick_race_tables($pdo) {
    try {
        // Create quick race bets table
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
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_user_date (user_id, race_date),
                INDEX idx_race_date_index (race_date, race_index)
            )
        ");
        
        // Create quick race results table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS quick_race_results (
                id INT AUTO_INCREMENT PRIMARY KEY,
                race_date DATE NOT NULL,
                race_index INT NOT NULL,
                race_name VARCHAR(100) NOT NULL,
                race_start_time DATETIME NOT NULL,
                race_end_time DATETIME NOT NULL,
                winning_horse_index INT NOT NULL,
                winning_horse_name VARCHAR(100) NOT NULL,
                winning_jockey_name VARCHAR(100) NOT NULL,
                race_results JSON NOT NULL,
                total_bets INT DEFAULT 0,
                total_bet_amount INT DEFAULT 0,
                total_payouts INT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_race (race_date, race_index),
                INDEX idx_race_date (race_date)
            )
        ");
        
        log_message("Quick race tables created/verified");
        return true;
    } catch (Exception $e) {
        log_message("Error creating tables: " . $e->getMessage());
        return false;
    }
}

function simulate_race($horses) {
    $race_results = [];
    
    // Simulate race with random factors
    foreach ($horses as $index => $horse) {
        $speed = $horse['base_speed'];
        
        // Add random factors (±15 points)
        $random_factor = rand(-15, 15);
        $final_speed = $speed + $random_factor;
        
        // Add some time-based factors
        $hour = (int)date('H');
        if ($hour >= 6 && $hour <= 10) {
            // Morning boost for some horses
            if ($index == 0 || $index == 2) $final_speed += 5;
        } elseif ($hour >= 18 && $hour <= 22) {
            // Evening boost for others
            if ($index == 1 || $index == 3) $final_speed += 5;
        } elseif ($hour >= 0 && $hour <= 5) {
            // Night boost for night specialists
            if ($index == 5) $final_speed += 10;
        }
        
        // Calculate finish time (lower is better)
        $finish_time = 60.0 - ($final_speed / 100.0 * 10.0) + (rand(0, 200) / 100.0);
        
        $race_results[] = [
            'horse_index' => $index,
            'horse_name' => $horse['horse_name'],
            'jockey_name' => $horse['jockey_name'],
            'final_speed' => $final_speed,
            'finish_time' => round($finish_time, 2),
            'position' => 0 // Will be set after sorting
        ];
    }
    
    // Sort by finish time (fastest first)
    usort($race_results, function($a, $b) {
        return $a['finish_time'] <=> $b['finish_time'];
    });
    
    // Set positions
    foreach ($race_results as $index => &$result) {
        $result['position'] = $index + 1;
    }
    
    return $race_results;
}

function process_race_bets($pdo, $race_date, $race_index, $winning_horse_index, $race_results) {
    try {
        // Get all bets for this race
        $stmt = $pdo->prepare("
            SELECT * FROM quick_race_bets 
            WHERE race_date = ? AND race_index = ? AND status = 'pending'
        ");
        $stmt->execute([$race_date, $race_index]);
        $bets = $stmt->fetchAll();
        
        $total_payouts = 0;
        
        foreach ($bets as $bet) {
            if ($bet['horse_index'] == $winning_horse_index) {
                // Winner!
                $winnings = $bet['potential_winnings'];
                $total_payouts += $winnings;
                
                // Update bet status and pay out
                $stmt = $pdo->prepare("
                    UPDATE quick_race_bets 
                    SET status = 'won', actual_winnings = ?, race_result = ?
                    WHERE id = ?
                ");
                $stmt->execute([$winnings, json_encode($race_results), $bet['id']]);
                
                // Add winnings to user account
                $stmt = $pdo->prepare("UPDATE users SET qr_coins = qr_coins + ? WHERE id = ?");
                $stmt->execute([$winnings, $bet['user_id']]);
                
                log_message("Paid out {$winnings} QR coins to user {$bet['user_id']} for winning bet");
            } else {
                // Loser
                $stmt = $pdo->prepare("
                    UPDATE quick_race_bets 
                    SET status = 'lost', race_result = ?
                    WHERE id = ?
                ");
                $stmt->execute([json_encode($race_results), $bet['id']]);
            }
        }
        
        return $total_payouts;
    } catch (Exception $e) {
        log_message("Error processing bets: " . $e->getMessage());
        return 0;
    }
}

function save_race_results($pdo, $race_date, $race_index, $race_name, $race_start, $race_end, $race_results, $total_bets, $total_bet_amount, $total_payouts) {
    try {
        $winning_result = $race_results[0]; // First place
        
        $stmt = $pdo->prepare("
            INSERT INTO quick_race_results 
            (race_date, race_index, race_name, race_start_time, race_end_time, 
             winning_horse_index, winning_horse_name, winning_jockey_name, 
             race_results, total_bets, total_bet_amount, total_payouts)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
            race_results = VALUES(race_results),
            total_bets = VALUES(total_bets),
            total_bet_amount = VALUES(total_bet_amount),
            total_payouts = VALUES(total_payouts)
        ");
        
        $stmt->execute([
            $race_date, $race_index, $race_name, $race_start, $race_end,
            $winning_result['horse_index'], $winning_result['horse_name'], $winning_result['jockey_name'],
            json_encode($race_results), $total_bets, $total_bet_amount, $total_payouts
        ]);
        
        log_message("Race results saved for {$race_name}");
        return true;
    } catch (Exception $e) {
        log_message("Error saving race results: " . $e->getMessage());
        return false;
    }
}

// Main execution
try {
    log_message("Quick Race Engine started");
    
    // Create tables if needed
    if (!create_quick_race_tables($pdo)) {
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
            log_message("Processing race: {$race['name']} (Index: {$index})");
            
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
            
            // Get bet statistics
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as total_bets, COALESCE(SUM(bet_amount), 0) as total_amount
                FROM quick_race_bets 
                WHERE race_date = ? AND race_index = ?
            ");
            $stmt->execute([$current_date, $index]);
            $bet_stats = $stmt->fetch();
            
            // Simulate the race
            $race_results = simulate_race($quick_race_horses);
            $winning_horse_index = $race_results[0]['horse_index'];
            
            log_message("Race winner: {$race_results[0]['horse_name']} (Jockey: {$race_results[0]['jockey_name']})");
            
            // Process bets and payouts
            $total_payouts = process_race_bets($pdo, $current_date, $index, $winning_horse_index, $race_results);
            
            // Save race results
            save_race_results(
                $pdo, $current_date, $index, $race['name'], 
                $race_start->format('Y-m-d H:i:s'), $race_end->format('Y-m-d H:i:s'),
                $race_results, $bet_stats['total_bets'], $bet_stats['total_amount'], $total_payouts
            );
            
            log_message("Race processing complete. Bets: {$bet_stats['total_bets']}, Amount: {$bet_stats['total_amount']}, Payouts: {$total_payouts}");
        }
    }
    
    if (!$race_found) {
        log_message("No races to process at this time");
    }
    
    log_message("Quick Race Engine completed");
    
} catch (Exception $e) {
    log_message("Fatal error: " . $e->getMessage());
    exit(1);
}
?> 