-- Update QR codes table schema
ALTER TABLE `qr_codes`
    -- Add new columns for enhanced features
    ADD COLUMN `module_shape` VARCHAR(20) DEFAULT 'square' AFTER `meta`,
    ADD COLUMN `module_size` INT DEFAULT 1 AFTER `module_shape`,
    ADD COLUMN `module_spacing` INT DEFAULT 0 AFTER `module_size`,
    ADD COLUMN `module_glow` BOOLEAN DEFAULT FALSE AFTER `module_spacing`,
    ADD COLUMN `module_glow_color` VARCHAR(7) DEFAULT '#000000' AFTER `module_glow`,
    ADD COLUMN `module_glow_intensity` INT DEFAULT 5 AFTER `module_glow_color`,
    
    -- Gradient options
    ADD COLUMN `gradient_type` VARCHAR(20) DEFAULT 'none' AFTER `module_glow_intensity`,
    ADD COLUMN `gradient_start` VARCHAR(7) DEFAULT '#000000' AFTER `gradient_type`,
    ADD COLUMN `gradient_end` VARCHAR(7) DEFAULT '#0000FF' AFTER `gradient_start`,
    ADD COLUMN `gradient_angle` INT DEFAULT 45 AFTER `gradient_end`,
    ADD COLUMN `gradient_opacity` FLOAT DEFAULT 1.0 AFTER `gradient_angle`,
    
    -- Eye customization
    ADD COLUMN `eye_style` VARCHAR(20) DEFAULT 'square' AFTER `gradient_opacity`,
    ADD COLUMN `eye_color` VARCHAR(7) DEFAULT '#000000' AFTER `eye_style`,
    ADD COLUMN `eye_size` INT DEFAULT 1 AFTER `eye_color`,
    ADD COLUMN `eye_border` BOOLEAN DEFAULT FALSE AFTER `eye_size`,
    ADD COLUMN `eye_border_color` VARCHAR(7) DEFAULT '#000000' AFTER `eye_border`,
    ADD COLUMN `eye_border_width` INT DEFAULT 1 AFTER `eye_border_color`,
    ADD COLUMN `eye_glow` BOOLEAN DEFAULT FALSE AFTER `eye_border_width`,
    ADD COLUMN `eye_glow_color` VARCHAR(7) DEFAULT '#000000' AFTER `eye_glow`,
    ADD COLUMN `eye_glow_intensity` INT DEFAULT 5 AFTER `eye_glow_color`,
    
    -- Frame customization
    ADD COLUMN `frame_style` VARCHAR(20) DEFAULT 'none' AFTER `eye_glow_intensity`,
    ADD COLUMN `frame_color` VARCHAR(7) DEFAULT '#000000' AFTER `frame_style`,
    ADD COLUMN `frame_width` INT DEFAULT 2 AFTER `frame_color`,
    ADD COLUMN `frame_radius` INT DEFAULT 5 AFTER `frame_width`,
    ADD COLUMN `frame_glow` BOOLEAN DEFAULT FALSE AFTER `frame_radius`,
    ADD COLUMN `frame_glow_color` VARCHAR(7) DEFAULT '#000000' AFTER `frame_glow`,
    ADD COLUMN `frame_glow_intensity` INT DEFAULT 5 AFTER `frame_glow_color`,
    
    -- Text options
    ADD COLUMN `label_text` VARCHAR(255) DEFAULT '' AFTER `frame_glow_intensity`,
    ADD COLUMN `label_font` VARCHAR(50) DEFAULT 'Arial' AFTER `label_text`,
    ADD COLUMN `label_size` INT DEFAULT 12 AFTER `label_font`,
    ADD COLUMN `label_color` VARCHAR(7) DEFAULT '#000000' AFTER `label_size`,
    ADD COLUMN `label_alignment` VARCHAR(20) DEFAULT 'center' AFTER `label_color`,
    ADD COLUMN `label_glow` BOOLEAN DEFAULT FALSE AFTER `label_alignment`,
    ADD COLUMN `label_glow_color` VARCHAR(7) DEFAULT '#000000' AFTER `label_glow`,
    ADD COLUMN `label_glow_intensity` INT DEFAULT 5 AFTER `label_glow_color`,
    ADD COLUMN `label_rotation` INT DEFAULT 0 AFTER `label_glow_intensity`,
    
    -- Bottom text options
    ADD COLUMN `bottom_text` VARCHAR(255) DEFAULT '' AFTER `label_rotation`,
    ADD COLUMN `bottom_font` VARCHAR(50) DEFAULT 'Arial' AFTER `bottom_text`,
    ADD COLUMN `bottom_size` INT DEFAULT 12 AFTER `bottom_font`,
    ADD COLUMN `bottom_color` VARCHAR(7) DEFAULT '#000000' AFTER `bottom_size`,
    ADD COLUMN `bottom_alignment` VARCHAR(20) DEFAULT 'center' AFTER `bottom_color`,
    ADD COLUMN `bottom_glow` BOOLEAN DEFAULT FALSE AFTER `bottom_alignment`,
    ADD COLUMN `bottom_glow_color` VARCHAR(7) DEFAULT '#000000' AFTER `bottom_glow`,
    ADD COLUMN `bottom_glow_intensity` INT DEFAULT 5 AFTER `bottom_glow_color`,
    ADD COLUMN `bottom_rotation` INT DEFAULT 0 AFTER `bottom_glow_intensity`,
    
    -- Effects
    ADD COLUMN `shadow` BOOLEAN DEFAULT FALSE AFTER `bottom_rotation`,
    ADD COLUMN `shadow_color` VARCHAR(7) DEFAULT '#000000' AFTER `shadow`,
    ADD COLUMN `shadow_blur` INT DEFAULT 5 AFTER `shadow_color`,
    ADD COLUMN `shadow_offset_x` INT DEFAULT 2 AFTER `shadow_blur`,
    ADD COLUMN `shadow_offset_y` INT DEFAULT 2 AFTER `shadow_offset_x`,
    ADD COLUMN `shadow_opacity` FLOAT DEFAULT 0.5 AFTER `shadow_offset_y`,
    
    -- Statistics
    ADD COLUMN `enable_stats` BOOLEAN DEFAULT FALSE AFTER `shadow_opacity`,
    ADD COLUMN `stats_display` VARCHAR(20) DEFAULT 'none' AFTER `enable_stats`,
    
    -- Update existing columns
    MODIFY COLUMN `qr_type` ENUM('static', 'dynamic', 'campaign', 'cross_promo', 'stackable') NOT NULL DEFAULT 'static',
    MODIFY COLUMN `meta` JSON NULL,
    MODIFY COLUMN `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    MODIFY COLUMN `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP;

-- Create index for QR code lookups
CREATE INDEX `idx_qr_codes_code` ON `qr_codes` (`code`);
CREATE INDEX `idx_qr_codes_type` ON `qr_codes` (`qr_type`);
CREATE INDEX `idx_qr_codes_status` ON `qr_codes` (`status`);

-- Create QR code statistics table
CREATE TABLE IF NOT EXISTS `qr_code_stats` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `qr_code_id` INT NOT NULL,
    `scan_time` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `ip_address` VARCHAR(45),
    `user_agent` VARCHAR(255),
    `referrer` VARCHAR(255),
    `device_type` VARCHAR(50),
    `browser` VARCHAR(50),
    `os` VARCHAR(50),
    `location` VARCHAR(255),
    `conversion` BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (`qr_code_id`) REFERENCES `qr_codes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create index for QR code statistics
CREATE INDEX `idx_qr_stats_code` ON `qr_code_stats` (`qr_code_id`);
CREATE INDEX `idx_qr_stats_time` ON `qr_code_stats` (`scan_time`); 