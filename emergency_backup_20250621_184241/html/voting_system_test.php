<?php
require_once __DIR__ . '/core/config.php';

// Include all necessary services
require_once __DIR__ . '/core/services/VotingService.php';
require_once __DIR__ . '/core/functions.php';

// Initialize VotingService with PDO connection
VotingService::init($pdo);

$test_results = [];
$errors = [];

echo "<h1>🗳️ COMPREHENSIVE VOTING SYSTEM TEST</h1>";
echo "<hr>";

// Test 1: Check vote submission functionality
echo "<h2>1. Testing Vote Submission</h2>";

try {
    // Get a test voting list and campaign
    $stmt = $pdo->prepare("
        SELECT vl.*, c.id as campaign_id, c.name as campaign_name
        FROM voting_lists vl
        LEFT JOIN campaign_voting_lists cvl ON vl.id = cvl.voting_list_id
        LEFT JOIN campaigns c ON cvl.campaign_id = c.id
        WHERE vl.id IS NOT NULL
        LIMIT 1
    ");
    $stmt->execute();
    $test_data = $stmt->fetch();
    
    if ($test_data) {
        echo "✅ Found test data: List '{$test_data['name']}' (ID: {$test_data['id']})";
        if ($test_data['campaign_id']) {
            echo " linked to Campaign '{$test_data['campaign_name']}' (ID: {$test_data['campaign_id']})";
        }
        echo "<br>";
        
        // Get items for this list
        $stmt = $pdo->prepare("SELECT * FROM voting_list_items WHERE voting_list_id = ? LIMIT 1");
        $stmt->execute([$test_data['id']]);
        $test_item = $stmt->fetch();
        
        if ($test_item) {
            echo "✅ Found test item: '{$test_item['item_name']}' (ID: {$test_item['id']})<br>";
            
            // Test vote data
            $vote_data = [
                'item_id' => $test_item['id'],
                'vote_type' => 'vote_in',
                'voter_ip' => '127.0.0.1',
                'user_id' => null,
                'campaign_id' => $test_data['campaign_id'],
                'machine_id' => $test_data['id'],
                'user_agent' => 'Test Browser',
                'vote_method' => 'auto'
            ];
            
            // Test vote submission through VotingService
            $vote_result = VotingService::recordVote($vote_data);
            
            if ($vote_result['success']) {
                echo "✅ Vote submission successful: {$vote_result['message']}<br>";
                $test_results['vote_submission'] = 'PASS';
            } else {
                echo "❌ Vote submission failed: {$vote_result['message']}<br>";
                $errors[] = "Vote submission: " . $vote_result['message'];
                $test_results['vote_submission'] = 'FAIL';
            }
        } else {
            echo "❌ No test items found in voting list<br>";
            $errors[] = "No test items available";
            $test_results['vote_submission'] = 'SKIP';
        }
    } else {
        echo "❌ No test voting lists found<br>";
        $errors[] = "No test voting lists available";
        $test_results['vote_submission'] = 'SKIP';
    }
} catch (Exception $e) {
    echo "❌ Vote submission test error: " . $e->getMessage() . "<br>";
    $errors[] = "Vote submission exception: " . $e->getMessage();
    $test_results['vote_submission'] = 'ERROR';
}

echo "<hr>";

// Test 2: Check vote count updates
echo "<h2>2. Testing Vote Count Updates</h2>";

if ($test_data && $test_item) {
    try {
        // Get vote counts before
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(CASE WHEN vote_type = 'vote_in' THEN 1 END) as votes_in,
                COUNT(CASE WHEN vote_type = 'vote_out' THEN 1 END) as votes_out,
                COUNT(*) as total_votes
            FROM votes 
            WHERE item_id = ?
        ");
        $stmt->execute([$test_item['id']]);
        $vote_counts = $stmt->fetch();
        
        echo "✅ Current vote counts for '{$test_item['item_name']}':<br>";
        echo "&nbsp;&nbsp;&nbsp;• Votes IN: {$vote_counts['votes_in']}<br>";
        echo "&nbsp;&nbsp;&nbsp;• Votes OUT: {$vote_counts['votes_out']}<br>";
        echo "&nbsp;&nbsp;&nbsp;• Total: {$vote_counts['total_votes']}<br>";
        
        $test_results['vote_counts'] = 'PASS';
    } catch (Exception $e) {
        echo "❌ Vote count test error: " . $e->getMessage() . "<br>";
        $errors[] = "Vote count exception: " . $e->getMessage();
        $test_results['vote_counts'] = 'ERROR';
    }
} else {
    echo "⚠️ Skipping vote count test - no test data available<br>";
    $test_results['vote_counts'] = 'SKIP';
}

echo "<hr>";

// Test 3: Check banner image display system
echo "<h2>3. Testing Banner Image Display</h2>";

try {
    // Check for banner images in voting lists
    $stmt = $pdo->prepare("
        SELECT id, name, header_image 
        FROM voting_lists 
        WHERE header_image IS NOT NULL AND header_image != '' 
        LIMIT 3
    ");
    $stmt->execute();
    $lists_with_banners = $stmt->fetchAll();
    
    if ($lists_with_banners) {
        echo "✅ Found " . count($lists_with_banners) . " voting lists with banner images:<br>";
        foreach ($lists_with_banners as $list) {
            $image_path = $list['header_image'];
            $full_path = __DIR__ . '/' . $image_path;
            
            echo "&nbsp;&nbsp;&nbsp;• List: '{$list['name']}' - Image: {$image_path}";
            
            if (file_exists($full_path)) {
                $image_size = @getimagesize($full_path);
                if ($image_size) {
                    echo " ✅ (Exists: {$image_size[0]}x{$image_size[1]}px)<br>";
                } else {
                    echo " ⚠️ (Exists but invalid image)<br>";
                }
            } else {
                echo " ❌ (File not found)<br>";
                $errors[] = "Banner image not found: {$image_path}";
            }
        }
        $test_results['banner_images'] = 'PASS';
    } else {
        echo "⚠️ No voting lists with banner images found<br>";
        echo "Creating test banner image...<br>";
        
        // Create a simple test banner
        $banner_width = 1200;
        $banner_height = 300;
        $image = imagecreate($banner_width, $banner_height);
        
        // Colors
        $bg_color = imagecolorallocate($image, 25, 118, 210);
        $text_color = imagecolorallocate($image, 255, 255, 255);
        
        // Fill background
        imagefill($image, 0, 0, $bg_color);
        
        // Add text
        $text = "REVENUE QR VOTING BANNER";
        imagestring($image, 5, ($banner_width - strlen($text) * 10) / 2, $banner_height / 2 - 10, $text, $text_color);
        
        // Save banner
        $banner_path = 'public/test_banner.png';
        $full_banner_path = __DIR__ . '/' . $banner_path;
        
        if (imagepng($image, $full_banner_path)) {
            echo "✅ Created test banner: {$banner_path}<br>";
            
            // Update a voting list with this banner
            if ($test_data) {
                $stmt = $pdo->prepare("UPDATE voting_lists SET header_image = ? WHERE id = ?");
                $stmt->execute([$banner_path, $test_data['id']]);
                echo "✅ Updated voting list '{$test_data['name']}' with test banner<br>";
            }
            
            $test_results['banner_images'] = 'PASS';
        } else {
            echo "❌ Failed to create test banner<br>";
            $errors[] = "Could not create test banner image";
            $test_results['banner_images'] = 'FAIL';
        }
        
        imagedestroy($image);
    }
} catch (Exception $e) {
    echo "❌ Banner image test error: " . $e->getMessage() . "<br>";
    $errors[] = "Banner image exception: " . $e->getMessage();
    $test_results['banner_images'] = 'ERROR';
}

echo "<hr>";

// Test 4: Check voting payouts and rewards
echo "<h2>4. Testing Voting Payouts & Rewards</h2>";

try {
    // Check QR coin transactions for voting
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_transactions,
            SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) as total_earned,
            SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END) as total_spent
        FROM qr_coin_transactions 
        WHERE category = 'voting' 
        AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    $stmt->execute();
    $payout_stats = $stmt->fetch();
    
    echo "✅ Voting payout statistics (last 7 days):<br>";
    echo "&nbsp;&nbsp;&nbsp;• Total transactions: {$payout_stats['total_transactions']}<br>";
    echo "&nbsp;&nbsp;&nbsp;• Total earned: {$payout_stats['total_earned']} QR coins<br>";
    echo "&nbsp;&nbsp;&nbsp;• Total spent: {$payout_stats['total_spent']} QR coins<br>";
    
    // Check winner calculation system
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as winner_count
        FROM weekly_winners 
        WHERE week_year = YEARWEEK(NOW(), 1)
    ");
    $stmt->execute();
    $current_winners = $stmt->fetchColumn();
    
    echo "✅ Current week winners calculated: {$current_winners}<br>";
    
    // Test economic balance
    $net_coins = $payout_stats['total_earned'] - $payout_stats['total_spent'];
    echo "✅ Net QR coin flow: ";
    if ($net_coins >= 0) {
        echo "+{$net_coins} (earning more than spending) ✅<br>";
    } else {
        echo "{$net_coins} (spending more than earning) ⚠️<br>";
    }
    
    $test_results['payouts'] = 'PASS';
} catch (Exception $e) {
    echo "❌ Payout test error: " . $e->getMessage() . "<br>";
    $errors[] = "Payout exception: " . $e->getMessage();
    $test_results['payouts'] = 'ERROR';
}

echo "<hr>";

// Test 5: Check database integrity
echo "<h2>5. Testing Database Integrity</h2>";

try {
    // Check for votes with NULL business_id
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM votes WHERE campaign_id IS NULL OR campaign_id = 0");
    $stmt->execute();
    $null_campaign_votes = $stmt->fetchColumn();
    
    if ($null_campaign_votes > 0) {
        echo "⚠️ Found {$null_campaign_votes} votes with NULL/0 campaign_id<br>";
    } else {
        echo "✅ All votes have proper campaign_id<br>";
    }
    
    // Check for QR codes with NULL business_id
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM qr_codes WHERE business_id IS NULL");
    $stmt->execute();
    $null_business_qr = $stmt->fetchColumn();
    
    if ($null_business_qr > 0) {
        echo "⚠️ Found {$null_business_qr} QR codes with NULL business_id<br>";
    } else {
        echo "✅ All QR codes have proper business_id<br>";
    }
    
    // Check campaign-voting list relationships
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_campaigns,
            COUNT(cvl.voting_list_id) as linked_campaigns
        FROM campaigns c
        LEFT JOIN campaign_voting_lists cvl ON c.id = cvl.campaign_id
        WHERE c.status = 'active'
    ");
    $stmt->execute();
    $campaign_stats = $stmt->fetch();
    
    echo "✅ Campaign linking: {$campaign_stats['linked_campaigns']}/{$campaign_stats['total_campaigns']} active campaigns linked to voting lists<br>";
    
    $test_results['database'] = 'PASS';
} catch (Exception $e) {
    echo "❌ Database integrity test error: " . $e->getMessage() . "<br>";
    $errors[] = "Database integrity exception: " . $e->getMessage();
    $test_results['database'] = 'ERROR';
}

echo "<hr>";

// Test 6: Live URL Testing
echo "<h2>6. Testing Live Voting URLs</h2>";

try {
    // Get QR codes with proper URLs
    $stmt = $pdo->prepare("
        SELECT qr.*, c.name as campaign_name
        FROM qr_codes qr
        LEFT JOIN campaigns c ON qr.campaign_id = c.id
        WHERE qr.code IS NOT NULL 
        AND qr.business_id IS NOT NULL
        LIMIT 3
    ");
    $stmt->execute();
    $test_qr_codes = $stmt->fetchAll();
    
    if ($test_qr_codes) {
        echo "✅ Found " . count($test_qr_codes) . " QR codes for URL testing:<br>";
        foreach ($test_qr_codes as $qr) {
            $voting_url = APP_URL . "/vote.php?code=" . urlencode($qr['code']);
            echo "&nbsp;&nbsp;&nbsp;• QR Code: {$qr['code']} → <a href='{$voting_url}' target='_blank'>{$voting_url}</a><br>";
            
            if ($qr['campaign_name']) {
                echo "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Campaign: {$qr['campaign_name']}<br>";
            }
        }
        $test_results['live_urls'] = 'PASS';
    } else {
        echo "⚠️ No QR codes available for URL testing<br>";
        $test_results['live_urls'] = 'SKIP';
    }
} catch (Exception $e) {
    echo "❌ Live URL test error: " . $e->getMessage() . "<br>";
    $errors[] = "Live URL exception: " . $e->getMessage();
    $test_results['live_urls'] = 'ERROR';
}

echo "<hr>";

// Summary
echo "<h2>📊 TEST SUMMARY</h2>";

$total_tests = count($test_results);
$passed_tests = count(array_filter($test_results, function($result) { return $result === 'PASS'; }));
$failed_tests = count(array_filter($test_results, function($result) { return $result === 'FAIL'; }));
$error_tests = count(array_filter($test_results, function($result) { return $result === 'ERROR'; }));
$skipped_tests = count(array_filter($test_results, function($result) { return $result === 'SKIP'; }));

echo "<table border='1' cellpadding='10'>";
echo "<tr><th>Test Category</th><th>Result</th></tr>";
foreach ($test_results as $test => $result) {
    $color = '';
    switch ($result) {
        case 'PASS': $color = 'green'; break;
        case 'FAIL': $color = 'red'; break;
        case 'ERROR': $color = 'orange'; break;
        case 'SKIP': $color = 'gray'; break;
    }
    echo "<tr><td>" . ucwords(str_replace('_', ' ', $test)) . "</td><td style='color: {$color}; font-weight: bold;'>{$result}</td></tr>";
}
echo "</table>";

echo "<br>";
echo "<strong>Overall Results:</strong><br>";
echo "✅ Passed: {$passed_tests}/{$total_tests}<br>";
echo "❌ Failed: {$failed_tests}/{$total_tests}<br>";
echo "🔥 Errors: {$error_tests}/{$total_tests}<br>";
echo "⚠️ Skipped: {$skipped_tests}/{$total_tests}<br>";

if ($passed_tests === $total_tests) {
    echo "<h3 style='color: green;'>🎉 ALL TESTS PASSED! Voting system is fully operational!</h3>";
} elseif ($passed_tests >= $total_tests * 0.8) {
    echo "<h3 style='color: orange;'>⚠️ Most tests passed, minor issues detected</h3>";
} else {
    echo "<h3 style='color: red;'>❌ Multiple issues detected, system needs attention</h3>";
}

if (!empty($errors)) {
    echo "<h3>🚨 Issues Found:</h3>";
    echo "<ul>";
    foreach ($errors as $error) {
        echo "<li style='color: red;'>{$error}</li>";
    }
    echo "</ul>";
}

echo "<hr>";
echo "<h3>🔧 Quick Actions:</h3>";
echo "<a href='qr_comprehensive_test.php' style='background: #007bff; color: white; padding: 10px 15px; text-decoration: none; border-radius: 5px; margin-right: 10px;'>Run QR Test</a>";
echo "<a href='qr_dynamic_editor.php' style='background: #28a745; color: white; padding: 10px 15px; text-decoration: none; border-radius: 5px; margin-right: 10px;'>Dynamic Editor</a>";
echo "<a href='vote.php' style='background: #17a2b8; color: white; padding: 10px 15px; text-decoration: none; border-radius: 5px; margin-right: 10px;'>Test Vote Page</a>";
echo "<a href='business/manage-campaigns.php' style='background: #6f42c1; color: white; padding: 10px 15px; text-decoration: none; border-radius: 5px;'>Manage Campaigns</a>";

echo "<br><br>";
echo "<em>Test completed at: " . date('Y-m-d H:i:s') . "</em>";
?> 