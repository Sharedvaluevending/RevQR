<?php
require_once 'html/includes/QRGenerator.php';

echo "<h1>QR Text Rendering Debug Test</h1>";

// Test 1: Basic text rendering with QRGenerator
echo "<h2>Test 1: QRGenerator Direct Text Test</h2>";

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

$result = $generator->generate($options);

if ($result['success']) {
    echo "<p>✅ QRGenerator text test successful</p>";
    echo "<p>File: " . $result['data']['qr_code_url'] . "</p>";
    echo "<img src='" . $result['data']['qr_code_url'] . "' alt='QR with text' style='border: 1px solid #ccc; margin: 10px;'>";
} else {
    echo "<p>❌ QRGenerator text test failed: " . ($result['error'] ?? 'Unknown error') . "</p>";
}

// Test 2: Check font availability
echo "<h2>Test 2: Font Availability Check</h2>";

$fontPaths = [
    '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
    '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
    '/usr/share/fonts/TTF/DejaVuSans.ttf',
    '/System/Library/Fonts/Arial.ttf',
    '/Windows/Fonts/arial.ttf',
    __DIR__ . '/html/vendor/endroid/qr-code/assets/noto_sans.otf',
    '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf'
];

echo "<ul>";
foreach ($fontPaths as $path) {
    $exists = file_exists($path);
    $readable = $exists ? is_readable($path) : false;
    echo "<li>" . $path . " - " . ($exists ? "✅ Exists" : "❌ Missing") . 
         ($readable ? " & Readable" : ($exists ? " but NOT readable" : "")) . "</li>";
}
echo "</ul>";

// Test 3: Test with different canvas sizes to check positioning
echo "<h2>Test 3: Canvas Size & Positioning Test</h2>";

$sizes = [200, 300, 400, 500];
foreach ($sizes as $size) {
    $options['size'] = $size;
    $options['label_text'] = "SIZE {$size}px";
    $options['bottom_text'] = "Bottom {$size}px";
    
    $result = $generator->generate($options);
    
    if ($result['success']) {
        echo "<div style='display: inline-block; margin: 10px; text-align: center;'>";
        echo "<p>Size: {$size}px</p>";
        echo "<img src='" . $result['data']['qr_code_url'] . "' alt='QR {$size}px' style='border: 1px solid #ccc; max-width: 200px;'>";
        echo "</div>";
    }
}

// Test 4: Test API endpoints directly
echo "<h2>Test 4: API Endpoint Tests</h2>";

// Test basic generator API
echo "<h3>Basic Generator API Test</h3>";
$basicData = [
    'qr_type' => 'url',
    'content' => 'https://example.com/basic',
    'size' => 300,
    'enable_label' => true,
    'label_text' => 'BASIC API TEST',
    'label_size' => 16,
    'label_color' => '#FF0000',
    'enable_bottom_text' => true,
    'bottom_text' => 'Basic Bottom',
    'bottom_size' => 14,
    'bottom_color' => '#0000FF'
];

// Simulate API call
$_POST = $basicData;
$_SESSION['user_id'] = 1; // Mock session

ob_start();
try {
    include 'html/api/qr/generate.php';
    $basicApiOutput = ob_get_contents();
} catch (Exception $e) {
    $basicApiOutput = "Error: " . $e->getMessage();
}
ob_end_clean();

echo "<pre>Basic API Response: " . htmlspecialchars($basicApiOutput) . "</pre>";

// Test enhanced generator API
echo "<h3>Enhanced Generator API Test</h3>";
$enhancedData = [
    'qr_type' => 'url',
    'content' => 'https://example.com/enhanced',
    'size' => 300,
    'enable_label' => true,
    'label_text' => 'ENHANCED API TEST',
    'label_size' => 16,
    'label_color' => '#FF0000',
    'enable_bottom_text' => true,
    'bottom_text' => 'Enhanced Bottom',
    'bottom_size' => 14,
    'bottom_color' => '#0000FF'
];

$_POST = $enhancedData;

ob_start();
try {
    include 'html/api/qr/enhanced-generate.php';
    $enhancedApiOutput = ob_get_contents();
} catch (Exception $e) {
    $enhancedApiOutput = "Error: " . $e->getMessage();
}
ob_end_clean();

echo "<pre>Enhanced API Response: " . htmlspecialchars($enhancedApiOutput) . "</pre>";

// Test 5: Check canvas dimensions and text positioning calculations
echo "<h2>Test 5: Canvas & Text Position Analysis</h2>";

// Create a test image to analyze positioning
$testCanvas = imagecreatetruecolor(400, 500); // Extra height for text
$white = imagecolorallocate($testCanvas, 255, 255, 255);
$black = imagecolorallocate($testCanvas, 0, 0, 0);
$red = imagecolorallocate($testCanvas, 255, 0, 0);
$blue = imagecolorallocate($testCanvas, 0, 0, 255);

imagefill($testCanvas, 0, 0, $white);

// Draw QR area (simulated)
$qrX = 50;
$qrY = 100;
$qrSize = 300;
imagerectangle($testCanvas, $qrX, $qrY, $qrX + $qrSize, $qrY + $qrSize, $black);

// Test text positioning calculations
$fontSize = 16;
$dynamicSpacing = max(50, $fontSize * 3);

// Top text position
$topTextY = $qrY - $dynamicSpacing;
imagestring($testCanvas, 5, $qrX + 50, $topTextY, "TOP TEXT (Y: $topTextY)", $red);

// Bottom text position  
$bottomDynamicSpacing = max(30, 14 * 2);
$bottomTextY = $qrY + $qrSize + $bottomDynamicSpacing;
imagestring($testCanvas, 4, $qrX + 50, $bottomTextY, "BOTTOM TEXT (Y: $bottomTextY)", $blue);

// Add reference lines
imageline($testCanvas, 0, $qrY, 400, $qrY, $black); // QR top
imageline($testCanvas, 0, $qrY + $qrSize, 400, $qrY + $qrSize, $black); // QR bottom
imageline($testCanvas, 0, $topTextY, 400, $topTextY, $red); // Top text line
imageline($testCanvas, 0, $bottomTextY, 400, $bottomTextY, $blue); // Bottom text line

// Save test canvas
$testPath = 'html/uploads/temp/text_position_test.png';
@mkdir(dirname($testPath), 0755, true);
imagepng($testCanvas, $testPath);
imagedestroy($testCanvas);

echo "<p>Text positioning analysis:</p>";
echo "<img src='uploads/temp/text_position_test.png' alt='Position test' style='border: 1px solid #ccc;'>";
echo "<ul>";
echo "<li>QR Area: Y {$qrY} to " . ($qrY + $qrSize) . "</li>";
echo "<li>Top Text Y: {$topTextY} (spacing: {$dynamicSpacing}px)</li>";
echo "<li>Bottom Text Y: {$bottomTextY} (spacing: {$bottomDynamicSpacing}px)</li>";
echo "</ul>";

// Test 6: Check if imagettftext function is available
echo "<h2>Test 6: GD Library Function Check</h2>";
echo "<ul>";
echo "<li>imagettftext available: " . (function_exists('imagettftext') ? "✅ Yes" : "❌ No") . "</li>";
echo "<li>imagestring available: " . (function_exists('imagestring') ? "✅ Yes" : "❌ No") . "</li>";
echo "<li>imagettfbbox available: " . (function_exists('imagettfbbox') ? "✅ Yes" : "❌ No") . "</li>";
echo "</ul>";

if (function_exists('imagettftext')) {
    // Test TTF text rendering directly
    $testTTF = imagecreatetruecolor(300, 100);
    $white = imagecolorallocate($testTTF, 255, 255, 255);
    $black = imagecolorallocate($testTTF, 0, 0, 0);
    imagefill($testTTF, 0, 0, $white);
    
    $fontPath = '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf';
    if (file_exists($fontPath)) {
        imagettftext($testTTF, 16, 0, 10, 50, $black, $fontPath, 'TTF Test Text');
        $ttfTestPath = 'html/uploads/temp/ttf_test.png';
        imagepng($testTTF, $ttfTestPath);
        echo "<p>TTF Test: <img src='uploads/temp/ttf_test.png' alt='TTF test' style='border: 1px solid #ccc;'></p>";
    } else {
        echo "<p>❌ TTF font not found at: $fontPath</p>";
    }
    imagedestroy($testTTF);
}

echo "<p><strong>Debug Complete!</strong></p>";
?> 