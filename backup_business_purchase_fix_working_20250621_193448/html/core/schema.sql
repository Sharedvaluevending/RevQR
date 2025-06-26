USE `revenueqr`;
-- 1) Create database
CREATE DATABASE IF NOT EXISTS `revenueqr`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

-- System Settings Table
CREATE TABLE system_settings (
    setting_key VARCHAR(50) PRIMARY KEY,
    value TEXT,
    description TEXT,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Email Templates Table
CREATE TABLE email_templates (
    template_name VARCHAR(50) PRIMARY KEY,
    template_content TEXT,
    description TEXT,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default system settings
INSERT INTO system_settings (setting_key, value, description) VALUES
('spin_hoodie_probability', '5', 'Probability of winning a hoodie in spin wheel (%)'),
('spin_bogo_probability', '15', 'Probability of winning BOGO in spin wheel (%)'),
('spin_nothing_probability', '80', 'Probability of winning nothing in spin wheel (%)'),
('spin_cooldown_hours', '24', 'Hours between spins for same IP'),
('qr_default_size', '300', 'Default QR code size in pixels'),
('qr_default_margin', '10', 'Default QR code margin in pixels'),
('qr_default_error_correction', 'M', 'Default QR code error correction level'),
('qr_max_logo_size', '50', 'Maximum logo size in pixels for QR codes'),
('max_votes_per_ip', '10', 'Maximum votes per IP address per day'),
('max_qr_codes_per_business', '50', 'Maximum QR codes per business account'),
('max_items_per_business', '100', 'Maximum items per business account'),
('max_file_upload_size', '5', 'Maximum file upload size in MB'),
('smtp_host', 'smtp.gmail.com', 'SMTP server hostname'),
('smtp_port', '587', 'SMTP server port'),
('smtp_username', '', 'SMTP username/email'),
('smtp_password', '', 'SMTP password'),
('smtp_from_email', 'noreply@revenueqr.com', 'Default sender email'),
('smtp_from_name', 'RevenueQR Platform', 'Default sender name');

-- Insert default email templates
INSERT INTO email_templates (template_name, template_content, description) VALUES
('welcome_email', 'Welcome {name}!\n\nThank you for joining our vending machine community. You can now log in at {login_url}.\n\nBest regards,\nThe Team', 'Welcome email for new users'),
('password_reset', 'Hello {name},\n\nYou requested a password reset. Click here to reset your password: {reset_url}\n\nThis link will expire in {expiry_hours} hours.\n\nBest regards,\nThe Team', 'Password reset email'),
('prize_notification', 'Congratulations {name}!\n\nYou won a {prize_name} worth ${prize_value}!\n\nClick here to claim your prize: {claim_url}\n\nBest regards,\nThe Team', 'Prize notification email'),
('business_approval', 'Hello {owner_name},\n\nYour business account for {business_name} has been approved!\n\nYou can now log in at {login_url} to start managing your vending machines.\n\nBest regards,\nThe Team', 'Business account approval email');

CREATE TABLE IF NOT EXISTS categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    parent_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    business_id INT,
    name VARCHAR(255) NOT NULL,
    category_id INT,
    brand VARCHAR(100),
    retail_price DECIMAL(10,2) NOT NULL,
    cost_price DECIMAL(10,2) NOT NULL,
    popularity ENUM('high', 'medium', 'low') NOT NULL,
    shelf_life INT NOT NULL,
    is_seasonal BOOLEAN DEFAULT FALSE,
    is_imported BOOLEAN DEFAULT FALSE,
    is_healthy BOOLEAN DEFAULT FALSE,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
);

-- 16) VOTING_LISTS: Stores voting list configurations
CREATE TABLE IF NOT EXISTS `voting_lists` (
  `id`            INT AUTO_INCREMENT PRIMARY KEY,
  `business_id`   INT             NOT NULL,
  `name`          VARCHAR(255)    NOT NULL,
  `description`   TEXT,
  `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_voting_lists_business` (`business_id`),
  FOREIGN KEY (`business_id`)
    REFERENCES `businesses`(`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- Campaign Voting Lists Table (links campaigns to voting lists)
CREATE TABLE IF NOT EXISTS `campaign_voting_lists` (
  `campaign_id`   INT             NOT NULL,
  `voting_list_id` INT            NOT NULL,
  `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`campaign_id`, `voting_list_id`),
  FOREIGN KEY (`campaign_id`)
    REFERENCES `campaigns`(`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  FOREIGN KEY (`voting_list_id`)
    REFERENCES `voting_lists`(`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- 17) QR_CAMPAIGNS: Stores campaign configurations
CREATE TABLE IF NOT EXISTS `qr_campaigns` (
  `id`            INT AUTO_INCREMENT PRIMARY KEY,
  `business_id`   INT             NOT NULL,
  `name`          VARCHAR(255)    NOT NULL,
  `description`   TEXT,
  `type`          ENUM('vote','promo') NOT NULL,
  `qr_type`       ENUM('dynamic','static') NOT NULL DEFAULT 'dynamic',
  `static_url`    VARCHAR(255)    NULL,
  `tooltip`       TEXT            NULL,
  `is_active`     BOOLEAN         NOT NULL DEFAULT TRUE,
  `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_campaigns_business` (`business_id`),
  FOREIGN KEY (`business_id`)
    REFERENCES `businesses`
);

-- Password Reset Tokens
CREATE TABLE IF NOT EXISTS `password_resets` (
  `id`            INT AUTO_INCREMENT PRIMARY KEY,
  `user_id`       INT             NOT NULL,
  `token`         VARCHAR(64)     NOT NULL,
  `expires_at`    DATETIME        NOT NULL,
  `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_resets_user` (`user_id`),
  INDEX `idx_resets_token` (`token`),
  FOREIGN KEY (`user_id`)
    REFERENCES `users`(`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `users` (
  `id`            INT AUTO_INCREMENT PRIMARY KEY,
  `username`      VARCHAR(255)    NOT NULL UNIQUE,
  `password_hash` VARCHAR(255)    NOT NULL,
  `role`          ENUM('admin','business','user') NOT NULL,
  `business_id`   INT             DEFAULT NULL,
  `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `chk_user_role_business`
    CHECK (
      (role = 'business' AND business_id IS NOT NULL)
      OR (role = 'admin'  AND business_id IS NULL)
      OR (role = 'user'   AND business_id IS NULL)
    ),
  INDEX `idx_users_business` (`business_id`),
  FOREIGN KEY (`business_id`)
    REFERENCES `businesses`(`id`)
    ON DELETE SET NULL
    ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- 15) CAMPAIGN_MACHINES: Links campaigns to machines
CREATE TABLE IF NOT EXISTS `campaign_machines` (
  `id`            INT AUTO_INCREMENT PRIMARY KEY,
  `campaign_id`   INT             NOT NULL,
  `machine_id`    INT             NOT NULL,
  `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_campaign_machine` (`campaign_id`, `machine_id`),
  FOREIGN KEY (`campaign_id`)
    REFERENCES `qr_campaigns`(`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  FOREIGN KEY (`machine_id`)
    REFERENCES `machines`(`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- Header Templates Table
CREATE TABLE IF NOT EXISTS `header_templates` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `business_id` INT NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `file_path` VARCHAR(255) NOT NULL,
    `created_by` INT NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`business_id`) REFERENCES `businesses`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 16) Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE IF NOT EXISTS `qr_codes` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `machine_id` INT NULL,
    `campaign_id` INT NULL,
    `qr_type` ENUM('static', 'dynamic', 'cross_promo', 'stackable') NOT NULL,
    `machine_name` VARCHAR(255) NULL,
    `machine_location` VARCHAR(255) NULL,
    `code` VARCHAR(255) NOT NULL,
    `meta` JSON NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `status` ENUM('active', 'inactive', 'deleted') NOT NULL DEFAULT 'active',
    PRIMARY KEY (`id`),
    FOREIGN KEY (`machine_id`) REFERENCES `voting_lists`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`campaign_id`) REFERENCES `campaigns`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;