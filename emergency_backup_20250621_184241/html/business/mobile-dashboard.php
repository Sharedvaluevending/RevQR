<?php
/**
 * Mobile Business Dashboard
 * Progressive Web App for business owners to manage Nayax integration on-the-go
 */

require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/nayax_analytics_engine.php';
require_once __DIR__ . '/../core/nayax_optimizer.php';

// Check authentication and role
require_login();
if (!has_role('business')) {
    header('Location: ' . APP_URL . '/login.php');
    exit;
}

// Get business ID
$business_id = get_business_id();
if (!$business_id) {
    header('Location: ' . APP_URL . '/business/profile.php?error=no_business');
    exit;
}

// Get business data
try {
    $stmt = $pdo->prepare("SELECT * FROM businesses WHERE id = ?");
    $stmt->execute([$business_id]);
    $business = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$business) {
        header('Location: ' . APP_URL . '/business/profile.php?error=business_not_found');
        exit;
    }
} catch (Exception $e) {
    error_log("Error fetching business data: " . $e->getMessage());
    $business = ['name' => 'Demo Business', 'id' => $business_id];
}

// Initialize engines
$analytics_engine = new NayaxAnalyticsEngine($pdo);
$optimizer = new NayaxOptimizer($pdo, $analytics_engine);

// Get today's quick stats
$today_analytics = $analytics_engine->getBusinessAnalytics($business_id, 1, false);
$week_analytics = $analytics_engine->getBusinessAnalytics($business_id, 7, false);

// Get optimization recommendations
$optimization = $optimizer->getOptimizationRecommendations($business_id, 7);
$quick_wins = $optimization['quick_wins'];
$priority_actions = array_slice($optimization['priority_actions'], 0, 3);

// Get recent transactions
$stmt = $pdo->prepare("
    SELECT 
        nt.id,
        nt.amount_cents,
        nt.transaction_type,
        nt.created_at,
        nm.machine_name,
        nt.qr_coins_awarded
    FROM nayax_transactions nt
    JOIN nayax_machines nm ON nt.nayax_machine_id = nm.nayax_machine_id
    WHERE nm.business_id = ? 
    AND nt.status = 'completed'
    AND nt.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ORDER BY nt.created_at DESC
    LIMIT 10
");
$stmt->execute([$business_id]);
$recent_transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get machine status (with error handling for missing tables/columns)
$machines = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            nm.nayax_machine_id,
            nm.machine_name,
            COUNT(nt.id) as today_transactions,
            SUM(nt.amount_cents) as today_revenue,
            MAX(nt.created_at) as last_activity
        FROM nayax_machines nm
        LEFT JOIN nayax_transactions nt ON nm.nayax_machine_id = nt.nayax_machine_id 
            AND nt.status = 'completed'
            AND DATE(nt.created_at) = CURDATE()
        WHERE nm.business_id = ?
        GROUP BY nm.nayax_machine_id
        ORDER BY today_revenue DESC
    ");
    $stmt->execute([$business_id]);
    $machines = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching machine data: " . $e->getMessage());
    // Use demo data if tables don't exist
    $machines = [
        [
            'nayax_machine_id' => 'NAY001',
            'machine_name' => 'Demo Vending Machine #1',
            'today_transactions' => 5,
            'today_revenue' => 2500,
            'last_activity' => date('Y-m-d H:i:s', strtotime('-1 hour'))
        ],
        [
            'nayax_machine_id' => 'NAY002', 
            'machine_name' => 'Demo Vending Machine #2',
            'today_transactions' => 3,
            'today_revenue' => 1800,
            'last_activity' => date('Y-m-d H:i:s', strtotime('-2 hours'))
        ]
    ];
}

$page_title = "Mobile Dashboard - " . $business['name'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title><?= htmlspecialchars($page_title) ?> | RevenueQR</title>
    
    <!-- PWA Meta Tags -->
    <meta name="theme-color" content="#4a90e2">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="RevenueQR Mobile">
    <meta name="description" content="Mobile dashboard for managing your Nayax vending machine integration">
    
    <!-- PWA Icons -->
    <link rel="apple-touch-icon" sizes="180x180" href="/html/assets/img/pwa/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/html/assets/img/pwa/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/html/assets/img/pwa/favicon-16x16.png">
    <link rel="manifest" href="/html/assets/manifest.json">
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #4a90e2;
            --secondary-color: #f39c12;
            --success-color: #27ae60;
            --danger-color: #e74c3c;
            --warning-color: #f39c12;
            --info-color: #3498db;
            --light-bg: #f8f9fa;
            --shadow: 0 2px 15px rgba(0,0,0,0.1);
            --border-radius: 15px;
            --safe-area-inset-top: env(safe-area-inset-top);
            --safe-area-inset-bottom: env(safe-area-inset-bottom);
        }
        
        * {
            box-sizing: border-box;
        }
        
        body {
            background-color: var(--light-bg);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
            margin: 0;
            padding: 0;
            padding-top: var(--safe-area-inset-top);
            padding-bottom: var(--safe-area-inset-bottom);
            -webkit-touch-callout: none;
            -webkit-user-select: none;
            user-select: none;
            overflow-x: hidden;
        }
        
        .mobile-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 20px 15px;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .business-info h1 {
            font-size: 1.3rem;
            margin: 0;
            font-weight: 600;
        }
        
        .business-info p {
            font-size: 0.9rem;
            margin: 0;
            opacity: 0.9;
        }
        
        .header-actions {
            display: flex;
            gap: 10px;
        }
        
        .refresh-btn {
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            padding: 8px 12px;
            border-radius: 8px;
            font-size: 0.9rem;
            transition: all 0.2s;
        }
        
        .refresh-btn:hover {
            background: rgba(255,255,255,0.3);
        }
        
        .container-mobile {
            padding: 15px;
            max-width: 100%;
        }
        
        .metric-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: var(--shadow);
            transition: transform 0.2s;
            position: relative;
            overflow: hidden;
        }
        
        .metric-card:active {
            transform: scale(0.98);
        }
        
        .metric-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: var(--primary-color);
        }
        
        .metric-card.success::before { background: var(--success-color); }
        .metric-card.warning::before { background: var(--warning-color); }
        .metric-card.info::before { background: var(--info-color); }
        
        .metric-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .metric-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            color: white;
        }
        
        .metric-icon.success { background: var(--success-color); }
        .metric-icon.warning { background: var(--warning-color); }
        .metric-icon.info { background: var(--info-color); }
        .metric-icon.primary { background: var(--primary-color); }
        
        .metric-value {
            font-size: 2rem;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .metric-label {
            color: #6c757d;
            font-size: 0.9rem;
            margin-bottom: 10px;
        }
        
        .metric-change {
            display: flex;
            align-items: center;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .metric-change.positive {
            color: var(--success-color);
        }
        
        .metric-change.negative {
            color: var(--danger-color);
        }
        
        .metric-change.neutral {
            color: #6c757d;
        }
        
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .action-btn {
            background: white;
            border: none;
            border-radius: var(--border-radius);
            padding: 20px 15px;
            text-align: center;
            box-shadow: var(--shadow);
            transition: all 0.2s;
            color: #2c3e50;
            text-decoration: none;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
        }
        
        .action-btn:active {
            transform: scale(0.95);
            color: #2c3e50;
        }
        
        .action-btn i {
            font-size: 1.5rem;
            color: var(--primary-color);
        }
        
        .action-btn span {
            font-weight: 500;
            font-size: 0.9rem;
        }
        
        .section-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #2c3e50;
            margin: 25px 0 15px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .transaction-item {
            background: white;
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .transaction-info {
            flex: 1;
        }
        
        .transaction-type {
            font-weight: 500;
            color: #2c3e50;
            font-size: 0.9rem;
        }
        
        .transaction-details {
            color: #6c757d;
            font-size: 0.8rem;
            margin-top: 2px;
        }
        
        .transaction-amount {
            font-weight: bold;
            color: var(--success-color);
            font-size: 1rem;
        }
        
        .machine-status {
            background: white;
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .machine-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .machine-name {
            font-weight: 500;
            color: #2c3e50;
        }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 500;
        }
        
        .status-active {
            background: #d4edda;
            color: #155724;
        }
        
        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }
        
        .machine-stats {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .stat {
            text-align: center;
        }
        
        .stat-value {
            font-weight: bold;
            color: var(--primary-color);
            font-size: 1.1rem;
        }
        
        .stat-label {
            font-size: 0.7rem;
            color: #6c757d;
        }
        
        .recommendation-card {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border-radius: var(--border-radius);
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: var(--shadow);
        }
        
        .recommendation-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }
        
        .priority-high {
            background: linear-gradient(135deg, #ff6b6b, #ee5a6f);
        }
        
        .priority-medium {
            background: linear-gradient(135deg, #4ecdc4, #44a08d);
        }
        
        .pull-to-refresh {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 60px;
            background: var(--primary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            transform: translateY(-100%);
            transition: transform 0.3s ease;
            z-index: 1000;
        }
        
        .pull-to-refresh.active {
            transform: translateY(0);
        }
        
        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .offline-indicator {
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            background: var(--danger-color);
            color: white;
            padding: 10px 20px;
            border-radius: 25px;
            font-size: 0.9rem;
            display: none;
            z-index: 1000;
        }
        
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            box-shadow: 0 -2px 15px rgba(0,0,0,0.1);
            padding: 10px 0;
            padding-bottom: calc(10px + var(--safe-area-inset-bottom));
            z-index: 100;
        }
        
        .nav-items {
            display: flex;
            justify-content: space-around;
            align-items: center;
        }
        
        .nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            color: #6c757d;
            text-decoration: none;
            font-size: 0.7rem;
            transition: color 0.2s;
        }
        
        .nav-item.active {
            color: var(--primary-color);
        }
        
        .nav-item i {
            font-size: 1.2rem;
            margin-bottom: 4px;
        }
        
        .main-content {
            padding-bottom: 80px; /* Space for bottom nav */
        }
        
        /* iOS Safari specific fixes */
        @supports (-webkit-touch-callout: none) {
            .mobile-header {
                padding-top: calc(20px + var(--safe-area-inset-top));
            }
        }
        
        /* Hide scrollbar but keep functionality */
        ::-webkit-scrollbar {
            display: none;
        }
        
        /* Prevent zoom on form inputs */
        input, select, textarea {
            font-size: 16px !important;
        }
        
        @media (max-width: 375px) {
            .container-mobile {
                padding: 10px;
            }
            
            .metric-value {
                font-size: 1.8rem;
            }
            
            .quick-actions {
                grid-template-columns: 1fr;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <!-- Pull to Refresh Indicator -->
    <div class="pull-to-refresh" id="pullToRefresh">
        <div class="loading-spinner"></div>
        <span class="ms-2">Release to refresh</span>
    </div>
    
    <!-- Mobile Header -->
    <div class="mobile-header">
        <div class="header-content">
            <div class="business-info">
                <h1><?= htmlspecialchars($business['name']) ?></h1>
                <p>Nayax Mobile Dashboard</p>
            </div>
            <div class="header-actions">
                <button class="refresh-btn" onclick="refreshData()">
                    <i class="bi bi-arrow-clockwise"></i>
                </button>
            </div>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <div class="container-mobile">
            <!-- Key Metrics -->
            <div class="metric-card success">
                <div class="metric-header">
                    <div class="metric-icon success">
                        <i class="bi bi-currency-dollar"></i>
                    </div>
                    <div class="metric-change <?= $week_analytics['revenue']['growth_rate'] >= 0 ? 'positive' : 'negative' ?>">
                        <i class="bi bi-<?= $week_analytics['revenue']['growth_rate'] >= 0 ? 'arrow-up' : 'arrow-down' ?> me-1"></i>
                        <?= abs($week_analytics['revenue']['growth_rate']) ?>%
                    </div>
                </div>
                <div class="metric-value">$<?= number_format($today_analytics['revenue']['total_revenue_dollars'], 2) ?></div>
                <div class="metric-label">Today's Revenue</div>
                <div class="metric-change neutral">
                    <i class="bi bi-calendar-week me-1"></i>
                    $<?= number_format($week_analytics['revenue']['total_revenue_dollars'], 2) ?> this week
                </div>
            </div>
            
            <div class="row">
                <div class="col-6">
                    <div class="metric-card info">
                        <div class="metric-header">
                            <div class="metric-icon info">
                                <i class="bi bi-receipt"></i>
                            </div>
                        </div>
                        <div class="metric-value"><?= $today_analytics['revenue']['total_transactions'] ?></div>
                        <div class="metric-label">Transactions</div>
                        <div class="metric-change neutral">
                            <small>Today</small>
                        </div>
                    </div>
                </div>
                
                <div class="col-6">
                    <div class="metric-card warning">
                        <div class="metric-header">
                            <div class="metric-icon warning">
                                <i class="bi bi-coin"></i>
                            </div>
                        </div>
                        <div class="metric-value"><?= number_format($today_analytics['qr_coins']['total_coins_sold']) ?></div>
                        <div class="metric-label">QR Coins</div>
                        <div class="metric-change neutral">
                            <small>Sold today</small>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="section-title">
                <i class="bi bi-lightning"></i>
                Quick Actions
            </div>
            
            <div class="quick-actions">
                <a href="/html/business/nayax-analytics.php" class="action-btn">
                    <i class="bi bi-graph-up"></i>
                    <span>Analytics</span>
                </a>
                
                <a href="/html/business/nayax-machines.php" class="action-btn">
                    <i class="bi bi-cpu"></i>
                    <span>Machines</span>
                </a>
                
                <a href="/html/business/nayax-customers.php" class="action-btn">
                    <i class="bi bi-people"></i>
                    <span>Customers</span>
                </a>
                
                <a href="/html/core/nayax_qr_generator.php?business_id=<?= $business_id ?>" class="action-btn">
                    <i class="bi bi-qr-code"></i>
                    <span>QR Codes</span>
                </a>
            </div>
            
            <!-- Priority Recommendations -->
            <?php if (!empty($priority_actions)): ?>
            <div class="section-title">
                <i class="bi bi-lightbulb"></i>
                Priority Actions
            </div>
            
            <?php foreach (array_slice($priority_actions, 0, 2) as $action): ?>
            <div class="recommendation-card priority-<?= $action['priority'] ?? 'medium' ?>">
                <div class="recommendation-header">
                    <i class="bi bi-exclamation-triangle"></i>
                    <strong><?= ucfirst($action['priority'] ?? 'Medium') ?> Priority</strong>
                </div>
                <h6><?= htmlspecialchars($action['title'] ?? $action['type'] ?? 'Optimization Opportunity') ?></h6>
                <p class="mb-0"><?= htmlspecialchars($action['description'] ?? $action['reason'] ?? 'Review recommended action') ?></p>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
            
            <!-- Machine Status -->
            <div class="section-title">
                <i class="bi bi-cpu"></i>
                Machine Status
            </div>
            
            <?php foreach (array_slice($machines, 0, 3) as $machine): ?>
            <div class="machine-status">
                <div class="machine-header">
                    <div class="machine-name"><?= htmlspecialchars($machine['machine_name']) ?></div>
                    <div class="status-badge <?= $machine['today_transactions'] > 0 ? 'status-active' : 'status-inactive' ?>">
                        <?= $machine['today_transactions'] > 0 ? 'Active' : 'Inactive' ?>
                    </div>
                </div>
                <div class="machine-stats">
                    <div class="stat">
                        <div class="stat-value"><?= $machine['today_transactions'] ?></div>
                        <div class="stat-label">Transactions</div>
                    </div>
                    <div class="stat">
                        <div class="stat-value">$<?= number_format(($machine['today_revenue'] ?? 0) / 100, 2) ?></div>
                        <div class="stat-label">Revenue</div>
                    </div>
                    <div class="stat">
                        <div class="stat-value"><?= $machine['last_activity'] ? date('H:i', strtotime($machine['last_activity'])) : '--:--' ?></div>
                        <div class="stat-label">Last Activity</div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            
            <!-- Recent Transactions -->
            <div class="section-title">
                <i class="bi bi-clock-history"></i>
                Recent Transactions
            </div>
            
            <?php if (!empty($recent_transactions)): ?>
            <?php foreach (array_slice($recent_transactions, 0, 5) as $transaction): ?>
            <div class="transaction-item">
                <div class="transaction-info">
                    <div class="transaction-type">
                        <?= $transaction['transaction_type'] === 'qr_coin_purchase' ? 'QR Coin Purchase' : 'Regular Purchase' ?>
                    </div>
                    <div class="transaction-details">
                        <?= htmlspecialchars($transaction['machine_name']) ?> • 
                        <?= date('H:i', strtotime($transaction['created_at'])) ?>
                        <?php if ($transaction['qr_coins_awarded']): ?>
                        • <?= $transaction['qr_coins_awarded'] ?> coins
                        <?php endif; ?>
                    </div>
                </div>
                <div class="transaction-amount">
                    $<?= number_format($transaction['amount_cents'] / 100, 2) ?>
                </div>
            </div>
            <?php endforeach; ?>
            <?php else: ?>
            <div class="transaction-item">
                <div class="transaction-info">
                    <div class="transaction-type">No transactions today</div>
                    <div class="transaction-details">Check back later for updates</div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Bottom Navigation -->
    <div class="bottom-nav">
        <div class="nav-items">
            <a href="/html/business/mobile-dashboard.php" class="nav-item active">
                <i class="bi bi-house"></i>
                Dashboard
            </a>
            <a href="/html/business/nayax-analytics.php" class="nav-item">
                <i class="bi bi-graph-up"></i>
                Analytics
            </a>
            <a href="/html/business/nayax-customers.php" class="nav-item">
                <i class="bi bi-people"></i>
                Customers
            </a>
            <a href="/html/business/settings.php" class="nav-item">
                <i class="bi bi-gear"></i>
                Settings
            </a>
        </div>
    </div>
    
    <!-- Offline Indicator -->
    <div class="offline-indicator" id="offlineIndicator">
        <i class="bi bi-wifi-off me-2"></i>
        You're offline
    </div>
    
    <!-- Scripts -->
    <script>
        // Progressive Web App functionality
        let deferredPrompt;
        let pullToRefreshEnabled = false;
        let startY = 0;
        
        // Service Worker Registration
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/sw.js')
                .then(registration => console.log('SW registered'))
                .catch(error => console.log('SW registration failed'));
        }
        
        // Install PWA prompt
        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            deferredPrompt = e;
            showInstallPrompt();
        });
        
        function showInstallPrompt() {
            // Show custom install prompt
            console.log('App can be installed');
        }
        
        // Pull to refresh functionality
        let pullDistance = 0;
        let pullThreshold = 100;
        let isPulling = false;
        
        document.addEventListener('touchstart', (e) => {
            if (window.scrollY === 0) {
                startY = e.touches[0].clientY;
                pullToRefreshEnabled = true;
            }
        }, { passive: true });
        
        document.addEventListener('touchmove', (e) => {
            if (!pullToRefreshEnabled) return;
            
            const currentY = e.touches[0].clientY;
            pullDistance = Math.max(0, currentY - startY);
            
            if (pullDistance > 10) {
                e.preventDefault();
                isPulling = true;
                
                const pullIndicator = document.getElementById('pullToRefresh');
                const progress = Math.min(pullDistance / pullThreshold, 1);
                
                if (progress >= 1) {
                    pullIndicator.classList.add('active');
                } else {
                    pullIndicator.style.transform = `translateY(${-100 + (progress * 100)}%)`;
                }
            }
        }, { passive: false });
        
        document.addEventListener('touchend', () => {
            if (isPulling && pullDistance >= pullThreshold) {
                refreshData();
            }
            
            // Reset
            pullToRefreshEnabled = false;
            isPulling = false;
            pullDistance = 0;
            
            const pullIndicator = document.getElementById('pullToRefresh');
            pullIndicator.classList.remove('active');
            pullIndicator.style.transform = 'translateY(-100%)';
        });
        
        // Refresh data function
        function refreshData() {
            console.log('Refreshing data...');
            
            // Show loading state
            const refreshBtn = document.querySelector('.refresh-btn');
            const originalContent = refreshBtn.innerHTML;
            refreshBtn.innerHTML = '<div class="loading-spinner"></div>';
            
            // Simulate API refresh
            setTimeout(() => {
                location.reload();
            }, 1000);
        }
        
        // Online/Offline detection
        function updateOnlineStatus() {
            const offlineIndicator = document.getElementById('offlineIndicator');
            
            if (navigator.onLine) {
                offlineIndicator.style.display = 'none';
            } else {
                offlineIndicator.style.display = 'block';
            }
        }
        
        window.addEventListener('online', updateOnlineStatus);
        window.addEventListener('offline', updateOnlineStatus);
        updateOnlineStatus();
        
        // Prevent zoom on double-tap
        let lastTouchEnd = 0;
        document.addEventListener('touchend', function (event) {
            const now = (new Date()).getTime();
            if (now - lastTouchEnd <= 300) {
                event.preventDefault();
            }
            lastTouchEnd = now;
        }, false);
        
        // Auto-refresh every 5 minutes when visible
        let refreshInterval;
        
        function startAutoRefresh() {
            refreshInterval = setInterval(() => {
                if (document.visibilityState === 'visible') {
                    // Fetch new data without full page reload
                    fetchLatestMetrics();
                }
            }, 300000); // 5 minutes
        }
        
        function stopAutoRefresh() {
            if (refreshInterval) {
                clearInterval(refreshInterval);
            }
        }
        
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') {
                startAutoRefresh();
            } else {
                stopAutoRefresh();
            }
        });
        
        startAutoRefresh();
        
        // Fetch latest metrics without page reload
        async function fetchLatestMetrics() {
            try {
                const response = await fetch('/html/api/mobile-metrics.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({business_id: <?= $business_id ?>})
                });
                
                if (response.ok) {
                    const data = await response.json();
                    updateMetricsDisplay(data);
                }
            } catch (error) {
                console.error('Failed to fetch latest metrics:', error);
            }
        }
        
        function updateMetricsDisplay(data) {
            // Update metric values dynamically
            if (data.today_revenue !== undefined) {
                document.querySelector('.metric-value').textContent = 
                    '$' + parseFloat(data.today_revenue).toFixed(2);
            }
            
            // Add subtle animation to indicate update
            const metricCards = document.querySelectorAll('.metric-card');
            metricCards.forEach(card => {
                card.style.transform = 'scale(1.02)';
                setTimeout(() => {
                    card.style.transform = 'scale(1)';
                }, 200);
            });
        }
        
        // Track mobile dashboard usage
        fetch('/html/api/track-analytics.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                event: 'mobile_dashboard_viewed',
                data: {
                    business_id: <?= $business_id ?>,
                    user_agent: navigator.userAgent,
                    screen_size: `${screen.width}x${screen.height}`,
                    is_pwa: window.matchMedia('(display-mode: standalone)').matches
                }
            })
        }).catch(console.error);
        
        // Performance monitoring
        window.addEventListener('load', function() {
            console.log('Mobile dashboard loaded successfully');
            
            // Report loading performance
            if ('performance' in window) {
                const loadTime = performance.timing.loadEventEnd - performance.timing.navigationStart;
                console.log(`Page load time: ${loadTime}ms`);
            }
        });
    </script>
</body>
</html> 