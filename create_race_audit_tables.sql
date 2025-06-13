-- Race Audit Log Table
CREATE TABLE IF NOT EXISTS race_audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    race_id INT NOT NULL,
    business_id INT NOT NULL,
    action_type ENUM('created', 'started', 'finished', 'cancelled', 'modified') NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_race_audit_race_id (race_id),
    INDEX idx_race_audit_business_id (business_id),
    INDEX idx_race_audit_action_type (action_type),
    FOREIGN KEY (race_id) REFERENCES race_events(id) ON DELETE CASCADE,
    FOREIGN KEY (business_id) REFERENCES business_accounts(id) ON DELETE CASCADE
);

-- Business Wallet Transactions Table (if not exists)
CREATE TABLE IF NOT EXISTS business_wallet_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    business_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    transaction_type ENUM('deposit', 'withdrawal', 'refund', 'prize_pool', 'commission') NOT NULL,
    description TEXT,
    related_race_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_bwt_business_id (business_id),
    INDEX idx_bwt_type (transaction_type),
    INDEX idx_bwt_race_id (related_race_id),
    FOREIGN KEY (business_id) REFERENCES business_accounts(id) ON DELETE CASCADE,
    FOREIGN KEY (related_race_id) REFERENCES race_events(id) ON DELETE SET NULL
);

-- Add cancelled status to race_bets if not exists
ALTER TABLE race_bets 
MODIFY COLUMN status ENUM('active', 'won', 'lost', 'cancelled') DEFAULT 'active';

-- Add cancelled status to race_events if not exists  
ALTER TABLE race_events 
MODIFY COLUMN status ENUM('upcoming', 'active', 'finished', 'cancelled') DEFAULT 'upcoming';

-- Ensure QR coin transactions table exists
CREATE TABLE IF NOT EXISTS qr_coin_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount INT NOT NULL,
    transaction_type ENUM('bet', 'win', 'refund', 'purchase', 'deposit', 'withdrawal') NOT NULL,
    description TEXT,
    related_race_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_qr_trans_user_id (user_id),
    INDEX idx_qr_trans_type (transaction_type),
    INDEX idx_qr_trans_race_id (related_race_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (related_race_id) REFERENCES race_events(id) ON DELETE SET NULL
); 