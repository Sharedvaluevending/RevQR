-- Update prices for items in the master_items table
UPDATE master_items SET 
    suggested_price = 1.23,
    suggested_cost = 0.40
WHERE name = 'Mr. Noodles';

UPDATE master_items SET 
    suggested_price = 1.32,
    suggested_cost = 0.80
WHERE name IN ('Big Daddy Cookie (Oatmeal Raisin)', 'Big Daddy Cookie (Chocolate Chunk)');

UPDATE master_items SET 
    suggested_price = 1.15,
    suggested_cost = 1.15
WHERE name = 'Shire Cookies';

UPDATE master_items SET 
    suggested_price = 0.93,
    suggested_cost = 0.86
WHERE name = 'Ruffles All Dressed';

UPDATE master_items SET 
    suggested_price = 0.91,
    suggested_cost = 0.90
WHERE name = 'Ruffles Sour Cream';

UPDATE master_items SET 
    suggested_price = 0.93,
    suggested_cost = 0.90
WHERE name = 'Doritos Nacho';

UPDATE master_items SET 
    suggested_price = 0.91,
    suggested_cost = 0.91
WHERE name = 'Doritos Zesty';

UPDATE master_items SET 
    suggested_price = 0.94,
    suggested_cost = 0.84
WHERE name = 'Lays Classic';

UPDATE master_items SET 
    suggested_price = 0.93,
    suggested_cost = 0.84
WHERE name = 'Miss Vickies Original';

UPDATE master_items SET 
    suggested_price = 0.93,
    suggested_cost = 0.93
WHERE name = 'Miss Vickies Sweet Chili';

UPDATE master_items SET 
    suggested_price = 0.83,
    suggested_cost = 0.83
WHERE name = 'Miss Vickies Jalapeno';

UPDATE master_items SET 
    suggested_price = 0.93,
    suggested_cost = 0.93
WHERE name IN ('Cheetos Crunchy', 'Cheetos Jalapeno');

UPDATE master_items SET 
    suggested_price = 2.02,
    suggested_cost = 1.75
WHERE name = 'Red Bull (Original)';

UPDATE master_items SET 
    suggested_price = 3.95,
    suggested_cost = 2.75
WHERE name IN ('Red Bull Large', 'Red Bull Sugar Free');

UPDATE master_items SET 
    suggested_price = 2.27,
    suggested_cost = 1.75
WHERE name = 'Monster Energy (Original)';

UPDATE master_items SET 
    suggested_price = 0.67,
    suggested_cost = 0.62
WHERE name = 'Coke Zero';

UPDATE master_items SET 
    suggested_price = 0.62,
    suggested_cost = 0.54
WHERE name = 'Pepsi';

UPDATE master_items SET 
    suggested_price = 0.44,
    suggested_cost = 0.54
WHERE name = 'Diet Pepsi';

UPDATE master_items SET 
    suggested_price = 0.68,
    suggested_cost = 0.68
WHERE name IN ('Mountain Dew', 'Mountain Dew Zero');

UPDATE master_items SET 
    suggested_price = 1.17,
    suggested_cost = 1.00
WHERE name IN ('Mountain Dew Major Melon', 'Mountain Dew Spark');

UPDATE master_items SET 
    suggested_price = 0.79,
    suggested_cost = 0.75
WHERE name IN ('Canada Dry', 'Nestea');

UPDATE master_items SET 
    suggested_price = 2.00,
    suggested_cost = 1.50
WHERE name IN ('Mountain Dew Bottles', 'Pepsi Bottles', 'Gingerale Bottles', 'Dr Pepper Bottles', 'Cream Soda Bottles');

UPDATE master_items SET 
    suggested_price = 1.53,
    suggested_cost = 1.42
WHERE name IN ('Sprite Bottles', 'Coke Zero Bottles');

UPDATE master_items SET 
    suggested_price = 2.18,
    suggested_cost = 0.80
WHERE name = 'Dole Orange Juice';

UPDATE master_items SET 
    suggested_price = 0.79,
    suggested_cost = 0.75
WHERE name = 'Dole Apple Juice';

UPDATE master_items SET 
    suggested_price = 1.76,
    suggested_cost = 1.50
WHERE name IN ('Orange Powerade', 'Blue Powerade');

UPDATE master_items SET 
    suggested_price = 1.34,
    suggested_cost = 1.00
WHERE name = 'Grape Gatorade';

UPDATE master_items SET 
    suggested_price = 0.20,
    suggested_cost = 0.25
WHERE name = 'Eska Water'; 