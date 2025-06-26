CREATE TABLE IF NOT EXISTS sales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    business_id INT NOT NULL,
    machine_id INT NOT NULL,
    item_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    sale_price DECIMAL(10,2) NOT NULL,
    sale_time DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_business_date (business_id, sale_time),
    INDEX idx_machine_date (machine_id, sale_time)
); 