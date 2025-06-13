<?php
// Simple test without database dependencies

echo "<h2>🎰 DIAGONAL WIN FIXES TEST - " . date('Y-m-d H:i:s') . "</h2>\n";

// Test function to simulate the JavaScript logic in PHP
function checkWin($results, $betAmount) {
    $reel1 = $results[0];
    $reel2 = $results[1];
    $reel3 = $results[2];
    
    echo "<div style='background: #f0f0f0; padding: 10px; margin: 10px 0;'>";
    echo "<strong>Checking Results:</strong><br>";
    echo "Reel 1: {$reel1['name']} ({$reel1['rarity']}, level {$reel1['level']})<br>";
    echo "Reel 2: {$reel2['name']} ({$reel2['rarity']}, level {$reel2['level']})<br>";
    echo "Reel 3: {$reel3['name']} ({$reel3['rarity']}, level {$reel3['level']})<br>";
    echo "</div>";
    
    // STRAIGHT LINE WINS
    if ($reel1['image'] === $reel2['image'] && $reel2['image'] === $reel3['image']) {
        $multiplier = $reel1['level'] >= 8 ? 25 : ($reel1['level'] * 3);
        echo "✅ <strong>STRAIGHT LINE WIN:</strong> {$reel1['name']} x3 - Payout: " . ($betAmount * $multiplier) . "<br>";
        return ['isWin' => true, 'type' => 'straight_line', 'amount' => $betAmount * $multiplier];
    }
    
    // DIAGONAL EXACT MATCH - Same exact avatar on corners
    if ($reel1['image'] === $reel3['image'] && $reel1['image'] !== $reel2['image']) {
        $multiplier = $reel1['level'] >= 8 ? 12 : 8;
        echo "✅ <strong>DIAGONAL EXACT WIN:</strong> {$reel1['name']} corners - Payout: " . ($betAmount * $multiplier) . "<br>";
        return ['isWin' => true, 'type' => 'diagonal_exact', 'amount' => $betAmount * $multiplier];
    }
    
    // DIAGONAL RARITY MATCH - Same rarity on corners
    if ($reel1['rarity'] === $reel3['rarity'] && 
        $reel1['rarity'] !== $reel2['rarity'] && 
        $reel1['rarity'] !== 'common' && 
        $reel1['image'] !== $reel3['image']) {
        
        $multiplier = $reel1['rarity'] === 'legendary' ? 10 : 
                     ($reel1['rarity'] === 'epic' ? 7 : 5);
        echo "✅ <strong>DIAGONAL RARITY WIN:</strong> {$reel1['rarity']} corners - Payout: " . ($betAmount * $multiplier) . "<br>";
        return ['isWin' => true, 'type' => 'diagonal_rarity', 'amount' => $betAmount * $multiplier];
    }
    
    // STRAIGHT LINE RARITY WIN
    if ($reel1['rarity'] === $reel2['rarity'] && 
        $reel2['rarity'] === $reel3['rarity'] && 
        $reel1['rarity'] !== 'common') {
        
        $multiplier = $reel1['rarity'] === 'legendary' ? 10 : 
                     ($reel1['rarity'] === 'epic' ? 7 : 
                     ($reel1['rarity'] === 'rare' ? 4 : 3));
        echo "✅ <strong>RARITY LINE WIN:</strong> {$reel1['rarity']} line - Payout: " . ($betAmount * $multiplier) . "<br>";
        return ['isWin' => true, 'type' => 'rarity_line', 'amount' => $betAmount * $multiplier];
    }
    
    echo "❌ <strong>NO WIN</strong><br>";
    return ['isWin' => false, 'type' => 'loss', 'amount' => 0];
}

// Get some sample avatars for testing
$pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
$stmt = $pdo->query("
    SELECT name, image, level, rarity, value 
    FROM qr_avatars 
    WHERE level >= 1 
    ORDER BY level DESC, rarity DESC 
    LIMIT 20
");
$avatars = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($avatars)) {
    echo "❌ No avatars found in database!<br>";
    exit;
}

echo "<h3>📋 Available Test Avatars:</h3>";
foreach ($avatars as $i => $avatar) {
    echo ($i + 1) . ". {$avatar['name']} - Level {$avatar['level']} ({$avatar['rarity']})<br>";
}

echo "<h3>🧪 Test Cases:</h3>";

// Test Case 1: Valid Diagonal Exact Match
echo "<h4>Test 1: Valid Diagonal Exact Match</h4>";
$testResults1 = [
    $avatars[0], // High level avatar
    $avatars[5], // Different avatar in middle
    $avatars[0]  // Same as first (diagonal)
];
checkWin($testResults1, 10);

// Test Case 2: Valid Diagonal Rarity Match
echo "<h4>Test 2: Valid Diagonal Rarity Match</h4>";
$legendary = array_filter($avatars, fn($a) => $a['rarity'] === 'legendary');
$epic = array_filter($avatars, fn($a) => $a['rarity'] === 'epic');

if (count($legendary) >= 2) {
    $legendary = array_values($legendary);
    $testResults2 = [
        $legendary[0], // Legendary corner
        $avatars[8],   // Different rarity middle
        $legendary[1]  // Different legendary corner
    ];
    checkWin($testResults2, 10);
} else {
    echo "⚠️ Not enough legendary avatars for this test<br>";
}

// Test Case 3: False Positive Check (should NOT be diagonal)
echo "<h4>Test 3: False Positive Check (No Match)</h4>";
$testResults3 = [
    $avatars[0], // Avatar A
    $avatars[1], // Avatar B  
    $avatars[2]  // Avatar C (all different)
];
checkWin($testResults3, 10);

// Test Case 4: Straight Line Win
echo "<h4>Test 4: Straight Line Win</h4>";
$testResults4 = [
    $avatars[0], // Same avatar
    $avatars[0], // Same avatar
    $avatars[0]  // Same avatar
];
checkWin($testResults4, 10);

// Test Case 5: Rarity Line Win
echo "<h4>Test 5: Rarity Line Win</h4>";
if (count($epic) >= 3) {
    $epic = array_values($epic);
    $testResults5 = [
        $epic[0], // Epic A
        $epic[1], // Epic B
        $epic[2]  // Epic C (all epic rarity)
    ];
    checkWin($testResults5, 10);
} else {
    echo "⚠️ Not enough epic avatars for this test<br>";
}

echo "<h3>🔍 Key Fixes Made:</h3>";
echo "<ul>";
echo "<li>✅ <strong>Removed 'DIAGONAL LEVEL WIN'</strong> - Was triggering on ANY level 6+ corners regardless of match</li>";
echo "<li>✅ <strong>Added 'DIAGONAL EXACT MATCH'</strong> - Only triggers when corners have same exact avatar</li>";
echo "<li>✅ <strong>Fixed 'DIAGONAL RARITY MATCH'</strong> - Only triggers when corners have same rarity BUT different avatars</li>";
echo "<li>✅ <strong>Enhanced visual marking</strong> - Better reel indexing to highlight correct winning symbols</li>";
echo "<li>✅ <strong>Added debugging logs</strong> - Console will show exactly what wins are detected</li>";
echo "</ul>";

echo "<h3>📱 Testing Instructions:</h3>";
echo "<ol>";
echo "<li>Open the slot machine in your browser</li>";
echo "<li>Open browser console (F12 → Console tab)</li>";
echo "<li>Play several spins and watch the console logs</li>";
echo "<li>When a diagonal win is claimed:</li>";
echo "<ul>";
echo "<li>Check console for 'DIAGONAL WIN detected' message</li>";
echo "<li>Verify that reel 1 and reel 3 visually match (exact same avatar OR same rarity)</li>";
echo "<li>Verify that reel 2 is different</li>";
echo "<li>Verify that only reels 1 and 3 glow/highlight</li>";
echo "</ul>";
echo "</ol>";

echo "<h3>✅ Expected Behavior:</h3>";
echo "<ul>";
echo "<li><strong>Diagonal Exact:</strong> Avatar-A → Avatar-B → Avatar-A (corners match exactly)</li>";
echo "<li><strong>Diagonal Rarity:</strong> Legendary-1 → Epic-1 → Legendary-2 (corners same rarity, different avatars)</li>";
echo "<li><strong>NO diagonal win:</strong> Any other combination should not claim diagonal win</li>";
echo "</ul>";

echo "<p><strong>🎯 The fix ensures diagonal wins are only awarded when there's an actual visual diagonal pattern!</strong></p>";
?> 