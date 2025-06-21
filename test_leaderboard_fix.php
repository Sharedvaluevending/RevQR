<?php
require_once __DIR__ . '/html/core/config.php';
require_once __DIR__ . '/html/core/qr_coin_manager.php';

echo "=== LEADERBOARD FIX TEST ===\n\n";

// Test the leaderboard query with the new fields
try {
    $stmt = $pdo->prepare("
        SELECT 
            u.id as user_id,
            u.username, 
            u.email,
            COALESCE(u.equipped_avatar, 1) as equipped_avatar,
            COALESCE(vote_stats.total_votes, 0) as total_votes,
            COALESCE(vote_stats.voting_days, 0) as voting_days,
            COALESCE(spin_stats.total_spins, 0) as total_spins,
            COALESCE(spin_stats.spin_days, 0) as spin_days,
            (COALESCE(vote_stats.total_votes, 0) + COALESCE(spin_stats.total_spins, 0)) as total_activity
        FROM users u
        
        -- Vote stats
        LEFT JOIN (
            SELECT 
                user_id,
                COUNT(*) as total_votes,
                COUNT(DISTINCT DATE(created_at)) as voting_days
            FROM votes 
            WHERE user_id IS NOT NULL AND user_id > 0
            GROUP BY user_id
        ) vote_stats ON u.id = vote_stats.user_id
        
        -- Spin stats
        LEFT JOIN (
            SELECT 
                user_id,
                COUNT(*) as total_spins,
                COUNT(DISTINCT DATE(spin_time)) as spin_days
            FROM spin_results 
            WHERE user_id IS NOT NULL AND user_id > 0
            GROUP BY user_id
        ) spin_stats ON u.id = spin_stats.user_id
        
        -- Only users with activity
        WHERE u.id IS NOT NULL AND u.id > 0
          AND (
              (vote_stats.total_votes IS NOT NULL AND vote_stats.total_votes > 0) OR 
              (spin_stats.total_spins IS NOT NULL AND spin_stats.total_spins > 0)
          )
          AND COALESCE(TRIM(u.username), '') != ''
          AND u.username NOT LIKE 'test%'
          AND u.username NOT LIKE 'dummy%'
        
        ORDER BY total_activity DESC, user_id ASC
        LIMIT 3
    ");
    
    $stmt->execute();
    $leaderboard_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "✅ Leaderboard query executed successfully!\n";
    echo "Found " . count($leaderboard_data) . " users with activity\n\n";
    
    if (!empty($leaderboard_data)) {
        echo "Top 3 users with avatar info:\n";
        foreach ($leaderboard_data as $i => $user) {
            $user_points = QRCoinManager::getBalance($user['user_id']);
            
            // Test the fallback logic
            $equipped_avatar = $user['equipped_avatar'] ?? 1;
            
            echo ($i + 1) . ". {$user['username']} (Avatar ID: {$equipped_avatar})\n";
            echo "   Activity: {$user['total_activity']}, Points: {$user_points}\n";
            echo "   Username: {$user['username']}, Email: {$user['email']}\n\n";
        }
    }
    
    // Test the getAvatarFilename function
    echo "Testing getAvatarFilename function:\n";
    if (function_exists('getAvatarFilename')) {
        $test_avatars = [1, 2, null, 0];
        foreach ($test_avatars as $avatar) {
            $result = getAvatarFilename($avatar);
            echo "   getAvatarFilename({$avatar}) = '{$result}'\n";
        }
    } else {
        echo "   getAvatarFilename function not found\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n=== TEST COMPLETE ===\n"; 