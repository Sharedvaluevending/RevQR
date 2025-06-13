<?php
/**
 * Priority 3 Phase 2: Address Orphaned Master Items
 * Handles orphaned master items not linked to any machines
 */

require_once __DIR__ . '/html/core/config.php';

echo "🔧 PRIORITY 3 PHASE 2: ADDRESS ORPHANED MASTER ITEMS\n";
echo "====================================================\n\n";

function analyzeOrphanedItems($pdo) {
    echo "🔍 Analyzing orphaned master items...\n";
    
    try {
        $stmt = $pdo->query("
            SELECT 
                mi.id, mi.name, mi.category, mi.type, mi.suggested_price,
                mi.suggested_cost, mi.brand, mi.status, mi.created_at,
                CASE 
                    WHEN mi.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 'recent'
                    WHEN mi.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY) THEN 'moderate'
                    ELSE 'old'
                END as age_category
            FROM master_items mi
            LEFT JOIN voting_list_items vli ON mi.id = vli.master_item_id
            WHERE vli.master_item_id IS NULL
            ORDER BY mi.created_at DESC, mi.category, mi.name
        ");
        
        $orphaned_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "  📊 Found " . count($orphaned_items) . " orphaned master items\n\n";
        
        // Analyze by category, age, status
        $by_category = []; $by_age = []; $by_status = [];
        foreach ($orphaned_items as $item) {
            $category = $item['category'] ?: 'Uncategorized';
            $by_category[$category] = ($by_category[$category] ?? 0) + 1;
            $by_age[$item['age_category']] = ($by_age[$item['age_category']] ?? 0) + 1;
            $by_status[$item['status']] = ($by_status[$item['status']] ?? 0) + 1;
        }
        
        echo "  📈 Top categories:\n";
        arsort($by_category);
        foreach (array_slice($by_category, 0, 8, true) as $category => $count) {
            echo "    • $category: $count items\n";
        }
        
        echo "\n  ⏰ By age: ";
        foreach ($by_age as $age => $count) echo "$age: $count  ";
        
        echo "\n  📊 By status: ";
        foreach ($by_status as $status => $count) echo "$status: $count  ";
        echo "\n\n";
        
        return $orphaned_items;
    } catch (Exception $e) {
        echo "  ❌ Analysis failed: " . $e->getMessage() . "\n";
        return [];
    }
}

function categorizeForCleanup($orphaned_items) {
    echo "🎯 Categorizing items for cleanup actions...\n";
    
    $categories = [
        'duplicates' => [], 'inactive' => [], 'recent_unused' => [],
        'potential_cleanup' => [], 'keep_available' => []
    ];
    
    // Group by name for duplicate detection
    $name_groups = [];
    foreach ($orphaned_items as $item) {
        $key = strtolower(trim($item['name']));
        $name_groups[$key][] = $item;
    }
    
    foreach ($orphaned_items as $item) {
        $item_name = strtolower(trim($item['name']));
        
        if (count($name_groups[$item_name]) > 1) {
            $categories['duplicates'][] = $item;
        } elseif ($item['status'] !== 'active') {
            $categories['inactive'][] = $item;
        } elseif ($item['age_category'] === 'recent') {
            $categories['recent_unused'][] = $item;
        } elseif (empty(trim($item['name'])) || strlen(trim($item['name'])) < 2) {
            $categories['potential_cleanup'][] = $item;
        } else {
            $categories['keep_available'][] = $item;
        }
    }
    
    echo "  📋 Actions planned:\n";
    foreach ($categories as $category => $items) {
        echo "    • " . str_replace('_', ' ', $category) . ": " . count($items) . " items\n";
    }
    
    return $categories;
}

function executeCleanupActions($pdo, $categories) {
    echo "\n🧹 Executing cleanup actions...\n";
    
    $stats = ['duplicates_removed' => 0, 'inactive_archived' => 0, 'empty_removed' => 0, 'kept_available' => 0, 'failed' => 0];
    
    try {
        $pdo->beginTransaction();
        
        // Remove empty/invalid items
        foreach ($categories['potential_cleanup'] as $item) {
            if (empty(trim($item['name'])) || strlen(trim($item['name'])) < 2) {
                $stmt = $pdo->prepare("DELETE FROM master_items WHERE id = ?");
                if ($stmt->execute([$item['id']])) {
                    $stats['empty_removed']++;
                }
            }
        }
        echo "  🗑️  Removed {$stats['empty_removed']} items with empty names\n";
        
        // Archive inactive items
        foreach ($categories['inactive'] as $item) {
            $stmt = $pdo->prepare("UPDATE master_items SET status = 'archived', name = CONCAT('[ARCHIVED] ', name) WHERE id = ? AND status != 'archived'");
            if ($stmt->execute([$item['id']])) {
                $stats['inactive_archived']++;
            }
        }
        echo "  📦 Archived {$stats['inactive_archived']} inactive items\n";
        
        // Handle duplicates - keep oldest, mark others
        $processed_names = [];
        foreach ($categories['duplicates'] as $item) {
            $name_key = strtolower(trim($item['name']));
            if (!isset($processed_names[$name_key])) {
                $stmt = $pdo->prepare("SELECT id FROM master_items WHERE LOWER(TRIM(name)) = LOWER(TRIM(?)) ORDER BY created_at ASC LIMIT 1");
                $stmt->execute([$item['name']]);
                $oldest = $stmt->fetch();
                
                if ($oldest) {
                    $stmt = $pdo->prepare("UPDATE master_items SET status = 'duplicate', name = CONCAT('[DUPLICATE] ', name) WHERE LOWER(TRIM(name)) = LOWER(TRIM(?)) AND id != ? AND status = 'active'");
                    $stmt->execute([$item['name'], $oldest['id']]);
                    $stats['duplicates_removed']++;
                }
                $processed_names[$name_key] = true;
            }
        }
        echo "  🔄 Processed {$stats['duplicates_removed']} duplicate sets\n";
        
        // Tag remaining items as available
        $keep_count = count($categories['keep_available']) + count($categories['recent_unused']);
        if ($keep_count > 0) {
            $all_keep_ids = array_merge(
                array_column($categories['keep_available'], 'id'),
                array_column($categories['recent_unused'], 'id')
            );
            
            $placeholders = str_repeat('?,', count($all_keep_ids) - 1) . '?';
            $stmt = $pdo->prepare("UPDATE master_items SET status = 'available' WHERE id IN ($placeholders) AND status = 'active'");
            $stmt->execute($all_keep_ids);
            $stats['kept_available'] = $keep_count;
        }
        echo "  🏷️  Tagged {$stats['kept_available']} items as available\n";
        
        $pdo->commit();
        echo "  ✅ All cleanup actions committed\n";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "  ❌ Cleanup failed: " . $e->getMessage() . "\n";
        $stats['failed'] = 1;
    }
    
    return $stats;
}

function generateFinalReport($pdo) {
    echo "\n📊 FINAL SYSTEM REPORT\n";
    echo "======================\n";
    
    try {
        // Status distribution
        $status_dist = $pdo->query("SELECT status, COUNT(*) as count FROM master_items GROUP BY status ORDER BY count DESC")->fetchAll();
        echo "📈 Master items by status:\n";
        foreach ($status_dist as $status) {
            echo "  • {$status['status']}: {$status['count']}\n";
        }
        
        // Link efficiency
        $linked = $pdo->query("SELECT COUNT(DISTINCT mi.id) FROM master_items mi JOIN voting_list_items vli ON mi.id = vli.master_item_id WHERE mi.status = 'active'")->fetchColumn();
        $unlinked = $pdo->query("SELECT COUNT(*) FROM master_items mi LEFT JOIN voting_list_items vli ON mi.id = vli.master_item_id WHERE vli.master_item_id IS NULL AND mi.status = 'active'")->fetchColumn();
        
        echo "\n🔗 Active master items:\n";
        echo "  • Linked to machines: $linked\n";
        echo "  • Not linked: $unlinked\n";
        
        if (($linked + $unlinked) > 0) {
            $efficiency = round(($linked / ($linked + $unlinked)) * 100, 1);
            echo "  • Link efficiency: $efficiency%\n";
        }
        
    } catch (Exception $e) {
        echo "❌ Report generation failed: " . $e->getMessage() . "\n";
    }
}

function addPerformanceIndexes($pdo) {
    echo "\n⚡ Adding performance indexes...\n";
    
    $indexes = [
        "CREATE INDEX IF NOT EXISTS idx_master_items_status ON master_items(status)",
        "CREATE INDEX IF NOT EXISTS idx_voting_list_items_name ON voting_list_items(item_name)",
        "CREATE INDEX IF NOT EXISTS idx_sales_item_id ON sales(item_id)",
        "CREATE INDEX IF NOT EXISTS idx_master_items_category_status ON master_items(category, status)"
    ];
    
    $added = 0;
    foreach ($indexes as $sql) {
        try {
            $pdo->exec($sql);
            $added++;
        } catch (Exception $e) {
            // Index might already exist
        }
    }
    echo "  ✅ Added/verified $added performance indexes\n";
}

// EXECUTE PHASE 2
try {
    echo "🚀 Starting Phase 2: Orphaned Master Items Cleanup\n\n";
    
    $orphaned_items = analyzeOrphanedItems($pdo);
    if (empty($orphaned_items)) {
        echo "✅ No orphaned items found - Phase 2 not needed\n";
        exit(0);
    }
    
    $categories = categorizeForCleanup($orphaned_items);
    $stats = executeCleanupActions($pdo, $categories);
    addPerformanceIndexes($pdo);
    generateFinalReport($pdo);
    
    echo "\n🎉 PHASE 2 COMPLETE\n";
    echo "===================\n";
    echo "• Items processed: " . count($orphaned_items) . "\n";
    echo "• Empty names removed: {$stats['empty_removed']}\n";
    echo "• Inactive archived: {$stats['inactive_archived']}\n";
    echo "• Duplicate sets: {$stats['duplicates_removed']}\n";
    echo "• Items available: {$stats['kept_available']}\n";
    echo "• Failed operations: {$stats['failed']}\n\n";
    
    if ($stats['failed'] == 0) {
        echo "✅ Phase 2 completed successfully!\n";
        echo "✅ Ready for Phase 3: Performance optimization\n";
    } else {
        echo "⚠️  Phase 2 completed with issues\n";
    }
    
} catch (Exception $e) {
    echo "❌ Phase 2 failed: " . $e->getMessage() . "\n";
} 