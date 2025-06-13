<?php
echo "🔧 QR CODE SYSTEM ANALYSIS & FIX TOOL\n";
echo "=====================================\n\n";

require_once 'html/core/config.php';

class QRSystemAnalyzer {
    private $pdo;
    private $issues = [];
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function runFullAnalysis() {
        echo "🔍 Analyzing QR Code System...\n\n";
        
        $this->checkAPIEndpoints();
        $this->checkJavaScriptFiles();
        $this->checkDatabaseSchema();
        $this->generateFixPlan();
    }
    
    private function checkAPIEndpoints() {
        echo "📡 CHECKING QR API ENDPOINTS\n";
        echo "============================\n";
        
        $endpoints = [
            'html/api/qr/generate.php' => 'Basic QR Generator API',
            'html/api/qr/enhanced-generate.php' => 'Enhanced QR Generator API',
            'html/api/qr/unified-generate.php' => 'Unified QR Generator API',
            'html/api/qr/preview.php' => 'QR Preview API'
        ];
        
        foreach ($endpoints as $endpoint => $name) {
            if (file_exists($endpoint)) {
                echo "✅ $name: EXISTS\n";
                
                $content = file_get_contents($endpoint);
                
                // Check authentication
                if (strpos($content, 'is_logged_in()') !== false) {
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
                
                // Check database integration
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
                
                // Check business_id handling
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
                    
                    // Check if the endpoint exists
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
                'meta', 'created_at', 'status'
            ];
            
            $missing_columns = array_diff($required_columns, $columns);
            
            if (empty($missing_columns)) {
                echo "✅ QR codes table schema: COMPLETE\n";
            } else {
                echo "⚠️  Missing columns: " . implode(', ', $missing_columns) . "\n";
            }
            
            // Check for recent QR codes
            $stmt = $this->pdo->query("SELECT COUNT(*) FROM qr_codes WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
            $recent_count = $stmt->fetchColumn();
            
            echo "📊 Recent QR codes (7 days): $recent_count\n";
            
        } catch (Exception $e) {
            echo "❌ Database error: " . $e->getMessage() . "\n";
        }
        echo "\n";
    }
    
    private function generateFixPlan() {
        echo "🔧 ANALYSIS RESULTS\n";
        echo "==================\n\n";
        
        $high_priority = array_filter($this->issues, function($issue) {
            return $issue['severity'] === 'HIGH';
        });
        
        echo "🚨 HIGH PRIORITY ISSUES: " . count($high_priority) . "\n";
        foreach ($high_priority as $issue) {
            echo "   • " . basename($issue['file']) . ": " . $issue['issue'] . "\n";
        }
        
        echo "\n💡 RECOMMENDED FIXES:\n";
        echo "====================\n";
        echo "1. Update JavaScript files to use working API endpoints\n";
        echo "2. Ensure all APIs have proper authentication\n";
        echo "3. Use unified API endpoint for consistency\n";
        
        if (count($this->issues) === 0) {
            echo "\n🎉 NO CRITICAL ISSUES FOUND!\n";
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

echo "\n🎯 Analysis complete!\n";
?> 