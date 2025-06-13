-- Create winners table for tracking weekly winners
CREATE TABLE IF NOT EXISTS `winners` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `campaign_id` int(11) NOT NULL,
    `list_id` int(11) DEFAULT NULL,
    `item_id` int(11) NOT NULL,
    `vote_type` enum('in','out') NOT NULL,
    `week_start` date NOT NULL,
    `week_end` date NOT NULL,
    `votes_count` int(11) NOT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_winner` (`campaign_id`, `list_id`, `item_id`, `vote_type`, `week_start`),
    KEY `idx_winners_campaign` (`campaign_id`),
    KEY `idx_winners_list` (`list_id`),
    KEY `idx_winners_item` (`item_id`),
    KEY `idx_winners_week` (`week_start`, `week_end`),
    KEY `idx_winners_type` (`vote_type`),
    CONSTRAINT `winners_campaign_fk` FOREIGN KEY (`campaign_id`) REFERENCES `campaigns` (`id`) ON DELETE CASCADE,
    CONSTRAINT `winners_list_fk` FOREIGN KEY (`list_id`) REFERENCES `voting_lists` (`id`) ON DELETE CASCADE,
    CONSTRAINT `winners_item_fk` FOREIGN KEY (`item_id`) REFERENCES `voting_list_items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add weekly vote limit tracking indexes
ALTER TABLE `votes` 
ADD INDEX `idx_votes_weekly` (`voter_ip`, `created_at`);

-- Create vote archive table for historical data
CREATE TABLE IF NOT EXISTS `votes_archive` (
    `id` int(11) NOT NULL,
    `list_id` int(11) DEFAULT NULL,
    `item_id` int(11) DEFAULT NULL,
    `vote_type` enum('in','out') DEFAULT NULL,
    `user_id` int(11) DEFAULT NULL,
    `voter_ip` varchar(45) DEFAULT NULL,
    `campaign_id` int(11) DEFAULT NULL,
    `created_at` timestamp NULL DEFAULT NULL,
    `archived_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `week_archived` varchar(10) DEFAULT NULL,
    PRIMARY KEY (`id`, `archived_at`),
    KEY `idx_archive_week` (`week_archived`),
    KEY `idx_archive_campaign` (`campaign_id`),
    KEY `idx_archive_date` (`archived_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci; 