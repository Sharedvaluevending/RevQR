<?php
/**
 * Advanced Query Optimization Runner
 * Comprehensive performance optimization for the entire system
 */

require_once __DIR__ . '/html/core/config.php';
require_once __DIR__ . '/html/core/query_optimizer.php';

echo "=================================================\n";
echo "🚀 ADVANCED QUERY OPTIMIZATION SYSTEM\n";
echo "=================================================\n\n";

$start_time = microtime(true);
$initial_memory = memory_get_usage(true);

try {
    $pdo->beginTransaction();
    
    // Initialize query optimizer
    $optimizer = new QueryOptimizer($pdo);
    
    echo "Phase 1: Running Advanced Database Optimizations...\n";
    echo "==================================================\n";
    
    // Run the advanced SQL optimization script
    $sql_script = file_get_contents(__DIR__ . '/advanced_query_optimization.sql');
    $sql_statements = explode(';', $sql_script);
    
    $indexes_added = 0;
    $failed_operations = 0;
    
    foreach ($sql_statements as $statement) {
        $statement = trim($statement);
        if (empty($statement) || strpos($statement, '--') === 0) continue;
        
        try {
            if (strpos($statement, 'SELECT') === 0) {
                // Skip SELECT statements (they're just for display)
                continue;
            }
            
            $pdo->exec($statement);
            
            if (stripos($statement, 'INDEX') !== false) {
                $indexes_added++;
            }
            
            echo "  ✅ " . substr($statement, 0, 60) . "...\n";
            
        } catch (PDOException $e) {
            $failed_operations++;
            echo "  ⚠️  Warning: " . substr($statement, 0, 40) . "... - " . $e->getMessage() . "\n";
        }
    }
    
    echo "\nPhase 2: Testing Query Performance...\n";
    echo "===================================\n";
    
    // Test optimized queries
    $performance_results = testQueryPerformance($pdo, $optimizer);
    
    echo "\nPhase 3: Memory Usage Analysis...\n";
    echo "================================\n";
    
    // Analyze memory usage patterns
    analyzeMemoryUsage($pdo);
    
    $pdo->commit();
    
    echo "\n🎉 OPTIMIZATION COMPLETE!\n";
    echo "========================\n";
    
    $total_time = microtime(true) - $start_time;
    $memory_used = memory_get_usage(true) - $initial_memory;
    
    echo "📊 Summary Statistics:\n";
    echo "  • Total execution time: " . round($total_time, 2) . " seconds\n";
    echo "  • Memory used: " . formatBytes($memory_used) . "\n";
    echo "  • Indexes added: $indexes_added\n";
    echo "  • Failed operations: $failed_operations\n";
    
    // Display performance results
    if (!empty($performance_results)) {
        echo "\n📈 Performance Test Results:\n";
        foreach ($performance_results as $test => $result) {
            echo "  • $test: " . round($result['time'], 3) . "s (" . $result['status'] . ")\n";
        }
    }
    
    // Get optimizer report
    $optimizer_report = $optimizer->getPerformanceReport();
    if ($optimizer_report['total_queries'] > 0) {
        echo "\n⚡ Query Optimizer Report:\n";
        echo "  • Total queries executed: " . $optimizer_report['total_queries'] . "\n";
        echo "  • Average execution time: " . round($optimizer_report['avg_execution_time'], 3) . "s\n";
        echo "  • Slow queries: " . $optimizer_report['slow_queries'] . "\n";
        echo "  • High memory queries: " . $optimizer_report['high_memory_queries'] . "\n";
    }
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo "❌ OPTIMIZATION FAILED: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

function testQueryPerformance($pdo, $optimizer) {
    echo "  ⚡ Testing query performance...\n";
    
    $tests = [];
    
    try {
        // Test 1: Simple SELECT with optimization
        $start = microtime(true);
        $stmt = $optimizer->executeOptimized("SELECT COUNT(*) FROM users");
        $stmt->fetch();
        $tests['simple_select'] = ['time' => microtime(true) - $start, 'status' => 'pass'];
        
        // Test 2: Complex JOIN with optimization
        $start = microtime(true);
        $stmt = $optimizer->executeOptimized("
            SELECT COUNT(*) 
            FROM voting_list_items vli 
            JOIN voting_lists vl ON vli.voting_list_id = vl.id 
            WHERE vl.business_id = 1
        ", []);
        $stmt->fetch();
        $tests['complex_join'] = ['time' => microtime(true) - $start, 'status' => 'pass'];
        
        echo "    ✅ All performance tests passed\n";
        
    } catch (Exception $e) {
        echo "    ❌ Performance test failed: " . $e->getMessage() . "\n";
        $tests['error'] = ['time' => 0, 'status' => 'fail'];
    }
    
    return $tests;
}

function analyzeMemoryUsage($pdo) {
    echo "  🧠 Analyzing memory usage patterns...\n";
    
    try {
        // Get table sizes
        $stmt = $pdo->query("
            SELECT 
                table_name,
                table_rows,
                ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb,
                ROUND((index_length / 1024 / 1024), 2) AS index_size_mb
            FROM information_schema.tables 
            WHERE table_schema = DATABASE()
            AND table_rows > 0
            ORDER BY (data_length + index_length) DESC
            LIMIT 10
        ");
        
        $tables = $stmt->fetchAll();
        
        echo "    📊 Top 10 Tables by Size:\n";
        foreach ($tables as $table) {
            echo sprintf("      • %-25s %8s rows %8s MB (%s MB indexes)\n",
                $table['table_name'],
                number_format($table['table_rows']),
                $table['size_mb'],
                $table['index_size_mb']
            );
        }
        
        // Memory recommendations
        $total_size = array_sum(array_column($tables, 'size_mb'));
        echo "    💡 Total database size: " . round($total_size, 2) . " MB\n";
        
        if ($total_size > 100) {
            echo "    ⚠️  Large database detected - consider implementing data archiving\n";
        }
        
    } catch (Exception $e) {
        echo "    ❌ Memory analysis failed: " . $e->getMessage() . "\n";
    }
}

function formatBytes($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    while ($bytes > 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 2) . ' ' . $units[$i];
}
?> 