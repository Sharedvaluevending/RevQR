-- Update schema for RevenueQR platform
-- This file contains the updated schema with campaign and list relationships

-- Disable foreign key checks for clean deploy
SET FOREIGN_KEY_CHECKS = 0;

-- Create new lists table (renamed from machines)
CREATE TABLE IF NOT EXISTS `lists` (
  `id`            INT AUTO_INCREMENT PRIMARY KEY,
  `business_id`   INT             NOT NULL,
  `name`          VARCHAR(255)    NOT NULL,
  `description`   TEXT            NULL,
  `status`        ENUM('active','inactive') 
                   NOT NULL DEFAULT 'active',
  `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_lists_business` (`business_id`),
  FOREIGN KEY (`business_id`)
    REFERENCES `businesses`(`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- Create new machines table for physical locations
CREATE TABLE IF NOT EXISTS `machines` (
  `id`            INT AUTO_INCREMENT PRIMARY KEY,
  `business_id`   INT             NOT NULL,
  `name`          VARCHAR(255)    NOT NULL,
  `location`      VARCHAR(255)    NULL,
  `description`   TEXT            NULL,
  `status`        ENUM('active','inactive','maintenance') 
                   NOT NULL DEFAULT 'active',
  `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_machines_business` (`business_id`),
  FOREIGN KEY (`business_id`)
    REFERENCES `businesses`(`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- Create campaigns table
CREATE TABLE IF NOT EXISTS `campaigns` (
  `id`            INT AUTO_INCREMENT PRIMARY KEY,
  `business_id`   INT             NOT NULL,
  `name`          VARCHAR(255)    NOT NULL,
  `description`   TEXT            NULL,
  `start_date`    DATE            NULL,
  `end_date`      DATE            NULL,
  `status`        ENUM('draft','active','ended') 
                   NOT NULL DEFAULT 'draft',
  `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_campaigns_business` (`business_id`),
  FOREIGN KEY (`business_id`)
    REFERENCES `businesses`(`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- Create campaign_lists table (many-to-many relationship)
CREATE TABLE IF NOT EXISTS `campaign_lists` (
  `campaign_id`   INT             NOT NULL,
  `list_id`       INT             NOT NULL,
  `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`campaign_id`, `list_id`),
  FOREIGN KEY (`campaign_id`)
    REFERENCES `campaigns`(`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  FOREIGN KEY (`list_id`)
    REFERENCES `lists`(`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- Update items table to reference lists instead of machines
-- Drop the old foreign key (ignore error if it doesn't exist)
ALTER TABLE `items` DROP FOREIGN KEY `items_ibfk_1`;
-- Add the new foreign key
ALTER TABLE `items` ADD CONSTRAINT `items_ibfk_1` FOREIGN KEY (`list_id`) REFERENCES `lists`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- Update qr_codes table to reference campaigns and machines
-- Drop the old foreign key (ignore error if it doesn't exist)
ALTER TABLE `qr_codes` DROP FOREIGN KEY `qr_codes_ibfk_1`;
-- Add new columns
ALTER TABLE `qr_codes` ADD COLUMN `campaign_id` INT NULL AFTER `id`;
ALTER TABLE `qr_codes` ADD COLUMN `machine_id` INT NULL AFTER `campaign_id`;
-- Add new foreign keys
ALTER TABLE `qr_codes` ADD CONSTRAINT `qr_codes_ibfk_1` FOREIGN KEY (`campaign_id`) REFERENCES `campaigns`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `qr_codes` ADD CONSTRAINT `qr_codes_ibfk_2` FOREIGN KEY (`machine_id`) REFERENCES `machines`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1; 