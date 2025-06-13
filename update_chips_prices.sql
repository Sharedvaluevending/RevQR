-- Update prices for chips and savory snacks
UPDATE master_items 
SET suggested_price = 1.50, 
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