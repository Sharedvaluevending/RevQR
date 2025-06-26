-- First create machines table if it doesn't exist
CREATE TABLE IF NOT EXISTS machines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    business_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    qr_code_path VARCHAR(255),
    qr_type ENUM('static','dynamic','cross_promo','stackable') NOT NULL DEFAULT 'static',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_machines_business (business_id),
    FOREIGN KEY (business_id)
        REFERENCES businesses(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- Create campaigns table
CREATE TABLE IF NOT EXISTS campaigns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    business_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status ENUM('draft','active','paused','ended') NOT NULL DEFAULT 'draft',
    campaign_type ENUM('vote','promo','cross_promo') NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_campaigns_business (business_id),
    INDEX idx_campaigns_dates (start_date, end_date),
    INDEX idx_campaigns_status (status),
    FOREIGN KEY (business_id)
        REFERENCES businesses(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- Create campaign_items table (links campaigns to items)
CREATE TABLE IF NOT EXISTS campaign_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    campaign_id INT NOT NULL,
    item_id INT NOT NULL,
    machine_id INT NOT NULL,
    vote_type ENUM('vote_in','vote_out') NOT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_campaign_item (campaign_id, item_id, machine_id),
    INDEX idx_campaign_items_campaign (campaign_id),
    INDEX idx_campaign_items_item (item_id),
    INDEX idx_campaign_items_machine (machine_id),
    FOREIGN KEY (campaign_id)
        REFERENCES campaigns(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    FOREIGN KEY (item_id)
        REFERENCES items(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    FOREIGN KEY (machine_id)
        REFERENCES machines(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- Create qr_codes table
CREATE TABLE IF NOT EXISTS qr_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    campaign_id INT NOT NULL,
    machine_id INT NOT NULL,
    code VARCHAR(255) NOT NULL,
    qr_type ENUM('static','dynamic','cross_promo','stackable') NOT NULL,
    url VARCHAR(255) NOT NULL,
    image_path VARCHAR(255),
    scan_count INT NOT NULL DEFAULT 0,
    last_scanned_at TIMESTAMP NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_campaign_machine (campaign_id, machine_id),
    INDEX idx_qr_codes_campaign (campaign_id),
    INDEX idx_qr_codes_machine (machine_id),
    INDEX idx_qr_codes_status (status),
    FOREIGN KEY (campaign_id)
        REFERENCES campaigns(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    FOREIGN KEY (machine_id)
        REFERENCES machines(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- Create votes table
CREATE TABLE IF NOT EXISTS votes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    campaign_id INT NOT NULL,
    item_id INT NOT NULL,
    machine_id INT NOT NULL,
    voter_ip VARCHAR(45) NOT NULL,
    vote_type ENUM('vote_in','vote_out') NOT NULL,
    hour_of_day TINYINT NOT NULL,
    day_of_week TINYINT NOT NULL,
    user_agent TEXT,
    device_type VARCHAR(50),
    browser VARCHAR(50),
    os VARCHAR(50),
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_votes_campaign (campaign_id),
    INDEX idx_votes_item (item_id),
    INDEX idx_votes_machine (machine_id),
    INDEX idx_votes_ip (voter_ip),
    INDEX idx_votes_time (hour_of_day, day_of_week),
    UNIQUE KEY unique_vote_per_day (campaign_id, item_id, voter_ip, DATE(created_at)),
    FOREIGN KEY (campaign_id)
        REFERENCES campaigns(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    FOREIGN KEY (item_id)
        REFERENCES items(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    FOREIGN KEY (machine_id)
        REFERENCES machines(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- Create winners table
CREATE TABLE IF NOT EXISTS winners (
    id INT AUTO_INCREMENT PRIMARY KEY,
    campaign_id INT NOT NULL,
    item_id INT NOT NULL,
    machine_id INT NOT NULL,
    vote_type ENUM('vote_in','vote_out') NOT NULL,
    vote_count INT NOT NULL,
    week_start DATE NOT NULL,
    week_end DATE NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_winner (campaign_id, machine_id, vote_type, week_start),
    INDEX idx_winners_campaign (campaign_id),
    INDEX idx_winners_item (item_id),
    INDEX idx_winners_machine (machine_id),
    INDEX idx_winners_dates (week_start, week_end),
    FOREIGN KEY (campaign_id)
        REFERENCES campaigns(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    FOREIGN KEY (item_id)
        REFERENCES items(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    FOREIGN KEY (machine_id)
        REFERENCES machines(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- Create analytics table for detailed tracking
CREATE TABLE IF NOT EXISTS analytics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    machine_id INT NOT NULL,
    campaign_id INT NOT NULL,
    item_id INT NOT NULL,
    voter_ip VARCHAR(45) NOT NULL,
    vote_type ENUM('vote_in','vote_out') NOT NULL,
    hour_of_day TINYINT NOT NULL,
    day_of_week TINYINT NOT NULL,
    user_agent TEXT,
    device_type VARCHAR(50),
    browser VARCHAR(50),
    os VARCHAR(50),
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_analytics_machine (machine_id),
    INDEX idx_analytics_campaign (campaign_id),
    INDEX idx_analytics_item (item_id),
    INDEX idx_analytics_ip (voter_ip),
    INDEX idx_analytics_time (hour_of_day, day_of_week),
    INDEX idx_analytics_device (device_type),
    FOREIGN KEY (machine_id)
        REFERENCES machines(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    FOREIGN KEY (campaign_id)
        REFERENCES campaigns(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    FOREIGN KEY (item_id)
        REFERENCES items(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- Create user_profiles table for future account matching
CREATE TABLE IF NOT EXISTS user_profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    ip_address VARCHAR(45) NOT NULL,
    first_seen_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_seen_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    vote_count INT NOT NULL DEFAULT 0,
    favorite_machine_id INT DEFAULT NULL,
    favorite_item_id INT DEFAULT NULL,
    preferred_vote_type ENUM('vote_in','vote_out') DEFAULT NULL,
    preferred_hour_of_day TINYINT DEFAULT NULL,
    preferred_day_of_week TINYINT DEFAULT NULL,
    INDEX idx_user_profiles_ip (ip_address),
    INDEX idx_user_profiles_user (user_id),
    INDEX idx_user_profiles_machine (favorite_machine_id),
    INDEX idx_user_profiles_item (favorite_item_id),
    FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE,
    FOREIGN KEY (favorite_machine_id)
        REFERENCES machines(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE,
    FOREIGN KEY (favorite_item_id)
        REFERENCES items(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- Create dashboard_metrics table for quick access to analytics
CREATE TABLE IF NOT EXISTS dashboard_metrics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    machine_id INT NOT NULL,
    campaign_id INT NOT NULL,
    metric_type ENUM('hourly','daily','weekly','monthly') NOT NULL,
    period_start TIMESTAMP NOT NULL,
    period_end TIMESTAMP NOT NULL,
    total_votes INT NOT NULL DEFAULT 0,
    unique_voters INT NOT NULL DEFAULT 0,
    peak_hour TINYINT,
    peak_day TINYINT,
    most_voted_item_id INT,
    most_active_ip VARCHAR(45),
    device_breakdown JSON,
    browser_breakdown JSON,
    os_breakdown JSON,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_metric (machine_id, campaign_id, metric_type, period_start),
    INDEX idx_dashboard_metrics_machine (machine_id),
    INDEX idx_dashboard_metrics_campaign (campaign_id),
    INDEX idx_dashboard_metrics_period (period_start, period_end),
    FOREIGN KEY (machine_id)
        REFERENCES machines(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    FOREIGN KEY (campaign_id)
        REFERENCES campaigns(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    FOREIGN KEY (most_voted_item_id)
        REFERENCES items(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- Create some example machines
INSERT INTO machines (business_id, name, slug, description, qr_type) VALUES
(1, 'Main Floor Machine', 'main-floor', 'Main vending machine on the first floor', 'static'),
(1, 'Second Floor Machine', 'second-floor', 'Vending machine on the second floor', 'static'),
(1, 'Break Room Machine', 'break-room', 'Vending machine in the break room', 'static'),
(1, 'Lobby Machine', 'lobby', 'Vending machine in the lobby', 'static'),
(1, 'Office Machine', 'office', 'Vending machine in the office area', 'static');

-- Create an example campaign
INSERT INTO campaigns (business_id, name, description, start_date, end_date, status, campaign_type) VALUES
(1, 'Summer Snack Vote 2024', 'Vote for your favorite summer snacks!', '2024-06-01', '2024-08-31', 'draft', 'vote');

-- Create QR codes for the campaign
INSERT INTO qr_codes (campaign_id, machine_id, code, qr_type, url, status) VALUES
(1, 1, 'SUMMER-VOTE-MAIN', 'static', 'https://revenueqr.com/vote/SUMMER-VOTE-MAIN', 'active'),
(1, 2, 'SUMMER-VOTE-SECOND', 'static', 'https://revenueqr.com/vote/SUMMER-VOTE-SECOND', 'active'),
(1, 3, 'SUMMER-VOTE-BREAK', 'static', 'https://revenueqr.com/vote/SUMMER-VOTE-BREAK', 'active'),
(1, 4, 'SUMMER-VOTE-LOBBY', 'static', 'https://revenueqr.com/vote/SUMMER-VOTE-LOBBY', 'active'),
(1, 5, 'SUMMER-VOTE-OFFICE', 'static', 'https://revenueqr.com/vote/SUMMER-VOTE-OFFICE', 'active');

-- Rename old items table
RENAME TABLE items TO old_items;

-- Create new items table with RevenueQR schema + additional fields
CREATE TABLE items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    machine_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    type ENUM('snack','drink','pizza','side','other') NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    cost_price DECIMAL(10,2) NOT NULL,
    brand VARCHAR(100),
    popularity ENUM('high', 'medium', 'low') NOT NULL DEFAULT 'medium',
    shelf_life INT NOT NULL DEFAULT 180,
    is_seasonal BOOLEAN DEFAULT FALSE,
    is_imported BOOLEAN DEFAULT FALSE,
    is_healthy BOOLEAN DEFAULT FALSE,
    list_type ENUM('regular','in','vote_in','vote_out','showcase') NOT NULL DEFAULT 'regular',
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_items_machine (machine_id),
    INDEX idx_items_type (type),
    INDEX idx_items_popularity (popularity),
    INDEX idx_items_status (status),
    FOREIGN KEY (machine_id)
        REFERENCES machines(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- Disable foreign key checks for clean import
SET FOREIGN_KEY_CHECKS = 0;

-- Clear existing data
TRUNCATE TABLE items;
TRUNCATE TABLE categories;

-- Create categories
INSERT INTO categories (name, description) VALUES
('Candy and Chocolate Bars', 'A broad selection of chocolate bars, candies, and gum'),
('Chips and Savory Snacks', 'A diverse range of chips and salty snacks'),
('Cookies (Brand-Name & Generic)', 'Includes major cookie brands and generic/store-brand options'),
('Energy Drinks', 'Common energy drink brands and flavors'),
('Healthy Snacks', 'Better-for-you snack options'),
('Juices and Bottled Teas', 'Non-carbonated beverages'),
('Water and Flavored Water', 'Still and sparkling waters'),
('Protein and Meal Replacement Bars', 'High-protein bars and meal-substitute snacks'),
('Soft Drinks and Carbonated Beverages', 'A selection of sodas and fizzy drinks'),
('Odd or Unique Items', 'Unusual or specialty products');

-- Insert items
INSERT INTO items (machine_id, name, type, price, cost_price, brand, popularity, shelf_life, is_seasonal, is_imported, is_healthy, list_type, status) VALUES
-- Candy and Chocolate Bars
(1, '3 Musketeers Bar', 'snack', 1.25, 0.90, '3', 'medium', 180, 0, 0, 0, 'regular', 'active'),
(1, '5 Gum (Peppermint "Cobalt")', 'snack', 1.25, 0.90, '5', 'medium', 180, 0, 0, 0, 'regular', 'active'),
(1, '5 Gum (Spearmint "Rain")', 'snack', 1.25, 0.90, '5', 'medium', 180, 0, 0, 0, 'regular', 'active'),
(1, 'Aero (Milk Chocolate)', 'snack', 1.25, 0.90, 'Aero', 'medium', 180, 0, 0, 0, 'regular', 'active'),
(1, 'Aero (Mint)', 'snack', 1.25, 0.90, 'Aero', 'medium', 180, 0, 0, 0, 'regular', 'active'),

-- Chips and Savory Snacks
(1, 'Cheetos (Crunchy)', 'snack', 1.25, 0.90, 'Cheetos', 'high', 180, 0, 0, 0, 'regular', 'active'),
(1, 'Doritos (Nacho Cheese)', 'snack', 1.25, 0.90, 'Doritos', 'high', 180, 0, 0, 0, 'regular', 'active'),
(1, 'Fritos (Original)', 'snack', 1.25, 0.90, 'Fritos', 'medium', 180, 0, 0, 0, 'regular', 'active'),
(1, 'Lay\'s (Classic)', 'snack', 1.25, 0.90, 'Lay\'s', 'high', 180, 0, 0, 0, 'regular', 'active'),
(1, 'Pringles (Original)', 'snack', 1.25, 0.90, 'Pringles', 'high', 180, 0, 0, 0, 'regular', 'active'),

-- Cookies
(1, 'Chips Ahoy! (Chocolate Chip)', 'snack', 1.25, 0.90, 'Chips Ahoy!', 'high', 180, 0, 0, 0, 'regular', 'active'),
(1, 'Oreo (Original)', 'snack', 1.25, 0.90, 'Oreo', 'high', 180, 0, 0, 0, 'regular', 'active'),
(1, 'Pepperidge Farm Milano', 'snack', 1.25, 0.90, 'Pepperidge Farm', 'medium', 180, 0, 0, 0, 'regular', 'active'),
(1, 'Tate\'s Bake Shop (Chocolate Chip)', 'snack', 1.25, 0.90, 'Tate\'s', 'medium', 180, 0, 0, 0, 'regular', 'active'),

-- Energy Drinks
(1, 'Monster Energy (Original)', 'drink', 2.50, 1.75, 'Monster', 'high', 180, 0, 0, 0, 'regular', 'active'),
(1, 'Red Bull (Original)', 'drink', 2.50, 1.75, 'Red Bull', 'high', 180, 0, 0, 0, 'regular', 'active'),
(1, 'Rockstar (Original)', 'drink', 2.50, 1.75, 'Rockstar', 'medium', 180, 0, 0, 0, 'regular', 'active'),
(1, 'Bang Energy (Blue Razz)', 'drink', 2.50, 1.75, 'Bang', 'medium', 180, 0, 0, 0, 'regular', 'active'),

-- Healthy Snacks
(1, 'Kind Bar (Dark Chocolate Nuts & Sea Salt)', 'snack', 1.75, 1.25, 'Kind', 'medium', 180, 0, 0, 1, 'regular', 'active'),
(1, 'Larabar (Apple Pie)', 'snack', 1.75, 1.25, 'Larabar', 'medium', 180, 0, 0, 1, 'regular', 'active'),
(1, 'RXBAR (Chocolate Sea Salt)', 'snack', 1.75, 1.25, 'RXBAR', 'medium', 180, 0, 0, 1, 'regular', 'active'),
(1, 'That\'s It (Apple + Mango)', 'snack', 1.75, 1.25, 'That\'s It', 'medium', 180, 0, 0, 1, 'regular', 'active'),

-- Juices and Bottled Teas
(1, 'Honest Tea (Green Tea)', 'drink', 1.75, 1.25, 'Honest Tea', 'medium', 180, 0, 0, 1, 'regular', 'active'),
(1, 'Naked Juice (Green Machine)', 'drink', 2.00, 1.50, 'Naked', 'medium', 180, 0, 0, 1, 'regular', 'active'),
(1, 'Simply Orange', 'drink', 1.75, 1.25, 'Simply', 'high', 180, 0, 0, 1, 'regular', 'active'),
(1, 'Tropicana (Orange)', 'drink', 1.75, 1.25, 'Tropicana', 'high', 180, 0, 0, 1, 'regular', 'active'),

-- Odd or Unique Items
(1, 'Japanese Kit Kat (Matcha)', 'snack', 2.50, 1.75, 'Kit Kat', 'medium', 180, 0, 1, 0, 'regular', 'active'),
(1, 'Mexican Coca-Cola', 'drink', 2.00, 1.50, 'Coca-Cola', 'medium', 180, 0, 1, 0, 'regular', 'active'),
(1, 'Polish Chocolate (Wedel)', 'snack', 2.00, 1.50, 'Wedel', 'low', 180, 0, 1, 0, 'regular', 'active'),
(1, 'Turkish Delight', 'snack', 2.00, 1.50, 'Lokum', 'low', 180, 0, 1, 0, 'regular', 'active'),

-- Protein and Meal Replacement Bars
(1, 'Clif Bar (Chocolate Chip)', 'snack', 2.00, 1.50, 'Clif', 'medium', 180, 0, 0, 1, 'regular', 'active'),
(1, 'Quest Bar (Cookies & Cream)', 'snack', 2.50, 1.75, 'Quest', 'high', 180, 0, 0, 1, 'regular', 'active'),
(1, 'Think! (Brownie Crunch)', 'snack', 2.00, 1.50, 'Think!', 'medium', 180, 0, 0, 1, 'regular', 'active'),
(1, 'ZonePerfect (Fudge Graham)', 'snack', 2.00, 1.50, 'ZonePerfect', 'medium', 180, 0, 0, 1, 'regular', 'active'),

-- Soft Drinks and Carbonated Beverages
(1, 'Coca-Cola (Classic)', 'drink', 1.50, 1.00, 'Coca-Cola', 'high', 180, 0, 0, 0, 'regular', 'active'),
(1, 'Dr Pepper', 'drink', 1.50, 1.00, 'Dr Pepper', 'high', 180, 0, 0, 0, 'regular', 'active'),
(1, 'Mountain Dew', 'drink', 1.50, 1.00, 'Mountain Dew', 'high', 180, 0, 0, 0, 'regular', 'active'),
(1, 'Sprite', 'drink', 1.50, 1.00, 'Sprite', 'high', 180, 0, 0, 0, 'regular', 'active'),

-- Water and Flavored Water
(1, 'Dasani (Spring Water)', 'drink', 1.25, 0.75, 'Dasani', 'high', 365, 0, 0, 1, 'regular', 'active'),
(1, 'Smartwater', 'drink', 1.50, 1.00, 'Smartwater', 'medium', 365, 0, 0, 1, 'regular', 'active'),
(1, 'Vitamin Water (XXX)', 'drink', 1.75, 1.25, 'Vitamin Water', 'medium', 180, 0, 0, 1, 'regular', 'active'),
(1, 'Zephyrhills (Spring Water)', 'drink', 1.25, 0.75, 'Zephyrhills', 'high', 365, 0, 0, 1, 'regular', 'active');

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

-- Create a trigger to update user_profiles when votes are recorded
DELIMITER //
CREATE TRIGGER after_vote_insert
AFTER INSERT ON votes
FOR EACH ROW
BEGIN
    -- Update or insert user profile
    INSERT INTO user_profiles (ip_address, vote_count, favorite_machine_id, favorite_item_id)
    VALUES (NEW.voter_ip, 1, NEW.machine_id, NEW.item_id)
    ON DUPLICATE KEY UPDATE
        vote_count = vote_count + 1,
        last_seen_at = CURRENT_TIMESTAMP,
        favorite_machine_id = (
            SELECT machine_id 
            FROM votes 
            WHERE voter_ip = NEW.voter_ip 
            GROUP BY machine_id 
            ORDER BY COUNT(*) DESC 
            LIMIT 1
        ),
        favorite_item_id = (
            SELECT item_id 
            FROM votes 
            WHERE voter_ip = NEW.voter_ip 
            GROUP BY item_id 
            ORDER BY COUNT(*) DESC 
            LIMIT 1
        );

    -- Record detailed analytics
    INSERT INTO analytics (
        machine_id, campaign_id, item_id, voter_ip, vote_type,
        hour_of_day, day_of_week, user_agent, device_type, browser, os
    ) VALUES (
        NEW.machine_id, NEW.campaign_id, NEW.item_id, NEW.voter_ip, NEW.vote_type,
        NEW.hour_of_day, NEW.day_of_week, NEW.user_agent, NEW.device_type, NEW.browser, NEW.os
    );
END //
DELIMITER ;

-- Create a stored procedure to update dashboard metrics
DELIMITER //
CREATE PROCEDURE update_dashboard_metrics()
BEGIN
    -- Update hourly metrics
    INSERT INTO dashboard_metrics (
        machine_id, campaign_id, metric_type, period_start, period_end,
        total_votes, unique_voters, peak_hour, peak_day, most_voted_item_id,
        most_active_ip, device_breakdown, browser_breakdown, os_breakdown
    )
    SELECT 
        machine_id,
        campaign_id,
        'hourly',
        DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00'),
        DATE_FORMAT(created_at, '%Y-%m-%d %H:59:59'),
        COUNT(*) as total_votes,
        COUNT(DISTINCT voter_ip) as unique_voters,
        HOUR(created_at) as peak_hour,
        DAYOFWEEK(created_at) as peak_day,
        (
            SELECT item_id 
            FROM votes v2 
            WHERE v2.machine_id = v1.machine_id 
            AND v2.campaign_id = v1.campaign_id
            AND v2.created_at BETWEEN 
                DATE_FORMAT(v1.created_at, '%Y-%m-%d %H:00:00')
                AND DATE_FORMAT(v1.created_at, '%Y-%m-%d %H:59:59')
            GROUP BY item_id 
            ORDER BY COUNT(*) DESC 
            LIMIT 1
        ) as most_voted_item_id,
        (
            SELECT voter_ip 
            FROM votes v2 
            WHERE v2.machine_id = v1.machine_id 
            AND v2.campaign_id = v1.campaign_id
            AND v2.created_at BETWEEN 
                DATE_FORMAT(v1.created_at, '%Y-%m-%d %H:00:00')
                AND DATE_FORMAT(v1.created_at, '%Y-%m-%d %H:59:59')
            GROUP BY voter_ip 
            ORDER BY COUNT(*) DESC 
            LIMIT 1
        ) as most_active_ip,
        JSON_OBJECTAGG(device_type, COUNT(*)) as device_breakdown,
        JSON_OBJECTAGG(browser, COUNT(*)) as browser_breakdown,
        JSON_OBJECTAGG(os, COUNT(*)) as os_breakdown
    FROM votes v1
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
    GROUP BY machine_id, campaign_id, HOUR(created_at)
    ON DUPLICATE KEY UPDATE
        total_votes = VALUES(total_votes),
        unique_voters = VALUES(unique_voters),
        peak_hour = VALUES(peak_hour),
        peak_day = VALUES(peak_day),
        most_voted_item_id = VALUES(most_voted_item_id),
        most_active_ip = VALUES(most_active_ip),
        device_breakdown = VALUES(device_breakdown),
        browser_breakdown = VALUES(browser_breakdown),
        os_breakdown = VALUES(os_breakdown),
        updated_at = CURRENT_TIMESTAMP;
END //
DELIMITER ;

-- Create an event to update dashboard metrics every hour
CREATE EVENT update_metrics_hourly
ON SCHEDULE EVERY 1 HOUR
DO CALL update_dashboard_metrics();

-- 3) BUSINESSES (owners of QR-enabled vending machines)
CREATE TABLE IF NOT EXISTS `businesses` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `name`        VARCHAR(255) NOT NULL,
  `email`       VARCHAR(255) NOT NULL,
  `slug`        VARCHAR(100) NOT NULL UNIQUE,
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- 4) USERS: Admins & Vendor accounts
CREATE TABLE IF NOT EXISTS `users` (
  `id`            INT AUTO_INCREMENT PRIMARY KEY,
  `username`      VARCHAR(255)    NOT NULL UNIQUE,
  `email`         VARCHAR(255)    NOT NULL UNIQUE,
  `password_hash` VARCHAR(255)    NOT NULL,
  `role`          ENUM('admin','vendor') NOT NULL,
  `business_id`   INT             DEFAULT NULL,
  `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `chk_user_role_business`
    CHECK (
      (role = 'vendor' AND business_id IS NOT NULL)
      OR (role = 'admin'  AND business_id IS NULL)
    ),
  INDEX `idx_users_business` (`business_id`),
  INDEX `idx_users_email` (`email`),
  FOREIGN KEY (`business_id`)
    REFERENCES `businesses`(`id`)
    ON DELETE SET NULL
    ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- Add email column to users table if it doesn't exist
ALTER TABLE `users` 
ADD COLUMN IF NOT EXISTS `email` VARCHAR(255) NOT NULL UNIQUE AFTER `username`,
ADD INDEX IF NOT EXISTS `idx_users_email` (`email`);

-- 13) ANALYTICS: Aggregated metrics placeholder
CREATE TABLE IF NOT EXISTS `analytics` (
  `id`            INT AUTO_INCREMENT PRIMARY KEY,
  `machine_id`    INT             NOT NULL,
  `voter_ip`      VARCHAR(45)     NOT NULL,
  `hour_of_day`   TINYINT         NOT NULL,
  `day_of_week`   TINYINT         NOT NULL,
  `user_agent`    VARCHAR(255)    NULL,
  `device_type`   VARCHAR(50)     NULL,
  `browser`       VARCHAR(50)     NULL,
  `os`            VARCHAR(50)     NULL,
  `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_analytics_machine` (`machine_id`),
  INDEX `idx_analytics_time` (`hour_of_day`, `day_of_week`),
  FOREIGN KEY (`machine_id`)
    REFERENCES `machines`(`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- Create trigger to populate analytics when a vote is recorded
DELIMITER //
CREATE TRIGGER IF NOT EXISTS trg_vote_analytics
AFTER INSERT ON votes
FOR EACH ROW
BEGIN
    INSERT INTO analytics (
        machine_id,
        campaign_id,
        item_id,
        voter_ip,
        vote_type,
        hour_of_day,
        day_of_week,
        user_agent,
        device_type,
        browser,
        os
    )
    VALUES (
        NEW.machine_id,
        NEW.campaign_id,
        NEW.item_id,
        NEW.voter_ip,
        NEW.vote_type,
        HOUR(NEW.created_at),
        DAYOFWEEK(NEW.created_at),
        NEW.user_agent,
        NEW.device_type,
        NEW.browser,
        NEW.os
    );
END//
DELIMITER ;

-- Create campaign_machines table
CREATE TABLE IF NOT EXISTS `campaign_machines` (
  `id`            INT AUTO_INCREMENT PRIMARY KEY,
  `campaign_id`   INT             NOT NULL,
  `machine_id`    INT             NOT NULL,
  `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_campaign_machines_campaign` (`campaign_id`),
  INDEX `idx_campaign_machines_machine` (`machine_id`),
  UNIQUE KEY `uniq_campaign_machine` (`campaign_id`, `machine_id`),
  FOREIGN KEY (`campaign_id`)
    REFERENCES `qr_campaigns`(`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  FOREIGN KEY (`machine_id`)
    REFERENCES `machines`(`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- Update qr_codes table to reference campaigns
ALTER TABLE `qr_codes`
  ADD COLUMN `campaign_id` INT AFTER `machine_id`,
  ADD INDEX `idx_qr_codes_campaign` (`campaign_id`),
  ADD FOREIGN KEY (`campaign_id`)
    REFERENCES `qr_campaigns`(`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE;

-- Create master_items table for categorized items
CREATE TABLE IF NOT EXISTS `master_items` (
  `id`            INT AUTO_INCREMENT PRIMARY KEY,
  `name`          VARCHAR(255)    NOT NULL,
  `category`      VARCHAR(100)    NOT NULL,
  `type`          ENUM('snack','drink','pizza','side','other') NOT NULL,
  `brand`         VARCHAR(100),
  `suggested_price` DECIMAL(10,2) NOT NULL,
  `suggested_cost` DECIMAL(10,2)  NOT NULL,
  `popularity`    ENUM('high','medium','low') NOT NULL DEFAULT 'medium',
  `shelf_life`    INT NOT NULL DEFAULT 180,
  `is_seasonal`   BOOLEAN DEFAULT FALSE,
  `is_imported`   BOOLEAN DEFAULT FALSE,
  `is_healthy`    BOOLEAN DEFAULT FALSE,
  `status`        ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_master_items_category` (`category`),
  INDEX `idx_master_items_type` (`type`),
  INDEX `idx_master_items_status` (`status`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- Create item_mapping table to link master_items to items
CREATE TABLE IF NOT EXISTS `item_mapping` (
  `id`            INT AUTO_INCREMENT PRIMARY KEY,
  `master_item_id` INT NOT NULL,
  `item_id`       INT NOT NULL,
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_master_item` (`master_item_id`),
  UNIQUE KEY `uniq_item` (`item_id`),
  FOREIGN KEY (`master_item_id`)
    REFERENCES `master_items`(`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  FOREIGN KEY (`item_id`)
    REFERENCES `items`(`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- Create categories table
CREATE TABLE IF NOT EXISTS `categories` (
  `id`            INT AUTO_INCREMENT PRIMARY KEY,
  `name`          VARCHAR(100)    NOT NULL UNIQUE,
  `description`   TEXT,
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- Insert default categories
INSERT INTO categories (name, description) VALUES
('Candy and Chocolate Bars', 'Various candy and chocolate products'),
('Chips and Savory Snacks', 'Chips, pretzels, and other savory snacks'),
('Cookies', 'Various cookie products'),
('Energy Drinks', 'Energy drinks and supplements'),
('Healthy Snacks', 'Health-focused snack options'),
('Juices and Bottled Teas', 'Juices, teas, and other non-carbonated drinks'),
('Odd or Unique Items', 'Specialty and imported items'),
('Protein and Meal Replacement Bars', 'Protein bars and meal replacements'),
('Soft Drinks and Carbonated Beverages', 'Carbonated soft drinks and sodas'),
('Water and Flavored Water', 'Water and flavored water products'); 