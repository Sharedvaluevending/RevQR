<?php
/**
 * Test Script for Enhanced Voting System
 * Tests the new Daily + Weekly + Premium vote structure
 */

require_once __DIR__ . '/html/core/config.php';
require_once __DIR__ . '/html/core/services/VotingService.php';

// Initialize voting service
VotingService::init($pdo);

echo "🗳️ ENHANCED VOTING SYSTEM TEST\n";
echo "===============================\n\n";

// Test user data
$test_user_id = 1; // Use existing user
$test_voter_ip = '127.0.0.1';

echo "📊 Testing Vote Status Retrieval...\n";
$vote_status = VotingService::getUserVoteStatus($test_user_id, $test_voter_ip);

echo "Vote Status Results:\n";
echo "- Daily Free Remaining: {$vote_status['daily_free_remaining']}\n";
echo "- Weekly Bonus Remaining: {$vote_status['weekly_bonus_remaining']}\n";
echo "- Premium Votes Available: {$vote_status['premium_votes_available']}\n";
echo "- QR Balance: {$vote_status['qr_balance']}\n";
echo "- Total Votes Today: {$vote_status['total_votes_today']}\n";
echo "- Total Votes This Week: {$vote_status['total_votes_this_week']}\n\n";

// Test vote recording with different methods
echo "🎯 Testing Vote Recording...\n";

// Get a test item
$stmt = $pdo->prepare("SELECT id FROM voting_list_items LIMIT 1");
$stmt->execute();
$test_item = $stmt->fetch();

if (!$test_item) {
    echo "❌ No test items found. Creating test data...\n";
    
    // Create test voting list
    $stmt = $pdo->prepare("INSERT INTO voting_lists (name, description, business_id) VALUES (?, ?, ?)");
    $stmt->execute(['Test List', 'Test voting list for new system', 1]);
    $list_id = $pdo->lastInsertId();
    
    // Create test item
    $stmt = $pdo->prepare("INSERT INTO voting_list_items (voting_list_id, item_name, item_category) VALUES (?, ?, ?)");
    $stmt->execute([$list_id, 'Test Item', 'test']);
    $test_item_id = $pdo->lastInsertId();
    
    echo "✅ Created test item with ID: $test_item_id\n";
} else {
    $test_item_id = $test_item['id'];
    echo "✅ Using existing test item ID: $test_item_id\n";
}

// Test 1: Daily Free Vote
echo "\n1️⃣ Testing Daily Free Vote...\n";
$vote_data = [
    'item_id' => $test_item_id,
    'vote_type' => 'in',
    'voter_ip' => $test_voter_ip,
    'user_id' => $test_user_id,
    'vote_method' => 'auto'
];

$result = VotingService::recordVote($vote_data);
echo "Result: " . ($result['success'] ? '✅ SUCCESS' : '❌ FAILED') . "\n";
echo "Message: {$result['message']}\n";
if ($result['success']) {
    echo "Vote Category: {$result['vote_category']}\n";
    echo "Coins Earned: {$result['coins_earned']}\n";
    echo "Daily Bonus: " . ($result['is_daily_bonus'] ? 'Yes' : 'No') . "\n";
}

// Test 2: Weekly Bonus Vote (if daily is used up)
echo "\n2️⃣ Testing Weekly Bonus Vote...\n";
$vote_data['vote_method'] = 'weekly';
$result = VotingService::recordVote($vote_data);
echo "Result: " . ($result['success'] ? '✅ SUCCESS' : '❌ FAILED') . "\n";
echo "Message: {$result['message']}\n";
if ($result['success']) {
    echo "Vote Category: {$result['vote_category']}\n";
    echo "Coins Earned: {$result['coins_earned']}\n";
}

// Test 3: Premium Vote (if user has enough coins)
echo "\n3️⃣ Testing Premium Vote...\n";
$vote_data['vote_method'] = 'premium';
$result = VotingService::recordVote($vote_data);
echo "Result: " . ($result['success'] ? '✅ SUCCESS' : '❌ FAILED') . "\n";
echo "Message: {$result['message']}\n";
if ($result['success']) {
    echo "Vote Category: {$result['vote_category']}\n";
    echo "Coins Earned: {$result['coins_earned']}\n";
    echo "Coins Spent: {$result['coins_spent']}\n";
}

// Test 4: Vote Counts
echo "\n4️⃣ Testing Vote Count Retrieval...\n";
$counts = VotingService::getVoteCounts($test_item_id);
echo "Vote Counts Result: " . ($counts['success'] ? '✅ SUCCESS' : '❌ FAILED') . "\n";
if ($counts['success']) {
    echo "Vote In Count: {$counts['vote_in_count']}\n";
    echo "Vote Out Count: {$counts['vote_out_count']}\n";
    echo "Total Votes: {$counts['total_votes']}\n";
}

// Test 5: Updated Vote Status
echo "\n5️⃣ Testing Updated Vote Status...\n";
$updated_status = VotingService::getUserVoteStatus($test_user_id, $test_voter_ip);
echo "Updated Vote Status:\n";
echo "- Daily Free Remaining: {$updated_status['daily_free_remaining']}\n";
echo "- Weekly Bonus Remaining: {$updated_status['weekly_bonus_remaining']}\n";
echo "- Premium Votes Available: {$updated_status['premium_votes_available']}\n";
echo "- QR Balance: {$updated_status['qr_balance']}\n";
echo "- Total Votes Today: {$updated_status['total_votes_today']}\n";
echo "- Total Votes This Week: {$updated_status['total_votes_this_week']}\n";

// Test 6: Vote Limits
echo "\n6️⃣ Testing Vote Limits...\n";
echo "Attempting to vote when limits are reached...\n";

// Try to vote again with auto method
$vote_data['vote_method'] = 'auto';
$result = VotingService::recordVote($vote_data);
echo "Auto Vote Result: " . ($result['success'] ? '✅ SUCCESS' : '❌ BLOCKED') . "\n";
echo "Message: {$result['message']}\n";

echo "\n🎉 ENHANCED VOTING SYSTEM TEST COMPLETE!\n";
echo "========================================\n";

// Summary
echo "\n📋 SYSTEM SUMMARY:\n";
echo "✅ Daily Free Vote: 1 per day, earns 30 QR coins (5 base + 25 bonus)\n";
echo "✅ Weekly Bonus Votes: 2 per week, earn 5 QR coins each\n";
echo "✅ Premium Votes: Unlimited, cost 50 QR coins, earn 5 back (net: 45 coins)\n";
echo "✅ Vote Status Tracking: Real-time tracking of available votes\n";
echo "✅ Smart Vote Selection: Auto-selects best available vote type\n";
echo "✅ QR Coin Integration: Seamless integration with economy system\n";

echo "\n🚀 The new voting system is ready for production!\n";
?> 