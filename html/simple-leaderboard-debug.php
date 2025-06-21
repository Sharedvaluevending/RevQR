<?php
// Simple debug - no auth required
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    // Direct database connection
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
    
    echo "<h2>Direct Leaderboard Query Test</h2>";
    
    // Test the exact query from leaderboard
    $stmt = $pdo->prepare("
        SELECT 
            u.id as user_id,
            u.username,
            COALESCE(NULLIF(TRIM(u.username), ''), CONCAT('User_', u.id)) as display_name,
            COALESCE(qr_balance.current_balance, 0) as qr_balance
        FROM users u
        LEFT JOIN (
            SELECT 
                user_id,
                SUM(CASE 
                    WHEN transaction_type IN ('earned', 'bonus', 'reward', 'win', 'refund') THEN amount 
                    WHEN transaction_type IN ('spent', 'deducted', 'bet', 'purchase') THEN -amount 
                    ELSE 0 
                END) as current_balance
            FROM qr_coin_transactions 
            WHERE user_id IS NOT NULL AND user_id > 0
            GROUP BY user_id
        ) qr_balance ON u.id = qr_balance.user_id
        WHERE u.id IS NOT NULL 
          AND u.id > 0
          AND u.id != 915
        ORDER BY qr_balance DESC
        LIMIT 25
    ");
    
    $stmt->execute();
    $users = $stmt->fetchAll();
    
    echo "<p><strong>Query executed successfully!</strong></p>";
    echo "<p><strong>Found " . count($users) . " users</strong></p>";
    
    if (empty($users)) {
        echo "<p style='color: red;'><strong>NO USERS RETURNED BY QUERY!</strong></p>";
        
        // Test basic user query
        $stmt2 = $pdo->prepare("SELECT id, username FROM users WHERE id > 0 AND id != 915");
        $stmt2->execute();
        $basic_users = $stmt2->fetchAll();
        
        echo "<p>Basic user query found: " . count($basic_users) . " users</p>";
        foreach ($basic_users as $user) {
            echo "<p>- User " . $user['id'] . ": " . htmlspecialchars($user['username']) . "</p>";
        }
        
    } else {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>User ID</th><th>Username</th><th>Display Name</th><th>QR Balance</th></tr>";
        foreach ($users as $user) {
            echo "<tr>";
            echo "<td>" . $user['user_id'] . "</td>";
            echo "<td>" . htmlspecialchars($user['username']) . "</td>";
            echo "<td>" . htmlspecialchars($user['display_name']) . "</td>";
            echo "<td>" . $user['qr_balance'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'><strong>Error:</strong> " . $e->getMessage() . "</p>";
    echo "<p><strong>Full error:</strong> " . print_r($e, true) . "</p>";
}
?> 