<?php
/**
 * Advanced Query Optimizer for Memory-Efficient Database Operations
 * Addresses performance bottlenecks identified in the codebase
 */

class QueryOptimizer {
    private $pdo;
    private $memory_limit;
    private $query_log = [];
    
    public function __construct($pdo, $memory_limit_mb = 50) {
        $this->pdo = $pdo;
        $this->memory_limit = $memory_limit_mb * 1024 * 1024; // Convert to bytes
    }
    
    /**
     * Execute query with memory monitoring and optimization
     */
    public function executeOptimized($query, $params = [], $options = []) {
        $start_memory = memory_get_usage(true);
        $start_time = microtime(true);
        
        // Automatically optimize query structure
        $optimized_query = $this->optimizeQueryStructure($query, $options);
        
        try {
            $stmt = $this->pdo->prepare($optimized_query);
            $stmt->execute($params);
            
            $execution_time = microtime(true) - $start_time;
            $memory_used = memory_get_usage(true) - $start_memory;
            
            // Log performance metrics
            $this->logQuery([
                'query' => substr($optimized_query, 0, 200),
                'execution_time' => $execution_time,
                'memory_used' => $memory_used,
                'optimized' => $query !== $optimized_query
            ]);
            
            return $stmt;
            
        } catch (Exception $e) {
            error_log("Query Optimization Error: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Fetch results with pagination to prevent memory overload
     */
    public function fetchPaginated($base_query, $params = [], $page = 1, $per_page = 100, $max_per_page = 1000) {
        // Enforce memory-safe limits
        $per_page = min($per_page, $max_per_page);
        $offset = ($page - 1) * $per_page;
        
        // Get total count efficiently
        $count_query = $this->buildCountQuery($base_query);
        $count_stmt = $this->executeOptimized($count_query, $params);
        $total_count = $count_stmt->fetchColumn();
        
        // Get paginated results
        $paginated_query = "$base_query LIMIT $per_page OFFSET $offset";
        $stmt = $this->executeOptimized($paginated_query, $params);
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
     * Stream large result sets to prevent memory issues
     */
    public function streamResults($query, $params = [], $chunk_size = 1000) {
        $offset = 0;
        $has_more = true;
        
        while ($has_more) {
            $chunked_query = "$query LIMIT $chunk_size OFFSET $offset";
            $stmt = $this->executeOptimized($chunked_query, $params);
            
            $chunk = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $has_more = count($chunk) === $chunk_size;
            
            if (!empty($chunk)) {
                yield $chunk;
            }
            
            $offset += $chunk_size;
            
            // Memory cleanup
            unset($chunk);
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
        }
    }
    
    /**
     * Optimize complex JOIN queries identified in the codebase
     */
    public function executeComplexJoin($base_table, $joins, $conditions = [], $options = []) {
        // Horse Racing Style Complex Query Optimization
        // Based on get-jockey-assignments.php patterns
        
        $query_parts = ["SELECT"];
        
        // Build optimized SELECT clause
        if (isset($options['select'])) {
            $query_parts[] = $options['select'];
        } else {
            $query_parts[] = "$base_table.*";
        }
        
        $query_parts[] = "FROM $base_table";
        
        // Add JOINs with optimization hints
        foreach ($joins as $join) {
            $join_type = $join['type'] ?? 'LEFT JOIN';
            $table = $join['table'];
            $condition = $join['on'];
            
            // Add index hints for complex joins
            if (isset($join['use_index'])) {
                $table .= " USE INDEX ({$join['use_index']})";
            }
            
            $query_parts[] = "$join_type $table ON $condition";
        }
        
        // Add WHERE conditions
        if (!empty($conditions)) {
            $query_parts[] = "WHERE " . implode(' AND ', $conditions);
        }
        
        // Add optimization clauses
        if (isset($options['group_by'])) {
            $query_parts[] = "GROUP BY " . $options['group_by'];
        }
        
        if (isset($options['order_by'])) {
            $query_parts[] = "ORDER BY " . $options['order_by'];
        }
        
        // Always add LIMIT for safety
        $limit = $options['limit'] ?? 1000;
        $query_parts[] = "LIMIT $limit";
        
        $final_query = implode(' ', $query_parts);
        return $this->executeOptimized($final_query, $options['params'] ?? []);
    }
    
    /**
     * Optimized aggregation queries for analytics
     */
    public function executeAggregation($table, $aggregations, $conditions = [], $group_by = null) {
        $select_parts = [];
        
        foreach ($aggregations as $alias => $expression) {
            $select_parts[] = "$expression as $alias";
        }
        
        $query = "SELECT " . implode(', ', $select_parts) . " FROM $table";
        
        if (!empty($conditions)) {
            $query .= " WHERE " . implode(' AND ', $conditions);
        }
        
        if ($group_by) {
            $query .= " GROUP BY $group_by";
        }
        
        return $this->executeOptimized($query);
    }
    
    /**
     * Leaderboard optimization for user analytics
     */
    public function getOptimizedLeaderboard($business_id = null, $limit = 100) {
        // Optimized version of leaderboard query from html/user/leaderboard.php
        
        $base_query = "
            SELECT 
                u.id,
                u.username,
                COALESCE(uas.total_votes, 0) as total_votes,
                COALESCE(uas.votes_in, 0) as votes_in,
                COALESCE(uas.votes_out, 0) as votes_out,
                COALESCE(uas.total_spins, 0) as total_spins,
                COALESCE(uas.big_wins, 0) as big_wins,
                COALESCE(uas.total_prize_points, 0) as total_prize_points,
                uas.last_activity
            FROM users u
            LEFT JOIN user_activity_summary uas ON u.id = uas.user_id
            WHERE uas.total_votes > 0 OR uas.total_spins > 0
        ";
        
        if ($business_id) {
            $base_query .= " AND u.business_id = ?";
            $params = [$business_id];
        } else {
            $params = [];
        }
        
        $base_query .= " ORDER BY uas.total_prize_points DESC, uas.total_votes DESC LIMIT ?";
        $params[] = $limit;
        
        return $this->executeOptimized($base_query, $params);
    }
    
    /**
     * Business analytics optimization
     */
    public function getBusinessAnalytics($business_id, $days = 30) {
        // Use cached performance data when available
        $cache_query = "
            SELECT 
                machine_count,
                total_items,
                linked_master_items,
                total_revenue,
                total_sales,
                last_updated
            FROM business_performance_cache 
            WHERE business_id = ?
            AND last_updated >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ";
        
        $stmt = $this->executeOptimized($cache_query, [$business_id]);
        $cached = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($cached) {
            return $cached;
        }
        
        // Fallback to real-time calculation with optimization
        return $this->calculateBusinessAnalytics($business_id, $days);
    }
    
    /**
     * Calculate business analytics efficiently
     */
    private function calculateBusinessAnalytics($business_id, $days) {
        $joins = [
            [
                'type' => 'LEFT JOIN',
                'table' => 'voting_list_items vli',
                'on' => 'vl.id = vli.voting_list_id'
            ],
            [
                'type' => 'LEFT JOIN', 
                'table' => 'sales s',
                'on' => 'vli.id = s.item_id AND s.sale_time >= DATE_SUB(NOW(), INTERVAL ? DAY)',
                'use_index' => 'idx_sales_item_time_qty'
            ]
        ];
        
        $options = [
            'select' => '
                COUNT(DISTINCT vl.id) as machine_count,
                COUNT(DISTINCT vli.id) as total_items,
                COUNT(DISTINCT vli.master_item_id) as linked_master_items,
                COALESCE(SUM(s.sale_price * s.quantity), 0) as total_revenue,
                COALESCE(COUNT(s.id), 0) as total_sales
            ',
            'params' => [$days, $business_id],
            'limit' => 1
        ];
        
        $stmt = $this->executeComplexJoin('voting_lists vl', $joins, ['vl.business_id = ?'], $options);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Automatically optimize query structure
     */
    private function optimizeQueryStructure($query, $options = []) {
        // Add LIMIT if missing and not explicitly disabled
        if (!isset($options['no_limit']) && stripos($query, 'LIMIT') === false) {
            $default_limit = $options['default_limit'] ?? 1000;
            $query .= " LIMIT $default_limit";
        }
        
        // Add index hints for known performance patterns
        $query = $this->addIndexHints($query);
        
        return $query;
    }
    
    /**
     * Add index hints for common query patterns
     */
    private function addIndexHints($query) {
        // Add hints for voting_list_items JOINs
        if (strpos($query, 'voting_list_items') !== false && strpos($query, 'JOIN') !== false) {
            $query = str_replace(
                'voting_list_items vli',
                'voting_list_items vli USE INDEX (idx_vli_comprehensive)',
                $query
            );
        }
        
        // Add hints for sales time-based queries
        if (strpos($query, 'sales') !== false && strpos($query, 'sale_time') !== false) {
            $query = str_replace(
                'sales s',
                'sales s USE INDEX (idx_sales_time_performance)',
                $query
            );
        }
        
        return $query;
    }
    
    /**
     * Build count query from base query
     */
    private function buildCountQuery($base_query) {
        // Extract FROM and WHERE clauses
        $from_pos = stripos($base_query, 'FROM');
        $order_pos = stripos($base_query, 'ORDER BY');
        $limit_pos = stripos($base_query, 'LIMIT');
        
        $end_pos = false;
        if ($order_pos !== false) $end_pos = $order_pos;
        if ($limit_pos !== false && ($end_pos === false || $limit_pos < $end_pos)) $end_pos = $limit_pos;
        
        if ($end_pos !== false) {
            $from_clause = substr($base_query, $from_pos, $end_pos - $from_pos);
        } else {
            $from_clause = substr($base_query, $from_pos);
        }
        
        return "SELECT COUNT(*) " . trim($from_clause);
    }
    
    /**
     * Log query performance
     */
    private function logQuery($metrics) {
        $this->query_log[] = $metrics;
        
        // Log slow queries
        if ($metrics['execution_time'] > 1.0) {
            error_log("Slow Query ({$metrics['execution_time']}s): " . $metrics['query']);
        }
        
        // Log high memory usage
        if ($metrics['memory_used'] > $this->memory_limit) {
            error_log("High Memory Query: " . $this->formatBytes($metrics['memory_used']) . " - " . $metrics['query']);
        }
    }
    
    /**
     * Get performance report
     */
    public function getPerformanceReport() {
        $total_queries = count($this->query_log);
        $slow_queries = array_filter($this->query_log, function($q) { return $q['execution_time'] > 1.0; });
        $high_memory_queries = array_filter($this->query_log, function($q) { return $q['memory_used'] > $this->memory_limit; });
        
        return [
            'total_queries' => $total_queries,
            'slow_queries' => count($slow_queries),
            'high_memory_queries' => count($high_memory_queries),
            'avg_execution_time' => $total_queries > 0 ? array_sum(array_column($this->query_log, 'execution_time')) / $total_queries : 0,
            'total_memory_used' => array_sum(array_column($this->query_log, 'memory_used')),
            'optimized_queries' => count(array_filter($this->query_log, function($q) { return $q['optimized']; }))
        ];
    }
    
    /**
     * Format bytes for human reading
     */
    private function formatBytes($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes > 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
}

// Global helper function for easy access
function getQueryOptimizer() {
    global $pdo;
    static $optimizer = null;
    
    if ($optimizer === null) {
        $optimizer = new QueryOptimizer($pdo);
    }
    
    return $optimizer;
}
?> 