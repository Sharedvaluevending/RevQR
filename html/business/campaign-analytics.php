<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';

// Require business role
require_role('business');

// Get business ID
$stmt = $pdo->prepare("SELECT id FROM businesses WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$business = $stmt->fetch();

if (!$business) {
    header('Location: manage-campaigns.php');
    exit;
}

// Get campaign statistics
$stmt = $pdo->prepare("
    SELECT 
        c.id,
        c.name,
        c.status,
        c.created_at,
        COUNT(DISTINCT qr.id) as qr_count,
        COUNT(DISTINCT v.id) as total_votes,
        MAX(v.created_at) as last_vote
    FROM campaigns c
    LEFT JOIN qr_codes qr ON c.id = qr.campaign_id
    LEFT JOIN votes v ON qr.id = v.qr_code_id
    WHERE c.business_id = ?
    GROUP BY c.id, c.name, c.status, c.created_at
    ORDER BY c.created_at DESC
");
$stmt->execute([$business['id']]);
$campaigns = $stmt->fetchAll();

// Get daily vote statistics for all campaigns
$stmt = $pdo->prepare("
    SELECT 
        DATE(v.created_at) as vote_date,
        COUNT(*) as vote_count
    FROM votes v
    JOIN qr_codes qr ON v.qr_code_id = qr.id
    JOIN campaigns c ON qr.campaign_id = c.id
    WHERE c.business_id = ? 
    AND v.created_at >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY)
    GROUP BY DATE(v.created_at)
    ORDER BY vote_date
");
$stmt->execute([$business['id']]);
$daily_votes = $stmt->fetchAll();

require_once __DIR__ . '/../core/includes/header.php';
?>

<style>
/* Custom table styling to fix visibility issues */
#campaignTable {
    background: rgba(255, 255, 255, 0.15) !important;
    backdrop-filter: blur(20px) !important;
    border-radius: 12px !important;
    overflow: hidden !important;
}

#campaignTable thead th {
    background: rgba(30, 60, 114, 0.6) !important;
    color: #ffffff !important;
    font-weight: 600 !important;
    border-bottom: 2px solid rgba(255, 255, 255, 0.3) !important;
    padding: 1rem 0.75rem !important;
}

#campaignTable tbody td {
    background: rgba(255, 255, 255, 0.12) !important;
    color: rgba(255, 255, 255, 0.95) !important;
    border-bottom: 1px solid rgba(255, 255, 255, 0.15) !important;
    padding: 0.875rem 0.75rem !important;
}

#campaignTable tbody tr:hover td {
    background: rgba(255, 255, 255, 0.18) !important;
    color: #ffffff !important;
}

/* Badge styling improvements */
#campaignTable .badge {
    font-weight: 500 !important;
    padding: 0.375rem 0.5rem !important;
}

/* Button styling inside table */
#campaignTable .btn-outline-primary {
    background: rgba(255, 255, 255, 0.1) !important;
    border-color: rgba(255, 255, 255, 0.3) !important;
    color: rgba(255, 255, 255, 0.9) !important;
}

#campaignTable .btn-outline-primary:hover {
    background: rgba(13, 110, 253, 0.8) !important;
    color: #ffffff !important;
}
</style>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Campaign Analytics</h1>
            <p class="text-muted">Overview of all campaign performance</p>
        </div>
        <a href="manage-campaigns.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-2"></i>Back to Campaigns
        </a>
    </div>

    <!-- Campaign Overview -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Total Campaigns</h5>
                    <h2 class="mb-0"><?php echo count($campaigns); ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Total QR Codes</h5>
                    <h2 class="mb-0"><?php echo array_sum(array_column($campaigns, 'qr_count')); ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Total Votes</h5>
                    <h2 class="mb-0"><?php echo array_sum(array_column($campaigns, 'total_votes')); ?></h2>
                </div>
            </div>
        </div>
    </div>

    <!-- Campaign List -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Campaign Performance</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="campaignTable">
                            <thead>
                                <tr>
                                    <th>Campaign</th>
                                    <th>Status</th>
                                    <th>QR Codes</th>
                                    <th>Total Votes</th>
                                    <th>Last Vote</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($campaigns as $campaign): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($campaign['name']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $campaign['status'] === 'active' ? 'success' : 'danger'; ?>">
                                                <?php echo ucfirst($campaign['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo $campaign['qr_count']; ?></td>
                                        <td><?php echo $campaign['total_votes']; ?></td>
                                        <td>
                                            <?php 
                                            echo $campaign['last_vote'] 
                                                ? date('M d, Y H:i', strtotime($campaign['last_vote']))
                                                : 'Never';
                                            ?>
                                        </td>
                                        <td>
                                            <a href="campaign-stats.php?id=<?php echo $campaign['id']; ?>" 
                                               class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-graph-up me-1"></i>Details
                                            </a>
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

    <!-- Activity Chart -->
    <div class="row">
        <div class="col-12">
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