<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/business_utils.php';

// Check if user is logged in
if (!is_logged_in()) {
    header('Location: ../login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

// Check if user has business role or is admin
$user_role = $_SESSION['role'] ?? 'user';
if (!in_array($user_role, ['business', 'admin'])) {
    header('Location: ../login.php?error=insufficient_permissions');
    exit;
}

// Get business ID
try {
    $business_id = getOrCreateBusinessId($pdo, $_SESSION['user_id']);
    if (!$business_id) {
        throw new Exception('Unable to determine business ID');
    }
} catch (Exception $e) {
    error_log("Print Manager - Business ID Error: " . $e->getMessage());
    header('Location: ../dashboard.php?error=business_setup_required');
    exit;
}

// Print templates configuration
$print_templates = [
    'avery_5160' => [
        'name' => 'Avery 5160 - Address Labels',
        'description' => '2⅝" × 1" labels, 30 per sheet',
        'dimensions' => ['width' => '2.625in', 'height' => '1in'],
        'per_sheet' => 30,
        'layout' => ['cols' => 3, 'rows' => 10]
    ],
    'avery_5658' => [
        'name' => 'Avery 5658 - Square Labels',
        'description' => '2" × 2" square labels, 10 per sheet',
        'dimensions' => ['width' => '2in', 'height' => '2in'],
        'per_sheet' => 10,
        'layout' => ['cols' => 2, 'rows' => 5]
    ],
    'avery_5908' => [
        'name' => 'Avery 5908 - Round Labels',
        'description' => '2" diameter round labels, 10 per sheet',
        'dimensions' => ['width' => '2in', 'height' => '2in'],
        'per_sheet' => 10,
        'layout' => ['cols' => 2, 'rows' => 5]
    ],
    'avery_94102' => [
        'name' => 'Avery 94102 - Square Labels',
        'description' => '2" × 2" specialty labels, 8 per sheet',
        'dimensions' => ['width' => '2in', 'height' => '2in'],
        'per_sheet' => 8,
        'layout' => ['cols' => 2, 'rows' => 4]
    ],
    'business_card' => [
        'name' => 'Business Card Format',
        'description' => '3.5" × 2" cards, 10 per sheet',
        'dimensions' => ['width' => '3.5in', 'height' => '2in'],
        'per_sheet' => 10,
        'layout' => ['cols' => 2, 'rows' => 5]
    ],
    'full_page' => [
        'name' => 'Full Page QR Codes',
        'description' => 'Large QR codes for posters/displays',
        'dimensions' => ['width' => '7.5in', 'height' => '10in'],
        'per_sheet' => 1,
        'layout' => ['cols' => 1, 'rows' => 1]
    ]
];

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'get_qr_codes':
            try {
                $stmt = $pdo->prepare("
                    SELECT 
                        qr.*,
                        COALESCE(qr.machine_name, CONCAT('QR-', SUBSTRING(qr.code, -8))) as display_name
                    FROM qr_codes qr
                    WHERE qr.business_id = ? AND qr.status = 'active'
                    ORDER BY qr.created_at DESC
                ");
                $stmt->execute([$business_id]);
                $qr_codes = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Process QR codes to add proper image URLs
                foreach ($qr_codes as &$qr) {
                    $meta = $qr['meta'] ? json_decode($qr['meta'], true) : [];
                    $file_path = $meta['file_path'] ?? '';
                    
                    // Try multiple possible QR code locations
                    $possible_paths = [
                        // From meta field
                        $file_path,
                        // Standard upload paths
                        '/var/www/html/uploads/qr/' . $qr['code'] . '.png',
                        '/var/www/html/uploads/qr/1/' . $qr['code'] . '.png',
                        '/var/www/html/uploads/qr/business/' . $qr['code'] . '.png',
                        // Legacy paths
                        '/var/www/html/assets/img/qr/' . $qr['code'] . '.png',
                        '/var/www/html/qr/' . $qr['code'] . '.png'
                    ];
                    
                    $qr_image_url = null;
                    foreach ($possible_paths as $path) {
                        if ($path && file_exists($path)) {
                            // Convert absolute path to relative URL
                            $qr_image_url = str_replace('/var/www/html', '', $path);
                            if (!str_starts_with($qr_image_url, '/')) {
                                $qr_image_url = '/' . ltrim($qr_image_url, '/');
                            }
                            break;
                        }
                    }
                    
                    // If no file found, generate a placeholder or regenerate QR code
                    if (!$qr_image_url) {
                        // Try to regenerate the QR code
                        $qr_dir = '/var/www/html/uploads/qr/';
                        if (!file_exists($qr_dir)) {
                            mkdir($qr_dir, 0755, true);
                        }
                        
                        $qr_file = $qr_dir . $qr['code'] . '.png';
                        
                        // Generate QR code if URL exists
                        if (!empty($qr['url'])) {
                            try {
                                require_once __DIR__ . '/../vendor/phpqrcode/qrlib.php';
                                QRcode::png($qr['url'], $qr_file, QR_ECLEVEL_M, 8, 2);
                                $qr_image_url = '/uploads/qr/' . $qr['code'] . '.png';
                            } catch (Exception $e) {
                                error_log("Failed to regenerate QR code for ID {$qr['id']}: " . $e->getMessage());
                                $qr_image_url = '/assets/img/placeholder-qr.png'; // Placeholder
                            }
                        } else {
                            $qr_image_url = '/assets/img/placeholder-qr.png'; // Placeholder
                        }
                    }
                    
                    $qr['qr_url'] = $qr_image_url;
                    
                    // Ensure display name is not empty
                    if (empty($qr['display_name']) || $qr['display_name'] === 'QR Code') {
                        $qr['display_name'] = 'QR-' . substr($qr['code'], -8);
                    }
                }
                
                echo json_encode(['success' => true, 'qr_codes' => $qr_codes]);
            } catch (Exception $e) {
                error_log("Print Manager - Get QR Codes Error: " . $e->getMessage());
                echo json_encode(['success' => false, 'error' => 'Failed to load QR codes: ' . $e->getMessage()]);
            }
            exit;
            
        case 'preview_template':
            $template = $_POST['template'] ?? 'avery_5658';
            $selected_ids = json_decode($_POST['selected_ids'] ?? '[]', true);
            
            if (empty($selected_ids)) {
                echo json_encode(['success' => false, 'error' => 'No QR codes selected']);
                exit;
            }
            
            try {
                $placeholders = str_repeat('?,', count($selected_ids) - 1) . '?';
                $stmt = $pdo->prepare("
                    SELECT 
                        qr.*,
                        COALESCE(qr.machine_name, 'QR Code') as display_name
                    FROM qr_codes qr
                    WHERE qr.id IN ($placeholders) AND qr.business_id = ?
                    ORDER BY qr.created_at DESC
                ");
                $params = array_merge($selected_ids, [$business_id]);
                $stmt->execute($params);
                $qr_codes = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Process QR codes to add proper image URLs
                foreach ($qr_codes as &$qr) {
                    $meta = $qr['meta'] ? json_decode($qr['meta'], true) : [];
                    $file_path = $meta['file_path'] ?? '';
                    
                    // Convert absolute path to relative URL
                    if ($file_path && strpos($file_path, '/uploads/') !== false) {
                        $qr_image_url = str_replace('/var/www/html', '', $file_path);
                        if (!str_starts_with($qr_image_url, '/')) {
                            $qr_image_url = '/' . ltrim($qr_image_url, '/');
                        }
                    } else {
                        $qr_image_url = '/uploads/qr/' . $qr['code'] . '.png';
                    }
                    
                    $qr['qr_url'] = $qr_image_url;
                    
                    // Ensure display name is not empty
                    if (empty($qr['display_name']) || $qr['display_name'] === 'QR Code') {
                        $qr['display_name'] = 'QR-' . substr($qr['code'], -8);
                    }
                }
                
                $template_config = $print_templates[$template] ?? $print_templates['avery_5658'];
                
                echo json_encode([
                    'success' => true, 
                    'qr_codes' => $qr_codes,
                    'template' => $template_config
                ]);
            } catch (Exception $e) {
                error_log("Print Manager - Preview Template Error: " . $e->getMessage());
                echo json_encode(['success' => false, 'error' => 'Failed to generate preview']);
            }
            exit;
    }
}

require_once __DIR__ . '/../core/includes/header.php';

// Debug information (remove in production)
if (isset($_GET['debug'])) {
    echo '<div class="alert alert-info">';
    echo '<strong>Debug Information:</strong><br>';
    echo 'User ID: ' . ($_SESSION['user_id'] ?? 'Not set') . '<br>';
    echo 'User Role: ' . ($_SESSION['role'] ?? 'Not set') . '<br>';
    echo 'Business ID: ' . ($business_id ?? 'Not set') . '<br>';
    echo 'Session Status: ' . (session_status() === PHP_SESSION_ACTIVE ? 'Active' : 'Inactive') . '<br>';
    echo '</div>';
}
?>

<style>
/* Print Preview Styles */
.print-preview-container {
    background: rgba(255, 255, 255, 0.03);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 8px;
    padding: 20px;
    margin: 20px 0;
    min-height: 400px;
    position: relative;
}

.print-preview-container .text-center {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 100%;
}

.print-preview-container:not(:empty) .text-center {
    position: static;
    transform: none;
}

.print-template-preview {
    background: rgba(255, 255, 255, 0.05);
    backdrop-filter: blur(10px);
    box-shadow: 0 4px 6px rgba(0,0,0,0.3);
    margin: 0 auto 20px auto;
    position: relative;
    overflow: hidden;
    border: 2px solid rgba(255, 255, 255, 0.2);
    z-index: 1;
}

.label-grid {
    display: grid;
    width: 100%;
    height: 100%;
    gap: 2px;
    padding: 10px;
}

.label-item {
    border: 1px dashed rgba(255, 255, 255, 0.3);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 5px;
    font-size: 10px;
    text-align: center;
    background: rgba(255, 255, 255, 0.1);
    position: relative;
}

.label-qr-code {
    max-width: 80%;
    max-height: 60%;
    object-fit: contain;
}

.label-text {
    margin-top: 5px;
    font-weight: 600;
    color: #fff;
    word-break: break-word;
}

.template-selector {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 20px;
}

.template-card {
    cursor: pointer;
    transition: all 0.3s ease;
    border: 2px solid transparent;
    height: 120px;
}

.template-card:hover {
    border-color: #007bff;
    box-shadow: 0 4px 8px rgba(0,123,255,0.2);
    transform: translateY(-2px);
}

.template-card.selected {
    border-color: #28a745 !important;
    background-color: #f8fff9;
    box-shadow: 0 4px 12px rgba(40,167,69,0.3);
    transform: translateY(-1px);
}

.template-card.selected .card-title {
    color: #28a745;
    font-weight: bold;
}

.template-card.selected::before {
    content: "✓ SELECTED";
    position: absolute;
    top: -8px;
    right: -8px;
    background: #28a745;
    color: white;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 0.6rem;
    font-weight: bold;
    z-index: 10;
}

.qr-selector {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 20px;
    height: fit-content;
}

.qr-item {
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    transition: all 0.3s ease;
    cursor: pointer;
}

.qr-item:hover {
    background: rgba(255, 255, 255, 0.2);
    border-color: rgba(255, 255, 255, 0.4);
}

.qr-item.selected {
    background: rgba(74, 144, 226, 0.4);
    border-color: #4a90e2;
    box-shadow: 0 0 10px rgba(74, 144, 226, 0.3);
}

.qr-item input[type="checkbox"] {
    cursor: pointer;
    transform: scale(1.2);
}

.qr-mini-preview {
    width: 60px;
    height: 60px;
    object-fit: contain;
    border: 1px solid #ddd;
    border-radius: 4px;
    margin-right: 15px;
    cursor: pointer;
}

/* Print Styles */
@media print {
    body * { visibility: hidden; }
    .print-content, .print-content * { visibility: visible; }
    .print-content {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
    }
    .no-print { display: none !important; }
    
    /* Avery 5160 - Address Labels */
    .template-avery_5160 {
        width: 8.5in;
        height: 11in;
    }
    .template-avery_5160 .label-grid {
        grid-template-columns: repeat(3, 2.625in);
        grid-template-rows: repeat(10, 1in);
        gap: 0;
    }
    
    /* Avery 5658 - Square Labels */
    .template-avery_5658 {
        width: 8.5in;
        height: 11in;
    }
    .template-avery_5658 .label-grid {
        grid-template-columns: repeat(2, 2in);
        grid-template-rows: repeat(5, 2in);
        gap: 0.5in;
        justify-content: center;
    }
    
    /* Business Cards */
    .template-business_card {
        width: 8.5in;
        height: 11in;
    }
    .template-business_card .label-grid {
        grid-template-columns: repeat(2, 3.5in);
        grid-template-rows: repeat(5, 2in);
        gap: 0.25in;
        justify-content: center;
    }
    
    /* Full Page */
    .template-full_page {
        width: 8.5in;
        height: 11in;
    }
    .template-full_page .label-grid {
        grid-template-columns: 1fr;
        grid-template-rows: 1fr;
        gap: 0;
        padding: 0.5in;
    }
    
    .label-item {
        border: none;
        padding: 2px;
    }
    
    .print-page-break {
        page-break-before: always;
    }
    
    .print-template-preview { 
        page-break-after: always; 
        border: none !important;
        box-shadow: none !important;
    }
}

.btn-print {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    color: white;
    font-weight: 600;
}

.btn-print:hover {
    background: linear-gradient(135deg, #5a67d8 0%, #6b46c1 100%);
    color: white;
}

.form-check-input:checked {
    background-color: #28a745;
    border-color: #28a745;
}

.preview-section {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 20px;
    height: fit-content;
}
</style>

<div class="container-fluid mt-5 pt-4">
    <div class="row mb-4 no-print">
        <div class="col-12">
            <h1 class="h3 mb-1">Print Manager</h1>
            <p class="text-muted">Design and print professional QR code labels for your business</p>
        </div>
    </div>

    <!-- Template Selector -->
    <div class="template-selector no-print">
        <h4 class="mb-3">1. Choose Print Template</h4>
        <div class="row">
            <?php foreach ($print_templates as $key => $template): ?>
            <div class="col-md-2 mb-3">
                <div class="card template-card" data-template="<?php echo $key; ?>">
                    <div class="card-body p-2">
                        <h6 class="card-title mb-1" style="font-size: 0.85rem;"><?php echo $template['name']; ?></h6>
                        <p class="card-text small mb-1" style="font-size: 0.7rem;"><?php echo $template['description']; ?></p>
                        <small class="text-muted" style="font-size: 0.65rem;">
                            <?php echo $template['per_sheet']; ?> per sheet 
                            (<?php echo $template['layout']['cols']; ?>×<?php echo $template['layout']['rows']; ?>)
                        </small>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- QR Code Selector and Preview Side by Side -->
    <div class="row no-print">
        <!-- QR Code Selection Column -->
        <div class="col-md-6">
            <div class="qr-selector">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4 class="mb-0">2. Select QR Codes to Print</h4>
                    <button type="button" class="btn btn-outline-success btn-sm" onclick="refreshQRCodes()">
                        <i class="bi bi-arrow-clockwise me-1"></i>Refresh
                    </button>
                </div>
                <div class="mb-3">
                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="selectAll()">Select All</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="selectNone()">Select None</button>
                    <button type="button" class="btn btn-outline-warning btn-sm" onclick="fillAllLabels()" title="Duplicate selected QR codes to fill entire template">
                        <i class="bi bi-grid-fill me-1"></i>Fill All Labels
                    </button>
                    <span class="ms-3 text-muted">Selected: <span id="selected-count">0</span></span>
                </div>
                
                <!-- Enhanced Print Options -->
                <div class="row mb-3">
                    <div class="col-md-6">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="fitToCutOut" onchange="toggleFitToCutOut()">
                            <label class="form-check-label" for="fitToCutOut">
                                <strong>Fit to Cut Out</strong>
                                <small class="text-muted d-block">QR codes fill 100% of label area (no text)</small>
                            </label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="d-flex align-items-center">
                            <span class="badge bg-info me-2" id="template-info">
                                <i class="bi bi-info-circle me-1"></i>
                                Select template for details
                            </span>
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="showTemplateHelp()" title="Template measurement guide">
                                <i class="bi bi-question-circle"></i>
                            </button>
                        </div>
                    </div>
                </div>
                
                <div id="qr-codes-list" style="max-height: 500px; overflow-y: auto;">
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading QR codes...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Preview Column -->
        <div class="col-md-6">
            <div class="preview-section">
                <h4 class="mb-3">3. Preview & Print</h4>
                <div class="mb-3">
                    <div class="row align-items-center">
                        <div class="col-12 mb-2">
                            <button type="button" class="btn btn-primary btn-print me-2" onclick="updatePreview()">
                                <i class="bi bi-eye me-2"></i>Update Preview
                            </button>
                            <button type="button" class="btn btn-success btn-print me-2" onclick="printLabels()">
                                <i class="bi bi-printer me-2"></i>Print Labels
                            </button>
                            <button type="button" class="btn btn-info me-2" onclick="generatePDF()">
                                <i class="bi bi-file-pdf me-2"></i>Generate PDF
                            </button>
                            <button type="button" class="btn btn-warning btn-sm me-2" onclick="testPreview()">
                                <i class="bi bi-bug me-1"></i>Test Preview
                            </button>
                        </div>
                        <div class="col-12">
                            <div class="form-check form-switch d-inline-block me-3">
                                <input class="form-check-input" type="checkbox" id="fitToCutOut" onchange="toggleFitToCutOut()">
                                <label class="form-check-label" for="fitToCutOut">
                                    <strong>Fit to Cut Out</strong> <small class="text-muted">(Fill entire label)</small>
                                </label>
                            </div>
                            <a href="template-designer.php" class="btn btn-outline-info btn-sm">
                                <i class="bi bi-palette me-1"></i>Custom Templates
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Preview Container -->
                <div class="print-preview-container" id="preview-container" style="max-height: 500px; overflow-y: auto;">
                    <div class="text-center text-muted py-5">
                        <i class="bi bi-printer" style="font-size: 3rem; opacity: 0.3;"></i>
                        <p class="mt-3">Select a template and QR codes to see preview</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Print Content (Hidden) -->
    <div class="print-content" id="print-content" style="display: none;">
        <!-- Print content will be generated here -->
    </div>
</div>

<script>
let qrCodes = [];
let selectedTemplate = 'avery_5658';
let selectedQRCodes = [];
let fitToCutOut = false;

// Load QR codes on page load
document.addEventListener('DOMContentLoaded', function() {
    console.log('Print Manager loaded');
    loadQRCodes();
    
    // Template selection handler
    document.querySelectorAll('.template-card').forEach(card => {
        card.addEventListener('click', function() {
            console.log('Template card clicked:', this.dataset.template);
            
            // Remove previous selection
            document.querySelectorAll('.template-card').forEach(c => {
                c.classList.remove('selected');
            });
            
            // Add selection to clicked card
            this.classList.add('selected');
            
            // Update selected template
            selectedTemplate = this.dataset.template;
            console.log('Selected template changed to:', selectedTemplate);
            
            // Update template info badge
            updateTemplateInfo();
            
            // Auto-update preview if QR codes are selected
            if (selectedQRCodes.length > 0) {
                console.log('Auto-updating preview with new template');
                updatePreview();
            }
        });
    });
    
    // Set default template with visual feedback
    const defaultTemplate = document.querySelector('[data-template="avery_5658"]');
    if (defaultTemplate) {
        defaultTemplate.classList.add('selected');
        console.log('Default template set to: avery_5658');
    }
});

function loadQRCodes() {
    console.log('Loading QR codes...');
    fetch('print-manager.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=get_qr_codes'
    })
    .then(response => response.json())
    .then(data => {
        console.log('QR codes response:', data);
        if (data.success) {
            qrCodes = data.qr_codes;
            renderQRCodesList();
            console.log('Loaded', qrCodes.length, 'QR codes');
        } else {
            document.getElementById('qr-codes-list').innerHTML = 
                '<div class="alert alert-danger">Error loading QR codes: ' + data.error + '</div>';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        document.getElementById('qr-codes-list').innerHTML = 
            '<div class="alert alert-danger">Error loading QR codes</div>';
    });
}

function refreshQRCodes() {
    console.log('Refreshing QR codes...');
    document.getElementById('qr-codes-list').innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Refreshing QR codes...</span>
            </div>
            <p class="mt-2">Refreshing QR codes from all generators...</p>
        </div>
    `;
    
    // Clear current selection
    selectedQRCodes = [];
    updateSelectedCount();
    
    // Reload QR codes
    loadQRCodes();
}

function renderQRCodesList() {
    const container = document.getElementById('qr-codes-list');
    if (qrCodes.length === 0) {
        container.innerHTML = '<div class="alert alert-info">No QR codes found. <a href="../qr-generator.php">Create some QR codes first</a>.</div>';
        return;
    }
    
    let html = '';
    qrCodes.forEach(qr => {
        html += `
            <div class="qr-item" data-id="${qr.id}">
                <input type="checkbox" class="form-check-input me-3" id="qr-${qr.id}" onchange="toggleQRSelection(${qr.id})">
                <img src="${qr.qr_url}" alt="QR Code" class="qr-mini-preview" onclick="toggleQRSelection(${qr.id})">
                <div class="flex-grow-1" onclick="toggleQRSelection(${qr.id})">
                    <h6 class="mb-1">${qr.display_name}</h6>
                    <small class="text-muted">
                        Type: ${qr.qr_type.replace('_', ' ')} | 
                        Created: ${new Date(qr.created_at).toLocaleDateString()}
                    </small>
                </div>
            </div>
        `;
    });
    container.innerHTML = html;
    
    console.log('Rendered', qrCodes.length, 'QR codes');
}

function toggleQRSelection(qrId) {
    console.log('toggleQRSelection called with ID:', qrId);
    
    const item = document.querySelector(`[data-id="${qrId}"]`);
    const checkbox = document.getElementById(`qr-${qrId}`);
    
    if (!item || !checkbox) {
        console.error('Could not find item or checkbox for ID:', qrId);
        return;
    }
    
    if (selectedQRCodes.includes(qrId)) {
        selectedQRCodes = selectedQRCodes.filter(id => id !== qrId);
        item.classList.remove('selected');
        checkbox.checked = false;
        console.log('Deselected QR code:', qrId);
    } else {
        selectedQRCodes.push(qrId);
        item.classList.add('selected');
        checkbox.checked = true;
        console.log('Selected QR code:', qrId);
    }
    
    updateSelectedCount();
    console.log('Currently selected QR codes:', selectedQRCodes);
}

function selectAll() {
    console.log('selectAll called');
    selectedQRCodes = qrCodes.map(qr => qr.id);
    document.querySelectorAll('.qr-item').forEach(item => item.classList.add('selected'));
    document.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.checked = true);
    updateSelectedCount();
    console.log('Selected all QR codes:', selectedQRCodes);
}

function selectNone() {
    console.log('selectNone called');
    selectedQRCodes = [];
    document.querySelectorAll('.qr-item').forEach(item => item.classList.remove('selected'));
    document.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.checked = false);
    updateSelectedCount();
    console.log('Deselected all QR codes');
}

function fillAllLabels() {
    console.log('fillAllLabels called');
    
    if (selectedQRCodes.length === 0) {
        alert('Please select one QR code first to fill all labels');
        return;
    }
    
    // Get current template configuration
    const template = <?php echo json_encode($print_templates); ?>[selectedTemplate];
    if (!template) {
        alert('Please select a template first');
        return;
    }
    
    const totalLabelsOnSheet = template.per_sheet;
    
    // If multiple QR codes are selected, ask user which one to use
    let qrCodeToUse;
    if (selectedQRCodes.length === 1) {
        qrCodeToUse = selectedQRCodes[0];
    } else {
        // Find the QR code details for user selection
        const selectedQRDetails = qrCodes.filter(qr => selectedQRCodes.includes(qr.id));
        const qrNames = selectedQRDetails.map(qr => qr.display_name).join('\n');
        
        const confirmed = confirm(`You have ${selectedQRCodes.length} QR codes selected.\n\nQR codes:\n${qrNames}\n\nClick OK to fill all ${totalLabelsOnSheet} labels with the FIRST selected QR code (${selectedQRDetails[0].display_name}), or Cancel to choose a different QR code.`);
        
        if (!confirmed) {
            alert('Please select just one QR code and try again');
            return;
        }
        
        qrCodeToUse = selectedQRCodes[0]; // Use the first one
    }
    
    const qrDetails = qrCodes.find(qr => qr.id === qrCodeToUse);
    const qrName = qrDetails ? qrDetails.display_name : `QR-${qrCodeToUse}`;
    
    console.log('Template:', selectedTemplate);
    console.log('Labels per sheet:', totalLabelsOnSheet);
    console.log('QR code to use:', qrCodeToUse, qrName);
    
    // Fill all labels with the same QR code
    selectedQRCodes = new Array(totalLabelsOnSheet).fill(qrCodeToUse);
    
    console.log('New selection after fill all labels:', selectedQRCodes);
    console.log('Total labels that will be printed:', selectedQRCodes.length);
    
    // Update UI - all QR codes should be deselected except the one being used
    document.querySelectorAll('.qr-item').forEach(item => {
        const qrId = parseInt(item.dataset.id);
        const checkbox = document.getElementById(`qr-${qrId}`);
        
        if (qrId === qrCodeToUse) {
            item.classList.add('selected');
            checkbox.checked = true;
        } else {
            item.classList.remove('selected');
            checkbox.checked = false;
        }
    });
    
    updateSelectedCount();
    
    // Show feedback to user
    const message = `✅ Ready to print ${totalLabelsOnSheet} identical labels!\n\n🏷️ All ${totalLabelsOnSheet} labels will use: "${qrName}"`;
    alert(message);
    
    // Auto-update preview to show the filled template
    console.log('Auto-updating preview with filled labels');
    updatePreview();
}

function updateSelectedCount() {
    const countElement = document.getElementById('selected-count');
    if (countElement) {
        countElement.textContent = selectedQRCodes.length;
    }
    console.log('Updated selected count:', selectedQRCodes.length);
}

function updatePreview() {
    console.log('updatePreview called');
    console.log('Selected template:', selectedTemplate);
    console.log('Selected QR codes:', selectedQRCodes);
    
    if (selectedQRCodes.length === 0) {
        alert('Please select at least one QR code');
        return;
    }
    
    // Show loading state
    const container = document.getElementById('preview-container');
    container.innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Generating preview...</span>
            </div>
            <p class="mt-2">Generating preview for ${selectedTemplate}...</p>
        </div>
    `;
    
    fetch('print-manager.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=preview_template&template=${selectedTemplate}&selected_ids=${JSON.stringify(selectedQRCodes)}`
    })
    .then(response => response.json())
    .then(data => {
        console.log('Preview response:', data);
        if (data.success) {
            generatePreview(data.qr_codes, data.template);
        } else {
            container.innerHTML = `<div class="alert alert-danger">Error generating preview: ${data.error}</div>`;
        }
    })
    .catch(error => {
        console.error('Preview error:', error);
        container.innerHTML = `<div class="alert alert-danger">Error generating preview</div>`;
    });
}

function generatePreview(codes, template) {
    console.log('generatePreview called with template:', template);
    console.log('Template config:', template);
    console.log('QR codes for preview:', codes.length);
    console.log('Fit to Cut Out mode:', fitToCutOut);
    
    const container = document.getElementById('preview-container');
    if (!container) {
        console.error('Preview container not found!');
        return;
    }
    
    // Clear any existing content and styles
    container.innerHTML = '';
    container.style.background = '#f8f9fa';
    
    const perSheet = template.per_sheet;
    const cols = template.layout.cols;
    const rows = template.layout.rows;
    
    console.log('Template details:', {
        perSheet: perSheet,
        cols: cols,
        rows: rows,
        selectedTemplate: selectedTemplate
    });
    
    // Calculate preview dimensions based on template
    let previewWidth, previewHeight;
    switch(selectedTemplate) {
        case 'avery_5160':
            previewWidth = 400;
            previewHeight = 520;
            break;
        case 'avery_5658':
        case 'avery_5908':
            previewWidth = 350;
            previewHeight = 450;
            break;
        case 'business_card':
            previewWidth = 450;
            previewHeight = 550;
            break;
        case 'full_page':
            previewWidth = 300;
            previewHeight = 400;
            break;
        default:
            previewWidth = 400;
            previewHeight = 520;
    }
    
    // QR code sizing based on fit mode
    let qrMaxWidth, qrMaxHeight, labelPadding, borderStyle, textSize;
    if (fitToCutOut) {
        qrMaxWidth = '95%';
        qrMaxHeight = '85%';
        labelPadding = '2px';
        borderStyle = '1px solid #999';
        textSize = '7px';
    } else {
        qrMaxWidth = '70%';
        qrMaxHeight = '50%';
        labelPadding = '8px';
        borderStyle = '1px dashed #ccc';
        textSize = '8px';
    }
    
    console.log('Preview settings:', {
        previewWidth: previewWidth,
        previewHeight: previewHeight,
        fitToCutOut: fitToCutOut,
        qrMaxWidth: qrMaxWidth,
        qrMaxHeight: qrMaxHeight
    });
    
    let html = `
        <div class="mb-3">
            <h5>Preview: ${template.name} 
                <span class="badge ${fitToCutOut ? 'bg-success' : 'bg-secondary'} ms-2">
                    ${fitToCutOut ? '✂️ Fit to Cut Out' : '📏 Standard'}
                </span>
            </h5>
            <p class="text-muted">${template.description} - ${codes.length} QR codes selected</p>
            ${fitToCutOut ? '<div class="alert alert-info py-2"><small><i class="bi bi-info-circle me-1"></i><strong>Fit to Cut Out Mode:</strong> QR codes will fill the entire label space with minimal borders for clean cutting.</small></div>' : ''}
        </div>
    `;
    
    let sheets = Math.ceil(codes.length / perSheet);
    console.log('Generating', sheets, 'sheets for', codes.length, 'QR codes');
    
    for (let sheet = 0; sheet < sheets; sheet++) {
        let sheetCodes = codes.slice(sheet * perSheet, (sheet + 1) * perSheet);
        
        html += `
            <div class="print-template-preview template-${selectedTemplate} mb-4" 
                 style="width: ${previewWidth}px; height: ${previewHeight}px; border: 2px solid #ddd; background: white;">
                <div class="label-grid" style="
                    display: grid;
                    grid-template-columns: repeat(${cols}, 1fr); 
                    grid-template-rows: repeat(${rows}, 1fr);
                    gap: ${fitToCutOut ? '1px' : '2px'};
                    padding: ${fitToCutOut ? '5px' : '10px'};
                    width: 100%;
                    height: 100%;
                ">
        `;
        
        // Fill with QR codes
        for (let i = 0; i < perSheet; i++) {
            if (i < sheetCodes.length) {
                const qr = sheetCodes[i];
                html += `
                    <div class="label-item" style="
                        border: ${borderStyle};
                        display: flex;
                        flex-direction: column;
                        align-items: center;
                        justify-content: center;
                        padding: ${labelPadding};
                        font-size: 10px;
                        text-align: center;
                        background: white;
                        ${fitToCutOut ? 'overflow: hidden;' : ''}
                    ">
                        <img src="${qr.qr_url}" alt="QR Code" class="label-qr-code" style="
                            max-width: ${qrMaxWidth};
                            max-height: ${qrMaxHeight};
                            object-fit: contain;
                            ${fitToCutOut ? 'border-radius: 0;' : ''}
                        ">
                        ${fitToCutOut ? '' : `<div class="label-text" style="
                            margin-top: 3px;
                            font-weight: 600;
                            color: #333;
                            word-break: break-word;
                            font-size: ${textSize};
                            line-height: 1.1;
                        ">${qr.display_name}</div>`}
                    </div>
                `;
            } else {
                html += `
                    <div class="label-item" style="
                        border: 1px dashed #eee;
                        background: #f8f9fa;
                    "></div>
                `;
            }
        }
        
        html += '</div></div>';
        
        if (sheet < sheets - 1) {
            html += '<div class="text-center text-muted my-3">--- Page Break ---</div>';
        }
    }
    
    container.innerHTML = html;
    console.log('Preview generated successfully');
    console.log('Preview HTML length:', html.length);
    console.log('Container innerHTML set, checking visibility...');
    
    // Force container to be visible
    container.style.display = 'block';
    container.style.visibility = 'visible';
    container.style.opacity = '1';
    
    // Scroll to preview
    container.scrollIntoView({ behavior: 'smooth', block: 'start' });
    
    console.log('Preview container final state:', {
        display: container.style.display,
        visibility: container.style.visibility,
        opacity: container.style.opacity,
        innerHTML_length: container.innerHTML.length
    });
}

function printLabels() {
    if (selectedQRCodes.length === 0) {
        alert('Please select QR codes and update preview first');
        return;
    }
    
    // Generate print content
    fetch('print-manager.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=preview_template&template=${selectedTemplate}&selected_ids=${JSON.stringify(selectedQRCodes)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            generatePrintContent(data.qr_codes, data.template);
            window.print();
        } else {
            alert('Error preparing print content: ' + data.error);
        }
    });
}

function generatePrintContent(codes, template) {
    const printContent = document.getElementById('print-content');
    const perSheet = template.per_sheet;
    const cols = template.layout.cols;
    const rows = template.layout.rows;
    
    let html = '';
    let sheets = Math.ceil(codes.length / perSheet);
    
    for (let sheet = 0; sheet < sheets; sheet++) {
        let sheetCodes = codes.slice(sheet * perSheet, (sheet + 1) * perSheet);
        
        if (sheet > 0) html += '<div class="print-page-break"></div>';
        
        html += `
            <div class="print-template-preview template-${selectedTemplate}">
                <div class="label-grid">
        `;
        
        // Fill with QR codes
        for (let i = 0; i < perSheet; i++) {
            if (i < sheetCodes.length) {
                const qr = sheetCodes[i];
                html += `
                    <div class="label-item">
                        <img src="${qr.qr_url}" alt="QR Code" class="label-qr-code">
                        <div class="label-text">${qr.display_name}</div>
                    </div>
                `;
            } else {
                html += '<div class="label-item"></div>';
            }
        }
        
        html += '</div></div>';
    }
    
    printContent.innerHTML = html;
    printContent.style.display = 'block';
}

function generatePDF() {
    if (selectedQRCodes.length === 0) {
        alert('Please select QR codes first');
        return;
    }
    
    // Show loading state
    const btn = event.target;
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="bi bi-spinner-border spinner-border-sm me-2"></i>Generating Enhanced PDF...';
    btn.disabled = true;
    
    // Get fit to cut out setting
    const fitToCutOutCheckbox = document.getElementById('fitToCutOut');
    const fitToCutOut = fitToCutOutCheckbox ? fitToCutOutCheckbox.checked : false;
    
    // Prepare request body
    let requestBody = `template=${selectedTemplate}&selected_ids=${JSON.stringify(selectedQRCodes)}&fit_to_cut_out=${fitToCutOut}`;
    
    // Add custom template if it exists
    const customTemplateData = getCustomTemplateData();
    if (customTemplateData) {
        requestBody += `&custom_template=${JSON.stringify(customTemplateData)}`;
    }
    
    fetch('../api/print/generate-pdf.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: requestBody
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Create download link
            const link = document.createElement('a');
            link.href = data.download_url;
            link.download = data.filename;
            link.click();
            
            // Enhanced success message with detailed info
            const config = data.template_config;
            const message = `✅ PDF Generated Successfully!\n\n` +
                `📋 Template: ${data.template}\n` +
                `📊 QR Codes: ${data.qr_count}\n` +
                `📄 Pages: ${data.pages}\n` +
                `🏷️ Labels per page: ${data.labels_per_page}\n` +
                `📏 Label size: ${config.label_size}\n` +
                `📐 Layout: ${config.layout}\n` +
                `🎯 QR fill: ${config.qr_fill_percentage}\n` +
                `📋 Page size: ${config.page_size}` +
                (data.fit_to_cut_out ? `\n🎨 Fit-to-Cut-Out: Enabled` : '');
            
            alert(message);
        } else {
            alert('Error generating PDF: ' + data.error);
            console.error('PDF Generation Error:', data);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error generating PDF. Please try again.');
    })
    .finally(() => {
        btn.innerHTML = originalText;
        btn.disabled = false;
    });
}

// Function to get custom template data if available
function getCustomTemplateData() {
    // This can be expanded later for custom template UI
    // For now, return null to use predefined templates
    return null;
}

// Toggle fit to cut out mode
function toggleFitToCutOut() {
    fitToCutOut = document.getElementById('fitToCutOut').checked;
    console.log('Fit to Cut Out mode:', fitToCutOut ? 'ON' : 'OFF');
    
    // Update template info badge
    updateTemplateInfo();
    
    // Auto-update preview if QR codes are selected
    if (selectedQRCodes.length > 0) {
        console.log('Auto-updating preview with new fit mode');
        updatePreview();
    }
}

// Update template info badge with current settings
function updateTemplateInfo() {
    const infoBadge = document.getElementById('template-info');
    if (!infoBadge || !selectedTemplate) return;
    
    const templates = <?php echo json_encode($print_templates); ?>;
    const template = templates[selectedTemplate];
    
    if (template) {
        const fitMode = document.getElementById('fitToCutOut')?.checked;
        const dimensions = template.dimensions;
        const fillPercentage = fitMode ? '100%' : (template.per_sheet <= 10 ? '95-98%' : '85%');
        
        infoBadge.innerHTML = `
            <i class="bi bi-tag me-1"></i>
            ${dimensions.width} × ${dimensions.height} | 
            ${template.per_sheet} labels | 
            QR Fill: ${fillPercentage}
            ${fitMode ? ' | <span class="text-warning">Fit-to-Cut</span>' : ''}
        `;
        infoBadge.className = fitMode ? 'badge bg-warning text-dark me-2' : 'badge bg-info me-2';
    }
}

// Show template help modal/alert
function showTemplateHelp() {
    const templates = <?php echo json_encode($print_templates); ?>;
    
    let helpText = "📏 **Template Measurement Guide**\n\n";
    
    Object.keys(templates).forEach(key => {
        const template = templates[key];
        helpText += `**${template.name}**\n`;
        helpText += `Size: ${template.dimensions.width} × ${template.dimensions.height}\n`;
        helpText += `Layout: ${template.layout.cols} × ${template.layout.rows} (${template.per_sheet} labels)\n`;
        helpText += `${template.description}\n\n`;
    });
    
    helpText += "\n🎯 **QR Code Fill Percentages:**\n";
    helpText += "• Address Labels: 85% (leave space for text)\n";
    helpText += "• Square Labels: 95-98% (optimal for stickers)\n";
    helpText += "• Business Cards: 70% (room for branding)\n";
    helpText += "• Full Page: 85% (poster/display size)\n\n";
    
    helpText += "✂️ **Fit to Cut Out Mode:**\n";
    helpText += "• QR codes fill 100% of label area\n";
    helpText += "• No text labels included\n";
    helpText += "• Perfect for die-cut stickers\n";
    helpText += "• Edge-to-edge printing\n\n";
    
    helpText += "📐 **Tips for Best Results:**\n";
    helpText += "• Use high-quality label sheets\n";
    helpText += "• Check printer alignment settings\n";
    helpText += "• Test print on regular paper first\n";
    helpText += "• Ensure QR codes have good contrast\n";
    
    alert(helpText);
}

// Test preview function for debugging
function testPreview() {
    console.log('=== TEST PREVIEW FUNCTION ===');
    console.log('Current state:', {
        selectedTemplate: selectedTemplate,
        selectedQRCodes: selectedQRCodes,
        qrCodes: qrCodes.length,
        fitToCutOut: fitToCutOut
    });
    
    const container = document.getElementById('preview-container');
    console.log('Preview container found:', !!container);
    
    if (qrCodes.length === 0) {
        container.innerHTML = '<div class="alert alert-warning">No QR codes loaded. Please wait for QR codes to load first.</div>';
        return;
    }
    
    // Use first QR code for testing
    const testQR = qrCodes[0];
    const testTemplate = {
        name: 'Test Template',
        description: 'Testing preview generation',
        per_sheet: 4,
        layout: { cols: 2, rows: 2 }
    };
    
    console.log('Testing with:', { testQR: testQR, testTemplate: testTemplate });
    
    // Generate test preview
    generatePreview([testQR], testTemplate);
}
</script>

<?php require_once __DIR__ . '/../core/includes/footer.php'; ?>
</rewritten_file>