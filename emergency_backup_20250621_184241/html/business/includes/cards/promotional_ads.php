<?php
/**
 * Promotional Ads Analytics Card
 * Digital advertising campaigns and performance metrics
 */

// Get business ID
$stmt = $pdo->prepare("SELECT b.id FROM businesses b JOIN users u ON b.id = u.business_id WHERE u.id = ?");
$stmt->execute([$_SESSION['user_id']]);
$business = $stmt->fetch();
$business_id = $business ? $business['id'] : 0;

// Initialize promotional ads data
$ads_data = ['active_ads' => 0, 'total_impressions' => 0, 'clicks' => 0, 'conversions' => 0, 'spend' => 0];

// Get promotional ads data (using correct column names)
try {
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_ads,
            SUM(total_views) as total_impressions,
            SUM(total_clicks) as total_clicks,
            COUNT(*) as total_ads
        FROM business_promotional_ads 
        WHERE business_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    $stmt->execute([$business_id]);
    $ads_result = $stmt->fetch();
    
    $ads_data = [
        'active_ads' => $ads_result['active_ads'] ?? 0,
        'total_impressions' => $ads_result['total_impressions'] ?? 0,
        'clicks' => $ads_result['total_clicks'] ?? 0,
        'conversions' => $ads_result['total_clicks'] ?? 0, // Using clicks as conversion proxy
        'spend' => 0 // This table doesn't track spend
    ];
} catch (Exception $e) {
    // Keep default values if table doesn't exist
}

// Calculate performance metrics
$ctr = $ads_data['total_impressions'] > 0 ? ($ads_data['clicks'] / $ads_data['total_impressions']) * 100 : 0;
$conversion_rate = $ads_data['clicks'] > 0 ? ($ads_data['conversions'] / $ads_data['clicks']) * 100 : 0;
$cost_per_click = $ads_data['clicks'] > 0 ? $ads_data['spend'] / $ads_data['clicks'] : 0;

// Get top performing ads
try {
    $stmt = $pdo->prepare("
        SELECT 
            ad_title,
            total_views as impressions,
            total_clicks as clicks,
            total_clicks as conversions,
            0 as spend_amount,
            (total_clicks / NULLIF(total_views, 0)) * 100 as ctr_rate
        FROM business_promotional_ads 
        WHERE business_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ORDER BY total_clicks DESC, total_views DESC
        LIMIT 5
    ");
    $stmt->execute([$business_id]);
    $top_ads = $stmt->fetchAll();
} catch (Exception $e) {
    $top_ads = [];
}
?>

<div class="card dashboard-card h-100" data-metric="promotional-ads">
    <div class="card-header bg-transparent border-0">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">
                <i class="bi bi-megaphone text-info me-2"></i>Promotional Ads
            </h5>
            <div class="d-flex align-items-center gap-1">
                <?php if ($ads_data['active_ads'] > 0): ?>
                    <span class="badge bg-info" title="Active Digital Ads">A</span>
                <?php endif; ?>
                <span class="badge bg-<?php echo $ads_data['active_ads'] > 0 ? 'success' : 'secondary'; ?>">
                    <?php echo $ads_data['active_ads'] > 0 ? 'Live' : 'Paused'; ?>
                </span>
            </div>
        </div>
    </div>
    
    <div class="card-body">
        <div class="text-center mb-3">
            <div class="card-metric"><?php echo number_format($ads_data['active_ads']); ?></div>
            <div class="small text-muted">Active campaigns</div>
        </div>
        
        <div class="row text-center mb-3">
            <div class="col-4">
                <div class="small text-muted">Impressions</div>
                <div class="fw-bold text-primary"><?php echo number_format($ads_data['total_impressions']); ?></div>
            </div>
            <div class="col-4">
                <div class="small text-muted">Clicks</div>
                <div class="fw-bold text-success"><?php echo number_format($ads_data['clicks']); ?></div>
            </div>
            <div class="col-4">
                <div class="small text-muted">CTR</div>
                <div class="fw-bold text-warning"><?php echo number_format($ctr, 1); ?>%</div>
            </div>
        </div>
        
        <!-- Performance Metrics -->
        <div class="mb-3">
            <div class="d-flex justify-content-between small text-muted mb-1">
                <span>Performance Overview</span>
                <span>Last 7 days</span>
            </div>
            
            <div class="row text-center">
                <div class="col-6">
                    <small class="text-muted">Conversions</small>
                    <div class="small fw-bold text-success"><?php echo $ads_data['conversions']; ?></div>
                </div>
                <div class="col-6">
                    <small class="text-muted">Conv Rate</small>
                    <div class="small fw-bold text-warning"><?php echo number_format($conversion_rate, 1); ?>%</div>
                </div>
            </div>
        </div>
        
        <?php if ($ads_data['total_impressions'] > 0): ?>
        <div class="mt-3">
            <div class="progress mb-2" style="height: 8px;">
                <div class="progress-bar bg-info" role="progressbar" style="width: <?php echo min(100, $ctr * 10); ?>%" title="Click-through Rate: <?php echo number_format($ctr, 1); ?>%"></div>
            </div>
            <small class="text-muted">CTR: <?php echo number_format($ctr, 1); ?>% | Conv: <?php echo number_format($conversion_rate, 1); ?>%</small>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="card-footer bg-transparent border-0">
        <div class="d-flex gap-2">
            <button class="btn btn-outline-info btn-sm flex-fill" data-bs-toggle="modal" data-bs-target="#promotionalAdsModal">
                <i class="bi bi-graph-up me-1"></i>Details
            </button>
            <a href="<?php echo APP_URL; ?>/business/promotional-ads.php" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-megaphone me-1"></i>Manage
            </a>
        </div>
    </div>
</div>

<!-- Promotional Ads Modal -->
<div class="modal fade" id="promotionalAdsModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-megaphone text-info me-2"></i>Promotional Ads Performance (Last 7 Days)
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-4 mb-4">
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body text-center">
                                <h3 class="text-info"><?php echo number_format($ads_data['total_impressions']); ?></h3>
                                <p class="text-muted mb-0">Total Impressions</p>
                                <small class="text-muted">Ad views</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body text-center">
                                <h3 class="text-success"><?php echo number_format($ads_data['clicks']); ?></h3>
                                <p class="text-muted mb-0">Total Clicks</p>
                                <small class="text-muted"><?php echo number_format($ctr, 1); ?>% CTR</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body text-center">
                                <h3 class="text-warning"><?php echo $ads_data['conversions']; ?></h3>
                                <p class="text-muted mb-0">Conversions</p>
                                <small class="text-muted"><?php echo number_format($conversion_rate, 1); ?>% rate</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body text-center">
                                <h3 class="text-info"><?php echo $ads_data['active_ads']; ?></h3>
                                <p class="text-muted mb-0">Active Campaigns</p>
                                <small class="text-muted">Live advertising</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php if (!empty($top_ads)): ?>
                <h6>Top Performing Ads</h6>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Ad Title</th>
                                <th>Impressions</th>
                                <th>Clicks</th>
                                <th>CTR</th>
                                <th>Conversions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($top_ads as $ad): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($ad['ad_title']); ?></td>
                                <td><?php echo number_format($ad['impressions']); ?></td>
                                <td><?php echo number_format($ad['clicks']); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $ad['ctr_rate'] >= 2 ? 'success' : ($ad['ctr_rate'] >= 1 ? 'warning' : 'danger'); ?>">
                                        <?php echo number_format($ad['ctr_rate'], 1); ?>%
                                    </span>
                                </td>
                                <td><?php echo $ad['conversions']; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center py-4">
                    <i class="bi bi-megaphone display-3 text-muted"></i>
                    <h6 class="mt-3">No Promotional Ads Yet</h6>
                    <p class="text-muted">Create your first digital advertising campaign</p>
                    <a href="<?php echo APP_URL; ?>/business/promotional-ads.php" class="btn btn-info">
                        <i class="bi bi-plus-circle me-1"></i>Create Ad Campaign
                    </a>
                </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <a href="<?php echo APP_URL; ?>/business/promotional-ads.php" class="btn btn-info">
                    <i class="bi bi-megaphone me-1"></i>Manage Ads
                </a>
                <a href="<?php echo APP_URL; ?>/business/analytics/ads-performance.php" class="btn btn-primary">
                    <i class="bi bi-graph-up me-1"></i>Full Analytics
                </a>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div> 