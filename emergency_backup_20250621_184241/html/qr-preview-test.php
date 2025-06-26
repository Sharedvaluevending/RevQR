<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Preview Test</title>
    <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .preview-container { border: 1px solid #ccc; padding: 20px; margin: 10px 0; }
        input, select, button { margin: 5px; padding: 8px; }
        #qrPreview { margin: 20px 0; }
    </style>
</head>
<body>
    <h1>🖼️ QR Preview Test</h1>
    
    <div class="preview-container">
        <h3>Settings</h3>
        <label>QR Type:</label>
        <select id="qrType">
            <option value="">Select Type</option>
            <option value="static">Static QR</option>
            <option value="dynamic">Dynamic QR</option>
            <option value="dynamic_vending">Vending Machine</option>
        </select><br>
        
        <label>URL:</label>
        <input type="url" id="url" value="https://example.com" style="width: 300px;"><br>
        
        <label>Size:</label>
        <input type="range" id="sizeRange" min="200" max="600" value="300">
        <span id="sizeValue">300px</span><br>
        
        <label>Colors:</label>
        <input type="color" id="foregroundColor" value="#000000">
        <input type="color" id="backgroundColor" value="#FFFFFF"><br>
        
        <button onclick="testPreview()">Generate Preview</button>
        <button onclick="showPlaceholder()">Show Placeholder</button>
    </div>
    
    <div class="preview-container">
        <h3>Preview</h3>
        <div id="qrPreview" style="display: none;"></div>
        <div id="previewPlaceholder" style="text-align: center; padding: 50px; color: #666;">
            <div style="font-size: 48px;">📱</div>
            <p>Configure QR code to see preview</p>
        </div>
        <div id="previewInfo">
            Type: <span id="previewType">None</span> | 
            Size: <span id="previewSize">300px</span>
        </div>
    </div>
    
    <div class="preview-container">
        <h3>Debug Log</h3>
        <div id="debugLog" style="background: #f5f5f5; padding: 10px; font-family: monospace; height: 200px; overflow-y: scroll;"></div>
    </div>

    <script>
    // Debug logging
    function debugLog(message) {
        const log = document.getElementById("debugLog");
        log.innerHTML += new Date().toLocaleTimeString() + ": " + message + "<br>";
        log.scrollTop = log.scrollHeight;
        console.log(message);
    }
    
    
// Enhanced QR Preview with better error handling
function generatePreview() {
    console.log('🖼️ Generating QR preview...');
    
    const qrType = document.getElementById('qrType')?.value;
    const qrPreview = document.getElementById('qrPreview');
    const placeholder = document.getElementById('previewPlaceholder');
    
    if (!qrType) {
        console.log('No QR type selected');
        showPlaceholder();
        return;
    }
    
    if (!qrPreview || !placeholder) {
        console.error('Preview elements not found');
        return;
    }
    
    let content = '';
    
    // Generate content based on QR type
    switch(qrType) {
        case 'static':
        case 'dynamic':
            const url = document.getElementById('url')?.value;
            content = url || 'https://example.com';
            break;
        case 'dynamic_voting':
            const campaignId = document.getElementById('campaignId')?.value;
            content = `https://revenueqr.sharedvaluevending.com/vote.php?campaign_id=${campaignId || '1'}`;
            break;
        case 'dynamic_vending':
            const machineName = document.getElementById('machineName')?.value;
            content = `https://revenueqr.sharedvaluevending.com/public/promotions.php?machine=${encodeURIComponent(machineName || 'Sample Machine')}&view=vending`;
            break;
        case 'machine_sales':
            const machineNameSales = document.getElementById('machineName')?.value;
            content = `https://revenueqr.sharedvaluevending.com/public/promotions.php?machine=${encodeURIComponent(machineNameSales || 'Sample Machine')}`;
            break;
        case 'promotion':
            const machineNamePromotion = document.getElementById('machineName')?.value;
            content = `https://revenueqr.sharedvaluevending.com/public/promotions.php?machine=${encodeURIComponent(machineNamePromotion || 'Sample Machine')}&view=promotions`;
            break;
        case 'spin_wheel':
            content = 'https://revenueqr.sharedvaluevending.com/public/spin-wheel.php?wheel_id=1';
            break;
        case 'pizza_tracker':
            content = 'https://revenueqr.sharedvaluevending.com/public/pizza-tracker.php?tracker_id=1';
            break;
        default:
            content = 'https://example.com';
    }
    
    console.log('QR Content:', content);
    
    const size = parseInt(document.getElementById('sizeRange')?.value || 300);
    const foregroundColor = document.getElementById('foregroundColor')?.value || '#000000';
    const backgroundColor = document.getElementById('backgroundColor')?.value || '#FFFFFF';
    
    console.log('QR Settings:', { size, foregroundColor, backgroundColor });
    
    // Check if QRCode library is loaded
    if (typeof QRCode === 'undefined') {
        console.error('QRCode library not loaded!');
        showPlaceholder();
        return;
    }
    
    // Generate QR code preview
    try {
        QRCode.toCanvas(content, {
            width: Math.min(size, 300),
            height: Math.min(size, 300),
            color: {
                dark: foregroundColor,
                light: backgroundColor
            },
            margin: 2,
            errorCorrectionLevel: 'H'
        }, function (error, canvas) {
            if (error) {
                console.error('QR generation error:', error);
                showPlaceholder();
                return;
            }
            
            console.log('✅ QR preview generated successfully');
            
            // Clear previous content and add new canvas
            qrPreview.innerHTML = '';
            qrPreview.appendChild(canvas);
            
            // Show preview, hide placeholder
            qrPreview.style.display = 'block';
            placeholder.style.display = 'none';
            
            // Update preview info
            updatePreviewInfo(qrType, size);
        });
    } catch (e) {
        console.error('QR generation exception:', e);
        showPlaceholder();
    }
}

function showPlaceholder() {
    console.log('Showing placeholder');
    const qrPreview = document.getElementById('qrPreview');
    const placeholder = document.getElementById('previewPlaceholder');
    
    if (qrPreview) qrPreview.style.display = 'none';
    if (placeholder) placeholder.style.display = 'block';
}

function updatePreviewInfo(type, size) {
    const previewType = document.getElementById('previewType');
    const previewSize = document.getElementById('previewSize');
    
    if (previewType) previewType.textContent = type.replace('_', ' ').toUpperCase();
    if (previewSize) previewSize.textContent = size + 'px';
}

// Enhanced event listeners with better error handling
document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 QR Generator DOM loaded');
    
    // Test QRCode library
    if (typeof QRCode === 'undefined') {
        console.error('❌ QRCode library not loaded!');
        showToast('QRCode library not loaded. Preview may not work.', 'danger');
    } else {
        console.log('✅ QRCode library loaded');
    }
    
    // QR Type changes
    const qrTypeSelect = document.getElementById('qrType');
    if (qrTypeSelect) {
        qrTypeSelect.addEventListener('change', function() {
            console.log('QR type changed to:', this.value);
            generatePreview();
        });
    }
    
    // Size changes
    const sizeRange = document.getElementById('sizeRange');
    if (sizeRange) {
        sizeRange.addEventListener('input', function() {
            const sizeValue = document.getElementById('sizeValue');
            if (sizeValue) sizeValue.textContent = this.value;
            generatePreview();
        });
    }
    
    // Color changes with debouncing
    let colorTimeout;
    function debouncedPreview() {
        clearTimeout(colorTimeout);
        colorTimeout = setTimeout(generatePreview, 300);
    }
    
    ['foregroundColor', 'backgroundColor', 'foregroundHex', 'backgroundHex'].forEach(id => {
        const element = document.getElementById(id);
        if (element) {
            element.addEventListener('input', debouncedPreview);
        }
    });
    
    // URL changes
    const urlField = document.getElementById('url');
    if (urlField) {
        urlField.addEventListener('input', debouncedPreview);
    }
    
    // Machine name changes
    const machineNameField = document.getElementById('machineName');
    if (machineNameField) {
        machineNameField.addEventListener('input', debouncedPreview);
    }
    
    // Generate initial preview
    setTimeout(() => {
        console.log('Generating initial preview...');
        generatePreview();
    }, 500);
});

    
    function testPreview() {
        debugLog("🧪 Manual preview test triggered");
        generatePreview();
    }
    
    // Test QRCode library on load
    window.addEventListener("load", function() {
        debugLog("Page loaded");
        if (typeof QRCode !== "undefined") {
            debugLog("✅ QRCode library is available");
        } else {
            debugLog("❌ QRCode library is NOT available");
        }
    });
    </script>
</body>
</html>