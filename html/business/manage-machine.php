<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/functions.php';

// Require business role
require_role('business');

$message = '';
$message_type = '';

// Get business details
$stmt = $pdo->prepare("
    SELECT b.*, u.username 
    FROM businesses b 
    JOIN users u ON b.id = u.business_id 
    WHERE u.id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$business = $stmt->fetch();

// Get all machines with their QR codes - Updated query to include ALL QR types including machine_sales
$stmt = $pdo->prepare("
    SELECT 
        qr.machine_name,
        qr.machine_location as location,
        qr.qr_type,
        qr.meta,
        qr.created_at as last_updated,
        COALESCE(c.name, 'Direct QR Code') as campaign_name,
        COUNT(qr.id) as qr_count
    FROM qr_codes qr
    LEFT JOIN campaigns c ON qr.campaign_id = c.id
    WHERE (
        -- Direct business ownership
        qr.business_id = ? OR
        
        -- Business ownership through metadata
        JSON_UNQUOTE(JSON_EXTRACT(qr.meta, '$.business_id')) = ? OR
        
        -- Business ownership through campaigns
        c.business_id = ?
    ) 
    AND qr.status = 'active'
    AND qr.machine_name IS NOT NULL
    GROUP BY qr.machine_name, qr.machine_location, qr.qr_type, 
             qr.meta, qr.created_at, c.name
    ORDER BY qr.created_at DESC
");
$stmt->execute([$business['id'], $business['id'], $business['id']]);
$machines = $stmt->fetchAll();

require_once __DIR__ . '/../core/includes/header.php';
?>

<style>
/* Enhanced table styling for better visibility */
.table {
    background: rgba(255, 255, 255, 0.15) !important;
    backdrop-filter: blur(20px) !important;
    border-radius: 12px !important;
    overflow: hidden !important;
}

.table thead th {
    background: rgba(30, 60, 114, 0.6) !important;
    color: #ffffff !important;
    font-weight: 600 !important;
    border-bottom: 2px solid rgba(255, 255, 255, 0.3) !important;
    padding: 1rem 0.75rem !important;
}

.table tbody td {
    background: rgba(255, 255, 255, 0.12) !important;
    color: rgba(255, 255, 255, 0.95) !important;
    border-bottom: 1px solid rgba(255, 255, 255, 0.15) !important;
    padding: 0.875rem 0.75rem !important;
}

.table tbody tr:hover td {
    background: rgba(255, 255, 255, 0.18) !important;
    color: #ffffff !important;
}

/* Button styling inside tables */
.table .btn-outline-primary,
.table .btn-outline-secondary,
.table .btn-outline-success,
.table .btn-outline-danger {
    background: rgba(255, 255, 255, 0.1) !important;
    border-color: rgba(255, 255, 255, 0.3) !important;
    color: rgba(255, 255, 255, 0.9) !important;
}

.table .btn-outline-primary:hover {
    background: rgba(13, 110, 253, 0.8) !important;
    color: #ffffff !important;
}

.table .btn-outline-secondary:hover {
    background: rgba(108, 117, 125, 0.8) !important;
    color: #ffffff !important;
}

.table .btn-outline-success:hover {
    background: rgba(25, 135, 84, 0.8) !important;
    color: #ffffff !important;
}

.table .btn-outline-danger:hover {
    background: rgba(220, 53, 69, 0.8) !important;
    color: #ffffff !important;
}

/* Badge styling in tables */
.table .badge {
    background: rgba(255, 255, 255, 0.2) !important;
    color: rgba(255, 255, 255, 0.95) !important;
    border: 1px solid rgba(255, 255, 255, 0.3) !important;
}

.table .badge.bg-primary {
    background: rgba(13, 110, 253, 0.7) !important;
    color: #ffffff !important;
}

.table .badge.bg-success {
    background: rgba(25, 135, 84, 0.7) !important;
    color: #ffffff !important;
}

.table .badge.bg-secondary {
    background: rgba(108, 117, 125, 0.7) !important;
    color: #ffffff !important;
}

/* Small text styling in tables */
.table small.text-muted {
    color: rgba(255, 255, 255, 0.7) !important;
}

/* Empty state styling */
.table tbody td.text-center.text-muted {
    color: rgba(255, 255, 255, 0.7) !important;
}

.text-center.py-4 .text-muted {
    color: rgba(255, 255, 255, 0.8) !important;
}

/* Form styling improvements */
.card .text-muted {
    color: rgba(255, 255, 255, 0.8) !important;
}

/* Date styling in tables */
.table .text-muted {
    color: rgba(255, 255, 255, 0.7) !important;
}
</style>

<div class="row mb-4">
    <div class="col-12">
        <h1 class="h3 mb-0">Manage QR Codes</h1>
        <p class="text-muted">View and manage your QR codes for vending machines</p>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">QR Codes</h5>
                <a href="qr-generator.php" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-lg me-1"></i>Generate New QR Code
                </a>
            </div>
            <div class="card-body">
                <?php if (empty($machines)): ?>
                    <div class="text-center py-4">
                        <p class="text-muted mb-0">No QR codes found</p>
                        <a href="qr-generator.php" class="btn btn-primary mt-3">
                            <i class="bi bi-plus-lg me-1"></i>Generate Your First QR Code
                        </a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Machine Name</th>
                                    <th>Location</th>
                                    <th>Campaign</th>
                                    <th>QR Type</th>
                                    <th>Last Updated</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($machines as $machine): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($machine['machine_name']); ?></td>
                                        <td><?php echo htmlspecialchars($machine['location'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($machine['campaign_name']); ?></td>
                                        <td>
                                            <?php
                                            // Determine badge color based on QR type
                                            $badge_class = 'bg-primary';
                                            $qr_type_display = ucfirst(str_replace('_', ' ', $machine['qr_type']));
                                            
                                            switch($machine['qr_type']) {
                                                case 'machine_sales':
                                                    $badge_class = 'bg-success';
                                                    $qr_type_display = 'Machine Sales';
                                                    break;
                                                                                case 'promotion':
                                    $badge_class = 'bg-warning text-dark';
                                    $qr_type_display = 'Promotion';
                                    break;
                                case 'vending_discount_store':
                                    $badge_class = 'bg-success';
                                    $qr_type_display = 'Discount Store';
                                    break;
                                                case 'dynamic_voting':
                                                    $badge_class = 'bg-primary';
                                                    $qr_type_display = 'Dynamic Voting';
                                                    break;
                                                case 'static':
                                                    $badge_class = 'bg-secondary';
                                                    $qr_type_display = 'Static';
                                                    break;
                                                default:
                                                    $badge_class = 'bg-primary';
                                                    break;
                                            }
                                            ?>
                                            <span class="badge <?php echo $badge_class; ?>">
                                                <?php echo $qr_type_display; ?>
                                            </span>
                                            <?php 
                                            $meta = json_decode($machine['meta'], true);
                                            if ($meta && isset($meta['label_text'])) {
                                                echo '<br><small class="text-muted">' . htmlspecialchars($meta['label_text']) . '</small>';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <?php 
                                            if ($machine['last_updated']) {
                                                echo date('M j, Y', strtotime($machine['last_updated']));
                                            } else {
                                                echo '<span class="text-muted">Never</span>';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="view-qr.php?machine_name=<?php echo urlencode($machine['machine_name']); ?>" 
                                                   class="btn btn-outline-primary" 
                                                   title="View QR Code">
                                                    <i class="bi bi-qr-code"></i>
                                                </a>
                                                <?php if (in_array($machine['qr_type'], ['dynamic_voting', 'promotion'])): ?>
                                                    <a href="view-votes.php?machine_name=<?php echo urlencode($machine['machine_name']); ?>" 
                                                       class="btn btn-outline-success" 
                                                       title="View Votes">
                                                        <i class="bi bi-bar-chart"></i>
                                                    </a>
                                                <?php elseif ($machine['qr_type'] === 'machine_sales'): ?>
                                                    <a href="machine-sales.php?machine=<?php echo urlencode($machine['machine_name']); ?>" 
                                                       class="btn btn-outline-success" 
                                                       title="Manage Sales">
                                                        <i class="bi bi-cart-plus"></i>
                                                    </a>
                                                <?php elseif ($machine['qr_type'] === 'promotion'): ?>
                                                    <a href="promotions.php?machine=<?php echo urlencode($machine['machine_name']); ?>" 
                                                       class="btn btn-outline-warning" 
                                                       title="Manage Promotions">
                                                        <i class="bi bi-gift"></i>
                                                    </a>
                                                <?php else: ?>
                                                    <a href="view-votes.php?machine_name=<?php echo urlencode($machine['machine_name']); ?>" 
                                                       class="btn btn-outline-success" 
                                                       title="View Details">
                                                        <i class="bi bi-info-circle"></i>
                                                    </a>
                                                <?php endif; ?>
                                                <button type="button" 
                                                        class="btn btn-outline-danger" 
                                                        title="Delete Machine"
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#deleteModal"
                                                        data-machine="<?php echo htmlspecialchars($machine['machine_name']); ?>">
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
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteModalLabel">Delete Machine</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this machine? This will also delete all associated QR codes and voting data.</p>
                <p class="text-danger">This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" action="delete-machine.php" class="d-inline">
                    <input type="hidden" name="machine_name" id="deleteMachineName">
                    <button type="submit" class="btn btn-danger">Delete Machine</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const deleteModal = document.getElementById('deleteModal');
    if (deleteModal) {
        deleteModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const machineName = button.getAttribute('data-machine');
            document.getElementById('deleteMachineName').value = machineName;
        });
    }
});
</script>

<?php require_once __DIR__ . '/../core/includes/footer.php'; ?> 