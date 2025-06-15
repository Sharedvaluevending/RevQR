<?php
require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/core/session.php';
require_once __DIR__ . '/core/auth.php';
require_once __DIR__ . '/core/business_utils.php';

// Require business role
require_role('business');

// Get business_id with proper error handling
try {
    $business_id = getOrCreateBusinessId($pdo, $_SESSION['user_id']);
} catch (Exception $e) {
    $business_id = null;
    error_log("Error getting business ID in QR generator: " . $e->getMessage());
}

$campaigns = [];
$promotions = [];

if ($business_id) {
    try {
        // Fetch campaigns for the logged-in business
        $stmt = $pdo->prepare("
            SELECT id, name
            FROM campaigns
            WHERE business_id = ?
            ORDER BY name ASC
        ");
        $stmt->execute([$business_id]);
        $campaigns = $stmt->fetchAll();

        // Fetch active promotions for the logged-in business
        $stmt = $pdo->prepare("
            SELECT p.id, p.promo_code, p.description, p.discount_type, p.discount_value
            FROM promotions p
            WHERE p.business_id = ? AND p.status = 'active'
            ORDER BY p.promo_code ASC
        ");
        $stmt->execute([$business_id]);
        $promotions = $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Error fetching campaigns/promotions: " . $e->getMessage());
    }
}

require_once __DIR__ . '/core/includes/header.php';
?>

<!-- QR Code Generation Library -->
<script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>

<style>
/* Advanced QR Generator Styles */
.qr-generator-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 2rem;
}

.qr-preview-container {
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    padding: 2rem;
    margin-bottom: 2rem;
    position: relative;
    min-height: 400px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    backdrop-filter: blur(10px);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
}

.qr-preview-header {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    padding: 1rem 2rem;
    background: rgba(255, 255, 255, 0.05);
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    color: #fff;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    border-radius: 12px 12px 0 0;
}

.qr-preview-canvas {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2rem;
    margin: 1rem 0;
    background: rgba(0, 0, 0, 0.2);
    border-radius: 8px;
    position: relative;
    min-width: 300px;
    min-height: 300px;
}

.qr-preview-footer {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    padding: 1rem 2rem;
    background: rgba(255, 255, 255, 0.05);
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.9rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-radius: 0 0 12px 12px;
}

.preview-info {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.preview-info span {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.preview-info i {
    font-size: 1.1rem;
    color: rgba(255, 255, 255, 0.5);
}

/* Form Controls */
.form-controls {
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    padding: 2rem;
    backdrop-filter: blur(10px);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-label {
    color: #fff;
    font-weight: 500;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.form-control,
.form-select {
    background: rgba(0, 0, 0, 0.2);
    border: 1px solid rgba(255, 255, 255, 0.1);
    color: #fff;
    padding: 0.75rem 1rem;
    border-radius: 8px;
    transition: all 0.3s ease;
}

.form-control:focus,
.form-select:focus {
    background: rgba(0, 0, 0, 0.3);
    border-color: rgba(255, 255, 255, 0.3);
    box-shadow: 0 0 0 0.2rem rgba(255, 255, 255, 0.1);
    color: #fff;
}

.form-control::placeholder {
    color: rgba(255, 255, 255, 0.5);
}

.form-select option {
    background: #1a1a1a;
    color: #fff;
}

/* Dynamic Fields */
.dynamic-fields {
    background: rgba(255, 255, 255, 0.05);
    border-radius: 8px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    border: 1px solid rgba(255, 255, 255, 0.1);
    transition: all 0.3s ease;
}

.dynamic-fields:hover {
    background: rgba(255, 255, 255, 0.08);
    border-color: rgba(255, 255, 255, 0.2);
}

.range-input-group {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.form-range {
    flex: 1;
}

.range-value {
    color: #fff;
    font-weight: 500;
    min-width: 50px;
    text-align: center;
    background: rgba(255, 255, 255, 0.1);
    padding: 0.5rem;
    border-radius: 6px;
}

/* Generate Button */
.btn-generate-download {
    background: linear-gradient(45deg, #4CAF50, #45a049);
    color: white;
    padding: 1rem 2rem;
    border: none;
    border-radius: 8px;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.3s ease;
    width: 100%;
    justify-content: center;
    margin-top: 2rem;
}

.btn-generate-download:hover {
    background: linear-gradient(45deg, #45a049, #4CAF50);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(76, 175, 80, 0.2);
}

.btn-generate-download i {
    font-size: 1.2rem;
}

/* Success Modal */
.success-modal .modal-content {
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    color: #fff;
}

.success-modal .modal-header {
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    padding: 1.5rem;
}

.success-modal .modal-body {
    padding: 2rem;
    text-align: center;
}

.success-modal .modal-footer {
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    padding: 1.5rem;
}

.success-icon {
    font-size: 4rem;
    color: #4CAF50;
    margin-bottom: 1rem;
}

/* Toast Notifications */
.toast {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    color: #fff;
}

.toast-body {
    padding: 1rem;
}

/* Form validation styles */
.form-control.is-invalid,
.form-select.is-invalid {
    border-color: #dc3545;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23dc3545'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath stroke-linejoin='round' d='M5.8 3.6h.4L6 6.5z'/%3e%3ccircle cx='6' cy='8.2' r='.6' fill='%23dc3545' stroke='none'/%3e%3c/svg%3e");
    background-repeat: no-repeat;
    background-position: right calc(0.375em + 0.1875rem) center;
    background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
}

.invalid-feedback {
    display: none;
    width: 100%;
    margin-top: 0.25rem;
    font-size: 0.875em;
    color: #dc3545;
}

.form-control.is-invalid ~ .invalid-feedback,
.form-select.is-invalid ~ .invalid-feedback {
    display: block;
}

/* Side-by-side layout styles */
.sticky-top {
    position: sticky;
    top: 2rem;
}

.card {
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    backdrop-filter: blur(10px);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
}

.card-header {
    background: rgba(255, 255, 255, 0.05);
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    color: #fff;
    font-weight: 500;
    border-radius: 12px 12px 0 0;
}

.card-body {
    padding: 2rem;
}

.card-title {
    margin-bottom: 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
</style>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0">🎯 QR Code Generator</h1>
                <a href="/qr-generator-enhanced.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-right"></i> Enhanced Generator
                </a>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Configuration Panel -->
        <div class="col-lg-8">
            <div class="form-controls">
                <form id="qrGeneratorForm">
                    <div class="form-group">
                        <label for="qrType" class="form-label">
                            <i class="bi bi-tag"></i>
                            QR Code Type
                        </label>
                        <select class="form-select" id="qrType" name="qrType" required>
                            <option value="">Select QR Code Type</option>
                            <option value="static">Static QR Code</option>
                            <option value="dynamic">Dynamic QR Code</option>
                            <option value="dynamic_voting">Dynamic Voting QR Code</option>
                            <option value="dynamic_vending">Dynamic Voting Vending Machine QR Code</option>
                            <option value="machine_sales">Vending Machine Promotions QR Code</option>
                            <option value="spin_wheel">Spin Wheel QR Code</option>
                            <option value="pizza_tracker">Pizza Tracker QR Code</option>
                            <option value="promotion" disabled>Dynamic Promotion QR Code (Coming Soon)</option>
                            <option value="cross_promo" disabled>Cross-Promotion QR Code (Coming Soon)</option>
                            <option value="stackable" disabled>Stackable QR Code (Coming Soon)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="sizeRange" class="form-label">
                            <i class="bi bi-arrows-angle-expand"></i>
                            Size (px)
                        </label>
                        <div class="range-input-group">
                            <input type="range" class="form-range" name="size" id="sizeRange" min="200" max="800" value="400" step="50">
                            <span class="range-value" id="sizeValue">400</span>
                        </div>
                    </div>

                    <!-- Dynamic Fields -->
                    <div id="urlFields" class="dynamic-fields" style="display: none;">
                        <div class="form-group">
                            <label for="url" class="form-label">
                                <i class="bi bi-link-45deg"></i>
                                URL
                            </label>
                            <input type="url" class="form-control" id="url" name="url" placeholder="Enter URL">
                        </div>
                    </div>

                    <div id="campaignFields" class="dynamic-fields" style="display: none;">
                        <div class="form-group">
                            <label for="campaignId" class="form-label">
                                <i class="bi bi-megaphone"></i>
                                Campaign
                            </label>
                            <select class="form-select" id="campaignId" name="campaignId">
                                <option value="">Select Campaign</option>
                                <?php foreach ($campaigns as $campaign): ?>
                                <option value="<?php echo $campaign['id']; ?>"><?php echo htmlspecialchars($campaign['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div id="machineFields" class="dynamic-fields" style="display: none;">
                        <div class="form-group">
                            <label for="machineName" class="form-label">
                                <i class="bi bi-cpu"></i>
                                Machine Name
                            </label>
                            <input type="text" class="form-control" id="machineName" name="machineName" placeholder="Enter machine name">
                        </div>
                    </div>

                    <div id="promotionFields" class="dynamic-fields" style="display: none;">
                        <div class="form-group">
                            <label for="promotionId" class="form-label">
                                <i class="bi bi-tag"></i>
                                Promotion
                            </label>
                            <select class="form-select" id="promotionId" name="promotionId">
                                <option value="">Select Promotion</option>
                                <?php foreach ($promotions as $promotion): ?>
                                <option value="<?php echo $promotion['id']; ?>"><?php echo htmlspecialchars($promotion['promo_code']); ?> - <?php echo $promotion['discount_type'] === 'percentage' ? $promotion['discount_value'] . '%' : '$' . $promotion['discount_value']; ?> off</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div id="machinePromotionFields" class="dynamic-fields" style="display: none;">
                        <div class="form-group">
                            <label for="machinePromotionId" class="form-label">
                                <i class="bi bi-tag"></i>
                                Machine Promotion
                            </label>
                            <select class="form-select" id="machinePromotionId" name="machinePromotionId">
                                <option value="">Select Machine Promotion</option>
                                <?php foreach ($promotions as $promotion): ?>
                                <option value="<?php echo $promotion['id']; ?>"><?php echo htmlspecialchars($promotion['promo_code']); ?> - <?php echo $promotion['discount_type'] === 'percentage' ? $promotion['discount_value'] . '%' : '$' . $promotion['discount_value']; ?> off</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div id="spinWheelFields" class="dynamic-fields" style="display: none;">
                        <div class="form-group">
                            <label for="spinWheelId" class="form-label">
                                <i class="bi bi-arrow-repeat"></i>
                                Spin Wheel
                            </label>
                            <select class="form-select" id="spinWheelId" name="spinWheelId">
                                <option value="">Select Spin Wheel</option>
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
                                            <option value="<?php echo htmlspecialchars($wheel['id']); ?>">
                                                <?php echo htmlspecialchars($wheel['name']); ?>
                                                <?php if ($wheel['description']): ?>
                                                    - <?php echo htmlspecialchars($wheel['description']); ?>
                                                <?php endif; ?>
                                            </option>
                                        <?php endforeach;
                                    } catch (Exception $e) {
                                        error_log("Error loading spin wheels: " . $e->getMessage());
                                        echo '<option value="">Error loading spin wheels</option>';
                                    }
                                }
                                ?>
                            </select>
                        </div>
                    </div>

                    <div id="pizzaTrackerFields" class="dynamic-fields" style="display: none;">
                        <div class="form-group">
                            <label for="pizzaTrackerSelect" class="form-label">
                                <i class="bi bi-pizza"></i>
                                Pizza Tracker
                            </label>
                            <select class="form-select" id="pizzaTrackerSelect" name="pizza_tracker_id" required>
                                <option value="">Select a pizza tracker</option>
                                <?php 
                                // Get pizza trackers for this business
                                if (isset($business_id) && $business_id) {
                                    try {
                                        $stmt = $pdo->prepare("
                                            SELECT id, name, description, 
                                                   ROUND((current_revenue / NULLIF(revenue_goal, 0)) * 100, 1) as progress_percent,
                                                   current_revenue, revenue_goal, completion_count
                                            FROM pizza_trackers 
                                            WHERE business_id = ? AND is_active = 1 
                                            ORDER BY name
                                        ");
                                        $stmt->execute([$business_id]);
                                        $pizza_trackers = $stmt->fetchAll();
                                        
                                        if (count($pizza_trackers) > 0) {
                                            foreach ($pizza_trackers as $tracker): ?>
                                                <option value="<?php echo $tracker['id']; ?>">
                                                    <?php echo htmlspecialchars($tracker['name']); ?>
                                                    <?php if ($tracker['progress_percent']): ?>
                                                        (<?php echo $tracker['progress_percent']; ?>% complete - $<?php echo number_format($tracker['current_revenue'], 2); ?>/$<?php echo number_format($tracker['revenue_goal'], 2); ?>)
                                                    <?php else: ?>
                                                        (Just started - $0.00/$<?php echo number_format($tracker['revenue_goal'], 2); ?>)
                                                    <?php endif; ?>
                                                </option>
                                            <?php endforeach;
                                        } else {
                                            echo '<option value="">No pizza trackers found for this business</option>';
                                        }
                                    } catch (Exception $e) {
                                        error_log("Error loading pizza trackers: " . $e->getMessage());
                                        error_log("Business ID: " . ($business_id ?? 'NULL'));
                                        echo '<option value="">Error loading pizza trackers</option>';
                                    }
                                } else {
                                    echo '<option value="">Business ID not found</option>';
                                    // Debug: Show actual user ID and business relationship
                                    if (isset($_SESSION['user_id'])) {
                                        try {
                                            $debug_stmt = $pdo->prepare("SELECT business_id FROM users WHERE id = ?");
                                            $debug_stmt->execute([$_SESSION['user_id']]);
                                            $debug_result = $debug_stmt->fetch();
                                            error_log("Debug - User ID: " . $_SESSION['user_id'] . ", Found business_id: " . ($debug_result['business_id'] ?? 'NULL'));
                                        } catch (Exception $e) {
                                            error_log("Debug query failed: " . $e->getMessage());
                                        }
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
                    </div>

                    <!-- Location Field - Required for all QR types -->
                    <div class="form-group">
                        <label for="location" class="form-label">
                            <i class="bi bi-geo-alt"></i>
                            Location <span class="text-danger">*</span>
                        </label>
                        <input type="text" class="form-control" id="location" name="location" required placeholder="e.g., Main Lobby, Building A">
                    </div>

                    <!-- Design & Colors Section -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-palette"></i> Design & Colors
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

                    <!-- Text & Labels Section -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-fonts"></i> Text & Labels
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

                    <button type="button" onclick="generateQRCode()" class="btn-generate-download">
                        <i class="bi bi-download"></i>
                        Generate & Download QR Code
                    </button>
                </form>
            </div>
        </div>

        <!-- Preview Panel -->
        <div class="col-lg-4">
            <div class="card sticky-top">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-qr-code"></i>
                        QR Code Preview
                    </h5>
                </div>
                <div class="card-body text-center">
                    <div class="qr-preview-canvas">
                        <div id="qrPreview" style="display: none;"></div>
                        <div class="text-muted" id="previewPlaceholder">
                            <i class="bi bi-qr-code" style="font-size: 4rem;"></i>
                            <p class="mt-2">Configure your QR code to see preview</p>
                        </div>
                    </div>
                    <div class="preview-info mt-3">
                        <span><i class="bi bi-arrows-angle-expand"></i> <span id="previewSize">400px</span></span>
                        <span><i class="bi bi-tag"></i> <span id="previewType">Select Type</span></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Success Modal -->
<div class="modal fade success-modal" id="successModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Success!</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="success-icon">
                    <i class="bi bi-check-circle-fill"></i>
                </div>
                <h4>QR Code Generated Successfully!</h4>
                <p class="mb-0">Your QR code has been generated and downloaded.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
// Handle QR type selection and show/hide appropriate fields
document.getElementById('qrType').addEventListener('change', function() {
    const type = this.value;
    const typeText = this.options[this.selectedIndex].text;
    
    // Hide all dynamic fields first and disable required validation
    document.getElementById('urlFields').style.display = 'none';
    // Hide all type-specific fields
    const fieldSets = ['urlFields', 'campaignFields', 'machineFields', 'promotionFields', 'machinePromotionFields', 'spinWheelFields', 'pizzaTrackerFields'];
    fieldSets.forEach(fieldSet => {
        const element = document.getElementById(fieldSet);
        if (element) {
            element.style.display = 'none';
            // Remove required attributes from hidden fields
            const inputs = element.querySelectorAll('input, select');
            inputs.forEach(input => input.removeAttribute('required'));
        }
    });
    
    // Disable required validation for hidden fields
    document.getElementById('pizzaTrackerSelect').removeAttribute('required');
    
    // Show fields based on selected type and add required validation
    switch(type) {
        case 'static':
        case 'dynamic':
            const urlField = document.getElementById('urlFields');
            if (urlField) {
                urlField.style.display = 'block';
                const urlInput = urlField.querySelector('#url');
                if (urlInput) urlInput.setAttribute('required', 'required');
            }
            break;
        case 'dynamic_voting':
            const campaignField = document.getElementById('campaignFields');
            if (campaignField) {
                campaignField.style.display = 'block';
                const campaignSelect = campaignField.querySelector('#campaignId, #campaignSelect');
                if (campaignSelect) campaignSelect.setAttribute('required', 'required');
            }
            break;
        case 'dynamic_vending':
            const machineField = document.getElementById('machineFields');
            if (machineField) {
                machineField.style.display = 'block';
                const machineInput = machineField.querySelector('#machineName');
                if (machineInput) machineInput.setAttribute('required', 'required');
            }
            break;
        case 'machine_sales':
            const salesField = document.getElementById('promotionFields');
            if (salesField) {
                salesField.style.display = 'block';
                const salesInput = salesField.querySelector('#machineName');
                if (salesInput) salesInput.setAttribute('required', 'required');
            }
            break;
        case 'promotion':
            const promoField = document.getElementById('machinePromotionFields');
            if (promoField) {
                promoField.style.display = 'block';
                const promoInput = promoField.querySelector('#machineName');
                if (promoInput) promoInput.setAttribute('required', 'required');
            }
            break;
        case 'spin_wheel':
            const spinField = document.getElementById('spinWheelFields');
            if (spinField) {
                spinField.style.display = 'block';
                const spinSelect = spinField.querySelector('#spinWheelId, #spinWheelSelect');
                if (spinSelect) spinSelect.setAttribute('required', 'required');
            }
            break;
        case 'pizza_tracker':
            const pizzaField = document.getElementById('pizzaTrackerFields');
            if (pizzaField) {
                pizzaField.style.display = 'block';
                const pizzaSelect = pizzaField.querySelector('#pizzaTrackerSelect');
                if (pizzaSelect) pizzaSelect.setAttribute('required', 'required');
            }
            break;
    }
    
    // Update preview type display
    document.getElementById('previewType').textContent = typeText;
    
    // Generate preview
    generatePreview();
});

// Update preview size display
document.getElementById('sizeRange').addEventListener('input', function() {
    const size = this.value;
    document.getElementById('sizeValue').textContent = size;
    document.getElementById('previewSize').textContent = size + 'px';
    generatePreview();
});

// Handle color picker and hex input synchronization
document.getElementById('foregroundColor').addEventListener('input', function() {
    document.getElementById('foregroundHex').value = this.value;
});
document.getElementById('foregroundHex').addEventListener('input', function() {
    document.getElementById('foregroundColor').value = this.value;
});
document.getElementById('backgroundColor').addEventListener('input', function() {
    document.getElementById('backgroundHex').value = this.value;
});
document.getElementById('backgroundHex').addEventListener('input', function() {
    document.getElementById('backgroundColor').value = this.value;
});

// Handle label toggle
document.getElementById('enableLabel').addEventListener('change', function() {
    const labelOptions = document.getElementById('labelOptions');
    labelOptions.style.display = this.checked ? 'block' : 'none';
});

// Handle bottom text toggle
document.getElementById('enableBottomText').addEventListener('change', function() {
    const bottomTextOptions = document.getElementById('bottomTextOptions');
    bottomTextOptions.style.display = this.checked ? 'block' : 'none';
});

// Handle label size range
document.getElementById('labelSizeRange').addEventListener('input', function() {
    document.getElementById('labelSizeValue').textContent = this.value;
});

// Handle bottom size range
document.getElementById('bottomSizeRange').addEventListener('input', function() {
    document.getElementById('bottomSizeValue').textContent = this.value;
});

// Logo upload functionality
document.getElementById('uploadLogoBtn').addEventListener('click', function() {
    const fileInput = document.getElementById('logoUpload');
    const file = fileInput.files[0];
    
    if (!file) {
        showToast('Please select a file first', 'warning');
        return;
    }
    
    if (file.size > 2 * 1024 * 1024) { // 2MB limit
        showToast('File size must be less than 2MB', 'danger');
        return;
    }
    
    const formData = new FormData();
    formData.append('logo', file);
    
    fetch('/api/upload-logo.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Add to logo select
            const option = document.createElement('option');
            option.value = data.filename;
            option.textContent = data.filename;
            option.selected = true;
            document.getElementById('logoSelect').appendChild(option);
            
            // Show preview
            const preview = document.getElementById('logoPreview');
            const img = preview.querySelector('img');
            img.src = data.url;
            preview.style.display = 'block';
            
            // Show delete button
            document.getElementById('deleteLogoBtn').style.display = 'inline-block';
            
            showToast('Logo uploaded successfully', 'success');
        } else {
            showToast(data.error || 'Failed to upload logo', 'danger');
        }
    })
    .catch(error => {
        console.error('Upload error:', error);
        showToast('Failed to upload logo', 'danger');
    });
});

// Delete logo functionality
document.getElementById('deleteLogoBtn').addEventListener('click', function() {
    const logoSelect = document.getElementById('logoSelect');
    const selectedOption = logoSelect.options[logoSelect.selectedIndex];
    
    if (selectedOption && selectedOption.value) {
        fetch('/api/delete-logo.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ filename: selectedOption.value })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                selectedOption.remove();
                document.getElementById('logoPreview').style.display = 'none';
                document.getElementById('deleteLogoBtn').style.display = 'none';
                logoSelect.value = '';
                showToast('Logo deleted successfully', 'success');
            } else {
                showToast(data.error || 'Failed to delete logo', 'danger');
            }
        })
        .catch(error => {
            console.error('Delete error:', error);
            showToast('Failed to delete logo', 'danger');
        });
    }
});

// Show toast notification
function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    toast.className = `toast align-items-center text-white bg-${type} border-0`;
    toast.setAttribute('role', 'alert');
    toast.setAttribute('aria-live', 'assertive');
    toast.setAttribute('aria-atomic', 'true');
    
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">
                ${message}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    `;
    
    document.body.appendChild(toast);
    const bsToast = new bootstrap.Toast(toast);
    bsToast.show();
    
    toast.addEventListener('hidden.bs.toast', function() {
        document.body.removeChild(toast);
    });
}

// Generate QR code preview
function generatePreview() {
    const qrType = document.getElementById('qrType').value;
    const size = parseInt(document.getElementById('sizeRange').value);
    const foregroundColor = document.getElementById('foregroundColor').value;
    const backgroundColor = document.getElementById('backgroundColor').value;
    
    if (!qrType) {
        showPlaceholder();
        return;
    }
    
    let content = '';
    
    // Generate content based on QR type
    switch(qrType) {
        case 'static':
            const url = document.getElementById('url').value;
            content = url || 'https://example.com';
            break;
        case 'dynamic':
            const dynamicUrl = document.getElementById('url').value;
            content = dynamicUrl || 'https://example.com';
            break;
        case 'dynamic_voting':
            content = 'https://revenueqr.sharedvaluevending.com/vote.php?campaign_id=1';
            break;
        case 'dynamic_vending':
            const machineName = document.getElementById('machineName').value;
            content = `https://revenueqr.sharedvaluevending.com/public/promotions.php?machine=${encodeURIComponent(machineName || 'Sample Machine')}&view=vending`;
            break;
        case 'machine_sales':
            content = 'https://revenueqr.sharedvaluevending.com/public/promotions.php?machine=Sample Machine';
            break;
        case 'spin_wheel':
            content = 'https://revenueqr.sharedvaluevending.com/public/spin-wheel.php?wheel_id=1';
            break;
        case 'pizza_tracker':
            content = 'https://revenueqr.sharedvaluevending.com/public/pizza-tracker.php?tracker_id=1';
            break;
        default:
            content = 'https://example.com';
    }
    
    // Generate QR code
    const qrPreview = document.getElementById('qrPreview');
    const placeholder = document.getElementById('previewPlaceholder');
    
    QRCode.toCanvas(content, {
        width: Math.min(size, 300), // Limit preview size
        height: Math.min(size, 300),
        color: {
            dark: foregroundColor,
            light: backgroundColor
        },
        margin: 2,
        errorCorrectionLevel: 'H'
    }, function (error, canvas) {
        if (error) {
            console.error('QR generation error:', error);
            showPlaceholder();
            return;
        }
        
        // Clear previous content
        qrPreview.innerHTML = '';
        qrPreview.appendChild(canvas);
        
        // Show preview, hide placeholder
        qrPreview.style.display = 'block';
        placeholder.style.display = 'none';
    });
}

function showPlaceholder() {
    const qrPreview = document.getElementById('qrPreview');
    const placeholder = document.getElementById('previewPlaceholder');
    
    qrPreview.style.display = 'none';
    placeholder.style.display = 'block';
}

// Add event listeners for real-time preview updates
document.addEventListener('DOMContentLoaded', function() {
    // Color changes
    document.getElementById('foregroundColor').addEventListener('input', generatePreview);
    document.getElementById('backgroundColor').addEventListener('input', generatePreview);
    document.getElementById('foregroundHex').addEventListener('input', generatePreview);
    document.getElementById('backgroundHex').addEventListener('input', generatePreview);
    
    // URL changes
    document.getElementById('url').addEventListener('input', generatePreview);
    
    // Machine name changes
    const machineNameField = document.getElementById('machineName');
    if (machineNameField) {
        machineNameField.addEventListener('input', generatePreview);
    }
});

// MISSING FUNCTIONALITY: QR Code Generation and Download
function generateQRCode() {
    const form = document.getElementById('qrGeneratorForm');
    const formData = new FormData(form);
    
    // Validate required fields
    const qrType = document.getElementById('qrType').value;
    if (!qrType) {
        showToast('Please select a QR code type', 'danger');
        return;
    }
    
    // Type-specific validation
    if (qrType === 'static' || qrType === 'dynamic') {
        const url = document.getElementById('url').value;
        if (!url) {
            showToast('Please enter a URL', 'danger');
            return;
        }
    } else if (qrType === 'dynamic_voting') {
        const campaignId = document.getElementById('campaignId').value;
        if (!campaignId) {
            showToast('Please select a campaign', 'danger');
            return;
        }
    } else if (qrType === 'dynamic_vending') {
        const machineName = document.getElementById('machineName').value;
        if (!machineName) {
            showToast('Please enter a machine name', 'danger');
            return;
        }
    } else if (qrType === 'machine_sales') {
        const machineNameSales = document.getElementById('machineName').value;
        if (!machineNameSales) {
            showToast('Please enter a machine name', 'danger');
            return;
        }
    } else if (qrType === 'promotion') {
        const machineNamePromotion = document.getElementById('machineName').value;
        if (!machineNamePromotion) {
            showToast('Please enter a machine name', 'danger');
            return;
        }
    } else if (qrType === 'spin_wheel') {
        const spinWheelId = document.getElementById('spinWheelId').value;
        if (!spinWheelId) {
            showToast('Please select a spin wheel', 'danger');
            return;
        }
    } else if (qrType === 'pizza_tracker') {
        const pizzaTrackerId = document.getElementById('pizzaTrackerSelect').value;
        if (!pizzaTrackerId) {
            showToast('Please select a pizza tracker', 'danger');
            return;
        }
    }
    
    // Show loading state
    const generateBtn = document.querySelector('button[type="submit"]');
    const originalText = generateBtn.innerHTML;
    generateBtn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Generating...';
    generateBtn.disabled = true;
    
    // Build proper request data
    const requestData = {
        qr_type: qrType,
        size: parseInt(document.getElementById('sizeRange')?.value || 400),
        foreground_color: document.getElementById('foregroundColor')?.value || '#000000',
        background_color: document.getElementById('backgroundColor')?.value || '#FFFFFF',
        error_correction_level: 'H'
    };
    
    // Add type-specific data
    if (qrType === 'static' || qrType === 'dynamic') {
        requestData.content = document.getElementById('url').value;
    } else if (qrType === 'dynamic_voting') {
        requestData.campaign_id = document.getElementById('campaignId').value;
    } else if (qrType === 'dynamic_vending') {
        requestData.machine_name = document.getElementById('machineName').value;
    }
    
    // Submit to the API
    fetch('/api/qr/generate.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(requestData)
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        return response.json();
    })
    .then(result => {
        console.log('API Response:', result);
        if (result.success) {
            // Create download link
            const link = document.createElement('a');
            link.href = result.data.qr_code_url;
            link.download = `qr-code-${qrType}-${Date.now()}.png`;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            
            showToast('QR code generated and downloaded successfully!', 'success');
            
            // Show success modal
            const successModal = new bootstrap.Modal(document.getElementById('successModal'));
            successModal.show();
        } else {
            throw new Error(result.message || 'QR generation failed');
        }
    })
    .catch(error => {
        console.error('QR generation error:', error);
        showToast(`Error: ${error.message}`, 'danger');
    })
    .finally(() => {
        // Reset button
        generateBtn.innerHTML = originalText;
        generateBtn.disabled = false;
    });
}

// Preview-only generation (different from download generation)
function generatePreviewOnly() {
    const qrType = document.getElementById('qrType').value;
    const size = parseInt(document.getElementById('sizeRange').value);
    const foregroundColor = document.getElementById('foregroundColor').value;
    const backgroundColor = document.getElementById('backgroundColor').value;
    
    if (!qrType) {
        showPlaceholder();
        return;
    }
    
    let content = '';
    
    // Generate content based on QR type
    switch(qrType) {
        case 'static':
            const url = document.getElementById('url').value;
            content = url || 'https://example.com';
            break;
        case 'dynamic':
            const dynamicUrl = document.getElementById('url').value;
            content = dynamicUrl || 'https://example.com';
            break;
        case 'dynamic_voting':
            content = 'https://revenueqr.sharedvaluevending.com/vote.php?campaign_id=1';
            break;
        case 'dynamic_vending':
            const machineName = document.getElementById('machineName').value;
            content = `https://revenueqr.sharedvaluevending.com/public/promotions.php?machine=${encodeURIComponent(machineName || 'Sample Machine')}&view=vending`;
            break;
        case 'machine_sales':
            const machineNameSales = document.getElementById('machineName').value;
            content = `https://revenueqr.sharedvaluevending.com/public/promotions.php?machine=${encodeURIComponent(machineNameSales || 'Sample Machine')}`;
            break;
        case 'promotion':
            const machineNamePromotion = document.getElementById('machineName').value;
            content = `https://revenueqr.sharedvaluevending.com/public/promotions.php?machine=${encodeURIComponent(machineNamePromotion || 'Sample Machine')}&view=promotions`;
            break;
        case 'spin_wheel':
            content = 'https://revenueqr.sharedvaluevending.com/public/spin-wheel.php?wheel_id=1';
            break;
        case 'pizza_tracker':
            const pizzaTrackerId = document.getElementById('pizzaTrackerSelect').value;
            content = `https://revenueqr.sharedvaluevending.com/public/pizza-tracker.php?tracker_id=${pizzaTrackerId || '1'}`;
            break;
        default:
            content = 'https://example.com';
    }
    
    // Generate QR code
    const qrPreview = document.getElementById('qrPreview');
    const placeholder = document.getElementById('previewPlaceholder');
    
    QRCode.toCanvas(content, {
        width: Math.min(size, 300), // Limit preview size
        height: Math.min(size, 300),
        color: {
            dark: foregroundColor,
            light: backgroundColor
        },
        margin: 2,
        errorCorrectionLevel: 'H'
    }, function (error, canvas) {
        if (error) {
            console.error('QR generation error:', error);
            showPlaceholder();
            return;
        }
        
        // Clear previous content
        qrPreview.innerHTML = '';
        qrPreview.appendChild(canvas);
        
        // Show preview, hide placeholder
        qrPreview.style.display = 'block';
        placeholder.style.display = 'none';
    });
}

// Fixed Preview Generation Function
function generatePreview() {
    const qrType = document.getElementById("qrType").value;
    
    if (!qrType) {
        showPlaceholder();
        return;
    }
    
    let content = "";
    
    // Generate content based on QR type
    switch(qrType) {
        case "static":
        case "dynamic":
            const url = document.getElementById("url")?.value;
            content = url || "https://example.com";
            break;
        case "dynamic_voting":
            const campaignId = document.getElementById("campaignId")?.value;
            content = `https://revenueqr.sharedvaluevending.com/vote.php?campaign_id=${campaignId || "1"}`;
            break;
        case "dynamic_vending":
            const machineName = document.getElementById("machineName")?.value;
            content = `https://revenueqr.sharedvaluevending.com/public/promotions.php?machine=${encodeURIComponent(machineName || "Sample Machine")}&view=vending`;
            break;
        case "machine_sales":
            const machineNameSales = document.getElementById("machineName")?.value;
            content = `https://revenueqr.sharedvaluevending.com/public/promotions.php?machine=${encodeURIComponent(machineNameSales || "Sample Machine")}`;
            break;
        case "promotion":
            const machineNamePromotion = document.getElementById("machineName")?.value;
            content = `https://revenueqr.sharedvaluevending.com/public/promotions.php?machine=${encodeURIComponent(machineNamePromotion || "Sample Machine")}&view=promotions`;
            break;
        case "spin_wheel":
            const spinWheelId = document.getElementById("spinWheelId")?.value;
            content = `https://revenueqr.sharedvaluevending.com/public/spin-wheel.php?wheel_id=${spinWheelId || "1"}`;
            break;
        case "pizza_tracker":
            const pizzaTrackerIdAlt = document.getElementById("pizzaTrackerSelect")?.value;
            content = `https://revenueqr.sharedvaluevending.com/public/pizza-tracker.php?tracker_id=${pizzaTrackerIdAlt || "1"}`;
            break;
        default:
            content = "https://example.com";
    }
    
    const size = parseInt(document.getElementById("sizeRange")?.value || 300);
    const foregroundColor = document.getElementById("foregroundColor")?.value || "#000000";
    const backgroundColor = document.getElementById("backgroundColor")?.value || "#FFFFFF";
    
    // Generate QR code preview
    const qrPreview = document.getElementById("qrPreview");
    const placeholder = document.getElementById("previewPlaceholder");
    
    if (!qrPreview || !placeholder) {
        console.error("Preview elements not found");
        return;
    }
    
    // Use QRCode library to generate preview
    QRCode.toCanvas(content, {
        width: Math.min(size, 300),
        height: Math.min(size, 300),
        color: {
            dark: foregroundColor,
            light: backgroundColor
        },
        margin: 2,
        errorCorrectionLevel: "H"
    }, function (error, canvas) {
        if (error) {
            console.error("QR generation error:", error);
            showPlaceholder();
            return;
        }
        
        // Clear previous content and add new canvas
        qrPreview.innerHTML = "";
        qrPreview.appendChild(canvas);
        
        // Show preview, hide placeholder
        qrPreview.style.display = "block";
        placeholder.style.display = "none";
    });
}

function showPlaceholder() {
    const qrPreview = document.getElementById("qrPreview");
    const placeholder = document.getElementById("previewPlaceholder");
    
    if (qrPreview) qrPreview.style.display = "none";
    if (placeholder) placeholder.style.display = "block";
}

// Toast notification function
function showToast(message, type = "info") {
    console.log(`Toast: ${message} (${type})`);
    
    // Remove existing toasts
    const existingToasts = document.querySelectorAll(".toast-notification");
    existingToasts.forEach(toast => toast.remove());
    
    // Create toast element
    const toast = document.createElement("div");
    toast.className = `alert alert-${type} toast-notification`;
    toast.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
        max-width: 400px;
        animation: slideIn 0.3s ease-out;
    `;
    toast.innerHTML = `
        <div class="d-flex align-items-center">
            <i class="bi bi-${type === "success" ? "check-circle" : type === "danger" ? "exclamation-triangle" : "info-circle"} me-2"></i>
            <span>${message}</span>
            <button type="button" class="btn-close ms-auto" onclick="this.parentElement.parentElement.remove()"></button>
        </div>
    `;
    
    document.body.appendChild(toast);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        if (toast.parentNode) {
            toast.remove();
        }
    }, 5000);
}
</script>

<?php require_once __DIR__ . '/core/includes/footer.php'; ?> 