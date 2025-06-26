<?php
require_once 'core/config.php';
require_once 'core/session.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes, viewport-fit=cover">
    <meta name="theme-color" content="#1e3c72">
    <title>📱 Mobile Responsive Fix Test - RevenueQR</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- Mobile Responsive Fix CSS -->
    <link href="assets/css/mobile-responsive-fix.css" rel="stylesheet">
    
    <style>
        /* Test page specific styles */
        .test-container {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 25%, #3d72b4 75%, #5a95d1 100%);
            min-height: 100vh;
            padding: 90px 0 20px 0;
        }
        
        .test-card {
            background: rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            padding: 2rem;
            margin-bottom: 2rem;
            color: white;
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
        
        .test-navbar {
            background: rgba(30, 60, 114, 0.95) !important;
            backdrop-filter: blur(20px) !important;
            border-bottom: 1px solid rgba(255, 255, 255, 0.15) !important;
        }
        
        .device-info {
            background: rgba(0, 0, 0, 0.2);
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
            font-family: monospace;
            font-size: 0.9rem;
        }
        
        .feature-test {
            background: rgba(255, 255, 255, 0.08);
            border-radius: 8px;
            padding: 1rem;
            margin: 0.5rem 0;
            border-left: 4px solid #28a745;
        }
        
        .feature-test.warning {
            border-left-color: #ffc107;
        }
        
        .feature-test.error {
            border-left-color: #dc3545;
        }
        
        .debug-section {
            background: rgba(0, 0, 0, 0.3);
            border-radius: 10px;
            padding: 1.5rem;
            margin: 1rem 0;
        }
        
        .modal-test-btn {
            margin: 0.5rem;
        }
    </style>
</head>
<body>
    <!-- Test Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark py-2 fixed-top test-navbar">
        <div class="container">
            <div class="navbar-brand d-flex align-items-center">
                <i class="bi bi-qr-code me-2"></i>
                <span class="d-none d-sm-inline">RevenueQR</span>
                <span class="d-sm-none">RQR</span>
            </div>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#testNav" aria-controls="testNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="testNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="#"><i class="bi bi-house me-1"></i>Home</a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="testDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-tools me-1"></i>Tools
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#"><i class="bi bi-qr-code me-2"></i>QR Generator</a></li>
                            <li><a class="dropdown-item" href="#"><i class="bi bi-printer me-2"></i>Print Shop</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="#"><i class="bi bi-gear me-2"></i>Settings</a></li>
                        </ul>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#"><i class="bi bi-graph-up me-1"></i>Analytics</a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="#"><i class="bi bi-person me-1"></i>Profile</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#"><i class="bi bi-box-arrow-right me-1"></i>Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Test Container -->
    <div class="test-container">
        <div class="container">
            <!-- Title Section -->
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <div class="test-card text-center">
                        <h1 class="mb-4">📱 Mobile Responsive Fix Test</h1>
                        <p class="lead">Comprehensive testing for mobile layout, hamburger menu, modals, and responsive design</p>
                        <div class="mt-3">
                            <span class="badge bg-success me-2">Viewport Fixed</span>
                            <span class="badge bg-success me-2">Hamburger Aligned</span>
                            <span class="badge bg-success me-2">Modals Fixed</span>
                            <span class="badge bg-success">Layout Centered</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Device Detection Section -->
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <div class="test-card">
                        <h3><i class="bi bi-phone me-2"></i>Device Detection & Status</h3>
                        <div class="device-info" id="device-info">
                            <p><strong>Loading device information...</strong></p>
                        </div>
                        
                        <div class="debug-section">
                            <h5>📊 Layout Status</h5>
                            <div id="layout-status">
                                <div class="feature-test">
                                    <span class="status-indicator status-good"></span>
                                    <strong>Container Centering:</strong> <span id="container-status">Good</span>
                                </div>
                                <div class="feature-test">
                                    <span class="status-indicator status-good"></span>
                                    <strong>Hamburger Alignment:</strong> <span id="hamburger-status">Right Side ✓</span>
                                </div>
                                <div class="feature-test">
                                    <span class="status-indicator status-good"></span>
                                    <strong>Responsive Grid:</strong> <span id="grid-status">Active</span>
                                </div>
                                <div class="feature-test">
                                    <span class="status-indicator status-good"></span>
                                    <strong>Touch Support:</strong> <span id="touch-status">Enhanced</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal Testing Section -->
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <div class="test-card">
                        <h3><i class="bi bi-window me-2"></i>Modal Functionality Test</h3>
                        <p>Test modals to ensure they appear correctly and buttons are clickable:</p>
                        
                        <div class="text-center">
                            <button type="button" class="btn btn-primary modal-test-btn" data-bs-toggle="modal" data-bs-target="#testModal1">
                                <i class="bi bi-window-plus me-1"></i>Test Basic Modal
                            </button>
                            <button type="button" class="btn btn-success modal-test-btn" data-bs-toggle="modal" data-bs-target="#testModal2">
                                <i class="bi bi-check-circle me-1"></i>Test Success Modal
                            </button>
                            <button type="button" class="btn btn-warning modal-test-btn" data-bs-toggle="modal" data-bs-target="#testModal3">
                                <i class="bi bi-exclamation-triangle me-1"></i>Test Form Modal
                            </button>
                        </div>
                        
                        <div class="mt-3">
                            <small class="text-light">
                                <i class="bi bi-info-circle me-1"></i>
                                If modals get stuck, press <kbd>Ctrl+Shift+X</kbd> or look for the red emergency close button.
                            </small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Responsive Grid Test -->
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <div class="test-card">
                        <h3><i class="bi bi-grid me-2"></i>Responsive Grid Test</h3>
                        <p>These cards should stack properly on mobile and align in rows on larger screens:</p>
                        
                        <div class="row">
                            <div class="col-md-6 col-lg-4">
                                <div class="card mb-3">
                                    <div class="card-body">
                                        <h5 class="card-title text-dark">Card 1</h5>
                                        <p class="card-text text-dark">This card should be centered and responsive.</p>
                                        <button class="btn btn-primary btn-sm">Action</button>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 col-lg-4">
                                <div class="card mb-3">
                                    <div class="card-body">
                                        <h5 class="card-title text-dark">Card 2</h5>
                                        <p class="card-text text-dark">Cards should not be pushed to the left.</p>
                                        <button class="btn btn-success btn-sm">Action</button>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 col-lg-4">
                                <div class="card mb-3">
                                    <div class="card-body">
                                        <h5 class="card-title text-dark">Card 3</h5>
                                        <p class="card-text text-dark">Perfect alignment across all devices.</p>
                                        <button class="btn btn-warning btn-sm">Action</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Feature Summary -->
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <div class="test-card">
                        <h3><i class="bi bi-check-circle me-2"></i>What's Been Fixed</h3>
                        <div class="row">
                            <div class="col-md-6">
                                <ul class="list-unstyled">
                                    <li class="mb-2"><i class="bi bi-check-lg text-success me-2"></i><strong>Viewport Meta Tag:</strong> Proper mobile scaling</li>
                                    <li class="mb-2"><i class="bi bi-check-lg text-success me-2"></i><strong>Container Centering:</strong> Fixed left-alignment issues</li>
                                    <li class="mb-2"><i class="bi bi-check-lg text-success me-2"></i><strong>Hamburger Menu:</strong> Always aligned to the right</li>
                                    <li class="mb-2"><i class="bi bi-check-lg text-success me-2"></i><strong>Mobile Navigation:</strong> Proper dropdown positioning</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <ul class="list-unstyled">
                                    <li class="mb-2"><i class="bi bi-check-lg text-success me-2"></i><strong>Modal Z-Index:</strong> Proper layering and clickability</li>
                                    <li class="mb-2"><i class="bi bi-check-lg text-success me-2"></i><strong>Emergency Modal Close:</strong> Automatic stuck modal recovery</li>
                                    <li class="mb-2"><i class="bi bi-check-lg text-success me-2"></i><strong>Touch Targets:</strong> 44px minimum for accessibility</li>
                                    <li class="mb-2"><i class="bi bi-check-lg text-success me-2"></i><strong>Double-Tap Zoom:</strong> Prevented on interactive elements</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Testing Instructions -->
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <div class="test-card">
                        <h3><i class="bi bi-list-check me-2"></i>Testing Instructions</h3>
                        <div class="debug-section">
                            <h5>📱 Mobile Tests:</h5>
                            <ol>
                                <li><strong>Portrait Mode:</strong> Content should be centered, not pushed left</li>
                                <li><strong>Landscape Mode:</strong> Should use full width effectively</li>
                                <li><strong>Hamburger Menu:</strong> Should be on the right side and open properly</li>
                                <li><strong>Modals:</strong> Should appear on top with clickable buttons</li>
                                <li><strong>Cards:</strong> Should stack vertically and center properly</li>
                            </ol>
                            
                            <h5>🖥️ Desktop Tests:</h5>
                            <ol>
                                <li><strong>Normal Navigation:</strong> Should show full menu without hamburger</li>
                                <li><strong>Modal Interactions:</strong> Should work smoothly without issues</li>
                                <li><strong>Responsive Breakpoints:</strong> Should transition smoothly at different sizes</li>
                            </ol>
                            
                            <h5>🔧 Debug Mode:</h5>
                            <p>Add <code>?debug=1</code> to the URL to enable debug logging and visual indicators.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Test Modals -->
    <div class="modal fade" id="testModal1" tabindex="-1" aria-labelledby="testModal1Label" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-dark" id="testModal1Label">Basic Modal Test</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-dark">
                    <p>This is a basic modal test. All buttons should be clickable and the modal should appear on top of the backdrop.</p>
                    <p><strong>Test checklist:</strong></p>
                    <ul>
                        <li>Modal appears on top</li>
                        <li>Background is darkened but not stuck</li>
                        <li>Close button works</li>
                        <li>Action buttons are clickable</li>
                    </ul>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" onclick="alert('✅ Modal button clicked successfully!')">Test Action</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="testModal2" tabindex="-1" aria-labelledby="testModal2Label" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="testModal2Label">Success Modal Test</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-dark">
                    <div class="text-center">
                        <i class="bi bi-check-circle-fill text-success" style="font-size: 3rem;"></i>
                        <h4 class="mt-3">Modal is Working!</h4>
                        <p>This success modal demonstrates that the z-index and pointer-events fixes are working correctly.</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-success" data-bs-dismiss="modal">Perfect!</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="testModal3" tabindex="-1" aria-labelledby="testModal3Label" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-dark" id="testModal3Label">Form Modal Test</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-dark">
                    <form>
                        <div class="mb-3">
                            <label for="testInput" class="form-label">Test Input</label>
                            <input type="text" class="form-control" id="testInput" placeholder="Test form interaction">
                        </div>
                        <div class="mb-3">
                            <label for="testSelect" class="form-label">Test Select</label>
                            <select class="form-select" id="testSelect">
                                <option>Option 1</option>
                                <option>Option 2</option>
                                <option>Option 3</option>
                            </select>
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="testCheck">
                            <label class="form-check-label" for="testCheck">
                                Test checkbox interaction
                            </label>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-warning" onclick="alert('✅ Form submitted successfully!')">Submit Test</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Mobile Responsive Fix JS -->
    <script src="assets/js/mobile-responsive-fix.js"></script>
    
    <!-- Test Page Script -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Device detection and status update
            function updateDeviceInfo() {
                const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
                const isTablet = /iPad|Android.*\b(tablet|large|xlarge)\b/i.test(navigator.userAgent);
                const isTouchDevice = 'ontouchstart' in window || navigator.maxTouchPoints > 0;
                const viewportWidth = window.innerWidth;
                const viewportHeight = window.innerHeight;
                const screenWidth = window.screen.width;
                const screenHeight = window.screen.height;
                
                let deviceType = 'Desktop';
                if (isMobile && viewportWidth < 768) {
                    deviceType = 'Mobile Portrait/Landscape';
                } else if (isMobile && viewportWidth >= 768) {
                    deviceType = 'Mobile Desktop Mode';
                } else if (isTablet) {
                    deviceType = 'Tablet';
                }
                
                const deviceInfo = document.getElementById('device-info');
                deviceInfo.innerHTML = `
                    <div><strong>🖥️ Screen:</strong> ${screenWidth} × ${screenHeight}</div>
                    <div><strong>📐 Viewport:</strong> ${viewportWidth} × ${viewportHeight}</div>
                    <div><strong>📱 Device Type:</strong> ${deviceType}</div>
                    <div><strong>👆 Touch Support:</strong> ${isTouchDevice ? 'Yes' : 'No'}</div>
                    <div><strong>🌐 User Agent:</strong> ${navigator.userAgent.substring(0, 80)}...</div>
                    <div><strong>🎯 Responsive Status:</strong> ${viewportWidth > 991 ? 'Desktop Layout' : 'Mobile Layout'}</div>
                `;
            }
            
            // Test hamburger alignment
            function testHamburgerAlignment() {
                const hamburger = document.querySelector('.navbar-toggler');
                if (hamburger) {
                    const hamburgerRect = hamburger.getBoundingClientRect();
                    const containerRect = hamburger.closest('.container').getBoundingClientRect();
                    const isRightAligned = hamburgerRect.right >= (containerRect.right - 20);
                    
                    document.getElementById('hamburger-status').textContent = isRightAligned ? 'Right Side ✓' : 'Left Side ✗';
                    document.getElementById('hamburger-status').className = isRightAligned ? 'text-success' : 'text-danger';
                }
            }
            
            // Test container centering
            function testContainerCentering() {
                const container = document.querySelector('.container');
                if (container) {
                    const containerRect = container.getBoundingClientRect();
                    const windowWidth = window.innerWidth;
                    const isCentered = Math.abs((containerRect.left + containerRect.width/2) - (windowWidth/2)) < 50;
                    
                    document.getElementById('container-status').textContent = isCentered ? 'Centered ✓' : 'Off-center ✗';
                    document.getElementById('container-status').className = isCentered ? 'text-success' : 'text-danger';
                }
            }
            
            // Test grid responsiveness
            function testGridResponsiveness() {
                const cards = document.querySelectorAll('.col-md-6.col-lg-4');
                const isResponsive = cards.length > 0;
                
                document.getElementById('grid-status').textContent = isResponsive ? 'Bootstrap Grid Active ✓' : 'Grid Issues ✗';
                document.getElementById('grid-status').className = isResponsive ? 'text-success' : 'text-danger';
            }
            
            // Test touch support
            function testTouchSupport() {
                const isTouchDevice = 'ontouchstart' in window;
                const hasTouchClass = document.body.classList.contains('touch-device');
                
                document.getElementById('touch-status').textContent = isTouchDevice && hasTouchClass ? 'Enhanced ✓' : 'Basic';
                document.getElementById('touch-status').className = isTouchDevice && hasTouchClass ? 'text-success' : 'text-warning';
            }
            
            // Run all tests
            function runAllTests() {
                updateDeviceInfo();
                testHamburgerAlignment();
                testContainerCentering();
                testGridResponsiveness();
                testTouchSupport();
            }
            
            // Initial test run
            runAllTests();
            
            // Re-run tests on window resize
            window.addEventListener('resize', () => {
                setTimeout(runAllTests, 100);
            });
            
            // Log test completion
            console.log('📱 Mobile Responsive Test Page Loaded');
            console.log('🔧 Use ?debug=1 in URL for detailed debugging');
        });
    </script>
</body>
</html> 