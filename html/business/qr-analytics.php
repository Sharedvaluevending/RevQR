<?php
/**
 * QR Analytics Dashboard
 * Comprehensive analytics for QR code performance, user engagement, and business insights
 */

require_once '../core/config.php';
require_once '../core/session.php';
require_once '../core/functions.php';

// Check if user is logged in and is a business
if (!is_logged_in() || !has_role('business')) {
    header('Location: ' . APP_URL . '/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Get business information
try {
    $stmt = $pdo->prepare("SELECT * FROM businesses WHERE id = (SELECT business_id FROM users WHERE id = ?)");
    $stmt->execute([$user_id]);
    $business = $stmt->fetch();
    
    if (!$business) {
        throw new Exception('Business not found');
    }
    
    $business_id = $business['id'];
} catch (Exception $e) {
    error_log("Error fetching business: " . $e->getMessage());
    header('Location: ' . APP_URL . '/business/dashboard.php');
    exit;
}

// Get QR Analytics Data
function getQRAnalytics($pdo, $business_id) {
    $analytics = [
        'total_qr_codes' => 0,
        'total_scans' => 0,
        'unique_users' => 0,
        'conversion_rate' => 0,
        'top_performing_qr' => [],
        'scan_trends' => [],
        'user_engagement' => [],
        'geographic_data' => [],
        'device_breakdown' => [],
        'time_analysis' => []
    ];
    
    try {
        // Total QR Codes Generated (from various sources)
        $qr_count = 0;
        
        // Check if qr_codes table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'qr_codes'");
        if ($stmt->rowCount() > 0) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM qr_codes WHERE business_id = ?");
            $stmt->execute([$business_id]);
            $qr_count += $stmt->fetchColumn() ?: 0;
        }
        
        // Count voting lists as QR codes
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM campaigns WHERE business_id = ?");
        $stmt->execute([$business_id]);
        $qr_count += $stmt->fetchColumn() ?: 0;
        
        // Count store items as potential QR codes (business-specific)
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM business_store_items WHERE business_id = ?");
        $stmt->execute([$business_id]);
        $qr_count += $stmt->fetchColumn() ?: 0;
        
        $analytics['total_qr_codes'] = $qr_count;
        
        // Total Scans from Business Activities
        $total_scans = 0;
        $unique_users = 0;
        
        // Business Store purchases (if exists)
        try {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as total_scans,
                       COUNT(DISTINCT user_id) as unique_users
                FROM user_store_purchases 
                WHERE business_id = ?
            ");
            $stmt->execute([$business_id]);
            $store_data = $stmt->fetch();
            $total_scans += $store_data['total_scans'] ?: 0;
            $unique_users = max($unique_users, $store_data['unique_users'] ?: 0);
        } catch (Exception $e) {
            // Table might not exist, continue
        }
        
        // Voting System Engagement
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as vote_scans,
                   COUNT(DISTINCT user_id) as voting_users
            FROM votes v
            JOIN campaigns c ON v.campaign_id = c.id
            WHERE c.business_id = ?
        ");
        $stmt->execute([$business_id]);
        $vote_data = $stmt->fetch();
        $total_scans += $vote_data['vote_scans'] ?: 0;
        $unique_users = max($unique_users, $vote_data['voting_users'] ?: 0);
        
        // Casino Engagement
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as casino_plays,
                   COUNT(DISTINCT user_id) as casino_users
            FROM casino_plays 
            WHERE business_id = ?
        ");
        $stmt->execute([$business_id]);
        $casino_data = $stmt->fetch();
        $total_scans += $casino_data['casino_plays'] ?: 0;
        $unique_users = max($unique_users, $casino_data['casino_users'] ?: 0);
        
        $analytics['total_scans'] = $total_scans;
        $analytics['unique_users'] = $unique_users;
        
        // Calculate conversion rate
        if ($analytics['total_scans'] > 0) {
            $analytics['conversion_rate'] = round(($analytics['unique_users'] / $analytics['total_scans']) * 100, 2);
        }
        
        // Top Performing Business Items (based on business store purchases)
        try {
            $stmt = $pdo->prepare("
                SELECT si.item_name, COUNT(*) as scan_count, SUM(usp.amount_paid) as revenue
                FROM user_store_purchases usp
                JOIN business_store_items si ON usp.item_id = si.id
                WHERE usp.business_id = ?
                GROUP BY si.id, si.item_name
                ORDER BY scan_count DESC
                LIMIT 5
            ");
            $stmt->execute([$business_id]);
            $analytics['top_performing_qr'] = $stmt->fetchAll();
        } catch (Exception $e) {
            // If business store doesn't exist, show voting campaigns as top performers
            $stmt = $pdo->prepare("
                SELECT c.name as item_name, COUNT(v.id) as scan_count, 0 as revenue
                FROM campaigns c
                LEFT JOIN votes v ON c.id = v.campaign_id
                WHERE c.business_id = ?
                GROUP BY c.id, c.name
                ORDER BY scan_count DESC
                LIMIT 5
            ");
            $stmt->execute([$business_id]);
            $analytics['top_performing_qr'] = $stmt->fetchAll();
        }
        
        // Scan Trends (last 30 days) - Business Activities
        $stmt = $pdo->prepare("
            SELECT DATE(created_at) as scan_date, COUNT(*) as daily_scans
            FROM (
                SELECT v.created_at FROM votes v 
                JOIN campaigns c ON v.campaign_id = c.id 
                WHERE c.business_id = ? AND v.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                UNION ALL
                SELECT played_at as created_at FROM casino_plays WHERE business_id = ? AND played_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ) as all_scans
            GROUP BY DATE(created_at)
            ORDER BY scan_date DESC
            LIMIT 30
        ");
        $stmt->execute([$business_id, $business_id]);
        $analytics['scan_trends'] = array_reverse($stmt->fetchAll());
        
        // User Engagement Patterns
        $stmt = $pdo->prepare("
            SELECT 
                HOUR(created_at) as hour_of_day,
                COUNT(*) as activity_count
            FROM (
                SELECT v.created_at FROM votes v 
                JOIN campaigns c ON v.campaign_id = c.id 
                WHERE c.business_id = ?
                UNION ALL
                SELECT played_at as created_at FROM casino_plays WHERE business_id = ?
            ) as all_activity
            GROUP BY HOUR(created_at)
            ORDER BY hour_of_day
        ");
        $stmt->execute([$business_id, $business_id]);
        $analytics['time_analysis'] = $stmt->fetchAll();
        
    } catch (Exception $e) {
        error_log("Error getting QR analytics: " . $e->getMessage());
    }
    
    return $analytics;
}

$qr_analytics = getQRAnalytics($pdo, $business_id);

// Page title
$page_title = "QR Analytics Dashboard";
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
    font-size: 2.5rem;
    font-weight: 700;
    color: #ffffff !important;
    line-height: 1;
}

.metric-label {
    color: rgba(255, 255, 255, 0.8) !important;
    font-size: 0.9rem;
    margin-top: 0.5rem;
}

.metric-change {
    font-size: 0.8rem;
    margin-top: 0.25rem;
}

.metric-change.positive {
    color: #28a745 !important;
}

.metric-change.negative {
    color: #dc3545 !important;
}

.chart-container {
    background: rgba(255, 255, 255, 0.08);
    border-radius: 8px;
    padding: 1rem;
    margin-top: 1rem;
}

.performance-item {
    background: rgba(255, 255, 255, 0.05);
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 0.5rem;
    border-left: 4px solid #007bff;
}

.performance-item:hover {
    background: rgba(255, 255, 255, 0.1);
}

.insight-card {
    background: linear-gradient(135deg, rgba(13, 110, 253, 0.12) 0%, rgba(102, 16, 242, 0.12) 100%);
    border: 1px solid rgba(13, 110, 253, 0.3);
    border-radius: 12px;
    padding: 1.5rem;
}

.insight-item {
    background: rgba(255, 255, 255, 0.08);
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 0.75rem;
    border-left: 4px solid #28a745;
}

.insight-item.warning {
    border-left-color: #ffc107;
}

.insight-item.danger {
    border-left-color: #dc3545;
}

@media (max-width: 768px) {
    .metric-value {
        font-size: 2rem;
    }
}
</style>

<div class="container-fluid py-4">
    <!-- Header Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="text-white mb-2">
                        <i class="bi bi-graph-up me-3"></i>QR Analytics Dashboard
                    </h1>
                    <p class="text-muted mb-0">Comprehensive insights into your QR code performance and user engagement</p>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-light btn-sm" onclick="exportAnalytics()">
                        <i class="bi bi-download me-1"></i>Export Data
                    </button>
                    <button class="btn btn-primary btn-sm" onclick="refreshAnalytics()">
                        <i class="bi bi-arrow-clockwise me-1"></i>Refresh
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Key Metrics Row -->
    <div class="row g-4 mb-4">
        <div class="col-12 col-md-3">
            <div class="analytics-card p-4 text-center">
                <div class="metric-value"><?php echo number_format($qr_analytics['total_qr_codes']); ?></div>
                <div class="metric-label">Total QR Codes</div>
                <div class="metric-change positive">
                    <i class="bi bi-arrow-up me-1"></i>Active & Generating
                </div>
            </div>
        </div>
        <div class="col-12 col-md-3">
            <div class="analytics-card p-4 text-center">
                <div class="metric-value"><?php echo number_format($qr_analytics['total_scans']); ?></div>
                <div class="metric-label">Total Interactions</div>
                <div class="metric-change positive">
                    <i class="bi bi-arrow-up me-1"></i>All QR Activities
                </div>
            </div>
        </div>
        <div class="col-12 col-md-3">
            <div class="analytics-card p-4 text-center">
                <div class="metric-value"><?php echo number_format($qr_analytics['unique_users']); ?></div>
                <div class="metric-label">Unique Users</div>
                <div class="metric-change positive">
                    <i class="bi bi-people me-1"></i>Engaged Customers
                </div>
            </div>
        </div>
        <div class="col-12 col-md-3">
            <div class="analytics-card p-4 text-center">
                <div class="metric-value"><?php echo $qr_analytics['conversion_rate']; ?>%</div>
                <div class="metric-label">Engagement Rate</div>
                <div class="metric-change <?php echo $qr_analytics['conversion_rate'] > 50 ? 'positive' : 'negative'; ?>">
                    <i class="bi bi-<?php echo $qr_analytics['conversion_rate'] > 50 ? 'arrow-up' : 'arrow-down'; ?> me-1"></i>
                    User to Interaction Ratio
                </div>
            </div>
        </div>
    </div>

    <!-- Charts and Performance Row -->
    <div class="row g-4 mb-4">
        <!-- Scan Trends Chart -->
        <div class="col-12 col-lg-8">
            <div class="analytics-card p-4">
                <h5 class="text-white mb-3">
                    <i class="bi bi-graph-up me-2"></i>QR Activity Trends (Last 30 Days)
                </h5>
                <div class="chart-container">
                    <canvas id="scanTrendsChart" height="300"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Top Performing QR Codes -->
        <div class="col-12 col-lg-4">
            <div class="analytics-card p-4">
                <h5 class="text-white mb-3">
                    <i class="bi bi-trophy me-2"></i>Top Performing Items
                </h5>
                <?php if (!empty($qr_analytics['top_performing_qr'])): ?>
                    <?php foreach ($qr_analytics['top_performing_qr'] as $item): ?>
                        <div class="performance-item">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="text-white fw-bold"><?php echo htmlspecialchars($item['item_name']); ?></div>
                                    <small class="text-muted"><?php echo number_format($item['scan_count']); ?> purchases</small>
                                </div>
                                <div class="text-end">
                                    <div class="text-warning fw-bold"><?php echo number_format($item['revenue']); ?> QR</div>
                                    <small class="text-muted">Revenue</small>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-graph-up fs-1 mb-2"></i>
                        <p>No QR store activity yet</p>
                        <small>Start promoting your QR store to see performance data</small>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- User Engagement Analysis -->
    <div class="row g-4 mb-4">
        <!-- Time-based Activity -->
        <div class="col-12 col-lg-6">
            <div class="analytics-card p-4">
                <h5 class="text-white mb-3">
                    <i class="bi bi-clock me-2"></i>Activity by Hour of Day
                </h5>
                <div class="chart-container">
                    <canvas id="hourlyActivityChart" height="250"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Engagement Insights -->
        <div class="col-12 col-lg-6">
            <div class="insight-card">
                <h5 class="text-white mb-3">
                    <i class="bi bi-lightbulb me-2"></i>AI-Powered Insights
                </h5>
                
                <?php
                // Generate insights based on data
                $insights = [];
                
                if ($qr_analytics['total_scans'] > 0) {
                    if ($qr_analytics['conversion_rate'] > 70) {
                        $insights[] = [
                            'type' => 'success',
                            'title' => 'Excellent Engagement',
                            'message' => 'Your QR codes have a ' . $qr_analytics['conversion_rate'] . '% engagement rate - well above average!'
                        ];
                    } elseif ($qr_analytics['conversion_rate'] < 30) {
                        $insights[] = [
                            'type' => 'warning',
                            'title' => 'Engagement Opportunity',
                            'message' => 'Consider adding more interactive elements to boost your ' . $qr_analytics['conversion_rate'] . '% engagement rate.'
                        ];
                    }
                }
                
                if ($qr_analytics['total_qr_codes'] > 0 && $qr_analytics['total_scans'] == 0) {
                    $insights[] = [
                        'type' => 'danger',
                        'title' => 'No Activity Detected',
                        'message' => 'Your QR codes haven\'t been scanned yet. Consider promoting them more actively.'
                    ];
                }
                
                if (!empty($qr_analytics['top_performing_qr'])) {
                    $top_item = $qr_analytics['top_performing_qr'][0];
                    $insights[] = [
                        'type' => 'success',
                        'title' => 'Top Performer',
                        'message' => '"' . $top_item['item_name'] . '" is your best performing item with ' . $top_item['scan_count'] . ' purchases.'
                    ];
                }
                
                if (empty($insights)) {
                    $insights[] = [
                        'type' => 'info',
                        'title' => 'Getting Started',
                        'message' => 'Create QR codes and start engaging with customers to see detailed analytics here.'
                    ];
                }
                ?>
                
                <?php foreach ($insights as $insight): ?>
                    <div class="insight-item <?php echo $insight['type'] === 'warning' ? 'warning' : ($insight['type'] === 'danger' ? 'danger' : ''); ?>">
                        <div class="fw-bold text-white mb-1"><?php echo $insight['title']; ?></div>
                        <div class="text-muted small"><?php echo $insight['message']; ?></div>
                    </div>
                <?php endforeach; ?>
                
                <div class="mt-3 pt-3 border-top border-secondary">
                    <div class="d-flex justify-content-between align-items-center">
                        <small class="text-muted">Want more detailed insights?</small>
                        <a href="ai-assistant.php" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-robot me-1"></i>AI Assistant
                        </a>
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
                    <i class="bi bi-lightning me-2"></i>Quick Actions
                </h5>
                <div class="row g-3">
                    <div class="col-12 col-md-3">
                        <a href="store.php" class="btn btn-outline-primary w-100">
                            <i class="bi bi-shop me-2"></i>Manage QR Store
                        </a>
                    </div>
                    <div class="col-12 col-md-3">
                        <a href="../qr-generator.php" class="btn btn-outline-success w-100">
                            <i class="bi bi-qr-code me-2"></i>Create QR Code
                        </a>
                    </div>
                    <div class="col-12 col-md-3">
                        <a href="promotional-ads.php" class="btn btn-outline-warning w-100">
                            <i class="bi bi-megaphone me-2"></i>Promote QR Codes
                        </a>
                    </div>
                    <div class="col-12 col-md-3">
                        <a href="reports.php" class="btn btn-outline-info w-100">
                            <i class="bi bi-file-earmark-text me-2"></i>Detailed Reports
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js for Analytics Charts -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Chart.js Configuration
Chart.defaults.color = 'rgba(255, 255, 255, 0.8)';
Chart.defaults.borderColor = 'rgba(255, 255, 255, 0.1)';

// Scan Trends Chart
const scanTrendsData = <?php echo json_encode($qr_analytics['scan_trends']); ?>;
const scanTrendsCtx = document.getElementById('scanTrendsChart').getContext('2d');
new Chart(scanTrendsCtx, {
    type: 'line',
    data: {
        labels: scanTrendsData.map(item => {
            const date = new Date(item.scan_date);
            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
        }),
        datasets: [{
            label: 'QR Interactions',
            data: scanTrendsData.map(item => item.daily_scans),
            borderColor: '#007bff',
            backgroundColor: 'rgba(0, 123, 255, 0.1)',
            borderWidth: 2,
            fill: true,
            tension: 0.4
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
                grid: {
                    color: 'rgba(255, 255, 255, 0.1)'
                }
            },
            x: {
                grid: {
                    color: 'rgba(255, 255, 255, 0.1)'
                }
            }
        }
    }
});

// Hourly Activity Chart
const hourlyData = <?php echo json_encode($qr_analytics['time_analysis']); ?>;
const hourlyCtx = document.getElementById('hourlyActivityChart').getContext('2d');
new Chart(hourlyCtx, {
    type: 'bar',
    data: {
        labels: Array.from({length: 24}, (_, i) => i + ':00'),
        datasets: [{
            label: 'Activity Count',
            data: Array.from({length: 24}, (_, hour) => {
                const found = hourlyData.find(item => parseInt(item.hour_of_day) === hour);
                return found ? found.activity_count : 0;
            }),
            backgroundColor: 'rgba(40, 167, 69, 0.6)',
            borderColor: '#28a745',
            borderWidth: 1
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
                grid: {
                    color: 'rgba(255, 255, 255, 0.1)'
                }
            },
            x: {
                grid: {
                    color: 'rgba(255, 255, 255, 0.1)'
                }
            }
        }
    }
});

// Utility Functions
function refreshAnalytics() {
    location.reload();
}

function exportAnalytics() {
    // Create CSV data
    const csvData = [
        ['Metric', 'Value'],
        ['Total QR Codes', '<?php echo $qr_analytics['total_qr_codes']; ?>'],
        ['Total Interactions', '<?php echo $qr_analytics['total_scans']; ?>'],
        ['Unique Users', '<?php echo $qr_analytics['unique_users']; ?>'],
        ['Engagement Rate', '<?php echo $qr_analytics['conversion_rate']; ?>%']
    ];
    
    const csvContent = csvData.map(row => row.join(',')).join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'qr-analytics-<?php echo date('Y-m-d'); ?>.csv';
    a.click();
    window.URL.revokeObjectURL(url);
}

// Auto-refresh every 5 minutes
setInterval(function() {
    const refreshBtn = document.querySelector('button[onclick="refreshAnalytics()"]');
    if (refreshBtn) {
        refreshBtn.innerHTML = '<i class="bi bi-arrow-clockwise spin me-1"></i>Auto Refreshing...';
        setTimeout(() => {
            location.reload();
        }, 1000);
    }
}, 300000); // 5 minutes
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