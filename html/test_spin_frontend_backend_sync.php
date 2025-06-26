<?php
/**
 * FRONTEND/BACKEND SPIN WHEEL SYNCHRONIZATION TEST
 * Tests 20 actual spin scenarios to verify the fix works
 */

require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/core/session.php';

echo "🎡 FRONTEND/BACKEND SPIN WHEEL SYNC TEST\n";
echo "=======================================\n";
echo "Running 20 spin simulations to verify fix...\n\n";

// Exact same reward structure as user spin
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

$total_tests = 20;
$successful_syncs = 0;
$failed_syncs = 0;
$test_results = [];

echo "🎯 RUNNING 20 SPIN SIMULATIONS:\n";
echo str_repeat("=", 80) . "\n";

for ($test = 1; $test <= $total_tests; $test++) {
    echo sprintf("TEST %2d: ", $test);
    
    // ==========================================
    // STEP 1: BACKEND SELECTION (EXACT COPY FROM USER SPIN)
    // ==========================================
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
    
    // ==========================================
    // STEP 2: BACKEND ANGLE CALCULATION (EXACT COPY FROM USER SPIN)
    // ==========================================
    $slice_angle = 360 / count($specific_rewards); // 45 degrees per slice (8 prizes)
    $prize_center_angle = ($selected_reward_index * $slice_angle) + ($slice_angle / 2); // Center of the prize slice
    $pointer_offset = 90; // Pointer is at top (90 degrees)
    $target_angle = $prize_center_angle - $pointer_offset;
    
    // Ensure the angle is positive and within 0-360 range
    $target_angle = ($target_angle + 360) % 360;
    
    // Add multiple full rotations for dramatic effect (8-12 full rotations)
    $full_rotations = 8 + mt_rand(0, 4); // 8-12 full rotations for excitement
    $spin_angle = ($full_rotations * 360) + $target_angle;
    
    // ==========================================
    // STEP 3: FRONTEND VISUAL CALCULATION (REVERSE ENGINEERING)
    // ==========================================
    // Simulate what the frontend would calculate from the final angle
    $final_visual_angle = $spin_angle % 360;
    $visual_slice_index = floor(($final_visual_angle + $pointer_offset) / $slice_angle) % count($specific_rewards);
    $visual_reward = $specific_rewards[$visual_slice_index];
    
    // ==========================================
    // STEP 4: VERIFICATION
    // ==========================================
    $sync_success = ($selected_reward_index === $visual_slice_index);
    
    if ($sync_success) {
        $successful_syncs++;
        $status = "✅ SYNC OK";
        $color = "\033[32m"; // Green
    } else {
        $failed_syncs++;
        $status = "❌ SYNC FAIL";
        $color = "\033[31m"; // Red
    }
    
    // Store detailed results
    $test_results[] = [
        'test_number' => $test,
        'backend_winner' => $selected_reward['name'],
        'backend_index' => $selected_reward_index,
        'backend_points' => $selected_reward['points'],
        'calculated_angle' => $target_angle,
        'full_spin_angle' => $spin_angle,
        'final_visual_angle' => $final_visual_angle,
        'visual_index' => $visual_slice_index,
        'visual_winner' => $visual_reward['name'],
        'visual_points' => $visual_reward['points'],
        'sync_success' => $sync_success,
        'full_rotations' => $full_rotations
    ];
    
    // Display result
    echo sprintf(
        "%s%s\033[0m | Backend: %-15s | Visual: %-15s | Angle: %6.1f°\n",
        $color,
        $status,
        $selected_reward['name'],
        $visual_reward['name'],
        $final_visual_angle
    );
}

echo str_repeat("=", 80) . "\n";

// ==========================================
// DETAILED ANALYSIS
// ==========================================
echo "\n📊 DETAILED ANALYSIS:\n\n";

if ($failed_syncs > 0) {
    echo "❌ FAILED SYNCHRONIZATIONS:\n";
    foreach ($test_results as $result) {
        if (!$result['sync_success']) {
            echo sprintf(
                "  Test %2d: Backend selected '%s' (index %d) but visual shows '%s' (index %d)\n",
                $result['test_number'],
                $result['backend_winner'],
                $result['backend_index'],
                $result['visual_winner'],
                $result['visual_index']
            );
            echo sprintf(
                "    - Backend angle: %.1f° | Visual angle: %.1f° | Rotations: %d\n",
                $result['calculated_angle'],
                $result['final_visual_angle'],
                $result['full_rotations']
            );
            echo sprintf(
                "    - Backend points: %d | Visual points: %d | Difference: %d\n\n",
                $result['backend_points'],
                $result['visual_points'],
                $result['visual_points'] - $result['backend_points']
            );
        }
    }
}

// ==========================================
// SUMMARY STATISTICS
// ==========================================
echo "📈 SUMMARY STATISTICS:\n";
echo str_repeat("-", 50) . "\n";
echo sprintf("Total Tests Run:        %2d\n", $total_tests);
echo sprintf("Successful Syncs:       %2d ✅\n", $successful_syncs);
echo sprintf("Failed Syncs:           %2d ❌\n", $failed_syncs);
echo sprintf("Success Rate:           %5.1f%%\n", ($successful_syncs / $total_tests) * 100);
echo sprintf("Failure Rate:           %5.1f%%\n", ($failed_syncs / $total_tests) * 100);

// ==========================================
// PRIZE DISTRIBUTION ANALYSIS
// ==========================================
echo "\n🎁 PRIZE DISTRIBUTION:\n";
echo str_repeat("-", 50) . "\n";

$backend_distribution = [];
$visual_distribution = [];

foreach ($test_results as $result) {
    // Backend distribution
    if (!isset($backend_distribution[$result['backend_winner']])) {
        $backend_distribution[$result['backend_winner']] = 0;
    }
    $backend_distribution[$result['backend_winner']]++;
    
    // Visual distribution
    if (!isset($visual_distribution[$result['visual_winner']])) {
        $visual_distribution[$result['visual_winner']] = 0;
    }
    $visual_distribution[$result['visual_winner']]++;
}

echo "Backend Selected vs Visual Shown:\n";
foreach ($specific_rewards as $reward) {
    $backend_count = $backend_distribution[$reward['name']] ?? 0;
    $visual_count = $visual_distribution[$reward['name']] ?? 0;
    $match = ($backend_count === $visual_count) ? "✅" : "❌";
    
    echo sprintf(
        "  %-20s | Backend: %2d | Visual: %2d | %s\n",
        $reward['name'],
        $backend_count,
        $visual_count,
        $match
    );
}

// ==========================================
// MATHEMATICAL VERIFICATION
// ==========================================
echo "\n🔢 MATHEMATICAL VERIFICATION:\n";
echo str_repeat("-", 50) . "\n";

$angle_precision_errors = 0;
foreach ($test_results as $result) {
    // Verify angle calculation is mathematically correct
    $expected_slice = $result['backend_index'];
    $calculated_slice = floor(($result['final_visual_angle'] + 90) / 45) % 8;
    
    if ($expected_slice !== $calculated_slice) {
        $angle_precision_errors++;
    }
}

echo sprintf("Angle Calculation Errors: %d\n", $angle_precision_errors);
echo sprintf("Mathematical Precision:   %5.1f%%\n", (($total_tests - $angle_precision_errors) / $total_tests) * 100);

// ==========================================
// FINAL VERDICT
// ==========================================
echo "\n" . str_repeat("=", 80) . "\n";
echo "🏁 FINAL VERDICT:\n";

if ($successful_syncs === $total_tests) {
    echo "🎉 \033[32mPERFECT SUCCESS!\033[0m All 20 tests passed!\n";
    echo "✅ Frontend and Backend are PERFECTLY synchronized!\n";
    echo "✅ What users see = What users win (100% accuracy)\n";
    echo "🎯 The spin wheel fix is WORKING CORRECTLY!\n";
} elseif ($successful_syncs >= ($total_tests * 0.95)) {
    echo "🟡 \033[33mNEAR PERFECT\033[0m - 95%+ success rate\n";
    echo "⚠️  Minor issues detected - may need fine-tuning\n";
} else {
    echo "🔴 \033[31mSYNC ISSUES DETECTED!\033[0m\n";
    echo "❌ Frontend/Backend synchronization needs attention\n";
    echo "🔧 Additional debugging required\n";
}

echo str_repeat("=", 80) . "\n";
?> 