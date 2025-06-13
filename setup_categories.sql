-- Insert default categories if they don't exist
INSERT IGNORE INTO categories (name, description) VALUES
('Candy and Chocolate Bars', 'A broad selection of chocolate bars, candies, and gum'),
('Chips and Savory Snacks', 'A diverse range of chips and salty snacks'),
('Cookies (Brand-Name & Generic)', 'Includes major cookie brands and generic/store-brand options'),
('Energy Drinks', 'Common energy drink brands and flavors'),
('Healthy Snacks', 'Better-for-you snack options'),
('Juices and Bottled Teas', 'Non-carbonated beverages'),
('Water and Flavored Water', 'Still and sparkling waters'),
('Protein and Meal Replacement Bars', 'High-protein bars and meal-substitute snacks'),
('Soft Drinks and Carbonated Beverages', 'A selection of sodas and fizzy drinks'),
('Odd or Unique Items', 'Unusual or specialty products');

-- Show categories that were inserted
SELECT * FROM categories ORDER BY name; 