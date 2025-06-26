<?php
/**
 * Admin Nayax Machine Management
 * Monitor and manage all Nayax vending machines across the platform
 */

$page_title = "Nayax Machine Management";
$show_breadcrumb = true;
$breadcrumb_items = [
    ['name' => 'Nayax', 'url' => 'nayax-overview.php'],
    ['name' => 'Machine Management']
];

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../core/config.php';

// Get all machines with business info
try {
    $machines = $pdo->query("
        SELECT 
            nm.*,
            b.name as business_name,
            b.id as business_id,
            COUNT(nt.id) as transaction_count,
            SUM(nt.amount_cents)/100 as total_revenue,
            MAX(nt.created_at) as last_transaction
        FROM nayax_machines nm
        LEFT JOIN businesses b ON nm.business_id = b.id
        LEFT JOIN nayax_transactions nt ON nm.nayax_machine_id = nt.nayax_machine_id
        GROUP BY nm.id
        ORDER BY nm.created_at DESC
    ")->fetchAll();

    $total_machines = count($machines);
    $online_machines = count(array_filter($machines, fn($m) => $m['status'] === 'online'));
    $offline_machines = $total_machines - $online_machines;

} catch (Exception $e) {
    $machines = [];
    $total_machines = 0;
    $online_machines = 0;
    $offline_machines = 0;
    $setup_needed = true;
}
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2><i class="bi bi-hdd-stack text-primary me-2"></i>Nayax Machine Management</h2>
                <p class="text-muted mb-0">Monitor and manage all vending machines</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-secondary" onclick="refreshMachines()">
                    <i class="bi bi-arrow-clockwise me-1"></i>Refresh Status
                </button>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addMachineModal">
                    <i class="bi bi-plus-lg me-1"></i>Add Machine
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Status Overview -->
<div class="row g-4 mb-4">
    <div class="col-lg-3 col-md-6">
        <div class="card admin-card stat-card">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <i class="bi bi-hdd-stack display-4 text-primary me-3"></i>
                    <div>
                        <div class="h3 mb-1"><?php echo $total_machines; ?></div>
                        <div class="text-muted">Total Machines</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6">
        <div class="card admin-card stat-card">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <i class="bi bi-check-circle display-4 text-success me-3"></i>
                    <div>
                        <div class="h3 mb-1"><?php echo $online_machines; ?></div>
                        <div class="text-muted">Online</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6">
        <div class="card admin-card stat-card">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <i class="bi bi-x-circle display-4 text-danger me-3"></i>
                    <div>
                        <div class="h3 mb-1"><?php echo $offline_machines; ?></div>
                        <div class="text-muted">Offline</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6">
        <div class="card admin-card stat-card">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <i class="bi bi-activity display-4 text-info me-3"></i>
                    <div>
                        <div class="h3 mb-1"><?php echo $total_machines > 0 ? round(($online_machines / $total_machines) * 100) : 0; ?>%</div>
                        <div class="text-muted">Uptime</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Machines Table -->
<div class="card admin-card">
    <div class="card-header bg-transparent">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <i class="bi bi-list me-2"></i>All Machines
            </h5>
            <div class="d-flex gap-2">
                <div class="input-group input-group-sm" style="width: 250px;">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control" placeholder="Search machines..." id="machineSearch">
                </div>
                <select class="form-select form-select-sm" id="statusFilter" style="width: auto;">
                    <option value="">All Status</option>
                    <option value="online">Online</option>
                    <option value="offline">Offline</option>
                    <option value="maintenance">Maintenance</option>
                </select>
            </div>
        </div>
    </div>
    <div class="card-body">
        <?php if (empty($machines)): ?>
            <div class="text-center py-5">
                <i class="bi bi-hdd-stack display-1 text-muted"></i>
                <h5 class="text-muted mt-3">No machines configured</h5>
                <p class="text-muted">Add your first Nayax vending machine to get started.</p>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addMachineModal">
                    <i class="bi bi-plus-lg me-1"></i>Add First Machine
                </button>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover" id="machinesTable">
                    <thead class="table-dark">
                        <tr>
                            <th>Status</th>
                            <th>Device ID</th>
                            <th>Business</th>
                            <th>Location</th>
                            <th>Revenue</th>
                            <th>Transactions</th>
                            <th>Last Activity</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($machines as $machine): ?>
                        <tr>
                            <td>
                                <?php
                                $status = $machine['status'] ?? 'unknown';
                                $statusClass = match($status) {
                                    'online' => 'success',
                                    'offline' => 'danger',
                                    'maintenance' => 'warning',
                                    default => 'secondary'
                                };
                                ?>
                                <span class="badge bg-<?php echo $statusClass; ?>">
                                    <i class="bi bi-circle-fill me-1" style="font-size: 0.5rem;"></i>
                                    <?php echo ucfirst($status); ?>
                                </span>
                            </td>
                            <td>
                                <code><?php echo htmlspecialchars($machine['device_id'] ?? 'N/A'); ?></code>
                            </td>
                            <td>
                                <a href="<?php echo APP_URL; ?>/admin/manage-businesses.php?id=<?php echo $machine['business_id']; ?>" class="text-decoration-none">
                                    <?php echo htmlspecialchars($machine['business_name'] ?? 'Unknown'); ?>
                                </a>
                            </td>
                            <td><?php echo htmlspecialchars($machine['location_description'] ?? 'No location set'); ?></td>
                            <td>
                                <span class="text-success fw-bold">
                                    $<?php echo number_format($machine['total_revenue'] ?? 0, 2); ?>
                                </span>
                            </td>
                            <td><?php echo number_format($machine['transaction_count'] ?? 0); ?></td>
                            <td>
                                <?php if ($machine['last_transaction']): ?>
                                    <small class="text-muted">
                                        <?php echo date('M j, g:i A', strtotime($machine['last_transaction'])); ?>
                                    </small>
                                <?php else: ?>
                                    <small class="text-muted">No activity</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-outline-primary" onclick="viewMachine('<?php echo $machine['id']; ?>')">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                    <button class="btn btn-outline-secondary" onclick="editMachine('<?php echo $machine['id']; ?>')">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button class="btn btn-outline-danger" onclick="removeMachine('<?php echo $machine['id']; ?>')">
                                        <i class="bi bi-trash"></i>
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

<!-- Add Machine Modal -->
<div class="modal fade" id="addMachineModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-plus-lg me-2"></i>Add New Machine
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="addMachineForm">
                    <div class="mb-3">
                        <label class="form-label">Business</label>
                        <select class="form-select" name="business_id" required>
                            <option value="">Select Business</option>
                            <?php
                            $businesses = $pdo->query("SELECT id, name FROM businesses ORDER BY name")->fetchAll();
                            foreach ($businesses as $business):
                            ?>
                                <option value="<?php echo $business['id']; ?>"><?php echo htmlspecialchars($business['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nayax Machine ID</label>
                        <input type="text" class="form-control" name="nayax_machine_id" required placeholder="e.g., NYX-12345">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Device ID</label>
                        <input type="text" class="form-control" name="device_id" required placeholder="e.g., DEV-67890">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Location Description</label>
                        <input type="text" class="form-control" name="location_description" required placeholder="e.g., Office Building Lobby">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status">
                            <option value="online">Online</option>
                            <option value="offline">Offline</option>
                            <option value="maintenance">Maintenance</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveMachine()">
                    <i class="bi bi-check-lg me-1"></i>Add Machine
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Search and filter functionality
document.getElementById('machineSearch').addEventListener('input', filterTable);
document.getElementById('statusFilter').addEventListener('change', filterTable);

function filterTable() {
    const searchTerm = document.getElementById('machineSearch').value.toLowerCase();
    const statusFilter = document.getElementById('statusFilter').value;
    const table = document.getElementById('machinesTable');
    const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');

    for (let row of rows) {
        const text = row.textContent.toLowerCase();
        const status = row.querySelector('.badge').textContent.trim().toLowerCase();
        
        const matchesSearch = text.includes(searchTerm);
        const matchesStatus = !statusFilter || status.includes(statusFilter);
        
        row.style.display = (matchesSearch && matchesStatus) ? '' : 'none';
    }
}

function refreshMachines() {
    location.reload();
}

function viewMachine(id) {
    // Would show detailed machine information
    alert('View machine details (to be implemented)');
}

function editMachine(id) {
    // Would open edit modal
    alert('Edit machine (to be implemented)');
}

function removeMachine(id) {
    if (confirm('Are you sure you want to remove this machine?')) {
        // Would send delete request
        alert('Remove machine (to be implemented)');
    }
}

function saveMachine() {
    const form = document.getElementById('addMachineForm');
    const formData = new FormData(form);
    
    // Would send save request
    alert('Save machine (to be implemented)');
    
    // Close modal and refresh
    bootstrap.Modal.getInstance(document.getElementById('addMachineModal')).hide();
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?> 