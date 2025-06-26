ALTER TABLE spin_results
ADD COLUMN business_id INT NULL AFTER id,
ADD INDEX idx_spin_results_business_id (business_id); 