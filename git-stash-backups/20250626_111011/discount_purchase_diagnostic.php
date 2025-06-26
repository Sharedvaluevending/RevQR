<?php
/**
 * Discount Purchase Diagnostic Tool
 * Identifies and fixes issues preventing discount purchases
 */

session_start();
require_once __DIR__ . '/html/core/config.php';
require_once __DIR__ . '/html/core/session.php';
require_once __DIR__ . '/html/core/qr_coin_manager.php';
require_once __DIR__ . '/html/core/store_manager.php';

$diagnostics = [];
$user_id = $_SESSION['user_id'] ?? null;

echo "🔍 DISCOUNT PURCHASE DIAGNOSTIC TOOL\n";
echo "=====================================\n\n";

// 1. Check User Authentication
echo "1. CHECKING USER AUTHENTICATION:\n";
if (!$user_id) {
    echo "   ❌ User not logged in - Please log in first\n\n";
    exit;
} else {
    echo "   ✅ User authenticated (ID: $user_id)\n\n";
}

// 2. Check User Balance
echo "2. CHECKING QR COIN BALANCE:\n";
try {
    $user_balance = QRCoinManager::getBalance($user_id);
    echo "   ✅ Current balance: " . number_format($user_balance) . " QR coins\n";
    
    if ($user_balance <= 0) {
        echo "   ⚠️ ISSUE: Zero balance - you need to earn QR coins first\n";
        echo "   💡 FIX: Visit voting page or spin wheel to earn coins\n";
    } elseif ($user_balance < 50) {
        echo "   ⚠️ WARNING: Low balance - cheapest discounts start around 50 coins\n";
    }
    echo "\n";
} catch (Exception $e) {
    echo "   ❌ Balance check failed: " . $e->getMessage() . "\n\n";
}

// 3. Check Available Items
echo "3. CHECKING AVAILABLE DISCOUNT ITEMS:\n";
try {
    $business_store_items = StoreManager::getAllBusinessStoreItems(true);
    
    if (empty($business_store_items)) {
        echo "   ⚠️ No business discount items found\n";
        echo "   💡 TIP: Businesses need to add discount items to their stores\n";
    } else {
        echo "   ✅ Found " . count($business_store_items) . " business discount items\n";
        
        $affordable_count = 0;
        foreach ($business_store_items as $item) {
            $affordable = $user_balance >= $item['qr_coin_cost'];
            if ($affordable) $affordable_count++;
            $status = $affordable ? "✅ Affordable" : "❌ Too expensive";
            echo "      - {$item['item_name']} ({$item['business_name']}): {$item['qr_coin_cost']} coins - $status\n";
        }
        
        if ($affordable_count == 0) {
            echo "   ⚠️ WARNING: No business discounts are affordable with current balance\n";
        }
    }
    echo "\n";
} catch (Exception $e) {
    echo "   ❌ Store check failed: " . $e->getMessage() . "\n\n";
}

echo "🎯 LIKELY ISSUES & SOLUTIONS:\n";
if ($user_balance <= 0) {
    echo "❌ ZERO QR COINS - You need to earn coins first!\n";
    echo "Solutions:\n";
    echo "• Visit html/user/vote.php to vote and earn 5-30 coins per vote\n";
    echo "• Visit html/user/spin.php to spin wheel for 15-65 coins\n";
    echo "• Play casino games to potentially win coins\n\n";
} else {
    echo "✅ You have QR coins, so check for:\n";
    echo "• JavaScript errors in browser console (F12)\n";
    echo "• Purchase buttons not working\n";
    echo "• Items marked as out of stock\n\n";
}

echo "📍 STORE LOCATIONS:\n";
echo "• Business Stores: html/user/business-stores.php\n";
echo "• QR Store: html/user/qr-store.php\n";
echo "• Nayax Store: html/nayax/discount-store.php\n\n";

echo "Diagnostic complete!\n";
?> 