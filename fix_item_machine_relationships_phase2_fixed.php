<?php
/**
 * Priority 3 Phase 2 FIXED: Address Orphaned Master Items
 * Uses existing enum values to avoid database constraints
 */

require_once __DIR__ . '/html/core/config.php';

echo "🔧 PRIORITY 3 PHASE 2 FIXED: ORPHANED MASTER ITEMS\n";
echo "===================================================\n\n";

function analyzeOrphaned($pdo) {
    echo "🔍 Analyzing orphaned master items...\n";
    
    $stmt = $pdo->query("
        SELECT mi.id, mi.name, mi.category, mi.status, mi.created_at
        FROM master_items mi
        LEFT JOIN voting_list_items vli ON mi.id = vli.master_item_id
        WHERE vli.master_item_id IS NULL
        ORDER BY mi.created_at DESC
    ");
    
    $orphaned = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "  📊 Found " . count($orphaned) . " orphaned master items\n";
    
    return $orphaned;
}

function smartCleanup($pdo, $orphaned) {
    echo "\n🧹 Smart cleanup using existing schema...\n";
    
    $stats = ['duplicates_marked' => 0, 'kept_active' => 0];
    
    try {
        $pdo->beginTransaction();
        
        // Find and mark obvious duplicates by prefixing name
        $name_counts = [];
        foreach ($orphaned as $item) {
            $clean_name = strtolower(trim($item['name']));
            if (!isset($name_counts[$clean_name])) {
                $name_counts[$clean_name] = [];
            }
            $name_counts[$clean_name][] = $item;
        }
        
        foreach ($name_counts as $clean_name => $items) {
            if (count($items) > 1) {
                // Keep the oldest, mark others as duplicates by prefixing name
                usort($items, function($a, $b) {
                    return strtotime($a['created_at']) - strtotime($b['created_at']);
                });
                
                for ($i = 1; $i < count($items); $i++) {
                    $stmt = $pdo->prepare("
                        UPDATE master_items 
                        SET name = CONCAT('[DUP] ', name) 
                        WHERE id = ? AND name NOT LIKE '[DUP]%'
                    ");
                    if ($stmt->execute([$items[$i]['id']])) {
                        $stats['duplicates_marked']++;
                    }
                }
            }
        }
        
        $stats['kept_active'] = count($orphaned) - $stats['duplicates_marked'];
        
        $pdo->commit();
        echo "  ✅ Cleanup completed successfully\n";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "  ❌ Cleanup failed: " . $e->getMessage() . "\n";
    }
    
    return $stats;
}

function addCriticalIndexes($pdo) {
    echo "\n⚡ Adding critical indexes...\n";
    
    $indexes = [
        "CREATE INDEX IF NOT EXISTS idx_master_items_status ON master_items(status)",
        "CREATE INDEX IF NOT EXISTS idx_voting_list_items_master_id ON voting_list_items(master_item_id)",
        "CREATE INDEX IF NOT EXISTS idx_master_items_name ON master_items(name(50))"
    ];
    
    $added = 0;
    foreach ($indexes as $sql) {
        try {
            $pdo->exec($sql);
            $added++;
        } catch (Exception $e) {
            // Index might exist, continue
        }
    }
    echo "  ✅ Added/verified $added indexes\n";
}

// Execute Fixed Phase 2
try {
    echo "🚀 Phase 2 Fixed: Working with existing constraints\n\n";
    
    $orphaned = analyzeOrphaned($pdo);
    $stats = smartCleanup($pdo, $orphaned);
    addCriticalIndexes($pdo);
    
    echo "\n✅ PHASE 2 FIXED COMPLETE\n";
    echo "==========================\n";
    echo "• Total orphaned items: " . count($orphaned) . "\n";
    echo "• Duplicates marked: {$stats['duplicates_marked']}\n";
    echo "• Items kept active: {$stats['kept_active']}\n\n";
    echo "✅ Ready for Phase 3: Performance optimization\n";
    
} catch (Exception $e) {
    echo "❌ Phase 2 fixed failed: " . $e->getMessage() . "\n";
} 