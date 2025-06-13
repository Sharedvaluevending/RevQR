<?php
/**
 * Voting System Security Fixes Test
 * Comprehensive testing of exploit patches and vote display fixes
 */

require_once __DIR__ . '/html/core/config.php';
require_once __DIR__ . '/html/core/services/VotingService.php';

// Initialize voting service
VotingService::init($pdo);

echo "🔒 VOTING SYSTEM SECURITY TEST\n";
echo "============================\n\n";

// Test Configuration
$test_user_id = 4; // User who was exploiting the system
$test_voter_ip = '72.136.113.148'; // One of their IPs
$test_item_id = 112; // Most voted item

echo "📊 CURRENT SYSTEM STATUS:\n";
echo "-" . str_repeat("-", 25) . "\n";

// 1. Check current vote counts for test user
echo "1️⃣ Checking Current Vote Status for User {$test_user_id}...\n";
$vote_status = VotingService::getUserVoteStatus($test_user_id, $test_voter_ip);
echo "   Daily Free Used: {$vote_status['daily_free_used']}\n";
echo "   Daily Free Remaining: {$vote_status['daily_free_remaining']}\n";
echo "   Weekly Bonus Used: {$vote_status['weekly_bonus_used']}\n";
echo "   Weekly Bonus Remaining: {$vote_status['weekly_bonus_remaining']}\n";
echo "   Total Votes Today: {$vote_status['total_votes_today']}\n";
echo "   QR Balance: {$vote_status['qr_balance']}\n\n";

// 2. Test current database state
echo "2️⃣ Current Database Analysis...\n";
$stmt = $pdo->prepare("
    SELECT 
        user_id,
        COUNT(*) as total_votes,
        COUNT(DISTINCT voter_ip) as unique_ips,
        MIN(created_at) as first_vote,
        MAX(created_at) as last_vote
    FROM votes 
    WHERE DATE(created_at) = CURDATE() AND user_id = ?
    GROUP BY user_id
");
$stmt->execute([$test_user_id]);
$current_data = $stmt->fetch();

if ($current_data) {
    echo "   User {$test_user_id} Today:\n";
    echo "   - Total Votes: {$current_data['total_votes']}\n";
    echo "   - Unique IPs: {$current_data['unique_ips']}\n";
    echo "   - First Vote: {$current_data['first_vote']}\n";
    echo "   - Last Vote: {$current_data['last_vote']}\n\n";
} else {
    echo "   No votes found for user {$test_user_id} today.\n\n";
}

// 3. Test security validation
echo "3️⃣ Testing Security Validation...\n";

// Test vote attempt that should be blocked
$test_vote_data = [
    'item_id' => $test_item_id,
    'vote_type' => 'in',
    'voter_ip' => $test_voter_ip,
    'user_id' => $test_user_id,
    'vote_method' => 'auto'
];

echo "   Attempting vote with exploited user...\n";
$vote_result = VotingService::recordVote($test_vote_data);
echo "   Result: " . ($vote_result['success'] ? '❌ ALLOWED (BAD)' : '✅ BLOCKED (GOOD)') . "\n";
echo "   Message: {$vote_result['message']}\n";
if (isset($vote_result['error_code'])) {
    echo "   Error Code: {$vote_result['error_code']}\n";
}
echo "\n";

// 4. Test vote count accuracy
echo "4️⃣ Testing Vote Count Accuracy...\n";
$counts = VotingService::getVoteCounts($test_item_id);
if ($counts['success']) {
    echo "   Item {$test_item_id} Vote Counts:\n";
    echo "   - Vote In: {$counts['vote_in_count']}\n";
    echo "   - Vote Out: {$counts['vote_out_count']}\n";
    echo "   - Total: {$counts['total_votes']}\n";
} else {
    echo "   ❌ Failed to get vote counts\n";
}
echo "\n";

// 5. Test IP-based restrictions
echo "5️⃣ Testing IP-based Restrictions...\n";
$stmt = $pdo->prepare("
    SELECT COUNT(*) as ip_votes_today
    FROM votes 
    WHERE voter_ip = ? AND DATE(created_at) = CURDATE()
");
$stmt->execute([$test_voter_ip]);
$ip_votes = $stmt->fetchColumn();
echo "   IP {$test_voter_ip} votes today: {$ip_votes}\n";

if ($ip_votes >= 15) {
    echo "   ✅ IP would be blocked for excessive voting\n";
} elseif ($ip_votes >= 10) {
    echo "   🟡 IP is approaching limit (15)\n";
} else {
    echo "   🟢 IP is within normal limits\n";
}
echo "\n";

// 6. Test QR coin transaction integrity
echo "6️⃣ Testing QR Coin Transaction Integrity...\n";
$stmt = $pdo->prepare("
    SELECT 
        category,
        COUNT(*) as transaction_count,
        SUM(amount) as total_amount
    FROM qr_coin_transactions 
    WHERE user_id = ? AND DATE(created_at) = CURDATE() AND transaction_type = 'earning'
    GROUP BY category
");
$stmt->execute([$test_user_id]);
$coin_data = $stmt->fetchAll();

echo "   User {$test_user_id} QR Coin Earnings Today:\n";
foreach ($coin_data as $row) {
    echo "   - {$row['category']}: {$row['transaction_count']} transactions, {$row['total_amount']} coins\n";
}
echo "\n";

// 7. Security recommendations
echo "7️⃣ Security Status Assessment...\n";
$security_score = 0;
$total_checks = 5;

// Check 1: User vote limits
if ($vote_status['total_votes_today'] <= 3) {
    echo "   ✅ User daily vote limits: SECURE\n";
    $security_score++;
} else {
    echo "   ❌ User daily vote limits: COMPROMISED ({$vote_status['total_votes_today']} votes)\n";
}

// Check 2: Vote blocking
if (!$vote_result['success']) {
    echo "   ✅ Vote blocking: WORKING\n";
    $security_score++;
} else {
    echo "   ❌ Vote blocking: FAILED\n";
}

// Check 3: IP limits
if ($ip_votes < 20) {
    echo "   ✅ IP vote limits: ACCEPTABLE\n";
    $security_score++;
} else {
    echo "   ❌ IP vote limits: EXCESSIVE\n";
}

// Check 4: Vote count accuracy
if ($counts['success']) {
    echo "   ✅ Vote counting: FUNCTIONAL\n";
    $security_score++;
} else {
    echo "   ❌ Vote counting: BROKEN\n";
}

// Check 5: Coin award ratio
$voting_transactions = 0;
$voting_coins = 0;
foreach ($coin_data as $row) {
    if ($row['category'] === 'voting') {
        $voting_transactions = $row['transaction_count'];
        $voting_coins = $row['total_amount'];
        break;
    }
}

$expected_max_coins = 35; // 1 daily (30) + 2 weekly (5 each)
if ($voting_coins <= $expected_max_coins * 2) { // Allow some buffer
    echo "   ✅ QR coin awards: REASONABLE\n";
    $security_score++;
} else {
    echo "   ❌ QR coin awards: EXCESSIVE ({$voting_coins} coins from {$voting_transactions} votes)\n";
}

echo "\n";

// Final assessment
echo "🎯 FINAL SECURITY ASSESSMENT:\n";
echo "============================\n";
$security_percentage = ($security_score / $total_checks) * 100;
echo "Security Score: {$security_score}/{$total_checks} ({$security_percentage}%)\n";

if ($security_percentage >= 80) {
    echo "🟢 SYSTEM STATUS: SECURE\n";
    echo "✅ Voting system is properly protected against exploitation.\n";
} elseif ($security_percentage >= 60) {
    echo "🟡 SYSTEM STATUS: NEEDS ATTENTION\n";
    echo "⚠️  Some security measures need refinement.\n";
} else {
    echo "🔴 SYSTEM STATUS: VULNERABLE\n";
    echo "🚨 Critical security issues need immediate attention.\n";
}

echo "\n";

// Action items
echo "📋 RECOMMENDED ACTIONS:\n";
echo "-" . str_repeat("-", 20) . "\n";

if ($vote_status['total_votes_today'] > 10) {
    echo "🔄 Review User {$test_user_id} for potential violations\n";
    echo "🔄 Consider reversing excess QR coin earnings\n";
}

if ($ip_votes > 20) {
    echo "🔄 Monitor IP {$test_voter_ip} for continued suspicious activity\n";
}

if ($voting_coins > $expected_max_coins * 3) {
    echo "🔄 Audit QR coin transactions for legitimacy\n";
    echo "🔄 Consider implementing retroactive limits\n";
}

echo "🔄 Continue monitoring for 24-48 hours to ensure fixes hold\n";
echo "🔄 Implement admin alerts for future exploitation attempts\n";

echo "\n🎉 VOTING SYSTEM SECURITY AUDIT COMPLETE!\n";
?> 