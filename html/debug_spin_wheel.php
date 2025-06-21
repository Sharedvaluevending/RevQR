<?php
/**
 * Debug Spin Wheel Script
 * This script helps identify issues with the spin wheel prize selection
 */

require_once __DIR__ . '/core/config.php';

// Define the same rewards array as in spin.php
$specific_rewards = [
    ['name' => 'Lord Pixel!', 'rarity_level' => 11, 'weight' => 1, 'special' => 'lord_pixel', 'points' => 0],
    ['name' => 'Try Again', 'rarity_level' => 2, 'weight' => 20, 'special' => 'spin_again', 'points' => 0],
    ['name' => 'Extra Vote', 'rarity_level' => 2, 'weight' => 15, 'points' => 0],
    ['name' => '50 QR Coins', 'rarity_level' => 3, 'weight' => 20, 'points' => 50],
    ['name' => '-20 QR Coins', 'rarity_level' => 5, 'weight' => 15, 'points' => -20],
    ['name' => '200 QR Coins', 'rarity_level' => 7, 'weight' => 12, 'points' => 200],
    ['name' => 'Lose All Votes', 'rarity_level' => 8, 'weight' => 10, 'points' => 0],
    ['name' => '500 QR Coins!', 'rarity_level' => 10, 'weight' => 7, 'points' => 500]
];

echo "<h1>🎡 Spin Wheel Debug Analysis</h1>";

// 1. Display current prize configuration
echo "<h2>1. Current Prize Configuration</h2>";
echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr><th>Index</th><th>Prize Name</th><th>Weight</th><th>Probability</th><th>Points</th><th>Rarity</th></tr>";

$total_weight = array_sum(array_column($specific_rewards, 'weight'));
foreach ($specific_rewards as $index => $reward) {
    $probability = round(($reward['weight'] / $total_weight) * 100, 2);
    echo "<tr>";
    echo "<td>{$index}</td>";
    echo "<td>{$reward['name']}</td>";
    echo "<td>{$reward['weight']}</td>";
    echo "<td>{$probability}%</td>";
    echo "<td>{$reward['points']}</td>";
    echo "<td>{$reward['rarity_level']}</td>";
    echo "</tr>";
}
echo "</table>";
echo "<p><strong>Total Weight:</strong> {$total_weight}</p>";

// 2. Test prize selection algorithm
echo "<h2>2. Prize Selection Algorithm Test (100 spins)</h2>";

$results = [];
for ($i = 0; $i < 100; $i++) {
    $random = mt_rand(1, $total_weight);
    $current_weight = 0;
    $selected_reward = null;
    $selected_index = null;
    
    foreach ($specific_rewards as $index => $reward) {
        $current_weight += $reward['weight'];
        if ($random <= $current_weight) {
            $selected_reward = $reward;
            $selected_index = $index;
            break;
        }
    }
    
    if ($selected_reward) {
        $results[] = [
            'spin' => $i + 1,
            'random' => $random,
            'prize' => $selected_reward['name'],
            'index' => $selected_index,
            'points' => $selected_reward['points']
        ];
    }
}

// Display results
echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr><th>Spin #</th><th>Random Value</th><th>Selected Prize</th><th>Index</th><th>Points</th></tr>";

foreach ($results as $result) {
    $row_color = '';
    if ($result['prize'] === '500 QR Coins!') {
        $row_color = 'background-color: #ffeb3b;';
    } elseif ($result['prize'] === '200 QR Coins') {
        $row_color = 'background-color: #ffcdd2;';
    }
    
    echo "<tr style='{$row_color}'>";
    echo "<td>{$result['spin']}</td>";
    echo "<td>{$result['random']}</td>";
    echo "<td>{$result['prize']}</td>";
    echo "<td>{$result['index']}</td>";
    echo "<td>{$result['points']}</td>";
    echo "</tr>";
}
echo "</table>";

// 3. Calculate actual vs expected probabilities
echo "<h2>3. Actual vs Expected Probabilities</h2>";

$actual_counts = [];
foreach ($results as $result) {
    $prize = $result['prize'];
    $actual_counts[$prize] = ($actual_counts[$prize] ?? 0) + 1;
}

echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr><th>Prize</th><th>Expected %</th><th>Actual Count</th><th>Actual %</th><th>Difference</th></tr>";

foreach ($specific_rewards as $reward) {
    $expected_pct = round(($reward['weight'] / $total_weight) * 100, 2);
    $actual_count = $actual_counts[$reward['name']] ?? 0;
    $actual_pct = round(($actual_count / 100) * 100, 2);
    $difference = $actual_pct - $expected_pct;
    
    $row_color = '';
    if (abs($difference) > 5) {
        $row_color = 'background-color: #ffcdd2;';
    }
    
    echo "<tr style='{$row_color}'>";
    echo "<td>{$reward['name']}</td>";
    echo "<td>{$expected_pct}%</td>";
    echo "<td>{$actual_count}</td>";
    echo "<td>{$actual_pct}%</td>";
    echo "<td>{$difference}%</td>";
    echo "</tr>";
}
echo "</table>";

// 4. Test angle calculation
echo "<h2>4. Angle Calculation Test</h2>";

echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr><th>Prize Index</th><th>Prize Name</th><th>Slice Angle</th><th>Center Angle</th><th>Target Angle</th><th>Final Spin Angle</th></tr>";

foreach ($specific_rewards as $index => $reward) {
    $slice_angle = 360 / count($specific_rewards); // 45 degrees per slice
    $prize_center_angle = ($index * $slice_angle) + ($slice_angle / 2);
    $pointer_offset = 90; // Pointer is at top
    $target_angle = $prize_center_angle - $pointer_offset;
    $target_angle = ($target_angle + 360) % 360;
    
    // Add 4 full rotations for dramatic effect
    $final_spin_angle = (4 * 360) + $target_angle;
    
    echo "<tr>";
    echo "<td>{$index}</td>";
    echo "<td>{$reward['name']}</td>";
    echo "<td>{$slice_angle}°</td>";
    echo "<td>{$prize_center_angle}°</td>";
    echo "<td>{$target_angle}°</td>";
    echo "<td>{$final_spin_angle}°</td>";
    echo "</tr>";
}
echo "</table>";

// 5. Check for any recent spin results in database
echo "<h2>5. Recent Spin Results (Last 20)</h2>";

try {
    $stmt = $pdo->prepare("
        SELECT user_id, prize_won, prize_points, spin_time, is_big_win
        FROM spin_results 
        ORDER BY spin_time DESC 
        LIMIT 20
    ");
    $stmt->execute();
    $recent_spins = $stmt->fetchAll();
    
    if ($recent_spins) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>User ID</th><th>Prize Won</th><th>Points</th><th>Big Win</th><th>Time</th></tr>";
        
        foreach ($recent_spins as $spin) {
            $row_color = '';
            if ($spin['prize_won'] === '500 QR Coins!') {
                $row_color = 'background-color: #ffeb3b;';
            } elseif ($spin['prize_won'] === '200 QR Coins') {
                $row_color = 'background-color: #ffcdd2;';
            }
            
            echo "<tr style='{$row_color}'>";
            echo "<td>{$spin['user_id']}</td>";
            echo "<td>{$spin['prize_won']}</td>";
            echo "<td>{$spin['prize_points']}</td>";
            echo "<td>" . ($spin['is_big_win'] ? 'Yes' : 'No') . "</td>";
            echo "<td>{$spin['spin_time']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No recent spin results found.</p>";
    }
} catch (Exception $e) {
    echo "<p>Error accessing spin_results table: " . $e->getMessage() . "</p>";
}

// 6. Summary and recommendations
echo "<h2>6. Summary & Recommendations</h2>";

echo "<div style='background-color: #e3f2fd; padding: 15px; border-radius: 5px;'>";
echo "<h3>🔍 Analysis Results:</h3>";
echo "<ul>";
echo "<li><strong>Total Prizes:</strong> " . count($specific_rewards) . " (8 prizes)</li>";
echo "<li><strong>Total Weight:</strong> {$total_weight}</li>";
echo "<li><strong>500 QR Coins Weight:</strong> 7 (7% expected probability)</li>";
echo "<li><strong>200 QR Coins Weight:</strong> 12 (12% expected probability)</li>";
echo "</ul>";

echo "<h3>🚨 Potential Issues:</h3>";
echo "<ul>";
echo "<li><strong>Visual vs Server Mismatch:</strong> The JavaScript animation was not synchronized with server-side prize selection</li>";
echo "<li><strong>Multiple Spin Systems:</strong> There are two different spin wheel systems that could cause confusion</li>";
echo "<li><strong>Weight Distribution:</strong> Verify that the weight-based selection is working correctly</li>";
echo "</ul>";

echo "<h3>✅ Fixes Applied:</h3>";
echo "<ul>";
echo "<li><strong>Server-Side Pre-determination:</strong> Prize is now selected on server before animation starts</li>";
echo "<li><strong>Angle Calculation:</strong> Visual animation now lands on the correct prize</li>";
echo "<li><strong>Synchronization:</strong> JavaScript uses server-determined angle for consistent results</li>";
echo "</ul>";
echo "</div>";

echo "<h2>7. Test the Fixed Spin Wheel</h2>";
echo "<p><a href='/user/spin.php' target='_blank' style='background-color: #4CAF50; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🎯 Test Spin Wheel Now</a></p>";
?> 