<?php
require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/core/session.php';
require_once __DIR__ . '/core/auth.php';
require_once __DIR__ . '/core/business_utils.php';

// Manual authentication check instead of require_role
if (!is_logged_in()) {
    header('Location: ' . APP_URL . '/login.php');
    exit();
}

if (!has_role('business')) {
    echo "Debug: User role is: " . ($_SESSION['role'] ?? 'not set') . "<br>";
    echo "Debug: Expected role: business<br>";
    echo "Debug: Session data: <pre>" . print_r($_SESSION, true) . "</pre>";
    header('Location: ' . APP_URL . '/unauthorized.php');
    exit();
}

// Get business_id with proper error handling
try {
    $business_id = getOrCreateBusinessId($pdo, $_SESSION['user_id']);
} catch (Exception $e) {
    $business_id = null;
    error_log("Error getting business ID in QR manager: " . $e->getMessage());
}

// Initialize variables
$qr_codes = [];
$analytics_data = [];
$search = $_GET['search'] ?? '';
$type_filter = $_GET['type'] ?? '';
$sort = $_GET['sort'] ?? 'created_desc';

<<<<<<< Updated upstream
if ($business_id) {
    try {
        // Build query with filters
        $where_conditions = ["qr.status != 'deleted'"];
        $params = [];
        
        // Add business filter - FIXED to use qr.business_id directly
        $where_conditions[] = "qr.business_id = ?";
        $params[] = $business_id;
        
        // Add search filter
        if ($search) {
            $where_conditions[] = "(qr.machine_name LIKE ? OR qr.code LIKE ? OR c.name LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
=======
<!-- Match Dashboard Blue Theme -->
<style>
    /* MATCH THE BLUE DASHBOARD THEME */
    html, body {
        background: linear-gradient(135deg, #1e3c72 0%, #2a5298 25%, #3d72b4 75%, #5a95d1 100%) !important;
        background-attachment: fixed !important;
        color: #ffffff !important;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif !important;
    }
    
    /* White text on blue theme */
    .text-dark, h1, h2, h3, h4, h5, h6, p, div, span, td, th {
        color: #ffffff !important;
    }
    
    .text-muted {
        color: rgba(255, 255, 255, 0.8) !important;
    }
    
    .text-primary {
        color: #64b5f6 !important;
    }
    
    /* Glass morphism cards like dashboard */
    .bg-white, .card, .card-body, .card-header {
        background: rgba(255, 255, 255, 0.12) !important;
        backdrop-filter: blur(20px) !important;
        border: 1px solid rgba(255, 255, 255, 0.15) !important;
    }
    
    .card {
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3) !important;
        border-radius: 16px !important;
    }
    
    .card-header {
        background: rgba(255, 255, 255, 0.15) !important;
        border-bottom: 1px solid rgba(255, 255, 255, 0.2) !important;
    }
    
    /* Table styling for blue theme */
    .table {
        color: #ffffff !important;
        background: transparent !important;
    }
    
    .table thead th {
        background: rgba(255, 255, 255, 0.15) !important;
        color: #ffffff !important;
        border-bottom: 2px solid rgba(255, 255, 255, 0.2) !important;
    }
    
    .table tbody tr {
        background: transparent !important;
        color: #ffffff !important;
    }
    
    .table tbody tr:hover {
        background: rgba(255, 255, 255, 0.1) !important;
    }
    
    /* Force ALL table elements transparent */
    .table td, .table th {
        background: transparent !important;
        border-color: rgba(255, 255, 255, 0.1) !important;
    }
    
    /* Reset alerts */
    .alert {
        color: inherit !important;
    }
    
    .alert-info {
        background-color: #d1ecf1 !important;
        border-color: #bee5eb !important;
        color: #0c5460 !important;
    }
    
    .alert-success {
        background-color: #d1e7dd !important;
        border-color: #badbcc !important;
        color: #0f5132 !important;
    }
    
    .alert-danger {
        background-color: #f8d7da !important;
        border-color: #f5c2c7 !important;
        color: #842029 !important;
    }
    
    /* Reset buttons */
    .btn-primary {
        background-color: #0d6efd !important;
        border-color: #0d6efd !important;
        color: #ffffff !important;
    }
    
    .btn-outline-primary {
        color: #0d6efd !important;
        border-color: #0d6efd !important;
        background-color: transparent !important;
    }
    
    .btn-outline-secondary {
        color: #6c757d !important;
        border-color: #6c757d !important;
        background-color: transparent !important;
    }
    
    .btn-outline-info {
        color: #0dcaf0 !important;
        border-color: #0dcaf0 !important;
        background-color: transparent !important;
    }
    
    /* Keep standard dark navbar like rest of app */
    .navbar {
        background: #212529 !important;
    }
    
    .navbar-brand, .nav-link {
        color: #ffffff !important;
    }
    
    .nav-link:hover {
        color: rgba(255, 255, 255, 0.8) !important;
    }
    
    /* Container background - transparent to show blue gradient */
    .container-fluid {
        background: transparent !important;
    }
</style>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="text-dark">
                    <i class="bi bi-qr-code me-2 text-primary"></i>QR Code Manager
                </h1>
                <div>
                    <a href="business/dashboard.php" class="btn btn-outline-secondary me-2 shadow-sm">
                        <i class="bi bi-arrow-left me-1"></i>Back to Dashboard
                    </a>
                    <a href="qr_dynamic_manager.php" class="btn btn-outline-info me-2 shadow-sm" 
                       title="Manage dynamic QR codes - change URLs without reprinting">
                        <i class="bi bi-arrow-clockwise me-1"></i>Dynamic Manager
                    </a>
                    <a href="qr-generator.php" class="btn btn-primary shadow-sm">
                        <i class="bi bi-plus-circle me-1"></i>Generate New QR
                    </a>
                </div>
            </div>

            <?php if (isset($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="bi bi-check-circle me-2"></i><?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="bi bi-exclamation-triangle me-2"></i><?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Analytics Cards -->
            <?php if (!empty($analytics)): ?>
                <div class="row mb-4">
                    <?php foreach ($analytics as $stat): ?>
                        <div class="col-md-3 mb-3">
                            <div class="card bg-primary text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h6 class="card-title text-uppercase"><?php echo ucfirst(str_replace('_', ' ', $stat['qr_type'])); ?></h6>
                                            <h3 class="mb-0"><?php echo $stat['count']; ?></h3>
                                            <small><?php echo $stat['total_scans']; ?> scans</small>
                                        </div>
                                        <div class="align-self-center">
                                            <i class="bi bi-qr-code fs-1 opacity-75"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>



            <!-- QR Codes Table -->
            <div class="card border-0 shadow-sm">
                <div class="card-header border-bottom">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 text-dark">
                            <i class="bi bi-qr-code me-2 text-primary"></i>Your QR Codes
                        </h5>
                        <div class="btn-group btn-group-sm">
                            <a href="qr_dynamic_manager.php" class="btn btn-outline-primary">
                                <i class="bi bi-arrow-clockwise me-1"></i>Dynamic Manager
                            </a>
                            <a href="print-shop.php" class="btn btn-outline-secondary">
                                <i class="bi bi-printer me-1"></i>Print Shop
                            </a>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (!empty($qr_codes)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th><i class="bi bi-hash me-1"></i>ID</th>
                                        <th><i class="bi bi-code-square me-1"></i>Code</th>
                                        <th><i class="bi bi-tag me-1"></i>Type</th>
                                        <th><i class="bi bi-link-45deg me-1"></i>Machine/URL</th>
                                        <th><i class="bi bi-eye me-1"></i>Scans</th>
                                        <th><i class="bi bi-calendar me-1"></i>Created</th>
                                        <th><i class="bi bi-gear me-1"></i>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($qr_codes as $qr): ?>
                                        <tr>
                                            <td><?php echo $qr['id']; ?></td>
                                            <td>
                                                <code><?php echo htmlspecialchars($qr['code']); ?></code>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary">
                                                    <?php echo ucfirst(str_replace('_', ' ', $qr['qr_type'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($qr['machine_name']): ?>
                                                    <strong><?php echo htmlspecialchars($qr['machine_name']); ?></strong><br>
                                                <?php endif; ?>
                                                <small class="text-muted">
                                                    <?php echo htmlspecialchars($qr['url'] ?? 'N/A'); ?>
                                                </small>
                                            </td>
                                            <td>
                                                <span class="badge bg-info"><?php echo $qr['scan_count']; ?></span>
                                            </td>
                                            <td>
                                                <small><?php echo date('M j, Y', strtotime($qr['created_at'])); ?></small><br>
                                                <small class="text-muted"><?php echo date('H:i', strtotime($qr['created_at'])); ?></small>
                                            </td>
                                            <td>
                                                <?php
                                                // Try to find QR image with smart path detection
                                                $qr_image_url = null;
                                                
                                                // First check meta field for file path
                                                if (!empty($qr['meta'])) {
                                                    $meta = json_decode($qr['meta'], true);
                                                    if (!empty($meta['file_path'])) {
                                                        $meta_path = $meta['file_path'];
                                                        // Ensure path starts with /
                                                        if (!str_starts_with($meta_path, '/')) {
                                                            $meta_path = '/' . $meta_path;
                                                        }
                                                        $full_path = $_SERVER['DOCUMENT_ROOT'] . $meta_path;
                                                        if (file_exists($full_path)) {
                                                            $qr_image_url = $meta_path;
                                                        }
                                                    }
                                                }
                                                
                                                // If not found in meta, try standard paths
                                                if (!$qr_image_url) {
                                                    $possible_paths = [
                                                        '/uploads/qr/' . $qr['code'] . '.png',
                                                        '/uploads/qr/1/' . $qr['code'] . '.png', 
                                                        '/uploads/qr/business/' . $qr['code'] . '.png',
                                                        '/assets/img/qr/' . $qr['code'] . '.png',
                                                        '/qr/' . $qr['code'] . '.png'
                                                    ];
                                                    
                                                    foreach ($possible_paths as $path) {
                                                        $full_path = $_SERVER['DOCUMENT_ROOT'] . $path;
                                                        if (file_exists($full_path)) {
                                                            $qr_image_url = $path;
                                                            break;
                                                        }
                                                    }
                                                }
                                                ?>
                                                <div class="btn-group btn-group-sm">
                                                    <button type="button" class="btn btn-outline-primary" 
                                                            onclick="previewQR(<?php echo $qr['id']; ?>, '<?php echo htmlspecialchars($qr['code']); ?>', '<?php echo htmlspecialchars($qr_image_url ?? ''); ?>')" title="Preview">
                                                        <i class="bi bi-eye"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-outline-success" 
                                                            onclick="printQR(<?php echo $qr['id']; ?>, '<?php echo htmlspecialchars($qr['code']); ?>', '<?php echo htmlspecialchars($qr_image_url ?? ''); ?>')" title="Print">
                                                        <i class="bi bi-printer"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-outline-info" 
                                                            onclick="openPrintStudio(<?php echo $qr['id']; ?>, '<?php echo htmlspecialchars($qr['code']); ?>', '<?php echo htmlspecialchars($qr_image_url ?? ''); ?>')" title="Print Studio">
                                                        <i class="bi bi-palette"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-outline-danger" 
                                                            onclick="deleteQR(<?php echo $qr['id']; ?>)" title="Delete">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="bi bi-qr-code display-1 text-muted"></i>
                            <h4 class="mt-3">No QR Codes Found</h4>
                            <p class="text-muted">You haven't created any QR codes yet.</p>
                            <a href="qr-generator.php" class="btn btn-primary">
                                <i class="bi bi-plus-circle me-1"></i>Create Your First QR Code
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delete Form (Hidden) -->
<form id="deleteForm" method="POST" style="display: none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="qr_id" id="deleteQRId">
</form>

<!-- QR Preview Modal -->
<div class="modal fade" id="qrPreviewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">QR Code Preview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <div id="qrPreviewContent">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-success" onclick="printCurrentQR()">
                    <i class="bi bi-printer me-1"></i>Print
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Print Studio Modal -->
<div class="modal fade" id="printStudioModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Print Studio</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-4">
                        <h6>Print Options</h6>
                        <div class="mb-3">
                            <label class="form-label">Paper Size</label>
                            <select class="form-select" id="paperSize">
                                <option value="letter">Letter (8.5" x 11")</option>
                                <option value="a4">A4 (210mm x 297mm)</option>
                                <option value="label">Label (4" x 6")</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">QR Size</label>
                            <select class="form-select" id="qrSize">
                                <option value="small">Small (1" x 1")</option>
                                <option value="medium" selected>Medium (2" x 2")</option>
                                <option value="large">Large (3" x 3")</option>
                                <option value="xlarge">Extra Large (4" x 4")</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Copies per Page</label>
                            <select class="form-select" id="copiesPerPage">
                                <option value="1">1 copy</option>
                                <option value="2">2 copies</option>
                                <option value="4" selected>4 copies</option>
                                <option value="6">6 copies</option>
                                <option value="9">9 copies</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="includeText" checked>
                                <label class="form-check-label" for="includeText">
                                    Include machine name/URL
                                </label>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="includeBorder">
                                <label class="form-check-label" for="includeBorder">
                                    Add border
                                </label>
                            </div>
                        </div>
                        <button type="button" class="btn btn-primary w-100" onclick="updatePreview()">
                            <i class="bi bi-arrow-clockwise me-1"></i>Update Preview
                        </button>
                    </div>
                    <div class="col-md-8">
                        <h6>Print Preview</h6>
                        <div id="printPreview" class="border rounded p-3" style="min-height: 400px; background: white;">
                            <div class="text-center text-muted">
                                <i class="bi bi-printer display-4"></i>
                                <p>Click "Update Preview" to see your print layout</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-success" onclick="printStudioLayout()">
                    <i class="bi bi-printer me-1"></i>Print Layout
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let currentQRId = null;
let currentQRCode = null;
let currentImagePath = null;

function deleteQR(qrId) {
    if (confirm('Are you sure you want to delete this QR code? This action cannot be undone.')) {
        document.getElementById('deleteQRId').value = qrId;
        document.getElementById('deleteForm').submit();
    }
}

function printQR(qrId, qrCode, imagePath) {
    const imageUrl = imagePath && imagePath.trim() !== '' ? imagePath : `/uploads/qr/${qrCode}.png`;
    
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <html>
        <head>
            <title>Print QR Code - ${qrCode}</title>
            <style>
                body { 
                    font-family: Arial, sans-serif; 
                    text-align: center; 
                    margin: 20px;
                }
                .qr-container {
                    display: inline-block;
                    padding: 20px;
                    border: 2px solid #000;
                    margin: 20px;
                }
                .qr-code {
                    width: 200px;
                    height: 200px;
                    margin: 10px auto;
                }
                .qr-label {
                    font-size: 16px;
                    font-weight: bold;
                    margin-top: 10px;
                }
                @media print {
                    .no-print { display: none; }
                }
            </style>
        </head>
        <body>
            <div class="no-print">
                <button onclick="window.print()">Print</button>
                <button onclick="window.close()">Close</button>
            </div>
            
            <div class="qr-container">
                <img src="${imageUrl}" alt="QR Code" class="qr-code" 
                     onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                <div style="display:none;">
                    <div style="width:200px;height:200px;border:2px dashed #ccc;display:flex;align-items:center;justify-content:center;margin:10px auto;">
                        QR Code<br>Image Missing
                    </div>
                </div>
                <div class="qr-label">${qrCode}</div>
            </div>
        </body>
        </html>
    `);
    printWindow.document.close();
    printWindow.focus();
    setTimeout(() => printWindow.print(), 500);
}

function previewQR(qrId, qrCode, imagePath) {
    currentQRId = qrId;
    currentQRCode = qrCode;
    currentImagePath = imagePath;
    
    const modal = new bootstrap.Modal(document.getElementById('qrPreviewModal'));
    const content = document.getElementById('qrPreviewContent');
    
    // Show loading
    content.innerHTML = `
        <div class="spinner-border" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
        <p class="mt-2">Loading QR code preview...</p>
    `;
    
    modal.show();
    
    // Try multiple image paths
    const possiblePaths = [
        imagePath,
        `/uploads/qr/${qrCode}.png`,
        `/uploads/qr/1/${qrCode}.png`,
        `/uploads/qr/business/${qrCode}.png`,
        `/assets/img/qr/${qrCode}.png`
    ].filter(path => path && path.trim() !== '');
    
    let imageFound = false;
    let currentPathIndex = 0;
    
    function tryNextImage() {
        if (currentPathIndex >= possiblePaths.length) {
            // No image found, try API generation
            generatePreviewViaAPI();
            return;
>>>>>>> Stashed changes
        }
        
        // Add type filter
        if ($type_filter) {
            $where_conditions[] = "qr.qr_type = ?";
            $params[] = $type_filter;
        }
        
        // Build ORDER BY clause
        $order_by = "qr.created_at DESC"; // default
        switch ($sort) {
            case 'name_asc':
                $order_by = "COALESCE(c.name, qr.machine_name, qr.code) ASC";
                break;
            case 'name_desc':
                $order_by = "COALESCE(c.name, qr.machine_name, qr.code) DESC";
                break;
            case 'type_asc':
                $order_by = "qr.qr_type ASC";
                break;
            case 'type_desc':
                $order_by = "qr.qr_type DESC";
                break;
            case 'created_asc':
                $order_by = "qr.created_at ASC";
                break;
            default:
                $order_by = "qr.created_at DESC";
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        // Fetch QR codes with campaign and analytics data
        $stmt = $pdo->prepare("
            SELECT 
                qr.*,
                c.name as campaign_name,
                c.description as campaign_description,
                c.type as campaign_type,
                COALESCE(
                    JSON_UNQUOTE(JSON_EXTRACT(qr.meta, '$.file_path')),
                    CONCAT('/uploads/qr/', qr.code, '.png')
                ) as qr_url,
                COALESCE(
                    JSON_UNQUOTE(JSON_EXTRACT(qr.meta, '$.preview_path')),
                    CONCAT('/uploads/qr/', qr.code, '_preview.png')
                ) as preview_url,
                (SELECT COUNT(*) FROM qr_code_stats WHERE qr_code_id = qr.id) as scan_count,
                (SELECT MAX(scan_time) FROM qr_code_stats WHERE qr_code_id = qr.id) as last_scan,
                (SELECT COUNT(*) FROM votes WHERE qr_code_id = qr.id) as vote_count
            FROM qr_codes qr
            LEFT JOIN campaigns c ON qr.campaign_id = c.id
            WHERE $where_clause
            ORDER BY $order_by
        ");
        $stmt->execute($params);
        $qr_codes = $stmt->fetchAll();
        
        // Get analytics summary
        $stmt = $pdo->prepare("
            SELECT 
                qr.qr_type,
                COUNT(*) as count,
                SUM(COALESCE((SELECT COUNT(*) FROM qr_code_stats WHERE qr_code_id = qr.id), 0)) as total_scans
            FROM qr_codes qr
            LEFT JOIN campaigns c ON qr.campaign_id = c.id AND c.business_id = ?
            WHERE qr.business_id = ? AND qr.status != 'deleted'
            GROUP BY qr.qr_type
        ");
        $stmt->execute([$business_id, $business_id]);
        $analytics_data = $stmt->fetchAll();
        
    } catch (Exception $e) {
        error_log("Error fetching QR codes: " . $e->getMessage());
    }
}

// Simple output instead of full page for testing
echo "<h1>QR Manager Fixed Test</h1>";
echo "<p>Business ID: " . $business_id . "</p>";
echo "<p>QR Codes found: " . count($qr_codes) . "</p>";

if (count($qr_codes) > 0) {
    echo "<h2>Your QR Codes:</h2>";
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Type</th><th>Machine</th><th>Status</th><th>Created</th></tr>";
    foreach ($qr_codes as $qr) {
        echo "<tr>";
        echo "<td>" . $qr['id'] . "</td>";
        echo "<td>" . $qr['qr_type'] . "</td>";
        echo "<td>" . ($qr['machine_name'] ?: '-') . "</td>";
        echo "<td>" . $qr['status'] . "</td>";
        echo "<td>" . date('M j, Y H:i', strtotime($qr['created_at'])) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No QR codes found.</p>";
}

echo "<p><a href='qr_manager.php'>Try Original QR Manager</a></p>";
echo "<p><a href='business/dashboard.php'>Back to Dashboard</a></p>";
?> 