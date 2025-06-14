<?php
require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/core/session.php';
require_once __DIR__ . '/core/auth.php';
require_once __DIR__ . '/core/business_utils.php';

// Require business role
require_role('business');

$business_id = getOrCreateBusinessId($pdo, $_SESSION['user_id']);
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
                            WHERE id = ? AND business_id = ?
                        ");
                        $stmt->execute([$new_url, $new_url, $qr_id, $business_id]);
                        
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
                        WHERE id = ? AND business_id = ?
                    ");
                    $stmt->execute([$new_status, $qr_id, $business_id]);
                    
                    $message = "QR code status updated successfully!";
                    $message_type = "success";
                } catch (Exception $e) {
                    $message = "Error updating status: " . $e->getMessage();
                    $message_type = "danger";
                }
                break;
            
            case 'delete_qr':
                $qr_id = intval($_POST['qr_id']);
                
                try {
                    // Verify ownership and get QR code details
                    $stmt = $pdo->prepare("
                        SELECT code, meta 
                        FROM qr_codes 
                        WHERE id = ? AND business_id = ?
                    ");
                    $stmt->execute([$qr_id, $business_id]);
                    $qr = $stmt->fetch();
                    
                    if ($qr) {
                        // Get file path from meta
                        $meta = json_decode($qr['meta'] ?? '{}', true);
                        $file_path = $meta['file_path'] ?? null;
                        
                        // Check multiple possible file locations
                        $possible_paths = [
                            $file_path,
                            '/uploads/qr/' . $qr['code'] . '.png',
                            '/uploads/qr/1/' . $qr['code'] . '.png',
                            '/uploads/qr/business/' . $qr['code'] . '.png',
                            '/assets/img/qr/' . $qr['code'] . '.png',
                            '/qr/' . $qr['code'] . '.png'
                        ];
                        
                        // Try to delete the file from any of the possible locations
                        foreach ($possible_paths as $path) {
                            if ($path && file_exists(__DIR__ . $path)) {
                                unlink(__DIR__ . $path);
                            }
                        }
                        
                        // Soft delete in database
                        $stmt = $pdo->prepare("
                            UPDATE qr_codes 
                            SET status = 'deleted' 
                            WHERE id = ? AND business_id = ?
                        ");
                        $stmt->execute([$qr_id, $business_id]);
                        
                        $message = "QR code deleted successfully!";
                        $message_type = "success";
                    } else {
                        $message = "QR code not found or you don't have permission to delete it.";
                        $message_type = "danger";
                    }
                } catch (Exception $e) {
                    $message = "Error deleting QR code: " . $e->getMessage();
                    $message_type = "danger";
                }
                break;
        }
    }
}

// Get QR codes for this business
$qr_codes = [];
try {
    $stmt = $pdo->prepare("
        SELECT qr.*, 
               COALESCE(qr.url, JSON_UNQUOTE(JSON_EXTRACT(qr.meta, '$.content'))) as current_url,
               JSON_UNQUOTE(JSON_EXTRACT(qr.meta, '$.file_path')) as file_path,
               (SELECT COUNT(*) FROM qr_code_stats WHERE qr_code_id = qr.id) as scan_count
        FROM qr_codes qr
        WHERE qr.business_id = ? AND qr.status != 'deleted'
        ORDER BY qr.created_at DESC
    ");
    $stmt->execute([$business_id]);
    $qr_codes = $stmt->fetchAll();
} catch (Exception $e) {
    $message = "Error loading QR codes: " . $e->getMessage();
    $message_type = "danger";
}

require_once __DIR__ . '/core/includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-1">🔄 Dynamic QR Manager</h1>
                    <p class="text-muted mb-0">Update QR code destinations without regenerating them</p>
                </div>
                <div class="btn-group">
                    <a href="qr-generator-enhanced.php" class="btn btn-primary">
                        <i class="bi bi-plus-circle me-2"></i>Generate New QR
                    </a>
                    <a href="qr_manager.php" class="btn btn-outline-secondary">
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
                            <a href="qr-generator-enhanced.php" class="btn btn-primary">
                                <i class="bi bi-plus-circle me-2"></i>Generate QR Code
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-dark table-hover border-secondary">
                                <thead class="table-secondary">
                                    <tr>
                                        <th>Preview</th>
                                        <th>Type</th>
                                        <th>Machine/Campaign</th>
                                        <th>Current URL</th>
                                        <th>Scans</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($qr_codes as $qr): ?>
                                        <tr>
                                            <td>
                                                <?php
                                                $qr_image_url = null;
                                                $possible_paths = [
                                                    $qr['file_path'],
                                                    '/uploads/qr/' . $qr['code'] . '.png',
                                                    '/uploads/qr/1/' . $qr['code'] . '.png',
                                                    '/uploads/qr/business/' . $qr['code'] . '.png',
                                                    '/assets/img/qr/' . $qr['code'] . '.png',
                                                    '/qr/' . $qr['code'] . '.png'
                                                ];
                                                
                                                foreach ($possible_paths as $path) {
                                                    if ($path && file_exists(__DIR__ . $path)) {
                                                        $qr_image_url = $path;
                                                        break;
                                                    }
                                                }
                                                
                                                if ($qr_image_url): ?>
                                                    <img src="<?php echo $qr_image_url; ?>" 
                                                         alt="QR Code" 
                                                         class="img-thumbnail" 
                                                         style="width: 60px; height: 60px;">
                                                <?php else: ?>
                                                    <div class="bg-secondary d-flex align-items-center justify-content-center" 
                                                         style="width: 60px; height: 60px;">
                                                        <i class="bi bi-qr-code text-light"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-info">
                                                    <?php echo ucfirst(str_replace('_', ' ', $qr['qr_type'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($qr['machine_name'] ?: 'N/A'); ?>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <small class="text-muted me-2" style="max-width: 300px; overflow: hidden; text-overflow: ellipsis;">
                                                        <?php echo htmlspecialchars($qr['current_url'] ?: 'No URL'); ?>
                                                    </small>
                                                    <button class="btn btn-sm btn-outline-primary" 
                                                            onclick="editUrl(<?php echo $qr['id']; ?>, '<?php echo htmlspecialchars($qr['current_url'], ENT_QUOTES); ?>')">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary">
                                                    <?php echo number_format($qr['scan_count']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($qr['status'] === 'active'): ?>
                                                    <span class="badge bg-success">Active</span>
                                                <?php elseif ($qr['status'] === 'inactive'): ?>
                                                    <span class="badge bg-warning">Inactive</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Deleted</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <?php if ($qr['status'] === 'active'): ?>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="action" value="toggle_status">
                                                            <input type="hidden" name="qr_id" value="<?php echo $qr['id']; ?>">
                                                            <input type="hidden" name="new_status" value="inactive">
                                                            <button type="submit" class="btn btn-outline-warning" 
                                                                    onclick="return confirm('Deactivate this QR code?')">
                                                                <i class="bi bi-pause"></i>
                                                            </button>
                                                        </form>
                                                    <?php else: ?>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="action" value="toggle_status">
                                                            <input type="hidden" name="qr_id" value="<?php echo $qr['id']; ?>">
                                                            <input type="hidden" name="new_status" value="active">
                                                            <button type="submit" class="btn btn-outline-success">
                                                                <i class="bi bi-play"></i>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                    
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="action" value="delete_qr">
                                                        <input type="hidden" name="qr_id" value="<?php echo $qr['id']; ?>">
                                                        <button type="submit" class="btn btn-outline-danger" 
                                                                onclick="return confirm('Are you sure you want to delete this QR code? This action cannot be undone.')">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </form>
                                                    
                                                    <?php if ($qr['current_url']): ?>
                                                        <a href="<?php echo htmlspecialchars($qr['current_url']); ?>" 
                                                           target="_blank" 
                                                           class="btn btn-outline-primary">
                                                            <i class="bi bi-box-arrow-up-right"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                    
                                                    <a href="/qr.php?code=<?php echo urlencode($qr['code']); ?>" 
                                                       target="_blank" 
                                                       class="btn btn-outline-info">
                                                        <i class="bi bi-qr-code"></i>
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

<!-- URL Edit Modal -->
<div class="modal fade" id="editUrlModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark text-white border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">Update QR Code URL</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_url">
                    <input type="hidden" name="qr_id" id="edit_qr_id">
                    
                    <div class="mb-3">
                        <label for="new_url" class="form-label">New URL</label>
                        <input type="url" class="form-control" name="new_url" id="new_url" required>
                        <div class="form-text">
                            Enter the new destination URL for this QR code. The QR code image will remain the same.
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update URL</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editUrl(qrId, currentUrl) {
    document.getElementById('edit_qr_id').value = qrId;
    document.getElementById('new_url').value = currentUrl;
    
    const modal = new bootstrap.Modal(document.getElementById('editUrlModal'));
    modal.show();
}
</script>

<?php require_once __DIR__ . '/core/includes/footer.php'; ?> 