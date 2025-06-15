-- Advanced Query Optimization Script
-- Addresses specific performance bottlenecks identified in the codebase
-- Safe to run multiple times with IF NOT EXISTS checks

-- ==================================================
-- CRITICAL PERFORMANCE INDEXES FOR COMPLEX QUERIES
-- ==================================================

-- Horse Racing Complex Queries Optimization
-- These indexes support the complex queries in horse-racing/get-jockey-assignments.php
ALTER TABLE voting_list_items
ADD INDEX IF NOT EXISTS idx_vli_comprehensive (voting_list_id, id, item_category),
ADD INDEX IF NOT EXISTS idx_vli_master_item_lookup (master_item_id, id),
ADD INDEX IF NOT EXISTS idx_vli_category_lookup (item_category);

-- Sales data aggregation optimization (lines 48-56 in get-jockey-assignments.php)
ALTER TABLE sales
ADD INDEX IF NOT EXISTS idx_sales_time_performance (sale_time, item_id, quantity, sale_price),
ADD INDEX IF NOT EXISTS idx_sales_item_time_qty (item_id, sale_time, quantity),
ADD INDEX IF NOT EXISTS idx_sales_24h_7d (sale_time, item_id);

-- Nayax transactions optimization for complex subqueries
ALTER TABLE nayax_transactions
ADD INDEX IF NOT EXISTS idx_nayax_machine_time (nayax_machine_id, created_at, amount_cents),
ADD INDEX IF NOT EXISTS idx_nayax_status_time (status, created_at);

ALTER TABLE nayax_machines
ADD INDEX IF NOT EXISTS idx_nayax_platform_mapping (platform_machine_id, nayax_machine_id);

-- Horse performance cache optimization
ALTER TABLE horse_performance_cache
ADD INDEX IF NOT EXISTS idx_hpc_item_date (item_id, cache_date, performance_score);

-- Item jockey assignments optimization
ALTER TABLE item_jockey_assignments
ADD INDEX IF NOT EXISTS idx_ija_business_item (business_id, item_id);

-- ==================================================
-- LEADERBOARD AND ANALYTICS OPTIMIZATION  
-- ==================================================

-- User leaderboard complex query optimization (html/user/leaderboard.php lines 125-172)
ALTER TABLE votes
ADD INDEX IF NOT EXISTS idx_votes_user_performance (user_id, vote_type, created_at),
ADD INDEX IF NOT EXISTS idx_votes_user_stats (user_id, vote_type),
ADD INDEX IF NOT EXISTS idx_votes_daily_analysis (user_id, created_at);

ALTER TABLE spin_results
ADD INDEX IF NOT EXISTS idx_spin_user_performance (user_id, is_big_win, prize_won, spin_time),
ADD INDEX IF NOT EXISTS idx_spin_user_stats (user_id, spin_time),
ADD INDEX IF NOT EXISTS idx_spin_daily_stats (user_id, spin_time);

-- ==================================================
-- BUSINESS ANALYTICS OPTIMIZATION
-- ==================================================

-- Master items with sales aggregation (html/business/master-items.php lines 42-91)
ALTER TABLE master_items
ADD INDEX IF NOT EXISTS idx_master_items_business_lookup (status, category, name(50)),
ADD INDEX IF NOT EXISTS idx_master_items_pricing (suggested_price, suggested_cost),
ADD INDEX IF NOT EXISTS idx_master_items_search (name(100), brand(50), category);

-- Item mapping optimization for master items queries
ALTER TABLE item_mapping
ADD INDEX IF NOT EXISTS idx_item_mapping_reverse (item_id, master_item_id);

-- Machine business relationship optimization
ALTER TABLE machines
ADD INDEX IF NOT EXISTS idx_machines_business_active (business_id, status, id);

-- ==================================================
-- AI ASSISTANT QUERY OPTIMIZATION
-- ==================================================

-- AI assistant analytics queries (html/core/ai_assistant.php lines 368-407)
ALTER TABLE voting_lists
ADD INDEX IF NOT EXISTS idx_voting_lists_business_spin (business_id, spin_enabled, id);

-- Vote aggregation optimization for AI insights
CREATE INDEX IF NOT EXISTS idx_votes_machine_item_analysis ON votes(machine_id, item_id, vote_type, created_at);

-- ==================================================
-- MEMORY-INTENSIVE QUERY OPTIMIZATION
-- ==================================================

-- Catalog queries optimization (my-catalog.php, get_catalog_items.php)
ALTER TABLE user_catalog_items
ADD INDEX IF NOT EXISTS idx_catalog_user_priority (user_id, priority_level, performance_rating),
ADD INDEX IF NOT EXISTS idx_catalog_user_search (user_id, custom_name(50));

ALTER TABLE catalog_item_tags
ADD INDEX IF NOT EXISTS idx_catalog_tags_item (catalog_item_id, tag_name);

-- ==================================================
-- QR SYSTEM PERFORMANCE OPTIMIZATION
-- ==================================================

-- QR codes comprehensive indexing for unified system
ALTER TABLE qr_codes
ADD INDEX IF NOT EXISTS idx_qr_type_business_target (type, business_id, target_id),
ADD INDEX IF NOT EXISTS idx_qr_url_lookup (qr_url(100)),
ADD INDEX IF NOT EXISTS idx_qr_business_type (business_id, type);

-- ==================================================
-- AGGREGATION QUERY OPTIMIZATION
-- ==================================================

-- Create materialized view alternatives using tables for heavy aggregations
-- Business performance summary table
CREATE TABLE IF NOT EXISTS business_performance_cache (
    business_id INT PRIMARY KEY,
    machine_count INT DEFAULT 0,
    total_items INT DEFAULT 0,
    linked_master_items INT DEFAULT 0,
    total_revenue DECIMAL(10,2) DEFAULT 0,
    total_sales INT DEFAULT 0,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_bpc_updated (last_updated)
);

-- Daily sales summary table for faster analytics
CREATE TABLE IF NOT EXISTS daily_sales_summary (
    id INT AUTO_INCREMENT PRIMARY KEY,
    business_id INT NOT NULL,
    item_id INT NOT NULL,
    sale_date DATE NOT NULL,
    total_quantity INT DEFAULT 0,
    total_revenue DECIMAL(10,2) DEFAULT 0,
    transaction_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_business_item_date (business_id, item_id, sale_date),
    INDEX idx_dss_business_date (business_id, sale_date),
    INDEX idx_dss_item_date (item_id, sale_date)
);

-- User activity summary table for leaderboard performance
CREATE TABLE IF NOT EXISTS user_activity_summary (
    user_id INT PRIMARY KEY,
    total_votes INT DEFAULT 0,
    votes_in INT DEFAULT 0,
    votes_out INT DEFAULT 0,
    total_spins INT DEFAULT 0,
    big_wins INT DEFAULT 0,
    real_wins INT DEFAULT 0,
    total_prize_points INT DEFAULT 0,
    last_activity TIMESTAMP NULL,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_uas_activity (last_activity),
    INDEX idx_uas_points (total_prize_points DESC)
);

-- ==================================================
-- QUERY OPTIMIZATION VIEWS
-- ==================================================

-- Optimized view for active machine items (replaces complex JOINs)
CREATE OR REPLACE VIEW v_active_machine_items_optimized AS
SELECT 
    vli.id,
    vli.item_name,
    vli.item_category,
    vli.retail_price,
    vli.inventory,
    vl.name as machine_name,
    vl.business_id,
    vli.master_item_id,
    mi.category as master_category,
    mi.status as master_status
FROM voting_list_items vli
JOIN voting_lists vl ON vli.voting_list_id = vl.id
LEFT JOIN master_items mi ON vli.master_item_id = mi.id
WHERE vl.status = 'active'
AND (mi.status IS NULL OR mi.status = 'active');

-- Business analytics view for dashboard performance
CREATE OR REPLACE VIEW v_business_analytics_fast AS
SELECT 
    b.id as business_id,
    b.business_name,
    COUNT(DISTINCT vl.id) as machine_count,
    COUNT(DISTINCT vli.id) as total_items,
    COUNT(DISTINCT vli.master_item_id) as linked_items,
    COALESCE(bpc.total_revenue, 0) as cached_revenue,
    COALESCE(bpc.total_sales, 0) as cached_sales
FROM businesses b
LEFT JOIN voting_lists vl ON b.id = vl.business_id
LEFT JOIN voting_list_items vli ON vl.id = vli.voting_list_id
LEFT JOIN business_performance_cache bpc ON b.id = bpc.business_id
GROUP BY b.id, b.business_name, bpc.total_revenue, bpc.total_sales;

-- ==================================================
-- STORED PROCEDURES FOR COMPLEX OPERATIONS
-- ==================================================

DELIMITER //

-- Procedure to update business performance cache
CREATE PROCEDURE IF NOT EXISTS UpdateBusinessPerformanceCache(IN business_id_param INT)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    
    START TRANSACTION;
    
    INSERT INTO business_performance_cache (
        business_id, machine_count, total_items, linked_master_items, total_revenue, total_sales
    )
    SELECT 
        vl.business_id,
        COUNT(DISTINCT vl.id) as machine_count,
        COUNT(DISTINCT vli.id) as total_items,
        COUNT(DISTINCT vli.master_item_id) as linked_master_items,
        COALESCE(SUM(s.sale_price * s.quantity), 0) as total_revenue,
        COALESCE(COUNT(s.id), 0) as total_sales
    FROM voting_lists vl
    LEFT JOIN voting_list_items vli ON vl.id = vli.voting_list_id
    LEFT JOIN sales s ON vli.id = s.item_id AND s.sale_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    WHERE vl.business_id = business_id_param
    GROUP BY vl.business_id
    ON DUPLICATE KEY UPDATE
        machine_count = VALUES(machine_count),
        total_items = VALUES(total_items),
        linked_master_items = VALUES(linked_master_items),
        total_revenue = VALUES(total_revenue),
        total_sales = VALUES(total_sales),
        last_updated = CURRENT_TIMESTAMP;
    
    COMMIT;
END //

-- Procedure to update user activity summary
CREATE PROCEDURE IF NOT EXISTS UpdateUserActivitySummary(IN user_id_param INT)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    
    START TRANSACTION;
    
    INSERT INTO user_activity_summary (user_id, total_votes, votes_in, votes_out, total_spins, big_wins, real_wins, total_prize_points, last_activity)
    SELECT 
        u.id,
        COALESCE(v_stats.total_votes, 0),
        COALESCE(v_stats.votes_in, 0),
        COALESCE(v_stats.votes_out, 0),
        COALESCE(s_stats.total_spins, 0),
        COALESCE(s_stats.big_wins, 0),
        COALESCE(s_stats.real_wins, 0),
        COALESCE(s_stats.total_prize_points, 0),
        GREATEST(COALESCE(v_stats.last_vote, '1970-01-01'), COALESCE(s_stats.last_spin, '1970-01-01'))
    FROM users u
    LEFT JOIN (
        SELECT 
            user_id,
            COUNT(*) as total_votes,
            COUNT(CASE WHEN vote_type IN ('in', 'vote_in') THEN 1 END) as votes_in,
            COUNT(CASE WHEN vote_type IN ('out', 'vote_out') THEN 1 END) as votes_out,
            MAX(created_at) as last_vote
        FROM votes 
        WHERE user_id = user_id_param
        GROUP BY user_id
    ) v_stats ON u.id = v_stats.user_id
    LEFT JOIN (
        SELECT 
            user_id,
            COUNT(*) as total_spins,
            COUNT(CASE WHEN is_big_win = 1 THEN 1 END) as big_wins,
            COUNT(CASE WHEN prize_won NOT IN ('No Prize', 'Lose All Votes', 'Try Again') THEN 1 END) as real_wins,
            COALESCE(SUM(prize_points), 0) as total_prize_points,
            MAX(spin_time) as last_spin
        FROM spin_results 
        WHERE user_id = user_id_param
        GROUP BY user_id
    ) s_stats ON u.id = s_stats.user_id
    WHERE u.id = user_id_param
    ON DUPLICATE KEY UPDATE
        total_votes = VALUES(total_votes),
        votes_in = VALUES(votes_in),
        votes_out = VALUES(votes_out),
        total_spins = VALUES(total_spins),
        big_wins = VALUES(big_wins),
        real_wins = VALUES(real_wins),
        total_prize_points = VALUES(total_prize_points),
        last_activity = VALUES(last_activity),
        last_updated = CURRENT_TIMESTAMP;
    
    COMMIT;
END //

DELIMITER ;

-- ==================================================
-- QUERY CACHE OPTIMIZATION (WHERE AVAILABLE)
-- ==================================================

-- Optimize session settings for better query performance
SET SESSION query_cache_type = 1;
SET SESSION tmp_table_size = 134217728; -- 128MB
SET SESSION max_heap_table_size = 134217728; -- 128MB
SET SESSION sort_buffer_size = 4194304; -- 4MB
SET SESSION read_buffer_size = 2097152; -- 2MB
SET SESSION read_rnd_buffer_size = 4194304; -- 4MB
SET SESSION join_buffer_size = 4194304; -- 4MB

-- ==================================================
-- PERFORMANCE MONITORING QUERIES
-- ==================================================

-- Query to identify slow query patterns
CREATE OR REPLACE VIEW v_performance_analysis AS
SELECT 
    'voting_list_items_joins' as query_type,
    COUNT(*) as total_records,
    'Complex JOINs with sales data' as description
FROM voting_list_items vli
JOIN voting_lists vl ON vli.voting_list_id = vl.id
UNION ALL
SELECT 
    'sales_aggregations' as query_type,
    COUNT(*) as total_records,
    'Sales data for time-based aggregations' as description
FROM sales s
WHERE s.sale_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)
UNION ALL
SELECT 
    'user_leaderboard_data' as query_type,
    COUNT(DISTINCT user_id) as total_records,
    'Active users for leaderboard calculations' as description
FROM votes v
WHERE v.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY);

-- Check for tables needing optimization
SELECT 
    table_name,
    table_rows,
    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'size_mb',
    ROUND((data_length / 1024 / 1024), 2) AS 'data_mb',
    ROUND((index_length / 1024 / 1024), 2) AS 'index_mb',
    ROUND((index_length / NULLIF(data_length, 0)) * 100, 2) AS 'index_ratio_percent'
FROM information_schema.tables 
WHERE table_schema = DATABASE()
AND table_rows > 1000
ORDER BY table_rows DESC;

-- Identify missing indexes on foreign key columns
SELECT 
    CONCAT(kcu.table_name, '.', kcu.column_name) as foreign_key,
    kcu.referenced_table_name,
    kcu.referenced_column_name,
    CASE 
        WHEN s.column_name IS NOT NULL THEN 'INDEXED'
        ELSE 'MISSING INDEX'
    END as index_status
FROM information_schema.key_column_usage kcu
LEFT JOIN information_schema.statistics s 
    ON kcu.table_schema = s.table_schema 
    AND kcu.table_name = s.table_name 
    AND kcu.column_name = s.column_name
WHERE kcu.table_schema = DATABASE()
AND kcu.referenced_table_name IS NOT NULL
ORDER BY 
    CASE WHEN s.column_name IS NULL THEN 0 ELSE 1 END,
    kcu.table_name;

SELECT '✅ Advanced Query Optimization Complete!' as status; 