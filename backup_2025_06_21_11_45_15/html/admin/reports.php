<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';

// Require admin role
require_role('admin');

// Get date range from query parameters
$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// Fetch system statistics
$stmt = $pdo->prepare("
    SELECT 
        COUNT(DISTINCT u.id) as total_users,
        COUNT(DISTINCT CASE WHEN u.role = 'business' THEN u.id END) as business_users,
        COUNT(DISTINCT CASE WHEN u.role = 'user' THEN u.id END) as regular_users,
        COUNT(DISTINCT b.id) as total_businesses,
        COUNT(DISTINCT i.id) as total_items,
        COUNT(DISTINCT v.id) as total_votes,
        COUNT(DISTINCT q.id) as total_qr_codes,
        COUNT(DISTINCT s.id) as total_spins
    FROM users u
    LEFT JOIN businesses b ON u.id = b.user_id
    LEFT JOIN machines m ON b.id = m.business_id
    LEFT JOIN items i ON m.id = i.machine_id
    LEFT JOIN votes v ON i.id = v.item_id
    LEFT JOIN qr_codes q ON b.id = q.business_id
    LEFT JOIN spin_results s ON b.id = s.business_id
    WHERE (v.created_at IS NULL OR DATE(v.created_at) BETWEEN ? AND ?)
    AND (s.spin_time IS NULL OR DATE(s.spin_time) BETWEEN ? AND ?)
");
$stmt->execute([$start_date, $end_date, $start_date, $end_date]);
$stats = $stmt->fetch();

// Fetch top businesses by votes
$stmt = $pdo->prepare("
    SELECT 
        b.name,
        u.username as owner_name,
        COUNT(DISTINCT i.id) as item_count,
        COUNT(DISTINCT v.id) as vote_count,
        DATE_FORMAT(MAX(v.created_at), '%Y-%m-%d %H:%i') as last_vote
    FROM businesses b
    JOIN users u ON b.user_id = u.id
    LEFT JOIN machines m ON b.id = m.business_id
    LEFT JOIN items i ON m.id = i.machine_id
    LEFT JOIN votes v ON i.id = v.item_id
    WHERE (v.created_at IS NULL OR DATE(v.created_at) BETWEEN ? AND ?)
    GROUP BY b.id
    ORDER BY vote_count DESC
    LIMIT 10
");
$stmt->execute([$start_date, $end_date]);
$top_businesses = $stmt->fetchAll();

// Fetch top items by votes
$stmt = $pdo->prepare("
    SELECT 
        i.name,
        i.type,
        i.price,
        b.name as business_name,
        COUNT(v.id) as vote_count,
        DATE_FORMAT(MAX(v.created_at), '%Y-%m-%d %H:%i') as last_vote
    FROM items i
    JOIN machines m ON i.machine_id = m.id
    JOIN businesses b ON m.business_id = b.id
    LEFT JOIN votes v ON i.id = v.item_id
    WHERE (v.created_at IS NULL OR DATE(v.created_at) BETWEEN ? AND ?)
    GROUP BY i.id
    ORDER BY vote_count DESC
    LIMIT 10
");
$stmt->execute([$start_date, $end_date]);
$top_items = $stmt->fetchAll();

// Fetch daily activity
$stmt = $pdo->prepare("
    SELECT 
        DATE(activity_time) as activity_date,
        SUM(vote_count) as votes,
        SUM(spin_count) as spins,
        SUM(qr_scan_count) as qr_scans
    FROM (
        SELECT created_at as activity_time, 1 as vote_count, 0 as spin_count, 0 as qr_scan_count
        FROM votes
        WHERE DATE(created_at) BETWEEN ? AND ?
        UNION ALL
        SELECT spin_time as activity_time, 0 as vote_count, 1 as spin_count, 0 as qr_scan_count
        FROM spin_results
        WHERE DATE(spin_time) BETWEEN ? AND ?
        -- QR scan tracking disabled due to schema mismatch
        -- UNION ALL
        -- SELECT scanned_at as activity_time, 0 as vote_count, 0 as spin_count, 1 as qr_scan_count
        -- FROM qr_codes
        -- WHERE DATE(scanned_at) BETWEEN ? AND ?
    ) combined_activity
    GROUP BY DATE(activity_time)
    ORDER BY activity_date ASC
");
$stmt->execute([$start_date, $end_date, $start_date, $end_date]);
$daily_activity = $stmt->fetchAll();

// Prepare data for charts
$chart_labels = [];
$chart_votes = [];
$chart_spins = [];
$chart_scans = [];
foreach ($daily_activity as $activity) {
    $chart_labels[] = $activity['activity_date'];
    $chart_votes[] = $activity['votes'];
    $chart_spins[] = $activity['spins'];
    $chart_scans[] = $activity['qr_scans'];
}

// Include header
require_once __DIR__ . '/../core/includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>System Reports</h1>
        <form class="d-flex gap-2">
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
                    <h6 class="card-subtitle mb-2 text-muted">Total Users</h6>
                    <h2 class="card-title mb-0"><?php echo number_format($stats['total_users']); ?></h2>
                    <small class="text-muted">
                        <?php echo number_format($stats['business_users']); ?> businesses, 
                        <?php echo number_format($stats['regular_users']); ?> regular users
                    </small>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card h-100">
                <div class="card-body">
                    <h6 class="card-subtitle mb-2 text-muted">Total Businesses</h6>
                    <h2 class="card-title mb-0"><?php echo number_format($stats['total_businesses']); ?></h2>
                    <small class="text-muted">
                        <?php echo number_format($stats['total_items']); ?> items
                    </small>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card h-100">
                <div class="card-body">
                    <h6 class="card-subtitle mb-2 text-muted">Total Votes</h6>
                    <h2 class="card-title mb-0"><?php echo number_format($stats['total_votes']); ?></h2>
                    <small class="text-muted">
                        <?php echo number_format($stats['total_qr_codes']); ?> QR codes
                    </small>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card h-100">
                <div class="card-body">
                    <h6 class="card-subtitle mb-2 text-muted">Total Spins</h6>
                    <h2 class="card-title mb-0"><?php echo number_format($stats['total_spins']); ?></h2>
                    <small class="text-muted">
                        Across all businesses
                    </small>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row g-4">
        <!-- Activity Chart -->
        <div class="col-md-8">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">Daily Activity</h5>
                </div>
                <div class="card-body">
                    <canvas id="activityChart"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Top Businesses -->
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">Top Businesses</h5>
                </div>
                <div class="card-body">
                    <div class="list-group list-group-flush">
                        <?php foreach ($top_businesses as $business): ?>
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1"><?php echo htmlspecialchars($business['name']); ?></h6>
                                        <small class="text-muted">
                                            <?php echo htmlspecialchars($business['owner_name']); ?> • 
                                            <?php echo $business['item_count']; ?> items
                                        </small>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge bg-primary rounded-pill">
                                            <?php echo number_format($business['vote_count']); ?> votes
                                        </span>
                                        <?php if ($business['last_vote']): ?>
                                            <br>
                                            <small class="text-muted">
                                                Last: <?php echo $business['last_vote']; ?>
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
        
        <!-- Top Items -->
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Top Voted Items</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Item Name</th>
                                    <th>Type</th>
                                    <th>Price</th>
                                    <th>Business</th>
                                    <th>Votes</th>
                                    <th>Last Vote</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($top_items as $item): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($item['name']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo match($item['type']) {
                                                    'snack' => 'warning',
                                                    'drink' => 'info',
                                                    'promo' => 'success',
                                                    default => 'secondary'
                                                };
                                            ?>">
                                                <?php echo ucfirst($item['type']); ?>
                                            </span>
                                        </td>
                                        <td>$<?php echo number_format($item['price'], 2); ?></td>
                                        <td><?php echo htmlspecialchars($item['business_name']); ?></td>
                                        <td><?php echo number_format($item['vote_count']); ?></td>
                                        <td>
                                            <?php if ($item['last_vote']): ?>
                                                <?php echo $item['last_vote']; ?>
                                            <?php else: ?>
                                                <span class="text-muted">No votes</span>
                                            <?php endif; ?>
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
</div>

<!-- Custom CSS fixes for dark theme text visibility -->
<style>
/* Fix dark grey text to white in reports */
.text-muted {
    color: rgba(255, 255, 255, 0.8) !important;
}

/* Fix list group items for Top Businesses section */
.list-group-item {
    background: rgba(255, 255, 255, 0.08) !important;
    border: 1px solid rgba(255, 255, 255, 0.15) !important;
    color: rgba(255, 255, 255, 0.9) !important;
}

.list-group-item h6 {
    color: #ffffff !important;
}

.list-group-item .text-muted {
    color: rgba(255, 255, 255, 0.75) !important;
}

/* Enhanced table styling to match Top Businesses look - HIGH SPECIFICITY */
.container .row .col-md-12 .card .card-body .table-responsive .table {
    background: rgba(255, 255, 255, 0.08) !important;
    backdrop-filter: blur(10px) !important;
    border-radius: 12px !important;
    overflow: hidden !important;
    border: 1px solid rgba(255, 255, 255, 0.15) !important;
}

.container .row .col-md-12 .card .card-body .table-responsive .table td,
.container .row .col-md-12 .card .card-body .table-responsive .table th {
    color: #ffffff !important;
    border-color: rgba(255, 255, 255, 0.1) !important;
    padding: 1rem 0.75rem !important;
    background: transparent !important;
}

.container .row .col-md-12 .card .card-body .table-responsive .table thead th {
    background: rgba(255, 255, 255, 0.15) !important;
    color: #ffffff !important;
    font-weight: 600 !important;
    text-transform: uppercase !important;
    font-size: 0.875rem !important;
    letter-spacing: 0.5px !important;
}

.container .row .col-md-12 .card .card-body .table-responsive .table tbody tr {
    transition: all 0.2s ease !important;
    background: transparent !important;
}

.container .row .col-md-12 .card .card-body .table-responsive .table tbody tr:hover {
    background: rgba(255, 255, 255, 0.12) !important;
    transform: translateX(2px) !important;
}

.container .row .col-md-12 .card .card-body .table-responsive .table tbody tr td {
    color: #ffffff !important;
}

.container .row .col-md-12 .card .card-body .table-responsive .table .text-muted {
    color: rgba(255, 255, 255, 0.75) !important;
}

/* Alternative fallback with even higher specificity */
div.container div.row div.col-md-12 div.card div.card-body div.table-responsive table.table {
    background: rgba(255, 255, 255, 0.08) !important;
}

div.container div.row div.col-md-12 div.card div.card-body div.table-responsive table.table td,
div.container div.row div.col-md-12 div.card div.card-body div.table-responsive table.table th {
    color: #ffffff !important;
}

/* Table responsive wrapper */
.table-responsive {
    border-radius: 12px !important;
    border: 1px solid rgba(255, 255, 255, 0.15) !important;
}

/* Card titles and text */
.card-title {
    color: #ffffff !important;
}

.card-subtitle {
    color: rgba(255, 255, 255, 0.8) !important;
}

/* Badge styling for better contrast */
.badge.bg-primary {
    background-color: #1976d2 !important;
}

.badge.bg-warning {
    background-color: #f57c00 !important;
    color: #ffffff !important;
}

.badge.bg-info {
    background-color: #0288d1 !important;
}

.badge.bg-success {
    background-color: #388e3c !important;
}

.badge.bg-secondary {
    background-color: #546e7a !important;
    color: #ffffff !important;
}

/* Fix any remaining dark text in charts and cards */
.card-header h5, .card-header .card-title {
    color: #ffffff !important;
}

/* Statistics cards text */
.card-body h2, .card-body h6 {
    color: #ffffff !important;
}

.card-body small {
    color: rgba(255, 255, 255, 0.8) !important;
}

/* Daily Activity chart container */
#activityChart {
    background: transparent !important;
}

/* Form labels and inputs in date filters */
.form-control {
    background: rgba(255, 255, 255, 0.9) !important;
    color: #333333 !important;
    border: 1px solid rgba(255, 255, 255, 0.3) !important;
}

.form-control:focus {
    background: #ffffff !important;
    color: #333333 !important;
    border-color: #007bff !important;
    box-shadow: 0 0 0 0.25rem rgba(0, 123, 255, 0.25) !important;
}

/* NUCLEAR OPTION - Force all text in tables to be white */
table.table * {
    color: #ffffff !important;
}

table.table .badge {
    color: #ffffff !important;
}

table.table span.text-muted {
    color: rgba(255, 255, 255, 0.8) !important;
}

/* Top Voted Items specific overrides */
.card .card-header .card-title {
    color: #ffffff !important;
}

.card .card-body table tr td {
    color: #ffffff !important;
}

.card .card-body table tr td span {
    color: #ffffff !important;
}

/* Force white text on all table elements with highest specificity */
body .container .row .col-md-12 .card .card-body .table-responsive .table tbody tr td {
    color: #ffffff !important;
}

body .container .row .col-md-12 .card .card-body .table-responsive .table thead tr th {
    color: #ffffff !important;
}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Initialize activity chart
const ctx = document.getElementById('activityChart').getContext('2d');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode($chart_labels); ?>,
        datasets: [
            {
                label: 'Votes',
                data: <?php echo json_encode($chart_votes); ?>,
                borderColor: 'rgb(75, 192, 192)',
                tension: 0.1,
                fill: false
            },
            {
                label: 'Spins',
                data: <?php echo json_encode($chart_spins); ?>,
                borderColor: 'rgb(255, 99, 132)',
                tension: 0.1,
                fill: false
            },
            {
                label: 'QR Scans',
                data: <?php echo json_encode($chart_scans); ?>,
                borderColor: 'rgb(54, 162, 235)',
                tension: 0.1,
                fill: false
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                labels: {
                    color: 'rgba(255, 255, 255, 0.9)',
                    font: {
                        size: 12,
                        weight: '500'
                    }
                }
            }
        },
        scales: {
            x: {
                ticks: {
                    color: 'rgba(255, 255, 255, 0.8)',
                    font: {
                        size: 11
                    }
                },
                grid: {
                    color: 'rgba(255, 255, 255, 0.1)',
                    borderColor: 'rgba(255, 255, 255, 0.2)'
                }
            },
            y: {
                beginAtZero: true,
                ticks: {
                    stepSize: 1,
                    color: 'rgba(255, 255, 255, 0.8)',
                    font: {
                        size: 11
                    }
                },
                grid: {
                    color: 'rgba(255, 255, 255, 0.1)',
                    borderColor: 'rgba(255, 255, 255, 0.2)'
                }
            }
        }
    }
});
</script>

<?php require_once __DIR__ . '/../core/includes/footer.php'; ?> 