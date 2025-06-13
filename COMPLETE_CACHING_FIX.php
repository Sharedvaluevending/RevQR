<?php
// COMPLETE CACHING FIX - Address ALL caching layers
header("Cache-Control: no-cache, no-store, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");
header("Clear-Site-Data: \"cache\", \"storage\", \"executionContexts\"");

// Prevent back/forward cache (bfcache)
header("Cache-Control: no-cache, no-store, must-revalidate, max-age=0");
header("X-Robots-Tag: noindex, nofollow, noarchive, nosnippet");

$timestamp = date('Y-m-d H:i:s');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Complete Caching System Fix</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <!-- PREVENT ALL CACHING -->
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #1a1a1a; color: #00ff00; }
        .section { margin: 20px 0; padding: 15px; background: #2a2a2a; border-radius: 8px; }
        .critical { background: #ff4444; color: white; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .success { background: #44ff44; color: black; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .btn { background: #ff6b6b; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; margin: 5px; }
        .result { margin: 5px 0; font-family: monospace; }
        .warning { background: #ffaa00; color: black; padding: 10px; border-radius: 5px; margin: 10px 0; }
    </style>
</head>
<body>
    <h1>🚨 COMPLETE CACHING SYSTEM FIX</h1>
    <p><strong>Time:</strong> <?php echo $timestamp; ?></p>
    
    <div class="critical">
        <h2>❌ CRITICAL ISSUES FOUND</h2>
        <p><strong>1. Server-Level Caching:</strong> Apache /etc/apache2/conf-enabled/optimization.conf</p>
        <p><strong>2. Browser Back/Forward Cache:</strong> Not prevented</p>
        <p><strong>3. Multiple Cache Layers:</strong> Still active</p>
    </div>

    <div class="section">
        <h2>🔧 REQUIRED FIXES</h2>
        <div id="fixes">
            <button class="btn" onclick="fixApacheConfig()">1. Fix Apache Server Config</button>
            <button class="btn" onclick="testCurrentHeaders()">2. Test Current Headers</button>
            <button class="btn" onclick="preventBFCache()">3. Prevent Back/Forward Cache</button>
            <button class="btn" onclick="clearAllClientCaches()">4. Clear All Client Caches</button>
            <button class="btn" onclick="runCompleteTest()">5. Run Complete Test</button>
        </div>
    </div>

    <div class="section">
        <h2>📊 RESULTS</h2>
        <div id="results"></div>
    </div>

    <div class="section">
        <h2>🔍 CURRENT CACHE STATUS</h2>
        <div id="status"></div>
    </div>

    <script>
        function log(message, type = 'result') {
            const results = document.getElementById('results');
            const div = document.createElement('div');
            div.className = type;
            div.innerHTML = `[${new Date().toLocaleTimeString()}] ${message}`;
            results.appendChild(div);
            results.scrollTop = results.scrollHeight;
        }

        function fixApacheConfig() {
            log('🚨 CRITICAL: Apache server has global cache rules!', 'critical');
            log('📁 Location: /etc/apache2/conf-enabled/optimization.conf', 'result');
            log('❌ Problem: CSS cached 1 month, JS cached 1 month', 'result');
            log('🛠️ Required: Server admin must disable or override these rules', 'warning');
            log('💡 Command needed: sudo nano /etc/apache2/conf-enabled/optimization.conf', 'result');
            log('💡 Then: sudo systemctl reload apache2', 'result');
        }

        async function testCurrentHeaders() {
            log('🔍 Testing current cache headers...', 'result');
            
            const testUrls = [
                '/assets/js/optimized.min.js',
                '/assets/css/optimized.min.css',
                '/business/dashboard_simple.php'
            ];

            for (let url of testUrls) {
                try {
                    const response = await fetch(url, { 
                        method: 'HEAD',
                        cache: 'no-cache'
                    });
                    
                    const cacheControl = response.headers.get('Cache-Control');
                    const expires = response.headers.get('Expires');
                    
                    log(`📄 ${url}:`, 'result');
                    log(`   Cache-Control: ${cacheControl || 'None'}`, 'result');
                    log(`   Expires: ${expires || 'None'}`, 'result');
                    
                    if (cacheControl && cacheControl.includes('max-age')) {
                        log(`   ❌ STILL CACHING!`, 'critical');
                    } else if (cacheControl && cacheControl.includes('no-cache')) {
                        log(`   ✅ No cache (good)`, 'success');
                    }
                    
                } catch (error) {
                    log(`❌ Error testing ${url}: ${error.message}`, 'critical');
                }
            }
        }

        function preventBFCache() {
            log('🔄 Implementing back/forward cache prevention...', 'result');
            
            // Prevent browser back/forward cache (bfcache)
            window.addEventListener('pageshow', function(event) {
                if (event.persisted) {
                    log('🔄 Page loaded from bfcache - reloading...', 'warning');
                    window.location.reload();
                }
            });

            // Force page reload on back button
            window.addEventListener('pagehide', function() {
                // Mark that we need a fresh load
                sessionStorage.setItem('needsReload', 'true');
            });

            // Check if we need to reload
            if (sessionStorage.getItem('needsReload') === 'true') {
                sessionStorage.removeItem('needsReload');
                log('🔄 Detected back button usage - forcing fresh load', 'warning');
                window.location.reload(true);
            }

            // Add no-cache meta tags dynamically
            const metaTags = [
                ['Cache-Control', 'no-cache, no-store, must-revalidate'],
                ['Pragma', 'no-cache'],
                ['Expires', '0']
            ];

            metaTags.forEach(([httpEquiv, content]) => {
                const meta = document.createElement('meta');
                meta.httpEquiv = httpEquiv;
                meta.content = content;
                document.head.appendChild(meta);
            });

            log('✅ Back/forward cache prevention implemented', 'success');
        }

        async function clearAllClientCaches() {
            log('🧹 Clearing ALL client-side caches...', 'result');
            
            try {
                // 1. Service Workers
                if ('serviceWorker' in navigator) {
                    const registrations = await navigator.serviceWorker.getRegistrations();
                    for (let reg of registrations) {
                        await reg.unregister();
                        log(`✅ Unregistered service worker: ${reg.scope}`, 'success');
                    }
                }

                // 2. Cache API
                if ('caches' in window) {
                    const cacheNames = await caches.keys();
                    for (let name of cacheNames) {
                        await caches.delete(name);
                        log(`✅ Deleted cache: ${name}`, 'success');
                    }
                }

                // 3. Storage
                localStorage.clear();
                sessionStorage.clear();
                log('✅ Cleared localStorage and sessionStorage', 'success');

                // 4. Force reload all assets
                const links = document.querySelectorAll('link[rel="stylesheet"]');
                links.forEach(link => {
                    const href = link.href;
                    link.href = href + (href.includes('?') ? '&' : '?') + 't=' + Date.now();
                });

                log('✅ All client caches cleared', 'success');
                
            } catch (error) {
                log(`❌ Error clearing caches: ${error.message}`, 'critical');
            }
        }

        async function runCompleteTest() {
            log('🚀 Running complete caching diagnostic...', 'result');
            
            // Test 1: Check for service workers
            if ('serviceWorker' in navigator) {
                const registrations = await navigator.serviceWorker.getRegistrations();
                if (registrations.length > 0) {
                    log(`❌ ${registrations.length} service workers still active`, 'critical');
                } else {
                    log('✅ No service workers found', 'success');
                }
            }

            // Test 2: Check cache API
            if ('caches' in window) {
                const cacheNames = await caches.keys();
                if (cacheNames.length > 0) {
                    log(`❌ ${cacheNames.length} browser caches found`, 'critical');
                } else {
                    log('✅ No browser caches found', 'success');
                }
            }

            // Test 3: Test actual page caching
            const testResponse = await fetch(window.location.href, {
                cache: 'no-cache',
                headers: {
                    'Cache-Control': 'no-cache',
                    'Pragma': 'no-cache'
                }
            });

            const cacheControl = testResponse.headers.get('Cache-Control');
            if (cacheControl && cacheControl.includes('no-cache')) {
                log('✅ This page properly set to no-cache', 'success');
            } else {
                log(`❌ This page cache headers: ${cacheControl}`, 'critical');
            }

            log('🔍 Complete diagnostic finished - check results above', 'result');
        }

        function updateStatus() {
            const status = document.getElementById('status');
            let html = '<h3>Current Browser State:</h3>';
            
            html += `<p><strong>User Agent:</strong> ${navigator.userAgent}</p>`;
            html += `<p><strong>Page loaded at:</strong> ${new Date().toLocaleString()}</p>`;
            html += `<p><strong>Service Worker support:</strong> ${'serviceWorker' in navigator ? 'Yes' : 'No'}</p>`;
            html += `<p><strong>Cache API support:</strong> ${'caches' in window ? 'Yes' : 'No'}</p>`;
            
            status.innerHTML = html;
        }

        // Auto-run on page load
        document.addEventListener('DOMContentLoaded', function() {
            updateStatus();
            preventBFCache();
            
            log('🚨 CACHING ISSUE ANALYSIS STARTED', 'critical');
            log('📍 Multiple cache layers detected requiring fixes', 'warning');
            
            // Auto-test current headers
            setTimeout(() => {
                testCurrentHeaders();
            }, 1000);
        });

        // Prevent this page from being cached
        window.addEventListener('beforeunload', function() {
            sessionStorage.setItem('needsReload', 'true');
        });
    </script>

    <div class="section">
        <h2>📋 MANUAL SERVER FIXES REQUIRED</h2>
        <div class="critical">
            <h3>1. Fix Apache Server Configuration</h3>
            <p><strong>File:</strong> /etc/apache2/conf-enabled/optimization.conf</p>
            <p><strong>Problem:</strong> Global cache rules override .htaccess</p>
            <p><strong>Commands to run:</strong></p>
            <pre style="background: #000; padding: 10px; color: #0f0;">
sudo nano /etc/apache2/conf-enabled/optimization.conf

# Comment out or remove these lines:
# ExpiresByType text/css "access plus 1 month"
# ExpiresByType application/javascript "access plus 1 month"

sudo systemctl reload apache2
            </pre>
        </div>

        <div class="warning">
            <h3>2. Test Different Browsers/Devices</h3>
            <p>• <strong>Chrome Incognito:</strong> Should work correctly</p>
            <p>• <strong>Different browser:</strong> Test Firefox or Edge</p>
            <p>• <strong>Mobile device:</strong> Clear browser cache completely</p>
            <p>• <strong>Different network:</strong> Test from different location</p>
        </div>
    </div>

    <p><strong>Next Steps:</strong> Run the server fixes above, then test navigation without F5</p>
</body>
</html> 