-- Simple fix for missing item mappings
-- Add unmapped items to master_items with basic categorization

-- First, create a simple master_items entry for each unmapped item
INSERT INTO master_items (name, category, type, suggested_price, suggested_cost, status)
SELECT DISTINCT 
    i.name,
    'Odd or Unique Items' as category,
    CASE 
        WHEN i.type IN ('Beverages', 'Energy', 'Sports') THEN 'drink'
        WHEN i.type IN ('Snacks', 'Candy', 'Healthy') THEN 'snack'
        WHEN i.type = 'snack' THEN 'snack'
        WHEN i.type = 'drink' THEN 'drink'
        ELSE 'other'
    END as normalized_type,
    i.price,
    i.price * 0.7,
    'active'
FROM items i
LEFT JOIN item_mapping im ON i.id = im.item_id
WHERE im.item_id IS NULL
ON DUPLICATE KEY UPDATE
    suggested_price = VALUES(suggested_price);

-- Now create mappings for all unmapped items
INSERT IGNORE INTO item_mapping (master_item_id, item_id)
SELECT mi.id, i.id
FROM items i
LEFT JOIN item_mapping im ON i.id = im.item_id
JOIN master_items mi ON mi.name = i.name 
WHERE im.item_id IS NULL;

-- Show final results
SELECT 
    COUNT(*) as total_items,
    COUNT(im.item_id) as mapped_items
FROM items i
LEFT JOIN item_mapping im ON i.id = im.item_id; 