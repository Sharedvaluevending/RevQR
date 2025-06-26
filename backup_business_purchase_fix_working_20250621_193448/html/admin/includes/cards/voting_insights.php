<?php
require_once __DIR__ . '/../../../core/config.php';

// Get votes in the last 7 days
$stmt = $pdo->prepare("SELECT vote_type, COUNT(*) as count FROM votes WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) GROUP BY vote_type");
$stmt->execute();
$votes = ['vote_in' => 0, 'vote_out' => 0];
foreach ($stmt->fetchAll() as $row) {
    $votes[$row['vote_type']] = (int)$row['count'];
}
$totalVotes = $votes['vote_in'] + $votes['vote_out'];
?>
<div class="card dashboard-card">
  <div class="card-body">
    <div class="card-title">Voting Insights</div>
    <div class="card-metric" id="voting-metric"><?php echo $totalVotes; ?></div>
    <canvas id="votingChart" height="60"></canvas>
  </div>
  <div class="card-footer text-end">
    <button class="btn btn-outline-primary btn-sm" disabled>View Details</button>
  </div>
</div>
<script>
if (window.Chart) {
  new Chart(document.getElementById('votingChart').getContext('2d'), {
    type: 'bar',
    data: {
      labels: ['In', 'Out'],
      datasets: [{
        data: [<?php echo $votes['vote_in']; ?>, <?php echo $votes['vote_out']; ?>],
        backgroundColor: ['#0d6efd','#6c757d']
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