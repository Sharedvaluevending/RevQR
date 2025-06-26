-- Standardize schema for RevenueQR platform
SET FOREIGN_KEY_CHECKS = 0;

-- Drop old views that might cause confusion
DROP VIEW IF EXISTS campaign_view;
DROP VIEW IF EXISTS campaign_items_view;

-- Update campaigns table
ALTER TABLE campaigns
    MODIFY COLUMN status ENUM('draft','active','paused','ended') NOT NULL DEFAULT 'draft',
    MODIFY COLUMN campaign_type ENUM('vote','promo','cross_promo') NOT NULL DEFAULT 'vote',
    ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Update qr_codes table
ALTER TABLE qr_codes
    DROP FOREIGN KEY IF EXISTS qr_codes_ibfk_1,
    DROP FOREIGN KEY IF EXISTS qr_codes_ibfk_2,
    DROP COLUMN IF EXISTS machine_id,
    DROP COLUMN IF EXISTS item_id,
    DROP COLUMN IF EXISTS code,
    DROP COLUMN IF EXISTS static_url,
    DROP COLUMN IF EXISTS tooltip,
    DROP COLUMN IF EXISTS label_text,
    MODIFY COLUMN machine_name VARCHAR(255) NOT NULL,
    MODIFY COLUMN location VARCHAR(255) NULL,
    MODIFY COLUMN qr_type ENUM('static','dynamic','cross_promo','stackable') NOT NULL,
    MODIFY COLUMN url VARCHAR(255) NOT NULL,
    MODIFY COLUMN options JSON NULL,
    ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Update votes table
ALTER TABLE votes
    DROP FOREIGN KEY IF EXISTS votes_ibfk_1,
    DROP FOREIGN KEY IF EXISTS votes_ibfk_2,
    DROP FOREIGN KEY IF EXISTS votes_ibfk_3,
    MODIFY COLUMN vote_type ENUM('vote_in','vote_out') NOT NULL,
    MODIFY COLUMN ip_address VARCHAR(45) NOT NULL,
    ADD COLUMN qr_code_id INT NULL AFTER campaign_id,
    ADD COLUMN machine_name VARCHAR(255) NULL AFTER qr_code_id,
    ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    ADD FOREIGN KEY (qr_code_id) REFERENCES qr_codes(id) ON DELETE SET NULL;

-- Add missing indexes
CREATE INDEX IF NOT EXISTS idx_qr_codes_business_campaign ON qr_codes(business_id, campaign_id);
CREATE INDEX IF NOT EXISTS idx_qr_codes_machine_name ON qr_codes(machine_name);
CREATE INDEX IF NOT EXISTS idx_votes_qr_code ON votes(qr_code_id);
CREATE INDEX IF NOT EXISTS idx_votes_machine ON votes(machine_name);
CREATE INDEX IF NOT EXISTS idx_votes_ip_date ON votes(ip_address, voted_at);

SET FOREIGN_KEY_CHECKS = 1; 