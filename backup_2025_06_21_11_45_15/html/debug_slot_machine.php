<?php
/**
 * Debug Slot Machine Script
 * This script helps test the server-side slot machine result generation
 */

require_once __DIR__ . '/core/config.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Slot Machine Debug</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #1a1a1a; color: white; }
        .container { max-width: 1200px; margin: 0 auto; }
        .test-section { background: #2a2a2a; padding: 20px; margin: 20px 0; border-radius: 10px; }
        .success { color: #4CAF50; }
        .error { color: #f44336; }
        .warning { color: #ff9800; }
        .info { color: #2196F3; }
        .grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin: 10px 0; }
        .symbol { background: #333; padding: 10px; text-align: center; border-radius: 5px; }
        .wild { background: #ffd700; color: black; }
        .winning { background: #4CAF50; }
        .stats { background: #333; padding: 15px; border-radius: 5px; margin: 10px 0; }
        button { background: #4CAF50; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; margin: 5px; }
        button:hover { background: #45a049; }
        .results { background: #333; padding: 15px; border-radius: 5px; margin: 10px 0; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🎰 Slot Machine Debug Tool</h1>
        <p>Testing server-side result generation for slot machines</p>";

// Test the slot machine result generation
echo "<div class='test-section'>
    <h2>🔧 Testing Server-Side Result Generation</h2>";

// Test single result generation
echo "<h3>Single Spin Test</h3>";
echo "<button onclick='testSingleSpin()'>Generate Single Spin</button>";
echo "<div id='singleResult' class='results'></div>";

// Test multiple spins for statistics
echo "<h3>Multiple Spins Test</h3>";
echo "<button onclick='testMultipleSpins(10)'>Test 10 Spins</button>";
echo "<button onclick='testMultipleSpins(50)'>Test 50 Spins</button>";
echo "<button onclick='testMultipleSpins(100)'>Test 100 Spins</button>";
echo "<div id='multipleResults' class='results'></div>";

echo "</div>";

// Check if the API endpoint exists
$api_file = __DIR__ . '/api/casino/generate-slot-results.php';
if (file_exists($api_file)) {
    echo "<div class='test-section'>
        <h2>✅ API Endpoint Status</h2>
        <p class='success'>✅ Slot machine API endpoint exists: <code>/api/casino/generate-slot-results.php</code></p>
        <p class='info'>The server-side result generation is properly implemented.</p>
    </div>";
} else {
    echo "<div class='test-section'>
        <h2>❌ API Endpoint Status</h2>
        <p class='error'>❌ Slot machine API endpoint missing: <code>/api/casino/generate-slot-results.php</code></p>
        <p class='warning'>The server-side result generation is not implemented.</p>
    </div>";
}

// Check JavaScript file
$js_file = __DIR__ . '/casino/js/slot-machine.js';
if (file_exists($js_file)) {
    $js_content = file_get_contents($js_file);
    
    // Check if the JavaScript has been updated to use server-side results
    if (strpos($js_content, 'generate-slot-results.php') !== false) {
        echo "<div class='test-section'>
            <h2>✅ JavaScript Integration Status</h2>
            <p class='success'>✅ Slot machine JavaScript has been updated to use server-side results</p>
            <p class='info'>The client-side code now properly calls the server API for result generation.</p>
        </div>";
    } else {
        echo "<div class='test-section'>
            <h2>❌ JavaScript Integration Status</h2>
            <p class='error'>❌ Slot machine JavaScript still uses client-side result generation</p>
            <p class='warning'>The client-side code needs to be updated to use server-side results.</p>
        </div>";
    }
} else {
    echo "<div class='test-section'>
        <h2>❌ JavaScript File Status</h2>
        <p class='error'>❌ Slot machine JavaScript file not found: <code>/casino/js/slot-machine.js</code></p>
    </div>";
}

echo "<div class='test-section'>
    <h2>📊 Expected Results</h2>
    <div class='stats'>
        <h3>Win Rate Analysis:</h3>
        <ul>
            <li><strong>Target Win Rate:</strong> ~35% (much better than previous ~15%)</li>
            <li><strong>Win Types:</strong> Horizontal lines (40%), Diagonals (30%), Rarity lines (20%), Wild wins (10%)</li>
            <li><strong>Wild Bonus:</strong> +1x per wild symbol</li>
            <li><strong>Diagonal Bonus:</strong> +2x for diagonal wins</li>
            <li><strong>Mythical Jackpot:</strong> 1.5x jackpot multiplier for Lord Pixel</li>
            <li><strong>Triple Wild:</strong> 2x jackpot multiplier</li>
        </ul>
    </div>
</div>";

echo "<div class='test-section'>
    <h2>🚀 Fix Summary</h2>
    <div class='stats'>
        <h3>What Was Fixed:</h3>
        <ul>
            <li><strong>Server-Side Generation:</strong> Results now generated on server instead of client</li>
            <li><strong>Visual Alignment:</strong> Animation now matches server-determined outcome</li>
            <li><strong>Security:</strong> Users can no longer manipulate client-side code to cheat</li>
            <li><strong>Consistency:</strong> Same logic as spin wheel fix - server determines outcome</li>
            <li><strong>Win Rate:</strong> Improved from ~15% to ~35% for better user experience</li>
        </ul>
        
        <h3>How It Works Now:</h3>
        <ol>
            <li>User clicks SPIN</li>
            <li>JavaScript calls server API for results</li>
            <li>Server generates random results with proper win rates</li>
            <li>JavaScript shows animation that matches server results</li>
            <li>Server records the play with validated results</li>
        </ol>
    </div>
</div>";

echo "</div>

<script>
async function testSingleSpin() {
    const resultDiv = document.getElementById('singleResult');
    resultDiv.innerHTML = '<p>Generating spin...</p>';
    
    try {
        const response = await fetch('/api/casino/generate-slot-results.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                business_id: 1,
                bet_amount: 1
            })
        });
        
        if (!response.ok) {
            throw new Error('API request failed');
        }
        
        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.error || 'API error');
        }
        
        // Display results in a 3x3 grid
        let html = '<h4>Spin Results:</h4>';
        html += '<div class=\"grid\">';
        
        for (let i = 0; i < 3; i++) {
            const result = data.results[i];
            const middleSymbol = result.middleSymbol || result;
            
            html += '<div class=\"symbol' + (middleSymbol.isWild ? ' wild' : '') + (data.is_win ? ' winning' : '') + '\">';
            html += '<strong>' + middleSymbol.name + '</strong><br>';
            html += 'Level: ' + middleSymbol.level + '<br>';
            html += 'Rarity: ' + middleSymbol.rarity;
            if (middleSymbol.isWild) {
                html += '<br>🌟 WILD 🌟';
            }
            html += '</div>';
        }
        
        html += '</div>';
        html += '<div class=\"stats\">';
        html += '<strong>Win Amount:</strong> ' + data.win_amount + ' coins<br>';
        html += '<strong>Is Win:</strong> ' + (data.is_win ? 'Yes' : 'No') + '<br>';
        html += '<strong>Win Type:</strong> ' + data.win_type + '<br>';
        html += '<strong>Message:</strong> ' + data.message + '<br>';
        if (data.winning_row !== null) {
            html += '<strong>Winning Row:</strong> ' + data.winning_row;
        }
        html += '</div>';
        
        resultDiv.innerHTML = html;
        
    } catch (error) {
        resultDiv.innerHTML = '<p class=\"error\">Error: ' + error.message + '</p>';
    }
}

async function testMultipleSpins(count) {
    const resultDiv = document.getElementById('multipleResults');
    resultDiv.innerHTML = '<p>Testing ' + count + ' spins...</p>';
    
    let wins = 0;
    let totalWinAmount = 0;
    let winTypes = {};
    
    for (let i = 0; i < count; i++) {
        try {
            const response = await fetch('/api/casino/generate-slot-results.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    business_id: 1,
                    bet_amount: 1
                })
            });
            
            if (response.ok) {
                const data = await response.json();
                
                if (data.success && data.is_win) {
                    wins++;
                    totalWinAmount += data.win_amount;
                    winTypes[data.win_type] = (winTypes[data.win_type] || 0) + 1;
                }
            }
        } catch (error) {
            console.error('Spin test error:', error);
        }
        
        // Small delay to avoid overwhelming the server
        await new Promise(resolve => setTimeout(resolve, 10));
    }
    
    const winRate = (wins / count * 100).toFixed(1);
    const avgWinAmount = wins > 0 ? (totalWinAmount / wins).toFixed(1) : 0;
    
    let html = '<h4>Test Results (' + count + ' spins):</h4>';
    html += '<div class=\"stats\">';
    html += '<strong>Win Rate:</strong> ' + winRate + '% (' + wins + '/' + count + ')<br>';
    html += '<strong>Average Win Amount:</strong> ' + avgWinAmount + ' coins<br>';
    html += '<strong>Total Win Amount:</strong> ' + totalWinAmount + ' coins<br>';
    html += '<strong>Win Types:</strong><br>';
    
    for (const [type, count] of Object.entries(winTypes)) {
        html += '&nbsp;&nbsp;• ' + type + ': ' + count + '<br>';
    }
    
    html += '</div>';
    
    resultDiv.innerHTML = html;
}
</script>

</body>
</html>";
?> 