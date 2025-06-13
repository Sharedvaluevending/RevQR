-- Create warehouse_inventory table for tracking inventory at different locations
CREATE TABLE IF NOT EXISTS warehouse_inventory (
    id INT AUTO_INCREMENT PRIMARY KEY,
    business_id INT NOT NULL,
    master_item_id INT NOT NULL,
    location_type ENUM('warehouse', 'storage', 'home', 'supplier') NOT NULL DEFAULT 'warehouse',
    location_name VARCHAR(255) NOT NULL DEFAULT 'Main Warehouse',
    quantity INT NOT NULL DEFAULT 0,
    minimum_stock INT NOT NULL DEFAULT 0,
    maximum_stock INT NOT NULL DEFAULT 1000,
    cost_per_unit DECIMAL(10,2) DEFAULT 0.00,
    supplier_info VARCHAR(255) NULL,
    last_restocked DATETIME NULL,
    expiry_date DATE NULL,
    notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_warehouse_business (business_id),
    INDEX idx_warehouse_item (master_item_id),
    INDEX idx_warehouse_location (location_type, location_name),
    INDEX idx_warehouse_quantity (quantity),
    
    UNIQUE KEY unique_warehouse_item_location (business_id, master_item_id, location_type, location_name),
    
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    FOREIGN KEY (master_item_id) REFERENCES master_items(id) ON DELETE CASCADE
);

-- Create inventory_transactions table for tracking stock movements
CREATE TABLE IF NOT EXISTS inventory_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    business_id INT NOT NULL,
    master_item_id INT NOT NULL,
    transaction_type ENUM('restock', 'transfer', 'adjustment', 'sale', 'waste', 'return') NOT NULL,
    from_location_type ENUM('warehouse', 'storage', 'home', 'machine', 'supplier') NULL,
    from_location_name VARCHAR(255) NULL,
    to_location_type ENUM('warehouse', 'storage', 'home', 'machine', 'supplier') NULL,
    to_location_name VARCHAR(255) NULL,
    quantity INT NOT NULL,
    unit_cost DECIMAL(10,2) DEFAULT 0.00,
    total_cost DECIMAL(10,2) DEFAULT 0.00,
    reference_number VARCHAR(100) NULL,
    notes TEXT NULL,
    user_id INT NULL,
    transaction_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_transaction_business (business_id),
    INDEX idx_transaction_item (master_item_id),
    INDEX idx_transaction_type (transaction_type),
    INDEX idx_transaction_date (transaction_date),
    INDEX idx_transaction_from (from_location_type, from_location_name),
    INDEX idx_transaction_to (to_location_type, to_location_name),
    
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    FOREIGN KEY (master_item_id) REFERENCES master_items(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Add sample warehouse inventory data
INSERT IGNORE INTO warehouse_inventory (business_id, master_item_id, location_type, location_name, quantity, minimum_stock, maximum_stock) 
SELECT 
    1 as business_id,
    id as master_item_id,
    'warehouse' as location_type,
    'Main Warehouse' as location_name,
    FLOOR(RAND() * 200) + 50 as quantity,
    20 as minimum_stock,
    500 as maximum_stock
FROM master_items 
WHERE id <= 20;

-- Add some home storage inventory
INSERT IGNORE INTO warehouse_inventory (business_id, master_item_id, location_type, location_name, quantity, minimum_stock, maximum_stock) 
SELECT 
    1 as business_id,
    id as master_item_id,
    'home' as location_type,
    'Home Storage' as location_name,
    FLOOR(RAND() * 50) + 10 as quantity,
    5 as minimum_stock,
    100 as maximum_stock
FROM master_items 
WHERE id BETWEEN 5 AND 15;

-- Check if master_item_id column exists in items table before adding it
SET @exist := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
               WHERE TABLE_SCHEMA = DATABASE() 
               AND TABLE_NAME = 'items' 
               AND COLUMN_NAME = 'master_item_id');

SET @sql = IF(@exist > 0, 'SELECT "Column master_item_id already exists"', 
              'ALTER TABLE items ADD COLUMN master_item_id INT NULL');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Check if location column exists in items table before adding it
SET @exist := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
               WHERE TABLE_SCHEMA = DATABASE() 
               AND TABLE_NAME = 'items' 
               AND COLUMN_NAME = 'location');

SET @sql = IF(@exist > 0, 'SELECT "Column location already exists"', 
              'ALTER TABLE items ADD COLUMN location VARCHAR(255) NULL');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add index for master_item_id if it doesn't exist
SET @exist := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
               WHERE TABLE_SCHEMA = DATABASE() 
               AND TABLE_NAME = 'items' 
               AND INDEX_NAME = 'idx_items_master_id');

SET @sql = IF(@exist > 0, 'SELECT "Index idx_items_master_id already exists"', 
              'ALTER TABLE items ADD INDEX idx_items_master_id (master_item_id)');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add some sample inventory to items table with locations for backward compatibility
UPDATE items i
JOIN master_items mi ON i.name = mi.name
SET i.master_item_id = mi.id,
    i.location = CASE 
        WHEN i.id % 3 = 0 THEN 'warehouse'
        WHEN i.id % 3 = 1 THEN 'storage'
        ELSE 'home'
    END,
    i.inventory = CASE 
        WHEN i.inventory = 0 THEN FLOOR(RAND() * 100) + 25
        ELSE i.inventory
    END
WHERE i.master_item_id IS NULL; 