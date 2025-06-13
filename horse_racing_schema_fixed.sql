-- =========================================
-- HORSE RACING SYSTEM DATABASE SCHEMA
-- Complete implementation for RevenueQR
-- =========================================

-- Business Race Management Table
CREATE TABLE IF NOT EXISTS business_races (
    id INT PRIMARY KEY AUTO_INCREMENT,
    business_id INT NOT NULL,
    race_name VARCHAR(255) NOT NULL,
    race_type ENUM('daily', '3day', 'weekly') NOT NULL,
    machine_id INT NOT NULL, -- Links to voting_lists (machines)
    start_time DATETIME NOT NULL,
    end_time DATETIME NOT NULL,
    prize_pool_qr_coins INT NOT NULL DEFAULT 0,
    min_bet_amount INT NOT NULL DEFAULT 10,
    max_bet_amount INT NOT NULL DEFAULT 500,
    status ENUM('pending', 'approved', 'active', 'completed', 'cancelled') NOT NULL DEFAULT 'pending',
    admin_approved_by INT NULL, -- Admin user who approved
    total_bets_placed INT DEFAULT 0,
    total_qr_coins_bet INT DEFAULT 0,
    winner_horse_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_business_races_status (status),
    INDEX idx_business_races_time (start_time, end_time),
    INDEX idx_business_races_business (business_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Race Horses (Items competing in races)
CREATE TABLE IF NOT EXISTS race_horses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    race_id INT NOT NULL,
    item_id INT NOT NULL, -- Links to voting_list_items
    horse_name VARCHAR(255) NOT NULL,
    slot_position VARCHAR(10) NOT NULL, -- A1, B2, C3, etc.
    jockey_avatar_url VARCHAR(255) NULL, -- Path to jockey image
    performance_weight DECIMAL(5,2) NOT NULL DEFAULT 1.00,
    current_odds DECIMAL(8,2) NOT NULL DEFAULT 2.50,
    final_position INT NULL, -- 1st, 2nd, 3rd, etc.
    final_time DECIMAL(10,3) NULL, -- Race completion time
    performance_data JSON NULL, -- Real vending data used
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_race_item (race_id, item_id),
    INDEX idx_race_horses_race (race_id),
    INDEX idx_race_horses_item (item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User Betting System
CREATE TABLE IF NOT EXISTS race_bets (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    race_id INT NOT NULL,
    horse_id INT NOT NULL,
    bet_amount_qr_coins INT NOT NULL,
    potential_winnings INT NOT NULL,
    actual_winnings INT DEFAULT 0,
    bet_placed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('active', 'won', 'lost', 'refunded') NOT NULL DEFAULT 'active',
    payout_processed_at TIMESTAMP NULL,
    
    INDEX idx_race_bets_user (user_id),
    INDEX idx_race_bets_race (race_id),
    INDEX idx_race_bets_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Race Results & Performance Data
CREATE TABLE IF NOT EXISTS race_results (
    id INT PRIMARY KEY AUTO_INCREMENT,
    race_id INT NOT NULL,
    horse_id INT NOT NULL,
    finish_position INT NOT NULL,
    finish_time DECIMAL(10,3) NOT NULL,
    performance_score DECIMAL(10,2) NOT NULL,
    sales_data_24h JSON NOT NULL, -- Real sales data used
    nayax_data_24h JSON NULL, -- Real Nayax data if available
    trend_data JSON NULL, -- 3-day trend analysis
    calculated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_race_horse_result (race_id, horse_id),
    INDEX idx_race_results_race (race_id),
    INDEX idx_race_results_position (finish_position)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User Racing Statistics
CREATE TABLE IF NOT EXISTS user_racing_stats (
    user_id INT PRIMARY KEY,
    total_races_participated INT DEFAULT 0,
    total_bets_placed INT DEFAULT 0,
    total_qr_coins_bet INT DEFAULT 0,
    total_qr_coins_won INT DEFAULT 0,
    win_rate DECIMAL(5,2) DEFAULT 0.00,
    favorite_horse_type VARCHAR(50) NULL, -- snack, drink, etc.
    biggest_win_amount INT DEFAULT 0,
    current_streak INT DEFAULT 0, -- Current win/loss streak
    best_streak INT DEFAULT 0, -- Best win streak ever
    last_race_participation TIMESTAMP NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_user_racing_winrate (win_rate),
    INDEX idx_user_racing_participation (last_race_participation)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Horse Performance Cache (for optimization)
CREATE TABLE IF NOT EXISTS horse_performance_cache (
    id INT PRIMARY KEY AUTO_INCREMENT,
    item_id INT NOT NULL,
    machine_id INT NOT NULL,
    cache_date DATE NOT NULL,
    units_sold_24h INT DEFAULT 0,
    profit_per_unit DECIMAL(10,2) DEFAULT 0,
    units_per_hour DECIMAL(8,2) DEFAULT 0,
    trend_delta DECIMAL(8,2) DEFAULT 0,
    performance_score DECIMAL(10,2) DEFAULT 0,
    nayax_sales_24h INT DEFAULT 0, -- From Nayax if available
    manual_sales_24h INT DEFAULT 0, -- From manual entries
    combined_revenue DECIMAL(10,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_item_date (item_id, cache_date),
    INDEX idx_performance_cache_date (cache_date),
    INDEX idx_performance_cache_score (performance_score)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Jockey Assignments (Horse-Jockey combinations)
CREATE TABLE IF NOT EXISTS jockey_assignments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    item_type ENUM('snack', 'drink', 'pizza', 'side', 'other') NOT NULL,
    jockey_name VARCHAR(100) NOT NULL,
    jockey_avatar_url VARCHAR(255) NOT NULL,
    jockey_color VARCHAR(7) NOT NULL, -- Hex color code
    win_rate DECIMAL(5,2) DEFAULT 0.00,
    total_races INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_type_jockey (item_type, jockey_name),
    INDEX idx_jockey_winrate (win_rate)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Racing System Settings
CREATE TABLE IF NOT EXISTS racing_system_settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value TEXT NOT NULL,
    setting_type ENUM('string', 'number', 'boolean', 'json') DEFAULT 'string',
    description TEXT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================
-- INSERT DEFAULT JOCKEY ASSIGNMENTS
-- =========================================

INSERT IGNORE INTO jockey_assignments (item_type, jockey_name, jockey_avatar_url, jockey_color) VALUES
('drink', 'Splash Rodriguez', '/horse-racing/assets/img/jockeys/jockey-drinks.png', '#007bff'),
('snack', 'Crunch Thompson', '/horse-racing/assets/img/jockeys/jockey-snacks.png', '#28a745'),
('pizza', 'Pepperoni Pete', '/horse-racing/assets/img/jockeys/jockey-pizza.png', '#dc3545'),
('side', 'Side-Kick Sam', '/horse-racing/assets/img/jockeys/jockey-sides.png', '#ffc107'),
('other', 'Wild Card Willie', '/horse-racing/assets/img/jockeys/jockey-other.png', '#6f42c1');

-- =========================================
-- ITEM-SPECIFIC JOCKEY ASSIGNMENTS
-- =========================================

-- New table for item-specific jockey assignments (businesses can customize individual items)
CREATE TABLE IF NOT EXISTS item_jockey_assignments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    business_id INT NOT NULL,
    item_id INT NOT NULL, -- Specific voting_list_item
    custom_jockey_name VARCHAR(100) NOT NULL,
    custom_jockey_avatar_url VARCHAR(255) NOT NULL,
    custom_jockey_color VARCHAR(7) NOT NULL DEFAULT '#007bff',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_business_item (business_id, item_id),
    INDEX idx_item_jockey_business (business_id),
    INDEX idx_item_jockey_item (item_id),
    
    FOREIGN KEY (item_id) REFERENCES voting_list_items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================
-- INSERT DEFAULT SYSTEM SETTINGS
-- =========================================

INSERT IGNORE INTO racing_system_settings (setting_key, setting_value, setting_type, description) VALUES
('max_horses_per_race', '8', 'number', 'Maximum number of horses allowed in a single race'),
('min_horses_per_race', '3', 'number', 'Minimum number of horses required for a race'),
('default_race_duration_hours', '24', 'number', 'Default race duration in hours'),
('performance_weight_sales', '0.6', 'number', 'Weight given to sales data in performance calculation'),
('performance_weight_votes', '0.3', 'number', 'Weight given to voting data in performance calculation'),
('performance_weight_random', '0.1', 'number', 'Random factor in performance calculation'),
('admin_approval_required', 'true', 'boolean', 'Whether races require admin approval'),
('max_bet_percentage_balance', '50', 'number', 'Max bet as percentage of user balance'),
('race_animation_duration_seconds', '60', 'number', 'How long the race animation takes'),
('payout_processing_delay_minutes', '5', 'number', 'Delay before processing payouts');

-- =========================================
-- CREATE FOLDER FOR JOCKEY ASSETS
-- =========================================

-- Create sample data for testing
INSERT IGNORE INTO business_races 
(business_id, race_name, race_type, machine_id, start_time, end_time, prize_pool_qr_coins, status) 
SELECT 
    1 as business_id,
    'Welcome Race - Test Your Luck!' as race_name,
    'daily' as race_type,
    1 as machine_id,
    DATE_ADD(NOW(), INTERVAL 1 HOUR) as start_time,
    DATE_ADD(NOW(), INTERVAL 25 HOUR) as end_time,
    1000 as prize_pool_qr_coins,
    'approved' as status
WHERE EXISTS (SELECT 1 FROM businesses WHERE id = 1)
AND EXISTS (SELECT 1 FROM voting_lists WHERE id = 1)
AND NOT EXISTS (SELECT 1 FROM business_races WHERE race_name = 'Welcome Race - Test Your Luck!');

-- =========================================
-- COMPLETION MESSAGE
-- =========================================

SELECT 'Horse Racing System Database Schema Created Successfully!' as Status; 