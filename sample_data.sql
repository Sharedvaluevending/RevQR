-- Sample data for AI recommendations testing

-- Voting list items with diverse pricing, stock levels, and margins
INSERT IGNORE INTO voting_list_items (voting_list_id, item_name, retail_price, cost_price, inventory, item_category, popularity) VALUES
-- Machine A (Main Campus) - Mix of items
(1, 'Coca-Cola', 2.50, 1.20, 2, 'Beverages', 'high'),     -- LOW STOCK
(1, 'Snickers', 3.00, 1.50, 1, 'Candy', 'high'),          -- CRITICAL LOW STOCK  
(1, 'Doritos', 2.75, 1.00, 15, 'Snacks', 'high'),         -- HIGH MARGIN (64%)
(1, 'Red Bull', 4.50, 2.80, 8, 'Energy', 'medium'),
(1, 'Water Bottle', 1.50, 0.30, 25, 'Beverages', 'high'), -- VERY HIGH MARGIN (80%)

-- Machine B (Library) - Different mix
(2, 'Pepsi', 2.40, 1.15, 12, 'Beverages', 'medium'),      -- UNDERPRICED vs Coca-Cola
(2, 'Kit-Kat', 2.80, 1.40, 3, 'Candy', 'medium'),         -- LOW STOCK
(2, 'Cheetos', 2.60, 1.10, 20, 'Snacks', 'medium'),       -- GOOD MARGIN (58%)
(2, 'Monster Energy', 4.75, 3.00, 6, 'Energy', 'low'),     -- OVERPRICED
(2, 'Coffee', 2.00, 0.50, 18, 'Beverages', 'high'),       -- HIGH MARGIN (75%)

-- Machine C (Student Center) - High traffic location
(3, 'Coca-Cola', 2.50, 1.20, 20, 'Beverages', 'high'),
(3, 'Snickers', 3.00, 1.50, 18, 'Candy', 'high'),
(3, 'Gatorade', 3.25, 1.80, 15, 'Sports', 'high'),
(3, 'Granola Bar', 2.50, 1.00, 12, 'Healthy', 'medium'),  -- HIGH MARGIN (60%)
(3, 'Pretzels', 2.25, 0.75, 25, 'Snacks', 'medium'),      -- HIGH MARGIN (67%)

-- Machine D (Gym) - Sports-focused
(4, 'Gatorade', 3.50, 1.80, 22, 'Sports', 'high'),        -- HIGHER PRICE for location
(4, 'Water Bottle', 1.75, 0.30, 30, 'Beverages', 'high'), -- PREMIUM LOCATION PRICING
(4, 'Red Bull', 5.00, 2.80, 14, 'Energy', 'high'),        -- PREMIUM PRICING
(4, 'Granola Bar', 2.75, 1.00, 16, 'Healthy', 'high'),
(4, 'Peanuts', 2.00, 0.60, 4, 'Snacks', 'low'),           -- LOW STOCK, UNPOPULAR

-- Machine E (Cafeteria) - Lower performance location
(5, 'Pepsi', 2.30, 1.15, 8, 'Beverages', 'low'),          -- UNDERPERFORMING
(5, 'Crackers', 1.80, 0.90, 2, 'Snacks', 'low'),          -- CRITICAL LOW STOCK, LOW MARGIN
(5, 'Monster Energy', 4.25, 3.00, 5, 'Energy', 'low'),    -- POOR PERFORMANCE
(5, 'Coffee', 1.85, 0.50, 6, 'Beverages', 'medium'),      -- UNDERPRICED
(5, 'Water Bottle', 1.25, 0.30, 3, 'Beverages', 'medium'); -- LOW STOCK, UNDERPRICED

-- Sales data with varying performance by machine and time
INSERT IGNORE INTO sales (business_id, machine_id, item_id, quantity, sale_price, sale_time) VALUES
-- Recent sales (last 30 days) - Main Campus performing well
(1, 1, 1, 2, 2.50, DATE_SUB(NOW(), INTERVAL 1 DAY)),   -- Coca-Cola
(1, 1, 3, 1, 3.00, DATE_SUB(NOW(), INTERVAL 1 DAY)),   -- Snickers
(1, 1, 5, 3, 2.75, DATE_SUB(NOW(), INTERVAL 2 DAYS)),  -- Doritos
(1, 1, 7, 1, 4.50, DATE_SUB(NOW(), INTERVAL 2 DAYS)),  -- Red Bull
(1, 1, 9, 4, 1.50, DATE_SUB(NOW(), INTERVAL 3 DAYS)),  -- Water

-- Library sales (moderate performance)
(1, 2, 2, 1, 2.40, DATE_SUB(NOW(), INTERVAL 1 DAY)),   -- Pepsi
(1, 2, 4, 2, 2.80, DATE_SUB(NOW(), INTERVAL 2 DAYS)),  -- Kit-Kat
(1, 2, 15, 3, 2.00, DATE_SUB(NOW(), INTERVAL 3 DAYS)), -- Coffee

-- Student Center sales (high performance)
(1, 3, 1, 5, 2.50, DATE_SUB(NOW(), INTERVAL 1 DAY)),   -- Coca-Cola
(1, 3, 3, 3, 3.00, DATE_SUB(NOW(), INTERVAL 1 DAY)),   -- Snickers
(1, 3, 10, 2, 3.25, DATE_SUB(NOW(), INTERVAL 2 DAYS)), -- Gatorade
(1, 3, 14, 4, 2.50, DATE_SUB(NOW(), INTERVAL 3 DAYS)), -- Granola Bar

-- Gym sales (premium pricing, good performance)
(1, 4, 10, 3, 3.50, DATE_SUB(NOW(), INTERVAL 1 DAY)),  -- Gatorade
(1, 4, 9, 6, 1.75, DATE_SUB(NOW(), INTERVAL 2 DAYS)),  -- Water
(1, 4, 7, 2, 5.00, DATE_SUB(NOW(), INTERVAL 3 DAYS)),  -- Red Bull

-- Cafeteria sales (poor performance)
(1, 5, 2, 1, 2.30, DATE_SUB(NOW(), INTERVAL 5 DAYS)),  -- Pepsi
(1, 5, 15, 1, 1.85, DATE_SUB(NOW(), INTERVAL 7 DAYS)); -- Coffee

-- Voting data with varying approval rates
INSERT IGNORE INTO votes (machine_id, item_id, vote_type, created_at) VALUES
-- Popular items (high approval - 70%+)
(1, 1, 'vote_in', DATE_SUB(NOW(), INTERVAL 1 DAY)),    -- Coca-Cola: popular
(1, 1, 'vote_in', DATE_SUB(NOW(), INTERVAL 2 DAYS)),
(1, 1, 'vote_in', DATE_SUB(NOW(), INTERVAL 3 DAYS)),
(1, 1, 'vote_in', DATE_SUB(NOW(), INTERVAL 4 DAYS)),
(1, 1, 'vote_in', DATE_SUB(NOW(), INTERVAL 5 DAYS)),
(1, 1, 'vote_in', DATE_SUB(NOW(), INTERVAL 6 DAYS)),
(1, 1, 'vote_in', DATE_SUB(NOW(), INTERVAL 7 DAYS)),
(1, 1, 'vote_out', DATE_SUB(NOW(), INTERVAL 8 DAYS)),
(1, 1, 'vote_out', DATE_SUB(NOW(), INTERVAL 9 DAYS)),
(1, 1, 'vote_in', DATE_SUB(NOW(), INTERVAL 10 DAYS)),  -- 80% approval

-- Snickers - also popular
(2, 3, 'vote_in', DATE_SUB(NOW(), INTERVAL 1 DAY)),
(2, 3, 'vote_in', DATE_SUB(NOW(), INTERVAL 2 DAYS)),
(2, 3, 'vote_in', DATE_SUB(NOW(), INTERVAL 3 DAYS)),
(2, 3, 'vote_in', DATE_SUB(NOW(), INTERVAL 4 DAYS)),
(2, 3, 'vote_in', DATE_SUB(NOW(), INTERVAL 5 DAYS)),
(2, 3, 'vote_in', DATE_SUB(NOW(), INTERVAL 6 DAYS)),
(2, 3, 'vote_in', DATE_SUB(NOW(), INTERVAL 7 DAYS)),
(2, 3, 'vote_out', DATE_SUB(NOW(), INTERVAL 8 DAYS)),
(2, 3, 'vote_out', DATE_SUB(NOW(), INTERVAL 9 DAYS)),
(2, 3, 'vote_out', DATE_SUB(NOW(), INTERVAL 10 DAYS)), -- 70% approval

-- Unpopular items (low approval - under 40%)
(3, 8, 'vote_out', DATE_SUB(NOW(), INTERVAL 1 DAY)),   -- Monster Energy: unpopular
(3, 8, 'vote_out', DATE_SUB(NOW(), INTERVAL 2 DAYS)),
(3, 8, 'vote_out', DATE_SUB(NOW(), INTERVAL 3 DAYS)),
(3, 8, 'vote_out', DATE_SUB(NOW(), INTERVAL 4 DAYS)),
(3, 8, 'vote_out', DATE_SUB(NOW(), INTERVAL 5 DAYS)),
(3, 8, 'vote_out', DATE_SUB(NOW(), INTERVAL 6 DAYS)),
(3, 8, 'vote_in', DATE_SUB(NOW(), INTERVAL 7 DAYS)),
(3, 8, 'vote_out', DATE_SUB(NOW(), INTERVAL 8 DAYS)),
(3, 8, 'vote_out', DATE_SUB(NOW(), INTERVAL 9 DAYS)),
(3, 8, 'vote_out', DATE_SUB(NOW(), INTERVAL 10 DAYS)), -- 10% approval

-- Crackers - also unpopular
(4, 13, 'vote_out', DATE_SUB(NOW(), INTERVAL 1 DAY)),
(4, 13, 'vote_out', DATE_SUB(NOW(), INTERVAL 2 DAYS)),
(4, 13, 'vote_out', DATE_SUB(NOW(), INTERVAL 3 DAYS)),
(4, 13, 'vote_out', DATE_SUB(NOW(), INTERVAL 4 DAYS)),
(4, 13, 'vote_out', DATE_SUB(NOW(), INTERVAL 5 DAYS)),
(4, 13, 'vote_in', DATE_SUB(NOW(), INTERVAL 6 DAYS)),
(4, 13, 'vote_in', DATE_SUB(NOW(), INTERVAL 7 DAYS)),
(4, 13, 'vote_out', DATE_SUB(NOW(), INTERVAL 8 DAYS)); -- 25% approval 