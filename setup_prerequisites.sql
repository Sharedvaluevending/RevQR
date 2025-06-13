-- Create a default business
INSERT INTO businesses (id, name, email, slug) VALUES
(1, 'Default Business', 'default@revenueqr.com', 'default-business');

-- Create a default machine
INSERT INTO machines (id, business_id, name, slug, description, qr_type) VALUES
(1, 1, 'Default Machine', 'default-machine', 'Default vending machine', 'static'); 