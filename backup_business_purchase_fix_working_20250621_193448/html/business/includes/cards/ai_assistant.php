<?php
// Get business ID
$stmt = $pdo->prepare("SELECT b.id FROM businesses b JOIN users u ON b.id = u.business_id WHERE u.id = ?");
$stmt->execute([$_SESSION['user_id']]);
$business = $stmt->fetch();
$business_id = $business ? $business['id'] : 0;

// Check if we need to update insights (daily basis for more current data)
$last_update = null;
$needs_update = true;
$cached_insights = null;

try {
    $stmt = $pdo->prepare("
        SELECT created_at as last_update, insights_data 
        FROM ai_insights_log 
        WHERE business_id = ? 
        AND DATE(created_at) = DATE(NOW())
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $stmt->execute([$business_id]);
    $result = $stmt->fetch();
    if ($result && $result['last_update']) {
        $last_update = strtotime($result['last_update']);
        $needs_update = false; // Already updated today
        $cached_insights = json_decode($result['insights_data'], true);
    }
} catch (Exception $e) {
    // Table might not exist yet, continue with default behavior
    error_log("AI insights check failed: " . $e->getMessage());
}

// Get latest sales trends for preview - FIXED: using correct column names
try {
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT DATE(sale_time)) as days_tracked,
            SUM(quantity * sale_price) as total_revenue,
            COUNT(*) as total_sales,
            AVG(quantity * sale_price) as avg_sale_value
        FROM sales 
        WHERE business_id = ? 
        AND sale_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $stmt->execute([$business_id]);
    $salesData = $stmt->fetch();
    $daysTracked = $salesData['days_tracked'] ?? 0;
    $totalRevenue = $salesData['total_revenue'] ?? 0;
    $totalSales = $salesData['total_sales'] ?? 0;
    $avgSaleValue = $salesData['avg_sale_value'] ?? 0;
} catch (Exception $e) {
    // Handle case where sales table structure is different
    error_log("Sales data query failed: " . $e->getMessage());
    $daysTracked = 0;
    $totalRevenue = 0;
    $totalSales = 0;
    $avgSaleValue = 0;
}

// Get fresh AI insights if needed (daily update or first time)
if ($needs_update && $business_id > 0) {
    try {
        require_once __DIR__ . '/../../../core/ai_assistant.php';
        $aiAssistant = new AIAssistant();
        $analytics = $aiAssistant->getBusinessAnalytics($business_id, $pdo);
        $fresh_insights = $aiAssistant->generateInsights($analytics);
        
        // Store the fresh insights
        try {
            $stmt = $pdo->prepare("
                INSERT INTO ai_insights_log (business_id, insights_data, created_at) 
                VALUES (?, ?, NOW())
            ");
            $stmt->execute([$business_id, json_encode($fresh_insights)]);
        } catch (Exception $e) {
            error_log("Failed to store fresh insights: " . $e->getMessage());
        }
        
        $ai_recommendations = $fresh_insights['recommendations'] ?? [];
        $ai_opportunities = $fresh_insights['sales_opportunities'] ?? [];
        $insights_count = count($ai_recommendations);
        $opportunities_count = count($ai_opportunities);
        
    } catch (Exception $e) {
        error_log("Failed to generate fresh insights: " . $e->getMessage());
        // Fall back to sample insights
        $ai_recommendations = [
            [
                'title' => 'Stock Analysis Required',
                'description' => 'Enable sales tracking for data-driven recommendations',
                'priority' => 'medium'
            ]
        ];
        $ai_opportunities = [];
        $insights_count = 1;
        $opportunities_count = 0;
    }
} else {
    // Use cached insights from today
    if ($cached_insights) {
        $ai_recommendations = $cached_insights['recommendations'] ?? [];
        $ai_opportunities = $cached_insights['sales_opportunities'] ?? [];
        $insights_count = count($ai_recommendations);
        $opportunities_count = count($ai_opportunities);
    } else {
        // Fallback if no cached data
        $ai_recommendations = [
            [
                'title' => 'Daily Analysis Ready',
                'description' => 'AI assistant monitoring your business performance',
                'priority' => 'low'
            ]
        ];
        $ai_opportunities = [];
        $insights_count = 1;
        $opportunities_count = 0;
    }
}
?>
<div class="card dashboard-card ai-assistant-card">
  <div class="card-body">
    <div class="card-title d-flex align-items-center justify-content-between">
      <div>
        <i class="bi bi-robot text-primary me-2 fs-4"></i>
        AI Business Assistant
      </div>
      <div class="d-flex gap-1">
        <div class="badge bg-primary">Advanced</div>
        <?php if ($needs_update): ?>
          <div class="badge bg-success">
            <i class="bi bi-arrow-clockwise me-1"></i>Updated
          </div>
        <?php else: ?>
          <div class="badge bg-info">
            <i class="bi bi-check-circle me-1"></i>Today
          </div>
        <?php endif; ?>
      </div>
    </div>
    
    <div class="row mb-3">
      <div class="col-12">
        <div class="small text-muted mb-2">
          <i class="bi bi-lightbulb text-warning me-1"></i>
          Latest AI Recommendations 
          <span class="text-info">(Daily updates)</span>
        </div>
        <div class="ai-insights-preview">
          <?php 
          $displayItems = array_slice($ai_recommendations, 0, 2);
          foreach ($displayItems as $recommendation): 
            $iconClass = match($recommendation['priority'] ?? 'medium') {
                'high' => 'bi-exclamation-triangle text-danger',
                'medium' => 'bi-lightbulb text-warning', 
                'low' => 'bi-info-circle text-info',
                default => 'bi-lightbulb text-warning'
            };
          ?>
            <div class="insight-item mb-2 priority-<?php echo htmlspecialchars($recommendation['priority'] ?? 'medium'); ?>">
              <div class="d-flex align-items-start">
                <i class="<?php echo $iconClass; ?> me-2 mt-1"></i>
                <div class="flex-grow-1">
                  <strong class="d-block"><?php echo htmlspecialchars($recommendation['title']); ?></strong>
                  <div class="small text-muted mt-1"><?php echo htmlspecialchars($recommendation['description']); ?></div>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
          
          <?php if ($opportunities_count > 0): ?>
            <div class="opportunity-preview mt-2 p-2 bg-success bg-opacity-10 rounded">
              <i class="bi bi-graph-up text-success me-1"></i>
              <small class="text-success fw-bold">
                <?php echo $opportunities_count; ?> Revenue Opportunity<?php echo $opportunities_count > 1 ? 's' : ''; ?> Identified
              </small>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
    
    <div class="row text-center">
      <div class="col-6 col-md-3">
        <div class="small text-muted">Insights</div>
        <div class="fw-bold text-primary"><?php echo $insights_count; ?></div>
      </div>
      <div class="col-6 col-md-3">
        <div class="small text-muted">Opportunities</div>
        <div class="fw-bold text-success"><?php echo $opportunities_count; ?></div>
      </div>
      <div class="col-6 col-md-3">
        <div class="small text-muted">Revenue</div>
        <div class="fw-bold text-warning">$<?php echo number_format($totalRevenue, 0); ?></div>
      </div>
      <div class="col-6 col-md-3">
        <div class="small text-muted">Avg Sale</div>
        <div class="fw-bold text-info">$<?php echo number_format($avgSaleValue, 2); ?></div>
      </div>
    </div>
  </div>
  <div class="card-footer d-flex justify-content-between align-items-center">
    <div class="small text-muted">
      <i class="bi bi-clock me-1"></i>
      <?php if ($last_update): ?>
        Today: <?php echo date('g:i A', $last_update); ?>
      <?php else: ?>
        Updated: <?php echo date('M j, g:i A'); ?>
      <?php endif; ?>
      <?php if ($totalSales > 0): ?>
        <span class="text-success ms-2">
          <i class="bi bi-database me-1"></i><?php echo $totalSales; ?> sales analyzed
        </span>
      <?php endif; ?>
    </div>
    <a href="<?php echo APP_URL; ?>/business/ai-assistant.php" class="btn btn-primary btn-sm">
      <i class="bi bi-eye me-1"></i>View All
    </a>
  </div>
</div>

<style>
.ai-assistant-card {
  background: linear-gradient(135deg, rgba(13, 110, 253, 0.12) 0%, rgba(102, 16, 242, 0.12) 100%);
  border: 1px solid rgba(13, 110, 253, 0.3);
  transition: all 0.3s ease;
}

.ai-assistant-card:hover {
  border: 1px solid rgba(13, 110, 253, 0.5);
  background: linear-gradient(135deg, rgba(13, 110, 253, 0.18) 0%, rgba(102, 16, 242, 0.18) 100%);
  transform: translateY(-2px);
}

.insight-item {
  padding: 0.8rem;
  background: rgba(255, 255, 255, 0.08);
  border-radius: 8px;
  transition: all 0.2s ease;
  margin-bottom: 0.75rem;
}

.insight-item.priority-high {
  border-left: 4px solid #dc3545;
  background: rgba(220, 53, 69, 0.1);
}

.insight-item.priority-medium {
  border-left: 4px solid #ffc107;
  background: rgba(255, 193, 7, 0.1);
}

.insight-item.priority-low {
  border-left: 4px solid #0dcaf0;
  background: rgba(13, 202, 240, 0.1);
}

.ai-insights-preview {
  max-height: 180px;
  overflow: hidden;
  overflow-y: auto;
}

.opportunity-preview {
  border: 1px solid rgba(25, 135, 84, 0.3);
}

/* Enhanced badge styling */
.badge {
  font-size: 0.7rem;
  padding: 0.35em 0.6em;
}

/* Improved metrics grid */
.row.text-center .col-6,
.row.text-center .col-md-3 {
  border-right: 1px solid rgba(255, 255, 255, 0.1);
  padding: 0.5rem;
}

.row.text-center .col-6:nth-child(2n),
.row.text-center .col-md-3:last-child {
  border-right: none;
}

@media (min-width: 768px) {
  .row.text-center .col-6 {
    border-right: 1px solid rgba(255, 255, 255, 0.1);
  }
  
  .row.text-center .col-6:nth-child(2n) {
    border-right: 1px solid rgba(255, 255, 255, 0.1);
  }
}
</style> 