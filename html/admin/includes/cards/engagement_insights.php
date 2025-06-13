<div class="card dashboard-card">
  <div class="card-body">
    <div class="card-title">Engagement Insights</div>
    <div class="card-metric" id="engagement-metric">--</div>
    <canvas id="engagementChart" height="60"></canvas>
  </div>
  <div class="card-footer text-end">
    <button class="btn btn-outline-primary btn-sm" disabled>View Details</button>
  </div>
</div>
<script>
// Placeholder for engagement chart
if (window.Chart) {
  new Chart(document.getElementById('engagementChart').getContext('2d'), {
    type: 'line',
    data: { labels: ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'], datasets: [{ data: [0,0,0,0,0,0,0], borderColor: '#0d6efd', fill: false }] },
    options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
  });
}
</script> 