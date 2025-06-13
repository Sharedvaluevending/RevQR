-- Add missing columns to winners
ALTER TABLE winners
  ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP;

-- Add missing columns to machines
ALTER TABLE machines
  ADD COLUMN slug VARCHAR(100) NOT NULL UNIQUE AFTER name,
  ADD COLUMN type ENUM('vote','promo') NOT NULL DEFAULT 'vote' AFTER description,
  ADD COLUMN is_active BOOLEAN NOT NULL DEFAULT TRUE AFTER type,
  ADD COLUMN tooltip TEXT AFTER is_active;

-- Add missing columns to qr_codes
ALTER TABLE qr_codes
  ADD COLUMN campaign_type ENUM('static','dynamic','cross_promo','stackable') NOT NULL DEFAULT 'static' AFTER qr_type,
  ADD COLUMN meta JSON NULL AFTER campaign_type,
  ADD COLUMN status ENUM('active','inactive') NOT NULL DEFAULT 'active' AFTER created_at,
  ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER status;

-- Add missing columns to items
ALTER TABLE items
  ADD COLUMN type ENUM('snack','drink','pizza','side','other') NOT NULL AFTER name,
  ADD COLUMN list_type ENUM('regular','in','vote_in','vote_out','showcase') NOT NULL AFTER price,
  ADD COLUMN status ENUM('active','inactive') NOT NULL DEFAULT 'active' AFTER list_type,
  ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

-- Add missing index to items
CREATE INDEX idx_machine_status ON items(machine_id, status);

-- Create missing views
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
WHERE m.type IN ('vote', 'promo')
WITH CHECK OPTION; 