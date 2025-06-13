-- Update all prices by adding $1.50 to cost price for retail price
UPDATE master_items 
SET suggested_price = suggested_cost + 1.50
WHERE suggested_price > 0;

-- Update candy and chocolate bars
UPDATE master_items 
SET suggested_price = 2.75,  -- 1.25 + 1.50
    suggested_cost = 1.25
WHERE name IN (
    '3 Musketeers Bar',
    'Aero (Milk Chocolate)',
    'Aero (Mint)',
    'Big Turk Bar',
    'Bounty Bar (Coconut)',
    'Butterfinger Bar',
    'Cadbury Caramilk Bar',
    'Cadbury Crunchie Bar',
    'Cadbury Dairy Milk (Milk Chocolate)',
    'Cadbury Mr. Big Bar'
);

-- Update Dove chocolate items
UPDATE master_items 
SET suggested_price = 2.75,  -- 1.25 + 1.50
    suggested_cost = 1.25
WHERE name LIKE 'Dove%' 
AND suggested_price = 0;

-- Update chips and savory snacks
UPDATE master_items 
SET suggested_price = CASE 
        WHEN name LIKE 'Ruffles%' THEN 2.40  -- 0.90 + 1.50
        WHEN name LIKE 'Doritos%' THEN 2.41  -- 0.91 + 1.50
        WHEN name LIKE 'Lays%' THEN 2.34     -- 0.84 + 1.50
        WHEN name LIKE 'Miss Vickies%' THEN 2.33  -- 0.83 + 1.50
        WHEN name LIKE 'Cheetos%' THEN 2.43  -- 0.93 + 1.50
        ELSE 2.40
    END,
    suggested_cost = CASE 
        WHEN name LIKE 'Ruffles%' THEN 0.90
        WHEN name LIKE 'Doritos%' THEN 0.91
        WHEN name LIKE 'Lays%' THEN 0.84
        WHEN name LIKE 'Miss Vickies%' THEN 0.83
        WHEN name LIKE 'Cheetos%' THEN 0.93
        ELSE 0.90
    END
WHERE (name LIKE 'Ruffles%' 
    OR name LIKE 'Doritos%' 
    OR name LIKE 'Lays%' 
    OR name LIKE 'Miss Vickies%' 
    OR name LIKE 'Cheetos%')
AND suggested_price = 0;

-- Update energy drinks
UPDATE master_items 
SET suggested_price = CASE 
        WHEN name LIKE 'Red Bull%' THEN 3.25  -- 1.75 + 1.50
        WHEN name LIKE 'Monster%' THEN 2.75   -- 1.25 + 1.50
        ELSE suggested_price
    END,
    suggested_cost = CASE 
        WHEN name LIKE 'Red Bull%' THEN 1.75
        WHEN name LIKE 'Monster%' THEN 1.25
        ELSE suggested_cost
    END
WHERE (name LIKE 'Red Bull%' OR name LIKE 'Monster%')
AND suggested_price = 0;

-- Update prices using original cost prices and adding $1.50 for retail price
UPDATE master_items SET 
    suggested_price = 1.90,  -- 0.40 + 1.50
    suggested_cost = 0.40
WHERE name = 'Mr. Noodles';

UPDATE master_items SET 
    suggested_price = 2.30,  -- 0.80 + 1.50
    suggested_cost = 0.80
WHERE name IN ('Big Daddy Cookie (Oatmeal Raisin)', 'Big Daddy Cookie (Chocolate Chunk)');

UPDATE master_items SET 
    suggested_price = 2.65,  -- 1.15 + 1.50
    suggested_cost = 1.15
WHERE name = 'Shire Cookies';

UPDATE master_items SET 
    suggested_price = 2.36,  -- 0.86 + 1.50
    suggested_cost = 0.86
WHERE name = 'Ruffles All Dressed';

UPDATE master_items SET 
    suggested_price = 2.40,  -- 0.90 + 1.50
    suggested_cost = 0.90
WHERE name = 'Ruffles Sour Cream';

UPDATE master_items SET 
    suggested_price = 2.40,  -- 0.90 + 1.50
    suggested_cost = 0.90
WHERE name = 'Doritos Nacho';

UPDATE master_items SET 
    suggested_price = 2.41,  -- 0.91 + 1.50
    suggested_cost = 0.91
WHERE name = 'Doritos Zesty';

UPDATE master_items SET 
    suggested_price = 2.34,  -- 0.84 + 1.50
    suggested_cost = 0.84
WHERE name = 'Lays Classic';

UPDATE master_items SET 
    suggested_price = 2.34,  -- 0.84 + 1.50
    suggested_cost = 0.84
WHERE name = 'Miss Vickies Original';

UPDATE master_items SET 
    suggested_price = 2.43,  -- 0.93 + 1.50
    suggested_cost = 0.93
WHERE name = 'Miss Vickies Sweet Chili';

UPDATE master_items SET 
    suggested_price = 2.33,  -- 0.83 + 1.50
    suggested_cost = 0.83
WHERE name = 'Miss Vickies Jalapeno';

UPDATE master_items SET 
    suggested_price = 2.43,  -- 0.93 + 1.50
    suggested_cost = 0.93
WHERE name IN ('Cheetos Crunchy', 'Cheetos Jalapeno');

UPDATE master_items SET 
    suggested_price = 3.25,  -- 1.75 + 1.50
    suggested_cost = 1.75
WHERE name = 'Red Bull (Original)';

UPDATE master_items SET 
    suggested_price = 4.25,  -- 2.75 + 1.50
    suggested_cost = 2.75
WHERE name IN ('Red Bull Large', 'Red Bull Sugar Free');

UPDATE master_items SET 
    suggested_price = 3.25,  -- 1.75 + 1.50
    suggested_cost = 1.75
WHERE name = 'Monster Energy (Original)';

UPDATE master_items SET 
    suggested_price = 2.12,  -- 0.62 + 1.50
    suggested_cost = 0.62
WHERE name = 'Coke Zero';

UPDATE master_items SET 
    suggested_price = 2.04,  -- 0.54 + 1.50
    suggested_cost = 0.54
WHERE name = 'Pepsi';

UPDATE master_items SET 
    suggested_price = 2.04,  -- 0.54 + 1.50
    suggested_cost = 0.54
WHERE name = 'Diet Pepsi';

UPDATE master_items SET 
    suggested_price = 2.18,  -- 0.68 + 1.50
    suggested_cost = 0.68
WHERE name IN ('Mountain Dew', 'Mountain Dew Zero');

UPDATE master_items SET 
    suggested_price = 2.50,  -- 1.00 + 1.50
    suggested_cost = 1.00
WHERE name IN ('Mountain Dew Major Melon', 'Mountain Dew Spark');

UPDATE master_items SET 
    suggested_price = 2.25,  -- 0.75 + 1.50
    suggested_cost = 0.75
WHERE name IN ('Canada Dry', 'Nestea');

UPDATE master_items SET 
    suggested_price = 3.00,  -- 1.50 + 1.50
    suggested_cost = 1.50
WHERE name IN ('Mountain Dew Bottles', 'Pepsi Bottles', 'Gingerale Bottles', 'Dr Pepper Bottles', 'Cream Soda Bottles');

UPDATE master_items SET 
    suggested_price = 2.92,  -- 1.42 + 1.50
    suggested_cost = 1.42
WHERE name IN ('Sprite Bottles', 'Coke Zero Bottles');

UPDATE master_items SET 
    suggested_price = 2.30,  -- 0.80 + 1.50
    suggested_cost = 0.80
WHERE name = 'Dole Orange Juice';

UPDATE master_items SET 
    suggested_price = 2.25,  -- 0.75 + 1.50
    suggested_cost = 0.75
WHERE name = 'Dole Apple Juice';

UPDATE master_items SET 
    suggested_price = 3.00,  -- 1.50 + 1.50
    suggested_cost = 1.50
WHERE name IN ('Orange Powerade', 'Blue Powerade');

UPDATE master_items SET 
    suggested_price = 2.50,  -- 1.00 + 1.50
    suggested_cost = 1.00
WHERE name = 'Grape Gatorade';

UPDATE master_items SET 
    suggested_price = 1.75,  -- 0.25 + 1.50
    suggested_cost = 0.25
WHERE name = 'Eska Water'; 