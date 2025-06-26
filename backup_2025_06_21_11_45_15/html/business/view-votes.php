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
    header('Location: dashboard.php');
    exit;
}

$business_id = $business['id'];

// Get date range from query parameters
$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// Get machine name filter from query parameters
$machine_name = $_GET['machine_name'] ?? null;

// Build base WHERE clause and parameters
$base_where = "WHERE m.business_id = ? AND DATE(v.created_at) BETWEEN ? AND ?";
$base_params = [$business_id, $start_date, $end_date];

if ($machine_name) {
    $base_where .= " AND m.name = ?";
    $base_params[] = $machine_name;
}

// Fetch vote statistics
$stmt = $pdo->prepare("
    SELECT 
        COUNT(DISTINCT v.id) as total_votes,
        COUNT(DISTINCT v.voter_ip) as unique_voters,
        COUNT(DISTINCT i.id) as items_voted,
        DATE_FORMAT(MAX(v.created_at), '%Y-%m-%d %H:%i') as last_vote
    FROM votes v
    JOIN items i ON v.item_id = i.id
    JOIN machines m ON i.machine_id = m.id
    $base_where
");
$stmt->execute($base_params);
$stats = $stmt->fetch();

// Fetch top voted items
$stmt = $pdo->prepare("
    SELECT 
        i.name,
        i.type,
        i.price,
        COUNT(v.id) as vote_count,
        DATE_FORMAT(MAX(v.created_at), '%Y-%m-%d %H:%i') as last_vote
    FROM items i
    JOIN machines m ON i.machine_id = m.id
    LEFT JOIN votes v ON i.id = v.item_id
    $base_where
    GROUP BY i.id
    ORDER BY vote_count DESC
    LIMIT 10
");
$stmt->execute($base_params);
$top_items = $stmt->fetchAll();

// Fetch daily vote counts
$stmt = $pdo->prepare("
    SELECT 
        DATE(v.created_at) as vote_date,
        COUNT(*) as vote_count
    FROM votes v
    JOIN items i ON v.item_id = i.id
    JOIN machines m ON i.machine_id = m.id
    $base_where
    GROUP BY DATE(v.created_at)
    ORDER BY vote_date ASC
");
$stmt->execute($base_params);
$daily_votes = $stmt->fetchAll();

// Prepare data for chart
$chart_labels = [];
$chart_data = [];
foreach ($daily_votes as $vote) {
    $chart_labels[] = $vote['vote_date'];
    $chart_data[] = $vote['vote_count'];
}

// Include header
require_once __DIR__ . '/../core/includes/header.php';
?>

<style>
/* Enhanced card and list group styling for better visibility */

/* List group styling for top voted items */
.list-group-item {
    background: rgba(255, 255, 255, 0.12) !important;
    border-color: rgba(255, 255, 255, 0.15) !important;
    color: rgba(255, 255, 255, 0.95) !important;
}

.list-group-item:hover {
    background: rgba(255, 255, 255, 0.18) !important;
}

.list-group-item h6 {
    color: rgba(255, 255, 255, 0.95) !important;
}

.list-group-item .text-muted {
    color: rgba(255, 255, 255, 0.7) !important;
}

.list-group-item small.text-muted {
    color: rgba(255, 255, 255, 0.7) !important;
}

/* Badge styling in list groups */
.list-group-item .badge {
    background: rgba(13, 110, 253, 0.8) !important;
    color: #ffffff !important;
    border: 1px solid rgba(255, 255, 255, 0.3) !important;
}

/* Card subtitle and title improvements */
.card-subtitle.text-muted {
    color: rgba(255, 255, 255, 0.8) !important;
}

.card-title {
    color: rgba(255, 255, 255, 0.95) !important;
}

/* Form text and muted text improvements */
.text-muted {
    color: rgba(255, 255, 255, 0.8) !important;
}

/* Button styling improvements */
.btn-outline-secondary {
    background: rgba(255, 255, 255, 0.1) !important;
    border-color: rgba(255, 255, 255, 0.3) !important;
    color: rgba(255, 255, 255, 0.9) !important;
}

.btn-outline-secondary:hover {
    background: rgba(108, 117, 125, 0.8) !important;
    color: #ffffff !important;
}

/* Strong text styling */
strong {
    color: rgba(255, 255, 255, 0.95) !important;
}

/* Chart container background for better visibility */
.card-body canvas {
    background: rgba(255, 255, 255, 0.05) !important;
    border-radius: 8px !important;
}

/* No votes state styling */
.text-muted span {
    color: rgba(255, 255, 255, 0.7) !important;
}

/* Ensure main headings are visible */
h1, h2, h3, h4, h5, h6 {
    color: rgba(255, 255, 255, 0.95) !important;
}
</style>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <div class="d-flex align-items-center gap-3 mb-2">
                <h1 class="mb-0">Vote Analytics</h1>
                <?php if ($machine_name): ?>
                    <a href="manage-machine.php" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-arrow-left me-1"></i>Back to Machines
                    </a>
                <?php endif; ?>
            </div>
            <?php if ($machine_name): ?>
                <p class="text-muted mb-0">Machine: <strong><?php echo htmlspecialchars($machine_name); ?></strong></p>
            <?php endif; ?>
        </div>
        <form class="d-flex gap-2">
            <?php if ($machine_name): ?>
                <input type="hidden" name="machine_name" value="<?php echo htmlspecialchars($machine_name); ?>">
            <?php endif; ?>
            <input type="date" class="form-control" name="start_date" 
                   value="<?php echo $start_date; ?>" max="<?php echo $end_date; ?>">
            <input type="date" class="form-control" name="end_date" 
                   value="<?php echo $end_date; ?>" min="<?php echo $start_date; ?>">
            <button type="submit" class="btn btn-primary">Apply</button>
        </form>
    </div>
    
    <!-- Statistics Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="card h-100">
                <div class="card-body">
                    <h6 class="card-subtitle mb-2 text-muted">Total Votes</h6>
                    <h2 class="card-title mb-0"><?php echo number_format($stats['total_votes']); ?></h2>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card h-100">
                <div class="card-body">
                    <h6 class="card-subtitle mb-2 text-muted">Unique Voters</h6>
                    <h2 class="card-title mb-0"><?php echo number_format($stats['unique_voters']); ?></h2>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card h-100">
                <div class="card-body">
                    <h6 class="card-subtitle mb-2 text-muted">Items Voted</h6>
                    <h2 class="card-title mb-0"><?php echo number_format($stats['items_voted']); ?></h2>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card h-100">
                <div class="card-body">
                    <h6 class="card-subtitle mb-2 text-muted">Last Vote</h6>
                    <h2 class="card-title mb-0">
                        <?php if ($stats['last_vote']): ?>
                            <?php echo $stats['last_vote']; ?>
                        <?php else: ?>
                            <span class="text-muted">No votes</span>
                        <?php endif; ?>
                    </h2>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row g-4">
        <!-- Vote Trend Chart -->
        <div class="col-md-8">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">Vote Trend</h5>
                </div>
                <div class="card-body">
                    <canvas id="voteTrendChart"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Top Voted Items -->
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">Top Voted Items</h5>
                </div>
                <div class="card-body">
                    <div class="list-group list-group-flush">
                        <?php foreach ($top_items as $item): ?>
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1"><?php echo htmlspecialchars($item['name']); ?></h6>
                                        <small class="text-muted">
                                            <?php echo ucfirst($item['type']); ?> • 
                                            $<?php echo number_format($item['price'], 2); ?>
                                        </small>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge bg-primary rounded-pill">
                                            <?php echo number_format($item['vote_count']); ?> votes
                                        </span>
                                        <?php if ($item['last_vote']): ?>
                                            <br>
                                            <small class="text-muted">
                                                Last: <?php echo $item['last_vote']; ?>
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Initialize vote trend chart
const ctx = document.getElementById('voteTrendChart').getContext('2d');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode($chart_labels); ?>,
        datasets: [{
            label: 'Daily Votes',
            data: <?php echo json_encode($chart_data); ?>,
            borderColor: 'rgb(75, 192, 192)',
            tension: 0.1,
            fill: false
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    stepSize: 1
                }
            }
        },
        plugins: {
            legend: {
                display: false
            }
        }
    }
});
</script>

<?php require_once __DIR__ . '/../core/includes/footer.php'; ?> 