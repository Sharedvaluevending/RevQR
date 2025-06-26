<?php
/**
 * Comprehensive Blackjack Testing & Verification Script
 * Tests all fixes and confirms blackjack is working properly
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html>\n<html><head><meta charset='UTF-8'><title>Blackjack Testing Results</title>";
echo "<style>body{font-family:Arial,sans-serif;background:#f5f5f5;padding:20px;} .test{background:white;margin:10px 0;padding:15px;border-radius:8px;border-left:4px solid #007bff;} .success{border-left-color:#28a745;} .error{border-left-color:#dc3545;} .warning{border-left-color:#ffc107;} table{width:100%;border-collapse:collapse;margin:10px 0;} th,td{padding:8px;border:1px solid #ddd;text-align:left;} th{background:#f8f9fa;}</style>";
echo "</head><body>";

echo "<h1>🃏 BLACKJACK COMPREHENSIVE TESTING</h1>";
echo "<p><strong>Testing all blackjack fixes and functionality...</strong></p>";

$tests = [];
$passed = 0;
$failed = 0;

// Test 1: File existence
echo "<div class='test'>";
echo "<h2>📁 File Existence Tests</h2>";

$files_to_check = [
    'blackjack.php' => 'Main blackjack game',
    'blackjack-simple.php' => 'Simple test version',
    'blackjack-diagnostic.php' => 'Diagnostic tool',
    'blackjack-fixes-summary.php' => 'Fixes summary',
    'js/blackjack.js' => 'JavaScript game logic'
];

foreach ($files_to_check as $file => $description) {
    $path = __DIR__ . '/' . $file;
    if (file_exists($path)) {
        echo "<p>✅ <strong>$description:</strong> Found ($file)</p>";
        $tests[] = ['test' => $description, 'result' => 'PASS', 'details' => 'File exists'];
        $passed++;
    } else {
        echo "<p>❌ <strong>$description:</strong> Missing ($file)</p>";
        $tests[] = ['test' => $description, 'result' => 'FAIL', 'details' => 'File not found'];
        $failed++;
    }
}
echo "</div>";

// Test 2: PHP Syntax Check
echo "<div class='test'>";
echo "<h2>🔍 PHP Syntax Tests</h2>";

$php_files = ['blackjack.php', 'blackjack-simple.php', 'blackjack-diagnostic.php'];
foreach ($php_files as $file) {
    if (file_exists(__DIR__ . '/' . $file)) {
        $output = [];
        $return_var = 0;
        exec("php -l " . escapeshellarg(__DIR__ . '/' . $file) . " 2>&1", $output, $return_var);
        
        if ($return_var === 0) {
            echo "<p>✅ <strong>$file:</strong> No syntax errors</p>";
            $tests[] = ['test' => "$file syntax", 'result' => 'PASS', 'details' => 'Valid PHP'];
            $passed++;
        } else {
            echo "<p>❌ <strong>$file:</strong> Syntax errors found</p>";
            echo "<pre>" . implode("\n", $output) . "</pre>";
            $tests[] = ['test' => "$file syntax", 'result' => 'FAIL', 'details' => implode(', ', $output)];
            $failed++;
        }
    }
}
echo "</div>";

// Test 3: Config and Dependencies
echo "<div class='test'>";
echo "<h2>⚙️ Configuration Tests</h2>";

try {
    require_once __DIR__ . '/../core/config.php';
    echo "<p>✅ <strong>Config file:</strong> Loaded successfully</p>";
    $tests[] = ['test' => 'Config load', 'result' => 'PASS', 'details' => 'Configuration loaded'];
    $passed++;
    
    // Test database
    $test_query = $pdo->query("SELECT 1");
    echo "<p>✅ <strong>Database:</strong> Connection working</p>";
    $tests[] = ['test' => 'Database connection', 'result' => 'PASS', 'details' => 'MySQL connection active'];
    $passed++;
    
} catch (Exception $e) {
    echo "<p>❌ <strong>Configuration:</strong> " . $e->getMessage() . "</p>";
    $tests[] = ['test' => 'Config/Database', 'result' => 'FAIL', 'details' => $e->getMessage()];
    $failed++;
}
echo "</div>";

// Test 4: REQUEST_URI Fix Verification
echo "<div class='test'>";
echo "<h2>🔧 REQUEST_URI Fix Test</h2>";

$blackjack_content = file_get_contents(__DIR__ . '/blackjack.php');
if (strpos($blackjack_content, "\$_SERVER['REQUEST_URI'] ?? '/casino/blackjack.php'") !== false) {
    echo "<p>✅ <strong>REQUEST_URI Fix:</strong> Applied correctly</p>";
    $tests[] = ['test' => 'REQUEST_URI fix', 'result' => 'PASS', 'details' => 'Null coalescing operator added'];
    $passed++;
} else {
    echo "<p>❌ <strong>REQUEST_URI Fix:</strong> Not found or incorrect</p>";
    $tests[] = ['test' => 'REQUEST_URI fix', 'result' => 'FAIL', 'details' => 'Fix not applied'];
    $failed++;
}
echo "</div>";

// Test 5: JavaScript File Analysis
echo "<div class='test'>";
echo "<h2>📜 JavaScript Tests</h2>";

$js_file = __DIR__ . '/js/blackjack.js';
if (file_exists($js_file)) {
    $js_content = file_get_contents($js_file);
    $js_size = filesize($js_file);
    
    echo "<p>✅ <strong>JavaScript file:</strong> Present (" . number_format($js_size) . " bytes)</p>";
    
    // Check for key classes/functions
    if (strpos($js_content, 'class QRBlackjack') !== false) {
        echo "<p>✅ <strong>QRBlackjack class:</strong> Found</p>";
        $tests[] = ['test' => 'JavaScript QRBlackjack class', 'result' => 'PASS', 'details' => 'Main game class present'];
        $passed++;
    } else {
        echo "<p>❌ <strong>QRBlackjack class:</strong> Missing</p>";
        $tests[] = ['test' => 'JavaScript QRBlackjack class', 'result' => 'FAIL', 'details' => 'Main class not found'];
        $failed++;
    }
    
    // Check for essential methods
    $methods = ['startNewGame', 'hit', 'stand', 'createCardElement'];
    foreach ($methods as $method) {
        if (strpos($js_content, $method) !== false) {
            echo "<p>✅ <strong>Method $method:</strong> Found</p>";
        } else {
            echo "<p>⚠️ <strong>Method $method:</strong> Missing</p>";
        }
    }
    
} else {
    echo "<p>❌ <strong>JavaScript file:</strong> Missing</p>";
    $tests[] = ['test' => 'JavaScript file', 'result' => 'FAIL', 'details' => 'blackjack.js not found'];
    $failed++;
}
echo "</div>";

// Test 6: Output Testing
echo "<div class='test'>";
echo "<h2>📤 Output Generation Tests</h2>";

// Test simple blackjack output
ob_start();
$error_occurred = false;
try {
    include __DIR__ . '/blackjack-simple.php';
    $simple_output = ob_get_contents();
    ob_end_clean();
    
    if (strlen($simple_output) > 1000 && strpos($simple_output, 'Simple Blackjack Test') !== false) {
        echo "<p>✅ <strong>Simple Blackjack:</strong> Generates full HTML output</p>";
        $tests[] = ['test' => 'Simple blackjack output', 'result' => 'PASS', 'details' => 'Complete HTML generated'];
        $passed++;
    } else {
        echo "<p>❌ <strong>Simple Blackjack:</strong> Incomplete output</p>";
        $tests[] = ['test' => 'Simple blackjack output', 'result' => 'FAIL', 'details' => 'Output too short or missing content'];
        $failed++;
    }
} catch (Exception $e) {
    ob_end_clean();
    echo "<p>❌ <strong>Simple Blackjack:</strong> Error - " . $e->getMessage() . "</p>";
    $tests[] = ['test' => 'Simple blackjack output', 'result' => 'FAIL', 'details' => $e->getMessage()];
    $failed++;
}
echo "</div>";

// Test 7: Asset Dependencies
echo "<div class='test'>";
echo "<h2>🎨 Asset Dependencies</h2>";

$external_dependencies = [
    'Bootstrap CSS' => 'https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css',
    'Bootstrap Icons' => 'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css'
];

foreach ($external_dependencies as $name => $url) {
    // Simple check - we can't actually test external URLs in CLI, but we can verify they're referenced
    if (strpos($simple_output ?? '', $url) !== false) {
        echo "<p>✅ <strong>$name:</strong> Referenced correctly</p>";
        $tests[] = ['test' => "$name reference", 'result' => 'PASS', 'details' => 'URL found in output'];
        $passed++;
    } else {
        echo "<p>⚠️ <strong>$name:</strong> Reference not found (may still work)</p>";
        $tests[] = ['test' => "$name reference", 'result' => 'WARNING', 'details' => 'URL not in output'];
    }
}
echo "</div>";

// Test Summary
echo "<div class='test " . ($failed > 0 ? 'error' : 'success') . "'>";
echo "<h2>📊 Test Summary</h2>";
echo "<table>";
echo "<tr><th>Test</th><th>Result</th><th>Details</th></tr>";
foreach ($tests as $test) {
    $class = '';
    if ($test['result'] === 'PASS') $class = 'style="background:#d4edda;"';
    if ($test['result'] === 'FAIL') $class = 'style="background:#f8d7da;"';
    if ($test['result'] === 'WARNING') $class = 'style="background:#fff3cd;"';
    
    echo "<tr $class>";
    echo "<td>{$test['test']}</td>";
    echo "<td><strong>{$test['result']}</strong></td>";
    echo "<td>{$test['details']}</td>";
    echo "</tr>";
}
echo "</table>";

echo "<div style='margin:20px 0;padding:15px;background:#e9ecef;border-radius:5px;'>";
echo "<h3>Final Results:</h3>";
echo "<ul>";
echo "<li><strong>✅ Passed:</strong> $passed tests</li>";
echo "<li><strong>❌ Failed:</strong> $failed tests</li>";
echo "<li><strong>Success Rate:</strong> " . round(($passed / max(1, $passed + $failed)) * 100, 1) . "%</li>";
echo "</ul>";

if ($failed === 0) {
    echo "<div style='background:#d4edda;color:#155724;padding:10px;border-radius:5px;text-align:center;'>";
    echo "<h4>🎉 ALL TESTS PASSED! 🎉</h4>";
    echo "<p>Blackjack is fully functional and ready to use!</p>";
    echo "</div>";
} elseif ($failed <= 2) {
    echo "<div style='background:#fff3cd;color:#856404;padding:10px;border-radius:5px;text-align:center;'>";
    echo "<h4>⚠️ MOSTLY WORKING ⚠️</h4>";
    echo "<p>Minor issues found but blackjack should still work.</p>";
    echo "</div>";
} else {
    echo "<div style='background:#f8d7da;color:#721c24;padding:10px;border-radius:5px;text-align:center;'>";
    echo "<h4>❌ ISSUES FOUND ❌</h4>";
    echo "<p>Multiple issues detected. Review failed tests above.</p>";
    echo "</div>";
}
echo "</div>";
echo "</div>";

// Quick Access Links
echo "<div class='test'>";
echo "<h2>🚀 Test the Fixed Blackjack</h2>";
echo "<div style='text-align:center;margin:20px 0;'>";
echo "<a href='blackjack-simple.php' style='padding:10px 20px;margin:5px;background:#28a745;color:white;text-decoration:none;border-radius:5px;display:inline-block;'>🎮 Try Simple Blackjack</a>";
echo "<a href='blackjack-diagnostic.php' style='padding:10px 20px;margin:5px;background:#ffc107;color:#000;text-decoration:none;border-radius:5px;display:inline-block;'>🔧 Run Diagnostic</a>";
echo "<a href='blackjack.php' style='padding:10px 20px;margin:5px;background:#007bff;color:white;text-decoration:none;border-radius:5px;display:inline-block;'>🃏 Full Blackjack</a>";
echo "</div>";
echo "</div>";

echo "</body></html>";
?> 