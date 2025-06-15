<?php
/**
 * Visual Logo Rendering Test
 * Creates actual QR codes with logos to verify placement and rendering
 */

require_once __DIR__ . '/html/core/config.php';
require_once __DIR__ . '/html/includes/QRGenerator.php';

echo "🎨 VISUAL LOGO RENDERING TEST\n";
echo "============================\n\n";

class VisualLogoTest {
    private $logoPath;
    private $outputPath;
    
    public function __construct() {
        $this->logoPath = __DIR__ . '/html/assets/img/logos/';
        $this->outputPath = __DIR__ . '/html/uploads/qr/test_renders/';
        
        // Create test output directory
        if (!file_exists($this->outputPath)) {
            mkdir($this->outputPath, 0755, true);
        }
    }
    
    public function runVisualTests() {
        echo "🔍 Creating visual QR codes with logos...\n\n";
        
        $generator = new QRGenerator();
        $logos = glob($this->logoPath . '*.{png,jpg,jpeg}', GLOB_BRACE);
        
        if (count($logos) === 0) {
            echo "❌ No logos found for testing\n";
            return;
        }
        
        $testConfigurations = [
            [
                'name' => 'Small QR (200px)',
                'size' => 200,
                'content' => 'https://example.com/small-qr-test'
            ],
            [
                'name' => 'Medium QR (300px)',
                'size' => 300,
                'content' => 'https://example.com/medium-qr-test'
            ],
            [
                'name' => 'Large QR (500px)',
                'size' => 500,
                'content' => 'https://example.com/large-qr-test'
            ]
        ];
        
        $testCount = 0;
        $successCount = 0;
        
        foreach ($testConfigurations as $config) {
            echo "📋 Testing: {$config['name']}\n";
            
            foreach (array_slice($logos, 0, 2) as $logoFile) { // Test with first 2 logos
                $logoName = basename($logoFile);
                $testCount++;
                
                echo "  🎯 Logo: $logoName\n";
                
                $options = [
                    'content' => $config['content'] . '?logo=' . urlencode($logoName),
                    'type' => 'static',
                    'size' => $config['size'],
                    'logo' => $logoName,
                    'preview' => false // Generate actual file
                ];
                
                try {
                    $result = $generator->generate($options);
                    
                    if ($result['success']) {
                        $sourceFile = __DIR__ . '/html' . $result['data']['qr_code_url'];
                        $testFileName = 'visual_test_' . $config['size'] . 'px_' . pathinfo($logoName, PATHINFO_FILENAME) . '.png';
                        $testFilePath = $this->outputPath . $testFileName;
                        
                        // Copy to test directory for easier viewing
                        if (file_exists($sourceFile)) {
                            copy($sourceFile, $testFilePath);
                            echo "    ✅ Generated: $testFileName\n";
                            $successCount++;
                            
                            // Get file info
                            $fileSize = $this->formatFileSize(filesize($testFilePath));
                            echo "    📊 File size: $fileSize\n";
                            
                            // Check if file is readable as image
                            $imageInfo = getimagesize($testFilePath);
                            if ($imageInfo) {
                                echo "    📐 Dimensions: {$imageInfo[0]}x{$imageInfo[1]}\n";
                            }
                        } else {
                            echo "    ❌ Failed to copy generated file\n";
                        }
                    } else {
                        echo "    ❌ Generation failed: " . ($result['message'] ?? 'Unknown error') . "\n";
                    }
                } catch (Exception $e) {
                    echo "    ❌ Exception: " . $e->getMessage() . "\n";
                }
                
                echo "\n";
            }
        }
        
        echo "📊 VISUAL TEST SUMMARY\n";
        echo "=====================\n";
        echo "Total tests: $testCount\n";
        echo "Successful: $successCount\n";
        echo "Failed: " . ($testCount - $successCount) . "\n\n";
        
        if ($successCount > 0) {
            echo "✅ Visual test files created in: {$this->outputPath}\n";
            echo "🔍 Review these files to verify logo placement and quality:\n\n";
            
            $testFiles = glob($this->outputPath . '*.png');
            foreach ($testFiles as $file) {
                $fileName = basename($file);
                $fileSize = $this->formatFileSize(filesize($file));
                echo "  • $fileName ($fileSize)\n";
            }
            
            echo "\n💡 Manual verification checklist:\n";
            echo "  ✓ Logo is centered in QR code\n";
            echo "  ✓ Logo doesn't obstruct QR readability\n";
            echo "  ✓ Logo scales appropriately with QR size\n";
            echo "  ✓ QR codes can be scanned successfully\n";
            echo "  ✓ Visual quality is acceptable\n";
        }
        
        // Test logo upload UI components
        $this->testLogoUploadComponents();
    }
    
    private function testLogoUploadComponents() {
        echo "\n🖥️  TESTING LOGO UPLOAD UI COMPONENTS\n";
        echo "====================================\n";
        
        $generators = [
            'Basic Generator' => __DIR__ . '/html/qr-generator.php',
            'Enhanced Generator' => __DIR__ . '/html/qr-generator-enhanced.php'
        ];
        
        foreach ($generators as $name => $path) {
            echo "🔍 Checking $name...\n";
            
            if (!file_exists($path)) {
                echo "  ❌ File not found: $path\n";
                continue;
            }
            
            $content = file_get_contents($path);
            
            // Check for logo upload input
            if (preg_match('/input[^>]*id=["\']logoUpload["\']/', $content)) {
                echo "  ✅ Logo upload input found\n";
            } else {
                echo "  ❌ Logo upload input missing\n";
            }
            
            // Check for logo preview
            if (strpos($content, 'logoPreview') !== false) {
                echo "  ✅ Logo preview element found\n";
            } else {
                echo "  ⚠️  Logo preview element not found\n";
            }
            
            // Check for logo upload handling JavaScript
            if (preg_match('/logoUpload.*addEventListener|on.*logoUpload/', $content)) {
                echo "  ✅ Logo upload JavaScript found\n";
            } else {
                echo "  ⚠️  Logo upload JavaScript not found\n";
            }
            
            // Check for logo API calls
            if (strpos($content, '/api/qr/logo') !== false) {
                echo "  ✅ Logo API integration found\n";
            } else {
                echo "  ⚠️  Logo API integration not found\n";
            }
            
            echo "\n";
        }
    }
    
    private function formatFileSize($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes > 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
}

// Run visual tests
$visualTest = new VisualLogoTest();
$visualTest->runVisualTests();
?> 