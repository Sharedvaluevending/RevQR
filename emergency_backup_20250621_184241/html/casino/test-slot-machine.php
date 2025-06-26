<?php
/**
 * Slot Machine Loading Test
 * Simple test page to verify slot machine functionality
 */

// Minimal configuration
$APP_URL = 'https://revenueqr.sharedvaluevending.com';
$test_business_id = 1;
$test_user_balance = 1000;
$test_spins_remaining = 10;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Slot Machine Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: #1a1a1a; color: white; }
        .test-info { background: rgba(0,123,255,0.1); border-left: 4px solid #007bff; }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <div class="row mb-4">
            <div class="col-12">
                <div class="alert test-info">
                    <h4><i class="bi bi-wrench me-2"></i>Slot Machine Test Page</h4>
                    <p>This page tests slot machine loading without full authentication.</p>
                    <p><strong>Test Parameters:</strong></p>
                    <ul>
                        <li>Business ID: <?php echo $test_business_id; ?></li>
                        <li>User Balance: <?php echo number_format($test_user_balance); ?> QR Coins</li>
                        <li>Spins Remaining: <?php echo $test_spins_remaining; ?></li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Loading Indicator -->
        <div id="loadingIndicator" class="text-center py-4">
            <div class="d-flex align-items-center justify-content-center">
                <div class="spinner-border text-warning me-3" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <div>
                    <h5 class="mb-1">Loading Casino Assets...</h5>
                    <small class="text-muted">Testing slot machine initialization</small>
                </div>
            </div>
            <div class="progress mt-3" style="height: 8px;">
                <div class="progress-bar bg-warning progress-bar-striped progress-bar-animated" 
                     role="progressbar" style="width: 100%"></div>
            </div>
        </div>

        <!-- Slot Machine Container -->
        <div id="slotMachine" class="slot-machine-container" style="display: none;">
            <div class="text-center">
                <h2 class="text-success">✅ Slot Machine Loaded Successfully!</h2>
                <p>The slot machine has initialized properly.</p>
            </div>
            
            <!-- Simple slot machine display -->
            <div class="row justify-content-center mt-4">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-body text-center">
                            <h5>Slot Machine Reels</h5>
                            <div class="d-flex justify-content-center gap-3 my-4">
                                <div class="slot-reel border rounded p-3" id="reel1" style="width: 100px; height: 120px; background: #f8f9fa;">
                                    <div class="slot-symbol-container">
                                        <!-- Symbols will be populated by JavaScript -->
                                    </div>
                                </div>
                                <div class="slot-reel border rounded p-3" id="reel2" style="width: 100px; height: 120px; background: #f8f9fa;">
                                    <div class="slot-symbol-container">
                                        <!-- Symbols will be populated by JavaScript -->
                                    </div>
                                </div>
                                <div class="slot-reel border rounded p-3" id="reel3" style="width: 100px; height: 120px; background: #f8f9fa;">
                                    <div class="slot-symbol-container">
                                        <!-- Symbols will be populated by JavaScript -->
                                    </div>
                                </div>
                            </div>
                            
                            <button id="spinButton" class="btn btn-danger btn-lg">
                                <i class="bi bi-dice-5-fill me-2"></i>TEST SPIN
                            </button>
                            
                            <div class="mt-3">
                                <small>Balance: <span id="userBalance"><?php echo number_format($test_user_balance); ?></span> QR Coins</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Console Log Display -->
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="bi bi-terminal me-2"></i>Console Output</h6>
                    </div>
                    <div class="card-body">
                        <pre id="consoleOutput" style="max-height: 300px; overflow-y: auto; background: #1a1a1a; color: #00ff00; padding: 15px; border-radius: 5px; font-family: monospace; font-size: 12px;"></pre>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Pass test data to JavaScript -->
    <script>
        // Capture console logs
        const originalLog = console.log;
        const originalError = console.error;
        const originalWarn = console.warn;
        const consoleOutput = document.getElementById('consoleOutput');
        
        function addToConsole(type, ...args) {
            const timestamp = new Date().toLocaleTimeString();
            const message = args.map(arg => typeof arg === 'object' ? JSON.stringify(arg, null, 2) : arg).join(' ');
            consoleOutput.textContent += `[${timestamp}] ${type.toUpperCase()}: ${message}\n`;
            consoleOutput.scrollTop = consoleOutput.scrollHeight;
        }
        
        console.log = function(...args) {
            originalLog.apply(console, args);
            addToConsole('log', ...args);
        };
        
        console.error = function(...args) {
            originalError.apply(console, args);
            addToConsole('error', ...args);
        };
        
        console.warn = function(...args) {
            originalWarn.apply(console, args);
            addToConsole('warn', ...args);
        };

        // Test casino data
        window.casinoData = {
            businessId: <?php echo $test_business_id; ?>,
            userBalance: <?php echo $test_user_balance; ?>,
            jackpotMultiplier: 6,
            appUrl: '<?php echo $APP_URL; ?>',
            spinInfo: {
                spins_remaining: <?php echo $test_spins_remaining; ?>,
                total_spins: 10,
                spins_used: 0,
                bonus_spins: 0
            }
        };

        console.log('🎰 Test page loaded with casino data:', window.casinoData);
    </script>

    <!-- Include GSAP Animation Library -->
    <script src="https://cdn.jsdelivr.net/npm/gsap@3.12.2/dist/gsap.min.js"></script>

    <!-- Include Slot Machine JavaScript -->
    <script src="<?php echo $APP_URL; ?>/casino/js/slot-machine.js?v=<?php echo time(); ?>"></script>

    <script>
        // Additional test functionality
        setTimeout(() => {
            if (window.slotMachine) {
                console.log('✅ Slot machine instance created successfully');
                console.log('Slot machine object:', window.slotMachine);
            } else {
                console.error('❌ Slot machine instance not found');
            }
        }, 2000);
    </script>
</body>
</html> 