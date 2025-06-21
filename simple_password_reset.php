<?php
/**
 * Simple Password Reset - Fixed for missing columns
 */

require_once __DIR__ . '/html/core/config.php';

echo "🔑 Simple Password Reset\n";
echo "=======================\n\n";

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
        
        // Update password - simplified without updated_at column
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $result = $stmt->execute([$hashed_password, $user['id']]);
        
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
            echo "SQL Error: " . print_r($stmt->errorInfo(), true) . "\n";
        }
        
    } catch (Exception $e) {
        echo "❌ Error: " . $e->getMessage() . "\n";
    }
    
} else {
    echo "💡 Usage:\n";
    echo "--------\n";
    echo "php simple_password_reset.php [username] [new_password]\n\n";
    
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
        echo "php simple_password_reset.php Mike test123\n";
        echo "php simple_password_reset.php sharedvaluevending business123\n";
        
    } catch (Exception $e) {
        echo "❌ Database error: " . $e->getMessage() . "\n";
    }
}
?> 