<?php

// Get business ID
$stmt = $pdo->prepare("SELECT b.id FROM businesses b JOIN users u ON b.id = u.business_id WHERE u.id = ?");
$stmt->execute([$_SESSION['user_id']]);
$business = $stmt->fetch();
$business_id = $business ? $business['id'] : 0;

// Get total scans and unique scanners in the last 7 days for this business (using qr_code_stats)
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total_scans, COUNT(DISTINCT qcs.ip_address) as unique_scanners
    FROM qr_code_stats qcs
    JOIN qr_codes qr ON qcs.qr_code_id = qr.id
    WHERE qr.business_id = ? AND qcs.scan_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)
");
$stmt->execute([$business_id]);
$engagement = $stmt->fetch();
$totalScans = $engagement['total_scans'] ?? 0;
$uniqueScanners = $engagement['unique_scanners'] ?? 0;

// Get scans per day for chart
$stmt = $pdo->prepare("
    SELECT DATE(qcs.scan_time) as scan_date, COUNT(*) as scans
    FROM qr_code_stats qcs
    JOIN qr_codes qr ON qcs.qr_code_id = qr.id
    WHERE qr.business_id = ? AND qcs.scan_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY scan_date
    ORDER BY scan_date ASC
");
$stmt->execute([$business_id]);
$scansPerDay = $stmt->fetchAll();
$labels = [];
$data = [];
foreach ($scansPerDay as $row) {
    $labels[] = $row['scan_date'];
    $data[] = (int)$row['scans'];
}

// Get scans by QR code type for modal
$stmt = $pdo->prepare("
    SELECT qr.qr_type as qr_type, COUNT(*) as scans, COUNT(DISTINCT qcs.ip_address) as unique_scanners
    FROM qr_code_stats qcs
    JOIN qr_codes qr ON qcs.qr_code_id = qr.id
    WHERE qr.business_id = ? AND qcs.scan_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY qr.qr_type
    ORDER BY scans DESC
    LIMIT 10
");
$stmt->execute([$business_id]);
$machineScans = $stmt->fetchAll();
?>
<div class="card dashboard-card">
  <div class="card-body">
    <div class="card-title d-flex align-items-center">
      <i class="bi bi-qr-code-scan text-info me-2 fs-4"></i>
      Engagement Insights
    </div>
    <div class="card-metric" id="engagement-metric"><?php echo $totalScans; ?></div>
    <div class="small text-muted mb-2">QR scans in last 7 days</div>
    <canvas id="engagementChart" height="60"></canvas>
  </div>
  <div class="card-footer text-end">
    <a href="analytics/engagement.php" class="btn btn-outline-info btn-sm">View Details</a>
  </div>
</div>

<!-- Engagement Details Modal -->
<div class="modal fade" id="engagementDetailsModal" tabindex="-1" aria-labelledby="engagementDetailsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="engagementDetailsModalLabel">Engagement Insights - QR Code Breakdown (Last 7 Days)</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3"><strong>Total Unique Scanners:</strong> <?php echo $uniqueScanners; ?></div>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead>
              <tr>
                  <th>QR Code Type</th>
                <th>Scans</th>
                <th>Unique Scanners</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($machineScans as $machine): ?>
                <tr>
                    <td><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $machine['qr_type']))); ?></td>
                  <td><?php echo $machine['scans']; ?></td>
                  <td><?php echo $machine['unique_scanners']; ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
<script>
if (window.Chart) {
  new Chart(document.getElementById('engagementChart').getContext('2d'), {
    type: 'line',
    data: {
      labels: <?php echo json_encode($labels); ?>,
      datasets: [{
        data: <?php echo json_encode($data); ?>,
        borderColor: '#0dcaf0',
        backgroundColor: 'rgba(13,202,240,0.1)',
        fill: true,
        tension: 0.4
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
      } 
    }
  });
}
</script> 