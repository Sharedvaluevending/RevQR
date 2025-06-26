<?php
/**
 * Unified QR Service
 * Consolidates all QR generation, validation, and management
 * Replaces fragmented QR generation system
 */

class QRService {
    private static $pdo;
    private static $generator;
    private static $upload_dir;
    private static $base_url;
    
    // Standard QR types - single source of truth
    const QR_TYPES = [
        'static' => [
            'name' => 'Static QR Code',
            'description' => 'Direct URL link',
            'requires' => ['url'],
            'validation' => 'validateURL'
        ],
        'dynamic' => [
            'name' => 'Dynamic QR Code',
            'description' => 'Changeable destination',
            'requires' => ['url'],
            'validation' => 'validateURL'
        ],
        'voting' => [
            'name' => 'Voting QR Code',
            'description' => 'Product voting interface',
            'requires' => ['campaign_id'],
            'validation' => 'validateCampaign'
        ],
        'vending' => [
            'name' => 'Vending Machine QR',
            'description' => 'Machine-specific interaction',
            'requires' => ['machine_id'],
            'validation' => 'validateMachine'
        ],
        'promotion' => [
            'name' => 'Promotion QR',
            'description' => 'Special offers and deals',
            'requires' => ['machine_id'],
            'validation' => 'validateMachine'
        ],
        'spin_wheel' => [
            'name' => 'Spin Wheel QR',
            'description' => 'Interactive spin wheel game',
            'requires' => ['spin_wheel_id'],
            'validation' => 'validateSpinWheel'
        ],
        'pizza_tracker' => [
            'name' => 'Pizza Tracker QR',
            'description' => 'Order tracking interface',
            'requires' => ['tracker_id'],
            'validation' => 'validatePizzaTracker'
        ]
    ];
    
    /**
     * Initialize the service
     */
    public static function init($pdo_connection) {
        self::$pdo = $pdo_connection;
        self::$upload_dir = __DIR__ . '/../../uploads/qr/';
        self::$base_url = defined('APP_URL') ? APP_URL : 'https://revenueqr.sharedvaluevending.com';
        
        // Initialize QR generator
        require_once __DIR__ . '/../../includes/QRGenerator.php';
        self::$generator = new QRGenerator();
        
        // Ensure upload directory exists
        if (!is_dir(self::$upload_dir)) {
            mkdir(self::$upload_dir, 0775, true);
        }
    }
    
    /**
     * Generate QR code with unified validation and processing
     * 
     * @param array $data QR generation parameters
     * @param int $business_id Business ID for validation
     * @return array Result with success status and data/error
     */
    public static function generateQR($data, $business_id) {
        try {
            $start_time = microtime(true);
            
            // Step 1: Validate input data
            self::validateQRData($data, $business_id);
            
            // Step 2: Generate unique QR code
            $qr_code = self::generateUniqueCode();
            
            // Step 3: Build content URL based on type
            $content_url = self::buildContentURL($data, $qr_code);
            
            // Step 4: Prepare QR generation options
            $qr_options = self::prepareQROptions($data, $content_url);
            
            // Step 5: Generate QR code image
            $generation_result = self::generateQRImage($qr_options);
            
            if (!$generation_result['success']) {
                throw new Exception('QR image generation failed: ' . ($generation_result['error'] ?? 'Unknown error'));
            }
            
            // Step 6: Save to database
            $qr_id = self::saveQRToDatabase($data, $qr_code, $content_url, $generation_result, $business_id);
            
            // Step 7: Log successful generation
            $generation_time = microtime(true) - $start_time;
            self::logGeneration($qr_id, $business_id, $generation_time, $data['qr_type']);
            
            return [
                'success' => true,
                'data' => [
                    'id' => $qr_id,
                    'code' => $qr_code,
                    'qr_url' => $generation_result['url'],
                    'content_url' => $content_url,
                    'type' => $data['qr_type'],
                    'size' => $qr_options['size'],
                    'generation_time' => round($generation_time, 4)
                ]
            ];
            
        } catch (Exception $e) {
            error_log("QRService::generateQR() error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'error_code' => self::getErrorCode($e->getMessage())
            ];
        }
    }
    
    /**
     * Generate preview QR code (not saved to database)
     * 
     * @param array $data QR preview parameters
     * @return array Result with preview data
     */
    public static function generatePreview($data) {
        try {
            // Basic validation for preview
            if (empty($data['qr_type']) || !isset(self::QR_TYPES[$data['qr_type']])) {
                throw new Exception('Invalid QR type for preview');
            }
            
            // Use sample content for preview
            $content_url = self::buildPreviewURL($data);
            $qr_options = self::prepareQROptions($data, $content_url);
            $qr_options['preview'] = true;
            
            $generation_result = self::generateQRImage($qr_options);
            
            if (!$generation_result['success']) {
                throw new Exception('Preview generation failed: ' . ($generation_result['error'] ?? 'Unknown error'));
            }
            
            return [
                'success' => true,
                'preview_url' => $generation_result['url'],
                'info' => [
                    'type' => self::QR_TYPES[$data['qr_type']]['name'],
                    'size' => $qr_options['size'],
                    'scan_distance' => self::calculateScanDistance($qr_options['size'])
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
     * Get QR code details by ID
     * 
     * @param int $qr_id QR code ID
     * @param int $business_id Business ID for validation
     * @return array QR code details
     */
    public static function getQRDetails($qr_id, $business_id) {
        try {
            $stmt = self::$pdo->prepare("
                SELECT qr.*, 
                       CASE 
                           WHEN qr.machine_id > 0 THEN m.name
                           ELSE 'N/A'
                       END as machine_name,
                       CASE 
                           WHEN qr.campaign_id > 0 THEN c.name
                           ELSE 'N/A'
                       END as campaign_name
                FROM qr_codes qr
                LEFT JOIN machines m ON qr.machine_id = m.id
                LEFT JOIN campaigns c ON qr.campaign_id = c.id
                WHERE qr.id = ? AND qr.business_id = ?
            ");
            $stmt->execute([$qr_id, $business_id]);
            $qr_data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$qr_data) {
                throw new Exception('QR code not found or access denied');
            }
            
            return [
                'success' => true,
                'data' => $qr_data
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get QR code statistics
     * 
     * @param int $qr_id QR code ID
     * @param int $business_id Business ID for validation
     * @return array QR statistics
     */
    public static function getQRStats($qr_id, $business_id) {
        try {
            // Verify QR code belongs to business
            $qr_result = self::getQRDetails($qr_id, $business_id);
            if (!$qr_result['success']) {
                throw new Exception('QR code not found');
            }
            
            // Get scan statistics
            $stmt = self::$pdo->prepare("
                SELECT 
                    COUNT(*) as total_scans,
                    COUNT(DISTINCT ip_address) as unique_visitors,
                    COUNT(DISTINCT DATE(scan_time)) as active_days,
                    MAX(scan_time) as last_scan,
                    MIN(scan_time) as first_scan
                FROM qr_code_stats 
                WHERE qr_code_id = ?
            ");
            $stmt->execute([$qr_id]);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return [
                'success' => true,
                'stats' => $stats
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Validate QR data based on type and business access
     */
    private static function validateQRData($data, $business_id) {
        // Check QR type
        if (empty($data['qr_type']) || !isset(self::QR_TYPES[$data['qr_type']])) {
            throw new Exception('Invalid QR code type: ' . ($data['qr_type'] ?? 'none'));
        }
        
        $type_config = self::QR_TYPES[$data['qr_type']];
        
        // Check required fields
        foreach ($type_config['requires'] as $field) {
            if (empty($data[$field])) {
                throw new Exception("Required field missing: {$field}");
            }
        }
        
        // Run type-specific validation
        if (isset($type_config['validation'])) {
            $validation_method = $type_config['validation'];
            if (method_exists(__CLASS__, $validation_method)) {
                self::$validation_method($data, $business_id);
            }
        }
    }
    
    /**
     * Validate URL for static/dynamic QR codes
     */
    private static function validateURL($data, $business_id) {
        $url = $data['url'];
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new Exception('Invalid URL format');
        }
        
        // Check for malicious URLs (basic security)
        $blocked_domains = ['malware.com', 'phishing.net'];
        $parsed_url = parse_url($url);
        if (isset($parsed_url['host']) && in_array($parsed_url['host'], $blocked_domains)) {
            throw new Exception('URL domain is blocked');
        }
    }
    
    /**
     * Validate campaign access
     */
    private static function validateCampaign($data, $business_id) {
        $stmt = self::$pdo->prepare("
            SELECT id FROM campaigns 
            WHERE id = ? AND business_id = ? AND status = 'active'
        ");
        $stmt->execute([$data['campaign_id'], $business_id]);
        
        if (!$stmt->fetch()) {
            throw new Exception('Campaign not found or access denied');
        }
    }
    
    /**
     * Validate machine access
     */
    private static function validateMachine($data, $business_id) {
        $stmt = self::$pdo->prepare("
            SELECT id FROM machines 
            WHERE id = ? AND business_id = ?
        ");
        $stmt->execute([$data['machine_id'], $business_id]);
        
        if (!$stmt->fetch()) {
            throw new Exception('Machine not found or access denied');
        }
    }
    
    /**
     * Validate spin wheel access
     */
    private static function validateSpinWheel($data, $business_id) {
        $stmt = self::$pdo->prepare("
            SELECT id FROM spin_wheels 
            WHERE id = ? AND business_id = ? AND is_active = 1
        ");
        $stmt->execute([$data['spin_wheel_id'], $business_id]);
        
        if (!$stmt->fetch()) {
            throw new Exception('Spin wheel not found or access denied');
        }
    }
    
    /**
     * Validate pizza tracker access
     */
    private static function validatePizzaTracker($data, $business_id) {
        $stmt = self::$pdo->prepare("
            SELECT id FROM pizza_trackers 
            WHERE id = ? AND business_id = ? AND is_active = 1
        ");
        $stmt->execute([$data['tracker_id'], $business_id]);
        
        if (!$stmt->fetch()) {
            throw new Exception('Pizza tracker not found or access denied');
        }
    }
    
    /**
     * Build content URL based on QR type
     */
    private static function buildContentURL($data, $qr_code) {
        switch ($data['qr_type']) {
            case 'static':
            case 'dynamic':
                return $data['url'];
                
            case 'voting':
            case 'dynamic_voting':
                return self::$base_url . '/vote.php?code=' . $qr_code;
                
            case 'vending':
            case 'dynamic_vending':
                return self::$base_url . '/vending.php?code=' . $qr_code;
                
            case 'machine_sales':
                return self::$base_url . '/purchase.php?code=' . $qr_code;
                
            case 'promotion':
                return self::$base_url . '/promotion.php?code=' . $qr_code;
                
            case 'spin_wheel':
                return self::$base_url . '/spin-wheel.php?code=' . $qr_code;
                
            case 'pizza_tracker':
                return self::$base_url . '/pizza-tracker.php?code=' . $qr_code;
                
            default:
                throw new Exception('Unsupported QR type for URL building');
        }
    }
    
    /**
     * Build preview URL for demonstration
     */
    private static function buildPreviewURL($data) {
        switch ($data['qr_type']) {
            case 'static':
            case 'dynamic':
                return 'https://example.com/preview-url';
                
            case 'voting':
                return self::$base_url . '/vote.php?preview=1';
                
            case 'vending':
                return self::$base_url . '/vote.php?preview=1';
                
            case 'promotion':
                return self::$base_url . '/public/promotions.php?preview=1';
                
            case 'spin_wheel':
                return self::$base_url . '/public/spin-wheel.php?preview=1';
                
            case 'pizza_tracker':
                return self::$base_url . '/public/pizza-tracker.php?preview=1';
                
            default:
                return 'https://example.com/preview';
        }
    }
    
    /**
     * Prepare QR generation options
     */
    private static function prepareQROptions($data, $content_url) {
        return [
            'content' => $content_url,
            'size' => intval($data['size'] ?? 400),
            'foreground_color' => $data['foreground_color'] ?? '#000000',
            'background_color' => $data['background_color'] ?? '#FFFFFF',
            'error_correction_level' => $data['error_correction_level'] ?? 'H',
            'output_format' => $data['output_format'] ?? 'png',
            'logo' => $data['logo'] ?? null,
            'margin' => intval($data['margin'] ?? 10)
        ];
    }
    
    /**
     * Generate QR code image
     */
    private static function generateQRImage($options) {
        try {
            return self::$generator->generate($options);
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Save QR code to database
     */
    private static function saveQRToDatabase($data, $qr_code, $content_url, $generation_result, $business_id) {
        $stmt = self::$pdo->prepare("
            INSERT INTO qr_codes (
                business_id, code, type, content, file_url, 
                machine_id, campaign_id, location, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $business_id,
            $qr_code,
            $data['qr_type'],
            $content_url,
            $generation_result['url'],
            $data['machine_id'] ?? null,
            $data['campaign_id'] ?? null,
            $data['location'] ?? ''
        ]);
        
        return self::$pdo->lastInsertId();
    }
    
    /**
     * Log QR generation for analytics
     */
    private static function logGeneration($qr_id, $business_id, $generation_time, $qr_type) {
        try {
            // Create log table if it doesn't exist
            self::$pdo->exec("
                CREATE TABLE IF NOT EXISTS qr_generation_log (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    qr_id INT,
                    business_id INT,
                    generation_time DECIMAL(8,4),
                    qr_type VARCHAR(50),
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ");
            
            $stmt = self::$pdo->prepare("
                INSERT INTO qr_generation_log (
                    qr_id, business_id, generation_time, qr_type, created_at
                ) VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$qr_id, $business_id, $generation_time, $qr_type]);
        } catch (Exception $e) {
            // Log but don't fail the main operation
            error_log("QR generation logging failed: " . $e->getMessage());
        }
    }
    
    /**
     * Generate unique QR code
     */
    private static function generateUniqueCode() {
        do {
            $code = 'qr_' . bin2hex(random_bytes(8));
            $stmt = self::$pdo->prepare("SELECT id FROM qr_codes WHERE code = ?");
            $stmt->execute([$code]);
        } while ($stmt->fetch());
        
        return $code;
    }
    
    /**
     * Calculate scan distance based on QR size
     */
    private static function calculateScanDistance($size) {
        $size = intval($size);
        if ($size <= 200) return '1-2 inches';
        if ($size <= 400) return '2-4 inches';
        if ($size <= 600) return '4-6 inches';
        return '6+ inches';
    }
    
    /**
     * Get error code from error message
     */
    private static function getErrorCode($message) {
        if (strpos($message, 'Invalid QR type') !== false) return 'INVALID_TYPE';
        if (strpos($message, 'Required field missing') !== false) return 'MISSING_FIELD';
        if (strpos($message, 'not found') !== false) return 'NOT_FOUND';
        if (strpos($message, 'access denied') !== false) return 'ACCESS_DENIED';
        if (strpos($message, 'Invalid URL') !== false) return 'INVALID_URL';
        return 'GENERAL_ERROR';
    }
    
    /**
     * Get all available QR types
     */
    public static function getAvailableTypes() {
        return self::QR_TYPES;
    }
} 