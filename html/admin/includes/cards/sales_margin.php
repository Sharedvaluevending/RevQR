<div class="card dashboard-card">
  <div class="card-body">
    <div class="card-title">Sales & Margin</div>
    <div class="card-metric" id="sales-metric">--</div>
    <canvas id="salesChart" height="60"></canvas>
  </div>
  <div class="card-footer text-end">
    <button class="btn btn-outline-primary btn-sm" disabled>View Details</button>
  </div>
</div>
<script>
// Placeholder for sales chart
if (window.Chart) {
  new Chart(document.getElementById('salesChart').getContext('2d'), {
    type: 'bar',
    data: { labels: ['Sales','Margin'], datasets: [{ data: [0,0], backgroundColor: ['#198754','#fd7e14'] }] },
    options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
  });
}
</script> 