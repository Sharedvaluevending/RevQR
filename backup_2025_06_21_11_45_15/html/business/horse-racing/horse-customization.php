<?php
/**
 * Business Horse Customization Management
 * Allows businesses to assign custom names and images to their racing horses
 */

require_once __DIR__ . '/../../core/config.php';
require_once __DIR__ . '/../../core/session.php';
require_once __DIR__ . '/../../core/auth.php';

// Require business role
require_role('business');

$business_id = $_SESSION['business_id'];

$message = '';
$message_type = '';

// Handle horse assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_horse'])) {
    $item_id = intval($_POST['item_id']);
    $horse_name = trim($_POST['horse_name']);
    $horse_color = $_POST['horse_color'];
    $horse_image = trim($_POST['horse_image']);
    
    if ($item_id && $horse_name && $horse_color && $horse_image) {
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
            
            // Validate horse name length
            if (strlen($horse_name) > 30) {
                throw new Exception("Horse name must be 30 characters or less");
            }
            
            // Insert or update horse assignment
            $stmt = $pdo->prepare("
                INSERT INTO custom_horse_assignments 
                (business_id, item_id, custom_horse_name, custom_horse_image_url, custom_horse_color)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                custom_horse_name = VALUES(custom_horse_name),
                custom_horse_image_url = VALUES(custom_horse_image_url),
                custom_horse_color = VALUES(custom_horse_color),
                updated_at = CURRENT_TIMESTAMP
            ");
            
            $stmt->execute([$business_id, $item_id, $horse_name, $horse_image, $horse_color]);
            
            $message = "Horse customized successfully for " . htmlspecialchars($item['item_name']);
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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_horse'])) {
    $item_id = intval($_POST['item_id']);
    
    try {
        $stmt = $pdo->prepare("DELETE FROM custom_horse_assignments WHERE business_id = ? AND item_id = ?");
        $stmt->execute([$business_id, $item_id]);
        
        $message = "Horse customization removed successfully";
        $message_type = 'success';
        
    } catch (Exception $e) {
        $message = "Error removing customization: " . $e->getMessage();
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

// Get all business items with their current horse assignments
$stmt = $pdo->prepare("
    SELECT vli.*, vl.name as machine_name, vl.description as machine_location,
           cha.custom_horse_name, cha.custom_horse_image_url, cha.custom_horse_color,
           -- Default horse name
           CONCAT('Horse ', vli.item_name) as default_horse_name
    FROM voting_list_items vli
    JOIN voting_lists vl ON vli.voting_list_id = vl.id
    LEFT JOIN custom_horse_assignments cha ON vli.id = cha.item_id AND cha.business_id = ?
    $where_clause
    ORDER BY vl.name, vli.item_name
");
$stmt->execute(array_merge([$business_id], $params));
$items = $stmt->fetchAll();

// Get available horse images from jockey directory
$horse_images = [];
$jockey_dir = __DIR__ . '/../../horse-racing/assets/img/jockeys/';
if (is_dir($jockey_dir)) {
    $files = scandir($jockey_dir);
    foreach ($files as $file) {
        if (in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif'])) {
            $horse_images[] = '/horse-racing/assets/img/jockeys/' . $file;
        }
    }
}

// Default horse names for suggestions
$default_horse_names = ['Thunder', 'Lightning', 'Storm', 'Blaze', 'Spirit', 'Champion', 'Victory', 'Star', 'Shadow', 'Fire', 'Wind', 'Diamond', 'Golden', 'Silver', 'Swift', 'Mighty', 'Brave', 'Royal', 'Noble', 'Magic'];

require_once __DIR__ . '/../../core/includes/header.php';
?>

<style>
/* Horse customization table styling */
.horse-customization-table {
    background: rgba(255, 255, 255, 0.12) !important;
    backdrop-filter: blur(20px) !important;
    border: 1px solid rgba(255, 255, 255, 0.15) !important;
    border-radius: 12px !important;
    color: #ffffff;
}

.horse-customization-table th {
    background: rgba(255, 255, 255, 0.1) !important;
    border: none !important;
    color: #ffffff !important;
    font-weight: 600;
}

.horse-customization-table td {
    background: transparent !important;
    border: 1px solid rgba(255, 255, 255, 0.1) !important;
    color: #ffffff !important;
}

/* Horse avatar styling */
.horse-avatar {
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
    border-radius: 8px;
    transition: transform 0.2s ease;
}

.horse-avatar:hover {
    transform: scale(1.05);
}

/* Horse image gallery */
.horse-image-option {
    transition: all 0.3s ease;
    border-radius: 8px;
    overflow: hidden;
}

.horse-image-option:hover {
    transform: scale(1.05);
    box-shadow: 0 4px 15px rgba(100, 181, 246, 0.3);
}

.horse-image-option.selected {
    border: 3px solid #64b5f6 !important;
    box-shadow: 0 0 20px rgba(100, 181, 246, 0.5);
}

/* Horse name suggestions */
.name-suggestion {
    cursor: pointer;
    transition: background-color 0.2s ease;
}

.name-suggestion:hover {
    background-color: rgba(100, 181, 246, 0.2);
}
</style>

<div class="container py-4">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="index.php">Horse Racing</a></li>
                    <li class="breadcrumb-item active">Horse Customization</li>
                </ol>
            </nav>
            <h1 class="mb-2">🐎 Horse Customization</h1>
            <p class="text-muted">Customize your racing horses with unique names and images</p>
        </div>
    </div>

    <!-- Messages -->
    <?php if ($message): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                    <i class="bi bi-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Filter & Info -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <form method="GET" class="d-flex align-items-end gap-3">
                        <div class="flex-grow-1">
                            <label for="machine_id" class="form-label">Filter by Machine</label>
                            <select name="machine_id" id="machine_id" class="form-select">
                                <option value="0">All Machines</option>
                                <?php foreach ($machines as $machine): ?>
                                    <option value="<?php echo $machine['id']; ?>" <?php echo $selected_machine == $machine['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($machine['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-funnel me-1"></i>Filter
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card bg-info bg-opacity-10">
                <div class="card-body">
                    <h6 class="text-info mb-1">
                        <i class="bi bi-info-circle me-2"></i>How It Works
                    </h6>
                    <p class="mb-0 small text-light">
                        Give your items custom horse names and choose images from the available gallery. 
                        These will represent your items in horse races!
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Items/Horses List -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="bi bi-grid-3x3-gap me-2"></i>Your Racing Horses
                            <span class="badge bg-primary ms-2"><?php echo count($items); ?> Items</span>
                        </h5>
                        <small class="text-muted">Customize each item's racing horse</small>
                    </div>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($items)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-inbox display-1 text-muted"></i>
                            <h4 class="text-muted mt-3">No Items Found</h4>
                            <p class="text-muted">
                                No items available for horse customization. 
                                <?php if ($selected_machine > 0): ?>
                                    <a href="?">View all machines</a>
                                <?php else: ?>
                                    Add items to your machines first.
                                <?php endif; ?>
                            </p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table horse-customization-table">
                                <thead>
                                    <tr>
                                        <th>Machine</th>
                                        <th>Item</th>
                                        <th>Current Horse</th>
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
                                                <div>
                                                    <strong><?php echo htmlspecialchars($item['item_name']); ?></strong>
                                                    <br><small class="text-muted">
                                                        <?php echo htmlspecialchars($item['item_category']); ?> • 
                                                        $<?php echo number_format($item['retail_price'], 2); ?>
                                                    </small>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if ($item['custom_horse_name']): ?>
                                                    <!-- Custom Horse -->
                                                    <div class="d-flex align-items-center">
                                                        <div class="horse-avatar me-2" 
                                                             style="width: 40px; height: 40px; background-image: url('<?php echo $item['custom_horse_image_url']; ?>'); border: 2px solid <?php echo $item['custom_horse_color']; ?>">
                                                        </div>
                                                        <div>
                                                            <strong style="color: <?php echo $item['custom_horse_color']; ?>">
                                                                <?php echo htmlspecialchars($item['custom_horse_name']); ?>
                                                            </strong>
                                                            <br><small class="text-success">Custom Horse</small>
                                                        </div>
                                                    </div>
                                                <?php else: ?>
                                                    <!-- Default Horse -->
                                                    <div class="d-flex align-items-center">
                                                        <div class="horse-avatar me-2" 
                                                             style="width: 40px; height: 40px; background-image: url('/horse-racing/assets/img/jockeys/jockey-other.png'); border: 2px solid #8B4513;">
                                                        </div>
                                                        <div>
                                                            <strong style="color: #8B4513;">
                                                                <?php echo htmlspecialchars($item['default_horse_name']); ?>
                                                            </strong>
                                                            <br><small class="text-muted">Default Horse</small>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-primary" 
                                                        onclick="showHorseModal(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars($item['item_name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($item['custom_horse_name'] ?? '', ENT_QUOTES); ?>', '<?php echo $item['custom_horse_color'] ?? '#8B4513'; ?>', '<?php echo $item['custom_horse_image_url'] ?? ''; ?>')">
                                                    <i class="bi bi-pencil"></i> <?php echo $item['custom_horse_name'] ? 'Edit' : 'Customize'; ?>
                                                </button>
                                                
                                                <?php if ($item['custom_horse_name']): ?>
                                                    <form method="POST" class="d-inline" onsubmit="return confirm('Remove custom horse and use default?')">
                                                        <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                                        <button type="submit" name="remove_horse" class="btn btn-sm btn-outline-danger">
                                                            <i class="bi bi-x"></i> Remove
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

<!-- Horse Customization Modal -->
<div class="modal fade" id="horseCustomModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">🐎 Customize Horse</h5>
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
                                <label class="form-label">Horse Name *</label>
                                <input type="text" class="form-control" name="horse_name" id="modal_horse_name" required 
                                       placeholder="Enter custom horse name" maxlength="30">
                                <div class="form-text">Maximum 30 characters</div>
                                
                                <!-- Name suggestions -->
                                <div class="mt-2">
                                    <small class="text-muted">Quick suggestions:</small>
                                    <div class="d-flex flex-wrap gap-1 mt-1">
                                        <?php foreach (array_slice($default_horse_names, 0, 5) as $name): ?>
                                            <span class="badge bg-secondary name-suggestion" onclick="setHorseName('<?php echo $name; ?>')"><?php echo $name; ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Horse Color *</label>
                                <input type="color" class="form-control form-control-color" name="horse_color" id="modal_horse_color" required value="#8B4513">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Horse Image *</label>
                                <input type="hidden" name="horse_image" id="modal_horse_image" required>
                                
                                <!-- Image Gallery Selection -->
                                <div id="imageGallery">
                                    <div class="row g-2" style="max-height: 300px; overflow-y: auto;">
                                        <?php foreach ($horse_images as $image): ?>
                                            <div class="col-3">
                                                <div class="horse-image-option" onclick="selectHorseImage('<?php echo $image; ?>')" 
                                                     style="cursor: pointer; border: 2px solid transparent; padding: 3px;">
                                                    <img src="<?php echo $image; ?>" class="img-fluid rounded" 
                                                         style="width: 60px; height: 60px; object-fit: cover;">
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                        <?php if (empty($horse_images)): ?>
                                            <div class="col-12">
                                                <small class="text-muted">No horse images found in /horse-racing/assets/img/jockeys/</small>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Horse Preview -->
                            <div class="mb-3">
                                <label class="form-label">Preview</label>
                                <div class="d-flex align-items-center p-3 bg-white bg-opacity-10 rounded">
                                    <div id="horsePreview" class="horse-avatar me-3" 
                                         style="width: 60px; height: 60px; background-image: url('/horse-racing/assets/img/jockeys/jockey-other.png'); border: 2px solid #8B4513;">
                                    </div>
                                    <div>
                                        <strong id="previewName" style="color: #8B4513;">Horse Name</strong>
                                        <br><small class="text-success">Custom Horse</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="assign_horse" class="btn btn-primary">
                        <i class="bi bi-check-circle me-1"></i>Save Horse
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let currentImage = '';
let currentColor = '#8B4513';

function showHorseModal(itemId, itemName, horseName, horseColor, horseImage) {
    document.getElementById('modal_item_id').value = itemId;
    document.getElementById('modal_item_name').value = itemName;
    document.getElementById('modal_horse_name').value = horseName;
    document.getElementById('modal_horse_color').value = horseColor || '#8B4513';
    document.getElementById('modal_horse_image').value = horseImage;
    
    currentImage = horseImage || '/horse-racing/assets/img/jockeys/jockey-other.png';
    currentColor = horseColor || '#8B4513';
    
    // Clear previous selections
    document.querySelectorAll('.horse-image-option').forEach(opt => {
        opt.classList.remove('selected');
    });
    
    // Select current image if exists
    if (horseImage) {
        const currentOpt = document.querySelector(`[onclick="selectHorseImage('${horseImage}')"]`);
        if (currentOpt) currentOpt.classList.add('selected');
    }
    
    updateHorsePreview(currentImage, currentColor, horseName || 'Horse Name');
    
    new bootstrap.Modal(document.getElementById('horseCustomModal')).show();
}

function selectHorseImage(imagePath) {
    // Remove previous selection
    document.querySelectorAll('.horse-image-option').forEach(opt => {
        opt.classList.remove('selected');
    });
    
    // Add selection to clicked image
    event.currentTarget.classList.add('selected');
    
    document.getElementById('modal_horse_image').value = imagePath;
    currentImage = imagePath;
    
    const horseName = document.getElementById('modal_horse_name').value || 'Horse Name';
    updateHorsePreview(imagePath, currentColor, horseName);
}

function setHorseName(name) {
    document.getElementById('modal_horse_name').value = name;
    updateHorsePreview(currentImage, currentColor, name);
}

function updateHorsePreview(imagePath, color, name) {
    const preview = document.getElementById('horsePreview');
    const previewName = document.getElementById('previewName');
    
    preview.style.backgroundImage = `url('${imagePath}')`;
    preview.style.borderColor = color;
    previewName.style.color = color;
    previewName.textContent = name;
}

// Update preview when color changes
document.addEventListener('DOMContentLoaded', function() {
    const colorInput = document.getElementById('modal_horse_color');
    const nameInput = document.getElementById('modal_horse_name');
    
    if (colorInput) {
        colorInput.addEventListener('change', function() {
            currentColor = this.value;
            const horseName = nameInput.value || 'Horse Name';
            updateHorsePreview(currentImage, currentColor, horseName);
        });
    }
    
    if (nameInput) {
        nameInput.addEventListener('input', function() {
            const horseName = this.value || 'Horse Name';
            updateHorsePreview(currentImage, currentColor, horseName);
        });
    }
});
</script>

<?php require_once __DIR__ . '/../../core/includes/footer.php'; ?> 