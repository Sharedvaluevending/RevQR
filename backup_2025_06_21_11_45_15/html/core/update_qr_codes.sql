USE revenueqr;

-- First drop the foreign key constraint from votes table
ALTER TABLE votes DROP FOREIGN KEY fk_votes_qr_code;

-- Drop the existing qr_codes table
DROP TABLE IF EXISTS qr_codes;

-- Recreate the qr_codes table with the correct foreign key constraint
CREATE TABLE IF NOT EXISTS `qr_codes` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `machine_id` INT NULL,
    `campaign_id` INT NULL,
    `qr_type` ENUM('static', 'dynamic', 'dynamic_voting', 'dynamic_vending', 'cross_promo', 'stackable') NOT NULL,
    `machine_name` VARCHAR(255) NULL,
    `machine_location` VARCHAR(255) NULL,
    `location` VARCHAR(255) NOT NULL,
    `code` VARCHAR(255) NOT NULL,
    `meta` JSON NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `status` ENUM('active', 'inactive', 'deleted') NOT NULL DEFAULT 'active',
    PRIMARY KEY (`id`),
    FOREIGN KEY (`machine_id`) REFERENCES `voting_lists`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`campaign_id`) REFERENCES `qr_campaigns`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Re-add the foreign key constraint to votes table
ALTER TABLE votes ADD CONSTRAINT fk_votes_qr_code FOREIGN KEY (qr_code_id) REFERENCES qr_codes(id) ON DELETE SET NULL; 