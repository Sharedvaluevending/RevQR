<?php
require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/core/session.php';
require_once __DIR__ . '/core/auth.php';

// Simulate business session for testing if not logged in
if (!is_logged_in()) {
    $_SESSION['user_id'] = 1;
    $_SESSION['role'] = 'business';
    $_SESSION['user_name'] = 'Test Business User';
}

echo "<h1>🖼️ BUSINESS LOGO DISPLAY TEST</h1>";
echo "<p><strong>Testing business logo display throughout the platform!</strong></p>";

// 1. Check business logo in database
echo "<div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
echo "<h2>1. 📊 Business Logo Status</h2>";

try {
    $stmt = $pdo->prepare("
        SELECT b.*, u.username, u.email 
        FROM businesses b 
        JOIN users u ON b.id = u.business_id 
        WHERE u.id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $business = $stmt->fetch();
    
    if ($business) {
        echo "<div style='color: green; background: white; padding: 15px; border-radius: 5px; border: 2px solid green;'>";
        echo "✅ <strong>Business Found:</strong> " . htmlspecialchars($business['name']) . "<br>";
        echo "<strong>Username:</strong> " . htmlspecialchars($business['username']) . "<br>";
        echo "<strong>Email:</strong> " . htmlspecialchars($business['email']) . "<br>";
        echo "<strong>Type:</strong> " . htmlspecialchars($business['type']) . "<br>";
        
        if (!empty($business['logo_path'])) {
            echo "<strong>Logo Path:</strong> <code>" . htmlspecialchars($business['logo_path']) . "</code><br>";
            $full_logo_url = APP_URL . '/' . $business['logo_path'];
            echo "<strong>Full Logo URL:</strong> <a href='{$full_logo_url}' target='_blank'>{$full_logo_url}</a><br>";
            
            echo "<div style='margin: 15px 0; padding: 15px; background: #f0f8ff; border-radius: 8px;'>";
            echo "<strong>🖼️ Logo Preview:</strong><br>";
            echo "<img src='{$full_logo_url}' alt='Business Logo' style='max-width: 150px; max-height: 150px; object-fit: contain; border: 2px solid #ddd; border-radius: 8px; background: white; padding: 8px; margin: 10px 0;'>";
            echo "</div>";
            
            // Check if file exists
            $logo_file_path = __DIR__ . '/' . $business['logo_path'];
            if (file_exists($logo_file_path)) {
                echo "<div style='color: green; font-weight: bold;'>✅ Logo file exists on server</div>";
            } else {
                echo "<div style='color: red; font-weight: bold;'>❌ Logo file NOT found on server</div>";
                echo "<div style='color: red;'>Expected path: <code>{$logo_file_path}</code></div>";
            }
        } else {
            echo "<div style='color: orange; font-weight: bold;'>⚠️ No logo uploaded yet</div>";
            echo "<div style='margin-top: 10px;'>";
            echo "<a href='business/profile.php' style='background: #007bff; color: white; padding: 8px 16px; text-decoration: none; border-radius: 4px;'>📎 Upload Logo in Profile</a>";
            echo "</div>";
        }
        echo "</div>";
    } else {
        echo "<div style='color: red; background: white; padding: 15px; border-radius: 5px; border: 2px solid red;'>";
        echo "❌ <strong>No business found for this user</strong>";
        echo "</div>";
    }
} catch (Exception $e) {
    echo "<div style='color: red;'>❌ Database Error: " . $e->getMessage() . "</div>";
}
echo "</div>";

// 2. Test logo display in different locations
echo "<div style='background: #e7f3ff; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
echo "<h2>2. 🎯 Logo Display Locations</h2>";

echo "<div style='display: grid; grid-template-columns: 1fr 1fr; gap: 20px;'>";

// Dashboard Welcome Section
echo "<div style='background: white; padding: 15px; border-radius: 5px; border: 2px solid #28a745;'>";
echo "<h3>📊 Dashboard Welcome Section</h3>";
echo "<p><strong>Location:</strong> <code>business/dashboard_simple.php</code></p>";
echo "<p>Shows: Large business logo (60px) beside welcome message</p>";
echo "<a href='business/dashboard_simple.php' target='_blank' style='background: #28a745; color: white; padding: 8px 12px; text-decoration: none; border-radius: 3px;'>🔗 View Dashboard</a>";
echo "</div>";

// Navbar User Menu
echo "<div style='background: white; padding: 15px; border-radius: 5px; border: 2px solid #17a2b8;'>";
echo "<h3>🧭 Navigation Bar</h3>";
echo "<p><strong>Location:</strong> <code>core/includes/navbar.php</code></p>";
echo "<p>Shows: Small logo (24px) in user dropdown + larger logo (40px) in dropdown menu</p>";
echo "<p style='color: #17a2b8; font-weight: bold;'>✨ Visible on ALL pages throughout the platform!</p>";
echo "</div>";

echo "</div>";
echo "</div>";

// 3. Test all business pages with logo
echo "<div style='background: #e8f5e8; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
echo "<h2>3. 🌐 Test Logo on All Business Pages</h2>";
echo "<p>Click any of these links to see your logo displayed in the navbar:</p>";

$test_pages = [
    'business/dashboard_simple.php' => 'Simple Dashboard (with welcome logo)',
    'qr-generator.php' => 'QR Generator',
    'business/profile.php' => 'Business Profile (logo upload)',
    'business/promotions.php' => 'Promotions Management',
    'business/view-votes.php' => 'View Votes',
    'business/edit-items.php' => 'Edit Items',
    'business/manage-campaigns.php' => 'Manage Campaigns',
];

echo "<div style='display: grid; grid-template-columns: 1fr 1fr; gap: 10px;'>";
foreach ($test_pages as $url => $title) {
    echo "<div style='background: white; padding: 10px; border-radius: 4px; border: 1px solid #ddd;'>";
    echo "<a href='{$url}' target='_blank' style='color: #007bff; font-weight: bold; text-decoration: none;'>";
    echo "📄 {$title}";
    echo "</a>";
    echo "</div>";
}
echo "</div>";
echo "</div>";

// 4. Logo Management Instructions
echo "<div style='background: #fff3cd; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
echo "<h2>4. 🛠️ Logo Management</h2>";

echo "<div style='background: white; padding: 15px; border-radius: 5px; margin: 15px 0;'>";
echo "<h3>📤 How to Upload/Change Your Logo:</h3>";
echo "<ol>";
echo "<li>Go to <a href='business/profile.php' target='_blank' style='color: #007bff;'>Business Profile</a></li>";
echo "<li>Scroll down to 'Upload Logo' section</li>";
echo "<li>Choose your logo image file (PNG, JPG, JPEG)</li>";
echo "<li>Click 'Update Profile'</li>";
echo "<li>Your logo will appear throughout the platform!</li>";
echo "</ol>";
echo "</div>";

echo "<div style='background: white; padding: 15px; border-radius: 5px; margin: 15px 0;'>";
echo "<h3>📋 Logo Requirements:</h3>";
echo "<ul>";
echo "<li><strong>File Types:</strong> PNG, JPG, JPEG</li>";
echo "<li><strong>Recommended Size:</strong> 200x200 pixels (square works best)</li>";
echo "<li><strong>File Size:</strong> Under 2MB</li>";
echo "<li><strong>Background:</strong> Transparent or white works best</li>";
echo "</ul>";
echo "</div>";

echo "<div style='background: white; padding: 15px; border-radius: 5px; margin: 15px 0;'>";
echo "<h3>🎨 Logo Display Specifications:</h3>";
echo "<ul>";
echo "<li><strong>Dashboard Welcome:</strong> 60x60px with rounded corners and shadow</li>";
echo "<li><strong>Navbar User Menu:</strong> 24x24px circular thumbnail</li>";
echo "<li><strong>Dropdown Header:</strong> 40x40px with business name</li>";
echo "<li><strong>Fallback:</strong> Default RevenueQR logo if none uploaded</li>";
echo "</ul>";
echo "</div>";
echo "</div>";

// 5. Quick Actions
echo "<div style='background: #f3e5f5; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
echo "<h2>5. ⚡ Quick Actions</h2>";

echo "<div style='display: flex; gap: 15px; flex-wrap: wrap;'>";
echo "<a href='business/profile.php' style='background: #007bff; color: white; padding: 12px 20px; text-decoration: none; border-radius: 6px; font-weight: bold;'>📤 Upload/Change Logo</a>";
echo "<a href='business/dashboard_simple.php' style='background: #28a745; color: white; padding: 12px 20px; text-decoration: none; border-radius: 6px; font-weight: bold;'>📊 View Dashboard</a>";
echo "<a href='qr-generator.php' style='background: #17a2b8; color: white; padding: 12px 20px; text-decoration: none; border-radius: 6px; font-weight: bold;'>🔲 Generate QR Codes</a>";
echo "<a href='business/promotions.php' style='background: #ffc107; color: black; padding: 12px 20px; text-decoration: none; border-radius: 6px; font-weight: bold;'>🎁 Manage Promotions</a>";
echo "</div>";
echo "</div>";

?>

<style>
body { 
    font-family: Arial, sans-serif; 
    padding: 20px; 
    max-width: 1200px; 
    margin: 0 auto; 
    line-height: 1.6;
    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
    min-height: 100vh;
}
h1 { 
    color: #2c5530; 
    border-bottom: 3px solid #2c5530; 
    padding-bottom: 10px; 
    text-align: center;
}
h2 { 
    color: #4a6741; 
    margin-top: 30px; 
}
h3 {
    color: #5a7a61;
    margin-bottom: 10px;
}
a { 
    color: #0066cc; 
    text-decoration: none; 
}
a:hover { 
    text-decoration: underline; 
    opacity: 0.8;
}
code { 
    background: #f4f4f4; 
    padding: 2px 6px; 
    border-radius: 3px; 
    font-family: 'Courier New', monospace;
    font-size: 0.9em;
}
ul, ol {
    padding-left: 20px;
}
li {
    margin-bottom: 5px;
}
</style> 