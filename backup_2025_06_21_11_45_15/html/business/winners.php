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
        vli.item_category,
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
    FROM votes v
    JOIN voting_list_items vli ON v.item_id = vli.id
    JOIN voting_lists vl ON vli.voting_list_id = vl.id
    WHERE vl.business_id = ? 
    AND v.vote_type = 'vote_out'
    AND v.created_at >= {$date_filter}
    GROUP BY vli.id, vli.item_name, vli.item_category, vl.name
    HAVING vote_out_count >= 3
    ORDER BY vote_out_count DESC, dislike_percentage DESC
    LIMIT 20
");
$stmt->execute([$business_id]);
$disliked_items = $stmt->fetchAll();

// 🚀 CORE INSIGHT 2: What to ADD (Trending positive patterns)
$stmt = $pdo->prepare("
    SELECT 
        vli.item_category,
        COUNT(CASE WHEN v.vote_type = 'vote_in' THEN 1 END) as positive_votes,
        COUNT(CASE WHEN v.vote_type = 'vote_out' THEN 1 END) as negative_votes,
        ROUND(COUNT(CASE WHEN v.vote_type = 'vote_in' THEN 1 END) * 100.0 / 
              NULLIF(COUNT(*), 0), 1) as category_approval_rate,
        COUNT(DISTINCT vli.id) as items_in_category,
        AVG(HOUR(v.created_at)) as avg_vote_hour,
        GROUP_CONCAT(DISTINCT vli.item_name ORDER BY 
            (SELECT COUNT(*) FROM votes v2 WHERE v2.item_id = vli.id AND v2.vote_type = 'vote_in') DESC 
            SEPARATOR ', ') as top_liked_items
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
        COUNT(DISTINCT vli.item_category) as category_diversity,
        COUNT(*) as total_votes,
        COUNT(CASE WHEN v.vote_type = 'vote_in' THEN 1 END) as likes,
        COUNT(CASE WHEN v.vote_type = 'vote_out' THEN 1 END) as dislikes,
        ROUND(COUNT(CASE WHEN v.vote_type = 'vote_in' THEN 1 END) * 100.0 / COUNT(*), 1) as satisfaction_score,
        COUNT(DISTINCT v.voter_ip) as unique_customers,
        GROUP_CONCAT(DISTINCT vli.item_category) as categories_offered,
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

// 🔥 CORE INSIGHT 6: Competitive intelligence (Item performance vs category average)
$stmt = $pdo->prepare("
    WITH category_stats AS (
        SELECT 
            vli.item_category,
            AVG(CASE WHEN v.vote_type = 'vote_in' THEN 1 ELSE 0 END) * 100 as category_avg_approval
        FROM votes v
        JOIN voting_list_items vli ON v.item_id = vli.id
        JOIN voting_lists vl ON vli.voting_list_id = vl.id
        WHERE vl.business_id = ? 
        AND v.created_at >= {$date_filter}
        GROUP BY vli.item_category
    )
    SELECT 
        vli.item_name,
        vli.item_category,
        vl.name as list_name,
        COUNT(*) as total_votes,
        COUNT(CASE WHEN v.vote_type = 'vote_in' THEN 1 END) as likes,
        ROUND(COUNT(CASE WHEN v.vote_type = 'vote_in' THEN 1 END) * 100.0 / COUNT(*), 1) as approval_rate,
        cs.category_avg_approval,
        ROUND((COUNT(CASE WHEN v.vote_type = 'vote_in' THEN 1 END) * 100.0 / COUNT(*)) - cs.category_avg_approval, 1) as vs_category_performance,
        CASE 
            WHEN (COUNT(CASE WHEN v.vote_type = 'vote_in' THEN 1 END) * 100.0 / COUNT(*)) > cs.category_avg_approval + 10 THEN '🔥 OUTPERFORMER'
            WHEN (COUNT(CASE WHEN v.vote_type = 'vote_in' THEN 1 END) * 100.0 / COUNT(*)) < cs.category_avg_approval - 10 THEN '⚠️ UNDERPERFORMER'
            ELSE '📊 AVERAGE'
        END as performance_status
    FROM votes v
    JOIN voting_list_items vli ON v.item_id = vli.id
    JOIN voting_lists vl ON vli.voting_list_id = vl.id
    JOIN category_stats cs ON vli.item_category = cs.item_category
    WHERE vl.business_id = ? 
    AND v.created_at >= {$date_filter}
    GROUP BY vli.id, vli.item_name, vli.item_category, vl.name, cs.category_avg_approval
    HAVING total_votes >= 5
    ORDER BY vs_category_performance DESC
");
$stmt->execute([$business_id, $business_id]);
$competitive_analysis = $stmt->fetchAll();

// 📱 Device and engagement patterns
$stmt = $pdo->prepare("
    SELECT 
        COALESCE(v.device_type, 'Unknown') as device,
        COALESCE(v.browser, 'Unknown') as browser,
        COUNT(*) as votes,
        COUNT(CASE WHEN v.vote_type = 'vote_in' THEN 1 END) as positive_votes,
        ROUND(COUNT(CASE WHEN v.vote_type = 'vote_in' THEN 1 END) * 100.0 / COUNT(*), 1) as positivity_rate,
        COUNT(DISTINCT v.voter_ip) as unique_users,
        AVG(HOUR(v.created_at)) as avg_hour
    FROM votes v
    JOIN voting_list_items vli ON v.item_id = vli.id
    JOIN voting_lists vl ON vli.voting_list_id = vl.id
    WHERE vl.business_id = ? 
    AND v.created_at >= {$date_filter}
    GROUP BY device, browser
    HAVING votes >= 3
    ORDER BY votes DESC
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
");
$stmt->execute([$business_id]);
$sentiment_heatmap = $stmt->fetchAll();

// 🚀 ADVANCED INSIGHT 8: Predictive analysis (trend forecasting)
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
    AND v.created_at >= DATE_SUB(NOW(), INTERVAL 60 DAY)
    GROUP BY vote_date
    ORDER BY vote_date DESC
    LIMIT 30
");
$stmt->execute([$business_id]);
$daily_trends = $stmt->fetchAll();

// 🎯 ADVANCED INSIGHT 9: Geographic/IP analysis (if available)
$stmt = $pdo->prepare("
    SELECT 
        SUBSTRING_INDEX(v.voter_ip, '.', 3) as ip_prefix,
        COUNT(*) as votes_from_area,
        COUNT(CASE WHEN v.vote_type = 'vote_in' THEN 1 END) as positive_votes,
        ROUND(COUNT(CASE WHEN v.vote_type = 'vote_in' THEN 1 END) * 100.0 / COUNT(*), 1) as area_satisfaction,
        COUNT(DISTINCT v.voter_ip) as unique_ips_in_area,
        AVG(HOUR(v.created_at)) as avg_voting_hour
    FROM votes v
    JOIN voting_list_items vli ON v.item_id = vli.id
    JOIN voting_lists vl ON vli.voting_list_id = vl.id
    WHERE vl.business_id = ? 
    AND v.created_at >= {$date_filter}
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
    }
}

require_once __DIR__ . '/../core/includes/header.php';
?>

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
    
    /* Performance indicators */
    .performance-badge {
        font-size: 0.8rem;
        padding: 4px 8px;
        border-radius: 12px;
        font-weight: bold;
    }
    
    .outperformer { background: #51cf66; color: #000; }
    .underperformer { background: #ff6b6b; color: #fff; }
    .average { background: #74c0fc; color: #000; }
    
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
        </div>
    </div>
</div>

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
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Category</th>
                                <th>Approval Rate</th>
                                <th>Volume</th>
                                <th>Top Items</th>
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
                                    <td><small><?= htmlspecialchars($cat['top_liked_items']) ?></small></td>
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
            </div>
        </div>
    </div>
</div>

<!-- 📊 TIME PATTERNS: Shift Analysis -->
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

<!-- 📈 WEEKLY TRENDS -->
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

<!-- 🔥 COMPETITIVE ANALYSIS -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h4 class="mb-0">🔥 Item Performance vs Category Average</h4>
                <small>See which items are outperforming or underperforming in their categories</small>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Category</th>
                                <th>Approval Rate</th>
                                <th>vs Category Avg</th>
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
                                    <td>
                                        <span class="badge bg-<?= $item['vs_category_performance'] > 0 ? 'success' : ($item['vs_category_performance'] < 0 ? 'danger' : 'secondary') ?>">
                                            <?= $item['vs_category_performance'] > 0 ? '+' : '' ?><?= $item['vs_category_performance'] ?>%
                                        </span>
                                    </td>
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

<!-- 📱 DEVICE & ENGAGEMENT ANALYSIS -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">📱 Device Usage Patterns</h5>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="deviceChart" width="400" height="300"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">🎯 List Performance Comparison</h5>
            </div>
            <div class="card-body">
                <?php foreach ($list_performance as $list): ?>
                    <div class="mb-3 p-3 rounded" style="background: rgba(255,255,255,0.05)">
                        <h6><?= htmlspecialchars($list['voting_list']) ?></h6>
                    <div class="row">
                            <div class="col-6">
                                <small>Satisfaction Score:</small><br>
                                <span class="badge bg-<?= $list['satisfaction_score'] >= 70 ? 'success' : ($list['satisfaction_score'] >= 50 ? 'warning' : 'danger') ?>">
                                    <?= $list['satisfaction_score'] ?>%
                                </span>
                            </div>
                            <div class="col-6">
                                <small>Total Votes:</small><br>
                                <strong><?= $list['total_votes'] ?></strong>
                            </div>
                        </div>
                        <small class="text-muted">
                            Categories: <?= htmlspecialchars($list['categories_offered']) ?>
                        </small>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- 🌡️ ADVANCED: Sentiment Heatmap -->
<?php if (!empty($sentiment_heatmap)): ?>
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h4 class="mb-0">🌡️ Customer Sentiment Heatmap</h4>
                <small>Sentiment analysis by hour and day of week - find your peak satisfaction times</small>
            </div>
                                    <div class="card-body">
                <div class="chart-container">
                    <canvas id="heatmapChart" width="400" height="200"></canvas>
                                            </div>
                <div class="mt-3">
                    <small class="text-muted">
                        <span class="badge bg-success me-2">70%+</span> Excellent
                        <span class="badge bg-warning me-2">50-69%</span> Good
                        <span class="badge bg-danger me-2">&lt;50%</span> Needs Attention
                    </small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
<?php endif; ?>

<!-- 🌍 GEOGRAPHIC INSIGHTS -->
<?php if (!empty($geographic_analysis)): ?>
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">🌍 Geographic Voting Patterns</h5>
                <small>Customer engagement by area (IP-based)</small>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Area</th>
                                <th>Votes</th>
                                <th>Satisfaction</th>
                                <th>Peak Hour</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($geographic_analysis as $geo): ?>
                                <tr>
                                    <td><?= htmlspecialchars($geo['ip_prefix']) ?>.x</td>
                                    <td><?= $geo['votes_from_area'] ?></td>
                                    <td>
                                        <span class="badge bg-<?= $geo['area_satisfaction'] >= 70 ? 'success' : ($geo['area_satisfaction'] >= 50 ? 'warning' : 'danger') ?>">
                                            <?= $geo['area_satisfaction'] ?>%
                                        </span>
                                    </td>
                                    <td><?= floor($geo['avg_voting_hour']) ?>:00</td>
                                </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">📈 Daily Trends (Last 30 Days)</h5>
                <small>Predictive analysis of customer satisfaction</small>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="dailyChart" width="400" height="200"></canvas>
                </div>
                <?php if (!empty($daily_trends)): ?>
                    <div class="mt-3">
                        <?php 
                        $recent_avg = array_sum(array_column(array_slice($daily_trends, 0, 7), 'daily_satisfaction')) / 7;
                        $previous_avg = array_sum(array_column(array_slice($daily_trends, 7, 7), 'daily_satisfaction')) / 7;
                        $trend = $recent_avg - $previous_avg;
                        ?>
                        <small>
                            <strong>Trend Analysis:</strong>
                            <span class="badge bg-<?= $trend > 0 ? 'success' : ($trend < -5 ? 'danger' : 'warning') ?>">
                                <?= $trend > 0 ? '↗️' : ($trend < -5 ? '↘️' : '➡️') ?>
                                <?= abs(round($trend, 1)) ?>% 
                                <?= $trend > 0 ? 'Improving' : ($trend < -5 ? 'Declining' : 'Stable') ?>
                            </span>
                        </small>
                    </div>
                <?php endif; ?>
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

// Weekly Trends Chart
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

// Device Analysis Chart
const deviceData = <?= json_encode($device_analysis) ?>;
const deviceCtx = document.getElementById('deviceChart').getContext('2d');
new Chart(deviceCtx, {
    type: 'doughnut',
    data: {
        labels: deviceData.map(d => d.device),
        datasets: [{
            data: deviceData.map(d => d.votes),
            backgroundColor: [
                '#51cf66', '#ffd43b', '#74c0fc', '#ff6b6b', '#9775fa', '#20c997'
            ],
            borderColor: '#ffffff',
            borderWidth: 2
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { 
                position: 'bottom',
                labels: { color: '#ffffff', padding: 20 }
            }
        }
    }
});

// Sentiment Heatmap Chart (Matrix visualization)
<?php if (!empty($sentiment_heatmap)): ?>
const heatmapData = <?= json_encode($sentiment_heatmap) ?>;
const heatmapCtx = document.getElementById('heatmapChart').getContext('2d');

// Create matrix data for heatmap
const days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
const hours = Array.from({length: 24}, (_, i) => i);
const matrixData = [];

heatmapData.forEach(item => {
    matrixData.push({
        x: item.hour_of_day,
        y: item.day_number,
        v: item.sentiment_score
    });
});

new Chart(heatmapCtx, {
    type: 'scatter',
    data: {
        datasets: [{
            label: 'Sentiment Score',
            data: matrixData,
            backgroundColor: function(context) {
                const value = context.parsed.v;
                if (value >= 70) return '#51cf66';
                if (value >= 50) return '#ffd43b';
                return '#ff6b6b';
            },
            borderColor: '#ffffff',
            borderWidth: 1,
            pointRadius: function(context) {
                return Math.max(3, context.parsed.v / 10);
            }
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { labels: { color: '#ffffff' } },
            tooltip: {
                callbacks: {
                    title: function(tooltipItems) {
                        const item = tooltipItems[0];
                        return days[item.parsed.y] + ' at ' + item.parsed.x + ':00';
                    },
                    label: function(tooltipItem) {
                        return 'Satisfaction: ' + tooltipItem.parsed.v + '%';
                    }
                }
            }
        },
        scales: {
            x: {
                type: 'linear',
                position: 'bottom',
                min: 0,
                max: 23,
                ticks: { 
                    color: '#ffffff',
                    stepSize: 2,
                    callback: function(value) {
                        return value + ':00';
                    }
                },
                grid: { color: 'rgba(255,255,255,0.1)' },
                title: { display: true, text: 'Hour of Day', color: '#ffffff' }
            },
            y: {
                type: 'linear',
                min: 0,
                max: 6,
                ticks: { 
                    color: '#ffffff',
                    stepSize: 1,
                    callback: function(value) {
                        return days[value];
                    }
                },
                grid: { color: 'rgba(255,255,255,0.1)' },
                title: { display: true, text: 'Day of Week', color: '#ffffff' }
            }
        }
    }
});
<?php endif; ?>

// Daily Trends Chart
<?php if (!empty($daily_trends)): ?>
const dailyData = <?= json_encode(array_reverse($daily_trends)) ?>;
const dailyCtx = document.getElementById('dailyChart').getContext('2d');
new Chart(dailyCtx, {
    type: 'line',
    data: {
        labels: dailyData.map(d => d.vote_date),
        datasets: [{
            label: 'Daily Satisfaction %',
            data: dailyData.map(d => d.daily_satisfaction),
            borderColor: '#64b5f6',
            backgroundColor: 'rgba(100, 181, 246, 0.1)',
            borderWidth: 2,
            fill: true,
            tension: 0.3
        }, {
            label: 'Daily Votes',
            data: dailyData.map(d => d.daily_votes),
            borderColor: '#51cf66',
            backgroundColor: 'rgba(81, 207, 102, 0.1)',
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
                title: { display: true, text: 'Vote Count', color: '#ffffff' }
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
    csvContent += "Category,Approval Rate,Positive Votes,Top Items\n";
    reportData.trending_categories.forEach(cat => {
        csvContent += `"${cat.item_category}",${cat.category_approval_rate}%,${cat.positive_votes},"${cat.top_liked_items}"\n`;
    });
    
    csvContent += "\nCOMPETITIVE ANALYSIS\n";
    csvContent += "Item,Category,Approval Rate,vs Category Performance,Status\n";
    reportData.competitive_analysis.forEach(item => {
        csvContent += `"${item.item_name}","${item.item_category}",${item.approval_rate}%,${item.vs_category_performance}%,"${item.performance_status}"\n`;
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