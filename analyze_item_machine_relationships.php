<?php
/**
 * Priority 3: Item-Machine Relationship Analysis
 * Comprehensive analysis of the item-machine relationship structure
 */

require_once __DIR__ . '/html/core/config.php';

echo "🔍 PRIORITY 3: ITEM-MACHINE RELATIONSHIP ANALYSIS\n";
echo "=================================================\n\n";

function analyzeTableStructure($pdo) {
    echo "📊 TABLE STRUCTURE ANALYSIS\n";
    echo "---------------------------\n";
    
    $tables_to_check = [
        'master_items' => 'Master Items Catalog',
        'voting_lists' => 'Machines (Voting Lists)', 
        'voting_list_items' => 'Machine Items',
        'items' => 'Legacy Items Table',
        'machines' => 'Legacy Machines Table',
        'item_mapping' => 'Item Mapping Table',
        'warehouse_inventory' => 'Warehouse Inventory',
        'sales' => 'Sales Records',
        'votes' => 'Voting Records'
    ];
    
    $existing_tables = [];
    $missing_tables = [];
    
    foreach ($tables_to_check as $table => $description) {
        try {
            $result = $pdo->query("SHOW TABLES LIKE '$table'")->fetch();
            if ($result) {
                $existing_tables[$table] = $description;
                echo "  ✅ $description: EXISTS\n";
            } else {
                $missing_tables[$table] = $description;
                echo "  ❌ $description: MISSING\n";
            }
        } catch (Exception $e) {
            echo "  ❌ $description: ERROR - " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n";
    return ['existing' => $existing_tables, 'missing' => $missing_tables];
}

function analyzeDataConsistency($pdo) {
    echo "🔗 DATA CONSISTENCY ANALYSIS\n";
    echo "----------------------------\n";
    
    $issues = [];
    
    try {
        // 1. Check master_items without corresponding voting_list_items
        echo "Checking master items integration:\n";
        $orphaned_master = $pdo->query("
            SELECT COUNT(*) FROM master_items mi
            LEFT JOIN voting_list_items vli ON mi.id = vli.master_item_id
            WHERE vli.master_item_id IS NULL
        ")->fetchColumn();
        
        if ($orphaned_master > 0) {
            echo "  ⚠️  Master items not linked to machines: $orphaned_master\n";
            $issues[] = "Orphaned master items need machine assignment";
        } else {
            echo "  ✅ All master items are linked to machines\n";
        }
        
        // 2. Check voting_list_items without master_item_id
        $unlinked_items = $pdo->query("
            SELECT COUNT(*) FROM voting_list_items 
            WHERE master_item_id IS NULL OR master_item_id = 0
        ")->fetchColumn();
        
        if ($unlinked_items > 0) {
            echo "  ⚠️  Machine items without master item link: $unlinked_items\n";
            $issues[] = "Machine items need master item mapping";
        } else {
            echo "  ✅ All machine items linked to master items\n";
        }
        
        // 3. Check voting_lists (machines) without items
        $empty_machines = $pdo->query("
            SELECT COUNT(*) FROM voting_lists vl
            LEFT JOIN voting_list_items vli ON vl.id = vli.voting_list_id
            WHERE vli.voting_list_id IS NULL
        ")->fetchColumn();
        
        if ($empty_machines > 0) {
            echo "  ⚠️  Machines with no items: $empty_machines\n";
            $issues[] = "Empty machines should be populated or removed";
        } else {
            echo "  ✅ All machines have items\n";
        }
        
        // 4. Check sales data integrity
        echo "\nChecking sales data integrity:\n";
        $sales_without_items = $pdo->query("
            SELECT COUNT(*) FROM sales s
            LEFT JOIN voting_list_items vli ON s.item_id = vli.id
            WHERE vli.id IS NULL
        ")->fetchColumn();
        
        if ($sales_without_items > 0) {
            echo "  ⚠️  Sales records with invalid item references: $sales_without_items\n";
            $issues[] = "Sales data has orphaned item references";
        } else {
            echo "  ✅ All sales records have valid item references\n";
        }
        
        // 5. Check votes data integrity
        $votes_without_items = $pdo->query("
            SELECT COUNT(*) FROM votes v
            LEFT JOIN voting_list_items vli ON v.item_id = vli.id
            WHERE vli.id IS NULL
        ")->fetchColumn();
        
        if ($votes_without_items > 0) {
            echo "  ⚠️  Vote records with invalid item references: $votes_without_items\n";
            $issues[] = "Vote data has orphaned item references";
        } else {
            echo "  ✅ All vote records have valid item references\n";
        }
        
        // 6. Check business isolation
        echo "\nChecking business isolation:\n";
        $isolation_issues = $pdo->query("
            SELECT COUNT(*) FROM voting_list_items vli
            JOIN voting_lists vl ON vli.voting_list_id = vl.id
            WHERE vl.business_id IS NULL
        ")->fetchColumn();
        
        if ($isolation_issues > 0) {
            echo "  ⚠️  Machine items without business isolation: $isolation_issues\n";
            $issues[] = "Business isolation not enforced for some items";
        } else {
            echo "  ✅ Business isolation properly enforced\n";
        }
        
    } catch (Exception $e) {
        echo "  ❌ Error checking data consistency: " . $e->getMessage() . "\n";
        $issues[] = "Unable to verify data consistency";
    }
    
    echo "\n";
    return $issues;
}

function analyzePerformanceImpact($pdo) {
    echo "⚡ PERFORMANCE IMPACT ANALYSIS\n";
    echo "-----------------------------\n";
    
    try {
        // Count records in each table
        $tables = ['master_items', 'voting_lists', 'voting_list_items', 'sales', 'votes'];
        $record_counts = [];
        
        foreach ($tables as $table) {
            $count = $pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
            $record_counts[$table] = $count;
            echo "  📊 $table: " . number_format($count) . " records\n";
        }
        
        // Check for missing indexes
        echo "\nIndex Analysis:\n";
        $critical_indexes = [
            'voting_list_items' => ['voting_list_id', 'master_item_id', 'item_name'],
            'sales' => ['item_id', 'business_id', 'sale_time'],
            'votes' => ['item_id', 'machine_id', 'created_at'],
            'master_items' => ['category', 'status', 'name']
        ];
        
        foreach ($critical_indexes as $table => $columns) {
            $indexes = $pdo->query("SHOW INDEX FROM $table")->fetchAll();
            $existing_indexes = array_column($indexes, 'Column_name');
            
            foreach ($columns as $column) {
                if (in_array($column, $existing_indexes)) {
                    echo "  ✅ $table.$column: Indexed\n";
                } else {
                    echo "  ⚠️  $table.$column: Missing index\n";
                }
            }
        }
        
        // Query performance analysis
        echo "\nQuery Performance:\n";
        $test_queries = [
            "SELECT COUNT(*) FROM voting_list_items vli JOIN voting_lists vl ON vli.voting_list_id = vl.id WHERE vl.business_id = 1",
            "SELECT COUNT(*) FROM sales s JOIN voting_list_items vli ON s.item_id = vli.id WHERE s.sale_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
            "SELECT COUNT(*) FROM master_items mi LEFT JOIN voting_list_items vli ON mi.id = vli.master_item_id"
        ];
        
        foreach ($test_queries as $query) {
            $start = microtime(true);
            $pdo->query($query);
            $duration = microtime(true) - $start;
            
            if ($duration < 0.1) {
                echo "  ✅ Query performance: " . round($duration * 1000, 2) . "ms (Good)\n";
            } else if ($duration < 0.5) {
                echo "  ⚠️  Query performance: " . round($duration * 1000, 2) . "ms (Moderate)\n";
            } else {
                echo "  ❌ Query performance: " . round($duration * 1000, 2) . "ms (Slow)\n";
            }
        }
        
    } catch (Exception $e) {
        echo "  ❌ Performance analysis error: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
}

function analyzeBusinessLogic($pdo) {
    echo "🎯 BUSINESS LOGIC ANALYSIS\n";
    echo "-------------------------\n";
    
    try {
        // Get business data overview
        $business_stats = $pdo->query("
            SELECT 
                COUNT(DISTINCT vl.business_id) as businesses,
                COUNT(DISTINCT vl.id) as machines,
                COUNT(DISTINCT vli.id) as machine_items,
                COUNT(DISTINCT mi.id) as master_items,
                COUNT(DISTINCT s.id) as sales_records,
                COUNT(DISTINCT v.id) as vote_records
            FROM voting_lists vl
            LEFT JOIN voting_list_items vli ON vl.id = vli.voting_list_id
            LEFT JOIN master_items mi ON vli.master_item_id = mi.id
            LEFT JOIN sales s ON vli.id = s.item_id
            LEFT JOIN votes v ON vli.id = v.item_id
        ")->fetch();
        
        echo "Business Overview:\n";
        echo "  • Businesses: {$business_stats['businesses']}\n";
        echo "  • Machines: {$business_stats['machines']}\n";
        echo "  • Machine Items: {$business_stats['machine_items']}\n";
        echo "  • Master Items: {$business_stats['master_items']}\n";
        echo "  • Sales Records: {$business_stats['sales_records']}\n";
        echo "  • Vote Records: {$business_stats['vote_records']}\n\n";
        
        // Check item distribution
        echo "Item Distribution Analysis:\n";
        $item_distribution = $pdo->query("
            SELECT 
                vl.name as machine_name,
                COUNT(vli.id) as item_count,
                SUM(vli.inventory) as total_inventory,
                COUNT(CASE WHEN vli.inventory > 0 THEN 1 END) as stocked_items
            FROM voting_lists vl
            LEFT JOIN voting_list_items vli ON vl.id = vli.voting_list_id
            GROUP BY vl.id, vl.name
            ORDER BY item_count DESC
            LIMIT 5
        ")->fetchAll();
        
        foreach ($item_distribution as $machine) {
            echo "  • {$machine['machine_name']}: {$machine['item_count']} items, {$machine['total_inventory']} inventory, {$machine['stocked_items']} stocked\n";
        }
        
        // Check sales patterns
        echo "\nSales Pattern Analysis:\n";
        $sales_patterns = $pdo->query("
            SELECT 
                COUNT(*) as total_sales,
                SUM(quantity * sale_price) as total_revenue,
                COUNT(DISTINCT item_id) as unique_items_sold,
                AVG(sale_price) as avg_sale_price,
                DATE(MAX(sale_time)) as last_sale_date
            FROM sales
            WHERE sale_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ")->fetch();
        
        echo "  • Total sales (30 days): {$sales_patterns['total_sales']}\n";
        echo "  • Total revenue: $" . number_format($sales_patterns['total_revenue'], 2) . "\n";
        echo "  • Unique items sold: {$sales_patterns['unique_items_sold']}\n";
        echo "  • Average sale price: $" . number_format($sales_patterns['avg_sale_price'], 2) . "\n";
        echo "  • Last sale: {$sales_patterns['last_sale_date']}\n";
        
    } catch (Exception $e) {
        echo "  ❌ Business logic analysis error: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
}

function generateCleanupPlan($table_analysis, $consistency_issues) {
    echo "🛠️  CLEANUP PLAN GENERATION\n";
    echo "==========================\n";
    
    $plan = [];
    $priority_level = 1;
    
    // Phase 1: Critical fixes
    if (!empty($consistency_issues)) {
        echo "Phase 1: Critical Data Consistency Fixes\n";
        echo "---------------------------------------\n";
        
        foreach ($consistency_issues as $issue) {
            echo "  $priority_level. Fix: $issue\n";
            $plan[] = [
                'phase' => 1,
                'priority' => $priority_level++,
                'action' => $issue,
                'type' => 'critical'
            ];
        }
        echo "\n";
    }
    
    // Phase 2: Schema optimization
    echo "Phase 2: Schema Optimization\n";
    echo "---------------------------\n";
    echo "  $priority_level. Add missing database indexes\n";
    echo "  " . ($priority_level + 1) . ". Optimize foreign key constraints\n";
    echo "  " . ($priority_level + 2) . ". Create performance views\n";
    echo "  " . ($priority_level + 3) . ". Add data validation triggers\n\n";
    
    $priority_level += 4;
    
    // Phase 3: Business logic improvements
    echo "Phase 3: Business Logic Improvements\n";
    echo "-----------------------------------\n";
    echo "  $priority_level. Standardize item categorization\n";
    echo "  " . ($priority_level + 1) . ". Implement inventory sync mechanisms\n";
    echo "  " . ($priority_level + 2) . ". Create automated data cleanup jobs\n";
    echo "  " . ($priority_level + 3) . ". Add business rule validation\n\n";
    
    // Risk assessment
    echo "🎯 RISK ASSESSMENT:\n";
    echo "===================\n";
    
    $total_issues = count($consistency_issues);
    if ($total_issues == 0) {
        echo "✅ LOW RISK: No critical data consistency issues found\n";
        echo "   Safe to proceed with optimization phases\n\n";
        return 'LOW';
    } else if ($total_issues <= 3) {
        echo "⚠️  MEDIUM RISK: $total_issues data consistency issues found\n";
        echo "   Should fix critical issues before optimization\n\n";
        return 'MEDIUM';
    } else {
        echo "🚨 HIGH RISK: $total_issues data consistency issues found\n";
        echo "   Must fix all critical issues before proceeding\n\n";
        return 'HIGH';
    }
}

// Execute comprehensive analysis
try {
    echo "🚀 Starting Priority 3 Analysis...\n\n";
    
    $table_analysis = analyzeTableStructure($pdo);
    $consistency_issues = analyzeDataConsistency($pdo);
    analyzePerformanceImpact($pdo);
    analyzeBusinessLogic($pdo);
    $risk_level = generateCleanupPlan($table_analysis, $consistency_issues);
    
    echo "📋 ANALYSIS COMPLETE\n";
    echo "====================\n";
    echo "Risk Level: $risk_level\n";
    echo "Issues Found: " . count($consistency_issues) . "\n";
    echo "Tables Analyzed: " . count($table_analysis['existing']) . "\n";
    echo "\n";
    
    echo "🎯 RECOMMENDATION:\n";
    echo "==================\n";
    
    if ($risk_level === 'LOW') {
        echo "✅ PROCEED: System is in good shape, ready for optimization\n";
    } else if ($risk_level === 'MEDIUM') {
        echo "⚠️  CAUTION: Fix identified issues before major changes\n";
    } else {
        echo "🚨 HALT: Critical issues must be resolved first\n";
    }
    
} catch (Exception $e) {
    echo "❌ Analysis failed: " . $e->getMessage() . "\n";
} 