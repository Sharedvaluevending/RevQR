<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';

// Require business role
require_role('business');

// Get business ID from session
$business_id = $_SESSION['business_id'] ?? null;

if (!$business_id) {
    header('Location: ' . APP_URL . '/business/dashboard.php');
    exit;
}

<<<<<<< Updated upstream
// 🚨 MASSIVE BUSINESS INTELLIGENCE ANALYTICS - FULLY RESTORED
// Time period filter
$period_filter = $_GET['period'] ?? '30';
$date_filter = match($period_filter) {
    '7' => "DATE_SUB(NOW(), INTERVAL 7 DAY)",
    '14' => "DATE_SUB(NOW(), INTERVAL 14 DAY)", 
    '30' => "DATE_SUB(NOW(), INTERVAL 30 DAY)",
    '90' => "DATE_SUB(NOW(), INTERVAL 90 DAY)",
    default => "DATE_SUB(NOW(), INTERVAL 30 DAY)"
};

// 🎯 CORE INSIGHT 1: What Customers DON'T Like (Actionable Negative Feedback)
$stmt = $pdo->prepare("
    SELECT 
        vli.item_name,
        vli.item_category,
        vl.name as machine_name,
        COUNT(*) as negative_votes,
        MIN(v.created_at) as first_negative_vote,
        MAX(v.created_at) as latest_negative_vote,
        COUNT(CASE WHEN WEEKDAY(v.created_at) IN (0,1,2,3,4) THEN 1 END) as weekday_negatives,
        COUNT(CASE WHEN WEEKDAY(v.created_at) IN (5,6) THEN 1 END) as weekend_negatives,
        CASE 
            WHEN COUNT(*) >= 10 THEN '🚨 CRITICAL - Remove Immediately'
            WHEN COUNT(*) >= 5 THEN '⚠️ WARNING - Review Required'
            ELSE '📊 MONITOR - Track Trends'
        END as action_required,
        ROUND(AVG(HOUR(v.created_at)), 1) as avg_complaint_hour
    FROM votes v
    JOIN voting_list_items vli ON v.item_id = vli.id
    JOIN voting_lists vl ON vli.voting_list_id = vl.id
    WHERE vl.business_id = ? 
    AND v.vote_type = 'vote_out'
    AND v.created_at >= {$date_filter}
    GROUP BY vli.id, vli.item_name, vli.item_category, vl.name
    HAVING negative_votes >= 3
    ORDER BY negative_votes DESC, latest_negative_vote DESC
");
$stmt->execute([$business_id]);
$items_to_review = $stmt->fetchAll();

// 🚀 CORE INSIGHT 2: What to ADD to Inventory (Growth Opportunities)
$stmt = $pdo->prepare("
    SELECT 
        COALESCE(vli.item_category, 'Uncategorized') as item_category,
        COUNT(CASE WHEN v.vote_type = 'vote_in' THEN 1 END) as positive_votes,
        COUNT(CASE WHEN v.vote_type = 'vote_out' THEN 1 END) as negative_votes,
        ROUND(COUNT(CASE WHEN v.vote_type = 'vote_in' THEN 1 END) * 100.0 / 
              NULLIF(COUNT(*), 0), 1) as category_approval_rate,
        COUNT(DISTINCT vli.id) as items_in_category,
        AVG(HOUR(v.created_at)) as avg_vote_hour,
        GROUP_CONCAT(DISTINCT vli.item_name SEPARATOR ', ') as top_liked_items
    FROM votes v
    JOIN voting_list_items vli ON v.item_id = vli.id
    JOIN voting_lists vl ON vli.voting_list_id = vl.id
    WHERE vl.business_id = ? 
    AND v.created_at >= {$date_filter}
    GROUP BY vli.item_category
    HAVING COUNT(*) >= 5
    ORDER BY category_approval_rate DESC, positive_votes DESC
");
$stmt->execute([$business_id]);
$trending_categories = $stmt->fetchAll();

// 📊 CORE INSIGHT 3: Time-based patterns (Shift analysis)
$stmt = $pdo->prepare("
    SELECT 
        CASE 
            WHEN HOUR(v.created_at) BETWEEN 6 AND 14 THEN 'Morning (6AM-2PM)'
            WHEN HOUR(v.created_at) BETWEEN 14 AND 22 THEN 'Afternoon (2PM-10PM)'
            ELSE 'Night (10PM-6AM)'
        END as shift_period,
        CASE
            WHEN WEEKDAY(v.created_at) IN (0,1,2,3,4) THEN 'Weekday'
            ELSE 'Weekend'
        END as day_type,
        COUNT(*) as total_votes,
        COUNT(CASE WHEN v.vote_type = 'vote_in' THEN 1 END) as positive_votes,
        COUNT(CASE WHEN v.vote_type = 'vote_out' THEN 1 END) as negative_votes,
        ROUND(COUNT(CASE WHEN v.vote_type = 'vote_in' THEN 1 END) * 100.0 / COUNT(*), 1) as positivity_rate,
        COUNT(DISTINCT DATE(v.created_at)) as active_days,
        COUNT(DISTINCT v.voter_ip) as unique_voters
    FROM votes v
    JOIN voting_list_items vli ON v.item_id = vli.id
    JOIN voting_lists vl ON vli.voting_list_id = vl.id
    WHERE vl.business_id = ? 
    AND v.created_at >= {$date_filter}
    GROUP BY shift_period, day_type
    ORDER BY total_votes DESC
");
$stmt->execute([$business_id]);
$shift_analysis = $stmt->fetchAll();

// 📈 CORE INSIGHT 4: Weekly trends analysis
$stmt = $pdo->prepare("
    SELECT 
        YEARWEEK(v.created_at, 1) as year_week,
        DATE(DATE_SUB(v.created_at, INTERVAL WEEKDAY(v.created_at) DAY)) as week_start,
        COUNT(*) as total_votes,
        COUNT(CASE WHEN v.vote_type = 'vote_in' THEN 1 END) as positive_votes,
        COUNT(CASE WHEN v.vote_type = 'vote_out' THEN 1 END) as negative_votes,
        COUNT(DISTINCT v.voter_ip) as unique_voters,
        COUNT(DISTINCT vli.id) as items_voted_on,
        ROUND(COUNT(CASE WHEN v.vote_type = 'vote_in' THEN 1 END) * 100.0 / COUNT(*), 1) as weekly_satisfaction
=======
// Get time period filter (default to last 30 days)
$period = $_GET['period'] ?? '30';
$date_filter = match($period) {
    '7' => 'DATE_SUB(NOW(), INTERVAL 7 DAY)',
    '14' => 'DATE_SUB(NOW(), INTERVAL 14 DAY)', 
    '30' => 'DATE_SUB(NOW(), INTERVAL 30 DAY)',
    '90' => 'DATE_SUB(NOW(), INTERVAL 90 DAY)',
    'all' => '1970-01-01',
    default => 'DATE_SUB(NOW(), INTERVAL 30 DAY)'
};

// 🎯 CORE INSIGHT 1: What customers DON'T like (Vote OUT trends)
$stmt = $pdo->prepare("
    SELECT 
        vli.item_name,
        COALESCE(vli.item_category, 'Uncategorized') as item_category,
        vl.name as list_name,
        COUNT(*) as vote_out_count,
        COUNT(CASE WHEN WEEKDAY(v.created_at) IN (0,1,2,3,4) THEN 1 END) as weekday_votes,
        COUNT(CASE WHEN WEEKDAY(v.created_at) IN (5,6) THEN 1 END) as weekend_votes,
        COUNT(CASE WHEN HOUR(v.created_at) BETWEEN 6 AND 14 THEN 1 END) as morning_shift,
        COUNT(CASE WHEN HOUR(v.created_at) BETWEEN 14 AND 22 THEN 1 END) as afternoon_shift,
        COUNT(CASE WHEN HOUR(v.created_at) BETWEEN 22 AND 23 OR HOUR(v.created_at) BETWEEN 0 AND 6 THEN 1 END) as night_shift,
        ROUND(AVG(CASE WHEN v.vote_type = 'vote_out' THEN 1 ELSE 0 END) * 100, 1) as dislike_percentage,
        DATE(MIN(v.created_at)) as first_negative_vote,
        DATE(MAX(v.created_at)) as latest_negative_vote
>>>>>>> Stashed changes
    FROM votes v
    JOIN voting_list_items vli ON v.item_id = vli.id
    JOIN voting_lists vl ON vli.voting_list_id = vl.id
    WHERE vl.business_id = ? 
<<<<<<< Updated upstream
    AND v.created_at >= {$date_filter}
    GROUP BY year_week, week_start
    ORDER BY week_start DESC
    LIMIT 12
");
$stmt->execute([$business_id]);
$weekly_trends = $stmt->fetchAll();

// 🔥 CORE INSIGHT 5: Competitive intelligence (Item performance vs category average)
=======
    AND v.vote_type = 'vote_out'
    AND v.created_at >= {$date_filter}
    GROUP BY vli.id, vli.item_name, vli.item_category, vl.name
    HAVING vote_out_count >= 3
    ORDER BY vote_out_count DESC, dislike_percentage DESC
    LIMIT 20
");
$stmt->execute([$business_id]);
$disliked_items = $stmt->fetchAll();

// 🚀 CORE INSIGHT 2: What to ADD (Trending positive patterns) - FIXED QUERY
$stmt = $pdo->prepare("
    SELECT 
        COALESCE(vli.item_category, 'Uncategorized') as item_category,
        COUNT(CASE WHEN v.vote_type = 'vote_in' THEN 1 END) as positive_votes,
        COUNT(CASE WHEN v.vote_type = 'vote_out' THEN 1 END) as negative_votes,
        ROUND(COUNT(CASE WHEN v.vote_type = 'vote_in' THEN 1 END) * 100.0 / 
              NULLIF(COUNT(*), 0), 1) as category_approval_rate,
        COUNT(DISTINCT vli.id) as items_in_category,
        AVG(HOUR(v.created_at)) as avg_vote_hour,
        GROUP_CONCAT(DISTINCT vli.item_name SEPARATOR ', ') as top_liked_items
    FROM votes v
    JOIN voting_list_items vli ON v.item_id = vli.id
    JOIN voting_lists vl ON vli.voting_list_id = vl.id
    WHERE vl.business_id = ? 
    AND v.created_at >= {$date_filter}
    GROUP BY vli.item_category
    HAVING COUNT(*) >= 5
    ORDER BY category_approval_rate DESC, positive_votes DESC
");
$stmt->execute([$business_id]);
$trending_categories = $stmt->fetchAll();

// 📊 CORE INSIGHT 3: Time-based patterns (Shift analysis)
$stmt = $pdo->prepare("
    SELECT 
        CASE 
            WHEN HOUR(v.created_at) BETWEEN 6 AND 14 THEN 'Morning (6AM-2PM)'
            WHEN HOUR(v.created_at) BETWEEN 14 AND 22 THEN 'Afternoon (2PM-10PM)'
            ELSE 'Night (10PM-6AM)'
        END as shift_period,
        CASE
            WHEN WEEKDAY(v.created_at) IN (0,1,2,3,4) THEN 'Weekday'
            ELSE 'Weekend'
        END as day_type,
        COUNT(*) as total_votes,
        COUNT(CASE WHEN v.vote_type = 'vote_in' THEN 1 END) as positive_votes,
        COUNT(CASE WHEN v.vote_type = 'vote_out' THEN 1 END) as negative_votes,
        ROUND(COUNT(CASE WHEN v.vote_type = 'vote_in' THEN 1 END) * 100.0 / COUNT(*), 1) as positivity_rate,
        COUNT(DISTINCT DATE(v.created_at)) as active_days,
        COUNT(DISTINCT v.voter_ip) as unique_voters
    FROM votes v
    JOIN voting_list_items vli ON v.item_id = vli.id
    JOIN voting_lists vl ON vli.voting_list_id = vl.id
    WHERE vl.business_id = ? 
    AND v.created_at >= {$date_filter}
    GROUP BY shift_period, day_type
    ORDER BY total_votes DESC
");
$stmt->execute([$business_id]);
$shift_analysis = $stmt->fetchAll();

// 📈 CORE INSIGHT 4: Weekly trends analysis
$stmt = $pdo->prepare("
    SELECT 
        YEARWEEK(v.created_at, 1) as year_week,
        DATE(DATE_SUB(v.created_at, INTERVAL WEEKDAY(v.created_at) DAY)) as week_start,
        COUNT(*) as total_votes,
        COUNT(CASE WHEN v.vote_type = 'vote_in' THEN 1 END) as positive_votes,
        COUNT(CASE WHEN v.vote_type = 'vote_out' THEN 1 END) as negative_votes,
        COUNT(DISTINCT v.voter_ip) as unique_voters,
        COUNT(DISTINCT vli.id) as items_voted_on,
        ROUND(COUNT(CASE WHEN v.vote_type = 'vote_in' THEN 1 END) * 100.0 / COUNT(*), 1) as weekly_satisfaction
    FROM votes v
    JOIN voting_list_items vli ON v.item_id = vli.id
    JOIN voting_lists vl ON vli.voting_list_id = vl.id
    WHERE vl.business_id = ? 
    AND v.created_at >= {$date_filter}
    GROUP BY year_week, week_start
    ORDER BY week_start DESC
    LIMIT 12
");
$stmt->execute([$business_id]);
$weekly_trends = $stmt->fetchAll();

// 🎯 CORE INSIGHT 5: Cross-reference analysis (What combinations work)
$stmt = $pdo->prepare("
    SELECT 
        vl.name as voting_list,
        COUNT(DISTINCT COALESCE(vli.item_category, 'Uncategorized')) as category_diversity,
        COUNT(*) as total_votes,
        COUNT(CASE WHEN v.vote_type = 'vote_in' THEN 1 END) as likes,
        COUNT(CASE WHEN v.vote_type = 'vote_out' THEN 1 END) as dislikes,
        ROUND(COUNT(CASE WHEN v.vote_type = 'vote_in' THEN 1 END) * 100.0 / COUNT(*), 1) as satisfaction_score,
        COUNT(DISTINCT v.voter_ip) as unique_customers,
        GROUP_CONCAT(DISTINCT COALESCE(vli.item_category, 'Uncategorized') SEPARATOR ', ') as categories_offered,
        AVG(HOUR(v.created_at)) as peak_engagement_hour
    FROM votes v
    JOIN voting_list_items vli ON v.item_id = vli.id
    JOIN voting_lists vl ON vli.voting_list_id = vl.id
    WHERE vl.business_id = ? 
    AND v.created_at >= {$date_filter}
    GROUP BY vl.id, vl.name
    HAVING total_votes >= 10
    ORDER BY satisfaction_score DESC, total_votes DESC
");
$stmt->execute([$business_id]);
$list_performance = $stmt->fetchAll();

// 🔥 CORE INSIGHT 6: Competitive intelligence (Item performance vs category average) - SIMPLIFIED
>>>>>>> Stashed changes
$stmt = $pdo->prepare("
    SELECT 
        vli.item_name,
        COALESCE(vli.item_category, 'Uncategorized') as item_category,
        vl.name as list_name,
        COUNT(*) as total_votes,
        COUNT(CASE WHEN v.vote_type = 'vote_in' THEN 1 END) as likes,
        ROUND(COUNT(CASE WHEN v.vote_type = 'vote_in' THEN 1 END) * 100.0 / COUNT(*), 1) as approval_rate,
        CASE 
            WHEN COUNT(CASE WHEN v.vote_type = 'vote_in' THEN 1 END) * 100.0 / COUNT(*) >= 70 THEN '🔥 OUTPERFORMER'
            WHEN COUNT(CASE WHEN v.vote_type = 'vote_in' THEN 1 END) * 100.0 / COUNT(*) <= 40 THEN '⚠️ UNDERPERFORMER'
            ELSE '📊 AVERAGE'
        END as performance_status
    FROM votes v
    JOIN voting_list_items vli ON v.item_id = vli.id
    JOIN voting_lists vl ON vli.voting_list_id = vl.id
    WHERE vl.business_id = ? 
    AND v.created_at >= {$date_filter}
    GROUP BY vli.id, vli.item_name, vli.item_category, vl.name
    HAVING total_votes >= 5
    ORDER BY approval_rate DESC
");
$stmt->execute([$business_id]);
$competitive_analysis = $stmt->fetchAll();

<<<<<<< Updated upstream
// 🌡️ CORE INSIGHT 6: Sentiment Heatmap Data (Hour x Day analysis)
$stmt = $pdo->prepare("
    SELECT 
        HOUR(v.created_at) as vote_hour,
        DAYNAME(v.created_at) as day_name,
        WEEKDAY(v.created_at) as day_num,
        COUNT(*) as total_votes,
        COUNT(CASE WHEN v.vote_type = 'vote_in' THEN 1 END) as positive_votes,
        ROUND(COUNT(CASE WHEN v.vote_type = 'vote_in' THEN 1 END) * 100.0 / COUNT(*), 1) as satisfaction_score,
        COUNT(DISTINCT v.voter_ip) as unique_voters
=======
// 📱 Device and engagement patterns - SIMPLIFIED
$stmt = $pdo->prepare("
    SELECT 
        'Mobile' as device,
        COUNT(*) as votes,
        COUNT(CASE WHEN v.vote_type = 'vote_in' THEN 1 END) as positive_votes,
        ROUND(COUNT(CASE WHEN v.vote_type = 'vote_in' THEN 1 END) * 100.0 / COUNT(*), 1) as positivity_rate,
        COUNT(DISTINCT v.voter_ip) as unique_users,
        AVG(HOUR(v.created_at)) as avg_hour
>>>>>>> Stashed changes
    FROM votes v
    JOIN voting_list_items vli ON v.item_id = vli.id
    JOIN voting_lists vl ON vli.voting_list_id = vl.id
    WHERE vl.business_id = ? 
    AND v.created_at >= {$date_filter}
<<<<<<< Updated upstream
    GROUP BY HOUR(v.created_at), DAYNAME(v.created_at), WEEKDAY(v.created_at)
    HAVING total_votes >= 3
    ORDER BY day_num, vote_hour
=======
    HAVING votes >= 1
");
$stmt->execute([$business_id]);
$device_analysis = $stmt->fetchAll();

// 💡 ADVANCED INSIGHT 7: Sentiment heatmap (Hour x Day analysis)
$stmt = $pdo->prepare("
    SELECT 
        HOUR(v.created_at) as hour_of_day,
        DAYNAME(v.created_at) as day_name,
        WEEKDAY(v.created_at) as day_number,
        COUNT(*) as total_votes,
        COUNT(CASE WHEN v.vote_type = 'vote_in' THEN 1 END) as positive_votes,
        ROUND(COUNT(CASE WHEN v.vote_type = 'vote_in' THEN 1 END) * 100.0 / COUNT(*), 1) as sentiment_score
    FROM votes v
    JOIN voting_list_items vli ON v.item_id = vli.id
    JOIN voting_lists vl ON vli.voting_list_id = vl.id
    WHERE vl.business_id = ? 
    AND v.created_at >= {$date_filter}
    GROUP BY hour_of_day, day_name, day_number
    HAVING total_votes >= 3
    ORDER BY day_number, hour_of_day
>>>>>>> Stashed changes
");
$stmt->execute([$business_id]);
$sentiment_heatmap = $stmt->fetchAll();

<<<<<<< Updated upstream
// 🌍 CORE INSIGHT 7: Geographic Analysis (IP-based areas)
$stmt = $pdo->prepare("
    SELECT 
        SUBSTRING(v.voter_ip, 1, 7) as ip_area,
        COUNT(*) as total_votes,
        COUNT(CASE WHEN v.vote_type = 'vote_in' THEN 1 END) as positive_votes,
        ROUND(COUNT(CASE WHEN v.vote_type = 'vote_in' THEN 1 END) * 100.0 / COUNT(*), 1) as area_satisfaction,
        COUNT(DISTINCT v.voter_ip) as unique_voters,
        AVG(HOUR(v.created_at)) as peak_hour
    FROM votes v
    JOIN voting_list_items vli ON v.item_id = vli.id
    JOIN voting_lists vl ON vli.voting_list_id = vl.id
    WHERE vl.business_id = ? 
    AND v.created_at >= {$date_filter}
    GROUP BY SUBSTRING(v.voter_ip, 1, 7)
    HAVING total_votes >= 10
    ORDER BY total_votes DESC
    LIMIT 8
");
$stmt->execute([$business_id]);
$geographic_analysis = $stmt->fetchAll();

// 📈 CORE INSIGHT 8: Predictive Analysis (30-day trends)
=======
// 🚀 ADVANCED INSIGHT 8: Predictive analysis (trend forecasting)
>>>>>>> Stashed changes
$stmt = $pdo->prepare("
    SELECT 
        DATE(v.created_at) as vote_date,
        COUNT(*) as daily_votes,
        COUNT(CASE WHEN v.vote_type = 'vote_in' THEN 1 END) as daily_positive,
        ROUND(COUNT(CASE WHEN v.vote_type = 'vote_in' THEN 1 END) * 100.0 / COUNT(*), 1) as daily_satisfaction,
        COUNT(DISTINCT v.voter_ip) as daily_unique_voters
    FROM votes v
    JOIN voting_list_items vli ON v.item_id = vli.id
    JOIN voting_lists vl ON vli.voting_list_id = vl.id
    WHERE vl.business_id = ? 
<<<<<<< Updated upstream
    AND v.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY DATE(v.created_at)
    ORDER BY vote_date DESC
=======
    AND v.created_at >= DATE_SUB(NOW(), INTERVAL 60 DAY)
    GROUP BY vote_date
    ORDER BY vote_date DESC
    LIMIT 30
>>>>>>> Stashed changes
");
$stmt->execute([$business_id]);
$daily_trends = $stmt->fetchAll();

<<<<<<< Updated upstream
// 📱 CORE INSIGHT 9: Device and engagement patterns
$stmt = $pdo->prepare("
    SELECT 
        COALESCE(v.device_type, 'Unknown') as device_type,
        COALESCE(v.browser, 'Unknown') as browser,
        COUNT(*) as votes,
        COUNT(CASE WHEN v.vote_type = 'vote_in' THEN 1 END) as positive_votes,
        ROUND(COUNT(CASE WHEN v.vote_type = 'vote_in' THEN 1 END) * 100.0 / COUNT(*), 1) as positivity_rate,
        COUNT(DISTINCT v.voter_ip) as unique_users,
        AVG(HOUR(v.created_at)) as avg_hour
=======
// 🎯 ADVANCED INSIGHT 9: Geographic/IP analysis (simplified)
$stmt = $pdo->prepare("
    SELECT 
        SUBSTRING_INDEX(v.voter_ip, '.', 3) as ip_prefix,
        COUNT(*) as votes_from_area,
        COUNT(CASE WHEN v.vote_type = 'vote_in' THEN 1 END) as positive_votes,
        ROUND(COUNT(CASE WHEN v.vote_type = 'vote_in' THEN 1 END) * 100.0 / COUNT(*), 1) as area_satisfaction,
        COUNT(DISTINCT v.voter_ip) as unique_ips_in_area,
        AVG(HOUR(v.created_at)) as avg_voting_hour
>>>>>>> Stashed changes
    FROM votes v
    JOIN voting_list_items vli ON v.item_id = vli.id
    JOIN voting_lists vl ON vli.voting_list_id = vl.id
    WHERE vl.business_id = ? 
    AND v.created_at >= {$date_filter}
<<<<<<< Updated upstream
    GROUP BY COALESCE(v.device_type, 'Unknown'), COALESCE(v.browser, 'Unknown')
    HAVING votes >= 5
    ORDER BY votes DESC
");
$stmt->execute([$business_id]);
$device_analysis = $stmt->fetchAll();

// 🚨 REAL-TIME ALERT SYSTEM
$alerts = [];
if (count($items_to_review) > 0) {
    $critical_items = array_filter($items_to_review, fn($item) => $item['negative_votes'] >= 10);
    if (count($critical_items) > 0) {
        $alerts[] = ['type' => 'danger', 'message' => count($critical_items) . ' items need IMMEDIATE removal'];
    }
}

$week_satisfaction_drop = false;
if (count($weekly_trends) >= 2) {
    $current_satisfaction = $weekly_trends[0]['weekly_satisfaction'];
    $previous_satisfaction = $weekly_trends[1]['weekly_satisfaction'];
    if ($current_satisfaction < $previous_satisfaction - 10) {
        $alerts[] = ['type' => 'warning', 'message' => 'Weekly satisfaction dropped by ' . round($previous_satisfaction - $current_satisfaction, 1) . '%'];
        $week_satisfaction_drop = true;
=======
    GROUP BY ip_prefix
    HAVING votes_from_area >= 5
    ORDER BY votes_from_area DESC
    LIMIT 10
");
$stmt->execute([$business_id]);
$geographic_analysis = $stmt->fetchAll();

// 🔥 ADVANCED INSIGHT 10: Alert system data
$alerts = [];

// Alert for items with >10 negative votes
foreach ($disliked_items as $item) {
    if ($item['vote_out_count'] >= 10) {
        $alerts[] = [
            'type' => 'critical',
            'title' => 'Item Removal Required',
            'message' => "'{$item['item_name']}' has {$item['vote_out_count']} negative votes",
            'action' => 'Remove or replace this item immediately'
        ];
    }
}

// Alert for declining satisfaction
if (count($weekly_trends) >= 2) {
    $recent_satisfaction = $weekly_trends[0]['weekly_satisfaction'];
    $previous_satisfaction = $weekly_trends[1]['weekly_satisfaction'];
    
    if ($recent_satisfaction < $previous_satisfaction - 10) {
        $alerts[] = [
            'type' => 'warning',
            'title' => 'Declining Satisfaction',
            'message' => "Weekly satisfaction dropped from {$previous_satisfaction}% to {$recent_satisfaction}%",
            'action' => 'Review recent changes and customer feedback'
        ];
    }
}

// Alert for low-performing categories
foreach ($trending_categories as $category) {
    if ($category['category_approval_rate'] < 40) {
        $alerts[] = [
            'type' => 'info',
            'title' => 'Category Performance',
            'message' => "'{$category['item_category']}' category has {$category['category_approval_rate']}% approval",
            'action' => 'Consider refreshing this category with new items'
        ];
>>>>>>> Stashed changes
    }
}

require_once __DIR__ . '/../core/includes/header.php';
?>

<<<<<<< Updated upstream
<!-- Complete Business Intelligence Dashboard CSS -->
<style>
body {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: 100vh;
}

.glass-card {
    background: rgba(255, 255, 255, 0.12);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.15);
    border-radius: 15px;
    color: white;
}

.metric-card {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(15px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 12px;
    transition: transform 0.3s ease;
}

.metric-card:hover {
    transform: translateY(-5px);
}

.chart-container {
    position: relative;
    height: 300px;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 12px;
    padding: 15px;
}

.alert-custom {
    background: rgba(255, 255, 255, 0.15);
    border: 1px solid rgba(255, 255, 255, 0.3);
    border-radius: 10px;
    color: white;
}

.performance-badge {
    font-size: 0.8rem;
    padding: 0.25rem 0.5rem;
    border-radius: 8px;
    font-weight: 600;
}

.outperformer { background: #28a745; color: white; }
.underperformer { background: #dc3545; color: white; }
.average { background: #6c757d; color: white; }

.heatmap-cell {
    min-height: 40px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 0.8rem;
}

.satisfaction-high { background: #28a745; }
.satisfaction-medium { background: #ffc107; color: #000; }
.satisfaction-low { background: #dc3545; }

.insight-section {
    background: rgba(255, 255, 255, 0.08);
    border-radius: 15px;
    padding: 1.5rem;
    margin-bottom: 2rem;
    border: 1px solid rgba(255, 255, 255, 0.12);
}

.insight-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 1rem;
    font-size: 1.2rem;
    font-weight: 600;
}

.data-table {
    background: rgba(255, 255, 255, 0.05);
    border-radius: 10px;
    overflow: hidden;
}

.data-table table {
    color: white;
    margin: 0;
}

.data-table th {
    background: rgba(255, 255, 255, 0.1);
    border: none;
    color: white;
    font-weight: 600;
    padding: 0.75rem;
}

.data-table td {
    border: 1px solid rgba(255, 255, 255, 0.1);
    padding: 0.5rem;
    color: white;
}
</style>

<!-- Chart.js for Interactive Visualizations -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- Alert System -->
<?php if (!empty($alerts)): ?>
<div class="container-fluid mb-4">
    <?php foreach ($alerts as $alert): ?>
        <div class="alert alert-<?php echo $alert['type']; ?> alert-custom" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <strong>Alert:</strong> <?php echo $alert['message']; ?>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="container-fluid">
    <!-- Header Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="glass-card p-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="h2 mb-2 text-white">🏆 Complete Business Intelligence Dashboard</h1>
                        <p class="text-light mb-0">Advanced voting analytics with 10 core business insights</p>
                    </div>
                    <div class="d-flex gap-2">
                        <!-- Time Period Filter -->
                        <select class="form-select form-select-sm" id="periodFilter" onchange="location.href='?period='+this.value">
                            <option value="7" <?php echo $period_filter == '7' ? 'selected' : ''; ?>>Last 7 days</option>
                            <option value="14" <?php echo $period_filter == '14' ? 'selected' : ''; ?>>Last 2 weeks</option>
                            <option value="30" <?php echo $period_filter == '30' ? 'selected' : ''; ?>>Last 30 days</option>
                            <option value="90" <?php echo $period_filter == '90' ? 'selected' : ''; ?>>Last 3 months</option>
                        </select>
                        <button class="btn btn-outline-light btn-sm" onclick="exportData()">
                            <i class="bi bi-download me-1"></i>Export CSV
                        </button>
                        <a href="dashboard.php" class="btn btn-outline-light btn-sm">
                            <i class="bi bi-arrow-left me-1"></i>Dashboard
                        </a>
                    </div>
                </div>
            </div>
=======
<style>
    /* ENHANCED BUSINESS INTELLIGENCE THEME */
    html, body {
        background: linear-gradient(135deg, #1e3c72 0%, #2a5298 25%, #3d72b4 75%, #5a95d1 100%) !important;
        background-attachment: fixed !important;
        color: #ffffff !important;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif !important;
    }
    
    .text-dark, h1, h2, h3, h4, h5, h6, p, div, span, td, th { color: #ffffff !important; }
    .text-muted { color: rgba(255, 255, 255, 0.8) !important; }
    .text-primary { color: #64b5f6 !important; }
    
    /* Glass morphism cards */
    .card {
        background: rgba(255, 255, 255, 0.12) !important;
        backdrop-filter: blur(20px) !important;
        border: 1px solid rgba(255, 255, 255, 0.15) !important;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3) !important;
        border-radius: 16px !important;
    }
    
    .card-header {
        background: rgba(255, 255, 255, 0.15) !important;
        border-bottom: 1px solid rgba(255, 255, 255, 0.2) !important;
    }
    
    /* Insight cards with special styling */
    .insight-card {
        background: rgba(255, 255, 255, 0.08) !important;
        border-left: 4px solid #ffd700 !important;
        margin-bottom: 20px;
    }
    
    .negative-insight { border-left-color: #ff6b6b !important; }
    .positive-insight { border-left-color: #51cf66 !important; }
    .neutral-insight { border-left-color: #74c0fc !important; }
    
    /* Table styling */
    .table { color: #ffffff !important; background: transparent !important; }
    .table thead th {
        background: rgba(255, 255, 255, 0.15) !important;
        color: #ffffff !important;
        border-bottom: 2px solid rgba(255, 255, 255, 0.2) !important;
    }
    .table tbody tr:hover { background: rgba(255, 255, 255, 0.1) !important; }
    .table td, .table th {
        background: transparent !important;
        border-color: rgba(255, 255, 255, 0.1) !important;
    }
    
    /* Chart containers */
    .chart-container {
        background: rgba(255, 255, 255, 0.05);
        border-radius: 12px;
        padding: 20px;
        margin: 15px 0;
    }
    
    /* Metric cards */
    .metric-card {
        background: rgba(255, 255, 255, 0.1);
        border-radius: 12px;
        padding: 20px;
        text-align: center;
        border: 1px solid rgba(255, 255, 255, 0.2);
    }
    
    .metric-number {
        font-size: 2.5rem;
        font-weight: bold;
        margin-bottom: 5px;
    }
    
    .metric-label {
        font-size: 0.9rem;
        opacity: 0.8;
    }
</style>

<!-- Chart.js for advanced visualizations -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns/dist/chartjs-adapter-date-fns.bundle.min.js"></script>

<div class="container-fluid">

<!-- Header with period filter -->
<div class="row mb-4">
    <div class="col-md-6">
        <h1 class="h2 mb-1">🏆 Business Intelligence Center</h1>
        <p class="text-muted">Advanced voting analytics and customer insights</p>
    </div>
    <div class="col-md-6">
        <div class="d-flex gap-2 justify-content-end">
            <button class="btn btn-success" onclick="exportReport()">
                <i class="bi bi-download me-2"></i>Export Report
            </button>
            <select class="form-select" style="max-width: 200px;" onchange="window.location.href='?period='+this.value">
                <option value="7" <?= $period == '7' ? 'selected' : '' ?>>Last 7 days</option>
                <option value="14" <?= $period == '14' ? 'selected' : '' ?>>Last 2 weeks</option>
                <option value="30" <?= $period == '30' ? 'selected' : '' ?>>Last 30 days</option>
                <option value="90" <?= $period == '90' ? 'selected' : '' ?>>Last 3 months</option>
                <option value="all" <?= $period == 'all' ? 'selected' : '' ?>>All time</option>
            </select>
            <a href="dashboard.php" class="btn btn-outline-light">
                <i class="bi bi-arrow-left"></i>
            </a>
>>>>>>> Stashed changes
        </div>
    </div>

    <!-- Key Metrics Cards -->
    <div class="row mb-4">
        <div class="col-md-2">
            <div class="metric-card p-3 text-center">
                <div class="h3 text-warning mb-1"><?php echo count($items_to_review); ?></div>
                <div class="small text-light">Items to Review</div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="metric-card p-3 text-center">
                <div class="h3 text-success mb-1"><?php echo count($trending_categories); ?></div>
                <div class="small text-light">Growing Categories</div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="metric-card p-3 text-center">
                <div class="h3 text-info mb-1"><?php echo count($weekly_trends); ?></div>
                <div class="small text-light">Weeks Analyzed</div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="metric-card p-3 text-center">
                <div class="h3 text-primary mb-1"><?php echo count($competitive_analysis); ?></div>
                <div class="small text-light">Items Benchmarked</div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="metric-card p-3 text-center">
                <div class="h3 text-danger mb-1"><?php echo count($alerts); ?></div>
                <div class="small text-light">Active Alerts</div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="metric-card p-3 text-center">
                <div class="h3 text-light mb-1"><?php echo count($daily_trends); ?></div>
                <div class="small text-light">Days Tracked</div>
            </div>
        </div>
    </div>

    <!-- INSIGHT 1: Items to Review -->
    <?php if (!empty($items_to_review)): ?>
    <div class="insight-section">
        <div class="insight-header">
            🚨 Core Insight 1: What Customers DON'T Like
        </div>
        <div class="data-table">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>Item Name</th>
                        <th>Category</th>
                        <th>Machine</th>
                        <th>Negative Votes</th>
                        <th>Action Required</th>
                        <th>Avg Complaint Hour</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items_to_review as $item): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                        <td><?php echo htmlspecialchars($item['item_category'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($item['machine_name']); ?></td>
                        <td><span class="badge bg-danger"><?php echo $item['negative_votes']; ?></span></td>
                        <td><?php echo $item['action_required']; ?></td>
                        <td><?php echo round($item['avg_complaint_hour'], 1); ?>:00</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- INSIGHT 2: Category Performance -->
    <?php if (!empty($trending_categories)): ?>
    <div class="insight-section">
        <div class="insight-header">
            🚀 Core Insight 2: What to ADD to Inventory
        </div>
        <div class="data-table">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>Category</th>
                        <th>Approval Rate</th>
                        <th>Positive Votes</th>
                        <th>Items in Category</th>
                        <th>Peak Hour</th>
                        <th>Top Items</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($trending_categories as $category): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($category['item_category']); ?></td>
                        <td>
                            <span class="badge <?php echo $category['category_approval_rate'] >= 70 ? 'bg-success' : ($category['category_approval_rate'] >= 50 ? 'bg-warning' : 'bg-danger'); ?>">
                                <?php echo $category['category_approval_rate']; ?>%
                            </span>
                        </td>
                        <td><?php echo $category['positive_votes']; ?></td>
                        <td><?php echo $category['items_in_category']; ?></td>
                        <td><?php echo round($category['avg_vote_hour']); ?>:00</td>
                        <td class="small"><?php echo htmlspecialchars(substr($category['top_liked_items'], 0, 50)) . (strlen($category['top_liked_items']) > 50 ? '...' : ''); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- INSIGHT 3: Shift Analysis Chart -->
    <?php if (!empty($shift_analysis)): ?>
    <div class="insight-section">
        <div class="insight-header">
            📊 Core Insight 3: Time-based Pattern Analysis
        </div>
        <div class="row">
            <div class="col-md-8">
                <div class="chart-container">
                    <canvas id="shiftChart"></canvas>
                </div>
            </div>
            <div class="col-md-4">
                <div class="data-table">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Shift</th>
                                <th>Day Type</th>
                                <th>Positivity</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($shift_analysis as $shift): ?>
                            <tr>
                                <td><?php echo $shift['shift_period']; ?></td>
                                <td><?php echo $shift['day_type']; ?></td>
                                <td><?php echo $shift['positivity_rate']; ?>%</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- INSIGHT 4: Weekly Trends Chart -->
    <?php if (!empty($weekly_trends)): ?>
    <div class="insight-section">
        <div class="insight-header">
            📈 Core Insight 4: Weekly Satisfaction Trends
        </div>
        <div class="chart-container">
            <canvas id="weeklyTrendsChart"></canvas>
        </div>
    </div>
    <?php endif; ?>

    <!-- INSIGHT 5: Competitive Analysis -->
    <?php if (!empty($competitive_analysis)): ?>
    <div class="insight-section">
        <div class="insight-header">
            🔥 Core Insight 5: Competitive Intelligence
        </div>
        <div class="data-table">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>Item Name</th>
                        <th>Category</th>
                        <th>Total Votes</th>
                        <th>Approval Rate</th>
                        <th>Performance Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($competitive_analysis, 0, 15) as $item): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                        <td><?php echo htmlspecialchars($item['item_category']); ?></td>
                        <td><?php echo $item['total_votes']; ?></td>
                        <td><?php echo $item['approval_rate']; ?>%</td>
                        <td>
                            <span class="performance-badge <?php echo strpos($item['performance_status'], 'OUTPERFORMER') !== false ? 'outperformer' : (strpos($item['performance_status'], 'UNDERPERFORMER') !== false ? 'underperformer' : 'average'); ?>">
                                <?php echo $item['performance_status']; ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- INSIGHT 6: Sentiment Heatmap -->
    <?php if (!empty($sentiment_heatmap)): ?>
    <div class="insight-section">
        <div class="insight-header">
            🌡️ Core Insight 6: Sentiment Heatmap (Hour x Day)
        </div>
        <div class="row">
            <div class="col-12">
                <div style="display: grid; grid-template-columns: repeat(24, 1fr); gap: 2px; margin-bottom: 1rem;">
                    <?php
                    $heatmap_grid = [];
                    foreach ($sentiment_heatmap as $data) {
                        $key = $data['day_num'] . '_' . $data['vote_hour'];
                        $heatmap_grid[$key] = $data['satisfaction_score'];
                    }
                    
                    $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                    for ($day = 0; $day < 7; $day++):
                        for ($hour = 0; $hour < 24; $hour++):
                            $key = $day . '_' . $hour;
                            $satisfaction = $heatmap_grid[$key] ?? 0;
                            $class = $satisfaction >= 70 ? 'satisfaction-high' : ($satisfaction >= 50 ? 'satisfaction-medium' : 'satisfaction-low');
                    ?>
                        <div class="heatmap-cell <?php echo $class; ?>" title="<?php echo $days[$day] . ' ' . $hour . ':00 - ' . $satisfaction . '%'; ?>">
                            <?php echo $satisfaction > 0 ? round($satisfaction) : ''; ?>
                        </div>
                    <?php endfor; endfor; ?>
                </div>
                <div class="text-center small text-light">
                    Hours: 00:00 → 23:00 | Days: Mon → Sun
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- INSIGHT 7: Geographic Analysis -->
    <?php if (!empty($geographic_analysis)): ?>
    <div class="insight-section">
        <div class="insight-header">
            🌍 Core Insight 7: Geographic Analysis
        </div>
        <div class="data-table">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>IP Area</th>
                        <th>Total Votes</th>
                        <th>Satisfaction</th>
                        <th>Unique Voters</th>
                        <th>Peak Hour</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($geographic_analysis as $area): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($area['ip_area']); ?>xxx</td>
                        <td><?php echo $area['total_votes']; ?></td>
                        <td><?php echo $area['area_satisfaction']; ?>%</td>
                        <td><?php echo $area['unique_voters']; ?></td>
                        <td><?php echo round($area['peak_hour']); ?>:00</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- INSIGHT 8: Predictive Analysis Chart -->
    <?php if (!empty($daily_trends)): ?>
    <div class="insight-section">
        <div class="insight-header">
            📈 Core Insight 8: Predictive Analysis (30-day trends)
        </div>
        <div class="chart-container">
            <canvas id="predictiveChart"></canvas>
        </div>
    </div>
    <?php endif; ?>

    <!-- INSIGHT 9: Device Analysis -->
    <?php if (!empty($device_analysis)): ?>
    <div class="insight-section">
        <div class="insight-header">
            📱 Core Insight 9: Device & Engagement Patterns
        </div>
        <div class="row">
            <div class="col-md-6">
                <div class="chart-container">
                    <canvas id="deviceChart"></canvas>
                </div>
            </div>
            <div class="col-md-6">
                <div class="data-table">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Device</th>
                                <th>Browser</th>
                                <th>Votes</th>
                                <th>Positivity</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($device_analysis, 0, 10) as $device): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($device['device_type']); ?></td>
                                <td><?php echo htmlspecialchars($device['browser']); ?></td>
                                <td><?php echo $device['votes']; ?></td>
                                <td><?php echo $device['positivity_rate']; ?>%</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>

<<<<<<< Updated upstream
<!-- Interactive Charts JavaScript -->
<script>
// Chart.js configuration
Chart.defaults.color = '#ffffff';
Chart.defaults.backgroundColor = 'rgba(255, 255, 255, 0.1)';

// Shift Analysis Chart
<?php if (!empty($shift_analysis)): ?>
const shiftCtx = document.getElementById('shiftChart').getContext('2d');
new Chart(shiftCtx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_map(fn($s) => $s['shift_period'] . ' (' . $s['day_type'] . ')', $shift_analysis)); ?>,
        datasets: [{
            label: 'Positivity Rate %',
            data: <?php echo json_encode(array_column($shift_analysis, 'positivity_rate')); ?>,
            backgroundColor: 'rgba(54, 162, 235, 0.6)',
            borderColor: 'rgba(54, 162, 235, 1)',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { labels: { color: '#ffffff' } }
        },
        scales: {
            y: { beginAtZero: true, ticks: { color: '#ffffff' } },
            x: { ticks: { color: '#ffffff' } }
        }
    }
});
<?php endif; ?>

// Weekly Trends Chart
<?php if (!empty($weekly_trends)): ?>
const weeklyCtx = document.getElementById('weeklyTrendsChart').getContext('2d');
new Chart(weeklyCtx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode(array_reverse(array_column($weekly_trends, 'week_start'))); ?>,
        datasets: [{
            label: 'Weekly Satisfaction %',
            data: <?php echo json_encode(array_reverse(array_column($weekly_trends, 'weekly_satisfaction'))); ?>,
            borderColor: 'rgba(75, 192, 192, 1)',
            backgroundColor: 'rgba(75, 192, 192, 0.2)',
            tension: 0.4
        }, {
            label: 'Total Votes',
            data: <?php echo json_encode(array_reverse(array_column($weekly_trends, 'total_votes'))); ?>,
            borderColor: 'rgba(255, 99, 132, 1)',
            backgroundColor: 'rgba(255, 99, 132, 0.2)',
            yAxisID: 'y1'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { labels: { color: '#ffffff' } }
        },
        scales: {
            y: { beginAtZero: true, ticks: { color: '#ffffff' } },
            y1: { type: 'linear', display: true, position: 'right', ticks: { color: '#ffffff' } },
            x: { ticks: { color: '#ffffff' } }
        }
    }
});
<?php endif; ?>

// Predictive Analysis Chart
<?php if (!empty($daily_trends)): ?>
const predictiveCtx = document.getElementById('predictiveChart').getContext('2d');
new Chart(predictiveCtx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode(array_reverse(array_column($daily_trends, 'vote_date'))); ?>,
        datasets: [{
            label: 'Daily Satisfaction %',
            data: <?php echo json_encode(array_reverse(array_column($daily_trends, 'daily_satisfaction'))); ?>,
            borderColor: 'rgba(153, 102, 255, 1)',
            backgroundColor: 'rgba(153, 102, 255, 0.2)',
            tension: 0.4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { labels: { color: '#ffffff' } }
        },
        scales: {
            y: { beginAtZero: true, ticks: { color: '#ffffff' } },
            x: { ticks: { color: '#ffffff' } }
        }
    }
});
<?php endif; ?>

// Device Analysis Chart
<?php if (!empty($device_analysis)): ?>
const deviceCtx = document.getElementById('deviceChart').getContext('2d');
new Chart(deviceCtx, {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode(array_map(fn($d) => $d['device_type'], array_slice($device_analysis, 0, 6))); ?>,
        datasets: [{
            data: <?php echo json_encode(array_column(array_slice($device_analysis, 0, 6), 'votes')); ?>,
            backgroundColor: [
                'rgba(255, 99, 132, 0.8)',
                'rgba(54, 162, 235, 0.8)',
                'rgba(255, 205, 86, 0.8)',
                'rgba(75, 192, 192, 0.8)',
                'rgba(153, 102, 255, 0.8)',
                'rgba(255, 159, 64, 0.8)'
            ]
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { labels: { color: '#ffffff' } }
        }
    }
});
<?php endif; ?>

// Export Data Function
function exportData() {
    const data = {
        items_to_review: <?php echo json_encode($items_to_review); ?>,
        trending_categories: <?php echo json_encode($trending_categories); ?>,
        shift_analysis: <?php echo json_encode($shift_analysis); ?>,
        weekly_trends: <?php echo json_encode($weekly_trends); ?>,
        competitive_analysis: <?php echo json_encode($competitive_analysis); ?>,
        geographic_analysis: <?php echo json_encode($geographic_analysis); ?>,
        daily_trends: <?php echo json_encode($daily_trends); ?>,
        device_analysis: <?php echo json_encode($device_analysis); ?>
    };
    
    // Convert to CSV
    let csv = 'Business Intelligence Report - Generated: ' + new Date().toISOString() + '\n\n';
    
    // Add each section
    for (const [section, items] of Object.entries(data)) {
        if (items.length > 0) {
            csv += section.replace('_', ' ').toUpperCase() + '\n';
            csv += Object.keys(items[0]).join(',') + '\n';
            items.forEach(item => {
                csv += Object.values(item).map(v => '"' + String(v).replace(/"/g, '""') + '"').join(',') + '\n';
            });
            csv += '\n';
        }
    }
    
    // Download
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'business_intelligence_report_' + new Date().toISOString().split('T')[0] + '.csv';
    a.click();
    window.URL.revokeObjectURL(url);
}
</script>
=======
<!-- 🚨 ALERTS SYSTEM -->
<?php if (!empty($alerts)): ?>
<div class="row mb-4">
    <div class="col-12">
        <div class="card" style="border-left: 4px solid #ff6b6b;">
            <div class="card-header">
                <h4 class="mb-0">🚨 Action Required - Business Alerts</h4>
                <small>Important items that need your immediate attention</small>
            </div>
            <div class="card-body">
                <?php foreach ($alerts as $alert): ?>
                    <div class="alert alert-<?= $alert['type'] == 'critical' ? 'danger' : ($alert['type'] == 'warning' ? 'warning' : 'info') ?> mb-2">
                        <strong><?= $alert['title'] ?>:</strong> <?= $alert['message'] ?><br>
                        <small><strong>Recommended Action:</strong> <?= $alert['action'] ?></small>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- KEY INSIGHTS SUMMARY -->
<div class="row mb-4">
    <div class="col-md-2">
        <div class="metric-card">
            <div class="metric-number text-danger"><?= count($disliked_items) ?></div>
            <div class="metric-label">Items to Review</div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="metric-card">
            <div class="metric-number text-success"><?= count($trending_categories) ?></div>
            <div class="metric-label">Growing Categories</div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="metric-card">
            <div class="metric-number text-info"><?= count($weekly_trends) ?></div>
            <div class="metric-label">Weeks Analyzed</div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="metric-card">
            <div class="metric-number text-warning"><?= count($competitive_analysis) ?></div>
            <div class="metric-label">Items Benchmarked</div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="metric-card">
            <div class="metric-number text-primary"><?= count($alerts) ?></div>
            <div class="metric-label">Active Alerts</div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="metric-card">
            <div class="metric-number text-light"><?= count($daily_trends) ?></div>
            <div class="metric-label">Days Tracked</div>
        </div>
    </div>
</div>

<!-- 🚨 CRITICAL INSIGHT: What customers DON'T like -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card insight-card negative-insight">
            <div class="card-header">
                <h4 class="mb-0">🚨 Items Customers Dislike - Action Required</h4>
                <small>These items are getting consistently negative votes. Consider removing or replacing them.</small>
            </div>
            <div class="card-body">
                <?php if (empty($disliked_items)): ?>
                    <div class="alert alert-success">
                        <h5>🎉 Great News!</h5>
                        <p>No items have significant negative feedback. Your customers seem satisfied with your current offerings!</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Item Name</th>
                                    <th>Category</th>
                                    <th>Negative Votes</th>
                                    <th>Time Pattern</th>
                                    <th>Recommendation</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($disliked_items as $item): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($item['item_name']) ?></strong></td>
                                        <td><?= htmlspecialchars($item['item_category']) ?></td>
                                        <td><span class="badge bg-danger"><?= $item['vote_out_count'] ?> votes</span></td>
                                        <td>
                                            <small>
                                                <?php if ($item['morning_shift'] > $item['afternoon_shift']): ?>
                                                    🌅 Mornings (<?= $item['morning_shift'] ?>)
                                                <?php elseif ($item['afternoon_shift'] > $item['night_shift']): ?>
                                                    🌞 Afternoons (<?= $item['afternoon_shift'] ?>)
                                                <?php else: ?>
                                                    🌙 Nights (<?= $item['night_shift'] ?>)
                                                <?php endif; ?>
                                            </small>
                                        </td>
                                        <td>
                                            <?php if ($item['vote_out_count'] >= 10): ?>
                                                <span class="badge bg-danger">⚠️ REMOVE ASAP</span>
                                            <?php elseif ($item['vote_out_count'] >= 5): ?>
                                                <span class="badge bg-warning">🔄 REPLACE SOON</span>
                                            <?php else: ?>
                                                <span class="badge bg-info">👀 MONITOR</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- 🚀 GROWTH OPPORTUNITY: What to ADD -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card insight-card positive-insight">
            <div class="card-header">
                <h4 class="mb-0">🚀 Growth Opportunities - What Customers Want More Of</h4>
                <small>These categories are performing well. Consider adding more variety in these areas.</small>
            </div>
            <div class="card-body">
                <?php if (empty($trending_categories)): ?>
                    <div class="alert alert-info">
                        <p>Not enough voting data yet to identify trending categories. Keep collecting votes!</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Category</th>
                                    <th>Approval Rate</th>
                                    <th>Volume</th>
                                    <th>Items in Category</th>
                                    <th>Peak Time</th>
                                    <th>Recommendation</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($trending_categories as $cat): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($cat['item_category']) ?></strong></td>
                                        <td>
                                            <span class="badge bg-<?= $cat['category_approval_rate'] >= 70 ? 'success' : ($cat['category_approval_rate'] >= 50 ? 'warning' : 'danger') ?>">
                                                <?= $cat['category_approval_rate'] ?>%
                                            </span>
                                        </td>
                                        <td><?= $cat['positive_votes'] ?> positive votes</td>
                                        <td><?= $cat['items_in_category'] ?> items</td>
                                        <td><small><?= floor($cat['avg_vote_hour']) ?>:00</small></td>
                                        <td>
                                            <?php if ($cat['category_approval_rate'] >= 80): ?>
                                                <span class="badge bg-success">🔥 ADD MORE VARIETY</span>
                                            <?php elseif ($cat['category_approval_rate'] >= 60): ?>
                                                <span class="badge bg-warning">➕ EXPAND SELECTION</span>
                                            <?php else: ?>
                                                <span class="badge bg-info">📊 OPTIMIZE CURRENT</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
>>>>>>> Stashed changes

<!-- 📊 TIME PATTERNS: Shift Analysis -->
<?php if (!empty($shift_analysis)): ?>
<div class="row mb-4">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h4 class="mb-0">⏰ Time Pattern Analysis</h4>
                <small>Customer satisfaction by shift and day type</small>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="shiftChart" width="400" height="200"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">🎯 Shift Insights</h5>
            </div>
            <div class="card-body">
                <?php foreach ($shift_analysis as $shift): ?>
                    <div class="mb-3 p-2 rounded" style="background: rgba(255,255,255,0.05)">
                        <strong><?= $shift['shift_period'] ?></strong><br>
                        <small><?= $shift['day_type'] ?></small><br>
                        <div class="d-flex justify-content-between">
                            <span>Satisfaction:</span>
                            <span class="badge bg-<?= $shift['positivity_rate'] >= 70 ? 'success' : ($shift['positivity_rate'] >= 50 ? 'warning' : 'danger') ?>">
                                <?= $shift['positivity_rate'] ?>%
                            </span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>Volume:</span>
                            <span><?= $shift['total_votes'] ?> votes</span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- 📈 WEEKLY TRENDS -->
<?php if (!empty($weekly_trends)): ?>
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h4 class="mb-0">📈 Weekly Satisfaction Trends</h4>
                <small>Track customer satisfaction over time to spot patterns</small>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="trendsChart" width="400" height="200"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- 🔥 COMPETITIVE ANALYSIS -->
<?php if (!empty($competitive_analysis)): ?>
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h4 class="mb-0">🔥 Item Performance Analysis</h4>
                <small>See which items are performing best</small>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Category</th>
                                <th>Approval Rate</th>
                                <th>Performance</th>
                                <th>Votes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($competitive_analysis as $item): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($item['item_name']) ?></strong></td>
                                    <td><?= htmlspecialchars($item['item_category']) ?></td>
                                    <td><?= $item['approval_rate'] ?>%</td>
                                    <td><?= $item['performance_status'] ?></td>
                                    <td><?= $item['total_votes'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

</div>

<script>
// Chart.js Configuration
Chart.defaults.color = '#ffffff';
Chart.defaults.borderColor = 'rgba(255,255,255,0.1)';

// Shift Analysis Chart
<?php if (!empty($shift_analysis)): ?>
const shiftData = <?= json_encode($shift_analysis) ?>;
const shiftCtx = document.getElementById('shiftChart').getContext('2d');
new Chart(shiftCtx, {
    type: 'bar',
    data: {
        labels: shiftData.map(s => s.shift_period + ' - ' + s.day_type),
        datasets: [{
            label: 'Positivity Rate (%)',
            data: shiftData.map(s => s.positivity_rate),
            backgroundColor: shiftData.map(s => s.positivity_rate >= 70 ? '#51cf66' : s.positivity_rate >= 50 ? '#ffd43b' : '#ff6b6b'),
            borderColor: '#ffffff',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { labels: { color: '#ffffff' } }
        },
        scales: {
            y: {
                beginAtZero: true,
                max: 100,
                ticks: { color: '#ffffff' },
                grid: { color: 'rgba(255,255,255,0.1)' }
            },
            x: {
                ticks: { color: '#ffffff' },
                grid: { color: 'rgba(255,255,255,0.1)' }
            }
        }
    }
});
<?php endif; ?>

// Weekly Trends Chart
<?php if (!empty($weekly_trends)): ?>
const trendsData = <?= json_encode($weekly_trends) ?>;
const trendsCtx = document.getElementById('trendsChart').getContext('2d');
new Chart(trendsCtx, {
    type: 'line',
    data: {
        labels: trendsData.map(w => w.week_start),
        datasets: [{
            label: 'Weekly Satisfaction %',
            data: trendsData.map(w => w.weekly_satisfaction),
            borderColor: '#64b5f6',
            backgroundColor: 'rgba(100, 181, 246, 0.1)',
            borderWidth: 3,
            fill: true,
            tension: 0.4
        }, {
            label: 'Total Votes',
            data: trendsData.map(w => w.total_votes),
            borderColor: '#ffd43b',
            backgroundColor: 'rgba(255, 212, 59, 0.1)',
            borderWidth: 2,
            yAxisID: 'y1',
            fill: false
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { labels: { color: '#ffffff' } }
        },
        scales: {
            y: {
                beginAtZero: true,
                max: 100,
                ticks: { color: '#ffffff' },
                grid: { color: 'rgba(255,255,255,0.1)' },
                title: { display: true, text: 'Satisfaction %', color: '#ffffff' }
            },
            y1: {
                type: 'linear',
                display: true,
                position: 'right',
                ticks: { color: '#ffffff' },
                grid: { drawOnChartArea: false },
                title: { display: true, text: 'Vote Volume', color: '#ffffff' }
            },
            x: {
                ticks: { color: '#ffffff' },
                grid: { color: 'rgba(255,255,255,0.1)' }
            }
        }
    }
});
<?php endif; ?>

// Export functionality
function exportReport() {
    const reportData = {
        period: '<?= $period ?>',
        generated: new Date().toISOString(),
        disliked_items: <?= json_encode($disliked_items) ?>,
        trending_categories: <?= json_encode($trending_categories) ?>,
        shift_analysis: <?= json_encode($shift_analysis) ?>,
        weekly_trends: <?= json_encode($weekly_trends) ?>,
        competitive_analysis: <?= json_encode($competitive_analysis) ?>,
        alerts: <?= json_encode($alerts) ?>
    };
    
    // Create CSV export
    let csvContent = "data:text/csv;charset=utf-8,";
    
    // Add summary
    csvContent += "RevenueQR Business Intelligence Report\n";
    csvContent += "Generated: " + new Date().toLocaleString() + "\n";
    csvContent += "Period: " + reportData.period + " days\n\n";
    
    // Add disliked items
    csvContent += "ITEMS TO REVIEW\n";
    csvContent += "Item Name,Category,Negative Votes,Morning Votes,Afternoon Votes,Night Votes\n";
    reportData.disliked_items.forEach(item => {
        csvContent += `"${item.item_name}","${item.item_category}",${item.vote_out_count},${item.morning_shift},${item.afternoon_shift},${item.night_shift}\n`;
    });
    
    csvContent += "\nCATEGORY PERFORMANCE\n";
    csvContent += "Category,Approval Rate,Positive Votes,Items Count\n";
    reportData.trending_categories.forEach(cat => {
        csvContent += `"${cat.item_category}",${cat.category_approval_rate}%,${cat.positive_votes},${cat.items_in_category}\n`;
    });
    
    csvContent += "\nITEM PERFORMANCE\n";
    csvContent += "Item,Category,Approval Rate,Status\n";
    reportData.competitive_analysis.forEach(item => {
        csvContent += `"${item.item_name}","${item.item_category}",${item.approval_rate}%,"${item.performance_status}"\n`;
    });
    
    // Download the file
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", `business_intelligence_report_${reportData.period}days_${new Date().toISOString().split('T')[0]}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>

<?php require_once __DIR__ . '/../core/includes/footer.php'; ?> 