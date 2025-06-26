<?php
require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/core/session.php';
require_once __DIR__ . '/core/auth.php';
require_once __DIR__ . '/includes/QRGenerator.php';

// Create a test session for business ID 1
$_SESSION = [
    'user_id' => 1,
    'business_id' => 1,
    'role' => 'business',
    'logged_in' => true
];

echo "<h1>🎯 QR Code Test Generator</h1>";
echo "<p>This script will create one QR code of each supported type for testing purposes.</p><br>";

$generator = new QRGenerator();
$test_results = [];

// Test 1: Static QR Code
echo "<h2>1. Static QR Code</h2>";
$options1 = [
    'type' => 'static',
    'content' => 'https://example.com/static-test',
    'size' => 300,
    'foreground_color' => '#000000',
    'background_color' => '#FFFFFF',
    'error_correction_level' => 'H',
    'preview' => false
];

try {
    $result1 = $generator->generate($options1);
    if ($result1['success']) {
        echo "✅ Static QR Code generated successfully<br>";
        echo "📁 File: " . $result1['data']['qr_code_url'] . "<br>";
        echo "🔗 Content: " . $options1['content'] . "<br>";
        echo "<img src='" . $result1['data']['qr_code_url'] . "' style='max-width: 200px; border: 1px solid #ddd; margin: 10px 0;'><br>";
        $test_results['static'] = true;
    } else {
        echo "❌ Static QR Code failed: " . $result1['error'] . "<br>";
        $test_results['static'] = false;
    }
} catch (Exception $e) {
    echo "❌ Static QR Code error: " . $e->getMessage() . "<br>";
    $test_results['static'] = false;
}
echo "<hr>";

// Test 2: Dynamic QR Code
echo "<h2>2. Dynamic QR Code</h2>";
$options2 = [
    'type' => 'dynamic',
    'content' => 'https://example.com/dynamic-test?id=12345',
    'size' => 300,
    'foreground_color' => '#1565c0',
    'background_color' => '#ffffff',
    'error_correction_level' => 'H',
    'preview' => false
];

try {
    $result2 = $generator->generate($options2);
    if ($result2['success']) {
        echo "✅ Dynamic QR Code generated successfully<br>";
        echo "📁 File: " . $result2['data']['qr_code_url'] . "<br>";
        echo "🔗 Content: " . $options2['content'] . "<br>";
        echo "<img src='" . $result2['data']['qr_code_url'] . "' style='max-width: 200px; border: 1px solid #ddd; margin: 10px 0;'><br>";
        $test_results['dynamic'] = true;
    } else {
        echo "❌ Dynamic QR Code failed: " . $result2['error'] . "<br>";
        $test_results['dynamic'] = false;
    }
} catch (Exception $e) {
    echo "❌ Dynamic QR Code error: " . $e->getMessage() . "<br>";
    $test_results['dynamic'] = false;
}
echo "<hr>";

// Test 3: Dynamic Voting QR Code
echo "<h2>3. Dynamic Voting QR Code</h2>";
$qr_code3 = uniqid('qr_voting_', true);
$options3 = [
    'type' => 'dynamic_voting',
    'content' => APP_URL . '/vote.php?code=' . $qr_code3,
    'size' => 300,
    'foreground_color' => '#2e7d32',
    'background_color' => '#ffffff',
    'error_correction_level' => 'H',
    'preview' => false
];

try {
    $result3 = $generator->generate($options3);
    if ($result3['success']) {
        echo "✅ Dynamic Voting QR Code generated successfully<br>";
        echo "📁 File: " . $result3['data']['qr_code_url'] . "<br>";
        echo "🔗 Content: " . $options3['content'] . "<br>";
        echo "🗳️ Campaign: More tests (ID: 11)<br>";
        echo "<img src='" . $result3['data']['qr_code_url'] . "' style='max-width: 200px; border: 1px solid #ddd; margin: 10px 0;'><br>";
        $test_results['dynamic_voting'] = true;
    } else {
        echo "❌ Dynamic Voting QR Code failed: " . $result3['error'] . "<br>";
        $test_results['dynamic_voting'] = false;
    }
} catch (Exception $e) {
    echo "❌ Dynamic Voting QR Code error: " . $e->getMessage() . "<br>";
    $test_results['dynamic_voting'] = false;
}
echo "<hr>";

// Test 4: Dynamic Vending QR Code
echo "<h2>4. Dynamic Vending QR Code</h2>";
$qr_code4 = uniqid('qr_vending_', true);
$options4 = [
    'type' => 'dynamic_vending',
    'content' => APP_URL . '/vote.php?code=' . $qr_code4,
    'size' => 300,
    'foreground_color' => '#f57c00',
    'background_color' => '#ffffff',
    'error_correction_level' => 'H',
    'preview' => false
];

try {
    $result4 = $generator->generate($options4);
    if ($result4['success']) {
        echo "✅ Dynamic Vending QR Code generated successfully<br>";
        echo "📁 File: " . $result4['data']['qr_code_url'] . "<br>";
        echo "🔗 Content: " . $options4['content'] . "<br>";
        echo "🏭 Machine: More tests (ID: 226)<br>";
        echo "<img src='" . $result4['data']['qr_code_url'] . "' style='max-width: 200px; border: 1px solid #ddd; margin: 10px 0;'><br>";
        $test_results['dynamic_vending'] = true;
    } else {
        echo "❌ Dynamic Vending QR Code failed: " . $result4['error'] . "<br>";
        $test_results['dynamic_vending'] = false;
    }
} catch (Exception $e) {
    echo "❌ Dynamic Vending QR Code error: " . $e->getMessage() . "<br>";
    $test_results['dynamic_vending'] = false;
}
echo "<hr>";

// Test 5: Machine Sales QR Code
echo "<h2>5. Machine Sales QR Code</h2>";
$machine_name = "More tests";
$options5 = [
    'type' => 'machine_sales',
    'content' => APP_URL . '/public/promotions.php?machine=' . urlencode($machine_name),
    'size' => 300,
    'foreground_color' => '#d32f2f',
    'background_color' => '#ffffff',
    'error_correction_level' => 'H',
    'preview' => false
];

try {
    $result5 = $generator->generate($options5);
    if ($result5['success']) {
        echo "✅ Machine Sales QR Code generated successfully<br>";
        echo "📁 File: " . $result5['data']['qr_code_url'] . "<br>";
        echo "🔗 Content: " . $options5['content'] . "<br>";
        echo "🏭 Machine: " . $machine_name . "<br>";
        echo "<img src='" . $result5['data']['qr_code_url'] . "' style='max-width: 200px; border: 1px solid #ddd; margin: 10px 0;'><br>";
        $test_results['machine_sales'] = true;
    } else {
        echo "❌ Machine Sales QR Code failed: " . $result5['error'] . "<br>";
        $test_results['machine_sales'] = false;
    }
} catch (Exception $e) {
    echo "❌ Machine Sales QR Code error: " . $e->getMessage() . "<br>";
    $test_results['machine_sales'] = false;
}
echo "<hr>";

// Test 6: Promotion QR Code
echo "<h2>6. Promotion QR Code</h2>";
$options6 = [
    'type' => 'promotion',
    'content' => APP_URL . '/public/promotions.php?machine=' . urlencode($machine_name) . '&view=promotions',
    'size' => 300,
    'foreground_color' => '#7b1fa2',
    'background_color' => '#ffffff',
    'error_correction_level' => 'H',
    'preview' => false
];

try {
    $result6 = $generator->generate($options6);
    if ($result6['success']) {
        echo "✅ Promotion QR Code generated successfully<br>";
        echo "📁 File: " . $result6['data']['qr_code_url'] . "<br>";
        echo "🔗 Content: " . $options6['content'] . "<br>";
        echo "🏭 Machine: " . $machine_name . "<br>";
        echo "<img src='" . $result6['data']['qr_code_url'] . "' style='max-width: 200px; border: 1px solid #ddd; margin: 10px 0;'><br>";
        $test_results['promotion'] = true;
    } else {
        echo "❌ Promotion QR Code failed: " . $result6['error'] . "<br>";
        $test_results['promotion'] = false;
    }
} catch (Exception $e) {
    echo "❌ Promotion QR Code error: " . $e->getMessage() . "<br>";
    $test_results['promotion'] = false;
}
echo "<hr>";

// Test 7: Spin Wheel QR Code
echo "<h2>7. Spin Wheel QR Code</h2>";
$spin_wheel_id = 1;
$options7 = [
    'type' => 'spin_wheel',
    'content' => APP_URL . '/public/spin-wheel.php?wheel_id=' . $spin_wheel_id,
    'size' => 300,
    'foreground_color' => '#00796b',
    'background_color' => '#ffffff',
    'error_correction_level' => 'H',
    'preview' => false
];

try {
    $result7 = $generator->generate($options7);
    if ($result7['success']) {
        echo "✅ Spin Wheel QR Code generated successfully<br>";
        echo "📁 File: " . $result7['data']['qr_code_url'] . "<br>";
        echo "🔗 Content: " . $options7['content'] . "<br>";
        echo "🎡 Spin Wheel: Shared Value Vending - Default Wheel (ID: " . $spin_wheel_id . ")<br>";
        echo "<img src='" . $result7['data']['qr_code_url'] . "' style='max-width: 200px; border: 1px solid #ddd; margin: 10px 0;'><br>";
        $test_results['spin_wheel'] = true;
    } else {
        echo "❌ Spin Wheel QR Code failed: " . $result7['error'] . "<br>";
        $test_results['spin_wheel'] = false;
    }
} catch (Exception $e) {
    echo "❌ Spin Wheel QR Code error: " . $e->getMessage() . "<br>";
    $test_results['spin_wheel'] = false;
}
echo "<hr>";

// Summary
echo "<h2>📊 Test Results Summary</h2>";
$successful = array_sum($test_results);
$total = count($test_results);

echo "<div style='background: #f5f5f5; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
echo "<h3>Overall Results: {$successful}/{$total} QR Codes Generated Successfully</h3>";

foreach ($test_results as $type => $success) {
    $icon = $success ? "✅" : "❌";
    $status = $success ? "SUCCESS" : "FAILED";
    $color = $success ? "#2e7d32" : "#d32f2f";
    echo "<div style='margin: 10px 0; color: {$color};'>";
    echo "{$icon} <strong>" . ucfirst(str_replace('_', ' ', $type)) . ":</strong> {$status}";
    echo "</div>";
}

if ($successful == $total) {
    echo "<div style='background: #e8f5e8; color: #2e7d32; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
    echo "<strong>🎉 ALL QR CODE TYPES WORKING PERFECTLY!</strong><br>";
    echo "Your QR code system is fully functional and ready for use.";
    echo "</div>";
} else {
    echo "<div style='background: #fce4ec; color: #d32f2f; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
    echo "<strong>⚠️ Some QR code types failed to generate.</strong><br>";
    echo "Please check the error messages above for troubleshooting.";
    echo "</div>";
}

echo "</div>";

echo "<h2>🔗 Quick Links</h2>";
echo "<ul>";
echo "<li><a href='/qr-generator.php' target='_blank'>QR Generator Interface</a></li>";
echo "<li><a href='/qr-codes.php' target='_blank'>View All QR Codes</a></li>";
echo "<li><a href='/qr-display.php' target='_blank'>QR Display Page</a></li>";
echo "<li><a href='/uploads/qr/' target='_blank'>QR Files Directory</a></li>";
echo "</ul>";

echo "<h2>💡 Test Instructions</h2>";
echo "<ol>";
echo "<li><strong>Scan each QR code</strong> with your phone to test functionality</li>";
echo "<li><strong>Verify the URLs</strong> point to the correct pages</li>";
echo "<li><strong>Test user flows</strong> - voting, promotions, spin wheel, etc.</li>";
echo "<li><strong>Check mobile responsiveness</strong> of landing pages</li>";
echo "<li><strong>Validate data tracking</strong> - ensure scans are logged properly</li>";
echo "</ol>";
?> 