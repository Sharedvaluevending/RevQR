<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/functions.php';
require_once __DIR__ . '/../core/promotional_ads_manager.php';

// Ensure business access
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'business') {
    header('Location: /login.php');
    exit;
}

// Get business details
$stmt = $pdo->prepare("
    SELECT b.*, u.username 
    FROM businesses b 
    JOIN users u ON b.id = u.business_id 
    WHERE u.id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$business = $stmt->fetch();

if (!$business) {
    die('Business not found.');
}

$business_id = $business['id'];
$adsManager = new PromotionalAdsManager($pdo);
$message = '';
$message_type = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'create_ad':
                $feature_type = $_POST['feature_type'] ?? '';
                $ad_title = $_POST['ad_title'] ?? '';
                $ad_description = $_POST['ad_description'] ?? '';
                $ad_cta_text = $_POST['ad_cta_text'] ?? 'Learn More';
                $ad_cta_url = $_POST['ad_cta_url'] ?? '';
                $background_color = $_POST['background_color'] ?? '#007bff';
                $text_color = $_POST['text_color'] ?? '#ffffff';
                $show_on_vote_page = isset($_POST['show_on_vote_page']);
                $show_on_dashboard = isset($_POST['show_on_dashboard']);
                $priority = (int)($_POST['priority'] ?? 1);
                $max_daily_views = (int)($_POST['max_daily_views'] ?? 1000);
                
                if ($feature_type && $ad_title && $ad_description) {
                    $options = [
                        'background_color' => $background_color,
                        'text_color' => $text_color,
                        'show_on_vote_page' => $show_on_vote_page,
                        'show_on_dashboard' => $show_on_dashboard,
                        'priority' => $priority,
                        'max_daily_views' => $max_daily_views
                    ];
                    
                    if ($adsManager->createAd($business_id, $feature_type, $ad_title, $ad_description, $ad_cta_text, $ad_cta_url, $options)) {
                        $message = 'Promotional ad created successfully!';
                        $message_type = 'success';
                    } else {
                        $message = 'Error creating promotional ad.';
                        $message_type = 'danger';
                    }
                } else {
                    $message = 'Please fill in all required fields.';
                    $message_type = 'warning';
                }
                break;
                
            case 'update_ad':
                $ad_id = $_POST['ad_id'] ?? '';
                $is_active = isset($_POST['is_active']) ? 1 : 0;
                
                if ($ad_id) {
                    $stmt = $pdo->prepare("
                        UPDATE business_promotional_ads 
                        SET is_active = ?, updated_at = CURRENT_TIMESTAMP
                        WHERE id = ? AND business_id = ?
                    ");
                    
                    if ($stmt->execute([$is_active, $ad_id, $business_id])) {
                        $message = 'Ad status updated successfully!';
                        $message_type = 'success';
                    } else {
                        $message = 'Error updating ad status.';
                        $message_type = 'danger';
                    }
                }
                break;
                
            case 'delete_ad':
                $ad_id = $_POST['ad_id'] ?? '';
                
                if ($ad_id) {
                    $stmt = $pdo->prepare("
                        DELETE FROM business_promotional_ads 
                        WHERE id = ? AND business_id = ?
                    ");
                    
                    if ($stmt->execute([$ad_id, $business_id])) {
                        $message = 'Promotional ad deleted successfully!';
                        $message_type = 'success';
                    } else {
                        $message = 'Error deleting promotional ad.';
                        $message_type = 'danger';
                    }
                }
                break;
        }
    } catch (Exception $e) {
        $message = 'Error: ' . $e->getMessage();
        $message_type = 'danger';
    }
}

// Get current promotional ads
$stmt = $pdo->prepare("
    SELECT pa.*, 
           (SELECT COUNT(*) FROM business_ad_views bav WHERE bav.ad_id = pa.id) as total_views,
           (SELECT COUNT(*) FROM business_ad_views bav WHERE bav.ad_id = pa.id AND bav.clicked = TRUE) as total_clicks
    FROM business_promotional_ads pa
    WHERE pa.business_id = ?
    ORDER BY pa.created_at DESC
");
$stmt->execute([$business_id]);
$current_ads = $stmt->fetchAll();

// Get business feature availability
$stmt = $pdo->prepare("SELECT * FROM business_casino_participation WHERE business_id = ?");
$stmt->execute([$business_id]);
$casino_participation = $stmt->fetch();

$stmt = $pdo->prepare("SELECT * FROM business_promotional_settings WHERE business_id = ?");
$stmt->execute([$business_id]);
$promo_settings = $stmt->fetch();

// Get recent ad performance stats
$ad_stats = $adsManager->getBusinessAdStats($business_id, 30);

require_once __DIR__ . '/../core/includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0">
                        <i class="bi bi-megaphone-fill text-primary me-2"></i>
                        Promotional Ads Manager
                    </h1>
                    <p class="text-muted mb-0">Create and manage promotional ads shown to users across the platform</p>
                </div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createAdModal">
                    <i class="bi bi-plus-circle me-1"></i>Create New Ad
                </button>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Overview Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="card-title">Total Ads</h6>
                                    <h3 class="mb-0"><?php echo count($current_ads); ?></h3>
                                </div>
                                <i class="bi bi-megaphone-fill fs-1 opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="card-title">Active Ads</h6>
                                    <h3 class="mb-0"><?php echo count(array_filter($current_ads, function($ad) { return $ad['is_active']; })); ?></h3>
                                </div>
                                <i class="bi bi-check-circle-fill fs-1 opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="card-title">Total Views (30d)</h6>
                                    <h3 class="mb-0"><?php echo array_sum(array_column($ad_stats, 'total_views')); ?></h3>
                                </div>
                                <i class="bi bi-eye-fill fs-1 opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="card-title">Total Clicks (30d)</h6>
                                    <h3 class="mb-0"><?php echo array_sum(array_column($ad_stats, 'total_clicks')); ?></h3>
                                </div>
                                <i class="bi bi-cursor-fill fs-1 opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Current Ads -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Your Promotional Ads</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($current_ads)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-megaphone display-1 text-muted mb-3"></i>
                            <h4>No Promotional Ads Yet</h4>
                            <p class="text-muted">Create your first promotional ad to start driving traffic to your business features.</p>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createAdModal">
                                <i class="bi bi-plus-circle me-1"></i>Create Your First Ad
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Preview</th>
                                        <th>Feature</th>
                                        <th>Title</th>
                                        <th>Placement</th>
                                        <th>Performance</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($current_ads as $ad): ?>
                                        <tr>
                                            <td>
                                                <div class="promotional-ad-preview p-2 rounded text-white text-center" 
                                                     style="background-color: <?php echo htmlspecialchars($ad['background_color']); ?>; 
                                                            color: <?php echo htmlspecialchars($ad['text_color']); ?>; 
                                                            min-width: 150px; font-size: 0.8em;">
                                                    <strong><?php echo htmlspecialchars($ad['ad_title']); ?></strong><br>
                                                    <small><?php echo htmlspecialchars(substr($ad['ad_description'], 0, 30)) . '...'; ?></small>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary">
                                                    <?php echo ucfirst(str_replace('_', ' ', $ad['feature_type'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($ad['ad_title']); ?></strong><br>
                                                <small class="text-muted"><?php echo htmlspecialchars($ad['ad_description']); ?></small>
                                            </td>
                                            <td>
                                                <?php if ($ad['show_on_vote_page']): ?>
                                                    <span class="badge bg-primary mb-1">Vote Page</span><br>
                                                <?php endif; ?>
                                                <?php if ($ad['show_on_dashboard']): ?>
                                                    <span class="badge bg-info">Dashboard</span>
                                                <?php endif; ?>
                                                <br><small class="text-muted">Priority: <?php echo $ad['priority']; ?></small>
                                            </td>
                                            <td>
                                                <div class="d-flex flex-column">
                                                    <span class="text-primary">
                                                        <i class="bi bi-eye me-1"></i><?php echo number_format($ad['total_views']); ?> views
                                                    </span>
                                                    <span class="text-success">
                                                        <i class="bi bi-cursor me-1"></i><?php echo number_format($ad['total_clicks']); ?> clicks
                                                    </span>
                                                    <?php if ($ad['total_views'] > 0): ?>
                                                        <small class="text-muted">
                                                            CTR: <?php echo round(($ad['total_clicks'] / $ad['total_views']) * 100, 1); ?>%
                                                        </small>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="action" value="update_ad">
                                                    <input type="hidden" name="ad_id" value="<?php echo $ad['id']; ?>">
                                                    <div class="form-check form-switch">
                                                        <input class="form-check-input" type="checkbox" name="is_active" 
                                                               <?php echo $ad['is_active'] ? 'checked' : ''; ?>
                                                               onchange="this.form.submit()">
                                                        <label class="form-check-label">
                                                            <?php echo $ad['is_active'] ? 'Active' : 'Inactive'; ?>
                                                        </label>
                                                    </div>
                                                </form>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <button class="btn btn-outline-primary" 
                                                            onclick="editAd(<?php echo htmlspecialchars(json_encode($ad)); ?>)">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <form method="POST" class="d-inline" 
                                                          onsubmit="return confirm('Are you sure you want to delete this ad?')">
                                                        <input type="hidden" name="action" value="delete_ad">
                                                        <input type="hidden" name="ad_id" value="<?php echo $ad['id']; ?>">
                                                        <button type="submit" class="btn btn-outline-danger">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </form>
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

<!-- Create Ad Modal -->
<div class="modal fade" id="createAdModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create Promotional Ad</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="create_ad">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Feature Type *</label>
                                <select class="form-select" name="feature_type" required>
                                    <option value="">Select Feature</option>
                                    <?php if ($casino_participation && $casino_participation['casino_enabled']): ?>
                                        <option value="casino">Casino</option>
                                    <?php endif; ?>
                                    <?php if ($promo_settings && $promo_settings['spin_wheel_promo_enabled']): ?>
                                        <option value="spin_wheel">Spin Wheel</option>
                                    <?php endif; ?>
                                    <?php if ($promo_settings && $promo_settings['pizza_tracker_promo_enabled']): ?>
                                        <option value="pizza_tracker">Pizza Tracker</option>
                                    <?php endif; ?>
                                    <option value="general">General Business</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Priority</label>
                                <select class="form-select" name="priority">
                                    <option value="1">Low (1)</option>
                                    <option value="2" selected>Normal (2)</option>
                                    <option value="3">High (3)</option>
                                    <option value="4">Urgent (4)</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Ad Title *</label>
                        <input type="text" class="form-control" name="ad_title" maxlength="100" required>
                        <div class="form-text">Maximum 100 characters</div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Ad Description *</label>
                        <textarea class="form-control" name="ad_description" rows="3" required></textarea>
                        <div class="form-text">Describe your promotion or offer</div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Call-to-Action Text</label>
                                <input type="text" class="form-control" name="ad_cta_text" value="Learn More" maxlength="50">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Call-to-Action URL</label>
                                <input type="url" class="form-control" name="ad_cta_url" placeholder="/casino/index.php">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Background Color</label>
                                <input type="color" class="form-control form-control-color" name="background_color" value="#007bff">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Text Color</label>
                                <input type="color" class="form-control form-control-color" name="text_color" value="#ffffff">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Display Settings</label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="show_on_vote_page" id="showVotePage" checked>
                            <label class="form-check-label" for="showVotePage">
                                Show on Vote Pages
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="show_on_dashboard" id="showDashboard">
                            <label class="form-check-label" for="showDashboard">
                                Show on User Dashboard
                            </label>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Max Daily Views</label>
                        <input type="number" class="form-control" name="max_daily_views" value="1000" min="1">
                        <div class="form-text">Maximum number of times this ad will be shown per day</div>
                    </div>
                    
                    <!-- Live Preview -->
                    <div class="mt-4">
                        <label class="form-label">Live Preview</label>
                        <div id="adPreview" class="promotional-ad-preview p-3 rounded text-white" 
                             style="background-color: #007bff; color: #ffffff;">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <h6 class="mb-1" id="previewTitle">Your Ad Title</h6>
                                    <p class="mb-2 small" id="previewDescription">Your ad description will appear here</p>
                                    <small class="text-white-50" id="previewBusiness"><?php echo htmlspecialchars($business['name']); ?></small>
                                </div>
                                <button type="button" class="btn btn-light btn-sm" id="previewCTA">Learn More</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Ad</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Live preview functionality
document.addEventListener('DOMContentLoaded', function() {
    const titleInput = document.querySelector('input[name="ad_title"]');
    const descriptionInput = document.querySelector('textarea[name="ad_description"]');
    const ctaInput = document.querySelector('input[name="ad_cta_text"]');
    const bgColorInput = document.querySelector('input[name="background_color"]');
    const textColorInput = document.querySelector('input[name="text_color"]');
    
    const previewElement = document.getElementById('adPreview');
    const previewTitle = document.getElementById('previewTitle');
    const previewDescription = document.getElementById('previewDescription');
    const previewCTA = document.getElementById('previewCTA');
    
    function updatePreview() {
        previewTitle.textContent = titleInput.value || 'Your Ad Title';
        previewDescription.textContent = descriptionInput.value || 'Your ad description will appear here';
        previewCTA.textContent = ctaInput.value || 'Learn More';
        previewElement.style.backgroundColor = bgColorInput.value;
        previewElement.style.color = textColorInput.value;
    }
    
    titleInput.addEventListener('input', updatePreview);
    descriptionInput.addEventListener('input', updatePreview);
    ctaInput.addEventListener('input', updatePreview);
    bgColorInput.addEventListener('change', updatePreview);
    textColorInput.addEventListener('change', updatePreview);
});

function editAd(ad) {
    // Populate modal with existing ad data for editing
    // This would require additional modal for editing
    console.log('Edit ad:', ad);
    alert('Edit functionality coming soon! Use the status toggle for now.');
}
</script>

<?php require_once __DIR__ . '/../core/includes/footer.php'; ?>