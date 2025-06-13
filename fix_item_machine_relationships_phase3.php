<?php
/**
 * Priority 3 Phase 3: Performance Optimization & System Tuning
 * Optimizes queries, adds indexes, and improves system performance
 */

require_once __DIR__ . '/html/core/config.php';

echo "🔧 PRIORITY 3 PHASE 3: PERFORMANCE OPTIMIZATION\n";
echo "================================================\n\n";

function analyzeCurrentPerformance($pdo) {
    echo "📊 Analyzing current system performance...\n";
    
    try {
        // Check table sizes
        $tables = ['master_items', 'voting_list_items', 'voting_lists', 'sales', 'qr_codes'];
        echo "  📋 Table sizes:\n";
        
        foreach ($tables as $table) {
            try {
                $count = $pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
                echo "    • $table: $count records\n";
            } catch (Exception $e) {
                echo "    • $table: unavailable\n";
            }
        }
        
        // Check slow queries potential
        echo "\n  🔍 Performance analysis:\n";
        
        // Test master_items join performance
        $start = microtime(true);
        $linked_items = $pdo->query("
            SELECT COUNT(DISTINCT mi.id) 
            FROM master_items mi 
            JOIN voting_list_items vli ON mi.id = vli.master_item_id 
            WHERE mi.status = 'active'
        ")->fetchColumn();
        $join_time = round((microtime(true) - $start) * 1000, 2);
        
        echo "    • Master-items join query: {$join_time}ms\n";
        echo "    • Active linked items: $linked_items\n";
        
        // Test QR lookup performance
        $start = microtime(true);
        $qr_count = $pdo->query("SELECT COUNT(*) FROM qr_codes WHERE type = 'machine'")->fetchColumn();
        $qr_time = round((microtime(true) - $start) * 1000, 2);
        
        echo "    • QR code lookup: {$qr_time}ms\n";
        echo "    • Machine QR codes: $qr_count\n";
        
        return [
            'join_time' => $join_time,
            'qr_time' => $qr_time,
            'linked_items' => $linked_items
        ];
        
    } catch (Exception $e) {
        echo "  ❌ Performance analysis failed: " . $e->getMessage() . "\n";
        return [];
    }
}

function optimizeDatabase($pdo) {
    echo "\n⚡ Optimizing database performance...\n";
    
    $stats = ['indexes_added' => 0, 'tables_optimized' => 0, 'failed' => 0];
    
    try {
        // Add critical performance indexes
        $indexes = [
            // Core business logic indexes
            "CREATE INDEX IF NOT EXISTS idx_voting_list_items_master_id ON voting_list_items(master_item_id)",
            "CREATE INDEX IF NOT EXISTS idx_voting_list_items_list_id ON voting_list_items(voting_list_id)",
            "CREATE INDEX IF NOT EXISTS idx_voting_lists_business_id ON voting_lists(business_id)",
            
            // QR system indexes
            "CREATE INDEX IF NOT EXISTS idx_qr_codes_type_target ON qr_codes(type, target_id)",
            "CREATE INDEX IF NOT EXISTS idx_qr_codes_business_id ON qr_codes(business_id)",
            
            // Sales system indexes
            "CREATE INDEX IF NOT EXISTS idx_sales_item_id_date ON sales(item_id, purchase_date)",
            "CREATE INDEX IF NOT EXISTS idx_sales_business_date ON sales(business_id, purchase_date)",
            
            // Master items performance
            "CREATE INDEX IF NOT EXISTS idx_master_items_status_category ON master_items(status, category)",
            "CREATE INDEX IF NOT EXISTS idx_master_items_name_search ON master_items(name(100))",
            
            // Voting performance
            "CREATE INDEX IF NOT EXISTS idx_votes_item_date ON votes(item_id, vote_date)",
            "CREATE INDEX IF NOT EXISTS idx_votes_business_id ON votes(business_id)"
        ];
        
        echo "  🔧 Adding performance indexes...\n";
        foreach ($indexes as $sql) {
            try {
                $pdo->exec($sql);
                $stats['indexes_added']++;
                echo "    ✅ Index added\n";
            } catch (Exception $e) {
                if (strpos($e->getMessage(), 'Duplicate key name') === false) {
                    echo "    ⚠️  Index skipped: " . substr($e->getMessage(), 0, 50) . "...\n";
                }
            }
        }
        
        // Optimize table storage
        echo "\n  🗜️  Optimizing table storage...\n";
        $tables_to_optimize = ['master_items', 'voting_list_items', 'qr_codes', 'sales'];
        
        foreach ($tables_to_optimize as $table) {
            try {
                $pdo->exec("OPTIMIZE TABLE $table");
                $stats['tables_optimized']++;
                echo "    ✅ Optimized $table\n";
            } catch (Exception $e) {
                echo "    ⚠️  Could not optimize $table\n";
            }
        }
        
    } catch (Exception $e) {
        echo "  ❌ Database optimization failed: " . $e->getMessage() . "\n";
        $stats['failed'] = 1;
    }
    
    return $stats;
}

function createPerformanceViews($pdo) {
    echo "\n📋 Creating performance views...\n";
    
    $stats = ['views_created' => 0, 'failed' => 0];
    
    try {
        // Active machine items view
        $pdo->exec("DROP VIEW IF EXISTS v_active_machine_items");
        $pdo->exec("
            CREATE VIEW v_active_machine_items AS
            SELECT 
                vli.id,
                vli.item_name,
                vli.item_category,
                vli.retail_price,
                vli.inventory,
                vl.name as machine_name,
                vl.business_id,
                mi.id as master_item_id,
                mi.category as master_category
            FROM voting_list_items vli
            JOIN voting_lists vl ON vli.voting_list_id = vl.id
            LEFT JOIN master_items mi ON vli.master_item_id = mi.id
            WHERE vl.status = 'active'
        ");
        echo "  ✅ Created v_active_machine_items view\n";
        $stats['views_created']++;
        
        // Business performance view  
        $pdo->exec("DROP VIEW IF EXISTS v_business_performance");
        $pdo->exec("
            CREATE VIEW v_business_performance AS
            SELECT 
                vl.business_id,
                COUNT(DISTINCT vl.id) as machine_count,
                COUNT(DISTINCT vli.id) as total_items,
                COUNT(DISTINCT vli.master_item_id) as linked_master_items,
                COALESCE(SUM(s.total_amount), 0) as total_revenue,
                COUNT(DISTINCT s.id) as total_sales
            FROM voting_lists vl
            LEFT JOIN voting_list_items vli ON vl.id = vli.voting_list_id
            LEFT JOIN sales s ON vli.id = s.item_id
            GROUP BY vl.business_id
        ");
        echo "  ✅ Created v_business_performance view\n";
        $stats['views_created']++;
        
        // QR code status view
        $pdo->exec("DROP VIEW IF EXISTS v_qr_status");
        $pdo->exec("
            CREATE VIEW v_qr_status AS
            SELECT 
                qr.business_id,
                qr.type,
                COUNT(*) as qr_count,
                SUM(CASE WHEN qr.qr_url IS NOT NULL THEN 1 ELSE 0 END) as valid_qr_count
            FROM qr_codes qr
            GROUP BY qr.business_id, qr.type
        ");
        echo "  ✅ Created v_qr_status view\n";
        $stats['views_created']++;
        
    } catch (Exception $e) {
        echo "  ❌ View creation failed: " . $e->getMessage() . "\n";
        $stats['failed'] = 1;
    }
    
    return $stats;
}

function measurePerformanceGains($pdo, $baseline) {
    echo "\n📈 Measuring performance improvements...\n";
    
    if (empty($baseline)) {
        echo "  ⚠️  No baseline data available\n";
        return;
    }
    
    try {
        // Re-test join performance
        $start = microtime(true);
        $pdo->query("
            SELECT COUNT(DISTINCT mi.id) 
            FROM master_items mi 
            JOIN voting_list_items vli ON mi.id = vli.master_item_id 
            WHERE mi.status = 'active'
        ")->fetchColumn();
        $new_join_time = round((microtime(true) - $start) * 1000, 2);
        
        // Re-test QR performance
        $start = microtime(true);
        $pdo->query("SELECT COUNT(*) FROM qr_codes WHERE type = 'machine'")->fetchColumn();
        $new_qr_time = round((microtime(true) - $start) * 1000, 2);
        
        // Calculate improvements
        $join_improvement = $baseline['join_time'] > 0 ? 
            round((($baseline['join_time'] - $new_join_time) / $baseline['join_time']) * 100, 1) : 0;
        $qr_improvement = $baseline['qr_time'] > 0 ? 
            round((($baseline['qr_time'] - $new_qr_time) / $baseline['qr_time']) * 100, 1) : 0;
        
        echo "  📊 Performance comparison:\n";
        echo "    • Join query: {$baseline['join_time']}ms → {$new_join_time}ms ($join_improvement% improvement)\n";
        echo "    • QR lookup: {$baseline['qr_time']}ms → {$new_qr_time}ms ($qr_improvement% improvement)\n";
        
        // Test view performance
        $start = microtime(true);
        $view_count = $pdo->query("SELECT COUNT(*) FROM v_active_machine_items")->fetchColumn();
        $view_time = round((microtime(true) - $start) * 1000, 2);
        
        echo "    • New view query: {$view_time}ms ($view_count records)\n";
        
    } catch (Exception $e) {
        echo "  ❌ Performance measurement failed: " . $e->getMessage() . "\n";
    }
}

function generateOptimizationReport($pdo) {
    echo "\n📋 OPTIMIZATION SUMMARY REPORT\n";
    echo "===============================\n";
    
    try {
        // Current system state
        $active_items = $pdo->query("SELECT COUNT(*) FROM master_items WHERE status = 'active'")->fetchColumn();
        $linked_items = $pdo->query("
            SELECT COUNT(DISTINCT mi.id) 
            FROM master_items mi 
            JOIN voting_list_items vli ON mi.id = vli.master_item_id 
            WHERE mi.status = 'active'
        ")->fetchColumn();
        $machines = $pdo->query("SELECT COUNT(*) FROM voting_lists")->fetchColumn();
        $qr_codes = $pdo->query("SELECT COUNT(*) FROM qr_codes WHERE qr_url IS NOT NULL")->fetchColumn();
        
        echo "📊 System metrics:\n";
        echo "  • Active master items: $active_items\n";
        echo "  • Linked to machines: $linked_items\n";
        echo "  • Total machines: $machines\n";
        echo "  • Valid QR codes: $qr_codes\n";
        
        // Link efficiency
        if ($active_items > 0) {
            $efficiency = round(($linked_items / $active_items) * 100, 1);
            echo "  • Link efficiency: $efficiency%\n";
        }
        
        // Check view availability
        echo "\n📋 Performance views:\n";
        $views = ['v_active_machine_items', 'v_business_performance', 'v_qr_status'];
        foreach ($views as $view) {
            try {
                $count = $pdo->query("SELECT COUNT(*) FROM $view")->fetchColumn();
                echo "  ✅ $view: $count records\n";
            } catch (Exception $e) {
                echo "  ❌ $view: unavailable\n";
            }
        }
        
    } catch (Exception $e) {
        echo "❌ Report generation failed: " . $e->getMessage() . "\n";
    }
}

// Execute Phase 3
try {
    echo "🚀 Starting Phase 3: Performance Optimization\n\n";
    
    // Step 1: Baseline performance analysis
    $baseline = analyzeCurrentPerformance($pdo);
    
    // Step 2: Database optimization
    $db_stats = optimizeDatabase($pdo);
    
    // Step 3: Create performance views
    $view_stats = createPerformanceViews($pdo);
    
    // Step 4: Measure improvements
    measurePerformanceGains($pdo, $baseline);
    
    // Step 5: Generate final report
    generateOptimizationReport($pdo);
    
    echo "\n🎉 PHASE 3 COMPLETE\n";
    echo "===================\n";
    echo "• Indexes added: {$db_stats['indexes_added']}\n";
    echo "• Tables optimized: {$db_stats['tables_optimized']}\n";
    echo "• Views created: {$view_stats['views_created']}\n";
    echo "• Database errors: {$db_stats['failed']}\n";
    echo "• View errors: {$view_stats['failed']}\n\n";
    
    if ($db_stats['failed'] == 0 && $view_stats['failed'] == 0) {
        echo "✅ Phase 3 completed successfully!\n";
        echo "✅ Ready for Phase 4: Final verification\n";
    } else {
        echo "⚠️  Phase 3 completed with some issues\n";
    }
    
} catch (Exception $e) {
    echo "❌ Phase 3 failed: " . $e->getMessage() . "\n";
} 