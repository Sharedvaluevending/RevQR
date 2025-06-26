<?php
require_once __DIR__ . '/../core/config.php';

echo "<h2>Leaderboard Query Debug</h2>";

// Test 1: Basic user count
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as user_count FROM users WHERE id > 0");
    $stmt->execute();
    $result = $stmt->fetch();
    echo "<p><strong>Total users in database:</strong> " . $result['user_count'] . "</p>";
} catch (Exception $e) {
    echo "<p><strong>Error counting users:</strong> " . $e->getMessage() . "</p>";
}

// Test 2: Users with current filtering
try {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as filtered_count 
        FROM users u
        WHERE u.id IS NOT NULL AND u.id > 0
          AND u.username NOT LIKE 'test%'
          AND u.username NOT LIKE 'dummy%'
          AND COALESCE(TRIM(u.username), '') != ''
          AND u.id != 915
    ");
    $stmt->execute();
    $result = $stmt->fetch();
    echo "<p><strong>Users after basic filtering:</strong> " . $result['filtered_count'] . "</p>";
} catch (Exception $e) {
    echo "<p><strong>Error with basic filtering:</strong> " . $e->getMessage() . "</p>";
}

// Test 3: Check QR balance subquery
try {
    $stmt = $pdo->prepare("
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
        ORDER BY current_balance DESC
    ");
    $stmt->execute();
    $balances = $stmt->fetchAll();
    
    echo "<h3>QR Coin Balances:</h3>";
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>User ID</th><th>Balance</th></tr>";
    foreach ($balances as $balance) {
        echo "<tr><td>" . $balance['user_id'] . "</td><td>" . $balance['current_balance'] . "</td></tr>";
    }
    echo "</table>";
} catch (Exception $e) {
    echo "<p><strong>Error with balance query:</strong> " . $e->getMessage() . "</p>";
}

// Test 4: Full leaderboard query (simplified)
try {
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
        WHERE u.id IS NOT NULL AND u.id > 0
          AND u.username NOT LIKE 'test%'
          AND u.username NOT LIKE 'dummy%'
          AND COALESCE(TRIM(u.username), '') != ''
          AND u.id != 915
        ORDER BY qr_balance DESC
    ");
    $stmt->execute();
    $users = $stmt->fetchAll();
    
    echo "<h3>Leaderboard Results (" . count($users) . " users):</h3>";
    if (empty($users)) {
        echo "<p><strong>NO USERS FOUND!</strong></p>";
    } else {
        echo "<table border='1' style='border-collapse: collapse;'>";
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
    echo "<p><strong>Error with full query:</strong> " . $e->getMessage() . "</p>";
    echo "<p><strong>Error details:</strong> " . print_r($e, true) . "</p>";
}

// Test 5: Check individual user details
echo "<h3>Individual User Check:</h3>";
try {
    $stmt = $pdo->prepare("
        SELECT 
            id, 
            username, 
            COALESCE(TRIM(username), '') as trimmed_username,
            LENGTH(COALESCE(TRIM(username), '')) as username_length,
            CASE 
                WHEN username LIKE 'test%' THEN 'FILTERED: test prefix'
                WHEN username LIKE 'dummy%' THEN 'FILTERED: dummy prefix'
                WHEN COALESCE(TRIM(username), '') = '' THEN 'FILTERED: empty username'
                WHEN id = 915 THEN 'FILTERED: test user ID'
                ELSE 'PASSES FILTER'
            END as filter_status
        FROM users 
        WHERE id > 0
        ORDER BY id
    ");
    $stmt->execute();
    $users = $stmt->fetchAll();
    
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>ID</th><th>Username</th><th>Trimmed</th><th>Length</th><th>Filter Status</th></tr>";
    foreach ($users as $user) {
        echo "<tr>";
        echo "<td>" . $user['id'] . "</td>";
        echo "<td>" . htmlspecialchars($user['username']) . "</td>";
        echo "<td>" . htmlspecialchars($user['trimmed_username']) . "</td>";
        echo "<td>" . $user['username_length'] . "</td>";
        echo "<td>" . $user['filter_status'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} catch (Exception $e) {
    echo "<p><strong>Error checking individual users:</strong> " . $e->getMessage() . "</p>";
}
?> 