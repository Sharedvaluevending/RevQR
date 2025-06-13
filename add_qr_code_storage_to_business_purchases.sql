-- Add QR Code Storage to Business Purchases for Nayax Integration
-- Date: 2025-01-17
-- Purpose: Enable QR code generation and storage for discount redemption at Nayax machines

USE revenueqr;

-- Add QR code storage fields to business_purchases table
ALTER TABLE business_purchases 
ADD COLUMN qr_code_data TEXT NULL COMMENT 'Base64 encoded QR code image data' AFTER purchase_code,
ADD COLUMN qr_code_content VARCHAR(500) NULL COMMENT 'QR code content/payload for Nayax machines' AFTER qr_code_data,
ADD COLUMN nayax_machine_id VARCHAR(50) NULL COMMENT 'Specific Nayax machine ID if applicable' AFTER qr_code_content,
ADD COLUMN item_selection JSON NULL COMMENT 'Pre-selected items for vending machine' AFTER nayax_machine_id,
ADD COLUMN last_scanned_at TIMESTAMP NULL COMMENT 'When QR code was last scanned' AFTER item_selection,
ADD COLUMN scan_count INT DEFAULT 0 COMMENT 'Number of times QR code has been scanned' AFTER last_scanned_at;

-- Add indexes for QR code functionality
ALTER TABLE business_purchases
ADD INDEX idx_qr_code_content (qr_code_content),
ADD INDEX idx_nayax_machine (nayax_machine_id),
ADD INDEX idx_last_scanned (last_scanned_at),
ADD INDEX idx_scan_count (scan_count);

-- Create QR code scans tracking table for analytics
CREATE TABLE IF NOT EXISTS business_purchase_qr_scans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    business_purchase_id INT NOT NULL,
    scanned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    scanner_ip VARCHAR(45) NULL COMMENT 'IP address of scanner',
    user_agent TEXT NULL COMMENT 'User agent of scanning device',
    nayax_machine_id VARCHAR(50) NULL COMMENT 'Machine that scanned the code',
    location_data JSON NULL COMMENT 'GPS or other location data if available',
    scan_result ENUM('success', 'expired', 'already_used', 'invalid', 'error') NOT NULL,
    error_message VARCHAR(255) NULL,
    
    FOREIGN KEY (business_purchase_id) REFERENCES business_purchases(id) ON DELETE CASCADE,
    INDEX idx_purchase_scans (business_purchase_id, scanned_at),
    INDEX idx_machine_scans (nayax_machine_id, scanned_at),
    INDEX idx_scan_result (scan_result),
    INDEX idx_scanner_ip (scanner_ip)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create Nayax machine integration table
CREATE TABLE IF NOT EXISTS nayax_machines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    business_id INT NOT NULL,
    machine_id VARCHAR(50) NOT NULL UNIQUE COMMENT 'Nayax machine identifier',
    machine_name VARCHAR(255) NOT NULL,
    location_description VARCHAR(255) NULL,
    api_endpoint VARCHAR(500) NULL COMMENT 'Nayax API endpoint for this machine',
    api_key VARCHAR(255) NULL COMMENT 'API key for machine integration',
    supports_qr_codes BOOLEAN DEFAULT TRUE,
    supported_discount_types JSON NULL COMMENT 'Types of discounts this machine supports',
    machine_status ENUM('active', 'inactive', 'maintenance', 'error') DEFAULT 'active',
    last_communication TIMESTAMP NULL COMMENT 'Last successful communication with machine',
    settings JSON NULL COMMENT 'Machine-specific settings and capabilities',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    INDEX idx_business_machines (business_id, machine_status),
    INDEX idx_machine_id (machine_id),
    INDEX idx_last_communication (last_communication)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert sample Nayax machines for testing
INSERT INTO nayax_machines (business_id, machine_id, machine_name, location_description, supports_qr_codes, supported_discount_types) VALUES
(1, 'NYX001', 'Main Office Vending Machine', 'Lobby - First Floor', TRUE, '["percentage", "fixed_amount", "item_specific"]'),
(1, 'NYX002', 'Break Room Snack Machine', 'Employee Break Room - Second Floor', TRUE, '["percentage", "item_specific"]'),
(2, 'NYX003', 'Customer Area Machine', 'Waiting Area - Main Entrance', TRUE, '["percentage", "fixed_amount"]');

-- Add QR code generation settings to system_settings
INSERT INTO system_settings (setting_key, value, description) VALUES
('qr_code_size', '200', 'Default QR code image size in pixels'),
('qr_code_error_correction', 'M', 'QR code error correction level (L, M, Q, H)'),
('qr_code_margin', '4', 'QR code margin size'),
('nayax_integration_enabled', '1', 'Enable Nayax machine integration'),
('qr_code_expiry_hours', '720', 'QR code validity period in hours (30 days default)')
ON DUPLICATE KEY UPDATE 
value = VALUES(value),
description = VALUES(description); 