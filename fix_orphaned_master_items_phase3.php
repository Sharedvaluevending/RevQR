<?php
/**
 * Phase 3 Step 1: Orphaned Master Items Cleanup
 * 
 * Addresses 781 master items not linked to any machines
 * Uses smart categorization and preservation strategies
 * 
 * Part of Phase 3 Critical System Fixes
 */

require_once 'html/core/config.php';

class OrphanedMasterItemsFix {
    private $pdo;
    private $log = [];
    private $stats = [
        'analyzed' => 0,
        'preserved' => 0,
        'deactivated' => 0,
        'auto_linked' => 0,
        'failed' => 0
    ];
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Execute the complete orphaned items cleanup
     */
    public function execute() {
        try {
            $this->log("🚀 PHASE 3 STEP 1: ORPHANED MASTER ITEMS CLEANUP");
            $this->log("===============================================");
            
            // Step 1: Create safety backups
            $this->createBackups();
            
            // Step 2: Analyze orphaned items
            $orphaned_items = $this->analyzeOrphanedItems();
            
            // Step 3: Categorize items by preservation value
            $categorized = $this->categorizeItems($orphaned_items);
            
            // Step 4: Execute cleanup strategy
            $this->executeCleanupStrategy($categorized);
            
            // Step 5: Fix vote references
            $this->fixVoteReferences();
            
            // Step 6: Add performance indexes
            $this->addPerformanceIndexes();
            
            // Step 7: Generate final report
            $this->generateReport();
            
            $this->log("\n✅ PHASE 3 STEP 1 COMPLETED SUCCESSFULLY!");
            
        } catch (Exception $e) {
            $this->log("❌ CRITICAL ERROR: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Create safety backups before any modifications
     */
    private function createBackups() {
        $this->log("\n💾 Creating safety backups...");
        
        try {
            // Backup master_items
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS master_items_backup_phase3 AS SELECT * FROM master_items");
            $master_count = $this->pdo->query("SELECT COUNT(*) FROM master_items_backup_phase3")->fetchColumn();
            $this->log("✅ master_items backup: {$master_count} records");
            
            // Backup voting_list_items  
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS voting_list_items_backup_phase3 AS SELECT * FROM voting_list_items WHERE 1=0");
            $this->pdo->exec("INSERT INTO voting_list_items_backup_phase3 SELECT * FROM voting_list_items");
            $vli_count = $this->pdo->query("SELECT COUNT(*) FROM voting_list_items_backup_phase3")->fetchColumn();
            $this->log("✅ voting_list_items backup: {$vli_count} records");
            
            // Backup votes
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS votes_backup_phase3 AS SELECT * FROM votes WHERE 1=0");
            $this->pdo->exec("INSERT INTO votes_backup_phase3 SELECT * FROM votes");
            $votes_count = $this->pdo->query("SELECT COUNT(*) FROM votes_backup_phase3")->fetchColumn();
            $this->log("✅ votes backup: {$votes_count} records");
            
        } catch (Exception $e) {
            throw new Exception("Backup creation failed: " . $e->getMessage());
        }
    }
    
    /**
     * Analyze all orphaned master items
     */
    private function analyzeOrphanedItems() {
        $this->log("\n🔍 Analyzing orphaned master items...");
        
        $stmt = $this->pdo->query("
            SELECT 
                mi.id, mi.name, mi.category, mi.type, mi.brand,
                mi.suggested_price, mi.suggested_cost, mi.status, 
                mi.created_at, mi.popularity,
                CASE 
                    WHEN mi.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 'very_recent'
                    WHEN mi.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 'recent'
                    WHEN mi.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY) THEN 'moderate'
                    ELSE 'old'
                END as age_category,
                CASE 
                    WHEN mi.suggested_price > 5.00 THEN 'high_value'
                    WHEN mi.suggested_price > 2.00 THEN 'medium_value'
                    ELSE 'low_value'
                END as value_category
            FROM master_items mi
            LEFT JOIN voting_list_items vli ON mi.id = vli.master_item_id
            WHERE vli.master_item_id IS NULL
            ORDER BY mi.created_at DESC, mi.suggested_price DESC
        ");
        
        $orphaned_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $this->stats['analyzed'] = count($orphaned_items);
        
        $this->log("📊 Found {$this->stats['analyzed']} orphaned master items");
        
        // Quick statistics
        $by_age = []; $by_status = []; $by_value = [];
        foreach ($orphaned_items as $item) {
            $by_age[$item['age_category']] = ($by_age[$item['age_category']] ?? 0) + 1;
            $by_status[$item['status']] = ($by_status[$item['status']] ?? 0) + 1;
            $by_value[$item['value_category']] = ($by_value[$item['value_category']] ?? 0) + 1;
        }
        
        $this->log("  📈 By age: " . json_encode($by_age));
        $this->log("  📈 By status: " . json_encode($by_status));  
        $this->log("  📈 By value: " . json_encode($by_value));
        
        return $orphaned_items;
    }
    
    /**
     * Categorize items by preservation strategy
     */
    private function categorizeItems($orphaned_items) {
        $this->log("\n📋 Categorizing items by preservation strategy...");
        
        $categories = [
            'preserve_high_value' => [],
            'auto_link_candidates' => [],
            'deactivate_old' => [],
            'deactivate_duplicates' => []
        ];
        
        foreach ($orphaned_items as $item) {
            $preserve_score = $this->calculatePreservationScore($item);
            
            if ($preserve_score >= 8) {
                // High-value items to preserve
                $categories['preserve_high_value'][] = $item;
            } elseif ($preserve_score >= 5 && $this->canAutoLink($item)) {
                // Items that can be auto-linked to machines
                $categories['auto_link_candidates'][] = $item;
            } elseif ($item['age_category'] === 'old' && $preserve_score < 3) {
                // Old, low-value items to deactivate
                $categories['deactivate_old'][] = $item;
            } else {
                // Potential duplicates or unclear status
                $categories['deactivate_duplicates'][] = $item;
            }
        }
        
        foreach ($categories as $category => $items) {
            $this->log("  📊 {$category}: " . count($items) . " items");
        }
        
        return $categories;
    }
    
    /**
     * Calculate preservation score (0-10) for an item
     */
    private function calculatePreservationScore($item) {
        $score = 0;
        
        // Age factor (newer = higher score)
        switch ($item['age_category']) {
            case 'very_recent': $score += 4; break;
            case 'recent': $score += 3; break;
            case 'moderate': $score += 2; break;
            case 'old': $score += 0; break;
        }
        
        // Value factor
        switch ($item['value_category']) {
            case 'high_value': $score += 3; break;
            case 'medium_value': $score += 2; break;
            case 'low_value': $score += 1; break;
        }
        
        // Brand factor (branded items more valuable)
        if (!empty($item['brand']) && $item['brand'] !== 'Generic') {
            $score += 2;
        }
        
        // Status factor
        if ($item['status'] === 'active') {
            $score += 1;
        }
        
        // Popularity factor
        if ($item['popularity'] === 'high') {
            $score += 1;
        }
        
        return $score;
    }
    
    /**
     * Check if item can be auto-linked to existing machines
     */
    private function canAutoLink($item) {
        try {
            // Look for similar items in voting_list_items
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) 
                FROM voting_list_items vli
                WHERE LOWER(TRIM(vli.item_name)) = LOWER(TRIM(?))
                   OR SOUNDEX(vli.item_name) = SOUNDEX(?)
                LIMIT 1
            ");
            $stmt->execute([$item['name'], $item['name']]);
            
            return $stmt->fetchColumn() > 0;
            
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Execute the cleanup strategy for each category
     */
    private function executeCleanupStrategy($categorized) {
        $this->log("\n🔧 Executing cleanup strategies...");
        
        try {
            $this->pdo->beginTransaction();
            
            // Strategy 1: Preserve high-value items (mark as archived but keep active)
            $this->preserveHighValueItems($categorized['preserve_high_value']);
            
            // Strategy 2: Auto-link candidates to similar machine items
            $this->autoLinkSimilarItems($categorized['auto_link_candidates']);
            
            // Strategy 3: Deactivate old, low-value items
            $this->deactivateOldItems($categorized['deactivate_old']);
            
            // Strategy 4: Deactivate unclear/duplicate items
            $this->deactivateDuplicateItems($categorized['deactivate_duplicates']);
            
            $this->pdo->commit();
            $this->log("✅ All cleanup strategies executed successfully");
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw new Exception("Cleanup strategy failed: " . $e->getMessage());
        }
    }
    
    /**
     * Preserve high-value items by adding to a special "catalog" machine
     */
    private function preserveHighValueItems($items) {
        if (empty($items)) {
            return;
        }
        
        $this->log("\n📚 Preserving " . count($items) . " high-value items...");
        
        // Create or find "Master Catalog" voting list
        $stmt = $this->pdo->prepare("
            SELECT id FROM voting_lists 
            WHERE name = 'Master Catalog' AND business_id = 1
        ");
        $stmt->execute();
        $catalog_id = $stmt->fetchColumn();
        
        if (!$catalog_id) {
            // Create master catalog
            $stmt = $this->pdo->prepare("
                INSERT INTO voting_lists (business_id, name, description) 
                VALUES (1, 'Master Catalog', 'Preserved high-value items')
            ");
            $stmt->execute();
            $catalog_id = $this->pdo->lastInsertId();
            $this->log("  📋 Created Master Catalog (ID: {$catalog_id})");
        }
        
        // Add items to catalog
        $stmt = $this->pdo->prepare("
            INSERT INTO voting_list_items (
                voting_list_id, master_item_id, item_name, item_category,
                retail_price, cost_price, inventory, status
            ) VALUES (?, ?, ?, ?, ?, ?, 0, 'inactive')
        ");
        
        foreach ($items as $item) {
            $stmt->execute([
                $catalog_id,
                $item['id'],
                $item['name'],
                $item['category'],
                $item['suggested_price'],
                $item['suggested_cost']
            ]);
            $this->stats['preserved']++;
        }
        
        $this->log("  ✅ Preserved {$this->stats['preserved']} high-value items");
    }
    
    /**
     * Auto-link items to existing machines with similar items
     */
    private function autoLinkSimilarItems($items) {
        if (empty($items)) {
            return;
        }
        
        $this->log("\n🔗 Auto-linking " . count($items) . " items to similar machines...");
        
        foreach ($items as $item) {
            try {
                // Find the best matching machine item
                $stmt = $this->pdo->prepare("
                    SELECT vli.voting_list_id, vli.id
                    FROM voting_list_items vli
                    WHERE (LOWER(TRIM(vli.item_name)) = LOWER(TRIM(?))
                       OR SOUNDEX(vli.item_name) = SOUNDEX(?))
                      AND vli.master_item_id IS NOT NULL
                    LIMIT 1
                ");
                $stmt->execute([$item['name'], $item['name']]);
                $match = $stmt->fetch();
                
                if ($match) {
                    // Create new voting_list_item linked to this master_item
                    $stmt = $this->pdo->prepare("
                        INSERT INTO voting_list_items (
                            voting_list_id, master_item_id, item_name, item_category,
                            retail_price, cost_price, inventory, status
                        ) VALUES (?, ?, ?, ?, ?, ?, 0, 'active')
                    ");
                    $stmt->execute([
                        $match['voting_list_id'],
                        $item['id'],
                        $item['name'],
                        $item['category'],
                        $item['suggested_price'],
                        $item['suggested_cost']
                    ]);
                    
                    $this->stats['auto_linked']++;
                    $this->log("  🔗 Linked '{$item['name']}' to machine {$match['voting_list_id']}");
                }
                
            } catch (Exception $e) {
                $this->log("  ⚠️ Failed to auto-link '{$item['name']}': " . $e->getMessage());
            }
        }
        
        $this->log("  ✅ Auto-linked {$this->stats['auto_linked']} items");
    }
    
    /**
     * Deactivate old, low-value items
     */
    private function deactivateOldItems($items) {
        if (empty($items)) {
            return;
        }
        
        $this->log("\n🗂️ Deactivating " . count($items) . " old items...");
        
        $item_ids = array_column($items, 'id');
        $placeholders = str_repeat('?,', count($item_ids) - 1) . '?';
        
        $stmt = $this->pdo->prepare("
            UPDATE master_items 
            SET status = 'inactive', 
                category = CONCAT(category, ' [Archived]')
            WHERE id IN ($placeholders)
        ");
        $stmt->execute($item_ids);
        
        $this->stats['deactivated'] += $stmt->rowCount();
        $this->log("  ✅ Deactivated {$this->stats['deactivated']} old items");
    }
    
    /**
     * Deactivate duplicate/unclear items
     */
    private function deactivateDuplicateItems($items) {
        if (empty($items)) {
            return;
        }
        
        $this->log("\n📦 Deactivating " . count($items) . " duplicate/unclear items...");
        
        $additional_deactivated = 0;
        foreach ($items as $item) {
            try {
                $stmt = $this->pdo->prepare("
                    UPDATE master_items 
                    SET status = 'inactive'
                    WHERE id = ?
                ");
                $stmt->execute([$item['id']]);
                $additional_deactivated++;
                
            } catch (Exception $e) {
                $this->stats['failed']++;
            }
        }
        
        $this->stats['deactivated'] += $additional_deactivated;
        $this->log("  ✅ Deactivated {$additional_deactivated} duplicate items");
    }
    
    /**
     * Fix orphaned vote references
     */
    private function fixVoteReferences() {
        $this->log("\n🗳️ Fixing orphaned vote references...");
        
        try {
            // Find votes with invalid item_id references
            $stmt = $this->pdo->query("
                SELECT v.id, v.item_id, v.vote_type, v.voter_ip
                FROM votes v
                LEFT JOIN voting_list_items vli ON v.item_id = vli.id
                WHERE vli.id IS NULL
            ");
            $orphaned_votes = $stmt->fetchAll();
            
            if (empty($orphaned_votes)) {
                $this->log("  ✅ No orphaned vote references found");
                return;
            }
            
            $this->log("  📊 Found " . count($orphaned_votes) . " orphaned vote references");
            
            // Remove orphaned votes (they're invalid)
            $vote_ids = array_column($orphaned_votes, 'id');
            $placeholders = str_repeat('?,', count($vote_ids) - 1) . '?';
            
            $stmt = $this->pdo->prepare("DELETE FROM votes WHERE id IN ($placeholders)");
            $stmt->execute($vote_ids);
            
            $this->log("  ✅ Removed " . count($orphaned_votes) . " orphaned vote references");
            
        } catch (Exception $e) {
            $this->log("  ⚠️ Vote reference fix failed: " . $e->getMessage());
        }
    }
    
    /**
     * Add missing performance indexes
     */
    private function addPerformanceIndexes() {
        $this->log("\n⚡ Adding performance indexes...");
        
        $indexes_to_add = [
            "ALTER TABLE voting_list_items ADD INDEX idx_item_name (item_name)" => "voting_list_items.item_name",
            "ALTER TABLE sales ADD INDEX idx_sales_item_id (item_id)" => "sales.item_id",
            "ALTER TABLE master_items ADD INDEX idx_master_status (status)" => "master_items.status"
        ];
        
        foreach ($indexes_to_add as $sql => $description) {
            try {
                $this->pdo->exec($sql);
                $this->log("  ✅ Added index: {$description}");
            } catch (Exception $e) {
                if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
                    $this->log("  ℹ️ Index already exists: {$description}");
                } else {
                    $this->log("  ⚠️ Failed to add index {$description}: " . $e->getMessage());
                }
            }
        }
    }
    
    /**
     * Generate comprehensive cleanup report
     */
    private function generateReport() {
        $this->log("\n📊 PHASE 3 STEP 1 COMPLETION REPORT");
        $this->log("==================================");
        
        // Final statistics
        $final_orphaned = $this->pdo->query("
            SELECT COUNT(*) 
            FROM master_items mi
            LEFT JOIN voting_list_items vli ON mi.id = vli.master_item_id
            WHERE vli.master_item_id IS NULL AND mi.status = 'active'
        ")->fetchColumn();
        
        $total_active = $this->pdo->query("SELECT COUNT(*) FROM master_items WHERE status = 'active'")->fetchColumn();
        $total_inactive = $this->pdo->query("SELECT COUNT(*) FROM master_items WHERE status = 'inactive'")->fetchColumn();
        
        $this->log("📈 Final Statistics:");
        $this->log("  • Items analyzed: {$this->stats['analyzed']}");
        $this->log("  • Items preserved: {$this->stats['preserved']}");
        $this->log("  • Items auto-linked: {$this->stats['auto_linked']}");
        $this->log("  • Items deactivated: {$this->stats['deactivated']}");
        $this->log("  • Processing failures: {$this->stats['failed']}");
        
        $this->log("\n📊 Current System State:");
        $this->log("  • Active master items: {$total_active}");
        $this->log("  • Inactive master items: {$total_inactive}");
        $this->log("  • Remaining orphaned (active): {$final_orphaned}");
        
        $improvement = $this->stats['analyzed'] - $final_orphaned;
        $improvement_rate = round(($improvement / max($this->stats['analyzed'], 1)) * 100, 1);
        
        $this->log("\n🎯 Improvement Metrics:");
        $this->log("  • Orphaned items reduced by: {$improvement} ({$improvement_rate}%)");
        $this->log("  • Target achieved: " . ($final_orphaned < 50 ? "✅ YES" : "⚠️ NO"));
        
        // Performance impact
        $performance_start = microtime(true);
        $this->pdo->query("SELECT COUNT(*) FROM master_items WHERE status = 'active'");
        $performance_end = microtime(true);
        $query_time = round(($performance_end - $performance_start) * 1000, 2);
        
        $this->log("  • Query performance: {$query_time}ms");
        
        $this->log("\n🎉 Phase 3 Step 1 Results:");
        if ($final_orphaned < 50) {
            $this->log("  ✅ EXCELLENT: Orphaned items reduced to manageable level");
        } elseif ($final_orphaned < 200) {
            $this->log("  ⚠️ GOOD: Significant improvement, minor cleanup remaining");
        } else {
            $this->log("  🔧 NEEDS ATTENTION: Additional cleanup required");
        }
    }
    
    /**
     * Add message to log
     */
    private function log($message) {
        $timestamp = date('Y-m-d H:i:s');
        $formatted = "[{$timestamp}] {$message}";
        $this->log[] = $formatted;
        echo $formatted . "\n";
    }
}

// Execute Phase 3 Step 1 if run directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    try {
        echo "Starting Phase 3 Step 1: Orphaned Master Items Cleanup...\n\n";
        
        $phase3_step1 = new OrphanedMasterItemsFix($pdo);
        $phase3_step1->execute();
        
        echo "\n✅ PHASE 3 STEP 1 COMPLETED SUCCESSFULLY!\n";
        echo "Master item relationships have been optimized and cleaned up.\n";
        
    } catch (Exception $e) {
        echo "\n❌ PHASE 3 STEP 1 FAILED: " . $e->getMessage() . "\n";
        exit(1);
    }
}
?> 