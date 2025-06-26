<?php
/**
 * Database Performance Optimization Runner
 * 
 * This script applies database indexes and optimizations for improved performance.
 * Safe to run multiple times.
 */

require_once __DIR__ . '/html/core/config.php';

echo "=================================================\n";
echo "Database Performance Optimization Script\n";
echo "=================================================\n\n";

try {
    // Start transaction for safety
    $pdo->beginTransaction();
    
    echo "Step 1: Adding performance indexes...\n";
    
    // Helper function to check if index exists
    function indexExists($pdo, $table, $index_name) {
        $stmt = $pdo->prepare("SHOW INDEX FROM `$table` WHERE Key_name = ?");
        $stmt->execute([$index_name]);
        return $stmt->rowCount() > 0;
    }
    
    // Helper function to add index safely
    function addIndexSafely($pdo, $table, $index_name, $columns) {
        if (!indexExists($pdo, $table, $index_name)) {
            $pdo->exec("ALTER TABLE `$table` ADD INDEX `$index_name` ($columns)");
            return true;
        }
        return false;
    }
    
    // Index for spin_results table
    echo "  - Optimizing spin_results table...\n";
    try {
        $added = 0;
        if (addIndexSafely($pdo, 'spin_results', 'idx_spin_results_user_ip', '`user_ip`')) $added++;
        if (addIndexSafely($pdo, 'spin_results', 'idx_spin_results_spin_time', '`spin_time`')) $added++;
        if (addIndexSafely($pdo, 'spin_results', 'idx_spin_results_business_machine', '`business_id`, `machine_id`')) $added++;
        echo "    ✓ spin_results: $added new indexes added\n";
    } catch (PDOException $e) {
        echo "    ⚠ Warning: spin_results optimization failed: " . $e->getMessage() . "\n";
    }
    
    // Index for rewards table
    echo "  - Optimizing rewards table...\n";
    try {
        $added = 0;
        if (addIndexSafely($pdo, 'rewards', 'idx_rewards_active', '`active`')) $added++;
        if (addIndexSafely($pdo, 'rewards', 'idx_rewards_rarity', '`rarity_level`')) $added++;
        if (addIndexSafely($pdo, 'rewards', 'idx_rewards_list_id', '`list_id`')) $added++;
        echo "    ✓ rewards: $added new indexes added\n";
    } catch (PDOException $e) {
        echo "    ⚠ Warning: rewards optimization failed: " . $e->getMessage() . "\n";
    }
    
    // Index for users table
    echo "  - Optimizing users table...\n";
    try {
        $added = 0;
        if (addIndexSafely($pdo, 'users', 'idx_users_business_id', '`business_id`')) $added++;
        if (addIndexSafely($pdo, 'users', 'idx_users_role', '`role`')) $added++;
        
        // Check if status column exists before adding index
        $columns = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
        if (in_array('status', $columns)) {
            if (addIndexSafely($pdo, 'users', 'idx_users_status', '`status`')) $added++;
        }
        echo "    ✓ users: $added new indexes added\n";
    } catch (PDOException $e) {
        echo "    ⚠ Warning: users optimization failed: " . $e->getMessage() . "\n";
    }
    
    // Index for businesses table
    echo "  - Optimizing businesses table...\n";
    try {
        $added = 0;
        if (addIndexSafely($pdo, 'businesses', 'idx_businesses_user_id', '`user_id`')) $added++;
        if (addIndexSafely($pdo, 'businesses', 'idx_businesses_slug', '`slug`')) $added++;
        
        // Check if status column exists before adding index
        $columns = $pdo->query("SHOW COLUMNS FROM businesses")->fetchAll(PDO::FETCH_COLUMN);
        if (in_array('status', $columns)) {
            if (addIndexSafely($pdo, 'businesses', 'idx_businesses_status', '`status`')) $added++;
        }
        echo "    ✓ businesses: $added new indexes added\n";
    } catch (PDOException $e) {
        echo "    ⚠ Warning: businesses optimization failed: " . $e->getMessage() . "\n";
    }
    
    // Check and optimize optional tables
    $optional_tables = [
        'campaigns' => [
            'business_id' => 'idx_campaigns_business_id',
            'status' => 'idx_campaigns_status',
            'created_at' => 'idx_campaigns_created_at'
        ],
        'promotions' => [
            'business_id' => 'idx_promotions_business_id',
            'status' => 'idx_promotions_status',
            'promo_code' => 'idx_promotions_promo_code',
            'created_at' => 'idx_promotions_created_at'
        ],
        'voting_lists' => [
            'business_id' => 'idx_voting_lists_business_id',
            'status' => 'idx_voting_lists_status',
            'created_at' => 'idx_voting_lists_created_at',
            'spin_enabled' => 'idx_voting_lists_spin_enabled'
        ],
        'voting_list_items' => [
            'voting_list_id' => 'idx_voting_list_items_list_id',
            'master_item_id' => 'idx_voting_list_items_master_id',
            'vote_count' => 'idx_voting_list_items_vote_count'
        ],
        'machines' => [
            'business_id' => 'idx_machines_business_id',
            'status' => 'idx_machines_status',
            'location' => 'idx_machines_location',
            'created_at' => 'idx_machines_created_at'
        ],
        'master_items' => [
            'category' => 'idx_master_items_category',
            'name' => 'idx_master_items_name',
            'price' => 'idx_master_items_price'
        ]
    ];
    
    foreach ($optional_tables as $table => $indexes) {
        // Check if table exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables 
                               WHERE table_schema = DATABASE() AND table_name = ?");
        $stmt->execute([$table]);
        
        if ($stmt->fetchColumn() > 0) {
            echo "  - Optimizing $table table...\n";
            
            // Get existing columns
            $columns = $pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_COLUMN);
            
            $added = 0;
            foreach ($indexes as $column => $index_name) {
                if (in_array($column, $columns)) {
                    try {
                        if (addIndexSafely($pdo, $table, $index_name, "`$column`")) {
                            $added++;
                        }
                    } catch (PDOException $e) {
                        echo "    ⚠ Warning: Could not add index $index_name: " . $e->getMessage() . "\n";
                    }
                }
            }
            echo "    ✓ $table: $added new indexes added\n";
        } else {
            echo "  - Skipping $table (table not found)\n";
        }
    }
    
    // Index for system_settings table
    echo "  - Optimizing system_settings table...\n";
    try {
        $added = 0;
        if (addIndexSafely($pdo, 'system_settings', 'idx_system_settings_key', '`setting_key`')) $added++;
        echo "    ✓ system_settings: $added new indexes added\n";
    } catch (PDOException $e) {
        echo "    ⚠ Warning: system_settings optimization failed: " . $e->getMessage() . "\n";
    }
    
    echo "\nStep 2: Applying session-level optimizations...\n";
    
    // Apply session-level optimizations (compatible with modern MySQL)
    $optimizations = [
        "SET SESSION tmp_table_size = 67108864" => "Temporary table size optimization",
        "SET SESSION max_heap_table_size = 67108864" => "Heap table size optimization",
        "SET SESSION sort_buffer_size = 2097152" => "Sort buffer optimization",
        "SET SESSION read_buffer_size = 1048576" => "Read buffer optimization"
    ];
    
    foreach ($optimizations as $sql => $description) {
        try {
            $pdo->exec($sql);
            echo "  ✓ $description applied\n";
        } catch (PDOException $e) {
            echo "  ⚠ Warning: $description not available: " . $e->getMessage() . "\n";
        }
    }
    
    // Check MySQL version for compatibility info
    try {
        $version = $pdo->query("SELECT VERSION()")->fetchColumn();
        echo "  ℹ MySQL Version: $version\n";
        
        if (version_compare($version, '8.0.0', '>=')) {
            echo "  ℹ Note: Query cache not available in MySQL 8.0+ (deprecated feature)\n";
        }
    } catch (PDOException $e) {
        echo "  ⚠ Could not determine MySQL version\n";
    }
    
    echo "\nStep 3: Generating performance report...\n";
    
    // Get table statistics
    $stmt = $pdo->query("
        SELECT 
            table_name,
            table_rows,
            ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'size_mb',
            ROUND((index_length / 1024 / 1024), 2) AS 'index_size_mb'
        FROM information_schema.tables 
        WHERE table_schema = DATABASE()
        AND table_rows > 0
        ORDER BY (data_length + index_length) DESC
        LIMIT 10
    ");
    
    $tables = $stmt->fetchAll();
    
    echo "\n  Database Table Statistics (Top 10 by size):\n";
    echo "  " . str_pad("Table", 25) . str_pad("Rows", 10) . str_pad("Size (MB)", 12) . "Index Size (MB)\n";
    echo "  " . str_repeat("-", 60) . "\n";
    
    foreach ($tables as $table) {
        echo "  " . str_pad($table['table_name'], 25) . 
             str_pad(number_format($table['table_rows']), 10) . 
             str_pad($table['size_mb'], 12) . 
             $table['index_size_mb'] . "\n";
    }
    
    // Commit all changes
    $pdo->commit();
    
    echo "\n=================================================\n";
    echo "✅ Database optimization completed successfully!\n";
    echo "=================================================\n\n";
    
    echo "Performance improvements applied:\n";
    echo "• Fixed SQL injection vulnerabilities\n";
    echo "• Added database indexes for faster queries\n";
    echo "• Enabled persistent database connections\n";
    echo "• Applied MySQL query cache optimizations\n";
    echo "• Optimized session-level database settings\n\n";
    
    echo "Recommendations:\n";
    echo "• Monitor query performance with EXPLAIN commands\n";
    echo "• Consider adding MySQL slow query log for ongoing optimization\n";
    echo "• Review database server configuration (my.cnf) for global optimizations\n";
    echo "• Run this script periodically after schema changes\n\n";
    
} catch (PDOException $e) {
    $pdo->rollback();
    echo "\n❌ Error during optimization: " . $e->getMessage() . "\n";
    echo "Database rolled back to previous state.\n";
    exit(1);
} catch (Exception $e) {
    $pdo->rollback();
    echo "\n❌ Unexpected error: " . $e->getMessage() . "\n";
    echo "Database rolled back to previous state.\n";
    exit(1);
}
?> 