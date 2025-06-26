-- Add new columns to qr_campaigns table
ALTER TABLE qr_campaigns
ADD COLUMN qr_type ENUM('static','dynamic','cross_promo','stackable') NOT NULL DEFAULT 'static' AFTER type,
ADD COLUMN static_url VARCHAR(255) NULL AFTER qr_type,
ADD COLUMN tooltip TEXT NULL AFTER static_url; 