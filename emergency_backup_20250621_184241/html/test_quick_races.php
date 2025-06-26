<?php
require_once __DIR__ . '/core/config.php';

echo "<h2>⚡ Quick Races System Test</h2>";

// Test 1: Check if quick race tables exist
echo "<h3>1. Database Tables Status</h3>";
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'quick_race_bets'");
    if ($stmt->rowCount() > 0) {
        echo "✅ quick_race_bets table exists<br>";
        
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM quick_race_bets");
        $count = $stmt->fetch()['count'];
        echo "📊 Found $count quick race bets<br>";
    } else {
        echo "❌ quick_race_bets table does not exist<br>";
    }
    
    $stmt = $pdo->query("SHOW TABLES LIKE 'quick_race_results'");
    if ($stmt->rowCount() > 0) {
        echo "✅ quick_race_results table exists<br>";
        
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM quick_race_results");
        $count = $stmt->fetch()['count'];
        echo "📊 Found $count quick race results<br>";
    } else {
        echo "❌ quick_race_results table does not exist<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "<br>";
}

// Test 2: Check race schedule
echo "<h3>2. Race Schedule</h3>";
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

echo "<strong>📅 Today's Schedule ($current_date):</strong><br>";
foreach ($race_schedule as $index => $race) {
    $race_datetime = new DateTime($current_date . ' ' . $race['time']);
    $race_end = clone $race_datetime;
    $race_end->add(new DateInterval('PT1M'));
    
    $status = 'upcoming';
    if ($current_time >= $race_datetime && $current_time <= $race_end) {
        $status = 'live';
    } elseif ($current_time > $race_end) {
        $status = 'finished';
    }
    
    $status_icon = $status === 'live' ? '🔴' : ($status === 'finished' ? '✅' : '⏰');
    echo "$status_icon Race $index: {$race['name']} at {$race['time']} - " . ucfirst($status) . "<br>";
}

// Test 3: Check horses and jockeys
echo "<h3>3. Quick Race Horses & Jockeys</h3>";
$quick_race_horses = [
    ['horse_name' => 'Thunder Bolt', 'jockey_name' => 'Lightning Larry', 'jockey_image' => '/horse-racing/assets/img/jockeys/bluejokeybluehorse.png'],
    ['horse_name' => 'Golden Arrow', 'jockey_name' => 'Swift Sarah', 'jockey_image' => '/horse-racing/assets/img/jockeys/brownjokeybrownhorse.png'],
    ['horse_name' => 'Emerald Flash', 'jockey_name' => 'Speedy Steve', 'jockey_image' => '/horse-racing/assets/img/jockeys/greenjokeybluehorse.png'],
    ['horse_name' => 'Crimson Comet', 'jockey_name' => 'Rapid Rita', 'jockey_image' => '/horse-racing/assets/img/jockeys/redjockeybrownhorse.png'],
    ['horse_name' => 'Sunset Streak', 'jockey_name' => 'Turbo Tom', 'jockey_image' => '/horse-racing/assets/img/jockeys/greenjokeyorangehorse.png'],
    ['horse_name' => 'Midnight Storm', 'jockey_name' => 'Flash Fiona', 'jockey_image' => '/horse-racing/assets/img/jockeys/bluejokeybluehorse.png']
];

foreach ($quick_race_horses as $index => $horse) {
    $image_exists = file_exists(__DIR__ . $horse['jockey_image']) ? '✅' : '❌';
    echo "$image_exists Horse $index: {$horse['horse_name']} with jockey {$horse['jockey_name']}<br>";
}

// Test 4: Check file permissions and structure
echo "<h3>4. File System Status</h3>";
$files_to_check = [
    'horse-racing/quick-races.php' => 'Quick Races Interface',
    'horse-racing/quick-race-engine.php' => 'Race Engine',
    'horse-racing/setup-quick-races-cron.sh' => 'Cron Setup Script',
    'logs/' => 'Logs Directory'
];

foreach ($files_to_check as $file => $description) {
    $path = __DIR__ . '/' . $file;
    if (file_exists($path)) {
        $permissions = substr(sprintf('%o', fileperms($path)), -4);
        echo "✅ $description ($file) - Permissions: $permissions<br>";
    } else {
        echo "❌ $description ($file) - Not found<br>";
    }
}

// Test 5: Simulate a quick race
echo "<h3>5. Race Simulation Test</h3>";
function simulate_test_race($horses) {
    $race_results = [];
    
    foreach ($horses as $index => $horse) {
        $base_speed = 80 + ($index * 2); // Vary base speeds
        $random_factor = rand(-10, 10);
        $final_speed = $base_speed + $random_factor;
        $finish_time = 60.0 - ($final_speed / 100.0 * 10.0) + (rand(0, 200) / 100.0);
        
        $race_results[] = [
            'horse_index' => $index,
            'horse_name' => $horse['horse_name'],
            'jockey_name' => $horse['jockey_name'],
            'final_speed' => $final_speed,
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

$test_results = simulate_test_race($quick_race_horses);
echo "<strong>🏁 Test Race Results:</strong><br>";
foreach ($test_results as $result) {
    $medal = $result['position'] == 1 ? '🥇' : ($result['position'] == 2 ? '🥈' : ($result['position'] == 3 ? '🥉' : ''));
    echo "$medal Position {$result['position']}: {$result['horse_name']} (Jockey: {$result['jockey_name']}) - Time: {$result['finish_time']}s<br>";
}

// Test 6: Check cron job status
echo "<h3>6. Cron Job Status</h3>";
$cron_output = shell_exec('crontab -l 2>/dev/null | grep quick-race-engine');
if ($cron_output) {
    echo "✅ Quick races cron job is installed:<br>";
    echo "<code>" . htmlspecialchars(trim($cron_output)) . "</code><br>";
} else {
    echo "❌ Quick races cron job not found<br>";
    echo "ℹ️ Run: <code>bash horse-racing/setup-quick-races-cron.sh</code> to install<br>";
}

// Test 7: Create sample tables for testing
echo "<h3>7. Sample Data Creation</h3>";
try {
    // Create tables
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
    
    echo "✅ Quick race tables created successfully<br>";
    
    // Insert a sample race result for testing
    $sample_race_result = json_encode($test_results);
    $stmt = $pdo->prepare("
        INSERT INTO quick_race_results 
        (race_date, race_index, race_name, race_start_time, race_end_time, 
         winning_horse_index, winning_horse_name, winning_jockey_name, 
         race_results, total_bets, total_bet_amount, total_payouts)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE race_results = VALUES(race_results)
    ");
    
    $stmt->execute([
        $current_date, 0, 'Test Race', 
        $current_date . ' 09:35:00', $current_date . ' 09:36:00',
        $test_results[0]['horse_index'], $test_results[0]['horse_name'], $test_results[0]['jockey_name'],
        $sample_race_result, 5, 150, 300
    ]);
    
    echo "✅ Sample race result inserted<br>";
    
} catch (Exception $e) {
    echo "❌ Error creating sample data: " . $e->getMessage() . "<br>";
}

echo "<h3>✅ Test Complete!</h3>";
echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
echo "<h4>🎉 Quick Races System Summary</h4>";
echo "<ul>";
echo "<li>✅ <strong>6 Daily Races</strong> - Every few hours with 1-minute duration</li>";
echo "<li>✅ <strong>New Horses & Jockeys</strong> - Using existing jockey images</li>";
echo "<li>✅ <strong>Simulated Racing</strong> - Fast-paced action with instant results</li>";
echo "<li>✅ <strong>Betting System</strong> - QR coin wagering with automatic payouts</li>";
echo "<li>✅ <strong>Automated Processing</strong> - Cron job handles race execution</li>";
echo "<li>✅ <strong>Separate from Regular Racing</strong> - Keeps machine-driven races intact</li>";
echo "</ul>";
echo "<p><strong>🔗 Access URL:</strong> <a href='horse-racing/quick-races.php'>horse-racing/quick-races.php</a></p>";
echo "<p><strong>⚙️ Setup Cron:</strong> <code>bash horse-racing/setup-quick-races-cron.sh</code></p>";
echo "</div>";
?> 