-- Create audit log table for tracking item changes
CREATE TABLE IF NOT EXISTS `item_audit_log` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `item_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `business_id` INT NOT NULL,
  `action` ENUM('create', 'update', 'delete') NOT NULL,
  `old_values` JSON NULL,
  `new_values` JSON NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_audit_item` (`item_id`),
  INDEX `idx_audit_user` (`user_id`),
  INDEX `idx_audit_business` (`business_id`),
  INDEX `idx_audit_created` (`created_at`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`business_id`) REFERENCES `businesses`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add updated_at column to master_items if it doesn't exist
SET @sql = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
   WHERE TABLE_SCHEMA = DATABASE() 
   AND TABLE_NAME = 'master_items' 
   AND COLUMN_NAME = 'updated_at') = 0,
  'ALTER TABLE master_items ADD COLUMN updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP',
  'SELECT "Column updated_at already exists"'
));

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Create index on updated_at for better performance
SET @sql = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
   WHERE TABLE_SCHEMA = DATABASE() 
   AND TABLE_NAME = 'master_items' 
   AND INDEX_NAME = 'idx_master_items_updated') = 0,
  'CREATE INDEX idx_master_items_updated ON master_items(updated_at)',
  'SELECT "Index idx_master_items_updated already exists"'
));

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt; 