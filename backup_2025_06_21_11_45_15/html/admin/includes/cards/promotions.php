<div class="card dashboard-card">
  <div class="card-body">
    <div class="card-title">Promotions</div>
    <div class="card-metric" id="promo-metric">--</div>
    <canvas id="promoChart" height="60"></canvas>
  </div>
  <div class="card-footer text-end">
    <button class="btn btn-outline-primary btn-sm" disabled>View Details</button>
  </div>
</div>
<script>
// Placeholder for promotions chart
if (window.Chart) {
  new Chart(document.getElementById('promoChart').getContext('2d'), {
    type: 'pie',
    data: { labels: ['Active','Used','Expired'], datasets: [{ data: [0,0,0], backgroundColor: ['#0d6efd','#198754','#6c757d'] }] },
    options: { plugins: { legend: { display: true } } }
  });
}
</script> 