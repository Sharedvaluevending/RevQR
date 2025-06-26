<?php
require_once __DIR__ . '/includes/QRGenerator.php';

// Test the improved glow effect
$qrGenerator = new QRGenerator();

$options = [
    'content' => 'https://example.com/test-glow',
    'type' => 'static',
    'size' => 400,
    'foreground_color' => '#000000',
    'background_color' => '#FFFFFF',
    'error_correction_level' => 'H',
    
    // Enable enhanced border with glow
    'enable_enhanced_border' => true,
    'border_style' => 'solid',
    'border_width' => 3,
    'border_color_primary' => '#0d6efd',
    'border_glow_intensity' => 15, // Test with strong glow
    'border_radius_style' => 'md',
    
    // Enable background gradient for contrast
    'enable_background_gradient' => true,
    'bg_gradient_type' => 'linear',
    'bg_gradient_start' => '#f8f9fa',
    'bg_gradient_end' => '#e9ecef',
    'bg_gradient_angle' => 135,
    
    'preview' => false
];

echo "<h1>🧪 Testing Improved Glow Effect</h1>";
echo "<p>This test generates a QR code with the improved glow border effect.</p>";

try {
    $result = $qrGenerator->generate($options);
    
    if ($result['success']) {
        $qrUrl = $result['data']['qr_code_url'];
        echo "<div style='text-align: center; padding: 20px;'>";
        echo "<h3>✅ QR Code Generated Successfully!</h3>";
        echo "<img src='{$qrUrl}' alt='Test QR Code with Glow' style='border: 1px solid #ddd; padding: 10px; background: white;'>";
        echo "<p><strong>File:</strong> {$qrUrl}</p>";
        echo "<p><strong>Glow Intensity:</strong> 15px</p>";
        echo "<p><strong>Border Style:</strong> Solid with rounded corners</p>";
        echo "</div>";
        
        // Test different glow intensities
        echo "<h3>🎨 Different Glow Intensities</h3>";
        echo "<div style='display: flex; flex-wrap: wrap; gap: 20px; justify-content: center;'>";
        
        $glowLevels = [0, 5, 10, 15, 20];
        foreach ($glowLevels as $glow) {
            $testOptions = $options;
            $testOptions['border_glow_intensity'] = $glow;
            $testOptions['content'] = "https://example.com/glow-{$glow}";
            
            $testResult = $qrGenerator->generate($testOptions);
            if ($testResult['success']) {
                $testUrl = $testResult['data']['qr_code_url'];
                echo "<div style='text-align: center;'>";
                echo "<img src='{$testUrl}' alt='Glow {$glow}' style='width: 150px; height: 150px; border: 1px solid #ddd;'>";
                echo "<p><small>Glow: {$glow}px</small></p>";
                echo "</div>";
            }
        }
        echo "</div>";
        
    } else {
        echo "<div style='color: red; padding: 20px;'>";
        echo "<h3>❌ Error Generating QR Code</h3>";
        echo "<p>{$result['message']}</p>";
        echo "</div>";
    }
    
} catch (Exception $e) {
    echo "<div style='color: red; padding: 20px;'>";
    echo "<h3>❌ Exception Occurred</h3>";
    echo "<p>{$e->getMessage()}</p>";
    echo "</div>";
}

echo "<hr>";
echo "<h3>📋 Test Configuration</h3>";
echo "<pre>" . json_encode($options, JSON_PRETTY_PRINT) . "</pre>";
?>

<style>
body {
    font-family: Arial, sans-serif;
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
    background: #f8f9fa;
}

h1, h3 {
    color: #333;
}

img {
    max-width: 100%;
    height: auto;
}

pre {
    background: #f1f3f4;
    padding: 15px;
    border-radius: 5px;
    overflow-x: auto;
}
</style> 