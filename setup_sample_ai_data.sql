-- Setup sample data for enhanced AI insights testing

-- Enable casino for business 1
INSERT INTO business_casino_participation (business_id, casino_enabled, featured_promotion, location_bonus_multiplier, show_promotional_ad) 
VALUES (1, 1, 'Play our casino for 20% bonus rewards!', 1.2, 1) 
ON DUPLICATE KEY UPDATE casino_enabled = 1, featured_promotion = 'Play our casino for 20% bonus rewards!', location_bonus_multiplier = 1.2, show_promotional_ad = 1;

-- Add some casino revenue
INSERT INTO business_casino_revenue (business_id, date_period, total_plays_at_location, total_bets_at_location, revenue_share_earned) VALUES 
(1, CURDATE() - INTERVAL 1 DAY, 15, 150.00, 25.50),
(1, CURDATE() - INTERVAL 2 DAY, 12, 125.00, 18.75),
(1, CURDATE() - INTERVAL 3 DAY, 18, 180.00, 32.25);

-- Add promotional ads
INSERT INTO business_promotional_ads (business_id, feature_type, ad_title, ad_description, ad_cta_text, is_active, show_on_vote_page) VALUES
(1, 'casino', 'Casino Bonus!', 'Play our casino for 20% bonus rewards!', 'Play Now', 1, 1),
(1, 'general', 'Visit Our Location', 'Fresh snacks and drinks available!', 'Visit Us', 1, 1);

-- Add ad views
INSERT INTO business_ad_views (ad_id, user_id, page_viewed, clicked, view_date) VALUES
(1, 1, 'vote', 1, CURDATE()),
(1, 2, 'vote', 0, CURDATE()),
(1, 3, 'vote', 1, CURDATE()),
(2, 1, 'dashboard', 0, CURDATE()),
(2, 2, 'dashboard', 1, CURDATE());

-- Add a spin wheel
INSERT INTO spin_wheels (business_id, wheel_name, description, is_active) VALUES
(1, 'Lucky Snack Wheel', 'Spin for prizes and discounts!', 1);

-- Add spin results
INSERT INTO spin_results (spin_wheel_id, user_id, result_type, prize_value) VALUES
(1, 1, 'win', 5.00),
(1, 2, 'lose', 0.00),
(1, 3, 'win', 2.50),
(1, 1, 'lose', 0.00);

-- Add a pizza tracker
INSERT INTO pizza_trackers (business_id, name, description, revenue_goal, current_revenue, is_active) VALUES
(1, 'Office Pizza Fund', 'Help us reach our pizza goal!', 100.00, 75.50, 1);

-- Add QR codes
INSERT INTO qr_codes (business_id, qr_type, machine_id) VALUES
(1, 'casino', NULL),
(1, 'voting', 1),
(1, 'spin_wheel', NULL);

-- Add QR scans
INSERT INTO qr_scans (qr_code_id, user_id, scan_date) VALUES
(1, 1, CURDATE()),
(1, 2, CURDATE() - INTERVAL 1 DAY),
(2, 3, CURDATE()),
(3, 1, CURDATE() - INTERVAL 2 DAY);

-- Add some sales data
INSERT INTO sales (business_id, machine_id, item_id, quantity, sale_price, sale_time) VALUES
(1, 1, 1, 2, 1.50, NOW() - INTERVAL 1 DAY),
(1, 1, 2, 1, 2.00, NOW() - INTERVAL 2 DAY),
(1, 1, 3, 3, 1.25, NOW() - INTERVAL 3 DAY);

-- Add some voting data
INSERT INTO votes (machine_id, item_id, vote_type, user_id) VALUES
(1, 1, 'vote_in', 1),
(1, 1, 'vote_in', 2),
(1, 2, 'vote_out', 3),
(1, 3, 'vote_in', 1);

SELECT 'Sample data setup completed successfully!' as status; 