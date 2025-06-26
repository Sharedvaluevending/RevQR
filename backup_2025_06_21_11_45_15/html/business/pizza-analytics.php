<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/business_utils.php';
require_once __DIR__ . '/../core/pizza_tracker_utils.php';

// Require business role
require_role('business');

$business_id = getOrCreateBusinessId($pdo, $_SESSION['user_id']);
$pizzaTracker = new PizzaTracker($pdo);

// Get date range from query params
$start_date = $_GET['start_date'] ?? date('Y-m-01'); // Default to start of month
$end_date = $_GET['end_date'] ?? date('Y-m-d'); // Default to today
$tracker_id = $_GET['tracker_id'] ?? 'all';

// Get all trackers for this business
$trackers = $pizzaTracker->getBusinessTrackers($business_id);

// Get comprehensive analytics data
$analytics = $pizzaTracker->getAdvancedAnalytics($business_id, $start_date, $end_date, $tracker_id);

// Ensure all numeric values are properly typed
$analytics['total_revenue'] = floatval($analytics['total_revenue'] ?? 0);
$analytics['pizzas_earned'] = intval($analytics['pizzas_earned'] ?? 0);
$analytics['avg_progress'] = floatval($analytics['avg_progress'] ?? 0);
$analytics['active_trackers'] = intval($analytics['active_trackers'] ?? 0);
$analytics['total_clicks'] = intval($analytics['total_clicks'] ?? 0);
$analytics['unique_visitors'] = intval($analytics['unique_visitors'] ?? 0);
$analytics['avg_session_duration'] = floatval($analytics['avg_session_duration'] ?? 0);
$analytics['bounce_rate'] = floatval($analytics['bounce_rate'] ?? 0);

// Ensure prediction data is properly typed
if (isset($analytics['prediction'])) {
    $analytics['prediction']['days_to_goal'] = intval($analytics['prediction']['days_to_goal'] ?? 0);
    $analytics['prediction']['daily_average'] = floatval($analytics['prediction']['daily_average'] ?? 0);
    $analytics['prediction']['peak_hours'] = $analytics['prediction']['peak_hours'] ?? 'N/A';
}

// Ensure tracker comparison data is properly typed
if (isset($analytics['tracker_comparison']) && is_array($analytics['tracker_comparison'])) {
    foreach ($analytics['tracker_comparison'] as &$tracker) {
        $tracker['progress_percent'] = floatval($tracker['progress_percent'] ?? 0);
        $tracker['current_revenue'] = floatval($tracker['current_revenue'] ?? 0);
        $tracker['revenue_goal'] = floatval($tracker['revenue_goal'] ?? 0);
    }
}

// Ensure other arrays exist
$analytics['top_performers'] = $analytics['top_performers'] ?? [];
$analytics['recent_milestones'] = $analytics['recent_milestones'] ?? [];
$analytics['revenue_timeline'] = $analytics['revenue_timeline'] ?? ['labels' => [], 'values' => []];
$analytics['progress_distribution'] = $analytics['progress_distribution'] ?? ['labels' => [], 'values' => []];
$analytics['activity_timeline'] = $analytics['activity_timeline'] ?? ['labels' => [], 'values' => []];
$analytics['traffic_sources'] = $analytics['traffic_sources'] ?? ['labels' => [], 'values' => []];

require_once __DIR__ . '/../core/includes/header.php';
?>

<style>
/* Enhanced Glass Morphism Cards - Matching Spin Wheel Design */
.gradient-card-primary {
    background: rgba(255, 255, 255, 0.12) !important;
    backdrop-filter: blur(25px) !important;
    border: 1px solid rgba(255, 255, 255, 0.2) !important;
    border-radius: 16px !important;
    box-shadow: 
        0 8px 32px rgba(31, 38, 135, 0.37),
        inset 0 1px 0 rgba(255, 255, 255, 0.1) !important;
    transition: all 0.3s ease !important;
}

.gradient-card-primary:hover {
    transform: translateY(-2px) !important;
    box-shadow: 
        0 12px 40px rgba(31, 38, 135, 0.5),
        inset 0 1px 0 rgba(255, 255, 255, 0.15) !important;
    border: 1px solid rgba(255, 255, 255, 0.25) !important;
}

/* Metrics Glass Cards */
.metrics-glass-card {
    background: rgba(255, 255, 255, 0.12) !important;
    backdrop-filter: blur(25px) !important;
    border: 1px solid rgba(255, 255, 255, 0.2) !important;
    border-radius: 16px !important;
    box-shadow: 
        0 8px 32px rgba(31, 38, 135, 0.37),
        inset 0 1px 0 rgba(255, 255, 255, 0.1) !important;
    transition: all 0.3s ease !important;
}

.metrics-glass-card:hover {
    transform: translateY(-2px) !important;
    box-shadow: 
        0 12px 40px rgba(31, 38, 135, 0.5),
        inset 0 1px 0 rgba(255, 255, 255, 0.15) !important;
    border: 1px solid rgba(255, 255, 255, 0.25) !important;
}

/* Enhanced Metric Cards */
.metric-card {
    background: linear-gradient(135deg, rgba(255, 107, 107, 0.9) 0%, rgba(254, 202, 87, 0.9) 100%) !important;
    backdrop-filter: blur(20px) !important;
    border: 1px solid rgba(255, 255, 255, 0.3) !important;
    color: white !important;
    border-radius: 16px !important;
    padding: 24px !important;
    text-align: center !important;
    transition: all 0.3s ease !important;
    margin-bottom: 20px !important;
    box-shadow: 0 8px 32px rgba(255, 107, 107, 0.3) !important;
}

.metric-card:hover {
    transform: translateY(-5px) scale(1.02) !important;
    box-shadow: 0 15px 40px rgba(255, 107, 107, 0.4) !important;
    background: linear-gradient(135deg, rgba(255, 107, 107, 1) 0%, rgba(254, 202, 87, 1) 100%) !important;
}

.metric-value {
    font-size: 2.8rem !important;
    font-weight: 700 !important;
    margin-bottom: 0.5rem !important;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3) !important;
}

.metric-label {
    font-size: 0.95rem !important;
    opacity: 0.95 !important;
    font-weight: 500 !important;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2) !important;
}

/* Chart Container Styling */
.chart-container {
    position: relative !important;
    height: 400px !important;
    margin: 20px 0 !important;
    background: rgba(255, 255, 255, 0.08) !important;
    backdrop-filter: blur(15px) !important;
    border-radius: 12px !important;
    padding: 15px !important;
    border: 1px solid rgba(255, 255, 255, 0.15) !important;
}

/* Progress Bar Styling */
.progress-comparison {
    display: flex !important;
    align-items: center !important;
    margin: 15px 0 !important;
    padding: 12px !important;
    background: rgba(255, 255, 255, 0.08) !important;
    backdrop-filter: blur(15px) !important;
    border-radius: 12px !important;
    border: 1px solid rgba(255, 255, 255, 0.15) !important;
}

.progress-bar-container {
    flex: 1 !important;
    height: 28px !important;
    background: rgba(233, 236, 239, 0.3) !important;
    border-radius: 14px !important;
    overflow: hidden !important;
    margin: 0 15px !important;
    border: 1px solid rgba(255, 255, 255, 0.1) !important;
}

.progress-bar {
    height: 100% !important;
    background: linear-gradient(90deg, #ff6b6b 0%, #feca57 100%) !important;
    border-radius: 14px !important;
    transition: width 0.5s ease !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    color: white !important;
    font-weight: 600 !important;
    font-size: 0.85rem !important;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3) !important;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.2) !important;
}

/* Engagement Metrics Grid */
.engagement-metrics {
    display: grid !important;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)) !important;
    gap: 20px !important;
    margin: 20px 0 !important;
}

.engagement-item {
    background: rgba(255, 255, 255, 0.12) !important;
    backdrop-filter: blur(20px) !important;
    padding: 20px !important;
    border-radius: 12px !important;
    text-align: center !important;
    border: 1px solid rgba(255, 255, 255, 0.15) !important;
    transition: all 0.3s ease !important;
}

.engagement-item:hover {
    transform: translateY(-3px) !important;
    background: rgba(255, 255, 255, 0.18) !important;
    border: 1px solid rgba(255, 255, 255, 0.25) !important;
}

/* Filters Section */
.filters-section {
    background: rgba(255, 255, 255, 0.12) !important;
    backdrop-filter: blur(20px) !important;
    padding: 24px !important;
    border-radius: 12px !important;
    margin-bottom: 20px !important;
    border: 1px solid rgba(255, 255, 255, 0.15) !important;
}

/* Export Buttons */
.export-buttons {
    text-align: right !important;
    margin: 20px 0 !important;
}

/* Card Headers and Titles */
.card-header {
    background: rgba(30, 60, 114, 0.6) !important;
    color: #ffffff !important;
    font-weight: 600 !important;
    border-bottom: 1px solid rgba(255, 255, 255, 0.2) !important;
    border-radius: 16px 16px 0 0 !important;
}

.card-title {
    color: rgba(255, 255, 255, 0.95) !important;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3) !important;
}

/* Text and Content Styling */
h1, h2, h3, h4, h5, h6 {
    color: rgba(255, 255, 255, 0.95) !important;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3) !important;
}

.text-muted {
    color: rgba(255, 255, 255, 0.75) !important;
}

/* Table Styling */
.table {
    background: rgba(255, 255, 255, 0.08) !important;
    backdrop-filter: blur(15px) !important;
    border-radius: 12px !important;
    overflow: hidden !important;
    border: 1px solid rgba(255, 255, 255, 0.15) !important;
}

.table thead th {
    background: rgba(30, 60, 114, 0.6) !important;
    color: #ffffff !important;
    font-weight: 600 !important;
    border-bottom: 2px solid rgba(255, 255, 255, 0.3) !important;
}

.table tbody td {
    background: rgba(255, 255, 255, 0.05) !important;
    color: rgba(255, 255, 255, 0.95) !important;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1) !important;
}

.table tbody tr:hover td {
    background: rgba(255, 255, 255, 0.12) !important;
}

/* Button Enhancements */
.btn {
    backdrop-filter: blur(10px) !important;
    border-radius: 8px !important;
    font-weight: 500 !important;
    transition: all 0.3s ease !important;
}

.btn:hover {
    transform: translateY(-1px) !important;
}

/* Mobile Responsive */
@media (max-width: 768px) {
    .metric-card .metric-value {
        font-size: 2.2rem !important;
    }
    
    .engagement-metrics {
        grid-template-columns: 1fr !important;
        gap: 15px !important;
    }
    
    .chart-container {
        height: 300px !important;
        padding: 10px !important;
    }
}
</style>

<div class="container py-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1><i class="bi bi-bar-chart-line me-2 text-info"></i>Pizza Tracker Analytics</h1>
                    <p class="text-muted">Advanced insights and performance metrics for your pizza trackers</p>
                </div>
                <a href="pizza-tracker.php" class="btn btn-outline-light">
                    <i class="bi bi-arrow-left me-2"></i>Back to Trackers
                </a>
            </div>
        </div>
    </div>

    <!-- Filters Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card gradient-card-primary shadow-lg">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-funnel me-2"></i>Analytics Filters</h5>
                </div>
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Start Date</label>
                            <input type="date" class="form-control" name="start_date" value="<?php echo $start_date; ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">End Date</label>
                            <input type="date" class="form-control" name="end_date" value="<?php echo $end_date; ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Pizza Tracker</label>
                            <select class="form-select" name="tracker_id">
                                <option value="all" <?php echo $tracker_id === 'all' ? 'selected' : ''; ?>>All Trackers</option>
                                <?php foreach ($trackers as $tracker): ?>
                                    <option value="<?php echo $tracker['id']; ?>" 
                                            <?php echo $tracker_id == $tracker['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($tracker['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <button type="submit" class="btn btn-primary d-block w-100">
                                <i class="bi bi-funnel me-2"></i>Apply Filters
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Key Metrics Row -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="metric-card">
                <div class="metric-value">$<?php echo number_format($analytics['total_revenue'], 2); ?></div>
                <div class="metric-label">Total Revenue</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="metric-card">
                <div class="metric-value"><?php echo $analytics['pizzas_earned']; ?></div>
                <div class="metric-label">Pizzas Earned</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="metric-card">
                <div class="metric-value"><?php echo number_format($analytics['avg_progress'], 1); ?>%</div>
                <div class="metric-label">Average Progress</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="metric-card">
                <div class="metric-value"><?php echo $analytics['active_trackers']; ?></div>
                <div class="metric-label">Active Trackers</div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row">
        <!-- Revenue Trends Chart -->
        <div class="col-md-8 mb-4">
            <div class="card gradient-card-primary shadow-lg">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-graph-up me-2"></i>Revenue Trends</h5>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="revenueChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Progress Overview -->
        <div class="col-md-4 mb-4">
            <div class="card metrics-glass-card shadow-lg">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-pie-chart me-2"></i>Progress Overview</h5>
                </div>
                <div class="card-body">
                    <div class="chart-container" style="height: 300px;">
                        <canvas id="progressChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Engagement Analytics -->
    <div class="row">
        <div class="col-md-6 mb-4">
            <div class="card gradient-card-primary shadow-lg">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-people me-2"></i>Engagement Metrics</h5>
                </div>
                <div class="card-body">
                    <div class="engagement-metrics">
                        <div class="engagement-item">
                            <h4><?php echo $analytics['total_clicks']; ?></h4>
                            <p class="mb-0">Total Clicks</p>
                        </div>
                        <div class="engagement-item">
                            <h4><?php echo $analytics['unique_visitors']; ?></h4>
                            <p class="mb-0">Unique Visitors</p>
                        </div>
                        <div class="engagement-item">
                            <h4><?php echo number_format($analytics['avg_session_duration'], 1); ?>s</h4>
                            <p class="mb-0">Avg Session</p>
                        </div>
                        <div class="engagement-item">
                            <h4><?php echo number_format($analytics['bounce_rate'], 1); ?>%</h4>
                            <p class="mb-0">Bounce Rate</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6 mb-4">
            <div class="card metrics-glass-card shadow-lg">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-clock me-2"></i>Activity Timeline</h5>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="activityChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Promotional Message Analytics -->
    <div class="row">
        <div class="col-12 mb-4">
            <div class="card gradient-card-primary shadow-lg">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-megaphone-fill me-2"></i>Promotional Message Analytics</h5>
                </div>
                <div class="card-body">
                    <?php
                    // Get promotional message analytics for all trackers
                    $promo_stmt = $pdo->prepare("
                        SELECT 
                            pt.name,
                            pt.promo_message,
                            pt.promo_active,
                            pt.promo_views,
                            pt.promo_clicks,
                            pt.promo_updated_at,
                            pt.promo_expire_date,
                            CASE 
                                WHEN pt.promo_views > 0 THEN ROUND((pt.promo_clicks / pt.promo_views) * 100, 2)
                                ELSE 0 
                            END as click_through_rate
                        FROM pizza_trackers pt
                        WHERE pt.business_id = ? 
                        AND (pt.promo_message IS NOT NULL AND pt.promo_message != '')
                        ORDER BY pt.promo_views DESC, pt.promo_clicks DESC
                    ");
                    $promo_stmt->execute([$business_id]);
                    $promo_analytics = $promo_stmt->fetchAll();
                    ?>
                    
                    <?php if (!empty($promo_analytics)): ?>
                        <div class="engagement-metrics">
                            <?php 
                            $total_views = array_sum(array_column($promo_analytics, 'promo_views'));
                            $total_clicks = array_sum(array_column($promo_analytics, 'promo_clicks'));
                            $avg_ctr = $total_views > 0 ? round(($total_clicks / $total_views) * 100, 2) : 0;
                            ?>
                            <div class="engagement-item">
                                <h4><?php echo number_format($total_views); ?></h4>
                                <p class="mb-0">Total Promo Views</p>
                            </div>
                            <div class="engagement-item">
                                <h4><?php echo number_format($total_clicks); ?></h4>
                                <p class="mb-0">Total Promo Clicks</p>
                            </div>
                            <div class="engagement-item">
                                <h4><?php echo $avg_ctr; ?>%</h4>
                                <p class="mb-0">Avg Click Rate</p>
                            </div>
                            <div class="engagement-item">
                                <h4><?php echo count($promo_analytics); ?></h4>
                                <p class="mb-0">Active Promotions</p>
                            </div>
                        </div>
                        
                        <div class="row mt-4">
                            <?php foreach ($promo_analytics as $promo): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="p-3 rounded" style="background: rgba(255, 255, 255, 0.08); border: 1px solid rgba(255, 255, 255, 0.1);">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <strong style="color: rgba(255, 255, 255, 0.95);">
                                                <?php echo htmlspecialchars($promo['name']); ?>
                                            </strong>
                                            <span class="badge bg-<?php echo $promo['promo_active'] ? 'success' : 'secondary'; ?>">
                                                <?php echo $promo['promo_active'] ? 'Active' : 'Inactive'; ?>
                                            </span>
                                        </div>
                                        <p class="mb-2" style="color: rgba(255, 255, 255, 0.85); font-size: 0.9rem;">
                                            "<?php echo htmlspecialchars(substr($promo['promo_message'], 0, 60)) . (strlen($promo['promo_message']) > 60 ? '...' : ''); ?>"
                                        </p>
                                        <div class="row text-center">
                                            <div class="col-4">
                                                <small class="text-muted d-block">Views</small>
                                                <strong><?php echo number_format($promo['promo_views']); ?></strong>
                                            </div>
                                            <div class="col-4">
                                                <small class="text-muted d-block">Clicks</small>
                                                <strong><?php echo number_format($promo['promo_clicks']); ?></strong>
                                            </div>
                                            <div class="col-4">
                                                <small class="text-muted d-block">CTR</small>
                                                <strong><?php echo $promo['click_through_rate']; ?>%</strong>
                                            </div>
                                        </div>
                                        <?php if ($promo['promo_expire_date']): ?>
                                            <div class="mt-2">
                                                <small class="text-muted">
                                                    Expires: <?php echo date('M j, Y', strtotime($promo['promo_expire_date'])); ?>
                                                </small>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="bi bi-megaphone display-4 text-muted mb-3"></i>
                            <h5 style="color: rgba(255, 255, 255, 0.7);">No Promotional Messages Yet</h5>
                            <p class="text-muted">Start creating promotional messages to boost engagement and track performance.</p>
                            <a href="pizza-tracker.php" class="btn btn-outline-light">
                                <i class="bi bi-plus-circle me-2"></i>Create First Promotion
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Tracker Comparison -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card gradient-card-primary shadow-lg">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-bar-chart me-2"></i>Tracker Performance Comparison</h5>
                </div>
                <div class="card-body">
                    <?php foreach ($analytics['tracker_comparison'] as $tracker): ?>
                        <div class="progress-comparison">
                            <div style="width: 150px; font-weight: bold; color: rgba(255, 255, 255, 0.95);">
                                <?php echo htmlspecialchars($tracker['name']); ?>
                            </div>
                            <div class="progress-bar-container">
                                <div class="progress-bar" style="width: <?php echo $tracker['progress_percent']; ?>%">
                                    <?php echo $tracker['progress_percent']; ?>%
                                </div>
                            </div>
                            <div style="width: 120px; text-align: right; color: rgba(255, 255, 255, 0.9);">
                                $<?php echo number_format($tracker['current_revenue'], 0); ?> / 
                                $<?php echo number_format($tracker['revenue_goal'], 0); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Traffic Sources and Top Performers -->
    <div class="row">
        <div class="col-md-8 mb-4">
            <div class="card gradient-card-primary shadow-lg">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-diagram-3 me-2"></i>Traffic Sources</h5>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="sourcesChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4 mb-4">
            <div class="card metrics-glass-card shadow-lg">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-trophy me-2"></i>Top Performers</h5>
                </div>
                <div class="card-body">
                    <?php foreach ($analytics['top_performers'] as $index => $performer): ?>
                        <div class="d-flex align-items-center mb-3 p-2 rounded" style="background: rgba(255, 255, 255, 0.08); border: 1px solid rgba(255, 255, 255, 0.1);">
                            <div class="badge bg-<?php echo $index === 0 ? 'warning' : ($index === 1 ? 'secondary' : 'success'); ?> me-3">
                                #<?php echo $index + 1; ?>
                            </div>
                            <div>
                                <strong style="color: rgba(255, 255, 255, 0.95);"><?php echo htmlspecialchars($performer['name']); ?></strong><br>
                                <small class="text-muted">
                                    <?php echo $performer['completions']; ?> pizzas earned
                                </small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Predictions & Insights -->
    <div class="row">
        <div class="col-md-6 mb-4">
            <div class="card metrics-glass-card shadow-lg">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-lightbulb me-2"></i>Predictions & Insights</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3 p-3 rounded" style="background: rgba(255, 255, 255, 0.08); border: 1px solid rgba(255, 255, 255, 0.1);">
                        <strong style="color: rgba(255, 255, 255, 0.95);">Goal Completion Forecast:</strong><br>
                        <small class="text-muted">Based on current trends, you'll reach your next pizza goal in 
                        <?php echo $analytics['prediction']['days_to_goal']; ?> days</small>
                    </div>
                    <div class="mb-3 p-3 rounded" style="background: rgba(255, 255, 255, 0.08); border: 1px solid rgba(255, 255, 255, 0.1);">
                        <strong style="color: rgba(255, 255, 255, 0.95);">Revenue Velocity:</strong><br>
                        <small class="text-muted">$<?php echo number_format($analytics['prediction']['daily_average'], 2); ?> per day average</small>
                    </div>
                    <div class="p-3 rounded" style="background: rgba(255, 255, 255, 0.08); border: 1px solid rgba(255, 255, 255, 0.1);">
                        <strong style="color: rgba(255, 255, 255, 0.95);">Peak Hours:</strong><br>
                        <small class="text-muted">Most activity between <?php echo $analytics['prediction']['peak_hours']; ?></small>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6 mb-4">
            <div class="card gradient-card-primary shadow-lg">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-calendar-event me-2"></i>Recent Milestones</h5>
                </div>
                <div class="card-body">
                    <?php foreach ($analytics['recent_milestones'] as $milestone): ?>
                        <div class="d-flex align-items-center mb-3 p-2 rounded" style="background: rgba(255, 255, 255, 0.08); border: 1px solid rgba(255, 255, 255, 0.1);">
                            <div class="text-success me-3">
                                <i class="bi bi-check-circle-fill"></i>
                            </div>
                            <div>
                                <strong style="color: rgba(255, 255, 255, 0.95);"><?php echo $milestone['achievement']; ?></strong><br>
                                <small class="text-muted"><?php echo $milestone['date']; ?></small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Export Options -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card metrics-glass-card shadow-lg">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-download me-2"></i>Export Reports</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <button class="btn btn-outline-light w-100" onclick="exportData('pdf')">
                                <i class="bi bi-file-earmark-pdf me-2"></i>Export to PDF
                            </button>
                        </div>
                        <div class="col-md-3">
                            <button class="btn btn-outline-light w-100" onclick="exportData('csv')">
                                <i class="bi bi-file-earmark-spreadsheet me-2"></i>Export to CSV
                            </button>
                        </div>
                        <div class="col-md-3">
                            <button class="btn btn-outline-light w-100" onclick="exportData('excel')">
                                <i class="bi bi-file-earmark-excel me-2"></i>Export to Excel
                            </button>
                        </div>
                        <div class="col-md-3">
                            <button class="btn btn-outline-light w-100" onclick="window.print()">
                                <i class="bi bi-printer me-2"></i>Print Report
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Prepare data for charts
const revenueData = <?php echo json_encode($analytics['revenue_timeline']); ?>;
const progressData = <?php echo json_encode($analytics['progress_distribution']); ?>;
const activityData = <?php echo json_encode($analytics['activity_timeline']); ?>;
const sourcesData = <?php echo json_encode($analytics['traffic_sources']); ?>;

// Revenue Trends Chart
const revenueCtx = document.getElementById('revenueChart').getContext('2d');
new Chart(revenueCtx, {
    type: 'line',
    data: {
        labels: revenueData.labels,
        datasets: [{
            label: 'Daily Revenue',
            data: revenueData.values,
            borderColor: '#ff6b6b',
            backgroundColor: 'rgba(255, 107, 107, 0.1)',
            tension: 0.4,
            fill: true
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return '$' + value;
                    }
                }
            }
        }
    }
});

// Progress Distribution Chart
const progressCtx = document.getElementById('progressChart').getContext('2d');
new Chart(progressCtx, {
    type: 'doughnut',
    data: {
        labels: progressData.labels,
        datasets: [{
            data: progressData.values,
            backgroundColor: [
                '#ff6b6b',
                '#feca57',
                '#48cae4',
                '#06d6a0',
                '#f72585'
            ]
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});

// Activity Timeline Chart
const activityCtx = document.getElementById('activityChart').getContext('2d');
new Chart(activityCtx, {
    type: 'bar',
    data: {
        labels: activityData.labels,
        datasets: [{
            label: 'Page Views',
            data: activityData.values,
            backgroundColor: 'rgba(255, 107, 107, 0.8)'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});

// Traffic Sources Chart
const sourcesCtx = document.getElementById('sourcesChart').getContext('2d');
new Chart(sourcesCtx, {
    type: 'horizontalBar',
    data: {
        labels: sourcesData.labels,
        datasets: [{
            data: sourcesData.values,
            backgroundColor: [
                '#ff6b6b',
                '#feca57',
                '#48cae4',
                '#06d6a0'
            ]
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        indexAxis: 'y',
        plugins: {
            legend: {
                display: false
            }
        }
    }
});

// Export functions
function exportData(format) {
    const params = new URLSearchParams({
        start_date: '<?php echo $start_date; ?>',
        end_date: '<?php echo $end_date; ?>',
        tracker_id: '<?php echo $tracker_id; ?>',
        format: format
    });
    
    switch(format) {
        case 'pdf':
            window.print();
            break;
        case 'csv':
            window.location.href = '/api/analytics/export-csv.php?' + params.toString();
            break;
        case 'excel':
            window.location.href = '/api/analytics/export-excel.php?' + params.toString();
            break;
        default:
            console.error('Unknown export format:', format);
    }
}

// Auto-refresh every 5 minutes
setInterval(() => {
    location.reload();
}, 300000);
</script>

<?php require_once __DIR__ . '/../core/includes/footer.php'; ?> 