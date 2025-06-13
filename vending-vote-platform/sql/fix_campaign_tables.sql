-- Update qr_campaigns table structure
ALTER TABLE qr_campaigns
    ADD COLUMN start_date DATE AFTER description,
    ADD COLUMN end_date DATE AFTER start_date,
    ADD COLUMN status ENUM('active', 'inactive', 'completed') DEFAULT 'active' AFTER end_date,
    ADD COLUMN campaign_type ENUM('vote_in', 'vote_out', 'spin', 'hunt') AFTER status,
    ADD COLUMN machine_id INT AFTER business_id,
    ADD FOREIGN KEY (machine_id) REFERENCES machines(id) ON DELETE CASCADE,
    MODIFY COLUMN type ENUM('vote', 'spin', 'hunt') NULL;

-- Create campaign_items table
CREATE TABLE IF NOT EXISTS campaign_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    campaign_id INT NOT NULL,
    item_id INT NOT NULL,
    position INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (campaign_id) REFERENCES qr_campaigns(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
    INDEX idx_campaign_items_campaign (campaign_id),
    INDEX idx_campaign_items_item (item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create views
CREATE OR REPLACE VIEW campaign_view AS
SELECT 
    qc.id,
    qc.business_id,
    qc.machine_id,
    qc.type,
    qc.name,
    qc.description,
    qc.start_date,
    qc.end_date,
    qc.status,
    qc.campaign_type,
    qc.is_active,
    qc.prize_logic,
    qc.created_at,
    b.name as business_name,
    m.name as machine_name
FROM qr_campaigns qc
LEFT JOIN businesses b ON qc.business_id = b.id
LEFT JOIN machines m ON qc.machine_id = m.id;

CREATE OR REPLACE VIEW campaign_items_view AS
SELECT 
    ci.id,
    ci.campaign_id,
    ci.item_id,
    ci.position,
    ci.created_at,
    i.name as item_name,
    i.type as item_type,
    i.price as item_price,
    i.list_type as item_list_type,
    i.status as item_status,
    qc.name as campaign_name,
    qc.type as campaign_type,
    qc.status as campaign_status
FROM campaign_items ci
JOIN items i ON ci.item_id = i.id
JOIN qr_campaigns qc ON ci.campaign_id = qc.id; 