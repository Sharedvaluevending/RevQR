ALTER TABLE spin_results
ADD COLUMN machine_id INT NULL AFTER business_id,
ADD INDEX idx_spin_results_machine_id (machine_id); 