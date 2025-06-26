<?php
require_once __DIR__ . '/html/core/config.php';
require_once __DIR__ . '/html/core/qr_coin_manager.php';
require_once __DIR__ . '/html/core/loot_box_manager.php';

echo "=== PURCHASE SYSTEM TESTING ===\n\n";

// Test user ID (you can change this to test with different users)
$test_user_id = 1; // Change this to test with a different user

echo "🧪 Testing with User ID: {$test_user_id}\n\n";

// 1. Check current QR coin balance
echo "1️⃣ CHECKING QR COIN BALANCE:\n";
try {
    $balance = QRCoinManager::getBalance($test_user_id);
    echo "   Current balance: {$balance} QR coins\n\n";
} catch (Exception $e) {
    echo "   ❌ Error getting balance: {$e->getMessage()}\n\n";
}

// 2. Check available items in QR store
echo "2️⃣ CHECKING AVAILABLE ITEMS:\n";
try {
    $stmt = $pdo->prepare("
        SELECT id, item_name, item_type, qr_coin_cost, rarity, is_active
        FROM qr_store_items 
        WHERE is_active = 1 
        AND item_type IN ('spin_pack', 'vote_pack', 'loot_box', 'slot_pack')
        ORDER BY item_type, qr_coin_cost
    ");
    $stmt->execute();
    $available_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($available_items)) {
        echo "   No items available in store\n";
    } else {
        foreach ($available_items as $item) {
            echo "   {$item['item_type']}: {$item['item_name']} - {$item['qr_coin_cost']} QR coins ({$item['rarity']})\n";
        }
    }
} catch (Exception $e) {
    echo "   ❌ Error checking available items: {$e->getMessage()}\n";
}
echo "\n";

// 3. Check current user purchases
echo "3️⃣ CHECKING CURRENT PURCHASES:\n";
try {
    $stmt = $pdo->prepare("
        SELECT 
            uqsp.id,
            uqsp.status,
            uqsp.created_at,
            qsi.item_name,
            qsi.item_type,
            qsi.rarity
        FROM user_qr_store_purchases uqsp
        JOIN qr_store_items qsi ON uqsp.qr_store_item_id = qsi.id
        WHERE uqsp.user_id = ?
        AND qsi.item_type IN ('spin_pack', 'vote_pack', 'loot_box', 'slot_pack')
        ORDER BY uqsp.created_at DESC
    ");
    $stmt->execute([$test_user_id]);
    $user_purchases = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($user_purchases)) {
        echo "   No purchases found for this user\n";
    } else {
        foreach ($user_purchases as $purchase) {
            echo "   {$purchase['item_type']}: {$purchase['item_name']} - Status: {$purchase['status']} (Created: {$purchase['created_at']})\n";
        }
    }
} catch (Exception $e) {
    echo "   ❌ Error checking purchases: {$e->getMessage()}\n";
}
echo "\n";

// 4. Check spin pack functionality
echo "4️⃣ TESTING SPIN PACK FUNCTIONALITY:\n";
try {
    $stmt = $pdo->prepare("
        SELECT 
            uqsp.*,
            qsi.item_name,
            qsi.item_data
        FROM user_qr_store_purchases uqsp
        JOIN qr_store_items qsi ON uqsp.qr_store_item_id = qsi.id
        WHERE uqsp.user_id = ? 
        AND qsi.item_type = 'spin_pack' 
        AND uqsp.status = 'active'
        AND (uqsp.expires_at IS NULL OR uqsp.expires_at > NOW())
        ORDER BY uqsp.created_at ASC
    ");
    $stmt->execute([$test_user_id]);
    $active_spin_packs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($active_spin_packs)) {
        echo "   No active spin packs found\n";
    } else {
        foreach ($active_spin_packs as $pack) {
            $pack_data = json_decode($pack['item_data'], true);
            echo "   Active: {$pack['item_name']} - {$pack_data['spins_per_day']} spins/day (Expires: {$pack['expires_at']})\n";
        }
    }

    // Check daily spins used
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as spins_used_today
        FROM spin_results 
        WHERE user_id = ? 
        AND DATE(spin_time) = CURDATE()
    ");
    $stmt->execute([$test_user_id]);
    $daily_spins_used = $stmt->fetchColumn();
    echo "   Daily spins used today: {$daily_spins_used}\n";
} catch (Exception $e) {
    echo "   ❌ Error checking spin packs: {$e->getMessage()}\n";
}
echo "\n";

// 5. Check vote pack functionality
echo "5️⃣ TESTING VOTE PACK FUNCTIONALITY:\n";
try {
    $stmt = $pdo->prepare("
        SELECT 
            uvp.*,
            uqsp.created_at as purchase_date
        FROM user_vote_packs uvp
        JOIN user_qr_store_purchases uqsp ON uvp.purchase_id = uqsp.id
        WHERE uvp.user_id = ?
        AND uvp.votes_remaining > 0
        AND (uvp.expires_at IS NULL OR uvp.expires_at > NOW())
        ORDER BY uqsp.created_at ASC
    ");
    $stmt->execute([$test_user_id]);
    $active_vote_packs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($active_vote_packs)) {
        echo "   No active vote packs found\n";
    } else {
        foreach ($active_vote_packs as $pack) {
            echo "   Active: {$pack['votes_remaining']} votes remaining (Expires: {$pack['expires_at']})\n";
        }
    }
} catch (Exception $e) {
    echo "   ❌ Error checking vote packs: {$e->getMessage()}\n";
}
echo "\n";

// 6. Check loot box functionality
echo "6️⃣ TESTING LOOT BOX FUNCTIONALITY:\n";
try {
    $stmt = $pdo->prepare("
        SELECT 
            uqsp.id as purchase_id,
            uqsp.created_at,
            qsi.item_name,
            qsi.rarity,
            qsi.item_data
        FROM user_qr_store_purchases uqsp
        JOIN qr_store_items qsi ON uqsp.qr_store_item_id = qsi.id
        WHERE uqsp.user_id = ? 
        AND qsi.item_type = 'loot_box' 
        AND uqsp.status = 'active'
        AND uqsp.id NOT IN (SELECT purchase_id FROM loot_box_openings WHERE user_id = ?)
        ORDER BY uqsp.created_at DESC
    ");
    $stmt->execute([$test_user_id, $test_user_id]);
    $unopened_loot_boxes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($unopened_loot_boxes)) {
        echo "   No unopened loot boxes found\n";
    } else {
        foreach ($unopened_loot_boxes as $loot_box) {
            echo "   Unopened: {$loot_box['item_name']} ({$loot_box['rarity']}) - Purchase ID: {$loot_box['purchase_id']}\n";
        }
    }

    // Check loot box opening history
    $stmt = $pdo->prepare("
        SELECT 
            lbo.opened_at,
            qsi.item_name,
            qsi.rarity,
            lbo.total_rewards
        FROM loot_box_openings lbo
        JOIN qr_store_items qsi ON lbo.qr_store_item_id = qsi.id
        WHERE lbo.user_id = ?
        ORDER BY lbo.opened_at DESC
        LIMIT 5
    ");
    $stmt->execute([$test_user_id]);
    $loot_box_history = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($loot_box_history)) {
        echo "   Recent openings:\n";
        foreach ($loot_box_history as $opening) {
            echo "     {$opening['item_name']} ({$opening['rarity']}) - {$opening['total_rewards']} rewards - {$opening['opened_at']}\n";
        }
    }
} catch (Exception $e) {
    echo "   ❌ Error checking loot boxes: {$e->getMessage()}\n";
}
echo "\n";

// 7. Check for potential issues
echo "7️⃣ CHECKING FOR POTENTIAL ISSUES:\n";

try {
    // Check for stuck modals (orphaned loot box openings)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as orphaned_openings
        FROM loot_box_openings lbo
        LEFT JOIN user_qr_store_purchases uqsp ON lbo.purchase_id = uqsp.id
        WHERE lbo.user_id = ? AND uqsp.id IS NULL
    ");
    $stmt->execute([$test_user_id]);
    $orphaned_openings = $stmt->fetchColumn();

    if ($orphaned_openings > 0) {
        echo "   ⚠️  Found {$orphaned_openings} orphaned loot box openings\n";
    } else {
        echo "   ✅ No orphaned loot box openings found\n";
    }

    // Check for expired but not marked as used purchases
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as expired_active
        FROM user_qr_store_purchases uqsp
        JOIN qr_store_items qsi ON uqsp.qr_store_item_id = qsi.id
        WHERE uqsp.user_id = ? 
        AND uqsp.status = 'active'
        AND uqsp.expires_at IS NOT NULL 
        AND uqsp.expires_at <= NOW()
    ");
    $stmt->execute([$test_user_id]);
    $expired_active = $stmt->fetchColumn();

    if ($expired_active > 0) {
        echo "   ⚠️  Found {$expired_active} expired but still active purchases\n";
    } else {
        echo "   ✅ No expired active purchases found\n";
    }

    // Check for vote packs with negative remaining votes
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as negative_votes
        FROM user_vote_packs
        WHERE user_id = ? AND votes_remaining < 0
    ");
    $stmt->execute([$test_user_id]);
    $negative_votes = $stmt->fetchColumn();

    if ($negative_votes > 0) {
        echo "   ⚠️  Found {$negative_votes} vote packs with negative remaining votes\n";
    } else {
        echo "   ✅ No vote packs with negative votes found\n";
    }
} catch (Exception $e) {
    echo "   ❌ Error checking for issues: {$e->getMessage()}\n";
}

echo "\n";

// 8. Test purchase simulation
echo "8️⃣ PURCHASE SIMULATION (READ-ONLY):\n";
echo "   To test actual purchases, you would need to:\n";
echo "   1. Go to /html/user/qr-store.php\n";
echo "   2. Click 'Confirm Purchase' on any item\n";
echo "   3. Check if the purchase completes successfully\n";
echo "   4. For loot boxes, click 'Open Now!' to test the popup\n\n";

// 9. Test loot box opening simulation
echo "9️⃣ LOOT BOX OPENING SIMULATION:\n";
if (!empty($unopened_loot_boxes)) {
    $test_loot_box = $unopened_loot_boxes[0];
    echo "   Testing opening: {$test_loot_box['item_name']} (Purchase ID: {$test_loot_box['purchase_id']})\n";
    
    // Simulate the opening process
    try {
        $result = LootBoxManager::openLootBox($test_user_id, $test_loot_box['purchase_id']);
        
        if ($result['success']) {
            echo "   ✅ Loot box opened successfully!\n";
            echo "   Rewards received:\n";
            foreach ($result['rewards'] as $reward) {
                echo "     - {$reward['display']} ({$reward['rarity']})\n";
            }
        } else {
            echo "   ❌ Failed to open loot box: {$result['message']}\n";
        }
    } catch (Exception $e) {
        echo "   ❌ Error opening loot box: {$e->getMessage()}\n";
    }
} else {
    echo "   No loot boxes available for testing\n";
}

echo "\n=== TEST COMPLETE ===\n";
echo "\nRECOMMENDATIONS:\n";
echo "1. If you found issues, check the browser console for JavaScript errors\n";
echo "2. For stuck popups, try refreshing the page or clearing browser cache\n";
echo "3. Test purchases with different item types to ensure all work correctly\n";
echo "4. Monitor the loot box opening animation to ensure it completes properly\n"; 