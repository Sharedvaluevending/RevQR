-- Add qr_code_id column to votes table
ALTER TABLE votes ADD COLUMN qr_code_id INT NULL AFTER machine_id;

-- Add foreign key constraint
ALTER TABLE votes 
ADD CONSTRAINT fk_votes_qr_code 
FOREIGN KEY (qr_code_id) 
REFERENCES qr_codes(id) 
ON DELETE SET NULL;

-- Add index for better performance
CREATE INDEX idx_votes_qr_code ON votes(qr_code_id); 