<?php
/**
 * Business Discount Transaction/Purchase Synchronization Repair Script
 * 
 * This script diagnoses and fixes issues where business discount transactions
 * exist but corresponding purchase records are missing.
 */

require_once __DIR__ . '/html/core/config.php';

echo "=== Business Discount Transaction/Purchase Sync Repair ===\n\n";

try {
    // Step 1: Find orphaned business discount transactions
    echo "Step 1: Finding orphaned business discount transactions...\n";
    
    $stmt = $pdo->prepare("
        SELECT 
            t.id as transaction_id,
            t.user_id,
            t.amount,
            t.description,
            t.metadata,
            t.created_at,
            u.username
        FROM qr_coin_transactions t
        LEFT JOIN users u ON t.user_id = u.id
        WHERE (t.transaction_type = 'business_purchase' OR t.category = 'business_discount_purchase')
        AND t.amount < 0
        AND NOT EXISTS (
            SELECT 1 FROM business_purchases bp 
            WHERE bp.user_id = t.user_id 
            AND ABS(bp.qr_coins_spent) = ABS(t.amount)
            AND DATE(bp.created_at) = DATE(t.created_at)
        )
        ORDER BY t.created_at DESC
    ");
    $stmt->execute();
    $orphaned_transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Found " . count($orphaned_transactions) . " orphaned transactions.\n\n";
    
    if (empty($orphaned_transactions)) {
        echo "✅ No orphaned transactions found. All business discount transactions have corresponding purchase records.\n";
        exit(0);
    }
    
    // Step 2: Analyze orphaned transactions
    echo "Step 2: Analyzing orphaned transactions...\n";
    foreach ($orphaned_transactions as $index => $transaction) {
        $meta_data = json_decode($transaction['metadata'], true);
        echo sprintf(
            "  %d. Transaction ID: %d, User: %s (%d), Amount: %d QR coins\n     Description: %s\n     Date: %s\n     Meta: %s\n\n",
            $index + 1,
            $transaction['transaction_id'],
            $transaction['username'],
            $transaction['user_id'],
            abs($transaction['amount']),
            $transaction['description'],
            $transaction['created_at'],
            json_encode($meta_data, JSON_PRETTY_PRINT)
        );
    }
    
    // Step 3: Prompt user for repair action
    echo "Step 3: Repair Options\n";
    echo "1. Automatically create missing purchase records\n";
    echo "2. Show detailed analysis only (no changes)\n";
    echo "3. Exit without changes\n\n";
    
    echo "Choose an option (1-3): ";
    $handle = fopen("php://stdin", "r");
    $choice = trim(fgets($handle));
    fclose($handle);
    
    switch ($choice) {
        case '1':
            echo "\nRepairing orphaned transactions...\n";
            repairOrphanedTransactions($pdo, $orphaned_transactions);
            break;
        case '2':
            echo "\nDetailed Analysis:\n";
            detailedAnalysis($pdo, $orphaned_transactions);
            break;
        case '3':
            echo "Exiting without changes.\n";
            exit(0);
        default:
            echo "Invalid choice. Exiting.\n";
            exit(1);
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

/**
 * Repair orphaned transactions by creating missing purchase records
 */
function repairOrphanedTransactions($pdo, $orphaned_transactions) {
    $pdo->beginTransaction();
    $repaired_count = 0;
    
    try {
        foreach ($orphaned_transactions as $transaction) {
            $meta_data = json_decode($transaction['metadata'], true);
            
            // Extract business discount purchase data from transaction
            $business_id = $meta_data['business_id'] ?? null;
            $item_id = $meta_data['item_id'] ?? null;
            $discount_percentage = $meta_data['discount_percentage'] ?? 10;
            $purchase_code = $meta_data['purchase_code'] ?? null;
            
            // Generate purchase code if missing
            if (!$purchase_code) {
                $purchase_code = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));
            }
            
            // Validate required data
            if (!$business_id || !$item_id) {
                echo "  ⚠️  Skipping transaction {$transaction['transaction_id']} - insufficient data\n";
                continue;
            }
            
            // Create missing purchase record
            $expires_at = date('Y-m-d H:i:s', strtotime($transaction['created_at'] . ' +30 days'));
            
            $stmt = $pdo->prepare("
                INSERT INTO business_purchases 
                (user_id, business_id, business_store_item_id, qr_coins_spent, discount_percentage, purchase_code, expires_at, status, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?)
            ");
            
            $result = $stmt->execute([
                $transaction['user_id'],
                $business_id,
                $item_id,
                abs($transaction['amount']),
                $discount_percentage,
                $purchase_code,
                $expires_at,
                $transaction['created_at']
            ]);
            
            if ($result) {
                $purchase_id = $pdo->lastInsertId();
                echo "  ✅ Created purchase record {$purchase_id} for transaction {$transaction['transaction_id']}\n";
                $repaired_count++;
                
                // Generate QR code for the purchase if needed
                $purchase_data = [
                    'purchase_code' => $purchase_code,
                    'business_id' => $business_id,
                    'discount_percentage' => $discount_percentage,
                    'expires_at' => $expires_at,
                    'user_id' => $transaction['user_id']
                ];
                
                try {
                    $qr_result = QRCodeManager::generateDiscountQRCode($purchase_id, $purchase_data);
                    if ($qr_result['success']) {
                        echo "    📱 QR code generated successfully\n";
                    }
                } catch (Exception $e) {
                    echo "    ⚠️  QR code generation failed: " . $e->getMessage() . "\n";
                }
            }
        }
        
        $pdo->commit();
        echo "\n✅ Repair completed! Created {$repaired_count} missing purchase records.\n";
        
    } catch (Exception $e) {
        $pdo->rollback();
        echo "\n❌ Repair failed: " . $e->getMessage() . "\n";
        throw $e;
    }
}

/**
 * Detailed analysis of orphaned transactions
 */
function detailedAnalysis($pdo, $orphaned_transactions) {
    echo "\n=== DETAILED ANALYSIS ===\n\n";
    
    // Group by user
    $users = [];
    foreach ($orphaned_transactions as $transaction) {
        $user_id = $transaction['user_id'];
        if (!isset($users[$user_id])) {
            $users[$user_id] = [
                'username' => $transaction['username'],
                'transactions' => [],
                'total_amount' => 0
            ];
        }
        $users[$user_id]['transactions'][] = $transaction;
        $users[$user_id]['total_amount'] += abs($transaction['amount']);
    }
    
    foreach ($users as $user_id => $user_data) {
        echo "User: {$user_data['username']} (ID: {$user_id})\n";
        echo "Total affected amount: {$user_data['total_amount']} QR coins\n";
        echo "Number of orphaned transactions: " . count($user_data['transactions']) . "\n";
        
        foreach ($user_data['transactions'] as $transaction) {
            $meta_data = json_decode($transaction['metadata'], true);
            echo "  - Transaction {$transaction['transaction_id']}: {$transaction['amount']} coins on {$transaction['created_at']}\n";
            echo "    Description: {$transaction['description']}\n";
            if ($meta_data) {
                echo "    Business ID: " . ($meta_data['business_id'] ?? 'N/A') . "\n";
                echo "    Item ID: " . ($meta_data['item_id'] ?? 'N/A') . "\n";
                echo "    Discount: " . ($meta_data['discount_percentage'] ?? 'N/A') . "%\n";
            }
        }
        echo "\n";
    }
    
    // Check for duplicate business purchases that might be hiding the issue
    echo "=== CHECKING FOR EXISTING PURCHASES ===\n\n";
    
    foreach ($orphaned_transactions as $transaction) {
        $stmt = $pdo->prepare("
            SELECT bp.*, bsi.item_name, b.name as business_name
            FROM business_purchases bp
            LEFT JOIN business_store_items bsi ON bp.business_store_item_id = bsi.id
            LEFT JOIN businesses b ON bp.business_id = b.id
            WHERE bp.user_id = ? 
            AND DATE(bp.created_at) = DATE(?)
            ORDER BY bp.created_at DESC
        ");
        $stmt->execute([$transaction['user_id'], $transaction['created_at']]);
        $existing_purchases = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($existing_purchases)) {
            echo "Transaction {$transaction['transaction_id']} - Found {count($existing_purchases)} purchases on same date:\n";
            foreach ($existing_purchases as $purchase) {
                echo "  - Purchase {$purchase['id']}: {$purchase['item_name']} ({$purchase['discount_percentage']}%) - {$purchase['qr_coins_spent']} coins\n";
            }
            echo "\n";
        }
    }
}

echo "\n=== Script completed ===\n";
?> 