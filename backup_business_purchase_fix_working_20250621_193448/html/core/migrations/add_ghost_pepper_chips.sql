-- Add Ghost Pepper Chips to master_items table
INSERT INTO master_items (
    name,
    type,
    brand,
    suggested_price,
    suggested_cost,
    popularity,
    shelf_life,
    is_seasonal,
    is_imported,
    is_healthy,
    category,
    status
) VALUES (
    'Ghost Pepper Chips',
    'snack',
    'Paqui',
    2.50,
    1.75,
    'medium',
    180,
    false,
    false,
    false,
    'Odd or Unique Items',
    'active'
); 