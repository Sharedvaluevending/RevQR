-- Winners Tracking Table
CREATE TABLE winners (
    id INT AUTO_INCREMENT PRIMARY KEY,
    campaign_id INT NOT NULL,
    item_id INT NOT NULL,
    win_type ENUM('in', 'out') NOT NULL,
    win_date DATE NOT NULL,
    vote_count INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (campaign_id) REFERENCES qr_campaigns(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
);

-- Campaign Items Relationship Table
CREATE TABLE campaign_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    campaign_id INT NOT NULL,
    item_id INT NOT NULL,
    added_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (campaign_id) REFERENCES qr_campaigns(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
    UNIQUE KEY unique_campaign_item (campaign_id, item_id)
);

-- Add indexes for better performance
CREATE INDEX idx_winners_campaign ON winners(campaign_id);
CREATE INDEX idx_winners_item ON winners(item_id);
CREATE INDEX idx_winners_date ON winners(win_date);
CREATE INDEX idx_campaign_items_campaign ON campaign_items(campaign_id);
CREATE INDEX idx_campaign_items_item ON campaign_items(item_id);

-- Add indexes for machine-related queries
CREATE INDEX idx_qr_codes_machine ON qr_codes(machine_name, machine_location);
CREATE INDEX idx_qr_codes_scanned ON qr_codes(scanned_at);
CREATE INDEX idx_qr_codes_campaign ON qr_codes(campaign_id);

-- Add indexes for vote-related queries
CREATE INDEX idx_votes_qr_code ON votes(qr_code_id);
CREATE INDEX idx_votes_business ON votes(business_id);
CREATE INDEX idx_votes_item ON votes(item_id);
CREATE INDEX idx_votes_ip ON votes(ip_address);
CREATE INDEX idx_votes_date ON votes(voted_at); 