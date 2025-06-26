<div class="card dashboard-card">
  <div class="card-body">
    <div class="card-title">Spin & Rewards</div>
    <div class="card-metric" id="spin-metric">--</div>
    <canvas id="spinChart" height="60"></canvas>
  </div>
  <div class="card-footer text-end">
    <button class="btn btn-outline-primary btn-sm" disabled>View Details</button>
  </div>
</div>
<script>
// Placeholder for spin chart
if (window.Chart) {
  new Chart(document.getElementById('spinChart').getContext('2d'), {
    type: 'doughnut',
    data: { labels: ['Big Win','Other'], datasets: [{ data: [0,0], backgroundColor: ['#ffc107','#0d6efd'] }] },
    options: { plugins: { legend: { display: true } } }
  });
}
</script> 