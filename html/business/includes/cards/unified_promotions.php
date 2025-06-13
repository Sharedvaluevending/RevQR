<?php
// Get business ID
$stmt = $pdo->prepare("SELECT b.id FROM businesses b JOIN users u ON b.id = u.business_id WHERE u.id = ?");
$stmt->execute([$_SESSION['user_id']]);
$business = $stmt->fetch();
$business_id = $business ? $business['id'] : 0;

// Initialize data arrays
$traditional_promos = ['active' => 0, 'total_reach' => 0, 'engagement' => 0, 'roi' => 0];
$digital_ads = ['active' => 0, 'impressions' => 0, 'clicks' => 0, 'spend' => 0];

// Get traditional promotions data (using same logic as promotions.php)
try {
    // Get promotion statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_promotions,
            COUNT(CASE WHEN status = 'active' THEN 1 END) as active_promotions
        FROM promotions 
        WHERE business_id = ?
    ");
    $stmt->execute([$business_id]);
    $stats = $stmt->fetch();
    
    // Get engagement data (using sales as proxy for engagement, like promotions.php does)
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT s.id) as total_engagement
        FROM sales s
        WHERE s.business_id = ? 
        AND s.sale_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    $stmt->execute([$business_id]);
    $engagement = $stmt->fetch();
    
    $traditional_promos = [
        'active' => $stats['active_promotions'] ?? 0,
        'total_reach' => $engagement['total_engagement'] ?? 0, // Using engagement as reach proxy
        'engagement' => $engagement['total_engagement'] ?? 0,
        'roi' => $stats['active_promotions'] > 0 ? round(($engagement['total_engagement'] / $stats['active_promotions']), 1) : 0
    ];
} catch (Exception $e) {
    // Keep default values if table doesn't exist
}

// Get promotional ads data (using correct column names from business_promotional_ads)
try {
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_ads,
            SUM(total_views) as total_impressions,
            SUM(total_clicks) as total_clicks,
            SUM(daily_views_count) as daily_views
        FROM business_promotional_ads 
        WHERE business_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    $stmt->execute([$business_id]);
    $ads_result = $stmt->fetch();
    
    $digital_ads = [
        'active' => $ads_result['active_ads'] ?? 0,
        'impressions' => $ads_result['total_impressions'] ?? 0,
        'clicks' => $ads_result['total_clicks'] ?? 0,
        'spend' => 0 // This table doesn't track spend, so we'll set to 0
    ];
} catch (Exception $e) {
    // Keep default values if table doesn't exist
}

// Calculate combined metrics
$total_active = $traditional_promos['active'] + $digital_ads['active'];
$total_reach = $traditional_promos['total_reach'] + $digital_ads['impressions'];
$total_engagement = $traditional_promos['engagement'] + $digital_ads['clicks'];
$total_investment = $digital_ads['spend']; // Traditional promotions cost tracking would need separate implementation

// Calculate effectiveness score
$effectiveness_score = 0;
if ($total_reach > 0) {
    $engagement_rate = ($total_engagement / $total_reach) * 100;
    $effectiveness_score = min(100, $engagement_rate * 10); // Scale to 0-100
}

// Calculate system contributions
$traditional_percentage = $total_reach > 0 ? ($traditional_promos['total_reach'] / $total_reach) * 100 : 0;
$digital_percentage = $total_reach > 0 ? ($digital_ads['impressions'] / $total_reach) * 100 : 0;

// Calculate digital CTR for display
$digital_ctr = $digital_ads['impressions'] > 0 ? ($digital_ads['clicks'] / $digital_ads['impressions']) * 100 : 0;
?>

<div class="card dashboard-card h-100" data-metric="unified-promotions">
    <div class="card-header bg-transparent border-0">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">
                <i class="bi bi-bullseye text-purple me-2"></i>Unified Promotions
            </h5>
            <div class="d-flex align-items-center gap-1">
                <?php if ($traditional_promos['active'] > 0): ?>
                    <span class="badge bg-primary" title="Traditional Promotions">P</span>
                <?php endif; ?>
                <?php if ($digital_ads['active'] > 0): ?>
                    <span class="badge bg-info" title="Digital Advertising">A</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="card-body">
        <div class="text-center mb-3">
            <div class="card-metric"><?php echo number_format($total_active); ?></div>
            <div class="small text-muted">Active campaigns</div>
        </div>
        
        <div class="row text-center mb-3">
            <div class="col-4">
                <div class="small text-muted">Total Reach</div>
                <div class="fw-bold text-primary"><?php echo number_format($total_reach); ?></div>
            </div>
            <div class="col-4">
                <div class="small text-muted">Engagement</div>
                <div class="fw-bold text-success"><?php echo number_format($total_engagement); ?></div>
            </div>
            <div class="col-4">
                <div class="small text-muted">Score</div>
                <div class="fw-bold text-<?php echo $effectiveness_score >= 70 ? 'success' : ($effectiveness_score >= 40 ? 'warning' : 'danger'); ?>">
                    <?php echo number_format($effectiveness_score, 0); ?>%
                </div>
            </div>
        </div>
        
        <!-- System Breakdown -->
        <?php if ($total_reach > 0): ?>
        <div class="mb-3">
            <div class="d-flex justify-content-between small text-muted mb-1">
                <span>Reach Distribution</span>
                <span><?php echo number_format($total_reach); ?></span>
            </div>
            <div class="progress" style="height: 8px;">
                <?php if ($traditional_percentage > 0): ?>
                    <div class="progress-bar bg-primary" role="progressbar" style="width: <?php echo $traditional_percentage; ?>%" title="Traditional: <?php echo number_format($traditional_promos['total_reach']); ?>"></div>
                <?php endif; ?>
                <?php if ($digital_percentage > 0): ?>
                    <div class="progress-bar bg-info" role="progressbar" style="width: <?php echo $digital_percentage; ?>%" title="Digital: <?php echo number_format($digital_ads['impressions']); ?>"></div>
                <?php endif; ?>
            </div>
            <div class="d-flex justify-content-between small text-muted mt-1">
                <span><span class="badge bg-primary me-1"></span>Traditional: <?php echo number_format($traditional_promos['total_reach']); ?></span>
                <span>Digital: <?php echo number_format($digital_ads['impressions']); ?><span class="badge bg-info ms-1"></span></span>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Performance Indicators -->
        <div class="row text-center">
            <div class="col-6">
                <small class="text-muted">Traditional ROI</small>
                <div class="small fw-bold text-success"><?php echo number_format($traditional_promos['roi'], 1); ?>%</div>
            </div>
            <div class="col-6">
                <small class="text-muted">Digital CTR</small>
                <div class="small fw-bold text-info"><?php echo number_format($digital_ctr, 1); ?>%</div>
            </div>
        </div>
    </div>
    
    <div class="card-footer bg-transparent border-0">
        <div class="d-flex gap-2">
            <button class="btn btn-outline-purple btn-sm flex-fill" data-bs-toggle="modal" data-bs-target="#unifiedPromotionsModal">
                <i class="bi bi-bullseye me-1"></i>Details
            </button>
            <a href="<?php echo APP_URL; ?>/business/analytics/promotions.php" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-graph-up me-1"></i>Analytics
            </a>
        </div>
    </div>
</div>

<!-- Unified Promotions Modal -->
<div class="modal fade" id="unifiedPromotionsModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-bullseye text-purple me-2"></i>Unified Promotions Analytics (Last 7 Days)
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <ul class="nav nav-tabs" id="promotionsTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="combined-promotions-tab" data-bs-toggle="tab" data-bs-target="#combined-promotions" type="button" role="tab">
                            <i class="bi bi-bullseye me-1"></i>Combined View
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="traditional-promotions-tab" data-bs-toggle="tab" data-bs-target="#traditional-promotions" type="button" role="tab">
                            <i class="bi bi-megaphone me-1"></i>Traditional Promotions
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="digital-ads-tab" data-bs-toggle="tab" data-bs-target="#digital-ads" type="button" role="tab">
                            <i class="bi bi-globe me-1"></i>Digital Advertising
                        </button>
                    </li>
                </ul>
                
                <div class="tab-content mt-3" id="promotionsTabsContent">
                    <!-- Combined View -->
                    <div class="tab-pane fade show active" id="combined-promotions" role="tabpanel">
                        <div class="row g-4">
                            <div class="col-md-3">
                                <div class="card">
                                    <div class="card-body text-center">
                                        <h3 class="text-purple"><?php echo number_format($total_active); ?></h3>
                                        <p class="text-muted mb-0">Total Active</p>
                                        <small class="text-muted">All campaigns</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card">
                                    <div class="card-body text-center">
                                        <h3 class="text-primary"><?php echo number_format($total_reach); ?></h3>
                                        <p class="text-muted mb-0">Total Reach</p>
                                        <small class="text-muted">
                                            Traditional: <?php echo number_format($traditional_promos['total_reach']); ?> | 
                                            Digital: <?php echo number_format($digital_ads['impressions']); ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card">
                                    <div class="card-body text-center">
                                        <h3 class="text-success"><?php echo number_format($total_engagement); ?></h3>
                                        <p class="text-muted mb-0">Total Engagement</p>
                                        <small class="text-muted">Combined interactions</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card">
                                    <div class="card-body text-center">
                                        <h3 class="text-warning"><?php echo number_format($effectiveness_score, 0); ?>%</h3>
                                        <p class="text-muted mb-0">Effectiveness</p>
                                        <small class="text-muted">Combined score</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Traditional Promotions Tab -->
                    <div class="tab-pane fade" id="traditional-promotions" role="tabpanel">
                        <div class="alert alert-info">
                            <i class="bi bi-megaphone me-2"></i>
                            <strong>Traditional Promotions</strong> - Standard promotional campaigns and activities
                        </div>
                        <div class="row g-4">
                            <div class="col-md-3">
                                <div class="card">
                                    <div class="card-body text-center">
                                        <h3 class="text-primary"><?php echo $traditional_promos['active']; ?></h3>
                                        <p class="text-muted mb-0">Active Promotions</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card">
                                    <div class="card-body text-center">
                                        <h3 class="text-info"><?php echo number_format($traditional_promos['total_reach']); ?></h3>
                                        <p class="text-muted mb-0">Total Reach</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card">
                                    <div class="card-body text-center">
                                        <h3 class="text-success"><?php echo number_format($traditional_promos['engagement']); ?></h3>
                                        <p class="text-muted mb-0">Engagement</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card">
                                    <div class="card-body text-center">
                                        <h3 class="text-warning"><?php echo number_format($traditional_promos['roi'], 1); ?>%</h3>
                                        <p class="text-muted mb-0">Average ROI</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Digital Advertising Tab -->
                    <div class="tab-pane fade" id="digital-ads" role="tabpanel">
                        <div class="alert alert-success">
                            <i class="bi bi-globe me-2"></i>
                            <strong>Digital Advertising</strong> - Online promotional campaigns and ads
                        </div>
                        <div class="row g-4">
                            <div class="col-md-3">
                                <div class="card">
                                    <div class="card-body text-center">
                                        <h3 class="text-info"><?php echo $digital_ads['active']; ?></h3>
                                        <p class="text-muted mb-0">Active Ad Campaigns</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card">
                                    <div class="card-body text-center">
                                        <h3 class="text-primary"><?php echo number_format($digital_ads['impressions']); ?></h3>
                                        <p class="text-muted mb-0">Impressions</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card">
                                    <div class="card-body text-center">
                                        <h3 class="text-success"><?php echo number_format($digital_ads['clicks']); ?></h3>
                                        <p class="text-muted mb-0">Clicks</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card">
                                    <div class="card-body text-center">
                                        <h3 class="text-info"><?php echo number_format($digital_ctr, 1); ?>%</h3>
                                        <p class="text-muted mb-0">Digital CTR</p>
                                        <small class="text-muted">Click-through rate</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <a href="<?php echo APP_URL; ?>/business/promotions.php" class="btn btn-primary">
                    <i class="bi bi-megaphone me-1"></i>Traditional Promotions
                </a>
                <a href="<?php echo APP_URL; ?>/business/promotional-ads.php" class="btn btn-info">
                    <i class="bi bi-globe me-1"></i>Digital Advertising
                </a>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
