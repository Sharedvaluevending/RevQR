-- Update prices for Dove chocolate items
UPDATE master_items 
SET suggested_price = 1.25, 
    suggested_cost = 0.95 
WHERE name LIKE 'Dove%' 
AND suggested_price = 0; 