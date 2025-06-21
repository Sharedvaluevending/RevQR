<?php
/**
 * User-Specific Purchase Diagnostic Script (CLI Version)
 * 
 * Usage: php diagnose_user_purchases_cli.php [username|user_id]
 */

require_once __DIR__ . '/html/core/config.php';

echo "=== User Purchase Diagnostic Tool ===\n\n";

// Get user input from command line or prompt
$user_input = $argv[1] ?? null;

if (!$user_input) {
    echo "Available users:\n";
    $stmt = $pdo->query("SELECT id, username FROM users ORDER BY id DESC LIMIT 10");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($users as $user) {
        echo "  ID: {$user['id']} | Username: {$user['username']}\n";
    }
    echo "\nUsage: php diagnose_user_purchases_cli.php [username|user_id]\n";
    echo "Example: php diagnose_user_purchases_cli.php Bob\n";
    exit(1);
}

try {
    // Find user by username or ID
    if (is_numeric($user_input)) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user_input]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$user_input]);
    }
    
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo "User not found. Please check your username or ID.\n";
        exit(1);
    }
    
    $user_id = $user['id'];
    echo "Found user: {$user['username']} (ID: {$user_id})\n\n";
    
    // 1. Check QR Coin Transactions
    echo "=== QR COIN TRANSACTIONS (Last 10) ===\n";
    $stmt = $pdo->prepare("
        SELECT 
            id,
            transaction_type,
            category,
            amount,
            description,
            metadata,
            created_at
        FROM qr_coin_transactions 
        WHERE user_id = ?
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $stmt->execute([$user_id]);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($transactions)) {
        echo "No transactions found.\n\n";
    } else {
        foreach ($transactions as $transaction) {
            $metadata = json_decode($transaction['metadata'], true);
            echo sprintf(
                "ID: %d | %s | %s | %+d coins | %s | %s\n",
                $transaction['id'],
                $transaction['transaction_type'],
                $transaction['category'],
                $transaction['amount'],
                $transaction['description'],
                $transaction['created_at']
            );
            if ($metadata && !empty($metadata)) {
                echo "  Metadata: " . json_encode($metadata, JSON_UNESCAPED_SLASHES) . "\n";
            }
            echo "\n";
        }
    }
    
    // 2. Check Business Purchases
    echo "=== BUSINESS PURCHASES ===\n";
    $stmt = $pdo->prepare("
        SELECT 
            bp.*,
            bsi.item_name,
            b.name as business_name
        FROM business_purchases bp
        LEFT JOIN business_store_items bsi ON bp.business_store_item_id = bsi.id
        LEFT JOIN businesses b ON bp.business_id = b.id
        WHERE bp.user_id = ?
        ORDER BY bp.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $business_purchases = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($business_purchases)) {
        echo "No business purchases found.\n\n";
    } else {
        foreach ($business_purchases as $purchase) {
            echo sprintf(
                "ID: %d | %s | %s%% OFF | %d coins | %s | %s | %s\n",
                $purchase['id'],
                $purchase['item_name'] ?? 'Unknown Item',
                $purchase['discount_percentage'],
                $purchase['qr_coins_spent'],
                $purchase['purchase_code'],
                $purchase['status'],
                $purchase['created_at']
            );
            echo "  Business: " . ($purchase['business_name'] ?? 'Unknown') . "\n";
            echo "  Expires: " . ($purchase['expires_at'] ?? 'N/A') . "\n\n";
        }
    }
    
    // 3. Check QR Store Purchases  
    echo "=== QR STORE PURCHASES ===\n";
    $stmt = $pdo->prepare("
        SELECT 
            usp.*,
            bsi.item_name,
            bsi.discount_percentage
        FROM user_store_purchases usp
        LEFT JOIN business_store_items bsi ON usp.store_item_id = bsi.id
        WHERE usp.user_id = ?
        ORDER BY usp.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$user_id]);
    $qr_purchases = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($qr_purchases)) {
        echo "No QR store purchases found.\n\n";
    } else {
        foreach ($qr_purchases as $purchase) {
            echo sprintf(
                "ID: %d | %s | %s | %d coins | %s | %s\n",
                $purchase['id'],
                $purchase['item_name'] ?? 'Unknown Item',
                $purchase['rarity'] ?? 'common',
                $purchase['qr_coins_spent'],
                $purchase['status'],
                $purchase['created_at']
            );
            if ($purchase['discount_code']) {
                echo "  Discount Code: " . $purchase['discount_code'] . "\n";
                echo "  Discount: " . $purchase['discount_percent'] . "%\n";
            }
            echo "\n";
        }
    }
    
    // 4. Check for Mismatches
    echo "=== MISMATCH ANALYSIS ===\n";
    
    // Find spending transactions without matching purchases
    $stmt = $pdo->prepare("
        SELECT 
            t.*
        FROM qr_coin_transactions t
        WHERE t.user_id = ? 
        AND t.amount < 0
        AND t.transaction_type IN ('spending', 'business_purchase')
        AND NOT EXISTS (
            SELECT 1 FROM business_purchases bp 
            WHERE bp.user_id = t.user_id 
            AND ABS(bp.qr_coins_spent) = ABS(t.amount)
            AND DATE(bp.created_at) = DATE(t.created_at)
        )
        AND NOT EXISTS (
            SELECT 1 FROM user_store_purchases usp 
            WHERE usp.user_id = t.user_id 
            AND ABS(usp.qr_coins_spent) = ABS(t.amount)
            AND DATE(usp.created_at) = DATE(t.created_at)
        )
        ORDER BY t.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $orphaned_spending = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($orphaned_spending)) {
        echo "✅ No orphaned spending transactions found.\n\n";
    } else {
        echo "⚠️  Found " . count($orphaned_spending) . " spending transactions without matching purchases:\n\n";
        foreach ($orphaned_spending as $transaction) {
            $metadata = json_decode($transaction['metadata'], true);
            echo sprintf(
                "Transaction ID: %d | %s | %+d coins | %s | %s\n",
                $transaction['id'],
                $transaction['category'],
                $transaction['amount'],
                $transaction['description'],
                $transaction['created_at']
            );
            if ($metadata) {
                echo "  Metadata: " . json_encode($metadata, JSON_UNESCAPED_SLASHES) . "\n";
            }
            echo "\n";
        }
        
        echo "🔧 To fix orphaned transactions, run: php fix_business_discount_sync.php\n\n";
    }
    
    // 5. Summary
    echo "=== SUMMARY ===\n";
    
    // Total QR coins balance
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as balance FROM qr_coin_transactions WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $balance = $stmt->fetchColumn();
    
    // Total spent on business purchases
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(qr_coins_spent), 0) as total_business FROM business_purchases WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $total_business = $stmt->fetchColumn();
    
    // Total spent on QR store
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(qr_coins_spent), 0) as total_qr FROM user_store_purchases WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $total_qr = $stmt->fetchColumn();
    
    echo "Current QR Coin Balance: {$balance}\n";
    echo "Total Business Purchases: {$total_business} coins\n";
    echo "Total QR Store Purchases: {$total_qr} coins\n";
    echo "Total Purchases: " . ($total_business + $total_qr) . " coins\n";
    
    if (!empty($orphaned_spending)) {
        $orphaned_total = array_sum(array_map(function($t) { return abs($t['amount']); }, $orphaned_spending));
        echo "\n⚠️  Orphaned Spending: {$orphaned_total} coins\n";
        echo "This might explain missing purchases in your account.\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n=== Diagnostic Complete ===\n";
?> 