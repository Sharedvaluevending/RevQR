<?php
require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/core/session.php';
require_once __DIR__ . '/core/auth.php';
require_once __DIR__ . '/core/functions.php';
require_once __DIR__ . '/core/business_utils.php';

// Require business role
require_role('business');

$message = '';
$message_type = '';

// Get business_id with proper error handling
try {
    $business_id = getOrCreateBusinessId($pdo, $_SESSION['user_id']);
    
    // Get business details
    $stmt = $pdo->prepare("SELECT * FROM businesses WHERE id = ?");
    $stmt->execute([$business_id]);
    $business = $stmt->fetch();
} catch (Exception $e) {
    $message = "Error: " . $e->getMessage();
    $message_type = "danger";
    $business_id = null;
    $business = null;
}

$page_title = 'Enhanced QR Generator';

// Force no-cache headers to prevent old navigation from showing
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache"); 
header("Expires: 0");

require_once __DIR__ . '/core/includes/header.php';
?>

<!-- Include enhanced styles -->
<link rel="stylesheet" href="/assets/css/enhanced-gradients.css">
<style>
/* Spin Wheel Preview Animations */
.qr-preview-shift {
    animation: gradientShift 3s ease-in-out infinite alternate;
}

.qr-preview-pulse {
    animation: pulse 2s ease-in-out infinite;
}

.qr-preview-rotate {
    animation: rotate 4s linear infinite;
}

.qr-preview-border-glow {
    animation: borderGlow 2s ease-in-out infinite alternate;
}

.animation-slow {
    animation-duration: 5s !important;
}

.animation-medium {
    animation-duration: 3s !important;
}

.animation-fast {
    animation-duration: 1s !important;
}

@keyframes gradientShift {
    0% { filter: hue-rotate(0deg); }
    100% { filter: hue-rotate(30deg); }
}

@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.05); }
}

@keyframes rotate {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

@keyframes borderGlow {
    0% { box-shadow: 0 0 5px rgba(13, 110, 253, 0.3); }
    100% { box-shadow: 0 0 20px rgba(13, 110, 253, 0.8); }
}

/* Spin Wheel Canvas Styling */
#previewSpinWheel {
    transition: transform 0.3s ease;
    cursor: pointer;
}

#previewSpinWheel:hover {
    transform: scale(1.02);
}

/* Compact Card Styling for Spin Wheel Sidebar */
#spinWheelPreview .card {
    border: 1px solid #dee2e6;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

#spinWheelPreview .card-body {
    padding: 0.5rem;
}

#spinWheelPreview .card-title {
    font-size: 0.9rem;
    margin-bottom: 0.5rem;
    font-weight: 600;
}

#spinWheelPreview .btn-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.8rem;
}

/* Prize List Styling */
.alert-sm {
    padding: 0.375rem 0.75rem;
    font-size: 0.875rem;
}

/* Modal Enhancements */
#prizeModal .modal-content {
    border-radius: 15px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
}

#prizeModal .modal-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 15px 15px 0 0;
}

/* Prize table styling */
.table th {
    border-top: none;
    font-weight: 600;
    color: #495057;
}

.badge {
    font-size: 0.75em;
}
</style>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0">🎨 Enhanced QR Generator</h1>
                <a href="/qr-generator.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Back to Standard Generator
                </a>
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
        <!-- Configuration Panel -->
        <div class="col-lg-8">
            <form id="qrForm" class="needs-validation" novalidate>
                <!-- Basic Settings -->
                <div class="card mb-4">
                    <div class="card-header bg-gradient-primary text-white">
                        <h5 class="card-title mb-0"><i class="bi bi-gear"></i> Basic Settings</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">QR Code Type</label>
                                <select class="form-select" name="qr_type" id="qrType" required>
                                    <option value="static">Static QR Code</option>
                                    <option value="dynamic">Dynamic QR Code</option>
                                    <option value="dynamic_voting">Dynamic Voting QR Code</option>
                                    <option value="promotion">Promotion QR Code</option>
                                    <option value="machine_sales">Vending Machine Promotions QR Code</option>
                                    <option value="vending_discount_store">Vending Machine Discount Store QR Code</option>
                                    <option value="spin_wheel">Spin Wheel QR Code</option>
                                    <option value="pizza_tracker">Pizza Tracker QR Code</option>
                                    <option value="cross_promo" disabled>Cross-Promotion QR Code (Coming Soon)</option>
                                    <option value="stackable" disabled>Stackable QR Code (Coming Soon)</option>
                                </select>
                                <div id="qrTypeDescription" class="form-text mt-2"></div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Size (px)</label>
                                <div class="d-flex align-items-center gap-2">
                                    <input type="range" class="form-range" name="size" id="sizeRange" min="200" max="800" value="400" step="50">
                                    <span class="text-nowrap" id="sizeValue">400</span>
                                </div>
                            </div>
                        </div>

                        <!-- Dynamic Fields -->
                        <div id="urlFields" class="mb-3" style="display: none;">
                            <label class="form-label">URL</label>
                            <input type="url" class="form-control" name="url" placeholder="https://example.com">
                        </div>

                        <div id="campaignFields" class="mb-3" style="display: none;">
                            <label class="form-label">Campaign</label>
                            <select class="form-select" name="campaign_id" id="campaignSelect">
                                <option value="">Select a campaign</option>
                                <?php
                                // Get campaigns for this business using proper business ID logic
                                try {
                                    require_once __DIR__ . '/core/business_utils.php';
                                    $business_id = getOrCreateBusinessId($pdo, $_SESSION['user_id']);
                                    $stmt = $pdo->prepare("SELECT id, name FROM campaigns WHERE business_id = ? ORDER BY name ASC");
                                    $stmt->execute([$business_id]);
                                    $campaigns = $stmt->fetchAll();
                                    foreach ($campaigns as $campaign): ?>
                                        <option value="<?php echo htmlspecialchars($campaign['id']); ?>"><?php echo htmlspecialchars($campaign['name']); ?></option>
                                    <?php endforeach;
                                } catch (Exception $e) {
                                    error_log("Error loading campaigns: " . $e->getMessage());
                                    echo '<option value="">Error loading campaigns</option>';
                                }
                                ?>
                            </select>
                        </div>

                        <div id="machineFields" class="mb-3" style="display: none;">
                            <label class="form-label">Machine Name</label>
                            <div class="input-group">
                                <input type="text" class="form-control" name="machine_name" id="machineName" placeholder="Enter machine name">
                                <a href="/business/manage-machine.php" class="btn btn-outline-secondary" target="_blank">
                                    <i class="bi bi-gear"></i> Manage
                                </a>
                            </div>
                        </div>

                        <div id="promotionFields" class="mb-3" style="display: none;">
                            <label class="form-label">Machine Name</label>
                            <input type="text" class="form-control" name="machine_name_sales" placeholder="Enter machine name">
                            <small class="form-text text-muted">
                                QR code will show current promotions and sales for this machine
                            </small>
                        </div>

                        <div id="machinePromotionFields" class="mb-3" style="display: none;">
                            <label class="form-label">Machine Name</label>
                            <input type="text" class="form-control" name="machine_name_promotion" id="machinePromotionMachine" placeholder="Enter machine name">
                            <label class="form-label mt-2">Promotion</label>
                            <select class="form-select" name="promotion_id" id="machinePromotionSelect">
                                <option value="">Select a promotion</option>
                                <?php
                                // Get promotions for this business using proper business ID logic
                                try {
                                    $stmt = $pdo->prepare("
                                        SELECT p.id, p.promo_code, p.description, p.discount_type, p.discount_value
                                        FROM promotions p
                                        WHERE p.business_id = ? AND p.status = 'active'
                                        ORDER BY p.promo_code ASC
                                    ");
                                    $stmt->execute([$business_id]);
                                    $promotions = $stmt->fetchAll();
                                    foreach ($promotions as $promotion): ?>
                                        <option value="<?php echo htmlspecialchars($promotion['id']); ?>">
                                            <?php echo htmlspecialchars($promotion['promo_code']); ?> - 
                                            <?php echo $promotion['discount_type'] === 'percentage' ? $promotion['discount_value'] . '%' : '$' . $promotion['discount_value']; ?> off
                                            <?php if ($promotion['description']): ?>
                                                (<?php echo htmlspecialchars($promotion['description']); ?>)
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach;
                                } catch (Exception $e) {
                                    error_log("Error loading promotions: " . $e->getMessage());
                                    echo '<option value="">Error loading promotions</option>';
                                }
                                ?>
                            </select>
                        </div>

                        <div id="spinWheelFields" class="mb-3" style="display: none;">
                            <label class="form-label">Spin Wheel</label>
                            <select class="form-select" name="spin_wheel_id" id="spinWheelSelect" required>
                                <option value="">Select a spin wheel</option>
                                <?php 
                                // Get spin wheels for this business
                                if (isset($business_id)) {
                                    try {
                                        $stmt = $pdo->prepare("
                                            SELECT id, name, wheel_type, description 
                                            FROM spin_wheels 
                                            WHERE business_id = ? AND is_active = 1 
                                            ORDER BY name
                                        ");
                                        $stmt->execute([$business_id]);
                                        $spin_wheels = $stmt->fetchAll();
                                        
                                        foreach ($spin_wheels as $wheel): ?>
                                            <option value="<?php echo $wheel['id']; ?>">
                                                <?php echo htmlspecialchars($wheel['name']); ?>
                                                (<?php echo ucfirst($wheel['wheel_type']); ?>)
                                            </option>
                                        <?php endforeach;
                                    } catch (Exception $e) {
                                        error_log("Error loading spin wheels: " . $e->getMessage());
                                        echo '<option value="">Error loading spin wheels</option>';
                                    }
                                }
                                ?>
                            </select>
                            <small class="form-text text-muted">
                                QR code will link directly to this spin wheel for users to play
                            </small>
                            <div class="mt-2">
                                <a href="/business/spin-wheel.php" class="btn btn-outline-secondary btn-sm" target="_blank">
                                    <i class="bi bi-gear"></i> Manage Spin Wheels
                                </a>
                            </div>
                        </div>

                        <div id="pizzaTrackerFields" class="mb-3" style="display: none;">
                            <label class="form-label">Pizza Tracker</label>
                            <select class="form-select" name="pizza_tracker_id" id="pizzaTrackerSelect" required>
                                <option value="">Select a pizza tracker</option>
                                <?php 
                                // Get pizza trackers for this business
                                if (isset($business_id)) {
                                    try {
                                        $stmt = $pdo->prepare("
                                            SELECT id, tracker_name, progress_percent, stage_count 
                                            FROM pizza_trackers 
                                            WHERE business_id = ? AND is_active = 1 
                                            ORDER BY tracker_name
                                        ");
                                        $stmt->execute([$business_id]);
                                        $pizza_trackers = $stmt->fetchAll();
                                        
                                        foreach ($pizza_trackers as $tracker): ?>
                                            <option value="<?php echo $tracker['id']; ?>">
                                                <?php echo htmlspecialchars($tracker['tracker_name']); ?>
                                                (<?php echo $tracker['progress_percent']; ?>% complete - <?php echo $tracker['stage_count']; ?> stages)
                                            </option>
                                        <?php endforeach;
                                    } catch (Exception $e) {
                                        error_log("Error loading pizza trackers: " . $e->getMessage());
                                        echo '<option value="">Error loading pizza trackers</option>';
                                    }
                                }
                                ?>
                            </select>
                            <small class="form-text text-muted">
                                QR code will link to this pizza tracker for customers to view progress
                            </small>
                            <div class="mt-2">
                                <a href="/business/pizza-tracker.php" class="btn btn-outline-secondary btn-sm" target="_blank">
                                    <i class="bi bi-pizza"></i> Manage Pizza Trackers
                                </a>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Location</label>
                            <input type="text" class="form-control" name="location" required placeholder="e.g., Main Lobby, Building A">
                        </div>
                    </div>
                </div>

                <!-- Design & Colors -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-palette me-2"></i>Design & Colors
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Foreground Color</label>
                                <div class="d-flex align-items-center gap-2">
                                    <input type="color" class="form-control form-control-color" name="foreground_color" value="#000000" id="foregroundColor">
                                    <input type="text" class="form-control" id="foregroundHex" value="#000000" placeholder="#000000">
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Background Color</label>
                                <div class="d-flex align-items-center gap-2">
                                    <input type="color" class="form-control form-control-color" name="background_color" value="#FFFFFF" id="backgroundColor">
                                    <input type="text" class="form-control" id="backgroundHex" value="#FFFFFF" placeholder="#FFFFFF">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Error Correction Level</label>
                                <select class="form-select" name="error_correction_level">
                                    <option value="L">Low (7%)</option>
                                    <option value="M">Medium (15%)</option>
                                    <option value="Q">Quartile (25%)</option>
                                    <option value="H" selected>High (30%)</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Output Format</label>
                                <select class="form-select" name="output_format">
                                    <option value="svg">SVG (Scalable)</option>
                                    <option value="png">PNG (Image)</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Logo</label>
                                <div class="mb-2">
                                    <select class="form-select" name="logo" id="logoSelect">
                                        <option value="">No Logo</option>
                                    </select>
                                </div>
                                <div class="input-group">
                                    <input type="file" class="form-control" id="logoUpload" accept="image/png,image/jpeg,image/jpg">
                                    <button type="button" class="btn btn-primary" id="uploadLogoBtn">
                                        <i class="bi bi-upload"></i> Upload
                                    </button>
                                    <button type="button" class="btn btn-danger" id="deleteLogoBtn" style="display: none;">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                                <div id="logoPreview" class="mt-2" style="display: none;">
                                    <img src="" alt="Logo Preview" class="img-thumbnail" style="max-height: 80px;">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Text & Labels -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-fonts me-2"></i>Text & Labels
                        </h5>
                    </div>
                    <div class="card-body">
                        <!-- Top Label -->
                        <div class="border rounded p-3 mb-3" id="labelToggle">
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" name="enable_label" id="enableLabel">
                                <label class="form-check-label" for="enableLabel">
                                    <strong>Top Label Text</strong>
                                </label>
                            </div>
                            
                            <div id="labelOptions" style="display: none;">
                                <div class="mb-3">
                                    <input type="text" class="form-control" name="label_text" id="labelText" placeholder="Enter text to display above QR code">
                                </div>
                                <div class="row">
                                    <div class="col-md-3 mb-2">
                                        <label class="form-label small">Font</label>
                                        <select class="form-select form-select-sm font-select" name="label_font" id="labelFontSelect">
                                            <option value="Arial">Arial</option>
                                            <option value="Helvetica">Helvetica</option>
                                            <option value="Times New Roman">Times New Roman</option>
                                            <option value="Georgia">Georgia</option>
                                            <option value="Verdana">Verdana</option>
                                            <option value="Courier New">Courier New</option>
                                            <option value="Comic Sans MS">Comic Sans MS</option>
                                            <option value="Impact">Impact</option>
                                            <option value="Trebuchet MS">Trebuchet MS</option>
                                            <option value="Lucida Console">Lucida Console</option>
                                            <option value="Brush Script MT">Brush Script MT</option>
                                            <option value="Caveat">Caveat</option>
                                            <option value="Pacifico">Pacifico</option>
                                            <option value="Lobster">Lobster</option>
                                            <option value="Bebas Neue">Bebas Neue</option>
                                            <option value="Oswald">Oswald</option>
                                            <option value="Montserrat">Montserrat</option>
                                            <option value="Roboto">Roboto</option>
                                            <option value="Raleway">Raleway</option>
                                            <option value="Dancing Script">Dancing Script</option>
                                            <option value="Permanent Marker">Permanent Marker</option>
                                            <option value="Orbitron">Orbitron</option>
                                            <option value="Fjalla One">Fjalla One</option>
                                            <option value="Shadows Into Light">Shadows Into Light</option>
                                            <option value="Indie Flower">Indie Flower</option>
                                        </select>
                                        <div class="form-check form-check-inline ms-1">
                                            <input class="form-check-input" type="checkbox" name="label_bold" id="labelBold">
                                            <label class="form-check-label small" for="labelBold">Bold</label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="checkbox" name="label_underline" id="labelUnderline">
                                            <label class="form-check-label small" for="labelUnderline">Underline</label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="checkbox" name="label_shadow" id="labelShadow">
                                            <label class="form-check-label small" for="labelShadow">Shadow</label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="checkbox" name="label_outline" id="labelOutline">
                                            <label class="form-check-label small" for="labelOutline">Outline</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3 mb-2">
                                        <label class="form-label small">Size</label>
                                        <div class="d-flex align-items-center gap-1">
                                            <input type="range" class="form-range" name="label_size" min="8" max="48" value="16" id="labelSizeRange">
                                            <span class="text-nowrap small" id="labelSizeValue">16</span>
                                        </div>
                                    </div>
                                    <div class="col-md-3 mb-2">
                                        <label class="form-label small">Color</label>
                                        <input type="color" class="form-control form-control-color" name="label_color" value="#000000">
                                    </div>
                                    <div class="col-md-3 mb-2">
                                        <label class="form-label small">Align</label>
                                        <select class="form-select form-select-sm" name="label_alignment">
                                            <option value="left">Left</option>
                                            <option value="center" selected>Center</option>
                                            <option value="right">Right</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3 mb-2">
                                        <label class="form-label small">Shadow Color</label>
                                        <input type="color" class="form-control form-control-color" name="label_shadow_color" value="#000000">
                                    </div>
                                    <div class="col-md-3 mb-2">
                                        <label class="form-label small">Outline Color</label>
                                        <input type="color" class="form-control form-control-color" name="label_outline_color" value="#000000">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Bottom Label -->
                        <div class="border rounded p-3" id="bottomToggle">
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" name="enable_bottom_text" id="enableBottomText">
                                <label class="form-check-label" for="enableBottomText">
                                    <strong>Bottom Label Text</strong>
                                </label>
                            </div>
                            
                            <div id="bottomTextOptions" style="display: none;">
                                <div class="mb-3">
                                    <input type="text" class="form-control" name="bottom_text" id="bottomText" placeholder="Enter text to display below QR code">
                                </div>
                                <div class="row">
                                    <div class="col-md-3 mb-2">
                                        <label class="form-label small">Font</label>
                                        <select class="form-select form-select-sm font-select" name="bottom_font" id="bottomFontSelect">
                                            <option value="Arial">Arial</option>
                                            <option value="Helvetica">Helvetica</option>
                                            <option value="Times New Roman">Times New Roman</option>
                                            <option value="Georgia">Georgia</option>
                                            <option value="Verdana">Verdana</option>
                                            <option value="Courier New">Courier New</option>
                                            <option value="Comic Sans MS">Comic Sans MS</option>
                                            <option value="Impact">Impact</option>
                                            <option value="Trebuchet MS">Trebuchet MS</option>
                                            <option value="Lucida Console">Lucida Console</option>
                                            <option value="Brush Script MT">Brush Script MT</option>
                                            <option value="Caveat">Caveat</option>
                                            <option value="Pacifico">Pacifico</option>
                                            <option value="Lobster">Lobster</option>
                                            <option value="Bebas Neue">Bebas Neue</option>
                                            <option value="Oswald">Oswald</option>
                                            <option value="Montserrat">Montserrat</option>
                                            <option value="Roboto">Roboto</option>
                                            <option value="Raleway">Raleway</option>
                                            <option value="Dancing Script">Dancing Script</option>
                                            <option value="Permanent Marker">Permanent Marker</option>
                                            <option value="Orbitron">Orbitron</option>
                                            <option value="Fjalla One">Fjalla One</option>
                                            <option value="Shadows Into Light">Shadows Into Light</option>
                                            <option value="Indie Flower">Indie Flower</option>
                                        </select>
                                        <div class="form-check form-check-inline ms-1">
                                            <input class="form-check-input" type="checkbox" name="bottom_bold" id="bottomBold">
                                            <label class="form-check-label small" for="bottomBold">Bold</label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="checkbox" name="bottom_underline" id="bottomUnderline">
                                            <label class="form-check-label small" for="bottomUnderline">Underline</label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="checkbox" name="bottom_shadow" id="bottomShadow">
                                            <label class="form-check-label small" for="bottomShadow">Shadow</label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="checkbox" name="bottom_outline" id="bottomOutline">
                                            <label class="form-check-label small" for="bottomOutline">Outline</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3 mb-2">
                                        <label class="form-label small">Size</label>
                                        <div class="d-flex align-items-center gap-1">
                                            <input type="range" class="form-range" name="bottom_size" min="8" max="48" value="14" id="bottomSizeRange">
                                            <span class="text-nowrap small" id="bottomSizeValue">14</span>
                                        </div>
                                    </div>
                                    <div class="col-md-3 mb-2">
                                        <label class="form-label small">Color</label>
                                        <input type="color" class="form-control form-control-color" name="bottom_color" value="#666666">
                                    </div>
                                    <div class="col-md-3 mb-2">
                                        <label class="form-label small">Align</label>
                                        <select class="form-select form-select-sm" name="bottom_alignment">
                                            <option value="left">Left</option>
                                            <option value="center" selected>Center</option>
                                            <option value="right">Right</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3 mb-2">
                                        <label class="form-label small">Shadow Color</label>
                                        <input type="color" class="form-control form-control-color" name="bottom_shadow_color" value="#000000">
                                    </div>
                                    <div class="col-md-3 mb-2">
                                        <label class="form-label small">Outline Color</label>
                                        <input type="color" class="form-control form-control-color" name="bottom_outline_color" value="#000000">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Enhanced Background Gradients -->
                <div class="card mb-4">
                    <div class="card-header bg-gradient-sunset text-white">
                        <h5 class="card-title mb-0"><i class="bi bi-palette"></i> Background Gradients</h5>
                    </div>
                    <div class="card-body">
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" name="enable_background_gradient" id="enableBgGradient">
                            <label class="form-check-label" for="enableBgGradient">
                                <strong>Enable Background Gradient</strong>
                            </label>
                        </div>
                        
                        <div id="backgroundGradientOptions" style="display: none;">
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label class="form-label">Gradient Type</label>
                                    <select class="form-select" name="bg_gradient_type">
                                        <option value="linear">Linear</option>
                                        <option value="radial">Radial</option>
                                        <option value="conic">Conic</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Preset Styles</label>
                                    <select class="form-select" name="bg_gradient_preset" id="bgGradientPreset">
                                        <option value="custom">Custom</option>
                                        <option value="sunset">Sunset</option>
                                        <option value="ocean">Ocean</option>
                                        <option value="forest">Forest</option>
                                        <option value="fire">Fire</option>
                                        <option value="rainbow">Rainbow</option>
                                        <option value="neon">Neon</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Animation</label>
                                    <select class="form-select" name="bg_gradient_animation">
                                        <option value="none">None</option>
                                        <option value="shift">Gradient Shift</option>
                                        <option value="pulse">Pulse</option>
                                        <option value="rotate">Rotate</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-3">
                                    <label class="form-label">Start Color</label>
                                    <input type="color" class="form-control form-control-color" name="bg_gradient_start" value="#ff7e5f">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Middle Color</label>
                                    <input type="color" class="form-control form-control-color" name="bg_gradient_middle" value="#feb47b">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">End Color</label>
                                    <input type="color" class="form-control form-control-color" name="bg_gradient_end" value="#ff6b6b">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Opacity</label>
                                    <input type="range" class="form-range" name="bg_gradient_opacity" min="0" max="1" step="0.1" value="1" id="bgOpacityRange">
                                    <div class="text-center"><span id="bgOpacityValue">100</span>%</div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <label class="form-label">Angle (Linear)</label>
                                    <input type="range" class="form-range" name="bg_gradient_angle" min="0" max="360" value="135" id="bgAngleRange">
                                    <div class="text-center"><span id="bgAngleValue">135</span>°</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Blend Mode</label>
                                    <select class="form-select" name="bg_blend_mode">
                                        <option value="normal">Normal</option>
                                        <option value="multiply">Multiply</option>
                                        <option value="screen">Screen</option>
                                        <option value="overlay">Overlay</option>
                                        <option value="soft-light">Soft Light</option>
                                        <option value="hard-light">Hard Light</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Enhanced Border Styles -->
                <div class="card mb-4">
                    <div class="card-header bg-gradient-ocean text-white">
                        <h5 class="card-title mb-0"><i class="bi bi-border-style"></i> Enhanced Borders</h5>
                    </div>
                    <div class="card-body">
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" name="enable_enhanced_border" id="enableEnhancedBorder">
                            <label class="form-check-label" for="enableEnhancedBorder">
                                <strong>Enable Enhanced Borders</strong>
                            </label>
                        </div>
                        
                        <div id="enhancedBorderOptions" style="display: none;">
                            <div class="row mb-3">
                                <div class="col-md-3">
                                    <label class="form-label">Border Style</label>
                                    <select class="form-select" name="border_style">
                                        <option value="solid">Solid</option>
                                        <option value="dashed">Dashed</option>
                                        <option value="dotted">Dotted</option>
                                        <option value="double">Double</option>
                                        <option value="groove">Groove</option>
                                        <option value="ridge">Ridge</option>
                                        <option value="gradient">Gradient</option>
                                        <option value="neon">Neon Glow</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Border Width</label>
                                    <select class="form-select" name="border_width">
                                        <option value="1">Thin (1px)</option>
                                        <option value="2" selected>Medium (2px)</option>
                                        <option value="3">Thick (3px)</option>
                                        <option value="5">Extra Thick (5px)</option>
                                        <option value="8">Ultra Thick (8px)</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Corner Style</label>
                                    <select class="form-select" name="border_radius_style">
                                        <option value="none">Sharp (0px)</option>
                                        <option value="xs">Extra Small (2px)</option>
                                        <option value="sm">Small (4px)</option>
                                        <option value="md">Medium (8px)</option>
                                        <option value="lg">Large (12px)</option>
                                        <option value="xl">Extra Large (16px)</option>
                                        <option value="round">Fully Rounded</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Border Pattern</label>
                                    <select class="form-select" name="border_pattern">
                                        <option value="uniform">Uniform</option>
                                        <option value="top-only">Top Only</option>
                                        <option value="bottom-only">Bottom Only</option>
                                        <option value="left-right">Left & Right</option>
                                        <option value="corners">Corners Only</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label class="form-label">Primary Color</label>
                                    <input type="color" class="form-control form-control-color" name="border_color_primary" value="#0d6efd">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Secondary Color</label>
                                    <input type="color" class="form-control form-control-color" name="border_color_secondary" value="#6610f2">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Accent Color</label>
                                    <input type="color" class="form-control form-control-color" name="border_color_accent" value="#d63384">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4">
                                    <label class="form-label">Glow Intensity</label>
                                    <input type="range" class="form-range" name="border_glow_intensity" min="0" max="20" value="0" id="borderGlowRange">
                                    <div class="text-center"><span id="borderGlowValue">0</span>px</div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Shadow Offset</label>
                                    <input type="range" class="form-range" name="border_shadow_offset" min="0" max="10" value="2" id="borderShadowRange">
                                    <div class="text-center"><span id="borderShadowValue">2</span>px</div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Animation Speed</label>
                                    <select class="form-select" name="border_animation_speed">
                                        <option value="none">No Animation</option>
                                        <option value="slow">Slow (5s)</option>
                                        <option value="medium">Medium (3s)</option>
                                        <option value="fast">Fast (1s)</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- QR Code Foreground Gradients -->
                <div class="card mb-4">
                    <div class="card-header bg-gradient-forest text-white">
                        <h5 class="card-title mb-0"><i class="bi bi-droplet"></i> QR Code Gradients</h5>
                    </div>
                    <div class="card-body">
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" name="enable_qr_gradient" id="enableQrGradient">
                            <label class="form-check-label" for="enableQrGradient">
                                <strong>Enable QR Code Gradient</strong>
                            </label>
                        </div>
                        
                        <div id="qrGradientOptions" style="display: none;">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Gradient Type</label>
                                    <select class="form-select" name="qr_gradient_type">
                                        <option value="linear">Linear</option>
                                        <option value="radial">Radial</option>
                                        <option value="conic">Conic</option>
                                        <option value="diamond">Diamond</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Preset Styles</label>
                                    <select class="form-select" name="qr_gradient_preset">
                                        <option value="custom">Custom</option>
                                        <option value="blue-purple">Blue to Purple</option>
                                        <option value="green-blue">Green to Blue</option>
                                        <option value="red-orange">Red to Orange</option>
                                        <option value="purple-pink">Purple to Pink</option>
                                        <option value="gold-bronze">Gold to Bronze</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-3">
                                    <label class="form-label">Start Color</label>
                                    <input type="color" class="form-control form-control-color" name="qr_gradient_start" value="#000000">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Middle Color</label>
                                    <input type="color" class="form-control form-control-color" name="qr_gradient_middle" value="#444444">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">End Color</label>
                                    <input type="color" class="form-control form-control-color" name="qr_gradient_end" value="#333333">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Angle</label>
                                    <input type="range" class="form-range" name="qr_gradient_angle" min="0" max="360" value="45" id="qrAngleRange">
                                    <div class="text-center"><span id="qrAngleValue">45</span>°</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Custom Eye Finder Patterns -->
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0"><i class="bi bi-eye"></i> Custom Eye Finder Patterns</h5>
                    </div>
                    <div class="card-body">
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" name="enable_custom_eyes" id="enableCustomEyes">
                            <label class="form-check-label" for="enableCustomEyes">
                                <strong>Enable Custom Eye Finder Patterns</strong>
                            </label>
                        </div>
                        
                        <div id="customEyesOptions" style="display: none;">
                            <!-- Eye Shape and Style -->
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label class="form-label">Eye Shape</label>
                                    <select class="form-select" name="eye_shape">
                                        <option value="square">Square (Default)</option>
                                        <option value="rounded">Rounded Square</option>
                                        <option value="circle">Circle</option>
                                        <option value="diamond">Diamond</option>
                                        <option value="leaf">Leaf</option>
                                        <option value="star">Star</option>
                                        <option value="heart">Heart</option>
                                        <option value="hexagon">Hexagon</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Eye Style</label>
                                    <select class="form-select" name="eye_style">
                                        <option value="solid">Solid Fill</option>
                                        <option value="outline">Outline Only</option>
                                        <option value="gradient">Gradient Fill</option>
                                        <option value="pattern">Pattern Fill</option>
                                        <option value="glow">Glow Effect</option>
                                        <option value="shadow">Drop Shadow</option>
                                    </select>
                                </div>

                            </div>

                            <!-- Eye Colors -->
                            <div class="row mb-3">
                                <div class="col-md-3">
                                    <label class="form-label">Outer Ring Color</label>
                                    <input type="color" class="form-control form-control-color" name="eye_outer_color" value="#000000">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Inner Ring Color</label>
                                    <input type="color" class="form-control form-control-color" name="eye_inner_color" value="#000000">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Center Dot Color</label>
                                    <input type="color" class="form-control form-control-color" name="eye_center_color" value="#000000">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Background Color</label>
                                    <input type="color" class="form-control form-control-color" name="eye_background_color" value="#FFFFFF">
                                </div>
                            </div>

                            <!-- Eye Gradient Options -->
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Eye Gradient Type</label>
                                    <select class="form-select" name="eye_gradient_type">
                                        <option value="none">No Gradient</option>
                                        <option value="linear">Linear</option>
                                        <option value="radial">Radial</option>
                                        <option value="conic">Conic</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Gradient Direction</label>
                                    <input type="range" class="form-range" name="eye_gradient_angle" min="0" max="360" value="45" id="eyeGradientAngleRange">
                                    <div class="text-center"><span id="eyeGradientAngleValue">45</span>°</div>
                                </div>
                            </div>

                            <!-- Eye Effects -->
                            <div class="row mb-3">
                                <div class="col-md-3">
                                    <label class="form-label">Border Width</label>
                                    <input type="range" class="form-range" name="eye_border_width" min="0" max="5" value="0" id="eyeBorderWidthRange">
                                    <div class="text-center"><span id="eyeBorderWidthValue">0</span>px</div>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Glow Intensity</label>
                                    <input type="range" class="form-range" name="eye_glow_intensity" min="0" max="20" value="0" id="eyeGlowRange">
                                    <div class="text-center"><span id="eyeGlowValue">0</span>px</div>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Shadow Offset</label>
                                    <input type="range" class="form-range" name="eye_shadow_offset" min="0" max="10" value="0" id="eyeShadowRange">
                                    <div class="text-center"><span id="eyeShadowValue">0</span>px</div>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Rotation</label>
                                    <input type="range" class="form-range" name="eye_rotation" min="0" max="360" value="0" id="eyeRotationRange">
                                    <div class="text-center"><span id="eyeRotationValue">0</span>°</div>
                                </div>
                            </div>


                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="card">
                    <div class="card-body text-center">
                        <button type="button" class="btn btn-gradient-primary btn-lg me-3" onclick="generatePreview()">
                            <i class="bi bi-eye"></i> Preview QR Code
                        </button>
                        <button type="button" class="btn btn-gradient-success btn-lg me-3" onclick="generateQR()">
                            <i class="bi bi-download"></i> Generate & Download
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="resetForm()">
                            <i class="bi bi-arrow-clockwise"></i> Reset
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Preview Panel -->
        <div class="col-lg-4">
            <div class="card sticky-top">
                <div class="card-header bg-gradient-fire text-white">
                    <h5 class="card-title mb-0"><i class="bi bi-eye"></i> Live Preview</h5>
                </div>
                <div class="card-body text-center">
                    <div id="qrPreview" class="qr-container-fancy mx-auto mb-3" style="min-height: 300px; display: flex; align-items: center; justify-content: center;">
                        <div class="text-muted">
                            <i class="bi bi-qr-code" style="font-size: 4rem;"></i>
                            <p class="mt-2">Configure your QR code to see preview</p>
                        </div>
                    </div>
                    
                    <!-- Spin Wheel Specific Preview -->
                    <div id="spinWheelPreview" style="display: none;">
                        <h6 class="text-start mb-3"><i class="bi bi-stars text-warning"></i> Spin Wheel Preview</h6>
                        
                        <div class="row">
                            <!-- Spin Wheel Column -->
                            <div class="col-md-7">
                                <div id="spinWheelContainer" class="mx-auto mb-3" style="max-width: 200px;">
                                    <canvas id="previewSpinWheel" width="200" height="200" style="border-radius: 50%; box-shadow: 0 4px 12px rgba(0,0,0,0.3);"></canvas>
                                </div>
                                <div class="text-center">
                                    <button type="button" class="btn btn-success btn-sm mb-2" id="testSpinBtn">
                                        <i class="bi bi-play-circle me-1"></i>Test Spin
                                    </button>
                                    <div id="testSpinResult" class="small"></div>
                                </div>
                            </div>
                            
                            <!-- Settings & Metrics Column -->
                            <div class="col-md-5">
                                <!-- Spin Metrics -->
                                <div class="card mb-2">
                                    <div class="card-body p-2">
                                        <h6 class="card-title mb-2"><i class="bi bi-bar-chart"></i> Metrics</h6>
                                        <div class="row text-center">
                                            <div class="col-6">
                                                <div class="fw-bold text-primary" id="totalSpinsMetric">0</div>
                                                <div class="small text-muted">Spins</div>
                                            </div>
                                            <div class="col-6">
                                                <div class="fw-bold text-warning" id="bigWinsMetric">0</div>
                                                <div class="small text-muted">Big Wins</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Quick Prize Setup -->
                                <div class="card mb-2">
                                    <div class="card-body p-2">
                                        <h6 class="card-title mb-2"><i class="bi bi-gift"></i> Quick Setup</h6>
                                        <div class="d-grid gap-1">
                                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="addDefaultPrizes()">
                                                <i class="bi bi-plus"></i> Add Defaults
                                            </button>
                                            <button type="button" class="btn btn-outline-success btn-sm" onclick="openPrizeModal()">
                                                <i class="bi bi-pencil"></i> Manage Prizes
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Prize List -->
                                <div class="card">
                                    <div class="card-body p-2">
                                        <h6 class="card-title mb-2"><i class="bi bi-list"></i> Current Prizes</h6>
                                        <div id="prizeList" class="small" style="max-height: 120px; overflow-y: auto;">
                                            <div class="text-muted">Select a spin wheel to see prizes</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div id="qrInfo" class="text-start" style="display: none;">
                        <h6>QR Code Information:</h6>
                        <ul class="list-unstyled small">
                            <li><strong>Type:</strong> <span id="infoType">-</span></li>
                            <li><strong>Size:</strong> <span id="infoSize">-</span></li>
                            <li><strong>Error Correction:</strong> <span id="infoError">-</span></li>
                            <li><strong>Estimated Scan Distance:</strong> <span id="infoDistance">-</span></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Prize Management Modal -->
<div class="modal fade" id="prizeModal" tabindex="-1" aria-labelledby="prizeModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="prizeModalLabel">Manage Spin Wheel Prizes</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-info">
          <i class="bi bi-info-circle me-2"></i>
          These prizes will be used for the spin wheel preview. For production use, manage prizes in the 
          <a href="/business/spin-wheel.php" target="_blank">Spin Wheel Management</a> section.
        </div>
        
        <form id="prizeForm">
          <div class="row">
            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label">Prize Name</label>
                <input type="text" class="form-control" id="prizeName" placeholder="e.g., Free Drink">
              </div>
            </div>
            <div class="col-md-3">
              <div class="mb-3">
                <label class="form-label">Rarity (1-10)</label>
                <input type="number" class="form-control" id="prizeRarity" min="1" max="10" value="5">
              </div>
            </div>
            <div class="col-md-3">
              <div class="mb-3">
                <label class="form-label">Color</label>
                <input type="color" class="form-control form-control-color" id="prizeColor" value="#28a745">
              </div>
            </div>
          </div>
          <div class="row">
            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label">Code/Coupon</label>
                <input type="text" class="form-control" id="prizeCode" placeholder="Optional">
              </div>
            </div>
            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label">Link</label>
                <input type="url" class="form-control" id="prizeLink" placeholder="Optional">
              </div>
            </div>
          </div>
          <div class="mb-3">
            <button type="button" class="btn btn-primary" onclick="addPrize()">
              <i class="bi bi-plus"></i> Add Prize
            </button>
          </div>
        </form>
        
        <div id="currentPrizes">
          <h6>Current Prizes:</h6>
          <div class="table-responsive">
            <table class="table table-sm">
              <thead>
                <tr>
                  <th>Name</th>
                  <th>Rarity</th>
                  <th>Color</th>
                  <th>Code</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody id="prizeTableBody">
                <tr>
                  <td colspan="5" class="text-muted text-center">No prizes added yet</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-success" onclick="updateSpinWheel()">
          <i class="bi bi-check"></i> Update Wheel
        </button>
      </div>
    </div>
  </div>
</div>

<script>
// Enhanced QR Generator JavaScript
class EnhancedQRGenerator {
    constructor() {
        this.form = document.getElementById('qrForm');
        this.preview = document.getElementById('qrPreview');
        this.spinWheelPrizes = [];
        this.setupEventListeners();
        this.setupRangeUpdaters();
        this.setupPresetHandlers();
    }

    setupEventListeners() {
        // QR Type change handler
        const qrTypeEl = document.getElementById('qrType');
        if (qrTypeEl) {
            qrTypeEl.addEventListener('change', (e) => {
                this.handleQRTypeChange(e.target.value);
            });
        } else {
            console.error('QR Type element not found');
        }

        // Toggle sections
        const enableBgGradient = document.getElementById('enableBgGradient');
        if (enableBgGradient) {
            enableBgGradient.addEventListener('change', (e) => {
                const options = document.getElementById('backgroundGradientOptions');
                if (options) {
                    options.style.display = e.target.checked ? 'block' : 'none';
                }
            });
        } else {
            console.error('enableBgGradient element not found');
        }

        const enableEnhancedBorder = document.getElementById('enableEnhancedBorder');
        if (enableEnhancedBorder) {
            enableEnhancedBorder.addEventListener('change', (e) => {
                const options = document.getElementById('enhancedBorderOptions');
                if (options) {
                    options.style.display = e.target.checked ? 'block' : 'none';
                }
            });
        } else {
            console.error('enableEnhancedBorder element not found');
        }

        const enableQrGradient = document.getElementById('enableQrGradient');
        if (enableQrGradient) {
            enableQrGradient.addEventListener('change', (e) => {
                const options = document.getElementById('qrGradientOptions');
                if (options) {
                    options.style.display = e.target.checked ? 'block' : 'none';
                }
            });
        } else {
            console.error('enableQrGradient element not found');
        }

        const enableCustomEyes = document.getElementById('enableCustomEyes');
        if (enableCustomEyes) {
            enableCustomEyes.addEventListener('change', (e) => {
                const options = document.getElementById('customEyesOptions');
                if (options) {
                    options.style.display = e.target.checked ? 'block' : 'none';
                }
            });
        } else {
            console.error('enableCustomEyes element not found');
        }



        // Text label toggles
        const enableLabel = document.getElementById('enableLabel');
        if (enableLabel) {
            enableLabel.addEventListener('change', (e) => {
                const options = document.getElementById('labelOptions');
                if (options) {
                    options.style.display = e.target.checked ? 'block' : 'none';
                }
            });
        }

        const enableBottomText = document.getElementById('enableBottomText');
        if (enableBottomText) {
            enableBottomText.addEventListener('change', (e) => {
                const options = document.getElementById('bottomTextOptions');
                if (options) {
                    options.style.display = e.target.checked ? 'block' : 'none';
                }
            });
        }

        // Auto-preview on changes
        if (this.form) {
            this.form.addEventListener('change', () => {
                this.debouncePreview();
            });

            this.form.addEventListener('input', () => {
                this.debouncePreview();
            });
        } else {
            console.error('Form element not found');
        }
    }

    handleQRTypeChange(type) {
        // Hide all dynamic fields first and disable required validation
        const fields = ['urlFields', 'campaignFields', 'machineFields', 'promotionFields', 'machinePromotionFields', 'spinWheelFields', 'pizzaTrackerFields'];
        fields.forEach(fieldId => {
            const field = document.getElementById(fieldId);
            if (field) field.style.display = 'none';
        });
        
        // Disable required validation for hidden fields
        const pizzaTrackerSelect = document.getElementById('pizzaTrackerSelect');
        if (pizzaTrackerSelect) pizzaTrackerSelect.removeAttribute('required');

        // Hide/show spin wheel preview
        const spinWheelPreview = document.getElementById('spinWheelPreview');
        const qrInfo = document.getElementById('qrInfo');
        
        if (type === 'spin_wheel') {
            if (spinWheelPreview) spinWheelPreview.style.display = 'block';
            if (qrInfo) qrInfo.style.display = 'none';
            this.initializeSpinWheelPreview();
        } else {
            if (spinWheelPreview) spinWheelPreview.style.display = 'none';
            if (qrInfo) qrInfo.style.display = 'block';
        }

        // Show relevant fields based on type
        switch(type) {
            case 'static':
                document.getElementById('urlFields').style.display = 'block';
                break;
            case 'dynamic':
                document.getElementById('urlFields').style.display = 'block';
                break;
            case 'dynamic_voting':
                document.getElementById('campaignFields').style.display = 'block';
                break;
            case 'promotion':
                document.getElementById('machineFields').style.display = 'block';
                break;
            case 'machine_sales':
                document.getElementById('promotionFields').style.display = 'block';
                break;
            case 'vending_discount_store':
                // No additional fields needed - auto-links to business store
                break;
            case 'spin_wheel':
                document.getElementById('spinWheelFields').style.display = 'block';
                break;
            case 'pizza_tracker':
                document.getElementById('pizzaTrackerFields').style.display = 'block';
                const pizzaTrackerSelect = document.getElementById('pizzaTrackerSelect');
                if (pizzaTrackerSelect) pizzaTrackerSelect.setAttribute('required', 'required');
                break;
        }
        
        // Update QR type description
        this.updateQRTypeDescription(type);
    }
    
    updateQRTypeDescription(type) {
        const descriptions = {
            'static': 'Creates a QR code that links directly to a fixed URL.',
            'dynamic': 'Creates a QR code with a URL that can be changed later.',
            'dynamic_voting': 'Creates a QR code that links to a voting campaign.',
            'promotion': 'Creates a QR code that shows promotions for a specific vending machine.',
            'machine_sales': 'Creates a QR code that shows current promotions and sales for a vending machine.',
            'vending_discount_store': 'Creates a QR code that links directly to your business discount store.',
            'spin_wheel': 'Creates a QR code that links to an interactive spin wheel game.',
            'pizza_tracker': 'Creates a QR code that links to a pizza order tracking page.'
        };
        
        const descriptionEl = document.getElementById('qrTypeDescription');
        if (descriptionEl && descriptions[type]) {
            descriptionEl.textContent = descriptions[type];
            descriptionEl.style.color = '#6c757d';
        }
    }

    setupRangeUpdaters() {
        const ranges = [
            { range: 'sizeRange', value: 'sizeValue', suffix: '' },
            { range: 'bgOpacityRange', value: 'bgOpacityValue', suffix: '', multiplier: 100 },
            { range: 'bgAngleRange', value: 'bgAngleValue', suffix: '' },
            { range: 'borderGlowRange', value: 'borderGlowValue', suffix: '' },
            { range: 'borderShadowRange', value: 'borderShadowValue', suffix: '' },
            { range: 'qrAngleRange', value: 'qrAngleValue', suffix: '' },

            { range: 'eyeGradientAngleRange', value: 'eyeGradientAngleValue', suffix: '' },
            { range: 'eyeBorderWidthRange', value: 'eyeBorderWidthValue', suffix: '' },
            { range: 'eyeGlowRange', value: 'eyeGlowValue', suffix: '' },
            { range: 'eyeShadowRange', value: 'eyeShadowValue', suffix: '' },
            { range: 'eyeRotationRange', value: 'eyeRotationValue', suffix: '' },
            { range: 'labelSizeRange', value: 'labelSizeValue', suffix: '' },
            { range: 'bottomSizeRange', value: 'bottomSizeValue', suffix: '' }
        ];

        ranges.forEach(({ range, value, suffix, multiplier = 1 }) => {
            const rangeEl = document.getElementById(range);
            const valueEl = document.getElementById(value);
            if (rangeEl && valueEl) {
                rangeEl.addEventListener('input', (e) => {
                    valueEl.textContent = Math.round(e.target.value * multiplier) + suffix;
                });
            }
        });

        // Color sync functionality
        const foregroundColor = document.getElementById('foregroundColor');
        const foregroundHex = document.getElementById('foregroundHex');
        if (foregroundColor && foregroundHex) {
            foregroundColor.addEventListener('input', (e) => {
                foregroundHex.value = e.target.value;
            });
            foregroundHex.addEventListener('input', (e) => {
                if (/^#[0-9A-F]{6}$/i.test(e.target.value)) {
                    foregroundColor.value = e.target.value;
                }
            });
        }

        const backgroundColor = document.getElementById('backgroundColor');
        const backgroundHex = document.getElementById('backgroundHex');
        if (backgroundColor && backgroundHex) {
            backgroundColor.addEventListener('input', (e) => {
                backgroundHex.value = e.target.value;
            });
            backgroundHex.addEventListener('input', (e) => {
                if (/^#[0-9A-F]{6}$/i.test(e.target.value)) {
                    backgroundColor.value = e.target.value;
                }
            });
        }
    }

    setupPresetHandlers() {
        // Background gradient presets
        const bgGradientPreset = document.getElementById('bgGradientPreset');
        if (bgGradientPreset) {
            bgGradientPreset.addEventListener('change', (e) => {
                const presets = {
                    sunset: { start: '#ff7e5f', middle: '#feb47b', end: '#ff6b6b' },
                    ocean: { start: '#667eea', middle: '#764ba2', end: '#f093fb' },
                    forest: { start: '#134e5e', middle: '#71b280', end: '#a8e6cf' },
                    fire: { start: '#ff416c', middle: '#ff4b2b', end: '#ff9a56' },
                    rainbow: { start: '#ff0000', middle: '#00ff00', end: '#0000ff' },
                    neon: { start: '#00ff00', middle: '#00ffff', end: '#ff00ff' }
                };

                const preset = presets[e.target.value];
                if (preset) {
                    const startEl = document.querySelector('[name="bg_gradient_start"]');
                    const middleEl = document.querySelector('[name="bg_gradient_middle"]');
                    const endEl = document.querySelector('[name="bg_gradient_end"]');
                    
                    if (startEl) startEl.value = preset.start;
                    if (middleEl) middleEl.value = preset.middle;
                    if (endEl) endEl.value = preset.end;
                    
                    // Trigger preview update
                    this.debouncePreview();
                }
            });
        }

        // QR gradient presets
        const qrGradientPreset = document.querySelector('[name="qr_gradient_preset"]');
        if (qrGradientPreset) {
            qrGradientPreset.addEventListener('change', (e) => {
                const presets = {
                    'blue-purple': { start: '#0d6efd', middle: '#4c63d2', end: '#6610f2' },
                    'green-blue': { start: '#198754', middle: '#17a2b8', end: '#0dcaf0' },
                    'red-orange': { start: '#dc3545', middle: '#e8743b', end: '#fd7e14' },
                    'purple-pink': { start: '#6610f2', middle: '#9d4edd', end: '#d63384' },
                    'gold-bronze': { start: '#ffc107', middle: '#d4a574', end: '#8b4513' }
                };

                const preset = presets[e.target.value];
                if (preset) {
                    const startEl = document.querySelector('[name="qr_gradient_start"]');
                    const middleEl = document.querySelector('[name="qr_gradient_middle"]');
                    const endEl = document.querySelector('[name="qr_gradient_end"]');
                    
                    if (startEl) startEl.value = preset.start;
                    if (middleEl) middleEl.value = preset.middle;
                    if (endEl) endEl.value = preset.end;
                    
                    // Trigger preview update
                    this.debouncePreview();
                }
            });
        }

        // Eye shape presets
        const eyeShapeSelect = document.querySelector('[name="eye_shape"]');
        if (eyeShapeSelect) {
            eyeShapeSelect.addEventListener('change', (e) => {
                // Auto-adjust eye style based on shape
                const eyeStyleSelect = document.querySelector('[name="eye_style"]');
                const shapeStyleMap = {
                    'circle': 'gradient',
                    'star': 'glow',
                    'heart': 'gradient',
                    'diamond': 'outline',
                    'hexagon': 'solid',
                    'leaf': 'gradient'
                };
                
                if (eyeStyleSelect && shapeStyleMap[e.target.value]) {
                    eyeStyleSelect.value = shapeStyleMap[e.target.value];
                }
                
                // Trigger preview update
                this.debouncePreview();
            });
        }
    }

    debouncePreview() {
        clearTimeout(this.previewTimeout);
        this.previewTimeout = setTimeout(() => {
            this.generatePreview();
        }, 500);
    }

    generatePreview() {
        // Validate form first
        if (!this.validateForm()) {
            return;
        }

        const formData = new FormData(this.form);
        formData.append('preview', '1');

        // Show loading state
        this.preview.innerHTML = `
            <div class="text-center">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Generating preview...</span>
                </div>
                <p class="mt-2">Generating preview...</p>
            </div>
        `;

        fetch('/api/qr/enhanced-preview.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            return response.text();
        })
        .then(text => {
            try {
                const data = JSON.parse(text);
                if (data.success) {
                    this.updatePreview(data.preview_url, data.info);
                } else {
                    this.showPreviewError(data.error || 'Failed to generate preview');
                }
            } catch (parseError) {
                console.error('JSON Parse Error:', parseError);
                console.error('Response text:', text);
                this.showPreviewError('Invalid response from server. Please check your login status.');
            }
        })
        .catch(error => {
            console.error('Preview error:', error);
            this.showPreviewError('Network error: ' + error.message);
        });
    }

    validateForm() {
        const qrTypeEl = document.querySelector('[name="qr_type"]');
        const locationEl = document.querySelector('[name="location"]');
        
        if (!qrTypeEl || !locationEl) {
            console.error('Required form elements not found');
            return false;
        }
        
        const qrType = qrTypeEl.value;
        const location = locationEl.value;

        // Check required fields based on QR type
        switch(qrType) {
            case 'static':
            case 'dynamic':
                const urlEl = document.querySelector('[name="url"]');
                if (urlEl && !urlEl.value) {
                    urlEl.value = 'https://example.com';
                }
                break;
            case 'dynamic_voting':
                const campaignEl = document.querySelector('[name="campaign_id"]');
                if (campaignEl && !campaignEl.value) {
                    // Don't block preview for testing
                }
                break;
            case 'promotion':
                const machineVendingEl = document.querySelector('[name="machine_name"]');
                if (machineVendingEl && !machineVendingEl.value) {
                    machineVendingEl.value = 'Test Machine';
                }
                break;
            case 'vending_discount_store':
                // No validation needed - auto-links to business store
                break;
            case 'machine_sales':
                const machineSalesEl = document.querySelector('[name="machine_name_sales"]') || 
                                      document.querySelector('[name="machine_name_promotion"]') || 
                                      document.querySelector('[name="machine_name"]');
                if (machineSalesEl && !machineSalesEl.value) {
                    machineSalesEl.value = 'Test Machine';
                }
                break;
            case 'spin_wheel':
                // No additional validation needed for spin_wheel
                break;
        }

        if (!location.trim()) {
            locationEl.value = 'Test Location';
        }

        return true;
    }

    showPreviewError(message) {
        this.preview.innerHTML = `
            <div class="text-center text-danger">
                <i class="bi bi-exclamation-triangle" style="font-size: 3rem;"></i>
                <p class="mt-2">${message}</p>
                <button class="btn btn-outline-primary btn-sm" onclick="window.qrGenerator.generatePreview()">
                    Try Again
                </button>
            </div>
        `;
    }

    updatePreview(previewUrl, info) {
        this.preview.innerHTML = `<img src="${previewUrl}" alt="QR Preview" style="max-width: 100%; height: auto;">`;
        
        // Apply animations if enabled
        this.applyPreviewAnimations();
        
        if (info) {
            document.getElementById('infoType').textContent = info.type;
            document.getElementById('infoSize').textContent = info.size + 'px';
            document.getElementById('infoError').textContent = info.error_correction;
            document.getElementById('infoDistance').textContent = info.scan_distance;
            document.getElementById('qrInfo').style.display = 'block';
        }
    }

    applyPreviewAnimations() {
        // Remove existing animation classes
        this.preview.classList.remove('qr-preview-shift', 'qr-preview-pulse', 'qr-preview-rotate', 'qr-preview-border-glow');
        this.preview.classList.remove('animation-slow', 'animation-medium', 'animation-fast');
        
        // Get animation settings
        const bgAnimation = document.querySelector('[name="bg_gradient_animation"]')?.value || 'none';
        const borderAnimationSpeed = document.querySelector('[name="border_animation_speed"]')?.value || 'none';
        
        // Apply background animations
        if (bgAnimation !== 'none') {
            switch (bgAnimation) {
                case 'shift':
                    this.preview.classList.add('qr-preview-shift');
                    break;
                case 'pulse':
                    this.preview.classList.add('qr-preview-pulse');
                    break;
                case 'rotate':
                    this.preview.classList.add('qr-preview-rotate');
                    break;
            }
        }
        
        // Apply border animations
        if (borderAnimationSpeed !== 'none') {
            this.preview.classList.add('qr-preview-border-glow');
            
            // Apply speed class
            switch (borderAnimationSpeed) {
                case 'slow':
                    this.preview.classList.add('animation-slow');
                    break;
                case 'medium':
                    this.preview.classList.add('animation-medium');
                    break;
                case 'fast':
                    this.preview.classList.add('animation-fast');
                    break;
            }
        }
    }

    initializeSpinWheelPreview() {
        // Initialize default prizes if none exist
        if (!this.spinWheelPrizes || this.spinWheelPrizes.length === 0) {
            this.addDefaultPrizes();
        }
        
        // Load metrics
        this.loadSpinMetrics();
        
        // Setup spin wheel canvas
        this.setupSpinWheelCanvas();
        
        // Load current wheel's prizes if one is selected
        this.loadSelectedWheelPrizes();
    }
    
    setupSpinWheelCanvas() {
        const canvas = document.getElementById('previewSpinWheel');
        if (!canvas) return;
        
        const ctx = canvas.getContext('2d');
        this.spinWheelCtx = ctx;
        this.spinWheelCanvas = canvas;
        this.spinRotation = 0;
        this.isSpinning = false;
        
        // Setup test spin button
        const testSpinBtn = document.getElementById('testSpinBtn');
        if (testSpinBtn) {
            testSpinBtn.addEventListener('click', () => this.testSpin());
        }
        
        this.drawSpinWheel();
    }
    
    drawSpinWheel() {
        if (!this.spinWheelCtx || !this.spinWheelPrizes) return;
        
        const canvas = this.spinWheelCanvas;
        const ctx = this.spinWheelCtx;
        const centerX = canvas.width / 2;
        const centerY = canvas.height / 2;
        const radius = (canvas.width / 2) - 15;
        
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        
        const prizes = this.spinWheelPrizes;
        if (prizes.length === 0) return;
        
        const sliceAngle = (2 * Math.PI) / prizes.length;
        
        // Enhanced gradient color palette
        const gradientColors = [
            ['#FF6B6B', '#FF8E53', '#FF6B9D'], // Red gradient
            ['#4ECDC4', '#44A08D', '#093637'], // Teal gradient
            ['#45B7D1', '#96C93D', '#00D2FF'], // Blue-green gradient
            ['#F093FB', '#F5576C', '#4FACFE'], // Pink-blue gradient
            ['#43E97B', '#38F9D7', '#84FAB0'], // Green gradient
            ['#FAACA8', '#DDD6F3', '#FAACA8'], // Purple-pink gradient
            ['#FFD89B', '#19547B', '#FFD89B'], // Orange-blue gradient
            ['#A8EDEA', '#FED6E3', '#D299C2'], // Mint-pink gradient
        ];
        
        prizes.forEach((prize, index) => {
            const startAngle = index * sliceAngle + this.spinRotation;
            const endAngle = startAngle + sliceAngle;
            const colorSet = gradientColors[index % gradientColors.length];
            
            // Create sophisticated gradient
            const gradient = ctx.createRadialGradient(centerX, centerY, 30, centerX, centerY, radius);
            gradient.addColorStop(0, colorSet[0]);
            gradient.addColorStop(0.6, colorSet[1]);
            gradient.addColorStop(1, colorSet[2]);
            
            ctx.beginPath();
            ctx.moveTo(centerX, centerY);
            ctx.arc(centerX, centerY, radius, startAngle, endAngle);
            ctx.closePath();
            ctx.fillStyle = gradient;
            ctx.fill();
            
            // Black border lines for premium look
            ctx.strokeStyle = '#000000';
            ctx.lineWidth = 2;
            ctx.stroke();
            
            // Inner highlight for depth
            ctx.beginPath();
            ctx.moveTo(centerX, centerY);
            ctx.arc(centerX, centerY, radius - 6, startAngle, endAngle);
            ctx.closePath();
            ctx.strokeStyle = 'rgba(255, 255, 255, 0.3)';
            ctx.lineWidth = 1;
            ctx.stroke();
            
            // Enhanced text with black outline (smaller for 200px canvas)
            ctx.save();
            ctx.translate(centerX, centerY);
            ctx.rotate(startAngle + sliceAngle / 2);
            
            // Smaller font sizes for compact canvas
            let fontSize = 10;
            let fontFamily = 'Arial';
            
            if (prize.rarity >= 8) {
                fontSize = 11;
                fontFamily = 'Impact';
            } else if (prize.rarity >= 5) {
                fontSize = 10;
                fontFamily = 'Arial Black';
            }
            
            ctx.font = `bold ${fontSize}px ${fontFamily}`;
            ctx.textAlign = 'right';
            
            const textRadius = radius - 18;
            
            // Black text outline (thinner for smaller text)
            ctx.strokeStyle = '#000000';
            ctx.lineWidth = 3;
            ctx.strokeText(prize.name, textRadius, 4);
            ctx.lineWidth = 2;
            ctx.strokeText(prize.name, textRadius, 4);
            
            // White text fill
            ctx.fillStyle = '#ffffff';
            ctx.fillText(prize.name, textRadius, 4);
            
            // Premium sparkle effect for rare items (smaller)
            if (prize.rarity >= 7) {
                ctx.font = `${fontSize - 1}px serif`;
                ctx.fillStyle = '#FFD700';
                ctx.strokeStyle = '#B8860B';
                ctx.lineWidth = 1;
                ctx.strokeText('★', textRadius + 8, -6);
                ctx.fillText('★', textRadius + 8, -6);
                ctx.strokeText('★', textRadius + 8, 12);
                ctx.fillText('★', textRadius + 8, 12);
            }
            
            ctx.restore();
        });
        
        // Center hub with gradient (smaller)
        const centerRadius = 18;
        const centerGradient = ctx.createRadialGradient(centerX, centerY, 0, centerX, centerY, centerRadius);
        centerGradient.addColorStop(0, '#ffffff');
        centerGradient.addColorStop(0.3, '#f8f9fa');
        centerGradient.addColorStop(0.7, '#dee2e6');
        centerGradient.addColorStop(1, '#495057');
        
        ctx.beginPath();
        ctx.arc(centerX, centerY, centerRadius, 0, 2 * Math.PI);
        ctx.fillStyle = centerGradient;
        ctx.fill();
        ctx.strokeStyle = '#000000';
        ctx.lineWidth = 2;
        ctx.stroke();
        
        // Premium arrow pointer (smaller)
        ctx.save();
        ctx.translate(centerX, centerY - radius - 8);
        ctx.fillStyle = '#DC143C';
        ctx.strokeStyle = '#000000';
        ctx.lineWidth = 2;
        ctx.beginPath();
        ctx.moveTo(0, 0);
        ctx.lineTo(-10, -15);
        ctx.lineTo(10, -15);
        ctx.closePath();
        ctx.fill();
        ctx.stroke();
        
        // Arrow highlight
        ctx.fillStyle = 'rgba(255, 255, 255, 0.4)';
        ctx.beginPath();
        ctx.moveTo(0, -2);
        ctx.lineTo(-8, -15);
        ctx.lineTo(8, -15);
        ctx.closePath();
        ctx.fill();
        ctx.restore();
    }
    
    testSpin() {
        if (this.isSpinning || !this.spinWheelPrizes || this.spinWheelPrizes.length === 0) return;
        
        this.isSpinning = true;
        const testSpinBtn = document.getElementById('testSpinBtn');
        if (testSpinBtn) {
            testSpinBtn.disabled = true;
            testSpinBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Spinning...';
        }
        
        const spinDuration = 4000;
        const startTime = Date.now();
        const startRotation = this.spinRotation;
        const spinAngle = 6 + Math.random() * 4;
        
        const animate = () => {
            const elapsed = Date.now() - startTime;
            const progress = Math.min(elapsed / spinDuration, 1);
            const easeOut = t => 1 - Math.pow(1 - t, 3);
            this.spinRotation = startRotation + (spinAngle * 2 * Math.PI * easeOut(progress));
            this.drawSpinWheel();
            
            if (progress < 1) {
                requestAnimationFrame(animate);
            } else {
                this.isSpinning = false;
                if (testSpinBtn) {
                    testSpinBtn.disabled = false;
                    testSpinBtn.innerHTML = '<i class="bi bi-play-circle me-1"></i>Test Spin';
                }
                
                // Determine winner
                const sliceAngle = (2 * Math.PI) / this.spinWheelPrizes.length;
                const normalizedRotation = ((this.spinRotation % (2 * Math.PI)) + (2 * Math.PI)) % (2 * Math.PI);
                const arrowAngle = (3 * Math.PI) / 2;
                const relativeAngle = (arrowAngle - normalizedRotation + (2 * Math.PI)) % (2 * Math.PI);
                const sliceIndex = Math.floor(relativeAngle / sliceAngle);
                const selectedIndex = sliceIndex >= 0 && sliceIndex < this.spinWheelPrizes.length ? sliceIndex : 0;
                const selected = this.spinWheelPrizes[selectedIndex];
                
                this.showTestResult(selected);
            }
        };
        animate();
    }
    
    showTestResult(prize) {
        const resultDiv = document.getElementById('testSpinResult');
        if (!resultDiv || !prize) return;
        
        const rarityBadge = prize.rarity >= 7 ? 
            '<span class="badge bg-warning text-dark ms-1">RARE! ✨</span>' : 
            prize.rarity >= 4 ? 
            '<span class="badge bg-info text-dark ms-1">GOOD! 🎯</span>' : 
            '<span class="badge bg-success text-dark ms-1">WIN! 🎉</span>';
        
        resultDiv.innerHTML = `
            <div class="alert alert-success alert-sm">
                <div class="fw-bold">🎉 ${prize.name} ${rarityBadge}</div>
                ${prize.code ? `<div class="small mt-1">Code: <code>${prize.code}</code></div>` : ''}
            </div>
        `;
        
        // Auto-clear after 5 seconds
        setTimeout(() => {
            resultDiv.innerHTML = '';
        }, 5000);
    }
    
    loadSpinMetrics() {
        // For preview purposes, show sample metrics
        document.getElementById('totalSpinsMetric').textContent = '1,247';
        document.getElementById('bigWinsMetric').textContent = '89';
    }
    
    loadSelectedWheelPrizes() {
        const spinWheelSelect = document.getElementById('spinWheelSelect');
        if (!spinWheelSelect || !spinWheelSelect.value) return;
        
        // For now, use default prizes - in production this would load from the selected wheel
        this.updatePrizeList();
    }
    
    updatePrizeList() {
        const prizeList = document.getElementById('prizeList');
        if (!prizeList || !this.spinWheelPrizes) return;
        
        if (this.spinWheelPrizes.length === 0) {
            prizeList.innerHTML = '<div class="text-muted">No prizes added yet</div>';
            return;
        }
        
        const html = this.spinWheelPrizes.map(prize => `
            <div class="d-flex justify-content-between align-items-center mb-1 p-1 border-bottom">
                <div>
                    <span class="fw-bold">${prize.name}</span>
                    <span class="badge bg-secondary ms-1">${prize.rarity}/10</span>
                </div>
                <div style="width: 20px; height: 20px; background: ${prize.color}; border-radius: 3px; border: 1px solid #000;"></div>
            </div>
        `).join('');
        
        prizeList.innerHTML = html;
    }
    
    updatePrizeTable() {
        const tbody = document.getElementById('prizeTableBody');
        if (!tbody || !this.spinWheelPrizes) return;
        
        if (this.spinWheelPrizes.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-muted text-center">No prizes added yet</td></tr>';
            return;
        }
        
        const html = this.spinWheelPrizes.map((prize, index) => `
            <tr>
                <td class="fw-bold">${prize.name}</td>
                <td>
                    <span class="badge bg-secondary">${prize.rarity}/10</span>
                </td>
                <td>
                    <div style="width: 30px; height: 20px; background: ${prize.color}; border-radius: 3px; border: 1px solid #000; display: inline-block;"></div>
                </td>
                <td>
                    ${prize.code ? `<code class="small">${prize.code}</code>` : '<span class="text-muted">-</span>'}
                </td>
                <td>
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="removePrize(${index})">
                        <i class="bi bi-trash"></i>
                    </button>
                </td>
            </tr>
        `).join('');
        
        tbody.innerHTML = html;
    }
    
    addDefaultPrizes() {
        const defaultPrizes = [
            { name: "Free Drink", rarity: 3, color: "#28a745", code: "DRINK25", link: "" },
            { name: "10% Off", rarity: 4, color: "#17a2b8", code: "SAVE10", link: "" },
            { name: "Free Snack", rarity: 5, color: "#ffc107", code: "SNACK5", link: "" },
            { name: "25% Off", rarity: 6, color: "#fd7e14", code: "SAVE25", link: "" },
            { name: "Free Meal", rarity: 8, color: "#dc3545", code: "MEAL1", link: "" },
            { name: "50% Off", rarity: 9, color: "#6f42c1", code: "SAVE50", link: "" },
            { name: "Better Luck Next Time", rarity: 1, color: "#6c757d", code: "", link: "" },
            { name: "Try Again", rarity: 2, color: "#adb5bd", code: "", link: "" }
        ];
        
        this.spinWheelPrizes = [...defaultPrizes];
        this.updatePrizeList();
        this.updatePrizeTable();
        if (this.spinWheelCtx) {
            this.drawSpinWheel();
        }
        
        // Show success message
        const prizeList = document.getElementById('prizeList');
        if (prizeList) {
            const successMsg = document.createElement('div');
            successMsg.className = 'alert alert-success alert-sm mt-2';
            successMsg.innerHTML = '<i class="bi bi-check"></i> Default prizes added!';
            prizeList.appendChild(successMsg);
            setTimeout(() => successMsg.remove(), 3000);
        }
    }
    
    updateQRTypeDescription(type) {
        const descriptions = {
            'static': 'Creates a QR code that links directly to a fixed URL.',
            'dynamic': 'Creates a QR code with a URL that can be changed later.',
            'dynamic_voting': 'Creates a QR code that links to a voting campaign.',
            'promotion': 'Creates a QR code that shows promotions for a specific vending machine.',
            'machine_sales': 'Creates a QR code that shows current promotions and sales for a vending machine.',
            'vending_discount_store': 'Creates a QR code that links directly to your business discount store.',
            'spin_wheel': 'Creates a QR code that links to an interactive spin wheel game.',
            'pizza_tracker': 'Creates a QR code that links to a pizza order tracking page.'
        };
        
        const descriptionEl = document.getElementById('qrTypeDescription');
        if (descriptionEl && descriptions[type]) {
            descriptionEl.textContent = descriptions[type];
            descriptionEl.style.color = '#6c757d';
        }
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    console.log('DOM loaded, initializing Enhanced QR Generator...');
    
    // Check if required elements exist
    const form = document.getElementById('qrForm');
    const preview = document.getElementById('qrPreview');
    
    if (!form) {
        console.error('Form element #qrForm not found!');
        return;
    }
    
    if (!preview) {
        console.error('Preview element #qrPreview not found!');
        return;
    }
    
    window.qrGenerator = new EnhancedQRGenerator();
    console.log('Enhanced QR Generator initialized');
    
    // Trigger initial field setup
    const qrType = document.getElementById('qrType');
    if (qrType && qrType.value) {
        console.log('Setting initial QR type:', qrType.value);
        window.qrGenerator.handleQRTypeChange(qrType.value);
    }
    
    // Test the toggle functionality
    setTimeout(() => {
        console.log('Testing toggle functionality...');
        const bgGradientToggle = document.getElementById('enableBgGradient');
        if (bgGradientToggle) {
            console.log('Background gradient toggle found');
        }
        
        const borderToggle = document.getElementById('enableEnhancedBorder');
        if (borderToggle) {
            console.log('Enhanced border toggle found');
        }
        
        const qrGradientToggle = document.getElementById('enableQrGradient');
        if (qrGradientToggle) {
            console.log('QR gradient toggle found');
        }
    }, 100);
});

// Global functions for buttons
function generatePreview() {
    window.qrGenerator.generatePreview();
}

function generateQR() {
    const formData = new FormData(document.getElementById('qrForm'));
    formData.append('generate', '1');

    fetch('/api/qr/enhanced-generate.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.blob())
    .then(blob => {
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'enhanced-qr-code.' + document.querySelector('[name="output_format"]').value;
        a.click();
        window.URL.revokeObjectURL(url);
    })
    .catch(error => {
        console.error('Generation error:', error);
        alert('Failed to generate QR code. Please try again.');
    });
}

function resetForm() {
    document.getElementById('qrForm').reset();
    document.getElementById('qrPreview').innerHTML = `
        <div class="text-muted">
            <i class="bi bi-qr-code" style="font-size: 4rem;"></i>
            <p class="mt-2">Configure your QR code to see preview</p>
        </div>
    `;
    document.getElementById('qrInfo').style.display = 'none';
}

// Global Prize Management Functions
function addDefaultPrizes() {
    const generator = window.qrGenerator;
    if (!generator) return;
    
    generator.addDefaultPrizes();
}

function openPrizeModal() {
    const modal = new bootstrap.Modal(document.getElementById('prizeModal'));
    const generator = window.qrGenerator;
    if (generator) {
        generator.updatePrizeTable();
    }
    modal.show();
}

function addPrize() {
    const generator = window.qrGenerator;
    if (!generator) return;
    
    const name = document.getElementById('prizeName').value.trim();
    const rarity = parseInt(document.getElementById('prizeRarity').value);
    const color = document.getElementById('prizeColor').value;
    const code = document.getElementById('prizeCode').value.trim();
    const link = document.getElementById('prizeLink').value.trim();
    
    if (!name) {
        alert('Prize name is required');
        return;
    }
    
    const newPrize = { name, rarity, color, code, link };
    generator.spinWheelPrizes.push(newPrize);
    
    // Clear form
    document.getElementById('prizeName').value = '';
    document.getElementById('prizeRarity').value = '5';
    document.getElementById('prizeColor').value = '#28a745';
    document.getElementById('prizeCode').value = '';
    document.getElementById('prizeLink').value = '';
    
    generator.updatePrizeTable();
}

function removePrize(index) {
    const generator = window.qrGenerator;
    if (!generator || !confirm('Remove this prize?')) return;
    
    generator.spinWheelPrizes.splice(index, 1);
    generator.updatePrizeTable();
}

function updateSpinWheel() {
    const generator = window.qrGenerator;
    if (!generator) return;
    
    generator.updatePrizeList();
    if (generator.spinWheelCtx) {
        generator.drawSpinWheel();
    }
    
    // Close modal
    const modal = bootstrap.Modal.getInstance(document.getElementById('prizeModal'));
    if (modal) modal.hide();
}
</script>

<?php require_once __DIR__ . '/core/includes/footer.php'; ?> 