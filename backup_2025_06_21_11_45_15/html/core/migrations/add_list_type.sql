-- Add list_type column to voting_list_items table
ALTER TABLE voting_list_items
ADD COLUMN list_type ENUM('regular', 'vote_in', 'vote_out', 'showcase') NOT NULL DEFAULT 'regular' AFTER item_name;

-- Add index for better performance
CREATE INDEX idx_voting_list_items_type ON voting_list_items(list_type); 