CREATE TABLE IF NOT EXISTS `qr_codes` (
  `id`            INT AUTO_INCREMENT PRIMARY KEY,
  `business_id`   INT             NOT NULL,
  `campaign_id`   INT             NOT NULL,
  `qr_type`       ENUM('static','dynamic','cross_promo','stackable')
                   NOT NULL,
  `machine_name`  VARCHAR(255)    NOT NULL,
  `location`      VARCHAR(255)    NULL,
  `url`           VARCHAR(255)    NOT NULL,
  `options`       JSON            NULL,
  `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_qr_business` (`business_id`),
  INDEX `idx_qr_campaign` (`campaign_id`),
  FOREIGN KEY (`business_id`)
    REFERENCES `businesses`(`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  FOREIGN KEY (`campaign_id`)
    REFERENCES `campaigns`(`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci; 