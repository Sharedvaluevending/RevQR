<?php
require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/core/session.php';
include 'core/includes/header.php';
?>

<style>
/* Test page specific styles */
.test-section {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 12px;
    padding: 2rem;
    margin: 1rem 0;
    color: white;
}

.device-info {
    background: rgba(0, 255, 136, 0.1);
    border: 1px solid rgba(0, 255, 136, 0.3);
    border-radius: 8px;
    padding: 1rem;
    margin: 1rem 0;
    font-family: monospace;
    font-size: 0.9rem;
}

.layout-status {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin: 2rem 0;
}

.status-card {
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 8px;
    padding: 1rem;
    text-align: center;
}

.status-good { border-color: rgba(0, 255, 136, 0.5); }
.status-fixed { border-color: rgba(0, 136, 255, 0.5); }
.status-warning { border-color: rgba(255, 193, 7, 0.5); }

.test-content {
    width: 100%;
    background: linear-gradient(45deg, rgba(255, 255, 255, 0.1), rgba(255, 255, 255, 0.05));
    border-radius: 8px;
    padding: 1rem;
    margin: 1rem 0;
    border: 2px dashed rgba(255, 255, 255, 0.3);
}
</style>

<div class="container-fluid">
    <div class="test-section">
        <h1><i class="bi bi-phone me-2"></i>📱 Mobile Layout Test - FIXED</h1>
        <p class="lead">Testing the mobile desktop mode layout fix</p>
        
        <div class="device-info" id="deviceInfo">
            <strong>Loading device information...</strong>
        </div>
        
        <div class="layout-status">
            <div class="status-card status-fixed">
                <h5><i class="bi bi-check-circle text-success"></i> Flexbox Centering</h5>
                <p><small>Removed align-items: center</small></p>
            </div>
            <div class="status-card status-fixed">
                <h5><i class="bi bi-check-circle text-success"></i> Debug Borders</h5>
                <p><small>Removed lime/red borders</small></p>
            </div>
            <div class="status-card status-fixed">
                <h5><i class="bi bi-check-circle text-success"></i> Container Width</h5>
                <p><small>100% width on mobile</small></p>
            </div>
            <div class="status-card status-good">
                <h5><i class="bi bi-check-circle text-primary"></i> Responsive Padding</h5>
                <p><small>Proper mobile spacing</small></p>
            </div>
        </div>
        
        <div class="test-content">
            <h3>📏 Layout Test Content</h3>
            <p>This content should now use the full width of your screen, whether you're in:</p>
            <ul>
                <li>📱 Mobile portrait mode</li>
                <li>📱 Mobile landscape mode</li>
                <li>🖥️ Desktop mode on mobile</li>
                <li>💻 Actual desktop</li>
            </ul>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Left Card</h5>
                            <p class="card-text">This card should align properly without being "shoved left".</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Right Card</h5>
                            <p class="card-text">Both cards should use the full available width.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="alert alert-info">
            <h5><i class="bi bi-info-circle me-2"></i>What Was Fixed:</h5>
            <ol>
                <li><strong>Flexbox Centering:</strong> Removed <code>align-items: center</code> that was forcing content to the left</li>
                <li><strong>Container Constraints:</strong> Changed <code>max-width: 1200px</code> to <code>max-width: 100%</code> for mobile</li>
                <li><strong>Debug Borders:</strong> Removed the lime and red debug borders</li>
                <li><strong>Responsive Breakpoints:</strong> Added proper mobile desktop mode handling</li>
                <li><strong>Main Element:</strong> Changed from flexbox to block display for natural flow</li>
            </ol>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Device detection
    const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
    const isTablet = /iPad|Android.*\b(tablet|large|xlarge)\b/i.test(navigator.userAgent);
    const viewportWidth = window.innerWidth;
    const viewportHeight = window.innerHeight;
    const screenWidth = window.screen.width;
    const screenHeight = window.screen.height;
    
    // Determine mode
    let mode = 'Desktop';
    if (isMobile && viewportWidth < 768) {
        mode = 'Mobile Portrait/Landscape';
    } else if (isMobile && viewportWidth >= 768) {
        mode = 'Mobile Desktop Mode';
    } else if (isTablet) {
        mode = 'Tablet';
    }
    
    // Update device info
    document.getElementById('deviceInfo').innerHTML = `
        <div><strong>🖥️ Screen:</strong> ${screenWidth} × ${screenHeight}</div>
        <div><strong>📐 Viewport:</strong> ${viewportWidth} × ${viewportHeight}</div>
        <div><strong>📱 Device:</strong> ${isMobile ? 'Mobile' : 'Desktop'}</div>
        <div><strong>🎯 Mode:</strong> ${mode}</div>
        <div><strong>🌐 User Agent:</strong> ${navigator.userAgent.substring(0, 60)}...</div>
        <div><strong>✅ Layout Status:</strong> ${mode === 'Mobile Desktop Mode' ? 'FIXED - Should use full width' : 'Normal'}</div>
    `;
    
    // Test container width
    const container = document.querySelector('.container-fluid');
    const containerRect = container.getBoundingClientRect();
    const containerWidth = containerRect.width;
    const windowWidth = window.innerWidth;
    const widthUsage = Math.round((containerWidth / windowWidth) * 100);
    
    console.log('Layout Test Results:', {
        containerWidth: containerWidth,
        windowWidth: windowWidth,
        widthUsage: widthUsage + '%',
        mode: mode,
        isFixed: widthUsage > 90 ? 'YES' : 'NO'
    });
});

// Test resize behavior
window.addEventListener('resize', () => {
    console.log('Resized to:', window.innerWidth, '×', window.innerHeight);
});
</script>

<?php include 'core/includes/footer.php'; ?> 