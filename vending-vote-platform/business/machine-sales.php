<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/functions.php';

// Ensure business access
require_role('business');

$message = '';
$message_type = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'add_sale':
            $machine_id = $_POST['machine_id'] ?? null;
            $item_id = $_POST['item_id'] ?? null;
            $sale_price = $_POST['sale_price'] ?? null;
            $start_date = $_POST['start_date'] ?? null;
            $end_date = $_POST['end_date'] ?? null;
            
            if ($machine_id && $item_id && $sale_price && $start_date && $end_date) {
                $stmt = $pdo->prepare("
                    INSERT INTO machine_sales (
                        machine_id, item_id, sale_price, start_date, end_date, status
                    ) VALUES (?, ?, ?, ?, ?, 'active')
                ");
                
                if ($stmt->execute([$machine_id, $item_id, $sale_price, $start_date, $end_date])) {
                    $message = "Sale added successfully!";
                    $message_type = "success";
                } else {
                    $message = "Error adding sale.";
                    $message_type = "danger";
                }
            }
            break;
            
        case 'update_sale':
            $sale_id = $_POST['sale_id'] ?? null;
            $status = $_POST['status'] ?? null;
            
            if ($sale_id && $status) {
                $stmt = $pdo->prepare("
                    UPDATE machine_sales 
                    SET status = ? 
                    WHERE id = ? AND machine_id IN (
                        SELECT id FROM machines WHERE business_id = ?
                    )
                ");
                
                if ($stmt->execute([$status, $sale_id, $_SESSION['business_id']])) {
                    $message = "Sale updated successfully!";
                    $message_type = "success";
                } else {
                    $message = "Error updating sale.";
                    $message_type = "danger";
                }
            }
            break;
    }
}

// Get all machines for this business
$stmt = $pdo->prepare("
    SELECT id, name, location
    FROM machines
    WHERE business_id = ?
    ORDER BY name
");
$stmt->execute([$_SESSION['business_id']]);
$machines = $stmt->fetchAll();

// Get active sales for all machines
$stmt = $pdo->prepare("
    SELECT ms.*, m.name as machine_name, m.location, i.name as item_name, i.retail_price
    FROM machine_sales ms
    JOIN machines m ON ms.machine_id = m.id
    JOIN items i ON ms.item_id = i.id
    WHERE m.business_id = ?
    ORDER BY ms.start_date DESC
");
$stmt->execute([$_SESSION['business_id']]);
$sales = $stmt->fetchAll();

// Get available items for new sales
$stmt = $pdo->prepare("
    SELECT id, name, retail_price
    FROM items
    WHERE business_id = ? AND status = 'active'
    ORDER BY name
");
$stmt->execute([$_SESSION['business_id']]);
$items = $stmt->fetchAll();

require_once __DIR__ . '/../core/includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Machine Sales Management</h1>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newSaleModal">
            <i class="bi bi-plus-circle me-2"></i>Add Sale Item
        </button>
    </div>
    
    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <!-- Active Sales -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0">Active Sales</h5>
        </div>
        <div class="card-body">
            <?php if (empty($sales)): ?>
                <p class="text-muted text-center mb-0">No active sales</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Machine</th>
                                <th>Item</th>
                                <th>Sale Price</th>
                                <th>Duration</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sales as $sale): ?>
                                <tr>
                                    <td>
                                        <div>
                                            <h6 class="mb-0"><?php echo htmlspecialchars($sale['machine_name']); ?></h6>
                                            <small class="text-muted"><?php echo htmlspecialchars($sale['location']); ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            <h6 class="mb-0"><?php echo htmlspecialchars($sale['item_name']); ?></h6>
                                            <small class="text-muted">Regular: $<?php echo number_format($sale['retail_price'], 2); ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-success">
                                            $<?php echo number_format($sale['sale_price'], 2); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo date('M d, Y', strtotime($sale['start_date'])); ?> -
                                        <?php echo date('M d, Y', strtotime($sale['end_date'])); ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $sale['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                            <?php echo ucfirst($sale['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <button type="button" class="btn btn-sm btn-outline-primary" 
                                                    onclick="generateQR(<?php echo $sale['machine_id']; ?>)">
                                                <i class="bi bi-qr-code"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" 
                                                    onclick="toggleSale(<?php echo $sale['id']; ?>, '<?php echo $sale['status']; ?>')">
                                                <i class="bi bi-toggle-on"></i>
                                            </button>
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
    
    <!-- Sales Stats -->
    <div class="row">
        <div class="col-md-4">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h6 class="card-title">Active Sales</h6>
                    <h2 class="mb-0">
                        <?php echo count(array_filter($sales, function($s) { return $s['status'] === 'active'; })); ?>
                    </h2>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h6 class="card-title">Total Savings</h6>
                    <h2 class="mb-0">
                        <?php
                        $total_savings = 0;
                        foreach ($sales as $sale) {
                            if ($sale['status'] === 'active') {
                                $total_savings += ($sale['retail_price'] - $sale['sale_price']);
                            }
                        }
                        echo '$' . number_format($total_savings, 2);
                        ?>
                    </h2>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h6 class="card-title">Machines with Sales</h6>
                    <h2 class="mb-0">
                        <?php
                        $machines_with_sales = array_unique(array_map(function($s) {
                            return $s['machine_id'];
                        }, array_filter($sales, function($s) {
                            return $s['status'] === 'active';
                        })));
                        echo count($machines_with_sales);
                        ?>
                    </h2>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- New Sale Modal -->
<div class="modal fade" id="newSaleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="">
                <input type="hidden" name="action" value="add_sale">
                
                <div class="modal-header">
                    <h5 class="modal-title">Add Sale Item</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Machine</label>
                        <select class="form-select" name="machine_id" required>
                            <option value="">Select a machine</option>
                            <?php foreach ($machines as $machine): ?>
                                <option value="<?php echo $machine['id']; ?>">
                                    <?php echo htmlspecialchars($machine['name']); ?> 
                                    (<?php echo htmlspecialchars($machine['location']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Item</label>
                        <select class="form-select" name="item_id" required>
                            <option value="">Select an item</option>
                            <?php foreach ($items as $item): ?>
                                <option value="<?php echo $item['id']; ?>">
                                    <?php echo htmlspecialchars($item['name']); ?> 
                                    ($<?php echo number_format($item['retail_price'], 2); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Sale Price</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" class="form-control" name="sale_price" 
                                   min="0" step="0.01" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Start Date</label>
                                <input type="date" class="form-control" name="start_date" 
                                       min="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">End Date</label>
                                <input type="date" class="form-control" name="end_date" 
                                       min="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Sale</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- QR Code Modal -->
<div class="modal fade" id="qrCodeModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Machine Sales QR Code</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <div id="qrcode" class="mb-3"></div>
                <p class="text-muted small mb-0">Scan to view current sales</p>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
// Generate QR Code
function generateQR(machineId) {
    const modal = new bootstrap.Modal(document.getElementById('qrCodeModal'));
    const qrContainer = document.getElementById('qrcode');
    qrContainer.innerHTML = '';
    
    new QRCode(qrContainer, {
        text: `${window.location.origin}/machine-sales.php?machine=${machineId}`,
        width: 200,
        height: 200
    });
    
    modal.show();
}

// Toggle sale status
function toggleSale(saleId, currentStatus) {
    const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
    
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `
        <input type="hidden" name="action" value="update_sale">
        <input type="hidden" name="sale_id" value="${saleId}">
        <input type="hidden" name="status" value="${newStatus}">
    `;
    
    document.body.appendChild(form);
    form.submit();
}
</script>

<?php require_once __DIR__ . '/../core/includes/footer.php'; ?> 