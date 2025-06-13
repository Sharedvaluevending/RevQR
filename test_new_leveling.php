<?php
require_once 'html/core/functions.php';

// Test the new leveling system
echo "<h2>New Point-Based Leveling System Test</h2>\n";
echo "<h3>Level Requirements (First 20 levels):</h3>\n";

for ($level = 1; $level <= 20; $level++) {
    // Simulate points for each level threshold
    $test_points = ($level == 1) ? 0 : null;
    
    if ($level == 2) $test_points = 1000;
    else if ($level > 2) {
        // Calculate what points would be needed for this level
        $temp_points = 1000; // Level 2 start
        for ($l = 3; $l <= $level; $l++) {
            $base_increase = 200 + (($l - 2) * 50);
            $temp_points += $base_increase;
        }
        $test_points = $temp_points;
    }
    
    if ($test_points !== null) {
        $level_data = calculateUserLevel(0, $test_points, 0, 0);
        echo "Level {$level}: " . number_format($test_points) . " points\n";
        
        if ($level > 1 && $level <= 10) {
            // Show some examples
            $example_points = $test_points - 50; // Slightly under threshold
            $example_data = calculateUserLevel(0, $example_points, 0, 0);
            echo "  → At " . number_format($example_points) . " points: Level {$example_data['level']}, {$example_data['progress']}% to next ({$example_data['points_to_next']} needed)\n";
        }
    }
}

echo "\n<h3>Sample User Progression:</h3>\n";
$sample_points = [0, 500, 1000, 1500, 2200, 3000, 5000, 8000, 12000, 20000];

foreach ($sample_points as $points) {
    $data = calculateUserLevel(0, $points, 0, 0);
    echo "Points: " . number_format($points) . " → Level {$data['level']} ({$data['progress']}% to Level " . ($data['level'] + 1) . ", {$data['points_to_next']} points needed)\n";
}
?> 