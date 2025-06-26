<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/functions.php';

// Debug information
echo "<h2>Debug Information for Avatars Page</h2>";
echo "<p><strong>Current Time:</strong> " . date('Y-m-d H:i:s') . "</p>";
echo "<p><strong>Session Status:</strong> " . (session_status() === PHP_SESSION_ACTIVE ? "Active" : "Inactive") . "</p>";
echo "<p><strong>Session ID:</strong> " . session_id() . "</p>";
echo "<p><strong>Session Name:</strong> " . session_name() . "</p>";
echo "<p><strong>APP_URL:</strong> " . APP_URL . "</p>";

echo "<h3>Session Data:</h3>";
echo "<pre>" . print_r($_SESSION, true) . "</pre>";

echo "<p><strong>User ID in Session:</strong> " . ($_SESSION['user_id'] ?? 'Not set') . "</p>";
echo "<p><strong>Is Logged In:</strong> " . (is_logged_in() ? "Yes" : "No") . "</p>";

if (is_logged_in()) {
    echo "<p><strong>User ID:</strong> " . $_SESSION['user_id'] . "</p>";
    echo "<p><strong>Role:</strong> " . ($_SESSION['role'] ?? 'Not set') . "</p>";
    
    // Get user data
    $stmt = $pdo->prepare("SELECT username, role, equipped_avatar FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user_data = $stmt->fetch();
    
    if ($user_data) {
        echo "<p><strong>Username:</strong> " . $user_data['username'] . "</p>";
        echo "<p><strong>Database Role:</strong> " . $user_data['role'] . "</p>";
        echo "<p><strong>Equipped Avatar in DB:</strong> " . $user_data['equipped_avatar'] . "</p>";
        
        // Get equipped avatar using function
        $equipped_avatar_id = getUserEquippedAvatar();
        echo "<p><strong>Equipped Avatar from Function:</strong> " . $equipped_avatar_id . "</p>";
        
        // Show avatar filename
        $avatar_filename = getAvatarFilename($equipped_avatar_id);
        echo "<p><strong>Avatar Filename:</strong> " . $avatar_filename . "</p>";
        
        echo "<h3>Test Avatar Display</h3>";
        echo "<img src='../assets/img/avatars/" . $avatar_filename . "' alt='Avatar' style='max-width: 100px; border: 1px solid #ccc;'>";
        
        echo "<h3>All Available Avatars (first few):</h3>";
        $available_avatars = [
            ['id' => 1, 'name' => 'QR Ted', 'filename' => 'qrted.png'],
            ['id' => 12, 'name' => 'QR Steve', 'filename' => 'qrsteve.png'], 
            ['id' => 13, 'name' => 'QR Bob', 'filename' => 'qrbob.png']
        ];
        
        foreach ($available_avatars as $avatar) {
            $isEquipped = ($avatar['id'] === $equipped_avatar_id);
            echo "<div style='border: " . ($isEquipped ? '3px solid red' : '1px solid #ccc') . "; margin: 5px; padding: 10px; display: inline-block;'>";
            echo "<strong>ID: " . $avatar['id'] . " - " . $avatar['name'] . "</strong>";
            if ($isEquipped) echo " <span style='color: red;'>(EQUIPPED)</span>";
            echo "<br>";
            echo "<img src='../assets/img/avatars/" . $avatar['filename'] . "' alt='" . $avatar['name'] . "' style='max-width: 60px; border: 1px solid #ccc;'>";
            echo "</div>";
        }
        
    } else {
        echo "<p><strong>ERROR:</strong> User not found in database!</p>";
    }
} else {
    echo "<p><strong>ERROR:</strong> User is not logged in!</p>";
    echo "<p><strong>Potential Solutions:</strong></p>";
    echo "<ul>";
    echo "<li>Clear browser cookies and try logging in again</li>";
    echo "<li>Make sure you are logged in as a 'user' role (not admin or business)</li>";
    echo "<li>Check if session expired</li>";
    echo "</ul>";
}

echo "<hr>";
echo "<h3>Navigation Links:</h3>";
echo "<p><a href='avatars.php'>Go to Avatars Page</a></p>";
echo "<p><a href='../login.php'>Go to Login Page</a></p>";
echo "<p><a href='dashboard.php'>Go to User Dashboard</a></p>";

// Show database users for reference
echo "<h3>Available Users in Database:</h3>";
$stmt = $pdo->query("SELECT id, username, role, equipped_avatar FROM users LIMIT 5");
$users = $stmt->fetchAll();
echo "<table border='1' style='border-collapse: collapse;'>";
echo "<tr><th>ID</th><th>Username</th><th>Role</th><th>Equipped Avatar</th></tr>";
foreach ($users as $user) {
    echo "<tr>";
    echo "<td>" . $user['id'] . "</td>";
    echo "<td>" . $user['username'] . "</td>";
    echo "<td>" . $user['role'] . "</td>";
    echo "<td>" . $user['equipped_avatar'] . "</td>";
    echo "</tr>";
}
echo "</table>";
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
table { margin: 10px 0; }
th, td { padding: 8px; text-align: left; }
</style> 