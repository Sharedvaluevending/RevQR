-- Add campaign-like features to machines table
ALTER TABLE machines
ADD COLUMN description TEXT AFTER name,
ADD COLUMN type ENUM('vote','promo') NOT NULL DEFAULT 'vote' AFTER description,
ADD COLUMN is_active BOOLEAN NOT NULL DEFAULT TRUE AFTER type,
ADD COLUMN tooltip TEXT AFTER is_active;

-- Add campaign-like features to qr_codes table
ALTER TABLE qr_codes
ADD COLUMN campaign_type ENUM('static','dynamic','cross_promo','stackable') 
    NOT NULL DEFAULT 'static' AFTER qr_type,
ADD COLUMN static_url VARCHAR(255) NULL AFTER campaign_type;

-- Create a view to maintain backward compatibility
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

-- Create a view for campaign items
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