-- Create master_items table (independent reference list)
CREATE TABLE IF NOT EXISTS master_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    type ENUM('snack','drink','pizza','side','other') NOT NULL,
    brand VARCHAR(100),
    suggested_price DECIMAL(10,2) NOT NULL,
    suggested_cost DECIMAL(10,2) NOT NULL,
    popularity ENUM('high', 'medium', 'low') NOT NULL DEFAULT 'medium',
    shelf_life INT NOT NULL DEFAULT 180,
    is_seasonal BOOLEAN DEFAULT FALSE,
    is_imported BOOLEAN DEFAULT FALSE,
    is_healthy BOOLEAN DEFAULT FALSE,
    category VARCHAR(100) NOT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_master_items_type (type),
    INDEX idx_master_items_category (category),
    INDEX idx_master_items_status (status)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- Insert master list items with updated prices
INSERT INTO master_items (name, type, brand, suggested_price, suggested_cost, popularity, shelf_life, is_seasonal, is_imported, is_healthy, category, status) VALUES
-- Noodles and Cookies
('Mr. Noodles', 'snack', 'Mr. Noodles', 1.23, 0.40, 'medium', 180, 0, 0, 0, 'Noodles and Cookies', 'active'),
('Big Daddy Cookie (Oatmeal Raisin)', 'snack', 'Big Daddy', 1.32, 0.80, 'medium', 180, 0, 0, 0, 'Noodles and Cookies', 'active'),
('Big Daddy Cookie (Chocolate Chunk)', 'snack', 'Big Daddy', 1.32, 0.80, 'high', 180, 0, 0, 0, 'Noodles and Cookies', 'active'),
('Shire Cookies', 'snack', 'Shire', 1.15, 1.15, 'medium', 180, 0, 0, 0, 'Noodles and Cookies', 'active'),

-- Chips and Savory Snacks
('Ruffles All Dressed', 'snack', 'Ruffles', 0.93, 0.86, 'high', 180, 0, 0, 0, 'Chips and Savory Snacks', 'active'),
('Ruffles Sour Cream', 'snack', 'Ruffles', 0.91, 0.90, 'high', 180, 0, 0, 0, 'Chips and Savory Snacks', 'active'),
('Doritos Nacho', 'snack', 'Doritos', 0.93, 0.90, 'high', 180, 0, 0, 0, 'Chips and Savory Snacks', 'active'),
('Doritos Zesty', 'snack', 'Doritos', 0.91, 0.91, 'medium', 180, 0, 0, 0, 'Chips and Savory Snacks', 'active'),
('Lays Classic', 'snack', 'Lays', 0.94, 0.84, 'high', 180, 0, 0, 0, 'Chips and Savory Snacks', 'active'),
('Miss Vickies Original', 'snack', 'Miss Vickies', 0.93, 0.84, 'high', 180, 0, 0, 0, 'Chips and Savory Snacks', 'active'),
('Miss Vickies Sweet Chili', 'snack', 'Miss Vickies', 0.93, 0.93, 'medium', 180, 0, 0, 0, 'Chips and Savory Snacks', 'active'),
('Miss Vickies Jalapeno', 'snack', 'Miss Vickies', 0.83, 0.83, 'medium', 180, 0, 0, 0, 'Chips and Savory Snacks', 'active'),
('Cheetos Crunchy', 'snack', 'Cheetos', 0.93, 0.93, 'high', 180, 0, 0, 0, 'Chips and Savory Snacks', 'active'),
('Cheetos Jalapeno', 'snack', 'Cheetos', 0.93, 0.93, 'medium', 180, 0, 0, 0, 'Chips and Savory Snacks', 'active'),

-- Energy Drinks
('Red Bull (Original)', 'drink', 'Red Bull', 2.02, 1.75, 'high', 180, 0, 0, 0, 'Energy Drinks', 'active'),
('Red Bull Large', 'drink', 'Red Bull', 3.95, 2.75, 'high', 180, 0, 0, 0, 'Energy Drinks', 'active'),
('Red Bull Sugar Free', 'drink', 'Red Bull', 3.95, 2.75, 'medium', 180, 0, 0, 0, 'Energy Drinks', 'active'),
('Monster Energy (Original)', 'drink', 'Monster', 2.27, 1.75, 'high', 180, 0, 0, 0, 'Energy Drinks', 'active'),

-- Soft Drinks
('Coke Zero', 'drink', 'Coca-Cola', 0.67, 0.62, 'high', 180, 0, 0, 0, 'Soft Drinks', 'active'),
('Pepsi', 'drink', 'Pepsi', 0.62, 0.54, 'high', 180, 0, 0, 0, 'Soft Drinks', 'active'),
('Diet Pepsi', 'drink', 'Pepsi', 0.44, 0.54, 'high', 180, 0, 0, 0, 'Soft Drinks', 'active'),
('Mountain Dew', 'drink', 'Mountain Dew', 0.68, 0.68, 'high', 180, 0, 0, 0, 'Soft Drinks', 'active'),
('Mountain Dew Zero', 'drink', 'Mountain Dew', 0.68, 0.68, 'medium', 180, 0, 0, 0, 'Soft Drinks', 'active'),
('Mountain Dew Major Melon', 'drink', 'Mountain Dew', 1.17, 1.00, 'medium', 180, 0, 0, 0, 'Soft Drinks', 'active'),
('Mountain Dew Spark', 'drink', 'Mountain Dew', 1.17, 1.00, 'medium', 180, 0, 0, 0, 'Soft Drinks', 'active'),
('Canada Dry', 'drink', 'Canada Dry', 0.79, 0.75, 'medium', 180, 0, 0, 0, 'Soft Drinks', 'active'),
('Nestea', 'drink', 'Nestea', 0.79, 0.75, 'medium', 180, 0, 0, 0, 'Soft Drinks', 'active'),

-- Bottled Drinks
('Mountain Dew Bottles', 'drink', 'Mountain Dew', 2.00, 1.50, 'high', 180, 0, 0, 0, 'Bottled Drinks', 'active'),
('Sprite Bottles', 'drink', 'Sprite', 1.53, 1.42, 'high', 180, 0, 0, 0, 'Bottled Drinks', 'active'),
('Coke Zero Bottles', 'drink', 'Coca-Cola', 1.53, 1.42, 'high', 180, 0, 0, 0, 'Bottled Drinks', 'active'),
('Pepsi Bottles', 'drink', 'Pepsi', 2.00, 1.50, 'high', 180, 0, 0, 0, 'Bottled Drinks', 'active'),
('Gingerale Bottles', 'drink', 'Canada Dry', 2.00, 1.50, 'medium', 180, 0, 0, 0, 'Bottled Drinks', 'active'),
('Dr Pepper Bottles', 'drink', 'Dr Pepper', 2.00, 1.50, 'high', 180, 0, 0, 0, 'Bottled Drinks', 'active'),
('Cream Soda Bottles', 'drink', 'Barq\'s', 2.00, 1.50, 'medium', 180, 0, 0, 0, 'Bottled Drinks', 'active'),

-- Juices and Sports Drinks
('Dole Orange Juice', 'drink', 'Dole', 2.18, 0.80, 'medium', 180, 0, 0, 1, 'Juices and Sports Drinks', 'active'),
('Dole Apple Juice', 'drink', 'Dole', 0.79, 0.75, 'medium', 180, 0, 0, 1, 'Juices and Sports Drinks', 'active'),
('Orange Powerade', 'drink', 'Powerade', 1.76, 1.50, 'medium', 180, 0, 0, 0, 'Juices and Sports Drinks', 'active'),
('Blue Powerade', 'drink', 'Powerade', 1.76, 1.50, 'medium', 180, 0, 0, 0, 'Juices and Sports Drinks', 'active'),
('Grape Gatorade', 'drink', 'Gatorade', 1.34, 1.00, 'high', 180, 0, 0, 0, 'Juices and Sports Drinks', 'active'),

-- Water
('Eska Water', 'drink', 'Eska', 0.20, 0.25, 'high', 180, 0, 0, 1, 'Water', 'active'); 