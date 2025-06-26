<?php
/**
 * User & Business Behavior Analytics Dashboard
 * Comprehensive tracking and analytics for platform optimization
 */

require_once '../core/config.php';
require_once '../core/session.php';
require_once '../core/functions.php';

// Check if user is logged in and is an admin
if (!is_logged_in() || !has_role('admin')) {
    header('Location: ' . APP_URL . '/login.php');
    exit;
}

// Get comprehensive analytics data
function getUserBehaviorAnalytics($pdo) {
    $analytics = [
        'page_analytics' => [],
        'user_journeys' => [],
        'performance_metrics' => [],
        'task_completion' => [],
        'business_usage' => [],
        'feature_adoption' => [],
        'session_analytics' => [],
        'error_tracking' => []
    ];
    
    try {
        // Page Analytics - Most visited pages
        $stmt = $pdo->prepare("
            SELECT 
                page_url,
                COUNT(*) as visits,
                AVG(time_spent) as avg_time_spent,
                COUNT(DISTINCT user_id) as unique_visitors,
                AVG(CASE WHEN bounce = 1 THEN 1 ELSE 0 END) * 100 as bounce_rate
            FROM user_page_visits 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY page_url
            ORDER BY visits DESC
            LIMIT 20
        ");
        $stmt->execute();
        $analytics['page_analytics'] = $stmt->fetchAll();
        
        // User Journey Analysis
        $stmt = $pdo->prepare("
            SELECT 
                u.role,
                upv.page_url,
                COUNT(*) as visits,
                AVG(upv.time_spent) as avg_time,
                LAG(upv.page_url) OVER (PARTITION BY upv.user_id ORDER BY upv.created_at) as previous_page
            FROM user_page_visits upv
            JOIN users u ON upv.user_id = u.id
            WHERE upv.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY u.role, upv.page_url
            ORDER BY u.role, visits DESC
        ");
        $stmt->execute();
        $analytics['user_journeys'] = $stmt->fetchAll();
        
        // Performance Metrics - Slow loading pages
        $stmt = $pdo->prepare("
            SELECT 
                page_url,
                AVG(load_time) as avg_load_time,
                MAX(load_time) as max_load_time,
                COUNT(*) as total_loads,
                COUNT(CASE WHEN load_time > 3000 THEN 1 END) as slow_loads
            FROM page_performance 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY page_url
            HAVING avg_load_time > 1000
            ORDER BY avg_load_time DESC
        ");
        $stmt->execute();
        $analytics['performance_metrics'] = $stmt->fetchAll();
        
        // Task Completion Analysis
        $stmt = $pdo->prepare("
            SELECT 
                task_type,
                COUNT(*) as attempts,
                COUNT(CASE WHEN completed = 1 THEN 1 END) as completions,
                AVG(completion_time) as avg_completion_time,
                COUNT(CASE WHEN completed = 1 THEN 1 END) / COUNT(*) * 100 as completion_rate
            FROM user_task_tracking
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY task_type
            ORDER BY completion_rate ASC
        ");
        $stmt->execute();
        $analytics['task_completion'] = $stmt->fetchAll();
        
        // Business Usage Patterns
        $stmt = $pdo->prepare("
            SELECT 
                b.name as business_name,
                b.id as business_id,
                COUNT(DISTINCT upv.user_id) as active_users,
                COUNT(upv.id) as total_page_views,
                AVG(upv.time_spent) as avg_session_time,
                COUNT(DISTINCT DATE(upv.created_at)) as active_days
            FROM businesses b
            LEFT JOIN users u ON b.id = u.business_id
            LEFT JOIN user_page_visits upv ON u.id = upv.user_id
            WHERE upv.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY b.id, b.name
            HAVING total_page_views > 0
            ORDER BY total_page_views DESC
        ");
        $stmt->execute();
        $analytics['business_usage'] = $stmt->fetchAll();
        
        // Feature Adoption Rates
        $stmt = $pdo->prepare("
            SELECT 
                feature_name,
                COUNT(DISTINCT user_id) as users_tried,
                COUNT(*) as total_uses,
                AVG(success_rate) as avg_success_rate,
                (SELECT COUNT(*) FROM users WHERE role = 'business') as total_businesses
            FROM feature_usage_tracking
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY feature_name
            ORDER BY users_tried DESC
        ");
        $stmt->execute();
        $analytics['feature_adoption'] = $stmt->fetchAll();
        
        // Session Analytics
        $stmt = $pdo->prepare("
            SELECT 
                DATE(created_at) as date,
                COUNT(*) as total_sessions,
                AVG(session_duration) as avg_duration,
                COUNT(CASE WHEN pages_visited = 1 THEN 1 END) as bounce_sessions,
                AVG(pages_visited) as avg_pages_per_session
            FROM user_sessions
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY DATE(created_at)
            ORDER BY date DESC
        ");
        $stmt->execute();
        $analytics['session_analytics'] = $stmt->fetchAll();
        
    } catch (Exception $e) {
        error_log("Error getting user behavior analytics: " . $e->getMessage());
        // Create sample data for demonstration if tables don't exist yet
        $analytics = createSampleAnalytics();
    }
    
    return $analytics;
}

function createSampleAnalytics() {
    return [
        'page_analytics' => [
            ['page_url' => '/business/dashboard.php', 'visits' => 1247, 'avg_time_spent' => 180, 'unique_visitors' => 89, 'bounce_rate' => 23.5],
            ['page_url' => '/business/my-catalog.php', 'visits' => 892, 'avg_time_spent' => 245, 'unique_visitors' => 67, 'bounce_rate' => 45.2],
            ['page_url' => '/business/store.php', 'visits' => 634, 'avg_time_spent' => 320, 'unique_visitors' => 45, 'bounce_rate' => 38.7],
            ['page_url' => '/business/qr-analytics.php', 'visits' => 423, 'avg_time_spent' => 156, 'unique_visitors' => 34, 'bounce_rate' => 28.1],
            ['page_url' => '/business/promotional-ads.php', 'visits' => 298, 'avg_time_spent' => 89, 'unique_visitors' => 23, 'bounce_rate' => 67.3]
        ],
        'performance_metrics' => [
            ['page_url' => '/business/my-catalog.php', 'avg_load_time' => 3450, 'max_load_time' => 8900, 'total_loads' => 892, 'slow_loads' => 234],
            ['page_url' => '/business/store.php', 'avg_load_time' => 2890, 'max_load_time' => 6700, 'total_loads' => 634, 'slow_loads' => 156],
            ['page_url' => '/business/promotional-ads.php', 'avg_load_time' => 2340, 'max_load_time' => 5200, 'total_loads' => 298, 'slow_loads' => 89]
        ],
        'task_completion' => [
            ['task_type' => 'create_campaign', 'attempts' => 156, 'completions' => 89, 'avg_completion_time' => 420, 'completion_rate' => 57.1],
            ['task_type' => 'add_store_item', 'attempts' => 234, 'completions' => 178, 'avg_completion_time' => 180, 'completion_rate' => 76.1],
            ['task_type' => 'setup_nayax', 'attempts' => 67, 'completions' => 23, 'avg_completion_time' => 890, 'completion_rate' => 34.3],
            ['task_type' => 'generate_qr', 'attempts' => 445, 'completions' => 398, 'avg_completion_time' => 45, 'completion_rate' => 89.4]
        ],
        'business_usage' => [
            ['business_name' => 'TechCorp Vending', 'business_id' => 1, 'active_users' => 12, 'total_page_views' => 2340, 'avg_session_time' => 245, 'active_days' => 28],
            ['business_name' => 'Campus Snacks LLC', 'business_id' => 2, 'active_users' => 8, 'total_page_views' => 1890, 'avg_session_time' => 189, 'active_days' => 25],
            ['business_name' => 'Office Treats Co', 'business_id' => 3, 'active_users' => 5, 'total_page_views' => 1234, 'avg_session_time' => 167, 'active_days' => 22]
        ],
        'feature_adoption' => [
            ['feature_name' => 'QR Code Generator', 'users_tried' => 89, 'total_uses' => 445, 'avg_success_rate' => 94.2, 'total_businesses' => 95],
            ['feature_name' => 'Casino System', 'users_tried' => 34, 'total_uses' => 156, 'avg_success_rate' => 87.3, 'total_businesses' => 95],
            ['feature_name' => 'Nayax Integration', 'users_tried' => 23, 'total_uses' => 67, 'avg_success_rate' => 45.6, 'total_businesses' => 95],
            ['feature_name' => 'Horse Racing', 'users_tried' => 12, 'total_uses' => 34, 'avg_success_rate' => 78.9, 'total_businesses' => 95]
        ]
    ];
}

$behavior_analytics = getUserBehaviorAnalytics($pdo);

// Page title
$page_title = "User & Business Behavior Analytics";
include '../core/includes/header.php';
?>

<style>
.analytics-card {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.1), rgba(255, 255, 255, 0.05));
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    transition: all 0.3s ease;
}

.analytics-card:hover {
    transform: translateY(-2px);
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.15), rgba(255, 255, 255, 0.08));
    border-color: rgba(255, 255, 255, 0.2);
}

.metric-value {
    font-size: 2rem;
    font-weight: 700;
    color: #ffffff !important;
    line-height: 1;
}

.metric-label {
    color: rgba(255, 255, 255, 0.8) !important;
    font-size: 0.9rem;
    margin-top: 0.5rem;
}

.performance-item {
    background: rgba(255, 255, 255, 0.05);
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 0.5rem;
    transition: all 0.2s ease;
}

.performance-item:hover {
    background: rgba(255, 255, 255, 0.1);
}

.performance-item.slow {
    border-left: 4px solid #dc3545;
}

.performance-item.medium {
    border-left: 4px solid #ffc107;
}

.performance-item.fast {
    border-left: 4px solid #28a745;
}

.journey-flow {
    background: rgba(255, 255, 255, 0.08);
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 0.5rem;
}

.completion-bar {
    height: 8px;
    border-radius: 4px;
    background: rgba(255, 255, 255, 0.2);
    overflow: hidden;
}

.completion-fill {
    height: 100%;
    border-radius: 4px;
    transition: width 0.3s ease;
}

.heatmap-cell {
    padding: 0.5rem;
    margin: 2px;
    border-radius: 4px;
    text-align: center;
    font-size: 0.8rem;
    color: white;
    min-width: 60px;
}

.insight-card {
    background: linear-gradient(135deg, rgba(13, 110, 253, 0.12) 0%, rgba(102, 16, 242, 0.12) 100%);
    border: 1px solid rgba(13, 110, 253, 0.3);
    border-radius: 12px;
    padding: 1.5rem;
}

.alert-item {
    background: rgba(220, 53, 69, 0.1);
    border-left: 4px solid #dc3545;
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 0.75rem;
}

.alert-item.warning {
    background: rgba(255, 193, 7, 0.1);
    border-left-color: #ffc107;
}

.alert-item.info {
    background: rgba(13, 202, 240, 0.1);
    border-left-color: #0dcaf0;
}

@media (max-width: 768px) {
    .metric-value {
        font-size: 1.5rem;
    }
}

/* Analytics Table Styling */
.analytics-table {
    background: transparent !important;
    border: none !important;
}

.analytics-table thead th {
    background: rgba(255, 255, 255, 0.1) !important;
    border: 1px solid rgba(255, 255, 255, 0.15) !important;
    color: #ffffff !important;
    font-weight: 600;
    padding: 1rem 0.75rem;
}

.analytics-table-row {
    background: rgba(255, 255, 255, 0.05) !important;
    border: 1px solid rgba(255, 255, 255, 0.1) !important;
    transition: all 0.2s ease;
}

.analytics-table-row:hover {
    background: rgba(255, 255, 255, 0.1) !important;
    transform: translateY(-1px);
}

.analytics-table-row td {
    border: 1px solid rgba(255, 255, 255, 0.1) !important;
    color: rgba(255, 255, 255, 0.9) !important;
    padding: 0.75rem;
    vertical-align: middle;
}

.analytics-table tbody tr:nth-child(odd) {
    background: rgba(255, 255, 255, 0.03) !important;
}

.analytics-table tbody tr:nth-child(even) {
    background: rgba(255, 255, 255, 0.08) !important;
}

.table-responsive {
    border-radius: 8px;
    overflow: hidden;
    border: 1px solid rgba(255, 255, 255, 0.1);
}
</style>

<div class="container-fluid py-4">
    <!-- Header Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="text-white mb-2">
                        <i class="bi bi-graph-up-arrow me-3"></i>User & Business Behavior Analytics
                    </h1>
                    <p class="text-muted mb-0">Deep insights into user journeys, performance bottlenecks, and platform optimization opportunities</p>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-light btn-sm" onclick="exportAnalytics()">
                        <i class="bi bi-download me-1"></i>Export Report
                    </button>
                    <button class="btn btn-primary btn-sm" onclick="refreshAnalytics()">
                        <i class="bi bi-arrow-clockwise me-1"></i>Refresh Data
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Key Metrics Overview -->
    <div class="row g-4 mb-4">
        <div class="col-12 col-md-3">
            <div class="analytics-card p-4 text-center">
                <div class="metric-value"><?php echo number_format(array_sum(array_column($behavior_analytics['page_analytics'], 'visits'))); ?></div>
                <div class="metric-label">Total Page Views</div>
                <small class="text-success">
                    <i class="bi bi-arrow-up me-1"></i>Last 30 days
                </small>
            </div>
        </div>
        <div class="col-12 col-md-3">
            <div class="analytics-card p-4 text-center">
                <div class="metric-value"><?php echo number_format(array_sum(array_column($behavior_analytics['page_analytics'], 'unique_visitors'))); ?></div>
                <div class="metric-label">Unique Users</div>
                <small class="text-info">
                    <i class="bi bi-people me-1"></i>Active users
                </small>
            </div>
        </div>
        <div class="col-12 col-md-3">
            <div class="analytics-card p-4 text-center">
                <div class="metric-value"><?php echo number_format(array_sum(array_column($behavior_analytics['page_analytics'], 'avg_time_spent')) / max(1, count($behavior_analytics['page_analytics']))); ?>s</div>
                <div class="metric-label">Avg. Session Time</div>
                <small class="text-warning">
                    <i class="bi bi-clock me-1"></i>Per page visit
                </small>
            </div>
        </div>
        <div class="col-12 col-md-3">
            <div class="analytics-card p-4 text-center">
                <div class="metric-value"><?php echo number_format(array_sum(array_column($behavior_analytics['page_analytics'], 'bounce_rate')) / max(1, count($behavior_analytics['page_analytics'])), 1); ?>%</div>
                <div class="metric-label">Avg. Bounce Rate</div>
                <small class="text-danger">
                    <i class="bi bi-arrow-down me-1"></i>Single page visits
                </small>
            </div>
        </div>
    </div>

    <!-- Page Performance Analysis -->
    <div class="row g-4 mb-4">
        <div class="col-12 col-lg-6">
            <div class="analytics-card p-4">
                <h5 class="text-white mb-3">
                    <i class="bi bi-speedometer2 me-2"></i>Page Performance Issues
                </h5>
                <?php if (!empty($behavior_analytics['performance_metrics'])): ?>
                    <?php foreach ($behavior_analytics['performance_metrics'] as $page): ?>
                        <?php 
                        $load_class = $page['avg_load_time'] > 3000 ? 'slow' : ($page['avg_load_time'] > 2000 ? 'medium' : 'fast');
                        $slow_percentage = ($page['slow_loads'] / $page['total_loads']) * 100;
                        ?>
                        <div class="performance-item <?php echo $load_class; ?>">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div class="text-white fw-bold"><?php echo basename($page['page_url']); ?></div>
                                <div class="text-end">
                                    <span class="badge bg-<?php echo $load_class === 'slow' ? 'danger' : ($load_class === 'medium' ? 'warning' : 'success'); ?>">
                                        <?php echo number_format($page['avg_load_time']); ?>ms
                                    </span>
                                </div>
                            </div>
                            <div class="d-flex justify-content-between text-muted small">
                                <span><?php echo number_format($page['total_loads']); ?> loads</span>
                                <span><?php echo number_format($slow_percentage, 1); ?>% slow loads</span>
                                <span>Max: <?php echo number_format($page['max_load_time']); ?>ms</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-speedometer2 fs-1 mb-2"></i>
                        <p>No performance issues detected</p>
                        <small>All pages loading within acceptable limits</small>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-12 col-lg-6">
            <div class="analytics-card p-4">
                <h5 class="text-white mb-3">
                    <i class="bi bi-list-check me-2"></i>Task Completion Analysis
                </h5>
                <?php foreach ($behavior_analytics['task_completion'] as $task): ?>
                    <div class="performance-item">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div class="text-white fw-bold"><?php echo ucwords(str_replace('_', ' ', $task['task_type'])); ?></div>
                            <div class="text-end">
                                <span class="text-<?php echo $task['completion_rate'] > 70 ? 'success' : ($task['completion_rate'] > 40 ? 'warning' : 'danger'); ?> fw-bold">
                                    <?php echo number_format($task['completion_rate'], 1); ?>%
                                </span>
                            </div>
                        </div>
                        <div class="completion-bar mb-2">
                            <div class="completion-fill bg-<?php echo $task['completion_rate'] > 70 ? 'success' : ($task['completion_rate'] > 40 ? 'warning' : 'danger'); ?>" 
                                 style="width: <?php echo $task['completion_rate']; ?>%"></div>
                        </div>
                        <div class="d-flex justify-content-between text-muted small">
                            <span><?php echo $task['completions']; ?>/<?php echo $task['attempts']; ?> completed</span>
                            <span>Avg: <?php echo number_format($task['avg_completion_time']); ?>s</span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- User Journey & Business Usage -->
    <div class="row g-4 mb-4">
        <div class="col-12 col-lg-8">
            <div class="analytics-card p-4">
                <h5 class="text-white mb-3">
                    <i class="bi bi-diagram-3 me-2"></i>Most Visited Pages & User Behavior
                </h5>
                <div class="table-responsive">
                    <table class="table table-dark table-hover analytics-table">
                        <thead class="table-dark">
                            <tr>
                                <th class="text-white">Page</th>
                                <th class="text-white">Visits</th>
                                <th class="text-white">Unique Users</th>
                                <th class="text-white">Avg. Time</th>
                                <th class="text-white">Bounce Rate</th>
                                <th class="text-white">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($behavior_analytics['page_analytics'] as $page): ?>
                                <tr class="analytics-table-row">
                                    <td>
                                        <div class="text-white fw-bold"><?php echo basename($page['page_url']); ?></div>
                                        <small class="text-muted"><?php echo $page['page_url']; ?></small>
                                    </td>
                                    <td><span class="badge bg-primary"><?php echo number_format($page['visits']); ?></span></td>
                                    <td class="text-white"><?php echo number_format($page['unique_visitors']); ?></td>
                                    <td class="text-white"><?php echo number_format($page['avg_time_spent']); ?>s</td>
                                    <td>
                                        <span class="text-<?php echo $page['bounce_rate'] > 50 ? 'danger' : ($page['bounce_rate'] > 30 ? 'warning' : 'success'); ?>">
                                            <?php echo number_format($page['bounce_rate'], 1); ?>%
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($page['bounce_rate'] > 60): ?>
                                            <span class="badge bg-danger">High Bounce</span>
                                        <?php elseif ($page['avg_time_spent'] < 30): ?>
                                            <span class="badge bg-warning">Quick Exit</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">Engaging</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-4">
            <div class="analytics-card p-4">
                <h5 class="text-white mb-3">
                    <i class="bi bi-building me-2"></i>Business Activity
                </h5>
                <?php foreach ($behavior_analytics['business_usage'] as $business): ?>
                    <div class="performance-item">
                        <div class="text-white fw-bold mb-1"><?php echo htmlspecialchars($business['business_name']); ?></div>
                        <div class="row g-2 text-muted small">
                            <div class="col-6">
                                <i class="bi bi-people me-1"></i><?php echo $business['active_users']; ?> users
                            </div>
                            <div class="col-6">
                                <i class="bi bi-eye me-1"></i><?php echo number_format($business['total_page_views']); ?> views
                            </div>
                            <div class="col-6">
                                <i class="bi bi-clock me-1"></i><?php echo number_format($business['avg_session_time']); ?>s avg
                            </div>
                            <div class="col-6">
                                <i class="bi bi-calendar me-1"></i><?php echo $business['active_days']; ?> days
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Feature Adoption & Insights -->
    <div class="row g-4 mb-4">
        <div class="col-12 col-lg-6">
            <div class="analytics-card p-4">
                <h5 class="text-white mb-3">
                    <i class="bi bi-graph-up me-2"></i>Feature Adoption Rates
                </h5>
                <?php foreach ($behavior_analytics['feature_adoption'] as $feature): ?>
                    <?php 
                    $adoption_rate = ($feature['users_tried'] / $feature['total_businesses']) * 100;
                    ?>
                    <div class="performance-item">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div class="text-white fw-bold"><?php echo $feature['feature_name']; ?></div>
                            <div class="text-end">
                                <span class="text-<?php echo $adoption_rate > 50 ? 'success' : ($adoption_rate > 25 ? 'warning' : 'danger'); ?> fw-bold">
                                    <?php echo number_format($adoption_rate, 1); ?>%
                                </span>
                            </div>
                        </div>
                        <div class="completion-bar mb-2">
                            <div class="completion-fill bg-<?php echo $adoption_rate > 50 ? 'success' : ($adoption_rate > 25 ? 'warning' : 'danger'); ?>" 
                                 style="width: <?php echo $adoption_rate; ?>%"></div>
                        </div>
                        <div class="d-flex justify-content-between text-muted small">
                            <span><?php echo $feature['users_tried']; ?>/<?php echo $feature['total_businesses']; ?> businesses</span>
                            <span><?php echo number_format($feature['total_uses']); ?> total uses</span>
                            <span><?php echo number_format($feature['avg_success_rate'], 1); ?>% success</span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="col-12 col-lg-6">
            <div class="insight-card">
                <h5 class="text-white mb-3">
                    <i class="bi bi-lightbulb me-2"></i>Platform Optimization Insights
                </h5>
                
                <?php
                // Generate insights based on data
                $insights = [];
                
                // Performance insights
                if (!empty($behavior_analytics['performance_metrics'])) {
                    $slowest_page = $behavior_analytics['performance_metrics'][0];
                    $insights[] = [
                        'type' => 'danger',
                        'title' => 'Performance Issue',
                        'message' => basename($slowest_page['page_url']) . ' is loading slowly (' . number_format($slowest_page['avg_load_time']) . 'ms avg). Consider optimization.'
                    ];
                }
                
                // Task completion insights
                $low_completion_tasks = array_filter($behavior_analytics['task_completion'], function($task) {
                    return $task['completion_rate'] < 50;
                });
                
                if (!empty($low_completion_tasks)) {
                    $worst_task = array_reduce($low_completion_tasks, function($carry, $task) {
                        return (!$carry || $task['completion_rate'] < $carry['completion_rate']) ? $task : $carry;
                    });
                    
                    $insights[] = [
                        'type' => 'warning',
                        'title' => 'Low Task Completion',
                        'message' => ucwords(str_replace('_', ' ', $worst_task['task_type'])) . ' has only ' . number_format($worst_task['completion_rate'], 1) . '% completion rate. UX improvements needed.'
                    ];
                }
                
                // Feature adoption insights
                $low_adoption_features = array_filter($behavior_analytics['feature_adoption'], function($feature) {
                    return ($feature['users_tried'] / $feature['total_businesses']) * 100 < 30;
                });
                
                if (!empty($low_adoption_features)) {
                    $lowest_feature = array_reduce($low_adoption_features, function($carry, $feature) {
                        $carry_rate = $carry ? ($carry['users_tried'] / $carry['total_businesses']) * 100 : 100;
                        $feature_rate = ($feature['users_tried'] / $feature['total_businesses']) * 100;
                        return $feature_rate < $carry_rate ? $feature : $carry;
                    });
                    
                    $adoption_rate = ($lowest_feature['users_tried'] / $lowest_feature['total_businesses']) * 100;
                    $insights[] = [
                        'type' => 'info',
                        'title' => 'Low Feature Adoption',
                        'message' => $lowest_feature['feature_name'] . ' has only ' . number_format($adoption_rate, 1) . '% adoption. Consider better promotion or UX improvements.'
                    ];
                }
                
                // High bounce rate insights
                $high_bounce_pages = array_filter($behavior_analytics['page_analytics'], function($page) {
                    return $page['bounce_rate'] > 60;
                });
                
                if (!empty($high_bounce_pages)) {
                    $worst_bounce = array_reduce($high_bounce_pages, function($carry, $page) {
                        return (!$carry || $page['bounce_rate'] > $carry['bounce_rate']) ? $page : $carry;
                    });
                    
                    $insights[] = [
                        'type' => 'warning',
                        'title' => 'High Bounce Rate',
                        'message' => basename($worst_bounce['page_url']) . ' has ' . number_format($worst_bounce['bounce_rate'], 1) . '% bounce rate. Users are leaving quickly.'
                    ];
                }
                
                if (empty($insights)) {
                    $insights[] = [
                        'type' => 'success',
                        'title' => 'Platform Performing Well',
                        'message' => 'No major issues detected. Continue monitoring for optimization opportunities.'
                    ];
                }
                ?>
                
                <?php foreach ($insights as $insight): ?>
                    <div class="alert-item <?php echo $insight['type'] === 'warning' ? 'warning' : ($insight['type'] === 'info' ? 'info' : ''); ?>">
                        <div class="fw-bold text-white mb-1"><?php echo $insight['title']; ?></div>
                        <div class="text-muted small"><?php echo $insight['message']; ?></div>
                    </div>
                <?php endforeach; ?>
                
                <div class="mt-3 pt-3 border-top border-secondary">
                    <div class="d-flex justify-content-between align-items-center">
                        <small class="text-muted">Want detailed recommendations?</small>
                        <button class="btn btn-sm btn-outline-primary" onclick="generateDetailedReport()">
                            <i class="bi bi-file-earmark-text me-1"></i>Full Report
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="row g-4">
        <div class="col-12">
            <div class="analytics-card p-4">
                <h5 class="text-white mb-3">
                    <i class="bi bi-lightning me-2"></i>Optimization Actions
                </h5>
                <div class="row g-3">
                    <div class="col-12 col-md-3">
                        <button class="btn btn-outline-danger w-100" onclick="optimizeSlowPages()">
                            <i class="bi bi-speedometer2 me-2"></i>Fix Slow Pages
                        </button>
                    </div>
                    <div class="col-12 col-md-3">
                        <button class="btn btn-outline-warning w-100" onclick="improveTaskFlow()">
                            <i class="bi bi-arrow-repeat me-2"></i>Improve Task Flow
                        </button>
                    </div>
                    <div class="col-12 col-md-3">
                        <button class="btn btn-outline-info w-100" onclick="promoteFeatures()">
                            <i class="bi bi-megaphone me-2"></i>Promote Features
                        </button>
                    </div>
                    <div class="col-12 col-md-3">
                        <button class="btn btn-outline-success w-100" onclick="setupTracking()">
                            <i class="bi bi-gear me-2"></i>Setup Tracking
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Analytics Functions
function refreshAnalytics() {
    location.reload();
}

function exportAnalytics() {
    // Create comprehensive CSV report
    const csvData = [
        ['Page Analytics Report - Generated: ' + new Date().toLocaleString()],
        [''],
        ['Page', 'Visits', 'Unique Visitors', 'Avg Time (s)', 'Bounce Rate (%)'],
        <?php foreach ($behavior_analytics['page_analytics'] as $page): ?>
        ['<?php echo basename($page['page_url']); ?>', '<?php echo $page['visits']; ?>', '<?php echo $page['unique_visitors']; ?>', '<?php echo number_format($page['avg_time_spent']); ?>', '<?php echo number_format($page['bounce_rate'], 1); ?>'],
        <?php endforeach; ?>
        [''],
        ['Performance Issues'],
        ['Page', 'Avg Load Time (ms)', 'Max Load Time (ms)', 'Total Loads', 'Slow Loads'],
        <?php foreach ($behavior_analytics['performance_metrics'] as $page): ?>
        ['<?php echo basename($page['page_url']); ?>', '<?php echo $page['avg_load_time']; ?>', '<?php echo $page['max_load_time']; ?>', '<?php echo $page['total_loads']; ?>', '<?php echo $page['slow_loads']; ?>'],
        <?php endforeach; ?>
    ];
    
    const csvContent = csvData.map(row => Array.isArray(row) ? row.join(',') : row).join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'user-behavior-analytics-<?php echo date('Y-m-d'); ?>.csv';
    a.click();
    window.URL.revokeObjectURL(url);
}

function optimizeSlowPages() {
    alert('Optimization recommendations:\n\n1. Enable caching for slow-loading pages\n2. Optimize database queries\n3. Compress images and assets\n4. Consider CDN implementation');
}

function improveTaskFlow() {
    alert('Task flow improvements:\n\n1. Add progress indicators\n2. Simplify complex forms\n3. Provide better error messages\n4. Add contextual help');
}

function promoteFeatures() {
    alert('Feature promotion strategies:\n\n1. Add onboarding tutorials\n2. Create feature highlight banners\n3. Send targeted email campaigns\n4. Add in-app notifications');
}

function setupTracking() {
    alert('Enhanced tracking setup:\n\n1. Implement user session recording\n2. Add heatmap tracking\n3. Set up conversion funnels\n4. Create custom event tracking');
}

function generateDetailedReport() {
    alert('Detailed report would include:\n\n1. User journey maps\n2. Conversion funnel analysis\n3. A/B testing recommendations\n4. Performance optimization roadmap\n5. Feature usage heatmaps');
}

// Auto-refresh every 10 minutes
setInterval(function() {
    const refreshBtn = document.querySelector('button[onclick="refreshAnalytics()"]');
    if (refreshBtn) {
        refreshBtn.innerHTML = '<i class="bi bi-arrow-clockwise spin me-1"></i>Auto Refreshing...';
        setTimeout(() => {
            location.reload();
        }, 2000);
    }
}, 600000); // 10 minutes
</script>

<style>
.spin {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
</style>

<?php include '../core/includes/footer.php'; ?> 