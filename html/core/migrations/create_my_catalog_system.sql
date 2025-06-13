-- My Catalog System Database Schema
-- This creates tables for advanced catalog management and analytics

-- User's personal catalog items
CREATE TABLE IF NOT EXISTS user_catalog_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    master_item_id INT NOT NULL,
    custom_name VARCHAR(255),
    custom_price DECIMAL(10,2),
    custom_cost DECIMAL(10,2),
    target_margin DECIMAL(5,2),
    notes TEXT,
    priority_level ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    performance_rating DECIMAL(3,2) DEFAULT 0.00,
    last_sale_date DATE,
    total_sales INT DEFAULT 0,
    total_revenue DECIMAL(10,2) DEFAULT 0.00,
    is_favorite BOOLEAN DEFAULT FALSE,
    is_seasonal BOOLEAN DEFAULT FALSE,
    season_start_month INT,
    season_end_month INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (master_item_id) REFERENCES master_items(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_item (user_id, master_item_id),
    INDEX idx_user_performance (user_id, performance_rating DESC),
    INDEX idx_user_priority (user_id, priority_level),
    INDEX idx_user_favorites (user_id, is_favorite),
    INDEX idx_seasonal (is_seasonal, season_start_month, season_end_month)
);

-- Catalog analytics and metrics
CREATE TABLE IF NOT EXISTS catalog_analytics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    catalog_item_id INT NOT NULL,
    metric_date DATE NOT NULL,
    sales_count INT DEFAULT 0,
    revenue DECIMAL(10,2) DEFAULT 0.00,
    profit DECIMAL(10,2) DEFAULT 0.00,
    margin_percentage DECIMAL(5,2) DEFAULT 0.00,
    vote_count INT DEFAULT 0,
    vote_percentage DECIMAL(5,2) DEFAULT 0.00,
    promo_usage INT DEFAULT 0,
    combo_usage INT DEFAULT 0,
    customer_rating DECIMAL(3,2) DEFAULT 0.00,
    inventory_turnover DECIMAL(5,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (catalog_item_id) REFERENCES user_catalog_items(id) ON DELETE CASCADE,
    UNIQUE KEY unique_daily_metric (catalog_item_id, metric_date),
    INDEX idx_user_date (user_id, metric_date),
    INDEX idx_performance (user_id, revenue DESC, profit DESC)
);

-- Catalog categories and tags
CREATE TABLE IF NOT EXISTS catalog_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    color_code VARCHAR(7) DEFAULT '#007bff',
    sort_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_category (user_id, name),
    INDEX idx_user_active (user_id, is_active, sort_order)
);

-- Item tags for advanced filtering
CREATE TABLE IF NOT EXISTS catalog_item_tags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    catalog_item_id INT NOT NULL,
    tag_name VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (catalog_item_id) REFERENCES user_catalog_items(id) ON DELETE CASCADE,
    UNIQUE KEY unique_item_tag (catalog_item_id, tag_name),
    INDEX idx_tag_name (tag_name)
);

-- Price history tracking
CREATE TABLE IF NOT EXISTS catalog_price_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    catalog_item_id INT NOT NULL,
    old_price DECIMAL(10,2),
    new_price DECIMAL(10,2),
    old_cost DECIMAL(10,2),
    new_cost DECIMAL(10,2),
    change_reason VARCHAR(255),
    changed_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (catalog_item_id) REFERENCES user_catalog_items(id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES users(id),
    INDEX idx_item_date (catalog_item_id, created_at DESC)
);

-- Promotional campaigns for catalog items
CREATE TABLE IF NOT EXISTS catalog_promotions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    discount_type ENUM('percentage', 'fixed_amount', 'buy_x_get_y') NOT NULL,
    discount_value DECIMAL(10,2) NOT NULL,
    min_quantity INT DEFAULT 1,
    max_usage INT,
    current_usage INT DEFAULT 0,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_active (user_id, is_active),
    INDEX idx_date_range (start_date, end_date)
);

-- Link promotions to catalog items
CREATE TABLE IF NOT EXISTS catalog_promotion_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    promotion_id INT NOT NULL,
    catalog_item_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (promotion_id) REFERENCES catalog_promotions(id) ON DELETE CASCADE,
    FOREIGN KEY (catalog_item_id) REFERENCES user_catalog_items(id) ON DELETE CASCADE,
    UNIQUE KEY unique_promo_item (promotion_id, catalog_item_id)
);

-- Combo deals and bundles
CREATE TABLE IF NOT EXISTS catalog_combos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    combo_price DECIMAL(10,2) NOT NULL,
    savings_amount DECIMAL(10,2) DEFAULT 0.00,
    min_items INT DEFAULT 2,
    max_items INT DEFAULT 10,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_active (user_id, is_active)
);

-- Items in combo deals
CREATE TABLE IF NOT EXISTS catalog_combo_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    combo_id INT NOT NULL,
    catalog_item_id INT NOT NULL,
    quantity INT DEFAULT 1,
    is_required BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (combo_id) REFERENCES catalog_combos(id) ON DELETE CASCADE,
    FOREIGN KEY (catalog_item_id) REFERENCES user_catalog_items(id) ON DELETE CASCADE,
    UNIQUE KEY unique_combo_item (combo_id, catalog_item_id)
);

-- Performance benchmarks and goals
CREATE TABLE IF NOT EXISTS catalog_benchmarks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    catalog_item_id INT,
    benchmark_type ENUM('sales_target', 'revenue_target', 'margin_target', 'vote_target') NOT NULL,
    target_value DECIMAL(10,2) NOT NULL,
    current_value DECIMAL(10,2) DEFAULT 0.00,
    target_period ENUM('daily', 'weekly', 'monthly', 'quarterly', 'yearly') NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    is_achieved BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (catalog_item_id) REFERENCES user_catalog_items(id) ON DELETE CASCADE,
    INDEX idx_user_period (user_id, target_period, start_date, end_date),
    INDEX idx_achievement (user_id, is_achieved)
);

-- Insert default categories
INSERT IGNORE INTO catalog_categories (user_id, name, description, color_code, sort_order) 
SELECT DISTINCT u.id, 'High Performers', 'Items with excellent sales and margins', '#28a745', 1
FROM users u WHERE u.role = 'business';

INSERT IGNORE INTO catalog_categories (user_id, name, description, color_code, sort_order) 
SELECT DISTINCT u.id, 'Seasonal Items', 'Products that perform well during specific seasons', '#ffc107', 2
FROM users u WHERE u.role = 'business';

INSERT IGNORE INTO catalog_categories (user_id, name, description, color_code, sort_order) 
SELECT DISTINCT u.id, 'Promotional Candidates', 'Items suitable for promotions and discounts', '#17a2b8', 3
FROM users u WHERE u.role = 'business';

INSERT IGNORE INTO catalog_categories (user_id, name, description, color_code, sort_order) 
SELECT DISTINCT u.id, 'Combo Opportunities', 'Items that work well in bundle deals', '#6f42c1', 4
FROM users u WHERE u.role = 'business';

INSERT IGNORE INTO catalog_categories (user_id, name, description, color_code, sort_order) 
SELECT DISTINCT u.id, 'Underperformers', 'Items that need attention or optimization', '#dc3545', 5
FROM users u WHERE u.role = 'business'; 