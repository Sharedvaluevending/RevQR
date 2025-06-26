<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';

// Test page to verify casino fixes
if (!is_logged_in()) {
    header('Location: ' . APP_URL . '/login.php');
    exit;
}

include '../core/includes/header.php';
?>

<div class="container py-4">
    <div class="row">
        <div class="col-12">
            <h2>🎰 Casino Fixes Test Page</h2>
            <p class="text-muted">Testing fixes for blackjack blank screens and slots winner calculation issues.</p>
            
            <div class="row g-4">
                <!-- Blackjack Test -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5>🃏 Blackjack Test</h5>
                        </div>
                        <div class="card-body">
                            <p>Test blackjack with improved error handling and debugging.</p>
                            <a href="blackjack.php?location_id=1" class="btn btn-primary" target="_blank">
                                Test Blackjack
                            </a>
                            <br><small class="text-muted">Opens in new window to preserve error messages</small>
                        </div>
                    </div>
                </div>
                
                <!-- Slots Test -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5>🎰 Slots Test</h5>
                        </div>
                        <div class="card-body">
                            <p>Test slot machine with improved winner calculation logic.</p>
                            <a href="slot-machine.php?business_id=1" class="btn btn-success" target="_blank">
                                Test Slots
                            </a>
                            <br><small class="text-muted">Check browser console for detailed win calculation logs</small>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Debug Info -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5>🔧 Debug Information</h5>
                        </div>
                        <div class="card-body">
                            <h6>Fixes Applied:</h6>
                            <ul class="list-group">
                                <li class="list-group-item">
                                    <strong>Blackjack:</strong> Added proper error handling, try-catch blocks, and JavaScript data validation
                                </li>
                                <li class="list-group-item">
                                    <strong>Slots:</strong> Fixed win calculation logic to properly handle 3x3 grid symbol matching
                                </li>
                                <li class="list-group-item">
                                    <strong>Slots:</strong> Improved losing results generation to avoid accidental wins
                                </li>
                                <li class="list-group-item">
                                    <strong>Both:</strong> Added detailed console logging for debugging winner calculation issues
                                </li>
                            </ul>
                            
                            <h6 class="mt-3">User Info:</h6>
                            <ul class="list-unstyled">
                                <li><strong>User ID:</strong> <?php echo $_SESSION['user_id']; ?></li>
                                <li><strong>Balance:</strong> <?php 
                                    require_once __DIR__ . '/../core/qr_coin_manager.php';
                                    echo number_format(QRCoinManager::getBalance($_SESSION['user_id'])); 
                                ?> QR Coins</li>
                            </ul>
                            
                            <h6 class="mt-3">Test Instructions:</h6>
                            <ol>
                                <li>Open blackjack test - verify no blank screens appear</li>
                                <li>Open slots test - play a few spins and check console for detailed win calculation logs</li>
                                <li>If issues persist, check the PHP error log for additional information</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../core/includes/footer.php'; ?> 