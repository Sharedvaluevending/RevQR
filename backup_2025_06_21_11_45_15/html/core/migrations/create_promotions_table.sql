-- Create promotions table
CREATE TABLE IF NOT EXISTS `promotions` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `business_id` int(11) NOT NULL,
    `list_id` int(11) NOT NULL,
    `item_id` int(11) NOT NULL,
    `discount_type` enum('percentage','fixed') NOT NULL,
    `discount_value` decimal(10,2) NOT NULL,
    `start_date` date NOT NULL,
    `end_date` date NOT NULL,
    `promo_code` varchar(20) NOT NULL,
    `description` text DEFAULT NULL,
    `manual_pricing` boolean DEFAULT FALSE,
    `display_message` varchar(255) DEFAULT NULL,
    `status` enum('active','inactive','expired') NOT NULL DEFAULT 'active',
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_promo_code` (`promo_code`),
    KEY `idx_promotions_business` (`business_id`),
    KEY `idx_promotions_list` (`list_id`),
    KEY `idx_promotions_item` (`item_id`),
    KEY `idx_promotions_dates` (`start_date`, `end_date`),
    KEY `idx_promotions_status` (`status`),
    CONSTRAINT `promotions_business_fk` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE,
    CONSTRAINT `promotions_list_fk` FOREIGN KEY (`list_id`) REFERENCES `voting_lists` (`id`) ON DELETE CASCADE,
    CONSTRAINT `promotions_item_fk` FOREIGN KEY (`item_id`) REFERENCES `voting_list_items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create promotion redemptions table
CREATE TABLE IF NOT EXISTS `promotion_redemptions` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `promotion_id` int(11) NOT NULL,
    `business_id` int(11) NOT NULL,
    `user_ip` varchar(45) NOT NULL,
    `discount_amount` decimal(10,2) NOT NULL,
    `redeemed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_ip_promotion` (`promotion_id`, `user_ip`),
    KEY `idx_redemptions_promotion` (`promotion_id`),
    KEY `idx_redemptions_business` (`business_id`),
    KEY `idx_redemptions_ip` (`user_ip`),
    KEY `idx_redemptions_date` (`redeemed_at`),
    CONSTRAINT `redemptions_promotion_fk` FOREIGN KEY (`promotion_id`) REFERENCES `promotions` (`id`) ON DELETE CASCADE,
    CONSTRAINT `redemptions_business_fk` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci; 