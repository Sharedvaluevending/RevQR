<?php
/**
 * Test Race Functionality
 * Manual testing script for quick races
 */

require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/race_simulator.php';

echo "<h2>Quick Race Test</h2>\n";

try {
    // Initialize simulator
    $simulator = new QuickRaceSimulator($pdo);
    echo "<p>✅ Race simulator initialized successfully</p>\n";
    
    // Check current race status
    $current_time = new DateTime();
    $current_date = $current_time->format('Y-m-d');
    
    echo "<p><strong>Current Time:</strong> " . $current_time->format('Y-m-d H:i:s') . "</p>\n";
    
    // Test race schedule
    $race_schedule = [
        ['time' => '09:35:00', 'name' => 'Morning Sprint'],
        ['time' => '12:00:00', 'name' => 'Lunch Rush'],
        ['time' => '18:10:00', 'name' => 'Evening Thunder'],
        ['time' => '21:05:00', 'name' => 'Night Lightning'],
        ['time' => '02:10:00', 'name' => 'Midnight Express'],
        ['time' => '05:10:00', 'name' => 'Dawn Dash']
    ];
    
    echo "<h3>Race Schedule Status:</h3>\n";
    
    foreach ($race_schedule as $index => $race) {
        $race_datetime = new DateTime($current_date . ' ' . $race['time']);
        $race_end = clone $race_datetime;
        $race_end->add(new DateInterval('PT1M'));
        
        $status = 'Waiting';
        if ($current_time >= $race_datetime && $current_time <= $race_end) {
            $status = '🏁 LIVE';
        } elseif ($current_time > $race_end) {
            $status = '✅ Finished';
        }
        
        echo "<p><strong>Race {$index}:</strong> {$race['name']} at {$race['time']} - <em>{$status}</em></p>\n";
    }
    
    // Check race results
    echo "<h3>Today's Race Results:</h3>\n";
    $stmt = $pdo->prepare("SELECT * FROM quick_race_results WHERE race_date = ? ORDER BY race_index ASC");
    $stmt->execute([$current_date]);
    $results = $stmt->fetchAll();
    
    if ($results) {
        foreach ($results as $result) {
            $race_data = json_decode($result['race_results'], true);
            echo "<div style='border: 1px solid #ddd; padding: 10px; margin: 10px 0;'>\n";
            echo "<h4>{$result['race_name']} (Race {$result['race_index']})</h4>\n";
            echo "<p><strong>Results:</strong></p>\n";
            echo "<ul>\n";
            echo "<li>🥇 1st: Horse #{$result['first_place']}</li>\n";
            echo "<li>🥈 2nd: Horse #{$result['second_place']}</li>\n";
            echo "<li>🥉 3rd: Horse #{$result['third_place']}</li>\n";
            echo "</ul>\n";
            echo "</div>\n";
        }
    } else {
        echo "<p>No race results found for today.</p>\n";
    }
    
    // Test image paths
    echo "<h3>Horse Image Path Test:</h3>\n";
    $quick_race_horses = [
        ['horse_name' => 'Silver Bullet', 'jockey_image' => '/horse-racing/assets/img/jockeys/brownjokeybrownhorse.png'],
        ['horse_name' => 'Royal Thunder', 'jockey_image' => '/horse-racing/assets/img/jockeys/redjockeybrownhorse.png']
    ];
    
    foreach ($quick_race_horses as $index => $horse) {
        $full_path = __DIR__ . '/assets/img/jockeys/' . basename($horse['jockey_image']);
        $exists = file_exists($full_path);
        $status = $exists ? "✅ Found" : "❌ Missing";
        echo "<p><strong>Horse " . ($index + 7) . " ({$horse['horse_name']}):</strong> {$horse['jockey_image']} - {$status}</p>\n";
    }
    
    // Manual race trigger (for testing only)
    if (isset($_GET['trigger_race']) && $_GET['trigger_race'] === 'true') {
        echo "<h3>Manual Race Trigger:</h3>\n";
        $simulator->checkAndSimulateActiveRaces();
        echo "<p>✅ Race check completed</p>\n";
    }
    
    echo "<p><a href='?trigger_race=true'>🎯 Trigger Race Check</a></p>\n";
    echo "<p><a href='quick-races.php'>🏇 View Quick Races Page</a></p>\n";
    
} catch (Exception $e) {
    echo "<p>❌ Error: " . $e->getMessage() . "</p>\n";
}
?> 