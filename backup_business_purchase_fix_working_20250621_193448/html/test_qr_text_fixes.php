<?php
/**
 * QR TEXT & FONT FIXES VERIFICATION TEST
 * Tests the recent fixes to text positioning and font rendering
 */

require_once __DIR__ . '/includes/QRGenerator.php';

echo "<h1>🎨 QR Text & Font Fixes Test</h1>";

// Create QR generator instance
$generator = new QRGenerator();

echo "<h2>📁 Font Availability Check</h2>";

// Test font paths
$fontFamilies = ['Arial', 'Helvetica', 'Times New Roman', 'Montserrat', 'Roboto'];
foreach ($fontFamilies as $font) {
    // Use reflection to test the private getFontPath method
    $reflection = new ReflectionClass($generator);
    $method = $reflection->getMethod('getFontPath');
    $method->setAccessible(true);
    
    $path = $method->invoke($generator, $font);
    
    if ($path && file_exists($path)) {
        echo "✅ <strong>{$font}</strong>: {$path}<br>";
    } elseif ($path) {
        echo "❌ <strong>{$font}</strong>: {$path} (file not found)<br>";
    } else {
        echo "⚠️ <strong>{$font}</strong>: Will use built-in fonts<br>";
    }
}

echo "<h2>🔤 Text Positioning Test</h2>";

// Test QR code generation with text
$testOptions = [
    'content' => 'https://revenueqr.sharedvaluevending.com/test',
    'size' => 300,
    'foreground_color' => '#000000',
    'background_color' => '#FFFFFF',
    'enable_label' => true,
    'label_text' => 'TOP TEXT FIXED',
    'label_size' => 24,
    'label_color' => '#FF0000',
    'label_font' => 'Arial',
    'enable_bottom_text' => true,
    'bottom_text' => 'Bottom Text Also Fixed',
    'bottom_size' => 18,
    'bottom_color' => '#0000FF',
    'bottom_font' => 'Arial',
    'preview' => true
];

echo "<p>Generating QR code with large text to test positioning...</p>";

$result = $generator->generate($testOptions);

if ($result['success']) {
    echo "<div style='text-align: center; margin: 20px;'>";
    echo "<h3>✅ QR Code Generated Successfully!</h3>";
    echo "<img src='{$result['url']}' alt='Test QR Code' style='border: 1px solid #ccc; max-width: 500px;'>";
    echo "<p><strong>Test Features:</strong></p>";
    echo "<ul style='text-align: left; display: inline-block;'>";
    echo "<li>Large top text (24px) - should not be cut off</li>";
    echo "<li>Bottom text (18px) - should be properly spaced</li>";
    echo "<li>Font fallback system - should work even if custom fonts missing</li>";
    echo "<li>Dynamic spacing - adjusts based on font size</li>";
    echo "</ul>";
    echo "</div>";
} else {
    echo "<p>❌ Error generating QR code: {$result['message']}</p>";
}

echo "<h2>📊 Canvas Size Test</h2>";

// Test different font sizes to verify canvas sizing
$fontSizes = [12, 16, 24, 32, 48];
foreach ($fontSizes as $size) {
    $testOptions['label_size'] = $size;
    $testOptions['label_text'] = "Font Size {$size}px";
    
    $result = $generator->generate($testOptions);
    
    if ($result['success']) {
        echo "<div style='display: inline-block; margin: 10px; text-align: center;'>";
        echo "<img src='{$result['url']}' alt='Font Size {$size}' style='max-width: 150px; border: 1px solid #ccc;'>";
        echo "<br><small>{$size}px font</small>";
        echo "</div>";
    }
}

echo "<h2>🎯 Summary of Fixes</h2>";
echo "<div style='background: #f0f8ff; padding: 15px; border-radius: 8px;'>";
echo "<h3>✅ Issues Fixed:</h3>";
echo "<ul>";
echo "<li><strong>Text Cut-off Prevention:</strong> Dynamic spacing based on font size (minimum 50px or 3x font size)</li>";
echo "<li><strong>Font Fallback System:</strong> Multiple fallback paths ensure fonts always work</li>";
echo "<li><strong>Canvas Sizing:</strong> Dynamic extra space calculation based on text size</li>";
echo "<li><strong>QR Positioning:</strong> Automatic adjustment when top text is enabled</li>";
echo "<li><strong>Consistent Spacing:</strong> Both TTF and built-in fonts use proper positioning</li>";
echo "</ul>";

echo "<h3>🔧 Technical Improvements:</h3>";
echo "<ul>";
echo "<li>Replaced fixed 30px spacing with dynamic calculation</li>";
echo "<li>Added font existence checking with graceful fallbacks</li>";
echo "<li>Improved text height calculation using font metrics</li>";
echo "<li>Added QR position adjustment to prevent text overlap</li>";
echo "</ul>";
echo "</div>";

echo "<style>
body { font-family: Arial, sans-serif; max-width: 1200px; margin: 0 auto; padding: 20px; }
h1 { color: #2c5aa0; }
h2 { color: #0066cc; border-bottom: 2px solid #0066cc; padding-bottom: 5px; }
</style>";
?> 