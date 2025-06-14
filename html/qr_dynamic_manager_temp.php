<?php
// Temporary Dynamic QR Manager (bypasses authentication)
require_once __DIR__ . '/core/config.php';

// Mock session for testing
if (!isset($_SESSION)) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1; // Mock user ID
}

$business_id = 1; // Mock business ID for testing
$message = '';
$message_type = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_url':
                $qr_id = intval($_POST['qr_id']);
                $new_url = trim($_POST['new_url']);
                
                if (!empty($new_url)) {
                    try {
                        // Update the URL in the qr_codes table
                        $stmt = $pdo->prepare("
                            UPDATE qr_codes 
                            SET url = ?, 
                                meta = JSON_SET(COALESCE(meta, '{}'), '$.content', ?)
                            WHERE id = ?
                        ");
                        $stmt->execute([$new_url, $new_url, $qr_id]);
                        
                        $message = "QR code URL updated successfully!";
                        $message_type = "success";
                    } catch (Exception $e) {
                        $message = "Error updating QR code: " . $e->getMessage();
                        $message_type = "danger";
                    }
                } else {
                    $message = "URL cannot be empty";
                    $message_type = "warning";
                }
                break;
                
            case 'toggle_status':
                $qr_id = intval($_POST['qr_id']);
                $new_status = $_POST['new_status'];
                
                try {
                    $stmt = $pdo->prepare("
                        UPDATE qr_codes 
                        SET status = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$new_status, $qr_id]);
                    
                    $message = "QR code status updated successfully!";
                    $message_type = "success";
                } catch (Exception $e) {
                    $message = "Error updating status: " . $e->getMessage();
                    $message_type = "danger";
                }
                break;
        }
    }
}

// Get QR codes
$qr_codes = [];
try {
    $stmt = $pdo->prepare("
        SELECT qr.*, 
               COALESCE(qr.url, JSON_UNQUOTE(JSON_EXTRACT(qr.meta, '$.content'))) as current_url,
               JSON_UNQUOTE(JSON_EXTRACT(qr.meta, '$.file_path')) as file_path,
               (SELECT COUNT(*) FROM qr_code_stats WHERE qr_code_id = qr.id) as scan_count
        FROM qr_codes qr
        WHERE qr.status != 'deleted'
        ORDER BY qr.created_at DESC
        LIMIT 50
    ");
    $stmt->execute();
    $qr_codes = $stmt->fetchAll();
} catch (Exception $e) {
    $message = "Error loading QR codes: " . $e->getMessage();
    $message_type = "danger";
}

require_once __DIR__ . '/core/includes/header.php';
?>

<div class="container-fluid py-4" style="background-color: #f8f9fa;">
    <div class="row">
        <div class="col-12">
            <div class="alert alert-info">
                <strong>🔧 Temporary Access Mode</strong> - This is a test version of the Dynamic QR Manager with authentication bypassed.
            </div>
            
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-1 text-dark">
                        <i class="bi bi-arrow-clockwise me-2 text-primary"></i>Dynamic QR Manager
                    </h1>
                    <p class="text-muted mb-0">Update QR code destinations without regenerating them</p>
                </div>
                <div class="btn-group">
                    <a href="qr-generator.php" class="btn btn-primary shadow-sm">
                        <i class="bi bi-plus-circle me-2"></i>Generate New QR
                    </a>
                    <a href="qr_manager.php" class="btn btn-outline-secondary shadow-sm">
                        <i class="bi bi-arrow-left me-2"></i>Back to QR Manager
                    </a>
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

    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom">
                    <h5 class="card-title mb-0 text-dark">
                        <i class="bi bi-qr-code me-2 text-primary"></i>Your QR Codes
                        <span class="badge bg-primary ms-2"><?php echo count($qr_codes); ?></span>
                    </h5>
                </div>
                <div class="card-body bg-white">
                    <?php if (empty($qr_codes)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-qr-code display-1 text-muted"></i>
                            <h4 class="mt-3">No QR Codes Found</h4>
                            <p class="text-muted">Create your first QR code to get started.</p>
                            <a href="qr-generator.php" class="btn btn-primary">
                                <i class="bi bi-plus-circle me-2"></i>Generate QR Code
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-striped">
                                <thead class="table-light">
                                    <tr>
                                        <th>Preview</th>
                                        <th>Type</th>
                                        <th>Current URL</th>
                                        <th>Status</th>
                                        <th>Scans</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($qr_codes as $qr): ?>
                                        <tr>
                                            <td>
                                                <?php 
                                                $qr_url = '';
                                                if (!empty($qr['file_path'])) {
                                                    $qr_url = $qr['file_path'];
                                                } else {
                                                    $qr_url = '/uploads/qr/' . $qr['code'] . '.png';
                                                }
                                                ?>
                                                <img src="<?php echo htmlspecialchars($qr_url); ?>" 
                                                     alt="QR Code" 
                                                     class="img-thumbnail" 
                                                     style="width: 60px; height: 60px;" 
                                                     onerror="this.src='/assets/img/qr-placeholder.png'">
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary"><?php echo ucfirst($qr['type']); ?></span>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <input type="url" 
                                                           class="form-control form-control-sm me-2" 
                                                           value="<?php echo htmlspecialchars($qr['current_url']); ?>" 
                                                           id="url_<?php echo $qr['id']; ?>"
                                                           style="max-width: 300px;">
                                                    <button onclick="updateURL(<?php echo $qr['id']; ?>)" 
                                                            class="btn btn-sm btn-outline-primary">
                                                        <i class="bi bi-arrow-clockwise"></i>
                                                    </button>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $qr['status'] === 'active' ? 'success' : 'warning'; ?>">
                                                    <?php echo ucfirst($qr['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-info"><?php echo $qr['scan_count']; ?></span>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    <?php echo date('M j, Y', strtotime($qr['created_at'])); ?>
                                                </small>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <button onclick="toggleStatus(<?php echo $qr['id']; ?>, '<?php echo $qr['status'] === 'active' ? 'inactive' : 'active'; ?>')" 
                                                            class="btn btn-outline-warning">
                                                        <i class="bi bi-power"></i>
                                                    </button>
                                                    <a href="<?php echo htmlspecialchars($qr_url); ?>" 
                                                       download class="btn btn-outline-success">
                                                        <i class="bi bi-download"></i>
                                                    </a>
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
</div>

<script>
function updateURL(qrId) {
    const newUrl = document.getElementById('url_' + qrId).value;
    
    if (!newUrl.trim()) {
        alert('URL cannot be empty');
        return;
    }
    
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `
        <input type="hidden" name="action" value="update_url">
        <input type="hidden" name="qr_id" value="${qrId}">
        <input type="hidden" name="new_url" value="${newUrl}">
    `;
    document.body.appendChild(form);
    form.submit();
}

function toggleStatus(qrId, newStatus) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `
        <input type="hidden" name="action" value="toggle_status">
        <input type="hidden" name="qr_id" value="${qrId}">
        <input type="hidden" name="new_status" value="${newStatus}">
    `;
    document.body.appendChild(form);
    form.submit();
}
</script>

<?php require_once __DIR__ . '/core/includes/footer.php'; ?> 