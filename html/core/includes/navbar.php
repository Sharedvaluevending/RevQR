<?php
// This file is included in header.php, so we don't need to require config and session again
// Note: Cache headers are handled by header.php before HTML output starts

// Get business info for logo if user is a business
$business_logo = null;
if (is_logged_in() && has_role('business')) {
    try {
        $stmt = $pdo->prepare("
            SELECT b.logo_path, b.name 
            FROM businesses b 
            JOIN users u ON b.id = u.business_id 
            WHERE u.id = ?
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $business_data = $stmt->fetch();
        if ($business_data && !empty($business_data['logo_path'])) {
            $business_logo = $business_data['logo_path'];
            $business_name = $business_data['name'];
        }
    } catch (Exception $e) {
        // Silently handle any database errors
        error_log("Error fetching business logo: " . $e->getMessage());
    }
}
?>
<nav class="navbar navbar-expand-lg navbar-dark py-1 fixed-top" style="background: rgba(30, 60, 114, 0.95) !important; backdrop-filter: blur(20px) !important; border-bottom: 1px solid rgba(255, 255, 255, 0.15) !important;">
    <div class="container">
        <div class="navbar-brand d-flex align-items-center">
            <img src="<?php echo APP_URL; ?>/img/logoRQ.png" alt="RevenueQR Logo" height="32" class="me-2">
            <span class="d-none d-sm-inline">Revenue QR</span>
            <span class="badge ms-2" style="background-color: #ffc107; color: #000; font-size: 0.75rem; font-weight: bold;">BRANCH EDIT 2.02</span>
            
        </div>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <?php if (is_logged_in()): ?>
                    <?php if (has_role('admin')): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo APP_URL; ?>/admin/dashboard_modular.php">
                                <i class="bi bi-speedometer2 me-1"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo APP_URL; ?>/admin/manage-users.php">
                                <i class="bi bi-people me-1"></i>Manage Users
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo APP_URL; ?>/admin/manage-businesses.php">
                                <i class="bi bi-building me-1"></i>Manage Businesses
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo APP_URL; ?>/admin/reports.php">
                                <i class="bi bi-graph-up me-1"></i>Reports
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo APP_URL; ?>/admin/system-monitor.php">
                                <i class="bi bi-cpu me-1"></i>System Monitor
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo APP_URL; ?>/admin/user-behavior-analytics.php">
                                <i class="bi bi-graph-up-arrow me-1"></i>User Analytics
                                <span class="badge bg-info ms-1">New!</span>
                            </a>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="economyDropdown" role="button" data-bs-toggle="dropdown">
                                <i class="bi bi-coin text-warning me-1"></i>QR Coin Economy
                                <span class="badge bg-warning text-dark ms-1">Beta</span>
                            </a>
                            <ul class="dropdown-menu">
                                <li><h6 class="dropdown-header">Available Now</h6></li>
                                <li>
                                    <a class="dropdown-item" href="<?php echo APP_URL; ?>/admin/dashboard_modular.php">
                                        <i class="bi bi-graph-up me-2"></i>Economy Dashboard
                                        <span class="badge bg-success ms-1">Live</span>
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?php echo APP_URL; ?>/admin/manage-businesses.php">
                                        <i class="bi bi-building me-2"></i>Business Management
                                        <span class="badge bg-success ms-1">Live</span>
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?php echo APP_URL; ?>/admin/reports.php">
                                        <i class="bi bi-file-earmark-text me-2"></i>System Reports
                                        <span class="badge bg-success ms-1">Live</span>
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li><h6 class="dropdown-header">Coming Soon</h6></li>
                                <li>
                                    <span class="dropdown-item-text text-muted">
                                        <i class="bi bi-arrow-repeat me-2"></i>Circulation Analysis
                                        <span class="badge bg-secondary ms-1">Soon</span>
                                    </span>
                                </li>
                                <li>
                                    <span class="dropdown-item-text text-muted">
                                        <i class="bi bi-exclamation-triangle me-2"></i>Inflation Monitoring
                                        <span class="badge bg-secondary ms-1">Soon</span>
                                    </span>
                                </li>
                                <li>
                                    <span class="dropdown-item-text text-muted">
                                        <i class="bi bi-cash-stack me-2"></i>Revenue Analytics
                                        <span class="badge bg-secondary ms-1">Soon</span>
                                    </span>
                                </li>
                                <li>
                                    <span class="dropdown-item-text text-muted">
                                        <i class="bi bi-shop me-2"></i>Store Performance Analytics
                                        <span class="badge bg-secondary ms-1">Soon</span>
                                    </span>
                                </li>
                            </ul>
                        </li>
                        
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="adminNayaxDropdown" role="button" data-bs-toggle="dropdown">
                                <i class="bi bi-credit-card me-1"></i>Nayax
                                <span class="badge bg-success ms-1">Live</span>
                            </a>
                            <ul class="dropdown-menu">
                                <li><h6 class="dropdown-header">System Management</h6></li>
                                <li>
                                    <a class="dropdown-item" href="<?php echo APP_URL; ?>/admin/nayax-overview.php">
                                        <i class="bi bi-graph-up me-2"></i>System Overview
                                        <span class="badge bg-primary ms-1">Admin</span>
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?php echo APP_URL; ?>/admin/nayax-machines.php">
                                        <i class="bi bi-hdd-stack me-2"></i>Machine Management
                                        <span class="badge bg-info ms-1">Live</span>
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?php echo APP_URL; ?>/admin/nayax-transactions.php">
                                        <i class="bi bi-receipt me-2"></i>All Transactions
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li><h6 class="dropdown-header">Monitoring</h6></li>
                                <li>
                                    <a class="dropdown-item" href="<?php echo APP_URL; ?>/verify_nayax_phase4.php" target="_blank">
                                        <i class="bi bi-check-circle me-2"></i>System Status
                                        <span class="badge bg-success ms-1">Test</span>
                                    </a>
                                </li>
                            </ul>
                        </li>
                        
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo APP_URL; ?>/admin/horse-racing/">
                                <i class="bi bi-trophy me-1"></i>Horse Racing
                                <span class="badge bg-warning text-dark ms-1">New!</span>
                            </a>
                        </li>
                        
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="adminToolsDropdown" role="button" data-bs-toggle="dropdown">
                                <i class="bi bi-tools me-1"></i>Tools
                                <span class="badge bg-info ms-1">New!</span>
                            </a>
                            <ul class="dropdown-menu">
                                <li><h6 class="dropdown-header">QR Management</h6></li>
                                <li>
                                    <a class="dropdown-item" href="<?php echo APP_URL; ?>/admin/qr-scanner.php">
                                        <i class="bi bi-qr-code-scan me-2"></i>QR Scanner
                                        <span class="badge bg-primary ms-1">NIAX</span>
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?php echo APP_URL; ?>/qr_manager.php">
                                        <i class="bi bi-qr-code me-2"></i>QR Manager
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?php echo APP_URL; ?>/qr-generator.php">
                                        <i class="bi bi-plus-circle me-2"></i>QR Generator
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li><h6 class="dropdown-header">System Health</h6></li>
                                <li>
                                    <a class="dropdown-item" href="<?php echo APP_URL; ?>/admin/system-health.php">
                                        <i class="bi bi-heart-fill me-2"></i>System Health
                                        <span class="badge bg-success ms-1">Live</span>
                                    </a>
                                </li>
                            </ul>
                        </li>
                        
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo APP_URL; ?>/admin/settings.php">
                                <i class="bi bi-gear me-1"></i>System Settings
                            </a>
                        </li>
                    <?php elseif (has_role('business')): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo APP_URL; ?>/business/dashboard.php">
                                <i class="bi bi-speedometer2 me-1"></i>Dashboard
                            </a>
                        </li>
                        
                        <!-- Business Tools -->
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="businessToolsDropdown" role="button" data-bs-toggle="dropdown">
                                <i class="bi bi-tools me-1"></i>Business Tools
                            </a>
                            <ul class="dropdown-menu">
                                <li><h6 class="dropdown-header">Inventory & Catalog</h6></li>
                                <li>
                                    <a class="dropdown-item" href="<?php echo APP_URL; ?>/business/my-catalog.php">
                                        <i class="bi bi-bookmark-star me-2"></i>My Catalog
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?php echo APP_URL; ?>/business/master-items.php">
                                        <i class="bi bi-list-ul me-2"></i>Master Items
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?php echo APP_URL; ?>/business/stock-management.php">
                                        <i class="bi bi-boxes me-2"></i>Stock Management
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?php echo APP_URL; ?>/business/manual-sales.php">
                                        <i class="bi bi-cash-coin me-2"></i>Manual Sales Entry
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li><h6 class="dropdown-header">Marketing & Campaigns</h6></li>
                                <li>
                                    <a class="dropdown-item" href="<?php echo APP_URL; ?>/business/manage-campaigns.php">
                                        <i class="bi bi-list me-2"></i>All Campaigns
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?php echo APP_URL; ?>/business/horse-racing/">
                                        <i class="bi bi-trophy me-2"></i>Horse Racing
                                        <span class="badge bg-warning text-dark ms-1">New!</span>
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?php echo APP_URL; ?>/business/create-campaign.php">
                                        <i class="bi bi-plus-circle me-2"></i>New Campaign
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?php echo APP_URL; ?>/business/promotional-ads.php">
                                        <i class="bi bi-megaphone-fill me-2 text-primary"></i>Promotional Ads
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li><h6 class="dropdown-header">Campaign Content</h6></li>
                                <li>
                                    <a class="dropdown-item" href="<?php echo APP_URL; ?>/business/list-maker.php">
                                        <i class="bi bi-list-check me-2"></i>List Maker
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?php echo APP_URL; ?>/business/items.php">
                                        <i class="bi bi-pencil-square me-2"></i>Edit Items
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?php echo APP_URL; ?>/business/manage-lists.php">
                                        <i class="bi bi-collection me-2"></i>Manage Lists
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li><h6 class="dropdown-header">Promotions & Engagement</h6></li>
                                <li>
                                    <a class="dropdown-item" href="<?php echo APP_URL; ?>/business/promotions.php">
                                        <i class="bi bi-star me-2"></i>Promotions & Discounts
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?php echo APP_URL; ?>/business/spin-wheel.php">
                                        <i class="bi bi-stars me-2 text-warning"></i>Spin Wheel
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?php echo APP_URL; ?>/business/pizza-tracker.php">
                                        <i class="bi bi-pizza me-2 text-success"></i>Pizza Tracker
                                    </a>
                                </li>
                            </ul>
                        </li>
                        
                        <!-- QR Codes -->
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="qrDropdown" role="button" data-bs-toggle="dropdown">
                                <i class="bi bi-qr-code me-1"></i>QR Codes
                            </a>
                            <ul class="dropdown-menu">
                                <li>
                                    <a class="dropdown-item" href="<?php echo APP_URL; ?>/qr_manager.php">
                                        <i class="bi bi-grid-3x3-gap me-2 text-primary"></i>QR Manager <span class="badge bg-primary ms-2">New</span>
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <a class="dropdown-item" href="<?php echo APP_URL; ?>/qr-generator.php">
                                        <i class="bi bi-plus-square me-2"></i>Quick Generator
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?php echo APP_URL; ?>/qr-generator-enhanced.php">
                                        <i class="bi bi-palette me-2"></i>Enhanced Generator
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?php echo APP_URL; ?>/qr-display.php">
                                        <i class="bi bi-display me-2"></i>Display Mode
                                    </a>
                                </li>
                            </ul>
                        </li>
                        
                        <!-- Analytics & Reports -->
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="analyticsDropdown" role="button" data-bs-toggle="dropdown">
                                <i class="bi bi-graph-up me-1"></i>Analytics & Reports
                            </a>
                            <ul class="dropdown-menu">
                                <li><h6 class="dropdown-header">Campaign Analytics</h6></li>
                                <li>
                                    <a class="dropdown-item" href="<?php echo APP_URL; ?>/business/campaign-analytics.php">
                                        <i class="bi bi-graph-up me-2"></i>Campaign Performance
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?php echo APP_URL; ?>/business/view-votes.php">
                                        <i class="bi bi-eye me-2"></i>View Votes
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?php echo APP_URL; ?>/business/winners.php">
                                        <i class="bi bi-trophy me-2"></i>Campaign Winners
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li><h6 class="dropdown-header">Business Reports</h6></li>
                                <li>
                                    <a class="dropdown-item" href="<?php echo APP_URL; ?>/business/analytics/sales.php">
                                        <i class="bi bi-cash-coin me-2"></i>Sales Analytics
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?php echo APP_URL; ?>/business/reports.php">
                                        <i class="bi bi-file-earmark-text me-2"></i>Business Reports
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?php echo APP_URL; ?>/business/view-results.php">
                                        <i class="bi bi-bar-chart me-2"></i>View Results
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?php echo APP_URL; ?>/business/qr-analytics.php">
                                        <i class="bi bi-graph-up me-2 text-success"></i>QR Analytics
                                        <span class="badge bg-success ms-1">New!</span>
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?php echo APP_URL; ?>/business/ai-assistant.php">
                                        <i class="bi bi-robot me-2 text-primary"></i>AI Assistant
                                    </a>
                                </li>
                            </ul>
                        </li>
                        
                        <!-- QR Coin Economy & Store -->
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="qrCoinDropdown" role="button" data-bs-toggle="dropdown">
                                <i class="bi bi-coin text-warning me-1"></i>QR Coin Economy
                                <span class="badge bg-warning text-dark ms-1">Beta</span>
                            </a>
                            <ul class="dropdown-menu">
                                <li><h6 class="dropdown-header">Available Now</h6></li>
                                <li>
                                    <a class="dropdown-item" href="<?php echo APP_URL; ?>/business/store.php">
                                        <i class="bi bi-shop me-2"></i>Manage Store Items
                                        <span class="badge bg-success ms-1">Live</span>
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?php echo APP_URL; ?>/business/wallet.php">
                                        <i class="bi bi-wallet2 me-2"></i>QR Wallet
                                        <span class="badge bg-success ms-1">Live</span>
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?php echo APP_URL; ?>/business/settings.php">
                                        <i class="bi bi-dice-5-fill text-danger me-2"></i>🎰 Casino Settings
                                        <span class="badge bg-danger ms-1">10% Revenue</span>
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li><h6 class="dropdown-header">Coming Soon</h6></li>
                                <li>
                                    <span class="dropdown-item-text text-muted">
                                        <i class="bi bi-credit-card me-2"></i>Subscription Plans
                                        <span class="badge bg-secondary ms-1">Soon</span>
                                    </span>
                                </li>
                                <li>
                                    <span class="dropdown-item-text text-muted">
                                        <i class="bi bi-wallet me-2"></i>QR Coin Usage Analytics
                                        <span class="badge bg-secondary ms-1">Soon</span>
                                    </span>
                                </li>
                                <li>
                                    <span class="dropdown-item-text text-muted">
                                        <i class="bi bi-receipt me-2"></i>Billing History
                                        <span class="badge bg-secondary ms-1">Soon</span>
                                    </span>
                                </li>
                                <li>
                                    <span class="dropdown-item-text text-muted">
                                        <i class="bi bi-graph-up me-2"></i>Store Analytics Dashboard
                                        <span class="badge bg-secondary ms-1">Soon</span>
                                    </span>
                                </li>
                                <li>
                                    <span class="dropdown-item-text text-muted">
                                        <i class="bi bi-star me-2 text-warning"></i>Premium Portal Features
                                        <span class="badge bg-secondary ms-1">Soon</span>
                                    </span>
                                </li>
                            </ul>
                        </li>
                        
                        <!-- Nayax Integration -->
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="nayaxDropdown" role="button" data-bs-toggle="dropdown">
                                <i class="bi bi-credit-card me-1"></i>Nayax
                                <span class="badge bg-success ms-1">Live</span>
                            </a>
                            <ul class="dropdown-menu">
                                <li><h6 class="dropdown-header">Business Intelligence</h6></li>
                                <li>
                                    <a class="dropdown-item" href="<?php echo APP_URL; ?>/business/nayax-analytics.php">
                                        <i class="bi bi-graph-up me-2"></i>Advanced Analytics
                                        <span class="badge bg-primary ms-1">AI</span>
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?php echo APP_URL; ?>/business/nayax-customers.php">
                                        <i class="bi bi-people me-2"></i>Customer Intelligence
                                        <span class="badge bg-info ms-1">Insights</span>
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?php echo APP_URL; ?>/business/mobile-dashboard.php">
                                        <i class="bi bi-phone me-2"></i>Mobile Dashboard
                                        <span class="badge bg-secondary ms-1">PWA</span>
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li><h6 class="dropdown-header">Management</h6></li>
                                <li>
                                    <a class="dropdown-item" href="<?php echo APP_URL; ?>/business/nayax-machines.php">
                                        <i class="bi bi-hdd-stack me-2"></i>Machine Status
                                        <span class="badge bg-success ms-1">Live</span>
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?php echo APP_URL; ?>/business/nayax-settings.php">
                                        <i class="bi bi-gear me-2"></i>Nayax Settings
                                    </a>
                                </li>
                            </ul>
                        </li>
                        
                        <!-- Settings -->
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="settingsDropdown" role="button" data-bs-toggle="dropdown">
                                <i class="bi bi-gear me-1"></i>Settings
                            </a>
                            <ul class="dropdown-menu">
                                <li>
                                    <a class="dropdown-item" href="<?php echo APP_URL; ?>/business/profile.php">
                                        <i class="bi bi-building me-2"></i>Business Profile
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?php echo APP_URL; ?>/business/notification-settings.php">
                                        <i class="bi bi-bell me-2 text-warning"></i>Pizza Tracker Notifications
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?php echo APP_URL; ?>/business/user-settings.php">
                                        <i class="bi bi-person-gear me-2"></i>User Settings
                                    </a>
                                </li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <!-- 1. Dashboard -->
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo APP_URL; ?>/user/dashboard.php">
                                <i class="bi bi-speedometer2 me-1"></i>Dashboard
                            </a>
                        </li>
                        
                        <!-- 2. Engagement Dropdown (with QR Gallery and Casino) -->
                        <li class="nav-item dropdown engagement-dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="engagementDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-stars me-1"></i>
                                <span class="fw-bold">Engagement</span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-engagement animate__animated animate__fadeInDown" aria-labelledby="engagementDropdown">
                                <li>
                                    <a class="dropdown-item" href="<?php echo APP_URL; ?>/qr-display-public.php">
                                        <i class="bi bi-qr-code text-info me-2"></i>
                                        <span class="fw-semibold">QR Gallery</span>
                                        <span class="badge bg-gradient bg-info text-white ms-2">New</span>
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?php echo APP_URL; ?>/casino/index.php">
                                        <i class="bi bi-dice-5-fill text-danger me-2"></i>
                                        <span class="fw-semibold">🎰 Casino</span>
                                        <?php if (!is_logged_in()): ?>
                                            <span class="badge bg-gradient bg-danger text-white ms-2">Play Now!</span>
                                        <?php else: ?>
                                            <?php
                                            // Check if user has access to any casino-enabled businesses
                                            $stmt = $pdo->prepare("
                                                SELECT COUNT(*) as casino_count 
                                                FROM business_casino_settings bcs 
                                                JOIN businesses b ON bcs.business_id = b.id 
                                                WHERE bcs.casino_enabled = 1
                                            ");
                                            $stmt->execute();
                                            $casino_count = $stmt->fetchColumn();
                                            
                                            if ($casino_count > 0): ?>
                                                <span class="badge bg-gradient bg-success text-white ms-2"><?php echo $casino_count; ?> Available</span>
                                            <?php else: ?>
                                                <span class="badge bg-gradient bg-warning text-dark ms-2">Coming Soon</span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <a class="dropdown-item" href="<?php echo APP_URL; ?>/user/spin.php">
                                        <i class="bi bi-trophy-fill text-warning me-2"></i>
                                        <span class="fw-semibold">Spin to Win</span>
                                        <span class="badge bg-gradient bg-warning text-dark ms-2">Daily</span>
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?php echo APP_URL; ?>/user/avatars.php">
                                        <i class="bi bi-person-badge-fill text-primary me-2"></i>
                                        <span class="fw-semibold">QR Avatars</span>
                                        <span class="badge bg-gradient bg-primary text-white ms-2">New</span>
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?php echo APP_URL; ?>/horse-racing/">
                                        <i class="bi bi-trophy text-warning me-2"></i>
                                        <span class="fw-semibold">🏇 Horse Racing</span>
                                        <span class="badge bg-gradient bg-warning text-dark ms-2">Live!</span>
                                    </a>
                                </li>
                                <li>
                                    <?php
                                    // Check if user is currently tracking any pizza
                                    $is_tracking_pizza = false;
                                    $active_tracker_id = null;
                                    if (is_logged_in()) {
                                        try {
                                            $stmt = $pdo->prepare("
                                                SELECT pt.id, pt.tracker_id, pt.business_name, pt.customer_name,
                                                       pt.estimated_completion, pt.created_at, pt.pizza_status
                                                FROM pizza_trackers pt 
                                                WHERE pt.created_by = ? 
                                                  AND pt.is_active = 1 
                                                  AND pt.pizza_status IN ('ordered', 'preparing', 'baking', 'ready')
                                                ORDER BY pt.created_at DESC 
                                                LIMIT 1
                                            ");
                                            $stmt->execute([$_SESSION['user_id']]);
                                            $active_pizza = $stmt->fetch(PDO::FETCH_ASSOC);
                                            
                                            if ($active_pizza) {
                                                $is_tracking_pizza = true;
                                                $active_tracker_id = $active_pizza['tracker_id'];
                                            }
                                        } catch (Exception $e) {
                                            // Silently fail - navigation should still work
                                            $is_tracking_pizza = false;
                                        }
                                    }
                                    ?>
                                    <?php if ($is_tracking_pizza && $active_tracker_id): ?>
                                        <a class="dropdown-item" href="<?php echo APP_URL; ?>/public/pizza-tracker.php?tracker_id=<?php echo $active_tracker_id; ?>">
                                            <i class="bi bi-stopwatch text-success me-2"></i>
                                            <span class="fw-semibold">🍕 Tracking Pizza</span>
                                            <span class="badge bg-gradient bg-success text-white ms-2">Active</span>
                                        </a>
                                    <?php else: ?>
                                        <a class="dropdown-item" href="<?php echo APP_URL; ?>/public/pizza-tracker.php" title="You are not tracking any pizza. Scan QR code with pizza tracker link on page or direct URL">
                                            <i class="bi bi-stopwatch text-warning me-2"></i>
                                            <span class="fw-semibold">🍕 No Pizza Tracking</span>
                                            <span class="badge bg-gradient bg-warning text-dark ms-2">Scan QR</span>
                                        </a>
                                    <?php endif; ?>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?php echo APP_URL; ?>/user/result.php">
                                        <i class="bi bi-graph-up-arrow text-info me-2"></i>
                                        <span class="fw-semibold">My Results</span>
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?php echo APP_URL; ?>/user/leaderboard.php">
                                        <i class="bi bi-trophy-fill text-warning me-2"></i>
                                        <span class="fw-semibold">Leaderboard</span>
                                        <span class="badge bg-gradient bg-warning text-dark ms-2">Top 100</span>
                                    </a>
                                </li>
                            </ul>
                        </li>
                        
                        <!-- 3. Stores -->
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="storeDropdown" role="button" data-bs-toggle="dropdown">
                                <i class="bi bi-shop me-1"></i>Stores
                                <span class="badge bg-success ms-1">Live</span>
                            </a>
                            <ul class="dropdown-menu">
                                <li>
                                    <a class="dropdown-item" href="<?php echo APP_URL; ?>/user/qr-store.php">
                                        <i class="bi bi-gem me-2"></i>QR Store
                                        <span class="badge bg-success ms-1">Available</span>
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?php echo APP_URL; ?>/user/business-stores.php">
                                        <i class="bi bi-building me-2"></i>Business Discounts
                                        <span class="badge bg-success ms-1">Available</span>
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <a class="dropdown-item" href="<?php echo APP_URL; ?>/user/my-purchases.php">
                                        <i class="bi bi-bag-check me-2"></i>My Purchases
                                        <span class="badge bg-success ms-1">New</span>
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?php echo APP_URL; ?>/user/my-discount-qr-codes.php">
                                        <i class="bi bi-qr-code me-2"></i>My QR Codes
                                        <span class="badge bg-primary ms-1">Nayax</span>
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?php echo APP_URL; ?>/user/qr-transactions.php">
                                        <i class="bi bi-clock-history me-2"></i>Transaction History
                                        <span class="badge bg-success ms-1">New</span>
                                    </a>
                                </li>
                            </ul>
                        </li>
                        
                        <!-- 4. Vote -->
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo APP_URL; ?>/user/vote.php">
                                <i class="bi bi-check2-square me-1"></i>Vote
                            </a>
                        </li>
                        
                        <!-- User Guide (kept at the end) -->
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo APP_URL; ?>/user/user-guide.php">
                                <i class="bi bi-book-half me-1"></i>User Guide
                                <span class="badge bg-info ms-1">Help</span>
                            </a>
                        </li>
                    <?php endif; ?>
                <?php endif; ?>
            </ul>
            <ul class="navbar-nav">
                <?php if (is_logged_in()): ?>
                    <!-- QR Coin Wallet (Users Only) -->
                    <?php if (!has_role('admin') && !has_role('business')): ?>
                        <?php
                        // Get user's QR coin balance
                        $user_qr_balance = 0;
                        if (isset($_SESSION['user_id'])) {
                            require_once __DIR__ . '/../../core/qr_coin_manager.php';
                            $user_qr_balance = QRCoinManager::getBalance($_SESSION['user_id']);
                        }
                        ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo APP_URL; ?>/user/qr-transactions.php" title="QR Coin Wallet">
                                <img src="<?php echo APP_URL; ?>/img/qrCoin.png" alt="QR Coin" class="me-1" style="width: 20px; height: 20px;">
                                <span class="fw-semibold" id="navbarQRBalance"><?php echo number_format($user_qr_balance); ?></span>
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                            <?php if (has_role('business') && $business_logo): ?>
                                <img src="<?php echo APP_URL . '/' . htmlspecialchars($business_logo); ?>" 
                                     alt="Business Logo" 
                                     class="rounded-circle me-2" 
                                     style="width: 24px; height: 24px; object-fit: contain; background: white; padding: 2px;">
                            <?php else: ?>
                                <i class="bi bi-person-circle me-1"></i>
                            <?php endif; ?>
                            <?php echo isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : (isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name']) : 'User'); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <?php if (has_role('business') && $business_logo): ?>
                                <li class="dropdown-header text-center">
                                    <img src="<?php echo APP_URL . '/' . htmlspecialchars($business_logo); ?>" 
                                         alt="Business Logo" 
                                         class="rounded" 
                                         style="width: 40px; height: 40px; object-fit: contain; background: white; padding: 4px;">
                                    <div class="small text-muted mt-1"><?php echo htmlspecialchars($business_name ?? 'Business'); ?></div>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                            <?php endif; ?>
                            <li>
                                <a class="dropdown-item" href="<?php echo has_role('business') ? '/business/profile.php' : '/user/profile.php'; ?>">
                                    <i class="bi bi-person me-2"></i>Profile
                                </a>
                            </li>
                            <?php if (has_role('business')): ?>
                                <li>
                                    <a class="dropdown-item" href="<?php echo APP_URL; ?>/business/business-guide.php">
                                        <i class="bi bi-book-half me-2"></i>Business Guide
                                        <span class="badge bg-success ms-2">Help</span>
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?php echo APP_URL; ?>/business/settings.php">
                                        <i class="bi bi-gear me-2"></i>Settings
                                    </a>
                                </li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item" href="<?php echo APP_URL; ?>/logout.php">
                                    <i class="bi bi-box-arrow-right me-2"></i>Logout
                                </a>
                            </li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo APP_URL; ?>/login.php">
                            <i class="bi bi-box-arrow-in-right me-1"></i>Login
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo APP_URL; ?>/register.php">
                            <i class="bi bi-person-plus me-1"></i>Register
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<script>
// Listen for balance updates from casino and other sources
window.addEventListener('storage', function(e) {
    if (e.key === 'qr_balance_update') {
        try {
            const data = JSON.parse(e.newValue);
            if (data && data.balance !== undefined) {
                updateNavbarBalance(data.balance);
            }
        } catch (error) {
            console.warn('Failed to parse balance update:', error);
        }
    }
});

// Also listen for same-tab updates
window.addEventListener('storage', function(e) {
    if (e.key === 'qr_balance_update') {
        try {
            const data = JSON.parse(e.newValue);
            if (data && data.balance !== undefined) {
                updateNavbarBalance(data.balance);
            }
        } catch (error) {
            console.warn('Failed to parse balance update:', error);
        }
    }
});

// Listen for postMessage events (from iframes)
window.addEventListener('message', function(e) {
    if (e.data && e.data.type === 'balance_update' && e.data.balance !== undefined) {
        updateNavbarBalance(e.data.balance);
    }
});

// Listen for custom balance update events
window.addEventListener('balanceUpdate', function(e) {
    if (e.detail && e.detail.balance !== undefined) {
        updateNavbarBalance(e.detail.balance);
    }
});

function updateNavbarBalance(newBalance) {
    const balanceElement = document.getElementById('navbarQRBalance');
    if (balanceElement) {
        balanceElement.textContent = new Intl.NumberFormat().format(newBalance);
        console.log('Updated navbar balance to:', newBalance);
    }
}
</script> 