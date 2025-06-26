-- Migration script to update schema for RevenueQR

-- 1. Create voting_lists table
CREATE TABLE IF NOT EXISTS `voting_lists` (
  `id`            INT AUTO_INCREMENT PRIMARY KEY,
  `business_id`   INT             NOT NULL,
  `name`          VARCHAR(255)    NOT NULL,
  `description`   TEXT,
  `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_voting_lists_business` (`business_id`),
  FOREIGN KEY (`business_id`)
    REFERENCES `businesses`(`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- 2. Create voting_list_items table
CREATE TABLE IF NOT EXISTS `voting_list_items` (
  `id`              INT AUTO_INCREMENT PRIMARY KEY,
  `voting_list_id`  INT             NOT NULL,
  `name`            VARCHAR(255)    NOT NULL,
  `description`     TEXT,
  `created_at`      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_voting_list_items_list` (`voting_list_id`),
  FOREIGN KEY (`voting_list_id`)
    REFERENCES `voting_lists`(`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- 3. Update votes table
ALTER TABLE `votes`
  ADD COLUMN `business_id` INT AFTER `machine_id`,
  ADD COLUMN `voted_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `voter_ip`,
  ADD INDEX `idx_votes_business` (`business_id`),
  ADD FOREIGN KEY (`business_id`)
    REFERENCES `businesses`(`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE;

-- 4. Create qr_codes table if not exists
CREATE TABLE IF NOT EXISTS `qr_codes` (
  `id`            INT AUTO_INCREMENT PRIMARY KEY,
  `campaign_id`   INT             NOT NULL,
  `code`          VARCHAR(255)    NOT NULL,
  `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_qr_codes_campaign` (`campaign_id`),
  FOREIGN KEY (`campaign_id`)
    REFERENCES `qr_campaigns`(`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- 5. Update items table to reference machines
ALTER TABLE `items`
  DROP FOREIGN KEY `items_ibfk_1`,
  DROP COLUMN `business_id`,
  ADD COLUMN `machine_id` INT NOT NULL AFTER `id`,
  ADD INDEX `idx_items_machine` (`machine_id`),
  ADD FOREIGN KEY (`machine_id`)
    REFERENCES `machines`(`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE;

-- 6. Create qr_campaigns table if not exists
CREATE TABLE IF NOT EXISTS `qr_campaigns` (
  `id`            INT AUTO_INCREMENT PRIMARY KEY,
  `business_id`   INT             NOT NULL,
  `name`          VARCHAR(255)    NOT NULL,
  `description`   TEXT,
  `type`          ENUM('vote','promo') NOT NULL,
  `qr_type`       ENUM('static','dynamic','cross_promo','stackable') NOT NULL DEFAULT 'static',
  `static_url`    VARCHAR(255)    NULL,
  `tooltip`       TEXT            NULL,
  `is_active`     BOOLEAN         NOT NULL DEFAULT TRUE,
  `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_campaigns_business` (`business_id`),
  FOREIGN KEY (`business_id`)
    REFERENCES `businesses`(`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci; 