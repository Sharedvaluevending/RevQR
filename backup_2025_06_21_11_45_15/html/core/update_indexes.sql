-- Add indexes for machine-related queries
CREATE INDEX idx_qr_codes_machine_name ON qr_codes(machine_name);
CREATE INDEX idx_qr_codes_machine_location ON qr_codes(machine_location(255));
CREATE INDEX idx_qr_codes_scanned ON qr_codes(scanned_at);
CREATE INDEX idx_qr_codes_campaign ON qr_codes(campaign_id);

-- Add indexes for vote-related queries
CREATE INDEX idx_votes_qr_code ON votes(qr_code_id);
CREATE INDEX idx_votes_business ON votes(business_id);
CREATE INDEX idx_votes_item ON votes(item_id);
CREATE INDEX idx_votes_ip ON votes(ip_address);
CREATE INDEX idx_votes_date ON votes(voted_at); 