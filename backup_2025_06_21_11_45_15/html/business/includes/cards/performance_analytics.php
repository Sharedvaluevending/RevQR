<?php
// Get business ID
$stmt = $pdo->prepare("SELECT b.id FROM businesses b JOIN users u ON b.id = u.business_id WHERE u.id = ?");
$stmt->execute([$_SESSION['user_id']]);
$business = $stmt->fetch();
$business_id = $business ? $business['id'] : 0;

// Performance Analytics - Real Database Analysis
$performance_data = [
    'total_machines' => 0,
    'active_campaigns' => 0,
    'conversion_rate' => 0,
    'revenue_growth' => 0,
    'top_performing_machine' => 'N/A',
    'weakest_machine' => 'N/A',
    'overall_score' => 50
];

try {
    // Get machine/location count
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT id) as machine_count
        FROM voting_lists 
        WHERE business_id = ?
    ");
    $stmt->execute([$business_id]);
    $machine_data = $stmt->fetch();
    $performance_data['total_machines'] = $machine_data['machine_count'] ?? 0;

    // Get active campaigns
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as active_campaigns
        FROM campaigns 
        WHERE business_id = ? AND status = 'active'
    ");
    $stmt->execute([$business_id]);
    $campaign_data = $stmt->fetch();
    $performance_data['active_campaigns'] = $campaign_data['active_campaigns'] ?? 0;

    // Calculate conversion rate (votes to sales ratio)
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT v.id) as total_votes,
            COUNT(DISTINCT s.id) as total_sales
        FROM voting_lists vl
        LEFT JOIN votes v ON vl.id = v.machine_id AND v.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        LEFT JOIN sales s ON vl.id = s.machine_id AND s.sale_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        WHERE vl.business_id = ?
    ");
    $stmt->execute([$business_id]);
    $conversion_data = $stmt->fetch();
    
    $total_votes = $conversion_data['total_votes'] ?? 0;
    $total_sales = $conversion_data['total_sales'] ?? 0;
    $performance_data['conversion_rate'] = $total_votes > 0 ? round(($total_sales / $total_votes) * 100, 1) : 0;

    // Calculate revenue growth (last 30 days vs previous 30 days)
    $stmt = $pdo->prepare("
        SELECT 
            SUM(CASE 
                WHEN sale_time >= DATE_SUB(NOW(), INTERVAL 30 DAY) 
                THEN quantity * sale_price 
                ELSE 0 
            END) as current_revenue,
            SUM(CASE 
                WHEN sale_time >= DATE_SUB(NOW(), INTERVAL 60 DAY) 
                AND sale_time < DATE_SUB(NOW(), INTERVAL 30 DAY) 
                THEN quantity * sale_price 
                ELSE 0 
            END) as previous_revenue
        FROM sales 
        WHERE business_id = ? 
        AND sale_time >= DATE_SUB(NOW(), INTERVAL 60 DAY)
    ");
    $stmt->execute([$business_id]);
    $revenue_data = $stmt->fetch();
    
    $current_revenue = $revenue_data['current_revenue'] ?? 0;
    $previous_revenue = $revenue_data['previous_revenue'] ?? 0;
    
    if ($previous_revenue > 0) {
        $performance_data['revenue_growth'] = round((($current_revenue - $previous_revenue) / $previous_revenue) * 100, 1);
    } elseif ($current_revenue > 0) {
        $performance_data['revenue_growth'] = 100; // New revenue
    }

    // Get top and weakest performing machines
    $stmt = $pdo->prepare("
        SELECT 
            vl.name as machine_name,
            vl.location,
            COALESCE(SUM(s.quantity * s.sale_price), 0) as machine_revenue,
            COUNT(DISTINCT s.id) as sales_count
        FROM voting_lists vl
        LEFT JOIN sales s ON vl.id = s.machine_id AND s.sale_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        WHERE vl.business_id = ?
        GROUP BY vl.id, vl.name, vl.location
        ORDER BY machine_revenue DESC
    ");
    $stmt->execute([$business_id]);
    $machine_performance = $stmt->fetchAll();
    
    if (!empty($machine_performance)) {
        $best_machine = $machine_performance[0];
        $worst_machine = end($machine_performance);
        
        $performance_data['top_performing_machine'] = $best_machine['location'] ?? $best_machine['machine_name'];
        $performance_data['weakest_machine'] = $worst_machine['location'] ?? $worst_machine['machine_name'];
    }

    // Calculate overall performance score
    $score = 50; // Base score
    
    if ($performance_data['total_machines'] >= 3) $score += 10;
    if ($performance_data['active_campaigns'] >= 1) $score += 10;
    if ($performance_data['conversion_rate'] >= 20) $score += 15;
    elseif ($performance_data['conversion_rate'] >= 10) $score += 10;
    if ($performance_data['revenue_growth'] > 0) $score += 15;
    elseif ($performance_data['revenue_growth'] >= -10) $score += 5;
    
    $performance_data['overall_score'] = min(100, max(0, $score));

} catch (Exception $e) {
    error_log("Performance analytics error: " . $e->getMessage());
}

// Determine performance level
$performance_level = 'Fair';
$performance_color = 'warning';
if ($performance_data['overall_score'] >= 80) {
    $performance_level = 'Excellent';
    $performance_color = 'success';
} elseif ($performance_data['overall_score'] >= 65) {
    $performance_level = 'Good';
    $performance_color = 'info';
} elseif ($performance_data['overall_score'] <= 40) {
    $performance_level = 'Needs Work';
    $performance_color = 'danger';
}
?>

<div class="card dashboard-card performance-analytics-card">
  <div class="card-body">
    <div class="card-title d-flex align-items-center">
      <i class="bi bi-graph-up text-info me-2 fs-4"></i>
      Performance Analytics
    </div>
    <div class="card-metric" id="performance-metric"><?php echo $performance_data['overall_score']; ?></div>
    <div class="small text-muted mb-2">Overall Performance Score</div>
    
    <div class="performance-level mb-3">
      <span class="badge bg-<?php echo $performance_color; ?> px-3 py-2">
        <?php echo $performance_level; ?>
      </span>
    </div>
    
    <div class="row text-center">
      <div class="col-6">
        <div class="small text-muted">Machines</div>
        <div class="fw-bold text-primary"><?php echo $performance_data['total_machines']; ?></div>
      </div>
      <div class="col-6">
        <div class="small text-muted">Campaigns</div>
        <div class="fw-bold text-info"><?php echo $performance_data['active_campaigns']; ?></div>
      </div>
    </div>
    
    <div class="mt-3">
      <div class="row text-center">
        <div class="col-6">
          <div class="small text-muted">Conversion</div>
          <div class="fw-bold text-success"><?php echo $performance_data['conversion_rate']; ?>%</div>
        </div>
        <div class="col-6">
          <div class="small text-muted">Growth</div>
          <div class="fw-bold text-<?php echo $performance_data['revenue_growth'] >= 0 ? 'success' : 'danger'; ?>">
            <?php echo $performance_data['revenue_growth'] >= 0 ? '+' : ''; ?><?php echo $performance_data['revenue_growth']; ?>%
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="card-footer text-end">
    <a href="<?php echo APP_URL; ?>/business/analytics.php" class="btn btn-outline-info btn-sm">
      View Details
    </a>
  </div>
</div>

<!-- Performance Details Modal -->
<div class="modal fade" id="performanceDetailsModal" tabindex="-1" aria-labelledby="performanceDetailsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="performanceDetailsModalLabel">
          <i class="bi bi-graph-up me-2"></i>Performance Analytics Breakdown
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="row g-4">
          <div class="col-md-6">
            <div class="performance-metric-card">
              <h6 class="text-primary">
                <i class="bi bi-geo-alt me-2"></i>Location Performance
              </h6>
              <div class="metric-item">
                <div class="d-flex justify-content-between">
                  <span>Top Performer:</span>
                  <strong class="text-success"><?php echo htmlspecialchars($performance_data['top_performing_machine']); ?></strong>
                </div>
              </div>
              <div class="metric-item">
                <div class="d-flex justify-content-between">
                  <span>Needs Attention:</span>
                  <strong class="text-warning"><?php echo htmlspecialchars($performance_data['weakest_machine']); ?></strong>
                </div>
              </div>
            </div>
          </div>
          
          <div class="col-md-6">
            <div class="performance-metric-card">
              <h6 class="text-info">
                <i class="bi bi-target me-2"></i>Engagement Metrics
              </h6>
              <div class="metric-item">
                <div class="d-flex justify-content-between">
                  <span>Conversion Rate:</span>
                  <strong class="text-success"><?php echo $performance_data['conversion_rate']; ?>%</strong>
                </div>
              </div>
              <div class="metric-item">
                <div class="d-flex justify-content-between">
                  <span>Revenue Growth:</span>
                  <strong class="text-<?php echo $performance_data['revenue_growth'] >= 0 ? 'success' : 'danger'; ?>">
                    <?php echo $performance_data['revenue_growth']; ?>%
                  </strong>
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <div class="mt-4">
          <h6 class="text-secondary mb-3">
            <i class="bi bi-lightbulb me-2"></i>Performance Insights
          </h6>
          <div class="insights-container">
            <?php if ($performance_data['overall_score'] >= 80): ?>
              <div class="insight-item bg-success bg-opacity-10 border-success">
                <i class="bi bi-trophy text-success me-2"></i>
                Excellent performance! Your business is running optimally across all metrics.
              </div>
            <?php elseif ($performance_data['overall_score'] >= 65): ?>
              <div class="insight-item bg-info bg-opacity-10 border-info">
                <i class="bi bi-arrow-up text-info me-2"></i>
                Good performance with room for improvement. Focus on increasing conversion rates.
              </div>
            <?php elseif ($performance_data['overall_score'] <= 40): ?>
              <div class="insight-item bg-danger bg-opacity-10 border-danger">
                <i class="bi bi-exclamation-triangle text-danger me-2"></i>
                Performance needs attention. Consider reviewing campaign strategies and machine placement.
              </div>
            <?php else: ?>
              <div class="insight-item bg-warning bg-opacity-10 border-warning">
                <i class="bi bi-gear text-warning me-2"></i>
                Fair performance. Focus on optimizing top-performing locations and improving weaker ones.
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <a href="<?php echo APP_URL; ?>/business/analytics.php" class="btn btn-primary">
          View Full Analytics
        </a>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<style>
.performance-analytics-card {
    background: linear-gradient(135deg, rgba(13, 202, 240, 0.1) 0%, rgba(25, 135, 84, 0.1) 100%);
    border: 1px solid rgba(13, 202, 240, 0.3);
}

.performance-analytics-card:hover {
    border: 1px solid rgba(13, 202, 240, 0.5);
    background: linear-gradient(135deg, rgba(13, 202, 240, 0.15) 0%, rgba(25, 135, 84, 0.15) 100%);
}

.performance-metric-card {
    background: rgba(255, 255, 255, 0.05);
    border-radius: 8px;
    padding: 1rem;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.metric-item {
    padding: 0.5rem 0;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.metric-item:last-child {
    border-bottom: none;
}

.insight-item {
    padding: 0.75rem;
    border-radius: 6px;
    border: 1px solid;
    margin-bottom: 0.5rem;
}
</style> 