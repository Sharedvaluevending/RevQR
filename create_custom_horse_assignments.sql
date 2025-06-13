-- Custom Horse Assignments System
-- Date: 2025-01-XX
-- Purpose: Allow businesses to customize horse names and images for their items

-- Create table for custom horse assignments
CREATE TABLE IF NOT EXISTS custom_horse_assignments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    business_id INT NOT NULL,
    item_id INT NOT NULL, -- Links to voting_list_items
    custom_horse_name VARCHAR(100) NOT NULL,
    custom_horse_image_url VARCHAR(255) NOT NULL,
    custom_horse_color VARCHAR(7) NOT NULL DEFAULT '#8B4513', -- Brown horse color default
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_business_item_horse (business_id, item_id),
    INDEX idx_custom_horse_business (business_id),
    INDEX idx_custom_horse_item (item_id),
    
    FOREIGN KEY (item_id) REFERENCES voting_list_items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert some default horse names for inspiration
INSERT IGNORE INTO racing_system_settings (setting_key, setting_value, setting_type, description) VALUES
('default_horse_names', '["Thunder","Lightning","Storm","Blaze","Spirit","Champion","Victory","Star","Shadow","Fire","Wind","Diamond","Golden","Silver","Swift","Mighty","Brave","Royal","Noble","Magic"]', 'json', 'Default horse names for suggestions'),
('max_custom_horses_per_business', '50', 'number', 'Maximum custom horses a business can create'),
('horse_name_max_length', '30', 'number', 'Maximum length for horse names');

SELECT 'Custom Horse Assignments System Created Successfully!' as Status; 