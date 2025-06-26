-- Migration: Add Pizza Tracker System
-- Date: 2025-06-02
-- Description: Adds pizza tracker functionality for revenue progress tracking

-- Start transaction for safety
START TRANSACTION;

-- 1. Create pizza_trackers table
CREATE TABLE pizza_trackers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    business_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    tracker_type ENUM('campaign', 'machine', 'qr_standalone') DEFAULT 'campaign',
    campaign_id INT NULL,
    machine_id INT NULL,
    qr_code_id INT NULL,
    
    -- Simple Financial Settings (no milestone complexity)
    pizza_cost DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    revenue_goal DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    current_revenue DECIMAL(10,2) DEFAULT 0.00,
    
    -- Progress Tracking
    completion_count INT DEFAULT 0,
    last_completion_date TIMESTAMP NULL,
    
    -- Status
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Indexes
    INDEX idx_tracker_business (business_id),
    INDEX idx_tracker_campaign (campaign_id),
    INDEX idx_tracker_machine (machine_id),
    INDEX idx_tracker_qr (qr_code_id),
    INDEX idx_tracker_active (is_active),
    
    -- Foreign Keys
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE SET NULL,
    FOREIGN KEY (qr_code_id) REFERENCES qr_codes(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Create pizza_tracker_updates table
CREATE TABLE pizza_tracker_updates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tracker_id INT NOT NULL,
    revenue_amount DECIMAL(10,2) NOT NULL,
    update_source ENUM('manual', 'sales_sync') DEFAULT 'manual',
    notes TEXT,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Indexes
    INDEX idx_updates_tracker (tracker_id),
    INDEX idx_updates_date (created_at),
    INDEX idx_updates_source (update_source),
    
    -- Foreign Keys
    FOREIGN KEY (tracker_id) REFERENCES pizza_trackers(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Create pizza_tracker_clicks table for analytics
CREATE TABLE pizza_tracker_clicks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tracker_id INT NOT NULL,
    campaign_id INT NULL,
    source_page ENUM('voting_page', 'qr_direct', 'campaign_page') DEFAULT 'voting_page',
    ip_address VARCHAR(45),
    user_agent TEXT,
    referrer_url TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Indexes
    INDEX idx_clicks_tracker (tracker_id),
    INDEX idx_clicks_campaign (campaign_id),
    INDEX idx_clicks_date (created_at),
    INDEX idx_clicks_source (source_page),
    
    -- Foreign Keys
    FOREIGN KEY (tracker_id) REFERENCES pizza_trackers(id) ON DELETE CASCADE,
    FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Add pizza_tracker to QR code types
ALTER TABLE qr_codes 
MODIFY qr_type ENUM(
    'static',
    'dynamic', 
    'dynamic_voting',
    'dynamic_vending',
    'machine_sales',
    'promotion',
    'spin_wheel',
    'pizza_tracker'
) NOT NULL;

-- Commit transaction
COMMIT;

-- Verification
SELECT 'Pizza Tracker migration completed successfully' as status;
SELECT COUNT(*) as pizza_trackers_table_created FROM information_schema.tables 
WHERE table_schema = 'revenueqr' AND table_name = 'pizza_trackers'; 