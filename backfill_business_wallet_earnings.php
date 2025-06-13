<?php
/**
 * Backfill Business Wallet Earnings
 * Credits business wallets for previous store purchases that were made before the fix
 * 
 * This script finds all successful store purchases and credits the appropriate
 * business wallets with the earnings they should have received.
 */

require_once __DIR__ . '/html/core/config.php';
require_once __DIR__ . '/html/core/store_manager.php';

echo "=== BUSINESS WALLET BACKFILL SCRIPT ===\n\n";

try {
    $pdo->beginTransaction();
    
    // Find all store purchases that haven't been credited to business wallets
    echo "1. Finding uncredited store purchases...\n";
    
    $stmt = $pdo->prepare("
        SELECT 
            usp.id as purchase_id,
            usp.business_id,
            usp.qr_coins_spent,
            usp.user_id,
            usp.store_item_id,
            usp.purchase_code,
            usp.created_at,
            bsi.item_name,
            b.name as business_name
        FROM user_store_purchases usp
        JOIN business_store_items bsi ON usp.store_item_id = bsi.id  
        JOIN businesses b ON usp.business_id = b.id
        WHERE usp.status != 'cancelled'
        AND NOT EXISTS (
            SELECT 1 FROM business_qr_transactions bqt 
            WHERE bqt.reference_id = usp.id 
            AND bqt.reference_type = 'store_purchase'
            AND bqt.business_id = usp.business_id
        )
        ORDER BY usp.created_at ASC
    ");
    $stmt->execute();
    $uncredited_purchases = $stmt->fetchAll();
    
    $total_purchases = count($uncredited_purchases);
    echo "   ✅ Found {$total_purchases} uncredited purchases\n\n";
    
    if ($total_purchases === 0) {
        echo "   🎉 All purchases are already credited! No backfill needed.\n";
        $pdo->rollback();
        exit;
    }
    
    echo "2. Processing backfill for each business...\n";
    
    $businesses_processed = [];
    $total_qr_coins_credited = 0;
    $successful_credits = 0;
    
    foreach ($uncredited_purchases as $purchase) {
        $business_id = $purchase['business_id'];
        $business_name = $purchase['business_name'];
        $qr_coins_spent = $purchase['qr_coins_spent'];
        
        // Calculate business earnings (90% of QR coins, 10% platform fee)
        $business_earning = (int) ($qr_coins_spent * 0.9);
        
        // Ensure business wallet exists
        $stmt = $pdo->prepare("
            INSERT IGNORE INTO business_wallets (business_id, qr_coin_balance, total_earned_all_time, total_spent_all_time)
            VALUES (?, 0, 0, 0)
        ");
        $stmt->execute([$business_id]);
        
        // Get current balance
        $stmt = $pdo->prepare("SELECT qr_coin_balance FROM business_wallets WHERE business_id = ?");
        $stmt->execute([$business_id]);
        $current_balance = $stmt->fetchColumn() ?: 0;
        $new_balance = $current_balance + $business_earning;
        
        // Update wallet balance
        $stmt = $pdo->prepare("
            UPDATE business_wallets SET 
                qr_coin_balance = qr_coin_balance + ?,
                total_earned_all_time = total_earned_all_time + ?,
                last_transaction_at = NOW(),
                updated_at = NOW()
            WHERE business_id = ?
        ");
        $stmt->execute([$business_earning, $business_earning, $business_id]);
        
        // Record transaction with original purchase date
        $stmt = $pdo->prepare("
            INSERT INTO business_qr_transactions 
            (business_id, transaction_type, category, amount, balance_before, balance_after, description, metadata, reference_id, reference_type, created_at)
            VALUES (?, 'earning', 'store_sale_backfill', ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $business_id,
            $business_earning,
            $current_balance,
            $new_balance,
            "BACKFILL: Store sale - {$purchase['item_name']} (Purchase code: {$purchase['purchase_code']})",
            json_encode([
                'user_id' => $purchase['user_id'],
                'store_item_id' => $purchase['store_item_id'],
                'purchase_code' => $purchase['purchase_code'],
                'original_qr_cost' => $qr_coins_spent,
                'platform_fee' => $qr_coins_spent - $business_earning,
                'backfill_reason' => 'Missing business wallet credit from before fix implementation'
            ]),
            $purchase['purchase_id'],
            'store_purchase',
            $purchase['created_at']
        ]);
        
        // Update revenue sources for the purchase date
        $purchase_date = date('Y-m-d', strtotime($purchase['created_at']));
        $stmt = $pdo->prepare("
            INSERT INTO business_revenue_sources 
            (business_id, source_type, date_period, qr_coins_earned, transaction_count, metadata)
            VALUES (?, 'store_sales', ?, ?, 1, ?)
            ON DUPLICATE KEY UPDATE 
                qr_coins_earned = qr_coins_earned + VALUES(qr_coins_earned),
                transaction_count = transaction_count + 1,
                updated_at = NOW()
        ");
        $stmt->execute([
            $business_id,
            $purchase_date,
            $business_earning,
            json_encode([
                'backfill_date' => date('Y-m-d H:i:s'),
                'original_purchase_date' => $purchase['created_at']
            ])
        ]);
        
        // Track progress
        if (!isset($businesses_processed[$business_id])) {
            $businesses_processed[$business_id] = [
                'name' => $business_name,
                'credits' => 0,
                'total_earned' => 0
            ];
        }
        
        $businesses_processed[$business_id]['credits']++;
        $businesses_processed[$business_id]['total_earned'] += $business_earning;
        $total_qr_coins_credited += $business_earning;
        $successful_credits++;
        
        // Show progress every 10 purchases
        if ($successful_credits % 10 === 0) {
            echo "   📊 Processed {$successful_credits}/{$total_purchases} purchases...\n";
        }
    }
    
    $pdo->commit();
    
    echo "\n=== BACKFILL COMPLETE ===\n";
    echo "✅ Successfully processed {$successful_credits} purchases\n";
    echo "✅ Total QR coins credited: " . number_format($total_qr_coins_credited) . "\n";
    echo "✅ Businesses updated: " . count($businesses_processed) . "\n\n";
    
    echo "Business-by-business breakdown:\n";
    foreach ($businesses_processed as $business_id => $data) {
        echo "• {$data['name']}: {$data['credits']} purchases = " . number_format($data['total_earned']) . " QR coins\n";
    }
    
    echo "\n=== VERIFICATION ===\n";
    
    // Verify wallet balances
    $stmt = $pdo->query("
        SELECT 
            b.name as business_name,
            bw.qr_coin_balance,
            bw.total_earned_all_time,
            (SELECT COUNT(*) FROM business_qr_transactions WHERE business_id = b.id) as transaction_count
        FROM businesses b
        LEFT JOIN business_wallets bw ON b.id = bw.business_id
        WHERE bw.qr_coin_balance > 0
        ORDER BY bw.qr_coin_balance DESC
        LIMIT 10
    ");
    $wallets = $stmt->fetchAll();
    
    echo "Top business wallet balances after backfill:\n";
    foreach ($wallets as $wallet) {
        echo "• {$wallet['business_name']}: " . number_format($wallet['qr_coin_balance']) . " QR coins ({$wallet['transaction_count']} transactions)\n";
    }
    
    echo "\n🎉 Business wallets have been successfully backfilled!\n";
    echo "📱 Businesses can now see their earnings at: https://revenueqr.sharedvaluevending.com/business/wallet.php\n";
    echo "📊 Store analytics are updated at: https://revenueqr.sharedvaluevending.com/business/store.php\n\n";
    
} catch (Exception $e) {
    $pdo->rollback();
    echo "❌ Error during backfill: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
?> 