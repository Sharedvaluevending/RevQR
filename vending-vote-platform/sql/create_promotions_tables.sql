-- Create promotions table
CREATE TABLE IF NOT EXISTS `promotions` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `business_id` int(11) NOT NULL,
    `item_id` int(11) NOT NULL,
    `discount_type` enum('percentage','fixed') NOT NULL,
    `discount_value` decimal(10,2) NOT NULL,
    `start_date` date NOT NULL,
    `end_date` date NOT NULL,
    `promo_code` varchar(20) NOT NULL,
    `status` enum('active','inactive','expired') NOT NULL DEFAULT 'active',
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `business_id` (`business_id`),
    KEY `item_id` (`item_id`),
    KEY `promo_code` (`promo_code`),
    CONSTRAINT `promotions_business_id_fk` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE,
    CONSTRAINT `promotions_item_id_fk` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create promotion redemptions table
CREATE TABLE IF NOT EXISTS `promotion_redemptions` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `promotion_id` int(11) NOT NULL,
    `business_id` int(11) NOT NULL,
    `user_ip` varchar(45) NOT NULL,
    `discount_value` decimal(10,2) NOT NULL,
    `redeemed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `promotion_id` (`promotion_id`),
    KEY `business_id` (`business_id`),
    KEY `user_ip` (`user_ip`),
    CONSTRAINT `redemptions_promotion_id_fk` FOREIGN KEY (`promotion_id`) REFERENCES `promotions` (`id`) ON DELETE CASCADE,
    CONSTRAINT `redemptions_business_id_fk` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add promotion-related settings to system_settings
INSERT INTO `system_settings` (`setting_key`, `value`, `description`, `updated_at`) VALUES
('max_promotions_per_business', '10', 'Maximum number of active promotions per business', NOW()),
('promotion_code_length', '8', 'Length of generated promotion codes', NOW()),
('promotion_min_duration_days', '1', 'Minimum duration for promotions in days', NOW()),
('promotion_max_duration_days', '30', 'Maximum duration for promotions in days', NOW()),
('promotion_max_discount_percent', '50', 'Maximum discount percentage allowed', NOW()),
('promotion_max_discount_amount', '10.00', 'Maximum fixed discount amount allowed', NOW());

-- Create function to generate promo codes
DELIMITER //
CREATE FUNCTION IF NOT EXISTS `generate_promo_code`() 
RETURNS varchar(20)
DETERMINISTIC
BEGIN
    DECLARE chars VARCHAR(62) DEFAULT 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    DECLARE code_length INT;
    DECLARE result VARCHAR(20);
    DECLARE i INT;
    
    -- Get code length from settings
    SELECT CAST(value AS UNSIGNED) INTO code_length 
    FROM system_settings 
    WHERE setting_key = 'promotion_code_length';
    
    -- Generate random code
    SET result = '';
    SET i = 1;
    
    WHILE i <= code_length DO
        SET result = CONCAT(result, SUBSTRING(chars, FLOOR(1 + RAND() * 62), 1));
        SET i = i + 1;
    END WHILE;
    
    RETURN result;
END //
DELIMITER ; 