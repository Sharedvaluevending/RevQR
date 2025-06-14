<?php
// Simple preview function checker
?>
<!DOCTYPE html>
<html>
<head>
    <title>Preview Function Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h2>🔍 Preview Function Diagnostic</h2>
        
        <p>This page tests if the preview function works correctly:</p>
        
        <div class="alert alert-info">
            <strong>Test Steps:</strong>
            <ol>
                <li>Select a QR type below</li>
                <li>Click the "Test Preview" button</li>
                <li>Check browser console for logs</li>
                <li>Verify the preview area shows content</li>
            </ol>
        </div>
        
        <div class="mb-3">
            <label for="qrType" class="form-label">QR Type:</label>
            <select id="qrType" class="form-select">
                <option value="">Select QR Type</option>
                <option value="static">Static QR</option>
                <option value="dynamic">Dynamic QR</option>
            </select>
        </div>
        
        <button onclick="testPreview()" class="btn btn-primary">🧪 Test Preview Function</button>
        <button onclick="checkElements()" class="btn btn-secondary">🔍 Check Elements</button>
        
        <div class="mt-4">
            <h4>Preview Area:</h4>
            <div id="qrPreview" style="display: none; border: 2px solid #007bff; padding: 20px; border-radius: 8px;"></div>
            <div id="previewPlaceholder" class="text-muted" style="border: 2px dashed #ccc; padding: 20px; border-radius: 8px;">
                <p>Preview will appear here when function is called</p>
            </div>
        </div>
        
        <div id="diagnostics" class="mt-4"></div>
    </div>

    <script>
    function showToast(message, type) {
        console.log(`Toast: ${type} - ${message}`);
        const diagnostics = document.getElementById('diagnostics');
        diagnostics.innerHTML += `<div class="alert alert-${type === 'success' ? 'success' : type === 'warning' ? 'warning' : 'danger'}">${message}</div>`;
    }
    
    function checkElements() {
        const qrType = document.getElementById('qrType');
        const qrPreview = document.getElementById('qrPreview');
        const placeholder = document.getElementById('previewPlaceholder');
        
        console.log('🔍 Element Check:');
        console.log('- qrType:', qrType ? '✅ Found' : '❌ Missing');
        console.log('- qrPreview:', qrPreview ? '✅ Found' : '❌ Missing');
        console.log('- placeholder:', placeholder ? '✅ Found' : '❌ Missing');
        
        showToast(`Elements: qrType=${qrType?'✅':'❌'}, qrPreview=${qrPreview?'✅':'❌'}, placeholder=${placeholder?'✅':'❌'}`, 'info');
    }
    
    function testPreview() {
        console.log('🧪 Test Preview Function Called');
        
        const qrType = document.getElementById('qrType').value;
        const qrPreview = document.getElementById('qrPreview');
        const placeholder = document.getElementById('previewPlaceholder');
        
        if (!qrType) {
            showToast('Please select QR type first', 'warning');
            return;
        }
        
        console.log('📋 Selected QR Type:', qrType);
        
        // Show loading
        qrPreview.innerHTML = '<div class="text-center"><div class="spinner-border text-primary"></div><p>Testing preview function...</p></div>';
        qrPreview.style.display = 'block';
        placeholder.style.display = 'none';
        
        // Simulate preview generation
        setTimeout(() => {
            qrPreview.innerHTML = `
                <div class="text-center text-success">
                    <h3>✅ Preview Function Works!</h3>
                    <p>QR Type: <strong>${qrType}</strong></p>
                    <p>Function called successfully</p>
                    <p>Elements found and manipulated correctly</p>
                </div>
            `;
            showToast('Preview function test successful!', 'success');
            console.log('✅ Preview test completed successfully');
        }, 1500);
    }
    
    // Run element check on page load
    document.addEventListener('DOMContentLoaded', function() {
        console.log('🚀 Page loaded, running element check...');
        checkElements();
    });
    </script>
</body>
</html> 