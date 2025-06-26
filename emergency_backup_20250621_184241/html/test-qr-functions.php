<?php
// Quick test page for QR functions
?>
<!DOCTYPE html>
<html>
<head>
    <title>QR Functions Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h2>🧪 QR Functions Test</h2>
        
        <div class="alert alert-success">
            <strong>✅ FIXED:</strong> Preview and Download now call REAL APIs instead of demo mode!
        </div>
        
        <div class="mb-3">
            <label for="qrType" class="form-label">QR Type:</label>
            <select id="qrType" class="form-select">
                <option value="">Select QR Type</option>
                <option value="static">Static QR</option>
                <option value="dynamic">Dynamic QR</option>
            </select>
        </div>
        
        <div class="mb-3">
            <label for="url" class="form-label">URL:</label>
            <input type="url" id="url" class="form-control" value="https://revenueqr.sharedvaluevending.com" placeholder="Enter URL">
        </div>
        
        <div class="mb-3">
            <label for="sizeRange" class="form-label">Size:</label>
            <input type="range" id="sizeRange" class="form-range" min="200" max="800" value="400">
        </div>
        
        <div class="mb-3">
            <label for="foregroundColor" class="form-label">Foreground Color:</label>
            <input type="color" id="foregroundColor" class="form-control form-control-color" value="#000000">
        </div>
        
        <div class="mb-3">
            <label for="backgroundColor" class="form-label">Background Color:</label>
            <input type="color" id="backgroundColor" class="form-control form-control-color" value="#FFFFFF">
        </div>
        
        <div class="mb-4">
            <button onclick="testPreview()" class="btn btn-primary me-2">👁️ Test Preview</button>
            <button onclick="testDownload()" class="btn btn-success">⬇️ Test Download</button>
        </div>
        
        <div class="mt-4">
            <h4>Preview Area:</h4>
            <div id="qrPreview" style="display: none; border: 2px solid #007bff; padding: 20px; border-radius: 8px;"></div>
            <div id="previewPlaceholder" class="text-muted" style="border: 2px dashed #ccc; padding: 20px; border-radius: 8px;">
                <p>Click "Test Preview" to see real QR code generation</p>
            </div>
        </div>
        
        <div id="results" class="mt-4"></div>
    </div>

    <script>
    function showToast(message, type) {
        const results = document.getElementById('results');
        const alertClass = type === 'success' ? 'alert-success' : type === 'warning' ? 'alert-warning' : 'alert-danger';
        results.innerHTML += `<div class="alert ${alertClass}">${message}</div>`;
        console.log(`${type.toUpperCase()}: ${message}`);
    }
    
    function getContentForQRType(qrType) {
        return document.getElementById('url')?.value || 'https://example.com';
    }
    
    function testPreview() {
        console.log('🖼️ Testing preview function');
        
        const qrType = document.getElementById('qrType').value;
        const qrPreview = document.getElementById('qrPreview');
        const placeholder = document.getElementById('previewPlaceholder');
        
        if (!qrType) {
            showToast('Please select a QR type first', 'warning');
            return;
        }
        
        // Show loading
        qrPreview.innerHTML = '<div class="text-center"><div class="spinner-border text-primary"></div><p>Generating REAL QR preview...</p></div>';
        qrPreview.style.display = 'block';
        placeholder.style.display = 'none';
        
        // Get form data
        const formData = {
            qr_type: qrType,
            content: getContentForQRType(qrType),
            size: parseInt(document.getElementById('sizeRange')?.value || 300),
            foreground_color: document.getElementById('foregroundColor')?.value || '#000000',
            background_color: document.getElementById('backgroundColor')?.value || '#FFFFFF'
        };
        
        console.log('📤 Calling preview API with:', formData);
        
        // Call preview API
        fetch('/api/qr/preview.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(formData)
        })
        .then(response => {
            console.log('📥 API Response Status:', response.status);
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            return response.json();
        })
        .then(data => {
            console.log('✅ Preview API Response:', data);
            if (data.success) {
                qrPreview.innerHTML = `
                    <div class="text-center">
                        <img src="data:image/png;base64,${data.preview_data}" alt="QR Preview" style="max-width: 100%; height: auto; border-radius: 8px; border: 2px solid #00a000;">
                        <p class="mt-2 text-success"><strong>✅ REAL QR Code Generated!</strong></p>
                        <p><small>Content: ${data.content}</small></p>
                    </div>
                `;
                showToast('✅ Real QR preview generated successfully!', 'success');
            } else {
                throw new Error(data.error || 'Preview generation failed');
            }
        })
        .catch(error => {
            console.error('❌ Preview error:', error);
            qrPreview.innerHTML = `<div class="text-center text-danger"><i class="bi bi-exclamation-triangle" style="font-size: 3rem;"></i><p class="mt-2">Error: ${error.message}</p></div>`;
            showToast('❌ Preview failed: ' + error.message, 'danger');
        });
    }
    
    function testDownload() {
        console.log('⬇️ Testing download function');
        
        const qrType = document.getElementById('qrType').value;
        if (!qrType) {
            showToast('Please select a QR type first', 'warning');
            return;
        }
        
        showToast('⏳ Generating QR code for download...', 'info');
        
        const formData = {
            qr_type: qrType,
            content: getContentForQRType(qrType),
            size: parseInt(document.getElementById('sizeRange')?.value || 400),
            foreground_color: document.getElementById('foregroundColor')?.value || '#000000',
            background_color: document.getElementById('backgroundColor')?.value || '#FFFFFF'
        };
        
        console.log('📤 Calling generate API with:', formData);
        
        fetch('/api/qr/generate.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(formData)
        })
        .then(response => {
            console.log('📥 Generate API Response Status:', response.status);
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            return response.json();
        })
        .then(data => {
            console.log('✅ Generate API Response:', data);
            if (data.success && data.data && data.data.qr_code_url) {
                // Trigger download
                const link = document.createElement('a');
                link.href = data.data.qr_code_url;
                link.download = `qr_test_${qrType}_${Date.now()}.png`;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                
                showToast('✅ QR code downloaded successfully!', 'success');
            } else {
                throw new Error(data.message || 'No download URL returned');
            }
        })
        .catch(error => {
            console.error('❌ Generate error:', error);
            showToast('❌ Download failed: ' + error.message, 'danger');
        });
    }
    </script>
</body>
</html> 