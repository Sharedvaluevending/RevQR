<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';

$message = '';
$message_type = '';
$machine = null;
$promotions = [];
$items = [];

// Get machine details
if (isset($_GET['machine'])) {
    $machine_name = $_GET['machine'];
    
    // Get machine info (fallback if machines table doesn't exist)
    try {
        $stmt = $pdo->prepare("
            SELECT m.*, b.name as business_name
            FROM machines m
            JOIN businesses b ON m.business_id = b.id
            WHERE m.name = ? AND m.status = 'active'
        ");
        $stmt->execute([$machine_name]);
        $machine = $stmt->fetch();
    } catch (Exception $e) {
        // Fallback: create a virtual machine from business data
        $stmt = $pdo->prepare("
            SELECT DISTINCT b.id, b.name as business_name
            FROM businesses b
            JOIN promotions p ON b.id = p.business_id
            WHERE p.status = 'active'
            LIMIT 1
        ");
        $stmt->execute();
        $business = $stmt->fetch();
        
        if ($business) {
            $machine = [
                'id' => null,
                'name' => $machine_name,
                'business_id' => $business['id'],
                'business_name' => $business['business_name'],
                'location' => 'Unknown Location',
                'status' => 'active',
                'updated_at' => date('Y-m-d H:i:s')
            ];
        }
    }
    
    if ($machine) {
        // Track engagement - record that someone scanned/viewed this machine's promotions
        $user_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $view_type = isset($_GET['view']) && $_GET['view'] === 'promotions' ? 'promotion_view' : 'machine_view';
        
        try {
            // Insert engagement tracking
            $stmt = $pdo->prepare("
                INSERT INTO machine_engagement (
                    machine_id, business_id, view_type, user_ip, user_agent, created_at
                ) VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$machine['id'], $machine['business_id'], $view_type, $user_ip, $user_agent]);
        } catch (Exception $e) {
            // If table doesn't exist, try to create it
            try {
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS machine_engagement (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        machine_id INT NULL,
                        business_id INT NOT NULL,
                        view_type ENUM('machine_view', 'promotion_view') NOT NULL,
                        user_ip VARCHAR(45),
                        user_agent TEXT,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        INDEX idx_machine_engagement_machine (machine_id),
                        INDEX idx_machine_engagement_business (business_id),
                        INDEX idx_machine_engagement_type (view_type),
                        INDEX idx_machine_engagement_date (created_at)
                    )
                ");
                // Try again
                $stmt = $pdo->prepare("
                    INSERT INTO machine_engagement (
                        machine_id, business_id, view_type, user_ip, user_agent, created_at
                    ) VALUES (?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$machine['id'], $machine['business_id'], $view_type, $user_ip, $user_agent]);
            } catch (Exception $e2) {
                // Silently fail tracking if we can't create the table
                error_log("Machine engagement tracking failed: " . $e2->getMessage());
            }
        }
        
        // Get current promotions and combo deals
        $promotions = [];
        
        // Get regular promotions
        $stmt = $pdo->prepare("
            SELECT p.*, vli.item_name, vli.retail_price, vl.name as list_name, 'regular' as promo_type
            FROM promotions p
            JOIN voting_list_items vli ON p.item_id = vli.id
            JOIN voting_lists vl ON p.list_id = vl.id
            WHERE p.business_id = ? 
            AND p.status = 'active'
            AND CURDATE() BETWEEN p.start_date AND p.end_date
            ORDER BY p.created_at DESC
        ");
        $stmt->execute([$machine['business_id']]);
        $regular_promotions = $stmt->fetchAll();
        
        // Add regular promotions to the array
        foreach ($regular_promotions as $promo) {
            $promotions[] = $promo;
        }
        
        // Get combo deals (if table exists)
        try {
            $stmt = $pdo->prepare("
                SELECT cd.*, 'combo' as promo_type, cd.name as item_name, 
                       cd.combo_price as retail_price, NULL as list_name,
                       GROUP_CONCAT(CONCAT(vli.item_name, ' (', cdi.quantity, ')') SEPARATOR ', ') as combo_items
                FROM combo_deals cd
                LEFT JOIN combo_deal_items cdi ON cd.id = cdi.combo_id
                LEFT JOIN voting_list_items vli ON cdi.item_id = vli.id
                WHERE cd.business_id = ? 
                AND cd.status = 'active'
                AND CURDATE() BETWEEN cd.start_date AND cd.end_date
                GROUP BY cd.id
                ORDER BY cd.created_at DESC
            ");
            $stmt->execute([$machine['business_id']]);
            $combo_deals = $stmt->fetchAll();
            
            // Add combo deals to the array
            foreach ($combo_deals as $combo) {
                $promotions[] = $combo;
            }
        } catch (Exception $e) {
            // Combo deals table doesn't exist, skip
        }
        
        // Get machine items if not showing promotions only
        if (!isset($_GET['view']) || $_GET['view'] !== 'promotions') {
            try {
                $stmt = $pdo->prepare("
                    SELECT vli.*, vl.name as list_name
                    FROM voting_list_items vli
                    JOIN voting_lists vl ON vli.list_id = vl.id
                    WHERE vl.business_id = ?
                    AND vli.status = 'active'
                    ORDER BY vl.name, vli.item_name
                ");
                $stmt->execute([$machine['business_id']]);
                $items = $stmt->fetchAll();
            } catch (Exception $e) {
                $items = [];
            }
        }
    }
}

// Check if we should show only promotions
$show_only_promotions = isset($_GET['view']) && $_GET['view'] === 'promotions';

// Auto-login functionality for returning users
require_once __DIR__ . '/../core/auto_login.php';

require_once __DIR__ . '/../core/includes/header.php';
?>

<!-- Login Prompt for Non-Logged In Users -->
<?php if (!is_logged_in()): ?>
    <div class="alert alert-info alert-dismissible fade show m-3" role="alert">
        <div class="d-flex align-items-center">
            <i class="bi bi-info-circle me-2"></i>
            <div class="flex-grow-1">
                <strong>New to RevenueQR?</strong> 
                <a href="<?php echo APP_URL; ?>/html/register.php" class="alert-link">Register now</a> 
                or <a href="<?php echo APP_URL; ?>/html/login.php" class="alert-link">login</a> 
                to track your engagement, earn QR coins, and access exclusive features!
            </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="promotions-page-wrapper">
<html lang="en" class="promotions-page d-none">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Revenue QR - Promotions & Deals</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        /* MODERN DARK THEME MATCHING USER DASHBOARD */
        html.promotions-page, body.promotions-page {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 25%, #34495e 75%, #2c3e50 100%) !important;
            background-attachment: fixed !important;
            color: #ffffff !important;
            min-height: 100vh !important;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
            overflow-x: hidden !important;
            margin: 0;
            padding: 0;
            /* Prevent overscroll bounce that can cause white flash */
            overscroll-behavior: none !important;
            -webkit-overflow-scrolling: touch !important;
        }

        /* Fix mobile background issues - remove fixed attachment on mobile */
        @media (max-width: 768px) {
            html.promotions-page, body.promotions-page {
                background-attachment: scroll !important;
                /* Ensure background covers overscroll areas */
                background-size: 100% 120vh !important;
                background-repeat: no-repeat !important;
                /* Extended background for overscroll */
                background-position: center top !important;
            }
            
            /* Prevent mobile browser white flash on overscroll */
            html.promotions-page {
                overscroll-behavior-y: none !important;
                -webkit-overflow-scrolling: touch !important;
                /* Ensure full coverage */
                min-height: 120vh !important;
            }
            
            body.promotions-page {
                /* Additional mobile background coverage */
                background: linear-gradient(135deg, #1e3c72 0%, #2a5298 25%, #34495e 75%, #2c3e50 100%) !important;
                min-height: 120vh !important;
                position: relative !important;
            }
            
            /* Create a pseudo-element for extended background on very small screens */
            body.promotions-page::before {
                content: '';
                position: fixed;
                top: -20vh;
                left: 0;
                right: 0;
                bottom: -20vh;
                background: linear-gradient(135deg, #1e3c72 0%, #2a5298 25%, #34495e 75%, #2c3e50 100%);
                z-index: -1;
                pointer-events: none;
            }
        }

        /* Additional fix for very small screens and potential overscroll */
        @media (max-width: 576px) {
            html.promotions-page, body.promotions-page {
                /* Ensure no white shows during overscroll */
                background-size: 100% 150vh !important;
                min-height: 150vh !important;
            }
            
            body.promotions-page::before {
                top: -30vh;
                bottom: -30vh;
            }
        }

        /* Full Width Container Styling */
        .promotions-page .container-fluid {
            padding: 0 2rem !important;
            max-width: 100% !important;
        }

        /* FIX: Desktop mode on mobile - ensure natural Bootstrap behavior */
        @media (min-width: 768px) and (max-width: 1024px) {
            .promotions-page .container-fluid {
                max-width: 1200px !important;
                width: 100% !important;
                margin: 0 auto !important;
                padding-left: 15px !important;
                padding-right: 15px !important;
            }
            
            /* Ensure Bootstrap grid behaves naturally */
            .row {
                width: 100% !important;
                margin-left: -15px !important;
                margin-right: -15px !important;
                display: flex !important;
                flex-wrap: wrap !important;
            }
            
            [class*="col-"] {
                position: relative !important;
                width: 100% !important;
                padding-left: 15px !important;
                padding-right: 15px !important;
            }
            
            .col-md-6 { 
                flex: 0 0 50% !important; 
                max-width: 50% !important;
            }
            
            .card {
                width: 100% !important;
                margin-bottom: 1rem !important;
            }
        }

        .promotions-page .navbar,
        .promotions-page .navbar-nav,
        .promotions-page .nav-link,
        .promotions-page .footer {
            display: none !important;
        }

        .promotions-page main {
            padding: 2rem 0 !important;
            margin: 0 !important;
            width: 100% !important;
            min-height: 100vh !important;
        }

        /* Glass morphism cards matching user dashboard */
        .card {
            background: rgba(255, 255, 255, 0.12) !important;
            backdrop-filter: blur(20px) !important;
            border: 1px solid rgba(255, 255, 255, 0.15) !important;
            border-radius: 16px !important;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3) !important;
            transition: all 0.3s ease !important;
            margin: 0 auto 2rem auto;
            max-width: 1400px;
        }

        .card:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.4) !important;
            border: 1px solid rgba(255, 255, 255, 0.25) !important;
        }

        /* Machine info card - responsive container */
        .machine-info-card {
            background: linear-gradient(135deg, rgba(100, 181, 246, 0.15) 0%, rgba(255, 255, 255, 0.05) 100%) !important;
            border: 1px solid rgba(100, 181, 246, 0.3) !important;
            border-radius: 16px !important;
            padding: 2rem;
            margin: 0 auto 2rem auto;
            max-width: 1400px;
            text-align: center;
        }

        @media (max-width: 768px) {
            .machine-info-card {
                padding: 1.5rem;
                margin: 0 1rem 2rem 1rem;
            }
            
            .card {
                margin: 0 1rem 2rem 1rem;
            }
        }

        /* Header section with QR logo and avatars */
        .header-section {
            text-align: center;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }

        .qr-logo {
            width: 120px;
            height: 120px;
            margin-bottom: 1rem;
            border-radius: 50%;
            box-shadow: 0 8px 32px rgba(100, 181, 246, 0.3);
            border: 3px solid rgba(255, 255, 255, 0.2);
        }

        .avatars-section {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin: 1rem 0;
        }

        .avatar-img {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            border: 2px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.3);
            transition: all 0.3s ease;
        }

        .avatar-img:hover {
            transform: scale(1.1);
            border-color: #64b5f6;
            box-shadow: 0 8px 32px rgba(100, 181, 246, 0.4);
        }

        .hero-title {
            font-size: 3rem;
            font-weight: 700;
            background: linear-gradient(135deg, #64b5f6 0%, #ffffff 50%, #64b5f6 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 1rem;
        }

        .hero-subtitle {
            color: rgba(255, 255, 255, 0.8);
            font-size: 1.2rem;
            margin-bottom: 2rem;
        }

        /* Promotion cards */
        .promotion-card {
            background: rgba(255, 255, 255, 0.08) !important;
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
            border-radius: 12px !important;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            transition: all 0.3s ease !important;
            position: relative;
            overflow: hidden;
        }

        .promotion-card:hover {
            background: rgba(255, 255, 255, 0.15) !important;
            border-color: rgba(255, 255, 255, 0.25) !important;
            transform: translateY(-2px);
        }

        .promotion-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(135deg, #ff9800 0%, #f57c00 100%);
        }

        .combo-card::before {
            background: linear-gradient(135deg, #4caf50 0%, #388e3c 100%);
        }

        .promotion-badge {
            background: linear-gradient(135deg, #ff9800 0%, #f57c00 100%) !important;
            color: white !important;
            border: none !important;
            border-radius: 20px !important;
            padding: 0.5rem 1rem !important;
            font-weight: 600 !important;
        }

        .combo-badge {
            background: linear-gradient(135deg, #4caf50 0%, #388e3c 100%) !important;
            color: white !important;
            border: none !important;
            border-radius: 20px !important;
            padding: 0.5rem 1rem !important;
            font-weight: 600 !important;
        }

        .price-original {
            text-decoration: line-through;
            color: rgba(255, 255, 255, 0.6) !important;
            font-size: 0.9rem;
        }

        .price-sale {
            color: #4caf50 !important;
            font-weight: 700 !important;
            font-size: 1.2rem;
        }

        .savings-badge {
            background: linear-gradient(135deg, #4caf50 0%, #66bb6a 100%) !important;
            color: white !important;
            font-weight: 600 !important;
            border-radius: 12px !important;
            padding: 0.25rem 0.75rem !important;
        }

        /* Alert styling */
        .alert {
            background: rgba(255, 255, 255, 0.1) !important;
            border: 1px solid rgba(255, 255, 255, 0.2) !important;
            border-radius: 12px !important;
            color: #ffffff !important;
        }

        .alert-success {
            background: rgba(76, 175, 80, 0.2) !important;
            border-color: rgba(76, 175, 80, 0.3) !important;
        }

        .alert-warning {
            background: rgba(255, 152, 0, 0.2) !important;
            border-color: rgba(255, 152, 0, 0.3) !important;
        }

        .alert-danger {
            background: rgba(244, 67, 54, 0.2) !important;
            border-color: rgba(244, 67, 54, 0.3) !important;
        }

        /* Text colors matching dashboard */
        .text-primary { color: #64b5f6 !important; }
        .text-success { color: #4caf50 !important; }
        .text-warning { color: #ff9800 !important; }
        .text-info { color: #00bcd4 !important; }
        .text-muted { color: rgba(255, 255, 255, 0.75) !important; }

        @media (max-width: 768px) {
            .promotions-page .container-fluid {
                padding: 0 1rem !important;
            }
        }

        @media (max-width: 576px) {
            .promotions-page .container-fluid {
                padding: 0 0.5rem !important;
            }
        }

        .promotions-page .navbar,
    </style>
</head>
<body class="promotions-page">
    <main>
        <div class="container-fluid">
            <!-- Header Section with QR Logo and Avatars -->
            <div class="header-section">
                <img src="../img/logoRQ.png" alt="Revenue QR Logo" class="qr-logo">
                <p class="hero-subtitle">Exclusive Promotions & Special Deals</p>
                
                <!-- Sample Avatars -->
                <div class="avatars-section">
                    <img src="../img/qrCoin.png" alt="QR Coin" class="avatar-img">
                    <img src="../img/qractivity.png" alt="QR Activity" class="avatar-img">
                    <img src="../img/SHAREDLOGOblank.png" alt="Business Logo" class="avatar-img" style="background-color: white; padding: 4px;">
                </div>
            </div>

            <?php if ($message): ?>
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                            <i class="bi bi-<?php echo $message_type === 'success' ? 'check-circle' : ($message_type === 'warning' ? 'exclamation-triangle' : 'info-circle'); ?> me-2"></i>
                            <?php echo $message; ?>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($machine): ?>
                <!-- Machine Info Card -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="machine-info-card">
                            <h2 class="text-primary mb-2">
                                <i class="bi bi-shop me-2"></i>
                                <?php echo htmlspecialchars($machine['name']); ?>
                            </h2>
                            <p class="text-light mb-1"><?php echo htmlspecialchars($machine['business_name']); ?></p>
                            <?php if (!$show_only_promotions && isset($machine['location'])): ?>
                                <p class="text-muted">
                                    <i class="bi bi-geo-alt me-1"></i>
                                    <?php echo htmlspecialchars($machine['location']); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Current Promotions -->
                <?php if (!empty($promotions)): ?>
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-body p-4">
                                    <h3 class="text-warning mb-4 text-center">
                                        <i class="bi bi-tag-fill me-2"></i>
                                        Active Promotions & Deals
                                    </h3>
                                    
                                    <div class="row">
                                        <?php foreach ($promotions as $promo): ?>
                                            <div class="col-lg-6 mb-3">
                                                <div class="promotion-card <?php echo $promo['promo_type'] === 'combo' ? 'combo-card' : ''; ?>">
                                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                                        <h5 class="text-light mb-0"><?php echo htmlspecialchars($promo['item_name']); ?></h5>
                                                        <span class="badge <?php echo $promo['promo_type'] === 'combo' ? 'combo-badge' : 'promotion-badge'; ?>">
                                                            <?php echo $promo['promo_type'] === 'combo' ? 'COMBO DEAL' : 'SPECIAL OFFER'; ?>
                                                        </span>
                                                    </div>
                                                    
                                                    <?php if ($promo['promo_type'] === 'combo' && isset($promo['combo_items'])): ?>
                                                        <p class="text-muted mb-3">
                                                            <i class="bi bi-box me-1"></i>
                                                            Includes: <?php echo htmlspecialchars($promo['combo_items']); ?>
                                                        </p>
                                                    <?php endif; ?>
                                                    
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <div>
                                                            <?php if (isset($promo['retail_price']) && isset($promo['sale_price'])): ?>
                                                                <span class="price-original">$<?php echo number_format($promo['retail_price'], 2); ?></span>
                                                                <span class="price-sale ms-2">$<?php echo number_format($promo['sale_price'], 2); ?></span>
                                                            <?php elseif (isset($promo['retail_price'])): ?>
                                                                <span class="price-sale">$<?php echo number_format($promo['retail_price'], 2); ?></span>
                                                            <?php endif; ?>
                                                        </div>
                                                        
                                                        <?php if (isset($promo['sale_price']) && isset($promo['retail_price'])): ?>
                                                            <?php $savings = $promo['retail_price'] - $promo['sale_price']; ?>
                                                            <?php if ($savings > 0): ?>
                                                                <span class="savings-badge">
                                                                    Save $<?php echo number_format($savings, 2); ?>
                                                                </span>
                                                            <?php endif; ?>
                                                        <?php endif; ?>
                                                    </div>
                                                    
                                                    <?php if (isset($promo['description']) && $promo['description']): ?>
                                                        <p class="text-muted mt-2 mb-0">
                                                            <?php echo htmlspecialchars($promo['description']); ?>
                                                        </p>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Available Items (if not promotions-only view) -->
                <?php if (!$show_only_promotions && !empty($items)): ?>
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-body p-4">
                                    <h3 class="text-info mb-4 text-center">
                                        <i class="bi bi-grid-3x3-gap-fill me-2"></i>
                                        Available Items
                                    </h3>
                                    
                                    <div class="row">
                                        <?php foreach ($items as $item): ?>
                                            <div class="col-md-6 col-lg-4 mb-3">
                                                <div class="card">
                                                    <div class="card-body text-center">
                                                        <h6 class="text-light mb-2"><?php echo htmlspecialchars($item['item_name']); ?></h6>
                                                        <?php if (isset($item['retail_price'])): ?>
                                                            <p class="text-primary mb-0">$<?php echo number_format($item['retail_price'], 2); ?></p>
                                                        <?php endif; ?>
                                                        <?php if (isset($item['list_name'])): ?>
                                                            <small class="text-muted"><?php echo htmlspecialchars($item['list_name']); ?></small>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if (empty($promotions) && (empty($items) || $show_only_promotions)): ?>
                    <div class="row">
                        <div class="col-12">
                            <div class="card text-center py-5">
                                <div class="card-body">
                                    <i class="bi bi-tag display-1 text-muted mb-3"></i>
                                    <h4 class="text-muted">No Active Promotions</h4>
                                    <p class="text-muted">Check back later for special deals and promotions!</p>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="row">
                    <div class="col-12">
                        <div class="card text-center py-5">
                            <div class="card-body">
                                <i class="bi bi-shop display-1 text-primary mb-3"></i>
                                <h3 class="text-light mb-3">Welcome to Revenue QR Promotions</h3>
                                <p class="text-muted mb-4">Access this page through a vending machine QR code to see exclusive promotions and deals!</p>
                                <div class="text-muted">
                                    <i class="bi bi-info-circle me-2"></i>
                                    Scan a QR code on any participating vending machine to get started
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 