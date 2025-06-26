<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';

if (!is_logged_in()) {
    header('Location: ' . APP_URL . '/login.php');
    exit;
}

require_once __DIR__ . '/../core/qr_coin_manager.php';
$user_balance = QRCoinManager::getBalance($_SESSION['user_id']);

include '../core/includes/header.php';
?>

<div class="container py-4">
    <div class="row">
        <div class="col-12">
            <h2>🔄 Balance Update Test</h2>
            <p class="text-muted">Testing that casino balance updates properly sync with the navigation bar.</p>
            
            <div class="row g-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5>Current Balance Info</h5>
                        </div>
                        <div class="card-body">
                            <p><strong>Server Balance:</strong> <span id="serverBalance"><?php echo number_format($user_balance); ?></span> QR Coins</p>
                            <p><strong>Navbar Balance:</strong> <span id="navbarBalanceDisplay">Loading...</span></p>
                            <p><strong>Test Balance:</strong> <span id="testBalance"><?php echo number_format($user_balance); ?></span></p>
                            
                            <button class="btn btn-primary" onclick="refreshBalances()">🔄 Refresh All Balances</button>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5>Balance Update Tests</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <button class="btn btn-success" onclick="testBalanceUpdate(1000)">
                                    ➕ Test +1000 Update
                                </button>
                            </div>
                            <div class="mb-3">
                                <button class="btn btn-warning" onclick="testBalanceUpdate(-500)">
                                    ➖ Test -500 Update
                                </button>
                            </div>
                            <div class="mb-3">
                                <button class="btn btn-info" onclick="testSlotMachineUpdate()">
                                    🎰 Simulate Slot Win
                                </button>
                            </div>
                            <div class="mb-3">
                                <button class="btn btn-secondary" onclick="testBlackjackUpdate()">
                                    🃏 Simulate Blackjack Win
                                </button>
                            </div>
                            
                            <hr>
                            <div class="mt-3">
                                <h6>Quick Links:</h6>
                                <a href="slot-machine.php?business_id=1" class="btn btn-outline-success btn-sm" target="_blank">
                                    Test Real Slots
                                </a>
                                <a href="blackjack-simple.php?location_id=1" class="btn btn-outline-primary btn-sm" target="_blank">
                                    Test Real Blackjack
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5>Console Log</h5>
                        </div>
                        <div class="card-body">
                            <div id="logOutput" class="bg-dark text-light p-3 rounded" style="height: 200px; overflow-y: auto; font-family: monospace; font-size: 0.85em;">
                                <!-- Log messages will appear here -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Test balance update functionality

function log(message) {
    const logOutput = document.getElementById('logOutput');
    const timestamp = new Date().toLocaleTimeString();
    logOutput.innerHTML += `[${timestamp}] ${message}\n`;
    logOutput.scrollTop = logOutput.scrollHeight;
    console.log(message);
}

// Listen for balance update events
window.addEventListener('balanceUpdate', function(e) {
    log(`✅ Received balanceUpdate event: ${e.detail.balance}`);
    updateTestBalance(e.detail.balance);
});

window.addEventListener('balanceUpdated', function(e) {
    log(`✅ Received balanceUpdated event: ${e.detail.newBalance}`);
    updateTestBalance(e.detail.newBalance);
});

function updateTestBalance(newBalance) {
    document.getElementById('testBalance').textContent = new Intl.NumberFormat().format(newBalance);
}

function refreshBalances() {
    // Get navbar balance
    const navbarBalance = document.getElementById('navbarQRBalance');
    if (navbarBalance) {
        document.getElementById('navbarBalanceDisplay').textContent = navbarBalance.textContent;
        log(`📊 Navbar balance: ${navbarBalance.textContent}`);
    } else {
        document.getElementById('navbarBalanceDisplay').textContent = 'Not Found';
        log(`❌ Navbar balance element not found`);
    }
    
    // Get server balance
    fetch('<?php echo APP_URL; ?>/api/get-balance.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('serverBalance').textContent = new Intl.NumberFormat().format(data.balance);
                log(`🗄️ Server balance: ${data.balance}`);
            }
        })
        .catch(error => {
            log(`❌ Error fetching server balance: ${error.message}`);
        });
}

function testBalanceUpdate(change) {
    const currentBalance = parseInt(document.getElementById('testBalance').textContent.replace(/,/g, ''));
    const newBalance = currentBalance + change;
    
    log(`🔄 Testing balance update: ${currentBalance} → ${newBalance} (${change > 0 ? '+' : ''}${change})`);
    
    // Simulate slot machine balance update
    updateNavbarBalance(newBalance);
    
    // Dispatch the update event
    window.dispatchEvent(new CustomEvent('balanceUpdate', {
        detail: { balance: newBalance }
    }));
}

function testSlotMachineUpdate() {
    log(`🎰 Simulating slot machine balance update...`);
    const currentBalance = parseInt(document.getElementById('testBalance').textContent.replace(/,/g, ''));
    const winAmount = 150;
    const newBalance = currentBalance + winAmount;
    
    // Simulate what slot machine does
    updateNavbarBalance(newBalance);
    window.dispatchEvent(new CustomEvent('balanceUpdated', {
        detail: { newBalance: newBalance }
    }));
    window.dispatchEvent(new CustomEvent('balanceUpdate', {
        detail: { balance: newBalance }
    }));
    
    log(`🎰 Slot machine simulation: won ${winAmount}, new balance: ${newBalance}`);
}

function testBlackjackUpdate() {
    log(`🃏 Simulating blackjack balance update...`);
    const currentBalance = parseInt(document.getElementById('testBalance').textContent.replace(/,/g, ''));
    const winAmount = 100;
    const newBalance = currentBalance + winAmount;
    
    // Simulate what blackjack does
    updateNavbarBalance(newBalance);
    window.dispatchEvent(new CustomEvent('balanceUpdate', {
        detail: { balance: newBalance }
    }));
    
    log(`🃏 Blackjack simulation: won ${winAmount}, new balance: ${newBalance}`);
}

function updateNavbarBalance(newBalance) {
    // Update the navbar QR balance display
    const navbarBalance = document.getElementById('navbarQRBalance');
    if (navbarBalance) {
        navbarBalance.textContent = new Intl.NumberFormat().format(newBalance);
        log(`✅ Updated navbar balance to: ${newBalance}`);
    } else {
        log(`❌ Navbar balance element not found`);
    }
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    log(`🚀 Balance update test page loaded`);
    refreshBalances();
});
</script>

<?php include '../core/includes/footer.php'; ?> 