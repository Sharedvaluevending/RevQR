<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/includes/auth.php';

// Ensure user is logged in and is a business user
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'business') {
    header('Location: /login.php');
    exit;
}

$business_id = $_SESSION['business_id'];
$message = '';
$message_type = 'info';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'add_ad':
                $ad_title = trim($_POST['ad_title'] ?? '');
                $ad_description = trim($_POST['ad_description'] ?? '');
                $background_color = $_POST['background_color'] ?? '#007bff';
                $text_color = $_POST['text_color'] ?? '#ffffff';
                $cta_text = trim($_POST['cta_text'] ?? '');
                $cta_url = trim($_POST['cta_url'] ?? '');
                $is_active = isset($_POST['is_active']) ? 1 : 0;
                
                if (empty($ad_title) || empty($ad_description)) {
                    throw new Exception('Ad title and description are required');
                }
                
                // Create table if it doesn't exist
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS business_promotional_ads (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        business_id INT NOT NULL,
                        ad_title VARCHAR(255) NOT NULL,
                        ad_description TEXT NOT NULL,
                        background_color VARCHAR(7) DEFAULT '#007bff',
                        text_color VARCHAR(7) DEFAULT '#ffffff',
                        cta_text VARCHAR(100),
                        cta_url VARCHAR(500),
                        is_active TINYINT(1) DEFAULT 1,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
                    )
                ");
                
                $stmt = $pdo->prepare("
                    INSERT INTO business_promotional_ads 
                    (business_id, feature_type, ad_title, ad_description, background_color, text_color, ad_cta_text, ad_cta_url, is_active)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$business_id, 'general', $ad_title, $ad_description, $background_color, $text_color, $cta_text, $cta_url, $is_active]);
                
                $message = 'Promotional ad added successfully!';
                $message_type = 'success';
                break;
                
            case 'edit_ad':
                $ad_id = (int)$_POST['ad_id'];
                $ad_title = trim($_POST['ad_title'] ?? '');
                $ad_description = trim($_POST['ad_description'] ?? '');
                $background_color = $_POST['background_color'] ?? '#007bff';
                $text_color = $_POST['text_color'] ?? '#ffffff';
                $cta_text = trim($_POST['cta_text'] ?? '');
                $cta_url = trim($_POST['cta_url'] ?? '');
                $is_active = isset($_POST['is_active']) ? 1 : 0;
                
                if (empty($ad_title) || empty($ad_description)) {
                    throw new Exception('Ad title and description are required');
                }
                
                $stmt = $pdo->prepare("
                    UPDATE business_promotional_ads 
                    SET ad_title = ?, ad_description = ?, background_color = ?, text_color = ?, 
                        ad_cta_text = ?, ad_cta_url = ?, is_active = ?, updated_at = CURRENT_TIMESTAMP
                    WHERE id = ? AND business_id = ?
                ");
                $stmt->execute([$ad_title, $ad_description, $background_color, $text_color, $cta_text, $cta_url, $is_active, $ad_id, $business_id]);
                
                $message = 'Promotional ad updated successfully!';
                $message_type = 'success';
                break;
                
            case 'delete_ad':
                $ad_id = (int)$_POST['ad_id'];
                
                $stmt = $pdo->prepare("DELETE FROM business_promotional_ads WHERE id = ? AND business_id = ?");
                $stmt->execute([$ad_id, $business_id]);
                
                $message = 'Promotional ad deleted successfully!';
                $message_type = 'success';
                break;
                
            case 'toggle_ad':
                $ad_id = (int)$_POST['ad_id'];
                
                $stmt = $pdo->prepare("
                    UPDATE business_promotional_ads 
                    SET is_active = NOT is_active, updated_at = CURRENT_TIMESTAMP
                    WHERE id = ? AND business_id = ?
                ");
                $stmt->execute([$ad_id, $business_id]);
                
                $message = 'Ad status toggled successfully!';
                $message_type = 'success';
                break;
        }
    } catch (Exception $e) {
        $message = 'Error: ' . $e->getMessage();
        $message_type = 'danger';
    }
}

// Get all promotional ads for this business
try {
    $stmt = $pdo->prepare("
        SELECT * FROM business_promotional_ads 
        WHERE business_id = ? 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$business_id]);
    $promotional_ads = $stmt->fetchAll();
} catch (Exception $e) {
    $promotional_ads = [];
}

require_once __DIR__ . '/../core/includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="bi bi-badge-ad me-2"></i>Manage Promotional Ads</h1>
        <a href="edit-items.php" class="btn btn-secondary">
            <i class="bi bi-arrow-left me-1"></i>Back to Items
        </a>
    </div>
    
    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Add New Ad Button -->
    <div class="mb-4">
        <button type="button" class="btn btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#addAdModal">
            <i class="bi bi-plus-circle me-2"></i>Add New Promotional Ad
        </button>
    </div>

    <!-- Promotional Ads List -->
    <div class="row">
        <?php if (empty($promotional_ads)): ?>
            <div class="col-12">
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="bi bi-badge-ad display-1 text-muted mb-3"></i>
                        <h5 class="text-muted">No Promotional Ads Yet</h5>
                        <p class="text-muted">Create your first promotional ad to start engaging customers on your voting page.</p>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAdModal">
                            <i class="bi bi-plus-circle me-2"></i>Add Your First Ad
                        </button>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($promotional_ads as $ad): ?>
                <div class="col-md-6 mb-4">
                    <div class="card h-100">
                        <!-- Ad Preview -->
                        <div class="card-header p-0">
                            <div class="ad-preview p-3" 
                                 style="background: linear-gradient(135deg, <?php echo $ad['background_color']; ?> 0%, <?php echo $ad['background_color']; ?>dd 100%); color: <?php echo $ad['text_color']; ?>;">
                                <div class="d-flex align-items-center">
                                    <div class="rounded me-3 d-flex align-items-center justify-content-center text-white" 
                                         style="width: 40px; height: 40px; background: rgba(255,255,255,0.2);">
                                        <i class="bi bi-building"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h6 class="mb-1" style="color: <?php echo $ad['text_color']; ?>;">
                                            <?php echo htmlspecialchars($ad['ad_title']); ?>
                                        </h6>
                                        <p class="small mb-2 opacity-75" style="color: <?php echo $ad['text_color']; ?>;">
                                            <?php echo htmlspecialchars($ad['ad_description']); ?>
                                        </p>
                                        <?php if ($ad['ad_cta_text'] && $ad['ad_cta_url']): ?>
                                            <span class="btn btn-sm btn-light">
                                                <?php echo htmlspecialchars($ad['ad_cta_text']); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Ad Controls -->
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="badge bg-<?php echo $ad['is_active'] ? 'success' : 'secondary'; ?>">
                                    <?php echo $ad['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                                <small class="text-muted">
                                    Created: <?php echo date('M j, Y', strtotime($ad['created_at'])); ?>
                                </small>
                            </div>
                            
                            <div class="btn-group w-100" role="group">
                                <button type="button" class="btn btn-outline-primary btn-sm" 
                                        onclick="editAd(<?php echo htmlspecialchars(json_encode($ad)); ?>)">
                                    <i class="bi bi-pencil"></i> Edit
                                </button>
                                <form method="post" class="d-inline">
                                    <input type="hidden" name="action" value="toggle_ad">
                                    <input type="hidden" name="ad_id" value="<?php echo $ad['id']; ?>">
                                    <button type="submit" class="btn btn-outline-<?php echo $ad['is_active'] ? 'warning' : 'success'; ?> btn-sm">
                                        <i class="bi bi-<?php echo $ad['is_active'] ? 'pause' : 'play'; ?>"></i>
                                        <?php echo $ad['is_active'] ? 'Disable' : 'Enable'; ?>
                                    </button>
                                </form>
                                <button type="button" class="btn btn-outline-danger btn-sm" 
                                        onclick="deleteAd(<?php echo $ad['id']; ?>)">
                                    <i class="bi bi-trash"></i> Delete
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Add Ad Modal -->
<div class="modal fade" id="addAdModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Promotional Ad</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_ad">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Ad Title</label>
                            <input type="text" class="form-control" name="ad_title" required>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Background Color</label>
                            <input type="color" class="form-control" name="background_color" value="#007bff">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Text Color</label>
                            <input type="color" class="form-control" name="text_color" value="#ffffff">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Ad Description</label>
                        <textarea class="form-control" name="ad_description" rows="3" required></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Call-to-Action Text</label>
                            <input type="text" class="form-control" name="cta_text" placeholder="Learn More">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Call-to-Action URL</label>
                            <input type="url" class="form-control" name="cta_url" placeholder="https://...">
                        </div>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1" checked>
                        <label class="form-check-label">Active (show on voting page)</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Add Promotional Ad</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Ad Modal -->
<div class="modal fade" id="editAdModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Promotional Ad</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit_ad">
                    <input type="hidden" name="ad_id" id="editAdId">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Ad Title</label>
                            <input type="text" class="form-control" name="ad_title" id="editAdTitle" required>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Background Color</label>
                            <input type="color" class="form-control" name="background_color" id="editBackgroundColor">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Text Color</label>
                            <input type="color" class="form-control" name="text_color" id="editTextColor">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Ad Description</label>
                        <textarea class="form-control" name="ad_description" rows="3" id="editAdDescription" required></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Call-to-Action Text</label>
                            <input type="text" class="form-control" name="cta_text" id="editCtaText">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Call-to-Action URL</label>
                            <input type="url" class="form-control" name="cta_url" id="editCtaUrl">
                        </div>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1" id="editIsActive">
                        <label class="form-check-label">Active (show on voting page)</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Promotional Ad</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteAdModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete Promotional Ad</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this promotional ad? This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="post" class="d-inline">
                    <input type="hidden" name="action" value="delete_ad">
                    <input type="hidden" name="ad_id" id="deleteAdId">
                    <button type="submit" class="btn btn-danger">Delete Ad</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function editAd(ad) {
    document.getElementById('editAdId').value = ad.id;
    document.getElementById('editAdTitle').value = ad.ad_title;
    document.getElementById('editAdDescription').value = ad.ad_description;
    document.getElementById('editBackgroundColor').value = ad.background_color;
    document.getElementById('editTextColor').value = ad.text_color;
    document.getElementById('editCtaText').value = ad.ad_cta_text || '';
    document.getElementById('editCtaUrl').value = ad.ad_cta_url || '';
    document.getElementById('editIsActive').checked = ad.is_active == 1;
    
    new bootstrap.Modal(document.getElementById('editAdModal')).show();
}

function deleteAd(adId) {
    document.getElementById('deleteAdId').value = adId;
    new bootstrap.Modal(document.getElementById('deleteAdModal')).show();
}
</script>

<?php require_once __DIR__ . '/../core/includes/footer.php'; ?> 