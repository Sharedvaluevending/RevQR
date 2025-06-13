# Memory Optimization Plan (No Caching)

## Current Memory Issues Identified

### 1. Large Dataset Loading
- **Problem**: Multiple `fetchAll()` calls loading entire result sets into memory
- **Impact**: High memory usage for large tables (master_items, votes, sales)
- **Examples**: 
  - `html/business/master-items.php`: Complex joins with sales data
  - `html/user/vote.php`: Multiple analytics queries loading full datasets
  - Various admin pages loading complete table data

### 2. Inefficient Database Queries
- **Problem**: Complex joins without proper indexing and pagination
- **Impact**: Slow queries consuming memory while processing
- **Examples**:
  - Master items with sales aggregation (lines 42-75 in master-items.php)
  - Vote analytics with multiple JOINs (lines 40-90 in vote.php)

### 3. Unoptimized Array Processing
- **Problem**: Large arrays being processed in memory without streaming
- **Impact**: Memory spikes during data processing

## Memory Optimization Strategies

### 1. Database Query Optimization

#### A. Implement Cursor-Based Pagination
Replace `fetchAll()` with `fetch()` loops for large datasets:

```php
// Instead of:
$items = $stmt->fetchAll();

// Use:
while ($row = $stmt->fetch()) {
    // Process one row at a time
    processRow($row);
}
```

#### B. Limit Result Sets
Add strict LIMIT clauses to all queries:

```php
// Always limit results
$query .= " LIMIT 100"; // Never load more than needed
```

#### C. Use Streaming for Large Exports
Implement streaming for data exports instead of loading everything into memory.

### 2. Query Structure Optimization

#### A. Split Complex Queries
Break down complex multi-JOIN queries into smaller, focused queries:

```php
// Instead of one complex query with multiple JOINs
// Use separate queries and combine results efficiently
```

#### B. Use Subqueries for Aggregations
Replace JOINs with subqueries for better memory efficiency:

```php
SELECT 
    mi.*,
    (SELECT COUNT(*) FROM sales WHERE item_id = mi.id) as sales_count
FROM master_items mi
```

### 3. Memory-Efficient Data Processing

#### A. Implement Generator Functions
Use PHP generators for large data processing:

```php
function getItemsGenerator($pdo, $business_id) {
    $stmt = $pdo->prepare("SELECT * FROM items WHERE business_id = ? LIMIT 1000");
    $stmt->execute([$business_id]);
    
    while ($row = $stmt->fetch()) {
        yield $row;
    }
}
```

#### B. Process Data in Chunks
Break large operations into smaller chunks:

```php
$offset = 0;
$chunk_size = 100;

do {
    $items = getItemChunk($offset, $chunk_size);
    processChunk($items);
    $offset += $chunk_size;
} while (count($items) === $chunk_size);
```

### 4. Memory Monitoring and Limits

#### A. Set Memory Limits
Add memory monitoring to critical operations:

```php
$initial_memory = memory_get_usage();
// ... operation ...
$memory_used = memory_get_usage() - $initial_memory;

if ($memory_used > 50 * 1024 * 1024) { // 50MB
    error_log("High memory usage detected: " . formatBytes($memory_used));
}
```

#### B. Implement Memory Guards
Add memory checks before large operations:

```php
function checkMemoryAvailable($required_mb = 50) {
    $available = ini_get('memory_limit');
    $current = memory_get_usage(true);
    
    if (($current + ($required_mb * 1024 * 1024)) > $available) {
        throw new Exception("Insufficient memory for operation");
    }
}
```

## Specific File Optimizations

### 1. html/business/master-items.php
- **Issue**: Complex query with multiple JOINs loading all sales data
- **Solution**: 
  - Split into separate queries for basic items and sales data
  - Use pagination for sales data
  - Implement lazy loading for detailed analytics

### 2. html/user/vote.php
- **Issue**: Multiple `fetchAll()` calls for analytics
- **Solution**:
  - Combine related queries
  - Use LIMIT on all analytics queries
  - Implement progressive loading for detailed stats

### 3. Admin Dashboard Pages
- **Issue**: Loading complete system statistics
- **Solution**:
  - Use summary tables for dashboard metrics
  - Implement background processing for heavy analytics
  - Cache-free optimization using pre-calculated summaries

## Database Optimizations

### 1. Index Optimization
Add missing indexes for memory-efficient queries:

```sql
-- Critical indexes for memory optimization
CREATE INDEX idx_votes_user_created ON votes(user_id, created_at);
CREATE INDEX idx_sales_business_date ON sales(business_id, sale_time);
CREATE INDEX idx_items_machine_status ON items(machine_id, status);
```

### 2. Query Optimization
Optimize existing slow queries:

```sql
-- Use covering indexes to avoid table lookups
CREATE INDEX idx_master_items_covering ON master_items(id, name, category, suggested_price, status);
```

### 3. Table Structure Optimization
Consider table partitioning for large tables:

```sql
-- Partition large tables by date
ALTER TABLE votes PARTITION BY RANGE (YEAR(created_at));
```

## Implementation Priority

### Phase 1: Critical Memory Fixes (Immediate)
1. Add LIMIT clauses to all unbounded queries
2. Replace `fetchAll()` with `fetch()` loops in high-traffic pages
3. Implement memory monitoring on critical operations

### Phase 2: Query Optimization (Week 1)
1. Split complex queries in master-items.php
2. Optimize vote analytics queries
3. Add missing database indexes

### Phase 3: Advanced Optimizations (Week 2)
1. Implement generator functions for large datasets
2. Add memory guards to prevent OOM errors
3. Optimize admin dashboard queries

### Phase 4: Monitoring and Tuning (Ongoing)
1. Add memory usage logging
2. Monitor query performance
3. Optimize based on real-world usage patterns

## Expected Results

### Memory Usage Reduction
- **Current**: Potential 100MB+ memory usage for large operations
- **Target**: <50MB for any single operation
- **Method**: Streaming and chunked processing

### Performance Improvement
- **Query Speed**: 50-80% faster for complex queries
- **Page Load**: 30-50% faster for data-heavy pages
- **Scalability**: Support 10x more concurrent users

### System Stability
- **OOM Errors**: Eliminate out-of-memory crashes
- **Resource Usage**: More predictable memory consumption
- **Scalability**: Better handling of growing datasets

## Monitoring and Maintenance

### 1. Memory Usage Tracking
```php
// Add to critical operations
function logMemoryUsage($operation) {
    $usage = memory_get_usage(true);
    $peak = memory_get_peak_usage(true);
    error_log("Memory - $operation: Current=" . formatBytes($usage) . " Peak=" . formatBytes($peak));
}
```

### 2. Query Performance Monitoring
```php
// Add to database operations
function logSlowQuery($query, $execution_time) {
    if ($execution_time > 1.0) { // 1 second threshold
        error_log("Slow Query ({$execution_time}s): " . substr($query, 0, 100));
    }
}
```

### 3. Regular Optimization Reviews
- Weekly review of memory usage logs
- Monthly query performance analysis
- Quarterly optimization strategy updates

This plan provides immediate memory relief while establishing long-term optimization practices without relying on caching mechanisms. 