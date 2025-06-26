<?php
/**
 * Admin Nayax Transactions Management
 * View and manage all Nayax vending machine transactions
 */

$page_title = "Nayax Transactions";
$show_breadcrumb = true;
$breadcrumb_items = [
    ['name' => 'Nayax', 'url' => 'nayax-overview.php'],
    ['name' => 'Transactions']
];

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../core/config.php';

// Get filter parameters
$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$business_filter = $_GET['business_id'] ?? '';
$machine_filter = $_GET['machine_id'] ?? '';
$min_amount = $_GET['min_amount'] ?? '';
$max_amount = $_GET['max_amount'] ?? '';

// Build transaction query
$where_conditions = ['nt.created_at >= ?', 'nt.created_at <= ?'];
$query_params = [$start_date . ' 00:00:00', $end_date . ' 23:59:59'];

if ($business_filter) {
    $where_conditions[] = 'nm.business_id = ?';
    $query_params[] = $business_filter;
}

if ($machine_filter) {
    $where_conditions[] = 'nm.id = ?';
    $query_params[] = $machine_filter;
}

if ($min_amount) {
    $where_conditions[] = 'nt.amount_cents >= ?';
    $query_params[] = $min_amount * 100;
}

if ($max_amount) {
    $where_conditions[] = 'nt.amount_cents <= ?';
    $query_params[] = $max_amount * 100;
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

try {
    // Get transactions with business and machine details
    $transactions_query = "
        SELECT 
            nt.*,
            nm.device_id,
            nm.location_description,
            b.name as business_name,
            b.id as business_id,
            uc.username as customer_name,
            uc.email as customer_email
        FROM nayax_transactions nt
        LEFT JOIN nayax_machines nm ON nt.nayax_machine_id = nm.nayax_machine_id
        LEFT JOIN businesses b ON nm.business_id = b.id
        LEFT JOIN nayax_user_cards nuc ON nt.nayax_user_card_id = nuc.id
        LEFT JOIN users uc ON nuc.user_id = uc.id
        $where_clause
        ORDER BY nt.created_at DESC
        LIMIT 500
    ";
    
    $stmt = $pdo->prepare($transactions_query);
    $stmt->execute($query_params);
    $transactions = $stmt->fetchAll();

    // Get summary statistics
    $summary_query = "
        SELECT 
            COUNT(*) as total_transactions,
            SUM(nt.amount_cents)/100 as total_revenue,
            SUM(nt.platform_commission_cents)/100 as total_commission,
            AVG(nt.amount_cents)/100 as avg_transaction,
            COUNT(DISTINCT nm.business_id) as unique_businesses,
            COUNT(DISTINCT nt.nayax_machine_id) as unique_machines
        FROM nayax_transactions nt
        LEFT JOIN nayax_machines nm ON nt.nayax_machine_id = nm.nayax_machine_id
        $where_clause
    ";
    
    $stmt = $pdo->prepare($summary_query);
    $stmt->execute($query_params);
    $summary = $stmt->fetch();

    // Get businesses for filter dropdown
    $businesses = $pdo->query("
        SELECT DISTINCT b.id, b.name 
        FROM businesses b 
        JOIN nayax_machines nm ON b.id = nm.business_id 
        ORDER BY b.name
    ")->fetchAll();

    // Get machines for filter dropdown
    $machines = $pdo->query("
        SELECT nm.id, nm.device_id, nm.location_description, b.name as business_name
        FROM nayax_machines nm
        LEFT JOIN businesses b ON nm.business_id = b.id
        ORDER BY b.name, nm.device_id
    ")->fetchAll();

} catch (Exception $e) {
    $transactions = [];
    $summary = ['total_transactions' => 0, 'total_revenue' => 0, 'total_commission' => 0, 'avg_transaction' => 0, 'unique_businesses' => 0, 'unique_machines' => 0];
    $businesses = [];
    $machines = [];
    $error_message = "Error loading transactions: " . $e->getMessage();
}
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2><i class="bi bi-receipt text-success me-2"></i>Nayax Transactions</h2>
                <p class="text-muted mb-0">Monitor all vending machine transactions</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-success" onclick="exportTransactions()">
                    <i class="bi bi-download me-1"></i>Export CSV
                </button>
                <button class="btn btn-primary" onclick="refreshTransactions()">
                    <i class="bi bi-arrow-clockwise me-1"></i>Refresh
                </button>
            </div>
        </div>
    </div>
</div>

<?php if (isset($error_message)): ?>
<div class="alert alert-danger">
    <i class="bi bi-exclamation-triangle me-2"></i><?php echo $error_message; ?>
</div>
<?php endif; ?>

<!-- Summary Cards -->
<div class="row g-4 mb-4">
    <div class="col-lg-2 col-md-4 col-sm-6">
        <div class="card admin-card">
            <div class="card-body text-center">
                <i class="bi bi-receipt display-4 text-primary mb-2"></i>
                <h4 class="mb-1"><?php echo number_format($summary['total_transactions']); ?></h4>
                <small class="text-muted">Transactions</small>
            </div>
        </div>
    </div>
    
    <div class="col-lg-2 col-md-4 col-sm-6">
        <div class="card admin-card">
            <div class="card-body text-center">
                <i class="bi bi-currency-dollar display-4 text-success mb-2"></i>
                <h4 class="mb-1">$<?php echo number_format($summary['total_revenue'], 2); ?></h4>
                <small class="text-muted">Total Revenue</small>
            </div>
        </div>
    </div>
    
    <div class="col-lg-2 col-md-4 col-sm-6">
        <div class="card admin-card">
            <div class="card-body text-center">
                <i class="bi bi-cash-stack display-4 text-warning mb-2"></i>
                <h4 class="mb-1">$<?php echo number_format($summary['total_commission'], 2); ?></h4>
                <small class="text-muted">Commission</small>
            </div>
        </div>
    </div>
    
    <div class="col-lg-2 col-md-4 col-sm-6">
        <div class="card admin-card">
            <div class="card-body text-center">
                <i class="bi bi-graph-up display-4 text-info mb-2"></i>
                <h4 class="mb-1">$<?php echo number_format($summary['avg_transaction'], 2); ?></h4>
                <small class="text-muted">Avg Transaction</small>
            </div>
        </div>
    </div>
    
    <div class="col-lg-2 col-md-4 col-sm-6">
        <div class="card admin-card">
            <div class="card-body text-center">
                <i class="bi bi-building display-4 text-secondary mb-2"></i>
                <h4 class="mb-1"><?php echo number_format($summary['unique_businesses']); ?></h4>
                <small class="text-muted">Businesses</small>
            </div>
        </div>
    </div>
    
    <div class="col-lg-2 col-md-4 col-sm-6">
        <div class="card admin-card">
            <div class="card-body text-center">
                <i class="bi bi-hdd-stack display-4 text-primary mb-2"></i>
                <h4 class="mb-1"><?php echo number_format($summary['unique_machines']); ?></h4>
                <small class="text-muted">Machines</small>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card admin-card mb-4">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-funnel me-2"></i>Filters</h5>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Start Date</label>
                <input type="date" class="form-control" name="start_date" value="<?php echo $start_date; ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">End Date</label>
                <input type="date" class="form-control" name="end_date" value="<?php echo $end_date; ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Business</label>
                <select class="form-select" name="business_id">
                    <option value="">All Businesses</option>
                    <?php foreach ($businesses as $business): ?>
                        <option value="<?php echo $business['id']; ?>" <?php echo $business_filter == $business['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($business['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Machine</label>
                <select class="form-select" name="machine_id">
                    <option value="">All Machines</option>
                    <?php foreach ($machines as $machine): ?>
                        <option value="<?php echo $machine['id']; ?>" <?php echo $machine_filter == $machine['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($machine['device_id']) . ' - ' . htmlspecialchars($machine['business_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Min Amount ($)</label>
                <input type="number" class="form-control" name="min_amount" value="<?php echo $min_amount; ?>" step="0.01">
            </div>
            <div class="col-md-2">
                <label class="form-label">Max Amount ($)</label>
                <input type="number" class="form-control" name="max_amount" value="<?php echo $max_amount; ?>" step="0.01">
            </div>
            <div class="col-md-8 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-search me-1"></i>Apply Filters
                </button>
                <a href="nayax-transactions.php" class="btn btn-outline-secondary">
                    <i class="bi bi-x-lg me-1"></i>Clear
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Transactions Table -->
<div class="card admin-card">
    <div class="card-header">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <i class="bi bi-list me-2"></i>Transactions (<?php echo number_format(count($transactions)); ?>)
            </h5>
            <div class="input-group" style="width: 300px;">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="text" class="form-control" placeholder="Search transactions..." id="transactionSearch">
            </div>
        </div>
    </div>
    <div class="card-body">
        <?php if (empty($transactions)): ?>
            <div class="text-center py-5">
                <i class="bi bi-receipt display-1 text-muted"></i>
                <h5 class="text-muted mt-3">No transactions found</h5>
                <p class="text-muted">Try adjusting your filters or check back later.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover" id="transactionsTable">
                    <thead class="table-dark">
                        <tr>
                            <th>Date/Time</th>
                            <th>Business</th>
                            <th>Machine</th>
                            <th>Customer</th>
                            <th>Amount</th>
                            <th>Commission</th>
                            <th>Status</th>
                            <th>Transaction ID</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $tx): ?>
                        <tr>
                            <td>
                                <div><?php echo date('M j, Y', strtotime($tx['created_at'])); ?></div>
                                <small class="text-muted"><?php echo date('g:i A', strtotime($tx['created_at'])); ?></small>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($tx['business_name'] ?? 'Unknown'); ?></strong>
                            </td>
                            <td>
                                <div><code><?php echo htmlspecialchars($tx['device_id'] ?? 'N/A'); ?></code></div>
                                <small class="text-muted"><?php echo htmlspecialchars($tx['location_description'] ?? ''); ?></small>
                            </td>
                            <td>
                                <?php if ($tx['customer_name']): ?>
                                    <div><?php echo htmlspecialchars($tx['customer_name']); ?></div>
                                    <small class="text-muted"><?php echo htmlspecialchars($tx['customer_email']); ?></small>
                                <?php else: ?>
                                    <span class="text-muted">Guest</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="fw-bold text-success">$<?php echo number_format($tx['amount_cents'] / 100, 2); ?></span>
                            </td>
                            <td>
                                <span class="text-warning">$<?php echo number_format($tx['platform_commission_cents'] / 100, 2); ?></span>
                            </td>
                            <td>
                                <?php
                                $status = $tx['status'] ?? 'completed';
                                $statusClass = match($status) {
                                    'completed' => 'success',
                                    'pending' => 'warning',
                                    'failed' => 'danger',
                                    'refunded' => 'info',
                                    default => 'secondary'
                                };
                                ?>
                                <span class="badge bg-<?php echo $statusClass; ?>">
                                    <?php echo ucfirst($status); ?>
                                </span>
                            </td>
                            <td>
                                <code><?php echo htmlspecialchars($tx['nayax_transaction_id']); ?></code>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-outline-primary" onclick="viewTransaction('<?php echo $tx['id']; ?>')">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                    <?php if ($status === 'completed'): ?>
                                    <button class="btn btn-outline-warning" onclick="refundTransaction('<?php echo $tx['id']; ?>')">
                                        <i class="bi bi-arrow-counterclockwise"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Search functionality
document.getElementById('transactionSearch').addEventListener('input', function() {
    const searchTerm = this.value.toLowerCase();
    const table = document.getElementById('transactionsTable');
    const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');

    for (let row of rows) {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchTerm) ? '' : 'none';
    }
});

function refreshTransactions() {
    location.reload();
}

function exportTransactions() {
    const params = new URLSearchParams(window.location.search);
    params.set('export', 'csv');
    window.open('?' + params.toString());
}

function viewTransaction(id) {
    // Would show detailed transaction information
    alert('View transaction details (to be implemented)');
}

function refundTransaction(id) {
    if (confirm('Are you sure you want to refund this transaction?')) {
        // Would send refund request
        alert('Refund transaction (to be implemented)');
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?> 