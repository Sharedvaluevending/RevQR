<?php
/**
 * QR Code Logo Functionality Test
 * Tests both QR generators and logo upload/rendering placement
 */

require_once __DIR__ . '/html/core/config.php';
require_once __DIR__ . '/html/includes/QRGenerator.php';

echo "🧪 QR CODE LOGO FUNCTIONALITY TEST\n";
echo "================================\n\n";

class QRLogoTester {
    private $logoPath;
    private $testResults = [];
    
    public function __construct() {
        $this->logoPath = __DIR__ . '/html/assets/img/logos/';
    }
    
    public function runAllTests() {
        echo "Phase 1: Testing Logo Availability...\n";
        $this->testLogoAvailability();
        
        echo "\nPhase 2: Testing Basic QR Generator Logo Integration...\n";
        $this->testBasicGenerator();
        
        echo "\nPhase 3: Testing Logo Placement and Sizing...\n";
        $this->testLogoPlacement();
        
        echo "\nPhase 4: Testing Enhanced Generator Support...\n";
        $this->testEnhancedGenerator();
        
        $this->displaySummary();
    }
    
    private function testLogoAvailability() {
        echo "  📁 Checking logo directory...\n";
        
        if (!is_dir($this->logoPath)) {
            $this->addResult('Logo Directory', 'FAIL', 'Logo directory does not exist');
            return;
        }
        
        $logos = glob($this->logoPath . '*.{png,jpg,jpeg}', GLOB_BRACE);
        echo "    ✅ Logo directory exists\n";
        echo "    📊 Found " . count($logos) . " logo files\n";
        
        if (count($logos) > 0) {
            $this->addResult('Logo Availability', 'PASS', count($logos) . ' logos available');
            foreach ($logos as $logo) {
                $filename = basename($logo);
                $size = $this->formatFileSize(filesize($logo));
                echo "      • $filename ($size)\n";
            }
        } else {
            $this->addResult('Logo Availability', 'WARN', 'No logos found');
        }
    }
    
    private function testBasicGenerator() {
        echo "  🎯 Testing QRGenerator class logo integration...\n";
        
        try {
            $generator = new QRGenerator();
            
            // Test without logo first
            $options = [
                'content' => 'https://example.com/test',
                'type' => 'static',
                'size' => 300,
                'preview' => true
            ];
            
            $result = $generator->generate($options);
            
            if ($result['success']) {
                echo "    ✅ Basic QR generation works\n";
                $this->addResult('Basic QR Generation', 'PASS', 'QR generated successfully');
            } else {
                echo "    ❌ Basic QR generation failed\n";
                $this->addResult('Basic QR Generation', 'FAIL', 'Generation failed');
                return;
            }
            
            // Test with logo
            $logos = glob($this->logoPath . '*.{png,jpg,jpeg}', GLOB_BRACE);
            if (count($logos) > 0) {
                $testLogo = basename($logos[0]);
                $options['logo'] = $testLogo;
                
                echo "    🎨 Testing with logo: $testLogo\n";
                $resultWithLogo = $generator->generate($options);
                
                if ($resultWithLogo['success']) {
                    echo "    ✅ QR generation with logo works\n";
                    $this->addResult('QR with Logo', 'PASS', 'Logo integration successful');
                } else {
                    echo "    ❌ QR generation with logo failed\n";
                    $this->addResult('QR with Logo', 'FAIL', 'Logo integration failed');
                }
            } else {
                echo "    ⚠️  No logos available to test with\n";
                $this->addResult('QR with Logo', 'SKIP', 'No logos available');
            }
            
        } catch (Exception $e) {
            echo "    ❌ QRGenerator test failed: " . $e->getMessage() . "\n";
            $this->addResult('Basic Generator Test', 'FAIL', $e->getMessage());
        }
    }
    
    private function testLogoPlacement() {
        echo "  📐 Testing logo placement and sizing...\n";
        
        try {
            $generator = new QRGenerator();
            $logos = glob($this->logoPath . '*.{png,jpg,jpeg}', GLOB_BRACE);
            
            if (count($logos) === 0) {
                echo "    ⚠️  No logos available for placement testing\n";
                $this->addResult('Logo Placement', 'SKIP', 'No logos available');
                return;
            }
            
            $testLogo = basename($logos[0]);
            $sizes = [200, 300, 500];
            $successCount = 0;
            
            foreach ($sizes as $size) {
                echo "    🔍 Testing size: {$size}px\n";
                
                $options = [
                    'content' => 'https://example.com/test-' . $size,
                    'type' => 'static',
                    'size' => $size,
                    'logo' => $testLogo,
                    'preview' => true
                ];
                
                $result = $generator->generate($options);
                
                if ($result['success']) {
                    echo "      ✅ Size {$size}px works\n";
                    $successCount++;
                } else {
                    echo "      ❌ Size {$size}px failed\n";
                }
            }
            
            if ($successCount === count($sizes)) {
                $this->addResult('Logo Sizing', 'PASS', 'All sizes work correctly');
            } else {
                $this->addResult('Logo Sizing', 'WARN', "$successCount/" . count($sizes) . " sizes work");
            }
            
        } catch (Exception $e) {
            echo "    ❌ Logo placement test failed: " . $e->getMessage() . "\n";
            $this->addResult('Logo Placement', 'FAIL', $e->getMessage());
        }
    }
    
    private function testEnhancedGenerator() {
        echo "  🎨 Testing enhanced generator logo support...\n";
        
        $enhancedPath = __DIR__ . '/html/qr-generator-enhanced.php';
        $basicPath = __DIR__ . '/html/qr-generator.php';
        
        if (file_exists($enhancedPath)) {
            echo "    ✅ Enhanced generator exists\n";
            $content = file_get_contents($enhancedPath);
            
            if (strpos($content, 'logoUpload') !== false) {
                echo "    ✅ Logo upload field found in enhanced generator\n";
                $this->addResult('Enhanced Logo Upload', 'PASS', 'Upload field present');
            } else {
                echo "    ❌ Logo upload field missing in enhanced generator\n";
                $this->addResult('Enhanced Logo Upload', 'FAIL', 'Upload field missing');
            }
        } else {
            echo "    ❌ Enhanced generator not found\n";
            $this->addResult('Enhanced Generator', 'FAIL', 'File not found');
        }
        
        if (file_exists($basicPath)) {
            echo "    ✅ Basic generator exists\n";
            $content = file_get_contents($basicPath);
            
            if (strpos($content, 'logoUpload') !== false) {
                echo "    ✅ Logo upload field found in basic generator\n";
                $this->addResult('Basic Logo Upload', 'PASS', 'Upload field present');
            } else {
                echo "    ❌ Logo upload field missing in basic generator\n";
                $this->addResult('Basic Logo Upload', 'FAIL', 'Upload field missing');
            }
        } else {
            echo "    ❌ Basic generator not found\n";
            $this->addResult('Basic Generator', 'FAIL', 'File not found');
        }
    }
    
    private function addResult($test, $status, $message) {
        $this->testResults[] = [
            'test' => $test,
            'status' => $status,
            'message' => $message
        ];
    }
    
    private function displaySummary() {
        echo "\n📋 TEST SUMMARY\n";
        echo "==============\n\n";
        
        $passed = 0;
        $failed = 0;
        $warnings = 0;
        $skipped = 0;
        
        foreach ($this->testResults as $result) {
            $icon = $this->getStatusIcon($result['status']);
            echo sprintf("  %s %s: %s\n", $icon, $result['test'], $result['message']);
            
            switch ($result['status']) {
                case 'PASS': $passed++; break;
                case 'FAIL': $failed++; break;
                case 'WARN': $warnings++; break;
                case 'SKIP': $skipped++; break;
            }
        }
        
        echo "\n📊 Results:\n";
        echo "  ✅ Passed: $passed\n";
        echo "  ❌ Failed: $failed\n";
        echo "  ⚠️  Warnings: $warnings\n";
        echo "  ⏭️  Skipped: $skipped\n\n";
        
        if ($failed === 0) {
            echo "🎉 All critical tests passed! Logo functionality is working.\n";
        } else {
            echo "🔧 Some tests failed. Logo functionality needs attention.\n";
        }
    }
    
    private function getStatusIcon($status) {
        switch ($status) {
            case 'PASS': return '✅';
            case 'FAIL': return '❌';
            case 'WARN': return '⚠️ ';
            case 'SKIP': return '⏭️ ';
            default: return '❓';
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

// Run the tests
$tester = new QRLogoTester();
$tester->runAllTests();
?> 