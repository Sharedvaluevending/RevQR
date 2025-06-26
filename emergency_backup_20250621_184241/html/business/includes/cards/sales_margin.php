<?php
// Get business ID
$stmt = $pdo->prepare("SELECT b.id FROM businesses b JOIN users u ON b.id = u.business_id WHERE u.id = ?");
$stmt->execute([$_SESSION['user_id']]);
$business = $stmt->fetch();
$business_id = $business ? $business['id'] : 0;

// Get total sales in the last 7 days for this business
$stmt = $pdo->prepare("
    SELECT 
        SUM(s.quantity * s.sale_price) as total_sales,
        COUNT(*) as transaction_count,
        AVG(s.sale_price) as avg_price
    FROM sales s
    WHERE s.business_id = ? AND s.sale_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)
");
$stmt->execute([$business_id]);
$salesData = $stmt->fetch();
$totalSales = $salesData['total_sales'] ?? 0;
$transactionCount = $salesData['transaction_count'] ?? 0;
$avgPrice = $salesData['avg_price'] ?? 0;

// Get sales by item for modal - using correct relationship with voting_list_items
$stmt = $pdo->prepare("
    SELECT 
        vli.item_name, 
        SUM(s.quantity * s.sale_price) as sales,
        SUM(s.quantity) as units_sold,
        AVG(s.sale_price) as avg_sale_price
    FROM sales s
    JOIN voting_list_items vli ON s.item_id = vli.id
    WHERE s.business_id = ? AND s.sale_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY vli.id, vli.item_name
    ORDER BY sales DESC
    LIMIT 10
");
$stmt->execute([$business_id]);
$itemSales = $stmt->fetchAll();

// Calculate sales growth (compare to previous 7 days)
$stmt = $pdo->prepare("
    SELECT SUM(s.quantity * s.sale_price) as previous_sales
    FROM sales s
    WHERE s.business_id = ? 
    AND s.sale_time >= DATE_SUB(NOW(), INTERVAL 14 DAY) 
    AND s.sale_time < DATE_SUB(NOW(), INTERVAL 7 DAY)
");
$stmt->execute([$business_id]);
$previousSalesData = $stmt->fetch();
$previousSales = $previousSalesData['previous_sales'] ?? 0;

$growthPercent = 0;
if ($previousSales > 0) {
    $growthPercent = (($totalSales - $previousSales) / $previousSales) * 100;
}
?>
<div class="card dashboard-card">
  <div class="card-body">
    <div class="card-title d-flex align-items-center">
      <i class="bi bi-cash-coin text-success me-2 fs-4"></i>
      Sales (7 Days)
    </div>
    <div class="card-metric" id="sales-metric">$<?php echo number_format($totalSales, 2); ?></div>
    <div class="small text-muted mb-2">
      <?php echo $transactionCount; ?> transactions • Avg: $<?php echo number_format($avgPrice, 2); ?>
    </div>
    <div class="row text-center">
      <div class="col-6">
        <div class="small text-muted">Growth</div>
        <div class="fw-bold text-<?php echo $growthPercent >= 0 ? 'success' : 'danger'; ?>">
          <?php echo $growthPercent >= 0 ? '+' : ''; ?><?php echo number_format($growthPercent, 1); ?>%
        </div>
      </div>
      <div class="col-6">
        <div class="small text-muted">Items Sold</div>
        <div class="fw-bold text-info"><?php echo array_sum(array_column($itemSales, 'units_sold')); ?></div>
      </div>
    </div>
    <?php if ($totalSales > 0): ?>
    <div class="mt-3">
      <canvas id="salesChart" height="60"></canvas>
    </div>
    <?php endif; ?>
  </div>
  <div class="card-footer text-end">
    <a href="/business/analytics/sales.php" class="btn btn-outline-success btn-sm">View Details</a>
  </div>
</div>

<!-- Sales Details Modal -->
<div class="modal fade" id="salesDetailsModal" tabindex="-1" aria-labelledby="salesDetailsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="salesDetailsModalLabel">Sales - Item Breakdown (Last 7 Days)</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <?php if (empty($itemSales)): ?>
          <div class="text-center py-4">
            <i class="bi bi-receipt display-3 text-muted"></i>
            <h6 class="mt-3">No Sales Yet</h6>
            <p class="text-muted">Sales will appear here once recorded</p>
            <a href="/business/manual-sales.php" class="btn btn-success">Record First Sale</a>
          </div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead>
                <tr>
                  <th>Item</th>
                  <th>Units Sold</th>
                  <th>Avg Price</th>
                  <th>Total Sales</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($itemSales as $item): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                    <td><?php echo $item['units_sold']; ?></td>
                    <td>$<?php echo number_format($item['avg_sale_price'], 2); ?></td>
                    <td class="fw-bold">$<?php echo number_format($item['sales'], 2); ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
      <div class="modal-footer">
        <a href="/business/manual-sales.php" class="btn btn-success">Record Sale</a>
        <a href="/business/analytics/sales.php" class="btn btn-primary">Full Analytics</a>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
  const chartCanvas = document.getElementById('salesChart');
  if (window.Chart && chartCanvas) {
    try {
      new Chart(chartCanvas.getContext('2d'), {
        type: 'bar',
        data: {
          labels: ['Sales'],
          datasets: [{
            data: [<?php echo $totalSales; ?>],
            backgroundColor: ['#198754']
          }]
        },
        options: { 
          plugins: { legend: { display: false } }, 
          scales: { 
            y: { 
              beginAtZero: true,
              ticks: {
                color: 'rgba(255, 255, 255, 0.9)'
              }
            },
            x: {
              ticks: {
                color: 'rgba(255, 255, 255, 0.9)'
              }
            }
          },
          responsive: true,
          maintainAspectRatio: false
        }
      });
    } catch (error) {
      console.log('Chart initialization failed:', error);
    }
  }
});
</script> 