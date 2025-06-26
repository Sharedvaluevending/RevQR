<?php

// Get business ID
$stmt = $pdo->prepare("SELECT b.id FROM businesses b JOIN users u ON b.id = u.business_id WHERE u.id = ?");
$stmt->execute([$_SESSION['user_id']]);
$business = $stmt->fetch();
$business_id = $business ? $business['id'] : 0;

// Get votes in the last 7 days for this business
// Note: votes table references machine_id, and we need to check if machines table exists
// If not, we'll use voting_lists as machines
$stmt = $pdo->prepare("
    SELECT v.vote_type, COUNT(*) as count
    FROM votes v
    JOIN machines m ON v.machine_id = m.id
    WHERE m.business_id = ? AND v.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY v.vote_type
");
$stmt->execute([$business_id]);
$votes = ['vote_in' => 0, 'vote_out' => 0];
foreach ($stmt->fetchAll() as $row) {
    $votes[$row['vote_type']] = (int)$row['count'];
}
$totalVotes = $votes['vote_in'] + $votes['vote_out'];

// Get vote trend (compare to previous 7 days)
$stmt = $pdo->prepare("
    SELECT COUNT(*) as previous_votes
    FROM votes v
    JOIN machines m ON v.machine_id = m.id
    WHERE m.business_id = ? 
    AND v.created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY) 
    AND v.created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)
");
$stmt->execute([$business_id]);
$previousVotesData = $stmt->fetch();
$previousVotes = $previousVotesData['previous_votes'] ?? 0;

$voteGrowth = 0;
if ($previousVotes > 0) {
    $voteGrowth = (($totalVotes - $previousVotes) / $previousVotes) * 100;
}

// Get votes by item for modal - using items table since votes.item_id references items.id
$stmt = $pdo->prepare("
    SELECT 
        i.name, 
        SUM(CASE WHEN v.vote_type = 'vote_in' THEN 1 ELSE 0 END) as in_votes, 
        SUM(CASE WHEN v.vote_type = 'vote_out' THEN 1 ELSE 0 END) as out_votes,
        COUNT(*) as total_votes
    FROM votes v
    JOIN items i ON v.item_id = i.id
    JOIN machines m ON v.machine_id = m.id
    WHERE m.business_id = ? AND v.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY i.id, i.name
    ORDER BY total_votes DESC, in_votes DESC
    LIMIT 10
");
$stmt->execute([$business_id]);
$itemVotes = $stmt->fetchAll();

// Calculate engagement metrics
$engagementRate = $totalVotes > 0 ? ($votes['vote_in'] / $totalVotes) * 100 : 0;
?>
<div class="card dashboard-card">
  <div class="card-body">
    <div class="card-title d-flex align-items-center">
      <i class="bi bi-bar-chart-fill text-primary me-2 fs-4"></i>
      Voting Insights
    </div>
    <div class="card-metric" id="voting-metric"><?php echo number_format($totalVotes); ?></div>
    <div class="small text-muted mb-2">Votes in last 7 days</div>
    <div class="row text-center">
      <div class="col-4">
        <div class="small text-muted">In Votes</div>
        <div class="fw-bold text-success"><?php echo $votes['vote_in']; ?></div>
      </div>
      <div class="col-4">
        <div class="small text-muted">Out Votes</div>
        <div class="fw-bold text-warning"><?php echo $votes['vote_out']; ?></div>
      </div>
      <div class="col-4">
        <div class="small text-muted">Growth</div>
        <div class="fw-bold text-<?php echo $voteGrowth >= 0 ? 'success' : 'danger'; ?>">
          <?php echo $voteGrowth >= 0 ? '+' : ''; ?><?php echo number_format($voteGrowth, 1); ?>%
        </div>
      </div>
    </div>
    <?php if ($totalVotes > 0): ?>
    <div class="mt-3">
      <canvas id="votingChart" height="60"></canvas>
    </div>
    <?php endif; ?>
  </div>
  <div class="card-footer text-end">
    <a href="/business/view-votes.php" class="btn btn-outline-primary btn-sm">View Details</a>
  </div>
</div>

<!-- Voting Details Modal -->
<div class="modal fade" id="votingDetailsModal" tabindex="-1" aria-labelledby="votingDetailsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="votingDetailsModalLabel">Voting Insights - Item Breakdown (Last 7 Days)</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <?php if (empty($itemVotes)): ?>
          <div class="text-center py-4">
            <i class="bi bi-bar-chart display-3 text-muted"></i>
            <h6 class="mt-3">No Votes Yet</h6>
            <p class="text-muted">Voting data will appear here once campaigns are active</p>
            <a href="/business/manage-campaigns.php" class="btn btn-primary">Create Campaign</a>
          </div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead>
                <tr>
                  <th>Item</th>
                  <th>In Votes</th>
                  <th>Out Votes</th>
                  <th>Total</th>
                  <th>Preference</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($itemVotes as $item): ?>
                  <?php 
                  $preferencePercent = $item['total_votes'] > 0 ? ($item['in_votes'] / $item['total_votes']) * 100 : 0;
                  $preferenceColor = $preferencePercent >= 60 ? 'success' : ($preferencePercent >= 40 ? 'warning' : 'danger');
                  ?>
                  <tr>
                    <td><?php echo htmlspecialchars($item['name']); ?></td>
                    <td><span class="badge bg-success"><?php echo $item['in_votes']; ?></span></td>
                    <td><span class="badge bg-warning"><?php echo $item['out_votes']; ?></span></td>
                    <td class="fw-bold"><?php echo $item['total_votes']; ?></td>
                    <td>
                      <span class="badge bg-<?php echo $preferenceColor; ?>">
                        <?php echo number_format($preferencePercent, 1); ?>% positive
                      </span>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
      <div class="modal-footer">
        <a href="/business/view-votes.php" class="btn btn-primary">View All Votes</a>
        <a href="/business/manage-campaigns.php" class="btn btn-success">Manage Campaigns</a>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
<script>
// Safe Chart.js initialization with proper error checking
document.addEventListener('DOMContentLoaded', function() {
    // Only try to create chart if Chart.js is loaded and canvas element exists
    const chartCanvas = document.getElementById('votingChart');
    if (window.Chart && chartCanvas) {
        try {
            new Chart(chartCanvas.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: ['In', 'Out'],
                    datasets: [{
                        data: [<?php echo $votes['vote_in']; ?>, <?php echo $votes['vote_out']; ?>],
                        backgroundColor: ['#198754','#ffc107']
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