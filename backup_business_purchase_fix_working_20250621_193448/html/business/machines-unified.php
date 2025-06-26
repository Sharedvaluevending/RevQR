<?php
/**
 * Unified Machine Management Interface
 * Shows both Manual and Nayax machines with appropriate controls for each type
 */

require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/business_system_detector.php';

// Require business role
require_role('business');

// Initialize system detector
BusinessSystemDetector::init($pdo);

// Get business ID and capabilities
$business_id = get_business_id();
if (!$business_id) {
    header('Location: ' . APP_URL . '/business/profile.php?error=no_business');
    exit;
}

$capabilities = BusinessSystemDetector::getBusinessCapabilities($business_id);

// Get unified machine data
function getUnifiedMachines($business_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT 
                system_type,
                machine_id,
                machine_name,
                location,
                status,
                activity_count,
                today_activity,
                week_activity,
                revenue,
                created_at
            FROM unified_machine_performance 
            WHERE business_id = ?
            ORDER BY system_type, machine_name
        ");
        $stmt->execute([$business_id]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Unified Machines Error: " . $e->getMessage());
        return [];
    }
}

$machines = getUnifiedMachines($business_id);

// Handle machine actions
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        switch ($_POST['action']) {
            case 'add_manual':
                // Redirect to manual machine creation
                header('Location: ' . APP_URL . '/business/create-campaign.php');
                exit;
                break;
                
            case 'add_nayax':
                // Redirect to Nayax machine setup
                header('Location: ' . APP_URL . '/business/nayax-machines.php?action=add');
                exit;
                break;
                
            case 'refresh_cache':
                BusinessSystemDetector::clearCache($business_id);
                $message = 'Machine data refreshed successfully!';
                $message_type = 'success';
                break;
        }
    } catch (Exception $e) {
        $message = 'Error: ' . $e->getMessage();
        $message_type = 'danger';
    }
}

require_once __DIR__ . '/../core/includes/header.php';
?>

<style>
.machine-card {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 12px;
    transition: all 0.3s ease;
}

.machine-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
    border: 1px solid rgba(255, 255, 255, 0.3);
}

.system-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.system-manual {
    background: rgba(25, 135, 84, 0.2);
    color: #198754;
    border: 1px solid rgba(25, 135, 84, 0.4);
}

.system-nayax {
    background: rgba(13, 110, 253, 0.2);
    color: #0d6efd;
    border: 1px solid rgba(13, 110, 253, 0.4);
}

.status-badge {
    padding: 0.25rem 0.5rem;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
}

.status-active {
    background: rgba(25, 135, 84, 0.2);
    color: #198754;
}

.status-inactive {
    background: rgba(220, 53, 69, 0.2);
    color: #dc3545;
}

.status-maintenance {
    background: rgba(255, 193, 7, 0.2);
    color: #ffc107;
}

.metric-small {
    font-size: 1.25rem;
    font-weight: 600;
    color: #ffffff;
}

.filter-tabs {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    padding: 0.5rem;
    margin-bottom: 2rem;
}

.filter-tab {
    background: transparent;
    border: none;
    color: rgba(255, 255, 255, 0.7);
    padding: 0.5rem 1rem;
    border-radius: 8px;
    transition: all 0.3s ease;
}

.filter-tab.active {
    background: rgba(255, 255, 255, 0.2);
    color: #ffffff;
}

.filter-tab:hover {
    background: rgba(255, 255, 255, 0.15);
    color: #ffffff;
}
</style>

<div class="container-fluid mt-4">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-1 text-white">
                        <i class="bi bi-cpu text-primary me-2"></i>Machine Management
                    </h1>
                    <p class="text-light mb-0">
                        Unified view of all your machines - Manual and Nayax systems
                    </p>
                </div>
                <div>
                    <div class="btn-group">
                        <?php if ($capabilities['has_manual'] || $capabilities['total_machines'] == 0): ?>
                            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addManualModal">
                                <i class="bi bi-plus-circle me-1"></i>Add Manual Machine
                            </button>
                        <?php endif; ?>
                        
                        <?php if ($capabilities['has_nayax'] || $capabilities['total_machines'] == 0): ?>
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addNayaxModal">
                                <i class="bi bi-plus-circle me-1"></i>Add Nayax Machine
                            </button>
                        <?php endif; ?>
                        
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="action" value="refresh_cache">
                            <button type="submit" class="btn btn-outline-light">
                                <i class="bi bi-arrow-clockwise me-1"></i>Refresh
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Messages -->
    <?php if ($message): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- System Overview -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="machine-card p-3 text-center">
                <div class="metric-small"><?php echo $capabilities['total_machines']; ?></div>
                <div class="text-light small">Total Machines</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="machine-card p-3 text-center">
                <div class="metric-small"><?php echo $capabilities['manual_count']; ?></div>
                <div class="text-light small">Manual Machines</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="machine-card p-3 text-center">
                <div class="metric-small"><?php echo $capabilities['nayax_count']; ?></div>
                <div class="text-light small">Nayax Machines</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="machine-card p-3 text-center">
                <div class="metric-small">
                    <?php 
                    $active_count = 0;
                    foreach ($machines as $machine) {
                        if ($machine['status'] === 'active') $active_count++;
                    }
                    echo $active_count;
                    ?>
                </div>
                <div class="text-light small">Active Machines</div>
            </div>
        </div>
    </div>

    <!-- Filter Tabs -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="filter-tabs">
                <button class="filter-tab active" onclick="filterMachines('all')">
                    <i class="bi bi-grid me-1"></i>All Machines
                </button>
                <?php if ($capabilities['has_manual']): ?>
                    <button class="filter-tab" onclick="filterMachines('manual')">
                        <i class="bi bi-qr-code me-1"></i>Manual Only
                    </button>
                <?php endif; ?>
                <?php if ($capabilities['has_nayax']): ?>
                    <button class="filter-tab" onclick="filterMachines('nayax')">
                        <i class="bi bi-wifi me-1"></i>Nayax Only
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Machines Grid -->
    <div class="row" id="machinesGrid">
        <?php if (empty($machines)): ?>
            <div class="col-12">
                <div class="machine-card p-5 text-center">
                    <i class="bi bi-cpu text-muted" style="font-size: 4rem;"></i>
                    <h4 class="text-white mt-3">No Machines Found</h4>
                    <p class="text-light">Get started by adding your first machine to the platform.</p>
                    <div class="mt-4">
                        <button type="button" class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#addManualModal">
                            <i class="bi bi-plus-circle me-1"></i>Add Manual Machine
                        </button>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addNayaxModal">
                            <i class="bi bi-plus-circle me-1"></i>Add Nayax Machine
                        </button>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($machines as $machine): ?>
                <div class="col-lg-4 col-md-6 mb-4 machine-item" data-system="<?php echo $machine['system_type']; ?>">
                    <div class="machine-card p-4">
                        <!-- Header -->
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <h5 class="text-white mb-1"><?php echo htmlspecialchars($machine['machine_name']); ?></h5>
                                <?php if ($machine['location']): ?>
                                    <small class="text-light">
                                        <i class="bi bi-geo-alt me-1"></i><?php echo htmlspecialchars($machine['location']); ?>
                                    </small>
                                <?php endif; ?>
                            </div>
                            <div class="text-end">
                                <span class="system-badge system-<?php echo $machine['system_type']; ?>">
                                    <?php echo ucfirst($machine['system_type']); ?>
                                </span>
                                <br>
                                <span class="status-badge status-<?php echo $machine['status']; ?> mt-1">
                                    <?php echo ucfirst($machine['status']); ?>
                                </span>
                            </div>
                        </div>

                        <!-- Metrics -->
                        <div class="row mb-3">
                            <div class="col-4 text-center">
                                <div class="metric-small"><?php echo number_format($machine['today_activity']); ?></div>
                                <div class="text-light small">Today</div>
                            </div>
                            <div class="col-4 text-center">
                                <div class="metric-small"><?php echo number_format($machine['week_activity']); ?></div>
                                <div class="text-light small">This Week</div>
                            </div>
                            <div class="col-4 text-center">
                                <?php if ($machine['revenue']): ?>
                                    <div class="metric-small">$<?php echo number_format($machine['revenue'], 0); ?></div>
                                    <div class="text-light small">Revenue</div>
                                <?php else: ?>
                                    <div class="metric-small"><?php echo number_format($machine['activity_count']); ?></div>
                                    <div class="text-light small">Total</div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="d-flex gap-2">
                            <?php if ($machine['system_type'] === 'manual'): ?>
                                <a href="<?php echo APP_URL; ?>/business/manage-lists.php?machine_id=<?php echo $machine['machine_id']; ?>" 
                                   class="btn btn-success btn-sm flex-fill">
                                    <i class="bi bi-gear me-1"></i>Manage
                                </a>
                                <a href="<?php echo APP_URL; ?>/qr-generator.php?machine_id=<?php echo $machine['machine_id']; ?>" 
                                   class="btn btn-outline-success btn-sm">
                                    <i class="bi bi-qr-code"></i>
                                </a>
                            <?php else: ?>
                                <a href="<?php echo APP_URL; ?>/business/nayax-machines.php?machine_id=<?php echo $machine['machine_id']; ?>" 
                                   class="btn btn-primary btn-sm flex-fill">
                                    <i class="bi bi-gear me-1"></i>Manage
                                </a>
                                <a href="<?php echo APP_URL; ?>/business/nayax-analytics.php?machine_id=<?php echo $machine['machine_id']; ?>" 
                                   class="btn btn-outline-primary btn-sm">
                                    <i class="bi bi-graph-up"></i>
                                </a>
                            <?php endif; ?>
                        </div>

                        <!-- Created Date -->
                        <div class="text-center mt-3">
                            <small class="text-muted">
                                Added <?php echo date('M j, Y', strtotime($machine['created_at'])); ?>
                            </small>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Add Manual Machine Modal -->
<div class="modal fade" id="addManualModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark text-light">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-qr-code me-2"></i>Add Manual Machine
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Manual machines use QR codes for customer interaction and voting campaigns.</p>
                <div class="row">
                    <div class="col-6">
                        <h6 class="text-success">Features Include:</h6>
                        <ul class="small">
                            <li>QR Code Generation</li>
                            <li>Voting Campaigns</li>
                            <li>Spin Wheel Games</li>
                            <li>Casino Features</li>
                            <li>Manual Sales Tracking</li>
                        </ul>
                    </div>
                    <div class="col-6">
                        <h6 class="text-info">Perfect For:</h6>
                        <ul class="small">
                            <li>Customer Engagement</li>
                            <li>Product Feedback</li>
                            <li>Gamification</li>
                            <li>Brand Interaction</li>
                            <li>Loyalty Programs</li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" class="d-inline">
                    <input type="hidden" name="action" value="add_manual">
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-plus-circle me-1"></i>Create Manual Machine
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Add Nayax Machine Modal -->
<div class="modal fade" id="addNayaxModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark text-light">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-wifi me-2"></i>Add Nayax Machine
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Nayax machines provide real-time monitoring and automated transaction processing.</p>
                <div class="row">
                    <div class="col-6">
                        <h6 class="text-primary">Features Include:</h6>
                        <ul class="small">
                            <li>Real-time Monitoring</li>
                            <li>Automated Transactions</li>
                            <li>Advanced Analytics</li>
                            <li>Payment Processing</li>
                            <li>Inventory Tracking</li>
                        </ul>
                    </div>
                    <div class="col-6">
                        <h6 class="text-warning">Perfect For:</h6>
                        <ul class="small">
                            <li>High-volume Sales</li>
                            <li>Automated Operations</li>
                            <li>Revenue Optimization</li>
                            <li>Customer Intelligence</li>
                            <li>Remote Management</li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" class="d-inline">
                    <input type="hidden" name="action" value="add_nayax">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-plus-circle me-1"></i>Setup Nayax Machine
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function filterMachines(type) {
    // Update active tab
    document.querySelectorAll('.filter-tab').forEach(tab => {
        tab.classList.remove('active');
    });
    event.target.classList.add('active');
    
    // Filter machines
    const machines = document.querySelectorAll('.machine-item');
    machines.forEach(machine => {
        const systemType = machine.getAttribute('data-system');
        if (type === 'all' || systemType === type) {
            machine.style.display = 'block';
        } else {
            machine.style.display = 'none';
        }
    });
}
</script>

<?php require_once __DIR__ . '/../core/includes/footer.php'; ?> 