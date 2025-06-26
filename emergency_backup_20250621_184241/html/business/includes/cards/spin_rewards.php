<?php
// Get business ID
$stmt = $pdo->prepare("SELECT b.id FROM businesses b JOIN users u ON b.id = u.business_id WHERE u.id = ?");
$stmt->execute([$_SESSION['user_id']]);
$business = $stmt->fetch();
$business_id = $business ? $business['id'] : 0;

// Enhanced spin rewards analytics - UPDATED TO HANDLE BOTH SPIN SYSTEMS
$spin_data = [
    'total_spins' => 0,
    'business_spins' => 0,
    'user_nav_spins' => 0,
    'big_wins' => 0,
    'business_big_wins' => 0,
    'user_nav_big_wins' => 0,
    'win_rate' => 0,
    'daily_average' => 0,
    'top_day' => null,
    'weekly_trends' => [],
    'rewards_claimed' => 0
];

try {
    // Get BUSINESS QR CODE SPINS (business_id specific, machine_id present)
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as business_spins, 
            SUM(CASE WHEN is_big_win = 1 THEN 1 ELSE 0 END) as business_big_wins
        FROM spin_results s
        WHERE s.business_id = ? 
        AND s.machine_id IS NOT NULL 
        AND s.spin_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    $stmt->execute([$business_id]);
    $businessSpinData = $stmt->fetch();
    $spin_data['business_spins'] = $businessSpinData['business_spins'] ?? 0;
    $spin_data['business_big_wins'] = $businessSpinData['business_big_wins'] ?? 0;
    
    // Get USER NAVIGATION SPINS (business_id NULL, machine_id NULL - general user spins)
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as user_nav_spins, 
            SUM(CASE WHEN is_big_win = 1 THEN 1 ELSE 0 END) as user_nav_big_wins
        FROM spin_results s
        WHERE s.business_id IS NULL 
        AND s.machine_id IS NULL 
        AND s.spin_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    $stmt->execute();
    $userNavSpinData = $stmt->fetch();
    $spin_data['user_nav_spins'] = $userNavSpinData['user_nav_spins'] ?? 0;
    $spin_data['user_nav_big_wins'] = $userNavSpinData['user_nav_big_wins'] ?? 0;
    
    // Calculate totals
    $spin_data['total_spins'] = $spin_data['business_spins'] + $spin_data['user_nav_spins'];
    $spin_data['big_wins'] = $spin_data['business_big_wins'] + $spin_data['user_nav_big_wins'];
    
    // Calculate win rate
    if ($spin_data['total_spins'] > 0) {
        $spin_data['win_rate'] = round(($spin_data['big_wins'] / $spin_data['total_spins']) * 100, 1);
    }
    
    // Calculate daily average
    $spin_data['daily_average'] = round($spin_data['total_spins'] / 7, 1);

    // Get daily spin trends for line chart (last 7 days) - COMBINED DATA
    $stmt = $pdo->prepare("
        SELECT 
            DATE(s.spin_time) as spin_date,
            SUM(CASE WHEN s.business_id = ? AND s.machine_id IS NOT NULL THEN 1 ELSE 0 END) as business_spins,
            SUM(CASE WHEN s.business_id IS NULL AND s.machine_id IS NULL THEN 1 ELSE 0 END) as user_nav_spins,
            COUNT(*) as total_daily_spins,
            SUM(CASE WHEN s.is_big_win = 1 THEN 1 ELSE 0 END) as daily_wins
        FROM spin_results s
        WHERE (
            (s.business_id = ? AND s.machine_id IS NOT NULL) OR 
            (s.business_id IS NULL AND s.machine_id IS NULL)
        )
        AND s.spin_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY DATE(s.spin_time)
        ORDER BY spin_date DESC
        LIMIT 7
    ");
    $stmt->execute([$business_id, $business_id]);
    $weekly_spins = $stmt->fetchAll();
    
    // Fill in missing days and reverse for proper chronological order
    $last_7_days = [];
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $found = false;
        foreach ($weekly_spins as $day) {
            if ($day['spin_date'] === $date) {
                $last_7_days[] = [
                    'date' => $date,
                    'spins' => $day['total_daily_spins'],
                    'business_spins' => $day['business_spins'],
                    'user_nav_spins' => $day['user_nav_spins'],
                    'wins' => $day['daily_wins']
                ];
                $found = true;
                break;
            }
        }
        if (!$found) {
            $last_7_days[] = [
                'date' => $date,
                'spins' => 0,
                'business_spins' => 0,
                'user_nav_spins' => 0,
                'wins' => 0
            ];
        }
    }
    $spin_data['weekly_trends'] = $last_7_days;

    // Get top performing day
    if (!empty($weekly_spins)) {
        $best_day = array_reduce($weekly_spins, function($carry, $item) {
            return ($carry === null || $item['total_daily_spins'] > $carry['total_daily_spins']) ? $item : $carry;
        });
        $spin_data['top_day'] = date('M j', strtotime($best_day['spin_date']));
    }

    // Get rewards claimed (big wins from both systems)
    $spin_data['rewards_claimed'] = $spin_data['big_wins'];

} catch (Exception $e) {
    error_log("Spin rewards analytics error: " . $e->getMessage());
}

// Performance level based on win rate and activity
$performance_level = 'Low Activity';
$performance_color = 'secondary';
if ($spin_data['total_spins'] > 50 && $spin_data['win_rate'] >= 15) {
    $performance_level = 'Hot Streak';
    $performance_color = 'danger';
} elseif ($spin_data['total_spins'] > 30 && $spin_data['win_rate'] >= 10) {
    $performance_level = 'Good Momentum';
    $performance_color = 'success';
} elseif ($spin_data['total_spins'] > 15) {
    $performance_level = 'Building Up';
    $performance_color = 'warning';
} elseif ($spin_data['total_spins'] > 5) {
    $performance_level = 'Getting Started';
    $performance_color = 'info';
}

// Determine primary spin source
$primary_source = 'Mixed';
if ($spin_data['business_spins'] > $spin_data['user_nav_spins'] * 2) {
    $primary_source = 'QR Codes';
} elseif ($spin_data['user_nav_spins'] > $spin_data['business_spins'] * 2) {
    $primary_source = 'Navigation';
}
?>

<div class="card dashboard-card spin-rewards-card">
  <div class="card-body">
    <div class="card-title d-flex align-items-center">
      <i class="bi bi-stars text-warning me-2 fs-4"></i>
      Spin & Rewards
    </div>
    <div class="card-metric" id="spin-metric"><?php echo $spin_data['total_spins']; ?></div>
    <div class="small text-muted mb-2">Total Spins (7 days)</div>
    
    <div class="performance-badge mb-3">
      <span class="badge bg-<?php echo $performance_color; ?> px-2 py-1">
        <i class="bi bi-trophy me-1"></i>
        <?php echo $performance_level; ?>
      </span>
      <?php if ($primary_source !== 'Mixed'): ?>
      <span class="badge bg-info px-2 py-1 ms-1">
        <i class="bi bi-<?php echo $primary_source === 'QR Codes' ? 'qr-code' : 'compass'; ?> me-1"></i>
        <?php echo $primary_source; ?>
      </span>
      <?php endif; ?>
    </div>
    
    <!-- Line Chart for Daily Trends -->
    <div class="chart-container mb-3">
      <canvas id="spinChart" height="80"></canvas>
    </div>
    
    <div class="row text-center">
      <div class="col-4">
        <div class="small text-muted">QR Spins</div>
        <div class="fw-bold text-primary"><?php echo $spin_data['business_spins']; ?></div>
      </div>
      <div class="col-4">
        <div class="small text-muted">Nav Spins</div>
        <div class="fw-bold text-info"><?php echo $spin_data['user_nav_spins']; ?></div>
      </div>
      <div class="col-4">
        <div class="small text-muted">Big Wins</div>
        <div class="fw-bold text-success"><?php echo $spin_data['big_wins']; ?></div>
      </div>
    </div>
  </div>
  <div class="card-footer text-end">
    <a href="<?php echo APP_URL; ?>/business/analytics/rewards.php" class="btn btn-outline-warning btn-sm">
      View Details
    </a>
  </div>
</div>

<!-- Spin Details Modal -->
<div class="modal fade" id="spinDetailsModal" tabindex="-1" aria-labelledby="spinDetailsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="spinDetailsModalLabel">
          <i class="bi bi-stars me-2"></i>Spin & Rewards Analytics (Last 7 Days)
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="row g-4">
          <div class="col-md-6">
            <div class="spin-metric-card">
              <h6 class="text-warning">
                <i class="bi bi-graph-up-arrow me-2"></i>Spin Activity
              </h6>
              <div class="metric-item">
                <div class="d-flex justify-content-between">
                  <span>Total Spins:</span>
                  <strong class="text-info"><?php echo $spin_data['total_spins']; ?></strong>
                </div>
              </div>
              <div class="metric-item">
                <div class="d-flex justify-content-between">
                  <span>QR Code Spins:</span>
                  <strong class="text-primary"><?php echo $spin_data['business_spins']; ?></strong>
                </div>
              </div>
              <div class="metric-item">
                <div class="d-flex justify-content-between">
                  <span>Navigation Spins:</span>
                  <strong class="text-info"><?php echo $spin_data['user_nav_spins']; ?></strong>
                </div>
              </div>
              <div class="metric-item">
                <div class="d-flex justify-content-between">
                  <span>Daily Average:</span>
                  <strong class="text-secondary"><?php echo $spin_data['daily_average']; ?></strong>
                </div>
              </div>
              <div class="metric-item">
                <div class="d-flex justify-content-between">
                  <span>Best Day:</span>
                  <strong class="text-success"><?php echo $spin_data['top_day'] ?? 'N/A'; ?></strong>
                </div>
              </div>
            </div>
          </div>
          
          <div class="col-md-6">
            <div class="spin-metric-card">
              <h6 class="text-success">
                <i class="bi bi-trophy me-2"></i>Win Performance
              </h6>
              <div class="metric-item">
                <div class="d-flex justify-content-between">
                  <span>Total Big Wins:</span>
                  <strong class="text-warning"><?php echo $spin_data['big_wins']; ?></strong>
                </div>
              </div>
              <div class="metric-item">
                <div class="d-flex justify-content-between">
                  <span>QR Code Wins:</span>
                  <strong class="text-primary"><?php echo $spin_data['business_big_wins']; ?></strong>
                </div>
              </div>
              <div class="metric-item">
                <div class="d-flex justify-content-between">
                  <span>Navigation Wins:</span>
                  <strong class="text-info"><?php echo $spin_data['user_nav_big_wins']; ?></strong>
                </div>
              </div>
              <div class="metric-item">
                <div class="d-flex justify-content-between">
                  <span>Win Rate:</span>
                  <strong class="text-<?php echo $performance_color; ?>"><?php echo $spin_data['win_rate']; ?>%</strong>
                </div>
              </div>
              <div class="metric-item">
                <div class="d-flex justify-content-between">
                  <span>Performance:</span>
                  <strong class="text-<?php echo $performance_color; ?>"><?php echo $performance_level; ?></strong>
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <div class="mt-4">
          <h6 class="text-info mb-3">
            <i class="bi bi-graph-up me-2"></i>Spin System Analysis
          </h6>
          <div class="trend-insights">
            <?php if ($spin_data['business_spins'] > 0 && $spin_data['user_nav_spins'] > 0): ?>
              <div class="insight-item bg-success bg-opacity-10 border-success mb-2">
                <i class="bi bi-check-circle text-success me-2"></i>
                Great! Both QR code and navigation spins are active. Your engagement strategy is working across multiple touchpoints.
              </div>
            <?php elseif ($spin_data['business_spins'] > 0): ?>
              <div class="insight-item bg-info bg-opacity-10 border-info mb-2">
                <i class="bi bi-qr-code text-info me-2"></i>
                QR code spins are driving engagement. Consider promoting your QR codes more to increase activity.
              </div>
            <?php elseif ($spin_data['user_nav_spins'] > 0): ?>
              <div class="insight-item bg-warning bg-opacity-10 border-warning mb-2">
                <i class="bi bi-compass text-warning me-2"></i>
                Navigation spins are active. Set up QR code campaigns to capture more business-specific engagement.
              </div>
            <?php else: ?>
              <div class="insight-item bg-secondary bg-opacity-10 border-secondary mb-2">
                <i class="bi bi-play text-secondary me-2"></i>
                Ready to launch! Set up your spin wheel campaigns to start engaging customers.
              </div>
            <?php endif; ?>
            
            <?php if ($spin_data['win_rate'] >= 15): ?>
              <div class="insight-item bg-success bg-opacity-10 border-success">
                <i class="bi bi-trophy text-success me-2"></i>
                Excellent win rate keeping customers engaged and coming back for more spins!
              </div>
            <?php elseif ($spin_data['win_rate'] >= 10): ?>
              <div class="insight-item bg-info bg-opacity-10 border-info">
                <i class="bi bi-target text-info me-2"></i>
                Good win rate. Consider adjusting prize distribution to optimize engagement.
              </div>
            <?php endif; ?>
            
            <?php if ($spin_data['total_spins'] > 100): ?>
              <div class="insight-item bg-warning bg-opacity-10 border-warning">
                <i class="bi bi-fire text-warning me-2"></i>
                High activity! Your spin wheels are a major engagement driver.
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <a href="<?php echo APP_URL; ?>/business/analytics/rewards.php" class="btn btn-primary">
          View Full Analytics
        </a>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<style>
.spin-rewards-card {
    background: linear-gradient(135deg, rgba(255, 193, 7, 0.1) 0%, rgba(255, 143, 0, 0.1) 100%);
    border: 1px solid rgba(255, 193, 7, 0.3);
}

.spin-rewards-card:hover {
    border: 1px solid rgba(255, 193, 7, 0.5);
    background: linear-gradient(135deg, rgba(255, 193, 7, 0.15) 0%, rgba(255, 143, 0, 0.15) 100%);
}

.chart-container {
    position: relative;
    height: 80px;
}

.spin-metric-card {
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
}
</style>

<script>
// Enhanced chart with both spin types
document.addEventListener('DOMContentLoaded', function() {
    const spinChartCanvas = document.getElementById('spinChart');
    if (spinChartCanvas && window.Chart) {
        const weeklyData = <?php echo json_encode($spin_data['weekly_trends']); ?>;
        
        const labels = weeklyData.map(day => {
            const date = new Date(day.date);
            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
        });
        
        const businessSpins = weeklyData.map(day => day.business_spins);
        const userNavSpins = weeklyData.map(day => day.user_nav_spins);
        const totalSpins = weeklyData.map(day => day.spins);
        
        new Chart(spinChartCanvas.getContext('2d'), {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'QR Code Spins',
                        data: businessSpins,
                        borderColor: '#0d6efd',
                        backgroundColor: 'rgba(13, 110, 253, 0.1)',
                        borderWidth: 2,
                        fill: false,
                        tension: 0.4
                    },
                    {
                        label: 'Navigation Spins',
                        data: userNavSpins,
                        borderColor: '#0dcaf0',
                        backgroundColor: 'rgba(13, 202, 240, 0.1)',
                        borderWidth: 2,
                        fill: false,
                        tension: 0.4
                    },
                    {
                        label: 'Total Spins',
                        data: totalSpins,
                        borderColor: '#ffc107',
                        backgroundColor: 'rgba(255, 193, 7, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4
                    }
                ]
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
                    x: {
                        display: false
                    },
                    y: {
                        display: false,
                        beginAtZero: true
                    }
                },
                elements: {
                    point: {
                        radius: 3,
                        hoverRadius: 6
                    }
                }
            }
        });
    }
});
</script> 