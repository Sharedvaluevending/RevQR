<?php
/**
 * Priority 3 Phase 1: Fix Machine Items Without Master Links
 * Addresses 66 machine items that lack master_item_id references
 */

require_once __DIR__ . '/html/core/config.php';

echo "🔧 PRIORITY 3 PHASE 1: FIX UNLINKED MACHINE ITEMS\n";
echo "==================================================\n\n";

function createBackup($pdo) {
    echo "💾 Creating backup tables...\n";
    
    try {
        // Backup voting_list_items
        $pdo->exec("CREATE TABLE IF NOT EXISTS voting_list_items_backup_phase3 AS SELECT * FROM voting_list_items");
        $backup_count = $pdo->query("SELECT COUNT(*) FROM voting_list_items_backup_phase3")->fetchColumn();
        echo "  ✅ voting_list_items backup: $backup_count records\n";
        
        // Backup master_items  
        $pdo->exec("CREATE TABLE IF NOT EXISTS master_items_backup_phase3 AS SELECT * FROM master_items WHERE 1=0");
        $pdo->exec("INSERT INTO master_items_backup_phase3 SELECT * FROM master_items");
        $master_backup_count = $pdo->query("SELECT COUNT(*) FROM master_items_backup_phase3")->fetchColumn();
        echo "  ✅ master_items backup: $master_backup_count records\n";
        
        return true;
    } catch (Exception $e) {
        echo "  ❌ Backup failed: " . $e->getMessage() . "\n";
        return false;
    }
}

function analyzeUnlinkedItems($pdo) {
    echo "\n🔍 Analyzing unlinked machine items...\n";
    
    try {
        // Get all unlinked items with details
        $stmt = $pdo->query("
            SELECT 
                vli.id,
                vli.item_name,
                vli.item_category,
                vli.retail_price,
                vli.cost_price,
                vli.inventory,
                vl.name as machine_name,
                vl.business_id
            FROM voting_list_items vli
            JOIN voting_lists vl ON vli.voting_list_id = vl.id
            WHERE vli.master_item_id IS NULL OR vli.master_item_id = 0
            ORDER BY vli.item_name, vl.name
        ");
        
        $unlinked_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "  📊 Found " . count($unlinked_items) . " unlinked items:\n\n";
        
        // Group by item name for analysis
        $grouped_items = [];
        foreach ($unlinked_items as $item) {
            $key = strtolower(trim($item['item_name']));
            if (!isset($grouped_items[$key])) {
                $grouped_items[$key] = [];
            }
            $grouped_items[$key][] = $item;
        }
        
        echo "  📈 Item distribution:\n";
        foreach ($grouped_items as $item_name => $instances) {
            echo "    • '$item_name': " . count($instances) . " machines\n";
        }
        
        return $unlinked_items;
        
    } catch (Exception $e) {
        echo "  ❌ Analysis failed: " . $e->getMessage() . "\n";
        return [];
    }
}

function linkItemsToMaster($pdo, $unlinked_items) {
    echo "\n🔗 Linking machine items to master items...\n";
    
    $stats = [
        'linked_existing' => 0,
        'created_new' => 0,
        'failed' => 0
    ];
    
    try {
        $pdo->beginTransaction();
        
        foreach ($unlinked_items as $item) {
            $item_name = trim($item['item_name']);
            $category = trim($item['item_category'] ?: 'General');
            
            echo "  🔧 Processing: '$item_name' (ID: {$item['id']})\n";
            
            // Try to find existing master item by name (case-insensitive)
            $stmt = $pdo->prepare("
                SELECT id, name, category 
                FROM master_items 
                WHERE LOWER(TRIM(name)) = LOWER(TRIM(?))
                LIMIT 1
            ");
            $stmt->execute([$item_name]);
            $master_item = $stmt->fetch();
            
            $master_item_id = null;
            
            if ($master_item) {
                // Link to existing master item
                $master_item_id = $master_item['id'];
                echo "    ✅ Found existing master item: ID $master_item_id\n";
                $stats['linked_existing']++;
                
            } else {
                // Create new master item
                echo "    🆕 Creating new master item...\n";
                
                $stmt = $pdo->prepare("
                    INSERT INTO master_items (
                        name, category, type, suggested_price, suggested_cost,
                        popularity, shelf_life, status, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, 'active', NOW())
                ");
                
                // Determine item type from category
                $item_type = 'general';
                $category_lower = strtolower($category);
                if (strpos($category_lower, 'drink') !== false || strpos($category_lower, 'beverage') !== false) {
                    $item_type = 'drink';
                } elseif (strpos($category_lower, 'snack') !== false || strpos($category_lower, 'food') !== false) {
                    $item_type = 'snack';
                } elseif (strpos($category_lower, 'candy') !== false || strpos($category_lower, 'sweet') !== false) {
                    $item_type = 'candy';
                }
                
                // Use retail price as suggested price, estimate cost
                $suggested_price = floatval($item['retail_price']) ?: 1.00;
                $suggested_cost = floatval($item['cost_price']) ?: ($suggested_price * 0.6); // 40% margin
                
                $stmt->execute([
                    $item_name,
                    $category,
                    $item_type,
                    $suggested_price,
                    $suggested_cost,
                    'medium', // default popularity
                    30 // default shelf life
                ]);
                
                $master_item_id = $pdo->lastInsertId();
                echo "    ✅ Created master item: ID $master_item_id\n";
                $stats['created_new']++;
            }
            
            // Update the voting_list_item with master_item_id
            if ($master_item_id) {
                $stmt = $pdo->prepare("
                    UPDATE voting_list_items 
                    SET master_item_id = ? 
                    WHERE id = ?
                ");
                $stmt->execute([$master_item_id, $item['id']]);
                echo "    🔗 Linked machine item to master item\n";
            } else {
                echo "    ❌ Failed to link item\n";
                $stats['failed']++;
            }
            
            echo "\n";
        }
        
        $pdo->commit();
        echo "✅ Transaction committed successfully\n\n";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "❌ Transaction failed: " . $e->getMessage() . "\n";
        $stats['failed'] = count($unlinked_items);
    }
    
    return $stats;
}

function verifyFix($pdo) {
    echo "🔍 Verifying the fix...\n";
    
    try {
        // Check remaining unlinked items
        $remaining = $pdo->query("
            SELECT COUNT(*) FROM voting_list_items 
            WHERE master_item_id IS NULL OR master_item_id = 0
        ")->fetchColumn();
        
        if ($remaining == 0) {
            echo "  ✅ All machine items now linked to master items\n";
        } else {
            echo "  ⚠️  $remaining machine items still unlinked\n";
        }
        
        // Check master items count
        $master_count = $pdo->query("SELECT COUNT(*) FROM master_items")->fetchColumn();
        echo "  📊 Total master items: $master_count\n";
        
        // Check linked items count
        $linked_count = $pdo->query("
            SELECT COUNT(DISTINCT vli.master_item_id) 
            FROM voting_list_items vli 
            WHERE vli.master_item_id IS NOT NULL AND vli.master_item_id > 0
        ")->fetchColumn();
        echo "  🔗 Master items with machine links: $linked_count\n";
        
        // Check business isolation
        $isolation_check = $pdo->query("
            SELECT COUNT(*) FROM voting_list_items vli
            JOIN voting_lists vl ON vli.voting_list_id = vl.id
            WHERE vl.business_id IS NULL
        ")->fetchColumn();
        
        if ($isolation_check == 0) {
            echo "  ✅ Business isolation maintained\n";
        } else {
            echo "  ⚠️  $isolation_check items with isolation issues\n";
        }
        
        return $remaining == 0;
        
    } catch (Exception $e) {
        echo "  ❌ Verification failed: " . $e->getMessage() . "\n";
        return false;
    }
}

function logProgress($pdo, $stats) {
    echo "\n📝 Logging progress...\n";
    
    try {
        $message = "Phase 1 completed: Linked {$stats['linked_existing']} existing, created {$stats['created_new']} new master items, {$stats['failed']} failed";
        
        $stmt = $pdo->prepare("
            INSERT INTO migration_log (phase, step, status, message) 
            VALUES ('item_machine_fix', 1, ?, ?)
        ");
        
        $status = $stats['failed'] == 0 ? 'success' : 'partial';
        $stmt->execute([$status, $message]);
        
        echo "  ✅ Progress logged\n";
        
    } catch (Exception $e) {
        echo "  ⚠️  Logging failed: " . $e->getMessage() . "\n";
    }
}

// Execute Phase 1 Fix
try {
    echo "🚀 Starting Phase 1: Fix Unlinked Machine Items\n\n";
    
    // Step 1: Create backups
    if (!createBackup($pdo)) {
        throw new Exception("Backup creation failed - aborting");
    }
    
    // Step 2: Analyze unlinked items
    $unlinked_items = analyzeUnlinkedItems($pdo);
    if (empty($unlinked_items)) {
        echo "✅ No unlinked items found - Phase 1 not needed\n";
        exit(0);
    }
    
    // Step 3: Link items to master items
    $stats = linkItemsToMaster($pdo, $unlinked_items);
    
    // Step 4: Verify the fix
    $success = verifyFix($pdo);
    
    // Step 5: Log progress
    logProgress($pdo, $stats);
    
    // Summary
    echo "\n🎉 PHASE 1 COMPLETE\n";
    echo "===================\n";
    echo "• Processed: " . count($unlinked_items) . " items\n";
    echo "• Linked to existing: {$stats['linked_existing']}\n";
    echo "• Created new: {$stats['created_new']}\n";
    echo "• Failed: {$stats['failed']}\n";
    echo "• Success rate: " . round((1 - $stats['failed'] / count($unlinked_items)) * 100, 1) . "%\n\n";
    
    if ($success) {
        echo "✅ Ready for Phase 2: Address orphaned master items\n";
    } else {
        echo "⚠️  Some issues remain - review before Phase 2\n";
    }
    
} catch (Exception $e) {
    echo "❌ Phase 1 failed: " . $e->getMessage() . "\n";
} 