<?php
/**
 * Blackjack Balance Debug - Test the API calls specifically
 */
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/functions.php';
require_once __DIR__ . '/../core/qr_coin_manager.php';

// Check if user is logged in
$is_logged_in = is_logged_in();
$user_id = $_SESSION['user_id'] ?? null;
$current_balance = $is_logged_in ? QRCoinManager::getBalance($user_id) : 0;

?>
<!DOCTYPE html>
<html>
<head>
    <title>Blackjack Balance Debug</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container py-4">
    <h1>🃏 Blackjack Balance Debug</h1>
    
    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5>Current Status</h5>
                </div>
                <div class="card-body">
                    <p><strong>Logged In:</strong> <?php echo $is_logged_in ? 'Yes' : 'No'; ?></p>
                    <p><strong>User ID:</strong> <?php echo $user_id ?? 'None'; ?></p>
                    <p><strong>Current Balance:</strong> <span id="currentBalance"><?php echo number_format($current_balance); ?></span></p>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5>API Tests</h5>
                </div>
                <div class="card-body">
                    <button id="testBalanceApi" class="btn btn-primary mb-2">Test Balance API</button>
                    <button id="testRecordGame" class="btn btn-warning mb-2">Test Game Recording</button>
                    <button id="testFullFlow" class="btn btn-success">Test Full Flow</button>
                    
                    <div id="results" class="mt-3"></div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5>Live Test Results</h5>
                </div>
                <div class="card-body">
                    <div id="liveResults"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const resultsDiv = document.getElementById('results');
    const liveDiv = document.getElementById('liveResults');
    
    function addResult(message, isError = false) {
        const className = isError ? 'alert-danger' : 'alert-success';
        resultsDiv.innerHTML += `<div class="alert ${className} alert-sm">${message}</div>`;
    }
    
    function addLive(message) {
        liveDiv.innerHTML += `<p class="mb-1">${new Date().toLocaleTimeString()}: ${message}</p>`;
        liveDiv.scrollTop = liveDiv.scrollHeight;
    }
    
    // Test 1: Balance API
    document.getElementById('testBalanceApi').addEventListener('click', async () => {
        addLive('🔍 Testing balance API...');
        
        try {
            const response = await fetch('<?php echo APP_URL; ?>/html/user/api/get-balance.php', {
                method: 'GET',
                credentials: 'include'
            });
            
            const data = await response.json();
            addLive(`✅ Balance API Response: ${JSON.stringify(data)}`);
            
            if (data.success) {
                document.getElementById('currentBalance').textContent = data.balance.toLocaleString();
                addResult(`Balance API works! Current balance: ${data.balance}`);
            } else {
                addResult(`Balance API error: ${data.message}`, true);
            }
            
        } catch (error) {
            addLive(`❌ Balance API Error: ${error.message}`);
            addResult(`Balance API failed: ${error.message}`, true);
        }
    });
    
    // Test 2: Game Recording API
    document.getElementById('testRecordGame').addEventListener('click', async () => {
        addLive('🎮 Testing game recording API...');
        
        try {
            const testData = {
                bet_amount: 1,
                win_amount: 0,
                business_id: 1,
                game_type: 'blackjack'
            };
            
            addLive(`📤 Sending: ${JSON.stringify(testData)}`);
            
            const response = await fetch('<?php echo APP_URL; ?>/html/api/casino/simple-record-play.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
                body: JSON.stringify(testData)
            });
            
            const responseText = await response.text();
            addLive(`📥 Raw response: ${responseText}`);
            
            try {
                const data = JSON.parse(responseText);
                addLive(`✅ Parsed response: ${JSON.stringify(data)}`);
                
                if (data.success) {
                    addResult(`Game recording works! New balance: ${data.new_balance}`);
                    document.getElementById('currentBalance').textContent = data.new_balance.toLocaleString();
                } else {
                    addResult(`Game recording error: ${data.error}`, true);
                }
            } catch (parseError) {
                addResult(`Response parse error: ${parseError.message}`, true);
            }
            
        } catch (error) {
            addLive(`❌ Game Recording Error: ${error.message}`);
            addResult(`Game recording failed: ${error.message}`, true);
        }
    });
    
    // Test 3: Full Flow
    document.getElementById('testFullFlow').addEventListener('click', async () => {
        addLive('🔄 Testing full blackjack flow...');
        
        // Step 1: Get initial balance
        try {
            const balanceResponse = await fetch('<?php echo APP_URL; ?>/html/user/api/get-balance.php', {
                method: 'GET',
                credentials: 'include'
            });
            const balanceData = await balanceResponse.json();
            const initialBalance = balanceData.balance;
            addLive(`💰 Initial balance: ${initialBalance}`);
            
            // Step 2: Record a losing game
            const loseData = {
                bet_amount: 5,
                win_amount: 0,
                business_id: 1,
                game_type: 'blackjack'
            };
            
            const loseResponse = await fetch('<?php echo APP_URL; ?>/html/api/casino/simple-record-play.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify(loseData)
            });
            
            const loseResult = await loseResponse.json();
            addLive(`📉 After losing 5 coins: ${loseResult.new_balance} (should be ${initialBalance - 5})`);
            
            // Step 3: Record a winning game
            const winData = {
                bet_amount: 3,
                win_amount: 6,
                business_id: 1,
                game_type: 'blackjack'
            };
            
            const winResponse = await fetch('<?php echo APP_URL; ?>/html/api/casino/simple-record-play.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify(winData)
            });
            
            const winResult = await winResponse.json();
            addLive(`📈 After winning 6 coins (bet 3): ${winResult.new_balance}`);
            
            document.getElementById('currentBalance').textContent = winResult.new_balance.toLocaleString();
            addResult('Full flow test completed!');
            
        } catch (error) {
            addLive(`❌ Full flow error: ${error.message}`);
            addResult(`Full flow failed: ${error.message}`, true);
        }
    });
    
    addLive('🎯 Debug page loaded. Click buttons to test APIs.');
});
</script>

</body>
</html> 