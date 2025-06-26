<?php
require_once 'core/config.php';
require_once 'core/services/VotingService.php';

VotingService::init($pdo);

echo "=== VOTING SYSTEM ISSUES DIAGNOSTIC ===\n\n";

// Test user info
$user_id = 1; // Assuming business owner
$voter_ip = '127.0.0.1';

echo "1. CHECKING VOTE STATUS LOGIC:\n";
$vote_status = VotingService::getUserVoteStatus($user_id, $voter_ip);
echo "   Daily Free Used: {$vote_status['daily_free_used']}\n";
echo "   Daily Free Remaining: {$vote_status['daily_free_remaining']}\n";
echo "   Weekly Bonus Used: {$vote_status['weekly_bonus_used']}\n";
echo "   Weekly Bonus Remaining: {$vote_status['weekly_bonus_remaining']}\n";
echo "   Total Votes Today: {$vote_status['total_votes_today']}\n";
echo "   Total Votes This Week: {$vote_status['total_votes_this_week']}\n\n";

// Check actual votes in database
echo "2. CHECKING ACTUAL VOTES IN DATABASE:\n";
$today = date('Y-m-d');
$week_start = date('Y-m-d', strtotime('monday this week'));

$stmt = $pdo->prepare("
    SELECT COUNT(*) as daily_votes
    FROM votes 
    WHERE user_id = ? AND DATE(created_at) = ?
");
$stmt->execute([$user_id, $today]);
$actual_daily = $stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT COUNT(*) as weekly_votes
    FROM votes 
    WHERE user_id = ? 
    AND DATE(created_at) >= ?
");
$stmt->execute([$user_id, $week_start]);
$actual_weekly = $stmt->fetchColumn();

echo "   Actual Daily Votes: $actual_daily\n";
echo "   Actual Weekly Votes: $actual_weekly\n\n";

// Check the "already voted today" logic
echo "3. CHECKING 'ALREADY VOTED TODAY' LOGIC:\n";
$test_item_id = 1; // Test with first item
$stmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM votes 
    WHERE item_id = ? 
    AND voter_ip = ?
    AND DATE(created_at) = CURDATE()
");
$stmt->execute([$test_item_id, $voter_ip]);
$has_voted_today = $stmt->fetchColumn() > 0;
echo "   Has voted for item $test_item_id today: " . ($has_voted_today ? 'YES' : 'NO') . "\n\n";

// Check vote limit constants
echo "4. CHECKING VOTE LIMIT CONSTANTS:\n";
echo "   DAILY_FREE_VOTES: " . VotingService::DAILY_FREE_VOTES . "\n";
echo "   WEEKLY_BONUS_VOTES: " . VotingService::WEEKLY_BONUS_VOTES . "\n\n";

// Check recent votes
echo "5. RECENT VOTES FOR USER:\n";
$stmt = $pdo->prepare("
    SELECT v.*, vli.item_name, DATE(v.created_at) as vote_date, TIME(v.created_at) as vote_time
    FROM votes v
    JOIN voting_list_items vli ON v.item_id = vli.id
    WHERE v.user_id = ?
    ORDER BY v.created_at DESC
    LIMIT 10
");
$stmt->execute([$user_id]);
$recent_votes = $stmt->fetchAll();

foreach ($recent_votes as $vote) {
    echo "   - {$vote['vote_date']} {$vote['vote_time']}: {$vote['item_name']} ({$vote['vote_type']})\n";
}

echo "\n6. POTENTIAL ISSUES IDENTIFIED:\n";

// Issue 1: Check if vote limits are being properly enforced
if ($vote_status['daily_free_remaining'] > 0 && $actual_daily >= VotingService::DAILY_FREE_VOTES) {
    echo "   ❌ ISSUE: Daily vote limit not properly enforced\n";
    echo "      Status shows {$vote_status['daily_free_remaining']} remaining but user has $actual_daily votes today\n";
}

// Issue 2: Check if weekly calculation is correct
$expected_weekly_used = min($actual_weekly - $actual_daily, VotingService::WEEKLY_BONUS_VOTES);
if ($vote_status['weekly_bonus_used'] != $expected_weekly_used) {
    echo "   ❌ ISSUE: Weekly vote calculation incorrect\n";
    echo "      Expected: $expected_weekly_used, Got: {$vote_status['weekly_bonus_used']}\n";
}

// Issue 3: Check if the "per item per day" limit is working
$stmt = $pdo->prepare("
    SELECT item_id, COUNT(*) as votes_per_item
    FROM votes 
    WHERE user_id = ? AND DATE(created_at) = ?
    GROUP BY item_id
    HAVING votes_per_item > 1
");
$stmt->execute([$user_id, $today]);
$multiple_votes = $stmt->fetchAll();

if (!empty($multiple_votes)) {
    echo "   ❌ ISSUE: User voted multiple times for same item today:\n";
    foreach ($multiple_votes as $mv) {
        echo "      Item {$mv['item_id']}: {$mv['votes_per_item']} votes\n";
    }
}

echo "\n7. RECOMMENDED FIXES:\n";
echo "   1. Fix VotingService::getUserVoteStatus() weekly calculation\n";
echo "   2. Ensure 'already voted today' check works per item AND per user\n";
echo "   3. Add proper vote limit enforcement in recordVote()\n";
echo "   4. Fix AJAX vote updates to refresh counts properly\n";
echo "   5. Fix button movement by stabilizing CSS animations\n";
?> 