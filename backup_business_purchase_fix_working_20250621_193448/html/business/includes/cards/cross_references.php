<?php
// Get business ID using the same method as other cards
$stmt = $pdo->prepare("SELECT b.id FROM businesses b JOIN users u ON b.id = u.business_id WHERE u.id = ?");
$stmt->execute([$_SESSION['user_id']]);
$business = $stmt->fetch();
$business_id = $business ? $business['id'] : 0;

try {
    // Get votes and sales data with proper relationships
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT v.id) as total_votes,
            COUNT(DISTINCT s.id) as total_sales,
            COUNT(DISTINCT DATE(v.created_at)) as vote_days,
            COUNT(DISTINCT DATE(s.sale_time)) as sales_days,
            ROUND(
                CASE 
                    WHEN COUNT(DISTINCT v.id) > 0 
                    THEN (COUNT(DISTINCT s.id) * 100.0 / COUNT(DISTINCT v.id))
                    ELSE 0 
                END, 1
            ) as vote_to_sales_ratio,
            COALESCE(SUM(s.quantity * s.sale_price), 0) as total_revenue
        FROM votes v
        JOIN machines m ON v.machine_id = m.id
        LEFT JOIN sales s ON s.business_id = m.business_id 
            AND DATE(v.created_at) = DATE(s.sale_time)
        WHERE m.business_id = ?
        AND v.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $stmt->execute([$business_id]);
    $cross_stats = $stmt->fetch();
    
    // Calculate engagement metrics
    $totalVotes = $cross_stats['total_votes'] ?? 0;
    $totalSales = $cross_stats['total_sales'] ?? 0;
    $totalRevenue = $cross_stats['total_revenue'] ?? 0;
    $voteDays = $cross_stats['vote_days'] ?? 0;
    $salesDays = $cross_stats['sales_days'] ?? 0;
    
    // Calculate conversion rate (votes to sales)
    $conversionRate = $totalVotes > 0 ? round(($totalSales / $totalVotes) * 100, 1) : 0;
    
    // Calculate activity correlation (days with both votes and sales vs just votes)
    $activityCorrelation = $voteDays > 0 ? round(($salesDays / $voteDays) * 100, 1) : 0;
    
    $metric_value = $conversionRate . '%';
    $chart_data = [
        ['label' => 'Votes', 'value' => $totalVotes],
        ['label' => 'Sales', 'value' => $totalSales],
        ['label' => 'Correlation', 'value' => $activityCorrelation]
    ];
    
} catch (Exception $e) {
    error_log("Cross-references error: " . $e->getMessage());
    $metric_value = '0%';
    $totalVotes = 0;
    $totalSales = 0;
    $totalRevenue = 0;
    $conversionRate = 0;
    $activityCorrelation = 0;
    $chart_data = [
        ['label' => 'Votes', 'value' => 0],
        ['label' => 'Sales', 'value' => 0],
        ['label' => 'Correlation', 'value' => 0]
    ];
}
?>

<div class="card dashboard-card h-100">
    <div class="card-body d-flex flex-column">
        <div class="d-flex justify-content-between align-items-start mb-2">
            <div class="card-title">Cross-Referenced Insights</div>
            <i class="bi bi-graph-up-arrow text-info"></i>
        </div>
        <div class="card-metric text-info mb-2" id="cross-metric"><?php echo $metric_value; ?></div>
        <small class="text-muted mb-3">Vote-to-Sales conversion (30 days)</small>
        
        <div class="row text-center">
            <div class="col-4">
                <div class="small text-muted">Votes</div>
                <div class="fw-bold text-primary"><?php echo number_format($totalVotes); ?></div>
            </div>
            <div class="col-4">
                <div class="small text-muted">Sales</div>
                <div class="fw-bold text-success"><?php echo number_format($totalSales); ?></div>
            </div>
            <div class="col-4">
                <div class="small text-muted">Revenue</div>
                <div class="fw-bold text-warning">$<?php echo number_format($totalRevenue, 0); ?></div>
            </div>
        </div>
        
        <?php if ($totalVotes > 0 || $totalSales > 0): ?>
        <div class="mt-3">
            <canvas id="crossChart" height="60"></canvas>
        </div>
        <?php endif; ?>
    </div>
    <div class="card-footer text-end">
        <a href="/business/cross_references_details.php" class="btn btn-outline-info btn-sm">
            <i class="bi bi-eye me-1"></i>View Details
        </a>
    </div>
</div>

<!-- Cross-Referenced Details Modal -->
<div class="modal fade" id="crossReferencesModal" tabindex="-1" aria-labelledby="crossReferencesModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="crossReferencesModalLabel">Cross-Referenced Insights</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="row">
          <div class="col-md-6">
            <h6>Engagement Metrics</h6>
            <div class="table-responsive">
              <table class="table table-sm">
                <tbody>
                  <tr>
                    <td>Total Votes</td>
                    <td class="fw-bold text-primary"><?php echo number_format($totalVotes); ?></td>
                  </tr>
                  <tr>
                    <td>Total Sales</td>
                    <td class="fw-bold text-success"><?php echo number_format($totalSales); ?></td>
                  </tr>
                  <tr>
                    <td>Conversion Rate</td>
                    <td class="fw-bold text-info"><?php echo $conversionRate; ?>%</td>
                  </tr>
                  <tr>
                    <td>Activity Correlation</td>
                    <td class="fw-bold text-warning"><?php echo $activityCorrelation; ?>%</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
          <div class="col-md-6">
            <h6>Performance Summary</h6>
            <p class="small text-muted">
              <?php if ($totalVotes > 0 && $totalSales > 0): ?>
                <span class="badge bg-success">Active Engagement</span><br>
                Your voting campaigns are generating both user engagement and sales conversions.
                <?php if ($conversionRate >= 10): ?>
                  Excellent conversion rate of <?php echo $conversionRate; ?>%!
                <?php elseif ($conversionRate >= 5): ?>
                  Good conversion rate of <?php echo $conversionRate; ?>%.
                <?php else: ?>
                  Room to improve conversion rate (<?php echo $conversionRate; ?>%).
                <?php endif; ?>
              <?php elseif ($totalVotes > 0): ?>
                <span class="badge bg-warning">Votes Only</span><br>
                You have voting engagement but no recorded sales. Consider using the Manual Sales Entry to track transactions.
              <?php elseif ($totalSales > 0): ?>
                <span class="badge bg-info">Sales Only</span><br>
                You have sales but no voting data. Consider creating voting campaigns to boost engagement.
              <?php else: ?>
                <span class="badge bg-secondary">No Activity</span><br>
                Start by creating voting campaigns and recording sales to see cross-reference insights.
              <?php endif; ?>
            </p>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <a href="/business/manage-campaigns.php" class="btn btn-primary">Create Campaign</a>
        <a href="/business/manual-sales.php" class="btn btn-success">Record Sales</a>
        <a href="/business/cross_references_details.php" class="btn btn-info">Full Analysis</a>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script>
// Cross-referenced insights chart
document.addEventListener('DOMContentLoaded', function() {
    const chartCanvas = document.getElementById('crossChart');
    if (window.Chart && chartCanvas) {
        try {
            const chartData = <?php echo json_encode($chart_data); ?>;
            
            new Chart(chartCanvas.getContext('2d'), {
                type: 'line',
                data: { 
                    labels: chartData.map(d => d.label),
                    datasets: [{
                        data: chartData.map(d => d.value),
                        borderColor: '#17a2b8',
                        backgroundColor: 'rgba(23, 162, 184, 0.1)',
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: { 
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { 
                        legend: { display: false }
                    },
                    scales: { 
                        x: { 
                            display: false,
                            grid: { display: false }
                        },
                        y: { 
                            display: false,
                            beginAtZero: true,
                            grid: { display: false }
                        }
                    },
                    elements: {
                        point: { radius: 1 }
                    },
                    layout: {
                        padding: 0
                    },
                    animation: {
                        duration: 0
                    }
                }
            });
        } catch (error) {
            console.log('Chart initialization failed:', error);
        }
    }
});
</script> 