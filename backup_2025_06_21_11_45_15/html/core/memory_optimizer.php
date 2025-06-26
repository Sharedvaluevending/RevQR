<?php
/**
 * Memory Optimization Utilities
 * Provides memory-efficient database operations and monitoring
 */

class MemoryOptimizer {
    private $pdo;
    private $memory_threshold = 50 * 1024 * 1024; // 50MB default threshold
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Execute query with memory monitoring
     */
    public function executeWithMonitoring($query, $params = [], $operation_name = 'query') {
        $initial_memory = memory_get_usage(true);
        $start_time = microtime(true);
        
        try {
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            
            $execution_time = microtime(true) - $start_time;
            $memory_used = memory_get_usage(true) - $initial_memory;
            
            // Log slow queries
            if ($execution_time > 1.0) {
                error_log("Slow Query ({$execution_time}s): $operation_name - " . substr($query, 0, 100));
            }
            
            // Log high memory usage
            if ($memory_used > $this->memory_threshold) {
                error_log("High Memory Usage: $operation_name - " . $this->formatBytes($memory_used));
            }
            
            return $stmt;
            
        } catch (Exception $e) {
            error_log("Query Error in $operation_name: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Fetch data in chunks to avoid memory overload
     */
    public function fetchInChunks($query, $params = [], $chunk_size = 100) {
        $offset = 0;
        $has_more = true;
        
        while ($has_more) {
            $chunked_query = $query . " LIMIT $chunk_size OFFSET $offset";
            $stmt = $this->executeWithMonitoring($chunked_query, $params, 'chunk_fetch');
            
            $chunk = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $has_more = count($chunk) === $chunk_size;
            
            if (!empty($chunk)) {
                yield $chunk;
            }
            
            $offset += $chunk_size;
        }
    }
    
    /**
     * Stream large result sets one row at a time
     */
    public function streamResults($query, $params = []) {
        $stmt = $this->executeWithMonitoring($query, $params, 'stream_results');
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            yield $row;
        }
    }
    
    /**
     * Get paginated results with memory optimization
     */
    public function getPaginatedResults($base_query, $params, $page = 1, $per_page = 50, $max_per_page = 100) {
        // Enforce maximum per page to prevent memory issues
        $per_page = min($per_page, $max_per_page);
        $offset = ($page - 1) * $per_page;
        
        // Get total count efficiently
        $count_query = "SELECT COUNT(*) FROM ($base_query) as count_query";
        $count_stmt = $this->executeWithMonitoring($count_query, $params, 'count_query');
        $total_count = $count_stmt->fetchColumn();
        
        // Get paginated results
        $paginated_query = "$base_query LIMIT $per_page OFFSET $offset";
        $stmt = $this->executeWithMonitoring($paginated_query, $params, 'paginated_query');
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'data' => $results,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $per_page,
                'total_count' => $total_count,
                'total_pages' => ceil($total_count / $per_page),
                'has_next' => $page < ceil($total_count / $per_page),
                'has_prev' => $page > 1
            ]
        ];
    }
    
    /**
     * Check if enough memory is available for operation
     */
    public function checkMemoryAvailable($required_mb = 50) {
        $memory_limit = $this->parseMemoryLimit(ini_get('memory_limit'));
        $current_usage = memory_get_usage(true);
        $required_bytes = $required_mb * 1024 * 1024;
        
        if (($current_usage + $required_bytes) > $memory_limit) {
            throw new Exception("Insufficient memory for operation. Required: {$required_mb}MB, Available: " . 
                              $this->formatBytes($memory_limit - $current_usage));
        }
        
        return true;
    }
    
    /**
     * Log current memory usage
     */
    public function logMemoryUsage($operation) {
        $current = memory_get_usage(true);
        $peak = memory_get_peak_usage(true);
        
        error_log("Memory Usage - $operation: Current=" . $this->formatBytes($current) . 
                 " Peak=" . $this->formatBytes($peak));
    }
    
    /**
     * Optimize query by adding appropriate limits
     */
    public function optimizeQuery($query, $default_limit = 1000) {
        // Add LIMIT if not present
        if (stripos($query, 'LIMIT') === false) {
            $query .= " LIMIT $default_limit";
        }
        
        return $query;
    }
    
    /**
     * Execute aggregation query with memory optimization
     */
    public function executeAggregation($query, $params = []) {
        // Aggregation queries should be fast and memory-efficient
        $this->checkMemoryAvailable(10); // 10MB should be enough for aggregations
        
        $stmt = $this->executeWithMonitoring($query, $params, 'aggregation');
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Batch process large datasets
     */
    public function batchProcess($query, $params, $processor_callback, $batch_size = 100) {
        $processed = 0;
        
        foreach ($this->fetchInChunks($query, $params, $batch_size) as $chunk) {
            foreach ($chunk as $row) {
                call_user_func($processor_callback, $row);
                $processed++;
            }
            
            // Log progress for large operations
            if ($processed % 1000 === 0) {
                $this->logMemoryUsage("batch_process_$processed");
            }
        }
        
        return $processed;
    }
    
    /**
     * Format bytes for human reading
     */
    private function formatBytes($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
    
    /**
     * Parse memory limit string to bytes
     */
    private function parseMemoryLimit($limit) {
        if ($limit == -1) {
            return PHP_INT_MAX;
        }
        
        $limit = trim($limit);
        $last = strtolower($limit[strlen($limit)-1]);
        $limit = (int) $limit;
        
        switch($last) {
            case 'g': $limit *= 1024;
            case 'm': $limit *= 1024;
            case 'k': $limit *= 1024;
        }
        
        return $limit;
    }
    
    /**
     * Set memory threshold for warnings
     */
    public function setMemoryThreshold($mb) {
        $this->memory_threshold = $mb * 1024 * 1024;
    }
    
    /**
     * Get memory statistics
     */
    public function getMemoryStats() {
        return [
            'current_usage' => memory_get_usage(true),
            'current_usage_formatted' => $this->formatBytes(memory_get_usage(true)),
            'peak_usage' => memory_get_peak_usage(true),
            'peak_usage_formatted' => $this->formatBytes(memory_get_peak_usage(true)),
            'memory_limit' => $this->parseMemoryLimit(ini_get('memory_limit')),
            'memory_limit_formatted' => ini_get('memory_limit'),
            'available_memory' => $this->parseMemoryLimit(ini_get('memory_limit')) - memory_get_usage(true),
            'available_memory_formatted' => $this->formatBytes($this->parseMemoryLimit(ini_get('memory_limit')) - memory_get_usage(true))
        ];
    }
}

/**
 * Global helper function to get memory optimizer instance
 */
function getMemoryOptimizer() {
    static $optimizer = null;
    
    if ($optimizer === null) {
        require_once __DIR__ . '/config/database.php';
        $optimizer = new MemoryOptimizer(get_db_connection());
    }
    
    return $optimizer;
}

/**
 * Quick memory check function
 */
function checkMemory($operation = 'operation', $required_mb = 50) {
    $optimizer = getMemoryOptimizer();
    $optimizer->checkMemoryAvailable($required_mb);
    $optimizer->logMemoryUsage($operation);
}

?> 