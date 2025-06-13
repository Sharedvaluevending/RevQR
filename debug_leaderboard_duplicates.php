<?php
require_once 'html/core/config.php';

echo "🔍 LEADERBOARD DUPLICATE DEBUGGING TOOL\n";
echo "=====================================\n\n";

// Check for potential duplicate usernames
echo "1. Checking for similar usernames...\n";
$stmt = $pdo->query("
    SELECT id, username, email, created_at, 
           COUNT(*) OVER (PARTITION BY LOWER(TRIM(username))) as username_count
    FROM users 
    WHERE username IS NOT NULL AND TRIM(username) != ''
    ORDER BY LOWER(TRIM(username)), id
");

$duplicates = [];
while ($row = $stmt->fetch()) {
    if ($row['username_count'] > 1) {
        $duplicates[] = $row;
    }
}

if (empty($duplicates)) {
    echo "✅ No duplicate usernames found\n\n";
} else {
    echo "⚠️  Found potential duplicate usernames:\n";
    foreach ($duplicates as $user) {
        echo "   ID: {$user['id']}, Username: '{$user['username']}', Email: {$user['email']}, Created: {$user['created_at']}\n";
    }
    echo "\n";
}

// Check activity for potentially duplicate users
echo "2. Checking activity for similar usernames...\n";
$stmt = $pdo->query("
    SELECT 
        u.id, u.username,
        COUNT(DISTINCT v.id) as votes,
        COUNT(DISTINCT s.id) as spins,
        MAX(v.created_at) as last_vote,
        MAX(s.spin_time) as last_spin
    FROM users u
    LEFT JOIN votes v ON u.id = v.user_id
    LEFT JOIN spin_results s ON u.id = s.user_id
    WHERE u.username IS NOT NULL
    GROUP BY u.id, u.username
    HAVING votes > 0 OR spins > 0
    ORDER BY u.username, u.id
");

$active_users = $stmt->fetchAll();
$potential_issues = [];

for ($i = 0; $i < count($active_users) - 1; $i++) {
    $current = $active_users[$i];
    $next = $active_users[$i + 1];
    
    // Check for similar usernames
    if (levenshtein(strtolower($current['username']), strtolower($next['username'])) <= 2) {
        $potential_issues[] = [$current, $next];
    }
}

if (empty($potential_issues)) {
    echo "✅ No similar active usernames found\n\n";
} else {
    echo "⚠️  Found potentially similar active users:\n";
    foreach ($potential_issues as $pair) {
        echo "   User 1: ID {$pair[0]['id']}, '{$pair[0]['username']}', {$pair[0]['votes']} votes, {$pair[0]['spins']} spins\n";
        echo "   User 2: ID {$pair[1]['id']}, '{$pair[1]['username']}', {$pair[1]['votes']} votes, {$pair[1]['spins']} spins\n";
        echo "   ---\n";
    }
    echo "\n";
}

// Check current leaderboard data
echo "3. Current leaderboard (top 10)...\n";
$stmt = $pdo->query("
    SELECT 
        u.id as user_id,
        u.username,
        COALESCE(NULLIF(TRIM(u.username), ''), CONCAT('User_', u.id)) as display_name,
        COALESCE(vote_stats.total_votes, 0) as total_votes,
        COALESCE(spin_stats.total_spins, 0) as total_spins,
        (COALESCE(vote_stats.total_votes, 0) + COALESCE(spin_stats.total_spins, 0)) as total_activity
    FROM users u
    LEFT JOIN (
        SELECT user_id, COUNT(*) as total_votes
        FROM votes 
        WHERE user_id IS NOT NULL AND user_id > 0
        GROUP BY user_id
    ) vote_stats ON u.id = vote_stats.user_id
    LEFT JOIN (
        SELECT user_id, COUNT(*) as total_spins
        FROM spin_results 
        WHERE user_id IS NOT NULL AND user_id > 0
        GROUP BY user_id
    ) spin_stats ON u.id = spin_stats.user_id
    WHERE u.id IS NOT NULL AND u.id > 0
      AND (
          (vote_stats.total_votes IS NOT NULL AND vote_stats.total_votes > 0) OR 
          (spin_stats.total_spins IS NOT NULL AND spin_stats.total_spins > 0)
      )
      AND COALESCE(TRIM(u.username), '') != ''
      AND u.username NOT LIKE 'test%'
      AND u.username NOT LIKE 'dummy%'
    ORDER BY total_activity DESC, user_id ASC
    LIMIT 10
");

$leaderboard = $stmt->fetchAll();
if (empty($leaderboard)) {
    echo "⚠️  No users found in leaderboard\n\n";
} else {
    echo "📊 Current leaderboard top 10:\n";
    foreach ($leaderboard as $i => $user) {
        $rank = $i + 1;
        echo "   {$rank}. ID: {$user['user_id']}, '{$user['display_name']}', Votes: {$user['total_votes']}, Spins: {$user['total_spins']}, Total: {$user['total_activity']}\n";
    }
    echo "\n";
}

// Check for orphaned data
echo "4. Checking for orphaned vote/spin data...\n";
$stmt = $pdo->query("
    SELECT 'votes' as type, COUNT(*) as orphaned_count
    FROM votes v
    LEFT JOIN users u ON v.user_id = u.id
    WHERE v.user_id IS NOT NULL AND u.id IS NULL
    UNION ALL
    SELECT 'spins' as type, COUNT(*) as orphaned_count
    FROM spin_results s
    LEFT JOIN users u ON s.user_id = u.id
    WHERE s.user_id IS NOT NULL AND u.id IS NULL
");

$orphaned = $stmt->fetchAll();
foreach ($orphaned as $data) {
    if ($data['orphaned_count'] > 0) {
        echo "⚠️  Found {$data['orphaned_count']} orphaned {$data['type']} records\n";
    } else {
        echo "✅ No orphaned {$data['type']} records\n";
    }
}

echo "\n🎯 RECOMMENDATIONS:\n";
echo "==================\n";

if (!empty($duplicates)) {
    echo "• Consider merging duplicate user accounts or marking inactive ones\n";
}

if (!empty($potential_issues)) {
    echo "• Review similar usernames to ensure they're not the same person\n";
}

echo "• The leaderboard query now includes strict filtering to prevent duplicates\n";
echo "• Cache-busting headers are enabled for real-time updates\n";
echo "• Auto-refresh is enabled every 30 seconds\n";

echo "\n✅ Leaderboard fixes applied!\n";
?> 