<?php
echo "<h1>Spin Wheel Test Results</h1>\n";

// Backend prize array (exactly as in spin.php)
$backend_rewards = [
    ['name' => 'Lord Pixel!', 'rarity_level' => 11, 'weight' => 1, 'special' => 'lord_pixel', 'points' => 0],
    ['name' => 'Try Again', 'rarity_level' => 2, 'weight' => 20, 'special' => 'spin_again', 'points' => 0],
    ['name' => 'Extra Vote', 'rarity_level' => 2, 'weight' => 15, 'points' => 0],
    ['name' => '50 QR Coins', 'rarity_level' => 3, 'weight' => 20, 'points' => 50],
    ['name' => '-20 QR Coins', 'rarity_level' => 5, 'weight' => 15, 'points' => -20],
    ['name' => '200 QR Coins', 'rarity_level' => 7, 'weight' => 12, 'points' => 200],
    ['name' => 'Lose All Votes', 'rarity_level' => 8, 'weight' => 10, 'points' => 0],
    ['name' => '500 QR Coins!', 'rarity_level' => 10, 'weight' => 7, 'points' => 500]
];

// Frontend prize array (exactly as in spin.php JavaScript)
$frontend_rewards = [
    ['name' => 'Lord Pixel!'],
    ['name' => 'Try Again'],
    ['name' => 'Extra Vote'],
    ['name' => '50 QR Coins'],
    ['name' => '-20 QR Coins'],
    ['name' => '200 QR Coins'],
    ['name' => 'Lose All Votes'],
    ['name' => '500 QR Coins!']
];

echo "<h2>Frontend vs Backend Comparison:</h2>\n";
echo "<table border='1'>\n";
echo "<tr><th>Index</th><th>Backend Prize</th><th>Frontend Prize</th><th>Points</th><th>Match?</th></tr>\n";

for ($i = 0; $i < count($backend_rewards); $i++) {
    $backend_name = $backend_rewards[$i]['name'];
    $frontend_name = $frontend_rewards[$i]['name'];
    $points = $backend_rewards[$i]['points'];
    $match = ($backend_name === $frontend_name) ? "✅ YES" : "❌ NO";
    
    echo "<tr>";
    echo "<td>$i</td>";
    echo "<td>$backend_name</td>";
    echo "<td>$frontend_name</td>";
    echo "<td>$points</td>";
    echo "<td>$match</td>";
    echo "</tr>\n";
}

echo "</table>\n";

echo "<h2>The Issue:</h2>\n";
echo "<p><strong>Every spin gets BASE REWARDS regardless of the prize:</strong></p>\n";
echo "<ul>\n";
echo "<li>Base spin reward: 15 QR coins</li>\n";
echo "<li>Daily bonus (first spin): +50 QR coins</li>\n";
echo "<li>Total base reward: 65 QR coins</li>\n";
echo "</ul>\n";

echo "<h3>When you land on 'Extra Vote':</h3>\n";
echo "<ul>\n";
echo "<li>Prize coins: 0 (Extra Vote gives voting privilege, not coins)</li>\n";
echo "<li>Base reward: 15-65 coins (still awarded)</li>\n";
echo "<li>Total received: 15-65 coins from base rewards</li>\n";
echo "</ul>\n";

echo "<h3>If you're getting 200 coins:</h3>\n";
echo "<p>You might actually be landing on the '200 QR Coins' prize (index 5) instead of 'Extra Vote' (index 2).</p>\n";
echo "<p>This could be due to a visual/calculation mismatch in the wheel positioning.</p>\n";

echo "<h2>Next Steps:</h2>\n";
echo "<ol>\n";
echo "<li>Use the debug tools at /html/user/spin-debug.php</li>\n";
echo "<li>Check the exact prize you land on vs what you receive</li>\n";
echo "<li>Verify the wheel positioning matches the backend array</li>\n";
echo "</ol>\n";
?> 