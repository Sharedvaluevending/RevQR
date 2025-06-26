<?php
// Debug version of leaderboard with full error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Leaderboard Debug</h2>";

try {
    require_once __DIR__ . '/../core/config.php';
    echo "<p>✓ Config loaded</p>";
} catch (Exception $e) {
    echo "<p>✗ Config error: " . $e->getMessage() . "</p>";
    exit;
}

try {
    require_once __DIR__ . '/../core/session.php';
    echo "<p>✓ Session loaded</p>";
} catch (Exception $e) {
    echo "<p>✗ Session error: " . $e->getMessage() . "</p>";
}

try {
    require_once __DIR__ . '/../core/auth.php';
    echo "<p>✓ Auth loaded</p>";
} catch (Exception $e) {
    echo "<p>✗ Auth error: " . $e->getMessage() . "</p>";
}

// Skip auth requirement for debugging
// require_role('user');

echo "<p>Database connection: " . (isset($pdo) ? "✓ Connected" : "✗ Not connected") . "</p>";

if (!isset($pdo)) {
    echo "<p>Creating direct PDO connection...</p>";
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
        echo "<p>✓ Direct PDO connection successful</p>";
    } catch (Exception $e) {
        echo "<p>✗ Direct PDO connection failed: " . $e->getMessage() . "</p>";
        exit;
    }
}

// Simple test query first
echo "<h3>Simple User Query Test</h3>";
try {
    $stmt = $pdo->prepare("SELECT id, username FROM users WHERE id > 0 AND id != 915 ORDER BY id");
    $stmt->execute();
    $users = $stmt->fetchAll();
    echo "<p>Found " . count($users) . " users</p>";
    foreach ($users as $user) {
        echo "<p>- User " . $user['id'] . ": " . htmlspecialchars($user['username']) . "</p>";
    }
} catch (Exception $e) {
    echo "<p>Error: " . $e->getMessage() . "</p>";
}

// QR Balance test
echo "<h3>QR Balance Test</h3>";
try {
    $stmt = $pdo->prepare("
        SELECT 
            u.id,
            u.username,
            COALESCE(qr_balance.current_balance, 0) as qr_balance
        FROM users u
        LEFT JOIN (
            SELECT 
                user_id,
                SUM(CASE 
                    WHEN transaction_type IN ('earning', 'earned', 'bonus', 'reward', 'win', 'refund') THEN amount 
                    WHEN transaction_type IN ('spending', 'spent', 'deducted', 'bet', 'purchase') THEN ABS(amount)
                    ELSE 0 
                END) as current_balance
            FROM qr_coin_transactions 
            WHERE user_id IS NOT NULL AND user_id > 0
            GROUP BY user_id
        ) qr_balance ON u.id = qr_balance.user_id
        WHERE u.id > 0 AND u.id != 915
        ORDER BY qr_balance DESC
    ");
    $stmt->execute();
    $users = $stmt->fetchAll();
    
    echo "<p>QR Balance query returned " . count($users) . " users:</p>";
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Username</th><th>QR Balance</th></tr>";
    foreach ($users as $user) {
        echo "<tr>";
        echo "<td>" . $user['id'] . "</td>";
        echo "<td>" . htmlspecialchars($user['username']) . "</td>";
        echo "<td>" . number_format($user['qr_balance']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
} catch (Exception $e) {
    echo "<p>QR Balance Error: " . $e->getMessage() . "</p>";
}

// Test the exact query from leaderboard.php
$filter = 'coins';
$page = 1;
$per_page = 25;
$offset = 0;
$orderBy = 'qr_balance DESC, total_activity DESC, user_id ASC';

echo "<h3>Testing Leaderboard Query</h3>";

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
                    WHEN transaction_type IN ('spending', 'spent', 'deducted', 'bet', 'purchase') THEN ABS(amount)
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
                COUNT(CASE WHEN result = 'win' THEN 1 END) as casino_wins,
                COUNT(CASE WHEN result = 'loss' THEN 1 END) as casino_losses,
                COUNT(CASE WHEN game_type = 'blackjack' THEN 1 END) as blackjack_games,
                COUNT(CASE WHEN game_type = 'slots' THEN 1 END) as slot_games
            FROM casino_play_history 
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
    
    echo "<p>✓ Query prepared successfully</p>";
    
    $stmt->execute();
    echo "<p>✓ Query executed successfully</p>";
    
    $all_users = $stmt->fetchAll();
    echo "<p>✓ Results fetched: " . count($all_users) . " users found</p>";
    
    if (empty($all_users)) {
        echo "<p style='color: red;'><strong>NO USERS RETURNED!</strong></p>";
    } else {
        echo "<h3>Results:</h3>";
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>Username</th><th>QR Balance</th><th>Votes</th><th>Spins</th><th>Casino</th><th>Purchases</th></tr>";
        
        foreach ($all_users as $user) {
            echo "<tr>";
            echo "<td>" . $user['user_id'] . "</td>";
            echo "<td>" . htmlspecialchars($user['display_name']) . "</td>";
            echo "<td>" . number_format($user['qr_balance']) . "</td>";
            echo "<td>" . $user['total_votes'] . "</td>";
            echo "<td>" . $user['total_spins'] . "</td>";
            echo "<td>" . $user['casino_games'] . "</td>";
            echo "<td>" . $user['total_purchases'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'><strong>Query Error:</strong> " . $e->getMessage() . "</p>";
    echo "<p><strong>Full error:</strong></p>";
    echo "<pre>" . print_r($e, true) . "</pre>";
}

echo "<h3>Memory and Performance</h3>";
echo "<p>Memory usage: " . memory_get_usage(true) . " bytes</p>";
echo "<p>Peak memory: " . memory_get_peak_usage(true) . " bytes</p>";
?> 