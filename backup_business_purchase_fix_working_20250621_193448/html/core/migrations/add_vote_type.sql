-- Add vote_type column to votes table
ALTER TABLE votes ADD COLUMN vote_type ENUM('in', 'out') NOT NULL DEFAULT 'in' AFTER ip_address;

-- Update existing votes to have 'in' as default vote type
UPDATE votes SET vote_type = 'in' WHERE vote_type IS NULL; 