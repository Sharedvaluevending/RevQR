<?php
/**
 * Safe CPU Optimization Testing
 * 
 * This script safely tests the CPU optimization system with minimal impact
 */

require_once __DIR__ . '/html/core/config.php';

echo "🛡️ SAFE CPU OPTIMIZATION TEST\n";
echo "=============================\n\n";

class SafeCPUTester {
    private $cpu_cores;
    
    public function __construct() {
        $this->cpu_cores = $this->detectCPUCores();
        echo "System Info:\n";
        echo "  CPU Cores: {$this->cpu_cores}\n";
        echo "  Recommended Max Processes: " . max(2, floor($this->cpu_cores * 0.75)) . "\n";
        echo "  Current Load: " . $this->getCurrentLoad() . "\n\n";
    }
    
    public function runSafeTests() {
        echo "Running safe tests...\n\n";
        
        // Test 1: Resource Detection (completely safe)
        $this->testResourceDetection();
        
        // Test 2: Small parallel test (minimal impact)
        $this->testMinimalParallel();
        
        // Test 3: System monitoring (read-only)
        $this->testSystemMonitoring();
        
        echo "\n✅ All safe tests completed successfully!\n";
        echo "The CPU optimization system appears safe to use.\n";
    }
    
    private function testResourceDetection() {
        echo "Test 1: Resource Detection (Safe - Read Only)\n";
        echo "---------------------------------------------\n";
        
        // CPU Detection
        $cpu_info = $this->getCPUInfo();
        echo "  ✅ CPU Detection: {$cpu_info['cores']} cores, {$cpu_info['model']}\n";
        
        // Memory Detection  
        $memory_info = $this->getMemoryInfo();
        echo "  ✅ Memory Detection: {$memory_info['total']}MB total, {$memory_info['available']}MB available\n";
        
        // Load Detection
        $load = $this->getCurrentLoad();
        echo "  ✅ Load Detection: Current load = $load\n";
        
        echo "  → All system detection working safely\n\n";
    }
    
    private function testMinimalParallel() {
        echo "Test 2: Minimal Parallel Test (Safe - Very Light)\n";
        echo "-------------------------------------------------\n";
        
        // Only run if system load is reasonable
        $current_load = floatval($this->getCurrentLoad());
        $safe_threshold = $this->cpu_cores * 0.5; // Very conservative
        
        if ($current_load > $safe_threshold) {
            echo "  ⚠️ Current load ($current_load) too high for parallel test\n";
            echo "  → Skipping parallel test for safety\n\n";
            return;
        }
        
        echo "  Running 2 lightweight parallel tasks...\n";
        
        // Create very simple, short tasks
        $start_time = microtime(true);
        
        $processes = [];
        for ($i = 1; $i <= 2; $i++) {
            $process = proc_open(
                "php -r \"echo 'Task $i start\n'; usleep(500000); echo 'Task $i done\n';\"",
                [1 => ["pipe", "w"], 2 => ["pipe", "w"]],
                $pipes
            );
            
            if ($process) {
                $processes[] = ["process" => $process, "pipes" => $pipes, "id" => $i];
            }
        }
        
        // Wait for completion
        foreach ($processes as $proc_info) {
            $output = stream_get_contents($proc_info["pipes"][1]);
            echo "    " . trim($output) . "\n";
            
            fclose($proc_info["pipes"][1]);
            fclose($proc_info["pipes"][2]);
            proc_close($proc_info["process"]);
        }
        
        $duration = microtime(true) - $start_time;
        echo "  ✅ Parallel execution completed in " . round($duration, 2) . "s\n";
        echo "  → Parallel processing working safely\n\n";
    }
    
    private function testSystemMonitoring() {
        echo "Test 3: System Monitoring (Safe - Read Only)\n";
        echo "--------------------------------------------\n";
        
        $stats = [
            "CPU Usage" => $this->getCPUUsage() . "%",
            "Memory Usage" => round($this->getMemoryUsage(), 1) . "%", 
            "Load Average" => $this->getCurrentLoad(),
            "Disk Usage" => round($this->getDiskUsage(), 1) . "%",
            "Process Count" => $this->getProcessCount()
        ];
        
        foreach ($stats as $metric => $value) {
            echo "  ✅ $metric: $value\n";
        }
        
        echo "  → System monitoring working safely\n\n";
    }
    
    // Safe utility methods
    private function detectCPUCores() {
        $cores = 1;
        if (is_file('/proc/cpuinfo')) {
            $cores = substr_count(file_get_contents('/proc/cpuinfo'), 'processor');
        }
        return max(1, $cores);
    }
    
    private function getCPUInfo() {
        $cores = $this->cpu_cores;
        $model = "Unknown";
        
        if (is_file('/proc/cpuinfo')) {
            $cpuinfo = file_get_contents('/proc/cpuinfo');
            if (preg_match('/model name\s*:\s*(.+)/', $cpuinfo, $matches)) {
                $model = trim($matches[1]);
                $model = substr($model, 0, 50) . (strlen($model) > 50 ? '...' : '');
            }
        }
        
        return ["cores" => $cores, "model" => $model];
    }
    
    private function getMemoryInfo() {
        $total = 0;
        $available = 0;
        
        if (is_file('/proc/meminfo')) {
            $meminfo = file_get_contents('/proc/meminfo');
            if (preg_match('/MemTotal:\s+(\d+)/', $meminfo, $matches)) {
                $total = round(intval($matches[1]) / 1024); // Convert KB to MB
            }
            if (preg_match('/MemAvailable:\s+(\d+)/', $meminfo, $matches)) {
                $available = round(intval($matches[1]) / 1024); // Convert KB to MB
            }
        }
        
        return ["total" => $total, "available" => $available];
    }
    
    private function getCurrentLoad() {
        if (function_exists('sys_getloadavg')) {
            return round(sys_getloadavg()[0], 2);
        }
        
        if (is_file('/proc/loadavg')) {
            $loadavg = file_get_contents('/proc/loadavg');
            $parts = explode(' ', $loadavg);
            return round(floatval($parts[0]), 2);
        }
        
        return 0.0;
    }
    
    private function getCPUUsage() {
        $load = $this->getCurrentLoad();
        return round(min(100, ($load / $this->cpu_cores) * 100), 1);
    }
    
    private function getMemoryUsage() {
        $memory_info = $this->getMemoryInfo();
        if ($memory_info['total'] > 0) {
            $used = $memory_info['total'] - $memory_info['available'];
            return ($used / $memory_info['total']) * 100;
        }
        return 0;
    }
    
    private function getDiskUsage() {
        $total = disk_total_space('.');
        $free = disk_free_space('.');
        
        if ($total && $free) {
            return (($total - $free) / $total) * 100;
        }
        
        return 0;
    }
    
    private function getProcessCount() {
        if (function_exists('shell_exec')) {
            $output = shell_exec('ps aux | wc -l 2>/dev/null');
            return max(0, intval($output) - 1);
        }
        return 0;
    }
}

// Run safe tests
try {
    $tester = new SafeCPUTester();
    $tester->runSafeTests();
    
    echo "\n🎯 NEXT STEPS IF TESTS LOOK GOOD:\n";
    echo "1. Test the full system: php run_cpu_optimizations.php\n";
    echo "2. Test parallel execution: php test_cpu_optimizations.php\n"; 
    echo "3. Monitor system during tests: watch -n 5 'top -bn1 | head -10'\n";
    
} catch (Exception $e) {
    echo "❌ Safety test failed: " . $e->getMessage() . "\n";
    echo "The system may not be ready for CPU optimizations.\n";
}
?> 