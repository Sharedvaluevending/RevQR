<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'html/includes/QRGenerator.php';

echo "QR Text Rendering Simple Test\n";
echo "=============================\n\n";

// Test 1: Basic QRGenerator test
echo "Test 1: QRGenerator Direct Test\n";
echo "--------------------------------\n";

try {
    $generator = new QRGenerator();
    
    $options = [
        'type' => 'url',
        'content' => 'https://example.com',
        'size' => 300,
        'foreground_color' => '#000000',
        'background_color' => '#FFFFFF',
        'error_correction_level' => 'H',
        
        // Top text
        'enable_label' => true,
        'label_text' => 'TOP TEXT TEST',
        'label_size' => 16,
        'label_color' => '#FF0000',
        'label_font' => 'Arial',
        'label_alignment' => 'center',
        
        // Bottom text
        'enable_bottom_text' => true,
        'bottom_text' => 'BOTTOM TEXT TEST',
        'bottom_size' => 14,
        'bottom_color' => '#0000FF',
        'bottom_font' => 'Arial',
        'bottom_alignment' => 'center'
    ];
    
    echo "Options prepared:\n";
    echo "- Top text: " . $options['label_text'] . "\n";
    echo "- Bottom text: " . $options['bottom_text'] . "\n";
    echo "- Size: " . $options['size'] . "\n\n";
    
    $result = $generator->generate($options);
    
    if ($result['success']) {
        echo "✅ SUCCESS: QR generated with text\n";
        echo "File: " . $result['data']['qr_code_url'] . "\n";
        
        // Check if file actually exists
        $filePath = 'html/' . ltrim($result['data']['qr_code_url'], '/');
        if (file_exists($filePath)) {
            echo "✅ File exists: " . $filePath . "\n";
            echo "File size: " . filesize($filePath) . " bytes\n";
        } else {
            echo "❌ File missing: " . $filePath . "\n";
        }
    } else {
        echo "❌ FAILED: " . ($result['error'] ?? 'Unknown error') . "\n";
        if (isset($result['debug'])) {
            echo "Debug info: " . print_r($result['debug'], true) . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ EXCEPTION: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n";

// Test 2: Font availability
echo "Test 2: Font Check\n";
echo "------------------\n";

$fontPaths = [
    '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
    '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
    'html/vendor/endroid/qr-code/assets/noto_sans.otf'
];

foreach ($fontPaths as $path) {
    $exists = file_exists($path);
    $readable = $exists ? is_readable($path) : false;
    echo "- " . $path . ": " . ($exists ? "EXISTS" : "MISSING") . 
         ($readable ? " & READABLE" : ($exists ? " but NOT READABLE" : "")) . "\n";
}

echo "\n";

// Test 3: GD functions
echo "Test 3: GD Function Check\n";
echo "--------------------------\n";
echo "- imagettftext: " . (function_exists('imagettftext') ? "AVAILABLE" : "MISSING") . "\n";
echo "- imagestring: " . (function_exists('imagestring') ? "AVAILABLE" : "MISSING") . "\n";
echo "- imagettfbbox: " . (function_exists('imagettfbbox') ? "AVAILABLE" : "MISSING") . "\n";

echo "\n";

// Test 4: Manual text rendering test
echo "Test 4: Manual Text Rendering\n";
echo "------------------------------\n";

try {
    // Create a simple test image
    $testImage = imagecreatetruecolor(400, 200);
    $white = imagecolorallocate($testImage, 255, 255, 255);
    $black = imagecolorallocate($testImage, 0, 0, 0);
    $red = imagecolorallocate($testImage, 255, 0, 0);
    
    imagefill($testImage, 0, 0, $white);
    
    // Test built-in font
    imagestring($testImage, 5, 10, 10, "Built-in font test", $black);
    
    // Test TTF font if available
    $fontPath = '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf';
    if (file_exists($fontPath) && function_exists('imagettftext')) {
        imagettftext($testImage, 16, 0, 10, 50, $red, $fontPath, 'TTF font test');
        echo "✅ TTF text rendered\n";
    } else {
        echo "❌ TTF not available\n";
    }
    
    // Save test image
    $testPath = 'html/uploads/temp/manual_text_test.png';
    @mkdir(dirname($testPath), 0755, true);
    
    if (imagepng($testImage, $testPath)) {
        echo "✅ Test image saved: " . $testPath . "\n";
    } else {
        echo "❌ Failed to save test image\n";
    }
    
    imagedestroy($testImage);
    
} catch (Exception $e) {
    echo "❌ Manual test failed: " . $e->getMessage() . "\n";
}

echo "\nTest complete!\n";
?> 