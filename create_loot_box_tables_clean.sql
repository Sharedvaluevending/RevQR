-- Create tables for Fortnite-style Loot Box System

-- Table to track loot box openings
CREATE TABLE IF NOT EXISTS loot_box_openings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    purchase_id INT NOT NULL,
    qr_store_item_id INT NOT NULL,
    rewards_json JSON NOT NULL,
    total_rewards INT NOT NULL DEFAULT 0,
    opened_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_purchase_id (purchase_id),
    INDEX idx_opened_at (opened_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (purchase_id) REFERENCES user_qr_store_purchases(id) ON DELETE CASCADE,
    FOREIGN KEY (qr_store_item_id) REFERENCES qr_store_items(id) ON DELETE CASCADE
);

-- Table for user spin bonuses from loot boxes
CREATE TABLE IF NOT EXISTS user_spin_bonuses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    source VARCHAR(50) NOT NULL DEFAULT 'manual',
    spins_awarded INT NOT NULL DEFAULT 0,
    spins_used INT NOT NULL DEFAULT 0,
    spins_remaining INT GENERATED ALWAYS AS (spins_awarded - spins_used) STORED,
    expires_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_source (source),
    INDEX idx_expires_at (expires_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Table for user active boosts from loot boxes
CREATE TABLE IF NOT EXISTS user_active_boosts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    boost_type VARCHAR(100) NOT NULL,
    boost_value JSON NOT NULL,
    source VARCHAR(50) NOT NULL DEFAULT 'manual',
    source_id INT,
    is_active TINYINT(1) DEFAULT 1,
    expires_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_boost_type (boost_type),
    INDEX idx_source (source),
    INDEX idx_expires_at (expires_at),
    INDEX idx_active_expires (is_active, expires_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

SELECT 'Loot Box Tables Created Successfully!' as Status; 