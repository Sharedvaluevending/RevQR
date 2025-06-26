<?php
require_once __DIR__ . '/../../core/config.php';
require_once __DIR__ . '/../../core/session.php';
require_once __DIR__ . '/../../core/auth.php';
require_once __DIR__ . '/../../core/business_utils.php';
require_once __DIR__ . '/../../vendor/autoload.php';

// Require business role
require_role('business');

// Get business ID
$business_id = getOrCreateBusinessId($pdo, $_SESSION['user_id']);

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Enhanced Template Configuration Class
class LabelTemplate {
    public $name;
    public $description;
    public $page_width_mm;
    public $page_height_mm;
    public $label_width_mm;
    public $label_height_mm;
    public $cols;
    public $rows;
    public $margin_top_mm;
    public $margin_left_mm;
    public $spacing_x_mm;
    public $spacing_y_mm;
    public $qr_fill_percentage;
    public $corner_radius_mm;
    public $include_text;
    public $text_height_mm;
    public $fit_to_cut_out;
    
    public function __construct($config) {
        $this->name = $config['name'] ?? 'Custom Template';
        $this->description = $config['description'] ?? '';
        $this->page_width_mm = $config['page_width_mm'] ?? 215.9; // Letter width
        $this->page_height_mm = $config['page_height_mm'] ?? 279.4; // Letter height
        $this->label_width_mm = $config['label_width_mm'] ?? 50.8;
        $this->label_height_mm = $config['label_height_mm'] ?? 50.8;
        $this->cols = $config['cols'] ?? 2;
        $this->rows = $config['rows'] ?? 5;
        $this->margin_top_mm = $config['margin_top_mm'] ?? 12.7;
        $this->margin_left_mm = $config['margin_left_mm'] ?? 12.7;
        $this->spacing_x_mm = $config['spacing_x_mm'] ?? 3.175;
        $this->spacing_y_mm = $config['spacing_y_mm'] ?? 0;
        $this->qr_fill_percentage = $config['qr_fill_percentage'] ?? 95;
        $this->corner_radius_mm = $config['corner_radius_mm'] ?? 0;
        $this->include_text = $config['include_text'] ?? true;
        $this->text_height_mm = $config['text_height_mm'] ?? 6;
        $this->fit_to_cut_out = $config['fit_to_cut_out'] ?? false;
    }
    
    public function getLabelsPerPage() {
        return $this->cols * $this->rows;
    }
    
    public function getQRCodeSize() {
        $available_width = $this->label_width_mm;
        $available_height = $this->label_height_mm;
        
        if ($this->include_text && !$this->fit_to_cut_out) {
            $available_height -= $this->text_height_mm;
        }
        
        $max_size = min($available_width, $available_height);
        return $max_size * ($this->qr_fill_percentage / 100);
    }
    
    public function getLabelPosition($row, $col) {
        $x = $this->margin_left_mm + ($col * ($this->label_width_mm + $this->spacing_x_mm));
        $y = $this->margin_top_mm + ($row * ($this->label_height_mm + $this->spacing_y_mm));
        return [$x, $y];
    }
}

// Predefined Avery Templates with precise measurements
class AveryTemplates {
    public static function getTemplates() {
        return [
            'avery_5160' => new LabelTemplate([
                'name' => 'Avery 5160 - Address Labels',
                'description' => '2⅝" × 1" labels, 30 per sheet',
                'page_width_mm' => 215.9,
                'page_height_mm' => 279.4,
                'label_width_mm' => 66.675, // 2.625"
                'label_height_mm' => 25.4,   // 1"
                'cols' => 3,
                'rows' => 10,
                'margin_top_mm' => 12.7,     // 0.5"
                'margin_left_mm' => 4.233,   // 0.1666"
                'spacing_x_mm' => 3.175,     // 0.125"
                'spacing_y_mm' => 0,
                'qr_fill_percentage' => 85,  // Smaller for address labels
                'include_text' => true,
                'text_height_mm' => 8
            ]),
            
            'avery_5658' => new LabelTemplate([
                'name' => 'Avery 5658 - Square Labels',
                'description' => '2" × 2" square labels, 10 per sheet',
                'page_width_mm' => 215.9,
                'page_height_mm' => 279.4,
                'label_width_mm' => 50.8,    // 2.0"
                'label_height_mm' => 50.8,   // 2.0"
                'cols' => 2,
                'rows' => 5,
                'margin_top_mm' => 25.4,     // 1.0"
                'margin_left_mm' => 50.8,    // 2.0"
                'spacing_x_mm' => 12.7,      // 0.5"
                'spacing_y_mm' => 12.7,      // 0.5"
                'qr_fill_percentage' => 95,
                'include_text' => true,
                'text_height_mm' => 6
            ]),
            
            'avery_5908' => new LabelTemplate([
                'name' => 'Avery 5908 - Round Labels',
                'description' => '2" diameter round labels, 10 per sheet',
                'page_width_mm' => 215.9,
                'page_height_mm' => 279.4,
                'label_width_mm' => 50.8,    // 2.0"
                'label_height_mm' => 50.8,   // 2.0"
                'cols' => 2,
                'rows' => 5,
                'margin_top_mm' => 25.4,
                'margin_left_mm' => 50.8,
                'spacing_x_mm' => 12.7,
                'spacing_y_mm' => 12.7,
                'qr_fill_percentage' => 95,
                'corner_radius_mm' => 25.4,  // Make it round
                'include_text' => false       // No text for round labels
            ]),
            
            'avery_94102' => new LabelTemplate([
                'name' => 'Avery 94102 - Square Labels',
                'description' => '2" × 2" specialty labels, 8 per sheet',
                'page_width_mm' => 215.9,
                'page_height_mm' => 279.4,
                'label_width_mm' => 50.8,    // 2.0"
                'label_height_mm' => 50.8,   // 2.0"
                'cols' => 2,
                'rows' => 4,
                'margin_top_mm' => 38.1,     // 1.5"
                'margin_left_mm' => 57.15,   // 2.25"
                'spacing_x_mm' => 12.7,      // 0.5"
                'spacing_y_mm' => 25.4,      // 1.0"
                'qr_fill_percentage' => 98,  // Fill more for specialty labels
                'include_text' => true,
                'text_height_mm' => 5
            ]),
            
            'business_card' => new LabelTemplate([
                'name' => 'Business Card Format',
                'description' => '3.5" × 2" cards, 10 per sheet',
                'page_width_mm' => 215.9,
                'page_height_mm' => 279.4,
                'label_width_mm' => 88.9,    // 3.5"
                'label_height_mm' => 50.8,   // 2.0"
                'cols' => 2,
                'rows' => 5,
                'margin_top_mm' => 25.4,
                'margin_left_mm' => 19.05,
                'spacing_x_mm' => 6.35,
                'spacing_y_mm' => 12.7,
                'qr_fill_percentage' => 70,  // Leave space for business info
                'include_text' => true,
                'text_height_mm' => 10
            ]),
            
            'full_page' => new LabelTemplate([
                'name' => 'Full Page QR Codes',
                'description' => 'Large QR codes for posters/displays',
                'page_width_mm' => 215.9,
                'page_height_mm' => 279.4,
                'label_width_mm' => 190.5,   // 7.5"
                'label_height_mm' => 254,    // 10"
                'cols' => 1,
                'rows' => 1,
                'margin_top_mm' => 12.7,
                'margin_left_mm' => 12.7,
                'spacing_x_mm' => 0,
                'spacing_y_mm' => 0,
                'qr_fill_percentage' => 85,
                'include_text' => true,
                'text_height_mm' => 20
            ])
        ];
    }
}

// Enhanced PDF Generator Class
class QRLabelPDF extends TCPDF {
    private $template;
    private $debug_mode = false;
    
    public function __construct($template, $debug = false) {
        parent::__construct('P', 'mm', 'LETTER', true, 'UTF-8', false);
        $this->template = $template;
        $this->debug_mode = $debug;
        
        $this->SetCreator('RevenueQR Enhanced Print Manager');
        $this->SetAuthor('RevenueQR');
        $this->SetTitle('QR Code Labels - ' . $this->template->name);
        $this->SetSubject('Precision QR Code Labels');
        
        // Remove default header/footer
        $this->setPrintHeader(false);
        $this->setPrintFooter(false);
        
        // Set precise margins
        $this->SetMargins(0, 0, 0);
        $this->SetAutoPageBreak(false, 0);
    }
    
    public function Header() {
        // No header
    }
    
    public function Footer() {
        // No footer
    }
    
    public function renderLabelsPage($qr_codes) {
        $this->AddPage();
        
        $label_index = 0;
        for ($row = 0; $row < $this->template->rows; $row++) {
            for ($col = 0; $col < $this->template->cols; $col++) {
                if ($label_index >= count($qr_codes)) break;
                
                $qr = $qr_codes[$label_index];
                $this->renderSingleLabel($qr, $row, $col);
                $label_index++;
            }
            if ($label_index >= count($qr_codes)) break;
        }
    }
    
    private function renderSingleLabel($qr, $row, $col) {
        list($x, $y) = $this->template->getLabelPosition($row, $col);
        
        // Debug: Draw label boundaries
        if ($this->debug_mode) {
            $this->SetDrawColor(255, 0, 0); // Red for debugging
            $this->SetLineWidth(0.2);
            $this->Rect($x, $y, $this->template->label_width_mm, $this->template->label_height_mm);
        }
        
        // Calculate QR code placement
        $qr_size = $this->template->getQRCodeSize();
        $qr_x = $x + ($this->template->label_width_mm - $qr_size) / 2;
        
        if ($this->template->fit_to_cut_out) {
            // Fit to cut out: QR code fills entire label
            $qr_y = $y;
            $qr_size = min($this->template->label_width_mm, $this->template->label_height_mm);
            $qr_x = $x + ($this->template->label_width_mm - $qr_size) / 2;
        } else {
            // Standard mode: leave space for text
            $qr_y = $y + 2; // Small top margin
            if ($this->template->include_text) {
                $qr_y = $y + 1; // Smaller margin when text is included
            }
        }
        
        // Render QR code image
        $this->renderQRCode($qr, $qr_x, $qr_y, $qr_size);
        
        // Render text (if enabled and not fit-to-cut-out mode)
        if ($this->template->include_text && !$this->template->fit_to_cut_out) {
            $this->renderLabelText($qr, $x, $y, $qr_y + $qr_size);
        }
        
        // Apply corner radius for round labels
        if ($this->template->corner_radius_mm > 0) {
            $this->applyCornerRadius($x, $y);
        }
    }
    
    private function renderQRCode($qr, $x, $y, $size) {
        // Try multiple possible QR code paths
        $possible_paths = [
            __DIR__ . '/../../..' . $qr['qr_url'],
            __DIR__ . '/../../uploads/qr/' . $qr['code'] . '.png',
            __DIR__ . '/../../uploads/qr/1/' . $qr['code'] . '.png',
            __DIR__ . '/../../uploads/qr/business/' . $qr['code'] . '.png'
        ];
        
        $qr_image_path = null;
        foreach ($possible_paths as $path) {
            if (file_exists($path)) {
                $qr_image_path = $path;
                break;
            }
        }
        
        if ($qr_image_path) {
            // High-quality image rendering
            $this->Image($qr_image_path, $x, $y, $size, $size, 'PNG', '', '', true, 300, '', false, false, 0);
        } else {
            // Fallback: draw placeholder rectangle
            $this->SetFillColor(240, 240, 240);
            $this->Rect($x, $y, $size, $size, 'F');
            
            // Add placeholder text
            $this->SetFont('helvetica', 'B', 8);
            $this->SetTextColor(150, 150, 150);
            $this->SetXY($x, $y + $size/2 - 2);
            $this->Cell($size, 4, 'QR CODE', 0, 0, 'C');
        }
    }
    
    private function renderLabelText($qr, $label_x, $label_y, $text_y) {
        // Primary text (QR name)
        $this->SetFont('helvetica', 'B', 7);
        $this->SetTextColor(0, 0, 0);
        $this->SetXY($label_x, $text_y + 1);
        $this->Cell($this->template->label_width_mm, 4, $qr['display_name'], 0, 0, 'C');
        
        // Secondary text (QR type) if space allows
        if ($this->template->text_height_mm > 6) {
            $this->SetFont('helvetica', '', 5);
            $this->SetTextColor(80, 80, 80);
            $this->SetXY($label_x, $text_y + 5);
            $details = ucfirst(str_replace('_', ' ', $qr['qr_type'] ?? 'QR Code'));
            $this->Cell($this->template->label_width_mm, 3, $details, 0, 0, 'C');
        }
    }
    
    private function applyCornerRadius($x, $y) {
        // For round labels, we can use clipping paths
        // This is a simplified version - for production, consider using mask images
        $this->SetDrawColor(255, 255, 255);
        $this->SetLineWidth(1);
        
        $radius = $this->template->corner_radius_mm;
        $size = $this->template->label_width_mm;
        
        // Draw circle outline for round labels
        $center_x = $x + $size/2;
        $center_y = $y + $size/2;
        $this->Circle($center_x, $center_y, $radius, 0, 360, 'D');
    }
}

// Main execution
try {
    // Get request parameters
    $template_name = $_POST['template'] ?? 'avery_5658';
    $selected_ids = json_decode($_POST['selected_ids'] ?? '[]', true);
    $fit_to_cut_out = ($_POST['fit_to_cut_out'] ?? 'false') === 'true';
    $debug_mode = ($_POST['debug'] ?? 'false') === 'true';
    
    // Handle custom template
    $custom_template = null;
    if (isset($_POST['custom_template'])) {
        $custom_config = json_decode($_POST['custom_template'], true);
        if ($custom_config) {
            $custom_config['fit_to_cut_out'] = $fit_to_cut_out;
            $custom_template = new LabelTemplate($custom_config);
        }
    }
    
    if (empty($selected_ids)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'No QR codes selected']);
        exit;
    }
    
    // Get template configuration
    $templates = AveryTemplates::getTemplates();
    $template = $custom_template ?? $templates[$template_name] ?? $templates['avery_5658'];
    
    // Apply fit-to-cut-out mode
    if ($fit_to_cut_out) {
        $template->fit_to_cut_out = true;
        $template->qr_fill_percentage = 100;
        $template->include_text = false;
    }
    
    // Get QR codes data
    $placeholders = str_repeat('?,', count($selected_ids) - 1) . '?';
    $stmt = $pdo->prepare("
        SELECT 
            qr.*,
            COALESCE(qr.machine_name, CONCAT('QR-', SUBSTRING(qr.code, -8))) as display_name
        FROM qr_codes qr
        WHERE qr.id IN ($placeholders) AND qr.business_id = ?
        ORDER BY qr.created_at DESC
    ");
    $params = array_merge($selected_ids, [$business_id]);
    $stmt->execute($params);
    $qr_codes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($qr_codes)) {
        throw new Exception('No valid QR codes found');
    }
    
    // Process QR codes to add file paths
    foreach ($qr_codes as &$qr) {
        $meta = $qr['meta'] ? json_decode($qr['meta'], true) : [];
        $qr['qr_url'] = $meta['file_path'] ?? ('/uploads/qr/' . $qr['code'] . '.png');
    }
    
    // Generate PDF
    $pdf = new QRLabelPDF($template, $debug_mode);
    
    $labels_per_page = $template->getLabelsPerPage();
    $total_pages = ceil(count($qr_codes) / $labels_per_page);
    
    for ($page = 0; $page < $total_pages; $page++) {
        $page_qr_codes = array_slice($qr_codes, $page * $labels_per_page, $labels_per_page);
        $pdf->renderLabelsPage($page_qr_codes);
    }
    
    // Generate filename
    $timestamp = date('Y-m-d_H-i-s');
    $filename = "qr_labels_{$template_name}_{$timestamp}.pdf";
    $downloads_dir = __DIR__ . '/../../uploads/pdf/';
    
    if (!file_exists($downloads_dir)) {
        mkdir($downloads_dir, 0755, true);
    }
    
    $file_path = $downloads_dir . $filename;
    $pdf->Output($file_path, 'F');
    
    // Return success response
    echo json_encode([
        'success' => true,
        'download_url' => '/uploads/pdf/' . $filename,
        'filename' => $filename,
        'template' => $template->name,
        'qr_count' => count($qr_codes),
        'pages' => $total_pages,
        'labels_per_page' => $labels_per_page,
        'fit_to_cut_out' => $fit_to_cut_out,
        'template_config' => [
            'label_size' => $template->label_width_mm . 'mm × ' . $template->label_height_mm . 'mm',
            'qr_fill_percentage' => $template->qr_fill_percentage . '%',
            'layout' => $template->cols . ' × ' . $template->rows,
            'page_size' => $template->page_width_mm . 'mm × ' . $template->page_height_mm . 'mm'
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Enhanced PDF Generation Error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'error' => $e->getMessage(),
        'debug_info' => $debug_mode ? [
            'template' => $template_name ?? 'unknown',
            'selected_ids_count' => count($selected_ids ?? []),
            'qr_codes_found' => count($qr_codes ?? [])
        ] : null
    ]);
}
?> 