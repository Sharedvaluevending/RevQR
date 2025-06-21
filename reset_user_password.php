<?php
/**
 * Password Reset Utility - For Testing
 */

require_once __DIR__ . '/html/core/config.php';
require_once __DIR__ . '/html/core/auth.php';

echo "🔑 Password Reset Utility\n";
echo "========================\n\n";

if (isset($argv[1]) && isset($argv[2])) {
    $username = $argv[1];
    $new_password = $argv[2];
    
    echo "🔄 Resetting password for: $username\n";
    echo "-----------------------------------\n";
    
    try {
        // Get user by username
        $stmt = $pdo->prepare("SELECT id, username, role FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if (!$user) {
            echo "❌ User '$username' not found\n";
            exit(1);
        }
        
        echo "✅ Found user: {$user['username']} (ID: {$user['id']}, Role: {$user['role']})\n";
        
        // Update password
        $result = update_user_password($user['id'], $new_password);
        
        if ($result) {
            echo "✅ Password updated successfully!\n";
            echo "\n🚀 Login details:\n";
            echo "- Username: $username\n";
            echo "- Password: $new_password\n";
            echo "- Role: {$user['role']}\n";
            
            echo "\n💡 Test login:\n";
            echo "php quick_login_helper.php $username $new_password\n";
        } else {
            echo "❌ Failed to update password\n";
        }
        
    } catch (Exception $e) {
        echo "❌ Error: " . $e->getMessage() . "\n";
    }
    
} else {
    echo "💡 Usage:\n";
    echo "--------\n";
    echo "php reset_user_password.php [username] [new_password]\n\n";
    
    echo "📋 Available users:\n";
    echo "------------------\n";
    
    try {
        $stmt = $pdo->prepare("SELECT id, username, role FROM users ORDER BY id");
        $stmt->execute();
        $users = $stmt->fetchAll();
        
        foreach ($users as $user) {
            echo "- {$user['username']} (ID: {$user['id']}, Role: {$user['role']})\n";
        }
        
        echo "\n🔧 Quick examples:\n";
        echo "php reset_user_password.php Mike test123\n";
        echo "php reset_user_password.php sharedvaluevending business123\n";
        echo "php reset_user_password.php mike1 admin123\n";
        
    } catch (Exception $e) {
        echo "❌ Database error: " . $e->getMessage() . "\n";
    }
}
?> 