<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/csrf.php';
require_once __DIR__ . '/../core/business_system_detector.php';
require_once __DIR__ . '/../core/services/UnifiedSyncEngine.php';

// Require business role
require_role('business');

// Generate CSRF token
$csrf_token = generate_csrf_token();

$message = '';
$message_type = '';

// Get business details
$stmt = $pdo->prepare("SELECT b.id FROM businesses b JOIN users u ON b.id = u.business_id WHERE u.id = ?");
$stmt->execute([$_SESSION['user_id']]);
$business = $stmt->fetch();
$business_id = $business ? $business['id'] : 0;

// Initialize systems
BusinessSystemDetector::init($pdo);
$capabilities = BusinessSystemDetector::getBusinessCapabilities($business_id);
$syncEngine = new UnifiedSyncEngine($pdo);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $message = 'Invalid security token';
        $message_type = 'danger';
    } else {
        switch ($_POST['action']) {
            case 'create_mapping':
                $manualItemId = intval($_POST['manual_item_id']);
                $nayaxMachineId = trim($_POST['nayax_machine_id']);
                $nayaxSelection = trim($_POST['nayax_selection']);
                $notes = trim($_POST['notes'] ?? '');
                
                $result = $syncEngine->createItemMapping($business_id, $manualItemId, $nayaxMachineId, $nayaxSelection, $notes);
                
                if ($result['success']) {
                    $message = 'Item mapping created successfully!';
                    $message_type = 'success';
                } else {
                    $message = 'Error creating mapping: ' . $result['error'];
                    $message_type = 'danger';
                }
                break;
                
            case 'get_suggestions':
                // Handle AJAX request for smart suggestions
                if (isset($_POST['ajax'])) {
                    header('Content-Type: application/json');
                    $suggestions = $syncEngine->suggestItemMappings($business_id);
                    echo json_encode($suggestions);
                    exit;
                }
                break;
        }
    }
}

// Get current mappings
$stmt = $pdo->prepare("
    SELECT 
        uim.*,
        mi_manual.name as manual_item_name,
        mi_manual.brand as manual_brand,
        nm.machine_name as nayax_machine_name,
        uis.sync_status,
        uis.last_synced_at,
        uis.total_available_qty
    FROM unified_item_mapping uim
    LEFT JOIN master_items mi_manual ON uim.manual_item_id = mi_manual.id
    LEFT JOIN nayax_machines nm ON uim.nayax_machine_id = nm.nayax_machine_id AND nm.business_id = ?
    LEFT JOIN unified_inventory_status uis ON uim.id = uis.mapping_id
    WHERE uim.business_id = ? AND uim.deleted_at IS NULL
    ORDER BY uim.created_at DESC
");
$stmt->execute([$business_id, $business_id]);
$currentMappings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get smart suggestions
$smartSuggestions = $syncEngine->suggestItemMappings($business_id);

require_once __DIR__ . '/../core/includes/header.php';
?>

<div class="container-fluid px-0">
    <!-- Header Section -->
    <div class="bg-gradient-info text-white">
        <div class="container py-4">
            <div class="row align-items-center">
                <div class="col">
                    <h1 class="h3 mb-1 text-white">
                        <i class="bi bi-diagram-3 me-2"></i>Smart Item Mapping
                        <?php if ($capabilities['is_unified']): ?>
                            <span class="badge bg-warning text-dark">🔗 Unified System</span>
                        <?php endif; ?>
                    </h1>
                    <p class="mb-0 opacity-75">
                        Connect your manual inventory with Nayax machines for unified tracking
                    </p>
                </div>
                <div class="col-auto">
                    <button class="btn btn-outline-light" onclick="refreshSuggestions()">
                        <i class="bi bi-arrow-clockwise me-2"></i>Refresh Suggestions
                    </button>
                </div>
            </div>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show mx-4 mt-4" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="container-fluid py-4">
        <div class="row">
            <!-- System Status Card -->
            <div class="col-12 mb-4">
                <div class="card border-info">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i>System Status</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <div class="text-center">
                                    <h4 class="text-primary"><?php echo $capabilities['manual_count']; ?></h4>
                                    <small class="text-muted">Manual Machines</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center">
                                    <h4 class="text-info"><?php echo $capabilities['nayax_count']; ?></h4>
                                    <small class="text-muted">Nayax Machines</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center">
                                    <h4 class="text-success"><?php echo count($currentMappings); ?></h4>
                                    <small class="text-muted">Active Mappings</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center">
                                    <h4 class="text-warning"><?php echo count($smartSuggestions['suggestions'] ?? []); ?></h4>
                                    <small class="text-muted">Suggestions Available</small>
                                </div>
                            </div>
                        </div>
                        
                        <?php if (!$capabilities['has_nayax']): ?>
                            <div class="alert alert-warning mt-3 mb-0">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                <strong>No Nayax machines detected.</strong> 
                                You need to have Nayax machines configured to create unified mappings.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Manual Mapping Form -->
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="bi bi-plus-circle me-2"></i>Create New Mapping</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="mappingForm">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <input type="hidden" name="action" value="create_mapping">
                            
                            <!-- Manual Item Selection -->
                            <div class="mb-3">
                                <label for="manual_item_id" class="form-label">Manual Item</label>
                                <select class="form-select" id="manual_item_id" name="manual_item_id" required>
                                    <option value="">Select a manual item...</option>
                                    <?php
                                    // Get unmapped manual items
                                    $stmt = $pdo->prepare("
                                        SELECT vli.id, mi.name, mi.brand, vli.inventory
                                        FROM voting_list_items vli
                                        JOIN voting_lists vl ON vli.voting_list_id = vl.id
                                        JOIN master_items mi ON vli.master_item_id = mi.id
                                        WHERE vl.business_id = ?
                                        AND vli.id NOT IN (
                                            SELECT manual_item_id FROM unified_item_mapping 
                                            WHERE business_id = ? AND deleted_at IS NULL
                                        )
                                        ORDER BY mi.name
                                    ");
                                    $stmt->execute([$business_id, $business_id]);
                                    $unmappedItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                    
                                    foreach ($unmappedItems as $item): ?>
                                        <option value="<?php echo $item['id']; ?>">
                                            <?php echo htmlspecialchars($item['name']); ?>
                                            <?php if ($item['brand']): ?>
                                                (<?php echo htmlspecialchars($item['brand']); ?>)
                                            <?php endif; ?>
                                            - Stock: <?php echo $item['inventory']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <!-- Nayax Machine Selection -->
                            <div class="mb-3">
                                <label for="nayax_machine_id" class="form-label">Nayax Machine</label>
                                <select class="form-select" id="nayax_machine_id" name="nayax_machine_id" required>
                                    <option value="">Select a Nayax machine...</option>
                                    <?php
                                    $stmt = $pdo->prepare("
                                        SELECT nayax_machine_id, machine_name, status
                                        FROM nayax_machines 
                                        WHERE business_id = ? AND status != 'inactive'
                                        ORDER BY machine_name
                                    ");
                                    $stmt->execute([$business_id]);
                                    $nayaxMachines = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                    
                                    foreach ($nayaxMachines as $machine): ?>
                                        <option value="<?php echo htmlspecialchars($machine['nayax_machine_id']); ?>">
                                            <?php echo htmlspecialchars($machine['machine_name']); ?>
                                            (<?php echo ucfirst($machine['status']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <!-- Nayax Item Selection -->
                            <div class="mb-3">
                                <label for="nayax_selection" class="form-label">Nayax Item Selection</label>
                                <input type="text" class="form-control" id="nayax_selection" name="nayax_selection" required
                                       placeholder="e.g., A1, B2, 01, etc.">
                                <div class="form-text">Enter the item slot/selection code from the Nayax machine</div>
                            </div>
                            
                            <!-- Notes -->
                            <div class="mb-3">
                                <label for="notes" class="form-label">Notes (Optional)</label>
                                <textarea class="form-control" id="notes" name="notes" rows="2" 
                                          placeholder="Add any notes about this mapping..."></textarea>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-plus-circle me-2"></i>Create Mapping
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Current Mappings -->
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="bi bi-list-check me-2"></i>Current Mappings</h5>
                    </div>
                    <div class="card-body" style="max-height: 500px; overflow-y: auto;">
                        <?php if (empty($currentMappings)): ?>
                            <div class="text-center text-muted py-4">
                                <i class="bi bi-diagram-3 display-1 opacity-25"></i>
                                <p class="mt-2">No mappings created yet</p>
                                <small>Create your first mapping using the form on the left</small>
                            </div>
                        <?php else: ?>
                            <?php foreach ($currentMappings as $mapping): ?>
                                <div class="card mb-2 border-success">
                                    <div class="card-body p-3">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div class="flex-grow-1">
                                                <h6 class="mb-1">
                                                    <?php echo htmlspecialchars($mapping['manual_item_name']); ?>
                                                    <span class="badge bg-warning text-dark">🔗</span>
                                                    <?php echo htmlspecialchars($mapping['nayax_machine_name'] ?? 'Unknown Machine'); ?>
                                                </h6>
                                                <small class="text-muted">
                                                    Selection: <?php echo htmlspecialchars($mapping['nayax_item_selection']); ?> |
                                                    Confidence: <?php echo $mapping['mapping_confidence']; ?>% |
                                                    Stock: <?php echo $mapping['total_available_qty'] ?? 0; ?>
                                                </small>
                                                <div class="mt-1">
                                                    <?php
                                                    $statusBadge = 'secondary';
                                                    $statusText = 'Unknown';
                                                    if ($mapping['sync_status'] === 'synced') {
                                                        $statusBadge = 'success';
                                                        $statusText = '✅ Synced';
                                                    } elseif ($mapping['sync_status'] === 'partial') {
                                                        $statusBadge = 'warning';
                                                        $statusText = '⚠️ Partial';
                                                    } elseif ($mapping['sync_status'] === 'unsynced') {
                                                        $statusBadge = 'danger';
                                                        $statusText = '❌ Unsynced';
                                                    }
                                                    ?>
                                                    <span class="badge bg-<?php echo $statusBadge; ?>"><?php echo $statusText; ?></span>
                                                    <?php if ($mapping['last_synced_at']): ?>
                                                        <small class="text-muted ms-2">
                                                            Last sync: <?php echo date('M j, g:i A', strtotime($mapping['last_synced_at'])); ?>
                                                        </small>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Smart Suggestions -->
            <?php if (!empty($smartSuggestions['suggestions'])): ?>
                <div class="col-12 mb-4">
                    <div class="card">
                        <div class="card-header bg-warning text-dark">
                            <h5 class="mb-0"><i class="bi bi-lightbulb me-2"></i>Smart Mapping Suggestions</h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <?php foreach ($smartSuggestions['suggestions'] as $suggestion): ?>
                                    <?php if (isset($suggestion['manual_item'])): ?>
                                        <div class="col-md-6 col-lg-4">
                                            <div class="card border-warning h-100">
                                                <div class="card-body">
                                                    <h6 class="card-title text-warning">
                                                        <i class="bi bi-arrow-left-right me-2"></i>
                                                        <?php echo htmlspecialchars($suggestion['manual_item']['name']); ?>
                                                    </h6>
                                                    <p class="text-muted small mb-2">
                                                        Brand: <?php echo htmlspecialchars($suggestion['manual_item']['brand'] ?? 'N/A'); ?>
                                                    </p>
                                                    
                                                    <?php if (!empty($suggestion['suggested_matches'])): ?>
                                                        <div class="mb-3">
                                                            <strong>Suggested Matches:</strong>
                                                            <?php foreach ($suggestion['suggested_matches'] as $i => $match): ?>
                                                                <div class="mt-2 p-2 bg-light rounded">
                                                                    <div class="d-flex justify-content-between align-items-start">
                                                                        <div>
                                                                            <strong><?php echo htmlspecialchars($match['item_name']); ?></strong><br>
                                                                            <small class="text-muted">
                                                                                Machine: <?php echo htmlspecialchars($match['machine_name'] ?? 'Unknown'); ?><br>
                                                                                Selection: <?php echo htmlspecialchars($match['item_selection']); ?>
                                                                            </small>
                                                                        </div>
                                                                        <span class="badge bg-success">
                                                                            <?php echo round($suggestion['confidence_scores'][$i] ?? 0); ?>%
                                                                        </span>
                                                                    </div>
                                                                    <button class="btn btn-sm btn-warning mt-2" 
                                                                            onclick="createSuggestedMapping(
                                                                                <?php echo $suggestion['manual_item']['id']; ?>,
                                                                                '<?php echo htmlspecialchars($match['nayax_machine_id']); ?>',
                                                                                '<?php echo htmlspecialchars($match['item_selection']); ?>'
                                                                            )">
                                                                        <i class="bi bi-plus-circle me-2"></i>Create Mapping
                                                                    </button>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Create suggested mapping
function createSuggestedMapping(manualItemId, nayaxMachineId, nayaxSelection) {
    document.getElementById('manual_item_id').value = manualItemId;
    document.getElementById('nayax_machine_id').value = nayaxMachineId;
    document.getElementById('nayax_selection').value = nayaxSelection;
    document.getElementById('notes').value = 'Auto-suggested mapping';
    
    // Scroll to form
    document.getElementById('mappingForm').scrollIntoView({behavior: 'smooth'});
}

// Refresh suggestions
function refreshSuggestions() {
    location.reload();
}
</script>

<?php require_once __DIR__ . '/../core/includes/footer.php'; ?> 