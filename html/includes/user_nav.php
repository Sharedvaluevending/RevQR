<?php
// Get current page for navigation highlighting
$current_page = basename($_SERVER['PHP_SELF']);
$user_id = $_SESSION['user_id'] ?? null;

// Check if stores are enabled
require_once __DIR__ . '/../core/config_manager.php';
require_once __DIR__ . '/../core/qr_coin_manager.php';
$business_store_enabled = ConfigManager::get('business_store_enabled', false);
$qr_store_enabled = ConfigManager::get('qr_store_enabled', false);

// Get user QR coin balance
$qr_balance = $user_id ? QRCoinManager::getBalance($user_id) : 0;
?>

<nav class="navbar navbar-expand-lg navbar-dark bg-success">
    <div class="container-fluid">
        <a class="navbar-brand" href="/user/dashboard.php">
            <i class="bi bi-person-check me-2"></i>RevenueQR
        </a>
        
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#userNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="userNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'dashboard.php' ? 'active' : ''; ?>" 
                       href="/user/dashboard.php">
                        <i class="bi bi-house-heart me-1"></i>Home
                    </a>
                </li>
                
                <!-- Play Menu (Entertainment Hub) -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?php echo in_array($current_page, ['voting.php', 'spin-wheel.php']) ? 'active' : ''; ?>" 
                       href="#" id="playDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-controller me-1"></i>Play
                    </a>
                    <ul class="dropdown-menu">
                        <li>
                            <a class="dropdown-item <?php echo $current_page === 'index.php' && strpos($_SERVER['REQUEST_URI'], '/casino/') !== false ? 'active' : ''; ?>" 
                               href="/casino/index.php">
                                <i class="bi bi-suit-diamond me-2"></i>Casino
                                <span class="badge bg-danger ms-1">Slots</span>
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="/horse-racing/quick-races.php">
                                <i class="bi bi-speedometer2 me-2"></i>Horse Racing
                                <span class="badge bg-warning ms-1">Quick Races</span>
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item <?php echo $current_page === 'voting.php' ? 'active' : ''; ?>" 
                       href="/user/voting.php">
                                <i class="bi bi-hand-thumbs-up me-2"></i>Vote
                    </a>
                </li>
                        <li>
                            <a class="dropdown-item <?php echo $current_page === 'spin-wheel.php' ? 'active' : ''; ?>" 
                       href="/user/spin-wheel.php">
                                <i class="bi bi-arrow-clockwise me-2"></i>Spin Wheel
                    </a>
                        </li>
                    </ul>
                </li>
                
                <!-- Shop Menu (Simplified) -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?php echo $current_page === 'business-stores.php' ? 'active' : ''; ?>" 
                       href="#" id="shopDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-shop me-1"></i>Shop
                    </a>
                    <ul class="dropdown-menu">
                        <li>
                            <a class="dropdown-item" href="/user/qr-transactions.php">
                                <i class="bi bi-wallet2 me-2"></i>My Wallet
                                <span class="badge bg-primary ms-1"><?php echo number_format($qr_balance); ?></span>
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item <?php echo $current_page === 'business-stores.php' ? 'active' : ''; ?>" 
                               href="/user/business-stores.php">
                                <i class="bi bi-percent me-2"></i>Local Deals
                                <span class="badge bg-success ms-1">Available</span>
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li class="dropdown-header">Coming Soon</li>
                        <li>
                            <span class="dropdown-item-text text-muted">
                                <i class="bi bi-gem me-2"></i>Rewards Store
                                <span class="badge bg-secondary ms-1">Soon</span>
                            </span>
                        </li>
                    </ul>
                </li>
                
                <!-- Compete Menu -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?php echo in_array($current_page, ['leaderboard.php', 'achievements.php']) ? 'active' : ''; ?>" 
                       href="#" id="competeDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-trophy me-1"></i>Compete
                    </a>
                    <ul class="dropdown-menu">
                        <li>
                            <a class="dropdown-item <?php echo $current_page === 'leaderboard.php' ? 'active' : ''; ?>" 
                       href="/user/leaderboard.php">
                                <i class="bi bi-bar-chart me-2"></i>Leaderboard
                    </a>
                </li>
                        <li>
                            <a class="dropdown-item <?php echo $current_page === 'achievements.php' ? 'active' : ''; ?>" 
                       href="/user/achievements.php">
                                <i class="bi bi-award me-2"></i>Achievements
                    </a>
                        </li>
                    </ul>
                </li>
            </ul>
            
            <ul class="navbar-nav">
                <!-- Notifications -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="notificationDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-bell me-1"></i>
                        <span class="badge bg-danger">3</span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><h6 class="dropdown-header">Notifications</h6></li>
                        <li>
                            <a class="dropdown-item" href="#">
                                <i class="bi bi-gift me-2"></i>Daily bonus available!
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="#">
                                <i class="bi bi-trophy me-2"></i>Achievement unlocked
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="#">
                                <i class="bi bi-star me-2"></i>New QR store items
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item" href="/user/notifications.php">
                                <i class="bi bi-list me-2"></i>View All
                            </a>
                        </li>
                    </ul>
                </li>
                
                <!-- User Account -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle me-1"></i>
                        <?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <a class="dropdown-item" href="/user/profile.php">
                                <i class="bi bi-person me-2"></i>Profile
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="/user/avatar.php">
                                <i class="bi bi-emoji-smile me-2"></i>Avatar
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="/user/settings.php">
                                <i class="bi bi-gear me-2"></i>Settings
                            </a>
                        </li>
                        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'business'): ?>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item" href="/business/dashboard.php">
                                <i class="bi bi-building me-2"></i>Business Portal
                            </a>
                        </li>
                        <?php endif; ?>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item" href="/core/logout.php">
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
<?php if ((!$business_store_enabled || !$qr_store_enabled) && in_array($current_page, ['qr-store.php', 'business-stores.php'])): ?>
<div class="alert alert-info alert-dismissible fade show m-0" role="alert">
    <i class="bi bi-info-circle me-2"></i>
    <strong>Store Preview:</strong> Our QR coin stores are launching soon! Start earning QR coins now to be ready for exclusive discounts and items.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?> 