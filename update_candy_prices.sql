-- Update prices for candy and chocolate bars
UPDATE master_items SET 
    suggested_price = 1.25,
    suggested_cost = 0.90
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