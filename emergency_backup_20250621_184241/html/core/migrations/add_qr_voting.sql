-- Add qr_code_id to votes table
ALTER TABLE votes ADD COLUMN qr_code_id INT NULL AFTER business_id;

-- Add vote_type to qr_codes table
ALTER TABLE qr_codes ADD COLUMN vote_type ENUM('in', 'out') NULL AFTER type;

-- Add scan_count to qr_codes table
ALTER TABLE qr_codes ADD COLUMN scan_count INT NOT NULL DEFAULT 0 AFTER vote_type;

-- Add foreign key constraint if not exists
ALTER TABLE votes ADD CONSTRAINT IF NOT EXISTS fk_votes_qr_code FOREIGN KEY (qr_code_id) REFERENCES qr_codes(id) ON DELETE SET NULL; 