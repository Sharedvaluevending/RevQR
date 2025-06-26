<?php
// Get current system metrics
$uptime = shell_exec('uptime');
$load_avg = trim(shell_exec("uptime | awk -F'load average:' '{print $2}' | awk '{print $1}' | sed 's/,//'"));

// Memory usage
$mem_info = shell_exec('free');
preg_match('/Mem:\s+(\d+)\s+(\d+)/', $mem_info, $mem_matches);
$mem_total = $mem_matches[1] ?? 1;
$mem_used = $mem_matches[2] ?? 0;
$mem_percent = round(($mem_used / $mem_total) * 100, 1);

// Get recent alerts count
try {
    $alert_count = 0;
    if (file_exists('/var/log/system_alerts.log')) {
        $alerts = shell_exec("tail -50 /var/log/system_alerts.log | grep '$(date +%Y-%m-%d)' | wc -l");
        $alert_count = intval(trim($alerts));
    }
} catch (Exception $e) {
    $alert_count = 0;
}

// Determine health status
$health_status = 'good';
$health_color = 'success';
if ($load_avg > 2.0 || $mem_percent > 90 || $alert_count > 5) {
    $health_status = 'critical';
    $health_color = 'danger';
} elseif ($load_avg > 1.0 || $mem_percent > 80 || $alert_count > 2) {
    $health_status = 'warning';
    $health_color = 'warning';
}

// Apache processes
$apache_processes = intval(trim(shell_exec("ps aux | grep apache2 | grep -v grep | wc -l")));
?>
<div class="card dashboard-card">
  <div class="card-body">
    <div class="card-title d-flex justify-content-between align-items-center">
      <span>System Health</span>
      <span class="badge bg-<?php echo $health_color; ?>"><?php echo ucfirst($health_status); ?></span>
    </div>
    <div class="row mt-3">
      <div class="col-6">
        <small class="text-muted">Load Avg</small>
        <div class="fw-bold"><?php echo $load_avg; ?></div>
      </div>
      <div class="col-6">
        <small class="text-muted">Memory</small>
        <div class="fw-bold"><?php echo $mem_percent; ?>%</div>
      </div>
    </div>
    <div class="row mt-2">
      <div class="col-6">
        <small class="text-muted">Apache</small>
        <div class="fw-bold"><?php echo $apache_processes; ?> proc</div>
      </div>
      <div class="col-6">
        <small class="text-muted">Alerts Today</small>
        <div class="fw-bold <?php echo $alert_count > 0 ? 'text-warning' : 'text-success'; ?>">
          <?php echo $alert_count; ?>
        </div>
      </div>
    </div>
    <canvas id="systemHealthChart" height="60"></canvas>
  </div>
  <div class="card-footer text-end">
    <a href="system-monitor.php" class="btn btn-outline-primary btn-sm">View Details</a>
  </div>
</div>
<script>
if (window.Chart) {
  // Create a simple gauge-like chart for system health
  const healthValue = <?php echo ($health_status === 'good') ? 85 : (($health_status === 'warning') ? 60 : 25); ?>;
  new Chart(document.getElementById('systemHealthChart').getContext('2d'), {
    type: 'doughnut',
    data: {
      labels: ['Health', 'Issues'],
      datasets: [{
        data: [healthValue, 100 - healthValue],
        backgroundColor: ['<?php echo $health_color === "success" ? "#198754" : ($health_color === "warning" ? "#ffc107" : "#dc3545"); ?>', '#e9ecef'],
        borderWidth: 0
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { display: false }
      },
      cutout: '70%'
    }
  });
}
</script> 