<?php
require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/core/session.php';

// Test navigation links from the main business portal navbar
$navigation_links = [
    'Dashboard' => '/business/dashboard.php',
    
    // Inventory & Catalog
    'My Catalog' => '/business/my-catalog.php',
    'Master Items' => '/business/master-items.php',
    'Stock Management' => '/business/stock-management.php',
    'Manual Sales Entry' => '/business/manual-sales.php',
    
    // Marketing & Campaigns
    'All Campaigns' => '/business/manage-campaigns.php',
    'Create Campaign' => '/business/create-campaign.php',
    'Campaign Analytics' => '/business/campaign-analytics.php',
    'Winners' => '/business/winners.php',
    'List Maker' => '/business/list-maker.php',
    'Edit Items' => '/business/items.php',
    'Manage Lists' => '/business/manage-lists.php',
    'View Votes' => '/business/view-votes.php',
    'Promotions' => '/business/promotions.php',
    'Spin Wheel' => '/business/spin-wheel.php',
    'Pizza Tracker' => '/business/pizza-tracker.php',
    'AI Assistant' => '/business/ai-assistant.php',
    
    // QR Codes
    'QR Manager' => '/qr_manager.php',
    'Quick Generator' => '/qr-generator.php',
    'Enhanced Generator' => '/qr-generator-enhanced.php',
    'Display Mode' => '/qr-display.php',
    
    // Analytics
    'Sales Analytics' => '/business/analytics/sales.php',
    'Campaign Performance' => '/business/view-results.php',
    'Pizza Analytics' => '/business/pizza-analytics.php',
    'Business Reports' => '/business/reports.php',
    
    // Settings
    'Business Profile' => '/business/profile.php',
    'Notifications' => '/business/notification-settings.php',
    'User Settings' => '/business/user-settings.php'
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Navigation Link Test - RevenueQR</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-4">
        <div class="row">
            <div class="col-12">
                <h1 class="mb-4">🔗 Navigation Link Test</h1>
                <p class="text-muted mb-4">Testing all navigation links from the main business portal to see which ones work...</p>
                
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Navigation Link Status</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Page Name</th>
                                        <th>Link</th>
                                        <th>File Exists</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($navigation_links as $name => $link): ?>
                                        <?php
                                        $file_path = __DIR__ . $link;
                                        $file_exists = file_exists($file_path);
                                        $full_url = APP_URL . $link;
                                        ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($name); ?></strong></td>
                                            <td><code><?php echo htmlspecialchars($link); ?></code></td>
                                            <td>
                                                <?php if ($file_exists): ?>
                                                    <span class="badge bg-success">✅ Exists</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">❌ Missing</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($file_exists): ?>
                                                    <span class="text-success">Working</span>
                                                <?php else: ?>
                                                    <span class="text-danger">Broken Link</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($file_exists): ?>
                                                    <a href="<?php echo $full_url; ?>" class="btn btn-sm btn-outline-primary" target="_blank">Test</a>
                                                <?php else: ?>
                                                    <span class="text-muted">Cannot test</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <div class="card mt-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">Summary</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        $total_links = count($navigation_links);
                        $working_links = 0;
                        $broken_links = [];
                        
                        foreach ($navigation_links as $name => $link) {
                            $file_path = __DIR__ . $link;
                            if (file_exists($file_path)) {
                                $working_links++;
                            } else {
                                $broken_links[] = $name;
                            }
                        }
                        
                        $working_percentage = round(($working_links / $total_links) * 100, 1);
                        ?>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Link Status:</h6>
                                <ul>
                                    <li><strong>Total Links:</strong> <?php echo $total_links; ?></li>
                                    <li><strong>Working:</strong> <?php echo $working_links; ?> (<?php echo $working_percentage; ?>%)</li>
                                    <li><strong>Broken:</strong> <?php echo count($broken_links); ?></li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <?php if (!empty($broken_links)): ?>
                                    <h6>Broken Links:</h6>
                                    <ul class="text-danger">
                                        <?php foreach ($broken_links as $broken): ?>
                                            <li><?php echo htmlspecialchars($broken); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <div class="alert alert-success">
                                        <strong>✅ All navigation links are working!</strong>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="mt-4 text-center">
                    <a href="<?php echo APP_URL; ?>/business/dashboard.php" class="btn btn-primary">← Back to Dashboard</a>
                    <button onclick="window.location.reload()" class="btn btn-secondary">🔄 Refresh Test</button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 