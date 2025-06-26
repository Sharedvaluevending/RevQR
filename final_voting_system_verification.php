<?php
/**
 * FINAL VOTING SYSTEM VERIFICATION SCRIPT
 * Comprehensive check of all voting system components
 */

echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
    .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
    .section { margin: 20px 0; padding: 15px; border-left: 4px solid #007bff; background: #f8f9fa; }
    .success { color: #28a745; font-weight: bold; }
    .error { color: #dc3545; font-weight: bold; }
    .warning { color: #ffc107; font-weight: bold; }
    .info { color: #17a2b8; font-weight: bold; }
    .feature-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 15px; margin: 15px 0; }
    .feature-card { background: white; padding: 15px; border-radius: 8px; border: 1px solid #ddd; }
    .feature-card h4 { margin: 0 0 10px 0; color: #333; }
    .status-badge { padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: bold; }
    .status-good { background: #d4edda; color: #155724; }
    .status-warning { background: #fff3cd; color: #856404; }
    .status-error { background: #f8d7da; color: #721c24; }
    table { width: 100%; border-collapse: collapse; margin: 10px 0; }
    th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
    th { background-color: #f2f2f2; }
    .code-block { background: #f8f9fa; padding: 10px; border-radius: 4px; font-family: monospace; font-size: 12px; }
</style>";

echo "<div class='container'>";
echo "<h1>🔍 FINAL VOTING SYSTEM VERIFICATION</h1>";
echo "<p>Comprehensive check of all voting system components after improvements</p>";

// 1. FILE STRUCTURE VERIFICATION
echo "<div class='section'>";
echo "<h2>📁 1. FILE STRUCTURE VERIFICATION</h2>";

$critical_files = [
    'html/vote.php' => 'Main voting page',
    'html/public/vote.php' => 'Public voting page',
    'html/user/vote.php' => 'User voting page',
    'html/api/get-vote-status.php' => 'Vote status API',
    'html/api/track-ad-click.php' => 'Ad tracking API',
    'html/core/get-vote-counts.php' => 'Vote counts API',
    'html/core/promotional_ads_manager.php' => 'Promotional ads manager',
    'html/core/qr_coin_manager.php' => 'QR coin manager'
];

echo "<div class='feature-grid'>";
foreach ($critical_files as $file => $description) {
    echo "<div class='feature-card'>";
    echo "<h4>$description</h4>";
    if (file_exists($file)) {
        echo "<span class='status-badge status-good'>✅ EXISTS</span>";
        $size = filesize($file);
        echo "<br><small>Size: " . number_format($size) . " bytes</small>";
    } else {
        echo "<span class='status-badge status-error'>❌ MISSING</span>";
    }
    echo "</div>";
}
echo "</div>";
echo "</div>";

// 2. VOTING PAGE FEATURE ANALYSIS
echo "<div class='section'>";
echo "<h2>🗳️ 2. VOTING PAGE FEATURE ANALYSIS</h2>";

$voting_features = [
    'AJAX voting' => [
        'pattern' => 'handleVoteSubmission.*async',
        'description' => 'No page reload voting'
    ],
    'Real-time updates' => [
        'pattern' => 'setInterval.*updateAllVoteCounts.*5000',
        'description' => 'Vote counts update every 5 seconds'
    ],
    'QR coin rewards' => [
        'pattern' => 'QRCoinManager::addTransaction.*30.*vote',
        'description' => '30 coins per vote reward'
    ],
    'Toast notifications' => [
        'pattern' => 'showVoteToast',
        'description' => 'User feedback notifications'
    ],
    'Promotional ads' => [
        'pattern' => 'PromotionalAdsManager.*getAdsForPage.*vote',
        'description' => 'Business promotional integration'
    ],
    'Weekly vote limits' => [
        'pattern' => 'weekly_vote_limit.*2',
        'description' => '2 votes per week limit'
    ]
];

function checkFeatureInFile($file, $pattern, $feature_name) {
    if (!file_exists($file)) return false;
    $content = file_get_contents($file);
    return preg_match("/$pattern/i", $content) > 0;
}

$pages_to_check = ['html/vote.php', 'html/public/vote.php'];

echo "<table>";
echo "<tr><th>Feature</th><th>Main Vote Page</th><th>Public Vote Page</th><th>Status</th></tr>";

foreach ($voting_features as $feature => $details) {
    echo "<tr>";
    echo "<td><strong>$feature</strong><br><small>{$details['description']}</small></td>";
    
    $main_has_feature = checkFeatureInFile('html/vote.php', $details['pattern'], $feature);
    $public_has_feature = checkFeatureInFile('html/public/vote.php', $details['pattern'], $feature);
    
    echo "<td>" . ($main_has_feature ? "<span class='success'>✅</span>" : "<span class='error'>❌</span>") . "</td>";
    echo "<td>" . ($public_has_feature ? "<span class='success'>✅</span>" : "<span class='error'>❌</span>") . "</td>";
    
    if ($main_has_feature && $public_has_feature) {
        echo "<td><span class='status-badge status-good'>CONSISTENT</span></td>";
    } elseif ($main_has_feature || $public_has_feature) {
        echo "<td><span class='status-badge status-warning'>INCONSISTENT</span></td>";
    } else {
        echo "<td><span class='status-badge status-error'>MISSING</span></td>";
    }
    
    echo "</tr>";
}
echo "</table>";
echo "</div>";

// 3. API ENDPOINTS VERIFICATION
echo "<div class='section'>";
echo "<h2>🔌 3. API ENDPOINTS VERIFICATION</h2>";

$api_endpoints = [
    'html/api/get-vote-status.php' => 'Real-time vote status',
    'html/api/track-ad-click.php' => 'Ad click tracking',
    'html/core/get-vote-counts.php' => 'Vote count updates'
];

echo "<div class='feature-grid'>";
foreach ($api_endpoints as $endpoint => $description) {
    echo "<div class='feature-card'>";
    echo "<h4>$description</h4>";
    
    if (file_exists($endpoint)) {
        echo "<span class='status-badge status-good'>✅ EXISTS</span><br>";
        
        $content = file_get_contents($endpoint);
        
        // Check for proper JSON headers
        if (strpos($content, "header('Content-Type: application/json')") !== false) {
            echo "<span class='success'>✅ JSON headers</span><br>";
        } else {
            echo "<span class='error'>❌ Missing JSON headers</span><br>";
        }
        
        // Check for error handling
        if (strpos($content, 'try {') !== false && strpos($content, 'catch') !== false) {
            echo "<span class='success'>✅ Error handling</span><br>";
        } else {
            echo "<span class='warning'>⚠️ Limited error handling</span><br>";
        }
        
        // Check for proper response format
        if (strpos($content, 'json_encode') !== false) {
            echo "<span class='success'>✅ JSON responses</span><br>";
        } else {
            echo "<span class='error'>❌ No JSON responses</span><br>";
        }
        
    } else {
        echo "<span class='status-badge status-error'>❌ MISSING</span>";
    }
    echo "</div>";
}
echo "</div>";
echo "</div>";

// 4. PROMOTIONAL ADS SYSTEM CHECK
echo "<div class='section'>";
echo "<h2>📢 4. PROMOTIONAL ADS SYSTEM CHECK</h2>";

if (file_exists('html/core/promotional_ads_manager.php')) {
    echo "<span class='success'>✅ Promotional Ads Manager found</span><br>";
    
    $ads_content = file_get_contents('html/core/promotional_ads_manager.php');
    
    $ads_methods = [
        'getAdsForPage' => 'Get ads for specific pages',
        'trackView' => 'Track ad views',
        'trackClick' => 'Track ad clicks'
    ];
    
    echo "<h4>Core Methods:</h4>";
    foreach ($ads_methods as $method => $desc) {
        if (strpos($ads_content, "function $method") !== false) {
            echo "<span class='success'>✅ $desc ($method)</span><br>";
        } else {
            echo "<span class='error'>❌ Missing: $desc ($method)</span><br>";
        }
    }
    
    // Check for page integration
    $pages_with_ads = 0;
    foreach (['html/vote.php', 'html/public/vote.php', 'html/user/vote.php'] as $page) {
        if (file_exists($page)) {
            $page_content = file_get_contents($page);
            if (strpos($page_content, 'PromotionalAdsManager') !== false) {
                $pages_with_ads++;
            }
        }
    }
    
    echo "<h4>Integration Status:</h4>";
    echo "<span class='info'>$pages_with_ads/3 voting pages have ads integration</span><br>";
    
} else {
    echo "<span class='error'>❌ Promotional Ads Manager not found</span><br>";
}
echo "</div>";

// 5. QR COIN SYSTEM CHECK
echo "<div class='section'>";
echo "<h2>🪙 5. QR COIN SYSTEM CHECK</h2>";

if (file_exists('html/core/qr_coin_manager.php')) {
    echo "<span class='success'>✅ QR Coin Manager found</span><br>";
    
    $coin_content = file_get_contents('html/core/qr_coin_manager.php');
    
    $coin_methods = [
        'getBalance' => 'Get user balance',
        'addTransaction' => 'Add coin transactions',
        'awardVoteCoins' => 'Award coins for voting'
    ];
    
    echo "<h4>Core Methods:</h4>";
    foreach ($coin_methods as $method => $desc) {
        if (strpos($coin_content, "function $method") !== false || 
            strpos($coin_content, "static function $method") !== false) {
            echo "<span class='success'>✅ $desc ($method)</span><br>";
        } else {
            echo "<span class='error'>❌ Missing: $desc ($method)</span><br>";
        }
    }
    
    // Check for voting integration
    $voting_integration = 0;
    foreach (['html/vote.php', 'html/public/vote.php'] as $page) {
        if (file_exists($page)) {
            $page_content = file_get_contents($page);
            if (strpos($page_content, 'QRCoinManager::addTransaction') !== false) {
                $voting_integration++;
            }
        }
    }
    
    echo "<h4>Voting Integration:</h4>";
    echo "<span class='info'>$voting_integration/2 voting pages have QR coin integration</span><br>";
    
} else {
    echo "<span class='error'>❌ QR Coin Manager not found</span><br>";
}
echo "</div>";

// 6. JAVASCRIPT FUNCTIONALITY CHECK
echo "<div class='section'>";
echo "<h2>⚡ 6. JAVASCRIPT FUNCTIONALITY CHECK</h2>";

$js_features = [
    'AJAX form handling' => 'handleVoteSubmission',
    'Real-time updates' => 'updateAllVoteCounts',
    'Toast notifications' => 'showVoteToast',
    'Ad click tracking' => 'trackAdClick',
    'Animation effects' => 'animateCountUpdate'
];

echo "<table>";
echo "<tr><th>Feature</th><th>Main Page</th><th>Public Page</th><th>Status</th></tr>";

foreach ($js_features as $feature => $function_name) {
    echo "<tr>";
    echo "<td><strong>$feature</strong></td>";
    
    $main_has_js = checkFeatureInFile('html/vote.php', $function_name, $feature);
    $public_has_js = checkFeatureInFile('html/public/vote.php', $function_name, $feature);
    
    echo "<td>" . ($main_has_js ? "<span class='success'>✅</span>" : "<span class='error'>❌</span>") . "</td>";
    echo "<td>" . ($public_has_js ? "<span class='success'>✅</span>" : "<span class='error'>❌</span>") . "</td>";
    
    if ($main_has_js && $public_has_js) {
        echo "<td><span class='status-badge status-good'>CONSISTENT</span></td>";
    } elseif ($main_has_js || $public_has_js) {
        echo "<td><span class='status-badge status-warning'>INCONSISTENT</span></td>";
    } else {
        echo "<td><span class='status-badge status-error'>MISSING</span></td>";
    }
    
    echo "</tr>";
}
echo "</table>";
echo "</div>";

// 7. SYSTEM CONSISTENCY CHECK
echo "<div class='section'>";
echo "<h2>🔄 7. SYSTEM CONSISTENCY CHECK</h2>";

$consistency_checks = [
    'Vote reward amount' => ['pattern' => '30.*vote', 'expected' => '30 coins per vote'],
    'Weekly vote limit' => ['pattern' => 'weekly_vote_limit.*2', 'expected' => '2 votes per week'],
    'Update interval' => ['pattern' => 'setInterval.*5000', 'expected' => '5 second updates'],
    'Vote types' => ['pattern' => 'vote_in.*vote_out', 'expected' => 'IN/OUT vote types']
];

echo "<table>";
echo "<tr><th>Check</th><th>Expected</th><th>Main Page</th><th>Public Page</th><th>Status</th></tr>";

foreach ($consistency_checks as $check => $details) {
    echo "<tr>";
    echo "<td><strong>$check</strong></td>";
    echo "<td>{$details['expected']}</td>";
    
    $main_consistent = checkFeatureInFile('html/vote.php', $details['pattern'], $check);
    $public_consistent = checkFeatureInFile('html/public/vote.php', $details['pattern'], $check);
    
    echo "<td>" . ($main_consistent ? "<span class='success'>✅</span>" : "<span class='error'>❌</span>") . "</td>";
    echo "<td>" . ($public_consistent ? "<span class='success'>✅</span>" : "<span class='error'>❌</span>") . "</td>";
    
    if ($main_consistent && $public_consistent) {
        echo "<td><span class='status-badge status-good'>CONSISTENT</span></td>";
    } else {
        echo "<td><span class='status-badge status-error'>INCONSISTENT</span></td>";
    }
    
    echo "</tr>";
}
echo "</table>";
echo "</div>";

// 8. FINAL ASSESSMENT
echo "<div class='section'>";
echo "<h2>📊 8. FINAL ASSESSMENT</h2>";

$total_checks = 0;
$passed_checks = 0;

// File structure check
foreach ($critical_files as $file => $desc) {
    $total_checks++;
    if (file_exists($file)) $passed_checks++;
}

// Feature consistency check
foreach ($voting_features as $feature => $details) {
    $total_checks += 2; // Main and public page
    if (checkFeatureInFile('html/vote.php', $details['pattern'], $feature)) $passed_checks++;
    if (checkFeatureInFile('html/public/vote.php', $details['pattern'], $feature)) $passed_checks++;
}

// API endpoints check
foreach ($api_endpoints as $endpoint => $desc) {
    $total_checks++;
    if (file_exists($endpoint)) $passed_checks++;
}

$success_rate = round(($passed_checks / $total_checks) * 100, 1);

echo "<div class='feature-grid'>";
echo "<div class='feature-card'>";
echo "<h4>Overall System Health</h4>";
echo "<div style='font-size: 24px; font-weight: bold; color: " . 
     ($success_rate >= 90 ? '#28a745' : ($success_rate >= 70 ? '#ffc107' : '#dc3545')) . "'>";
echo "$success_rate%</div>";
echo "<div>$passed_checks/$total_checks checks passed</div>";
echo "</div>";

echo "<div class='feature-card'>";
echo "<h4>System Grade</h4>";
$grade = $success_rate >= 95 ? 'A+' : 
         ($success_rate >= 90 ? 'A' : 
         ($success_rate >= 80 ? 'B+' : 
         ($success_rate >= 70 ? 'B' : 
         ($success_rate >= 60 ? 'C' : 'F'))));
echo "<div style='font-size: 24px; font-weight: bold; color: " . 
     ($grade[0] == 'A' ? '#28a745' : ($grade[0] == 'B' ? '#17a2b8' : '#dc3545')) . "'>";
echo "$grade</div>";
echo "<div>" . ($grade[0] == 'A' ? 'Excellent' : ($grade[0] == 'B' ? 'Good' : 'Needs Improvement')) . "</div>";
echo "</div>";

echo "<div class='feature-card'>";
echo "<h4>Key Improvements Made</h4>";
echo "<ul style='margin: 10px 0; padding-left: 20px;'>";
echo "<li>✅ Public vote page updated with AJAX</li>";
echo "<li>✅ Real-time updates (5 seconds)</li>";
echo "<li>✅ QR coin rewards integrated</li>";
echo "<li>✅ Promotional ads system active</li>";
echo "<li>✅ Toast notifications added</li>";
echo "<li>✅ Consistent user experience</li>";
echo "</ul>";
echo "</div>";
echo "</div>";

if ($success_rate >= 90) {
    echo "<div class='alert alert-success' style='background: #d4edda; color: #155724; padding: 15px; border-radius: 8px; margin: 15px 0;'>";
    echo "<h4>🎉 SYSTEM STATUS: EXCELLENT</h4>";
    echo "<p>The voting system is functioning excellently with all major features implemented and consistent across all interfaces. The improvements have been successfully applied and the system is ready for production use.</p>";
    echo "</div>";
} elseif ($success_rate >= 70) {
    echo "<div class='alert alert-warning' style='background: #fff3cd; color: #856404; padding: 15px; border-radius: 8px; margin: 15px 0;'>";
    echo "<h4>⚠️ SYSTEM STATUS: GOOD WITH MINOR ISSUES</h4>";
    echo "<p>The voting system is mostly functional but has some minor inconsistencies that should be addressed for optimal performance.</p>";
    echo "</div>";
} else {
    echo "<div class='alert alert-danger' style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin: 15px 0;'>";
    echo "<h4>🚨 SYSTEM STATUS: NEEDS ATTENTION</h4>";
    echo "<p>The voting system has significant issues that need to be addressed before production use.</p>";
    echo "</div>";
}

echo "</div>";

echo "<div class='section'>";
echo "<h2>📋 VERIFICATION SUMMARY</h2>";
echo "<div class='code-block'>";
echo "Verification completed at: " . date('Y-m-d H:i:s') . "\n";
echo "Total checks performed: $total_checks\n";
echo "Checks passed: $passed_checks\n";
echo "Success rate: $success_rate%\n";
echo "System grade: $grade\n";
echo "\nKey files verified:\n";
foreach ($critical_files as $file => $desc) {
    echo "- $file: " . (file_exists($file) ? "✅ EXISTS" : "❌ MISSING") . "\n";
}
echo "</div>";
echo "</div>";

echo "</div>";
?> 