<?php
/**
 * Admin Panel Header Include
 * RevenueQR Platform - Admin Interface
 */

// Include core dependencies
require_once __DIR__ . '/../../core/config.php';
require_once __DIR__ . '/../../core/session.php';
require_once __DIR__ . '/../../core/auth.php';

// Ensure admin access
if (!is_logged_in() || !has_role('admin')) {
    header('Location: ' . APP_URL . '/login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?>RevenueQR Admin</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- Custom Admin CSS -->
    <style>
        :root {
            --admin-primary: #0d6efd;
            --admin-secondary: #6c757d;
            --admin-success: #198754;
            --admin-danger: #dc3545;
            --admin-warning: #ffc107;
            --admin-info: #0dcaf0;
        }
        
        body {
            padding-top: 70px;
            background-color: #f8f9fa;
        }
        
        .admin-navbar {
            background: linear-gradient(135deg, var(--admin-primary), #084298);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .admin-sidebar {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .admin-card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.05);
            transition: transform 0.2s ease;
        }
        
        .admin-card:hover {
            transform: translateY(-2px);
        }
        
        .stat-card {
            background: linear-gradient(135deg, #fff, #f8f9fa);
            border-left: 4px solid var(--admin-primary);
        }
        
        .admin-btn {
            border-radius: 8px;
            padding: 0.5rem 1rem;
            font-weight: 500;
        }
        
        .admin-nav-pills .nav-link {
            border-radius: 8px;
            margin: 2px;
            transition: all 0.2s ease;
        }
        
        .admin-nav-pills .nav-link.active {
            background: var(--admin-primary);
            box-shadow: 0 4px 15px rgba(13, 110, 253, 0.3);
        }
        
        .breadcrumb-admin {
            background: transparent;
            padding: 0;
        }
        
        .breadcrumb-admin .breadcrumb-item + .breadcrumb-item::before {
            content: "›";
            color: var(--admin-secondary);
        }
        
        @media (max-width: 768px) {
            body {
                padding-top: 60px;
            }
            
            .admin-card {
                margin-bottom: 1rem;
            }
        }
    </style>
    
    <?php if (isset($additional_head_content)) echo $additional_head_content; ?>
</head>
<body>
    <!-- Admin Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark admin-navbar fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand d-flex align-items-center" href="<?php echo APP_URL; ?>/admin/dashboard_modular.php">
                <i class="bi bi-shield-check me-2"></i>
                <span class="d-none d-sm-inline">RevenueQR Admin</span>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNavbar">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="adminNavbar">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo APP_URL; ?>/admin/dashboard_modular.php">
                            <i class="bi bi-speedometer2 me-1"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo APP_URL; ?>/admin/manage-users.php">
                            <i class="bi bi-people me-1"></i>Users
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo APP_URL; ?>/admin/manage-businesses.php">
                            <i class="bi bi-building me-1"></i>Businesses
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo APP_URL; ?>/admin/reports.php">
                            <i class="bi bi-graph-up me-1"></i>Reports
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo APP_URL; ?>/admin/system-monitor.php">
                            <i class="bi bi-cpu me-1"></i>Monitor
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="toolsDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-tools me-1"></i>Tools
                        </a>
                        <ul class="dropdown-menu">
                            <li>
                                <a class="dropdown-item" href="<?php echo APP_URL; ?>/admin/qr-scanner.php">
                                    <i class="bi bi-qr-code-scan me-2"></i>QR Scanner
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="<?php echo APP_URL; ?>/qr_manager.php">
                                    <i class="bi bi-qr-code me-2"></i>QR Manager
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item" href="<?php echo APP_URL; ?>/qr-generator.php">
                                    <i class="bi bi-plus-circle me-2"></i>QR Generator
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item" href="<?php echo APP_URL; ?>/admin/system-health.php">
                                    <i class="bi bi-heart-fill me-2"></i>System Health
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="nayaxDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-credit-card me-1"></i>Nayax
                        </a>
                        <ul class="dropdown-menu">
                            <li>
                                <a class="dropdown-item" href="<?php echo APP_URL; ?>/admin/nayax-overview.php">
                                    <i class="bi bi-graph-up me-2"></i>System Overview
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="<?php echo APP_URL; ?>/admin/nayax-machines.php">
                                    <i class="bi bi-hdd-stack me-2"></i>Machine Management
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="<?php echo APP_URL; ?>/admin/nayax-transactions.php">
                                    <i class="bi bi-receipt me-2"></i>Transactions
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item" href="<?php echo APP_URL; ?>/verify_nayax_phase4.php" target="_blank">
                                    <i class="bi bi-check-circle me-2"></i>System Status
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="horseRacingDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-trophy me-1"></i>Horse Racing
                        </a>
                        <ul class="dropdown-menu">
                            <li>
                                <a class="dropdown-item" href="<?php echo APP_URL; ?>/admin/horse-racing/index.php">
                                    <i class="bi bi-speedometer2 me-2"></i>Control Center
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="<?php echo APP_URL; ?>/admin/horse-racing/system-settings.php">
                                    <i class="bi bi-gear me-2"></i>System Settings
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="<?php echo APP_URL; ?>/admin/horse-racing/jockey-management.php">
                                    <i class="bi bi-person-badge me-2"></i>Jockey Management
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="<?php echo APP_URL; ?>/admin/horse-racing/emergency-controls.php">
                                    <i class="bi bi-exclamation-triangle me-2"></i>Emergency Controls
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item" href="<?php echo APP_URL; ?>/horse-racing/index.php" target="_blank">
                                    <i class="bi bi-eye me-2"></i>View Live Races
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="casinoDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-dice-5 me-1"></i>Casino
                        </a>
                        <ul class="dropdown-menu">
                            <li>
                                <a class="dropdown-item" href="<?php echo APP_URL; ?>/admin/casino-management.php">
                                    <i class="bi bi-gear me-2"></i>Casino Management
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="<?php echo APP_URL; ?>/admin/store-analytics.php">
                                    <i class="bi bi-graph-up me-2"></i>Store Analytics
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="<?php echo APP_URL; ?>/admin/manage-qr-store.php">
                                    <i class="bi bi-shop me-2"></i>Manage QR Store
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="<?php echo APP_URL; ?>/admin/manage-business-store.php">
                                    <i class="bi bi-building me-2"></i>Manage Business Store
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item" href="<?php echo APP_URL; ?>/casino/index.php" target="_blank">
                                    <i class="bi bi-eye me-2"></i>View Casino
                                </a>
                            </li>
                        </ul>
                    </li>
                </ul>
                
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="adminUserDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle me-1"></i>
                            <span class="d-none d-sm-inline"><?php echo $_SESSION['username'] ?? 'Admin'; ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                                <a class="dropdown-item" href="<?php echo APP_URL; ?>/admin/settings.php">
                                    <i class="bi bi-gear me-2"></i>Settings
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item" href="<?php echo APP_URL; ?>/business/dashboard.php">
                                    <i class="bi bi-building me-2"></i>Business View
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="<?php echo APP_URL; ?>/user/dashboard.php">
                                    <i class="bi bi-person me-2"></i>User View
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item text-danger" href="<?php echo APP_URL; ?>/logout.php">
                                    <i class="bi bi-box-arrow-right me-2"></i>Logout
                                </a>
                            </li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    
    <!-- Admin Content Container -->
    <div class="container-fluid mt-3">
        <?php if (isset($show_breadcrumb) && $show_breadcrumb): ?>
        <nav aria-label="Admin breadcrumb">
            <ol class="breadcrumb breadcrumb-admin">
                <li class="breadcrumb-item">
                    <a href="<?php echo APP_URL; ?>/admin/dashboard_modular.php">
                        <i class="bi bi-house me-1"></i>Admin
                    </a>
                </li>
                <?php if (isset($breadcrumb_items) && is_array($breadcrumb_items)): ?>
                    <?php foreach ($breadcrumb_items as $item): ?>
                        <?php if (isset($item['url'])): ?>
                            <li class="breadcrumb-item">
                                <a href="<?php echo $item['url']; ?>"><?php echo $item['name']; ?></a>
                            </li>
                        <?php else: ?>
                            <li class="breadcrumb-item active"><?php echo $item['name']; ?></li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ol>
        </nav>
        <?php endif; ?>
    </div>
</body>
</html> 