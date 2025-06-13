-- Update qr_codes table to use VARCHAR instead of ENUM
ALTER TABLE qr_codes 
    MODIFY COLUMN type VARCHAR(10) NOT NULL DEFAULT 'vote',
    DROP COLUMN vote_type; 