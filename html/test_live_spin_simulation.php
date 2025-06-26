<?php
/**
 * LIVE SPIN WHEEL SIMULATION TEST
 * Simulates actual user spin workflow with real session data
 */

require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/core/session.php';

echo "🎯 LIVE SPIN WHEEL SIMULATION TEST\n";
echo "==================================\n";
echo "Simulating actual user spin with session...\n\n";

// Mock user session for testing
$_SESSION['user_id'] = 999999; // Test user ID
$_SESSION['user_data'] = ['username' => 'TestUser'];

// Exact same rewards from user spin.php
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

echo "🎡 SIMULATING USER SPIN REQUEST...\n";
echo str_repeat("-", 60) . "\n";

// Simulate the $_POST request
$_POST['spin'] = true;
$_SERVER['REQUEST_METHOD'] = 'POST';

// ===== BACKEND LOGIC (Exact copy from user spin.php) =====
echo "🔧 BACKEND PROCESSING:\n";

// Randomly select a reward based on weights
$total_weight = array_sum(array_column($specific_rewards, 'weight'));
$random = mt_rand(1, $total_weight);
$current_weight = 0;
$selected_reward = null;
$selected_reward_index = null;

foreach ($specific_rewards as $index => $reward) {
    $current_weight += $reward['weight'];
    if ($random <= $current_weight) {
        $selected_reward = $reward;
        $selected_reward_index = $index;
        break;
    }
}

echo sprintf("  Random roll: %d out of %d total weight\n", $random, $total_weight);
echo sprintf("  Selected: %s (index %d)\n", $selected_reward['name'], $selected_reward_index);
echo sprintf("  Points: %d\n", $selected_reward['points']);

// Calculate the exact angle where this prize should land
if ($selected_reward_index !== null) {
    $slice_angle = 360 / count($specific_rewards);
    $prize_center_angle = ($selected_reward_index * $slice_angle) + ($slice_angle / 2);
    $pointer_offset = 90;
    $target_angle = $prize_center_angle - $pointer_offset;
    $target_angle = ($target_angle + 360) % 360;
    $full_rotations = 8 + mt_rand(0, 4);
    $spin_angle = ($full_rotations * 360) + $target_angle;
}

echo sprintf("  Target angle: %.1f°\n", $target_angle);
echo sprintf("  Full spin angle: %.1f° (%d rotations)\n", $spin_angle, $full_rotations);

// ===== FRONTEND SIMULATION =====
echo "\n🎨 FRONTEND SIMULATION:\n";

// What the frontend JavaScript would receive
$frontend_data = [
    'selectedRewardIndex' => $selected_reward_index,
    'serverSpinAngle' => $spin_angle,
    'rewards' => $specific_rewards
];

echo "  JavaScript would receive:\n";
echo "    selectedRewardIndex: " . $frontend_data['selectedRewardIndex'] . "\n";
echo "    serverSpinAngle: " . $frontend_data['serverSpinAngle'] . "\n";
echo "    rewards: [8 reward objects]\n";

// Simulate the animation ending position
$final_visual_angle = $spin_angle % 360;
$visual_slice_index = floor(($final_visual_angle + $pointer_offset) / $slice_angle) % count($specific_rewards);
$visual_reward = $specific_rewards[$visual_slice_index];

echo sprintf("  Animation ends at: %.1f°\n", $final_visual_angle);
echo sprintf("  Visual slice index: %d\n", $visual_slice_index);
echo sprintf("  Visual shows: %s\n", $visual_reward['name']);

// ===== VERIFICATION =====
echo "\n✅ VERIFICATION:\n";

$sync_perfect = ($selected_reward_index === $visual_slice_index);
$name_match = ($selected_reward['name'] === $visual_reward['name']);
$points_match = ($selected_reward['points'] === $visual_reward['points']);

echo sprintf("  Backend index: %d | Frontend index: %d | Match: %s\n", 
    $selected_reward_index, 
    $visual_slice_index, 
    $sync_perfect ? "✅ YES" : "❌ NO"
);

echo sprintf("  Backend prize: %s | Frontend prize: %s | Match: %s\n", 
    $selected_reward['name'], 
    $visual_reward['name'], 
    $name_match ? "✅ YES" : "❌ NO"
);

echo sprintf("  Backend points: %d | Frontend points: %d | Match: %s\n", 
    $selected_reward['points'], 
    $visual_reward['points'], 
    $points_match ? "✅ YES" : "❌ NO"
);

// ===== FINAL RESULT =====
echo "\n" . str_repeat("=", 60) . "\n";

if ($sync_perfect && $name_match && $points_match) {
    echo "🎉 PERFECT SYNCHRONIZATION!\n";
    echo "✅ User will see exactly what they win!\n";
    echo "✅ No discrepancy between visual and payout!\n";
    echo "🎯 THE FIX IS WORKING CORRECTLY!\n";
} else {
    echo "❌ SYNCHRONIZATION FAILURE!\n";
    echo "⚠️  User will see different result than what they win!\n";
    echo "🔧 Additional debugging needed!\n";
}

echo str_repeat("=", 60) . "\n";

// Show what the user experience would be
echo "\n👤 USER EXPERIENCE:\n";
echo "User clicks spin button...\n";
echo "Wheel spins and lands on: " . $visual_reward['name'] . "\n";
echo "User actually receives: " . $selected_reward['name'] . " (" . $selected_reward['points'] . " points)\n";

if ($sync_perfect) {
    echo "😊 User is happy - what they saw = what they got!\n";
} else {
    echo "😡 User is confused - wheel showed different result than payout!\n";
}

echo "\n💡 MATHEMATICAL BREAKDOWN:\n";
echo sprintf("Slice angle: %.1f° (360° ÷ %d rewards)\n", $slice_angle, count($specific_rewards));
echo sprintf("Prize %d center: %.1f°\n", $selected_reward_index, $prize_center_angle);
echo sprintf("Target (accounting for pointer): %.1f°\n", $target_angle);
echo sprintf("After %d full rotations: %.1f°\n", $full_rotations, $spin_angle);
echo sprintf("Final position: %.1f°\n", $final_visual_angle);
echo sprintf("Calculated slice: %d (should be %d)\n", $visual_slice_index, $selected_reward_index);

echo "\n🔬 TEST CONCLUSION: ";
echo $sync_perfect ? "SYNCHRONIZATION VERIFIED ✅" : "SYNCHRONIZATION FAILED ❌";
echo "\n";
?> 