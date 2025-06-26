CREATE TABLE IF NOT EXISTS `businesses` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `name`        VARCHAR(255) NOT NULL,
  `email`       VARCHAR(255),
  `slug`        VARCHAR(100) NOT NULL UNIQUE,
  `type`        ENUM('vending', 'restaurant', 'cannabis', 'retail', 'other') NOT NULL DEFAULT 'vending',
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci; 