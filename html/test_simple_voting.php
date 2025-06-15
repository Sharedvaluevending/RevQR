<?php
/**
 * Simple Voting System Test
 * Tests the new 2 votes per week system
 */

require_once __DIR__ . '/core/config.php';
session_start();

$user_id = $_SESSION['user_id'] ?? null;
$voter_ip = $_SERVER['REMOTE_ADDR'];

echo "<h2>🗳️ Simple Voting System Test</h2>";

// Test 1: Get weekly vote count
echo "<h3>Test 1: Weekly Vote Count</h3>";

$weekly_votes_used = 0;
$weekly_vote_limit = 2;

if ($user_id) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as weekly_votes
        FROM votes 
        WHERE user_id = ? 
        AND YEARWEEK(created_at, 1) = YEARWEEK(NOW(), 1)
    ");
    $stmt->execute([$user_id]);
    $weekly_votes_used = (int) $stmt->fetchColumn();
    echo "✅ User ID: $user_id<br>";
} else {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as weekly_votes
        FROM votes 
        WHERE voter_ip = ? 
        AND user_id IS NULL
        AND YEARWEEK(created_at, 1) = YEARWEEK(NOW(), 1)
    ");
    $stmt->execute([$voter_ip]);
    $weekly_votes_used = (int) $stmt->fetchColumn();
    echo "✅ Guest IP: $voter_ip<br>";
}

$votes_remaining = max(0, $weekly_vote_limit - $weekly_votes_used);

echo "📊 Votes used this week: <strong>$weekly_votes_used</strong><br>";
echo "📊 Votes remaining: <strong>$votes_remaining</strong><br>";
echo "📊 Weekly limit: <strong>$weekly_vote_limit</strong><br>";

// Test 2: Check current week's votes
echo "<h3>Test 2: This Week's Vote History</h3>";

if ($user_id) {
    $stmt = $pdo->prepare("
        SELECT v.*, vli.item_name, vl.name as list_name
        FROM votes v
        LEFT JOIN voting_list_items vli ON v.item_id = vli.id
        LEFT JOIN voting_lists vl ON vli.voting_list_id = vl.id
        WHERE v.user_id = ?
        AND YEARWEEK(v.created_at, 1) = YEARWEEK(NOW(), 1)
        ORDER BY v.created_at DESC
    ");
    $stmt->execute([$user_id]);
} else {
    $stmt = $pdo->prepare("
        SELECT v.*, vli.item_name, vl.name as list_name
        FROM votes v
        LEFT JOIN voting_list_items vli ON v.item_id = vli.id
        LEFT JOIN voting_lists vl ON vli.voting_list_id = vl.id
        WHERE v.voter_ip = ? AND v.user_id IS NULL
        AND YEARWEEK(v.created_at, 1) = YEARWEEK(NOW(), 1)
        ORDER BY v.created_at DESC
    ");
    $stmt->execute([$voter_ip]);
}

$this_week_votes = $stmt->fetchAll();

if ($this_week_votes) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Date</th><th>Item</th><th>Vote Type</th><th>List</th></tr>";
    foreach ($this_week_votes as $vote) {
        echo "<tr>";
        echo "<td>" . date('M j, Y H:i', strtotime($vote['created_at'])) . "</td>";
        echo "<td>" . htmlspecialchars($vote['item_name'] ?? 'Unknown') . "</td>";
        echo "<td>" . ucwords(str_replace('_', ' ', $vote['vote_type'])) . "</td>";
        echo "<td>" . htmlspecialchars($vote['list_name'] ?? 'Unknown') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "📭 No votes cast this week.<br>";
}

// Test 3: System Status
echo "<h3>Test 3: System Status</h3>";

if ($votes_remaining > 0) {
    echo "✅ <strong>SYSTEM OK:</strong> You can cast $votes_remaining more vote(s) this week.<br>";
    echo "🎯 <strong>Next step:</strong> Visit a voting page to test casting a vote.<br>";
} else {
    echo "⚠️ <strong>LIMIT REACHED:</strong> You have used all your votes for this week.<br>";
    echo "⏰ <strong>Reset time:</strong> Monday at midnight<br>";
}

echo "<br><h4>Quick Links:</h4>";
echo "<a href='/vote.php' class='btn'>🗳️ Vote Page</a> ";
echo "<a href='/qr_dynamic_manager.php' class='btn'>⚙️ QR Manager</a><br>";

echo "<br><small><em>Simple Voting System: 2 votes per week, no daily limits, no premium votes.</em></small>";
?> 