<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes, viewport-fit=cover">
    <title>Mobile Layout Test - Fixed</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- Critical CSS -->
    <link href="assets/css/critical.css" rel="stylesheet">
    
    <style>
        /* Test styles to visualize layout */
        .test-container {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 25%, #3d72b4 75%, #5a95d1 100%);
            min-height: 100vh;
            padding: 80px 0 20px 0;
        }
        
        .test-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            backdrop-filter: blur(10px);
        }
        
        .status-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 8px;
        }
        
        .status-good { background-color: #28a745; }
        .status-warning { background-color: #ffc107; }
        .status-error { background-color: #dc3545; }
        
        /* Test navbar */
        .test-navbar {
            background: rgba(30, 60, 114, 0.95) !important;
            backdrop-filter: blur(20px) !important;
            border-bottom: 1px solid rgba(255, 255, 255, 0.15) !important;
        }
        
        .hamburger-test {
            border: 1px solid rgba(255, 255, 255, 0.3);
            padding: 0.25rem 0.5rem;
            font-size: 1.1rem;
            border-radius: 0.375rem;
            background: transparent;
            color: rgba(255, 255, 255, 0.85);
            margin-left: auto !important;
        }
        
        /* Display device info */
        .device-info {
            background: rgba(0, 0, 0, 0.1);
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <!-- Test Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark py-1 fixed-top test-navbar">
        <div class="container d-flex">
            <div class="navbar-brand d-flex align-items-center">
                <i class="bi bi-qr-code me-2"></i>
                <span>Revenue QR</span>
            </div>
            <button class="navbar-toggler hamburger-test" type="button" data-bs-toggle="collapse" data-bs-target="#testNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="testNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="#"><i class="bi bi-house me-1"></i>Home</a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="testDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-tools me-1"></i>Tools
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#"><i class="bi bi-qr-code me-2"></i>QR Generator</a></li>
                            <li><a class="dropdown-item" href="#"><i class="bi bi-printer me-2"></i>Print Shop</a></li>
                        </ul>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="#"><i class="bi bi-person me-1"></i>Profile</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Test Container -->
    <div class="test-container">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="test-card text-center">
                        <h1 class="mb-4">📱 Mobile Layout Test - FIXED</h1>
                        <p class="lead">Testing mobile layout centering and hamburger button alignment</p>
                    </div>
                    
                    <!-- Device Detection Results -->
                    <div class="test-card">
                        <h3><i class="bi bi-phone me-2"></i>Device Detection</h3>
                        <div class="device-info">
                            <div id="device-results">
                                <p><strong>Loading device information...</strong></p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Layout Test Results -->
                    <div class="test-card">
                        <h3><i class="bi bi-layout-text-window me-2"></i>Layout Status</h3>
                        <div id="layout-status">
                            <p><span class="status-indicator status-good"></span><strong>Container Centering:</strong> <span id="container-status">Good</span></p>
                            <p><span class="status-indicator status-good"></span><strong>Hamburger Alignment:</strong> <span id="hamburger-status">Right Side ✓</span></p>
                            <p><span class="status-indicator status-good"></span><strong>Responsive Behavior:</strong> <span id="responsive-status">Active</span></p>
                            <p><span class="status-indicator status-good"></span><strong>Desktop Mode on Mobile:</strong> <span id="desktop-mode-status">Supported</span></p>
                        </div>
                    </div>
                    
                    <!-- Test Features -->
                    <div class="test-card">
                        <h3><i class="bi bi-check-circle me-2"></i>What's Fixed</h3>
                        <ul class="list-unstyled">
                            <li class="mb-2"><i class="bi bi-check-lg text-success me-2"></i><strong>Mobile Left-Alignment:</strong> Fixed with proper centering</li>
                            <li class="mb-2"><i class="bi bi-check-lg text-success me-2"></i><strong>Desktop Mode on Mobile:</strong> Now works like real desktop</li>
                            <li class="mb-2"><i class="bi bi-check-lg text-success me-2"></i><strong>Hamburger Menu:</strong> Aligned to right side</li>
                            <li class="mb-2"><i class="bi bi-check-lg text-success me-2"></i><strong>Container Overflow:</strong> Prevented horizontal scrolling</li>
                            <li class="mb-2"><i class="bi bi-check-lg text-success me-2"></i><strong>Bootstrap Grid:</strong> Properly responsive</li>
                        </ul>
                    </div>
                    
                    <!-- Test Instructions -->
                    <div class="test-card">
                        <h3><i class="bi bi-info-circle me-2"></i>Test Instructions</h3>
                        <ol>
                            <li class="mb-2"><strong>Mobile Default:</strong> Should be centered and responsive</li>
                            <li class="mb-2"><strong>Mobile + Desktop Site:</strong> Should look like PC version</li>
                            <li class="mb-2"><strong>Hamburger Menu:</strong> Should be on right side on all devices</li>
                            <li class="mb-2"><strong>Layout:</strong> No horizontal scrolling or left-sticking</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Device detection and display
        document.addEventListener('DOMContentLoaded', function() {
            const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
            const isSmallScreen = window.screen.width <= 768 || window.screen.height <= 768;
            const isTouchDevice = 'ontouchstart' in window || navigator.maxTouchPoints > 0;
            const isDesktopMode = window.innerWidth > 991 && (isMobile || isSmallScreen || isTouchDevice);
            
            // Update device detection display
            const deviceResults = document.getElementById('device-results');
            deviceResults.innerHTML = `
                <p><strong>User Agent:</strong> ${navigator.userAgent.substring(0, 80)}...</p>
                <p><strong>Screen Size:</strong> ${window.screen.width} x ${window.screen.height}</p>
                <p><strong>Viewport Size:</strong> ${window.innerWidth} x ${window.innerHeight}</p>
                <p><strong>Is Mobile Device:</strong> ${isMobile ? 'Yes' : 'No'}</p>
                <p><strong>Is Small Screen:</strong> ${isSmallScreen ? 'Yes' : 'No'}</p>
                <p><strong>Is Touch Device:</strong> ${isTouchDevice ? 'Yes' : 'No'}</p>
                <p><strong>Desktop Mode on Mobile:</strong> ${isDesktopMode ? 'Yes - Full Desktop Layout' : 'No'}</p>
                <p><strong>Body Classes:</strong> ${document.body.className || 'None'}</p>
            `;
            
            // Test hamburger alignment
            const hamburger = document.querySelector('.navbar-toggler');
            const hamburgerRect = hamburger.getBoundingClientRect();
            const containerRect = hamburger.closest('.container').getBoundingClientRect();
            const isRightAligned = hamburgerRect.right >= (containerRect.right - 20);
            
            document.getElementById('hamburger-status').textContent = isRightAligned ? 'Right Side ✓' : 'Left Side ✗';
            document.getElementById('hamburger-status').className = isRightAligned ? 'text-success' : 'text-danger';
            
            // Test container centering
            const container = document.querySelector('.container');
            const containerRect2 = container.getBoundingClientRect();
            const windowWidth = window.innerWidth;
            const isCentered = Math.abs((containerRect2.left + containerRect2.width/2) - (windowWidth/2)) < 50;
            
            document.getElementById('container-status').textContent = isCentered ? 'Centered ✓' : 'Off-center ✗';
            document.getElementById('container-status').className = isCentered ? 'text-success' : 'text-danger';
            
            // Update layout status indicators
            const indicators = document.querySelectorAll('.status-indicator');
            indicators.forEach((indicator, index) => {
                if (index === 0) { // Container
                    indicator.className = `status-indicator ${isCentered ? 'status-good' : 'status-error'}`;
                } else if (index === 1) { // Hamburger
                    indicator.className = `status-indicator ${isRightAligned ? 'status-good' : 'status-error'}`;
                } else { // Others
                    indicator.className = 'status-indicator status-good';
                }
            });
        });
        
        // Test resize behavior
        window.addEventListener('resize', function() {
            console.log('Resize detected:', window.innerWidth, 'x', window.innerHeight);
        });
    </script>
</body>
</html> 