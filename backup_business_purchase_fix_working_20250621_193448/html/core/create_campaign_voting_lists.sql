USE `revenueqr`;

-- Create campaign_voting_lists table
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