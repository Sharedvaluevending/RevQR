<?php
require_once __DIR__ . '/core/config.php';
?>
<!DOCTYPE html>
<html>
<head>
    <title>CSS Diagnostic - Green Theme Debug</title>
    <style>
        body { 
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 25%, #3d72b4 75%, #5a95d1 100%);
            color: white; 
            font-family: Arial, sans-serif; 
            padding: 20px;
        }
        .diagnostic { 
            background: rgba(255,255,255,0.1); 
            padding: 15px; 
            margin: 10px 0; 
            border-radius: 8px;
        }
        .red { color: #ff4444; }
        .green { color: #44ff44; }
        .blue { color: #4444ff; }
    </style>
</head>
<body>
    <h1>🔍 CSS Diagnostic Tool</h1>
    
    <div class="diagnostic">
        <h3>📁 CSS Files Status:</h3>
        <?php
        $css_files = [
            'html/assets/css/consolidated-theme.css' => 'Main Theme CSS',
            'html/assets/css/consolidated-theme.css.disabled' => 'Disabled Theme CSS',
            'html/assets/css/consolidated-theme.css.backup' => 'Backup Theme CSS',
            'html/assets/css/consolidated-theme.css.backup.disabled' => 'Disabled Backup CSS'
        ];
        
        foreach ($css_files as $file => $description) {
            if (file_exists($file)) {
                echo "<span class='red'>❌ EXISTS: $description ($file)</span><br>";
            } else {
                echo "<span class='green'>✅ MISSING: $description ($file)</span><br>";
            }
        }
        ?>
    </div>
    
    <div class="diagnostic">
        <h3>🎨 Current Computed Styles:</h3>
        <div id="style-check"></div>
    </div>
    
    <div class="diagnostic">
        <h3>📋 All Loaded Stylesheets:</h3>
        <div id="stylesheet-list"></div>
    </div>
    
    <div class="diagnostic">
        <h3>🔍 CSS Variables Check:</h3>
        <div id="css-vars"></div>
    </div>
    
    <div style="margin: 20px 0;">
        <button onclick="window.location.href='qr_manager.php'" style="background: #1976d2; color: white; padding: 10px 20px; border: none; border-radius: 5px;">
            Go to QR Manager
        </button>
        <button onclick="window.location.href='qr_dynamic_manager.php'" style="background: #1976d2; color: white; padding: 10px 20px; border: none; border-radius: 5px;">
            Go to Dynamic QR Manager
        </button>
    </div>

    <script>
    // Check computed styles
    const bodyStyle = getComputedStyle(document.body);
    const navStyle = getComputedStyle(document.querySelector('nav') || document.body);
    
    document.getElementById('style-check').innerHTML = `
        <strong>Body Background:</strong> ${bodyStyle.background}<br>
        <strong>Body Background-Image:</strong> ${bodyStyle.backgroundImage}<br>
        <strong>Navbar Background:</strong> ${navStyle.background || 'No navbar found'}<br>
    `;
    
    // List all stylesheets
    let stylesheetList = '';
    for (let i = 0; i < document.styleSheets.length; i++) {
        const sheet = document.styleSheets[i];
        stylesheetList += `<strong>Sheet ${i+1}:</strong> ${sheet.href || 'Inline styles'}<br>`;
    }
    document.getElementById('stylesheet-list').innerHTML = stylesheetList;
    
    // Check CSS variables
    const style = getComputedStyle(document.documentElement);
    const vars = [
        '--primary-gradient',
        '--brand-primary', 
        '--bs-success',
        '--green',
        '--success'
    ];
    
    let varList = '';
    vars.forEach(varName => {
        const value = style.getPropertyValue(varName);
        varList += `<strong>${varName}:</strong> ${value || 'Not set'}<br>`;
    });
    document.getElementById('css-vars').innerHTML = varList;
    
    console.log('🔍 CSS Diagnostic loaded at:', new Date());
    console.log('📋 Available stylesheets:', document.styleSheets.length);
    </script>
</body>
</html> 