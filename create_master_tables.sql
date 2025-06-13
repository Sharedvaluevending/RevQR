-- Create master_items table
CREATE TABLE IF NOT EXISTS `master_items` (
  `id`            INT AUTO_INCREMENT PRIMARY KEY,
  `name`          VARCHAR(255)    NOT NULL,
  `category`      VARCHAR(100)    NOT NULL,
  `type`          ENUM('snack','drink','pizza','side','other') NOT NULL,
  `brand`         VARCHAR(100),
  `suggested_price` DECIMAL(10,2),
  `suggested_cost` DECIMAL(10,2),
  `popularity`    ENUM('low','medium','high') DEFAULT 'medium',
  `shelf_life`    INT,
  `is_seasonal`   BOOLEAN DEFAULT FALSE,
  `is_imported`   BOOLEAN DEFAULT FALSE,
  `is_healthy`    BOOLEAN DEFAULT FALSE,
  `status`        ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- Create categories table
CREATE TABLE IF NOT EXISTS `categories` (
  `id`            INT AUTO_INCREMENT PRIMARY KEY,
  `name`          VARCHAR(100)    NOT NULL UNIQUE,
  `description`   TEXT,
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- Create item_mapping table
CREATE TABLE IF NOT EXISTS `item_mapping` (
  `id`            INT AUTO_INCREMENT PRIMARY KEY,
  `master_item_id` INT NOT NULL,
  `item_id`       INT NOT NULL,
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`master_item_id`)
    REFERENCES `master_items`(`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  FOREIGN KEY (`item_id`)
    REFERENCES `items`(`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci; 