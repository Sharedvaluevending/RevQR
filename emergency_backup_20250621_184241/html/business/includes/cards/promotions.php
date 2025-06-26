<?php
// Get business ID
$stmt = $pdo->prepare("SELECT b.id FROM businesses b JOIN users u ON b.id = u.business_id WHERE u.id = ?");
$stmt->execute([$_SESSION['user_id']]);
$business = $stmt->fetch();
$business_id = $business ? $business['id'] : 0;

// Enhanced promotion analytics
$promotion_data = [
    'total_promotions' => 0,
    'active_promotions' => 0,
    'total_engagement' => 0,
    'conversion_rate' => 0,
    'top_promotion' => null,
    'weekly_performance' => []
];

try {
    // Get promotion statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_promotions,
            COUNT(CASE WHEN status = 'active' THEN 1 END) as active_promotions,
            COUNT(CASE WHEN end_date < CURDATE() THEN 1 END) as expired_promotions
        FROM promotions 
        WHERE business_id = ?
    ");
    $stmt->execute([$business_id]);
    $stats = $stmt->fetch();
    $promotion_data['total_promotions'] = $stats['total_promotions'] ?? 0;
    $promotion_data['active_promotions'] = $stats['active_promotions'] ?? 0;

    // Get promotion engagement data (using sales data as proxy for engagement)
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT s.id) as total_engagement,
            AVG(s.quantity * s.sale_price) as avg_order_value
        FROM sales s
        WHERE s.business_id = ? 
        AND s.sale_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $stmt->execute([$business_id]);
    $engagement = $stmt->fetch();
    $promotion_data['total_engagement'] = $engagement['total_engagement'] ?? 0;

    // Calculate conversion rate (sales per active promotion)
    if ($promotion_data['active_promotions'] > 0) {
        $promotion_data['conversion_rate'] = round(($promotion_data['total_engagement'] / $promotion_data['active_promotions']), 1);
    }

    // Get weekly performance data for chart (last 7 days)
    $stmt = $pdo->prepare("
        SELECT 
            DATE(s.sale_time) as sale_date,
            COUNT(*) as daily_sales,
            SUM(s.quantity * s.sale_price) as daily_revenue
        FROM sales s
        WHERE s.business_id = ? 
        AND s.sale_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY DATE(s.sale_time)
        ORDER BY sale_date DESC
        LIMIT 7
    ");
    $stmt->execute([$business_id]);
    $weekly_data = $stmt->fetchAll();
    
    // Fill in missing days and reverse for proper chronological order
    $last_7_days = [];
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $found = false;
        foreach ($weekly_data as $day) {
            if ($day['sale_date'] === $date) {
                $last_7_days[] = [
                    'date' => $date,
                    'sales' => $day['daily_sales'],
                    'revenue' => $day['daily_revenue']
                ];
                $found = true;
                break;
            }
        }
        if (!$found) {
            $last_7_days[] = [
                'date' => $date,
                'sales' => 0,
                'revenue' => 0
            ];
        }
    }
    $promotion_data['weekly_performance'] = $last_7_days;

    // Get top performing day
    if (!empty($weekly_data)) {
        $best_day = array_reduce($weekly_data, function($carry, $item) {
            return ($carry === null || $item['daily_sales'] > $carry['daily_sales']) ? $item : $carry;
        });
        $promotion_data['top_promotion'] = date('M j', strtotime($best_day['sale_date']));
    }

} catch (Exception $e) {
    error_log("Promotions analytics error: " . $e->getMessage());
}

// Performance level
$performance_level = 'Getting Started';
$performance_color = 'secondary';
if ($promotion_data['conversion_rate'] >= 15) {
    $performance_level = 'Excellent';
    $performance_color = 'success';
} elseif ($promotion_data['conversion_rate'] >= 10) {
    $performance_level = 'Good';
    $performance_color = 'info';
} elseif ($promotion_data['conversion_rate'] >= 5) {
    $performance_level = 'Fair';
    $performance_color = 'warning';
}
?>

<div class="card dashboard-card promotions-card">
  <div class="card-body">
    <div class="card-title d-flex align-items-center">
      <i class="bi bi-megaphone text-primary me-2 fs-4"></i>
      Promotions
    </div>
    <div class="card-metric" id="promo-metric"><?php echo $promotion_data['total_promotions']; ?></div>
    <div class="small text-muted mb-2">Total Campaigns</div>
    
    <div class="performance-badge mb-3">
      <span class="badge bg-<?php echo $performance_color; ?> px-2 py-1">
        <?php echo $performance_level; ?> Performance
      </span>
    </div>
    
    <!-- Bar Chart for Weekly Performance -->
    <div class="chart-container mb-3">
      <canvas id="promoChart" height="80"></canvas>
    </div>
    
    <div class="row text-center">
      <div class="col-6">
        <div class="small text-muted">Active</div>
        <div class="fw-bold text-success"><?php echo $promotion_data['active_promotions']; ?></div>
      </div>
      <div class="col-6">
        <div class="small text-muted">Engagement</div>
        <div class="fw-bold text-info"><?php echo $promotion_data['conversion_rate']; ?></div>
      </div>
    </div>
  </div>
  <div class="card-footer text-end">
    <a href="<?php echo APP_URL; ?>/business/promotions.php" class="btn btn-outline-primary btn-sm">
      View Details
    </a>
  </div>
</div>

<!-- Promotions Details Modal -->
<div class="modal fade" id="promotionsModal" tabindex="-1" aria-labelledby="promotionsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="promotionsModalLabel">
          <i class="bi bi-megaphone me-2"></i>Promotion Performance Analytics
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="row g-4">
          <div class="col-md-6">
            <div class="promo-metric-card">
              <h6 class="text-primary">
                <i class="bi bi-bar-chart me-2"></i>Campaign Stats
              </h6>
              <div class="metric-item">
                <div class="d-flex justify-content-between">
                  <span>Total Campaigns:</span>
                  <strong class="text-info"><?php echo $promotion_data['total_promotions']; ?></strong>
                </div>
              </div>
              <div class="metric-item">
                <div class="d-flex justify-content-between">
                  <span>Currently Active:</span>
                  <strong class="text-success"><?php echo $promotion_data['active_promotions']; ?></strong>
                </div>
              </div>
              <div class="metric-item">
                <div class="d-flex justify-content-between">
                  <span>Best Day:</span>
                  <strong class="text-warning"><?php echo $promotion_data['top_promotion'] ?? 'N/A'; ?></strong>
                </div>
              </div>
            </div>
          </div>
          
          <div class="col-md-6">
            <div class="promo-metric-card">
              <h6 class="text-success">
                <i class="bi bi-graph-up me-2"></i>Performance
              </h6>
              <div class="metric-item">
                <div class="d-flex justify-content-between">
                  <span>Engagement Score:</span>
                  <strong class="text-<?php echo $performance_color; ?>"><?php echo $promotion_data['conversion_rate']; ?></strong>
                </div>
              </div>
              <div class="metric-item">
                <div class="d-flex justify-content-between">
                  <span>Total Interactions:</span>
                  <strong class="text-info"><?php echo $promotion_data['total_engagement']; ?></strong>
                </div>
              </div>
              <div class="metric-item">
                <div class="d-flex justify-content-between">
                  <span>Performance Level:</span>
                  <strong class="text-<?php echo $performance_color; ?>"><?php echo $performance_level; ?></strong>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <a href="<?php echo APP_URL; ?>/business/promotions.php" class="btn btn-primary">
          Manage Campaigns
        </a>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<style>
.promotions-card {
    background: linear-gradient(135deg, rgba(13, 110, 253, 0.1) 0%, rgba(102, 16, 242, 0.1) 100%);
    border: 1px solid rgba(13, 110, 253, 0.3);
}

.promotions-card:hover {
    border: 1px solid rgba(13, 110, 253, 0.5);
    background: linear-gradient(135deg, rgba(13, 110, 253, 0.15) 0%, rgba(102, 16, 242, 0.15) 100%);
}

.chart-container {
    position: relative;
    height: 80px;
}

.promo-metric-card {
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
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const chartCanvas = document.getElementById('promoChart');
  if (window.Chart && chartCanvas) {
    try {
      // Weekly performance data
      const weeklyData = <?php echo json_encode($promotion_data['weekly_performance']); ?>;
      const labels = weeklyData.map(day => {
        const date = new Date(day.date);
        return date.toLocaleDateString('en-US', { weekday: 'short' });
      });
      const salesData = weeklyData.map(day => day.sales);
      
      new Chart(chartCanvas.getContext('2d'), {
        type: 'bar',
        data: {
          labels: labels,
          datasets: [{
            label: 'Daily Sales',
            data: salesData,
            backgroundColor: 'rgba(13, 110, 253, 0.6)',
            borderColor: 'rgba(13, 110, 253, 1)',
            borderWidth: 1,
            borderRadius: 4,
            borderSkipped: false,
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              display: false
            }
          },
          scales: {
            y: {
              beginAtZero: true,
              display: false
            },
            x: {
              display: true,
              grid: {
                display: false
              },
              ticks: {
                font: {
                  size: 10
                },
                color: 'rgba(255, 255, 255, 0.7)'
              }
            }
          }
        }
      });
    } catch (error) {
      console.log('Chart initialization failed:', error);
    }
  }
});
</script> 