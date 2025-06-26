<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auto_login.php';

// Debug mode - always enabled for this debug version
$debug = true;

// Get wheel ID from URL parameter
$wheel_id = isset($_GET['wheel_id']) ? (int)$_GET['wheel_id'] : null;

if (!$wheel_id) {
    die("❌ ERROR: No wheel ID provided. Usage: spin-wheel-debug.php?wheel_id=1");
}

echo "<!DOCTYPE html>\n<html><head><meta charset='UTF-8'><title>Spin Wheel Debug</title></head><body style='font-family: monospace; background: #f0f0f0; padding: 20px;'>";
echo "<h1>🔍 SPIN WHEEL DEBUG ANALYSIS</h1>";

// Get spin wheel details
try {
    $stmt = $pdo->prepare("
        SELECT sw.*, b.name as business_name, b.logo_path as business_logo
        FROM spin_wheels sw
        JOIN businesses b ON sw.business_id = b.id
        WHERE sw.id = ?
    ");
    $stmt->execute([$wheel_id]);
    $wheel = $stmt->fetch();
    
    if (!$wheel) {
        echo "<div style='color: red; background: white; padding: 10px; border-radius: 5px;'>";
        echo "❌ WHEEL NOT FOUND: Wheel ID $wheel_id does not exist in database<br>";
        echo "Available wheels: ";
        
        $stmt = $pdo->query("SELECT id, name FROM spin_wheels ORDER BY id");
        $available = $stmt->fetchAll();
        foreach ($available as $w) {
            echo "<a href='?wheel_id={$w['id']}'>{$w['id']} ({$w['name']})</a> ";
        }
        echo "</div>";
        exit;
    }
    
    echo "<div style='color: green; background: white; padding: 10px; border-radius: 5px; margin: 10px 0;'>";
    echo "✅ WHEEL FOUND:<br>";
    echo "ID: {$wheel['id']}<br>";
    echo "Name: {$wheel['name']}<br>";
    echo "Business: {$wheel['business_name']}<br>";
    echo "Active: " . ($wheel['is_active'] ? 'YES' : 'NO') . "<br>";
    echo "</div>";
    
    if (!$wheel['is_active']) {
        echo "<div style='color: orange; background: white; padding: 10px; border-radius: 5px;'>";
        echo "⚠️ WARNING: This wheel is INACTIVE and won't show on the public page<br>";
        echo "</div>";
    }
    
    // Get rewards for this wheel
    $stmt = $pdo->prepare("
        SELECT * FROM rewards 
        WHERE spin_wheel_id = ? 
        ORDER BY rarity_level DESC
    ");
    $stmt->execute([$wheel_id]);
    $all_rewards = $stmt->fetchAll();
    
    $stmt = $pdo->prepare("
        SELECT * FROM rewards 
        WHERE spin_wheel_id = ? AND active = 1 
        ORDER BY rarity_level DESC
    ");
    $stmt->execute([$wheel_id]);
    $active_rewards = $stmt->fetchAll();
    
    echo "<div style='background: white; padding: 10px; border-radius: 5px; margin: 10px 0;'>";
    echo "<strong>🎁 REWARDS ANALYSIS:</strong><br>";
    echo "Total rewards: " . count($all_rewards) . "<br>";
    echo "Active rewards: " . count($active_rewards) . "<br>";
    
    if (count($active_rewards) == 0) {
        echo "<div style='color: red; background: #ffe6e6; padding: 10px; margin: 5px 0; border-radius: 3px;'>";
        echo "❌ CRITICAL ISSUE: No active rewards found!<br>";
        echo "This is why the spin wheel appears empty.<br>";
        if (count($all_rewards) > 0) {
            echo "There are inactive rewards. Activate them in the business dashboard.<br>";
        } else {
            echo "No rewards exist. Create rewards in the business dashboard.<br>";
        }
        echo "</div>";
    } else {
        echo "<div style='color: green; background: #e6ffe6; padding: 10px; margin: 5px 0; border-radius: 3px;'>";
        echo "✅ Active rewards found - wheel should display properly<br>";
        echo "</div>";
        
        echo "<strong>Active Rewards List:</strong><br>";
        foreach ($active_rewards as $reward) {
            echo "- {$reward['name']} (Rarity: {$reward['rarity_level']})<br>";
        }
    }
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div style='color: red; background: white; padding: 10px; border-radius: 5px;'>";
    echo "❌ DATABASE ERROR: " . $e->getMessage();
    echo "</div>";
    exit;
}

// Now create the actual spin wheel with enhanced debugging
echo "<hr><h2>🎡 ACTUAL SPIN WHEEL TEST</h2>";

// Handle spin result submission for debug version
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'spin') {
    header('Content-Type: application/json');
    
    try {
        if (empty($active_rewards)) {
            echo json_encode([
                'success' => false,
                'message' => 'No active rewards available for this wheel'
            ]);
            exit;
        }
        
        // Simple spin logic
        $totalWeight = 0;
        foreach ($active_rewards as $reward) {
            $totalWeight += (11 - $reward['rarity_level']);
        }
        
        $randomWeight = mt_rand(1, $totalWeight);
        $currentWeight = 0;
        $selectedReward = null;
        
        foreach ($active_rewards as $reward) {
            $currentWeight += (11 - $reward['rarity_level']);
            if ($randomWeight <= $currentWeight) {
                $selectedReward = $reward;
                break;
            }
        }
        
        if ($selectedReward) {
            // Record the spin result
            $stmt = $pdo->prepare("
                INSERT INTO spin_results (spin_wheel_id, reward_id, user_ip, created_at)
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([$wheel_id, $selectedReward['id'], $_SERVER['REMOTE_ADDR']]);
            
            echo json_encode([
                'success' => true,
                'reward' => $selectedReward,
                'debug' => [
                    'totalWeight' => $totalWeight,
                    'randomWeight' => $randomWeight,
                    'selectedWeight' => $currentWeight
                ]
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'No reward selected - algorithm error'
            ]);
        }
        exit;
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ]);
        exit;
    }
}

// Only show the wheel if we have active rewards
if (count($active_rewards) > 0):
?>

<div style="background: white; padding: 20px; border-radius: 10px; max-width: 600px; margin: 20px auto; text-align: center;">
    <h3><?php echo htmlspecialchars($wheel['name']); ?></h3>
    <p style="color: #666;"><?php echo htmlspecialchars($wheel['business_name']); ?></p>
    
    <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 15px 0; font-size: 12px; text-align: left;">
        <strong>🔍 DEBUG INFO:</strong><br>
        Wheel Active: <?php echo $wheel['is_active'] ? 'YES' : 'NO'; ?><br>
        Active Rewards: <?php echo count($active_rewards); ?><br>
        JavaScript Rewards Data: <pre><?php echo json_encode($active_rewards, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE); ?></pre>
    </div>
    
    <div id="errorMessage" style="background: #ffebee; color: #c62828; padding: 10px; border-radius: 5px; margin: 10px 0; display: none;"></div>
    
    <div style="position: relative; margin: 30px auto; width: 300px; height: 300px;">
        <canvas id="debugSpinWheel" width="300" height="300" style="border: 2px solid #ddd; border-radius: 50%;"></canvas>
    </div>
    
    <button id="spinButton" style="background: linear-gradient(45deg, #ff6b6b, #ee5a24); border: none; color: white; padding: 15px 30px; font-size: 16px; font-weight: bold; border-radius: 25px; cursor: pointer; margin: 10px;">
        🎯 SPIN TO WIN!
    </button>
    
    <div id="spinResult" style="margin: 20px 0; padding: 15px; background: #e8f5e8; border-radius: 8px; display: none;">
        <h4 id="resultTitle">🎉 Result</h4>
        <p id="resultText"></p>
        <div id="resultDebug" style="font-size: 11px; color: #666; margin-top: 10px;"></div>
    </div>
</div>

<script>
console.log('🚀 Starting Spin Wheel Debug Script');

// Error handling
function showError(message) {
    const errorDiv = document.getElementById('errorMessage');
    errorDiv.textContent = '❌ ERROR: ' + message;
    errorDiv.style.display = 'block';
    console.error('❌ Spin Wheel Error:', message);
}

function hideError() {
    document.getElementById('errorMessage').style.display = 'none';
}

try {
    // Load rewards data
    const rewards = <?php echo json_encode($active_rewards); ?>;
    const wheelId = <?php echo $wheel_id; ?>;
    
    console.log('📊 Loaded rewards:', rewards);
    console.log('🎡 Wheel ID:', wheelId);
    
    if (!rewards || rewards.length === 0) {
        throw new Error('No rewards data loaded');
    }
    
    // Get canvas and context
    const canvas = document.getElementById('debugSpinWheel');
    if (!canvas) throw new Error('Canvas not found');
    
    const ctx = canvas.getContext('2d');
    if (!ctx) throw new Error('Could not get canvas context');
    
    const spinButton = document.getElementById('spinButton');
    if (!spinButton) throw new Error('Spin button not found');
    
    // Wheel state
    let rotation = 0;
    let spinning = false;
    
    // Colors for segments
    const colors = ['#FF6B6B', '#4ECDC4', '#45B7D1', '#96CEB4', '#FFEAA7', '#DDA0DD', '#98D8C8', '#F7DC6F'];
    
    function drawWheel() {
        console.log('🎨 Drawing wheel with', rewards.length, 'rewards');
        
        const centerX = canvas.width / 2;
        const centerY = canvas.height / 2;
        const radius = 140;
        
        // Clear canvas
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        
        if (rewards.length === 0) {
            // Empty wheel
            ctx.fillStyle = '#f0f0f0';
            ctx.beginPath();
            ctx.arc(centerX, centerY, radius, 0, 2 * Math.PI);
            ctx.fill();
            
            ctx.fillStyle = '#666';
            ctx.font = '16px Arial';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText('No Rewards', centerX, centerY);
            return;
        }
        
        const anglePerSegment = (2 * Math.PI) / rewards.length;
        
        // Draw segments
        rewards.forEach((reward, index) => {
            const startAngle = rotation + index * anglePerSegment;
            const endAngle = startAngle + anglePerSegment;
            
            // Draw segment
            ctx.beginPath();
            ctx.moveTo(centerX, centerY);
            ctx.arc(centerX, centerY, radius, startAngle, endAngle);
            ctx.closePath();
            
            // Fill with color
            ctx.fillStyle = colors[index % colors.length];
            ctx.fill();
            
            // Border
            ctx.strokeStyle = '#fff';
            ctx.lineWidth = 3;
            ctx.stroke();
            
            // Text
            ctx.save();
            ctx.translate(centerX, centerY);
            ctx.rotate(startAngle + anglePerSegment / 2);
            ctx.fillStyle = '#fff';
            ctx.font = 'bold 12px Arial';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            
            const text = reward.name.length > 12 ? reward.name.substring(0, 12) + '...' : reward.name;
            ctx.fillText(text, radius * 0.7, 0);
            ctx.restore();
        });
        
        // Center circle
        ctx.beginPath();
        ctx.arc(centerX, centerY, 25, 0, 2 * Math.PI);
        ctx.fillStyle = '#fff';
        ctx.fill();
        ctx.strokeStyle = '#333';
        ctx.lineWidth = 3;
        ctx.stroke();
        
        // Pointer
        ctx.beginPath();
        ctx.moveTo(centerX, 10);
        ctx.lineTo(centerX - 15, 40);
        ctx.lineTo(centerX + 15, 40);
        ctx.closePath();
        ctx.fillStyle = '#333';
        ctx.fill();
        
        console.log('✅ Wheel drawn successfully');
    }
    
    async function spin() {
        if (spinning) return;
        
        console.log('🎯 Starting spin...');
        spinning = true;
        spinButton.disabled = true;
        spinButton.textContent = '🌀 SPINNING...';
        hideError();
        
        try {
            // Get result from server
            const response = await fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=spin'
            });
            
            const result = await response.json();
            console.log('🎊 Spin result:', result);
            
            if (result.success) {
                animateToWinner(result.reward, result.debug);
            } else {
                showError(result.message);
                resetSpinButton();
            }
        } catch (error) {
            console.error('❌ Spin error:', error);
            showError('Network error: ' + error.message);
            resetSpinButton();
        }
    }
    
    function animateToWinner(winningReward, debugInfo) {
        console.log('🎬 Animating to winner:', winningReward.name);
        
        const winningIndex = rewards.findIndex(r => r.id === winningReward.id);
        if (winningIndex === -1) {
            showError('Winner not found in rewards list');
            resetSpinButton();
            return;
        }
        
        const startTime = Date.now();
        const startRotation = rotation;
        const anglePerSegment = (2 * Math.PI) / rewards.length;
        const targetAngle = (8 * 2 * Math.PI) + (2 * Math.PI - (winningIndex * anglePerSegment + anglePerSegment / 2));
        const duration = 3000;
        
        function animate() {
            const elapsed = Date.now() - startTime;
            const progress = Math.min(elapsed / duration, 1);
            const easeOut = 1 - Math.pow(1 - progress, 3);
            
            rotation = startRotation + (targetAngle * easeOut);
            drawWheel();
            
            if (progress < 1) {
                requestAnimationFrame(animate);
            } else {
                showResult(winningReward, debugInfo);
                resetSpinButton();
            }
        }
        
        animate();
    }
    
    function showResult(reward, debugInfo) {
        const resultDiv = document.getElementById('spinResult');
        const titleEl = document.getElementById('resultTitle');
        const textEl = document.getElementById('resultText');
        const debugEl = document.getElementById('resultDebug');
        
        titleEl.textContent = '🎉 You Won: ' + reward.name;
        textEl.textContent = reward.description || 'Congratulations!';
        
        if (debugInfo) {
            debugEl.innerHTML = `
                <strong>Debug Info:</strong><br>
                Total Weight: ${debugInfo.totalWeight}<br>
                Random Weight: ${debugInfo.randomWeight}<br>
                Selected at Weight: ${debugInfo.selectedWeight}
            `;
        }
        
        resultDiv.style.display = 'block';
        console.log('🏆 Result shown:', reward.name);
    }
    
    function resetSpinButton() {
        spinning = false;
        spinButton.disabled = false;
        spinButton.textContent = '🎯 SPIN AGAIN!';
    }
    
    // Initialize
    drawWheel();
    spinButton.addEventListener('click', spin);
    
    console.log('✅ Debug spin wheel initialized successfully');
    
} catch (error) {
    showError('Initialization failed: ' + error.message);
    console.error('💥 Fatal error:', error);
}
</script>

<?php else: ?>
<div style="background: #ffebee; color: #c62828; padding: 20px; border-radius: 10px; text-align: center; margin: 20px;">
    <h3>❌ Cannot Display Spin Wheel</h3>
    <p><strong>Reason:</strong> No active rewards found for this wheel.</p>
    <p><strong>Solution:</strong> Add rewards to this wheel in the business dashboard.</p>
    <p><strong>Wheel ID:</strong> <?php echo $wheel_id; ?></p>
    <p><strong>Wheel Name:</strong> <?php echo htmlspecialchars($wheel['name']); ?></p>
</div>
<?php endif; ?>

</body></html> 