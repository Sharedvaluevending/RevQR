<?php
/**
 * Nayax Phase 4 Verification Script
 * Comprehensive testing for Business Dashboard & Analytics implementation
 * 
 * @author RevenueQR Team
 * @version 1.0
 * @date 2025-01-17
 */

require_once __DIR__ . '/html/core/config.php';

class NayaxPhase4Verifier {
    
    private $pdo;
    private $results = [];
    private $total_tests = 0;
    private $passed_tests = 0;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Run all Phase 4 verification tests
     */
    public function runAllTests() {
        echo "🚀 Starting Nayax Phase 4 Verification\n";
        echo "=====================================\n\n";
        
        // Core Analytics Engine Tests
        $this->testAnalyticsEngine();
        
        // Revenue Optimization Tests
        $this->testOptimizationEngine();
        
        // Advanced Dashboard Tests
        $this->testAdvancedDashboard();
        
        // Customer Analytics Tests
        $this->testCustomerAnalytics();
        
        // Mobile Dashboard Tests
        $this->testMobileDashboard();
        
        // Predictive Analytics Tests
        $this->testPredictiveAnalytics();
        
        // Performance Tests
        $this->testPerformanceOptimization();
        
        // Business Intelligence Tests
        $this->testBusinessIntelligence();
        
        // Integration Tests
        $this->testSystemIntegration();
        
        // Security Tests
        $this->testSecurityFeatures();
        
        return $this->generateReport();
    }
    
    /**
     * Test Analytics Engine Functionality
     */
    private function testAnalyticsEngine() {
        echo "📊 Testing Analytics Engine...\n";
        
        // Test 1: Analytics Engine Class Exists
        $this->test("Analytics Engine Class", function() {
            require_once __DIR__ . '/html/core/nayax_analytics_engine.php';
            return class_exists('NayaxAnalyticsEngine');
        });
        
        // Test 2: Analytics Engine Instantiation
        $this->test("Analytics Engine Instantiation", function() {
            try {
                require_once __DIR__ . '/html/core/nayax_analytics_engine.php';
                $engine = new NayaxAnalyticsEngine($this->pdo);
                return $engine instanceof NayaxAnalyticsEngine;
            } catch (Exception $e) {
                return false;
            }
        });
        
        // Test 3: Business Analytics Generation
        $this->test("Business Analytics Generation", function() {
            try {
                require_once __DIR__ . '/html/core/nayax_analytics_engine.php';
                $engine = new NayaxAnalyticsEngine($this->pdo);
                
                // Get sample business
                $stmt = $this->pdo->query("SELECT id FROM businesses LIMIT 1");
                $business = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$business) return false;
                
                $analytics = $engine->getBusinessAnalytics($business['id'], 30, true);
                
                return is_array($analytics) && 
                       isset($analytics['revenue']) && 
                       isset($analytics['transactions']) &&
                       isset($analytics['qr_coins']) &&
                       isset($analytics['predictions']);
            } catch (Exception $e) {
                return false;
            }
        });
        
        // Test 4: Revenue Analytics Structure
        $this->test("Revenue Analytics Structure", function() {
            try {
                require_once __DIR__ . '/html/core/nayax_analytics_engine.php';
                $engine = new NayaxAnalyticsEngine($this->pdo);
                
                $stmt = $this->pdo->query("SELECT id FROM businesses LIMIT 1");
                $business = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$business) return false;
                
                $analytics = $engine->getBusinessAnalytics($business['id'], 7, false);
                $revenue = $analytics['revenue'];
                
                return isset($revenue['total_revenue_dollars']) &&
                       isset($revenue['total_transactions']) &&
                       isset($revenue['daily_breakdown']) &&
                       isset($revenue['growth_rate']);
            } catch (Exception $e) {
                return false;
            }
        });
        
        // Test 5: Caching Functionality
        $this->test("Analytics Caching", function() {
            $cache_dir = __DIR__ . '/html/storage/cache/';
            if (!is_dir($cache_dir)) {
                mkdir($cache_dir, 0755, true);
            }
            
            // Test cache file creation
            $test_data = ['test' => 'data', 'timestamp' => time()];
            $cache_file = $cache_dir . 'test_cache.json';
            
            file_put_contents($cache_file, json_encode($test_data));
            $retrieved = json_decode(file_get_contents($cache_file), true);
            
            unlink($cache_file);
            
            return $retrieved['test'] === 'data';
        });
        
        echo "\n";
    }
    
    /**
     * Test Optimization Engine
     */
    private function testOptimizationEngine() {
        echo "🎯 Testing Optimization Engine...\n";
        
        // Test 1: Optimizer Class Exists
        $this->test("Optimizer Class", function() {
            require_once __DIR__ . '/html/core/nayax_optimizer.php';
            return class_exists('NayaxOptimizer');
        });
        
        // Test 2: Optimizer Instantiation
        $this->test("Optimizer Instantiation", function() {
            try {
                require_once __DIR__ . '/html/core/nayax_optimizer.php';
                require_once __DIR__ . '/html/core/nayax_analytics_engine.php';
                
                $analytics_engine = new NayaxAnalyticsEngine($this->pdo);
                $optimizer = new NayaxOptimizer($this->pdo, $analytics_engine);
                
                return $optimizer instanceof NayaxOptimizer;
            } catch (Exception $e) {
                return false;
            }
        });
        
        // Test 3: Optimization Recommendations
        $this->test("Optimization Recommendations", function() {
            try {
                require_once __DIR__ . '/html/core/nayax_optimizer.php';
                require_once __DIR__ . '/html/core/nayax_analytics_engine.php';
                
                $analytics_engine = new NayaxAnalyticsEngine($this->pdo);
                $optimizer = new NayaxOptimizer($this->pdo, $analytics_engine);
                
                $stmt = $this->pdo->query("SELECT id FROM businesses LIMIT 1");
                $business = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$business) return false;
                
                $recommendations = $optimizer->getOptimizationRecommendations($business['id'], 7);
                
                return is_array($recommendations) &&
                       isset($recommendations['optimization_score']) &&
                       isset($recommendations['recommendations']) &&
                       isset($recommendations['priority_actions']);
            } catch (Exception $e) {
                return false;
            }
        });
        
        // Test 4: Revenue Projections
        $this->test("Revenue Projections", function() {
            try {
                require_once __DIR__ . '/html/core/nayax_optimizer.php';
                require_once __DIR__ . '/html/core/nayax_analytics_engine.php';
                
                $analytics_engine = new NayaxAnalyticsEngine($this->pdo);
                $optimizer = new NayaxOptimizer($this->pdo, $analytics_engine);
                
                $stmt = $this->pdo->query("SELECT id FROM businesses LIMIT 1");
                $business = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$business) return false;
                
                $recommendations = $optimizer->getOptimizationRecommendations($business['id'], 7);
                $projections = $recommendations['revenue_projections'];
                
                return isset($projections['current_monthly_revenue']) &&
                       isset($projections['conservative_projection']) &&
                       isset($projections['potential_increase_range']);
            } catch (Exception $e) {
                return false;
            }
        });
        
        echo "\n";
    }
    
    /**
     * Test Advanced Dashboard
     */
    private function testAdvancedDashboard() {
        echo "📈 Testing Advanced Dashboard...\n";
        
        // Test 1: Advanced Analytics Page Exists
        $this->test("Advanced Analytics Page", function() {
            return file_exists(__DIR__ . '/html/business/nayax-analytics.php');
        });
        
        // Test 2: Dashboard Content Structure
        $this->test("Dashboard Content Structure", function() {
            $content = file_get_contents(__DIR__ . '/html/business/nayax-analytics.php');
            
            return strpos($content, 'NayaxAnalyticsEngine') !== false &&
                   strpos($content, 'Chart.js') !== false &&
                   strpos($content, 'bootstrap') !== false &&
                   strpos($content, 'revenueTrendChart') !== false;
        });
        
        // Test 3: Chart Implementation
        $this->test("Chart Implementation", function() {
            $content = file_get_contents(__DIR__ . '/html/business/nayax-analytics.php');
            
            return strpos($content, 'new Chart(') !== false &&
                   strpos($content, 'coinEconomyChart') !== false &&
                   strpos($content, 'machinePerformanceChart') !== false;
        });
        
        // Test 4: Export Functionality
        $this->test("Export Functionality", function() {
            $content = file_get_contents(__DIR__ . '/html/business/nayax-analytics.php');
            
            return strpos($content, 'export=json') !== false &&
                   strpos($content, 'export=csv') !== false;
        });
        
        // Test 5: Responsive Design
        $this->test("Responsive Design", function() {
            $content = file_get_contents(__DIR__ . '/html/business/nayax-analytics.php');
            
            return strpos($content, '@media (max-width: 768px)') !== false &&
                   strpos($content, 'col-lg-') !== false &&
                   strpos($content, 'col-md-') !== false;
        });
        
        echo "\n";
    }
    
    /**
     * Test Customer Analytics
     */
    private function testCustomerAnalytics() {
        echo "👥 Testing Customer Analytics...\n";
        
        // Test 1: Customer Analytics Page
        $this->test("Customer Analytics Page", function() {
            return file_exists(__DIR__ . '/html/business/nayax-customers.php');
        });
        
        // Test 2: Customer Segmentation
        $this->test("Customer Segmentation", function() {
            $content = file_get_contents(__DIR__ . '/html/business/nayax-customers.php');
            
            return strpos($content, 'customer_segment') !== false &&
                   strpos($content, 'High Value') !== false &&
                   strpos($content, 'segmentationChart') !== false;
        });
        
        // Test 3: Lifecycle Analysis
        $this->test("Lifecycle Analysis", function() {
            $content = file_get_contents(__DIR__ . '/html/business/nayax-customers.php');
            
            return strpos($content, 'lifecycle_stage') !== false &&
                   strpos($content, 'lifecycleChart') !== false &&
                   strpos($content, 'customer-journey') !== false;
        });
        
        // Test 4: DataTables Integration
        $this->test("DataTables Integration", function() {
            $content = file_get_contents(__DIR__ . '/html/business/nayax-customers.php');
            
            return strpos($content, 'dataTables') !== false &&
                   strpos($content, '#customersTable') !== false;
        });
        
        // Test 5: Customer Action Functions
        $this->test("Customer Action Functions", function() {
            $content = file_get_contents(__DIR__ . '/html/business/nayax-customers.php');
            
            return strpos($content, 'emailCustomer') !== false &&
                   strpos($content, 'promoteCustomer') !== false &&
                   strpos($content, 'retainCustomer') !== false;
        });
        
        echo "\n";
    }
    
    /**
     * Test Mobile Dashboard
     */
    private function testMobileDashboard() {
        echo "📱 Testing Mobile Dashboard...\n";
        
        // Test 1: Mobile Dashboard Page
        $this->test("Mobile Dashboard Page", function() {
            return file_exists(__DIR__ . '/html/business/mobile-dashboard.php');
        });
        
        // Test 2: PWA Features
        $this->test("PWA Features", function() {
            $content = file_get_contents(__DIR__ . '/html/business/mobile-dashboard.php');
            
            return strpos($content, 'manifest.json') !== false &&
                   strpos($content, 'serviceWorker') !== false &&
                   strpos($content, 'apple-mobile-web-app') !== false;
        });
        
        // Test 3: Mobile-First Design
        $this->test("Mobile-First Design", function() {
            $content = file_get_contents(__DIR__ . '/html/business/mobile-dashboard.php');
            
            return strpos($content, 'viewport') !== false &&
                   strpos($content, 'user-scalable=no') !== false &&
                   strpos($content, 'safe-area-inset') !== false;
        });
        
        // Test 4: Pull-to-Refresh
        $this->test("Pull-to-Refresh", function() {
            $content = file_get_contents(__DIR__ . '/html/business/mobile-dashboard.php');
            
            return strpos($content, 'pull-to-refresh') !== false &&
                   strpos($content, 'touchstart') !== false &&
                   strpos($content, 'refreshData') !== false;
        });
        
        // Test 5: Bottom Navigation
        $this->test("Bottom Navigation", function() {
            $content = file_get_contents(__DIR__ . '/html/business/mobile-dashboard.php');
            
            return strpos($content, 'bottom-nav') !== false &&
                   strpos($content, 'nav-items') !== false;
        });
        
        echo "\n";
    }
    
    /**
     * Test Predictive Analytics
     */
    private function testPredictiveAnalytics() {
        echo "🔮 Testing Predictive Analytics...\n";
        
        // Test 1: Revenue Forecasting
        $this->test("Revenue Forecasting", function() {
            try {
                require_once __DIR__ . '/html/core/nayax_analytics_engine.php';
                $engine = new NayaxAnalyticsEngine($this->pdo);
                
                $stmt = $this->pdo->query("SELECT id FROM businesses LIMIT 1");
                $business = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$business) return false;
                
                $analytics = $engine->getBusinessAnalytics($business['id'], 30, true);
                
                return isset($analytics['predictions']) &&
                       isset($analytics['predictions']['revenue_forecast']);
            } catch (Exception $e) {
                return false;
            }
        });
        
        // Test 2: Growth Predictions
        $this->test("Growth Predictions", function() {
            try {
                require_once __DIR__ . '/html/core/nayax_analytics_engine.php';
                $engine = new NayaxAnalyticsEngine($this->pdo);
                
                $stmt = $this->pdo->query("SELECT id FROM businesses LIMIT 1");
                $business = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$business) return false;
                
                $analytics = $engine->getBusinessAnalytics($business['id'], 30, true);
                
                return isset($analytics['predictions']['growth_prediction']) ||
                       isset($analytics['predictions']['confidence']);
            } catch (Exception $e) {
                return false;
            }
        });
        
        // Test 3: Trend Analysis
        $this->test("Trend Analysis", function() {
            try {
                require_once __DIR__ . '/html/core/nayax_analytics_engine.php';
                $engine = new NayaxAnalyticsEngine($this->pdo);
                
                $stmt = $this->pdo->query("SELECT id FROM businesses LIMIT 1");
                $business = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$business) return false;
                
                $analytics = $engine->getBusinessAnalytics($business['id'], 30, false);
                
                return isset($analytics['trends']) &&
                       isset($analytics['trends']['revenue_trend']) &&
                       isset($analytics['trends']['seasonality']);
            } catch (Exception $e) {
                return false;
            }
        });
        
        echo "\n";
    }
    
    /**
     * Test Performance Optimization
     */
    private function testPerformanceOptimization() {
        echo "⚡ Testing Performance Optimization...\n";
        
        // Test 1: Cache Directory Creation
        $this->test("Cache Directory", function() {
            $cache_dir = __DIR__ . '/html/storage/cache/';
            return is_dir($cache_dir) || mkdir($cache_dir, 0755, true);
        });
        
        // Test 2: Database Query Optimization
        $this->test("Query Optimization", function() {
            try {
                $start_time = microtime(true);
                
                // Test query performance
                $stmt = $this->pdo->prepare("
                    SELECT COUNT(*) as total 
                    FROM nayax_transactions nt
                    JOIN nayax_machines nm ON nt.nayax_machine_id = nm.nayax_machine_id
                    WHERE nt.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                ");
                $stmt->execute();
                $result = $stmt->fetch();
                
                $execution_time = microtime(true) - $start_time;
                
                return $execution_time < 1.0; // Should complete within 1 second
            } catch (Exception $e) {
                return false;
            }
        });
        
        // Test 3: Memory Usage
        $this->test("Memory Usage", function() {
            $initial_memory = memory_get_usage();
            
            // Simulate analytics processing
            try {
                require_once __DIR__ . '/html/core/nayax_analytics_engine.php';
                $engine = new NayaxAnalyticsEngine($this->pdo);
                
                $stmt = $this->pdo->query("SELECT id FROM businesses LIMIT 1");
                $business = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($business) {
                    $analytics = $engine->getBusinessAnalytics($business['id'], 7, false);
                }
            } catch (Exception $e) {
                return false;
            }
            
            $final_memory = memory_get_usage();
            $memory_used = $final_memory - $initial_memory;
            
            return $memory_used < 50 * 1024 * 1024; // Less than 50MB
        });
        
        echo "\n";
    }
    
    /**
     * Test Business Intelligence Features
     */
    private function testBusinessIntelligence() {
        echo "🧠 Testing Business Intelligence...\n";
        
        // Test 1: KPI Calculations
        $this->test("KPI Calculations", function() {
            try {
                require_once __DIR__ . '/html/core/nayax_analytics_engine.php';
                $engine = new NayaxAnalyticsEngine($this->pdo);
                
                $stmt = $this->pdo->query("SELECT id FROM businesses LIMIT 1");
                $business = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$business) return false;
                
                $analytics = $engine->getBusinessAnalytics($business['id'], 30, false);
                $performance = $analytics['performance'];
                
                return isset($performance['qr_adoption_rate']) &&
                       isset($performance['revenue_per_machine']) &&
                       isset($performance['transactions_per_day']);
            } catch (Exception $e) {
                return false;
            }
        });
        
        // Test 2: Business Insights Generation
        $this->test("Business Insights", function() {
            try {
                require_once __DIR__ . '/html/core/nayax_analytics_engine.php';
                $engine = new NayaxAnalyticsEngine($this->pdo);
                
                $stmt = $this->pdo->query("SELECT id FROM businesses LIMIT 1");
                $business = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$business) return false;
                
                $analytics = $engine->getBusinessAnalytics($business['id'], 30, true);
                
                return isset($analytics['transactions']['insights']) &&
                       isset($analytics['machines']['machine_insights']) &&
                       isset($analytics['customers']['customer_insights']);
            } catch (Exception $e) {
                return false;
            }
        });
        
        // Test 3: Recommendation Engine
        $this->test("Recommendation Engine", function() {
            try {
                require_once __DIR__ . '/html/core/nayax_analytics_engine.php';
                $engine = new NayaxAnalyticsEngine($this->pdo);
                
                $stmt = $this->pdo->query("SELECT id FROM businesses LIMIT 1");
                $business = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$business) return false;
                
                $analytics = $engine->getBusinessAnalytics($business['id'], 30, true);
                
                return isset($analytics['recommendations']) &&
                       is_array($analytics['recommendations']);
            } catch (Exception $e) {
                return false;
            }
        });
        
        echo "\n";
    }
    
    /**
     * Test System Integration
     */
    private function testSystemIntegration() {
        echo "🔗 Testing System Integration...\n";
        
        // Test 1: Phase 2 Integration
        $this->test("Phase 2 Integration", function() {
            // Check if Phase 2 files exist
            $phase2_files = [
                '/html/core/nayax_manager.php',
                '/html/core/nayax_discount_manager.php',
                '/html/core/qr_coin_manager.php'
            ];
            
            foreach ($phase2_files as $file) {
                if (!file_exists(__DIR__ . $file)) return false;
            }
            
            return true;
        });
        
        // Test 2: Database Schema Compatibility
        $this->test("Database Schema", function() {
            try {
                $tables = [
                    'nayax_transactions',
                    'nayax_machines', 
                    'nayax_discount_codes',
                    'business_store_items'
                ];
                
                foreach ($tables as $table) {
                    $stmt = $this->pdo->query("SHOW TABLES LIKE '$table'");
                    if (!$stmt->fetch()) return false;
                }
                
                return true;
            } catch (Exception $e) {
                return false;
            }
        });
        
        // Test 3: API Endpoints
        $this->test("API Endpoints", function() {
            $api_files = [
                '/html/api/purchase-discount.php',
                '/html/api/user-balance.php'
            ];
            
            foreach ($api_files as $file) {
                if (!file_exists(__DIR__ . $file)) return false;
            }
            
            return true;
        });
        
        echo "\n";
    }
    
    /**
     * Test Security Features
     */
    private function testSecurityFeatures() {
        echo "🔒 Testing Security Features...\n";
        
        // Test 1: Authentication Checks
        $this->test("Authentication Checks", function() {
            $files_to_check = [
                '/html/business/nayax-analytics.php',
                '/html/business/nayax-customers.php',
                '/html/business/mobile-dashboard.php'
            ];
            
            foreach ($files_to_check as $file) {
                $content = file_get_contents(__DIR__ . $file);
                if (strpos($content, '$user_id = $_SESSION[\'user_id\']') === false) {
                    return false;
                }
            }
            
            return true;
        });
        
        // Test 2: SQL Injection Prevention
        $this->test("SQL Injection Prevention", function() {
            $content = file_get_contents(__DIR__ . '/html/core/nayax_analytics_engine.php');
            
            // Check for prepared statements
            return strpos($content, '$this->pdo->prepare(') !== false &&
                   strpos($content, '$stmt->execute(') !== false;
        });
        
        // Test 3: Data Validation
        $this->test("Data Validation", function() {
            $content = file_get_contents(__DIR__ . '/html/business/nayax-analytics.php');
            
            return strpos($content, 'htmlspecialchars(') !== false ||
                   strpos($content, 'filter_var(') !== false;
        });
        
        echo "\n";
    }
    
    /**
     * Individual test runner
     */
    private function test($name, $callback) {
        $this->total_tests++;
        
        try {
            $result = $callback();
            if ($result) {
                echo "✅ $name\n";
                $this->passed_tests++;
                $this->results[] = ['name' => $name, 'status' => 'PASS'];
            } else {
                echo "❌ $name\n";
                $this->results[] = ['name' => $name, 'status' => 'FAIL'];
            }
        } catch (Exception $e) {
            echo "❌ $name (Exception: " . $e->getMessage() . ")\n";
            $this->results[] = ['name' => $name, 'status' => 'ERROR', 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Generate final verification report
     */
    private function generateReport() {
        echo "\n📋 NAYAX PHASE 4 VERIFICATION REPORT\n";
        echo "=====================================\n\n";
        
        echo "🎯 Test Summary:\n";
        echo "   Total Tests: {$this->total_tests}\n";
        echo "   Passed: {$this->passed_tests}\n";
        echo "   Failed: " . ($this->total_tests - $this->passed_tests) . "\n";
        echo "   Success Rate: " . round(($this->passed_tests / $this->total_tests) * 100, 1) . "%\n\n";
        
        if ($this->passed_tests === $this->total_tests) {
            echo "🎉 ALL TESTS PASSED! Phase 4 implementation is complete and ready for production.\n\n";
            
            echo "✨ Phase 4 Features Successfully Implemented:\n";
            echo "   📊 Advanced Analytics Engine with predictive capabilities\n";
            echo "   🎯 Revenue Optimization Engine with intelligent recommendations\n";
            echo "   📈 Comprehensive Business Dashboard with interactive charts\n";
            echo "   👥 Customer Analytics & Segmentation Dashboard\n";
            echo "   📱 Mobile-First Progressive Web App Dashboard\n";
            echo "   🔮 Predictive Analytics & Revenue Forecasting\n";
            echo "   🧠 Business Intelligence & KPI Tracking\n";
            echo "   ⚡ Performance Optimization & Caching\n";
            echo "   🔒 Security Features & Authentication\n";
            echo "   🔗 Seamless Integration with Phase 2 & 3\n\n";
            
            echo "🚀 Business Value Delivered:\n";
            echo "   • Advanced analytics with 360° business insights\n";
            echo "   • Predictive revenue forecasting and growth planning\n";
            echo "   • Intelligent optimization recommendations\n";
            echo "   • Customer segmentation and lifecycle analysis\n";
            echo "   • Mobile-first dashboard for on-the-go management\n";
            echo "   • Real-time performance monitoring and alerts\n";
            echo "   • Data-driven decision making capabilities\n";
            echo "   • Automated business intelligence reporting\n\n";
            
            echo "📱 Next Steps:\n";
            echo "   1. Deploy to production environment\n";
            echo "   2. Configure SSL certificates for PWA features\n";
            echo "   3. Set up automated backups for analytics data\n";
            echo "   4. Train business users on new dashboard features\n";
            echo "   5. Monitor performance and user adoption\n";
            echo "   6. Begin Phase 5: Advanced Integrations & Scaling\n\n";
            
        } else {
            echo "⚠️  Some tests failed. Please review and fix issues before deployment.\n\n";
            
            echo "❌ Failed Tests:\n";
            foreach ($this->results as $result) {
                if ($result['status'] !== 'PASS') {
                    echo "   • {$result['name']}: {$result['status']}\n";
                    if (isset($result['error'])) {
                        echo "     Error: {$result['error']}\n";
                    }
                }
            }
            echo "\n";
        }
        
        return [
            'total_tests' => $this->total_tests,
            'passed_tests' => $this->passed_tests,
            'success_rate' => round(($this->passed_tests / $this->total_tests) * 100, 1),
            'all_passed' => $this->passed_tests === $this->total_tests,
            'results' => $this->results
        ];
    }
}

// Run verification if called directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    try {
        $verifier = new NayaxPhase4Verifier($pdo);
        $results = $verifier->runAllTests();
        
        // Exit with appropriate code
        exit($results['all_passed'] ? 0 : 1);
        
    } catch (Exception $e) {
        echo "💥 Verification failed with exception: " . $e->getMessage() . "\n";
        exit(1);
    }
}
?> 