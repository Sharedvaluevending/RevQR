<?php
// Get business ID
$stmt = $pdo->prepare("SELECT b.id FROM businesses b JOIN users u ON b.id = u.business_id WHERE u.id = ?");
$stmt->execute([$_SESSION['user_id']]);
$business = $stmt->fetch();
$business_id = $business ? $business['id'] : 0;

// Pizza Tracker Data
$pizza_data = [
    'active_trackers' => 0,
    'total_progress' => 0,
    'pizzas_earned' => 0,
    'current_revenue' => 0,
    'goal_amount' => 0,
    'days_to_pizza' => 'N/A',
    'top_tracker' => null
];

try {
    // Include pizza tracker utils if available
    if (file_exists(__DIR__ . '/../../../core/pizza_tracker_utils.php')) {
        require_once __DIR__ . '/../../../core/pizza_tracker_utils.php';
        
        // Get active pizza trackers
        $stmt = $pdo->prepare("
            SELECT 
                pt.*,
                ROUND((current_revenue / NULLIF(revenue_goal, 0)) * 100, 1) as progress_percent
            FROM pizza_trackers pt
            WHERE pt.business_id = ? AND pt.is_active = 1
            ORDER BY progress_percent DESC
        ");
        $stmt->execute([$business_id]);
        $trackers = $stmt->fetchAll();
        
        $pizza_data['active_trackers'] = count($trackers);
        
        if (!empty($trackers)) {
            $pizza_data['top_tracker'] = $trackers[0];
            $pizza_data['current_revenue'] = $trackers[0]['current_revenue'];
            $pizza_data['goal_amount'] = $trackers[0]['revenue_goal'];
            $pizza_data['total_progress'] = $trackers[0]['progress_percent'];
            
            // Calculate average days to pizza based on recent performance
            if ($pizza_data['current_revenue'] > 0) {
                $days_active = max(1, (time() - strtotime($trackers[0]['created_at'])) / (24 * 60 * 60));
                $daily_rate = $pizza_data['current_revenue'] / $days_active;
                if ($daily_rate > 0) {
                    $remaining = $pizza_data['goal_amount'] - $pizza_data['current_revenue'];
                    $pizza_data['days_to_pizza'] = max(0, ceil($remaining / $daily_rate));
                }
            }
        }
        
        // Get total pizzas earned (completion count)
        $stmt = $pdo->prepare("
            SELECT SUM(completion_count) as total_pizzas
            FROM pizza_trackers 
            WHERE business_id = ?
        ");
        $stmt->execute([$business_id]);
        $pizza_count = $stmt->fetch();
        $pizza_data['pizzas_earned'] = $pizza_count['total_pizzas'] ?? 0;
        
    } else {
        // Fallback: Check if pizza tracker tables exist and get basic data
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as tracker_count
            FROM information_schema.tables 
            WHERE table_schema = DATABASE() AND table_name = 'pizza_trackers'
        ");
        $stmt->execute();
        $table_exists = $stmt->fetch()['tracker_count'] > 0;
        
        if ($table_exists) {
            $stmt = $pdo->prepare("
                SELECT 
                    COUNT(*) as active_count,
                    AVG(CASE 
                        WHEN revenue_goal > 0 
                        THEN (current_revenue / revenue_goal) * 100 
                        ELSE 0 
                    END) as avg_progress,
                    SUM(completion_count) as total_pizzas
                FROM pizza_trackers 
                WHERE business_id = ? AND is_active = 1
            ");
            $stmt->execute([$business_id]);
            $basic_data = $stmt->fetch();
            
            $pizza_data['active_trackers'] = $basic_data['active_count'] ?? 0;
            $pizza_data['total_progress'] = round($basic_data['avg_progress'] ?? 0, 1);
            $pizza_data['pizzas_earned'] = $basic_data['total_pizzas'] ?? 0;
        }
    }
    
} catch (Exception $e) {
    error_log("Pizza tracker card error: " . $e->getMessage());
}

// Progress status
$progress_status = 'Just Started';
$progress_color = 'secondary';
$progress_icon = 'bi-clock';

if ($pizza_data['total_progress'] >= 90) {
    $progress_status = 'Almost There!';
    $progress_color = 'warning';
    $progress_icon = 'bi-hourglass-split';
} elseif ($pizza_data['total_progress'] >= 75) {
    $progress_status = 'Getting Close';
    $progress_color = 'info';
    $progress_icon = 'bi-arrow-up';
} elseif ($pizza_data['total_progress'] >= 50) {
    $progress_status = 'Making Progress';
    $progress_color = 'primary';
    $progress_icon = 'bi-graph-up';
} elseif ($pizza_data['total_progress'] >= 25) {
    $progress_status = 'Good Start';
    $progress_color = 'success';
    $progress_icon = 'bi-play';
}
?>

<div class="card dashboard-card pizza-tracker-card">
  <div class="card-body">
    <div class="card-title d-flex align-items-center">
      <i class="bi bi-emoji-smile text-warning me-2 fs-4"></i>
      Pizza Tracker
    </div>
    <div class="card-metric" id="pizza-metric"><?php echo number_format($pizza_data['total_progress'], 1); ?>%</div>
    <div class="small text-muted mb-2">Progress to Next Pizza</div>
    
    <div class="progress mb-3" style="height: 8px;">
      <div class="progress-bar bg-warning" role="progressbar" 
           style="width: <?php echo min(100, $pizza_data['total_progress']); ?>%" 
           aria-valuenow="<?php echo $pizza_data['total_progress']; ?>" 
           aria-valuemin="0" aria-valuemax="100">
      </div>
    </div>
    
    <div class="progress-status mb-3">
      <span class="badge bg-<?php echo $progress_color; ?> px-2 py-1">
        <i class="<?php echo $progress_icon; ?> me-1"></i>
        <?php echo $progress_status; ?>
      </span>
    </div>
    
    <div class="row text-center">
      <div class="col-6">
        <div class="small text-muted">Pizzas Earned</div>
        <div class="fw-bold text-success"><?php echo $pizza_data['pizzas_earned']; ?></div>
      </div>
      <div class="col-6">
        <div class="small text-muted">Active Trackers</div>
        <div class="fw-bold text-info"><?php echo $pizza_data['active_trackers']; ?></div>
      </div>
    </div>
    
    <?php if ($pizza_data['days_to_pizza'] !== 'N/A'): ?>
    <div class="mt-3 text-center">
      <div class="small text-muted">Estimated Days to Pizza</div>
      <div class="fw-bold text-warning"><?php echo $pizza_data['days_to_pizza']; ?></div>
    </div>
    <?php endif; ?>
  </div>
  <div class="card-footer text-end">
    <a href="<?php echo APP_URL; ?>/business/pizza-tracker.php" class="btn btn-outline-warning btn-sm">
      View Tracker
    </a>
  </div>
</div>

<!-- Pizza Tracker Modal -->
<div class="modal fade" id="pizzaTrackerModal" tabindex="-1" aria-labelledby="pizzaTrackerModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="pizzaTrackerModalLabel">
          <i class="bi bi-emoji-smile me-2"></i>Pizza Tracker Overview
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <?php if ($pizza_data['active_trackers'] > 0): ?>
          <div class="row g-4">
            <div class="col-md-6">
              <div class="pizza-metric-card">
                <h6 class="text-warning">
                  <i class="bi bi-target me-2"></i>Current Progress
                </h6>
                <div class="metric-item">
                  <div class="d-flex justify-content-between">
                    <span>Revenue Raised:</span>
                    <strong class="text-success">$<?php echo number_format($pizza_data['current_revenue'], 2); ?></strong>
                  </div>
                </div>
                <div class="metric-item">
                  <div class="d-flex justify-content-between">
                    <span>Goal Amount:</span>
                    <strong class="text-info">$<?php echo number_format($pizza_data['goal_amount'], 2); ?></strong>
                  </div>
                </div>
                <div class="metric-item">
                  <div class="d-flex justify-content-between">
                    <span>Progress:</span>
                    <strong class="text-warning"><?php echo number_format($pizza_data['total_progress'], 1); ?>%</strong>
                  </div>
                </div>
              </div>
            </div>
            
            <div class="col-md-6">
              <div class="pizza-metric-card">
                <h6 class="text-success">
                  <i class="bi bi-trophy me-2"></i>Achievement Stats
                </h6>
                <div class="metric-item">
                  <div class="d-flex justify-content-between">
                    <span>Pizzas Earned:</span>
                    <strong class="text-success"><?php echo $pizza_data['pizzas_earned']; ?></strong>
                  </div>
                </div>
                <div class="metric-item">
                  <div class="d-flex justify-content-between">
                    <span>Active Trackers:</span>
                    <strong class="text-info"><?php echo $pizza_data['active_trackers']; ?></strong>
                  </div>
                </div>
                <?php if ($pizza_data['days_to_pizza'] !== 'N/A'): ?>
                <div class="metric-item">
                  <div class="d-flex justify-content-between">
                    <span>Days to Next Pizza:</span>
                    <strong class="text-warning"><?php echo $pizza_data['days_to_pizza']; ?></strong>
                  </div>
                </div>
                <?php endif; ?>
              </div>
            </div>
          </div>
          
          <?php if ($pizza_data['top_tracker']): ?>
          <div class="mt-4">
            <h6 class="text-primary mb-3">
              <i class="bi bi-graph-up me-2"></i>Top Performing Tracker
            </h6>
            <div class="top-tracker-card">
              <div class="d-flex justify-content-between align-items-center">
                <div>
                  <strong><?php echo htmlspecialchars($pizza_data['top_tracker']['name']); ?></strong>
                  <div class="small text-muted">
                    <?php echo htmlspecialchars($pizza_data['top_tracker']['description'] ?? 'No description'); ?>
                  </div>
                </div>
                <div class="text-end">
                  <div class="fw-bold text-warning">
                    <?php echo number_format($pizza_data['top_tracker']['progress_percent'], 1); ?>%
                  </div>
                  <div class="small text-muted">Progress</div>
                </div>
              </div>
            </div>
          </div>
          <?php endif; ?>
          
        <?php else: ?>
          <div class="text-center py-4">
            <i class="bi bi-emoji-smile display-3 text-muted"></i>
            <h6 class="mt-3">No Pizza Trackers Yet</h6>
            <p class="text-muted">Create a pizza tracker to start earning pizzas with your revenue goals!</p>
            <a href="<?php echo APP_URL; ?>/business/pizza-tracker.php" class="btn btn-warning">
              Create Pizza Tracker
            </a>
          </div>
        <?php endif; ?>
      </div>
      <div class="modal-footer">
        <a href="<?php echo APP_URL; ?>/business/pizza-tracker.php" class="btn btn-primary">
          Manage Trackers
        </a>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<style>
.pizza-tracker-card {
    background: linear-gradient(135deg, rgba(255, 193, 7, 0.1) 0%, rgba(255, 143, 0, 0.1) 100%);
    border: 1px solid rgba(255, 193, 7, 0.3);
}

.pizza-tracker-card:hover {
    border: 1px solid rgba(255, 193, 7, 0.5);
    background: linear-gradient(135deg, rgba(255, 193, 7, 0.15) 0%, rgba(255, 143, 0, 0.15) 100%);
}

.pizza-metric-card {
    background: rgba(255, 255, 255, 0.05);
    border-radius: 8px;
    padding: 1rem;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.top-tracker-card {
    background: rgba(255, 255, 255, 0.05);
    border-radius: 8px;
    padding: 1rem;
    border: 1px solid rgba(255, 193, 7, 0.2);
}

.metric-item {
    padding: 0.5rem 0;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.metric-item:last-child {
    border-bottom: none;
}
</style> 