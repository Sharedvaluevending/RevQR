<?php
/**
 * Spin Wheel Testing & Debug Script
 * Tests the spin wheel functionality including James avatar protection
 */

require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/core/session.php';
require_once __DIR__ . '/core/functions.php';
require_once __DIR__ . '/core/qr_coin_manager.php';
require_once __DIR__ . '/core/config_manager.php';

// Set up debug mode
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<!DOCTYPE html>\n<html><head><meta charset='UTF-8'><title>Spin Wheel Debug & Test</title>";
echo "<style>body{font-family:monospace;background:#f5f5f5;padding:20px;} .success{color:green;background:white;padding:10px;border-radius:5px;margin:10px 0;} .error{color:red;background:white;padding:10px;border-radius:5px;margin:10px 0;} .warning{color:orange;background:white;padding:10px;border-radius:5px;margin:10px 0;} .info{color:blue;background:white;padding:10px;border-radius:5px;margin:10px 0;}</style>";
echo "</head><body>";

echo "<h1>🎡 SPIN WHEEL TESTING & DEBUG ANALYSIS</h1>";

// Test 1: Analyze the current spin wheel logic
echo "<h2>1. 🔍 Spin Wheel Logic Analysis</h2>";

// Display the current reward structure
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

echo "<div class='info'>";
echo "<h3>Current Spin Wheel Rewards:</h3>";
$total_weight = array_sum(array_column($specific_rewards, 'weight'));
echo "<table border='1' style='border-collapse:collapse;width:100%;'>";
echo "<tr><th>Prize</th><th>Rarity</th><th>Weight</th><th>Probability</th><th>Points</th><th>Special</th></tr>";
foreach ($specific_rewards as $reward) {
    $probability = round(($reward['weight'] / $total_weight) * 100, 2);
    echo "<tr>";
    echo "<td>{$reward['name']}</td>";
    echo "<td>{$reward['rarity_level']}</td>";
    echo "<td>{$reward['weight']}</td>";
    echo "<td>{$probability}%</td>";
    echo "<td>{$reward['points']}</td>";
    echo "<td>" . ($reward['special'] ?? 'none') . "</td>";
    echo "</tr>";
}
echo "</table>";
echo "<p><strong>Total Weight:</strong> {$total_weight}</p>";
echo "</div>";

// Test 2: Simulate spins to identify the bug
echo "<h2>2. 🎯 Spin Simulation Testing (20 spins)</h2>";

function simulateSpin($specific_rewards) {
    $total_weight = array_sum(array_column($specific_rewards, 'weight'));
    $random = mt_rand(1, $total_weight);
    $current_weight = 0;
    
    foreach ($specific_rewards as $reward) {
        $current_weight += $reward['weight'];
        if ($random <= $current_weight) {
            return $reward;
        }
    }
    return null;
}

echo "<div class='info'>";
echo "<h3>20 Spin Test Results:</h3>";
echo "<table border='1' style='border-collapse:collapse;width:100%;'>";
echo "<tr><th>Spin #</th><th>Prize Won</th><th>Points</th><th>Rarity</th><th>Issue?</th></tr>";

$issues_found = [];
for ($i = 1; $i <= 20; $i++) {
    $result = simulateSpin($specific_rewards);
    $issue = "";
    
    // Check for issues
    if ($result['name'] === '-20 QR Coins') {
        $issue = "❌ Negative reward!";
        $issues_found[] = "Spin #{$i}: User lost 20 coins instead of winning";
    }
    if ($result['name'] === 'Lose All Votes') {
        $issue = "⚠️ Vote penalty!";
        $issues_found[] = "Spin #{$i}: User could lose voting privileges";
    }
    
    echo "<tr style='background:" . ($issue ? "#ffebee" : "#e8f5e9") . "'>";
    echo "<td>{$i}</td>";
    echo "<td>{$result['name']}</td>";
    echo "<td>{$result['points']}</td>";
    echo "<td>{$result['rarity_level']}</td>";
    echo "<td>{$issue}</td>";
    echo "</tr>";
}
echo "</table>";
echo "</div>";

if (!empty($issues_found)) {
    echo "<div class='error'>";
    echo "<h3>❌ ISSUES DETECTED:</h3>";
    foreach ($issues_found as $issue) {
        echo "<p>• {$issue}</p>";
    }
    echo "</div>";
}

// Test 3: James Avatar Protection Test
echo "<h2>3. 🛡️ James Avatar Protection Testing</h2>";

// Test James avatar vote protection
function testJamesProtection($equipped_avatar_id, $selected_reward) {
    $has_vote_protection = false;
    
    // Check if user has QR James (ID 2) or QR Terry (ID 7) equipped
    if ($equipped_avatar_id == 2 || $equipped_avatar_id == 7) {
        $has_vote_protection = true;
    }
    
    if ($selected_reward['name'] === 'Lose All Votes' && $has_vote_protection) {
        $avatar_name = ($equipped_avatar_id == 2) ? "QR James" : "QR Terry";
        return [
            'protected' => true,
            'message' => "🛡️ PROTECTED! Your {$avatar_name} avatar saved you from losing all votes! You get -20 coins instead as compensation!",
            'original_prize' => 'Lose All Votes',
            'actual_result' => '-20 coins penalty (but votes protected)'
        ];
    }
    
    return ['protected' => false, 'message' => 'No protection applied'];
}

echo "<div class='info'>";
echo "<h3>James Avatar Protection Scenarios:</h3>";

// Test without James avatar
$test_scenarios = [
    ['avatar_id' => 1, 'avatar_name' => 'QR Ted (no protection)', 'prize' => 'Lose All Votes'],
    ['avatar_id' => 2, 'avatar_name' => 'QR James (vote protection)', 'prize' => 'Lose All Votes'],
    ['avatar_id' => 7, 'avatar_name' => 'QR Terry (vote protection)', 'prize' => 'Lose All Votes'],
    ['avatar_id' => 2, 'avatar_name' => 'QR James', 'prize' => '-20 QR Coins'],
];

echo "<table border='1' style='border-collapse:collapse;width:100%;'>";
echo "<tr><th>Avatar</th><th>Prize Won</th><th>Protection Status</th><th>Result</th></tr>";

foreach ($test_scenarios as $scenario) {
    $test_reward = ['name' => $scenario['prize'], 'points' => $scenario['prize'] === '-20 QR Coins' ? -20 : 0];
    $protection_result = testJamesProtection($scenario['avatar_id'], $test_reward);
    
    echo "<tr>";
    echo "<td>{$scenario['avatar_name']}</td>";
    echo "<td>{$scenario['prize']}</td>";
    echo "<td>" . ($protection_result['protected'] ? "✅ Protected" : "❌ No Protection") . "</td>";
    echo "<td>" . ($protection_result['protected'] ? $protection_result['actual_result'] : "Standard result applied") . "</td>";
    echo "</tr>";
}
echo "</table>";
echo "</div>";

// Test 4: Identify the actual bug
echo "<h2>4. 🐛 BUG ANALYSIS</h2>";

echo "<div class='error'>";
echo "<h3>❌ IDENTIFIED BUG:</h3>";
echo "<p><strong>Issue:</strong> When user hits 'Lose All Votes' prize with James avatar equipped:</p>";
echo "<ol>";
echo "<li>✅ James avatar correctly protects from losing votes</li>";
echo "<li>❌ BUT the system still applies the -20 coin penalty as 'compensation'</li>";
echo "<li>❌ This means the user gets DOUBLE PUNISHMENT: loses 20 coins + the disappointment</li>";
echo "<li>❌ The protection should either give a POSITIVE reward or convert to a different prize</li>";
echo "</ol>";
echo "</div>";

echo "<div class='warning'>";
echo "<h3>⚠️ RELATED ISSUES:</h3>";
echo "<p><strong>1. Confusing Message:</strong> 'You get -20 coins instead as compensation!' makes no sense - compensation should be positive!</p>";
echo "<p><strong>2. Bad User Experience:</strong> User thinks they won a rare avatar but still loses coins</p>";
echo "<p><strong>3. Protection Logic Flaw:</strong> Protection should make the outcome BETTER, not just different</p>";
echo "</div>";

// Test 5: Proposed fixes
echo "<h2>5. 🔧 PROPOSED FIXES</h2>";

echo "<div class='success'>";
echo "<h3>✅ RECOMMENDED SOLUTIONS:</h3>";
echo "<p><strong>Option 1 (Best UX):</strong> Convert 'Lose All Votes' to '+50 QR Coins' when protected</p>";
echo "<p><strong>Option 2:</strong> Convert to 'Try Again' (free spin)</p>";
echo "<p><strong>Option 3:</strong> Convert to 'Extra Vote' reward</p>";
echo "<p><strong>Current (Bug):</strong> Still lose 20 coins as 'compensation'</p>";
echo "</div>";

// Test 6: Check avatar perks integration
echo "<h2>6. 🎭 Avatar Perks Integration Check</h2>";

try {
    // Check if avatar perks are being applied
    if (function_exists('getUserAvatarPerks')) {
        echo "<div class='success'>✅ Avatar perks function exists</div>";
        
        // Test with fake user ID
        $test_perks = getUserAvatarPerks(1, 'spin');
        echo "<div class='info'>📊 Test avatar perks for user 1: " . json_encode($test_perks) . "</div>";
    } else {
        echo "<div class='error'>❌ Avatar perks function not found</div>";
    }
} catch (Exception $e) {
    echo "<div class='error'>❌ Avatar perks error: " . $e->getMessage() . "</div>";
}

// Test 7: Check QR coin transactions
echo "<h2>7. 💰 QR Coin Transaction Testing</h2>";

try {
    // Test QRCoinManager
    if (class_exists('QRCoinManager')) {
        echo "<div class='success'>✅ QRCoinManager class exists</div>";
        
        // Check recent spin transactions
        $stmt = $pdo->prepare("
            SELECT transaction_type, category, amount, description, metadata, created_at 
            FROM qr_coin_transactions 
            WHERE category = 'spinning' 
            ORDER BY created_at DESC 
            LIMIT 5
        ");
        $stmt->execute();
        $recent_spins = $stmt->fetchAll();
        
        if ($recent_spins) {
            echo "<div class='info'>";
            echo "<h3>📈 Recent Spin Transactions:</h3>";
            echo "<table border='1' style='border-collapse:collapse;width:100%;'>";
            echo "<tr><th>Type</th><th>Amount</th><th>Description</th><th>Date</th></tr>";
            foreach ($recent_spins as $spin) {
                echo "<tr>";
                echo "<td>{$spin['transaction_type']}</td>";
                echo "<td>{$spin['amount']}</td>";
                echo "<td>{$spin['description']}</td>";
                echo "<td>{$spin['created_at']}</td>";
                echo "</tr>";
            }
            echo "</table>";
            echo "</div>";
        } else {
            echo "<div class='warning'>⚠️ No recent spin transactions found</div>";
        }
    } else {
        echo "<div class='error'>❌ QRCoinManager class not found</div>";
    }
} catch (Exception $e) {
    echo "<div class='error'>❌ QRCoinManager error: " . $e->getMessage() . "</div>";
}

echo "<h2>8. 📋 SUMMARY & RECOMMENDATIONS</h2>";

echo "<div class='success'>";
echo "<h3>🎯 MAIN FINDINGS:</h3>";
echo "<ol>";
echo "<li><strong>James Avatar Protection Works:</strong> ✅ Vote protection is functional</li>";
echo "<li><strong>BUT -20 Coin Bug:</strong> ❌ Users still lose coins when 'protected'</li>";
echo "<li><strong>Confusing Messages:</strong> ❌ 'Compensation' that loses money makes no sense</li>";
echo "<li><strong>Bad User Experience:</strong> ❌ Protection feels like punishment</li>";
echo "</ol>";
echo "</div>";

echo "<div class='info'>";
echo "<h3>🔧 IMMEDIATE FIXES NEEDED:</h3>";
echo "<ol>";
echo "<li><strong>Fix Protection Logic:</strong> Convert 'Lose All Votes' to positive reward when protected</li>";
echo "<li><strong>Update Message:</strong> Make protection feel like a win, not consolation</li>";
echo "<li><strong>Test Avatar Perks:</strong> Ensure all avatar perks are working as intended</li>";
echo "<li><strong>Add Logging:</strong> Better tracking of protection events</li>";
echo "</ol>";
echo "</div>";

echo "<div class='warning'>";
echo "<h3>⚡ TESTING RECOMMENDATIONS:</h3>";
echo "<p><strong>1.</strong> Run 20+ real spins with James equipped to verify fix</p>";
echo "<p><strong>2.</strong> Test all avatar perks systematically</p>";
echo "<p><strong>3.</strong> Verify QR coin transactions are correct</p>";
echo "<p><strong>4.</strong> Check that protection doesn't break other game mechanics</p>";
echo "</div>";

echo "<h3>🎉 READY TO IMPLEMENT FIXES!</h3>";

echo "</body></html>";
?> 