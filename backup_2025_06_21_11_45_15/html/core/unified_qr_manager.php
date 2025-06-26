<?php
/**
 * Unified QR Code Manager
 * Consolidates all QR generation logic into a single, reliable system
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/QRGenerator.php';
require_once __DIR__ . '/services/VotingService.php';
require_once __DIR__ . '/services/PizzaTrackerService.php';

class UnifiedQRManager {
    private $pdo;
    private $generator;
    private $business_id;
    private $upload_dir;
    private $allowed_types;
    
    public function __construct($pdo, $business_id = null) {
        $this->pdo = $pdo;
        $this->business_id = $business_id;
        $this->generator = new QRGenerator();
        $this->upload_dir = __DIR__ . '/../uploads/qr/';
        
        // Initialize services
        VotingService::init($pdo);
        PizzaTrackerService::init($pdo);
        
        // Unified QR types - single source of truth
        $this->allowed_types = [
            'static' => [
                'name' => 'Static QR Code',
                'description' => 'Direct URL link',
                'requires' => ['url'],
                'color' => 'primary'
            ],
            'dynamic' => [
                'name' => 'Dynamic QR Code', 
                'description' => 'Changeable destination',
                'requires' => ['url'],
                'color' => 'info'
            ],
            'dynamic_voting' => [
                'name' => 'Voting QR Code',
                'description' => 'Product voting interface',
                'requires' => ['campaign_id'],
                'color' => 'success'
            ],
            'dynamic_vending' => [
                'name' => 'Vending Machine QR',
                'description' => 'Machine-specific voting',
                'requires' => ['machine_id'],
                'color' => 'warning'
            ],
            'machine_sales' => [
                'name' => 'Machine Sales QR',
                'description' => 'Product purchase interface',
                'requires' => ['machine_id'],
                'color' => 'danger'
            ],
            'promotion' => [
                'name' => 'Promotion QR',
                'description' => 'Special offers and deals',
                'requires' => ['machine_id'],
                'color' => 'secondary'
            ],
            'spin_wheel' => [
                'name' => 'Spin Wheel QR',
                'description' => 'Interactive spin wheel game',
                'requires' => ['spin_wheel_id'],
                'color' => 'warning'
            ],
            'pizza_tracker' => [
                'name' => 'Pizza Tracker QR',
                'description' => 'Order tracking interface',
                'requires' => ['tracker_id'],
                'color' => 'success'
            ],
            'cross_promo' => [
                'name' => 'Cross Promotion QR',
                'description' => 'Multi-business promotion',
                'requires' => ['campaign_id'],
                'color' => 'info'
            ],
            'stackable' => [
                'name' => 'Stackable QR',
                'description' => 'Multiple actions in sequence',
                'requires' => ['actions'],
                'color' => 'dark'
            ],
            'casino' => [
                'name' => 'Casino QR Code',
                'description' => 'Direct link to your business casino',
                'requires' => ['business_id'],
                'color' => 'danger'
            ]
        ];
        
        // Ensure upload directory exists
        if (!is_dir($this->upload_dir)) {
            mkdir($this->upload_dir, 0775, true);
        }
    }
    
    /**
     * Generate a QR code with unified validation and processing
     */
    public function generateQR($data) {
        try {
            $start_time = microtime(true);
            
            // Step 1: Validate input
            $this->validateQRData($data);
            
            // Step 2: Build content URL
            $content = $this->buildContentURL($data);
            
            // Step 3: Generate unique code
            $qr_code = $this->generateUniqueCode();
            
            // Step 4: Prepare QR options with enhanced defaults
            $qr_options = $this->prepareQROptions($data);
            
            // Step 5: Generate QR code image with enhanced features
            $generation_result = $this->generator->generate(array_merge($qr_options, [
                'content' => $content,
                'type' => $data['qr_type'],
                'code' => $qr_code,
                'error_correction_level' => $data['error_correction_level'] ?? 'H',
                'size' => $data['size'] ?? 400,
                'margin' => $data['margin'] ?? 2,
                'foreground_color' => $data['foreground_color'] ?? '#000000',
                'background_color' => $data['background_color'] ?? '#FFFFFF',
                'logo' => $data['logo'] ?? null,
                'module_shape' => $data['module_shape'] ?? 'square',
                'eye_style' => $data['eye_style'] ?? 'square',
                'gradient_type' => $data['gradient_type'] ?? null,
                'frame_style' => $data['frame_style'] ?? null
            ]));
            
            if (!$generation_result['success']) {
                throw new Exception('QR generation failed: ' . ($generation_result['error'] ?? 'Unknown error'));
            }
            
            // Step 6: Save to database with enhanced metadata
            $qr_id = $this->saveQRToDatabase($data, $qr_code, $content, $generation_result['url'], $qr_options);
            
            // Step 7: Log generation with enhanced tracking
            $generation_time = microtime(true) - $start_time;
            $this->logGeneration($qr_id, 'unified_manager', $generation_time, $qr_options);
            
            return [
                'success' => true,
                'data' => [
                    'id' => $qr_id,
                    'code' => $qr_code,
                    'qr_code_url' => $generation_result['url'],
                    'content_url' => $content,
                    'type' => $data['qr_type'],
                    'generation_time' => round($generation_time, 4),
                    'preview_info' => [
                        'size' => $qr_options['size'],
                        'error_correction' => $qr_options['error_correction_level'],
                        'scan_distance' => $this->calculateScanDistance($qr_options['size'])
                    ]
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Validate QR data with unified rules
     */
    private function validateQRData($data) {
        // Check QR type
        if (empty($data['qr_type']) || !isset($this->allowed_types[$data['qr_type']])) {
            throw new Exception('Invalid QR type: ' . ($data['qr_type'] ?? 'none'));
        }
        
        $type_config = $this->allowed_types[$data['qr_type']];
        
        // Check required fields
        foreach ($type_config['requires'] as $field) {
            if (empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }
        
        // Business validation
        if ($this->business_id && !$this->validateBusinessAccess($data)) {
            throw new Exception('Access denied to specified resource');
        }
        
        // Type-specific validation
        switch ($data['qr_type']) {
            case 'static':
            case 'dynamic':
                if (!filter_var($data['url'], FILTER_VALIDATE_URL)) {
                    throw new Exception('Invalid URL provided');
                }
                break;
                
            case 'dynamic_voting':
            case 'cross_promo':
                $this->validateCampaign($data['campaign_id']);
                break;
                
            case 'dynamic_vending':
            case 'machine_sales':
            case 'promotion':
                $this->validateMachine($data['machine_id']);
                break;
                
            case 'spin_wheel':
                $this->validateSpinWheel($data['spin_wheel_id']);
                break;
                
            case 'pizza_tracker':
                $this->validatePizzaTracker($data['tracker_id']);
                break;
        }
    }
    
    /**
     * Calculate estimated scan distance based on QR size
     */
    private function calculateScanDistance($size) {
        $size = intval($size);
        if ($size <= 200) return '1-2 inches';
        if ($size <= 400) return '2-4 inches';
        if ($size <= 600) return '4-6 inches';
        return '6+ inches';
    }
    
    /**
     * Build content URL based on QR type with enhanced routing
     */
    private function buildContentURL($data) {
        $base_url = APP_URL;
        
        switch ($data['qr_type']) {
            case 'static':
            case 'dynamic':
                return $data['url'];
                
            case 'dynamic_voting':
                return $base_url . '/vote.php?campaign=' . intval($data['campaign_id']);
                
            case 'dynamic_vending':
                return $base_url . '/vote.php?machine=' . intval($data['machine_id']);
                
            case 'machine_sales':
                return $base_url . '/public/machine-sales.php?machine=' . intval($data['machine_id']);
                
            case 'promotion':
                return $base_url . '/public/promotions.php?machine=' . intval($data['machine_id']);
                
            case 'spin_wheel':
                return $base_url . '/public/spin-wheel.php?wheel=' . intval($data['spin_wheel_id']);
                
            case 'pizza_tracker':
                return $base_url . '/public/pizza-tracker.php?tracker=' . intval($data['tracker_id']);
                
            case 'cross_promo':
                return $base_url . '/public/cross-promo.php?campaign=' . intval($data['campaign_id']);
                
            case 'stackable':
                return $base_url . '/public/stackable.php?actions=' . urlencode(json_encode($data['actions']));
                
            default:
                throw new Exception('Unknown QR type for URL building');
        }
    }
    
    /**
     * Prepare QR generation options
     */
    private function prepareQROptions($data) {
        $options = [
            'size' => intval($data['size'] ?? 400),
            'foreground_color' => $data['foreground_color'] ?? '#000000',
            'background_color' => $data['background_color'] ?? '#FFFFFF',
            'error_correction_level' => $data['error_correction_level'] ?? 'H',
            'margin' => intval($data['margin'] ?? 2)
        ];
        
        // Add logo if specified
        if (!empty($data['logo'])) {
            $options['logo'] = $data['logo'];
        }
        
        // Add advanced options if specified
        $advanced_options = ['module_shape', 'eye_style', 'gradient_type', 'frame_style'];
        foreach ($advanced_options as $option) {
            if (!empty($data[$option])) {
                $options[$option] = $data[$option];
            }
        }
        
        return $options;
    }
    
    /**
     * Save QR code to database with unified schema
     */
    private function saveQRToDatabase($data, $qr_code, $content, $file_url, $qr_options) {
        $stmt = $this->pdo->prepare("
            INSERT INTO qr_codes (
                business_id, machine_id, campaign_id, qr_type, code, 
                machine_name, url, qr_options, status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())
        ");
        
        $stmt->execute([
            $this->business_id,
            $data['machine_id'] ?? null,
            $data['campaign_id'] ?? null,
            $data['qr_type'],
            $qr_code,
            $data['machine_name'] ?? $data['name'] ?? '',
            $content,
            json_encode($qr_options)
        ]);
        
        return $this->pdo->lastInsertId();
    }
    
    /**
     * Log QR generation for monitoring
     */
    private function logGeneration($qr_id, $method, $generation_time, $options) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO qr_generation_log (
                    qr_code_id, generation_method, generation_time, 
                    file_size, options_used
                ) VALUES (?, ?, ?, ?, ?)
            ");
            
            $file_size = 0; // TODO: Calculate actual file size
            
            $stmt->execute([
                $qr_id,
                $method,
                $generation_time,
                $file_size,
                json_encode($options)
            ]);
        } catch (Exception $e) {
            // Non-critical error, don't break the flow
            error_log("QR generation logging failed: " . $e->getMessage());
        }
    }
    
    /**
     * Generate unique QR code identifier
     */
    private function generateUniqueCode() {
        do {
            $code = 'QR' . strtoupper(substr(uniqid(), -8));
            $exists = $this->pdo->query("SELECT id FROM qr_codes WHERE code = '$code'")->fetch();
        } while ($exists);
        
        return $code;
    }
    
    /**
     * Get all allowed QR types
     */
    public function getAllowedTypes() {
        return $this->allowed_types;
    }
    
    /**
     * Validation helper methods
     */
    private function validateBusinessAccess($data) {
        // TODO: Implement business access validation
        return true;
    }
    
    private function validateCampaign($campaign_id) {
        $stmt = $this->pdo->prepare("SELECT id FROM campaigns WHERE id = ? AND business_id = ?");
        $stmt->execute([$campaign_id, $this->business_id]);
        if (!$stmt->fetch()) {
            throw new Exception('Invalid campaign ID');
        }
    }
    
    private function validateMachine($machine_id) {
        $stmt = $this->pdo->prepare("SELECT id FROM machines WHERE id = ? AND business_id = ?");
        $stmt->execute([$machine_id, $this->business_id]);
        if (!$stmt->fetch()) {
            throw new Exception('Invalid machine ID');
        }
    }
    
    private function validateSpinWheel($wheel_id) {
        $stmt = $this->pdo->prepare("SELECT id FROM spin_wheels WHERE id = ? AND business_id = ?");
        $stmt->execute([$wheel_id, $this->business_id]);
        if (!$stmt->fetch()) {
            throw new Exception('Invalid spin wheel ID');
        }
    }
    
    private function validatePizzaTracker($tracker_id) {
        $stmt = $this->pdo->prepare("SELECT id FROM pizza_trackers WHERE id = ? AND business_id = ?");
        $stmt->execute([$tracker_id, $this->business_id]);
        if (!$stmt->fetch()) {
            throw new Exception('Invalid pizza tracker ID');
        }
    }
} 