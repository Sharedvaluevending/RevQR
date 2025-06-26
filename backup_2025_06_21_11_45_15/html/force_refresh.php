<?php
header("Cache-Control: no-cache, no-store, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Force Refresh - Blue Theme Fix</title>
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <style>
        body { 
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 25%, #3d72b4 75%, #5a95d1 100%);
            color: white; 
            font-family: Arial, sans-serif; 
            padding: 50px; 
            text-align: center;
        }
        .btn {
            background: #1976d2;
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 8px;
            font-size: 18px;
            cursor: pointer;
            margin: 10px;
        }
        .btn:hover {
            background: #1565c0;
        }
    </style>
</head>
<body>
    <h1>🔄 Blue Theme Cache Fix</h1>
    <p>This page forces a complete cache refresh to fix the green/black navbar issue.</p>
    
    <h3>✅ Cache-Busting Applied:</h3>
    <ul style="text-align: left; max-width: 500px; margin: 0 auto;">
        <li>Removed `bg-dark` class from navbar</li>
        <li>Added inline blue styling with `!important`</li>
        <li>Added cache-busting headers</li>
        <li>Forced browser cache refresh</li>
    </ul>
    
    <div style="margin: 30px 0;">
        <button class="btn" onclick="clearCacheAndRedirect('qr_manager.php')">
            🔵 Go to QR Manager (Blue Fixed)
        </button>
        <br>
        <button class="btn" onclick="clearCacheAndRedirect('qr_dynamic_manager.php')">
            🔵 Go to Dynamic QR Manager (Blue Fixed)
        </button>
        <br>
        <button class="btn" onclick="clearCacheAndRedirect('business_login_temp.php')">
            🔑 Go to Business Login (Blue Fixed)
        </button>
    </div>
    
    <p><small>Timestamp: <?php echo date('Y-m-d H:i:s'); ?> (<?php echo time(); ?>)</small></p>
    
    <script>
    function clearCacheAndRedirect(page) {
        // Clear browser cache
        if ('caches' in window) {
            caches.keys().then(function(names) {
                names.forEach(function(name) {
                    caches.delete(name);
                });
            });
        }
        
        // Force reload with cache-busting parameter
        const timestamp = new Date().getTime();
        window.location.href = page + '?cache_bust=' + timestamp;
    }
    
    // Auto-clear cache on page load
    if ('caches' in window) {
        caches.keys().then(function(names) {
            names.forEach(function(name) {
                caches.delete(name);
                console.log('Cleared cache:', name);
            });
        });
    }
    
    console.log('🔵 Blue theme cache fix applied at:', new Date());
    </script>
</body>
</html> 