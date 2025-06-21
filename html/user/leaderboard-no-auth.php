<?php
// NO AUTH VERSION - for testing only
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Direct database connection
try {
    $pdo = new PDO(
        'mysql:host=localhost;dbname=revenueqr;charset=utf8mb4',
        'root',
        'root',
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
        ]
    );
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Simulate logged in user for testing
$_SESSION['user_id'] = 4; // Dax is the 🐐

// Include the level calculation function
function calculateUserLevel($user_votes, $current_balance, $voting_days, $spin_days, $user_id = null) {
    // Simplified version for testing
    $level = max(1, floor(($user_votes + $current_balance/100) / 10));
    return [
        'level' => min($level, 100),
        'progress' => 50.0
    ];
}

function getAvatarFilename($avatar_id) {
    return match($avatar_id) {
        1 => 'qrted.png',
        2 => 'qrjames.png', 
        3 => 'qrmike.png',
        4 => 'qrkevin.png',
        5 => 'qrtim.png',
        default => 'qrted.png'
    };
}

// Get filter and pagination parameters
$filter = $_GET['filter'] ?? 'coins';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 25;
$offset = ($page - 1) * $per_page;

$valid_filters = ['coins', 'level', 'votes', 'spins', 'casino', 'purchases', 'activity', 'streak'];
if (!in_array($filter, $valid_filters)) {
    $filter = 'coins';
}

// Build leaderboard query based on filter
switch($filter) {
    case 'coins':
        $title = '🪙 QR Coin Leaders';
        $subtitle = 'Users ranked by total QR coins earned';
        $primaryMetric = 'qr_balance';
        $primaryLabel = 'QR Coins';
        $orderBy = 'qr_balance DESC, total_activity DESC, user_id ASC';
        break;
        
    case 'level':
        $title = '⭐ Level Leaders';
        $subtitle = 'Users ranked by experience level';
        $primaryMetric = 'user_level';
        $primaryLabel = 'Level';
        $orderBy = 'user_level DESC, qr_balance DESC, total_activity DESC';
        break;
        
    case 'votes':
        $title = '🗳️ Top Voters';
        $subtitle = 'Users ranked by voting activity';
        $primaryMetric = 'total_votes';
        $primaryLabel = 'Votes';
        $orderBy = 'total_votes DESC, qr_balance DESC, user_id ASC';
        break;
        
    case 'spins':
        $title = '🎰 Spin Masters';
        $subtitle = 'Users ranked by spin wheel activity';
        $primaryMetric = 'total_spins';
        $primaryLabel = 'Spins';
        $orderBy = 'total_spins DESC, spin_wins DESC, qr_balance DESC';
        break;
        
    case 'casino':
        $title = '🎲 Casino Champions';
        $subtitle = 'Users ranked by casino game activity';
        $primaryMetric = 'casino_games';
        $primaryLabel = 'Games Played';
        $orderBy = 'casino_games DESC, casino_wins DESC, qr_balance DESC';
        break;
        
    case 'purchases':
        $title = '🛒 Big Spenders';
        $subtitle = 'Users ranked by store purchases';
        $primaryMetric = 'total_purchases';
        $primaryLabel = 'Purchases';
        $orderBy = 'total_purchases DESC, coins_spent DESC, qr_balance DESC';
        break;
        
    case 'activity':
        $title = '⚡ Most Active';
        $subtitle = 'Users ranked by total platform engagement';
        $primaryMetric = 'total_activity';
        $primaryLabel = 'Actions';
        $orderBy = 'total_activity DESC, qr_balance DESC, user_id ASC';
        break;
        
    case 'streak':
        $title = '🔥 Streak Champions';
        $subtitle = 'Users ranked by daily activity streaks';
        $primaryMetric = 'activity_streak';
        $primaryLabel = 'Days';
        $orderBy = 'activity_streak DESC, total_activity DESC, qr_balance DESC';
        break;
}

echo "<h1>$title (NO AUTH TEST)</h1>";
echo "<p>$subtitle</p>";

// EXACT SAME QUERY AS LEADERBOARD.PHP
try {
    $stmt = $pdo->prepare("
        SELECT 
            u.id as user_id,
            u.username,
            COALESCE(NULLIF(TRIM(u.username), ''), CONCAT('User_', u.id)) as display_name,
            COALESCE(u.equipped_avatar, 1) as equipped_avatar,
            u.created_at as join_date,
            
            -- QR Coin balance using QRCoinManager
            COALESCE(qr_balance.current_balance, 0) as qr_balance,
            COALESCE(qr_balance.total_earned, 0) as total_earned,
            COALESCE(qr_balance.total_spent, 0) as total_spent,
            
            -- Voting stats
            COALESCE(vote_stats.total_votes, 0) as total_votes,
            COALESCE(vote_stats.votes_in, 0) as votes_in,
            COALESCE(vote_stats.votes_out, 0) as votes_out,
            COALESCE(vote_stats.voting_days, 0) as voting_days,
            
            -- Spin wheel stats
            COALESCE(spin_stats.total_spins, 0) as total_spins,
            COALESCE(spin_stats.spin_wins, 0) as spin_wins,
            COALESCE(spin_stats.spin_losses, 0) as spin_losses,
            COALESCE(spin_stats.spin_days, 0) as spin_days,
            
            -- Casino stats
            COALESCE(casino_stats.casino_games, 0) as casino_games,
            COALESCE(casino_stats.casino_wins, 0) as casino_wins,
            COALESCE(casino_stats.casino_losses, 0) as casino_losses,
            COALESCE(casino_stats.blackjack_games, 0) as blackjack_games,
            COALESCE(casino_stats.slot_games, 0) as slot_games,
            
            -- Purchase stats
            COALESCE(purchase_stats.total_purchases, 0) as total_purchases,
            COALESCE(purchase_stats.qr_store_purchases, 0) as qr_store_purchases,
            COALESCE(purchase_stats.business_purchases, 0) as business_purchases,
            COALESCE(purchase_stats.coins_spent, 0) as coins_spent,
            
            -- Activity calculations
            (COALESCE(vote_stats.total_votes, 0) + 
             COALESCE(spin_stats.total_spins, 0) + 
             COALESCE(casino_stats.casino_games, 0) + 
             COALESCE(purchase_stats.total_purchases, 0)) as total_activity,
            
            GREATEST(
                COALESCE(vote_stats.voting_days, 0),
                COALESCE(spin_stats.spin_days, 0)
            ) as activity_streak,
            
            -- Level calculation (will be computed in PHP)
            0 as user_level,
            0 as level_progress,
            
            NOW() as query_time
            
        FROM users u
        
        -- QR Coin balance from transaction system
        LEFT JOIN (
            SELECT 
                user_id,
                SUM(CASE 
                    WHEN transaction_type IN ('earning', 'earned', 'bonus', 'reward', 'win', 'refund') THEN amount 
                    WHEN transaction_type IN ('spending', 'spent', 'deducted', 'bet', 'purchase') THEN -ABS(amount)
                    ELSE 0 
                END) as current_balance,
                SUM(CASE 
                    WHEN transaction_type IN ('earning', 'earned', 'bonus', 'reward', 'win', 'refund') THEN amount 
                    ELSE 0 
                END) as total_earned,
                SUM(CASE 
                    WHEN transaction_type IN ('spending', 'spent', 'deducted', 'bet', 'purchase') THEN ABS(amount)
                    ELSE 0 
                END) as total_spent
            FROM qr_coin_transactions 
            WHERE user_id IS NOT NULL AND user_id > 0
            GROUP BY user_id
        ) qr_balance ON u.id = qr_balance.user_id
        
        -- Vote statistics
        LEFT JOIN (
            SELECT 
                user_id,
                COUNT(*) as total_votes,
                COUNT(CASE WHEN vote_type IN ('in', 'vote_in') THEN 1 END) as votes_in,
                COUNT(CASE WHEN vote_type IN ('out', 'vote_out') THEN 1 END) as votes_out,
                COUNT(DISTINCT DATE(created_at)) as voting_days
            FROM votes 
            WHERE user_id IS NOT NULL AND user_id > 0
            GROUP BY user_id
        ) vote_stats ON u.id = vote_stats.user_id
        
        -- Spin wheel statistics
        LEFT JOIN (
            SELECT 
                user_id,
                COUNT(*) as total_spins,
                COUNT(CASE WHEN prize_won NOT IN ('No Prize', 'Lose All Votes', 'Try Again') THEN 1 END) as spin_wins,
                COUNT(CASE WHEN prize_won IN ('No Prize', 'Lose All Votes') THEN 1 END) as spin_losses,
                COUNT(DISTINCT DATE(spin_time)) as spin_days
            FROM spin_results 
            WHERE user_id IS NOT NULL AND user_id > 0
            GROUP BY user_id
        ) spin_stats ON u.id = spin_stats.user_id
        
                 -- Casino game statistics
         LEFT JOIN (
             SELECT 
                 user_id,
                 COUNT(*) as casino_games,
                 COUNT(CASE WHEN win_amount > 0 THEN 1 END) as casino_wins,
                 COUNT(CASE WHEN win_amount = 0 THEN 1 END) as casino_losses,
                 COUNT(CASE WHEN prize_type = 'qr_coins' THEN 1 END) as blackjack_games,
                 COUNT(*) as slot_games
             FROM casino_plays 
             WHERE user_id IS NOT NULL AND user_id > 0
             GROUP BY user_id
         ) casino_stats ON u.id = casino_stats.user_id
        
        -- Purchase statistics
        LEFT JOIN (
            SELECT 
                user_id,
                (COALESCE(qr_purchases, 0) + COALESCE(business_purchases, 0)) as total_purchases,
                COALESCE(qr_purchases, 0) as qr_store_purchases,
                COALESCE(business_purchases, 0) as business_purchases,
                (COALESCE(qr_coins_spent, 0) + COALESCE(business_coins_spent, 0)) as coins_spent
            FROM (
                SELECT 
                    u.id as user_id,
                    qr_stats.qr_purchases,
                    qr_stats.qr_coins_spent,
                    bus_stats.business_purchases,
                    bus_stats.business_coins_spent
                FROM users u
                LEFT JOIN (
                    SELECT user_id, COUNT(*) as qr_purchases, SUM(qr_coins_spent) as qr_coins_spent
                    FROM user_qr_store_purchases 
                    WHERE user_id IS NOT NULL AND user_id > 0
                    GROUP BY user_id
                ) qr_stats ON u.id = qr_stats.user_id
                LEFT JOIN (
                    SELECT user_id, COUNT(*) as business_purchases, SUM(qr_coins_spent) as business_coins_spent
                    FROM business_purchases 
                    WHERE user_id IS NOT NULL AND user_id > 0
                    GROUP BY user_id
                ) bus_stats ON u.id = bus_stats.user_id
            ) combined_purchases
        ) purchase_stats ON u.id = purchase_stats.user_id
        
        -- MINIMAL filtering: Show all real users
        WHERE u.id IS NOT NULL 
          AND u.id > 0
          AND u.id != 915  -- Exclude test_voter only
        
        ORDER BY {$orderBy}
        LIMIT " . ($per_page + $offset) . "
    ");
    
    $stmt->execute();
    $all_users = $stmt->fetchAll();
    
    // Calculate levels for each user using modern system
    foreach ($all_users as &$user) {
        // Use the modern level calculation
        $level_data = calculateUserLevel(
            $user['total_votes'], 
            $user['qr_balance'], 
            $user['voting_days'], 
            $user['spin_days'],
            $user['user_id']
        );
        $user['user_level'] = $level_data['level'];
        $user['level_progress'] = $level_data['progress'];
    }
    
    // Re-sort if level filter is selected
    if ($filter === 'level') {
        usort($all_users, function($a, $b) {
            if ($a['user_level'] == $b['user_level']) {
                if ($a['qr_balance'] == $b['qr_balance']) {
                    return $b['total_activity'] - $a['total_activity'];
                }
                return $b['qr_balance'] - $a['qr_balance'];
            }
            return $b['user_level'] - $a['user_level'];
        });
    }
    
    $total_users = count($all_users);
    $leaderboard_data = array_slice($all_users, $offset, $per_page);
    $total_pages = ceil($total_users / $per_page);
    
    echo "<h2>Query Results: " . count($leaderboard_data) . " users found</h2>";
    
    if (empty($leaderboard_data)) {
        echo "<p style='color: red;'><strong>NO USERS FOUND!</strong></p>";
        
        // Debug: Check basic user query
        $stmt2 = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE id > 0 AND id != 915");
        $stmt2->execute();
        $basic_count = $stmt2->fetchColumn();
        echo "<p>Basic user count: $basic_count</p>";
        
    } else {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>Rank</th><th>ID</th><th>Username</th><th>QR Balance</th><th>Level</th><th>Votes</th><th>Spins</th><th>Casino</th><th>Purchases</th><th>Activity</th></tr>";
        
        foreach ($leaderboard_data as $index => $user) {
            $rank = $index + 1;
            echo "<tr>";
            echo "<td><strong>#{$rank}</strong></td>";
            echo "<td>" . $user['user_id'] . "</td>";
            echo "<td>" . htmlspecialchars($user['display_name']) . "</td>";
            echo "<td>" . number_format($user['qr_balance']) . "</td>";
            echo "<td>" . $user['user_level'] . "</td>";
            echo "<td>" . $user['total_votes'] . "</td>";
            echo "<td>" . $user['total_spins'] . "</td>";
            echo "<td>" . $user['casino_games'] . "</td>";
            echo "<td>" . $user['total_purchases'] . "</td>";
            echo "<td>" . $user['total_activity'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'><strong>Error:</strong> " . $e->getMessage() . "</p>";
    echo "<pre>" . print_r($e, true) . "</pre>";
}

echo "<hr>";
echo "<p><strong>This is a test version without authentication. The real leaderboard requires login.</strong></p>";
echo "<p><a href='leaderboard.php'>Go to real leaderboard (requires login)</a></p>";
?> 