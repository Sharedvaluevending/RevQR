<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';

// Require admin role (same as other admin pages)
require_role('admin');

// Initialize scanner logs table if it doesn't exist
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS admin_scanner_logs (
            id INT PRIMARY KEY AUTO_INCREMENT,
            admin_user_id INT NOT NULL,
            qr_code_scanned VARCHAR(255),
            payload_data JSON,
            scan_type ENUM('live', 'test', 'invalid') DEFAULT 'test',
            device_info JSON,
            processing_time_ms INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (admin_user_id) REFERENCES users(id)
        )
    ");
} catch (Exception $e) {
    error_log("Failed to create admin_scanner_logs table: " . $e->getMessage());
}

$page_title = "QR Code Scanner - Admin Test Tool";
require_once __DIR__ . '/../core/includes/header.php';
?>

<div class="container py-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="mb-0">
                        <i class="bi bi-qr-code-scan text-primary me-2"></i>Admin QR Scanner
                    </h1>
                    <p class="text-muted">NIAX POS Emulation & Testing Tool</p>
                </div>
                <div class="btn-group">
                    <button type="button" class="btn btn-primary" id="startScanner">
                        <i class="bi bi-camera me-1"></i>Start Camera
                    </button>
                    <button type="button" class="btn btn-secondary" id="stopScanner" disabled>
                        <i class="bi bi-camera-fill me-1"></i>Stop Camera
                    </button>
                    <button type="button" class="btn btn-info" id="toggleFullscreen">
                        <i class="bi bi-fullscreen me-1"></i>Fullscreen
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Camera Scanner Section -->
        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="bi bi-camera-video me-2"></i>Live Scanner
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div id="scanner-container" class="position-relative">
                        <video id="scanner-preview" class="w-100" style="max-height: 400px; object-fit: cover;" autoplay muted></video>
                        <canvas id="scanner-canvas" style="display: none;"></canvas>
                        
                        <!-- Scanner Overlay -->
                        <div class="scanner-overlay position-absolute top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center">
                            <div class="scanner-frame border border-light border-3" style="width: 250px; height: 250px; background: rgba(255,255,255,0.1); border-radius: 10px;"></div>
                        </div>
                        
                        <!-- Status Overlay -->
                        <div class="scanner-status position-absolute bottom-0 start-0 w-100 p-3 bg-dark bg-opacity-75 text-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <small class="d-block">Status: <span id="scanner-status">Ready</span></small>
                                    <small class="d-block">FPS: <span id="scanner-fps">0</span></small>
                                </div>
                                <div class="text-end">
                                    <small class="d-block">Last Scan: <span id="last-scan-time">None</span></small>
                                    <small class="d-block">Total Scans: <span id="total-scans">0</span></small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Scanner Instructions -->
                    <div class="p-3 bg-light border-top">
                        <div class="row text-center">
                            <div class="col-4">
                                <i class="bi bi-camera text-primary fs-4"></i>
                                <p class="small mb-0 mt-1">Position QR code in frame</p>
                            </div>
                            <div class="col-4">
                                <i class="bi bi-brightness-high text-warning fs-4"></i>
                                <p class="small mb-0 mt-1">Ensure good lighting</p>
                            </div>
                            <div class="col-4">
                                <i class="bi bi-check-circle text-success fs-4"></i>
                                <p class="small mb-0 mt-1">Auto-detect & process</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Results & Processing Section -->
        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">
                        <i class="bi bi-cpu me-2"></i>NIAX Processing Results
                    </h5>
                </div>
                <div class="card-body">
                    <div id="scan-results" class="mb-4">
                        <div class="text-center text-muted py-4">
                            <i class="bi bi-qr-code display-4"></i>
                            <p class="mt-2">No QR code scanned yet</p>
                            <small>Scan a QR code to see processing details</small>
                        </div>
                    </div>

                    <!-- Quick Test Buttons -->
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <button type="button" class="btn btn-outline-primary btn-sm w-100" onclick="testSampleQR('dynamic_voting')">
                                <i class="bi bi-hand-thumbs-up me-1"></i>Test Voting QR
                            </button>
                        </div>
                        <div class="col-6">
                            <button type="button" class="btn btn-outline-success btn-sm w-100" onclick="testSampleQR('machine_sales')">
                                <i class="bi bi-cart me-1"></i>Test Sales QR
                            </button>
                        </div>
                        <div class="col-6">
                            <button type="button" class="btn btn-outline-warning btn-sm w-100" onclick="testSampleQR('spin_wheel')">
                                <i class="bi bi-arrow-clockwise me-1"></i>Test Spin QR
                            </button>
                        </div>
                        <div class="col-6">
                            <button type="button" class="btn btn-outline-info btn-sm w-100" onclick="testSampleQR('promotion')">
                                <i class="bi bi-gift me-1"></i>Test Promo QR
                            </button>
                        </div>
                    </div>

                    <!-- Manual Input -->
                    <div class="input-group mb-3">
                        <input type="text" class="form-control" id="manual-qr-input" placeholder="Enter QR code manually...">
                        <button class="btn btn-outline-secondary" type="button" onclick="processManualQR()">
                            <i class="bi bi-play me-1"></i>Process
                        </button>
                    </div>

                    <!-- Camera Troubleshooting -->
                    <div class="collapse" id="troubleshootingGuide">
                        <div class="border rounded p-3 bg-light">
                            <h6 class="text-dark mb-3">
                                <i class="bi bi-question-circle me-2"></i>Camera Troubleshooting
                            </h6>
                            
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="small">
                                        <strong class="text-primary">Permission Issues:</strong>
                                        <ul class="mb-2 ps-3">
                                            <li>Check camera permissions in browser settings</li>
                                            <li>Look for camera icon in address bar</li>
                                            <li>Try refreshing the page</li>
                                        </ul>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="small">
                                        <strong class="text-success">HTTPS Required:</strong>
                                        <ul class="mb-2 ps-3">
                                            <li>Camera requires secure connection</li>
                                            <li>Works on localhost/127.0.0.1</li>
                                            <li>Production must use HTTPS</li>
                                        </ul>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="small">
                                        <strong class="text-info">Browser Support:</strong>
                                        <ul class="mb-2 ps-3">
                                            <li>Chrome 87+, Firefox 85+, Safari 14+</li>
                                            <li>Mobile browsers supported</li>
                                            <li>Try different browser if needed</li>
                                        </ul>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="small">
                                        <strong class="text-warning">Alternative Options:</strong>
                                        <ul class="mb-2 ps-3">
                                            <li>Use manual QR input above</li>
                                            <li>Test with sample QR buttons</li>
                                            <li>Upload QR image (if available)</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="border-top pt-3 mt-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <small class="text-muted">
                                        <i class="bi bi-info-circle me-1"></i>
                                        Current: <span id="current-protocol"><?= $_SERVER['REQUEST_SCHEME'] ?? 'http' ?>://<?= $_SERVER['HTTP_HOST'] ?></span>
                                    </small>
                                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="checkCameraStatus()">
                                        <i class="bi bi-camera-video me-1"></i>Check Camera
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="text-center mt-2">
                        <button class="btn btn-link btn-sm text-decoration-none" type="button" data-bs-toggle="collapse" data-bs-target="#troubleshootingGuide">
                            <i class="bi bi-question-circle me-1"></i>Camera not working? Click for help
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Scans -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="bi bi-clock-history me-2"></i>Recent Scanner Activity
                        </h5>
                        <button type="button" class="btn btn-outline-danger btn-sm" onclick="clearScanHistory()">
                            <i class="bi bi-trash me-1"></i>Clear History
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Time</th>
                                    <th>QR Code</th>
                                    <th>Type</th>
                                    <th>Processing Time</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="scan-history">
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-3">
                                        No scans recorded yet
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- QR Code Libraries -->
<script src="https://cdn.jsdelivr.net/npm/qr-scanner@1.4.2/qr-scanner.umd.min.js"></script>

<script>
let scanner = null;
let scanCount = 0;
let fpsCounter = 0;
let lastFpsUpdate = Date.now();

// Initialize scanner
document.getElementById('startScanner').addEventListener('click', startScanner);
document.getElementById('stopScanner').addEventListener('click', stopScanner);
document.getElementById('toggleFullscreen').addEventListener('click', toggleFullscreen);

async function startScanner() {
    try {
        const video = document.getElementById('scanner-preview');
        const startBtn = document.getElementById('startScanner');
        const stopBtn = document.getElementById('stopScanner');
        
        startBtn.disabled = true;
        updateScannerStatus('Checking camera availability...');
        
        // Check if camera is available
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            throw new Error('Camera not supported on this device/browser');
        }
        
        // Check HTTPS requirement
        if (location.protocol !== 'https:' && location.hostname !== 'localhost' && location.hostname !== '127.0.0.1') {
            updateScannerStatus('HTTPS required for camera access');
            showCameraFallback();
            startBtn.disabled = false;
            return;
        }
        
        updateScannerStatus('Requesting camera permission...');
        
        // Test camera access first
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
            stream.getTracks().forEach(track => track.stop()); // Stop the test stream
        } catch (permissionError) {
            throw new Error(`Camera permission denied: ${permissionError.message}`);
        }
        
        updateScannerStatus('Initializing scanner...');
        
        scanner = new QrScanner(
            video,
            result => handleQRResult(result),
            {
                onDecodeError: error => {
                    // Handle decode errors silently
                    fpsCounter++;
                    updateFPS();
                },
                highlightScanRegion: true,
                highlightCodeOutline: true,
                preferredCamera: 'environment', // Try to use back camera first
                maxScansPerSecond: 5, // Limit scanning frequency
            }
        );
        
        await scanner.start();
        
        startBtn.disabled = true;
        stopBtn.disabled = false;
        updateScannerStatus('Scanning for QR codes...');
        
    } catch (error) {
        console.error('Failed to start scanner:', error);
        
        let errorMessage = 'Failed to start camera. ';
        let fallbackNeeded = false;
        
        if (error.message.includes('NotAllowedError') || error.message.includes('permission')) {
            errorMessage += 'Camera permission was denied. Please allow camera access and try again.';
        } else if (error.message.includes('NotFoundError')) {
            errorMessage += 'No camera found on this device.';
            fallbackNeeded = true;
        } else if (error.message.includes('NotSupportedError')) {
            errorMessage += 'Camera not supported on this browser.';
            fallbackNeeded = true;
        } else if (error.message.includes('HTTPS')) {
            errorMessage += 'HTTPS is required for camera access.';
            fallbackNeeded = true;
        } else {
            errorMessage += error.message || 'Unknown error occurred.';
            fallbackNeeded = true;
        }
        
        updateScannerStatus(errorMessage);
        
        if (fallbackNeeded) {
            showCameraFallback();
        }
        
        // Show detailed error information
        showErrorDetails(error);
        
        document.getElementById('startScanner').disabled = false;
    }
}

function showCameraFallback() {
    // Show manual input option prominently
    const fallbackHTML = `
        <div class="alert alert-warning border-0 mt-3" id="camera-fallback-alert">
            <div class="d-flex align-items-center">
                <i class="bi bi-exclamation-triangle me-2 fs-5"></i>
                <div>
                    <strong>Camera not available</strong><br>
                    <small>Use manual QR input below to test QR codes</small>
                </div>
            </div>
        </div>
    `;
    
    const scannerContainer = document.getElementById('scanner-container');
    const existingFallback = document.getElementById('camera-fallback-alert');
    if (!existingFallback) {
        scannerContainer.insertAdjacentHTML('afterend', fallbackHTML);
    }
    
    // Focus on manual input
    document.getElementById('manual-qr-input').focus();
}

function showErrorDetails(error) {
    console.log('Camera Error Details:', {
        name: error.name,
        message: error.message,
        stack: error.stack,
        userAgent: navigator.userAgent,
        protocol: location.protocol,
        hostname: location.hostname,
        hasMediaDevices: !!navigator.mediaDevices,
        hasGetUserMedia: !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia)
    });
}

function stopScanner() {
    if (scanner) {
        scanner.stop();
        scanner.destroy();
        scanner = null;
    }
    
    document.getElementById('startScanner').disabled = false;
    document.getElementById('stopScanner').disabled = true;
    updateScannerStatus('Camera stopped');
}

function handleQRResult(result) {
    const qrCode = result.data;
    scanCount++;
    
    updateScannerStatus('Processing QR code...');
    document.getElementById('total-scans').textContent = scanCount;
    document.getElementById('last-scan-time').textContent = new Date().toLocaleTimeString();
    
    processQRCode(qrCode, 'live');
}

async function processQRCode(qrCode, scanType = 'test') {
    const startTime = Date.now();
    
    try {
        // Send QR code to backend for processing
        const response = await fetch('/admin/api/process-qr-scan.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                qr_code: qrCode,
                scan_type: scanType,
                device_info: {
                    user_agent: navigator.userAgent,
                    timestamp: new Date().toISOString(),
                    scanner_type: 'admin_tool'
                }
            })
        });
        
        const data = await response.json();
        const processingTime = Date.now() - startTime;
        
        displayScanResult(qrCode, data, processingTime, scanType);
        addToScanHistory(qrCode, data, processingTime, scanType);
        
        if (scanType === 'live') {
            updateScannerStatus('QR code processed successfully');
        }
        
    } catch (error) {
        console.error('Failed to process QR code:', error);
        const processingTime = Date.now() - startTime;
        
        displayScanResult(qrCode, {
            success: false,
            error: 'Failed to process QR code: ' + error.message
        }, processingTime, scanType);
        
        if (scanType === 'live') {
            updateScannerStatus('Processing failed');
        }
    }
}

function displayScanResult(qrCode, data, processingTime, scanType) {
    const resultsDiv = document.getElementById('scan-results');
    
    const statusColor = data.success ? 'success' : 'danger';
    const statusIcon = data.success ? 'check-circle' : 'x-circle';
    
    resultsDiv.innerHTML = `
        <div class="alert alert-${statusColor} border-0">
            <div class="d-flex align-items-center mb-2">
                <i class="bi bi-${statusIcon} me-2 fs-5"></i>
                <strong>${data.success ? 'Processing Successful' : 'Processing Failed'}</strong>
                <span class="badge bg-${statusColor} ms-auto">${processingTime}ms</span>
            </div>
        </div>
        
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label fw-bold">QR Code:</label>
                <div class="form-control bg-light font-monospace small">${qrCode}</div>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-bold">Scan Type:</label>
                <div class="form-control bg-light">
                    <span class="badge bg-${scanType === 'live' ? 'primary' : 'secondary'}">${scanType.toUpperCase()}</span>
                </div>
            </div>
        </div>
        
        ${data.qr_info ? `
        <div class="mt-3">
            <label class="form-label fw-bold">QR Information:</label>
            <div class="bg-light p-3 rounded">
                <div class="row g-2">
                    <div class="col-sm-6"><strong>Type:</strong> ${data.qr_info.qr_type || 'Unknown'}</div>
                    <div class="col-sm-6"><strong>Business ID:</strong> ${data.qr_info.business_id || 'N/A'}</div>
                    <div class="col-sm-6"><strong>Machine:</strong> ${data.qr_info.machine_name || 'Unknown'}</div>
                    <div class="col-sm-6"><strong>Status:</strong> 
                        <span class="badge bg-${data.qr_info.status === 'active' ? 'success' : 'secondary'}">${data.qr_info.status || 'Unknown'}</span>
                    </div>
                </div>
            </div>
        </div>
        ` : ''}
        
        ${data.nayax_simulation ? `
        <div class="mt-3">
            <label class="form-label fw-bold">NIAX Simulation Results:</label>
            <div class="bg-info bg-opacity-10 p-3 rounded border border-info">
                <pre class="mb-0 small">${JSON.stringify(data.nayax_simulation, null, 2)}</pre>
            </div>
        </div>
        ` : ''}
        
        ${data.error ? `
        <div class="mt-3">
            <label class="form-label fw-bold text-danger">Error Details:</label>
            <div class="bg-danger bg-opacity-10 p-3 rounded border border-danger">
                <code class="text-danger">${data.error}</code>
            </div>
        </div>
        ` : ''}
    `;
}

function addToScanHistory(qrCode, data, processingTime, scanType) {
    const historyTable = document.getElementById('scan-history');
    
    // Remove "no scans" message if it exists
    const noScansRow = historyTable.querySelector('tr td[colspan="6"]');
    if (noScansRow) {
        noScansRow.parentElement.remove();
    }
    
    const statusBadge = data.success ? 
        '<span class="badge bg-success">Success</span>' : 
        '<span class="badge bg-danger">Failed</span>';
    
    const typeBadge = `<span class="badge bg-${scanType === 'live' ? 'primary' : 'secondary'}">${scanType}</span>`;
    
    const row = document.createElement('tr');
    row.innerHTML = `
        <td><small class="text-muted">${new Date().toLocaleTimeString()}</small></td>
        <td><code class="small">${qrCode.substring(0, 20)}${qrCode.length > 20 ? '...' : ''}</code></td>
        <td>${data.qr_info ? data.qr_info.qr_type : 'Unknown'}</td>
        <td><span class="badge bg-light text-dark">${processingTime}ms</span></td>
        <td>${statusBadge}</td>
        <td>
            <button class="btn btn-outline-primary btn-sm" onclick="reprocessQR('${qrCode}')">
                <i class="bi bi-arrow-clockwise"></i>
            </button>
        </td>
    `;
    
    historyTable.insertBefore(row, historyTable.firstChild);
    
    // Keep only last 10 entries
    while (historyTable.children.length > 10) {
        historyTable.removeChild(historyTable.lastChild);
    }
}

function updateScannerStatus(status) {
    document.getElementById('scanner-status').textContent = status;
}

function updateFPS() {
    const now = Date.now();
    if (now - lastFpsUpdate >= 1000) {
        document.getElementById('scanner-fps').textContent = fpsCounter;
        fpsCounter = 0;
        lastFpsUpdate = now;
    }
}

function toggleFullscreen() {
    const container = document.getElementById('scanner-container');
    if (!document.fullscreenElement) {
        container.requestFullscreen();
    } else {
        document.exitFullscreen();
    }
}

function testSampleQR(type) {
    // Get a sample QR code of the specified type
    fetch('/admin/api/get-sample-qr.php?type=' + type)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.qr_code) {
                processQRCode(data.qr_code, 'test');
            } else {
                alert('No sample QR code available for type: ' + type);
            }
        })
        .catch(error => {
            console.error('Failed to get sample QR:', error);
            alert('Failed to get sample QR code');
        });
}

function processManualQR() {
    const input = document.getElementById('manual-qr-input');
    const qrCode = input.value.trim();
    
    if (!qrCode) {
        alert('Please enter a QR code');
        return;
    }
    
    processQRCode(qrCode, 'test');
    input.value = '';
}

function reprocessQR(qrCode) {
    processQRCode(qrCode, 'test');
}

function clearScanHistory() {
    if (confirm('Clear all scan history?')) {
        const historyTable = document.getElementById('scan-history');
        historyTable.innerHTML = `
            <tr>
                <td colspan="6" class="text-center text-muted py-3">
                    No scans recorded yet
                </td>
            </tr>
        `;
    }
}

// Load recent scan history on page load
document.addEventListener('DOMContentLoaded', function() {
    loadRecentScans();
});

function loadRecentScans() {
    fetch('/admin/api/get-scanner-history.php')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.scans.length > 0) {
                const historyTable = document.getElementById('scan-history');
                historyTable.innerHTML = '';
                
                data.scans.forEach(scan => {
                    addToScanHistory(
                        scan.qr_code_scanned,
                        { success: scan.scan_type !== 'invalid', qr_info: { qr_type: scan.qr_type } },
                        scan.processing_time_ms,
                        scan.scan_type
                    );
                });
            }
        })
        .catch(error => {
            console.error('Failed to load scan history:', error);
        });
}

async function checkCameraStatus() {
    const btn = event.target;
    const originalText = btn.innerHTML;
    
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Checking...';
    
    try {
        const result = {
            hasMediaDevices: !!navigator.mediaDevices,
            hasGetUserMedia: !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia),
            protocol: location.protocol,
            hostname: location.hostname,
            userAgent: navigator.userAgent,
            cameras: []
        };
        
        if (result.hasGetUserMedia) {
            try {
                // Try to get camera devices
                const devices = await navigator.mediaDevices.enumerateDevices();
                result.cameras = devices.filter(device => device.kind === 'videoinput');
                
                // Try to access camera
                const stream = await navigator.mediaDevices.getUserMedia({ 
                    video: { facingMode: 'environment' } 
                });
                result.cameraAccess = 'granted';
                stream.getTracks().forEach(track => track.stop());
                
            } catch (error) {
                result.cameraAccess = 'denied';
                result.cameraError = error.message;
            }
        }
        
        // Display results
        const statusHTML = `
            <div class="mt-3 p-3 border rounded bg-white">
                <h6 class="mb-3"><i class="bi bi-info-circle me-2"></i>Camera Status Report</h6>
                
                <div class="row g-2 small">
                    <div class="col-md-6">
                        <strong>Browser Support:</strong><br>
                        <span class="badge bg-${result.hasGetUserMedia ? 'success' : 'danger'}">
                            ${result.hasGetUserMedia ? 'Supported' : 'Not Supported'}
                        </span>
                    </div>
                    
                    <div class="col-md-6">
                        <strong>Protocol:</strong><br>
                        <span class="badge bg-${result.protocol === 'https:' || result.hostname === 'localhost' || result.hostname === '127.0.0.1' ? 'success' : 'warning'}">
                            ${result.protocol.toUpperCase()}
                        </span>
                    </div>
                    
                    <div class="col-md-6">
                        <strong>Cameras Found:</strong><br>
                        <span class="badge bg-${result.cameras.length > 0 ? 'success' : 'secondary'}">
                            ${result.cameras.length} device(s)
                        </span>
                    </div>
                    
                    <div class="col-md-6">
                        <strong>Access Permission:</strong><br>
                        <span class="badge bg-${result.cameraAccess === 'granted' ? 'success' : 'danger'}">
                            ${result.cameraAccess === 'granted' ? 'Granted' : 'Denied'}
                        </span>
                    </div>
                </div>
                
                ${result.cameraError ? `
                    <div class="mt-3 p-2 bg-danger bg-opacity-10 rounded border border-danger">
                        <small><strong>Error:</strong> ${result.cameraError}</small>
                    </div>
                ` : ''}
                
                ${result.cameras.length > 0 ? `
                    <div class="mt-3 p-2 bg-success bg-opacity-10 rounded border border-success">
                        <small><strong>Available Cameras:</strong><br>
                        ${result.cameras.map((cam, i) => `${i + 1}. ${cam.label || 'Camera ' + (i + 1)}`).join('<br>')}
                        </small>
                    </div>
                ` : ''}
                
                <div class="mt-3 text-end">
                    <button type="button" class="btn btn-outline-danger btn-sm" onclick="this.parentElement.parentElement.remove()">
                        <i class="bi bi-x me-1"></i>Close
                    </button>
                </div>
            </div>
        `;
        
        // Insert status after troubleshooting guide
        const troubleshootingDiv = document.getElementById('troubleshootingGuide');
        troubleshootingDiv.insertAdjacentHTML('afterend', statusHTML);
        
        // Show troubleshooting guide if it's collapsed
        const collapse = new bootstrap.Collapse(troubleshootingDiv, { show: true });
        
    } catch (error) {
        console.error('Camera status check failed:', error);
        alert('Failed to check camera status: ' + error.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
}
</script>

<style>
.scanner-overlay {
    pointer-events: none;
}

.scanner-frame {
    animation: pulse-scanner 2s infinite;
}

@keyframes pulse-scanner {
    0% { opacity: 0.6; }
    50% { opacity: 1; }
    100% { opacity: 0.6; }
}

.scanner-status {
    font-family: 'Courier New', monospace;
}

#scanner-preview {
    border-radius: 0;
}

.table code {
    background: none;
    color: inherit;
    font-size: 0.85em;
}

.alert pre {
    background: rgba(255,255,255,0.1);
    border-radius: 0.375rem;
    padding: 0.75rem;
    margin: 0;
}
</style>

<?php require_once __DIR__ . '/../core/includes/footer.php'; ?> 