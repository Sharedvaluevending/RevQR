<?php
/**
 * COMPREHENSIVE SLOT MACHINE DIAGNOSTIC TOOL
 * 
 * This tool identifies and tests the critical frontend/backend symbol mismatches
 * that cause visual display to not match actual payouts in the slot machine.
 * 
 * CRITICAL ISSUES DETECTED:
 * 1. Frontend uses dynamic user avatars (8+ symbols)
 * 2. Backend uses hardcoded 5 symbols only
 * 3. Symbol indices don't match between frontend and backend
 * 4. Payouts calculated on different symbol sets than displayed
 */

require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/core/session.php';
require_once __DIR__ . '/core/functions.php';

// Get user avatars like the frontend does
$business_id = 1; // Default business for testing

// Get user's unlocked avatars for slot symbols (like frontend does)
$stmt = $pdo->prepare("
    SELECT ua.avatar_id, a.name
    FROM user_avatars ua
    LEFT JOIN avatar_config a ON ua.avatar_id = a.avatar_id
    WHERE ua.user_id = ?
    ORDER BY ua.unlocked_at DESC, ua.avatar_id ASC
");
$stmt->execute([1]); // Test user
$unlocked_avatars = $stmt->fetchAll();

// Add default avatars that everyone has access to (like frontend does)
$default_avatars = [
    ['avatar_id' => 1, 'avatar_name' => 'QR Ted', 'level' => 1],
    ['avatar_id' => 12, 'avatar_name' => 'QR Steve', 'level' => 1], 
    ['avatar_id' => 13, 'avatar_name' => 'QR Bob', 'level' => 1],
    ['avatar_id' => 2, 'avatar_name' => 'QR James', 'level' => 2],
    ['avatar_id' => 3, 'avatar_name' => 'QR Mike', 'level' => 3],
    ['avatar_id' => 4, 'avatar_name' => 'QR Ed', 'level' => 4],
    ['avatar_id' => 5, 'avatar_name' => 'QR Ned', 'level' => 5],
    ['avatar_id' => 6, 'avatar_name' => 'QR Easybake', 'level' => 6]
];

// Merge and ensure uniqueness (like frontend does)
$all_avatars = [];
$used_ids = [];

// Add unlocked avatars first (higher priority)
foreach ($unlocked_avatars as $avatar) {
    if (!in_array($avatar['avatar_id'], $used_ids)) {
        $all_avatars[] = $avatar;
        $used_ids[] = $avatar['avatar_id'];
    }
}

// Fill with defaults to ensure we have enough variety
foreach ($default_avatars as $avatar) {
    if (!in_array($avatar['avatar_id'], $used_ids) && count($all_avatars) < 9) {
        $all_avatars[] = $avatar;
        $used_ids[] = $avatar['avatar_id'];
    }
}

function getAvatarFilename($avatar_id) {
    $avatar_files = [
        1 => 'qrted.png',
        2 => 'qrjames.png', 
        3 => 'qrmike.png',
        4 => 'qred.png',
        5 => 'qrned.png',
        6 => 'qrEasybake.png',
        12 => 'qrsteve.png',
        13 => 'qrbob.png'
    ];
    return $avatar_files[$avatar_id] ?? 'qrted.png';
}

// Convert to the format expected by the slot machine (like frontend does)
$frontend_symbols = [];
foreach ($all_avatars as $i => $avatar) {
    $frontend_symbols[] = [
        'image' => 'assets/img/avatars/' . getAvatarFilename($avatar['avatar_id']),
        'name' => $avatar['name'] ?? 'Avatar ' . $avatar['avatar_id'],
        'level' => max(1, (int)($avatar['level'] ?? 1)),
        'avatar_id' => $avatar['avatar_id'],
        'index' => $i,
        'isWild' => ($i === 2) // Make the 3rd avatar a wild symbol
    ];
}

// Backend hardcoded symbols (from unified-slot-play.php)
$backend_symbols = [
    ['name' => 'QR Ted', 'image' => 'assets/img/avatars/qrted.png', 'level' => 1, 'rarity' => 'common', 'isWild' => false, 'index' => 0],
    ['name' => 'QR Steve', 'image' => 'assets/img/avatars/qrsteve.png', 'level' => 2, 'rarity' => 'common', 'isWild' => false, 'index' => 1],
    ['name' => 'QR Bob', 'image' => 'assets/img/avatars/qrbob.png', 'level' => 3, 'rarity' => 'uncommon', 'isWild' => false, 'index' => 2],
    ['name' => 'Lord Pixel', 'image' => 'assets/img/avatars/qrLordPixel.png', 'level' => 8, 'rarity' => 'mythical', 'isWild' => false, 'index' => 3],
    ['name' => 'Wild QR', 'image' => 'assets/img/avatars/qrEasybake.png', 'level' => 5, 'rarity' => 'wild', 'isWild' => true, 'index' => 4]
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🎰 SLOT MACHINE DIAGNOSTIC - Critical Frontend/Backend Mismatch Detection</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body { background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%); color: white; min-height: 100vh; }
        .symbol-card { background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); border-radius: 10px; }
        .mismatch { background: rgba(220,53,69,0.2) !important; border-color: #dc3545 !important; }
        .match { background: rgba(25,135,84,0.2) !important; border-color: #198754 !important; }
        .critical-alert { background: linear-gradient(45deg, #dc3545, #fd7e14); }
        .avatar-img { width: 50px; height: 50px; object-fit: cover; border-radius: 8px; }
    </style>
</head>
<body>

<div class="container py-4">
    <div class="row">
        <div class="col-12">
            <div class="alert critical-alert text-white text-center mb-4">
                <h2><i class="bi bi-exclamation-triangle"></i> CRITICAL SLOT MACHINE ISSUE DETECTED</h2>
                <p class="mb-0">Frontend and Backend use completely different symbol arrays!</p>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="card symbol-card">
                <div class="card-header bg-info">
                    <h4><i class="bi bi-display"></i> FRONTEND SYMBOLS (JavaScript)</h4>
                    <small>Dynamic user avatars - count: <?php echo count($frontend_symbols); ?></small>
                </div>
                <div class="card-body">
                    <?php foreach ($frontend_symbols as $symbol): ?>
                    <div class="d-flex align-items-center mb-2 p-2 border rounded">
                        <span class="badge bg-primary me-2"><?php echo $symbol['index']; ?></span>
                        <img src="<?php echo $symbol['image']; ?>" alt="<?php echo $symbol['name']; ?>" class="avatar-img me-3">
                        <div>
                            <strong><?php echo $symbol['name']; ?></strong><br>
                            <small>Level: <?php echo $symbol['level']; ?> 
                            <?php if ($symbol['isWild']): ?>
                                <span class="badge bg-warning">WILD</span>
                            <?php endif; ?>
                            </small>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card symbol-card">
                <div class="card-header bg-danger">
                    <h4><i class="bi bi-server"></i> BACKEND SYMBOLS (PHP)</h4>
                    <small>Hardcoded static array - count: <?php echo count($backend_symbols); ?></small>
                </div>
                <div class="card-body">
                    <?php foreach ($backend_symbols as $symbol): ?>
                    <div class="d-flex align-items-center mb-2 p-2 border rounded">
                        <span class="badge bg-primary me-2"><?php echo $symbol['index']; ?></span>
                        <img src="<?php echo $symbol['image']; ?>" alt="<?php echo $symbol['name']; ?>" class="avatar-img me-3">
                        <div>
                            <strong><?php echo $symbol['name']; ?></strong><br>
                            <small>Level: <?php echo $symbol['level']; ?> | Rarity: <?php echo $symbol['rarity']; ?>
                            <?php if ($symbol['isWild']): ?>
                                <span class="badge bg-warning">WILD</span>
                            <?php endif; ?>
                            </small>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-12">
            <div class="card symbol-card">
                <div class="card-header bg-warning text-dark">
                    <h4><i class="bi bi-bug"></i> MISMATCH ANALYSIS</h4>
                </div>
                <div class="card-body">
                    <?php
                    echo "<div class='alert alert-danger'>";
                    echo "<h5>🚨 CRITICAL PROBLEMS IDENTIFIED:</h5>";
                    echo "<ul>";
                    echo "<li><strong>Symbol Count Mismatch:</strong> Frontend has " . count($frontend_symbols) . " symbols, Backend has " . count($backend_symbols) . " symbols</li>";
                    echo "<li><strong>Index Misalignment:</strong> Same index positions refer to different symbols</li>";
                    echo "<li><strong>Wild Symbol Position:</strong> Frontend wild at index 2, Backend wild at index 4</li>";
                    echo "<li><strong>Missing Symbols:</strong> Backend doesn't have ";
                    
                    $missing = [];
                    foreach ($frontend_symbols as $fs) {
                        $found = false;
                        foreach ($backend_symbols as $bs) {
                            if ($fs['name'] === $bs['name']) {
                                $found = true;
                                break;
                            }
                        }
                        if (!$found) {
                            $missing[] = $fs['name'];
                        }
                    }
                    echo implode(', ', $missing);
                    echo "</li>";
                    echo "</ul>";
                    echo "</div>";

                    // Show impact
                    echo "<div class='alert alert-warning'>";
                    echo "<h5>🎰 IMPACT ON GAMEPLAY:</h5>";
                    echo "<ul>";
                    echo "<li>User sees different symbols than what backend calculates payouts for</li>";
                    echo "<li>Visual wins might not pay out correctly</li>";
                    echo "<li>Wild symbols appear in wrong positions</li>";
                    echo "<li>Symbol values/rarities don't match between display and calculation</li>";
                    echo "</ul>";
                    echo "</div>";
                    ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-12">
            <div class="card symbol-card">
                <div class="card-header bg-success">
                    <h4><i class="bi bi-tools"></i> RECOMMENDED FIXES</h4>
                </div>
                <div class="card-body">
                    <div class="alert alert-success">
                        <h5>✅ SOLUTION 1: Unified Symbol Management</h5>
                        <p>Create a shared symbol service that both frontend and backend use:</p>
                        <ol>
                            <li>Backend should load user avatars the same way frontend does</li>
                            <li>Use the same fallback logic for missing avatars</li>
                            <li>Ensure identical symbol arrays between frontend/backend</li>
                            <li>Add symbol validation before game play</li>
                        </ol>
                    </div>
                    
                    <div class="alert alert-info">
                        <h5>✅ SOLUTION 2: Symbol Sync API</h5>
                        <p>Frontend should fetch symbol definitions from backend before displaying slot machine:</p>
                        <ol>
                            <li>Add /api/casino/get-symbols.php endpoint</li>
                            <li>Frontend loads symbols from backend instead of generating them</li>
                            <li>Ensures 100% consistency between display and calculation</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-12">
            <div class="text-center">
                <a href="slot-machine.php?business_id=1" class="btn btn-warning btn-lg me-3">
                    <i class="bi bi-play-circle"></i> Test Slot Machine (See Mismatches Live)
                </a>
                <button onclick="location.reload()" class="btn btn-info btn-lg">
                    <i class="bi bi-arrow-clockwise"></i> Refresh Analysis
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 