<nav class="navbar navbar-expand-lg navbar-dark bg-dark py-1 fixed-top">
    <div class="container">
        <div class="navbar-brand d-flex align-items-center">
            <img src="<?php echo APP_URL; ?>/img/logoRQ.png" alt="RevenueQR Logo" height="32" class="me-2">
            <span class="d-none d-sm-inline">Revenue QR</span>
        </div>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo APP_URL; ?>/business/dashboard.php">
                        <i class="bi bi-speedometer2 me-1"></i>Dashboard
                    </a>
                </li>
                
                <!-- Inventory & Catalog -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="inventoryDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-box me-1"></i>Inventory & Catalog
                    </a>
                    <ul class="dropdown-menu">
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
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item" href="<?php echo APP_URL; ?>/business/manual-sales.php">
                                <i class="bi bi-cash-coin me-2"></i>Manual Sales Entry
                            </a>
                        </li>
                    </ul>
                </li>
                
                <!-- Marketing & Campaigns -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="campaignDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-megaphone me-1"></i>Marketing & Campaigns
                    </a>
                    <ul class="dropdown-menu">
                        <li><h6 class="dropdown-header">Campaign Management</h6></li>
                        <li>
                            <a class="dropdown-item" href="<?php echo APP_URL; ?>/business/manage-campaigns.php">
                                <i class="bi bi-list me-2"></i>All Campaigns
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="<?php echo APP_URL; ?>/business/create-campaign.php">
                                <i class="bi bi-plus-circle me-2"></i>New Campaign
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="<?php echo APP_URL; ?>/business/campaign-analytics.php">
                                <i class="bi bi-graph-up me-2"></i>Campaign Analytics
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="<?php echo APP_URL; ?>/business/winners.php">
                                <i class="bi bi-trophy me-2"></i>Campaign Winners
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
                        <li>
                            <a class="dropdown-item" href="<?php echo APP_URL; ?>/business/view-votes.php">
                                <i class="bi bi-eye me-2"></i>View Votes
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
                            <a class="dropdown-item" href="<?php echo APP_URL; ?>/business/promotional-ads.php">
                                <i class="bi bi-megaphone-fill me-2 text-primary"></i>Promotional Ads Manager
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="<?php echo APP_URL; ?>/business/spin-wheel.php">
                                <i class="bi bi-stars me-2 text-warning"></i>Spin Wheel Engagement
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="<?php echo APP_URL; ?>/business/pizza-tracker.php">
                                <i class="bi bi-pizza me-2 text-success"></i>Pizza Tracker
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li><h6 class="dropdown-header">AI & Analytics</h6></li>
                        <li>
                            <a class="dropdown-item" href="<?php echo APP_URL; ?>/business/ai-assistant.php">
                                <i class="bi bi-robot me-2 text-primary"></i>AI Business Assistant
                            </a>
                        </li>
                    </ul>
                </li>
                
                <!-- QR Codes & Display -->
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
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item" href="<?php echo APP_URL; ?>/qr-display.php">
                                <i class="bi bi-display me-2"></i>Display Mode
                            </a>
                        </li>
                    </ul>
                </li>
            </ul>
            
            <ul class="navbar-nav">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle me-1"></i>
                        <?php echo htmlspecialchars($_SESSION['username'] ?? $_SESSION['user_name'] ?? 'User'); ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <a class="dropdown-item" href="<?php echo APP_URL; ?>/business/profile.php">
                                <i class="bi bi-gear me-2"></i>Settings
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item" href="<?php echo APP_URL; ?>/logout.php">
                                <i class="bi bi-box-arrow-right me-2"></i>Logout
                            </a>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav> 