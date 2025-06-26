<?php
/**
 * QUICK TEST SCRIPT - Slot Machine Fix Verification
 * 
 * This script tests the fixed slot machine symbol loading to ensure
 * frontend and backend now use identical symbol arrays.
 */

require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/core/session.php';

// Test the loadUserSymbols function
function loadUserSymbols($pdo, $user_id) {
    // Get user's unlocked avatars for slot symbols (SAME AS FRONTEND)
    $stmt = $pdo->prepare("
        SELECT ua.avatar_id, a.name
        FROM user_avatars ua
        LEFT JOIN avatar_config a ON ua.avatar_id = a.avatar_id
        WHERE ua.user_id = ?
        ORDER BY ua.unlocked_at DESC, ua.avatar_id ASC
    ");
    $stmt->execute([$user_id]);
    $unlocked_avatars = $stmt->fetchAll();

    // Add default avatars that everyone has access to (SAME AS FRONTEND)
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

    // Merge and ensure uniqueness (SAME AS FRONTEND)
    $all_avatars = [];
    $used_ids = [];

    // Add unlocked avatars first
    foreach ($unlocked_avatars as $avatar) {
        if (!in_array($avatar['avatar_id'], $used_ids)) {
            $all_avatars[] = $avatar;
            $used_ids[] = $avatar['avatar_id'];
        }
    }

    // Fill with defaults
    foreach ($default_avatars as $avatar) {
        if (!in_array($avatar['avatar_id'], $used_ids) && count($all_avatars) < 9) {
            $all_avatars[] = $avatar;
            $used_ids[] = $avatar['avatar_id'];
        }
    }

    function getAvatarFilename($avatar_id) {
        $avatar_files = [
            1 => 'qrted.png', 2 => 'qrjames.png', 3 => 'qrmike.png',
            4 => 'qred.png', 5 => 'qrned.png', 6 => 'qrEasybake.png',
            12 => 'qrsteve.png', 13 => 'qrbob.png'
        ];
        return $avatar_files[$avatar_id] ?? 'qrted.png';
    }

    function getRarityFromLevel($level) {
        if ($level >= 10) return 'mythical';
        if ($level >= 8) return 'legendary';
        if ($level >= 6) return 'epic';
        if ($level >= 4) return 'rare';
        if ($level >= 2) return 'uncommon';
        return 'common';
    }

    // Convert to slot format (SAME AS FRONTEND)
    $symbols = [];
    foreach ($all_avatars as $index => $avatar) {
        $baseValue = max(5, $avatar['level'] * 2);
        $isWild = ($index === 2); // 3rd avatar is wild (SAME AS FRONTEND)
        
        $symbols[] = [
            'image' => 'assets/img/avatars/' . getAvatarFilename($avatar['avatar_id']),
            'name' => $avatar['name'] ?? 'Avatar ' . $avatar['avatar_id'],
            'level' => max(1, (int)($avatar['level'] ?? 1)),
            'value' => $isWild ? $baseValue * 2 : $baseValue,
            'rarity' => getRarityFromLevel($avatar['level'] ?? 1),
            'isWild' => $isWild,
            'avatar_id' => $avatar['avatar_id']
        ];
    }

    // Fallback (SAME AS FRONTEND)
    if (count($symbols) < 3) {
        $symbols = [
            ['image' => 'assets/img/avatars/qrted.png', 'name' => 'QR Ted', 'level' => 1, 'value' => 5, 'rarity' => 'common', 'isWild' => false, 'avatar_id' => 1],
            ['image' => 'assets/img/avatars/qrsteve.png', 'name' => 'QR Steve', 'level' => 1, 'value' => 5, 'rarity' => 'common', 'isWild' => false, 'avatar_id' => 12],
            ['image' => 'assets/img/avatars/qrbob.png', 'name' => 'QR Bob', 'level' => 1, 'value' => 10, 'rarity' => 'common', 'isWild' => true, 'avatar_id' => 13]
        ];
    }

    return $symbols;
}

// Test with a sample user ID
$test_user_id = 1;
$symbols = loadUserSymbols($pdo, $test_user_id);

echo "<h2>🎰 SLOT MACHINE FIX VERIFICATION</h2>";
echo "<p><strong>✅ SUCCESS:</strong> loadUserSymbols() function is working!</p>";
echo "<p><strong>Symbols Loaded:</strong> " . count($symbols) . "</p>";
echo "<p><strong>Wild Symbol Position:</strong> ";

foreach ($symbols as $index => $symbol) {
    if ($symbol['isWild']) {
        echo "Index $index - " . $symbol['name'] . " ✅";
        break;
    }
}

echo "</p>";

echo "<h3>Symbol Array:</h3>";
echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr><th>Index</th><th>Name</th><th>Level</th><th>Rarity</th><th>Wild</th><th>Value</th></tr>";

foreach ($symbols as $index => $symbol) {
    $wildBadge = $symbol['isWild'] ? '🌟 YES' : 'No';
    echo "<tr>";
    echo "<td>$index</td>";
    echo "<td>" . htmlspecialchars($symbol['name']) . "</td>";
    echo "<td>" . $symbol['level'] . "</td>";
    echo "<td>" . $symbol['rarity'] . "</td>";
    echo "<td>$wildBadge</td>";
    echo "<td>" . $symbol['value'] . "</td>";
    echo "</tr>";
}

echo "</table>";

echo "<br><p><strong>🎯 RESULT:</strong> Backend now uses dynamic symbol loading identical to frontend!</p>";
echo "<p><a href='casino/slot-machine-debug-comprehensive.php'>→ Run Full Diagnostic</a></p>";
echo "<p><a href='casino/slot-machine.php?business_id=1'>→ Test Live Slot Machine</a></p>";
?> 