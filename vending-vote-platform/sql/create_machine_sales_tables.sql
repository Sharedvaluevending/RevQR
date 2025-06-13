-- Create machine_sales table
CREATE TABLE IF NOT EXISTS `machine_sales` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `machine_id` int(11) NOT NULL,
    `item_id` int(11) NOT NULL,
    `sale_price` decimal(10,2) NOT NULL,
    `start_date` date NOT NULL,
    `end_date` date NOT NULL,
    `status` enum('active','inactive','expired') NOT NULL DEFAULT 'active',
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `machine_id` (`machine_id`),
    KEY `item_id` (`item_id`),
    KEY `status` (`status`),
    KEY `dates` (`start_date`, `end_date`),
    CONSTRAINT `machine_sales_machine_id_fk` FOREIGN KEY (`machine_id`) REFERENCES `machines` (`id`) ON DELETE CASCADE,
    CONSTRAINT `machine_sales_item_id_fk` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add machine sales QR type to system_settings
INSERT INTO `system_settings` (`setting_key`, `value`, `description`, `updated_at`) VALUES
('qr_types', '{"vote":"Voting QR","promo":"Promotion QR","sales":"Machine Sales QR"}', 'Available QR code types', NOW())
ON DUPLICATE KEY UPDATE 
    `value` = '{"vote":"Voting QR","promo":"Promotion QR","sales":"Machine Sales QR"}',
    `description` = 'Available QR code types',
    `updated_at` = NOW();

-- Add machine sales settings
INSERT INTO `system_settings` (`setting_key`, `value`, `description`, `updated_at`) VALUES
('max_sales_per_machine', '10', 'Maximum number of active sales per machine', NOW()),
('sale_min_duration_days', '1', 'Minimum duration for sales in days', NOW()),
('sale_max_duration_days', '30', 'Maximum duration for sales in days', NOW()),
('sale_max_discount_percent', '50', 'Maximum discount percentage allowed', NOW()),
('sale_max_discount_amount', '10.00', 'Maximum fixed discount amount allowed', NOW())
ON DUPLICATE KEY UPDATE 
    `value` = VALUES(`value`),
    `description` = VALUES(`description`),
    `updated_at` = NOW(); 