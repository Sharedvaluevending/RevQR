<?php
/**
 * Asset Helper for RevenueQR
 * 
 * Provides optimized asset loading with:
 * - Automatic minified file detection
 * - WebP image support
 * - Cache busting with versioning
 * - Critical resource preloading
 */

class AssetHelper {
    private static $instance = null;
    private $isProduction;
    private $assetVersion;
    private $baseUrl;
    
    private function __construct() {
        $this->isProduction = !defined('DEVELOPMENT') || !DEVELOPMENT;
        $this->assetVersion = $this->getAssetVersion();
        $this->baseUrl = defined('APP_URL') ? APP_URL : '';
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Generate versioned asset URL
     */
    public function asset($path, $version = true) {
        $fullPath = $this->baseUrl . '/' . ltrim($path, '/');
        
        if ($version) {
            $separator = strpos($fullPath, '?') !== false ? '&' : '?';
            $fullPath .= $separator . 'v=' . $this->assetVersion;
        }
        
        return $fullPath;
    }
    
    /**
     * Load CSS file with optimization
     */
    public function css($file, $media = 'all', $critical = false) {
        $path = $this->getOptimizedAssetPath($file, 'css');
        $url = $this->asset($path);
        
        $preload = $critical ? ' data-critical="true"' : '';
        $integrity = $this->getIntegrity($path);
        
        return sprintf(
            '<link rel="stylesheet" href="%s" media="%s"%s%s>',
            htmlspecialchars($url),
            htmlspecialchars($media),
            $integrity,
            $preload
        );
    }
    
    /**
     * Load JavaScript file with optimization
     */
    public function js($file, $defer = true, $async = false) {
        $path = $this->getOptimizedAssetPath($file, 'js');
        $url = $this->asset($path);
        
        $attributes = [];
        if ($defer) $attributes[] = 'defer';
        if ($async) $attributes[] = 'async';
        
        $integrity = $this->getIntegrity($path);
        $attributeString = !empty($attributes) ? ' ' . implode(' ', $attributes) : '';
        
        return sprintf(
            '<script src="%s"%s%s></script>',
            htmlspecialchars($url),
            $integrity,
            $attributeString
        );
    }
    
    /**
     * Load image with WebP support and lazy loading
     */
    public function image($src, $alt = '', $attributes = [], $lazy = true) {
        $webpSrc = $this->getWebPVersion($src);
        $originalSrc = $this->asset($src);
        
        $attrs = [];
        foreach ($attributes as $key => $value) {
            $attrs[] = sprintf('%s="%s"', $key, htmlspecialchars($value));
        }
        
        if ($lazy) {
            $attrs[] = 'loading="lazy"';
            $attrs[] = 'class="lazy-load ' . ($attributes['class'] ?? '') . '"';
            $attrs[] = 'data-src="' . htmlspecialchars($originalSrc) . '"';
            $originalSrc = 'data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 1 1\'%3E%3C/svg%3E';
        }
        
        $html = '';
        
        // Use picture element if WebP is available
        if ($webpSrc) {
            $webpUrl = $this->asset($webpSrc);
            $html .= '<picture>';
            $html .= sprintf('<source srcset="%s" type="image/webp">', htmlspecialchars($webpUrl));
            $html .= sprintf('<img src="%s" alt="%s" %s>', 
                htmlspecialchars($originalSrc), 
                htmlspecialchars($alt), 
                implode(' ', $attrs)
            );
            $html .= '</picture>';
        } else {
            $html = sprintf('<img src="%s" alt="%s" %s>', 
                htmlspecialchars($originalSrc), 
                htmlspecialchars($alt), 
                implode(' ', $attrs)
            );
        }
        
        return $html;
    }
    
    /**
     * Preload critical resources
     */
    public function preload($file, $type, $crossorigin = false) {
        $path = $this->getOptimizedAssetPath($file, $type);
        $url = $this->asset($path);
        
        $crossoriginAttr = $crossorigin ? ' crossorigin' : '';
        
        return sprintf(
            '<link rel="preload" href="%s" as="%s"%s>',
            htmlspecialchars($url),
            htmlspecialchars($type),
            $crossoriginAttr
        );
    }
    
    /**
     * Generate critical CSS inline
     */
    public function inlineCriticalCSS() {
        $criticalCssPath = __DIR__ . '/../assets/css/critical.css';
        
        if (file_exists($criticalCssPath)) {
            $css = file_get_contents($criticalCssPath);
            $minifiedCSS = $this->minifyCSS($css);
            return '<style>' . $minifiedCSS . '</style>';
        }
        
        return '';
    }
    
    /**
     * Get optimized asset path (minified in production)
     */
    private function getOptimizedAssetPath($file, $type) {
        if (!$this->isProduction) {
            return "assets/{$type}/{$file}";
        }
        
        // Check for optimized version first
        $optimizedFile = 'optimized.min.' . $type;
        $optimizedPath = __DIR__ . '/../assets/' . $type . '/' . $optimizedFile;
        
        if (file_exists($optimizedPath)) {
            return "assets/{$type}/{$optimizedFile}";
        }
        
        // Check for minified version of specific file
        $pathInfo = pathinfo($file);
        $minifiedFile = $pathInfo['filename'] . '.min.' . $pathInfo['extension'];
        $minifiedPath = __DIR__ . '/../assets/' . $type . '/' . $minifiedFile;
        
        if (file_exists($minifiedPath)) {
            return "assets/{$type}/{$minifiedFile}";
        }
        
        // Fallback to original file
        return "assets/{$type}/{$file}";
    }
    
    /**
     * Check for WebP version of image
     */
    private function getWebPVersion($imagePath) {
        $webpPath = $imagePath . '.webp';
        $fullPath = __DIR__ . '/../' . $webpPath;
        
        if (file_exists($fullPath)) {
            return $webpPath;
        }
        
        return null;
    }
    
    /**
     * Get asset version for cache busting
     */
    private function getAssetVersion() {
        // Use application version if available
        if (defined('APP_VERSION')) {
            return APP_VERSION;
        }
        
        // Use deployment timestamp
        $versionFile = __DIR__ . '/../../.version';
        if (file_exists($versionFile)) {
            return trim(file_get_contents($versionFile));
        }
        
        // Fallback to last modified time of this file
        return filemtime(__FILE__);
    }
    
    /**
     * Generate integrity hash for security
     */
    private function getIntegrity($path) {
        if (!$this->isProduction) {
            return '';
        }
        
        $fullPath = __DIR__ . '/../' . $path;
        if (!file_exists($fullPath)) {
            return '';
        }
        
        $hash = base64_encode(hash_file('sha384', $fullPath, true));
        return ' integrity="sha384-' . $hash . '"';
    }
    
    /**
     * Simple CSS minification
     */
    private function minifyCSS($css) {
        // Remove comments
        $css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css);
        
        // Remove whitespace
        $css = str_replace(["\r\n", "\r", "\n", "\t", '  ', '    ', '    '], '', $css);
        $css = str_replace(['; ', ' {', '{ ', ' }', '} ', ': ', ' :'], [';', '{', '{', '}', '}', ':', ':'], $css);
        
        return trim($css);
    }
    
    /**
     * Generate service worker for caching - DISABLED due to navigation cache issues
     */
    public function generateServiceWorker() {
        // DISABLED - Was causing navigation cache issues
        return false;
        $swContent = "
const CACHE_NAME = 'revenueqr-v3-{$this->assetVersion}';

// Essential files that should be cached if available
const essentialFiles = [
    '/',
    '/html/'
];

// Optional files that can fail to cache without breaking the service worker
const optionalFiles = [
    '/html/assets/css/style.css',
    '/html/assets/js/app.js',
    '/html/assets/img/logo.png',
    '/assets/css/optimized.min.css',
    '/assets/js/optimized.min.js'
];

self.addEventListener('install', function(event) {
    console.log('Service Worker installing...');
    
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(function(cache) {
                console.log('Cache opened, attempting to cache files...');
                
                // Cache essential files first
                const essentialPromises = essentialFiles.map(url => {
                    return fetch(url)
                        .then(response => {
                            if (response.ok) {
                                return cache.put(url, response);
                            } else {
                                console.log('Essential file failed to load:', url, 'Status:', response.status);
                            }
                        })
                        .catch(error => {
                            console.log('Error fetching essential file:', url, error);
                        });
                });
                
                // Cache optional files (don't let failures break installation)
                const optionalPromises = optionalFiles.map(url => {
                    return fetch(url)
                        .then(response => {
                            if (response.ok) {
                                return cache.put(url, response);
                            } else {
                                console.log('Optional file not available:', url, 'Status:', response.status);
                            }
                        })
                        .catch(error => {
                            console.log('Optional file cache error (ignoring):', url, error);
                        });
                });
                
                // Wait for essential files, but don't fail if optional files fail
                return Promise.allSettled([...essentialPromises, ...optionalPromises])
                    .then(() => {
                        console.log('Service Worker installation completed');
                    });
            })
            .catch(error => {
                console.error('Service Worker installation failed:', error);
            })
    );
});

self.addEventListener('fetch', function(event) {
    // Only handle GET requests
    if (event.request.method !== 'GET') {
        return;
    }
    
    event.respondWith(
        caches.match(event.request)
            .then(function(response) {
                if (response) {
                    return response;
                }
                
                // Clone the request before fetching
                const fetchRequest = event.request.clone();
                
                return fetch(fetchRequest)
                    .then(function(response) {
                        // Check if response is valid
                        if (!response || response.status !== 200 || response.type !== 'basic') {
                            return response;
                        }
                        
                        // Clone the response before caching
                        const responseToCache = response.clone();
                        
                        // Cache the response (don't wait for it)
                        caches.open(CACHE_NAME)
                            .then(function(cache) {
                                cache.put(event.request, responseToCache);
                            })
                            .catch(error => {
                                console.log('Error caching response:', error);
                            });
                        
                        return response;
                    })
                    .catch(function(error) {
                        console.log('Fetch failed for:', event.request.url, error);
                        return new Response('Network error', { 
                            status: 503,
                            statusText: 'Service Unavailable'
                        });
                    });
            })
    );
});

self.addEventListener('activate', function(event) {
    console.log('Service Worker activating...');
    
    event.waitUntil(
        caches.keys()
            .then(function(cacheNames) {
                return Promise.all(
                    cacheNames.map(function(cacheName) {
                        if (cacheName !== CACHE_NAME) {
                            console.log('Deleting old cache:', cacheName);
                            return caches.delete(cacheName);
                        }
                    })
                );
            })
            .then(() => {
                console.log('Service Worker activated');
                return self.clients.claim();
            })
    );
});

// Handle service worker errors
self.addEventListener('error', function(event) {
    console.error('Service Worker error:', event.error);
});

console.log('Service Worker script loaded');
        ";
        
        file_put_contents(__DIR__ . '/../../sw.js', $swContent);
    }
}

// Helper functions for templates
function asset($path, $version = true) {
    return AssetHelper::getInstance()->asset($path, $version);
}

function css($file, $media = 'all', $critical = false) {
    return AssetHelper::getInstance()->css($file, $media, $critical);
}

function js($file, $defer = true, $async = false) {
    return AssetHelper::getInstance()->js($file, $defer, $async);
}

function optimized_image($src, $alt = '', $attributes = [], $lazy = true) {
    return AssetHelper::getInstance()->image($src, $alt, $attributes, $lazy);
}

function preload($file, $type, $crossorigin = false) {
    return AssetHelper::getInstance()->preload($file, $type, $crossorigin);
}

function critical_css() {
    return AssetHelper::getInstance()->inlineCriticalCSS();
}
?> 