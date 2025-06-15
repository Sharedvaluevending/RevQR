<?php
require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/core/session.php';
require_once __DIR__ . '/core/auth.php';
require_once __DIR__ . '/core/business_utils.php';

// Require business role
require_role('business');

// Get business_id
try {
    $business_id = getOrCreateBusinessId($pdo, $_SESSION['user_id']);
} catch (Exception $e) {
    $business_id = null;
    error_log("Error getting business ID in Print Shop: " . $e->getMessage());
}

// Get QR codes for this business
$qr_codes = [];
if ($business_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                qr.id,
                qr.code,
                qr.qr_type,
                qr.machine_name,
                qr.machine_location,
                qr.url,
                qr.meta,
                qr.created_at,
                COUNT(qrs.id) as scan_count
            FROM qr_codes qr
            LEFT JOIN qr_code_stats qrs ON qr.id = qrs.qr_code_id
            WHERE qr.business_id = ? AND qr.status = 'active'
            GROUP BY qr.id
            ORDER BY qr.created_at DESC
        ");
        $stmt->execute([$business_id]);
        $qr_codes = $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Error fetching QR codes: " . $e->getMessage());
    }
}

$page_title = 'Print Shop - Professional QR Code Printing';
require_once __DIR__ . '/core/includes/header.php';
?>

<style>
/* Print Shop Styling - Match Business Dashboard */
.print-shop-container {
    min-height: 100vh;
    padding: 2rem 0;
}

.print-card {
    background: rgba(255, 255, 255, 0.12) !important;
    backdrop-filter: blur(20px) !important;
    border: 1px solid rgba(255, 255, 255, 0.15) !important;
    border-radius: 16px !important;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3) !important;
    transition: all 0.3s ease !important;
}

.print-card:hover {
    transform: translateY(-2px) !important;
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.4) !important;
    border: 1px solid rgba(255, 255, 255, 0.25) !important;
}

.print-header {
    background: rgba(255, 255, 255, 0.15);
    border-radius: 16px 16px 0 0;
    padding: 2rem;
    text-align: center;
    border: 1px solid rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(20px);
}

.print-header h1 {
    color: #ffffff !important;
    font-weight: 600;
}

.print-header p {
    color: rgba(255, 255, 255, 0.8) !important;
}

.template-card {
    background: rgba(255, 255, 255, 0.08) !important;
    border: 1px solid rgba(255, 255, 255, 0.15) !important;
    border-radius: 12px !important;
    transition: all 0.3s ease;
    cursor: pointer;
    height: 100%;
    backdrop-filter: blur(10px);
    color: rgba(255, 255, 255, 0.9) !important;
}

.template-card:hover {
    border-color: rgba(100, 181, 246, 0.6) !important;
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(100, 181, 246, 0.3) !important;
    background: rgba(255, 255, 255, 0.15) !important;
}

.template-card.selected {
    border-color: #64b5f6 !important;
    background: rgba(100, 181, 246, 0.2) !important;
    box-shadow: 0 8px 25px rgba(100, 181, 246, 0.4) !important;
}

.qr-item {
    background: rgba(255, 255, 255, 0.08) !important;
    border: 1px solid rgba(255, 255, 255, 0.15) !important;
    border-radius: 12px !important;
    padding: 1rem;
    transition: all 0.3s ease;
    cursor: pointer;
    backdrop-filter: blur(10px);
    color: rgba(255, 255, 255, 0.9) !important;
}

.qr-item:hover {
    box-shadow: 0 8px 25px rgba(255, 255, 255, 0.1) !important;
    background: rgba(255, 255, 255, 0.15) !important;
    border-color: rgba(255, 255, 255, 0.25) !important;
}

.qr-item.selected {
    border-color: #64b5f6 !important;
    background: rgba(100, 181, 246, 0.2) !important;
    box-shadow: 0 8px 25px rgba(100, 181, 246, 0.3) !important;
}

.print-preview {
    background: rgba(255, 255, 255, 0.05) !important;
    border: 1px solid rgba(255, 255, 255, 0.1) !important;
    border-radius: 12px !important;
    min-height: 400px;
    max-height: 600px;
    padding: 2rem;
    position: relative;
    overflow-y: auto;
    overflow-x: hidden;
    backdrop-filter: blur(10px);
}

.preview-page {
    background: white;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    margin: 0 auto 2rem;
    position: relative;
    transform: scale(0.4);
    transform-origin: top center;
    width: 8.5in;
    height: 11in;
}

.preview-page:last-child {
    margin-bottom: 0;
}

/* Form Controls Styling */
.form-select, .form-control {
    background: rgba(255, 255, 255, 0.1) !important;
    border: 1px solid rgba(255, 255, 255, 0.2) !important;
    color: rgba(255, 255, 255, 0.9) !important;
    border-radius: 8px !important;
}

.form-select:focus, .form-control:focus {
    background: rgba(255, 255, 255, 0.15) !important;
    border-color: #64b5f6 !important;
    box-shadow: 0 0 0 0.2rem rgba(100, 181, 246, 0.25) !important;
    color: white !important;
}

.form-select option {
    background: #2c3e50 !important;
    color: white !important;
}

.form-label {
    color: rgba(255, 255, 255, 0.9) !important;
    font-weight: 500;
}

.form-check-label {
    color: rgba(255, 255, 255, 0.9) !important;
}

.form-range {
    background: transparent;
}

.form-range::-webkit-slider-track {
    background: rgba(255, 255, 255, 0.2);
    border-radius: 5px;
}

.form-range::-webkit-slider-thumb {
    background: #64b5f6;
    border: none;
    border-radius: 50%;
}

.form-range::-moz-range-track {
    background: rgba(255, 255, 255, 0.2);
    border-radius: 5px;
}

.form-range::-moz-range-thumb {
    background: #64b5f6;
    border: none;
    border-radius: 50%;
}



.qr-label {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    border: 1px dashed #ccc;
    background: #f8f9fa;
}

.stats-card {
    background: rgba(255, 255, 255, 0.12) !important;
    backdrop-filter: blur(20px) !important;
    border: 1px solid rgba(255, 255, 255, 0.15) !important;
    border-radius: 16px !important;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3) !important;
    color: white;
    padding: 1.5rem;
    text-align: center;
    transition: all 0.3s ease;
}

.stats-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.4) !important;
}

.stats-card h3 {
    font-size: 2.5rem;
    font-weight: 700;
    color: #ffffff !important;
}

.stats-card small {
    color: rgba(255, 255, 255, 0.85) !important;
}

.batch-actions {
    background: rgba(40, 167, 69, 0.2) !important;
    backdrop-filter: blur(20px) !important;
    border: 1px solid rgba(40, 167, 69, 0.3) !important;
    border-radius: 16px !important;
    box-shadow: 0 8px 32px rgba(40, 167, 69, 0.2) !important;
    color: white;
    padding: 1.5rem;
    margin-bottom: 2rem;
}

@media print {
    body * {
        visibility: hidden;
    }
    .print-preview, .print-preview * {
        visibility: visible;
    }
    .print-preview {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        margin: 0;
        padding: 0;
        border: none;
        box-shadow: none;
    }
}
</style>

<div class="print-shop-container">
    <div class="container-fluid">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="print-card">
                    <div class="print-header">
                        <h1><i class="bi bi-printer me-3"></i>Professional Print Shop</h1>
                        <p class="mb-0">Create professional QR code sheets with advanced layout options</p>
                        <div class="mt-3">
                            <a href="qr_manager.php" class="btn btn-outline-light me-2">
                                <i class="bi bi-arrow-left me-1"></i>Back to QR Manager
                            </a>
                            <a href="qr-generator.php" class="btn btn-outline-light">
                                <i class="bi bi-plus-circle me-1"></i>Create New QR
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Left Panel - QR Selection & Options -->
            <div class="col-lg-4">
                <!-- QR Code Selection -->
                <div class="print-card mb-4">
                    <div class="card-header" style="background: rgba(255, 255, 255, 0.15); border-bottom: 1px solid rgba(255, 255, 255, 0.15); color: white;">
                        <h5 class="mb-0"><i class="bi bi-check2-square me-2"></i>Select QR Codes (<?php echo count($qr_codes); ?> available)</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <button type="button" class="btn btn-outline-primary btn-sm me-2" onclick="selectAll()">
                                <i class="bi bi-check-all"></i> Select All
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm me-2" onclick="selectNone()">
                                <i class="bi bi-x-square"></i> Clear All
                            </button>
                            <button type="button" class="btn btn-outline-info btn-sm" onclick="toggleSelectionMode()">
                                <i class="bi bi-cursor"></i> <span id="selectionModeText">Multi-Select</span>
                            </button>
                        </div>
                        
                        <div class="mb-2">
                            <small class="text-muted">
                                <i class="bi bi-info-circle me-1"></i>
                                <span id="selectionHint">Click checkboxes to select multiple QR codes</span>
                            </small>
                        </div>
                        
                        <div style="max-height: 400px; overflow-y: auto;">
                            <?php if (empty($qr_codes)): ?>
                                <div class="text-center py-4">
                                    <i class="bi bi-qr-code display-4 text-muted"></i>
                                    <p class="text-muted mt-2">No QR codes found. <a href="qr-generator.php">Create some first!</a></p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($qr_codes as $qr): ?>
                                    <div class="qr-item mb-2" data-qr-id="<?php echo $qr['id']; ?>" onclick="toggleQRSelection(this)">
                                        <div class="form-check">
                                            <input class="form-check-input qr-checkbox" type="checkbox" value="<?php echo $qr['id']; ?>" id="qr_<?php echo $qr['id']; ?>">
                                            <label class="form-check-label w-100" for="qr_<?php echo $qr['id']; ?>">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($qr['code']); ?></strong><br>
                                                        <small class="text-muted">
                                                            <?php echo ucfirst(str_replace('_', ' ', $qr['qr_type'])); ?>
                                                            <?php if ($qr['machine_name']): ?>
                                                                - <?php echo htmlspecialchars($qr['machine_name']); ?>
                                                            <?php endif; ?>
                                                        </small>
                                                    </div>
                                                    <span class="badge bg-info"><?php echo $qr['scan_count']; ?> scans</span>
                                                </div>
                                            </label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Template Selection -->
                <div class="print-card mb-4">
                    <div class="card-header" style="background: rgba(76, 175, 80, 0.2); border-bottom: 1px solid rgba(76, 175, 80, 0.3); color: white;">
                        <h5 class="mb-0"><i class="bi bi-grid-3x3 me-2"></i>Print Templates</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-2">
                            <div class="col-6">
                                <div class="template-card p-3 text-center" data-template="avery_5160" onclick="selectTemplate(this)">
                                    <i class="bi bi-grid-3x2 display-6 text-primary"></i>
                                    <h6 class="mt-2">Avery 5160</h6>
                                    <small>30 labels<br>2.625" x 1"</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="template-card p-3 text-center" data-template="avery_5163" onclick="selectTemplate(this)">
                                    <i class="bi bi-grid-3x3 display-6 text-success"></i>
                                    <h6 class="mt-2">Avery 5163</h6>
                                    <small>10 labels<br>4" x 2"</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="template-card p-3 text-center" data-template="avery_5164" onclick="selectTemplate(this)">
                                    <i class="bi bi-grid-3x2-gap display-6 text-warning"></i>
                                    <h6 class="mt-2">Avery 5164</h6>
                                    <small>6 labels<br>4" x 3.33"</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="template-card p-3 text-center" data-template="custom_sheet" onclick="selectTemplate(this)">
                                    <i class="bi bi-grid display-6 text-info"></i>
                                    <h6 class="mt-2">Full Sheet</h6>
                                    <small>Custom grid<br>Any size</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Advanced Options -->
                <div class="print-card">
                    <div class="card-header" style="background: rgba(255, 152, 0, 0.2); border-bottom: 1px solid rgba(255, 152, 0, 0.3); color: white;">
                        <h5 class="mb-0"><i class="bi bi-sliders me-2"></i>Advanced Options</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label"><i class="bi bi-arrows-angle-expand me-1"></i>QR Code Size</label>
                            <select class="form-select" id="qrSize" onchange="updatePreview()">
                                <option value="auto" selected>Auto Fit (Recommended)</option>
                                <option value="small">Small (50%)</option>
                                <option value="medium">Medium (70%)</option>
                                <option value="large">Large (85%)</option>
                                <option value="fill">Fill Label (90%)</option>
                            </select>
                            <small class="text-muted">Auto Fit ensures QR codes fit perfectly in each label</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label"><i class="bi bi-border-all me-1"></i>Label Padding</label>
                            <div class="row g-2">
                                <div class="col-12">
                                    <input type="range" class="form-range" id="labelPadding" min="2" max="15" value="8" onchange="updatePreview(); updatePaddingDisplay()">
                                    <div class="d-flex justify-content-between">
                                        <small>Tight</small>
                                        <small id="paddingDisplay">Medium (8px)</small>
                                        <small>Loose</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Fill Options -->
                        <div class="mb-3">
                            <label class="form-label"><i class="bi bi-grid-fill me-1"></i>Fill Options</label>
                            <div class="row g-2">
                                <div class="col-12">
                                    <button type="button" class="btn btn-outline-success btn-sm w-100 mb-2" onclick="fillPageWithSelected()" id="fillPageBtn" disabled>
                                        <i class="bi bi-grid-3x3-gap-fill me-1"></i>Fill All Labels with Selected QR
                                    </button>
                                    <small class="text-muted">Select one QR code to fill entire page with copies</small>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="includeText" checked onchange="updatePreview()">
                                <label class="form-check-label" for="includeText">
                                    <i class="bi bi-type me-1"></i>Include QR Code Names
                                </label>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="includeBorder" onchange="updatePreview()">
                                <label class="form-check-label" for="includeBorder">
                                    <i class="bi bi-border-style me-1"></i>Add Borders
                                </label>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="includeDate" onchange="updatePreview()">
                                <label class="form-check-label" for="includeDate">
                                    <i class="bi bi-calendar-date me-1"></i>Include Print Date
                                </label>
                            </div>
                        </div>

                        <div id="customGridOptions" style="display: none;">
                            <label class="form-label"><i class="bi bi-grid-3x3-gap me-1"></i>Custom Grid</label>
                            <div class="row g-2">
                                <div class="col-6">
                                    <label class="form-label">Columns</label>
                                    <input type="number" class="form-control" id="gridCols" min="1" max="10" value="3" onchange="updatePreview()">
                                </div>
                                <div class="col-6">
                                    <label class="form-label">Rows</label>
                                    <input type="number" class="form-control" id="gridRows" min="1" max="15" value="10" onchange="updatePreview()">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Panel - Preview & Actions -->
            <div class="col-lg-8">
                <!-- Stats -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stats-card">
                            <h3 id="selectedCount">0</h3>
                            <small>Selected QR Codes</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <h3 id="sheetsNeeded">0</h3>
                            <small>Sheets Needed</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <h3 id="totalLabels">0</h3>
                            <small>Total Labels</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <h3 id="estimatedTime">0</h3>
                            <small>Est. Print Time</small>
                        </div>
                    </div>
                </div>

                <!-- Batch Actions -->
                <div class="batch-actions">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h5><i class="bi bi-lightning me-2"></i>Batch Actions</h5>
                            <p class="mb-0">Print multiple sheets with professional layouts</p>
                        </div>
                        <div class="col-md-4 text-end">
                            <button type="button" class="btn btn-light btn-lg" onclick="printAll()" disabled id="printAllBtn">
                                <i class="bi bi-printer me-2"></i>Print All Sheets
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Print Preview -->
                <div class="print-card">
                    <div class="card-header" style="background: rgba(0, 188, 212, 0.2); border-bottom: 1px solid rgba(0, 188, 212, 0.3); color: white;">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="bi bi-eye me-2"></i>Print Preview</h5>
                            <div id="pageNavigation" style="display: none;">
                                <button type="button" class="btn btn-outline-light btn-sm me-2" onclick="previousPage()">
                                    <i class="bi bi-chevron-left"></i> Previous
                                </button>
                                <span id="pageInfo" class="text-light me-2">Page 1 of 1</span>
                                <button type="button" class="btn btn-outline-light btn-sm" onclick="nextPage()">
                                    Next <i class="bi bi-chevron-right"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="print-preview" id="printPreview">
                            <div class="text-center text-muted py-5">
                                <i class="bi bi-printer display-1"></i>
                                <h4>Select QR codes and template to see preview</h4>
                                <p>Choose your QR codes and printing options to generate a preview</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let selectedQRs = [];
let currentTemplate = 'avery_5160';
let qrCodeData = {}; // Cache for QR code data
let singleSelectMode = false; // Toggle between single and multi-select
let currentPage = 0; // Current page being viewed
let totalPages = 0; // Total number of pages
let templateConfigs = {
    'avery_5160': { cols: 3, rows: 10, labelWidth: 2.625, labelHeight: 1, labelsPerSheet: 30 },
    'avery_5163': { cols: 2, rows: 5, labelWidth: 4, labelHeight: 2, labelsPerSheet: 10 },
    'avery_5164': { cols: 2, rows: 3, labelWidth: 4, labelHeight: 3.33, labelsPerSheet: 6 },
    'custom_sheet': { cols: 3, rows: 10, labelWidth: 2.5, labelHeight: 1.5, labelsPerSheet: 30 }
};

function toggleQRSelection(element) {
    const checkbox = element.querySelector('.qr-checkbox');
    
    if (singleSelectMode) {
        // Single select mode - clear all others first
        document.querySelectorAll('.qr-checkbox').forEach(cb => {
            cb.checked = false;
            cb.closest('.qr-item').classList.remove('selected');
        });
        selectedQRs = [];
        
        // Select this one
        checkbox.checked = true;
        element.classList.add('selected');
        selectedQRs.push(checkbox.value);
    } else {
        // Multi-select mode
        checkbox.checked = !checkbox.checked;
        
        if (checkbox.checked) {
            element.classList.add('selected');
            selectedQRs.push(checkbox.value);
        } else {
            element.classList.remove('selected');
            selectedQRs = selectedQRs.filter(id => id !== checkbox.value);
        }
    }
    
    updateStats();
    updateFillPageButton();
    updatePreview();
}

function toggleSelectionMode() {
    singleSelectMode = !singleSelectMode;
    const modeText = document.getElementById('selectionModeText');
    const hint = document.getElementById('selectionHint');
    
    if (singleSelectMode) {
        modeText.textContent = 'Single-Select';
        hint.textContent = 'Click any QR code to select only that one';
        
        // Clear current selection if more than one selected
        if (selectedQRs.length > 1) {
            selectNone();
        }
    } else {
        modeText.textContent = 'Multi-Select';
        hint.textContent = 'Click checkboxes to select multiple QR codes';
    }
}

function selectAll() {
    document.querySelectorAll('.qr-checkbox').forEach(checkbox => {
        checkbox.checked = true;
        checkbox.closest('.qr-item').classList.add('selected');
        if (!selectedQRs.includes(checkbox.value)) {
            selectedQRs.push(checkbox.value);
        }
    });
    updateStats();
    updateFillPageButton();
    updatePreview();
}

function selectNone() {
    document.querySelectorAll('.qr-checkbox').forEach(checkbox => {
        checkbox.checked = false;
        checkbox.closest('.qr-item').classList.remove('selected');
    });
    selectedQRs = [];
    updateStats();
    updateFillPageButton();
    updatePreview();
}

function selectTemplate(element) {
    document.querySelectorAll('.template-card').forEach(card => card.classList.remove('selected'));
    element.classList.add('selected');
    currentTemplate = element.dataset.template;
    
    // Show/hide custom grid options
    const customOptions = document.getElementById('customGridOptions');
    if (currentTemplate === 'custom_sheet') {
        customOptions.style.display = 'block';
    } else {
        customOptions.style.display = 'none';
    }
    
    updateStats();
    updatePreview();
}

function updateStats() {
    const config = templateConfigs[currentTemplate];
    const selectedCount = selectedQRs.length;
    
    // Update custom grid if needed
    if (currentTemplate === 'custom_sheet') {
        const cols = parseInt(document.getElementById('gridCols').value);
        const rows = parseInt(document.getElementById('gridRows').value);
        config.cols = cols;
        config.rows = rows;
        config.labelsPerSheet = cols * rows;
    }
    
    const sheetsNeeded = Math.ceil(selectedCount / config.labelsPerSheet);
    const totalLabels = sheetsNeeded * config.labelsPerSheet;
    const estimatedTime = Math.ceil(sheetsNeeded * 0.5); // 30 seconds per sheet
    
    document.getElementById('selectedCount').textContent = selectedCount;
    document.getElementById('sheetsNeeded').textContent = sheetsNeeded;
    document.getElementById('totalLabels').textContent = totalLabels;
    document.getElementById('estimatedTime').textContent = estimatedTime + 'm';
    
    // Enable/disable print button
    const printBtn = document.getElementById('printAllBtn');
    if (selectedCount > 0) {
        printBtn.disabled = false;
        printBtn.classList.remove('btn-light');
        printBtn.classList.add('btn-success');
    } else {
        printBtn.disabled = true;
        printBtn.classList.remove('btn-success');
        printBtn.classList.add('btn-light');
    }
}

async function updatePreview() {
    if (selectedQRs.length === 0) {
        document.getElementById('printPreview').innerHTML = `
            <div class="text-center text-muted py-5">
                <i class="bi bi-printer display-1"></i>
                <h4>Select QR codes to see preview</h4>
                <p>Choose your QR codes and printing options to generate a preview</p>
            </div>
        `;
        document.getElementById('pageNavigation').style.display = 'none';
        return;
    }
    
    // Fetch QR code data if not cached
    await fetchQRCodeData();
    
    const config = templateConfigs[currentTemplate];
    
    // Update custom grid if needed
    if (currentTemplate === 'custom_sheet') {
        const cols = parseInt(document.getElementById('gridCols').value);
        const rows = parseInt(document.getElementById('gridRows').value);
        config.cols = cols;
        config.rows = rows;
        config.labelsPerSheet = cols * rows;
    }
    
    const qrSize = document.getElementById('qrSize').value;
    const labelPadding = document.getElementById('labelPadding').value;
    const includeText = document.getElementById('includeText').checked;
    const includeBorder = document.getElementById('includeBorder').checked;
    const includeDate = document.getElementById('includeDate').checked;
    
    totalPages = Math.ceil(selectedQRs.length / config.labelsPerSheet);
    
    // Show page navigation if more than one page
    const pageNav = document.getElementById('pageNavigation');
    if (totalPages > 1) {
        pageNav.style.display = 'block';
        updatePageInfo();
    } else {
        pageNav.style.display = 'none';
        currentPage = 0;
    }
    
    // Show only current page to prevent overflow
    const previewHTML = generateSheetPreview(currentPage, config, qrSize, labelPadding, includeText, includeBorder, includeDate);
    document.getElementById('printPreview').innerHTML = previewHTML;
    updateStats();
}

function generateSheetPreview(sheetIndex, config, qrSize, labelPadding, includeText, includeBorder, includeDate) {
    const startIndex = sheetIndex * config.labelsPerSheet;
    const endIndex = Math.min(startIndex + config.labelsPerSheet, selectedQRs.length);
    
    let sheetHTML = `
        <div class="preview-page" style="width: 8.5in; height: 11in; padding: 0.5in; page-break-after: always; margin-bottom: 2rem;">
            ${includeDate ? `<div style="text-align: center; margin-bottom: 10px; font-size: 10px; color: #666;">Sheet ${sheetIndex + 1} - Printed: ${new Date().toLocaleDateString()}</div>` : ''}
            <div style="display: grid; grid-template-columns: repeat(${config.cols}, 1fr); grid-template-rows: repeat(${config.rows}, 1fr); gap: 2px; height: calc(100% - ${includeDate ? '30px' : '10px'}); max-height: 10in;">
    `;
    
    // Only fill the actual labels needed, not all possible slots
    const labelsOnThisSheet = Math.min(config.labelsPerSheet, selectedQRs.length - startIndex);
    
    for (let i = 0; i < config.labelsPerSheet; i++) {
        const qrIndex = startIndex + i;
        const hasQR = qrIndex < selectedQRs.length;
        
        let labelContent = '';
        if (hasQR) {
            const qrId = selectedQRs[qrIndex];
            const qrCode = getQRCodeById(qrId);
            
            // Calculate QR size based on label dimensions and padding
            let qrSizePercent = '70%'; // Default auto fit
            if (qrSize === 'auto') {
                // Calculate optimal size based on label content
                const availableSpace = includeText ? '75%' : '85%';
                qrSizePercent = availableSpace;
            } else if (qrSize === 'small') qrSizePercent = '50%';
            else if (qrSize === 'medium') qrSizePercent = '70%';
            else if (qrSize === 'large') qrSizePercent = '85%';
            else if (qrSize === 'fill') qrSizePercent = '90%';
            
            labelContent = `
                <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; width: 100%;">
                    <img src="${qrCode.imagePath}" 
                         style="width: ${qrSizePercent}; height: auto; max-height: ${includeText ? '70%' : '90%'}; object-fit: contain;" 
                         alt="QR Code"
                         onerror="this.src='/uploads/qr/${qrCode.code}.png'; this.onerror=null;">
                    ${includeText ? `<div style="font-size: ${Math.max(6, Math.min(10, parseInt(labelPadding) - 2))}px; margin-top: 2px; word-break: break-all; text-align: center; flex-shrink: 0;">${qrCode.code}</div>` : ''}
                </div>
            `;
        }
        
        sheetHTML += `
            <div class="qr-label" style="
                padding: ${labelPadding}px;
                ${includeBorder ? 'border: 1px solid #000;' : 'border: 1px dashed #ccc;'}
                ${hasQR ? 'background: white;' : 'background: #f8f9fa;'}
                box-sizing: border-box;
                min-height: ${config.labelHeight * 72}px;
                position: relative;
            ">
                ${labelContent}
            </div>
        `;
    }
    
    sheetHTML += `
            </div>
        </div>
    `;
    
    return sheetHTML;
}

async function fetchQRCodeData() {
    if (selectedQRs.length === 0) return;
    
    // Check if we need to fetch data
    const needsFetch = selectedQRs.some(id => !qrCodeData[id]);
    if (!needsFetch) return;
    
    try {
        const response = await fetch(`/api/qr/get-qr-data.php?ids=${selectedQRs.join(',')}`);
        const result = await response.json();
        
        if (result.success) {
            result.data.forEach(qr => {
                qrCodeData[qr.id] = qr;
            });
        }
    } catch (error) {
        console.error('Error fetching QR code data:', error);
    }
}

function getQRCodeById(id) {
    // Use cached data if available
    if (qrCodeData[id]) {
        return {
            id: id,
            code: qrCodeData[id].code,
            imagePath: qrCodeData[id].image_path
        };
    }
    
    // Fallback to DOM data
    const qrItems = document.querySelectorAll('.qr-item');
    for (let item of qrItems) {
        if (item.dataset.qrId === id) {
            const label = item.querySelector('label strong');
            const code = label.textContent;
            return {
                id: id,
                code: code,
                imagePath: `/uploads/qr/${code}.png`
            };
        }
    }
    return { 
        id: id, 
        code: `QR-${id}`,
        imagePath: `/uploads/qr/QR-${id}.png`
    };
}

function updatePageInfo() {
    document.getElementById('pageInfo').textContent = `Page ${currentPage + 1} of ${totalPages}`;
}

function previousPage() {
    if (currentPage > 0) {
        currentPage--;
        updatePreview();
    }
}

function nextPage() {
    if (currentPage < totalPages - 1) {
        currentPage++;
        updatePreview();
    }
}

function printAll() {
    if (selectedQRs.length === 0) {
        alert('Please select at least one QR code to print.');
        return;
    }
    
    // Generate all pages for printing
    const config = templateConfigs[currentTemplate];
    
    // Update custom grid if needed
    if (currentTemplate === 'custom_sheet') {
        const cols = parseInt(document.getElementById('gridCols').value);
        const rows = parseInt(document.getElementById('gridRows').value);
        config.cols = cols;
        config.rows = rows;
        config.labelsPerSheet = cols * rows;
    }
    
    const qrSize = document.getElementById('qrSize').value;
    const labelPadding = document.getElementById('labelPadding').value;
    const includeText = document.getElementById('includeText').checked;
    const includeBorder = document.getElementById('includeBorder').checked;
    const includeDate = document.getElementById('includeDate').checked;
    
    const sheetsNeeded = Math.ceil(selectedQRs.length / config.labelsPerSheet);
    let allPagesHTML = '';
    
    for (let sheet = 0; sheet < sheetsNeeded; sheet++) {
        allPagesHTML += generateSheetPreview(sheet, config, qrSize, labelPadding, includeText, includeBorder, includeDate);
    }
    
    // Generate print-optimized version
    const printWindow = window.open('', '_blank');
    const previewContent = allPagesHTML;
    
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>QR Code Print Sheet</title>
            <style>
                @page {
                    size: letter;
                    margin: 0.5in;
                }
                body {
                    margin: 0;
                    padding: 0;
                    font-family: Arial, sans-serif;
                }
                .preview-page {
                    width: 100%;
                    height: 100vh;
                    page-break-after: always;
                    display: flex;
                    flex-direction: column;
                }
                .preview-page:last-child {
                    page-break-after: avoid;
                }
                .qr-label {
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    justify-content: center;
                    text-align: center;
                }
                img {
                    max-width: 100%;
                    max-height: 100%;
                }
                @media print {
                    body { -webkit-print-color-adjust: exact; }
                }
            </style>
        </head>
        <body>
            ${previewContent}
        </body>
        </html>
    `);
    
    printWindow.document.close();
    printWindow.focus();
    
    setTimeout(() => {
        printWindow.print();
    }, 500);
}

// New helper functions
function updatePaddingDisplay() {
    const padding = document.getElementById('labelPadding').value;
    const display = document.getElementById('paddingDisplay');
    
    let label = 'Medium';
    if (padding <= 4) label = 'Tight';
    else if (padding <= 6) label = 'Snug';
    else if (padding <= 10) label = 'Medium';
    else if (padding <= 12) label = 'Loose';
    else label = 'Very Loose';
    
    display.textContent = `${label} (${padding}px)`;
}

function updateFillPageButton() {
    const fillBtn = document.getElementById('fillPageBtn');
    if (selectedQRs.length === 1) {
        fillBtn.disabled = false;
        fillBtn.classList.remove('btn-outline-success');
        fillBtn.classList.add('btn-success');
        fillBtn.innerHTML = '<i class="bi bi-grid-3x3-gap-fill me-1"></i>Fill All Labels with This QR';
    } else {
        fillBtn.disabled = true;
        fillBtn.classList.remove('btn-success');
        fillBtn.classList.add('btn-outline-success');
        fillBtn.innerHTML = '<i class="bi bi-grid-3x3-gap-fill me-1"></i>Fill All Labels with Selected QR';
    }
}

function fillPageWithSelected() {
    if (selectedQRs.length !== 1) {
        alert('Please select exactly one QR code to fill the page.');
        return;
    }
    
    const config = templateConfigs[currentTemplate];
    
    // Update custom grid if needed
    if (currentTemplate === 'custom_sheet') {
        const cols = parseInt(document.getElementById('gridCols').value);
        const rows = parseInt(document.getElementById('gridRows').value);
        config.cols = cols;
        config.rows = rows;
        config.labelsPerSheet = cols * rows;
    }
    
    const selectedQRId = selectedQRs[0];
    const labelsPerSheet = config.labelsPerSheet;
    
    // Fill selectedQRs array with copies of the selected QR
    selectedQRs = Array(labelsPerSheet).fill(selectedQRId);
    
    // Update the UI to reflect the change
    updateStats();
    updatePreview();
    
    // Show success message
    const qrCode = getQRCodeById(selectedQRId);
    alert(`Page filled with ${labelsPerSheet} copies of "${qrCode.code}"`);
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    // Select first template by default
    const firstTemplate = document.querySelector('.template-card');
    if (firstTemplate) {
        selectTemplate(firstTemplate);
    }
    
    updateStats();
    updatePaddingDisplay();
    updateFillPageButton();
});
</script>

<?php require_once __DIR__ . '/core/includes/footer.php'; ?> 