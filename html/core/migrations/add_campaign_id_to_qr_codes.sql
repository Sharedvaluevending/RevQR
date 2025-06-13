-- Add campaign_id column to qr_codes table
ALTER TABLE qr_codes ADD COLUMN campaign_id INT NOT NULL AFTER machine_id;

-- Add foreign key constraint
ALTER TABLE qr_codes 
ADD CONSTRAINT fk_qr_codes_campaign 
FOREIGN KEY (campaign_id) 
REFERENCES qr_campaigns(id) 
ON DELETE CASCADE;

-- Add index for better performance
CREATE INDEX idx_qr_codes_campaign ON qr_codes(campaign_id); 