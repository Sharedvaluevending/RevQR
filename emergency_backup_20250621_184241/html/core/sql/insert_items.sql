-- Insert items with their costs
-- Format: (name, type, cost_per_unit, package_size, package_count, location, notes)

-- NOODLES & COOKIES
INSERT INTO items (name, type, cost_per_unit, package_size, package_count, location, notes) VALUES
('Mr. Noodles', 'snack', 0.80, 85, 24, 'Costco', 'Chicken and Beef'),
('Mr. Noodles', 'snack', 1.23, 64, 12, 'Cash and Carry', 'Chicken, Beef, Spicy Chicken'),
('Big Daddy Cookie', 'snack', 0.80, 100, 8, 'Costco', 'Oatmeal Raisin, Chocolate Chunk'),
('Big Daddy Cookie', 'snack', 1.32, 100, 8, 'Cash and Carry', 'Oatmeal'),
('Shire Cookies', 'snack', 1.15, 140, 12, 'Cash and Carry', NULL);

-- CHIPS (SMALL BAGS)
INSERT INTO items (name, type, cost_per_unit, package_size, package_count, location, notes) VALUES
('Ruffles All Dressed', 'snack', 0.86, 40, 48, 'Costco', NULL),
('Ruffles Sour Cream', 'snack', 0.90, 40, 48, 'Costco', NULL),
('Ruffles All Dressed', 'snack', 0.93, 40, 48, 'Cash and Carry', NULL),
('Ruffles Sour Cream', 'snack', 0.91, 40, 48, 'Cash and Carry', NULL),
('Doritos Nacho', 'snack', 0.90, 45, 48, 'Costco', NULL),
('Doritos Nacho', 'snack', 0.93, 45, 48, 'Cash and Carry', NULL),
('Doritos Zesty', 'snack', 0.91, 45, 48, 'Cash and Carry', NULL),
('Lays Classic', 'snack', 0.84, 40, 40, 'Costco', NULL),
('Lays Classic', 'snack', 0.94, 40, 40, 'Cash and Carry', NULL),
('Miss Vickies Original', 'snack', 0.84, 40, 40, 'Costco', NULL),
('Miss Vickies Original', 'snack', 0.93, 40, 40, 'Cash and Carry', NULL),
('Miss Vickies Sweet Chili', 'snack', 0.93, 40, 40, 'Cash and Carry', NULL),
('Miss Vickies Jalapeno', 'snack', 0.83, 40, 40, 'Cash and Carry', NULL),
('Cheetos Crunchy', 'snack', 0.93, 57, 40, 'Cash and Carry', NULL),
('Cheetos Jalapeno', 'snack', 0.93, 54, 40, 'Cash and Carry', NULL);

-- CHIPS (BIG BAGS)
INSERT INTO items (name, type, cost_per_unit, package_size, package_count, location, notes) VALUES
('Lays Classic Big', 'snack', 1.08, 60, 32, 'Cash and Carry', NULL),
('Ruffles All Dressed Big', 'snack', 1.10, 60, 36, 'Cash and Carry', NULL);

-- CANDY & CHOCOLATE
INSERT INTO items (name, type, cost_per_unit, package_size, package_count, location, notes) VALUES
('M&Ms Peanut', 'snack', 1.30, 49, 24, 'Costco', NULL),
('M&Ms Chocolate', 'snack', 1.30, 48, 24, 'Costco', NULL),
('M&Ms Milk Chocolate', 'snack', 1.10, 48, 24, 'Cash and Carry', NULL),
('M&Ms Milk Chocolate Big', 'snack', 5.51, 165, 15, 'Cash and Carry', NULL),
('Crispy Crunch', 'snack', 1.24, 48, 24, 'Costco', NULL),
('Crispy Crunch', 'snack', 1.25, 48, 24, 'Cash and Carry', NULL),
('Aero', 'snack', 1.42, 42, 48, 'Costco', NULL),
('Aero', 'snack', 1.09, 42, 48, 'Cash and Carry', NULL),
('Dairy Milk', 'snack', 1.15, 42, 24, 'Costco', NULL),
('Dairy Milk', 'snack', 1.30, 42, 24, 'Cash and Carry', NULL),
('Starburst', 'snack', 1.21, 59, 36, 'Costco', NULL),
('Starburst', 'snack', 1.33, 58, 36, 'Cash and Carry', NULL),
('Mr. Big', 'snack', 1.24, 60, 24, 'Costco', NULL),
('Mr. Big', 'snack', 1.30, 60, 24, 'Cash and Carry', NULL),
('O Henry', 'snack', 1.03, 58, 24, 'Costco', NULL),
('O Henry', 'snack', 1.13, 58, 24, 'Cash and Carry', NULL),
('O Henry Big', 'snack', 1.80, 85, 24, 'Costco', NULL),
('O Henry Big', 'snack', 1.81, 85, 24, 'Cash and Carry', NULL),
('Skittles Original', 'snack', 1.17, 92, 24, 'Costco', 'Tear and Share'),
('Skittles Original', 'snack', 1.33, 61, 36, 'Cash and Carry', NULL),
('Skittles Sour', 'snack', 1.15, 51, 24, 'Costco', NULL),
('Skittles Sour', 'snack', 1.48, 51, 24, 'Cash and Carry', NULL),
('Skittles Gummies', 'snack', 1.33, 57, 18, 'Costco', NULL),
('Skittles Gummies Sour', 'snack', 1.33, 48, 18, 'Costco', NULL),
('Skittles Tropical', 'snack', 1.17, 61, 36, 'Costco', NULL),
('Skittles Tropical', 'snack', 1.36, 61, 36, 'Cash and Carry', NULL),
('Skittles Berry', 'snack', 1.17, 61, 36, 'Costco', NULL),
('Skittles Berry', 'snack', 1.36, 61, 36, 'Cash and Carry', NULL),
('Kit Kat', 'snack', 2.04, 73, 24, 'Costco', '2 in one'),
('Kit Kat', 'snack', 1.09, 45, 48, 'Cash and Carry', NULL),
('Coffee Crisp', 'snack', 1.42, 50, 48, 'Costco', NULL),
('Coffee Crisp', 'snack', 1.05, 50, 48, 'Cash and Carry', NULL),
('Coffee Crisp King Size', 'snack', 1.67, 75, 24, 'Cash and Carry', NULL),
('Smarties', 'snack', 1.42, 45, 24, 'Costco', NULL),
('Smarties Share Size', 'snack', 1.83, 75, 24, 'Cash and Carry', NULL),
('Smarties Regular', 'snack', 1.06, 45, 24, 'Cash and Carry', NULL);

-- POP (CANS)
INSERT INTO items (name, type, cost_per_unit, package_size, package_count, location, notes) VALUES
('Coke Zero', 'drink', 0.62, 355, 32, 'Costco', NULL),
('Coke Zero', 'drink', 0.67, 355, 24, 'Cash and Carry', NULL),
('Coke Zero', 'drink', 0.79, 355, 12, 'Cash and Carry', NULL),
('Cherry Coke Zero', 'drink', 0.80, 355, 12, 'Cash and Carry', NULL),
('Oreo Coke Zero', 'drink', 0.83, 222, 6, 'Cash and Carry', NULL),
('Coke Regular', 'drink', 0.62, 355, 32, 'Costco', NULL),
('Coke Regular', 'drink', 0.67, 355, 24, 'Cash and Carry', NULL),
('Pepsi', 'drink', 0.54, 355, 32, 'Costco', NULL),
('Pepsi', 'drink', 0.62, 355, 24, 'Cash and Carry', NULL),
('Pepsi Zero', 'drink', 0.54, 355, 32, 'Costco', NULL),
('Pepsi Zero', 'drink', 0.68, 355, 12, 'Cash and Carry', NULL),
('Diet Pepsi', 'drink', 0.54, 355, 32, 'Costco', NULL),
('Diet Pepsi', 'drink', 0.44, 355, 34, 'Cash and Carry', NULL),
('Canada Dry', 'drink', 0.79, 355, 12, 'Cash and Carry', NULL),
('Canada Dry', 'drink', 0.80, 355, 12, 'Cash and Carry', NULL),
('Schweppes', 'drink', 0.68, 355, 12, 'Cash and Carry', NULL),
('Schweppes', 'drink', 0.62, 355, 24, 'Cash and Carry', NULL),
('Nestea Zero', 'drink', 0.79, 341, 12, 'Cash and Carry', 'Natural Lemon'),
('Mountain Dew', 'drink', 0.68, 355, 12, 'Cash and Carry', NULL),
('Mountain Dew Zero', 'drink', 0.68, 355, 12, 'Cash and Carry', NULL),
('Mountain Dew Major Melon', 'drink', 1.17, 355, 12, 'Cash and Carry', NULL),
('Mountain Dew Spark', 'drink', 1.17, 355, 12, 'Cash and Carry', NULL),
('Mountain Dew Voltage', 'drink', 1.17, 355, 12, 'Cash and Carry', NULL);

-- POP (BOTTLES)
INSERT INTO items (name, type, cost_per_unit, package_size, package_count, location, notes) VALUES
('Sprite', 'drink', 1.42, 500, 24, 'Costco', NULL),
('Sprite', 'drink', 1.53, 500, 24, 'Cash and Carry', NULL),
('Coke Zero', 'drink', 1.42, 500, 24, 'Costco', NULL),
('Coke Zero', 'drink', 1.53, 500, 24, 'Cash and Carry', NULL),
('Coke Regular', 'drink', 1.42, 500, 24, 'Costco', NULL),
('Coke Regular', 'drink', 1.53, 500, 24, 'Cash and Carry', NULL),
('Diet Coke', 'drink', 1.42, 500, 24, 'Costco', NULL),
('Diet Coke', 'drink', 1.53, 500, 24, 'Cash and Carry', NULL),
('Pepsi', 'drink', 2.00, 591, 24, 'Cash and Carry', NULL),
('Gingerale', 'drink', 2.00, 591, 24, 'Cash and Carry', NULL),
('Dr Pepper', 'drink', 2.00, 591, 24, 'Cash and Carry', NULL),
('Dr Pepper Cream Soda', 'drink', 2.00, 591, 24, 'Cash and Carry', NULL);

-- JUICE & ENERGY DRINKS
INSERT INTO items (name, type, cost_per_unit, package_size, package_count, location, notes) VALUES
('Dole Orange Juice', 'drink', 0.80, 300, 24, 'Costco', NULL),
('Dole Orange Juice', 'drink', 2.18, 450, 12, 'Cash and Carry', NULL),
('Dole Apple Juice', 'drink', 0.75, 300, 24, 'Costco', NULL),
('Dole Apple Juice', 'drink', 0.79, 340, 12, 'Cash and Carry', NULL),
('Oasis Apple Juice', 'drink', 0.75, 300, 24, 'Costco', NULL),
('Gatorade', 'drink', 1.00, 591, 28, 'Costco', 'Variety Pack'),
('Gatorade Grape', 'drink', 1.34, 591, 24, 'Cash and Carry', NULL),
('Powerade Orange', 'drink', 1.76, 710, 12, 'Cash and Carry', NULL),
('Powerade Blue', 'drink', 1.76, 710, 12, 'Cash and Carry', NULL),
('Red Bull', 'drink', 2.84, 355, 24, 'Costco', NULL),
('Red Bull Zero', 'drink', 1.59, 350, 24, 'Costco', NULL),
('Red Bull Sugar Free', 'drink', 1.59, 350, 24, 'Costco', NULL),
('Red Bull', 'drink', 2.02, 250, 24, 'Cash and Carry', NULL),
('Red Bull', 'drink', 3.95, 473, 12, 'Cash and Carry', NULL),
('Red Bull Sugar Free', 'drink', 3.95, 473, 12, 'Cash and Carry', NULL),
('Monster Black', 'drink', 2.08, 473, 24, 'Costco', NULL),
('Monster Black', 'drink', 2.27, 473, 12, 'Cash and Carry', NULL),
('Monster Java Mean Bean', 'drink', 2.31, 444, 12, 'Cash and Carry', NULL),
('Monster Java Loco Mocha', 'drink', 2.31, 444, 12, 'Cash and Carry', NULL);

-- OTHER DRINKS
INSERT INTO items (name, type, cost_per_unit, package_size, package_count, location, notes) VALUES
('Brisk Iced Tea', 'drink', 0.64, 355, 24, 'Costco', NULL),
('Brisk Iced Tea', 'drink', 2.00, 591, 24, 'Cash and Carry', NULL),
('Brisk Iced Tea', 'drink', 0.68, 355, 12, 'Cash and Carry', NULL),
('Eska Water', 'drink', 0.25, 500, 35, 'Costco', NULL),
('Eska Water', 'drink', 0.20, 500, 24, 'Cash and Carry', NULL); 