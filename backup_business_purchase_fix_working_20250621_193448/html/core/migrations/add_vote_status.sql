-- Add vote_status column to items table
ALTER TABLE items 
ADD COLUMN vote_status ENUM('in', 'out', 'pending') DEFAULT 'pending' AFTER high_margin;

-- Add index for better performance
CREATE INDEX idx_items_vote_status ON items(vote_status); 