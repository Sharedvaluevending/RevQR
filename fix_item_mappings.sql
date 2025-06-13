-- Fix missing item mappings and categories
-- This script will create master_items entries and mappings for items that don't have them

-- First, let's create master_items for items that don't have mappings
INSERT INTO master_items (name, category, type, suggested_price, suggested_cost, status)
SELECT DISTINCT 
    i.name,
    CASE 
        WHEN i.type IN ('snack', 'Snacks', 'Candy') AND (i.name LIKE '%chip%' OR i.name LIKE '%crisp%' OR i.name LIKE '%cheeto%' OR i.name LIKE '%dorito%' OR i.name LIKE '%frito%' OR i.name LIKE '%pringles%') THEN 'Chips and Savory Snacks'
        WHEN i.type IN ('snack', 'Snacks', 'Candy') AND (i.name LIKE '%candy%' OR i.name LIKE '%chocolate%' OR i.name LIKE '%bar%' OR i.name LIKE '%gum%' OR i.name LIKE '%mint%') THEN 'Candy and Chocolate Bars'
        WHEN i.type IN ('snack', 'Snacks', 'Candy') AND (i.name LIKE '%cookie%' OR i.name LIKE '%oreo%' OR i.name LIKE '%chip ahoy%') THEN 'Cookies (Brand-Name & Generic)'
        WHEN i.type IN ('drink', 'Beverages', 'Energy', 'Sports') AND (i.name LIKE '%energy%' OR i.name LIKE '%monster%' OR i.name LIKE '%red bull%' OR i.name LIKE '%rockstar%' OR i.name LIKE '%bang%' OR i.name LIKE '%jolt%') THEN 'Energy Drinks'
        WHEN i.type IN ('drink', 'Beverages') AND (i.name LIKE '%water%' OR i.name LIKE '%sparkling%' OR i.name LIKE '%dasani%' OR i.name LIKE '%smartwater%') THEN 'Water and Flavored Water'
        WHEN i.type IN ('drink', 'Beverages') AND (i.name LIKE '%soda%' OR i.name LIKE '%cola%' OR i.name LIKE '%pepsi%' OR i.name LIKE '%coke%' OR i.name LIKE '%sprite%' OR i.name LIKE '%dr pepper%' OR i.name LIKE '%mountain dew%' OR i.name LIKE '%root beer%') THEN 'Soft Drinks and Carbonated Beverages'
        WHEN i.type IN ('drink', 'Beverages') AND (i.name LIKE '%juice%' OR i.name LIKE '%tea%' OR i.name LIKE '%honest%' OR i.name LIKE '%naked%') THEN 'Juices and Bottled Teas'
        WHEN i.type IN ('snack', 'Snacks', 'Healthy') AND (i.name LIKE '%protein%' OR i.name LIKE '%quest%' OR i.name LIKE '%clif%' OR i.name LIKE '%kind%' OR i.name LIKE '%rxbar%') THEN 'Protein and Meal Replacement Bars'
        WHEN i.type IN ('snack', 'Snacks', 'Healthy') AND (i.name LIKE '%healthy%' OR i.name LIKE '%organic%' OR i.name LIKE '%natural%' OR i.name LIKE '%larabar%') THEN 'Healthy Snacks'
        ELSE 'Odd or Unique Items'
    END as category,
    CASE 
        WHEN i.type IN ('Beverages', 'Energy', 'Sports') THEN 'drink'
        WHEN i.type IN ('Snacks', 'Candy', 'Healthy') THEN 'snack'
        WHEN i.type IN ('snack', 'drink', 'pizza', 'side') THEN i.type
        ELSE 'other'
    END as normalized_type,
    i.price,
    i.price * 0.7, -- Calculate suggested cost as 70% of price
    i.status
FROM items i
LEFT JOIN item_mapping im ON i.id = im.item_id
WHERE im.item_id IS NULL
ON DUPLICATE KEY UPDATE
    suggested_price = VALUES(suggested_price),
    suggested_cost = VALUES(suggested_cost),
    status = VALUES(status);

-- Now create the mappings for items without mappings
INSERT INTO item_mapping (master_item_id, item_id)
SELECT mi.id, i.id
FROM items i
LEFT JOIN item_mapping im ON i.id = im.item_id
JOIN master_items mi ON i.name = mi.name 
    AND mi.type = CASE 
        WHEN i.type IN ('Beverages', 'Energy', 'Sports') THEN 'drink'
        WHEN i.type IN ('Snacks', 'Candy', 'Healthy') THEN 'snack'
        WHEN i.type IN ('snack', 'drink', 'pizza', 'side') THEN i.type
        ELSE 'other'
    END
WHERE im.item_id IS NULL
ON DUPLICATE KEY UPDATE master_item_id = VALUES(master_item_id);

-- Show results
SELECT 
    COUNT(*) as total_items,
    COUNT(im.item_id) as mapped_items,
    COUNT(*) - COUNT(im.item_id) as unmapped_items
FROM items i
LEFT JOIN item_mapping im ON i.id = im.item_id;

-- Show sample of items with categories
SELECT i.name, mi.category, m.name as machine_name
FROM items i
LEFT JOIN item_mapping im ON i.id = im.item_id
LEFT JOIN master_items mi ON im.master_item_id = mi.id
JOIN machines m ON i.machine_id = m.id
LIMIT 10; 