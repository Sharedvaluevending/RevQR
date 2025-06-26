<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';

// Require business role
require_role('business');

// Get campaign ID
$campaign_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$campaign_id) {
    header('Location: manage-campaigns.php');
    exit;
}

// Get business ID
$stmt = $pdo->prepare("SELECT id FROM businesses WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$business = $stmt->fetch();

if (!$business) {
    header('Location: manage-campaigns.php');
    exit;
}

// Get campaign details
$stmt = $pdo->prepare("
    SELECT q.*, 
           m.name as machine_name,
           (SELECT COUNT(*) FROM votes v WHERE v.qr_code_id = q.id) as vote_count
    FROM qr_codes q
    JOIN machines m ON q.machine_id = m.id
    WHERE q.id = ? AND m.business_id = ?
");
$stmt->execute([$campaign_id, $business['id']]);
$campaign = $stmt->fetch();

if (!$campaign) {
    header('Location: manage-campaigns.php');
    exit;
}

// Get QR code statistics
$stmt = $pdo->prepare("
    SELECT 
        q.id,
        q.code,
        m.name as machine_name,
        COUNT(DISTINCT v.id) as vote_count,
        MAX(v.created_at) as last_vote
    FROM qr_codes q
    JOIN machines m ON q.machine_id = m.id
    LEFT JOIN votes v ON q.id = v.qr_code_id
    WHERE q.id = ?
    GROUP BY q.id, q.code, m.name
");
$stmt->execute([$campaign_id]);
$qr_stats = $stmt->fetchAll();

// Get daily vote statistics for the last 30 days
$stmt = $pdo->prepare("
    SELECT 
        DATE(v.created_at) as vote_date,
        COUNT(*) as vote_count
    FROM votes v
    WHERE v.qr_code_id = ? 
    AND v.created_at >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY)
    GROUP BY DATE(v.created_at)
    ORDER BY vote_date
");
$stmt->execute([$campaign_id]);
$daily_votes = $stmt->fetchAll();

require_once __DIR__ . '/../core/includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="h3 mb-0">Campaign Statistics</h1>
                <p class="text-muted">Analytics for <?php echo htmlspecialchars(json_decode($campaign['meta'], true)['name']); ?></p>
                <p>
                    <strong>QR Type:</strong> <?php echo ucfirst($campaign['qr_type']); ?>
                    <br><strong>Machine:</strong> <?php echo htmlspecialchars($campaign['machine_name']); ?>
                </p>
            </div>
            <a href="manage-campaigns.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-2"></i>Back to Campaigns
            </a>
        </div>
    </div>
</div>

<!-- Campaign Overview -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Total Votes</h5>
                <h2 class="mb-0"><?php echo $campaign['vote_count']; ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Status</h5>
                <h2 class="mb-0">
                    <span class="badge bg-<?php echo $campaign['status'] === 'active' ? 'success' : 'danger'; ?>">
                        <?php echo ucfirst($campaign['status']); ?>
                    </span>
                </h2>
            </div>
        </div>
    </div>
</div>

<!-- QR Code Statistics -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">QR Code Performance</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>QR Code</th>
                                <th>Machine</th>
                                <th>Votes</th>
                                <th>Last Vote</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($qr_stats as $qr): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($qr['code']); ?></td>
                                    <td><?php echo htmlspecialchars($qr['machine_name']); ?></td>
                                    <td><?php echo $qr['vote_count']; ?></td>
                                    <td>
                                        <?php 
                                        echo $qr['last_vote'] 
                                            ? date('M d, Y H:i', strtotime($qr['last_vote']))
                                            : 'Never';
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Activity Charts -->
<div class="row">
    <div class="col-12 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Daily Votes (Last 30 Days)</h5>
            </div>
            <div class="card-body">
                <canvas id="votesChart"></canvas>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Prepare data for the votes chart
    const voteData = <?php echo json_encode($daily_votes); ?>;
    const dates = voteData.map(item => item.vote_date);
    const counts = voteData.map(item => item.vote_count);

    // Create the votes chart
    new Chart(document.getElementById('votesChart'), {
        type: 'line',
        data: {
            labels: dates,
            datasets: [{
                label: 'Votes',
                data: counts,
                borderColor: 'rgb(75, 192, 192)',
                tension: 0.1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });
});
</script>

<?php require_once __DIR__ . '/../core/includes/footer.php'; ?> 