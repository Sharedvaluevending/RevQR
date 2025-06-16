<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';

// Require business role
require_role('business');

// Get business ID
$stmt = $pdo->prepare("SELECT id, name FROM businesses WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$business = $stmt->fetch();

if (!$business) {
    header('Location: manage-campaigns.php');
    exit;
}

$business_id = $business['id'];

// Enhanced Campaign Analytics with Performance Metrics
$stmt = $pdo->prepare("
    SELECT 
        c.id,
        c.name,
        c.status,
        c.created_at,
        c.description,
        COUNT(DISTINCT qr.id) as qr_count,
        COUNT(DISTINCT v.id) as total_votes,
        COUNT(DISTINCT v.user_id) as unique_voters,
        COUNT(DISTINCT CASE WHEN v.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN v.id END) as votes_last_7_days,
        COUNT(DISTINCT CASE WHEN v.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN v.id END) as votes_last_30_days,
        MAX(v.created_at) as last_vote,
        MIN(v.created_at) as first_vote,
        DATEDIFF(IFNULL(MAX(v.created_at), NOW()), c.created_at) as campaign_age_days,
        ROUND(COUNT(DISTINCT v.id) / GREATEST(DATEDIFF(IFNULL(MAX(v.created_at), NOW()), c.created_at), 1), 2) as votes_per_day,
        ROUND(COUNT(DISTINCT v.user_id) / GREATEST(COUNT(DISTINCT v.id), 1) * 100, 1) as unique_engagement_rate
    FROM campaigns c
    LEFT JOIN qr_codes qr ON c.id = qr.campaign_id
    LEFT JOIN votes v ON qr.id = v.qr_code_id
    WHERE c.business_id = ?
    GROUP BY c.id, c.name, c.status, c.created_at, c.description
    ORDER BY c.created_at DESC
");
$stmt->execute([$business_id]);
$campaigns = $stmt->fetchAll();

// QR Code Performance by Campaign
$stmt = $pdo->prepare("
    SELECT 
        c.name as campaign_name,
        c.id as campaign_id,
        qr.qr_type,
        COUNT(qr.id) as qr_count,
        COUNT(v.id) as total_scans,
        COUNT(CASE WHEN v.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN v.id END) as recent_scans,
        ROUND(AVG(CASE WHEN v.id IS NOT NULL THEN 1 ELSE 0 END) * 100, 1) as scan_rate
    FROM campaigns c
    LEFT JOIN qr_codes qr ON c.id = qr.campaign_id
    LEFT JOIN votes v ON qr.id = v.qr_code_id
    WHERE c.business_id = ?
    GROUP BY c.id, c.name, qr.qr_type
    HAVING qr_count > 0
    ORDER BY total_scans DESC
");
$stmt->execute([$business_id]);
$qr_performance = $stmt->fetchAll();

// Hourly voting patterns for insights
$stmt = $pdo->prepare("
    SELECT 
        HOUR(v.created_at) as hour,
        COUNT(*) as vote_count,
        COUNT(DISTINCT v.user_id) as unique_users
    FROM votes v
    JOIN qr_codes qr ON v.qr_code_id = qr.id
    JOIN campaigns c ON qr.campaign_id = c.id
    WHERE c.business_id = ? 
    AND v.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY HOUR(v.created_at)
    ORDER BY hour
");
$stmt->execute([$business_id]);
$hourly_patterns = $stmt->fetchAll();

// Daily vote statistics for trend analysis (last 30 days)
$stmt = $pdo->prepare("
    SELECT 
        DATE(v.created_at) as vote_date,
        COUNT(*) as vote_count,
        COUNT(DISTINCT v.user_id) as unique_voters,
        COUNT(DISTINCT qr.campaign_id) as active_campaigns
    FROM votes v
    JOIN qr_codes qr ON v.qr_code_id = qr.id
    JOIN campaigns c ON qr.campaign_id = c.id
    WHERE c.business_id = ? 
    AND v.created_at >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY)
    GROUP BY DATE(v.created_at)
    ORDER BY vote_date
");
$stmt->execute([$business_id]);
$daily_votes = $stmt->fetchAll();

// Campaign status distribution
$campaign_stats = [
    'active' => 0,
    'inactive' => 0,
    'completed' => 0
];
foreach ($campaigns as $campaign) {
    $campaign_stats[$campaign['status']]++;
}

// Performance insights calculations
$total_campaigns = count($campaigns);
$total_qr_codes = array_sum(array_column($campaigns, 'qr_count'));
$total_votes = array_sum(array_column($campaigns, 'total_votes'));
$total_unique_voters = array_sum(array_column($campaigns, 'unique_voters'));
$avg_votes_per_campaign = $total_campaigns > 0 ? round($total_votes / $total_campaigns, 1) : 0;
$avg_engagement_rate = $total_campaigns > 0 ? round(array_sum(array_column($campaigns, 'unique_engagement_rate')) / $total_campaigns, 1) : 0;

// Performance score calculation
$performance_score = 0;
if ($total_campaigns > 0) {
    $active_campaign_ratio = $campaign_stats['active'] / $total_campaigns;
    $votes_per_qr = $total_qr_codes > 0 ? $total_votes / $total_qr_codes : 0;
    $recent_activity = array_sum(array_column($campaigns, 'votes_last_7_days')) / max($total_votes, 1);
    
    $performance_score = round(($active_campaign_ratio * 30) + min($votes_per_qr * 20, 40) + ($recent_activity * 30), 0);
}

// AI-powered insights generation
$insights = [];
if ($total_campaigns > 0) {
    // Performance insights
    if ($performance_score >= 80) {
        $insights[] = [
            'type' => 'success',
            'icon' => 'bi-trophy',
            'title' => 'Excellent Campaign Performance!',
            'message' => "Your campaigns are performing exceptionally well with a {$performance_score}% performance score."
        ];
    } elseif ($performance_score >= 60) {
        $insights[] = [
            'type' => 'info',
            'icon' => 'bi-graph-up',
            'title' => 'Good Performance with Room for Growth',
            'message' => "Your campaigns show solid performance ({$performance_score}%). Consider optimizing underperforming campaigns."
        ];
    } else {
        $insights[] = [
            'type' => 'warning',
            'icon' => 'bi-exclamation-triangle',
            'title' => 'Performance Needs Attention',
            'message' => "Your campaign performance ({$performance_score}%) could be improved. Review inactive campaigns and QR code placement."
        ];
    }
    
    // Engagement insights
    if ($avg_engagement_rate > 75) {
        $insights[] = [
            'type' => 'success',
            'icon' => 'bi-people',
            'title' => 'High User Engagement',
            'message' => "Excellent unique engagement rate of {$avg_engagement_rate}% shows strong audience connection."
        ];
    } elseif ($avg_engagement_rate < 25) {
        $insights[] = [
            'type' => 'warning',
            'icon' => 'bi-arrow-down',
            'title' => 'Low Unique Engagement',
            'message' => "Consider diversifying campaign content to attract more unique voters (current: {$avg_engagement_rate}%)."
        ];
    }
    
    // Activity pattern insights
    if (!empty($hourly_patterns)) {
        $peak_hour = array_reduce($hourly_patterns, function($carry, $item) {
            return ($carry === null || $item['vote_count'] > $carry['vote_count']) ? $item : $carry;
        });
        if ($peak_hour) {
            $insights[] = [
                'type' => 'info',
                'icon' => 'bi-clock',
                'title' => 'Peak Activity Time Identified',
                'message' => "Most votes occur at {$peak_hour['hour']}:00. Consider timing campaigns around this peak."
            ];
        }
    }
}

require_once __DIR__ . '/../core/includes/header.php';
?>

<style>
/* Enhanced styling for the analytics dashboard */
.analytics-dashboard {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 15px;
    padding: 2rem;
    color: white;
    margin-bottom: 2rem;
}

.metric-card {
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(20px);
    border-radius: 12px;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.metric-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.1);
}

.performance-score {
    font-size: 3rem;
    font-weight: bold;
    background: linear-gradient(45deg, #FFD700, #FFA500);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.insight-card {
    border-left: 4px solid;
    background: rgba(255, 255, 255, 0.05);
    backdrop-filter: blur(10px);
}

.insight-card.success { border-left-color: #28a745; }
.insight-card.info { border-left-color: #17a2b8; }
.insight-card.warning { border-left-color: #ffc107; }

/* OVERRIDE HEADER TABLE STYLES - Clear/Transparent table styling - NOT white */
.table-clear,
.table-clear.table,
table.table-clear,
#campaignTable,
#campaignTable.table {
    background: transparent !important;
    backdrop-filter: blur(20px) !important;
    border-radius: 12px !important;
    overflow: hidden !important;
    border: 1px solid rgba(255, 255, 255, 0.15) !important;
    width: 100% !important;
    max-width: 100% !important;
    min-width: 800px !important;
    table-layout: fixed !important; /* Prevent table from growing */
    color: rgba(255, 255, 255, 0.95) !important;
    display: table !important;
    border-collapse: separate !important;
    border-spacing: 0 !important;
}

/* FORCE SPECIFIC COLUMN WIDTHS - Prevent expansion */
.table-clear th:nth-child(1),
.table-clear td:nth-child(1) { width: 200px !important; max-width: 200px !important; }
.table-clear th:nth-child(2),
.table-clear td:nth-child(2) { width: 80px !important; max-width: 80px !important; }
.table-clear th:nth-child(3),
.table-clear td:nth-child(3) { width: 70px !important; max-width: 70px !important; }
.table-clear th:nth-child(4),
.table-clear td:nth-child(4) { width: 100px !important; max-width: 100px !important; }
.table-clear th:nth-child(5),
.table-clear td:nth-child(5) { width: 80px !important; max-width: 80px !important; }
.table-clear th:nth-child(6),
.table-clear td:nth-child(6) { width: 120px !important; max-width: 120px !important; }
.table-clear th:nth-child(7),
.table-clear td:nth-child(7) { width: 80px !important; max-width: 80px !important; }
.table-clear th:nth-child(8),
.table-clear td:nth-child(8) { width: 100px !important; max-width: 100px !important; }
.table-clear th:nth-child(9),
.table-clear td:nth-child(9) { width: 120px !important; max-width: 120px !important; }

.table-clear thead th,
.table-clear.table thead th,
table.table-clear thead th,
#campaignTable thead th,
#campaignTable.table thead th {
    background: rgba(102, 126, 234, 0.8) !important;
    color: rgba(255, 255, 255, 1) !important;
    font-weight: 700 !important;
    border: none !important;
    padding: 0.8rem 0.6rem !important;
    font-size: 0.9rem !important;
    text-align: center !important;
    word-wrap: break-word !important;
    border-bottom: 2px solid rgba(255, 255, 255, 0.3) !important;
}

.table-clear tbody td,
.table-clear.table tbody td,
table.table-clear tbody td,
#campaignTable tbody td,
#campaignTable.table tbody td {
    background: transparent !important;
    color: rgba(255, 255, 255, 0.95) !important;
    border: 1px solid rgba(255, 255, 255, 0.15) !important;
    padding: 0.5rem 0.3rem !important;
    font-size: 0.75rem !important;
    vertical-align: middle !important;
    word-wrap: break-word !important;
    overflow-wrap: break-word !important;
    text-overflow: ellipsis !important;
    white-space: nowrap !important;
    overflow: hidden !important;
    line-height: 1.2 !important;
    height: auto !important;
    min-height: 40px !important;
    max-height: 60px !important;
}

.table-clear tbody tr:hover td,
.table-clear.table tbody tr:hover td,
table.table-clear tbody tr:hover td,
#campaignTable tbody tr:hover td,
#campaignTable.table tbody tr:hover td {
    background: rgba(102, 126, 234, 0.2) !important;
    color: rgba(255, 255, 255, 1) !important;
}

.table-clear tbody tr:nth-child(even) td,
.table-clear.table tbody tr:nth-child(even) td,
table.table-clear tbody tr:nth-child(even) td,
#campaignTable tbody tr:nth-child(even) td,
#campaignTable.table tbody tr:nth-child(even) td {
    background: rgba(255, 255, 255, 0.03) !important;
}

.table-clear tbody tr:nth-child(even):hover td,
.table-clear.table tbody tr:nth-child(even):hover td,
table.table-clear tbody tr:nth-child(even):hover td,
#campaignTable tbody tr:nth-child(even):hover td,
#campaignTable.table tbody tr:nth-child(even):hover td {
    background: rgba(102, 126, 234, 0.2) !important;
}

/* Responsive table container - STRICT SIZE CONTROL */
.table-responsive {
    max-width: 100% !important;
    width: 100% !important;
    overflow-x: auto !important;
    overflow-y: hidden !important;
    -webkit-overflow-scrolling: touch !important;
    display: block !important;
    white-space: nowrap !important;
    border-radius: 12px !important;
    background: transparent !important;
}

.table-responsive .table {
    margin-bottom: 0 !important;
    width: 100% !important;
    max-width: 100% !important;
}

/* Container size control */
.card {
    max-width: 100% !important;
    overflow: hidden !important;
}

.card-body {
    max-width: 100% !important;
    overflow: hidden !important;
    padding: 1rem !important;
}

/* Badge styling in clear tables */
.table-clear .badge {
    font-size: 0.7rem !important;
    padding: 0.3rem 0.5rem !important;
    border-radius: 8px !important;
}

/* Progress bars in clear tables */
.table-clear .progress {
    background: rgba(255, 255, 255, 0.2) !important;
    height: 16px !important;
    border-radius: 8px !important;
}

/* Button groups in clear tables */
.table-clear .btn-group-sm .btn {
    padding: 0.25rem 0.4rem !important;
    font-size: 0.75rem !important;
    border-radius: 4px !important;
    margin: 0 1px !important;
}

.chart-container {
    background: rgba(255, 255, 255, 0.12) !important;
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    margin-bottom: 1.5rem;
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.15);
    color: rgba(255, 255, 255, 0.9) !important;
}

.chart-container h6 {
    color: rgba(255, 255, 255, 0.95) !important;
}

/* Activity Summary Styling */
.activity-summary .activity-item {
    padding: 0.5rem 0;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.activity-summary .activity-item:last-child {
    border-bottom: none;
}

.activity-summary .activity-label {
    font-size: 0.9rem;
    color: rgba(255, 255, 255, 0.85);
}

.activity-summary .activity-value {
    font-size: 0.85rem;
    font-weight: 600;
}

/* Quick Stats Styling */
.quick-stats .stat-item {
    padding: 0.5rem 0;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.quick-stats .stat-item:last-child {
    border-bottom: none;
}

.quick-stats .stat-item strong {
    font-size: 1.1rem;
    color: rgba(255, 255, 255, 0.95);
}

.quick-stats .stat-item small {
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.7);
}

.progress-circle {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: conic-gradient(#28a745 var(--progress), #e9ecef var(--progress));
    margin: 0 auto;
}

.progress-inner {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 1.2rem;
}

/* Card styling for consistent clear theme */
.card {
    background: rgba(255, 255, 255, 0.1) !important;
    backdrop-filter: blur(20px) !important;
    border: 1px solid rgba(255, 255, 255, 0.2) !important;
    border-radius: 12px !important;
}

.card-header {
    background: rgba(255, 255, 255, 0.05) !important;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1) !important;
    color: rgba(255, 255, 255, 0.9) !important;
}

.card-body {
    color: rgba(255, 255, 255, 0.85) !important;
}

/* Ensure small text in tables is readable */
.table-clear small {
    color: rgba(255, 255, 255, 0.7) !important;
    font-size: 0.75rem !important;
}

/* Compact table layout */
.table-compact {
    font-size: 0.8rem !important;
}

.table-compact th,
.table-compact td {
    padding: 0.5rem 0.4rem !important;
}

/* Prevent horizontal scrolling issues */
.container-fluid {
    max-width: 100vw !important;
    width: 100% !important;
    overflow-x: hidden !important;
    overflow-y: auto !important;
    padding-left: 15px !important;
    padding-right: 15px !important;
}

/* Force all rows and columns to respect max width */
.row {
    max-width: 100% !important;
    margin-left: 0 !important;
    margin-right: 0 !important;
}

.col-12,
.col-lg-8,
.col-lg-4,
.col-lg-6,
.col-md-3,
.col-md-4,
.col-md-6 {
    max-width: 100% !important;
    overflow: hidden !important;
    word-wrap: break-word !important;
}

/* EMERGENCY TABLE SIZE LOCK */
table,
.table,
#campaignTable {
    table-layout: fixed !important;
    width: 100% !important;
    max-width: 100% !important;
    border-collapse: separate !important;
    border-spacing: 0 !important;
    word-wrap: break-word !important;
    overflow: hidden !important;
}

/* NUCLEAR OPTION - Override ANY remaining white backgrounds */
.table,
table,
.table-responsive .table,
#campaignTable,
.table-clear {
    background: transparent !important;
    background-color: transparent !important;
}

.table th,
.table td,
table th,
table td,
#campaignTable th,
#campaignTable td {
    background: transparent !important;
    background-color: transparent !important;
    color: rgba(255, 255, 255, 0.95) !important;
}

/* Override Bootstrap defaults completely */
.table tbody tr,
.table thead tr,
table tbody tr,
table thead tr {
    background: transparent !important;
    background-color: transparent !important;
}

/* Make sure the card bodies are also clear */
.card-body {
    background: transparent !important;
    color: rgba(255, 255, 255, 0.9) !important;
}
</style>

<div class="container-fluid py-4">
    <!-- Header Section -->
    <div class="analytics-dashboard">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h2 mb-2">📊 Advanced Campaign Analytics</h1>
                <p class="mb-0 opacity-90">Comprehensive insights for <?php echo htmlspecialchars($business['name']); ?></p>
            </div>
            <div class="text-end">
                <div class="performance-score"><?php echo $performance_score; ?>%</div>
                <small>Performance Score</small>
            </div>
        </div>

        <!-- Key Metrics Row -->
        <div class="row g-3">
            <div class="col-md-3">
                <div class="metric-card card h-100">
                    <div class="card-body text-center">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <i class="bi bi-bullseye fs-3 text-warning"></i>
                            <span class="badge bg-primary"><?php echo $campaign_stats['active']; ?> Active</span>
                        </div>
                        <h3 class="mb-0"><?php echo $total_campaigns; ?></h3>
                        <small>Total Campaigns</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="metric-card card h-100">
                    <div class="card-body text-center">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <i class="bi bi-qr-code fs-3 text-info"></i>
                            <span class="badge bg-success"><?php echo $total_campaigns > 0 ? round($total_qr_codes/$total_campaigns,1) : 0; ?> Avg</span>
                        </div>
                        <h3 class="mb-0"><?php echo $total_qr_codes; ?></h3>
                        <small>QR Codes Generated</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="metric-card card h-100">
                    <div class="card-body text-center">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <i class="bi bi-hand-thumbs-up fs-3 text-success"></i>
                            <span class="badge bg-warning text-dark"><?php echo $avg_votes_per_campaign; ?> Avg</span>  
                        </div>
                        <h3 class="mb-0"><?php echo number_format($total_votes); ?></h3>
                        <small>Total Votes</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="metric-card card h-100">
                    <div class="card-body text-center">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <i class="bi bi-people fs-3 text-primary"></i>
                            <span class="badge bg-info"><?php echo $avg_engagement_rate; ?>%</span>
                        </div>
                        <h3 class="mb-0"><?php echo number_format($total_unique_voters); ?></h3>
                        <small>Unique Voters</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- AI Insights Section -->
    <?php if (!empty($insights)): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-robot me-2"></i>AI-Powered Insights</h5>
                </div>
                <div class="card-body">
                    <?php foreach ($insights as $insight): ?>
                        <div class="insight-card <?php echo $insight['type']; ?> p-3 mb-3">
                            <div class="d-flex align-items-start">
                                <i class="<?php echo $insight['icon']; ?> fs-4 me-3 mt-1"></i>
                                <div>
                                    <h6 class="mb-1"><?php echo $insight['title']; ?></h6>
                                    <p class="mb-0 text-muted"><?php echo $insight['message']; ?></p>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Charts Section -->
    <div class="row mb-4">
        <div class="col-lg-4">
            <div class="chart-container">
                <h6 class="mb-3"><i class="bi bi-pie-chart me-2"></i>Campaign Status Distribution</h6>
                <canvas id="statusChart" width="300" height="300"></canvas>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="chart-container">
                <h6 class="mb-3"><i class="bi bi-activity me-2"></i>Recent Activity Summary</h6>
                <div class="activity-summary">
                    <div class="activity-item mb-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="activity-label">Total Votes (7 days)</span>
                            <span class="activity-value badge bg-primary">
                                <?php echo array_sum(array_column($campaigns, 'votes_last_7_days')); ?>
                            </span>
                        </div>
                    </div>
                    <div class="activity-item mb-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="activity-label">Active Campaigns</span>
                            <span class="activity-value badge bg-success">
                                <?php echo $campaign_stats['active']; ?>
                            </span>
                        </div>
                    </div>
                    <div class="activity-item mb-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="activity-label">Avg. Engagement</span>
                            <span class="activity-value badge bg-info">
                                <?php echo $avg_engagement_rate; ?>%
                            </span>
                        </div>
                    </div>
                    <div class="activity-item">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="activity-label">Performance Score</span>
                            <span class="activity-value badge bg-warning text-dark">
                                <?php echo $performance_score; ?>%
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="chart-container">
                <h6 class="mb-3"><i class="bi bi-clock me-2"></i>Quick Stats</h6>
                <div class="quick-stats">
                    <div class="stat-item mb-2">
                        <small class="text-muted d-block">Most Active Hour</small>
                        <strong>
                            <?php 
                            if (!empty($hourly_patterns)) {
                                $peak_hour = array_reduce($hourly_patterns, function($carry, $item) {
                                    return ($carry === null || $item['vote_count'] > $carry['vote_count']) ? $item : $carry;
                                });
                                echo $peak_hour ? $peak_hour['hour'] . ':00' : 'N/A';
                            } else {
                                echo 'N/A';
                            }
                            ?>
                        </strong>
                    </div>
                    <div class="stat-item mb-2">
                        <small class="text-muted d-block">Total QR Codes</small>
                        <strong><?php echo $total_qr_codes; ?></strong>
                    </div>
                    <div class="stat-item mb-2">
                        <small class="text-muted d-block">Unique Voters</small>
                        <strong><?php echo number_format($total_unique_voters); ?></strong>
                    </div>
                    <div class="stat-item">
                        <small class="text-muted d-block">Avg Votes/Campaign</small>
                        <strong><?php echo $avg_votes_per_campaign; ?></strong>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-lg-6">
            <div class="chart-container">
                <h6 class="mb-3"><i class="bi bi-clock me-2"></i>Hourly Voting Patterns</h6>
                <canvas id="hourlyChart"></canvas>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="chart-container">
                <h6 class="mb-3"><i class="bi bi-bar-chart me-2"></i>QR Code Performance by Type</h6>
                <canvas id="qrPerformanceChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Detailed Campaign Performance Table -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-table me-2"></i>Detailed Campaign Performance</h5>
                    <div>
                        <button class="btn btn-outline-primary btn-sm me-2" onclick="exportToPDF()">
                            <i class="bi bi-file-earmark-pdf me-1"></i>Export PDF
                        </button>
                        <button class="btn btn-outline-success btn-sm" onclick="exportToCSV()">
                            <i class="bi bi-file-earmark-spreadsheet me-1"></i>Export CSV
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover table-clear table-compact" id="campaignTable">
                            <thead>
                                <tr>
                                    <th>Campaign</th>
                                    <th>Status</th>
                                    <th>QR Codes</th>
                                    <th>Total Votes</th>
                                    <th>Unique Voters</th>
                                    <th>Engagement Rate</th>
                                    <th>Votes/Day</th>
                                    <th>Last Activity</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($campaigns as $campaign): ?>
                                    <tr>
                                        <td style="max-width: 200px;">
                                            <div>
                                                <strong class="d-block" style="font-size: 0.85rem; line-height: 1.2;">
                                                    <?php echo htmlspecialchars(strlen($campaign['name']) > 25 ? substr($campaign['name'], 0, 25) . '...' : $campaign['name']); ?>
                                                </strong>
                                                <?php if ($campaign['description']): ?>
                                                    <small class="text-muted d-block" style="font-size: 0.7rem; opacity: 0.7;">
                                                        <?php echo htmlspecialchars(substr($campaign['description'], 0, 30)) . '...'; ?>
                                                    </small>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $campaign['status'] === 'active' ? 'success' : ($campaign['status'] === 'completed' ? 'info' : 'danger'); ?>">
                                                <?php echo ucfirst($campaign['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="fw-bold"><?php echo $campaign['qr_count']; ?></span>
                                        </td>
                                        <td style="max-width: 100px; text-align: center;">
                                            <div>
                                                <span class="fw-bold d-block" style="font-size: 0.9rem;">
                                                    <?php echo $campaign['total_votes'] > 999 ? round($campaign['total_votes']/1000, 1) . 'k' : number_format($campaign['total_votes']); ?>
                                                </span>
                                                <?php if ($campaign['votes_last_7_days'] > 0): ?>
                                                    <small class="text-success d-block" style="font-size: 0.65rem;">
                                                        +<?php echo $campaign['votes_last_7_days']; ?> 7d
                                                    </small>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td style="max-width: 80px; text-align: center;">
                                            <span style="font-size: 0.9rem;">
                                                <?php echo $campaign['unique_voters'] > 999 ? round($campaign['unique_voters']/1000, 1) . 'k' : number_format($campaign['unique_voters']); ?>
                                            </span>
                                        </td>
                                        <td style="max-width: 120px;">
                                            <div class="progress" style="height: 16px; font-size: 0.7rem;">
                                                <div class="progress-bar bg-<?php echo $campaign['unique_engagement_rate'] > 50 ? 'success' : ($campaign['unique_engagement_rate'] > 25 ? 'warning' : 'danger'); ?>" 
                                                     style="width: <?php echo min($campaign['unique_engagement_rate'], 100); ?>%; font-size: 0.65rem;">
                                                    <?php echo $campaign['unique_engagement_rate']; ?>%
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-info"><?php echo $campaign['votes_per_day']; ?></span>
                                        </td>
                                        <td style="max-width: 100px; text-align: center;">
                                            <?php 
                                            if ($campaign['last_vote']) {
                                                $date = new DateTime($campaign['last_vote']);
                                                $now = new DateTime();
                                                $diff = $now->diff($date);
                                                
                                                if ($diff->days == 0) {
                                                    echo '<span class="text-success" style="font-size: 0.75rem;">Today<br>' . $date->format('H:i') . '</span>';
                                                } elseif ($diff->days == 1) {
                                                    echo '<span class="text-info" style="font-size: 0.75rem;">Yesterday<br>' . $date->format('H:i') . '</span>';
                                                } elseif ($diff->days < 7) {
                                                    echo '<span class="text-warning" style="font-size: 0.75rem;">' . $diff->days . 'd ago<br>' . $date->format('H:i') . '</span>';
                                                } else {
                                                    echo '<span class="text-muted" style="font-size: 0.75rem;">' . $date->format('M d') . '</span>';
                                                }
                                            } else {
                                                echo '<span class="text-muted" style="font-size: 0.7rem;">No activity</span>';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="campaign-stats.php?id=<?php echo $campaign['id']; ?>" 
                                                   class="btn btn-outline-primary" title="Detailed Stats">
                                                    <i class="bi bi-graph-up"></i>
                                                </a>
                                                <a href="edit-campaign.php?id=<?php echo $campaign['id']; ?>" 
                                                   class="btn btn-outline-warning" title="Edit Campaign">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <button class="btn btn-outline-info" onclick="viewQRCodes(<?php echo $campaign['id']; ?>)" title="View QR Codes">
                                                    <i class="bi bi-qr-code"></i>
                                                </button>
                                            </div>
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

    <!-- Quick Actions -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body text-center">
                    <h6 class="mb-3">Quick Actions</h6>
                    <div class="btn-group" role="group">
                        <a href="manage-campaigns.php" class="btn btn-primary">
                            <i class="bi bi-plus-circle me-2"></i>Create New Campaign
                        </a>
                        <button class="btn btn-success" onclick="refreshData()">
                            <i class="bi bi-arrow-clockwise me-2"></i>Refresh Data
                        </button>
                        <button class="btn btn-info" onclick="scheduleReport()">
                            <i class="bi bi-calendar-event me-2"></i>Schedule Report
                        </button>
                        <a href="view-votes.php" class="btn btn-warning">
                            <i class="bi bi-eye me-2"></i>View All Votes
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Scripts for Charts and Interactivity -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/date-fns@2.29.3/index.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Data preparation
    const dailyVotes = <?php echo json_encode($daily_votes); ?>;
    const hourlyPatterns = <?php echo json_encode($hourly_patterns); ?>;
    const campaignStats = <?php echo json_encode($campaign_stats); ?>;
    const qrPerformance = <?php echo json_encode($qr_performance); ?>;

    // Status Distribution Chart (Doughnut)
    const statusCtx = document.getElementById('statusChart').getContext('2d');
    new Chart(statusCtx, {
        type: 'doughnut',
        data: {
            labels: ['Active', 'Inactive', 'Completed'],
            datasets: [{
                data: [campaignStats.active, campaignStats.inactive, campaignStats.completed],
                backgroundColor: ['#28a745', '#dc3545', '#17a2b8'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            aspectRatio: 1,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        color: 'rgba(255, 255, 255, 0.9)',
                        font: {
                            size: 12
                        }
                    }
                }
            }
        }
    });

    // Hourly Patterns Chart (Bar)
    const hourlyCtx = document.getElementById('hourlyChart').getContext('2d');
    const hours = Array.from({length: 24}, (_, i) => i);
    const hourlyData = hours.map(hour => {
        const found = hourlyPatterns.find(h => parseInt(h.hour) === hour);
        return found ? found.vote_count : 0;
    });

    new Chart(hourlyCtx, {
        type: 'bar',
        data: {
            labels: hours.map(h => `${h}:00`),
            datasets: [{
                label: 'Votes by Hour',
                data: hourlyData,
                backgroundColor: 'rgba(102, 126, 234, 0.6)',
                borderColor: '#667eea',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });

    // QR Performance Chart (Horizontal Bar)
    const qrCtx = document.getElementById('qrPerformanceChart').getContext('2d');
    const qrLabels = qrPerformance.map(item => item.qr_type || 'Standard');
    const qrData = qrPerformance.map(item => item.total_scans);

    new Chart(qrCtx, {
        type: 'bar',
        data: {
            labels: qrLabels,
            datasets: [{
                label: 'Total Scans',
                data: qrData,
                backgroundColor: [
                    'rgba(255, 99, 132, 0.6)',
                    'rgba(54, 162, 235, 0.6)',
                    'rgba(255, 205, 86, 0.6)',
                    'rgba(75, 192, 192, 0.6)',
                    'rgba(153, 102, 255, 0.6)'
                ],
                borderColor: [
                    'rgba(255, 99, 132, 1)',
                    'rgba(54, 162, 235, 1)',
                    'rgba(255, 205, 86, 1)',
                    'rgba(75, 192, 192, 1)',
                    'rgba(153, 102, 255, 1)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            scales: {
                x: {
                    beginAtZero: true
                }
            }
        }
    });
});

// Interactive Functions
function refreshData() {
    location.reload();
}

function exportToPDF() {
    // Implementation for PDF export
    alert('PDF export functionality - to be implemented with a PDF library');
}

function exportToCSV() {
    // Implementation for CSV export
    const campaigns = <?php echo json_encode($campaigns); ?>;
    let csv = 'Campaign Name,Status,QR Codes,Total Votes,Unique Voters,Engagement Rate,Votes Per Day,Last Activity\n';
    
    campaigns.forEach(campaign => {
        csv += `"${campaign.name}","${campaign.status}",${campaign.qr_count},${campaign.total_votes},${campaign.unique_voters},${campaign.unique_engagement_rate}%,${campaign.votes_per_day},"${campaign.last_vote || 'Never'}"\n`;
    });
    
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'campaign-analytics.csv';
    a.click();
}

function viewQRCodes(campaignId) {
    window.open(`qr-codes.php?campaign_id=${campaignId}`, '_blank');
}

function scheduleReport() {
    alert('Scheduled reporting functionality - to be implemented');
}

// Real-time updates every 5 minutes
setInterval(function() {
    // Check for new data and update charts if needed
    fetch('api/campaign-stats.php')
        .then(response => response.json())
        .then(data => {
            // Update key metrics if there are changes
            console.log('Checking for data updates...');
        })
        .catch(error => console.log('Auto-refresh check failed:', error));
}, 300000); // 5 minutes
</script>

<?php require_once __DIR__ . '/../core/includes/footer.php'; ?> 