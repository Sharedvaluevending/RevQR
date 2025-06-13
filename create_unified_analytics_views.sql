-- Unified Analytics Views for Cross-System Integration
-- Phase 2: Creating unified views for combined analytics

-- 1. Unified Machine Performance View
CREATE OR REPLACE VIEW unified_machine_performance AS
SELECT 
    'manual' as system_type,
    vl.id as machine_id,
    vl.name as machine_name,
    vl.location,
    vl.business_id,
    vl.created_at,
    'active' as status,
    COALESCE(vote_counts.total_votes, 0) as activity_count,
    COALESCE(vote_counts.votes_today, 0) as today_activity,
    COALESCE(vote_counts.votes_week, 0) as week_activity,
    NULL as revenue,
    NULL as transactions
FROM voting_lists vl
LEFT JOIN (
    SELECT 
        v.machine_id,
        COUNT(*) as total_votes,
        COUNT(CASE WHEN DATE(v.created_at) = CURDATE() THEN 1 END) as votes_today,
        COUNT(CASE WHEN DATE(v.created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 END) as votes_week
    FROM votes v
    GROUP BY v.machine_id
) vote_counts ON vl.id = vote_counts.machine_id

UNION ALL

SELECT 
    'nayax' as system_type,
    nm.id as machine_id,
    nm.machine_name,
    nm.location_description as location,
    nm.business_id,
    nm.created_at,
    nm.status,
    COALESCE(trans_counts.total_transactions, 0) as activity_count,
    COALESCE(trans_counts.transactions_today, 0) as today_activity,
    COALESCE(trans_counts.transactions_week, 0) as week_activity,
    COALESCE(trans_counts.total_revenue, 0) as revenue,
    COALESCE(trans_counts.total_transactions, 0) as transactions
FROM nayax_machines nm
LEFT JOIN (
    SELECT 
        nt.nayax_machine_id,
        COUNT(*) as total_transactions,
        COUNT(CASE WHEN DATE(nt.created_at) = CURDATE() THEN 1 END) as transactions_today,
        COUNT(CASE WHEN DATE(nt.created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 END) as transactions_week,
        SUM(nt.amount_cents/100) as total_revenue
    FROM nayax_transactions nt
    GROUP BY nt.nayax_machine_id
) trans_counts ON nm.nayax_machine_id = trans_counts.nayax_machine_id;

-- 2. Business System Summary View
CREATE OR REPLACE VIEW business_system_summary AS
SELECT 
    b.id as business_id,
    b.name as business_name,
    COALESCE(manual_stats.manual_count, 0) as manual_machines,
    COALESCE(nayax_stats.nayax_count, 0) as nayax_machines,
    COALESCE(manual_stats.manual_count, 0) + COALESCE(nayax_stats.nayax_count, 0) as total_machines,
    CASE 
        WHEN COALESCE(manual_stats.manual_count, 0) > 0 AND COALESCE(nayax_stats.nayax_count, 0) > 0 THEN 'unified'
        WHEN COALESCE(nayax_stats.nayax_count, 0) > 0 THEN 'nayax_only'
        WHEN COALESCE(manual_stats.manual_count, 0) > 0 THEN 'manual_only'
        ELSE 'no_machines'
    END as system_mode,
    COALESCE(manual_stats.total_votes, 0) as total_votes,
    COALESCE(nayax_stats.total_transactions, 0) as total_transactions,
    COALESCE(nayax_stats.total_revenue, 0) as total_revenue
FROM businesses b
LEFT JOIN (
    SELECT 
        vl.business_id,
        COUNT(*) as manual_count,
        COALESCE(SUM(vote_counts.total_votes), 0) as total_votes
    FROM voting_lists vl
    LEFT JOIN (
        SELECT machine_id, COUNT(*) as total_votes
        FROM votes
        GROUP BY machine_id
    ) vote_counts ON vl.id = vote_counts.machine_id
    GROUP BY vl.business_id
) manual_stats ON b.id = manual_stats.business_id
LEFT JOIN (
    SELECT 
        nm.business_id,
        COUNT(*) as nayax_count,
        COALESCE(SUM(trans_counts.total_transactions), 0) as total_transactions,
        COALESCE(SUM(trans_counts.total_revenue), 0) as total_revenue
    FROM nayax_machines nm
    LEFT JOIN (
        SELECT nayax_machine_id, COUNT(*) as total_transactions, SUM(amount_cents/100) as total_revenue
        FROM nayax_transactions
        GROUP BY nayax_machine_id
    ) trans_counts ON nm.nayax_machine_id = trans_counts.nayax_machine_id
    WHERE nm.status != 'inactive'
    GROUP BY nm.business_id
) nayax_stats ON b.id = nayax_stats.business_id;

-- 3. Cross-System Performance Comparison View
CREATE OR REPLACE VIEW cross_system_performance AS
SELECT 
    business_id,
    'manual' as system_type,
    COUNT(*) as machine_count,
    SUM(activity_count) as total_activity,
    SUM(today_activity) as today_activity,
    SUM(week_activity) as week_activity,
    0 as revenue,
    'votes' as activity_unit
FROM unified_machine_performance 
WHERE system_type = 'manual'
GROUP BY business_id

UNION ALL

SELECT 
    business_id,
    'nayax' as system_type,
    COUNT(*) as machine_count,
    SUM(activity_count) as total_activity,
    SUM(today_activity) as today_activity,
    SUM(week_activity) as week_activity,
    SUM(revenue) as revenue,
    'transactions' as activity_unit
FROM unified_machine_performance 
WHERE system_type = 'nayax'
GROUP BY business_id;

-- 4. Unified Product Performance (for businesses with both systems)
CREATE OR REPLACE VIEW unified_product_performance AS
SELECT 
    'manual' as system_type,
    vli.voting_list_id as machine_id,
    vli.item_name as product_name,
    COUNT(v.id) as interactions,
    0 as sales_revenue,
    'votes' as interaction_type,
    vl.business_id
FROM voting_list_items vli
LEFT JOIN votes v ON vli.id = v.item_id
LEFT JOIN voting_lists vl ON vli.voting_list_id = vl.id
GROUP BY vli.voting_list_id, vli.item_name, vl.business_id

UNION ALL

SELECT 
    'nayax' as system_type,
    nt.nayax_machine_id as machine_id,
    'Nayax Sales' as product_name,
    COUNT(*) as interactions,
    SUM(nt.amount_cents/100) as sales_revenue,
    'purchases' as interaction_type,
    nm.business_id
FROM nayax_transactions nt
JOIN nayax_machines nm ON nt.nayax_machine_id = nm.nayax_machine_id
GROUP BY nt.nayax_machine_id, nm.business_id; 