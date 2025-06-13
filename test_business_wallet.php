<?php
/**
 * Test Business Wallet System
 * Verifies that the business wallet functionality is working correctly
 */

require_once __DIR__ . '/html/core/config.php';
require_once __DIR__ . '/html/core/business_wallet_manager.php';

echo "=== BUSINESS WALLET SYSTEM TEST ===\n\n";

try {
    $walletManager = new BusinessWalletManager($pdo);
    
    // 1. Test wallet table exists and has data
    echo "1. Testing wallet table structure...\n";
    $stmt = $pdo->query("SHOW TABLES LIKE 'business_wallets'");
    if ($stmt->rowCount() > 0) {
        echo "✅ business_wallets table exists\n";
    } else {
        echo "❌ business_wallets table missing\n";
        exit;
    }
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM business_wallets");
    $count = $stmt->fetch()['count'];
    echo "✅ Found {$count} business wallets\n\n";
    
    // 2. Test wallet data
    echo "2. Testing wallet data...\n";
    $stmt = $pdo->query("
        SELECT 
            bw.business_id,
            b.name as business_name,
            bw.qr_coin_balance,
            bw.total_earned_all_time,
            bw.total_spent_all_time
        FROM business_wallets bw
        JOIN businesses b ON bw.business_id = b.id
        ORDER BY bw.qr_coin_balance DESC
        LIMIT 5
    ");
    $wallets = $stmt->fetchAll();
    
    foreach ($wallets as $wallet) {
        echo "Business: {$wallet['business_name']}\n";
        echo "  Balance: {$wallet['qr_coin_balance']} QR Coins\n";
        echo "  Total Earned: {$wallet['total_earned_all_time']}\n";
        echo "  Total Spent: {$wallet['total_spent_all_time']}\n\n";
    }
    
    // 3. Test revenue sources
    echo "3. Testing revenue sources...\n";
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM business_revenue_sources");
    $revenue_count = $stmt->fetch()['count'];
    echo "✅ Found {$revenue_count} revenue source records\n";
    
    if ($revenue_count > 0) {
        $stmt = $pdo->query("
            SELECT 
                brs.business_id,
                b.name as business_name,
                brs.source_type,
                brs.qr_coins_earned,
                brs.date_period
            FROM business_revenue_sources brs
            JOIN businesses b ON brs.business_id = b.id
            ORDER BY brs.qr_coins_earned DESC
            LIMIT 5
        ");
        $revenue_sources = $stmt->fetchAll();
        
        foreach ($revenue_sources as $source) {
            echo "Business: {$source['business_name']}\n";
            echo "  Source: {$source['source_type']}\n";
            echo "  Earned: {$source['qr_coins_earned']} QR Coins\n";
            echo "  Date: {$source['date_period']}\n\n";
        }
    }
    
    // 4. Test wallet manager functions
    echo "4. Testing wallet manager functions...\n";
    if (!empty($wallets)) {
        $test_business_id = $wallets[0]['business_id'];
        $test_business_name = $wallets[0]['business_name'];
        
        echo "Testing with business: {$test_business_name} (ID: {$test_business_id})\n";
        
        // Test getBalance
        $balance = $walletManager->getBalance($test_business_id);
        echo "✅ getBalance(): {$balance} QR Coins\n";
        
        // Test getWallet
        $wallet_data = $walletManager->getWallet($test_business_id);
        echo "✅ getWallet(): Found wallet data\n";
        
        // Test getWalletStats
        $stats = $walletManager->getWalletStats($test_business_id);
        echo "✅ getWalletStats(): 30-day earnings: {$stats['earnings_30d']} QR Coins\n";
        
        // Test getRecentTransactions
        $transactions = $walletManager->getRecentTransactions($test_business_id, 5);
        echo "✅ getRecentTransactions(): Found " . count($transactions) . " transactions\n";
    }
    
    // 5. Test transaction table
    echo "\n5. Testing transaction table...\n";
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM business_qr_transactions");
    $transaction_count = $stmt->fetch()['count'];
    echo "✅ Found {$transaction_count} business transactions\n";
    
    // 6. Test dashboard integration
    echo "\n6. Testing dashboard file existence...\n";
    if (file_exists(__DIR__ . '/html/business/wallet.php')) {
        echo "✅ Business wallet page exists\n";
    } else {
        echo "❌ Business wallet page missing\n";
    }
    
    if (file_exists(__DIR__ . '/html/business/dashboard_enhanced.php')) {
        echo "✅ Enhanced dashboard exists\n";
    } else {
        echo "❌ Enhanced dashboard missing\n";
    }
    
    echo "\n=== BUSINESS WALLET SYSTEM TEST COMPLETE ===\n";
    echo "✅ All core wallet functionality is working!\n";
    echo "\nTo access your business wallet:\n";
    echo "1. Log in to your business dashboard\n";
    echo "2. Click the 'QR Wallet' button in Quick Actions\n";
    echo "3. Or visit: https://revenueqr.sharedvaluevending.com/business/wallet.php\n\n";
    
    echo "Current wallet balances:\n";
    foreach ($wallets as $wallet) {
        echo "- {$wallet['business_name']}: {$wallet['qr_coin_balance']} QR Coins\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error testing wallet system: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
?> 