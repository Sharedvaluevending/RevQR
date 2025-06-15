<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auto_login.php';

// Get wheel ID from URL parameter
$wheel_id = isset($_GET['wheel_id']) ? (int)$_GET['wheel_id'] : null;

if (!$wheel_id) {
    header('HTTP/1.0 404 Not Found');
    echo "Spin wheel not found.";
    exit;
}

// Get spin wheel details
try {
    $stmt = $pdo->prepare("
        SELECT sw.*, b.name as business_name, b.logo_path as business_logo
        FROM spin_wheels sw
        JOIN businesses b ON sw.business_id = b.id
        WHERE sw.id = ? AND sw.is_active = 1
    ");
    $stmt->execute([$wheel_id]);
    $wheel = $stmt->fetch();
    
    if (!$wheel) {
        header('HTTP/1.0 404 Not Found');
        echo "Spin wheel not found or inactive.";
        exit;
    }
    
    // Get rewards for this wheel
    $stmt = $pdo->prepare("
        SELECT * FROM rewards 
        WHERE spin_wheel_id = ? AND active = 1 
        ORDER BY rarity_level DESC
    ");
    $stmt->execute([$wheel_id]);
    $rewards = $stmt->fetchAll();
    
} catch (Exception $e) {
    error_log("Error loading spin wheel: " . $e->getMessage());
    header('HTTP/1.0 500 Internal Server Error');
    echo "Error loading spin wheel.";
    exit;
}

// Handle spin result submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'spin') {
    try {
        // Simple spin logic - select random reward based on rarity
        $totalWeight = 0;
        foreach ($rewards as $reward) {
            $totalWeight += (11 - $reward['rarity_level']); // Higher rarity = lower weight
        }
        
        $randomWeight = mt_rand(1, $totalWeight);
        $currentWeight = 0;
        $selectedReward = null;
        
        foreach ($rewards as $reward) {
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
                'reward' => $selectedReward
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'No reward selected'
            ]);
        }
        exit;
        
    } catch (Exception $e) {
        error_log("Error processing spin: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Error processing spin'
        ]);
        exit;
    }
}

require_once __DIR__ . '/../core/includes/header.php';
?>

<!-- Login Prompt for Non-Logged In Users -->
<?php if (!is_logged_in()): ?>
    <div class="alert alert-info alert-dismissible fade show m-3" role="alert">
        <div class="d-flex align-items-center">
            <i class="bi bi-info-circle me-2"></i>
            <div class="flex-grow-1">
                <strong>New to RevenueQR?</strong> 
                <a href="<?php echo APP_URL; ?>/html/register.php" class="alert-link">Register now</a> 
                or <a href="<?php echo APP_URL; ?>/html/login.php" class="alert-link">login</a> 
                to track your spins, earn QR coins, and access exclusive features!
            </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="spin-wheel-page-wrapper">
<!DOCTYPE html>
<html lang="en" class="d-none">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($wheel['name']); ?> - Spin to Win!</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .spin-container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .business-header {
            text-align: center;
            color: white;
            margin-bottom: 30px;
        }
        
        .business-logo {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid white;
            margin-bottom: 15px;
        }
        
        .wheel-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            text-align: center;
        }
        
        .spin-wheel-container {
            position: relative;
            margin: 30px auto;
            width: 300px;
            height: 300px;
        }
        
        #publicSpinWheel {
            border-radius: 50%;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            border: 5px solid #fff;
        }
        
        .spin-button {
            background: linear-gradient(45deg, #ff6b6b, #ee5a24);
            border: none;
            color: white;
            padding: 15px 30px;
            font-size: 18px;
            font-weight: bold;
            border-radius: 50px;
            box-shadow: 0 8px 20px rgba(238, 90, 36, 0.3);
            transition: all 0.3s ease;
            margin-top: 20px;
        }
        
        .spin-button:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 12px 25px rgba(238, 90, 36, 0.4);
        }
        
        .spin-button:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .reward-modal .modal-content {
            border-radius: 20px;
            border: none;
        }
        
        .reward-icon {
            font-size: 4rem;
            color: #ffd700;
            margin-bottom: 20px;
        }
        
        .prize-list {
            margin-top: 30px;
        }
        
        .prize-item {
            background: rgba(255, 255, 255, 0.8);
            border-radius: 10px;
            padding: 10px 15px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .rarity-badge {
            font-size: 0.8rem;
            padding: 4px 8px;
            border-radius: 12px;
        }
        
        .rarity-1, .rarity-2, .rarity-3 { background: #28a745; color: white; }
        .rarity-4, .rarity-5, .rarity-6 { background: #ffc107; color: black; }
        .rarity-7, .rarity-8 { background: #fd7e14; color: white; }
        .rarity-9, .rarity-10 { background: #dc3545; color: white; }
        
        @media (max-width: 768px) {
            .spin-wheel-container {
                width: 250px;
                height: 250px;
            }
            
            #publicSpinWheel {
                width: 250px !important;
                height: 250px !important;
            }
            
            .wheel-card {
                padding: 20px;
                margin: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="spin-container">
            <!-- Business Header -->
            <div class="business-header">
                <?php if ($wheel['business_logo']): ?>
                    <img src="<?php echo htmlspecialchars($wheel['business_logo']); ?>" alt="Business Logo" class="business-logo">
                <?php endif; ?>
                <h2><?php echo htmlspecialchars($wheel['business_name']); ?></h2>
                <p class="mb-0">Presents</p>
            </div>
            
            <!-- Spin Wheel Card -->
            <div class="wheel-card">
                <h1 class="mb-3"><?php echo htmlspecialchars($wheel['name']); ?></h1>
                <?php if ($wheel['description']): ?>
                    <p class="text-muted mb-4"><?php echo htmlspecialchars($wheel['description']); ?></p>
                <?php endif; ?>
                
                <!-- Spin Wheel -->
                <div class="spin-wheel-container">
                    <canvas id="publicSpinWheel" width="300" height="300"></canvas>
                </div>
                
                <!-- Spin Button -->
                <button id="spinButton" class="btn spin-button">
                    <i class="bi bi-arrow-clockwise me-2"></i>Spin to Win!
                </button>
                
                <!-- Prize List -->
                <?php if (!empty($rewards)): ?>
                <div class="prize-list">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0">Available Prizes:</h5>
                        <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#oddsInfo">
                            <i class="bi bi-percent me-1"></i>View Odds
                        </button>
                    </div>
                    
                    <?php 
                    // Calculate total weight for odds display
                    $totalWeight = 0;
                    foreach ($rewards as $reward) {
                        $totalWeight += (11 - $reward['rarity_level']);
                    }
                    ?>
                    
                    <div class="collapse mb-3" id="oddsInfo">
                        <div class="alert alert-info">
                            <h6><i class="bi bi-calculator me-2"></i>Win Probabilities</h6>
                            <small>Chances are calculated based on rarity levels. Higher rarity = lower chance to win.</small>
                        </div>
                    </div>
                    
                    <?php foreach ($rewards as $reward): ?>
                        <?php 
                        $weight = 11 - $reward['rarity_level'];
                        $percentage = round(($weight / $totalWeight) * 100, 1);
                        ?>
                        <div class="prize-item">
                            <div>
                                <span><strong><?php echo htmlspecialchars($reward['name']); ?></strong></span>
                                <?php if ($reward['description']): ?>
                                    <br><small class="text-muted"><?php echo htmlspecialchars($reward['description']); ?></small>
                                <?php endif; ?>
                                <div class="collapse" data-bs-parent="#oddsInfo">
                                    <small class="text-primary">
                                        <i class="bi bi-percent me-1"></i><?php echo $percentage; ?>% chance to win
                                    </small>
                                </div>
                            </div>
                            <span class="rarity-badge rarity-<?php echo $reward['rarity_level']; ?>">
                                Rarity <?php echo $reward['rarity_level']; ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Reward Modal -->
    <div class="modal fade" id="rewardModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content text-center">
                <div class="modal-body p-5">
                    <div class="reward-icon">
                        <i class="bi bi-trophy-fill"></i>
                    </div>
                    <h3 class="mb-3">Congratulations!</h3>
                    <h4 id="rewardName" class="text-primary mb-3"></h4>
                    <p id="rewardDescription" class="text-muted mb-4"></p>
                    <div id="rewardCode" class="alert alert-success" style="display: none;">
                        <strong>Your Code: </strong><span id="codeValue"></span>
                    </div>
                    <div id="rewardLink" style="display: none;">
                        <a href="#" id="linkValue" class="btn btn-primary" target="_blank">Claim Reward</a>
                    </div>
                    <button type="button" class="btn btn-secondary mt-3" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Public Spin Wheel Logic
        const rewards = <?php echo json_encode($rewards); ?>;
        const wheelId = <?php echo $wheel_id; ?>;
        
        // Enhanced color palette for high-end look
        const colors = [
            '#FF6B6B', '#4ECDC4', '#45B7D1', '#96CEB4', '#FFEAA7',
            '#DDA0DD', '#98D8C8', '#F7DC6F', '#BB8FCE', '#85C1E9'
        ];
        
        const canvas = document.getElementById('publicSpinWheel');
        const ctx = canvas.getContext('2d');
        const spinButton = document.getElementById('spinButton');
        
        let rotation = 0;
        let spinning = false;
        
        // Mobile responsive canvas sizing
        function setupCanvasSize() {
            const container = document.querySelector('.spin-wheel-container');
            const containerWidth = container.offsetWidth;
            let canvasSize;
            
            if (window.innerWidth <= 576) {
                canvasSize = Math.min(250, containerWidth - 20);
            } else {
                canvasSize = Math.min(300, containerWidth - 20);
            }
            
            canvas.width = canvasSize;
            canvas.height = canvasSize;
            canvas.style.width = canvasSize + 'px';
            canvas.style.height = canvasSize + 'px';
        }
        
        function drawWheel() {
            const centerX = canvas.width / 2;
            const centerY = canvas.height / 2;
            const radius = Math.min(centerX, centerY) - 10;
            
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            
            if (rewards.length === 0) {
                ctx.fillStyle = '#f0f0f0';
                ctx.beginPath();
                ctx.arc(centerX, centerY, radius, 0, 2 * Math.PI);
                ctx.fill();
                
                ctx.fillStyle = '#666';
                ctx.font = '16px Arial';
                ctx.textAlign = 'center';
                ctx.fillText('No prizes available', centerX, centerY);
                return;
            }
            
            const anglePerSegment = (2 * Math.PI) / rewards.length;
            
            rewards.forEach((reward, index) => {
                const startAngle = rotation + index * anglePerSegment;
                const endAngle = startAngle + anglePerSegment;
                
                // Draw segment
                ctx.beginPath();
                ctx.moveTo(centerX, centerY);
                ctx.arc(centerX, centerY, radius, startAngle, endAngle);
                ctx.closePath();
                
                // Use color based on index
                ctx.fillStyle = colors[index % colors.length];
                ctx.fill();
                
                // Draw border
                ctx.strokeStyle = '#fff';
                ctx.lineWidth = 2;
                ctx.stroke();
                
                // Draw text
                ctx.save();
                ctx.translate(centerX, centerY);
                ctx.rotate(startAngle + anglePerSegment / 2);
                ctx.fillStyle = '#fff';
                ctx.font = 'bold 12px Arial';
                ctx.textAlign = 'center';
                
                const text = reward.name.length > 15 ? reward.name.substring(0, 15) + '...' : reward.name;
                ctx.fillText(text, radius * 0.7, 5);
                ctx.restore();
            });
            
            // Draw center circle
            ctx.beginPath();
            ctx.arc(centerX, centerY, 20, 0, 2 * Math.PI);
            ctx.fillStyle = '#fff';
            ctx.fill();
            ctx.strokeStyle = '#333';
            ctx.lineWidth = 3;
            ctx.stroke();
            
            // Draw pointer
            ctx.beginPath();
            ctx.moveTo(centerX, 10);
            ctx.lineTo(centerX - 15, 35);
            ctx.lineTo(centerX + 15, 35);
            ctx.closePath();
            ctx.fillStyle = '#333';
            ctx.fill();
        }
        
        function spin() {
            if (spinning || rewards.length === 0) return;
            
            spinning = true;
            spinButton.disabled = true;
            spinButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Spinning...';
            
            // CRITICAL FIX: Get winner from backend FIRST, then animate to it
            getSpinResult().then(result => {
                if (result.success) {
                    animateToWinner(result.reward);
                } else {
                    alert('Error: ' + result.message);
                    resetSpinButton();
                }
            }).catch(error => {
                console.error('Error getting spin result:', error);
                alert('Error processing spin');
                resetSpinButton();
            });
        }
        
        async function getSpinResult() {
            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=spin'
                });
                return await response.json();
            } catch (error) {
                throw error;
            }
        }
        
        function animateToWinner(winningReward) {
            // Find the index of the winning reward
            const winningIndex = rewards.findIndex(r => r.id === winningReward.id);
            if (winningIndex === -1) {
                console.error('Winning reward not found in rewards array');
                showRewardModal(winningReward);
                resetSpinButton();
                return;
            }
            
            const spinDuration = 4000;
            const startTime = Date.now();
            const startRotation = rotation;
            
            // Calculate target angle to land on winning segment
            const anglePerSegment = (2 * Math.PI) / rewards.length;
            const baseSpins = 8 + Math.random() * 4; // 8-12 full rotations for excitement
            const targetSegmentAngle = winningIndex * anglePerSegment;
            const segmentCenter = targetSegmentAngle + (anglePerSegment / 2);
            
            // Calculate final rotation to land on winner (accounting for pointer at top)
            const finalRotation = (baseSpins * 2 * Math.PI) + (2 * Math.PI - segmentCenter);
            
            function animate() {
                const elapsed = Date.now() - startTime;
                const progress = Math.min(elapsed / spinDuration, 1);
                
                // Easing function for realistic deceleration
                const easeOut = 1 - Math.pow(1 - progress, 3);
                rotation = startRotation + (finalRotation * easeOut);
                
                drawWheel();
                
                if (progress < 1) {
                    requestAnimationFrame(animate);
                } else {
                    // Animation complete - show the predetermined winner
                    showRewardModal(winningReward);
                    resetSpinButton();
                }
            }
            
            animate();
        }
        
        function resetSpinButton() {
            spinning = false;
            spinButton.disabled = false;
            spinButton.innerHTML = '<i class="bi bi-arrow-clockwise me-2"></i>Spin Again!';
        }
        
        // This function is no longer needed as we get the result before animation
        // Keeping for backward compatibility but it's not used in the new flow
        
        function showRewardModal(reward) {
            document.getElementById('rewardName').textContent = reward.name;
            document.getElementById('rewardDescription').textContent = reward.description || '';
            
            // Show code if available
            const codeDiv = document.getElementById('rewardCode');
            if (reward.code) {
                document.getElementById('codeValue').textContent = reward.code;
                codeDiv.style.display = 'block';
            } else {
                codeDiv.style.display = 'none';
            }
            
            // Show link if available
            const linkDiv = document.getElementById('rewardLink');
            if (reward.link) {
                document.getElementById('linkValue').href = reward.link;
                linkDiv.style.display = 'block';
            } else {
                linkDiv.style.display = 'none';
            }
            
            const modal = new bootstrap.Modal(document.getElementById('rewardModal'));
            modal.show();
        }
        
        // Initialize
        setupCanvasSize();
        drawWheel();
        
        // Event listeners
        spinButton.addEventListener('click', spin);
        window.addEventListener('resize', () => {
            setupCanvasSize();
            drawWheel();
        });
    </script>
</body>
</html> 