<?php
require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/core/session.php';
require_once __DIR__ . '/core/auth.php';
require_once __DIR__ . '/core/business_utils.php';

// Simple authentication check
if (!is_logged_in() || !has_role('business')) {
    header('Location: ' . APP_URL . '/login.php');
    exit();
}

// Get business ID
$business_id = $_SESSION['business_id'] ?? 1;
if (!$business_id) {
    $stmt = $pdo->prepare("SELECT id FROM businesses WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $business = $stmt->fetch();
    $business_id = $business ? $business['id'] : 1;
    $_SESSION['business_id'] = $business_id;
}

// Handle QR code deletion
if ($_POST['action'] ?? '' === 'delete' && isset($_POST['qr_id'])) {
    $qr_id = (int)$_POST['qr_id'];
    
    try {
        // Verify ownership and delete
        $stmt = $pdo->prepare("UPDATE qr_codes SET status = 'deleted' WHERE id = ? AND business_id = ?");
        $stmt->execute([$qr_id, $business_id]);
        
        if ($stmt->rowCount() > 0) {
            $success_message = "QR code deleted successfully!";
        } else {
            $error_message = "QR code not found or access denied.";
        }
    } catch (Exception $e) {
        $error_message = "Error deleting QR code: " . $e->getMessage();
    }
}

// Get QR codes
$qr_codes = [];
$analytics = [];

try {
    // Get active QR codes from all possible sources
    $stmt = $pdo->prepare("
        SELECT DISTINCT
            qr.id, 
            qr.code, 
            qr.qr_type, 
            COALESCE(qr.url, JSON_UNQUOTE(JSON_EXTRACT(qr.meta, '$.content'))) as url,
            COALESCE(qr.machine_name, JSON_UNQUOTE(JSON_EXTRACT(qr.meta, '$.machine_name'))) as machine_name,
            qr.created_at,
            qr.meta,
            COALESCE(
                JSON_UNQUOTE(JSON_EXTRACT(qr.meta, '$.file_path')),
                CONCAT('/uploads/qr/', qr.code, '.png')
            ) as file_path,
            (SELECT COUNT(*) FROM qr_code_stats WHERE qr_code_id = qr.id) as scan_count,
            COALESCE(qr.business_id, c.business_id, vl.business_id, JSON_UNQUOTE(JSON_EXTRACT(qr.meta, '$.business_id'))) as owner_business_id
        FROM qr_codes qr
        LEFT JOIN campaigns c ON qr.campaign_id = c.id
        LEFT JOIN voting_lists vl ON qr.machine_id = vl.id
        WHERE qr.status = 'active'
        AND (
            qr.business_id = ? OR
            c.business_id = ? OR
            vl.business_id = ? OR
            JSON_UNQUOTE(JSON_EXTRACT(qr.meta, '$.business_id')) = ?
        )
        ORDER BY qr.created_at DESC
    ");
    $stmt->execute([$business_id, $business_id, $business_id, $business_id]);
    $qr_codes = $stmt->fetchAll();
    
    // Get analytics
    $stmt = $pdo->prepare("
        SELECT qr_type, COUNT(*) as count, 
               SUM((SELECT COUNT(*) FROM qr_code_stats WHERE qr_code_id = qr_codes.id)) as total_scans
        FROM qr_codes 
        WHERE business_id = ? AND status = 'active'
        GROUP BY qr_type
    ");
    $stmt->execute([$business_id]);
    $analytics = $stmt->fetchAll();
    
} catch (Exception $e) {
    $error_message = "Error loading QR codes: " . $e->getMessage();
}

require_once __DIR__ . '/core/includes/header.php';
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1><i class="bi bi-qr-code me-2"></i>QR Code Manager</h1>
                <div>
                    <a href="business/dashboard.php" class="btn btn-outline-secondary me-2">
                        <i class="bi bi-arrow-left me-1"></i>Back to Dashboard
                    </a>
                    <a href="qr-generator.php" class="btn btn-primary">
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
            <div class="card bg-dark border-secondary">
                <div class="card-header bg-gradient text-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-qr-code me-2"></i>Your QR Codes</h5>
                        <a href="print-shop.php" class="btn btn-outline-light btn-sm">
                            <i class="bi bi-printer me-1"></i>Print Shop
                        </a>
                    </div>
                </div>
                <div class="card-body bg-dark text-white">
                    <?php if (!empty($qr_codes)): ?>
                        <div class="table-responsive">
                            <table class="table table-dark table-hover border-secondary">
                                <thead class="table-secondary">
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
        }
        
        const img = new Image();
        img.onload = function() {
            imageFound = true;
            content.innerHTML = `
                <div class="text-center">
                    <img src="${possiblePaths[currentPathIndex]}" alt="QR Code" 
                         style="max-width: 100%; max-height: 400px;" class="img-fluid border">
                    <div class="mt-3">
                        <p><strong>Code:</strong> ${qrCode}</p>
                        <p><strong>Path:</strong> ${possiblePaths[currentPathIndex]}</p>
                    </div>
                </div>
            `;
        };
        
        img.onerror = function() {
            currentPathIndex++;
            tryNextImage();
        };
        
        img.src = possiblePaths[currentPathIndex];
    }
    
    function generatePreviewViaAPI() {
        // Try to generate preview via API
        fetch('/api/qr/basic-preview.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `qr_id=${qrId}&action=regenerate`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.preview_data) {
                content.innerHTML = `
                    <div class="text-center">
                        <img src="data:image/png;base64,${data.preview_data}" alt="QR Code" 
                             style="max-width: 100%; max-height: 400px;" class="img-fluid border">
                        <div class="mt-3">
                            <p><strong>Code:</strong> ${qrCode}</p>
                            <p><strong>Status:</strong> Generated via API</p>
                        </div>
                    </div>
                `;
            } else {
                showPreviewError();
            }
        })
        .catch(error => {
            console.error('API Error:', error);
            showPreviewError();
        });
    }
    
    function showPreviewError() {
        content.innerHTML = `
            <div class="alert alert-warning text-center">
                <i class="bi bi-exclamation-triangle fs-1 mb-3"></i>
                <h5>QR Code Image Not Found</h5>
                <p>The QR code image file could not be located.</p>
                <p><strong>Code:</strong> ${qrCode}</p>
                <p><strong>ID:</strong> ${qrId}</p>
                <div class="mt-3">
                    <button class="btn btn-primary" onclick="regenerateQR(${qrId}, '${qrCode}')">
                        <i class="bi bi-arrow-clockwise me-1"></i>Regenerate QR Code
                    </button>
                </div>
                <div class="mt-2">
                    <small class="text-muted">Searched paths:</small><br>
                    ${possiblePaths.map(path => `<small class="text-muted">${path}</small>`).join('<br>')}
                </div>
            </div>
        `;
    }
    
    // Start trying images
    tryNextImage();
}

function printQR(qrId, qrCode, imagePath) {
    // Use provided image path or fallback
    const imageUrl = imagePath && imagePath.trim() !== '' ? imagePath : `/uploads/qr/${qrCode}.png`;
    
    // Simple direct print
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <html>
        <head>
            <title>Print QR Code</title>
            <style>
                body { 
                    font-family: Arial, sans-serif; 
                    text-align: center; 
                    margin: 20px;
                }
                .qr-container {
                    display: inline-block;
                    border: 2px solid #000;
                    padding: 20px;
                    margin: 20px;
                }
                img { 
                    width: 200px; 
                    height: 200px; 
                }
                @media print {
                    body { margin: 0; }
                    .no-print { display: none; }
                }
            </style>
        </head>
        <body>
            <div class="qr-container">
                <img src="${imageUrl}" alt="QR Code">
                <br><br>
                <strong>${qrCode}</strong>
            </div>
            <div class="no-print">
                <button onclick="window.print()">Print</button>
                <button onclick="window.close()">Close</button>
            </div>
        </body>
        </html>
    `);
    printWindow.document.close();
    printWindow.focus();
    setTimeout(() => printWindow.print(), 500);
}

function openPrintStudio(qrId, qrCode, imagePath) {
    currentQRId = qrId;
    currentQRCode = qrCode;
    currentImagePath = imagePath;
    
    const modal = new bootstrap.Modal(document.getElementById('printStudioModal'));
    modal.show();
    
    // Auto-update preview
    setTimeout(() => updatePreview(), 500);
}

function updatePreview() {
    if (!currentQRCode) return;
    
    const paperSize = document.getElementById('paperSize').value;
    const qrSize = document.getElementById('qrSize').value;
    const copiesPerPage = parseInt(document.getElementById('copiesPerPage').value);
    const includeText = document.getElementById('includeText').checked;
    const includeBorder = document.getElementById('includeBorder').checked;
    
    // Size mappings
    const sizes = {
        small: '80px',
        medium: '120px',
        large: '160px',
        xlarge: '200px'
    };
    
    const preview = document.getElementById('printPreview');
    let html = '<div style="display: grid; gap: 10px; justify-content: center;">';
    
    // Calculate grid columns
    let columns = 1;
    if (copiesPerPage === 2) columns = 2;
    else if (copiesPerPage === 4) columns = 2;
    else if (copiesPerPage === 6) columns = 3;
    else if (copiesPerPage === 9) columns = 3;
    
    html += `<div style="display: grid; grid-template-columns: repeat(${columns}, 1fr); gap: 15px;">`;
    
    for (let i = 0; i < copiesPerPage; i++) {
        const imageUrl = currentImagePath && currentImagePath.trim() !== '' ? currentImagePath : `/uploads/qr/${currentQRCode}.png`;
        html += `
            <div style="text-align: center; ${includeBorder ? 'border: 1px solid #000; padding: 10px;' : ''}">
                <img src="${imageUrl}" 
                     style="width: ${sizes[qrSize]}; height: ${sizes[qrSize]}; display: block; margin: 0 auto;">
                ${includeText ? `<div style="margin-top: 8px; font-size: 12px; font-weight: bold;">${currentQRCode}</div>` : ''}
            </div>
        `;
    }
    
    html += '</div></div>';
    preview.innerHTML = html;
}

function printCurrentQR() {
    if (currentQRCode) {
        printQR(currentQRId, currentQRCode, currentImagePath);
        bootstrap.Modal.getInstance(document.getElementById('qrPreviewModal')).hide();
    }
}

function regenerateQR(qrId, qrCode) {
    // Show loading in the preview
    const content = document.getElementById('qrPreviewContent');
    content.innerHTML = `
        <div class="spinner-border" role="status">
            <span class="visually-hidden">Regenerating...</span>
        </div>
        <p class="mt-2">Regenerating QR code...</p>
    `;
    
    // Call regeneration API
    fetch('/api/qr/regenerate.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({qr_id: qrId})
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Reload the preview
            setTimeout(() => previewQR(qrId, qrCode), 1000);
        } else {
            content.innerHTML = `
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    Failed to regenerate QR code: ${data.message || 'Unknown error'}
                </div>
            `;
        }
    })
    .catch(error => {
        content.innerHTML = `
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle me-2"></i>
                Error regenerating QR code: ${error}
            </div>
        `;
    });
}

function printStudioLayout() {
    if (!currentQRCode) return;
    
    const paperSize = document.getElementById('paperSize').value;
    const qrSize = document.getElementById('qrSize').value;
    const copiesPerPage = parseInt(document.getElementById('copiesPerPage').value);
    const includeText = document.getElementById('includeText').checked;
    const includeBorder = document.getElementById('includeBorder').checked;
    
    // Size mappings for print
    const sizes = {
        small: '1in',
        medium: '1.5in',
        large: '2in',
        xlarge: '2.5in'
    };
    
    // Calculate grid columns
    let columns = 1;
    if (copiesPerPage === 2) columns = 2;
    else if (copiesPerPage === 4) columns = 2;
    else if (copiesPerPage === 6) columns = 3;
    else if (copiesPerPage === 9) columns = 3;
    
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <html>
        <head>
            <title>Print QR Codes - Studio Layout</title>
            <style>
                @page { 
                    margin: 0.5in; 
                    size: ${paperSize === 'a4' ? 'A4' : 'letter'};
                }
                body { 
                    font-family: Arial, sans-serif; 
                    margin: 0;
                    padding: 20px;
                }
                .print-grid {
                    display: grid;
                    grid-template-columns: repeat(${columns}, 1fr);
                    gap: 20px;
                    justify-content: center;
                }
                .qr-item {
                    text-align: center;
                    ${includeBorder ? 'border: 2px solid #000; padding: 15px;' : ''}
                    break-inside: avoid;
                }
                .qr-item img {
                    width: ${sizes[qrSize]};
                    height: ${sizes[qrSize]};
                    display: block;
                    margin: 0 auto;
                }
                .qr-text {
                    margin-top: 10px;
                    font-size: 14px;
                    font-weight: bold;
                    word-break: break-all;
                }
                @media print {
                    .no-print { display: none; }
                }
            </style>
        </head>
        <body>
            <div class="no-print" style="text-align: center; margin-bottom: 20px;">
                <button onclick="window.print()" style="padding: 10px 20px; font-size: 16px;">Print</button>
                <button onclick="window.close()" style="padding: 10px 20px; font-size: 16px; margin-left: 10px;">Close</button>
            </div>
            
            <div class="print-grid">
                ${Array(copiesPerPage).fill().map(() => `
                    <div class="qr-item">
                        <img src="/uploads/qr/${currentQRCode}.png" alt="QR Code">
                        ${includeText ? `<div class="qr-text">${currentQRCode}</div>` : ''}
                    </div>
                `).join('')}
            </div>
        </body>
        </html>
    `);
    printWindow.document.close();
    printWindow.focus();
    setTimeout(() => printWindow.print(), 500);
}
</script>

<?php require_once __DIR__ . '/core/includes/footer.php'; ?> 