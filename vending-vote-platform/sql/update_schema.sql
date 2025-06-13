-- Add missing indexes and constraints
ALTER TABLE winners
ADD INDEX idx_machine_week (machine_id, week_start),
ADD CONSTRAINT fk_winners_item
    FOREIGN KEY (item_id)
    REFERENCES items(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE;

-- Add updated_at to machines table
ALTER TABLE machines
ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Add missing fields to qr_codes table
ALTER TABLE qr_codes
ADD COLUMN campaign_type ENUM('static','dynamic','cross_promo','stackable') 
    NOT NULL DEFAULT 'static' AFTER qr_type,
ADD COLUMN static_url VARCHAR(255) NULL AFTER campaign_type,
ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Add missing fields to items table
ALTER TABLE items
ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
ADD INDEX idx_machine_status (machine_id, status);

-- Add missing fields to votes table
ALTER TABLE votes
ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
ADD INDEX idx_machine_vote_type (machine_id, vote_type);

-- Create view for campaign compatibility
CREATE OR REPLACE VIEW campaign_view AS
SELECT 
    m.id as campaign_id,
    m.business_id,
    m.name as campaign_name,
    m.description as campaign_description,
    m.type as campaign_type,
    m.is_active,
    m.tooltip,
    m.created_at as campaign_created_at,
    m.updated_at as campaign_updated_at
FROM machines m
WHERE m.type IN ('vote', 'promo');

-- Create view for campaign items compatibility
CREATE OR REPLACE VIEW campaign_items_view AS
SELECT 
    m.id as campaign_id,
    i.id as item_id,
    i.name as item_name,
    i.type as item_type,
    i.price,
    i.list_type,
    i.status
FROM machines m
JOIN items i ON i.machine_id = m.id
WHERE m.type IN ('vote', 'promo'); 