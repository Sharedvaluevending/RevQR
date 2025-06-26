-- Drop existing indexes
DROP INDEX IF EXISTS idx_votes_campaign ON votes;
DROP INDEX IF EXISTS idx_votes_item ON votes;
DROP INDEX IF EXISTS idx_votes_user ON votes;
DROP INDEX IF EXISTS idx_votes_type ON votes;

-- Drop and recreate votes table
DROP TABLE IF EXISTS votes;

CREATE TABLE votes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    campaign_id INT NOT NULL,
    item_id INT NOT NULL,
    vote_type ENUM('in', 'out') NOT NULL,
    user_id INT,
    ip_address VARCHAR(45),
    voted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (campaign_id) REFERENCES qr_campaigns(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Create indexes
CREATE INDEX idx_votes_campaign ON votes(campaign_id);
CREATE INDEX idx_votes_item ON votes(item_id);
CREATE INDEX idx_votes_user ON votes(user_id);
CREATE INDEX idx_votes_type ON votes(vote_type); 