-- Business Promotional Ads System
-- Allows businesses to create promotional content for their enabled features

CREATE TABLE IF NOT EXISTS business_promotional_ads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    business_id INT NOT NULL,
    feature_type ENUM('spin_wheel', 'casino', 'pizza_tracker', 'general') NOT NULL,
    ad_title VARCHAR(100) NOT NULL,
    ad_description TEXT,
    ad_cta_text VARCHAR(50) DEFAULT 'Learn More',
    ad_cta_url VARCHAR(255),
    ad_image_url VARCHAR(255),
    background_color VARCHAR(7) DEFAULT '#007bff',
    text_color VARCHAR(7) DEFAULT '#ffffff',
    is_active BOOLEAN DEFAULT TRUE,
    show_on_vote_page BOOLEAN DEFAULT TRUE,
    show_on_dashboard BOOLEAN DEFAULT FALSE,
    priority INT DEFAULT 1,
    max_daily_views INT DEFAULT 1000,
    daily_views_count INT DEFAULT 0,
    total_views INT DEFAULT 0,
    total_clicks INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    INDEX idx_business_feature (business_id, feature_type),
    INDEX idx_active_ads (is_active, show_on_vote_page)
);

-- Add promotional settings to existing business feature tables
ALTER TABLE business_casino_participation 
ADD COLUMN show_promotional_ad BOOLEAN DEFAULT FALSE AFTER featured_promotion,
ADD COLUMN ad_budget_daily INT DEFAULT 0 AFTER show_promotional_ad;

-- Create table for business promotional settings for other features
CREATE TABLE IF NOT EXISTS business_promotional_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    business_id INT NOT NULL,
    spin_wheel_promo_enabled BOOLEAN DEFAULT FALSE,
    spin_wheel_promo_text VARCHAR(255),
    pizza_tracker_promo_enabled BOOLEAN DEFAULT FALSE,
    pizza_tracker_promo_text VARCHAR(255),
    general_promo_enabled BOOLEAN DEFAULT FALSE,
    general_promo_text VARCHAR(255),
    promotional_budget_daily DECIMAL(10,2) DEFAULT 0.00,
    promotional_budget_spent_today DECIMAL(10,2) DEFAULT 0.00,
    last_budget_reset DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    UNIQUE KEY unique_business_promo (business_id)
);

-- Create promotional ad views tracking
CREATE TABLE IF NOT EXISTS business_ad_views (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ad_id INT NOT NULL,
    user_id INT,
    viewer_ip VARCHAR(45),
    page_viewed VARCHAR(100),
    clicked BOOLEAN DEFAULT FALSE,
    view_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ad_id) REFERENCES business_promotional_ads(id) ON DELETE CASCADE,
    INDEX idx_ad_views (ad_id, view_date),
    INDEX idx_user_views (user_id, view_date),
    INDEX idx_daily_stats (view_date, clicked)
);

-- Insert sample promotional ads for existing businesses
INSERT INTO business_promotional_settings (business_id) 
SELECT id FROM businesses 
WHERE id NOT IN (SELECT business_id FROM business_promotional_settings);

-- Sample promotional ad for casino-enabled businesses
INSERT INTO business_promotional_ads (business_id, feature_type, ad_title, ad_description, ad_cta_text, ad_cta_url, background_color, text_color)
SELECT 
    bcp.business_id,
    'casino',
    CONCAT(b.name, ' Casino Bonus!'),
    COALESCE(bcp.featured_promotion, 'Play our slots and win big! Extra bonuses for location players.'),
    'Play Now',
    '/casino/index.php',
    '#dc3545',
    '#ffffff'
FROM business_casino_participation bcp
JOIN businesses b ON bcp.business_id = b.id
WHERE bcp.casino_enabled = TRUE 
AND bcp.business_id NOT IN (
    SELECT business_id FROM business_promotional_ads WHERE feature_type = 'casino'
); 