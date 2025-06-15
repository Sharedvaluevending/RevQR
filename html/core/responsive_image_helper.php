<?php
/**
 * Responsive Image Helper
 * Handles WebP serving, lazy loading, and responsive images
 */

class ResponsiveImageHelper {
    private static $instance = null;
    private $base_url;
    private $webp_support;
    
    private function __construct() {
        $this->base_url = defined('APP_URL') ? APP_URL : '';
        $this->webp_support = $this->checkWebPSupport();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Generate responsive image HTML with WebP support
     */
    public function responsiveImage($src, $alt = '', $options = []) {
        $defaults = [
            'lazy' => true,
            'sizes' => '(max-width: 600px) 100vw, (max-width: 1200px) 50vw, 25vw',
            'class' => '',
            'thumbnails' => true,
            'webp' => true
        ];
        
        $options = array_merge($defaults, $options);
        
        // Build srcset for responsive images
        $srcset = $this->buildSrcSet($src, $options['thumbnails']);
        $webp_srcset = $options['webp'] ? $this->buildWebPSrcSet($src, $options['thumbnails']) : null;
        
        $html = '';
        
        // Use picture element for WebP support
        if ($webp_srcset) {
            $html .= '<picture>';
            $html .= sprintf('<source srcset="%s" sizes="%s" type="image/webp">', 
                           htmlspecialchars($webp_srcset), 
                           htmlspecialchars($options['sizes']));
        }
        
        // Build img attributes
        $attributes = [];
        $attributes['alt'] = htmlspecialchars($alt);
        
        if ($options['lazy']) {
            $attributes['loading'] = 'lazy';
            $attributes['class'] = trim($options['class'] . ' lazy-image');
        } else {
            $attributes['class'] = $options['class'];
        }
        
        if ($srcset) {
            $attributes['srcset'] = $srcset;
            $attributes['sizes'] = $options['sizes'];
        }
        
        $attributes['src'] = $this->getOptimalImageUrl($src);
        
        // Build attribute string
        $attr_string = '';
        foreach ($attributes as $key => $value) {
            if (!empty($value)) {
                $attr_string .= sprintf(' %s="%s"', $key, htmlspecialchars($value));
            }
        }
        
        $html .= sprintf('<img%s>', $attr_string);
        
        if ($webp_srcset) {
            $html .= '</picture>';
        }
        
        return $html;
    }
    
    /**
     * Generate QR code image with optimization
     */
    public function qrImage($src, $alt = 'QR Code', $options = []) {
        $defaults = [
            'size' => 300,
            'lazy' => false, // QR codes usually need immediate visibility  
            'webp' => true,
            'class' => 'qr-code'
        ];
        
        $options = array_merge($defaults, $options);
        
        // QR codes don't need responsive thumbnails
        $options['thumbnails'] = false;
        $options['sizes'] = $options['size'] . 'px';
        
        return $this->responsiveImage($src, $alt, $options);
    }
    
    /**
     * Build srcset for different thumbnail sizes
     */
    private function buildSrcSet($src, $use_thumbnails = true) {
        if (!$use_thumbnails) {
            return $this->getOptimalImageUrl($src);
        }
        
        $srcset = [];
        $path_info = pathinfo($src);
        $base_path = $path_info['dirname'] . '/' . $path_info['filename'];
        $extension = $path_info['extension'];
        
        // Common thumbnail sizes
        $sizes = [150, 300, 600, 1200];
        
        foreach ($sizes as $size) {
            $thumb_path = $base_path . '-thumb-' . $size . '.' . $extension;
            $full_thumb_path = $_SERVER['DOCUMENT_ROOT'] . $thumb_path;
            
            if (file_exists($full_thumb_path)) {
                $srcset[] = $this->getOptimalImageUrl($thumb_path) . ' ' . $size . 'w';
            }
        }
        
        // Add original as largest size
        $srcset[] = $this->getOptimalImageUrl($src) . ' 1920w';
        
        return implode(', ', $srcset);
    }
    
    /**
     * Build WebP srcset
     */
    private function buildWebPSrcSet($src, $use_thumbnails = true) {
        if (!$use_thumbnails) {
            $webp_src = $this->getWebPUrl($src);
            return $webp_src ? $webp_src : null;
        }
        
        $srcset = [];
        $path_info = pathinfo($src);
        $base_path = $path_info['dirname'] . '/' . $path_info['filename'];
        $extension = $path_info['extension'];
        
        $sizes = [150, 300, 600, 1200];
        
        foreach ($sizes as $size) {
            $thumb_path = $base_path . '-thumb-' . $size . '.' . $extension;
            $webp_url = $this->getWebPUrl($thumb_path);
            
            if ($webp_url) {
                $srcset[] = $webp_url . ' ' . $size . 'w';
            }
        }
        
        // Add original WebP
        $original_webp = $this->getWebPUrl($src);
        if ($original_webp) {
            $srcset[] = $original_webp . ' 1920w';
        }
        
        return !empty($srcset) ? implode(', ', $srcset) : null;
    }
    
    /**
     * Get WebP URL if available
     */
    private function getWebPUrl($src) {
        $webp_path = $_SERVER['DOCUMENT_ROOT'] . $src . '.webp';
        
        if (file_exists($webp_path)) {
            return $this->base_url . $src . '.webp';
        }
        
        return null;
    }
    
    /**
     * Get optimal image URL (WebP if supported and available)
     */
    private function getOptimalImageUrl($src) {
        if ($this->webp_support) {
            $webp_url = $this->getWebPUrl($src);
            if ($webp_url) {
                return $webp_url;
            }
        }
        
        return $this->base_url . $src;
    }
    
    /**
     * Check if browser supports WebP
     */
    private function checkWebPSupport() {
        if (!isset($_SERVER['HTTP_ACCEPT'])) {
            return false;
        }
        
        return strpos($_SERVER['HTTP_ACCEPT'], 'image/webp') !== false;
    }
    
    /**
     * Generate lazy loading JavaScript
     */
    public function getLazyLoadingScript() {
        return <<<SCRIPT
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Modern browsers with native lazy loading support
    if ('loading' in HTMLImageElement.prototype) {
        return; // Browser handles lazy loading natively
    }
    
    // Fallback for older browsers
    const lazyImages = document.querySelectorAll('img.lazy-image');
    
    const imageObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const img = entry.target;
                
                // Handle srcset
                if (img.dataset.srcset) {
                    img.srcset = img.dataset.srcset;
                    delete img.dataset.srcset;
                }
                
                // Handle src
                if (img.dataset.src) {
                    img.src = img.dataset.src;
                    delete img.dataset.src;
                }
                
                img.classList.remove('lazy-image');
                observer.unobserve(img);
            }
        });
    });
    
    lazyImages.forEach(img => imageObserver.observe(img));
});
</script>
SCRIPT;
    }
    
    /**
     * Generate CSS for lazy loading
     */
    public function getLazyLoadingCSS() {
        return <<<CSS
<style>
.lazy-image {
    opacity: 0;
    transition: opacity 0.3s ease-in-out;
}

.lazy-image[src] {
    opacity: 1;
}

/* QR code specific styles */
.qr-code {
    max-width: 100%;
    height: auto;
    image-rendering: -webkit-optimize-contrast;
    image-rendering: crisp-edges;
    image-rendering: pixelated;
}

/* Responsive image container */
.responsive-image-container {
    position: relative;
    overflow: hidden;
}

.responsive-image-container img {
    width: 100%;
    height: auto;
    display: block;
}

/* Loading placeholder */
.image-placeholder {
    background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
    background-size: 200% 100%;
    animation: loading 1.5s infinite;
}

@keyframes loading {
    0% { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}
</style>
CSS;
    }
    
    /**
     * Preload critical images
     */
    public function preloadImage($src, $type = 'image') {
        $url = $this->getOptimalImageUrl($src);
        return sprintf('<link rel="preload" as="%s" href="%s">', 
                      htmlspecialchars($type), 
                      htmlspecialchars($url));
    }
}

// Helper functions for templates
function responsive_image($src, $alt = '', $options = []) {
    return ResponsiveImageHelper::getInstance()->responsiveImage($src, $alt, $options);
}

function qr_image($src, $alt = 'QR Code', $options = []) {
    return ResponsiveImageHelper::getInstance()->qrImage($src, $alt, $options);
}

function preload_image($src, $type = 'image') {
    return ResponsiveImageHelper::getInstance()->preloadImage($src, $type);
}

function lazy_loading_scripts() {
    return ResponsiveImageHelper::getInstance()->getLazyLoadingScript() . 
           ResponsiveImageHelper::getInstance()->getLazyLoadingCSS();
}
?> 