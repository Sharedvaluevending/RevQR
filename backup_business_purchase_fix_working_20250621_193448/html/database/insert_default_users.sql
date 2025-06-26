-- Insert default admin user
INSERT INTO users (name, email, password, role, status) VALUES 
('Admin User', 'admin@revenueqr.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'active');

-- Insert default business user
INSERT INTO users (name, email, password, role, status) VALUES 
('Business User', 'business@revenueqr.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'business', 'active');

-- Insert default regular user
INSERT INTO users (name, email, password, role, status) VALUES 
('Regular User', 'user@revenueqr.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user', 'active'); 