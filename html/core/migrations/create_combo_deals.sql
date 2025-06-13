-- Create combo deals table
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

-- Create combo deal items table (many-to-many relationship)
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

-- Create combo deal redemptions table
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