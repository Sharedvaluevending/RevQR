<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/qr_coin_manager.php';

// Require user role
require_role('user');

// Get filter parameters
$type = $_GET['type'] ?? 'all'; // all, earning, spending
$activity = $_GET['activity'] ?? 'all'; // all, vote, spin, purchase, daily_bonus, etc.
$date_range = $_GET['date_range'] ?? '30'; // 7, 30, 90, 365, custom
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 25;
$offset = ($page - 1) * $per_page;

// Handle custom date range
$start_date = null;
$end_date = null;
if ($date_range === 'custom') {
    $start_date = $_GET['start_date'] ?? null;
    $end_date = $_GET['end_date'] ?? null;
} else {
    $days = intval($date_range);
    $start_date = date('Y-m-d', strtotime("-{$days} days"));
    $end_date = date('Y-m-d');
}

// Build filter conditions
$where_conditions = ["user_id = ?"];
$params = [$_SESSION['user_id']];

if ($type === 'earning') {
    $where_conditions[] = "transaction_type = 'earning'";
} elseif ($type === 'spending') {
    $where_conditions[] = "transaction_type = 'spending'";
}

if ($activity !== 'all') {
    $where_conditions[] = "category = ?";
    $params[] = $activity;
}

if ($start_date && $end_date) {
    $where_conditions[] = "DATE(created_at) BETWEEN ? AND ?";
    $params[] = $start_date;
    $params[] = $end_date;
}

$where_clause = implode(' AND ', $where_conditions);

// Get total count for pagination
$count_sql = "SELECT COUNT(*) FROM qr_coin_transactions WHERE $where_clause";
$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$total_items = $stmt->fetchColumn();
$total_pages = ceil($total_items / $per_page);

// Get transactions with calculated running balance
$sql = "
    SELECT *, category as activity_type
    FROM qr_coin_transactions 
    WHERE $where_clause
    ORDER BY created_at DESC
    LIMIT $per_page OFFSET $offset
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$transactions = $stmt->fetchAll();

// Calculate balance_after for each transaction by working backwards from current balance
$running_balance = $current_balance;
foreach ($transactions as &$transaction) {
    $transaction['balance_after'] = $running_balance;
    // Work backwards: subtract the transaction amount to get the previous balance
    $running_balance -= $transaction['amount'];
}

// Get summary statistics
$summary_sql = "
    SELECT 
        SUM(CASE WHEN transaction_type = 'earning' THEN amount ELSE 0 END) as total_earned,
        SUM(CASE WHEN transaction_type = 'spending' THEN ABS(amount) ELSE 0 END) as total_spent,
        COUNT(CASE WHEN transaction_type = 'earning' THEN 1 END) as earning_count,
        COUNT(CASE WHEN transaction_type = 'spending' THEN 1 END) as spending_count,
        COUNT(*) as total_transactions
    FROM qr_coin_transactions 
    WHERE $where_clause
";
$stmt = $pdo->prepare($summary_sql);
$stmt->execute($params);
$summary = $stmt->fetch();

// Get activity breakdown
$activity_sql = "
    SELECT 
        category as activity_type,
        SUM(CASE WHEN transaction_type = 'earning' THEN amount ELSE 0 END) as earned,
        SUM(CASE WHEN transaction_type = 'spending' THEN ABS(amount) ELSE 0 END) as spent,
        COUNT(*) as count
    FROM qr_coin_transactions 
    WHERE $where_clause
    GROUP BY category
    ORDER BY count DESC
";
$stmt = $pdo->prepare($activity_sql);
$stmt->execute($params);
$activity_breakdown = $stmt->fetchAll();

// Get user's current balance
$current_balance = QRCoinManager::getBalance($_SESSION['user_id']);

require_once __DIR__ . '/../core/includes/header.php';
?>

<style>
.qr-transactions-table {
    background: rgba(255, 255, 255, 0.12) !important;
    backdrop-filter: blur(20px) !important;
    border: 1px solid rgba(255, 255, 255, 0.15) !important;
    border-radius: 12px !important;
    color: #ffffff;
}

.qr-transactions-table th {
    background: rgba(255, 255, 255, 0.1) !important;
    border: none !important;
    color: #ffffff !important;
    font-weight: 600;
}

.qr-transactions-table td {
    background: transparent !important;
    border: 1px solid rgba(255, 255, 255, 0.1) !important;
    color: #ffffff !important;
}
</style>

<div class="container py-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="mb-0">
                        <i class="bi bi-clock-history text-info me-2"></i>QR Coin Transactions
                    </h1>
                    <p class="text-muted mb-0">Complete history of your QR coin earnings and spending</p>
                </div>
                <div class="text-end">
                    <div class="d-flex align-items-center">
                        <img src="../img/qrCoin.png" alt="QR Coin" style="width: 2rem; height: 2rem;" class="me-2">
                        <h4 class="text-warning mb-0"><?php echo number_format($current_balance); ?></h4>
                    </div>
                    <small class="text-muted">Current Balance</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h3 class="text-success">
                        <i class="bi bi-plus-circle me-1"></i>
                        <?php echo number_format($summary['total_earned'] ?? 0); ?>
                    </h3>
                    <small class="text-muted">Total Earned</small>
                    <div class="text-muted small mt-1"><?php echo number_format($summary['earning_count'] ?? 0); ?> transactions</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h3 class="text-danger">
                        <i class="bi bi-dash-circle me-1"></i>
                        <?php echo number_format($summary['total_spent'] ?? 0); ?>
                    </h3>
                    <small class="text-muted">Total Spent</small>
                    <div class="text-muted small mt-1"><?php echo number_format($summary['spending_count'] ?? 0); ?> transactions</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h3 class="text-info">
                        <?php echo number_format(($summary['total_earned'] ?? 0) - ($summary['total_spent'] ?? 0)); ?>
                    </h3>
                    <small class="text-muted">Net Change</small>
                    <div class="text-muted small mt-1">In selected period</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h3 class="text-primary"><?php echo number_format($summary['total_transactions'] ?? 0); ?></h3>
                    <small class="text-muted">Total Transactions</small>
                    <div class="text-muted small mt-1">
                        <?php
                        $avg_per_day = 0;
                        if ($start_date && $end_date) {
                            $days_diff = max(1, (strtotime($end_date) - strtotime($start_date)) / 86400);
                            $avg_per_day = round(($summary['total_transactions'] ?? 0) / $days_diff, 1);
                        }
                        echo $avg_per_day . ' per day';
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-md-2">
                            <label class="form-label">Transaction Type</label>
                            <select name="type" class="form-select">
                                <option value="all" <?php echo $type === 'all' ? 'selected' : ''; ?>>All Types</option>
                                <option value="earning" <?php echo $type === 'earning' ? 'selected' : ''; ?>>Earnings Only</option>
                                <option value="spending" <?php echo $type === 'spending' ? 'selected' : ''; ?>>Spending Only</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Activity</label>
                            <select name="activity" class="form-select">
                                <option value="all" <?php echo $activity === 'all' ? 'selected' : ''; ?>>All Activities</option>
                                <option value="vote" <?php echo $activity === 'vote' ? 'selected' : ''; ?>>Voting</option>
                                <option value="spin" <?php echo $activity === 'spin' ? 'selected' : ''; ?>>Spin Wheel</option>
                                <option value="daily_bonus" <?php echo $activity === 'daily_bonus' ? 'selected' : ''; ?>>Daily Bonus</option>
                                <option value="qr_store_purchase" <?php echo $activity === 'qr_store_purchase' ? 'selected' : ''; ?>>QR Store</option>
                                <option value="business_store_purchase" <?php echo $activity === 'business_store_purchase' ? 'selected' : ''; ?>>Business Store</option>
                                <option value="achievement" <?php echo $activity === 'achievement' ? 'selected' : ''; ?>>Achievements</option>
                                <option value="bonus" <?php echo $activity === 'bonus' ? 'selected' : ''; ?>>Bonuses</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Date Range</label>
                            <select name="date_range" class="form-select" id="dateRangeSelect">
                                <option value="7" <?php echo $date_range === '7' ? 'selected' : ''; ?>>Last 7 Days</option>
                                <option value="30" <?php echo $date_range === '30' ? 'selected' : ''; ?>>Last 30 Days</option>
                                <option value="90" <?php echo $date_range === '90' ? 'selected' : ''; ?>>Last 90 Days</option>
                                <option value="365" <?php echo $date_range === '365' ? 'selected' : ''; ?>>Last Year</option>
                                <option value="custom" <?php echo $date_range === 'custom' ? 'selected' : ''; ?>>Custom Range</option>
                            </select>
                        </div>
                        <div class="col-md-2" id="startDateDiv" style="display: <?php echo $date_range === 'custom' ? 'block' : 'none'; ?>;">
                            <label class="form-label">Start Date</label>
                            <input type="date" name="start_date" class="form-select" value="<?php echo $start_date; ?>">
                        </div>
                        <div class="col-md-2" id="endDateDiv" style="display: <?php echo $date_range === 'custom' ? 'block' : 'none'; ?>;">
                            <label class="form-label">End Date</label>
                            <input type="date" name="end_date" class="form-select" value="<?php echo $end_date; ?>">
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-funnel me-1"></i>Filter
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Activity Breakdown -->
    <?php if (!empty($activity_breakdown)): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-pie-chart me-2"></i>Activity Breakdown
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <?php foreach ($activity_breakdown as $activity_data): ?>
                                <div class="col-md-3">
                                    <div class="text-center">
                                        <h6 class="text-capitalize"><?php echo str_replace('_', ' ', $activity_data['activity_type']); ?></h6>
                                        <div class="d-flex justify-content-center gap-2 mb-2">
                                            <?php if ($activity_data['earned'] > 0): ?>
                                                <span class="badge bg-success">+<?php echo number_format($activity_data['earned']); ?></span>
                                            <?php endif; ?>
                                            <?php if ($activity_data['spent'] > 0): ?>
                                                <span class="badge bg-danger">-<?php echo number_format($activity_data['spent']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <small class="text-muted"><?php echo number_format($activity_data['count']); ?> transactions</small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Transaction List -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Transaction History</h5>
                    <div class="text-muted">
                        <?php if ($total_items > 0): ?>
                            Showing <?php echo $offset + 1; ?>-<?php echo min($offset + $per_page, $total_items); ?> 
                            of <?php echo number_format($total_items); ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($transactions)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-journal-x display-1 text-muted mb-3"></i>
                            <h4 class="text-muted">No Transactions Found</h4>
                            <p class="text-muted mb-4">No transactions match your current filters.</p>
                            <a href="?type=all&activity=all&date_range=30" class="btn btn-outline-primary">
                                Reset Filters
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table qr-transactions-table mb-0">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Date & Time</th>
                                        <th>Activity</th>
                                        <th>Description</th>
                                        <th class="text-end">Amount</th>
                                        <th class="text-end">Balance After</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($transactions as $transaction): ?>
                                        <?php
                                        $metadata = json_decode($transaction['metadata'], true) ?? [];
                                        $is_earning = $transaction['transaction_type'] === 'earning';
                                        $amount = abs($transaction['amount']);
                                        ?>
                                        <tr>
                                            <td>
                                                <div class="small text-muted">
                                                    <?php echo date('M j, Y', strtotime($transaction['created_at'])); ?>
                                                </div>
                                                <div class="small">
                                                    <?php echo date('g:i A', strtotime($transaction['created_at'])); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $is_earning ? 'success' : 'danger'; ?>">
                                                    <i class="bi bi-<?php echo $is_earning ? 'plus' : 'dash'; ?>-circle me-1"></i>
                                                    <?php echo $is_earning ? 'Earned' : 'Spent'; ?>
                                                </span>
                                                <div class="small text-muted text-capitalize mt-1">
                                                    <?php echo str_replace('_', ' ', $transaction['activity_type']); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div><?php echo htmlspecialchars($transaction['description']); ?></div>
                                                <?php if (!empty($metadata)): ?>
                                                    <div class="small text-muted mt-1">
                                                        <?php
                                                        $details = [];
                                                        if (isset($metadata['item_name'])) {
                                                            $details[] = "Item: " . $metadata['item_name'];
                                                        }
                                                        if (isset($metadata['spin_result'])) {
                                                            $details[] = "Result: " . $metadata['spin_result'];
                                                        }
                                                        if (isset($metadata['streak_day'])) {
                                                            $details[] = "Day " . $metadata['streak_day'] . " bonus";
                                                        }
                                                        if (isset($metadata['machine_name'])) {
                                                            $details[] = "Machine: " . $metadata['machine_name'];
                                                        }
                                                        echo implode(' • ', $details);
                                                        ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end">
                                                <span class="fw-bold text-<?php echo $is_earning ? 'success' : 'danger'; ?>">
                                                    <?php echo $is_earning ? '+' : '-'; ?><?php echo number_format($amount); ?>
                                                </span>
                                                <div class="d-flex align-items-center justify-content-end">
                                                    <img src="../img/qrCoin.png" alt="QR Coin" style="width: 1rem; height: 1rem;" class="ms-1">
                                                </div>
                                            </td>
                                            <td class="text-end">
                                                <span class="text-muted">
                                                    <?php echo number_format($transaction['balance_after'] ?? 0); ?>
                                                </span>
                                                <div class="d-flex align-items-center justify-content-end">
                                                    <img src="../img/qrCoin.png" alt="QR Coin" style="width: 0.8rem; height: 0.8rem;" class="ms-1">
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <div class="d-flex justify-content-center mt-4 p-3">
                                <nav aria-label="Transaction history pagination">
                                    <ul class="pagination">
                                        <li class="page-item <?php echo $page === 1 ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>">
                                                <span aria-hidden="true">&laquo;&laquo;</span>
                                            </a>
                                        </li>
                                        <li class="page-item <?php echo $page === 1 ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                                <span aria-hidden="true">&laquo;</span>
                                            </a>
                                        </li>
                                        <?php
                                        $window = 2;
                                        $start = max(1, $page - $window);
                                        $end = min($total_pages, $page + $window);
                                        
                                        if ($start > 1) {
                                            echo '<li class="page-item"><span class="page-link">...</span></li>';
                                        }
                                        
                                        for ($i = $start; $i <= $end; $i++) {
                                            $active = $page === $i ? 'active' : '';
                                            echo '<li class="page-item ' . $active . '">';
                                            echo '<a class="page-link" href="?' . http_build_query(array_merge($_GET, ['page' => $i])) . '">' . $i . '</a>';
                                            echo '</li>';
                                        }
                                        
                                        if ($end < $total_pages) {
                                            echo '<li class="page-item"><span class="page-link">...</span></li>';
                                        }
                                        ?>
                                        <li class="page-item <?php echo $page === $total_pages ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                                <span aria-hidden="true">&raquo;</span>
                                            </a>
                                        </li>
                                        <li class="page-item <?php echo $page === $total_pages ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $total_pages])); ?>">
                                                <span aria-hidden="true">&raquo;&raquo;</span>
                                            </a>
                                        </li>
                                    </ul>
                                </nav>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('dateRangeSelect').addEventListener('change', function() {
    const customDivs = ['startDateDiv', 'endDateDiv'];
    const isCustom = this.value === 'custom';
    
    customDivs.forEach(divId => {
        document.getElementById(divId).style.display = isCustom ? 'block' : 'none';
    });
});
</script>

<?php require_once __DIR__ . '/../core/includes/footer.php'; ?>