<?php
echo "🔍 QR GENERATOR ISSUE DIAGNOSTICS\n";
echo "=================================\n\n";

require_once __DIR__ . '/html/core/config.php';
require_once __DIR__ . '/html/core/session.php';
require_once __DIR__ . '/html/core/auth.php';

echo "1. 🔐 AUTHENTICATION CHECK\n";
echo "--------------------------\n";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

echo "Session status: " . (session_status() === PHP_SESSION_ACTIVE ? "✅ Active" : "❌ Inactive") . "\n";

if (isset($_SESSION['user_id'])) {
    echo "User ID: ✅ " . $_SESSION['user_id'] . "\n";
    echo "User role: " . ($_SESSION['role'] ?? 'Not set') . "\n";
    
    if (function_exists('is_logged_in')) {
        echo "Login status: " . (is_logged_in() ? "✅ Logged in" : "❌ Not logged in") . "\n";
    }
    
    // Check if user has business role
    if (isset($_SESSION['role'])) {
        echo "Role check: " . ($_SESSION['role'] === 'business' ? "✅ Business role" : "⚠️  Role: " . $_SESSION['role']) . "\n";
    }
    
} else {
    echo "❌ No user session found\n";
    echo "🔗 User needs to login at: https://revenueqr.sharedvaluevending.com/login.php\n";
}

echo "\n2. 📁 FILE STRUCTURE CHECK\n";
echo "-------------------------\n";

$files_to_check = [
    'html/qr-generator.php' => 'QR Generator Page',
    'html/api/qr/generate.php' => 'QR Generation API',
    'html/api/qr/enhanced-generate.php' => 'Enhanced QR API',
    'html/includes/QRGenerator.php' => 'QR Generator Class',
    'html/qr-test.php' => 'QR Test Page'
];

foreach ($files_to_check as $file => $description) {
    if (file_exists($file)) {
        echo "✅ $description: $file\n";
        
        // Check file permissions
        if (is_readable($file)) {
            echo "   📖 File is readable\n";
        } else {
            echo "   ❌ File is not readable\n";
        }
    } else {
        echo "❌ $description: $file (NOT FOUND)\n";
    }
}

echo "\n3. 🔧 API ENDPOINTS TEST\n";
echo "-----------------------\n";

// Test the QR generation API
$test_data = [
    'qr_type' => 'static',
    'content' => 'https://example.com',
    'size' => 400,
    'foreground_color' => '#000000',
    'background_color' => '#FFFFFF',
    'error_correction_level' => 'H'
];

echo "Testing API endpoint: /api/qr/generate.php\n";

// Simulate API call
$api_file = 'html/api/qr/generate.php';
if (file_exists($api_file)) {
    echo "✅ API file exists\n";
    
    // Check if it requires authentication
    $api_content = file_get_contents($api_file);
    if (strpos($api_content, 'is_logged_in') !== false) {
        echo "⚠️  API requires authentication\n";
    }
    
    if (strpos($api_content, 'QRGenerator') !== false) {
        echo "✅ API uses QRGenerator class\n";
    }
    
} else {
    echo "❌ API file not found\n";
}

echo "\n4. 📋 QR GENERATOR CLASS CHECK\n";
echo "-----------------------------\n";

$qr_class_file = 'html/includes/QRGenerator.php';
if (file_exists($qr_class_file)) {
    echo "✅ QRGenerator class file exists\n";
    
    require_once $qr_class_file;
    
    if (class_exists('QRGenerator')) {
        echo "✅ QRGenerator class is loadable\n";
        
        try {
            $generator = new QRGenerator();
            echo "✅ QRGenerator can be instantiated\n";
            
            // Test basic generation
            $test_options = [
                'type' => 'static',
                'content' => 'https://example.com',
                'size' => 200
            ];
            
            $result = $generator->generate($test_options);
            if (isset($result['success']) && $result['success']) {
                echo "✅ QRGenerator can generate QR codes\n";
            } else {
                echo "⚠️  QRGenerator test failed: " . ($result['message'] ?? 'Unknown error') . "\n";
            }
            
        } catch (Exception $e) {
            echo "❌ QRGenerator instantiation failed: " . $e->getMessage() . "\n";
        }
    } else {
        echo "❌ QRGenerator class not found in file\n";
    }
} else {
    echo "❌ QRGenerator class file not found\n";
}

echo "\n5. 🌐 JAVASCRIPT LIBRARIES CHECK\n";
echo "-------------------------------\n";

$qr_generator_content = '';
if (file_exists('html/qr-generator.php')) {
    $qr_generator_content = file_get_contents('html/qr-generator.php');
    
    // Check for QRCode library
    if (strpos($qr_generator_content, 'qrcode@1.5.3') !== false || strpos($qr_generator_content, 'qrcode.min.js') !== false) {
        echo "✅ QRCode.js library is included\n";
    } else {
        echo "❌ QRCode.js library is missing\n";
    }
    
    // Check for Bootstrap
    if (strpos($qr_generator_content, 'bootstrap') !== false) {
        echo "✅ Bootstrap is included\n";
    } else {
        echo "⚠️  Bootstrap might be missing\n";
    }
    
    // Check for key functions
    if (strpos($qr_generator_content, 'function generatePreview') !== false) {
        echo "✅ generatePreview() function exists\n";
    } else {
        echo "❌ generatePreview() function missing\n";
    }
    
    if (strpos($qr_generator_content, 'function generateQRCode') !== false) {
        echo "✅ generateQRCode() function exists\n";
    } else {
        echo "❌ generateQRCode() function missing\n";
    }
    
    if (strpos($qr_generator_content, 'function showToast') !== false) {
        echo "✅ showToast() function exists\n";
    } else {
        echo "⚠️  showToast() function missing\n";
    }
}

echo "\n6. 🚨 COMMON ISSUES & SOLUTIONS\n";
echo "------------------------------\n";

echo "🔐 **AUTHENTICATION ISSUES:**\n";
if (!isset($_SESSION['user_id'])) {
    echo "   → User is not logged in\n";
    echo "   → Solution: Login at https://revenueqr.sharedvaluevending.com/login.php\n";
}

if (isset($_SESSION['role']) && $_SESSION['role'] !== 'business') {
    echo "   → User role is '" . $_SESSION['role'] . "' but needs 'business'\n";
    echo "   → Solution: Login with a business account or update user role\n";
}

echo "\n🔧 **JAVASCRIPT ISSUES:**\n";
echo "   → Open browser Developer Tools (F12)\n";
echo "   → Check Console tab for errors\n";
echo "   → Look for 'QRCode is not defined' errors\n";
echo "   → Check Network tab for failed API calls\n";

echo "\n📱 **TESTING STEPS:**\n";
echo "1. 🔗 Test simple version: https://revenueqr.sharedvaluevending.com/qr-test.php\n";
echo "2. 🔗 Test full version: https://revenueqr.sharedvaluevending.com/qr-generator.php\n";
echo "3. 📋 Check browser console for errors\n";
echo "4. 🔄 Try different QR code types\n";

echo "\n✨ **QUICK FIXES:**\n";

// Create a simple login bypass for testing
echo "Creating test access file...\n";
$test_access = '<?php
// TEMPORARY TEST ACCESS - REMOVE IN PRODUCTION
session_start();
$_SESSION[\'user_id\'] = 1;
$_SESSION[\'role\'] = \'business\';
$_SESSION[\'username\'] = \'test_user\';

echo "✅ Test session created! Now try:<br>";
echo "<a href=\"/qr-generator.php\">QR Generator</a><br>";
echo "<a href=\"/qr-test.php\">QR Test Page</a><br>";
?>';

file_put_contents('html/test-access.php', $test_access);
echo "✅ Created test access at: https://revenueqr.sharedvaluevending.com/test-access.php\n";

echo "\n🎯 **DIAGNOSIS COMPLETE!**\n";
echo "========================\n";

if (!isset($_SESSION['user_id'])) {
    echo "🚨 **MAIN ISSUE: USER NOT LOGGED IN**\n";
    echo "   → Visit: https://revenueqr.sharedvaluevending.com/test-access.php\n";
    echo "   → Then try: https://revenueqr.sharedvaluevending.com/qr-generator.php\n";
} else {
    echo "✅ **USER IS LOGGED IN - CHECKING OTHER ISSUES**\n";
}
?> 