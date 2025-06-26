<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mobile Desktop Layout Diagnostic</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 25%, #3d72b4 75%, #5a95d1 100%);
            min-height: 100vh;
            padding: 20px 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .diagnostic-card {
            background: rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            margin-bottom: 1.5rem;
            color: white;
        }
        
        .test-card {
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            margin-bottom: 1rem;
            color: white;
        }
        
        .debug-info {
            background: rgba(0, 0, 0, 0.3);
            border-radius: 8px;
            padding: 10px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            margin: 10px 0;
        }
        
        .status-good { color: #28a745; }
        .status-warning { color: #ffc107; }
        .status-error { color: #dc3545; }
        
        /* Test different container behaviors */
        .test-container-bootstrap {
            /* Pure Bootstrap - no custom CSS */
        }
        
        .test-container-fixed {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
        }
        
        .test-container-full {
            width: 100%;
            padding: 0 15px;
        }
        
        .visual-guide {
            border: 2px dashed rgba(255, 255, 255, 0.3);
            padding: 10px;
            margin: 10px 0;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row">
            <div class="col-12">
                <div class="diagnostic-card">
                    <div class="card-body">
                        <h1 class="text-center mb-4">🔍 Mobile Desktop Layout Diagnostic</h1>
                        <p class="text-center">This tool will help identify what's causing left alignment issues</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Device Information -->
        <div class="row">
            <div class="col-12">
                <div class="diagnostic-card">
                    <div class="card-body">
                        <h3>📱 Device Information</h3>
                        <div id="device-info" class="debug-info">Loading...</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Container Tests -->
        <div class="row">
            <div class="col-12">
                <div class="diagnostic-card">
                    <div class="card-body">
                        <h3>📦 Container Behavior Tests</h3>
                        
                        <h5>Test 1: Pure Bootstrap Container</h5>
                        <div class="container test-container-bootstrap">
                            <div class="visual-guide">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="test-card">
                                            <div class="card-body">
                                                <h6>Left Card</h6>
                                                <p>Pure Bootstrap behavior</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="test-card">
                                            <div class="card-body">
                                                <h6>Right Card</h6>
                                                <p>Should be centered naturally</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <h5>Test 2: Fixed Width Container (1200px)</h5>
                        <div class="test-container-fixed">
                            <div class="visual-guide">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="test-card">
                                            <div class="card-body">
                                                <h6>Left Card</h6>
                                                <p>Fixed 1200px max-width</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="test-card">
                                            <div class="card-body">
                                                <h6>Right Card</h6>
                                                <p>Might cause left alignment</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <h5>Test 3: Full Width Container</h5>
                        <div class="test-container-full">
                            <div class="visual-guide">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="test-card">
                                            <div class="card-body">
                                                <h6>Left Card</h6>
                                                <p>Full width container</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="test-card">
                                            <div class="card-body">
                                                <h6>Right Card</h6>
                                                <p>Should use full screen width</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- CSS Diagnostics -->
        <div class="row">
            <div class="col-12">
                <div class="diagnostic-card">
                    <div class="card-body">
                        <h3>🎨 CSS Diagnostics</h3>
                        <div id="css-diagnostics" class="debug-info">Loading...</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Recommendations -->
        <div class="row">
            <div class="col-12">
                <div class="diagnostic-card">
                    <div class="card-body">
                        <h3>💡 Recommendations</h3>
                        <div id="recommendations" class="debug-info">Loading...</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function runDiagnostics() {
            // Device Information
            const deviceInfo = {
                userAgent: navigator.userAgent,
                viewport: {
                    width: window.innerWidth,
                    height: window.innerHeight
                },
                screen: {
                    width: screen.width,
                    height: screen.height,
                    availWidth: screen.availWidth,
                    availHeight: screen.availHeight
                },
                devicePixelRatio: window.devicePixelRatio,
                isMobile: /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent),
                isDesktopMode: window.innerWidth >= 768 && /Android|iPhone|iPad|iPod/i.test(navigator.userAgent)
            };
            
            document.getElementById('device-info').innerHTML = `
                <strong>User Agent:</strong> ${deviceInfo.userAgent}<br>
                <strong>Viewport:</strong> ${deviceInfo.viewport.width}x${deviceInfo.viewport.height}<br>
                <strong>Screen:</strong> ${deviceInfo.screen.width}x${deviceInfo.screen.height}<br>
                <strong>Device Pixel Ratio:</strong> ${deviceInfo.devicePixelRatio}<br>
                <strong>Is Mobile:</strong> ${deviceInfo.isMobile ? 'Yes' : 'No'}<br>
                <strong>Is Desktop Mode on Mobile:</strong> ${deviceInfo.isDesktopMode ? 'Yes' : 'No'}
            `;
            
            // CSS Diagnostics
            const containers = document.querySelectorAll('.container, .test-container-fixed, .test-container-full');
            let cssInfo = '';
            
            containers.forEach((container, index) => {
                const styles = window.getComputedStyle(container);
                const containerType = container.className.includes('test-container-fixed') ? 'Fixed Width' : 
                                    container.className.includes('test-container-full') ? 'Full Width' : 'Bootstrap';
                
                cssInfo += `<strong>${containerType} Container:</strong><br>`;
                cssInfo += `  max-width: ${styles.maxWidth}<br>`;
                cssInfo += `  width: ${styles.width}<br>`;
                cssInfo += `  margin: ${styles.margin}<br>`;
                cssInfo += `  padding: ${styles.padding}<br>`;
                cssInfo += `  display: ${styles.display}<br>`;
                cssInfo += `  justify-content: ${styles.justifyContent}<br>`;
                cssInfo += `  text-align: ${styles.textAlign}<br><br>`;
            });
            
            document.getElementById('css-diagnostics').innerHTML = cssInfo;
            
            // Recommendations
            let recommendations = '';
            
            if (deviceInfo.isDesktopMode) {
                recommendations += '<span class="status-warning">⚠️ Desktop Mode on Mobile Detected</span><br>';
                
                const bootstrapContainer = document.querySelector('.container');
                const bootstrapStyles = window.getComputedStyle(bootstrapContainer);
                
                if (bootstrapStyles.maxWidth === '1200px') {
                    recommendations += '<span class="status-error">❌ Container has fixed 1200px max-width - this causes left alignment</span><br>';
                    recommendations += '<span class="status-good">✅ Fix: Remove max-width constraints and let Bootstrap handle responsive sizing</span><br>';
                } else if (bootstrapStyles.maxWidth === 'none' || bootstrapStyles.maxWidth === '100%') {
                    recommendations += '<span class="status-good">✅ Container max-width looks good</span><br>';
                } else {
                    recommendations += `<span class="status-warning">⚠️ Container max-width: ${bootstrapStyles.maxWidth}</span><br>`;
                }
                
                if (bootstrapStyles.margin.includes('auto')) {
                    recommendations += '<span class="status-good">✅ Container has auto margins for centering</span><br>';
                } else {
                    recommendations += '<span class="status-warning">⚠️ Container margins might not be centering properly</span><br>';
                }
                
            } else {
                recommendations += '<span class="status-good">✅ Not in desktop mode on mobile</span><br>';
            }
            
            document.getElementById('recommendations').innerHTML = recommendations;
        }
        
        // Run diagnostics when page loads and on resize
        window.addEventListener('load', runDiagnostics);
        window.addEventListener('resize', runDiagnostics);
        
        // Initial run
        runDiagnostics();
    </script>
</body>
</html> 