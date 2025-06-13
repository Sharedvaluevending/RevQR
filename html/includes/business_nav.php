<?php
// Get current page for navigation highlighting
$current_page = basename($_SERVER['PHP_SELF']);
$business_id = $_SESSION['business_id'] ?? null;

// Include config for APP_URL constant
require_once __DIR__ . '/../core/config.php';

// Check if stores are enabled
require_once __DIR__ . '/../core/config_manager.php';
$store_enabled = ConfigManager::get('business_store_enabled', false);
?>

<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container-fluid">
        <a class="navbar-brand" href="<?php echo APP_URL; ?>/business/dashboard.php">
            <i class="bi bi-building me-2"></i>Business Portal
        </a>
        
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#businessNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="businessNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'dashboard.php' ? 'active' : ''; ?>" 
                       href="<?php echo APP_URL; ?>/business/dashboard.php">
                        <i class="bi bi-house-door me-1"></i>Dashboard
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'machines.php' ? 'active' : ''; ?>" 
                       href="<?php echo APP_URL; ?>/business/machines.php">
                        <i class="bi bi-cpu me-1"></i>Machines
                    </a>
                </li>
                
                <!-- QR Code Management -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?php echo in_array($current_page, ['qr-manager.php', 'print-shop.php', 'qr-generator.php', 'qr-generator-enhanced.php']) ? 'active' : ''; ?>" 
                       href="#" id="qrDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-qr-code me-1"></i>QR Codes
                    </a>
                    <ul class="dropdown-menu">
                        <li>
                            <a class="dropdown-item <?php echo $current_page === 'qr-manager.php' ? 'active' : ''; ?>" 
                               href="<?php echo APP_URL; ?>/qr_manager.php">
                                <i class="bi bi-grid-3x3-gap me-2"></i>QR Manager
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item <?php echo $current_page === 'print-shop.php' ? 'active' : ''; ?>" 
                               href="<?php echo APP_URL; ?>/print-shop.php">
                                <i class="bi bi-printer me-2"></i>Print Shop
                                <span class="badge bg-success ms-1">New</span>
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li class="dropdown-header">Create QR Codes</li>
                        <li>
                            <a class="dropdown-item <?php echo $current_page === 'qr-generator.php' ? 'active' : ''; ?>" 
                               href="<?php echo APP_URL; ?>/qr-generator.php">
                                <i class="bi bi-plus-circle me-2"></i>Standard Generator
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item <?php echo $current_page === 'qr-generator-enhanced.php' ? 'active' : ''; ?>" 
                               href="<?php echo APP_URL; ?>/qr-generator-enhanced.php">
                                <i class="bi bi-magic me-2"></i>Enhanced Generator
                                <span class="badge bg-primary ms-1">Pro</span>
                            </a>
                        </li>
                    </ul>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'analytics.php' ? 'active' : ''; ?>" 
                       href="<?php echo APP_URL; ?>/business/analytics.php">
                        <i class="bi bi-graph-up me-1"></i>Analytics
                    </a>
                </li>
                
                <!-- Store Management -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?php echo $current_page === 'store.php' ? 'active' : ''; ?>" 
                       href="#" id="storeDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-shop me-1"></i>QR Store
                        <span class="badge bg-warning ms-1">Beta</span>
                    </a>
                    <ul class="dropdown-menu">
                        <li>
                            <a class="dropdown-item <?php echo $current_page === 'store.php' ? 'active' : ''; ?>" 
                               href="<?php echo APP_URL; ?>/business/store.php">
                                <i class="bi bi-grid me-2"></i>Manage Store Items
                                <span class="badge bg-success ms-1">Available</span>
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li class="dropdown-header">Coming Soon</li>
                        <li>
                            <span class="dropdown-item-text text-muted">
                                <i class="bi bi-bar-chart me-2"></i>Store Analytics
                                <span class="badge bg-secondary ms-1">Soon</span>
                            </span>
                        </li>
                        <li>
                            <span class="dropdown-item-text text-muted">
                                <i class="bi bi-calculator me-2"></i>QR Coin Pricing Calculator
                                <span class="badge bg-secondary ms-1">Soon</span>
                            </span>
                        </li>
                    </ul>
                </li>
                
                <!-- Nayax Integration -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?php echo in_array($current_page, ['nayax-analytics.php', 'nayax-customers.php', 'mobile-dashboard.php']) ? 'active' : ''; ?>" 
                       href="#" id="nayaxDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-credit-card me-1"></i>Nayax
                        <span class="badge bg-success ms-1">Live</span>
                    </a>
                    <ul class="dropdown-menu">
                        <li>
                            <a class="dropdown-item <?php echo $current_page === 'nayax-analytics.php' ? 'active' : ''; ?>" 
                               href="<?php echo APP_URL; ?>/business/nayax-analytics.php">
                                <i class="bi bi-graph-up me-2"></i>Advanced Analytics
                                <span class="badge bg-primary ms-1">AI</span>
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item <?php echo $current_page === 'nayax-customers.php' ? 'active' : ''; ?>" 
                               href="<?php echo APP_URL; ?>/business/nayax-customers.php">
                                <i class="bi bi-people me-2"></i>Customer Intelligence
                                <span class="badge bg-info ms-1">Insights</span>
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item <?php echo $current_page === 'mobile-dashboard.php' ? 'active' : ''; ?>" 
                               href="<?php echo APP_URL; ?>/business/mobile-dashboard.php">
                                <i class="bi bi-phone me-2"></i>Mobile Dashboard
                                <span class="badge bg-secondary ms-1">PWA</span>
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li class="dropdown-header">Management</li>
                        <li>
                            <a class="dropdown-item" href="<?php echo APP_URL; ?>/business/nayax-machines.php">
                                <i class="bi bi-hdd-stack me-2"></i>Machine Status
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="<?php echo APP_URL; ?>/business/nayax-settings.php">
                                <i class="bi bi-gear me-2"></i>Nayax Settings
                            </a>
                        </li>
                    </ul>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'settings.php' ? 'active' : ''; ?>" 
                       href="<?php echo APP_URL; ?>/business/settings.php">
                        <i class="bi bi-gear me-1"></i>Settings
                    </a>
                </li>
            </ul>
            
            <ul class="navbar-nav">
                <!-- Subscription Status -->
                <li class="nav-item">
                    <span class="nav-link" title="QR Coin Economy - Coming Soon">
                        <i class="bi bi-credit-card me-1"></i>
                        <?php
                        if ($business_id) {
                            require_once __DIR__ . '/../core/business_qr_manager.php';
                            $subscription = BusinessQRManager::getSubscription($business_id);
                            echo $subscription ? ucfirst($subscription['tier']) : 'No Plan';
                        } else {
                            echo 'QR Economy';
                        }
                        ?>
                        <span class="badge bg-secondary ms-1">Beta</span>
                    </span>
                </li>
                
                <!-- User Account -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle me-1"></i>
                        <?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <a class="dropdown-item" href="<?php echo APP_URL; ?>/business/profile.php">
                                <i class="bi bi-person me-2"></i>Profile
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="<?php echo APP_URL; ?>/user/dashboard.php">
                                <i class="bi bi-box-arrow-up-right me-2"></i>User Dashboard
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item" href="<?php echo APP_URL; ?>/core/logout.php">
                                <i class="bi bi-box-arrow-right me-2"></i>Logout
                            </a>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- Store Status Alert -->
<?php if (!$store_enabled && in_array($current_page, ['store.php', 'store-analytics.php'])): ?>
<div class="alert alert-warning alert-dismissible fade show m-0" role="alert">
    <i class="bi bi-exclamation-triangle me-2"></i>
    <strong>Store Preview Mode:</strong> Your store is being prepared and will be available soon. You can set up items now for when it goes live.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?> 