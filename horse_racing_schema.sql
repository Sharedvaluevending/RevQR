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
    
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    FOREIGN KEY (machine_id) REFERENCES voting_lists(id) ON DELETE CASCADE,
    
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
    
    FOREIGN KEY (race_id) REFERENCES business_races(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES voting_list_items(id) ON DELETE CASCADE,
    
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
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (race_id) REFERENCES business_races(id) ON DELETE CASCADE,
    FOREIGN KEY (horse_id) REFERENCES race_horses(id) ON DELETE CASCADE,
    
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
    
    FOREIGN KEY (race_id) REFERENCES business_races(id) ON DELETE CASCADE,
    FOREIGN KEY (horse_id) REFERENCES race_horses(id) ON DELETE CASCADE,
    
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
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    
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
    
    FOREIGN KEY (item_id) REFERENCES voting_list_items(id) ON DELETE CASCADE,
    FOREIGN KEY (machine_id) REFERENCES voting_lists(id) ON DELETE CASCADE,
    
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

INSERT INTO jockey_assignments (item_type, jockey_name, jockey_avatar_url, jockey_color) VALUES
('drink', 'Splash Rodriguez', '/horse-racing/assets/img/jockeys/jockey-drinks.png', '#007bff'),
('snack', 'Crunch Thompson', '/horse-racing/assets/img/jockeys/jockey-snacks.png', '#28a745'),
('pizza', 'Pepperoni Pete', '/horse-racing/assets/img/jockeys/jockey-pizza.png', '#dc3545'),
('side', 'Side-Kick Sam', '/horse-racing/assets/img/jockeys/jockey-sides.png', '#ffc107'),
('other', 'Wild Card Willie', '/horse-racing/assets/img/jockeys/jockey-other.png', '#6f42c1')
ON DUPLICATE KEY UPDATE 
    jockey_name = VALUES(jockey_name),
    jockey_avatar_url = VALUES(jockey_avatar_url);

-- =========================================
-- INSERT DEFAULT SYSTEM SETTINGS
-- =========================================

INSERT INTO racing_system_settings (setting_key, setting_value, setting_type, description) VALUES
('max_horses_per_race', '8', 'number', 'Maximum number of horses allowed in a single race'),
('min_horses_per_race', '3', 'number', 'Minimum number of horses required for a race'),
('default_race_duration_hours', '24', 'number', 'Default race duration in hours'),
('performance_weight_sales', '0.6', 'number', 'Weight given to sales data in performance calculation'),
('performance_weight_votes', '0.3', 'number', 'Weight given to voting data in performance calculation'),
('performance_weight_random', '0.1', 'number', 'Random factor in performance calculation'),
('admin_approval_required', 'true', 'boolean', 'Whether races require admin approval'),
('max_bet_percentage_balance', '50', 'number', 'Max bet as percentage of user balance'),
('race_animation_duration_seconds', '60', 'number', 'How long the race animation takes'),
('payout_processing_delay_minutes', '5', 'number', 'Delay before processing payouts')
ON DUPLICATE KEY UPDATE 
    setting_value = VALUES(setting_value),
    description = VALUES(description);

-- =========================================
-- TRIGGERS FOR AUTOMATIC STATS UPDATES
-- =========================================

DELIMITER //

-- Update user racing stats when bet is placed
CREATE TRIGGER IF NOT EXISTS update_user_stats_on_bet
AFTER INSERT ON race_bets
FOR EACH ROW
BEGIN
    INSERT INTO user_racing_stats (user_id, total_bets_placed, total_qr_coins_bet, last_race_participation)
    VALUES (NEW.user_id, 1, NEW.bet_amount_qr_coins, NOW())
    ON DUPLICATE KEY UPDATE
        total_bets_placed = total_bets_placed + 1,
        total_qr_coins_bet = total_qr_coins_bet + NEW.bet_amount_qr_coins,
        last_race_participation = NOW();
END//

-- Update user racing stats when bet wins/loses
CREATE TRIGGER IF NOT EXISTS update_user_stats_on_bet_result
AFTER UPDATE ON race_bets
FOR EACH ROW
BEGIN
    IF NEW.status != OLD.status AND NEW.status IN ('won', 'lost') THEN
        UPDATE user_racing_stats 
        SET 
            total_qr_coins_won = total_qr_coins_won + NEW.actual_winnings,
            win_rate = (
                SELECT (SUM(CASE WHEN status = 'won' THEN 1 ELSE 0 END) * 100.0 / COUNT(*))
                FROM race_bets 
                WHERE user_id = NEW.user_id AND status IN ('won', 'lost')
            ),
            biggest_win_amount = GREATEST(biggest_win_amount, NEW.actual_winnings),
            updated_at = NOW()
        WHERE user_id = NEW.user_id;
    END IF;
END//

-- Update race totals when bets are placed
CREATE TRIGGER IF NOT EXISTS update_race_totals_on_bet
AFTER INSERT ON race_bets
FOR EACH ROW
BEGIN
    UPDATE business_races 
    SET 
        total_bets_placed = total_bets_placed + 1,
        total_qr_coins_bet = total_qr_coins_bet + NEW.bet_amount_qr_coins
    WHERE id = NEW.race_id;
END//

DELIMITER ;

-- =========================================
-- PERFORMANCE CALCULATION FUNCTION
-- =========================================

DELIMITER //

CREATE FUNCTION IF NOT EXISTS calculate_horse_performance_score(
    p_item_id INT,
    p_days INT DEFAULT 1
) RETURNS DECIMAL(10,2)
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE v_units_sold INT DEFAULT 0;
    DECLARE v_profit_margin DECIMAL(10,2) DEFAULT 0;
    DECLARE v_units_per_hour DECIMAL(8,2) DEFAULT 0;
    DECLARE v_trend_delta DECIMAL(8,2) DEFAULT 0;
    DECLARE v_nayax_sales INT DEFAULT 0;
    DECLARE v_performance_score DECIMAL(10,2) DEFAULT 0;
    
    -- Get sales data
    SELECT COALESCE(SUM(quantity), 0) INTO v_units_sold
    FROM sales 
    WHERE item_id = p_item_id 
    AND sale_time >= DATE_SUB(NOW(), INTERVAL p_days DAY);
    
    -- Get profit margin
    SELECT (retail_price - COALESCE(cost_price, 0)) INTO v_profit_margin
    FROM voting_list_items 
    WHERE id = p_item_id;
    
    -- Calculate units per hour
    SET v_units_per_hour = v_units_sold / (p_days * 24);
    
    -- Get Nayax data if available (placeholder for now)
    SET v_nayax_sales = 0;
    
    -- Calculate performance score using the formula
    SET v_performance_score = (
        (v_units_sold * 1.0) +
        (v_profit_margin * 20.0) +
        (v_units_per_hour * 10.0) +
        (v_trend_delta * 15.0) +
        (v_nayax_sales * 0.5)
    );
    
    RETURN GREATEST(0, v_performance_score);
END//

DELIMITER ;

-- =========================================
-- INDEXES FOR PERFORMANCE
-- =========================================

-- Additional indexes for racing queries
ALTER TABLE business_races ADD INDEX idx_active_races (status, start_time, end_time);
ALTER TABLE race_bets ADD INDEX idx_user_active_bets (user_id, status);
ALTER TABLE race_horses ADD INDEX idx_race_performance (race_id, performance_weight);

-- =========================================
-- COMMENTS FOR DOCUMENTATION
-- =========================================

ALTER TABLE business_races COMMENT = 'Horse racing events created by businesses';
ALTER TABLE race_horses COMMENT = 'Items competing as horses in races';
ALTER TABLE race_bets COMMENT = 'User bets placed on horse races';
ALTER TABLE race_results COMMENT = 'Final results and performance data for races';
ALTER TABLE user_racing_stats COMMENT = 'User statistics and achievements in racing';
ALTER TABLE horse_performance_cache COMMENT = 'Cached performance data for optimization';
ALTER TABLE jockey_assignments COMMENT = 'Jockey avatars assigned to different item types';
ALTER TABLE racing_system_settings COMMENT = 'System-wide configuration for racing';

-- =========================================
-- COMPLETION MESSAGE
-- =========================================

-- Select a success message
SELECT 'Horse Racing System Database Schema Created Successfully!' as Status; 