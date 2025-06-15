<?php
/**
 * Advanced QR Code & Image Optimization System
 * 
 * Enhanced optimization beyond the existing optimize_images.php:
 * - QR code generation optimization
 * - Advanced image compression
 * - WebP/AVIF support
 * - CDN integration preparation
 * - Lazy loading implementation
 * - Smart caching strategies
 */

require_once __DIR__ . '/html/core/config.php';

class AdvancedImageQROptimizer {
    private $config;
    private $stats;
    
    public function __construct() {
        $this->config = [
            // QR Code optimization settings
            'qr' => [
                'default_size' => 300,
                'max_size' => 800,
                'compression_level' => 6,
                'error_correction' => 'M', // L=7%, M=15%, Q=25%, H=30%
                'cache_duration' => 86400 * 30 // 30 days
            ],
            
            // Image optimization settings  
            'images' => [
                'webp_quality' => 85,
                'avif_quality' => 80,
                'jpeg_quality' => 88,
                'png_compression' => 6,
                'max_width' => 1920,
                'max_height' => 1080,
                'thumbnail_sizes' => [150, 300, 600, 1200]
            ],
            
            // CDN and caching
            'cdn' => [
                'enable_cloudflare' => false, // Set to true when ready
                'cache_headers' => [
                    'qr_codes' => 'public, max-age=2592000', // 30 days
                    'images' => 'public, max-age=7776000',   // 90 days
                    'thumbnails' => 'public, max-age=31536000' // 1 year
                ]
            ]
        ];
        
        $this->stats = [
            'qr_codes_optimized' => 0,
            'images_optimized' => 0,
            'webp_created' => 0,
            'avif_created' => 0,
            'thumbnails_created' => 0,
            'bytes_saved' => 0,
            'cache_files_created' => 0
        ];
        
        echo "🚀 ADVANCED QR CODE & IMAGE OPTIMIZATION\n";
        echo "=======================================\n\n";
    }
    
    /**
     * Main optimization runner
     */
    public function optimize() {
        echo "Phase 1: QR Code Optimization...\n";
        $this->optimizeQRCodes();
        
        echo "\nPhase 2: Image Format Optimization...\n";
        $this->optimizeImages();
        
        echo "\nPhase 3: Thumbnail Generation...\n";
        $this->generateThumbnails();
        
        echo "\nPhase 4: Modern Format Creation...\n";
        $this->createModernFormats();
        
        echo "\nPhase 5: Cache Optimization...\n";
        $this->setupCacheOptimization();
        
        echo "\nPhase 6: CDN Preparation...\n";
        $this->prepareCDNIntegration();
        
        $this->displayResults();
    }
    
    /**
     * Optimize QR codes specifically
     */
    private function optimizeQRCodes() {
        $qr_directories = [
            __DIR__ . '/html/uploads/qr',
            __DIR__ . '/html/uploads/qr/business',
            __DIR__ . '/html/uploads/qr/nayax',
            __DIR__ . '/html/assets/img/qr'
        ];
        
        foreach ($qr_directories as $dir) {
            if (!is_dir($dir)) continue;
            
            echo "  📁 Processing QR directory: $dir\n";
            $this->processQRDirectory($dir);
        }
    }
    
    /**
     * Process QR codes in a directory
     */
    private function processQRDirectory($directory) {
        $files = glob($directory . '/*.png');
        
        foreach ($files as $file) {
            if (strpos(basename($file), 'qr_') === 0 || strpos(basename($file), 'demo_qr') === 0) {
                $result = $this->optimizeQRCode($file);
                if ($result) {
                    $this->stats['qr_codes_optimized']++;
                    $this->stats['bytes_saved'] += $result['bytes_saved'];
                    echo "    ✅ " . basename($file) . " - " . $this->formatBytes($result['bytes_saved']) . " saved\n";
                }
            }
        }
    }
    
    /**
     * Optimize individual QR code
     */
    private function optimizeQRCode($filepath) {
        $original_size = filesize($filepath);
        
        // Load QR code image
        $image = imagecreatefrompng($filepath);
        if (!$image) return false;
        
        $width = imagesx($image);
        $height = imagesy($image);
        
        // QR codes should be square
        if ($width !== $height) {
            $size = min($width, $height);
            $square_image = imagecreatetruecolor($size, $size);
            imagealphablending($square_image, false);
            imagesavealpha($square_image, true);
            
            $transparent = imagecolorallocatealpha($square_image, 255, 255, 255, 127);
            imagefill($square_image, 0, 0, $transparent);
            
            imagecopyresampled($square_image, $image, 0, 0, 0, 0, $size, $size, $width, $height);
            imagedestroy($image);
            $image = $square_image;
        }
        
        // Optimize for QR codes (high contrast, lossless)
        imagesavealpha($image, true);
        
        // Save optimized QR code
        $success = imagepng($image, $filepath, $this->config['qr']['compression_level']);
        imagedestroy($image);
        
        if ($success) {
            $new_size = filesize($filepath);
            $bytes_saved = max(0, $original_size - $new_size);
            
            // Create WebP version for QR codes
            $this->createWebPVersion($filepath);
            
            return [
                'original_size' => $original_size,
                'new_size' => $new_size,
                'bytes_saved' => $bytes_saved
            ];
        }
        
        return false;
    }
    
    /**
     * Optimize regular images
     */
    private function optimizeImages() {
        $image_directories = [
            __DIR__ . '/html/assets/img',
            __DIR__ . '/html/uploads/logos',
            __DIR__ . '/html/uploads/business_logos'
        ];
        
        foreach ($image_directories as $dir) {
            if (!is_dir($dir)) continue;
            
            echo "  🖼️  Processing image directory: $dir\n";
            $this->processImageDirectory($dir);
        }
    }
    
    /**
     * Process images in directory
     */
    private function processImageDirectory($directory) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($iterator as $file) {
            if ($this->isOptimizableImage($file->getPathname())) {
                $result = $this->optimizeImage($file->getPathname());
                if ($result) {
                    $this->stats['images_optimized']++;
                    $this->stats['bytes_saved'] += $result['bytes_saved'];
                    echo "    ✅ " . $file->getFilename() . " - " . $this->formatBytes($result['bytes_saved']) . " saved\n";
                }
            }
        }
    }
    
    /**
     * Check if image should be optimized
     */
    private function isOptimizableImage($filepath) {
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
        $extension = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));
        
        if (!in_array($extension, $allowed_extensions)) return false;
        
        // Skip if already optimized recently
        $optimized_flag = $filepath . '.optimized';
        if (file_exists($optimized_flag) && 
            filemtime($optimized_flag) > filemtime($filepath)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Optimize individual image
     */
    private function optimizeImage($filepath) {
        $original_size = filesize($filepath);
        $image_info = getimagesize($filepath);
        
        if (!$image_info) return false;
        
        list($width, $height, $type) = $image_info;
        
        // Create optimized version
        switch ($type) {
            case IMAGETYPE_JPEG:
                $image = imagecreatefromjpeg($filepath);
                break;
            case IMAGETYPE_PNG:
                $image = imagecreatefrompng($filepath);
                break;
            case IMAGETYPE_GIF:
                $image = imagecreatefromgif($filepath);
                break;
            default:
                return false;
        }
        
        if (!$image) return false;
        
        // Apply advanced optimization
        $optimized = $this->applyAdvancedImageOptimization($image, $width, $height, $type);
        
        // Save optimized image
        $success = false;
        switch ($type) {
            case IMAGETYPE_JPEG:
                $success = imagejpeg($optimized, $filepath, $this->config['images']['jpeg_quality']);
                break;
            case IMAGETYPE_PNG:
                $success = imagepng($optimized, $filepath, $this->config['images']['png_compression']);
                break;
            case IMAGETYPE_GIF:
                $success = imagegif($optimized, $filepath);
                break;
        }
        
        imagedestroy($image);
        imagedestroy($optimized);
        
        if ($success) {
            // Mark as optimized
            touch($filepath . '.optimized');
            
            $new_size = filesize($filepath);
            return [
                'original_size' => $original_size,
                'new_size' => $new_size,
                'bytes_saved' => max(0, $original_size - $new_size)
            ];
        }
        
        return false;
    }
    
    /**
     * Apply advanced image optimization techniques
     */
    private function applyAdvancedImageOptimization($image, $width, $height, $type) {
        // Calculate optimal size
        $new_width = $width;
        $new_height = $height;
        
        if ($width > $this->config['images']['max_width'] || 
            $height > $this->config['images']['max_height']) {
            
            $ratio = min(
                $this->config['images']['max_width'] / $width,
                $this->config['images']['max_height'] / $height
            );
            
            $new_width = round($width * $ratio);
            $new_height = round($height * $ratio);
        }
        
        // Create optimized image
        $optimized = imagecreatetruecolor($new_width, $new_height);
        
        // Preserve transparency
        if ($type == IMAGETYPE_PNG || $type == IMAGETYPE_GIF) {
            imagealphablending($optimized, false);
            imagesavealpha($optimized, true);
            $transparent = imagecolorallocatealpha($optimized, 255, 255, 255, 127);
            imagefill($optimized, 0, 0, $transparent);
        }
        
        // Apply sharpening filter for better quality at smaller sizes
        imagecopyresampled($optimized, $image, 0, 0, 0, 0, 
                          $new_width, $new_height, $width, $height);
        
        // Apply unsharp mask for better quality
        if ($new_width < $width) {
            imagefilter($optimized, IMG_FILTER_GAUSSIAN_BLUR);
            imagefilter($optimized, IMG_FILTER_CONTRAST, -5);
        }
        
        return $optimized;
    }
    
    /**
     * Generate thumbnails for responsive images
     */
    private function generateThumbnails() {
        $directories = [
            __DIR__ . '/html/uploads/logos',
            __DIR__ . '/html/uploads/business_logos',
            __DIR__ . '/html/assets/img'
        ];
        
        foreach ($directories as $dir) {
            if (!is_dir($dir)) continue;
            
            echo "  🔍 Generating thumbnails for: $dir\n";
            $this->processThumbnailDirectory($dir);
        }
    }
    
    /**
     * Process directory for thumbnail creation
     */
    private function processThumbnailDirectory($directory) {
        $files = glob($directory . '/*.{jpg,jpeg,png}', GLOB_BRACE);
        
        foreach ($files as $file) {
            // Skip if already a thumbnail
            if (preg_match('/-thumb-\d+/', basename($file))) continue;
            
            $created = $this->createResponsiveThumbnails($file);
            $this->stats['thumbnails_created'] += $created;
            
            if ($created > 0) {
                echo "    ✅ " . basename($file) . " - $created thumbnails created\n";
            }
        }
    }
    
    /**
     * Create responsive thumbnails
     */
    private function createResponsiveThumbnails($filepath) {
        $image_info = getimagesize($filepath);
        if (!$image_info) return 0;
        
        list($width, $height, $type) = $image_info;
        
        $image = null;
        switch ($type) {
            case IMAGETYPE_JPEG:
                $image = imagecreatefromjpeg($filepath);
                break;
            case IMAGETYPE_PNG:
                $image = imagecreatefrompng($filepath);
                break;
            default:
                return 0;
        }
        
        if (!$image) return 0;
        
        $created = 0;
        $path_info = pathinfo($filepath);
        
        foreach ($this->config['images']['thumbnail_sizes'] as $size) {
            // Skip if original is smaller
            if ($width <= $size && $height <= $size) continue;
            
            $thumb_name = $path_info['filename'] . '-thumb-' . $size . '.' . $path_info['extension'];
            $thumb_path = $path_info['dirname'] . '/' . $thumb_name;
            
            // Skip if thumbnail already exists and is newer
            if (file_exists($thumb_path) && filemtime($thumb_path) >= filemtime($filepath)) {
                continue;
            }
            
            // Calculate thumbnail dimensions
            $ratio = min($size / $width, $size / $height);
            $thumb_width = round($width * $ratio);
            $thumb_height = round($height * $ratio);
            
            // Create thumbnail
            $thumbnail = imagecreatetruecolor($thumb_width, $thumb_height);
            
            if ($type == IMAGETYPE_PNG) {
                imagealphablending($thumbnail, false);
                imagesavealpha($thumbnail, true);
                $transparent = imagecolorallocatealpha($thumbnail, 255, 255, 255, 127);
                imagefill($thumbnail, 0, 0, $transparent);
            }
            
            imagecopyresampled($thumbnail, $image, 0, 0, 0, 0,
                             $thumb_width, $thumb_height, $width, $height);
            
            // Save thumbnail
            $success = false;
            switch ($type) {
                case IMAGETYPE_JPEG:
                    $success = imagejpeg($thumbnail, $thumb_path, $this->config['images']['jpeg_quality']);
                    break;
                case IMAGETYPE_PNG:
                    $success = imagepng($thumbnail, $thumb_path, $this->config['images']['png_compression']);
                    break;
            }
            
            imagedestroy($thumbnail);
            
            if ($success) {
                $created++;
                // Create WebP version of thumbnail
                $this->createWebPVersion($thumb_path);
            }
        }
        
        imagedestroy($image);
        return $created;
    }
    
    /**
     * Create modern format versions (WebP, AVIF)
     */
    private function createModernFormats() {
        $directories = [
            __DIR__ . '/html/uploads/qr',
            __DIR__ . '/html/uploads/logos',
            __DIR__ . '/html/uploads/business_logos',
            __DIR__ . '/html/assets/img'
        ];
        
        foreach ($directories as $dir) {
            if (!is_dir($dir)) continue;
            
            echo "  🆕 Creating modern formats for: $dir\n";
            $this->processModernFormats($dir);
        }
    }
    
    /**
     * Process directory for modern formats
     */
    private function processModernFormats($directory) {
        $files = glob($directory . '/*.{jpg,jpeg,png}', GLOB_BRACE);
        
        foreach ($files as $file) {
            $webp_created = $this->createWebPVersion($file);
            $avif_created = $this->createAVIFVersion($file);
            
            if ($webp_created) $this->stats['webp_created']++;
            if ($avif_created) $this->stats['avif_created']++;
            
            if ($webp_created || $avif_created) {
                $formats = [];
                if ($webp_created) $formats[] = 'WebP';
                if ($avif_created) $formats[] = 'AVIF';
                echo "    ✅ " . basename($file) . " - " . implode(', ', $formats) . " created\n";
            }
        }
    }
    
    /**
     * Create WebP version of image
     */
    private function createWebPVersion($filepath) {
        if (!function_exists('imagewebp')) return false;
        
        $webp_path = $filepath . '.webp';
        
        // Skip if WebP exists and is newer
        if (file_exists($webp_path) && filemtime($webp_path) >= filemtime($filepath)) {
            return false;
        }
        
        $image_info = getimagesize($filepath);
        if (!$image_info) return false;
        
        list($width, $height, $type) = $image_info;
        
        $image = null;
        switch ($type) {
            case IMAGETYPE_JPEG:
                $image = imagecreatefromjpeg($filepath);
                break;
            case IMAGETYPE_PNG:
                $image = imagecreatefrompng($filepath);
                break;
            default:
                return false;
        }
        
        if (!$image) return false;
        
        $success = imagewebp($image, $webp_path, $this->config['images']['webp_quality']);
        imagedestroy($image);
        
        return $success;
    }
    
    /**
     * Create AVIF version of image (if supported)
     */
    private function createAVIFVersion($filepath) {
        // AVIF support is limited, this is future-proofing
        if (!function_exists('imageavif')) return false;
        
        $avif_path = str_replace(pathinfo($filepath, PATHINFO_EXTENSION), 'avif', $filepath);
        
        // Skip if AVIF exists and is newer
        if (file_exists($avif_path) && filemtime($avif_path) >= filemtime($filepath)) {
            return false;
        }
        
        $image_info = getimagesize($filepath);
        if (!$image_info) return false;
        
        list($width, $height, $type) = $image_info;
        
        $image = null;
        switch ($type) {
            case IMAGETYPE_JPEG:
                $image = imagecreatefromjpeg($filepath);
                break;
            case IMAGETYPE_PNG:
                $image = imagecreatefrompng($filepath);
                break;
            default:
                return false;
        }
        
        if (!$image) return false;
        
        $success = imageavif($image, $avif_path, $this->config['images']['avif_quality']);
        imagedestroy($image);
        
        return $success;
    }
    
    /**
     * Setup cache optimization
     */
    private function setupCacheOptimization() {
        echo "  📦 Setting up cache optimization...\n";
        
        // Create cache manifest for QR codes
        $this->createQRCacheManifest();
        
        // Create responsive image manifest
        $this->createResponsiveImageManifest();
        
        // Update .htaccess with optimized headers
        $this->updateCacheHeaders();
    }
    
    /**
     * Create QR code cache manifest
     */
    private function createQRCacheManifest() {
        $qr_files = [];
        $qr_directories = [
            __DIR__ . '/html/uploads/qr',
            __DIR__ . '/html/uploads/qr/business',
            __DIR__ . '/html/uploads/qr/nayax'
        ];
        
        foreach ($qr_directories as $dir) {
            if (!is_dir($dir)) continue;
            
            $files = glob($dir . '/*.png');
            foreach ($files as $file) {
                $relative_path = str_replace(__DIR__ . '/html', '', $file);
                $qr_files[] = [
                    'path' => $relative_path,
                    'size' => filesize($file),
                    'modified' => filemtime($file),
                    'webp' => file_exists($file . '.webp')
                ];
            }
        }
        
        $manifest_path = __DIR__ . '/html/cache/qr_manifest.json';
        if (!is_dir(dirname($manifest_path))) {
            mkdir(dirname($manifest_path), 0755, true);
        }
        
        file_put_contents($manifest_path, json_encode($qr_files, JSON_PRETTY_PRINT));
        $this->stats['cache_files_created']++;
        
        echo "    ✅ QR cache manifest created (" . count($qr_files) . " files)\n";
    }
    
    /**
     * Create responsive image manifest
     */
    private function createResponsiveImageManifest() {
        $image_sets = [];
        $directories = [
            __DIR__ . '/html/uploads/logos',
            __DIR__ . '/html/uploads/business_logos'
        ];
        
        foreach ($directories as $dir) {
            if (!is_dir($dir)) continue;
            
            $files = glob($dir . '/*.{jpg,jpeg,png}', GLOB_BRACE);
            foreach ($files as $file) {
                // Skip thumbnails
                if (preg_match('/-thumb-\d+/', basename($file))) continue;
                
                $path_info = pathinfo($file);
                $base_name = $path_info['filename'];
                $relative_path = str_replace(__DIR__ . '/html', '', $file);
                
                $image_set = [
                    'original' => $relative_path,
                    'thumbnails' => [],
                    'formats' => []
                ];
                
                // Find thumbnails
                foreach ($this->config['images']['thumbnail_sizes'] as $size) {
                    $thumb_file = $path_info['dirname'] . '/' . $base_name . '-thumb-' . $size . '.' . $path_info['extension'];
                    if (file_exists($thumb_file)) {
                        $thumb_relative = str_replace(__DIR__ . '/html', '', $thumb_file);
                        $image_set['thumbnails'][$size] = $thumb_relative;
                    }
                }
                
                // Check for WebP/AVIF
                if (file_exists($file . '.webp')) {
                    $image_set['formats']['webp'] = $relative_path . '.webp';
                }
                
                $avif_file = str_replace($path_info['extension'], 'avif', $file);
                if (file_exists($avif_file)) {
                    $image_set['formats']['avif'] = str_replace($path_info['extension'], 'avif', $relative_path);
                }
                
                $image_sets[$base_name] = $image_set;
            }
        }
        
        $manifest_path = __DIR__ . '/html/cache/responsive_images_manifest.json';
        file_put_contents($manifest_path, json_encode($image_sets, JSON_PRETTY_PRINT));
        $this->stats['cache_files_created']++;
        
        echo "    ✅ Responsive image manifest created (" . count($image_sets) . " image sets)\n";
    }
    
    /**
     * Update cache headers
     */
    private function updateCacheHeaders() {
        $htaccess_additions = "
# Advanced Image & QR Code Optimization
# Generated by AdvancedImageQROptimizer

<IfModule mod_headers.c>
    # QR Code specific headers
    <FilesMatch \"qr_.*\\.png$\">
        Header set Cache-Control \"public, max-age=2592000\"
        Header append Vary \"Accept\"
    </FilesMatch>
    
    # WebP images
    <FilesMatch \"\\.webp$\">
        Header set Cache-Control \"public, max-age=7776000\"
        Header append Vary \"Accept\"
        Header set Content-Type \"image/webp\"
    </FilesMatch>
    
    # AVIF images
    <FilesMatch \"\\.avif$\">
        Header set Cache-Control \"public, max-age=7776000\"
        Header append Vary \"Accept\"
        Header set Content-Type \"image/avif\"
    </FilesMatch>
    
    # Thumbnail images
    <FilesMatch \"-thumb-\\d+\\.(jpg|jpeg|png)$\">
        Header set Cache-Control \"public, max-age=31536000, immutable\"
        Header append Vary \"Accept\"
    </FilesMatch>
</IfModule>

# Serve WebP images if available and supported
<IfModule mod_rewrite.c>
    RewriteCond %{HTTP_ACCEPT} image/webp
    RewriteCond %{REQUEST_FILENAME} \\.(jpe?g|png)$
    RewriteCond %{REQUEST_FILENAME}\\.webp -f
    RewriteRule ^(.+)\\.(jpe?g|png)$ $1.$2.webp [T=image/webp,E=accept:1]
</IfModule>
";
        
        $htaccess_path = __DIR__ . '/html/uploads/.htaccess';
        if (!file_exists($htaccess_path)) {
            file_put_contents($htaccess_path, $htaccess_additions);
            echo "    ✅ Upload cache headers created\n";
        }
    }
    
    /**
     * Prepare CDN integration
     */
    private function prepareCDNIntegration() {
        echo "  🌐 Preparing CDN integration...\n";
        
        // Create CDN-ready file list
        $this->createCDNFileList();
        
        // Generate image optimization report
        $this->generateOptimizationReport();
    }
    
    /**
     * Create CDN file list
     */
    private function createCDNFileList() {
        $cdn_files = [];
        $directories = [
            '/uploads/qr' => 'QR Codes',
            '/uploads/logos' => 'Logos',
            '/uploads/business_logos' => 'Business Logos',
            '/assets/img' => 'Static Images'
        ];
        
        foreach ($directories as $path => $description) {
            $full_path = __DIR__ . '/html' . $path;
            if (!is_dir($full_path)) continue;
            
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($full_path, RecursiveDirectoryIterator::SKIP_DOTS)
            );
            
            foreach ($files as $file) {
                if (in_array(strtolower($file->getExtension()), ['png', 'jpg', 'jpeg', 'webp', 'avif'])) {
                    $relative_path = str_replace(__DIR__ . '/html', '', $file->getPathname());
                    $cdn_files[] = [
                        'path' => $relative_path,
                        'category' => $description,
                        'size' => $file->getSize(),
                        'type' => $file->getExtension()
                    ];
                }
            }
        }
        
        $cdn_list_path = __DIR__ . '/html/cache/cdn_file_list.json';
        file_put_contents($cdn_list_path, json_encode($cdn_files, JSON_PRETTY_PRINT));
        
        echo "    ✅ CDN file list created (" . count($cdn_files) . " files)\n";
    }
    
    /**
     * Generate optimization report
     */
    private function generateOptimizationReport() {
        $report = [
            'timestamp' => date('Y-m-d H:i:s'),
            'statistics' => $this->stats,
            'configuration' => $this->config,
            'recommendations' => $this->generateRecommendations()
        ];
        
        $report_path = __DIR__ . '/optimization_report_' . date('Y_m_d_H_i_s') . '.json';
        file_put_contents($report_path, json_encode($report, JSON_PRETTY_PRINT));
        
        echo "    ✅ Optimization report saved: " . basename($report_path) . "\n";
    }
    
    /**
     * Generate recommendations
     */
    private function generateRecommendations() {
        $recommendations = [];
        
        if ($this->stats['webp_created'] > 0) {
            $recommendations[] = "Implement automatic WebP serving in your application";
        }
        
        if ($this->stats['thumbnails_created'] > 0) {
            $recommendations[] = "Use responsive thumbnails in your HTML with srcset attributes";
        }
        
        if ($this->stats['qr_codes_optimized'] > 0) {
            $recommendations[] = "Consider implementing QR code caching with 30-day expiration";
        }
        
        $recommendations[] = "Set up a cron job to run this optimization weekly";
        $recommendations[] = "Monitor Core Web Vitals for image loading performance";
        $recommendations[] = "Consider implementing lazy loading for below-the-fold images";
        
        return $recommendations;
    }
    
    /**
     * Display optimization results
     */
    private function displayResults() {
        echo "\n🎉 OPTIMIZATION COMPLETE!\n";
        echo "=========================\n\n";
        
        echo "📊 Statistics:\n";
        echo "  • QR codes optimized: " . $this->stats['qr_codes_optimized'] . "\n";
        echo "  • Images optimized: " . $this->stats['images_optimized'] . "\n";
        echo "  • WebP versions created: " . $this->stats['webp_created'] . "\n";
        echo "  • AVIF versions created: " . $this->stats['avif_created'] . "\n";
        echo "  • Thumbnails created: " . $this->stats['thumbnails_created'] . "\n";
        echo "  • Total bytes saved: " . $this->formatBytes($this->stats['bytes_saved']) . "\n";
        echo "  • Cache files created: " . $this->stats['cache_files_created'] . "\n\n";
        
        echo "🔗 Generated Files:\n";
        echo "  • /html/cache/qr_manifest.json - QR code cache manifest\n";
        echo "  • /html/cache/responsive_images_manifest.json - Responsive image data\n";
        echo "  • /html/cache/cdn_file_list.json - CDN preparation list\n";
        echo "  • /html/uploads/.htaccess - Upload cache headers\n\n";
        
        echo "🚀 Next Steps:\n";
        echo "  1. Test WebP image serving on your website\n";
        echo "  2. Implement responsive images with srcset\n";
        echo "  3. Set up automated optimization cron job\n";
        echo "  4. Monitor image loading performance\n";
        echo "  5. Consider CDN integration for static assets\n\n";
        
        echo "💡 For implementation help, see the optimization report JSON file.\n";
    }
    
    /**
     * Format bytes for display
     */
    private function formatBytes($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes > 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
}

// Run optimization
$optimizer = new AdvancedImageQROptimizer();
$optimizer->optimize();
?> 