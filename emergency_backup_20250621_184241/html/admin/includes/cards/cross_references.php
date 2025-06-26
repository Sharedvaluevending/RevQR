<div class="card dashboard-card">
  <div class="card-body">
    <div class="card-title">Cross-Referenced Insights</div>
    <div class="card-metric" id="cross-metric">--</div>
    <canvas id="crossChart" height="60"></canvas>
  </div>
  <div class="card-footer text-end">
    <button class="btn btn-outline-primary btn-sm" disabled>View Details</button>
  </div>
</div>
<script>
// Placeholder for cross-referenced chart
if (window.Chart) {
  new Chart(document.getElementById('crossChart').getContext('2d'), {
    type: 'line',
    data: { labels: ['Votes','Sales','Engagement'], datasets: [{ data: [0,0,0], borderColor: '#fd7e14', fill: false }] },
    options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
  });
}
</script> 