-- Update prices with margins between 40% and 55%
-- For items with cost < $1.00, use 55% margin
-- For items with cost >= $1.00 and < $2.00, use 50% margin
-- For items with cost >= $2.00, use 45% margin

UPDATE master_items 
SET suggested_price = ROUND(suggested_cost * 1.55, 2)
WHERE suggested_cost < 1.00 AND suggested_price > 0;

UPDATE master_items 
SET suggested_price = ROUND(suggested_cost * 1.50, 2)
WHERE suggested_cost >= 1.00 AND suggested_cost < 2.00 AND suggested_price > 0;

UPDATE master_items 
SET suggested_price = ROUND(suggested_cost * 1.45, 2)
WHERE suggested_cost >= 2.00 AND suggested_price > 0;

-- Special cases for premium items
UPDATE master_items 
SET suggested_price = ROUND(suggested_cost * 1.40, 2)
WHERE name LIKE '%Red Bull%' AND suggested_price > 0;

UPDATE master_items 
SET suggested_price = ROUND(suggested_cost * 1.40, 2)
WHERE name LIKE '%Monster%' AND suggested_price > 0;

UPDATE master_items SET 
    suggested_cost = 0.40,
    suggested_price = 0.73  -- 45% margin
WHERE name = 'Maruchan Ramen Cup (Instant Noodles)';

UPDATE master_items SET 
    suggested_cost = 0.80,
    suggested_price = 1.45  -- 45% margin
WHERE name LIKE 'Big Daddy Cookie%';

UPDATE master_items SET 
    suggested_cost = 0.64,
    suggested_price = 1.16  -- 45% margin
WHERE name = 'Ritz Minis';

UPDATE master_items SET 
    suggested_cost = 1.24,
    suggested_price = 2.25  -- 45% margin
WHERE name = 'Peach Fuzzies';

UPDATE master_items SET 
    suggested_cost = 1.13,
    suggested_price = 2.05  -- 45% margin
WHERE name = 'Pure Protein Bars';

UPDATE master_items SET 
    suggested_cost = 1.59,
    suggested_price = 2.89  -- 45% margin
WHERE name = 'Kirkland Protein Bars';

UPDATE master_items SET 
    suggested_cost = 0.50,
    suggested_price = 0.91  -- 45% margin
WHERE name = 'Kirkland Chewy Protein Bars';

UPDATE master_items SET 
    suggested_cost = 0.73,
    suggested_price = 1.33  -- 45% margin
WHERE name = 'Kirkland Nut Bars';

UPDATE master_items SET 
    suggested_cost = 0.46,
    suggested_price = 0.84  -- 45% margin
WHERE name = 'Nature Valley Bars';

UPDATE master_items SET 
    suggested_cost = 0.48,
    suggested_price = 0.87  -- 45% margin
WHERE name = 'Fiber One Bars';

UPDATE master_items SET 
    suggested_cost = 0.82,
    suggested_price = 1.49  -- 45% margin
WHERE name = 'Munchie Peanuts (Salted)';

UPDATE master_items SET 
    suggested_cost = 0.81,
    suggested_price = 1.47  -- 45% margin
WHERE name = 'Munchie Peanuts (Honey Roasted)';

UPDATE master_items SET 
    suggested_cost = 0.78,
    suggested_price = 1.42  -- 45% margin
WHERE name = 'Planters Peanuts (Salted)';

UPDATE master_items SET 
    suggested_cost = 0.83,
    suggested_price = 1.51  -- 45% margin
WHERE name = 'Planters Peanuts (Honey Roasted)';

UPDATE master_items SET 
    suggested_cost = 0.86,
    suggested_price = 1.56  -- 45% margin
WHERE name = 'Ruffles All Dressed';

UPDATE master_items SET 
    suggested_cost = 0.90,
    suggested_price = 1.64  -- 45% margin
WHERE name = 'Ruffles Sour Cream';

UPDATE master_items SET 
    suggested_cost = 0.90,
    suggested_price = 1.64  -- 45% margin
WHERE name = 'Doritos Nacho';

UPDATE master_items SET 
    suggested_cost = 0.84,
    suggested_price = 1.53  -- 45% margin
WHERE name = 'Lays Classic';

UPDATE master_items SET 
    suggested_cost = 0.84,
    suggested_price = 1.53  -- 45% margin
WHERE name = 'Miss Vickies Original';

UPDATE master_items SET 
    suggested_cost = 1.30,
    suggested_price = 2.36  -- 45% margin
WHERE name LIKE 'M&Ms%';

UPDATE master_items SET 
    suggested_cost = 1.24,
    suggested_price = 2.25  -- 45% margin
WHERE name = 'Crispy Crunch';

UPDATE master_items SET 
    suggested_cost = 1.42,
    suggested_price = 2.58  -- 45% margin
WHERE name = 'Aero';

UPDATE master_items SET 
    suggested_cost = 1.15,
    suggested_price = 2.09  -- 45% margin
WHERE name = 'Dairy Milk';

UPDATE master_items SET 
    suggested_cost = 1.21,
    suggested_price = 2.20  -- 45% margin
WHERE name = 'Starburst';

UPDATE master_items SET 
    suggested_cost = 1.24,
    suggested_price = 2.25  -- 45% margin
WHERE name = 'Mr. Big';

UPDATE master_items SET 
    suggested_cost = 1.03,
    suggested_price = 1.87  -- 45% margin
WHERE name = 'O Henry';

UPDATE master_items SET 
    suggested_cost = 1.80,
    suggested_price = 3.27  -- 45% margin
WHERE name = 'O Henry (Large)';

UPDATE master_items SET 
    suggested_cost = 1.17,
    suggested_price = 2.13  -- 45% margin
WHERE name LIKE 'Skittles%';

UPDATE master_items SET 
    suggested_cost = 2.04,
    suggested_price = 3.71  -- 45% margin
WHERE name = 'Kit Kat (Wafer Bar)';

UPDATE master_items SET 
    suggested_cost = 1.42,
    suggested_price = 2.58  -- 45% margin
WHERE name = 'Coffee Crisp';

UPDATE master_items SET 
    suggested_cost = 1.42,
    suggested_price = 2.58  -- 45% margin
WHERE name = 'Smarties';

UPDATE master_items SET 
    suggested_cost = 0.62,
    suggested_price = 1.13  -- 45% margin
WHERE name = 'Coke Zero';

UPDATE master_items SET 
    suggested_cost = 0.54,
    suggested_price = 0.98  -- 45% margin
WHERE name = 'Pepsi';

UPDATE master_items SET 
    suggested_cost = 0.54,
    suggested_price = 0.98  -- 45% margin
WHERE name = 'Diet Pepsi';

UPDATE master_items SET 
    suggested_cost = 1.42,
    suggested_price = 2.58  -- 45% margin
WHERE name LIKE '%Bottles';

UPDATE master_items SET 
    suggested_cost = 0.80,
    suggested_price = 1.45  -- 45% margin
WHERE name = 'Dole Orange Juice';

UPDATE master_items SET 
    suggested_cost = 0.75,
    suggested_price = 1.36  -- 45% margin
WHERE name = 'Oasis Apple Juice';

UPDATE master_items SET 
    suggested_cost = 1.00,
    suggested_price = 1.82  -- 45% margin
WHERE name = 'Gatorade';

UPDATE master_items SET 
    suggested_cost = 2.84,
    suggested_price = 5.16  -- 45% margin
WHERE name = 'Red Bull (Original)';

UPDATE master_items SET 
    suggested_cost = 1.59,
    suggested_price = 2.89  -- 45% margin
WHERE name IN ('Red Bull Zero', 'Red Bull Sugarfree');

UPDATE master_items SET 
    suggested_cost = 2.08,
    suggested_price = 3.78  -- 45% margin
WHERE name = 'Monster Energy (Original)';

UPDATE master_items SET 
    suggested_cost = 0.64,
    suggested_price = 1.16  -- 45% margin
WHERE name = 'Brisk Iced Tea';

UPDATE master_items SET 
    suggested_cost = 0.25,
    suggested_price = 0.45  -- 45% margin
WHERE name = 'Eska Natural Spring Water (Canadian)'; 