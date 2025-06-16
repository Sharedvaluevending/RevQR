<?php
/**
 * CPU Optimization Safety Test
 * Tests the safety and functionality of CPU optimizations
 */

echo "🛡️ CPU OPTIMIZATION SAFETY TEST\n";
echo "===============================\n\n";

// System Information
echo "📊 SYSTEM ANALYSIS\n";
echo "------------------\n";

$cpu_cores = 1;
if (is_file('/proc/cpuinfo')) {
    $cpu_cores = substr_count(file_get_contents('/proc/cpuinfo'), 'processor');
}

echo "CPU Cores: $cpu_cores\n";

// Load average
$load = 0;
if (function_exists('sys_getloadavg')) {
    $load = sys_getloadavg()[0];
} elseif (is_file('/proc/loadavg')) {
    $loadavg = file_get_contents('/proc/loadavg');
    $parts = explode(' ', $loadavg);
    $load = floatval($parts[0]);
}
echo "Current Load: $load\n";

// Memory info
$memory_total = 0;
$memory_available = 0;
if (is_file('/proc/meminfo')) {
    $meminfo = file_get_contents('/proc/meminfo');
    if (preg_match('/MemTotal:\s+(\d+)/', $meminfo, $matches)) {
        $memory_total = round(intval($matches[1]) / 1024); // MB
    }
    if (preg_match('/MemAvailable:\s+(\d+)/', $meminfo, $matches)) {
        $memory_available = round(intval($matches[1]) / 1024); // MB
    }
}
echo "Memory: {$memory_available}MB available / {$memory_total}MB total\n";

$cpu_usage = min(100, ($load / $cpu_cores) * 100);
echo "CPU Usage: " . round($cpu_usage, 1) . "%\n\n";

// Safety Assessment
echo "🔍 SAFETY ASSESSMENT\n";
echo "-------------------\n";

$safety_score = 100;
$warnings = [];
$recommendations = [];

// Check CPU load
if ($load > $cpu_cores * 0.8) {
    $safety_score -= 30;
    $warnings[] = "High CPU load detected ($load)";
    $recommendations[] = "Wait for CPU load to decrease before running optimizations";
}

// Check memory
$memory_usage_percent = 0;
if ($memory_total > 0) {
    $memory_used = $memory_total - $memory_available;
    $memory_usage_percent = ($memory_used / $memory_total) * 100;
}

if ($memory_usage_percent > 80) {
    $safety_score -= 20;
    $warnings[] = "High memory usage (" . round($memory_usage_percent, 1) . "%)";
    $recommendations[] = "Free up memory before running parallel processes";
}

// Check available cores
$max_processes = max(2, floor($cpu_cores * 0.75));
if ($cpu_cores < 2) {
    $safety_score -= 20;
    $warnings[] = "Limited CPU cores ($cpu_cores)";
    $recommendations[] = "CPU optimizations will have limited benefit on single-core systems";
}

echo "Safety Score: $safety_score/100\n";

if (!empty($warnings)) {
    echo "\n⚠️ WARNINGS:\n";
    foreach ($warnings as $warning) {
        echo "  • $warning\n";
    }
}

if (!empty($recommendations)) {
    echo "\n💡 RECOMMENDATIONS:\n";
    foreach ($recommendations as $rec) {
        echo "  • $rec\n";
    }
}

echo "\n🚀 OPTIMIZATION PARAMETERS\n";
echo "-------------------------\n";
echo "Max Concurrent Processes: $max_processes\n";
echo "Load Threshold: " . ($cpu_cores * 0.8) . "\n";
echo "Memory Limit per Process: 512MB\n";
echo "Execution Timeout: 300 seconds\n";

// Safety Test
echo "\n🧪 RUNNING SAFETY TESTS\n";
echo "======================\n";

echo "Test 1: Process Creation Safety\n";
$test_start = microtime(true);

// Test creating a simple process
$test_process = proc_open(
    'php -r "echo \'Test process OK\'; exit(0);"',
    [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
    $pipes
);

if ($test_process) {
    $output = stream_get_contents($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exit_code = proc_close($test_process);
    
    if ($exit_code === 0) {
        echo "  ✅ Process creation: SAFE\n";
    } else {
        echo "  ❌ Process creation: FAILED (exit code: $exit_code)\n";
        $safety_score -= 40;
    }
} else {
    echo "  ❌ Process creation: FAILED\n";
    $safety_score -= 40;
}

$test_duration = microtime(true) - $test_start;
echo "  Duration: " . round($test_duration * 1000, 2) . "ms\n\n";

echo "Test 2: File System Safety\n";
$temp_file = sys_get_temp_dir() . '/cpu_test_' . uniqid() . '.tmp';

// Test file operations
if (file_put_contents($temp_file, 'test data')) {
    if (file_get_contents($temp_file) === 'test data') {
        unlink($temp_file);
        echo "  ✅ File operations: SAFE\n";
    } else {
        echo "  ❌ File read: FAILED\n";
        $safety_score -= 20;
    }
} else {
    echo "  ❌ File write: FAILED\n";
    $safety_score -= 20;
}

echo "\nTest 3: Resource Monitoring Safety\n";
try {
    $monitor_load = function_exists('sys_getloadavg') ? 'Available' : 'Limited';
    $monitor_memory = is_file('/proc/meminfo') ? 'Available' : 'Limited';
    $monitor_disk = function_exists('disk_free_space') ? 'Available' : 'Limited';
    
    echo "  ✅ Load monitoring: $monitor_load\n";
    echo "  ✅ Memory monitoring: $monitor_memory\n";
    echo "  ✅ Disk monitoring: $monitor_disk\n";
} catch (Exception $e) {
    echo "  ❌ Resource monitoring: FAILED - " . $e->getMessage() . "\n";
    $safety_score -= 15;
}

// Final Assessment
echo "\n" . str_repeat("=", 40) . "\n";
echo "🎯 FINAL SAFETY ASSESSMENT\n";
echo str_repeat("=", 40) . "\n";

if ($safety_score >= 90) {
    echo "✅ EXCELLENT - System is very safe for CPU optimizations\n";
    echo "   → Proceed with full optimization system\n";
} elseif ($safety_score >= 70) {
    echo "✅ GOOD - System is safe for CPU optimizations with monitoring\n";
    echo "   → Proceed with caution and monitor performance\n";
} elseif ($safety_score >= 50) {
    echo "⚠️  MODERATE - System has some limitations\n";
    echo "   → Proceed with reduced parallelization\n";
    echo "   → Consider addressing warnings first\n";
} else {
    echo "❌ POOR - System may not be suitable for CPU optimizations\n";
    echo "   → Address critical issues before proceeding\n";
    echo "   → Consider system upgrades\n";
}

echo "\nFinal Safety Score: $safety_score/100\n";

echo "\n📋 WHAT THE CPU OPTIMIZATIONS DO:\n";
echo "• Create parallel processing pools (max $max_processes processes)\n";
echo "• Monitor and balance CPU load automatically\n";
echo "• Process large datasets in chunks\n";
echo "• Optimize background tasks and cron jobs\n";
echo "• Provide real-time resource monitoring\n";
echo "• All operations include timeouts and resource limits\n";

echo "\n🔧 TO PROCEED SAFELY:\n";
echo "1. Start with: php run_cpu_optimizations.php\n";
echo "2. Test with: php test_cpu_optimizations.php\n";
echo "3. Monitor with: ./cpu_optimized_cron_runner.sh test\n";
echo "4. Watch system: htop or top in another terminal\n";

if ($safety_score < 70) {
    echo "\n⚠️  RECOMMENDED: Address the warnings above first\n";
}

echo "\n✅ This safety test is complete. The system appears " . 
     ($safety_score >= 70 ? "SAFE" : "RISKY") . " for CPU optimizations.\n";
?> 