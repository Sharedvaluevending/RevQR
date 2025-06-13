-- Add manual pricing support to existing promotions table
ALTER TABLE promotions 
ADD COLUMN manual_pricing BOOLEAN DEFAULT FALSE,
ADD COLUMN display_message VARCHAR(255) DEFAULT NULL;

-- Update existing promotions to ensure they work with new system
UPDATE promotions SET manual_pricing = FALSE WHERE manual_pricing IS NULL;

-- Add indexes for better performance
CREATE INDEX idx_promotions_manual_pricing ON promotions(manual_pricing);
CREATE INDEX idx_promotions_display_message ON promotions(display_message);

-- Create combo deals tables (if they don't exist)
CREATE TABLE IF NOT EXISTS `combo_deals` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `business_id` int(11) NOT NULL,
    `name` varchar(255) NOT NULL,
    `description` text DEFAULT NULL,
    `combo_type` enum('fixed_price','percentage_off','buy_x_get_y') NOT NULL DEFAULT 'fixed_price',
    `combo_price` decimal(10,2) DEFAULT NULL,
    `discount_percentage` decimal(5,2) DEFAULT NULL,
    `buy_quantity` int DEFAULT NULL,
    `get_quantity` int DEFAULT NULL,
    `start_date` date NOT NULL,
    `end_date` date NOT NULL,
    `promo_code` varchar(20) NOT NULL,
    `min_items` int DEFAULT 2,
    `max_items` int DEFAULT 10,
    `manual_pricing` boolean DEFAULT FALSE,
    `display_message` varchar(255) DEFAULT NULL,
    `status` enum('active','inactive','expired') NOT NULL DEFAULT 'active',
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_combo_promo_code` (`promo_code`),
    KEY `idx_combo_business` (`business_id`),
    KEY `idx_combo_dates` (`start_date`, `end_date`),
    KEY `idx_combo_status` (`status`),
    CONSTRAINT `combo_deals_business_fk` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create combo deal items table (if it doesn't exist)
CREATE TABLE IF NOT EXISTS `combo_deal_items` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `combo_id` int(11) NOT NULL,
    `item_id` int(11) NOT NULL,
    `quantity` int NOT NULL DEFAULT 1,
    `is_required` boolean DEFAULT TRUE,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_combo_item` (`combo_id`, `item_id`),
    KEY `idx_combo_items_combo` (`combo_id`),
    KEY `idx_combo_items_item` (`item_id`),
    CONSTRAINT `combo_items_combo_fk` FOREIGN KEY (`combo_id`) REFERENCES `combo_deals` (`id`) ON DELETE CASCADE,
    CONSTRAINT `combo_items_item_fk` FOREIGN KEY (`item_id`) REFERENCES `voting_list_items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create combo deal redemptions table (if it doesn't exist)
CREATE TABLE IF NOT EXISTS `combo_deal_redemptions` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `combo_id` int(11) NOT NULL,
    `business_id` int(11) NOT NULL,
    `user_ip` varchar(45) NOT NULL,
    `total_savings` decimal(10,2) NOT NULL,
    `items_purchased` text NOT NULL, -- JSON array of items
    `redeemed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `combo_redemptions_combo` (`combo_id`),
    KEY `combo_redemptions_business` (`business_id`),
    KEY `combo_redemptions_ip` (`user_ip`),
    CONSTRAINT `combo_redemptions_combo_fk` FOREIGN KEY (`combo_id`) REFERENCES `combo_deals` (`id`) ON DELETE CASCADE,
    CONSTRAINT `combo_redemptions_business_fk` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add settings for new features
INSERT INTO `system_settings` (`setting_key`, `value`, `description`, `updated_at`) VALUES
('max_items_per_promotion', '10', 'Maximum number of items per multi-item promotion', NOW()),
('max_combo_deals_per_business', '5', 'Maximum number of active combo deals per business', NOW()),
('combo_deal_min_savings', '0.50', 'Minimum savings required for combo deals', NOW()),
('manual_pricing_enabled', 'true', 'Allow manual pricing for promotions', NOW())
ON DUPLICATE KEY UPDATE 
    `updated_at` = NOW(); 