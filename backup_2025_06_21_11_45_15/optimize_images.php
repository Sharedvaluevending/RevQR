<?php
/**
 * Image Optimization Script for RevenueQR
 * 
 * This script optimizes images by:
 * - Converting to WebP format where supported
 * - Compressing existing images
 * - Resizing large images to reasonable dimensions
 * - Adding cache-friendly headers
 */

require_once __DIR__ . '/html/core/config.php';

class ImageOptimizer {
    private $maxWidth = 1920;
    private $maxHeight = 1080;
    private $jpegQuality = 85;
    private $webpQuality = 80;
    private $pngCompressionLevel = 6;
    
    public function __construct() {
        echo "=================================================\n";
        echo "Image Optimization Script for RevenueQR\n";
        echo "=================================================\n\n";
    }
    
    /**
     * Optimize all images in a directory
     */
    public function optimizeDirectory($directory) {
        if (!is_dir($directory)) {
            echo "Directory not found: $directory\n";
            return false;
        }
        
        echo "Optimizing images in: $directory\n";
        $this->processDirectory($directory);
        return true;
    }
    
    /**
     * Recursively process directory
     */
    private function processDirectory($dir) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        $imageCount = 0;
        $optimizedCount = 0;
        $savedBytes = 0;
        
        foreach ($iterator as $file) {
            if ($this->isImage($file->getPathname())) {
                $imageCount++;
                echo "  Processing: " . $file->getFilename() . "\n";
                
                $result = $this->optimizeImage($file->getPathname());
                if ($result) {
                    $optimizedCount++;
                    $savedBytes += $result['saved_bytes'];
                    echo "    ✓ Optimized: " . $this->formatBytes($result['saved_bytes']) . " saved\n";
                    
                    // Create WebP version if supported
                    if ($this->createWebP($file->getPathname())) {
                        echo "    ✓ WebP version created\n";
                    }
                } else {
                    echo "    ⚠ Skipped (already optimized or error)\n";
                }
            }
        }
        
        echo "\n📊 Summary:\n";
        echo "  Images found: $imageCount\n";
        echo "  Images optimized: $optimizedCount\n";
        echo "  Total space saved: " . $this->formatBytes($savedBytes) . "\n\n";
    }
    
    /**
     * Check if file is an image
     */
    private function isImage($filepath) {
        $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
        $extension = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));
        return in_array($extension, $allowedTypes);
    }
    
    /**
     * Optimize a single image
     */
    private function optimizeImage($filepath) {
        if (!file_exists($filepath)) {
            return false;
        }
        
        $originalSize = filesize($filepath);
        $imageInfo = getimagesize($filepath);
        
        if (!$imageInfo) {
            return false;
        }
        
        list($width, $height, $type) = $imageInfo;
        
        // Skip if image is already small and likely optimized
        if ($width <= 800 && $height <= 600 && $originalSize < 100000) {
            return false;
        }
        
        // Create image resource based on type
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
        
        if (!$image) {
            return false;
        }
        
        // Calculate new dimensions if resizing needed
        $newWidth = $width;
        $newHeight = $height;
        
        if ($width > $this->maxWidth || $height > $this->maxHeight) {
            $ratio = min($this->maxWidth / $width, $this->maxHeight / $height);
            $newWidth = round($width * $ratio);
            $newHeight = round($height * $ratio);
        }
        
        // Create optimized image
        $optimizedImage = imagecreatetruecolor($newWidth, $newHeight);
        
        // Preserve transparency for PNG and GIF
        if ($type == IMAGETYPE_PNG || $type == IMAGETYPE_GIF) {
            imagealphablending($optimizedImage, false);
            imagesavealpha($optimizedImage, true);
            $transparent = imagecolorallocatealpha($optimizedImage, 255, 255, 255, 127);
            imagefill($optimizedImage, 0, 0, $transparent);
        }
        
        // Resize image
        imagecopyresampled(
            $optimizedImage, $image, 
            0, 0, 0, 0, 
            $newWidth, $newHeight, $width, $height
        );
        
        // Save optimized image
        $success = false;
        switch ($type) {
            case IMAGETYPE_JPEG:
                $success = imagejpeg($optimizedImage, $filepath, $this->jpegQuality);
                break;
            case IMAGETYPE_PNG:
                $success = imagepng($optimizedImage, $filepath, $this->pngCompressionLevel);
                break;
            case IMAGETYPE_GIF:
                $success = imagegif($optimizedImage, $filepath);
                break;
        }
        
        // Clean up memory
        imagedestroy($image);
        imagedestroy($optimizedImage);
        
        if ($success) {
            $newSize = filesize($filepath);
            $savedBytes = $originalSize - $newSize;
            
            return [
                'original_size' => $originalSize,
                'new_size' => $newSize,
                'saved_bytes' => $savedBytes,
                'compression_ratio' => round(($savedBytes / $originalSize) * 100, 2)
            ];
        }
        
        return false;
    }
    
    /**
     * Create WebP version of image
     */
    private function createWebP($filepath) {
        if (!function_exists('imagewebp')) {
            return false;
        }
        
        $webpPath = $filepath . '.webp';
        
        // Skip if WebP version already exists and is newer
        if (file_exists($webpPath) && filemtime($webpPath) >= filemtime($filepath)) {
            return false;
        }
        
        $imageInfo = getimagesize($filepath);
        if (!$imageInfo) {
            return false;
        }
        
        list($width, $height, $type) = $imageInfo;
        
        // Create image resource
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
        
        if (!$image) {
            return false;
        }
        
        // Convert to WebP
        $success = imagewebp($image, $webpPath, $this->webpQuality);
        imagedestroy($image);
        
        return $success;
    }
    
    /**
     * Format bytes into human readable format
     */
    private function formatBytes($size, $precision = 2) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        
        for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
            $size /= 1024;
        }
        
        return round($size, $precision) . ' ' . $units[$i];
    }
    
    /**
     * Clean up old or unused images
     */
    public function cleanupOldImages($directory, $daysCutoff = 30) {
        echo "Cleaning up old images (older than $daysCutoff days)...\n";
        
        $cutoffTime = time() - ($daysCutoff * 24 * 60 * 60);
        $deletedCount = 0;
        $freedSpace = 0;
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($iterator as $file) {
            if ($this->isImage($file->getPathname()) && 
                $file->getMTime() < $cutoffTime && 
                strpos($file->getFilename(), 'temp_') === 0) {
                
                $size = $file->getSize();
                if (unlink($file->getPathname())) {
                    $deletedCount++;
                    $freedSpace += $size;
                    echo "  Deleted: " . $file->getFilename() . "\n";
                }
            }
        }
        
        echo "  Deleted $deletedCount old images, freed " . $this->formatBytes($freedSpace) . "\n\n";
    }
}

// Main execution
$optimizer = new ImageOptimizer();

// Optimize images in key directories
$directories = [
    __DIR__ . '/html/uploads/qr',
    __DIR__ . '/html/uploads/logos',
    __DIR__ . '/html/uploads/business_logos',
    __DIR__ . '/html/assets/img'
];

foreach ($directories as $dir) {
    if (is_dir($dir)) {
        $optimizer->optimizeDirectory($dir);
    } else {
        echo "Directory not found: $dir\n";
    }
}

// Clean up old temporary images
if (is_dir(__DIR__ . '/html/uploads/qr')) {
    $optimizer->cleanupOldImages(__DIR__ . '/html/uploads/qr', 7); // 7 days for QR codes
}

echo "🎉 Image optimization completed!\n";
echo "\n📋 Next steps:\n";
echo "• Set up a cron job to run this script weekly\n";
echo "• Monitor image sizes in your application\n";
echo "• Consider implementing real-time WebP conversion\n";
echo "• Review and update image upload size limits\n\n";
?> 