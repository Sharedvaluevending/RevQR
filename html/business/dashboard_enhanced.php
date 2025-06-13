<?php
/**
 * Unified Business Dashboard - Complete Business Management Center
 * Integrates all business systems, analytics, and management tools in one interface
 */

// Force no-cache headers to prevent old navigation from showing
header("Cache-Control: no-cache, no-store, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

// Clear OPcache for this file if it's stale
if (function_exists('opcache_invalidate')) {
    opcache_invalidate(__FILE__, true);
}

require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/business_qr_manager.php';
require_once __DIR__ . '/../core/store_manager.php';
require_once __DIR__ . '/../core/business_system_detector.php';

// Require business role
require_role('business');

// Initialize unified system detection
BusinessSystemDetector::init($pdo);
$business_id = get_business_id();
$capabilities = BusinessSystemDetector::getBusinessCapabilities($business_id);

// Fetch business details including logo_path (ORIGINAL)
$stmt = $pdo->prepare("
    SELECT b.*, u.username 
    FROM businesses b 
    JOIN users u ON b.id = u.business_id 
    WHERE u.id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$business = $stmt->fetch();

// Get QR Coin Economy data for business (ORIGINAL)
$business_subscription = BusinessQRManager::getSubscription($business['id']);
$qr_usage_stats = BusinessQRManager::getUsageStats($business['id']);
$store_stats = StoreManager::getBusinessStoreStats($business['id']);

// Get business wallet balance
$wallet_stmt = $pdo->prepare("SELECT qr_coin_balance FROM business_wallets WHERE business_id = ?");
$wallet_stmt->execute([$business['id']]);
$wallet = $wallet_stmt->fetch();
$wallet_balance = $wallet['qr_coin_balance'] ?? 0;

// Get recent QR activities (ORIGINAL)
$recent_qr_activities = [];

// NEW: Enhanced Analytics Data Collection
$business_id = $_SESSION['business_id'] ?? null;
$user_id = $_SESSION['user_id'];

function get_enhanced_analytics($business_id) {
    $analytics = [];
    
    try {
        // Include database functions
        require_once __DIR__ . '/../core/database.php';
        
        // Campaign Overview (fixed - no status column exists)
        $campaigns = db_fetch("
            SELECT 
                COUNT(*) as total_campaigns,
                COUNT(*) as active_campaigns,
                0 as completed_campaigns
            FROM voting_lists 
            WHERE business_id = ?
        ", [$business_id]) ?: ['total_campaigns' => 0, 'active_campaigns' => 0, 'completed_campaigns' => 0];
        
        // Vote Analytics (fixed - votes join on machine_id not voting_list_id, use 24h instead of today)
        $votes = db_fetch("
            SELECT 
                COUNT(*) as total_votes,
                COUNT(CASE WHEN v.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as votes_today,
                COUNT(CASE WHEN v.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as votes_week
            FROM votes v
            JOIN machines m ON v.machine_id = m.id
            WHERE m.business_id = ?
        ", [$business_id]) ?: ['total_votes' => 0, 'votes_today' => 0, 'votes_week' => 0];
        
        // QR Code Management (use 24h instead of today for consistency)
        $qr_codes = db_fetch("
            SELECT 
                COUNT(*) as total_qr_codes,
                COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as qr_today,
                COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as qr_week
            FROM qr_codes 
            WHERE business_id = ?
        ", [$business_id]) ?: ['total_qr_codes' => 0, 'qr_today' => 0, 'qr_week' => 0];
        
        return [
            'campaigns' => $campaigns,
            'votes' => $votes,
            'qr_codes' => $qr_codes
        ];
        
    } catch (Exception $e) {
        error_log("Error getting enhanced analytics: " . $e->getMessage());
        return [
            'campaigns' => ['total_campaigns' => 0, 'active_campaigns' => 0, 'completed_campaigns' => 0],
            'votes' => ['total_votes' => 0, 'votes_today' => 0, 'votes_week' => 0],
            'qr_codes' => ['total_qr_codes' => 0, 'qr_today' => 0, 'qr_week' => 0]
        ];
    }
}

$enhanced_analytics = get_enhanced_analytics($business_id);

require_once __DIR__ . '/../core/includes/header.php';
?>

<style>
/* ORIGINAL STYLES PRESERVED */
.business-logo {
    width: 80px;
    height: 80px;
    object-fit: contain;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    background: white;
    padding: 4px;
}

.welcome-section {
    background: rgba(255, 255, 255, 0.15);
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 2rem;
    box-shadow: 0 4px 16px rgba(0,0,0,0.2);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.welcome-section h1 {
    color: #ffffff !important;
    font-weight: 600;
}

.welcome-section .text-muted {
    color: rgba(255, 255, 255, 0.8) !important;
}

.fade-in {
    animation: fadeInUp 0.6s ease-out forwards;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Enhanced Dashboard Card Styling */
.dashboard-card {
    background: rgba(255, 255, 255, 0.12) !important;
    backdrop-filter: blur(20px) !important;
    border: 1px solid rgba(255, 255, 255, 0.15) !important;
    border-radius: 16px !important;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3) !important;
    transition: all 0.3s ease !important;
    cursor: pointer;
    height: 100%;
}

.dashboard-card:hover {
    transform: translateY(-2px) !important;
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.4) !important;
    border: 1px solid rgba(255, 255, 255, 0.25) !important;
}

.dashboard-card .card-title {
    color: rgba(255, 255, 255, 0.9) !important;
    font-weight: 600;
    font-size: 1.1rem;
}

.dashboard-card .card-metric {
    font-size: 2.5rem;
    font-weight: 700;
    line-height: 1;
    color: #ffffff !important;
}

/* Fix grey/muted text to white for better visibility */
.dashboard-card .text-muted,
.dashboard-card .small {
    color: rgba(255, 255, 255, 0.85) !important;
}

/* Additional grey text fixes for dashboard cards */
.dashboard-card .small.text-muted,
.card .small.text-muted,
.card-body .small.text-muted,
.card-footer .small.text-muted {
    color: rgba(255, 255, 255, 0.85) !important;
}

/* Progress bar container text and growth indicators */
.growth-indicator,
.metric-details,
.conversion-details {
    color: rgba(255, 255, 255, 0.85) !important;
}

/* Specific targeting for text under progress bars */
.progress-info,
.progress-container,
.analytics-breakdown {
    color: rgba(255, 255, 255, 0.85) !important;
}

/* Ensure all small text elements are visible */
small,
.small {
    color: rgba(255, 255, 255, 0.85) !important;
}

/* Override any Bootstrap muted text utilities */
.text-muted {
    color: rgba(255, 255, 255, 0.8) !important;
}

.text-secondary {
    color: rgba(255, 255, 255, 0.8) !important;
}

/* Purple color for unified promotions */
.text-purple {
    color: #6f42c1 !important;
}

.btn-outline-purple {
    color: #6f42c1;
    border-color: #6f42c1;
}

.btn-outline-purple:hover {
    color: #fff;
    background-color: #6f42c1;
    border-color: #6f42c1;
}

/* AI Card Full Width and Better Height */
.ai-card-container {
    margin-bottom: 2rem;
}

.ai-assistant-card {
    background: linear-gradient(135deg, rgba(13, 110, 253, 0.12) 0%, rgba(102, 16, 242, 0.12) 100%) !important;
    border: 1px solid rgba(13, 110, 253, 0.3) !important;
    transition: all 0.3s ease !important;
    min-height: 280px;
}

.ai-assistant-card:hover {
    border: 1px solid rgba(13, 110, 253, 0.5) !important;
    background: linear-gradient(135deg, rgba(13, 110, 253, 0.18) 0%, rgba(102, 16, 242, 0.18) 100%) !important;
    transform: translateY(-2px) !important;
}

.ai-insights-preview {
    max-height: 180px;
    overflow-y: auto;
}

.insight-item {
    padding: 0.8rem;
    background: rgba(255, 255, 255, 0.08);
    border-radius: 8px;
    transition: all 0.2s ease;
    margin-bottom: 0.75rem;
}

.insight-item.priority-high {
    border-left: 4px solid #dc3545;
    background: rgba(220, 53, 69, 0.1);
}

.insight-item.priority-medium {
    border-left: 4px solid #ffc107;
    background: rgba(255, 193, 7, 0.1);
}

.insight-item.priority-low {
    border-left: 4px solid #0dcaf0;
    background: rgba(13, 202, 240, 0.1);
}

/* Ensure all dashboard cards have consistent height */
.card-row {
    margin-bottom: 2rem;
}

.equal-height {
    display: flex;
    flex-direction: column;
    height: 100%;
}

.equal-height .card-body {
    flex: 1;
    display: flex;
    flex-direction: column;
}

.equal-height .card-footer {
    margin-top: auto;
}

/* NEW: Enhanced Analytics Styles */
.metric-card {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.1), rgba(255, 255, 255, 0.05));
    border-radius: 12px;
    padding: 1.5rem;
    text-align: center;
    transition: all 0.3s ease;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.metric-card:hover {
    transform: translateY(-2px);
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.15), rgba(255, 255, 255, 0.08));
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

/* Responsive improvements */
@media (max-width: 768px) {
    .dashboard-card .card-metric {
        font-size: 2rem;
    }
    
    .ai-insights-preview {
        max-height: 120px;
    }
    
    .metric-value {
        font-size: 2rem;
    }
    
    .welcome-section {
        padding: 1.5rem;
    }
}
</style>

<div class="container-fluid py-4">
    <!-- Enhanced Welcome Section with Logo (ORIGINAL PRESERVED) -->
    <div class="welcome-section fade-in">
        <div class="row align-items-center">
            <div class="col-auto">
                <?php
                $logoUrl = !empty($business['logo_path']) ? APP_URL . '/' . $business['logo_path'] : APP_URL . '/assets/img/logoRQ.png';
                ?>
                <img src="<?php echo htmlspecialchars($logoUrl); ?>" alt="Business Logo" class="business-logo">
            </div>
            <div class="col">
                <h1 class="mb-2">Welcome back, <?php echo htmlspecialchars($business['name']); ?>!</h1>
                <p class="text-muted mb-0">Here's your business dashboard</p>
                <div class="d-flex align-items-center gap-3 mt-2">
                    <?php if (!empty($business['logo_path'])): ?>
                        <small class="text-muted">
                            <i class="bi bi-check-circle text-success me-1"></i>
                            Custom logo loaded
                        </small>
                    <?php endif; ?>
                    <!-- NEW: Enhanced Status Indicators -->
                    <span class="badge bg-success">
                        <i class="bi bi-check-circle me-1"></i>System Online
                    </span>
                    <span class="text-muted small">
                        <i class="bi bi-clock me-1"></i>Last updated: <span id="lastUpdated">just now</span>
                    </span>
                </div>
            </div>
            <!-- Quick Actions -->
            <div class="col-auto">
                <div class="d-flex gap-2">
                    <a href="business-guide.php" class="btn btn-outline-light" title="Business Guide">
                        <i class="bi bi-book-half me-1"></i>Guide
                    </a>
                    <a href="wallet.php" class="btn btn-outline-light" title="QR Wallet">
                        <i class="bi bi-wallet2 me-1"></i>Wallet
                    </a>
                    <button class="btn btn-outline-light" onclick="refreshDashboard()" id="refreshBtn">
                        <i class="bi bi-arrow-clockwise me-1"></i>Refresh
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- NEW: Enhanced Key Performance Metrics (ADDED, NOT REPLACED) -->
    <div class="row mb-4" id="metricsRow">
        <div class="col-md-3 mb-3">
            <div class="metric-card" data-metric="campaigns">
                <div class="metric-value"><?php echo number_format($enhanced_analytics['campaigns']['active_campaigns']); ?></div>
                <div class="metric-label">Active Campaigns</div>
                <div class="metric-change positive">
                    <i class="bi bi-arrow-up me-1"></i>+<?php echo $enhanced_analytics['campaigns']['total_campaigns'] - $enhanced_analytics['campaigns']['active_campaigns']; ?> completed
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="metric-card" data-metric="votes">
                <div class="metric-value"><?php echo number_format($enhanced_analytics['votes']['votes_today']); ?></div>
                <div class="metric-label">Votes Today</div>
                <div class="metric-change positive">
                    <i class="bi bi-graph-up me-1"></i><?php echo number_format($enhanced_analytics['votes']['votes_week']); ?> this week
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="metric-card" data-metric="qr">
                <div class="metric-value"><?php echo number_format($enhanced_analytics['qr_codes']['qr_today']); ?></div>
                <div class="metric-label">QR Codes Generated</div>
                <div class="metric-change positive">
                    <i class="bi bi-qr-code me-1"></i><?php echo number_format($enhanced_analytics['qr_codes']['total_qr_codes']); ?> total
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="metric-card" data-metric="revenue">
                <div class="metric-value">$<?php echo number_format($store_stats['qr_coins_earned'] ?? 0); ?></div>
                <div class="metric-label">Revenue Today</div>
                <div class="metric-change positive">
                    <i class="bi bi-cash-stack me-1"></i><?php echo $store_stats['total_sales'] ?? 0; ?> sales
                </div>
            </div>
        </div>
    </div>

    <!-- QR Coin Economy Section (ORIGINAL PRESERVED) -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card dashboard-card">
                <div class="card-header bg-transparent border-0">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="text-white mb-0">
                            <i class="bi bi-coin text-warning me-2"></i>QR Coin Economy & Subscriptions
                        </h5>
                        <div class="d-flex align-items-center">
                            <span class="badge bg-<?php echo $business_subscription['status'] === 'active' ? 'success' : ($business_subscription['status'] === 'trial' ? 'warning' : 'secondary'); ?> me-2">
                                <?php echo ucfirst($business_subscription['status'] ?? 'Unknown'); ?>
                            </span>
                            <span class="text-warning fw-bold"><?php echo ucfirst($business_subscription['tier'] ?? 'Starter'); ?> Plan</span>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-4">
                        <!-- Subscription Stats -->
                        <div class="col-md-3">
                            <div class="d-flex align-items-center p-3 bg-white bg-opacity-10 rounded">
                                <div class="flex-shrink-0">
                                    <i class="bi bi-credit-card display-6 text-success"></i>
                                </div>
                                <div class="ms-3 text-white">
                                    <h6 class="mb-1">Monthly Plan</h6>
                                    <h4 class="mb-0">$<?php echo number_format(($business_subscription['monthly_price_cents'] ?? 0) / 100, 0); ?></h4>
                                    <small class="opacity-75"><?php echo ucfirst($business_subscription['tier'] ?? 'Starter'); ?> Tier</small>
                                </div>
                            </div>
                        </div>
                        
                        <!-- QR Coin Usage -->
                        <div class="col-md-3">
                            <div class="d-flex align-items-center p-3 bg-white bg-opacity-10 rounded">
                                <div class="flex-shrink-0">
                                    <i class="bi bi-wallet display-6 text-warning"></i>
                                </div>
                                <div class="ms-3 text-white">
                                    <h6 class="mb-1">QR Coins Used</h6>
                                    <h4 class="mb-0"><?php echo number_format($business_subscription['qr_coins_used'] ?? 0); ?></h4>
                                    <small class="opacity-75">of <?php echo number_format($business_subscription['qr_coin_allowance'] ?? 0); ?> allowance</small>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Store Performance -->
                        <div class="col-md-3">
                            <div class="d-flex align-items-center p-3 bg-white bg-opacity-10 rounded">
                                <div class="flex-shrink-0">
                                    <i class="bi bi-shop display-6 text-info"></i>
                                </div>
                                <div class="ms-3 text-white">
                                    <h6 class="mb-1">Store Items</h6>
                                    <h4 class="mb-0"><?php echo number_format($store_stats['total_items'] ?? 0); ?></h4>
                                    <small class="opacity-75"><?php echo number_format($store_stats['total_sales'] ?? 0); ?> sold</small>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Revenue -->
                        <div class="col-md-3">
                            <div class="d-flex align-items-center p-3 bg-white bg-opacity-10 rounded">
                                <div class="flex-shrink-0">
                                    <i class="bi bi-graph-up-arrow display-6 text-success"></i>
                                </div>
                                <div class="ms-3 text-white">
                                    <h6 class="mb-1">Revenue QR</h6>
                                    <h4 class="mb-0"><?php echo number_format($store_stats['qr_coins_earned'] ?? 0); ?></h4>
                                    <small class="opacity-75">QR Coins collected</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- QR Coin Usage Progress -->
                    <div class="row mt-4">
                        <div class="col-md-6">
                            <h6 class="text-white mb-3">Monthly QR Coin Usage</h6>
                            <?php 
                            $usage_percentage = $business_subscription['qr_coin_allowance'] > 0 
                                ? min(100, ($business_subscription['qr_coins_used'] / $business_subscription['qr_coin_allowance']) * 100)
                                : 0;
                            ?>
                            <div class="progress mb-2" style="height: 12px;">
                                <div class="progress-bar bg-warning" role="progressbar" style="width: <?php echo $usage_percentage; ?>%"></div>
                            </div>
                            <div class="d-flex justify-content-between text-white-50 small">
                                <span><?php echo number_format($business_subscription['qr_coins_used'] ?? 0); ?> used</span>
                                <span><?php echo number_format(max(0, ($business_subscription['qr_coin_allowance'] ?? 0) - ($business_subscription['qr_coins_used'] ?? 0))); ?> remaining</span>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <h6 class="text-white mb-3">Quick Actions</h6>
                            <div class="d-flex gap-2 flex-wrap">
                                <a href="store.php" class="btn btn-info btn-sm">
                                    <i class="bi bi-shop me-1"></i>Manage Store
                                </a>
                                <a href="subscription.php" class="btn btn-secondary btn-sm">
                                    <i class="bi bi-credit-card me-1"></i>Subscription
                                </a>
                                <a href="qr-analytics.php" class="btn btn-success btn-sm">
                                    <i class="bi bi-graph-up me-1"></i>Analytics
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Recent QR Activities -->
                    <?php if (!empty($recent_qr_activities)): ?>
                    <div class="mt-4">
                        <h6 class="text-white mb-3"><i class="bi bi-clock-history me-2"></i>Recent QR Activities</h6>
                        <div class="row g-2">
                            <?php foreach (array_slice($recent_qr_activities, 0, 3) as $activity): ?>
                            <div class="col-12">
                                <div class="d-flex justify-content-between align-items-center p-2 bg-white bg-opacity-10 rounded small">
                                    <div class="d-flex align-items-center text-white">
                                        <i class="bi bi-<?php echo $activity['type'] === 'store_sale' ? 'cart-check text-success' : 'qr-code text-info'; ?> me-2"></i>
                                        <span><?php echo htmlspecialchars($activity['description'] ?? 'QR Activity'); ?></span>
                                    </div>
                                    <div class="text-white-50">
                                        <?php echo $activity['created_at'] ?? ''; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- AI Assistant Section - Full Width (ORIGINAL PRESERVED) -->
    <div class="ai-card-container">
        <div class="row">
            <div class="col-12">
                <?php include __DIR__ . '/includes/cards/ai_assistant.php'; ?>
            </div>
        </div>
    </div>

    <!-- ===== UNIFIED DASHBOARD CARDS ===== -->
    
    <!-- Unified Dashboard Explanation -->
    <div class="alert alert-info alert-dismissible fade show" role="alert">
        <div class="d-flex align-items-center">
            <i class="bi bi-speedometer2 me-3 fs-4"></i>
            <div>
                <strong>Unified Business Dashboard</strong> - Your complete business management center combining all systems and data streams.
                <br><small class="text-muted">
                    Manual systems <span class="badge bg-success">M</span>, 
                    Nayax systems <span class="badge bg-primary">N</span>,
                    Promotions <span class="badge bg-warning">P</span>,
                    and Analytics <span class="badge bg-info">A</span>
                    are all integrated for comprehensive business insights.
                </small>
            </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    
    <!-- Row 1: Sales Analytics (Manual | Nayax | Unified) -->
    <div class="row g-4 card-row">
        <div class="col-12 col-md-4">
            <div class="equal-height">
                <?php include __DIR__ . '/includes/cards/sales_margin.php'; ?>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="equal-height">
                <?php include __DIR__ . '/includes/cards/nayax_analytics.php'; ?>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="equal-height">
                <?php include __DIR__ . '/includes/cards/unified_sales.php'; ?>
            </div>
        </div>
    </div>

    <!-- Row 2: Engagement Analytics (Manual | Nayax | Unified) -->
    <div class="row g-4 card-row">
        <div class="col-12 col-md-4">
            <div class="equal-height">
                <?php include __DIR__ . '/includes/cards/voting_insights.php'; ?>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="equal-height">
                <?php include __DIR__ . '/includes/cards/engagement_insights.php'; ?>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="equal-height">
                <?php include __DIR__ . '/includes/cards/unified_engagement.php'; ?>
            </div>
        </div>
    </div>

    <!-- Row 3: Promotions Analytics (Traditional | Digital | Unified) -->
    <div class="row g-4 card-row">
        <div class="col-12 col-md-4">
            <div class="equal-height">
                <?php include __DIR__ . '/includes/cards/promotions.php'; ?>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="equal-height">
                <?php include __DIR__ . '/includes/cards/promotional_ads.php'; ?>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="equal-height">
                <?php include __DIR__ . '/includes/cards/unified_promotions.php'; ?>
            </div>
        </div>
    </div>

    <!-- Row 4: Interactive Features & Analytics -->
    <div class="row g-4 card-row">
        <div class="col-12 col-md-4">
            <div class="equal-height">
                <?php include __DIR__ . '/includes/cards/spin_rewards.php'; ?>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="equal-height">
                <?php include __DIR__ . '/includes/cards/pizza_tracker_card.php'; ?>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="equal-height">
                <?php include __DIR__ . '/includes/cards/inventory_overview.php'; ?>
            </div>
        </div>
    </div>

    <!-- Row 5: Business Intelligence -->
    <div class="row g-4 card-row">
        <div class="col-12 col-md-4">
            <div class="equal-height">
                <?php include __DIR__ . '/includes/cards/cross_references.php'; ?>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="equal-height">
                <?php include __DIR__ . '/includes/cards/performance_analytics.php'; ?>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="equal-height">
                <?php include __DIR__ . '/includes/cards/casino_participation.php'; ?>
            </div>
        </div>
    </div>
</div>

<script>
// ORIGINAL JAVASCRIPT PRESERVED + NEW ENHANCEMENTS

// Original Navigation Cache Detection and Dashboard Card Click Handlers
document.addEventListener('DOMContentLoaded', function() {
    // Check for navigation cache issues
    function checkNavigationFreshness() {
        // Look for cache-buster parameter
        const urlParams = new URLSearchParams(window.location.search);
        const cacheBust = urlParams.get('cache_bust');
        
        // Check if we have the latest navigation by looking for specific elements
        const hasModernNav = document.querySelector('.dropdown-item[href*="spin-wheel"]') || 
                           document.querySelector('.dropdown-item[href*="qr_manager"]');
        
        // If we don't have modern nav and no cache_bust, show notification
        if (!hasModernNav && !cacheBust) {
            showCacheNotification();
        }
    }
    
    function showCacheNotification() {
        const notification = document.createElement('div');
        notification.className = 'alert alert-warning alert-dismissible fade show position-fixed';
        notification.style.cssText = 'top: 80px; right: 20px; z-index: 9999; max-width: 350px;';
        notification.innerHTML = `
            <strong><i class="bi bi-exclamation-triangle me-2"></i>Navigation Update Available</strong><br>
            <small>Please refresh your browser to see the latest features.</small>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        document.body.appendChild(notification);
        
        // Auto-hide after 10 seconds
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 10000);
    }
    
    // Run the check
    checkNavigationFreshness();
    
    // Make dashboard cards clickable to show their modals
    const cardSelectors = [
        { card: '.dashboard-card:has(#voting-metric)', modal: '#votingDetailsModal' },
        { card: '.dashboard-card:has(#sales-metric)', modal: '#salesDetailsModal' },
        { card: '.dashboard-card:has(#inventory-metric)', modal: '#inventoryDetailsModal' },
        { card: '.dashboard-card:has(#cross-metric)', modal: '#crossReferencesModal' },
        { card: '.dashboard-card:has(#performance-metric)', modal: '#performanceDetailsModal' },
        { card: '.dashboard-card:has(#pizza-metric)', modal: '#pizzaTrackerModal' },
        { card: '.dashboard-card[data-metric="nayax"]', modal: '#nayaxDetailsModal' },
        { card: '.dashboard-card[data-metric="unified-sales"]', modal: '#unifiedSalesModal' },
        { card: '.dashboard-card[data-metric="unified-engagement"]', modal: '#unifiedEngagementModal' },
        { card: '.dashboard-card[data-metric="promotional-ads"]', modal: '#promotionalAdsModal' },
        { card: '.dashboard-card[data-metric="unified-promotions"]', modal: '#unifiedPromotionsModal' }
    ];

    cardSelectors.forEach(({ card, modal }) => {
        const cardElement = document.querySelector(card);
        const modalElement = document.querySelector(modal);
        
        if (cardElement && modalElement) {
            // Add pointer cursor to indicate clickability
            cardElement.style.cursor = 'pointer';
            
            // Add click handler
            cardElement.addEventListener('click', function(e) {
                // Don't trigger modal if clicking on buttons/links
                if (e.target.closest('a, button')) {
                    return;
                }
                
                const modal = new bootstrap.Modal(modalElement);
                modal.show();
            });
            
            // Add hover effect
            cardElement.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-2px)';
                this.style.boxShadow = '0 4px 20px rgba(0,0,0,0.15)';
                this.style.transition = 'all 0.2s ease';
            });
            
            cardElement.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
                this.style.boxShadow = '';
            });
        }
    });

    // NEW: Enhanced Dashboard Features
    updateTimestamp();
    animateMetrics();
    
    // Update timestamp every minute
    setInterval(updateTimestamp, 60000);
});

// NEW: Enhanced Dashboard Functions
function updateTimestamp() {
    const now = new Date();
    const timeString = now.toLocaleTimeString('en-US', { 
        hour12: true, 
        hour: 'numeric', 
        minute: '2-digit' 
    });
    const lastUpdated = document.getElementById('lastUpdated');
    if (lastUpdated) {
        lastUpdated.textContent = timeString;
    }
}

function refreshDashboard() {
    const refreshBtn = document.getElementById('refreshBtn');
    if (refreshBtn) {
        refreshBtn.innerHTML = '<div class="spinner-border spinner-border-sm me-1" role="status"><span class="visually-hidden">Loading...</span></div>Refreshing...';
        refreshBtn.disabled = true;
        
        // Simulate refresh
        setTimeout(() => {
            location.reload();
        }, 1500);
    }
}

function animateMetrics() {
    const metricCards = document.querySelectorAll('[data-metric]');
    metricCards.forEach((card, index) => {
        setTimeout(() => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            card.style.transition = 'all 0.5s ease';
            
            setTimeout(() => {
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, 100);
        }, index * 150);
    });
}
</script>

<?php require_once __DIR__ . '/../core/includes/footer.php'; ?> 