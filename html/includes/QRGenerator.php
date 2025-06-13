<?php
// Suppress deprecation warnings to prevent output corruption
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);

require_once __DIR__ . '/../vendor/autoload.php';
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelHigh;
use Endroid\QrCode\RoundBlockSizeMode\RoundBlockSizeModeMargin;
use Endroid\QrCode\Logo\Logo;

class QRGenerator {
    private $uploadDir;
    private $allowedTypes;
    private $defaultOptions;

    public function __construct() {
        $this->uploadDir = __DIR__ . '/../uploads/qr/';
        $this->allowedTypes = [
            'static', 
            'dynamic', 
            'dynamic_voting', 
            'dynamic_vending', 
            'cross_promo', 
            'stackable',
            'machine_sales',
            'promotion',
            'spin_wheel',
            'pizza_tracker'
        ];
        $this->defaultOptions = [
            'size' => 300,
            'foreground_color' => '#000000',
            'background_color' => '#FFFFFF',
            'error_correction_level' => 'H'
        ];

        // Create upload directory if it doesn't exist
        if (!file_exists($this->uploadDir)) {
            if (!mkdir($this->uploadDir, 0775, true)) {
                throw new Exception("Failed to create upload directory: " . $this->uploadDir);
            }
        }

        // Check if directory is writable
        if (!is_writable($this->uploadDir)) {
            chmod($this->uploadDir, 0775);
            if (!is_writable($this->uploadDir)) {
                throw new Exception("Upload directory is not writable: " . $this->uploadDir);
            }
        }

        // Ensure proper ownership
        if (function_exists('posix_getpwuid')) {
            $currentUser = posix_getpwuid(posix_geteuid());
            if ($currentUser['name'] === 'root') {
                chown($this->uploadDir, 'www-data');
                chgrp($this->uploadDir, 'www-data');
            }
        }
    }

    public function generate($options) {
        try {
            // Sanitize and validate options
            $options = array_merge($this->defaultOptions, (array)$options);
            $options['foreground_color'] = $this->sanitizeHexColor($options['foreground_color'] ?? $this->defaultOptions['foreground_color']);
            $options['background_color'] = $this->sanitizeHexColor($options['background_color'] ?? $this->defaultOptions['background_color']);
            $options['error_correction_level'] = strtoupper($options['error_correction_level'] ?? $this->defaultOptions['error_correction_level']);
            $options['size'] = (int)($options['size'] ?? $this->defaultOptions['size']);
            $options['type'] = $options['type'] ?? 'static';
            $options['content'] = trim($options['content'] ?? '');
            $options['preview'] = !empty($options['preview']);

            // Validate required fields
            if (empty($options['content'])) {
                return $this->errorResponse('Content is required');
            }
            if (!in_array($options['type'], $this->allowedTypes)) {
                return $this->errorResponse('Invalid QR code type');
            }
            if (!$this->isValidHexColor($options['foreground_color'])) {
                $options['foreground_color'] = $this->defaultOptions['foreground_color'];
            }
            if (!$this->isValidHexColor($options['background_color'])) {
                $options['background_color'] = $this->defaultOptions['background_color'];
            }
            if (!in_array($options['error_correction_level'], ['L','M','Q','H'])) {
                $options['error_correction_level'] = $this->defaultOptions['error_correction_level'];
            }
            if ($options['size'] < 100 || $options['size'] > 1000) {
                $options['size'] = $this->defaultOptions['size'];
            }

            // Convert hex colors to RGB
            $foregroundRgb = $this->hexToRgb($options['foreground_color']);
            $backgroundRgb = $this->hexToRgb($options['background_color']);
            $errorCorrectionLevel = $this->getErrorCorrectionLevel($options['error_correction_level']);

            // Create QR code
            $qrCode = QrCode::create($options['content'])
                ->setEncoding(new Encoding('UTF-8'))
                ->setErrorCorrectionLevel($errorCorrectionLevel)
                ->setSize($options['size'])
                ->setMargin(10)
                ->setRoundBlockSizeMode(new RoundBlockSizeModeMargin())
                ->setForegroundColor(new Color($foregroundRgb[0], $foregroundRgb[1], $foregroundRgb[2]))
                ->setBackgroundColor(new Color($backgroundRgb[0], $backgroundRgb[1], $backgroundRgb[2]));

            $writer = new PngWriter();
            $result = null;

            // Add logo if specified
            if (!empty($options['logo'])) {
                $logoPath = __DIR__ . '/../assets/img/logos/' . basename($options['logo']);
                if (file_exists($logoPath) && is_readable($logoPath)) {
                    $logoSize = (int)($options['size'] * 0.3);
                    $logo = new Logo($logoPath, $logoSize, $logoSize, true);
                    $result = $writer->write($qrCode, $logo);
                } else {
                    return $this->errorResponse('Logo file not found or not readable: ' . $logoPath);
                }
            } else {
                $result = $writer->write($qrCode);
            }

            // Process advanced features
            $qrImage = $this->processAdvancedFeatures($result, $options);

            // Preview: return data URL
            if ($options['preview']) {
                $dataUrl = $this->imageToDataUrl($qrImage);
                return [
                    'success' => true,
                    'url' => $dataUrl,
                    'preview_url' => $dataUrl
                ];
            }

            // Generate unique code and save file
            $code = $this->generateUniqueCode();
            $filePath = $this->uploadDir . $code . '.png';
            
            // Save the processed image
            imagepng($qrImage, $filePath);
            imagedestroy($qrImage);
            
            chmod($filePath, 0664);
            if (function_exists('posix_getpwuid')) {
                $currentUser = posix_getpwuid(posix_geteuid());
                if ($currentUser['name'] === 'root') {
                    chown($filePath, 'www-data');
                    chgrp($filePath, 'www-data');
                }
            }

            return [
                'success' => true,
                'data' => [
                    'code' => $code,
                    'qr_code_url' => '/uploads/qr/' . $code . '.png',
                    'preview_url' => null
                ]
            ];
        } catch (Exception $e) {
            error_log('QR Generation Error: ' . $e->getMessage());
            return $this->errorResponse('QR Generation Error: ' . $e->getMessage());
        }
    }

    private function validateOptions($options) {
        // Validate required fields
        if (empty($options['content'])) {
            throw new Exception('Content is required');
        }

        // Validate QR type
        if (!in_array($options['type'], $this->allowedTypes)) {
            throw new Exception('Invalid QR code type');
        }

        // Validate colors
        if (!$this->isValidHexColor($options['foreground_color'])) {
            throw new Exception('Invalid foreground color');
        }
        if (!$this->isValidHexColor($options['background_color'])) {
            throw new Exception('Invalid background color');
        }

        // Validate size
        if (isset($options['size']) && (!is_numeric($options['size']) || $options['size'] < 100 || $options['size'] > 1000)) {
            throw new Exception('Invalid size. Must be between 100 and 1000');
        }

        // Validate error correction level
        if (isset($options['error_correction_level']) && !in_array(strtoupper($options['error_correction_level']), ['L', 'M', 'Q', 'H'])) {
            throw new Exception('Invalid error correction level. Must be L, M, Q, or H');
        }

        // Validate logo if specified
        if (!empty($options['logo'])) {
            $logoPath = __DIR__ . '/../assets/img/logos/' . $options['logo'];
            if (!file_exists($logoPath)) {
                throw new Exception('Logo file not found');
            }
        }
    }

    private function generateUniqueCode() {
        return uniqid('qr_', true);
    }

    private function isValidHexColor($color) {
        return preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $color);
    }

    private function hexToRgb($hex) {
        // Remove # if present
        $hex = ltrim($hex, '#');
        
        // Convert 3-digit hex to 6-digit
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        
        // Convert to RGB
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        
        return [$r, $g, $b];
    }

    private function getErrorCorrectionLevel($level) {
        switch (strtoupper($level)) {
            case 'L':
                return new \Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelLow();
            case 'M':
                return new \Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelMedium();
            case 'Q':
                return new \Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelQuartile();
            case 'H':
            default:
                return new \Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelHigh();
        }
    }

    private function sanitizeHexColor($color) {
        $color = trim($color);
        if (preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $color)) {
            return $color;
        }
        return $this->defaultOptions['foreground_color'];
    }

    private function errorResponse($msg) {
        return [ 'success' => false, 'message' => $msg ];
    }

    private function processAdvancedFeatures($result, $options) {
        // Create GD image from the QR code result
        $qrImage = imagecreatefromstring($result->getString());
        if (!$qrImage) {
            throw new Exception('Failed to create image from QR code result');
        }

        $width = imagesx($qrImage);
        $height = imagesy($qrImage);

        // Create a new canvas for advanced features
        $canvas = imagecreatetruecolor($width + 200, $height + 200); // Extra space for text/effects
        $canvasWidth = imagesx($canvas);
        $canvasHeight = imagesy($canvas);

        // Fill background
        $backgroundRgb = $this->hexToRgb($options['background_color']);
        $backgroundColor = imagecolorallocate($canvas, $backgroundRgb[0], $backgroundRgb[1], $backgroundRgb[2]);
        imagefill($canvas, 0, 0, $backgroundColor);

        // Calculate QR code position (centered)
        $qrX = (int)(($canvasWidth - $width) / 2);
        $qrY = (int)(($canvasHeight - $height) / 2);

        // Apply background gradient if enabled (but preserve QR area)
        if (!empty($options['enable_background_gradient'])) {
            $canvas = $this->applyBackgroundGradient($canvas, $options, $qrX, $qrY, $width, $height);
        }

        // Apply QR code gradient if enabled
        if (!empty($options['enable_qr_gradient'])) {
            $gradientOptions = [
                'type' => $options['qr_gradient_type'] ?? 'linear',
                'start' => $options['qr_gradient_start'] ?? '#000000',
                'middle' => $options['qr_gradient_middle'] ?? '#444444',
                'end' => $options['qr_gradient_end'] ?? '#333333',
                'angle' => $options['qr_gradient_angle'] ?? 45,
                'opacity' => 1
            ];
            $qrImage = $this->applyGradient($qrImage, $gradientOptions, $options);
        }

        // Apply custom eye finder patterns if enabled
        if (!empty($options['enable_custom_eyes'])) {
            $qrImage = $this->applyCustomEyePatterns($qrImage, $options);
        }

        // Apply enhanced borders if enabled
        if (!empty($options['enable_enhanced_border'])) {
            $canvas = $this->applyEnhancedBorder($canvas, $qrX, $qrY, $width, $height, $options);
        }

        // Apply module shape if enabled
        if (!empty($options['enable_module_shape']) && !empty($options['module_shape']) && $options['module_shape'] !== 'square') {
            $qrImage = $this->applyModuleShape($qrImage, $options);
        }

        // Add shadow effect if enabled
        if (!empty($options['enable_shadow'])) {
            $shadowOptions = [
                'color' => $options['shadow_color'] ?? '#000000',
                'blur' => $options['shadow_blur'] ?? 5,
                'offset_x' => $options['shadow_offset_x'] ?? 2,
                'offset_y' => $options['shadow_offset_y'] ?? 2,
                'opacity' => 0.5
            ];
            $this->applyShadow($canvas, $qrImage, $qrX, $qrY, $shadowOptions);
        }

        // Place QR code on canvas
        // Always fill the QR area with the correct background color first
        $backgroundRgb = $this->hexToRgb($options['background_color']);
        $qrBackgroundColor = imagecolorallocate($canvas, $backgroundRgb[0], $backgroundRgb[1], $backgroundRgb[2]);
        imagefilledrectangle($canvas, $qrX, $qrY, $qrX + $width, $qrY + $height, $qrBackgroundColor);
        
        // Ensure QR code has proper background before copying
        $this->ensureQRBackground($qrImage, $options['background_color']);
        
        // Copy QR code with proper blending to ensure background is preserved
        imagecopy($canvas, $qrImage, $qrX, $qrY, 0, 0, $width, $height);

        // Add label text (above QR) if enabled
        if (!empty($options['enable_label']) && !empty($options['label_text'])) {
            $labelOptions = [
                'text' => $options['label_text'],
                'size' => $options['label_size'] ?? 16,
                'color' => $options['label_color'] ?? '#000000',
                'font' => $options['label_font'] ?? 'Arial',
                'alignment' => $options['label_alignment'] ?? 'center',
                'bold' => !empty($options['label_bold']),
                'italic' => !empty($options['label_italic']),
                'underline' => !empty($options['label_underline']),
                'shadow' => !empty($options['label_shadow']),
                'outline' => !empty($options['label_outline']),
                'shadow_color' => $options['label_shadow_color'] ?? '#000000',
                'outline_color' => $options['label_outline_color'] ?? '#000000',
            ];
            $this->addText($canvas, $labelOptions, (int)($qrX + $width/2), (int)($qrY - 30), 'above');
        }

        // Add bottom text (below QR) if enabled
        if (!empty($options['enable_bottom_text']) && !empty($options['bottom_text'])) {
            $bottomOptions = [
                'text' => $options['bottom_text'],
                'size' => $options['bottom_size'] ?? 14,
                'color' => $options['bottom_color'] ?? '#666666',
                'font' => $options['bottom_font'] ?? 'Arial',
                'alignment' => $options['bottom_alignment'] ?? 'center',
                'bold' => !empty($options['bottom_bold']),
                'italic' => !empty($options['bottom_italic']),
                'underline' => !empty($options['bottom_underline']),
                'shadow' => !empty($options['bottom_shadow']),
                'outline' => !empty($options['bottom_outline']),
                'shadow_color' => $options['bottom_shadow_color'] ?? '#000000',
                'outline_color' => $options['bottom_outline_color'] ?? '#000000',
            ];
            $this->addText($canvas, $bottomOptions, (int)($qrX + $width/2), (int)($qrY + $height + 30), 'below');
        }

        imagedestroy($qrImage);
        return $canvas;
    }

    private function applyGradient($image, $gradient, $options) {
        $width = imagesx($image);
        $height = imagesy($image);
        
        $startRgb = $this->hexToRgb($gradient['start']);
        $middleRgb = isset($gradient['middle']) ? $this->hexToRgb($gradient['middle']) : null;
        $endRgb = $this->hexToRgb($gradient['end']);
        $angle = $gradient['angle'] ?? 45;
        $type = $gradient['type'] ?? 'linear';
        
        // Create a copy of the image to work with
        $gradientImage = imagecreatetruecolor($width, $height);
        imagecopy($gradientImage, $image, 0, 0, 0, 0, $width, $height);
        
        // Apply gradient based on type
        switch ($type) {
            case 'linear':
                $this->applyLinearGradient($gradientImage, $startRgb, $endRgb, $angle, $width, $height, $middleRgb);
                break;
            case 'radial':
                $this->applyRadialGradient($gradientImage, $startRgb, $endRgb, $width, $height, $middleRgb);
                break;
            case 'conic':
                $this->applyConicGradient($gradientImage, $startRgb, $endRgb, $width, $height, $middleRgb);
                break;
        }
        
        return $gradientImage;
    }

    private function applyLinearGradient($image, $startRgb, $endRgb, $angle, $width, $height, $middleRgb = null) {
        $angleRad = deg2rad($angle);
        
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $pixel = imagecolorat($image, $x, $y);
                $pixelRgb = imagecolorsforindex($image, $pixel);
                
                // Only apply gradient to dark pixels (foreground)
                if ($pixelRgb['red'] < 128) {
                    // Calculate gradient position based on angle
                    $normalizedX = $x / $width;
                    $normalizedY = $y / $height;
                    
                    $position = ($normalizedX * cos($angleRad) + $normalizedY * sin($angleRad));
                    $position = max(0, min(1, $position));
                    
                    // Calculate color based on position and middle color
                    if ($middleRgb && $position <= 0.5) {
                        // First half: start to middle
                        $localPosition = $position * 2; // Scale to 0-1
                        $r = (int)($startRgb[0] + ($middleRgb[0] - $startRgb[0]) * $localPosition);
                        $g = (int)($startRgb[1] + ($middleRgb[1] - $startRgb[1]) * $localPosition);
                        $b = (int)($startRgb[2] + ($middleRgb[2] - $startRgb[2]) * $localPosition);
                    } else if ($middleRgb) {
                        // Second half: middle to end
                        $localPosition = ($position - 0.5) * 2; // Scale to 0-1
                        $r = (int)($middleRgb[0] + ($endRgb[0] - $middleRgb[0]) * $localPosition);
                        $g = (int)($middleRgb[1] + ($endRgb[1] - $middleRgb[1]) * $localPosition);
                        $b = (int)($middleRgb[2] + ($endRgb[2] - $middleRgb[2]) * $localPosition);
                    } else {
                        // No middle color: direct start to end
                        $r = (int)($startRgb[0] + ($endRgb[0] - $startRgb[0]) * $position);
                        $g = (int)($startRgb[1] + ($endRgb[1] - $startRgb[1]) * $position);
                        $b = (int)($startRgb[2] + ($endRgb[2] - $startRgb[2]) * $position);
                    }
                    
                    $gradientColor = imagecolorallocate($image, $r, $g, $b);
                    imagesetpixel($image, $x, $y, $gradientColor);
                }
            }
        }
    }

    private function applyRadialGradient($image, $startRgb, $endRgb, $width, $height, $middleRgb = null) {
        $centerX = $width / 2;
        $centerY = $height / 2;
        $maxDistance = sqrt($centerX * $centerX + $centerY * $centerY);
        
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $pixel = imagecolorat($image, $x, $y);
                $pixelRgb = imagecolorsforindex($image, $pixel);
                
                // Only apply gradient to dark pixels (foreground)
                if ($pixelRgb['red'] < 128) {
                    $distance = sqrt(($x - $centerX) ** 2 + ($y - $centerY) ** 2);
                    $position = min(1, $distance / $maxDistance);
                    
                    // Calculate color based on position and middle color
                    if ($middleRgb && $position <= 0.5) {
                        // First half: start to middle
                        $localPosition = $position * 2; // Scale to 0-1
                        $r = (int)($startRgb[0] + ($middleRgb[0] - $startRgb[0]) * $localPosition);
                        $g = (int)($startRgb[1] + ($middleRgb[1] - $startRgb[1]) * $localPosition);
                        $b = (int)($startRgb[2] + ($middleRgb[2] - $startRgb[2]) * $localPosition);
                    } else if ($middleRgb) {
                        // Second half: middle to end
                        $localPosition = ($position - 0.5) * 2; // Scale to 0-1
                        $r = (int)($middleRgb[0] + ($endRgb[0] - $middleRgb[0]) * $localPosition);
                        $g = (int)($middleRgb[1] + ($endRgb[1] - $middleRgb[1]) * $localPosition);
                        $b = (int)($middleRgb[2] + ($endRgb[2] - $middleRgb[2]) * $localPosition);
                    } else {
                        // No middle color: direct start to end
                        $r = (int)($startRgb[0] + ($endRgb[0] - $startRgb[0]) * $position);
                        $g = (int)($startRgb[1] + ($endRgb[1] - $startRgb[1]) * $position);
                        $b = (int)($startRgb[2] + ($endRgb[2] - $startRgb[2]) * $position);
                    }
                    
                    $gradientColor = imagecolorallocate($image, $r, $g, $b);
                    imagesetpixel($image, $x, $y, $gradientColor);
                }
            }
        }
    }

    private function applyConicGradient($image, $startRgb, $endRgb, $width, $height, $middleRgb = null) {
        $centerX = $width / 2;
        $centerY = $height / 2;
        
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                // Skip QR code area if coordinates are provided
                if ($qrX !== null && $qrY !== null && $qrWidth !== null && $qrHeight !== null) {
                    if ($x >= $qrX && $x < ($qrX + $qrWidth) && $y >= $qrY && $y < ($qrY + $qrHeight)) {
                        continue; // Skip this pixel - it's in the QR code area
                    }
                }
                
                $angle = atan2($y - $centerY, $x - $centerX);
                $position = ($angle + M_PI) / (2 * M_PI); // Normalize to 0-1
                
                $r = (int)($startRgb[0] + ($endRgb[0] - $startRgb[0]) * $position);
                $g = (int)($startRgb[1] + ($endRgb[1] - $startRgb[1]) * $position);
                $b = (int)($startRgb[2] + ($endRgb[2] - $startRgb[2]) * $position);
                
                $gradientColor = imagecolorallocate($canvas, $r, $g, $b);
                imagesetpixel($canvas, $x, $y, $gradientColor);
            }
        }
    }

    private function applyCustomEyePatterns($image, $options) {
        $width = imagesx($image);
        $height = imagesy($image);
        
        // Detect actual QR code finder patterns by analyzing the image
        $finderInfo = $this->detectFinderPatterns($image);
        
        if ($finderInfo) {
            // Use detected finder pattern information and make eyes appropriately sized
            $eyeSize = $finderInfo['size'] * 1.4; // 140% to properly cover the finder patterns
            $offsetAdjust = ($eyeSize - $finderInfo['size']) / 2; // Center the larger eye
            $eyes = [
                'tl' => ['x' => $finderInfo['tl']['x'] - $offsetAdjust, 'y' => $finderInfo['tl']['y'] - $offsetAdjust], // Center larger eye
                'tr' => ['x' => $finderInfo['tr']['x'] - $offsetAdjust, 'y' => $finderInfo['tr']['y'] - $offsetAdjust], // Center larger eye
                'bl' => ['x' => $finderInfo['bl']['x'] - $offsetAdjust, 'y' => $finderInfo['bl']['y'] - $offsetAdjust]  // Center larger eye
            ];
        } else {
            // Fallback to improved estimation
            // Estimate module size more accurately by scanning the image
            $moduleSize = $this->estimateModuleSize($image);
            $baseEyeSize = $moduleSize * 7; // Each eye is 7x7 modules
            
            // Make eyes appropriately sized to cover the original finder patterns
            $eyeSize = $baseEyeSize * 1.3; // Increase to 130% to ensure proper coverage
            
            // Define eye positions with precise pixel alignment
            // Adjust positioning to center the larger eyes over the original patterns
            $offsetAdjust = ($eyeSize - $baseEyeSize) / 2; // Center the larger eye
            $eyes = [
                'tl' => ['x' => -$offsetAdjust, 'y' => -$offsetAdjust], // Top-left: centered
                'tr' => ['x' => $width - $eyeSize + $offsetAdjust, 'y' => -$offsetAdjust], // Top-right: centered
                'bl' => ['x' => -$offsetAdjust, 'y' => $height - $eyeSize + $offsetAdjust] // Bottom-left: centered
            ];
        }
        
        foreach ($eyes as $position => $coords) {
            $this->drawCustomEye($image, $coords['x'], $coords['y'], $eyeSize, $options, $position);
        }
        
        return $image;
    }

    private function detectFinderPatterns($image) {
        $width = imagesx($image);
        $height = imagesy($image);
        
        // Look for the characteristic 1:1:3:1:1 pattern of QR finder patterns
        // Scan from top-left to find the first finder pattern
        $topLeft = $this->findFinderPattern($image, 0, 0, min($width/3, $height/3));
        
        if (!$topLeft) {
            return false; // Could not detect finder patterns
        }
        
        $finderSize = $topLeft['size'];
        
        // Estimate positions of other finder patterns based on the first one
        $topRight = [
            'x' => $width - $finderSize - $topLeft['x'],
            'y' => $topLeft['y']
        ];
        
        $bottomLeft = [
            'x' => $topLeft['x'],
            'y' => $height - $finderSize - $topLeft['y']
        ];
        
        return [
            'size' => $finderSize,
            'tl' => $topLeft,
            'tr' => $topRight,
            'bl' => $bottomLeft
        ];
    }

    private function findFinderPattern($image, $startX, $startY, $maxSearch) {
        $width = imagesx($image);
        $height = imagesy($image);
        
        // Look for black pixels that could be the start of a finder pattern
        for ($y = $startY; $y < min($startY + $maxSearch, $height - 20); $y++) {
            for ($x = $startX; $x < min($startX + $maxSearch, $width - 20); $x++) {
                $pixel = imagecolorat($image, $x, $y);
                $rgb = imagecolorsforindex($image, $pixel);
                
                // If we find a dark pixel, check if it's part of a finder pattern
                if ($rgb['red'] < 128) {
                    $patternSize = $this->checkFinderPattern($image, $x, $y);
                    if ($patternSize > 0) {
                        return [
                            'x' => $x,
                            'y' => $y,
                            'size' => $patternSize
                        ];
                    }
                }
            }
        }
        
        return false;
    }

    private function checkFinderPattern($image, $x, $y) {
        $width = imagesx($image);
        $height = imagesy($image);
        
        // Check if there's enough space for a minimum finder pattern
        if ($x + 21 >= $width || $y + 21 >= $height) {
            return 0;
        }
        
        // Look for a square black region that could be a finder pattern
        $size = 0;
        for ($testSize = 15; $testSize <= 50; $testSize += 2) {
            if ($x + $testSize >= $width || $y + $testSize >= $height) {
                break;
            }
            
            // Check if this forms a reasonable finder pattern
            $blackPixels = 0;
            $totalPixels = 0;
            
            for ($dy = 0; $dy < $testSize; $dy++) {
                for ($dx = 0; $dx < $testSize; $dx++) {
                    $pixel = imagecolorat($image, $x + $dx, $y + $dy);
                    $rgb = imagecolorsforindex($image, $pixel);
                    $totalPixels++;
                    
                    if ($rgb['red'] < 128) {
                        $blackPixels++;
                    }
                }
            }
            
            // A finder pattern should have a good ratio of black to white pixels
            $blackRatio = $blackPixels / $totalPixels;
            if ($blackRatio > 0.3 && $blackRatio < 0.7) {
                $size = $testSize;
            }
        }
        
        return $size;
    }

    private function estimateModuleSize($image) {
        $width = imagesx($image);
        $height = imagesy($image);
        
        // Scan the first row to find the approximate module size
        $transitions = 0;
        $lastPixelDark = false;
        
        for ($x = 1; $x < $width; $x++) {
            $pixel = imagecolorat($image, $x, 10); // Sample from row 10
            $rgb = imagecolorsforindex($image, $pixel);
            $isDark = $rgb['red'] < 128;
            
            if ($isDark !== $lastPixelDark) {
                $transitions++;
                $lastPixelDark = $isDark;
            }
        }
        
        // Estimate module size based on transitions
        if ($transitions > 0) {
            $estimatedModules = $transitions / 2; // Each module creates 2 transitions
            return $width / $estimatedModules;
        }
        
        // Fallback to original estimation
        return $width / 25;
    }

    private function drawCustomEye($image, $x, $y, $size, $options, $position) {
        $shape = $options['eye_shape'] ?? 'square';
        $style = $options['eye_style'] ?? 'solid';
        $eyeSize = $size; // Use the pre-calculated size (already includes 80% scaling)
        $rotation = deg2rad($options['eye_rotation'] ?? 0);
        
        // Get colors for this eye
        $outerColor = $this->getEyeColor($options, $position, 'outer');
        $innerColor = $this->getEyeColor($options, $position, 'inner');
        $centerColor = $this->getEyeColor($options, $position, 'center');
        $backgroundColor = $this->hexToRgb($options['eye_background_color'] ?? '#FFFFFF');
        
        // Create colors
        $outerGdColor = imagecolorallocate($image, $outerColor[0], $outerColor[1], $outerColor[2]);
        $innerGdColor = imagecolorallocate($image, $innerColor[0], $innerColor[1], $innerColor[2]);
        $centerGdColor = imagecolorallocate($image, $centerColor[0], $centerColor[1], $centerColor[2]);
        $bgGdColor = imagecolorallocate($image, $backgroundColor[0], $backgroundColor[1], $backgroundColor[2]);
        
        // Clear the eye area first
        $this->clearEyeArea($image, $x, $y, $eyeSize, $bgGdColor);
        
        // Apply shadow if enabled
        if (($options['eye_shadow_offset'] ?? 0) > 0) {
            $this->drawEyeShadow($image, $x, $y, $eyeSize, $shape, $options);
        }
        
        // Draw the eye based on shape
        switch ($shape) {
            case 'circle':
                $this->drawCircularEye($image, $x, $y, $eyeSize, $outerGdColor, $innerGdColor, $centerGdColor, $style, $options);
                break;
            case 'rounded':
                $this->drawRoundedEye($image, $x, $y, $eyeSize, $outerGdColor, $innerGdColor, $centerGdColor, $style, $options);
                break;
            case 'diamond':
                $this->drawDiamondEye($image, $x, $y, $eyeSize, $outerGdColor, $innerGdColor, $centerGdColor, $style, $options);
                break;
            case 'star':
                $this->drawStarEye($image, $x, $y, $eyeSize, $outerGdColor, $innerGdColor, $centerGdColor, $style, $options);
                break;
            case 'heart':
                $this->drawHeartEye($image, $x, $y, $eyeSize, $outerGdColor, $innerGdColor, $centerGdColor, $style, $options);
                break;
            case 'hexagon':
                $this->drawHexagonEye($image, $x, $y, $eyeSize, $outerGdColor, $innerGdColor, $centerGdColor, $style, $options);
                break;
            case 'leaf':
                $this->drawLeafEye($image, $x, $y, $eyeSize, $outerGdColor, $innerGdColor, $centerGdColor, $style, $options);
                break;
            default: // square
                $this->drawSquareEye($image, $x, $y, $eyeSize, $outerGdColor, $innerGdColor, $centerGdColor, $style, $options);
                break;
        }
        
        // Apply glow effect if enabled
        if (($options['eye_glow_intensity'] ?? 0) > 0) {
            $this->drawEyeGlow($image, $x, $y, $eyeSize, $outerColor, $options);
        }
    }

    private function getEyeColor($options, $position, $part) {
        // Use global eye colors only
        switch ($part) {
            case 'outer':
                return $this->hexToRgb($options['eye_outer_color'] ?? '#000000');
            case 'inner':
                return $this->hexToRgb($options['eye_inner_color'] ?? '#000000');
            case 'center':
                return $this->hexToRgb($options['eye_center_color'] ?? '#000000');
            default:
                return $this->hexToRgb('#000000');
        }
    }

    private function clearEyeArea($image, $x, $y, $size, $bgColor) {
        imagefilledrectangle($image, $x, $y, $x + $size, $y + $size, $bgColor);
    }

    private function drawSquareEye($image, $x, $y, $size, $outerColor, $innerColor, $centerColor, $style, $options) {
        $borderWidth = $options['eye_border_width'] ?? 0;
        
        // Outer ring (7x7)
        imagefilledrectangle($image, (int)$x, (int)$y, (int)($x + $size), (int)($y + $size), $outerColor);
        
        // Inner white space (5x5)
        $innerOffset = $size / 7;
        imagefilledrectangle($image, 
            (int)($x + $innerOffset), (int)($y + $innerOffset), 
            (int)($x + $size - $innerOffset), (int)($y + $size - $innerOffset), 
            imagecolorallocate($image, 255, 255, 255)
        );
        
        // Inner ring (3x3)
        $innerRingOffset = $size * 2 / 7;
        imagefilledrectangle($image, 
            (int)($x + $innerRingOffset), (int)($y + $innerRingOffset), 
            (int)($x + $size - $innerRingOffset), (int)($y + $size - $innerRingOffset), 
            $innerColor
        );
        
        // Center dot (1x1)
        $centerOffset = $size * 3 / 7;
        imagefilledrectangle($image, 
            (int)($x + $centerOffset), (int)($y + $centerOffset), 
            (int)($x + $size - $centerOffset), (int)($y + $size - $centerOffset), 
            $centerColor
        );
    }

    private function drawCircularEye($image, $x, $y, $size, $outerColor, $innerColor, $centerColor, $style, $options) {
        $centerX = (int)($x + $size / 2);
        $centerY = (int)($y + $size / 2);
        
        // Outer circle
        imagefilledellipse($image, $centerX, $centerY, (int)$size, (int)$size, $outerColor);
        
        // Inner white space
        $innerSize = (int)($size * 5 / 7);
        imagefilledellipse($image, $centerX, $centerY, $innerSize, $innerSize, imagecolorallocate($image, 255, 255, 255));
        
        // Inner circle
        $innerRingSize = (int)($size * 3 / 7);
        imagefilledellipse($image, $centerX, $centerY, $innerRingSize, $innerRingSize, $innerColor);
        
        // Center dot
        $centerSize = (int)($size * 1 / 7);
        imagefilledellipse($image, $centerX, $centerY, $centerSize, $centerSize, $centerColor);
    }

    private function drawRoundedEye($image, $x, $y, $size, $outerColor, $innerColor, $centerColor, $style, $options) {
        $radius = $size / 6; // Rounded corners
        
        // Outer rounded rectangle
        $this->drawRoundedRect($image, $x, $y, $x + $size, $y + $size, $radius, $outerColor, true);
        
        // Inner white space
        $innerOffset = $size / 7;
        $this->drawRoundedRect($image, 
            $x + $innerOffset, $y + $innerOffset, 
            $x + $size - $innerOffset, $y + $size - $innerOffset, 
            $radius * 0.7, imagecolorallocate($image, 255, 255, 255), true
        );
        
        // Inner rounded rectangle
        $innerRingOffset = $size * 2 / 7;
        $this->drawRoundedRect($image, 
            $x + $innerRingOffset, $y + $innerRingOffset, 
            $x + $size - $innerRingOffset, $y + $size - $innerRingOffset, 
            $radius * 0.5, $innerColor, true
        );
        
        // Center rounded rectangle
        $centerOffset = $size * 3 / 7;
        $this->drawRoundedRect($image, 
            $x + $centerOffset, $y + $centerOffset, 
            $x + $size - $centerOffset, $y + $size - $centerOffset, 
            $radius * 0.3, $centerColor, true
        );
    }

    private function drawDiamondEye($image, $x, $y, $size, $outerColor, $innerColor, $centerColor, $style, $options) {
        $centerX = $x + $size / 2;
        $centerY = $y + $size / 2;
        $halfSize = $size / 2;
        
        // Outer diamond
        $outerPoints = [
            $centerX, $y,           // Top
            $x + $size, $centerY,   // Right
            $centerX, $y + $size,   // Bottom
            $x, $centerY            // Left
        ];
        imagefilledpolygon($image, $outerPoints, 4, $outerColor);
        
        // Inner white space
        $innerOffset = $size / 7;
        $innerPoints = [
            $centerX, $y + $innerOffset,
            $x + $size - $innerOffset, $centerY,
            $centerX, $y + $size - $innerOffset,
            $x + $innerOffset, $centerY
        ];
        imagefilledpolygon($image, $innerPoints, 4, imagecolorallocate($image, 255, 255, 255));
        
        // Inner diamond
        $innerRingOffset = $size * 2 / 7;
        $innerRingPoints = [
            $centerX, $y + $innerRingOffset,
            $x + $size - $innerRingOffset, $centerY,
            $centerX, $y + $size - $innerRingOffset,
            $x + $innerRingOffset, $centerY
        ];
        imagefilledpolygon($image, $innerRingPoints, 4, $innerColor);
        
        // Center diamond
        $centerOffset = $size * 3 / 7;
        $centerPoints = [
            $centerX, $y + $centerOffset,
            $x + $size - $centerOffset, $centerY,
            $centerX, $y + $size - $centerOffset,
            $x + $centerOffset, $centerY
        ];
        imagefilledpolygon($image, $centerPoints, 4, $centerColor);
    }

    private function drawStarEye($image, $x, $y, $size, $outerColor, $innerColor, $centerColor, $style, $options) {
        $centerX = $x + $size / 2;
        $centerY = $y + $size / 2;
        $radius = $size / 2;
        
        // Create star points (5-pointed star)
        $points = [];
        for ($i = 0; $i < 10; $i++) {
            $angle = ($i * M_PI) / 5;
            $r = ($i % 2 == 0) ? $radius : $radius * 0.5;
            $points[] = $centerX + $r * cos($angle - M_PI/2);
            $points[] = $centerY + $r * sin($angle - M_PI/2);
        }
        
        // Draw outer star
        imagefilledpolygon($image, $points, 10, $outerColor);
        
        // Draw inner circle for contrast
        $innerRadius = $radius * 0.4;
        imagefilledellipse($image, $centerX, $centerY, $innerRadius * 2, $innerRadius * 2, $innerColor);
        
        // Draw center dot
        $centerRadius = $radius * 0.2;
        imagefilledellipse($image, $centerX, $centerY, $centerRadius * 2, $centerRadius * 2, $centerColor);
    }

    private function drawHeartEye($image, $x, $y, $size, $outerColor, $innerColor, $centerColor, $style, $options) {
        $centerX = $x + $size / 2;
        $centerY = $y + $size / 2;
        
        // Simplified heart shape using circles and triangle
        $heartSize = $size * 0.8;
        $circleRadius = $heartSize / 4;
        
        // Left circle of heart
        imagefilledellipse($image, $centerX - $circleRadius/2, $centerY - $circleRadius/2, $circleRadius, $circleRadius, $outerColor);
        
        // Right circle of heart
        imagefilledellipse($image, $centerX + $circleRadius/2, $centerY - $circleRadius/2, $circleRadius, $circleRadius, $outerColor);
        
        // Bottom triangle
        $trianglePoints = [
            $centerX - $circleRadius, $centerY,
            $centerX + $circleRadius, $centerY,
            $centerX, $centerY + $circleRadius
        ];
        imagefilledpolygon($image, $trianglePoints, 3, $outerColor);
        
        // Inner heart (smaller)
        $innerScale = 0.6;
        $innerCircleRadius = $circleRadius * $innerScale;
        imagefilledellipse($image, $centerX - $innerCircleRadius/2, $centerY - $innerCircleRadius/2, $innerCircleRadius, $innerCircleRadius, $innerColor);
        imagefilledellipse($image, $centerX + $innerCircleRadius/2, $centerY - $innerCircleRadius/2, $innerCircleRadius, $innerCircleRadius, $innerColor);
        
        $innerTrianglePoints = [
            $centerX - $innerCircleRadius, $centerY,
            $centerX + $innerCircleRadius, $centerY,
            $centerX, $centerY + $innerCircleRadius
        ];
        imagefilledpolygon($image, $innerTrianglePoints, 3, $innerColor);
        
        // Center dot
        imagefilledellipse($image, $centerX, $centerY, $size/10, $size/10, $centerColor);
    }

    private function drawHexagonEye($image, $x, $y, $size, $outerColor, $innerColor, $centerColor, $style, $options) {
        $centerX = $x + $size / 2;
        $centerY = $y + $size / 2;
        $radius = $size / 2;
        
        // Create hexagon points
        $points = [];
        for ($i = 0; $i < 6; $i++) {
            $angle = ($i * M_PI) / 3;
            $points[] = $centerX + $radius * cos($angle);
            $points[] = $centerY + $radius * sin($angle);
        }
        
        // Draw outer hexagon
        imagefilledpolygon($image, $points, 6, $outerColor);
        
        // Inner white space
        $innerRadius = $radius * 5 / 7;
        $innerPoints = [];
        for ($i = 0; $i < 6; $i++) {
            $angle = ($i * M_PI) / 3;
            $innerPoints[] = $centerX + $innerRadius * cos($angle);
            $innerPoints[] = $centerY + $innerRadius * sin($angle);
        }
        imagefilledpolygon($image, $innerPoints, 6, imagecolorallocate($image, 255, 255, 255));
        
        // Inner hexagon
        $innerRingRadius = $radius * 3 / 7;
        $innerRingPoints = [];
        for ($i = 0; $i < 6; $i++) {
            $angle = ($i * M_PI) / 3;
            $innerRingPoints[] = $centerX + $innerRingRadius * cos($angle);
            $innerRingPoints[] = $centerY + $innerRingRadius * sin($angle);
        }
        imagefilledpolygon($image, $innerRingPoints, 6, $innerColor);
        
        // Center hexagon
        $centerRadius = $radius * 1 / 7;
        $centerPoints = [];
        for ($i = 0; $i < 6; $i++) {
            $angle = ($i * M_PI) / 3;
            $centerPoints[] = $centerX + $centerRadius * cos($angle);
            $centerPoints[] = $centerY + $centerRadius * sin($angle);
        }
        imagefilledpolygon($image, $centerPoints, 6, $centerColor);
    }

    private function drawLeafEye($image, $x, $y, $size, $outerColor, $innerColor, $centerColor, $style, $options) {
        $centerX = $x + $size / 2;
        $centerY = $y + $size / 2;
        
        // Simplified leaf shape using ellipse and arc
        $leafWidth = $size * 0.8;
        $leafHeight = $size;
        
        // Main leaf body (ellipse)
        imagefilledellipse($image, $centerX, $centerY, $leafWidth, $leafHeight, $outerColor);
        
        // Inner leaf (smaller ellipse)
        $innerWidth = $leafWidth * 0.6;
        $innerHeight = $leafHeight * 0.6;
        imagefilledellipse($image, $centerX, $centerY, $innerWidth, $innerHeight, $innerColor);
        
        // Center vein (line)
        imageline($image, $centerX, $y + $size * 0.2, $centerX, $y + $size * 0.8, $centerColor);
        
        // Center dot
        imagefilledellipse($image, $centerX, $centerY, $size/8, $size/8, $centerColor);
    }

    private function drawEyeShadow($image, $x, $y, $size, $shape, $options) {
        $offset = $options['eye_shadow_offset'];
        $shadowColor = imagecolorallocatealpha($image, 0, 0, 0, 100);
        
        // Draw a simple shadow offset
        switch ($shape) {
            case 'circle':
                imagefilledellipse($image, $x + $size/2 + $offset, $y + $size/2 + $offset, $size, $size, $shadowColor);
                break;
            default:
                imagefilledrectangle($image, $x + $offset, $y + $offset, $x + $size + $offset, $y + $size + $offset, $shadowColor);
                break;
        }
    }

    private function drawEyeGlow($image, $x, $y, $size, $color, $options) {
        $intensity = $options['eye_glow_intensity'];
        
        for ($i = $intensity; $i > 0; $i--) {
            $alpha = (int)(127 * ($i / $intensity));
            $glowColor = imagecolorallocatealpha($image, $color[0], $color[1], $color[2], $alpha);
            
            imagefilledellipse($image, $x + $size/2, $y + $size/2, $size + $i*2, $size + $i*2, $glowColor);
        }
    }

    private function applyModuleShape($image, $options) {
        // Module shape transformation would require advanced image processing
        // For now, return the original image
        // TODO: Implement circle, rounded, diamond module shapes
        return $image;
    }

    private function applyGlowEffects($image, $options) {
        // Glow effects would require convolution filters
        // For now, return the original image
        // TODO: Implement glow effects using imagefilter or custom convolution
        return $image;
    }

    private function applyShadow($canvas, $qrImage, $qrX, $qrY, $shadow) {
        $shadowX = (int)($qrX + $shadow['offset_x']);
        $shadowY = (int)($qrY + $shadow['offset_y']);
        
        $shadowRgb = $this->hexToRgb($shadow['color']);
        $shadowColor = imagecolorallocatealpha(
            $canvas, 
            $shadowRgb[0], 
            $shadowRgb[1], 
            $shadowRgb[2], 
            (int)((1 - $shadow['opacity']) * 127)
        );
        
        // Create shadow by copying QR and applying color
        $width = imagesx($qrImage);
        $height = imagesy($qrImage);
        
        for ($x = 0; $x < $width; $x++) {
            for ($y = 0; $y < $height; $y++) {
                $pixel = imagecolorat($qrImage, $x, $y);
                $pixelRgb = imagecolorsforindex($qrImage, $pixel);
                
                if ($pixelRgb['red'] < 240) { // Non-background pixels
                    imagesetpixel($canvas, $shadowX + $x, $shadowY + $y, $shadowColor);
                }
            }
        }
    }

    private function applyFrame($canvas, $qrX, $qrY, $width, $height, $frame) {
        $frameRgb = $this->hexToRgb($frame['color']);
        $frameColor = imagecolorallocate($canvas, $frameRgb[0], $frameRgb[1], $frameRgb[2]);
        
        $frameWidth = (int)$frame['width'];
        $x1 = (int)($qrX - $frameWidth);
        $y1 = (int)($qrY - $frameWidth);
        $x2 = (int)($qrX + $width + $frameWidth);
        $y2 = (int)($qrY + $height + $frameWidth);
        
        // Draw frame based on style
        switch ($frame['style']) {
            case 'solid':
                imagesetthickness($canvas, $frameWidth);
                imagerectangle($canvas, $x1, $y1, $x2, $y2, $frameColor);
                break;
            case 'dashed':
                // TODO: Implement dashed frame
                imagesetthickness($canvas, $frameWidth);
                imagerectangle($canvas, $x1, $y1, $x2, $y2, $frameColor);
                break;
            case 'dotted':
                // TODO: Implement dotted frame
                imagesetthickness($canvas, $frameWidth);
                imagerectangle($canvas, $x1, $y1, $x2, $y2, $frameColor);
                break;
        }
    }

    private function addText($canvas, $textOptions, $x, $y, $position) {
        $text = $textOptions['text'];
        $fontSize = $textOptions['size'] ?? 16;
        $textRgb = $this->hexToRgb($textOptions['color'] ?? '#000000');
        $textColor = imagecolorallocate($canvas, $textRgb[0], $textRgb[1], $textRgb[2]);
        $alignment = $textOptions['alignment'] ?? 'center';
        $fontFamily = $textOptions['font'] ?? 'Arial';
        $bold = !empty($textOptions['bold']);
        $italic = !empty($textOptions['italic']);
        $underline = !empty($textOptions['underline']);
        $shadow = !empty($textOptions['shadow']);
        $outline = !empty($textOptions['outline']);
        $shadowColor = isset($textOptions['shadow_color']) ? $this->hexToRgb($textOptions['shadow_color']) : [40,40,40];
        $outlineColor = isset($textOptions['outline_color']) ? $this->hexToRgb($textOptions['outline_color']) : [255,255,255];
        $fontPath = $this->getFontPath($fontFamily, $bold, $italic);
        if ($fontPath && function_exists('imagettftext')) {
            $this->addTTFText($canvas, $text, $fontSize, $textColor, $fontPath, $x, $y, $position, $alignment, $bold, $italic, $underline, $shadow, $outline, $shadowColor, $outlineColor);
        } else {
            $this->addBuiltInText($canvas, $text, $fontSize, $textColor, $x, $y, $position, $alignment);
        }
    }
    
    private function getFontPath($fontFamily, $bold = false, $italic = false) {
        // Expanded font map with style variants
        $basePath = __DIR__ . '/../assets/fonts/';
        $fontMap = [
            'Arial' => [
                'regular' => '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
                'bold' => '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
                'italic' => '/usr/share/fonts/truetype/dejavu/DejaVuSans-Oblique.ttf',
                'bolditalic' => '/usr/share/fonts/truetype/dejavu/DejaVuSans-BoldOblique.ttf',
            ],
            'Helvetica' => [
                'regular' => '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
                'bold' => '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
                'italic' => '/usr/share/fonts/truetype/dejavu/DejaVuSans-Oblique.ttf',
                'bolditalic' => '/usr/share/fonts/truetype/dejavu/DejaVuSans-BoldOblique.ttf',
            ],
            'Times New Roman' => [
                'regular' => '/usr/share/fonts/truetype/dejavu/DejaVuSerif.ttf',
                'bold' => '/usr/share/fonts/truetype/dejavu/DejaVuSerif-Bold.ttf',
                'italic' => '/usr/share/fonts/truetype/dejavu/DejaVuSerif-Italic.ttf',
                'bolditalic' => '/usr/share/fonts/truetype/dejavu/DejaVuSerif-BoldItalic.ttf',
            ],
            'Georgia' => [
                'regular' => '/usr/share/fonts/truetype/dejavu/DejaVuSerif.ttf',
                'bold' => '/usr/share/fonts/truetype/dejavu/DejaVuSerif-Bold.ttf',
                'italic' => '/usr/share/fonts/truetype/dejavu/DejaVuSerif-Italic.ttf',
                'bolditalic' => '/usr/share/fonts/truetype/dejavu/DejaVuSerif-BoldItalic.ttf',
            ],
            'Verdana' => [
                'regular' => '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
                'bold' => '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
                'italic' => '/usr/share/fonts/truetype/dejavu/DejaVuSans-Oblique.ttf',
                'bolditalic' => '/usr/share/fonts/truetype/dejavu/DejaVuSans-BoldOblique.ttf',
            ],
            'Courier New' => [
                'regular' => '/usr/share/fonts/truetype/dejavu/DejaVuSansMono.ttf',
                'bold' => '/usr/share/fonts/truetype/dejavu/DejaVuSansMono-Bold.ttf',
                'italic' => '/usr/share/fonts/truetype/dejavu/DejaVuSansMono-Oblique.ttf',
                'bolditalic' => '/usr/share/fonts/truetype/dejavu/DejaVuSansMono-BoldOblique.ttf',
            ],
            // Google fonts (add more as needed, place TTFs in assets/fonts/)
            'Montserrat' => [
                'regular' => $basePath . 'Montserrat-Regular.ttf',
                'bold' => $basePath . 'Montserrat-Bold.ttf',
                'italic' => $basePath . 'Montserrat-Italic.ttf',
                'bolditalic' => $basePath . 'Montserrat-BoldItalic.ttf',
            ],
            'Roboto' => [
                'regular' => $basePath . 'Roboto-Regular.ttf',
                'bold' => $basePath . 'Roboto-Bold.ttf',
                'italic' => $basePath . 'Roboto-Italic.ttf',
                'bolditalic' => $basePath . 'Roboto-BoldItalic.ttf',
            ],
            'Lobster' => [
                'regular' => $basePath . 'Lobster-Regular.ttf',
            ],
            'Oswald' => [
                'regular' => $basePath . 'Oswald-Regular.ttf',
                'bold' => $basePath . 'Oswald-Bold.ttf',
            ],
            'Raleway' => [
                'regular' => $basePath . 'Raleway-Regular.ttf',
                'bold' => $basePath . 'Raleway-Bold.ttf',
            ],
            'Bebas Neue' => [
                'regular' => $basePath . 'BebasNeue-Regular.ttf',
            ],
            'Pacifico' => [
                'regular' => $basePath . 'Pacifico-Regular.ttf',
            ],
            'Caveat' => [
                'regular' => $basePath . 'Caveat-Regular.ttf',
            ],
            'Dancing Script' => [
                'regular' => $basePath . 'DancingScript-Regular.ttf',
            ],
            'Permanent Marker' => [
                'regular' => $basePath . 'PermanentMarker-Regular.ttf',
            ],
            'Orbitron' => [
                'regular' => $basePath . 'Orbitron-Regular.ttf',
            ],
            'Fjalla One' => [
                'regular' => $basePath . 'FjallaOne-Regular.ttf',
            ],
            'Shadows Into Light' => [
                'regular' => $basePath . 'ShadowsIntoLight-Regular.ttf',
            ],
            'Indie Flower' => [
                'regular' => $basePath . 'IndieFlower-Regular.ttf',
            ],
            // Add more fonts as needed
        ];
        $style = 'regular';
        if ($bold && $italic) $style = 'bolditalic';
        else if ($bold) $style = 'bold';
        else if ($italic) $style = 'italic';
        if (isset($fontMap[$fontFamily][$style]) && file_exists($fontMap[$fontFamily][$style])) {
            return $fontMap[$fontFamily][$style];
        }
        // Fallback to regular
        if (isset($fontMap[$fontFamily]['regular']) && file_exists($fontMap[$fontFamily]['regular'])) {
            return $fontMap[$fontFamily]['regular'];
        }
        // Fallback to DejaVuSans
        return '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf';
    }
    
    private function addTTFText($canvas, $text, $fontSize, $textColor, $fontPath, $x, $y, $position, $alignment, $bold = false, $italic = false, $underline = false, $shadow = false, $outline = false, $shadowColor = [40,40,40], $outlineColor = [255,255,255]) {
        $bbox = imagettfbbox($fontSize, 0, $fontPath, $text);
        $textWidth = $bbox[4] - $bbox[0];
        $textHeight = $bbox[1] - $bbox[7];
        $textX = $x;
        switch ($alignment) {
            case 'left':
                $textX = $x - ($textWidth / 2) + 20;
                break;
            case 'center':
                $textX = (int)($x - ($textWidth / 2));
                break;
            case 'right':
                $textX = $x - ($textWidth / 2) - 20;
                break;
        }
        if ($position === 'above') {
            $textY = (int)($y - abs($textHeight) - 10);
        } else {
            $textY = (int)($y + $fontSize + 10);
        }
        // Shadow (more visible)
        if ($shadow) {
            $shadowCol = imagecolorallocate($canvas, $shadowColor[0], $shadowColor[1], $shadowColor[2]);
            for ($i = 1; $i <= 3; $i++) {
                imagettftext($canvas, $fontSize, 0, $textX + $i, $textY + $i, $shadowCol, $fontPath, $text);
            }
        }
        // Outline (thicker)
        if ($outline) {
            $outlineCol = imagecolorallocate($canvas, $outlineColor[0], $outlineColor[1], $outlineColor[2]);
            for ($ox = -2; $ox <= 2; $ox++) {
                for ($oy = -2; $oy <= 2; $oy++) {
                    if ($ox !== 0 || $oy !== 0) {
                        imagettftext($canvas, $fontSize, 0, $textX + $ox, $textY + $oy, $outlineCol, $fontPath, $text);
                    }
                }
            }
        }
        // Bold (simulate if not using bold font)
        if ($bold && strpos($fontPath, 'Bold') === false) {
            for ($i = 1; $i <= 2; $i++) {
                imagettftext($canvas, $fontSize, 0, $textX + $i, $textY, $textColor, $fontPath, $text);
            }
        }
        // Italic (simulate if not using italic font)
        $angle = ($italic && strpos($fontPath, 'Italic') === false && strpos($fontPath, 'Oblique') === false) ? -15 : 0;
        imagettftext($canvas, $fontSize, $angle, $textX, $textY, $textColor, $fontPath, $text);
        // Underline
        if ($underline) {
            $underlineY = $textY + 4;
            imageline($canvas, $textX, $underlineY, $textX + $textWidth, $underlineY, $textColor);
        }
        // Removed debug red box
    }
    
    private function addBuiltInText($canvas, $text, $fontSize, $textColor, $x, $y, $position, $alignment) {
        // Fallback to built-in fonts when TTF is not available
        
        // Map font size to built-in font (1-5, where 5 is largest)
        $font = 5; // Default to largest built-in font
        if ($fontSize <= 10) {
            $font = 2;
        } elseif ($fontSize <= 14) {
            $font = 3;
        } elseif ($fontSize <= 18) {
            $font = 4;
        } else {
            $font = 5;
        }
        
        // For larger text, draw multiple times to create a bolder effect
        $bold = $fontSize > 20;
        
        // Calculate text dimensions
        $textWidth = imagefontwidth($font) * strlen($text);
        $textHeight = imagefontheight($font);
        
        // Calculate text position based on alignment
        $textX = $x;
        switch ($alignment) {
            case 'left':
                $textX = $x - ($textWidth / 2) + 20;
                break;
            case 'center':
                $textX = (int)($x - ($textWidth / 2));
                break;
            case 'right':
                $textX = $x - ($textWidth / 2) - 20;
                break;
        }
        
        // Adjust Y position based on position
        if ($position === 'above') {
            $textY = (int)($y - $textHeight - 10);
        } else {
            $textY = (int)($y + 10);
        }
        
        // Draw text (with bold effect if needed)
        if ($bold) {
            // Draw text multiple times with slight offsets for bold effect
            for ($offsetX = 0; $offsetX <= 1; $offsetX++) {
                for ($offsetY = 0; $offsetY <= 1; $offsetY++) {
                    imagestring($canvas, $font, $textX + $offsetX, $textY + $offsetY, $text, $textColor);
                }
            }
        } else {
            imagestring($canvas, $font, $textX, $textY, $text, $textColor);
        }
    }

    private function ensureQRBackground($qrImage, $backgroundColor) {
        $width = imagesx($qrImage);
        $height = imagesy($qrImage);
        $backgroundRgb = $this->hexToRgb($backgroundColor);
        
        // Create a new image with solid background
        $newImage = imagecreatetruecolor($width, $height);
        $bgColor = imagecolorallocate($newImage, $backgroundRgb[0], $backgroundRgb[1], $backgroundRgb[2]);
        imagefill($newImage, 0, 0, $bgColor);
        
        // Copy the QR code onto the solid background
        imagecopy($newImage, $qrImage, 0, 0, 0, 0, $width, $height);
        
        // Copy the result back to the original image
        imagecopy($qrImage, $newImage, 0, 0, 0, 0, $width, $height);
        
        imagedestroy($newImage);
    }

    private function imageToDataUrl($image) {
        ob_start();
        imagepng($image);
        $imageData = ob_get_contents();
        ob_end_clean();
        
        return 'data:image/png;base64,' . base64_encode($imageData);
    }

    private function applyBackgroundGradient($canvas, $options, $qrX = null, $qrY = null, $qrWidth = null, $qrHeight = null) {
        $width = imagesx($canvas);
        $height = imagesy($canvas);
        
        $startRgb = $this->hexToRgb($options['bg_gradient_start'] ?? '#ff7e5f');
        $middleRgb = $this->hexToRgb($options['bg_gradient_middle'] ?? '#feb47b');
        $endRgb = $this->hexToRgb($options['bg_gradient_end'] ?? '#ff6b6b');
        $type = $options['bg_gradient_type'] ?? 'linear';
        $angle = intval($options['bg_gradient_angle'] ?? 135);
        
        switch ($type) {
            case 'linear':
                $this->applyLinearBackgroundGradient($canvas, $startRgb, $middleRgb, $endRgb, $angle, $width, $height, $qrX, $qrY, $qrWidth, $qrHeight);
                break;
            case 'radial':
                $this->applyRadialBackgroundGradient($canvas, $startRgb, $endRgb, $width, $height, $qrX, $qrY, $qrWidth, $qrHeight);
                break;
            case 'conic':
                $this->applyConicBackgroundGradient($canvas, $startRgb, $endRgb, $width, $height, $qrX, $qrY, $qrWidth, $qrHeight);
                break;
        }
        
        return $canvas;
    }

    private function applyLinearBackgroundGradient($canvas, $startRgb, $middleRgb, $endRgb, $angle, $width, $height, $qrX = null, $qrY = null, $qrWidth = null, $qrHeight = null) {
        $angleRad = deg2rad($angle);
        
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                // Skip QR code area if coordinates are provided
                if ($qrX !== null && $qrY !== null && $qrWidth !== null && $qrHeight !== null) {
                    if ($x >= $qrX && $x < ($qrX + $qrWidth) && $y >= $qrY && $y < ($qrY + $qrHeight)) {
                        continue; // Skip this pixel - it's in the QR code area
                    }
                }
                
                // Calculate gradient position based on angle
                $normalizedX = $x / $width;
                $normalizedY = $y / $height;
                
                $position = ($normalizedX * cos($angleRad) + $normalizedY * sin($angleRad));
                $position = max(0, min(1, $position));
                
                // Use three-color gradient
                if ($position <= 0.5) {
                    $localPos = $position * 2;
                    $r = (int)($startRgb[0] + ($middleRgb[0] - $startRgb[0]) * $localPos);
                    $g = (int)($startRgb[1] + ($middleRgb[1] - $startRgb[1]) * $localPos);
                    $b = (int)($startRgb[2] + ($middleRgb[2] - $startRgb[2]) * $localPos);
                } else {
                    $localPos = ($position - 0.5) * 2;
                    $r = (int)($middleRgb[0] + ($endRgb[0] - $middleRgb[0]) * $localPos);
                    $g = (int)($middleRgb[1] + ($endRgb[1] - $middleRgb[1]) * $localPos);
                    $b = (int)($middleRgb[2] + ($endRgb[2] - $middleRgb[2]) * $localPos);
                }
                
                $gradientColor = imagecolorallocate($canvas, $r, $g, $b);
                imagesetpixel($canvas, $x, $y, $gradientColor);
            }
        }
    }

    private function applyRadialBackgroundGradient($canvas, $startRgb, $endRgb, $width, $height, $qrX = null, $qrY = null, $qrWidth = null, $qrHeight = null) {
        $centerX = $width / 2;
        $centerY = $height / 2;
        $maxDistance = sqrt($centerX * $centerX + $centerY * $centerY);
        
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                // Skip QR code area if coordinates are provided
                if ($qrX !== null && $qrY !== null && $qrWidth !== null && $qrHeight !== null) {
                    if ($x >= $qrX && $x < ($qrX + $qrWidth) && $y >= $qrY && $y < ($qrY + $qrHeight)) {
                        continue; // Skip this pixel - it's in the QR code area
                    }
                }
                
                $distance = sqrt(($x - $centerX) ** 2 + ($y - $centerY) ** 2);
                $position = min(1, $distance / $maxDistance);
                
                $r = (int)($startRgb[0] + ($endRgb[0] - $startRgb[0]) * $position);
                $g = (int)($startRgb[1] + ($endRgb[1] - $startRgb[1]) * $position);
                $b = (int)($startRgb[2] + ($endRgb[2] - $startRgb[2]) * $position);
                
                $gradientColor = imagecolorallocate($canvas, $r, $g, $b);
                imagesetpixel($canvas, $x, $y, $gradientColor);
            }
        }
    }

    private function applyConicBackgroundGradient($canvas, $startRgb, $endRgb, $width, $height, $qrX = null, $qrY = null, $qrWidth = null, $qrHeight = null) {
        $centerX = $width / 2;
        $centerY = $height / 2;
        
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                // Skip QR code area if coordinates are provided
                if ($qrX !== null && $qrY !== null && $qrWidth !== null && $qrHeight !== null) {
                    if ($x >= $qrX && $x < ($qrX + $qrWidth) && $y >= $qrY && $y < ($qrY + $qrHeight)) {
                        continue; // Skip this pixel - it's in the QR code area
                    }
                }
                
                $angle = atan2($y - $centerY, $x - $centerX);
                $position = ($angle + M_PI) / (2 * M_PI); // Normalize to 0-1
                
                $r = (int)($startRgb[0] + ($endRgb[0] - $startRgb[0]) * $position);
                $g = (int)($startRgb[1] + ($endRgb[1] - $startRgb[1]) * $position);
                $b = (int)($startRgb[2] + ($endRgb[2] - $startRgb[2]) * $position);
                
                $gradientColor = imagecolorallocate($canvas, $r, $g, $b);
                imagesetpixel($canvas, $x, $y, $gradientColor);
            }
        }
    }

    private function applyEnhancedBorder($canvas, $qrX, $qrY, $qrWidth, $qrHeight, $options) {
        $borderWidth = intval($options['border_width'] ?? 2);
        $borderStyle = $options['border_style'] ?? 'solid';
        $borderPattern = $options['border_pattern'] ?? 'uniform';
        $borderRadiusStyle = $options['border_radius_style'] ?? 'none';
        $primaryColor = $this->hexToRgb($options['border_color_primary'] ?? '#0d6efd');
        $secondaryColor = $this->hexToRgb($options['border_color_secondary'] ?? '#6610f2');
        $accentColor = $this->hexToRgb($options['border_color_accent'] ?? '#d63384');
        $glowIntensity = intval($options['border_glow_intensity'] ?? 0);
        $shadowOffset = intval($options['border_shadow_offset'] ?? 2);
        
        // Calculate border coordinates
        $borderX = $qrX - $borderWidth;
        $borderY = $qrY - $borderWidth;
        $borderEndX = $qrX + $qrWidth + $borderWidth;
        $borderEndY = $qrY + $qrHeight + $borderWidth;
        
        // Apply shadow effect first if enabled
        if ($shadowOffset > 0) {
            $this->drawBorderShadow($canvas, $borderX, $borderY, $borderEndX, $borderEndY, $shadowOffset, $borderRadiusStyle);
        }
        
        // Apply glow effect if enabled
        if ($glowIntensity > 0) {
            $this->drawBorderGlow($canvas, $borderX, $borderY, $borderEndX, $borderEndY, $primaryColor, $glowIntensity, $borderRadiusStyle);
        }
        
        // Apply corner radius if specified
        if ($borderRadiusStyle !== 'none') {
            $this->drawRoundedBorder($canvas, $borderX, $borderY, $borderEndX, $borderEndY, $borderWidth, $borderStyle, $borderPattern, $borderRadiusStyle, $primaryColor, $secondaryColor, $accentColor);
        } else {
            // Draw regular border based on pattern
            $this->drawBorderPattern($canvas, $borderX, $borderY, $borderEndX, $borderEndY, $borderWidth, $borderStyle, $borderPattern, $primaryColor, $secondaryColor, $accentColor);
        }
        
        return $canvas;
    }

    private function drawBorderShadow($canvas, $x1, $y1, $x2, $y2, $offset, $radiusStyle) {
        $shadowColor = imagecolorallocatealpha($canvas, 0, 0, 0, 100); // Semi-transparent black
        
        $shadowX1 = $x1 + $offset;
        $shadowY1 = $y1 + $offset;
        $shadowX2 = $x2 + $offset;
        $shadowY2 = $y2 + $offset;
        
        if ($radiusStyle !== 'none') {
            $this->drawRoundedRect($canvas, $shadowX1, $shadowY1, $shadowX2, $shadowY2, $this->getCornerRadius($radiusStyle), $shadowColor, true);
        } else {
            imagefilledrectangle($canvas, $shadowX1, $shadowY1, $shadowX2, $shadowY2, $shadowColor);
        }
    }

    private function drawBorderGlow($canvas, $x1, $y1, $x2, $y2, $color, $intensity, $radiusStyle) {
        if ($intensity <= 0) return;
        
        // Create a proper glow effect with smooth fade
        for ($i = $intensity; $i > 0; $i--) {
            // Calculate fade: outer layers are more transparent
            $fadeRatio = $i / $intensity; // 1.0 at outermost, approaching 0 at innermost
            $alpha = (int)(127 * pow($fadeRatio, 1.5)); // Smoother fade curve
            
            // Ensure alpha is within valid range (0-127, where 127 = fully transparent)
            $alpha = max(15, min(127, $alpha));
            
            $glowColor = imagecolorallocatealpha($canvas, $color[0], $color[1], $color[2], $alpha);
            
            $glowX1 = $x1 - $i;
            $glowY1 = $y1 - $i;
            $glowX2 = $x2 + $i;
            $glowY2 = $y2 + $i;
            
            if ($radiusStyle !== 'none') {
                $radius = $this->getCornerRadius($radiusStyle) + $i;
                $this->drawRoundedRect($canvas, $glowX1, $glowY1, $glowX2, $glowY2, $radius, $glowColor, false);
            } else {
                // Draw a single rectangle outline for each glow layer
                imagerectangle($canvas, $glowX1, $glowY1, $glowX2, $glowY2, $glowColor);
            }
        }
        
        // Add an inner bright core for more realistic glow
        if ($intensity > 2) {
            $coreAlpha = 10; // More opaque core
            $coreColor = imagecolorallocatealpha($canvas, $color[0], $color[1], $color[2], $coreAlpha);
            
            if ($radiusStyle !== 'none') {
                $radius = $this->getCornerRadius($radiusStyle);
                $this->drawRoundedRect($canvas, $x1, $y1, $x2, $y2, $radius, $coreColor, false);
            } else {
                imagerectangle($canvas, $x1, $y1, $x2, $y2, $coreColor);
            }
        }
    }

    private function drawBorderPattern($canvas, $x1, $y1, $x2, $y2, $width, $style, $pattern, $primaryColor, $secondaryColor, $accentColor) {
        switch ($pattern) {
            case 'uniform':
                $this->drawFullBorder($canvas, $x1, $y1, $x2, $y2, $width, $style, $primaryColor, $secondaryColor);
                break;
            case 'top-only':
                $this->drawBorderSide($canvas, $x1, $y1, $x2, $y1, $width, $style, $primaryColor, $secondaryColor, 'horizontal');
                break;
            case 'bottom-only':
                $this->drawBorderSide($canvas, $x1, $y2, $x2, $y2, $width, $style, $primaryColor, $secondaryColor, 'horizontal');
                break;
            case 'left-right':
                $this->drawBorderSide($canvas, $x1, $y1, $x1, $y2, $width, $style, $primaryColor, $secondaryColor, 'vertical');
                $this->drawBorderSide($canvas, $x2, $y1, $x2, $y2, $width, $style, $primaryColor, $secondaryColor, 'vertical');
                break;
            case 'corners':
                $this->drawCornerBorders($canvas, $x1, $y1, $x2, $y2, $width, $primaryColor, $accentColor);
                break;
            default:
                $this->drawFullBorder($canvas, $x1, $y1, $x2, $y2, $width, $style, $primaryColor, $secondaryColor);
        }
    }

    private function drawFullBorder($canvas, $x1, $y1, $x2, $y2, $width, $style, $primaryColor, $secondaryColor) {
        switch ($style) {
            case 'solid':
                $borderColor = imagecolorallocate($canvas, $primaryColor[0], $primaryColor[1], $primaryColor[2]);
                for ($i = 0; $i < $width; $i++) {
                    imagerectangle($canvas, $x1 - $i, $y1 - $i, $x2 + $i, $y2 + $i, $borderColor);
                }
                break;
            case 'dashed':
                $this->drawDashedBorder($canvas, $x1, $y1, $x2, $y2, imagecolorallocate($canvas, $primaryColor[0], $primaryColor[1], $primaryColor[2]), $width);
                break;
            case 'dotted':
                $this->drawDottedBorder($canvas, $x1, $y1, $x2, $y2, imagecolorallocate($canvas, $primaryColor[0], $primaryColor[1], $primaryColor[2]), $width);
                break;
            case 'double':
                $this->drawDoubleBorder($canvas, $x1, $y1, $x2, $y2, $primaryColor, $secondaryColor, $width);
                break;
            case 'groove':
                $this->drawGrooveBorder($canvas, $x1, $y1, $x2, $y2, $primaryColor, $width);
                break;
            case 'ridge':
                $this->drawRidgeBorder($canvas, $x1, $y1, $x2, $y2, $primaryColor, $width);
                break;
            case 'gradient':
                $this->drawGradientBorder($canvas, $x1, $y1, $x2, $y2, $primaryColor, $secondaryColor, $width);
                break;
            case 'neon':
                $this->drawNeonBorder($canvas, $x1, $y1, $x2, $y2, $primaryColor, $secondaryColor, $width);
                break;
        }
    }

    private function drawBorderSide($canvas, $x1, $y1, $x2, $y2, $width, $style, $primaryColor, $secondaryColor, $orientation) {
        $borderColor = imagecolorallocate($canvas, $primaryColor[0], $primaryColor[1], $primaryColor[2]);
        
        for ($i = 0; $i < $width; $i++) {
            if ($orientation === 'horizontal') {
                imageline($canvas, $x1, $y1 - $i, $x2, $y2 - $i, $borderColor);
                imageline($canvas, $x1, $y1 + $i, $x2, $y2 + $i, $borderColor);
            } else {
                imageline($canvas, $x1 - $i, $y1, $x2 - $i, $y2, $borderColor);
                imageline($canvas, $x1 + $i, $y1, $x2 + $i, $y2, $borderColor);
            }
        }
    }

    private function drawCornerBorders($canvas, $x1, $y1, $x2, $y2, $width, $primaryColor, $accentColor) {
        $cornerLength = min(($x2 - $x1) / 4, ($y2 - $y1) / 4, 30); // Corner length
        $primaryBorderColor = imagecolorallocate($canvas, $primaryColor[0], $primaryColor[1], $primaryColor[2]);
        $accentBorderColor = imagecolorallocate($canvas, $accentColor[0], $accentColor[1], $accentColor[2]);
        
        // Top-left corner
        for ($i = 0; $i < $width; $i++) {
            imageline($canvas, $x1 - $i, $y1 - $i, $x1 - $i + $cornerLength, $y1 - $i, $primaryBorderColor);
            imageline($canvas, $x1 - $i, $y1 - $i, $x1 - $i, $y1 - $i + $cornerLength, $accentBorderColor);
        }
        
        // Top-right corner
        for ($i = 0; $i < $width; $i++) {
            imageline($canvas, $x2 + $i, $y1 - $i, $x2 + $i - $cornerLength, $y1 - $i, $primaryBorderColor);
            imageline($canvas, $x2 + $i, $y1 - $i, $x2 + $i, $y1 - $i + $cornerLength, $accentBorderColor);
        }
        
        // Bottom-left corner
        for ($i = 0; $i < $width; $i++) {
            imageline($canvas, $x1 - $i, $y2 + $i, $x1 - $i + $cornerLength, $y2 + $i, $primaryBorderColor);
            imageline($canvas, $x1 - $i, $y2 + $i, $x1 - $i, $y2 + $i - $cornerLength, $accentBorderColor);
        }
        
        // Bottom-right corner
        for ($i = 0; $i < $width; $i++) {
            imageline($canvas, $x2 + $i, $y2 + $i, $x2 + $i - $cornerLength, $y2 + $i, $primaryBorderColor);
            imageline($canvas, $x2 + $i, $y2 + $i, $x2 + $i, $y2 + $i - $cornerLength, $accentBorderColor);
        }
    }

    private function drawRoundedBorder($canvas, $x1, $y1, $x2, $y2, $width, $style, $pattern, $radiusStyle, $primaryColor, $secondaryColor, $accentColor) {
        $radius = $this->getCornerRadius($radiusStyle);
        $borderColor = imagecolorallocate($canvas, $primaryColor[0], $primaryColor[1], $primaryColor[2]);
        
        for ($i = 0; $i < $width; $i++) {
            $this->drawRoundedRect($canvas, $x1 - $i, $y1 - $i, $x2 + $i, $y2 + $i, $radius + $i, $borderColor, false);
        }
    }

    private function getCornerRadius($radiusStyle) {
        switch ($radiusStyle) {
            case 'xs': return 2;
            case 'sm': return 4;
            case 'md': return 8;
            case 'lg': return 12;
            case 'xl': return 16;
            case 'round': return 50; // Large radius for fully rounded
            default: return 0;
        }
    }

    private function drawRoundedRect($canvas, $x1, $y1, $x2, $y2, $radius, $color, $filled = false) {
        if ($radius <= 0) {
            if ($filled) {
                imagefilledrectangle($canvas, $x1, $y1, $x2, $y2, $color);
            } else {
                imagerectangle($canvas, $x1, $y1, $x2, $y2, $color);
            }
            return;
        }
        
        // Draw rounded rectangle using arcs and lines
        if ($filled) {
            // Fill the main rectangle
            imagefilledrectangle($canvas, $x1 + $radius, $y1, $x2 - $radius, $y2, $color);
            imagefilledrectangle($canvas, $x1, $y1 + $radius, $x1 + $radius, $y2 - $radius, $color);
            imagefilledrectangle($canvas, $x2 - $radius, $y1 + $radius, $x2, $y2 - $radius, $color);
            
            // Fill the corners with arcs
            imagefilledellipse($canvas, $x1 + $radius, $y1 + $radius, $radius * 2, $radius * 2, $color);
            imagefilledellipse($canvas, $x2 - $radius, $y1 + $radius, $radius * 2, $radius * 2, $color);
            imagefilledellipse($canvas, $x1 + $radius, $y2 - $radius, $radius * 2, $radius * 2, $color);
            imagefilledellipse($canvas, $x2 - $radius, $y2 - $radius, $radius * 2, $radius * 2, $color);
        } else {
            // Draw lines for the sides
            imageline($canvas, $x1 + $radius, $y1, $x2 - $radius, $y1, $color); // Top
            imageline($canvas, $x1 + $radius, $y2, $x2 - $radius, $y2, $color); // Bottom
            imageline($canvas, $x1, $y1 + $radius, $x1, $y2 - $radius, $color); // Left
            imageline($canvas, $x2, $y1 + $radius, $x2, $y2 - $radius, $color); // Right
            
            // Draw corner arcs
            imagearc($canvas, $x1 + $radius, $y1 + $radius, $radius * 2, $radius * 2, 180, 270, $color);
            imagearc($canvas, $x2 - $radius, $y1 + $radius, $radius * 2, $radius * 2, 270, 360, $color);
            imagearc($canvas, $x1 + $radius, $y2 - $radius, $radius * 2, $radius * 2, 90, 180, $color);
            imagearc($canvas, $x2 - $radius, $y2 - $radius, $radius * 2, $radius * 2, 0, 90, $color);
        }
    }

    private function drawDottedBorder($canvas, $x1, $y1, $x2, $y2, $color, $width) {
        $dotSize = 2;
        $gapSize = 3;
        
        // Top border
        for ($x = $x1; $x < $x2; $x += $dotSize + $gapSize) {
            for ($i = 0; $i < $width; $i++) {
                imagefilledellipse($canvas, $x, $y1 - $i, $dotSize, $dotSize, $color);
            }
        }
        
        // Bottom border
        for ($x = $x1; $x < $x2; $x += $dotSize + $gapSize) {
            for ($i = 0; $i < $width; $i++) {
                imagefilledellipse($canvas, $x, $y2 + $i, $dotSize, $dotSize, $color);
            }
        }
        
        // Left border
        for ($y = $y1; $y < $y2; $y += $dotSize + $gapSize) {
            for ($i = 0; $i < $width; $i++) {
                imagefilledellipse($canvas, $x1 - $i, $y, $dotSize, $dotSize, $color);
            }
        }
        
        // Right border
        for ($y = $y1; $y < $y2; $y += $dotSize + $gapSize) {
            for ($i = 0; $i < $width; $i++) {
                imagefilledellipse($canvas, $x2 + $i, $y, $dotSize, $dotSize, $color);
            }
        }
    }

    private function drawDoubleBorder($canvas, $x1, $y1, $x2, $y2, $primaryColor, $secondaryColor, $width) {
        $outerColor = imagecolorallocate($canvas, $primaryColor[0], $primaryColor[1], $primaryColor[2]);
        $innerColor = imagecolorallocate($canvas, $secondaryColor[0], $secondaryColor[1], $secondaryColor[2]);
        
        $outerWidth = max(1, $width / 2);
        $innerWidth = max(1, $width - $outerWidth - 2);
        
        // Draw outer border
        for ($i = 0; $i < $outerWidth; $i++) {
            imagerectangle($canvas, $x1 - $i, $y1 - $i, $x2 + $i, $y2 + $i, $outerColor);
        }
        
        // Draw inner border
        for ($i = 0; $i < $innerWidth; $i++) {
            imagerectangle($canvas, $x1 + $outerWidth + 2 + $i, $y1 + $outerWidth + 2 + $i, 
                         $x2 - $outerWidth - 2 - $i, $y2 - $outerWidth - 2 - $i, $innerColor);
        }
    }

    private function drawGrooveBorder($canvas, $x1, $y1, $x2, $y2, $primaryColor, $width) {
        // Create darker and lighter versions of the primary color
        $darkColor = [
            max(0, $primaryColor[0] - 60),
            max(0, $primaryColor[1] - 60),
            max(0, $primaryColor[2] - 60)
        ];
        $lightColor = [
            min(255, $primaryColor[0] + 60),
            min(255, $primaryColor[1] + 60),
            min(255, $primaryColor[2] + 60)
        ];
        
        $darkBorderColor = imagecolorallocate($canvas, $darkColor[0], $darkColor[1], $darkColor[2]);
        $lightBorderColor = imagecolorallocate($canvas, $lightColor[0], $lightColor[1], $lightColor[2]);
        
        $halfWidth = max(1, $width / 2);
        
        // Draw dark outer border
        for ($i = 0; $i < $halfWidth; $i++) {
            imagerectangle($canvas, $x1 - $i, $y1 - $i, $x2 + $i, $y2 + $i, $darkBorderColor);
        }
        
        // Draw light inner border
        for ($i = 0; $i < $halfWidth; $i++) {
            imagerectangle($canvas, $x1 + $halfWidth + $i, $y1 + $halfWidth + $i, 
                         $x2 - $halfWidth - $i, $y2 - $halfWidth - $i, $lightBorderColor);
        }
    }

    private function drawRidgeBorder($canvas, $x1, $y1, $x2, $y2, $primaryColor, $width) {
        // Ridge is the opposite of groove
        $darkColor = [
            max(0, $primaryColor[0] - 60),
            max(0, $primaryColor[1] - 60),
            max(0, $primaryColor[2] - 60)
        ];
        $lightColor = [
            min(255, $primaryColor[0] + 60),
            min(255, $primaryColor[1] + 60),
            min(255, $primaryColor[2] + 60)
        ];
        
        $darkBorderColor = imagecolorallocate($canvas, $darkColor[0], $darkColor[1], $darkColor[2]);
        $lightBorderColor = imagecolorallocate($canvas, $lightColor[0], $lightColor[1], $lightColor[2]);
        
        $halfWidth = max(1, $width / 2);
        
        // Draw light outer border
        for ($i = 0; $i < $halfWidth; $i++) {
            imagerectangle($canvas, $x1 - $i, $y1 - $i, $x2 + $i, $y2 + $i, $lightBorderColor);
        }
        
        // Draw dark inner border
        for ($i = 0; $i < $halfWidth; $i++) {
            imagerectangle($canvas, $x1 + $halfWidth + $i, $y1 + $halfWidth + $i, 
                         $x2 - $halfWidth - $i, $y2 - $halfWidth - $i, $darkBorderColor);
        }
    }

    private function drawNeonBorder($canvas, $x1, $y1, $x2, $y2, $primaryColor, $secondaryColor, $width) {
        // Create a bright neon effect with multiple colored layers
        $neonColors = [
            imagecolorallocate($canvas, $primaryColor[0], $primaryColor[1], $primaryColor[2]),
            imagecolorallocate($canvas, $secondaryColor[0], $secondaryColor[1], $secondaryColor[2]),
            imagecolorallocate($canvas, 255, 255, 255) // White core
        ];
        
        // Draw multiple layers for neon effect
        for ($layer = $width; $layer > 0; $layer--) {
            $colorIndex = min(count($neonColors) - 1, $width - $layer);
            $color = $neonColors[$colorIndex];
            
            imagerectangle($canvas, $x1 - $layer, $y1 - $layer, $x2 + $layer, $y2 + $layer, $color);
            
            // Add some transparency for outer layers
            if ($layer > 2) {
                $alpha = (int)(127 * (1 - ($layer / $width) * 0.7));
                $transparentColor = imagecolorallocatealpha($canvas, $primaryColor[0], $primaryColor[1], $primaryColor[2], $alpha);
                imagerectangle($canvas, $x1 - $layer - 1, $y1 - $layer - 1, $x2 + $layer + 1, $y2 + $layer + 1, $transparentColor);
            }
        }
    }

    private function drawDashedBorder($canvas, $x1, $y1, $x2, $y2, $color, $width) {
        $dashLength = 10;
        $gapLength = 5;
        
        // Top border
        for ($x = $x1; $x < $x2; $x += $dashLength + $gapLength) {
            $endX = min($x + $dashLength, $x2);
            for ($i = 0; $i < $width; $i++) {
                imageline($canvas, $x, $y1 - $i, $endX, $y1 - $i, $color);
            }
        }
        
        // Bottom border
        for ($x = $x1; $x < $x2; $x += $dashLength + $gapLength) {
            $endX = min($x + $dashLength, $x2);
            for ($i = 0; $i < $width; $i++) {
                imageline($canvas, $x, $y2 + $i, $endX, $y2 + $i, $color);
            }
        }
        
        // Left border
        for ($y = $y1; $y < $y2; $y += $dashLength + $gapLength) {
            $endY = min($y + $dashLength, $y2);
            for ($i = 0; $i < $width; $i++) {
                imageline($canvas, $x1 - $i, $y, $x1 - $i, $endY, $color);
            }
        }
        
        // Right border
        for ($y = $y1; $y < $y2; $y += $dashLength + $gapLength) {
            $endY = min($y + $dashLength, $y2);
            for ($i = 0; $i < $width; $i++) {
                imageline($canvas, $x2 + $i, $y, $x2 + $i, $endY, $color);
            }
        }
    }

    private function drawGradientBorder($canvas, $x1, $y1, $x2, $y2, $startRgb, $endRgb, $width) {
        $totalLength = 2 * ($x2 - $x1) + 2 * ($y2 - $y1);
        $currentLength = 0;
        
        // Top border
        for ($x = $x1; $x <= $x2; $x++) {
            $position = $currentLength / $totalLength;
            $r = (int)($startRgb[0] + ($endRgb[0] - $startRgb[0]) * $position);
            $g = (int)($startRgb[1] + ($endRgb[1] - $startRgb[1]) * $position);
            $b = (int)($startRgb[2] + ($endRgb[2] - $startRgb[2]) * $position);
            $color = imagecolorallocate($canvas, $r, $g, $b);
            
            for ($i = 0; $i < $width; $i++) {
                imagesetpixel($canvas, $x, $y1 - $i, $color);
            }
            $currentLength++;
        }
        
        // Right border
        for ($y = $y1; $y <= $y2; $y++) {
            $position = $currentLength / $totalLength;
            $r = (int)($startRgb[0] + ($endRgb[0] - $startRgb[0]) * $position);
            $g = (int)($startRgb[1] + ($endRgb[1] - $startRgb[1]) * $position);
            $b = (int)($startRgb[2] + ($endRgb[2] - $startRgb[2]) * $position);
            $color = imagecolorallocate($canvas, $r, $g, $b);
            
            for ($i = 0; $i < $width; $i++) {
                imagesetpixel($canvas, $x2 + $i, $y, $color);
            }
            $currentLength++;
        }
        
        // Bottom border
        for ($x = $x2; $x >= $x1; $x--) {
            $position = $currentLength / $totalLength;
            $r = (int)($startRgb[0] + ($endRgb[0] - $startRgb[0]) * $position);
            $g = (int)($startRgb[1] + ($endRgb[1] - $startRgb[1]) * $position);
            $b = (int)($startRgb[2] + ($endRgb[2] - $startRgb[2]) * $position);
            $color = imagecolorallocate($canvas, $r, $g, $b);
            
            for ($i = 0; $i < $width; $i++) {
                imagesetpixel($canvas, $x, $y2 + $i, $color);
            }
            $currentLength++;
        }
        
        // Left border
        for ($y = $y2; $y >= $y1; $y--) {
            $position = $currentLength / $totalLength;
            $r = (int)($startRgb[0] + ($endRgb[0] - $startRgb[0]) * $position);
            $g = (int)($startRgb[1] + ($endRgb[1] - $startRgb[1]) * $position);
            $b = (int)($startRgb[2] + ($endRgb[2] - $startRgb[2]) * $position);
            $color = imagecolorallocate($canvas, $r, $g, $b);
            
            for ($i = 0; $i < $width; $i++) {
                imagesetpixel($canvas, $x1 - $i, $y, $color);
            }
            $currentLength++;
        }
    }
} 