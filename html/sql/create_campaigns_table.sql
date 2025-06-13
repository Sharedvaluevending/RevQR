-- Create qr_campaigns table
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