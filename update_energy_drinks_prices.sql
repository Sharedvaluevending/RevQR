-- Update prices for energy drinks
UPDATE master_items 
SET suggested_price = CASE 
        WHEN name LIKE 'Red Bull%' THEN 3.50
        WHEN name LIKE 'Monster%' THEN 3.00
        ELSE suggested_price
    END,
    suggested_cost = CASE 
        WHEN name LIKE 'Red Bull%' THEN 1.75
        WHEN name LIKE 'Monster%' THEN 1.25
        ELSE suggested_cost
    END
WHERE (name LIKE 'Red Bull%' OR name LIKE 'Monster%')
AND suggested_price = 0; 