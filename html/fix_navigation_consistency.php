<?php
// Fix navigation consistency across all business pages
echo "<h1>🔧 Navigation Consistency Fix</h1>";
echo "<p>Updating all business pages to use the modern header system...</p>";

// Files that need to be updated to use the modern header
$files_to_fix = [
    // vending-vote-platform files using old header
    '/var/www/vending-vote-platform/business/manage-headers.php' => [
        'old' => "require_once __DIR__ . '/../templates/header.php';",
        'new' => "require_once __DIR__ . '/../core/includes/header.php';"
    ],
    '/var/www/vending-vote-platform/business/winners.php' => [
        'old' => "include '../templates/header.php';",
        'new' => "require_once __DIR__ . '/../core/includes/header.php';"
    ],
];

echo "<h3>📝 Files to Update:</h3>";
echo "<ul>";

$updated_count = 0;
$errors = [];

foreach ($files_to_fix as $file_path => $replacements) {
    echo "<li><strong>" . basename($file_path) . "</strong> - ";
    
    if (!file_exists($file_path)) {
        echo "❌ File not found<br>";
        $errors[] = "$file_path - File not found";
        continue;
    }
    
    $content = file_get_contents($file_path);
    $original_content = $content;
    
    // Make the replacement
    $content = str_replace($replacements['old'], $replacements['new'], $content);
    
    if ($content !== $original_content) {
        if (file_put_contents($file_path, $content)) {
            echo "✅ Updated successfully";
            $updated_count++;
        } else {
            echo "❌ Failed to write file";
            $errors[] = "$file_path - Failed to write";
        }
    } else {
        echo "⚠️ No changes needed";
    }
    echo "</li>";
}

echo "</ul>";

echo "<h3>📊 Summary:</h3>";
echo "<p><strong>Files updated:</strong> $updated_count</p>";

if (!empty($errors)) {
    echo "<p><strong>Errors:</strong></p>";
    echo "<ul>";
    foreach ($errors as $error) {
        echo "<li class='text-danger'>$error</li>";
    }
    echo "</ul>";
}

// Also check the vending-vote-platform navbar
echo "<h3>🔍 Checking Alternative Navigation Files:</h3>";

$alt_navbar = '/var/www/vending-vote-platform/core/includes/navbar.php';
if (file_exists($alt_navbar)) {
    echo "<p><strong>Found alternative navbar:</strong> $alt_navbar</p>";
    
    $navbar_content = file_get_contents($alt_navbar);
    $has_qr_manager = strpos($navbar_content, 'QR Manager') !== false;
    
    echo "<p><strong>Contains QR Manager:</strong> " . ($has_qr_manager ? "✅ Yes" : "❌ No") . "</p>";
    
    if (!$has_qr_manager) {
        echo "<p class='text-warning'>⚠️ This navbar needs to be updated with QR Manager functionality</p>";
    }
} else {
    echo "<p>Alternative navbar not found (this is okay)</p>";
}

echo "<h3>✅ Quick Verification:</h3>";
echo "<p>Test these business pages to see if navigation is now consistent:</p>";
echo "<ul>";
echo "<li><a href='/html/business/dashboard_simple.php' target='_blank'>Dashboard Simple</a></li>";
echo "<li><a href='/html/business/dashboard_modular.php' target='_blank'>Dashboard Modular</a></li>";
echo "<li><a href='/html/qr-generator-enhanced.php' target='_blank'>QR Generator Enhanced</a></li>";
echo "<li><a href='/html/qr_manager.php' target='_blank'>QR Manager</a></li>";
echo "</ul>";

echo "<h3>🎯 Expected Result:</h3>";
echo "<p>All pages should now show the same modern navigation with:</p>";
echo "<ul>";
echo "<li>QR Codes dropdown</li>";
echo "<li>QR Manager link (with 'New' badge)</li>";
echo "<li>Business tools and analytics</li>";
echo "<li>Modern styling and layout</li>";
echo "</ul>";

echo "<p class='text-success'><strong>✅ Navigation consistency fix completed!</strong></p>";
?> 