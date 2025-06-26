-- Database Performance Optimization Script
-- Run this to add indexes for frequently queried columns
-- Safe to run multiple times (uses IF NOT EXISTS where possible)

-- ==================================================
-- PERFORMANCE INDEXES
-- ==================================================

-- Index for spin_results table (user IP for rate limiting)
ALTER TABLE `spin_results` 
ADD INDEX IF NOT EXISTS `idx_spin_results_user_ip` (`user_ip`),
ADD INDEX IF NOT EXISTS `idx_spin_results_spin_time` (`spin_time`),
ADD INDEX IF NOT EXISTS `idx_spin_results_business_machine` (`business_id`, `machine_id`);

-- Index for rewards table
ALTER TABLE `rewards` 
ADD INDEX IF NOT EXISTS `idx_rewards_active` (`active`),
ADD INDEX IF NOT EXISTS `idx_rewards_rarity` (`rarity_level`),
ADD INDEX IF NOT EXISTS `idx_rewards_list_id` (`list_id`);

-- Index for users table
ALTER TABLE `users` 
ADD INDEX IF NOT EXISTS `idx_users_business_id` (`business_id`),
ADD INDEX IF NOT EXISTS `idx_users_role` (`role`),
ADD INDEX IF NOT EXISTS `idx_users_status` (`status`);

-- Index for businesses table
ALTER TABLE `businesses` 
ADD INDEX IF NOT EXISTS `idx_businesses_user_id` (`user_id`),
ADD INDEX IF NOT EXISTS `idx_businesses_status` (`status`),
ADD INDEX IF NOT EXISTS `idx_businesses_slug` (`slug`);

-- Index for campaigns table (if exists)
-- Check if table exists first
SET @table_exists = (SELECT COUNT(*) FROM information_schema.tables 
    WHERE table_schema = DATABASE() AND table_name = 'campaigns');

SET @sql = IF(@table_exists > 0,
    'ALTER TABLE `campaigns` 
     ADD INDEX IF NOT EXISTS `idx_campaigns_business_id` (`business_id`),
     ADD INDEX IF NOT EXISTS `idx_campaigns_status` (`status`),
     ADD INDEX IF NOT EXISTS `idx_campaigns_created_at` (`created_at`)',
    'SELECT "campaigns table not found, skipping indexes" as message');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Index for promotions table (if exists)
SET @table_exists = (SELECT COUNT(*) FROM information_schema.tables 
    WHERE table_schema = DATABASE() AND table_name = 'promotions');

SET @sql = IF(@table_exists > 0,
    'ALTER TABLE `promotions` 
     ADD INDEX IF NOT EXISTS `idx_promotions_business_id` (`business_id`),
     ADD INDEX IF NOT EXISTS `idx_promotions_status` (`status`),
     ADD INDEX IF NOT EXISTS `idx_promotions_promo_code` (`promo_code`),
     ADD INDEX IF NOT EXISTS `idx_promotions_created_at` (`created_at`)',
    'SELECT "promotions table not found, skipping indexes" as message');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Index for voting_lists table (if exists)
SET @table_exists = (SELECT COUNT(*) FROM information_schema.tables 
    WHERE table_schema = DATABASE() AND table_name = 'voting_lists');

SET @sql = IF(@table_exists > 0,
    'ALTER TABLE `voting_lists` 
     ADD INDEX IF NOT EXISTS `idx_voting_lists_business_id` (`business_id`),
     ADD INDEX IF NOT EXISTS `idx_voting_lists_status` (`status`),
     ADD INDEX IF NOT EXISTS `idx_voting_lists_created_at` (`created_at`),
     ADD INDEX IF NOT EXISTS `idx_voting_lists_spin_enabled` (`spin_enabled`)',
    'SELECT "voting_lists table not found, skipping indexes" as message');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Index for voting_list_items table (if exists)
SET @table_exists = (SELECT COUNT(*) FROM information_schema.tables 
    WHERE table_schema = DATABASE() AND table_name = 'voting_list_items');

SET @sql = IF(@table_exists > 0,
    'ALTER TABLE `voting_list_items` 
     ADD INDEX IF NOT EXISTS `idx_voting_list_items_list_id` (`voting_list_id`),
     ADD INDEX IF NOT EXISTS `idx_voting_list_items_master_id` (`master_item_id`),
     ADD INDEX IF NOT EXISTS `idx_voting_list_items_vote_count` (`vote_count`)',
    'SELECT "voting_list_items table not found, skipping indexes" as message');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Index for machines table (if exists)
SET @table_exists = (SELECT COUNT(*) FROM information_schema.tables 
    WHERE table_schema = DATABASE() AND table_name = 'machines');

SET @sql = IF(@table_exists > 0,
    'ALTER TABLE `machines` 
     ADD INDEX IF NOT EXISTS `idx_machines_business_id` (`business_id`),
     ADD INDEX IF NOT EXISTS `idx_machines_status` (`status`),
     ADD INDEX IF NOT EXISTS `idx_machines_location` (`location`),
     ADD INDEX IF NOT EXISTS `idx_machines_created_at` (`created_at`)',
    'SELECT "machines table not found, skipping indexes" as message');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Index for master_items table (if exists)
SET @table_exists = (SELECT COUNT(*) FROM information_schema.tables 
    WHERE table_schema = DATABASE() AND table_name = 'master_items');

SET @sql = IF(@table_exists > 0,
    'ALTER TABLE `master_items` 
     ADD INDEX IF NOT EXISTS `idx_master_items_category` (`category`),
     ADD INDEX IF NOT EXISTS `idx_master_items_name` (`name`),
     ADD INDEX IF NOT EXISTS `idx_master_items_price` (`price`)',
    'SELECT "master_items table not found, skipping indexes" as message');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Index for system_settings table
ALTER TABLE `system_settings` 
ADD INDEX IF NOT EXISTS `idx_system_settings_key` (`setting_key`);

-- ==================================================
-- MYSQL CONFIGURATION OPTIMIZATIONS
-- ==================================================

-- Enable query cache (if not already enabled globally)
-- Note: These are session-level settings. For permanent changes, 
-- modify my.cnf or my.ini file on the server

-- Query cache settings (session level)
SET SESSION query_cache_type = ON;
SET SESSION query_cache_limit = 2097152; -- 2MB max result size
SET SESSION tmp_table_size = 67108864; -- 64MB
SET SESSION max_heap_table_size = 67108864; -- 64MB

-- ==================================================
-- PERFORMANCE MONITORING QUERIES
-- ==================================================

-- Check index usage (run after optimization)
SELECT 
    table_name,
    index_name,
    column_name,
    cardinality
FROM information_schema.statistics 
WHERE table_schema = DATABASE()
AND table_name IN ('rewards', 'spin_results', 'users', 'businesses', 'campaigns', 'promotions')
ORDER BY table_name, index_name, seq_in_index;

-- Check for missing indexes on foreign keys
SELECT 
    table_name,
    column_name,
    constraint_name,
    referenced_table_name,
    referenced_column_name
FROM information_schema.key_column_usage
WHERE referenced_table_schema = DATABASE()
AND table_name NOT IN (
    SELECT DISTINCT table_name 
    FROM information_schema.statistics 
    WHERE table_schema = DATABASE()
    AND column_name = key_column_usage.column_name
);

-- Show table sizes and row counts
SELECT 
    table_name,
    table_rows,
    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'Size (MB)',
    ROUND((index_length / 1024 / 1024), 2) AS 'Index Size (MB)'
FROM information_schema.tables 
WHERE table_schema = DATABASE()
ORDER BY (data_length + index_length) DESC;

SELECT 'Database optimization complete!' as status; 