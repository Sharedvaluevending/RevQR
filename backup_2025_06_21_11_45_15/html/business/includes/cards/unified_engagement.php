<?php
/**
 * Unified Engagement Analytics Card
 * Combines manual voting and nayax customer engagement data
 */

// Get business ID
$stmt = $pdo->prepare("SELECT b.id FROM businesses b JOIN users u ON b.id = u.business_id WHERE u.id = ?");
$stmt->execute([$_SESSION['user_id']]);
$business = $stmt->fetch();
$business_id = $business ? $business['id'] : 0;

// Initialize data arrays
$manual_engagement = ['votes' => 0, 'vote_in' => 0, 'vote_out' => 0, 'campaigns' => 0, 'qr_scans' => 0];
$nayax_engagement = ['interactions' => 0, 'customers' => 0, 'repeat_customers' => 0];

// Get manual voting data (last 7 days)
try {
    $stmt = $pdo->prepare("
        SELECT v.vote_type, COUNT(*) as count
        FROM votes v
        JOIN machines m ON v.machine_id = m.id
        WHERE m.business_id = ? AND v.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY v.vote_type
    ");
    $stmt->execute([$business_id]);
    $votes = ['vote_in' => 0, 'vote_out' => 0];
    foreach ($stmt->fetchAll() as $row) {
        $votes[$row['vote_type']] = (int)$row['count'];
    }
    
    // Get campaigns (all voting lists are considered active)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as campaign_count
        FROM voting_lists 
        WHERE business_id = ?
    ");
    $stmt->execute([$business_id]);
    $campaign_data = $stmt->fetch();
    
    $manual_engagement = [
        'votes' => $votes['vote_in'] + $votes['vote_out'],
        'vote_in' => $votes['vote_in'],
        'vote_out' => $votes['vote_out'],
        'campaigns' => $campaign_data['campaign_count'] ?? 0,
        'qr_scans' => 0
    ];
} catch (Exception $e) {
    // Keep default values if tables don't exist
}

// Get QR scans data separately to ensure it runs even if voting queries fail
try {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as qr_scans
        FROM qr_code_stats qcs
        JOIN qr_codes qr ON qcs.qr_code_id = qr.id
        WHERE qr.business_id = ? AND qcs.scan_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    $stmt->execute([$business_id]);
    $qr_data = $stmt->fetch();
    $manual_engagement['qr_scans'] = $qr_data['qr_scans'] ?? 0;
} catch (Exception $e) {
    // QR scans will remain 0 if query fails
}

// Get nayax engagement data (last 7 days) - using correct column structure
try {
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as interactions,
            COUNT(DISTINCT customer_id) as unique_customers
        FROM nayax_transactions nt
        JOIN nayax_machines nm ON nt.nayax_machine_id = nm.nayax_machine_id
        WHERE nm.business_id = ? AND nt.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    $stmt->execute([$business_id]);
    $nayax_data = $stmt->fetch();
    
    // Get repeat customers (customers with more than 1 transaction in last 7 days)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as repeat_customers
        FROM (
            SELECT customer_id
            FROM nayax_transactions nt
            JOIN nayax_machines nm ON nt.nayax_machine_id = nm.nayax_machine_id
            WHERE nm.business_id = ? AND nt.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY customer_id
            HAVING COUNT(*) > 1
        ) as repeats
    ");
    $stmt->execute([$business_id]);
    $repeat_data = $stmt->fetch();
    
    $nayax_engagement = [
        'interactions' => $nayax_data['interactions'] ?? 0,
        'customers' => $nayax_data['unique_customers'] ?? 0,
        'repeat_customers' => $repeat_data['repeat_customers'] ?? 0
    ];
} catch (Exception $e) {
    // Keep default values if tables don't exist
}

// Calculate combined engagement metrics
$total_interactions = $manual_engagement['votes'] + $manual_engagement['qr_scans'] + $nayax_engagement['interactions'];
$engagement_score = 0;

// Calculate engagement score (weighted combination)
if ($total_interactions > 0) {
    $manual_weight = $manual_engagement['votes'] > 0 ? ($manual_engagement['vote_in'] / $manual_engagement['votes']) * 100 : 0;
    $nayax_weight = $nayax_engagement['customers'] > 0 ? ($nayax_engagement['repeat_customers'] / $nayax_engagement['customers']) * 100 : 0;
    
    if ($manual_engagement['votes'] > 0 && $nayax_engagement['interactions'] > 0) {
        $engagement_score = ($manual_weight + $nayax_weight) / 2;
    } elseif ($manual_engagement['votes'] > 0) {
        $engagement_score = $manual_weight;
    } elseif ($nayax_engagement['interactions'] > 0) {
        $engagement_score = $nayax_weight;
    }
}

// Calculate growth (previous week comparison)
$previous_total = 0;
try {
    // Manual previous votes
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as previous_votes
        FROM votes v
        JOIN machines m ON v.machine_id = m.id
        WHERE m.business_id = ? 
        AND v.created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY) 
        AND v.created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    $stmt->execute([$business_id]);
    $manual_previous = $stmt->fetch()['previous_votes'] ?? 0;
    
    // Nayax previous interactions - using correct column structure
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as previous_interactions
        FROM nayax_transactions nt
        JOIN nayax_machines nm ON nt.nayax_machine_id = nm.nayax_machine_id
        WHERE nm.business_id = ? 
        AND nt.created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY) 
        AND nt.created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    $stmt->execute([$business_id]);
    $nayax_previous = $stmt->fetch()['previous_interactions'] ?? 0;
    
    $previous_total = $manual_previous + $nayax_previous;
} catch (Exception $e) {
    // Keep default if tables don't exist
}

$growth_percent = $previous_total > 0 ? (($total_interactions - $previous_total) / $previous_total) * 100 : 0;

// Calculate system contributions
$manual_activities = $manual_engagement['votes'] + $manual_engagement['qr_scans'];
$manual_percentage = $total_interactions > 0 ? ($manual_activities / $total_interactions) * 100 : 0;
$nayax_percentage = $total_interactions > 0 ? ($nayax_engagement['interactions'] / $total_interactions) * 100 : 0;
?>

<div class="card dashboard-card h-100" data-metric="unified-engagement">
    <div class="card-header bg-transparent border-0">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">
                <i class="bi bi-heart text-danger me-2"></i>Unified Engagement
            </h5>
            <div class="d-flex align-items-center gap-1">
                <?php if ($manual_engagement['votes'] > 0): ?>
                    <span class="badge bg-primary" title="Manual Voting System">V</span>
                <?php endif; ?>
                <?php if ($nayax_engagement['interactions'] > 0): ?>
                    <span class="badge bg-success" title="Nayax Customer Engagement">N</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="card-body">
        <div class="text-center mb-3">
            <div class="card-metric"><?php echo number_format($total_interactions); ?></div>
            <div class="small text-muted">Total interactions (7 days)</div>
        </div>
        
        <div class="row text-center mb-3">
            <div class="col-4">
                <div class="small text-muted">Score</div>
                <div class="fw-bold text-<?php echo $engagement_score >= 70 ? 'success' : ($engagement_score >= 40 ? 'warning' : 'danger'); ?>">
                    <?php echo number_format($engagement_score, 0); ?>%
                </div>
            </div>
            <div class="col-4">
                <div class="small text-muted">Votes</div>
                <div class="fw-bold text-primary"><?php echo $manual_engagement['votes']; ?></div>
            </div>
            <div class="col-4">
                <div class="small text-muted">QR Scans</div>
                <div class="fw-bold text-info"><?php echo $manual_engagement['qr_scans']; ?></div>
            </div>
        </div>
        
        <!-- System Breakdown -->
        <?php if ($total_interactions > 0): ?>
        <div class="mb-3">
            <div class="d-flex justify-content-between small text-muted mb-1">
                <span>Engagement Sources</span>
                <span><?php echo number_format($total_interactions); ?></span>
            </div>
            <div class="progress" style="height: 8px;">
                <?php if ($manual_percentage > 0): ?>
                    <div class="progress-bar bg-primary" role="progressbar" style="width: <?php echo $manual_percentage; ?>%" title="Manual Activities: <?php echo $manual_engagement['votes']; ?> votes + <?php echo $manual_engagement['qr_scans']; ?> scans"></div>
                <?php endif; ?>
                <?php if ($nayax_percentage > 0): ?>
                    <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $nayax_percentage; ?>%" title="Nayax Interactions: <?php echo $nayax_engagement['interactions']; ?>"></div>
                <?php endif; ?>
            </div>
            <div class="d-flex justify-content-between small text-muted mt-1">
                <span><span class="badge bg-primary me-1"></span>Manual: <?php echo $manual_engagement['votes']; ?>v + <?php echo $manual_engagement['qr_scans']; ?>s</span>
                <span>Nayax: <?php echo $nayax_engagement['interactions']; ?><span class="badge bg-success ms-1"></span></span>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Engagement Quality Indicators -->
        <div class="row text-center">
            <div class="col-6">
                <small class="text-muted">Positive Votes</small>
                <div class="small fw-bold text-success"><?php echo $manual_engagement['vote_in']; ?></div>
            </div>
            <div class="col-6">
                <small class="text-muted">Repeat Customers</small>
                <div class="small fw-bold text-warning"><?php echo $nayax_engagement['repeat_customers']; ?></div>
            </div>
        </div>
        
        <?php if ($total_interactions > 0): ?>
        <div class="mt-3">
            <canvas id="unifiedEngagementChart" height="50"></canvas>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="card-footer bg-transparent border-0">
        <div class="d-flex gap-2">
            <button class="btn btn-outline-danger btn-sm flex-fill" data-bs-toggle="modal" data-bs-target="#unifiedEngagementModal">
                <i class="bi bi-heart me-1"></i>Details
            </button>
            <a href="<?php echo APP_URL; ?>/business/analytics/engagement.php" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-graph-up me-1"></i>Analytics
            </a>
        </div>
    </div>
</div>

<!-- Unified Engagement Modal -->
<div class="modal fade" id="unifiedEngagementModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-heart text-danger me-2"></i>Unified Engagement Analytics (Last 7 Days)
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <ul class="nav nav-tabs" id="engagementTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="combined-engagement-tab" data-bs-toggle="tab" data-bs-target="#combined-engagement" type="button" role="tab">
                            <i class="bi bi-heart me-1"></i>Combined View
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="manual-engagement-tab" data-bs-toggle="tab" data-bs-target="#manual-engagement" type="button" role="tab">
                            <i class="bi bi-person-check me-1"></i>Voting System
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="nayax-engagement-tab" data-bs-toggle="tab" data-bs-target="#nayax-engagement" type="button" role="tab">
                            <i class="bi bi-people me-1"></i>Customer Engagement
                        </button>
                    </li>
                </ul>
                
                <div class="tab-content mt-3" id="engagementTabsContent">
                    <!-- Combined View -->
                    <div class="tab-pane fade show active" id="combined-engagement" role="tabpanel">
                        <div class="row g-4">
                            <div class="col-md-3">
                                <div class="card">
                                    <div class="card-body text-center">
                                        <h3 class="text-danger"><?php echo number_format($total_interactions); ?></h3>
                                        <p class="text-muted mb-0">Total Interactions</p>
                                        <small class="text-<?php echo $growth_percent >= 0 ? 'success' : 'danger'; ?>">
                                            <i class="bi bi-arrow-<?php echo $growth_percent >= 0 ? 'up' : 'down'; ?>"></i>
                                            <?php echo number_format($growth_percent, 1); ?>% vs last week
                                        </small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card">
                                    <div class="card-body text-center">
                                        <h3 class="text-primary"><?php echo $manual_engagement['votes']; ?></h3>
                                        <p class="text-muted mb-0">Votes Cast</p>
                                        <small class="text-muted">
                                            <?php echo $manual_engagement['vote_in']; ?> positive
                                        </small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card">
                                    <div class="card-body text-center">
                                        <h3 class="text-success"><?php echo $nayax_engagement['customers']; ?></h3>
                                        <p class="text-muted mb-0">Unique Customers</p>
                                        <small class="text-muted">
                                            <?php echo $nayax_engagement['repeat_customers']; ?> returned
                                        </small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card">
                                    <div class="card-body text-center">
                                        <h3 class="text-warning"><?php echo number_format($engagement_score, 0); ?>%</h3>
                                        <p class="text-muted mb-0">Engagement Score</p>
                                        <small class="text-muted">Quality metric</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Engagement Breakdown Chart -->
                        <div class="mt-4">
                            <h6>Engagement Sources Breakdown</h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <canvas id="engagementSourceChart" height="200"></canvas>
                                </div>
                                <div class="col-md-6">
                                    <div class="d-flex justify-content-center align-items-center h-100">
                                        <div class="text-center">
                                            <div class="mb-3">
                                                <span class="badge bg-primary me-2">Voting</span>
                                                <strong><?php echo $manual_engagement['votes']; ?> interactions</strong>
                                                <small class="text-muted d-block"><?php echo number_format($manual_percentage, 1); ?>% of total</small>
                                            </div>
                                            <div class="mb-3">
                                                <span class="badge bg-success me-2">Nayax</span>
                                                <strong><?php echo $nayax_engagement['interactions']; ?> interactions</strong>
                                                <small class="text-muted d-block"><?php echo number_format($nayax_percentage, 1); ?>% of total</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Manual Voting Tab -->
                    <div class="tab-pane fade" id="manual-engagement" role="tabpanel">
                        <div class="alert alert-info">
                            <i class="bi bi-person-check me-2"></i>
                            <strong>Voting System Engagement</strong> - Customer feedback through manual voting campaigns
                        </div>
                        <div class="row g-4">
                            <div class="col-md-3">
                                <div class="card">
                                    <div class="card-body text-center">
                                        <h3 class="text-primary"><?php echo $manual_engagement['votes']; ?></h3>
                                        <p class="text-muted mb-0">Total Votes</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card">
                                    <div class="card-body text-center">
                                        <h3 class="text-success"><?php echo $manual_engagement['vote_in']; ?></h3>
                                        <p class="text-muted mb-0">Positive Votes</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card">
                                    <div class="card-body text-center">
                                        <h3 class="text-warning"><?php echo $manual_engagement['vote_out']; ?></h3>
                                        <p class="text-muted mb-0">Negative Votes</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card">
                                    <div class="card-body text-center">
                                        <h3 class="text-info"><?php echo $manual_engagement['campaigns']; ?></h3>
                                        <p class="text-muted mb-0">Active Campaigns</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Nayax Engagement Tab -->
                    <div class="tab-pane fade" id="nayax-engagement" role="tabpanel">
                        <div class="alert alert-success">
                            <i class="bi bi-people me-2"></i>
                            <strong>Nayax Customer Engagement</strong> - Automated customer interaction tracking
                        </div>
                        <div class="row g-4">
                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-body text-center">
                                        <h3 class="text-success"><?php echo $nayax_engagement['interactions']; ?></h3>
                                        <p class="text-muted mb-0">Total Interactions</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-body text-center">
                                        <h3 class="text-info"><?php echo $nayax_engagement['customers']; ?></h3>
                                        <p class="text-muted mb-0">Unique Customers</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-body text-center">
                                        <h3 class="text-warning"><?php echo $nayax_engagement['repeat_customers']; ?></h3>
                                        <p class="text-muted mb-0">Repeat Customers</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <a href="<?php echo APP_URL; ?>/business/view-votes.php" class="btn btn-primary">
                    <i class="bi bi-bar-chart me-1"></i>Voting Analytics
                </a>
                <a href="<?php echo APP_URL; ?>/business/nayax-customers.php" class="btn btn-success">
                    <i class="bi bi-people me-1"></i>Customer Intelligence
                </a>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Main card chart
    const chartCanvas = document.getElementById('unifiedEngagementChart');
    if (window.Chart && chartCanvas && <?php echo $total_interactions; ?> > 0) {
        try {
            new Chart(chartCanvas.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: ['Votes', 'Nayax'],
                    datasets: [{
                        data: [<?php echo $manual_engagement['votes']; ?>, <?php echo $nayax_engagement['interactions']; ?>],
                        backgroundColor: ['#0d6efd', '#198754'],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
        } catch (error) {
            console.log('Chart initialization failed:', error);
        }
    }
    
    // Modal source chart
    const sourceCanvas = document.getElementById('engagementSourceChart');
    if (window.Chart && sourceCanvas) {
        try {
            new Chart(sourceCanvas.getContext('2d'), {
                type: 'pie',
                data: {
                    labels: ['Manual Voting', 'Nayax Engagement'],
                    datasets: [{
                        data: [<?php echo $manual_engagement['votes']; ?>, <?php echo $nayax_engagement['interactions']; ?>],
                        backgroundColor: ['#0d6efd', '#198754']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        } catch (error) {
            console.log('Source chart failed:', error);
        }
    }
});
</script> 