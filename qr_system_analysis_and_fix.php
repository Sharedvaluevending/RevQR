<?php
echo "🔧 QR CODE SYSTEM ANALYSIS & FIX TOOL\n";
echo "=====================================\n\n";

require_once 'html/core/config.php';

class QRSystemAnalyzer {
    private $pdo;
    private $issues = [];
    private $fixes = [];
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function runFullAnalysis() {
        echo "🔍 Analyzing QR Code System...\n\n";
        
        $this->checkAPIEndpoints();
        $this->checkJavaScriptFiles();
        $this->checkDatabaseSchema();
        $this->testAPIConnectivity();
        $this->generateFixPlan();
    }
    
    private function checkAPIEndpoints() {
        echo "📡 CHECKING QR API ENDPOINTS\n";
        echo "============================\n";
        
        $endpoints = [
            'html/api/qr/generate.php' => 'Basic QR Generator API',
            'html/api/qr/enhanced-generate.php' => 'Enhanced QR Generator API',
            'html/api/qr/unified-generate.php' => 'Unified QR Generator API',
            'html/api/qr/generate_unified.php' => 'New Unified QR Generator API',
            'html/api/qr/preview.php' => 'QR Preview API'
        ];
        
        foreach ($endpoints as $endpoint => $name) {
            if (file_exists($endpoint)) {
                echo "✅ $name: EXISTS\n";
                
                // Check if it has proper authentication
                $content = file_get_contents($endpoint);
                if (strpos($content, 'is_logged_in()') !== false || strpos($content, 'validateAuth()') !== false) {
                    echo "   🔐 Authentication: OK\n";
                } else {
                    echo "   ⚠️  Authentication: MISSING\n";
                    $this->issues[] = [
                        'type' => 'SECURITY',
                        'file' => $endpoint,
                        'issue' => 'Missing authentication check',
                        'severity' => 'HIGH'
                    ];
                }
                
                // Check for database integration
                if (strpos($content, 'INSERT INTO qr_codes') !== false) {
                    echo "   💾 Database Save: OK\n";
                } else {
                    echo "   ⚠️  Database Save: MISSING\n";
                    $this->issues[] = [
                        'type' => 'DATABASE',
                        'file' => $endpoint,
                        'issue' => 'Missing database save functionality',
                        'severity' => 'MEDIUM'
                    ];
                }
                
                // Check for business_id handling
                if (strpos($content, 'business_id') !== false) {
                    echo "   🏢 Business ID: OK\n";
                } else {
                    echo "   ⚠️  Business ID: MISSING\n";
                    $this->issues[] = [
                        'type' => 'BUSINESS_LOGIC',
                        'file' => $endpoint,
                        'issue' => 'Missing business_id handling',
                        'severity' => 'HIGH'
                    ];
                }
                
            } else {
                echo "❌ $name: NOT FOUND\n";
                $this->issues[] = [
                    'type' => 'MISSING_FILE',
                    'file' => $endpoint,
                    'issue' => 'API endpoint file missing',
                    'severity' => 'HIGH'
                ];
            }
            echo "\n";
        }
    }
    
    private function checkJavaScriptFiles() {
        echo "🟨 CHECKING JAVASCRIPT FILES\n";
        echo "============================\n";
        
        $js_files = [
            'html/assets/js/qr-generator.js' => 'Basic QR Generator JS',
            'html/assets/js/qr-generator-v2.js' => 'Advanced QR Generator JS'
        ];
        
        foreach ($js_files as $js_file => $name) {
            if (file_exists($js_file)) {
                echo "✅ $name: EXISTS\n";
                
                $content = file_get_contents($js_file);
                
                // Check which API endpoint it's calling
                if (preg_match('/fetch\([\'"]([^\'"]*/api/qr/[^\'"]*)[\'"]\)/', $content, $matches)) {
                    $api_endpoint = $matches[1];
                    echo "   📞 API Endpoint: $api_endpoint\n";
                    
                    // Check if the endpoint it's calling exists
                    $full_endpoint_path = 'html' . $api_endpoint;
                    if (!file_exists($full_endpoint_path)) {
                        echo "   ❌ Called endpoint does NOT exist!\n";
                        $this->issues[] = [
                            'type' => 'BROKEN_REFERENCE',
                            'file' => $js_file,
                            'issue' => "Calls non-existent API: $api_endpoint",
                            'severity' => 'HIGH'
                        ];
                    } else {
                        echo "   ✅ Called endpoint exists\n";
                    }
                } else {
                    echo "   ⚠️  No API endpoint call found\n";
                    $this->issues[] = [
                        'type' => 'MISSING_API_CALL',
                        'file' => $js_file,
                        'issue' => 'No API endpoint call detected',
                        'severity' => 'MEDIUM'
                    ];
                }
                
                // Check for error handling
                if (strpos($content, 'catch') !== false) {
                    echo "   🛡️  Error Handling: OK\n";
                } else {
                    echo "   ⚠️  Error Handling: MISSING\n";
                }
                
            } else {
                echo "❌ $name: NOT FOUND\n";
                $this->issues[] = [
                    'type' => 'MISSING_FILE',
                    'file' => $js_file,
                    'issue' => 'JavaScript file missing',
                    'severity' => 'HIGH'
                ];
            }
            echo "\n";
        }
    }
    
    private function checkDatabaseSchema() {
        echo "🗄️  CHECKING DATABASE SCHEMA\n";
        echo "=============================\n";
        
        try {
            $stmt = $this->pdo->query("DESCRIBE qr_codes");
            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            $required_columns = [
                'id', 'business_id', 'qr_type', 'url', 'code', 
                'meta', 'created_at', 'status', 'machine_name'
            ];
            
            $missing_columns = array_diff($required_columns, $columns);
            
            if (empty($missing_columns)) {
                echo "✅ QR codes table schema: COMPLETE\n";
            } else {
                echo "⚠️  Missing columns: " . implode(', ', $missing_columns) . "\n";
                $this->issues[] = [
                    'type' => 'DATABASE_SCHEMA',
                    'table' => 'qr_codes',
                    'issue' => 'Missing required columns: ' . implode(', ', $missing_columns),
                    'severity' => 'HIGH'
                ];
            }
            
            // Check for recent QR codes
            $stmt = $this->pdo->query("SELECT COUNT(*) FROM qr_codes WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
            $recent_count = $stmt->fetchColumn();
            
            echo "📊 Recent QR codes (7 days): $recent_count\n";
            
            if ($recent_count == 0) {
                echo "⚠️  No recent QR codes generated - possible generation issue\n";
                $this->issues[] = [
                    'type' => 'DATA_ISSUE',
                    'table' => 'qr_codes',
                    'issue' => 'No QR codes generated in last 7 days',
                    'severity' => 'MEDIUM'
                ];
            }
            
        } catch (Exception $e) {
            echo "❌ Database error: " . $e->getMessage() . "\n";
            $this->issues[] = [
                'type' => 'DATABASE_ERROR',
                'issue' => $e->getMessage(),
                'severity' => 'HIGH'
            ];
        }
        echo "\n";
    }
    
    private function testAPIConnectivity() {
        echo "🔗 TESTING API CONNECTIVITY\n";
        echo "===========================\n";
        
        // Test if we can make a basic request to the APIs
        $apis_to_test = [
            '/api/qr/generate.php' => 'Basic Generator',
            '/api/qr/enhanced-generate.php' => 'Enhanced Generator',
            '/api/qr/unified-generate.php' => 'Unified Generator'
        ];
        
        foreach ($apis_to_test as $endpoint => $name) {
            $full_path = 'html' . $endpoint;
            if (file_exists($full_path)) {
                // We can't actually test HTTP requests from CLI easily, 
                // but we can check basic PHP syntax
                $output = [];
                $return_var = 0;
                exec("php -l " . escapeshellarg($full_path) . " 2>&1", $output, $return_var);
                
                if ($return_var === 0) {
                    echo "✅ $name: PHP SYNTAX OK\n";
                } else {
                    echo "❌ $name: PHP SYNTAX ERROR\n";
                    echo "   " . implode("\n   ", $output) . "\n";
                    $this->issues[] = [
                        'type' => 'SYNTAX_ERROR',
                        'file' => $full_path,
                        'issue' => 'PHP syntax error: ' . implode(' ', $output),
                        'severity' => 'HIGH'
                    ];
                }
            } else {
                echo "❌ $name: FILE NOT FOUND\n";
            }
        }
        echo "\n";
    }
    
    private function generateFixPlan() {
        echo "🔧 QR SYSTEM ANALYSIS REPORT\n";
        echo "=============================\n\n";
        
        $high_priority = array_filter($this->issues, function($issue) {
            return $issue['severity'] === 'HIGH';
        });
        
        $medium_priority = array_filter($this->issues, function($issue) {
            return $issue['severity'] === 'MEDIUM';
        });
        
        echo "🚨 HIGH PRIORITY ISSUES: " . count($high_priority) . "\n";
        foreach ($high_priority as $issue) {
            echo "   • {$issue['type']}: ";
            if (isset($issue['file'])) echo basename($issue['file']) . " - ";
            echo $issue['issue'] . "\n";
        }
        
        echo "\n⚠️  MEDIUM PRIORITY ISSUES: " . count($medium_priority) . "\n";
        foreach ($medium_priority as $issue) {
            echo "   • {$issue['type']}: ";
            if (isset($issue['file'])) echo basename($issue['file']) . " - ";
            echo $issue['issue'] . "\n";
        }
        
        echo "\n💡 RECOMMENDED FIXES:\n";
        echo "====================\n";
        
        echo "1. 🎯 **IMMEDIATE FIXES NEEDED:**\n";
        
        // Check specific issues and provide targeted fixes
        $broken_js_apis = array_filter($this->issues, function($issue) {
            return $issue['type'] === 'BROKEN_REFERENCE';
        });
        
        if (!empty($broken_js_apis)) {
            echo "   ❗ Fix JavaScript API calls:\n";
            foreach ($broken_js_apis as $issue) {
                echo "      - Update " . basename($issue['file']) . " to call working API\n";
            }
        }
        
        $missing_auth = array_filter($this->issues, function($issue) {
            return $issue['type'] === 'SECURITY';
        });
        
        if (!empty($missing_auth)) {
            echo "   🔐 Add authentication to APIs:\n";
            foreach ($missing_auth as $issue) {
                echo "      - Add authentication check to " . basename($issue['file']) . "\n";
            }
        }
        
        echo "\n2. 🔄 **UNIFICATION STRATEGY:**\n";
        echo "   - Use /api/qr/unified-generate.php as the main endpoint\n";
        echo "   - Update all JavaScript files to call the unified API\n";
        echo "   - Deprecate old individual APIs\n";
        
        echo "\n3. 🧪 **TESTING PLAN:**\n";
        echo "   - Test basic QR generation from both generators\n";
        echo "   - Verify database saves are working\n";
        echo "   - Check QR codes appear in QR manager\n";
        
        echo "\n📋 **NEXT STEPS:**\n";
        echo "=================\n";
        echo "1. Run the automatic fix script\n";
        echo "2. Test both QR generators\n";
        echo "3. Verify QR codes are saved to database\n";
        echo "4. Check QR codes display in manager\n";
        
        if (count($this->issues) === 0) {
            echo "\n🎉 NO CRITICAL ISSUES FOUND!\n";
            echo "Your QR system appears to be working correctly.\n";
        } else {
            echo "\n⚡ AUTOMATIC FIX AVAILABLE!\n";
            echo "Run: php qr_automatic_fix.php\n";
        }
    }
}

// Run the analysis
try {
    $analyzer = new QRSystemAnalyzer($pdo);
    $analyzer->runFullAnalysis();
} catch (Exception $e) {
    echo "❌ Analysis Error: " . $e->getMessage() . "\n";
}

echo "\n🎯 Analysis complete! Review the findings above.\n";
?> 