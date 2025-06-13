<?php
require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/core/session.php';
require_once __DIR__ . '/core/auth.php';

// Debug current state
echo "<h2>QR Manager Authentication Debug</h2>";
echo "<p><strong>Session Status:</strong></p>";
echo "<ul>";
echo "<li>is_logged_in(): " . (is_logged_in() ? "YES" : "NO") . "</li>";
echo "<li>Session User ID: " . ($_SESSION['user_id'] ?? 'NONE') . "</li>";
echo "<li>Session Role: " . ($_SESSION['role'] ?? 'NONE') . "</li>";
echo "<li>has_role('business'): " . (has_role('business') ? "YES" : "NO") . "</li>";
echo "<li>has_role('user'): " . (has_role('user') ? "YES" : "NO") . "</li>";
echo "<li>has_role('admin'): " . (has_role('admin') ? "YES" : "NO") . "</li>";
echo "</ul>";

echo "<p><strong>Session Data:</strong></p>";
echo "<pre>" . htmlspecialchars(print_r($_SESSION, true)) . "</pre>";

echo "<p><strong>Available Users:</strong></p>";
try {
    $stmt = $pdo->prepare("SELECT id, username, role FROM users ORDER BY role, username");
    $stmt->execute();
    $users = $stmt->fetchAll();
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Username</th><th>Role</th><th>Action</th></tr>";
    foreach ($users as $user) {
        echo "<tr>";
        echo "<td>" . $user['id'] . "</td>";
        echo "<td>" . htmlspecialchars($user['username']) . "</td>";
        echo "<td>" . $user['role'] . "</td>";
        echo "<td><a href='?login_as=" . $user['id'] . "'>Login as this user</a></td>";
        echo "</tr>";
    }
    echo "</table>";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

// Handle login test
if (isset($_GET['login_as']) && is_numeric($_GET['login_as'])) {
    $user_id = (int)$_GET['login_as'];
    $stmt = $pdo->prepare("SELECT id, username, role, business_id FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if ($user) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['business_id'] = $user['business_id'];
        echo "<div style='background: green; color: white; padding: 10px; margin: 10px 0;'>";
        echo "Logged in as " . htmlspecialchars($user['username']) . " (" . $user['role'] . ")";
        echo "</div>";
        echo "<script>setTimeout(() => window.location.href = 'qr_manager.php', 2000);</script>";
    }
}

echo "<hr>";
echo "<p><strong>Test Links:</strong></p>";
echo "<ul>";
echo "<li><a href='qr_manager.php'>Go to QR Manager</a></li>";
echo "<li><a href='qr-display-public.php'>Go to Public QR Gallery</a></li>";
echo "<li><a href='logout.php'>Logout</a></li>";
echo "</ul>";

echo "<p><strong>QR Codes in Database:</strong></p>";
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM qr_codes WHERE status != 'deleted'");
    $stmt->execute();
    $count = $stmt->fetchColumn();
    echo "<p>Total QR Codes: " . $count . "</p>";
    
    if ($count > 0) {
        $stmt = $pdo->prepare("SELECT id, code, qr_type, machine_name, created_at FROM qr_codes WHERE status != 'deleted' ORDER BY created_at DESC LIMIT 5");
        $stmt->execute();
        $recent_qrs = $stmt->fetchAll();
        echo "<h4>Recent QR Codes:</h4>";
        echo "<table border='1'>";
        echo "<tr><th>Code</th><th>Type</th><th>Machine</th><th>Created</th></tr>";
        foreach ($recent_qrs as $qr) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($qr['code']) . "</td>";
            echo "<td>" . htmlspecialchars($qr['qr_type']) . "</td>";
            echo "<td>" . htmlspecialchars($qr['machine_name'] ?? 'N/A') . "</td>";
            echo "<td>" . date('M j, Y g:i A', strtotime($qr['created_at'])) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?> 