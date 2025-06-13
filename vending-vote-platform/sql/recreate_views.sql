-- Re-create missing views
CREATE OR REPLACE VIEW campaign_view AS
SELECT 
    m.id as campaign_id,
    m.business_id,
    m.name as campaign_name,
    m.description as campaign_description,
    m.type as campaign_type,
    m.is_active,
    m.tooltip,
    m.created_at as campaign_created_at,
    m.updated_at as campaign_updated_at
FROM machines m
WHERE m.type IN ('vote', 'promo');

CREATE OR REPLACE VIEW campaign_items_view AS
SELECT 
    m.id as campaign_id,
    i.id as item_id,
    i.name as item_name,
    i.type as item_type,
    i.price,
    i.list_type,
    i.status
FROM machines m
JOIN items i ON i.machine_id = m.id
WHERE m.type IN ('vote', 'promo'); 