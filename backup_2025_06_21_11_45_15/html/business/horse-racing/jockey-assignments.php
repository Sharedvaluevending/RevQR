<?php
/**
 * Business Jockey Assignment Management
 * Allows businesses to assign custom jockeys to specific items
 */

require_once __DIR__ . '/../../core/config.php';
require_once __DIR__ . '/../../core/session.php';
require_once __DIR__ . '/../../core/auth.php';

// Require business role
require_role('business');

$business_id = $_SESSION['business_id'];

$message = '';
$message_type = '';

// Handle jockey assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_jockey'])) {
    $item_id = intval($_POST['item_id']);
    $jockey_name = trim($_POST['jockey_name']);
    $jockey_color = $_POST['jockey_color'];
    $jockey_avatar = trim($_POST['jockey_avatar']);
    
    if ($item_id && $jockey_name && $jockey_color) {
        try {
            // Verify item belongs to business
            $stmt = $pdo->prepare("
                SELECT vli.id, vli.item_name, vl.name as machine_name 
                FROM voting_list_items vli 
                JOIN voting_lists vl ON vli.voting_list_id = vl.id 
                WHERE vli.id = ? AND vl.business_id = ?
            ");
            $stmt->execute([$item_id, $business_id]);
            $item = $stmt->fetch();
            
            if (!$item) {
                throw new Exception("Item not found or doesn't belong to your business");
            }
            
            // Insert or update jockey assignment
            $stmt = $pdo->prepare("
                INSERT INTO item_jockey_assignments 
                (business_id, item_id, custom_jockey_name, custom_jockey_avatar_url, custom_jockey_color)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                custom_jockey_name = VALUES(custom_jockey_name),
                custom_jockey_avatar_url = VALUES(custom_jockey_avatar_url),
                custom_jockey_color = VALUES(custom_jockey_color),
                updated_at = CURRENT_TIMESTAMP
            ");
            
            $avatar_url = $jockey_avatar ?: '/horse-racing/assets/img/jockeys/jockey-custom.png';
            $stmt->execute([$business_id, $item_id, $jockey_name, $avatar_url, $jockey_color]);
            
            $message = "Jockey assigned successfully to " . htmlspecialchars($item['item_name']);
            $message_type = 'success';
            
        } catch (Exception $e) {
            $message = "Error: " . $e->getMessage();
            $message_type = 'danger';
        }
    } else {
        $message = "Please fill in all required fields";
        $message_type = 'danger';
    }
}

// Handle remove assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_jockey'])) {
    $item_id = intval($_POST['item_id']);
    
    try {
        $stmt = $pdo->prepare("DELETE FROM item_jockey_assignments WHERE business_id = ? AND item_id = ?");
        $stmt->execute([$business_id, $item_id]);
        
        $message = "Jockey assignment removed successfully";
        $message_type = 'success';
        
    } catch (Exception $e) {
        $message = "Error removing assignment: " . $e->getMessage();
        $message_type = 'danger';
    }
}

// Get filter parameters
$selected_machine = isset($_GET['machine_id']) ? intval($_GET['machine_id']) : 0;

// Get business machines for filter dropdown
$stmt = $pdo->prepare("SELECT id, name, description FROM voting_lists WHERE business_id = ? ORDER BY name");
$stmt->execute([$business_id]);
$machines = $stmt->fetchAll();

// Build WHERE clause based on machine filter
$where_clause = "WHERE vl.business_id = ?";
$params = [$business_id];

if ($selected_machine > 0) {
    $where_clause .= " AND vl.id = ?";
    $params[] = $selected_machine;
}

// Get all business items with their current jockey assignments and enhanced sales data
$stmt = $pdo->prepare("
    SELECT vli.*, vl.name as machine_name, vl.description as machine_location,
           ija.custom_jockey_name, ija.custom_jockey_avatar_url, ija.custom_jockey_color,
           ja.jockey_name as default_jockey_name, ja.jockey_avatar_url as default_jockey_avatar_url, ja.jockey_color as default_jockey_color,
           -- Enhanced sales data with Nayax integration
           COALESCE(sales_data.units_sold_24h, 0) as sales_24h,
           COALESCE(sales_data.revenue_24h, 0) as revenue_24h,
           COALESCE(sales_data.units_sold_7d, 0) as sales_7d,
           COALESCE(nayax_data.nayax_transactions_24h, 0) as nayax_sales_24h,
           COALESCE(nayax_data.nayax_revenue_24h, 0) as nayax_revenue_24h,
           -- Performance score for horse racing
           COALESCE(hpc.performance_score, 0) as performance_score
    FROM voting_list_items vli
    JOIN voting_lists vl ON vli.voting_list_id = vl.id
    LEFT JOIN item_jockey_assignments ija ON vli.id = ija.item_id AND ija.business_id = ?
    LEFT JOIN jockey_assignments ja ON LOWER(vli.item_category) = ja.item_type
    -- Sales data aggregation
    LEFT JOIN (
        SELECT 
            item_id,
            SUM(CASE WHEN sale_time >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN quantity ELSE 0 END) as units_sold_24h,
            SUM(CASE WHEN sale_time >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN (quantity * sale_price) ELSE 0 END) as revenue_24h,
            SUM(CASE WHEN sale_time >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN quantity ELSE 0 END) as units_sold_7d
        FROM sales 
        GROUP BY item_id
    ) sales_data ON vli.id = sales_data.item_id
    -- Nayax transaction data (if available)
    LEFT JOIN (
        SELECT 
            vli_inner.id as item_id,
            COUNT(CASE WHEN nt.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as nayax_transactions_24h,
            SUM(CASE WHEN nt.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN nt.amount_cents/100 ELSE 0 END) as nayax_revenue_24h
        FROM voting_list_items vli_inner
        JOIN voting_lists vl_inner ON vli_inner.voting_list_id = vl_inner.id
        LEFT JOIN nayax_machines nm ON vl_inner.id = nm.platform_machine_id
        LEFT JOIN nayax_transactions nt ON nm.nayax_machine_id = nt.nayax_machine_id
        WHERE vl_inner.business_id = ?
        GROUP BY vli_inner.id
    ) nayax_data ON vli.id = nayax_data.item_id
    -- Horse performance cache
    LEFT JOIN horse_performance_cache hpc ON vli.id = hpc.item_id AND hpc.cache_date = CURDATE()
    $where_clause
    ORDER BY vl.name, vli.item_name
");
$stmt->execute(array_merge([$business_id, $business_id], $params));
$items = $stmt->fetchAll();

// Get default jockeys for dropdown
$stmt = $pdo->prepare("SELECT * FROM jockey_assignments WHERE is_active = 1 ORDER BY jockey_name");
$stmt->execute();
$default_jockeys = $stmt->fetchAll();

// Get available jockey images from directory
$jockey_images = [];
$jockey_dir = __DIR__ . '/../../horse-racing/assets/img/jockeys/';
if (is_dir($jockey_dir)) {
    $files = scandir($jockey_dir);
    foreach ($files as $file) {
        if (in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif'])) {
            $jockey_images[] = '/horse-racing/assets/img/jockeys/' . $file;
        }
    }
}

require_once __DIR__ . '/../../core/includes/header.php';
?>

<style>
/* Transparent table styling like voting power table */
.table-responsive {
    border-radius: 12px;
    overflow: hidden;
}

.table-responsive .table {
    background-color: transparent !important;
    margin-bottom: 0;
}

.table-responsive .table td,
.table-responsive .table th {
    color: rgba(255, 255, 255, 0.9) !important;
    border-color: rgba(255, 255, 255, 0.15) !important;
    background-color: rgba(255, 255, 255, 0.05) !important;
    padding: 1rem 0.75rem;
}

.table-responsive .table thead th {
    background-color: rgba(255, 255, 255, 0.1) !important;
    border-bottom: 2px solid rgba(255, 255, 255, 0.2) !important;
    font-weight: 600;
}

.table-hover tbody tr:hover {
    background-color: rgba(255, 255, 255, 0.08) !important;
    transform: translateY(-1px);
}

.jockey-assignments-table {
    background: rgba(255, 255, 255, 0.12) !important;
    backdrop-filter: blur(20px) !important;
    border: 1px solid rgba(255, 255, 255, 0.15) !important;
    border-radius: 12px !important;
    color: #ffffff;
}

.jockey-assignments-table th {
    background: rgba(255, 255, 255, 0.1) !important;
    border: none !important;
    color: #ffffff !important;
    font-weight: 600;
}

.jockey-assignments-table td {
    background: transparent !important;
    border: 1px solid rgba(255, 255, 255, 0.1) !important;
    color: #ffffff !important;
    transition: all 0.2s ease;
}

.table-hover tbody tr:hover td {
    background-color: rgba(255, 255, 255, 0.08) !important;
}

/* Card styling */
.card {
    background: rgba(255, 255, 255, 0.12) !important;
    backdrop-filter: blur(20px) !important;
    border: 1px solid rgba(255, 255, 255, 0.15) !important;
    border-radius: 16px !important;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3) !important;
}

.card-header {
    background: rgba(255, 255, 255, 0.08) !important;
    border-bottom: 1px solid rgba(255, 255, 255, 0.15) !important;
    color: rgba(255, 255, 255, 0.95) !important;
}

.card-body {
    color: rgba(255, 255, 255, 0.9) !important;
}

/* Text color fixes */
.text-muted {
    color: rgba(255, 255, 255, 0.6) !important;
}

strong {
    color: rgba(255, 255, 255, 0.95) !important;
}

/* Form styling for filter dropdown */
.form-select {
    background-color: rgba(255, 255, 255, 0.1) !important;
    border: 1px solid rgba(255, 255, 255, 0.15) !important;
    color: rgba(255, 255, 255, 0.9) !important;
}

.form-select:focus {
    background-color: rgba(255, 255, 255, 0.15) !important;
    border-color: rgba(255, 255, 255, 0.3) !important;
    box-shadow: 0 0 0 0.2rem rgba(255, 255, 255, 0.25) !important;
    color: rgba(255, 255, 255, 0.95) !important;
}

.form-label {
    color: rgba(255, 255, 255, 0.9) !important;
    font-weight: 500;
}

/* Jockey avatar styling */
.jockey-avatar {
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
}

/* Button styling */
.btn-primary {
    background: linear-gradient(45deg, #007bff, #0056b3) !important;
    border: none !important;
    box-shadow: 0 2px 10px rgba(0, 123, 255, 0.3) !important;
}

.btn-outline-danger {
    border-color: rgba(220, 53, 69, 0.5) !important;
    color: #dc3545 !important;
}

.btn-outline-danger:hover {
    background-color: rgba(220, 53, 69, 0.1) !important;
    border-color: #dc3545 !important;
}

/* Performance data styling */
.small {
    color: rgba(255, 255, 255, 0.7) !important;
}

/* Status badge styling */
.text-success {
    color: #20c997 !important;
}

.text-muted {
    color: rgba(255, 255, 255, 0.6) !important;
}

/* Alert styling */
.alert {
    background: rgba(255, 255, 255, 0.1) !important;
    border: 1px solid rgba(255, 255, 255, 0.15) !important;
    color: rgba(255, 255, 255, 0.9) !important;
}

.alert-success {
    background: rgba(40, 167, 69, 0.15) !important;
    border-color: rgba(40, 167, 69, 0.3) !important;
    color: #20c997 !important;
}

.alert-danger {
    background: rgba(220, 53, 69, 0.15) !important;
    border-color: rgba(220, 53, 69, 0.3) !important;
    color: #ff6b6b !important;
}

/* Empty state styling */
.text-center i.fas {
    opacity: 0.6;
}

/* Numbers/metrics styling */
.h5.mb-0 {
    color: rgba(255, 255, 255, 0.95) !important;
}

/* Border styling for metric sections */
.border-end {
    border-right: 1px solid rgba(255, 255, 255, 0.15) !important;
}
</style>

<div class="main-content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0">🏇 Jockey Assignments</h4>
                    <div class="page-title-right">
                        <ol class="breadcrumb m-0">
                            <li class="breadcrumb-item"><a href="/business/dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="/business/horse-racing/">Horse Racing</a></li>
                            <li class="breadcrumb-item active">Jockey Assignments</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Machine Filter -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-4">
                                <label class="form-label">Filter by Machine</label>
                                <form method="GET" class="d-flex gap-2">
                                    <select class="form-select" name="machine_id" onchange="this.form.submit()">
                                        <option value="0"<?php echo $selected_machine === 0 ? ' selected' : ''; ?>>
                                            All Machines (<?php echo count($items); ?> items)
                                        </option>
                                        <?php foreach ($machines as $machine): ?>
                                            <?php
                                            $machine_item_count = 0;
                                            foreach ($items as $item) {
                                                if ($item['voting_list_id'] == $machine['id']) {
                                                    $machine_item_count++;
                                                }
                                            }
                                            ?>
                                            <option value="<?php echo $machine['id']; ?>"<?php echo $selected_machine === (int)$machine['id'] ? ' selected' : ''; ?>>
                                                <?php echo htmlspecialchars($machine['name']); ?> 
                                                (<?php echo $machine_item_count; ?> items)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </form>
                            </div>
                            <div class="col-md-8">
                                <div class="row text-center">
                                    <div class="col-3">
                                        <div class="border-end">
                                            <h5 class="mb-0 text-primary"><?php echo count($items); ?></h5>
                                            <small class="text-muted">Total Items</small>
                                        </div>
                                    </div>
                                    <div class="col-3">
                                        <div class="border-end">
                                            <h5 class="mb-0 text-success">
                                                <?php echo count(array_filter($items, function($item) { return !empty($item['custom_jockey_name']); })); ?>
                                            </h5>
                                            <small class="text-muted">Custom Jockeys</small>
                                        </div>
                                    </div>
                                    <div class="col-3">
                                        <div class="border-end">
                                            <h5 class="mb-0 text-info">
                                                <?php echo array_sum(array_column($items, 'sales_24h')); ?>
                                            </h5>
                                            <small class="text-muted">Sales (24h)</small>
                                        </div>
                                    </div>
                                    <div class="col-3">
                                        <h5 class="mb-0 text-warning">
                                            <?php echo array_sum(array_column($items, 'nayax_sales_24h')); ?>
                                        </h5>
                                        <small class="text-muted">Nayax Sales (24h)</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-user-friends"></i> Assign Jockeys to Your Items
                        </h5>
                        <p class="text-muted mt-2">
                            Customize which jockey represents each item in your vending machines. 
                            This affects how they appear in horse races and uses real sales data for performance.
                        </p>
                    </div>
                    <div class="card-body">
                        <?php if (empty($items)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-horse text-muted" style="font-size: 3rem;"></i>
                                <p class="text-muted mb-0 mt-2">
                                    <?php if ($selected_machine > 0): ?>
                                        No items found for the selected machine
                                    <?php else: ?>
                                        No items found. Create some machines and add items first.
                                    <?php endif; ?>
                                </p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table jockey-assignments-table">
                                    <thead>
                                        <tr>
                                            <th>Machine</th>
                                            <th>Item</th>
                                            <th>Performance Data</th>
                                            <th>Current Jockey</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($items as $item): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($item['machine_name']); ?></strong>
                                                    <?php if ($item['machine_location']): ?>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars($item['machine_location']); ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div>
                                                            <strong><?php echo htmlspecialchars($item['item_name']); ?></strong>
                                                            <br><small class="text-muted">
                                                                <?php echo htmlspecialchars($item['item_category']); ?> • 
                                                                $<?php echo number_format($item['retail_price'], 2); ?>
                                                            </small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="small">
                                                        <div class="d-flex justify-content-between">
                                                            <span>Manual Sales (24h):</span>
                                                            <strong class="text-primary"><?php echo $item['sales_24h']; ?></strong>
                                                        </div>
                                                        <div class="d-flex justify-content-between">
                                                            <span>Nayax Sales (24h):</span>
                                                            <strong class="text-info"><?php echo $item['nayax_sales_24h']; ?></strong>
                                                        </div>
                                                        <div class="d-flex justify-content-between">
                                                            <span>Revenue (24h):</span>
                                                            <strong class="text-success">$<?php echo number_format($item['revenue_24h'] + $item['nayax_revenue_24h'], 2); ?></strong>
                                                        </div>
                                                        <div class="d-flex justify-content-between">
                                                            <span>Performance Score:</span>
                                                            <strong class="text-warning"><?php echo number_format($item['performance_score'], 1); ?></strong>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php if ($item['custom_jockey_name']): ?>
                                                        <!-- Custom Jockey -->
                                                        <div class="d-flex align-items-center">
                                                            <div class="jockey-avatar me-2" 
                                                                 style="width: 40px; height: 40px; border-radius: 50%; background-image: url('<?php echo $item['custom_jockey_avatar_url']; ?>'); background-size: cover; border: 2px solid <?php echo $item['custom_jockey_color']; ?>">
                                                            </div>
                                                            <div>
                                                                <strong style="color: <?php echo $item['custom_jockey_color']; ?>">
                                                                    <?php echo htmlspecialchars($item['custom_jockey_name']); ?>
                                                                </strong>
                                                                <br><small class="text-success">Custom Assignment</small>
                                                            </div>
                                                        </div>
                                                    <?php else: ?>
                                                        <!-- Default Jockey -->
                                                        <div class="d-flex align-items-center">
                                                            <div class="jockey-avatar me-2" 
                                                                 style="width: 40px; height: 40px; border-radius: 50%; background-image: url('<?php echo $item['default_jockey_avatar_url'] ?? '/horse-racing/assets/img/jockeys/jockey-other.png'; ?>'); background-size: cover; border: 2px solid <?php echo $item['default_jockey_color'] ?? '#6f42c1'; ?>">
                                                            </div>
                                                            <div>
                                                                <strong style="color: <?php echo $item['default_jockey_color'] ?? '#6f42c1'; ?>">
                                                                    <?php echo htmlspecialchars($item['default_jockey_name'] ?? 'Wild Card Willie'); ?>
                                                                </strong>
                                                                <br><small class="text-muted">Default Assignment</small>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-primary" 
                                                            onclick="showAssignModal(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars($item['item_name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($item['custom_jockey_name'] ?? '', ENT_QUOTES); ?>', '<?php echo $item['custom_jockey_color'] ?? '#007bff'; ?>', '<?php echo $item['custom_jockey_avatar_url'] ?? ''; ?>')">
                                                        <i class="fas fa-edit"></i> <?php echo $item['custom_jockey_name'] ? 'Edit' : 'Assign'; ?>
                                                    </button>
                                                    
                                                    <?php if ($item['custom_jockey_name']): ?>
                                                        <form method="POST" class="d-inline" onsubmit="return confirm('Remove custom jockey assignment?')">
                                                            <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                                            <button type="submit" name="remove_jockey" class="btn btn-sm btn-outline-danger">
                                                                <i class="fas fa-times"></i> Remove
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
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
    </div>
</div>

<!-- Enhanced Jockey Assignment Modal -->
<div class="modal fade" id="jockeyAssignModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">🏇 Assign Jockey</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="item_id" id="modal_item_id">
                    
                    <div class="mb-3">
                        <label class="form-label">Item</label>
                        <input type="text" class="form-control" id="modal_item_name" readonly>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Jockey Name *</label>
                                <input type="text" class="form-control" name="jockey_name" id="modal_jockey_name" required 
                                       placeholder="Enter custom jockey name">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Jockey Color *</label>
                                <input type="color" class="form-control form-control-color" name="jockey_color" id="modal_jockey_color" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Jockey Avatar</label>
                                <div class="d-flex gap-2 mb-2">
                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="showImageGallery()">
                                        <i class="fas fa-images"></i> Choose from Gallery
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="showUrlInput()">
                                        <i class="fas fa-link"></i> Custom URL
                                    </button>
                                </div>
                                
                                <!-- Image Gallery Selection -->
                                <div id="imageGallery" style="display: none;">
                                    <div class="row g-2 mb-3" style="max-height: 200px; overflow-y: auto;">
                                        <?php foreach ($jockey_images as $image): ?>
                                            <div class="col-3">
                                                <div class="jockey-image-option" onclick="selectJockeyImage('<?php echo $image; ?>')" 
                                                     style="cursor: pointer; border: 2px solid transparent; padding: 5px; border-radius: 8px;">
                                                    <img src="<?php echo $image; ?>" class="img-fluid rounded" 
                                                         style="width: 60px; height: 60px; object-fit: cover;">
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                        <?php if (empty($jockey_images)): ?>
                                            <div class="col-12">
                                                <small class="text-muted">No jockey images found in /horse-racing/assets/img/jockeys/</small>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- URL Input -->
                                <div id="urlInput">
                                    <input type="url" class="form-control" name="jockey_avatar" id="modal_jockey_avatar" 
                                           placeholder="https://example.com/avatar.png (optional)">
                                    <div class="form-text">Leave blank to use default avatar</div>
                                </div>
                            </div>
                            
                            <!-- Avatar Preview -->
                            <div class="mb-3">
                                <label class="form-label">Preview</label>
                                <div id="avatarPreview" class="jockey-avatar" 
                                     style="width: 60px; height: 60px; border-radius: 50%; background-image: url('/horse-racing/assets/img/jockeys/jockey-custom.png'); background-size: cover; border: 2px solid #007bff; margin: 0 auto;">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Quick Select Default Jockey</label>
                        <select class="form-select" onchange="selectDefaultJockey(this.value)">
                            <option value="">-- Select a default jockey --</option>
                            <?php foreach ($default_jockeys as $jockey): ?>
                                <option value='<?php echo json_encode($jockey); ?>'>
                                    <?php echo htmlspecialchars($jockey['jockey_name']); ?> (<?php echo ucfirst($jockey['item_type']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="assign_jockey" class="btn btn-primary">
                        <i class="fas fa-save"></i> Assign Jockey
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function showAssignModal(itemId, itemName, currentJockey, currentColor, currentAvatar) {
    document.getElementById('modal_item_id').value = itemId;
    document.getElementById('modal_item_name').value = itemName;
    document.getElementById('modal_jockey_name').value = currentJockey || '';
    document.getElementById('modal_jockey_color').value = currentColor || '#007bff';
    document.getElementById('modal_jockey_avatar').value = currentAvatar || '';
    
    // Update preview
    updateAvatarPreview(currentAvatar || '/horse-racing/assets/img/jockeys/jockey-custom.png', currentColor || '#007bff');
    
    new bootstrap.Modal(document.getElementById('jockeyAssignModal')).show();
}

function selectDefaultJockey(jockeyData) {
    if (jockeyData) {
        const jockey = JSON.parse(jockeyData);
        document.getElementById('modal_jockey_name').value = jockey.jockey_name;
        document.getElementById('modal_jockey_color').value = jockey.jockey_color;
        document.getElementById('modal_jockey_avatar').value = jockey.jockey_avatar_url;
        updateAvatarPreview(jockey.jockey_avatar_url, jockey.jockey_color);
    }
}

function showImageGallery() {
    document.getElementById('imageGallery').style.display = 'block';
    document.getElementById('urlInput').style.display = 'none';
}

function showUrlInput() {
    document.getElementById('imageGallery').style.display = 'none';
    document.getElementById('urlInput').style.display = 'block';
}

function selectJockeyImage(imagePath) {
    // Remove previous selections
    document.querySelectorAll('.jockey-image-option').forEach(option => {
        option.style.border = '2px solid transparent';
    });
    
    // Highlight selected image
    event.target.closest('.jockey-image-option').style.border = '2px solid #007bff';
    
    // Update form and preview
    document.getElementById('modal_jockey_avatar').value = imagePath;
    const currentColor = document.getElementById('modal_jockey_color').value;
    updateAvatarPreview(imagePath, currentColor);
}

function updateAvatarPreview(imagePath, color) {
    const preview = document.getElementById('avatarPreview');
    preview.style.backgroundImage = `url('${imagePath}')`;
    preview.style.borderColor = color;
}

// Update preview when color or URL changes
document.getElementById('modal_jockey_color').addEventListener('change', function() {
    const imagePath = document.getElementById('modal_jockey_avatar').value || '/horse-racing/assets/img/jockeys/jockey-custom.png';
    updateAvatarPreview(imagePath, this.value);
});

document.getElementById('modal_jockey_avatar').addEventListener('input', function() {
    const color = document.getElementById('modal_jockey_color').value;
    updateAvatarPreview(this.value || '/horse-racing/assets/img/jockeys/jockey-custom.png', color);
});
</script>

<?php require_once __DIR__ . '/../../core/includes/footer.php'; ?> 