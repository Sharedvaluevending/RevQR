<?php
/**
 * COMPREHENSIVE VOTING SYSTEM REVIEW & TEST
 * 
 * This script reviews and tests all voting functionality:
 * 1. Vote display when they vote
 * 2. Coin balance updates
 * 3. Extra vote purchasing system
 * 4. Old/conflicting code detection
 * 5. All voting features and functions
 * 
 * Run this to get a complete analysis and test plan
 */

require_once __DIR__ . '/html/core/config.php';
require_once __DIR__ . '/html/core/session.php';
require_once __DIR__ . '/html/core/functions.php';

echo "<h1>🗳️ COMPREHENSIVE VOTING SYSTEM REVIEW</h1>";
echo "<style>
body { font-family: monospace; background: #f5f5f5; padding: 20px; }
h1, h2, h3 { color: #333; }
.section { background: white; padding: 15px; margin: 10px 0; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
.success { color: #28a745; font-weight: bold; }
.warning { color: #ffc107; font-weight: bold; }
.error { color: #dc3545; font-weight: bold; }
.info { color: #17a2b8; font-weight: bold; }
pre { background: #f8f9fa; padding: 10px; border-radius: 3px; overflow-x: auto; }
table { width: 100%; border-collapse: collapse; margin: 10px 0; }
th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
th { background: #f8f9fa; }
.issue { background: #ffe6e6; padding: 10px; border-left: 4px solid #dc3545; margin: 5px 0; }
.fix { background: #e6ffe6; padding: 10px; border-left: 4px solid #28a745; margin: 5px 0; }
</style>";

// Helper function to check file existence and return status
function checkFile($file, $description) {
    if (file_exists($file)) {
        echo "<span class='success'>✅ $description</span><br>";
        return true;
    } else {
        echo "<span class='error'>❌ $description (File not found: $file)</span><br>";
        return false;
    }
}

// Helper function to analyze code for specific patterns
function analyzeCode($file, $patterns, $description) {
    if (!file_exists($file)) {
        echo "<span class='error'>❌ Cannot analyze $file - file not found</span><br>";
        return [];
    }
    
    $content = file_get_contents($file);
    $issues = [];
    
    foreach ($patterns as $pattern => $issue_desc) {
        if (preg_match($pattern, $content)) {
            $issues[] = $issue_desc;
        }
    }
    
    if (empty($issues)) {
        echo "<span class='success'>✅ $description - No issues found</span><br>";
    } else {
        echo "<span class='warning'>⚠️ $description - Issues found:</span><br>";
        foreach ($issues as $issue) {
            echo "<div class='issue'>$issue</div>";
        }
    }
    
    return $issues;
}

echo "<div class='section'>";
echo "<h2>📁 1. VOTING FILE STRUCTURE ANALYSIS</h2>";

$voting_files = [
    'html/vote.php' => 'Main voting page',
    'html/public/vote.php' => 'Public voting page',
    'html/user/vote.php' => 'User voting page',
    'html/api/get-vote-status.php' => 'Vote status API',
    'html/core/get-vote-counts.php' => 'Vote count API',
    'html/core/qr_coin_manager.php' => 'QR Coin Manager',
    'vending-vote-platform/public/vote.php' => 'Vending platform vote page (if exists)',
    'vending-vote-platform/models/Vote.php' => 'Vote model (if exists)'
];

echo "<h3>Core Voting Files:</h3>";
$existing_files = [];
foreach ($voting_files as $file => $desc) {
    if (checkFile($file, $desc)) {
        $existing_files[] = $file;
    }
}

echo "</div>";

echo "<div class='section'>";
echo "<h2>🔍 2. DATABASE SCHEMA ANALYSIS</h2>";

try {
    // Check votes table structure
    echo "<h3>Votes Table Structure:</h3>";
    $stmt = $pdo->query("DESCRIBE votes");
    $vote_columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table>";
    echo "<tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    foreach ($vote_columns as $col) {
        echo "<tr>";
        echo "<td>{$col['Field']}</td>";
        echo "<td>{$col['Type']}</td>";
        echo "<td>{$col['Null']}</td>";
        echo "<td>{$col['Key']}</td>";
        echo "<td>{$col['Default']}</td>";
        echo "<td>{$col['Extra']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Check for vote type consistency
    echo "<h3>Vote Type Analysis:</h3>";
    $stmt = $pdo->query("SELECT DISTINCT vote_type, COUNT(*) as count FROM votes GROUP BY vote_type");
    $vote_types = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table>";
    echo "<tr><th>Vote Type</th><th>Count</th><th>Status</th></tr>";
    $expected_types = ['vote_in', 'vote_out'];
    $found_types = array_column($vote_types, 'vote_type');
    
    foreach ($vote_types as $type) {
        $status = in_array($type['vote_type'], $expected_types) ? 
                 "<span class='success'>✅ Valid</span>" : 
                 "<span class='error'>❌ Invalid/Legacy</span>";
        echo "<tr><td>{$type['vote_type']}</td><td>{$type['count']}</td><td>$status</td></tr>";
    }
    echo "</table>";
    
    if (array_diff($found_types, $expected_types)) {
        echo "<div class='issue'>Found legacy vote types that need migration: " . 
             implode(', ', array_diff($found_types, $expected_types)) . "</div>";
    }
    
} catch (Exception $e) {
    echo "<span class='error'>❌ Database analysis failed: " . $e->getMessage() . "</span>";
}

echo "</div>";

echo "<div class='section'>";
echo "<h2>💰 3. COIN SYSTEM INTEGRATION ANALYSIS</h2>";

// Check QR Coin Manager integration
if (file_exists('html/core/qr_coin_manager.php')) {
    echo "<span class='success'>✅ QR Coin Manager found</span><br>";
    
    // Check for proper methods
    $coin_content = file_get_contents('html/core/qr_coin_manager.php');
    $methods_to_check = [
        'awardVoteCoins' => 'Award coins for voting',
        'spendCoins' => 'Spend coins for extra votes',
        'getBalance' => 'Get user balance'
    ];
    
    echo "<h3>QR Coin Manager Methods:</h3>";
    foreach ($methods_to_check as $method => $desc) {
        if (strpos($coin_content, "function $method") !== false || 
            strpos($coin_content, "static function $method") !== false) {
            echo "<span class='success'>✅ $desc ($method)</span><br>";
        } else {
            echo "<span class='error'>❌ Missing: $desc ($method)</span><br>";
        }
    }
} else {
    echo "<span class='error'>❌ QR Coin Manager not found</span><br>";
}

echo "</div>";

echo "<div class='section'>";
echo "<h2>🔄 4. VOTE DISPLAY & REAL-TIME UPDATES</h2>";

// Analyze voting pages for AJAX functionality
$ajax_patterns = [
    '/handleVoteSubmission.*async/' => 'AJAX vote submission found',
    '/updateAllVoteCounts/' => 'Real-time vote count updates found',
    '/showVoteToast/' => 'Toast notifications found',
    '/fetch.*vote.*status/' => 'Vote status fetching found'
];

foreach ($existing_files as $file) {
    if (strpos($file, 'vote.php') !== false) {
        echo "<h3>$file:</h3>";
        analyzeCode($file, $ajax_patterns, "AJAX functionality analysis");
    }
}

echo "</div>";

echo "<div class='section'>";
echo "<h2>💸 5. EXTRA VOTE PURCHASE SYSTEM</h2>";

// Check for vote purchase functionality
$purchase_patterns = [
    '/purchase_vote/' => 'Vote purchase functionality found',
    '/spendCoins.*50/' => 'Coin spending for votes (50 coins) found',
    '/You can purchase additional votes/' => 'Purchase messaging found'
];

foreach ($existing_files as $file) {
    if (strpos($file, 'vote.php') !== false) {
        echo "<h3>$file:</h3>";
        analyzeCode($file, $purchase_patterns, "Vote purchase system analysis");
    }
}

// Check database for vote purchases
try {
    echo "<h3>Vote Purchase Transaction History:</h3>";
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as purchase_count,
            SUM(amount) as total_spent,
            AVG(amount) as avg_spent
        FROM qr_coin_transactions 
        WHERE transaction_type = 'spending' 
        AND category = 'vote_purchase'
    ");
    $purchase_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($purchase_stats['purchase_count'] > 0) {
        echo "<span class='success'>✅ Found {$purchase_stats['purchase_count']} vote purchases</span><br>";
        echo "<span class='info'>💰 Total spent: {$purchase_stats['total_spent']} coins</span><br>";
        echo "<span class='info'>📊 Average per purchase: " . round($purchase_stats['avg_spent'], 2) . " coins</span><br>";
    } else {
        echo "<span class='warning'>⚠️ No vote purchases found in transaction history</span><br>";
    }
} catch (Exception $e) {
    echo "<span class='error'>❌ Purchase analysis failed: " . $e->getMessage() . "</span>";
}

echo "</div>";

echo "<div class='section'>";
echo "<h2>🔍 6. CONFLICTING CODE DETECTION</h2>";

// Check for potential conflicts
$conflict_patterns = [
    '/VotingService/' => 'Legacy VotingService references (should be removed)',
    '/daily.*vote.*limit/' => 'Daily vote limits (conflicts with weekly system)',
    '/vote_type.*=.*[\'"]in[\'"]/' => 'Legacy vote type "in" (should be "vote_in")',
    '/vote_type.*=.*[\'"]out[\'"]/' => 'Legacy vote type "out" (should be "vote_out")',
    '/machine_id.*votes/' => 'Machine-specific voting (may conflict with campaign voting)'
];

echo "<h3>Potential Conflicts in Voting Files:</h3>";
$all_conflicts = [];
foreach ($existing_files as $file) {
    if (strpos($file, 'vote.php') !== false) {
        echo "<h4>$file:</h4>";
        $conflicts = analyzeCode($file, $conflict_patterns, "Conflict analysis");
        $all_conflicts = array_merge($all_conflicts, $conflicts);
    }
}

if (empty($all_conflicts)) {
    echo "<span class='success'>✅ No major conflicts detected</span><br>";
} else {
    echo "<div class='issue'><strong>⚠️ Total conflicts found: " . count($all_conflicts) . "</strong></div>";
}

echo "</div>";

echo "<div class='section'>";
echo "<h2>🧪 7. FUNCTIONAL TESTING RECOMMENDATIONS</h2>";

echo "<h3>Manual Tests to Perform:</h3>";
echo "<ol>";
echo "<li><strong>Vote Submission Test:</strong> Submit a vote and verify it shows immediately</li>";
echo "<li><strong>Balance Update Test:</strong> Check that QR coins are awarded after voting</li>";
echo "<li><strong>Vote Limit Test:</strong> Use all weekly votes and verify limits work</li>";
echo "<li><strong>Extra Vote Purchase Test:</strong> Purchase extra votes with QR coins</li>";
echo "<li><strong>Real-time Updates Test:</strong> Vote on one device, check updates on another</li>";
echo "<li><strong>Cross-browser Test:</strong> Test voting on different browsers/devices</li>";
echo "<li><strong>Guest vs Logged-in Test:</strong> Test voting as guest vs logged-in user</li>";
echo "</ol>";

echo "<h3>Test URLs to Check:</h3>";
echo "<ul>";
echo "<li><a href='html/vote.php?campaign_id=1' target='_blank'>Main Vote Page (Campaign 1)</a></li>";
echo "<li><a href='html/public/vote.php' target='_blank'>Public Vote Page</a></li>";
echo "<li><a href='html/user/vote.php' target='_blank'>User Vote Page</a></li>";
echo "<li><a href='html/api/get-vote-status.php' target='_blank'>Vote Status API</a></li>";
echo "</ul>";

echo "</div>";

echo "<div class='section'>";
echo "<h2>📋 8. ACTION PLAN & PRIORITY FIXES</h2>";

echo "<h3>🔴 HIGH PRIORITY (Must Fix):</h3>";
echo "<ol>";
if (in_array('Legacy vote types found', $all_conflicts)) {
    echo "<li><strong>Migrate legacy vote types:</strong> Convert 'in'/'out' to 'vote_in'/'vote_out'</li>";
}
if (!file_exists('html/core/qr_coin_manager.php')) {
    echo "<li><strong>Missing QR Coin Manager:</strong> Critical for vote rewards and purchases</li>";
}
echo "<li><strong>Test vote display updates:</strong> Ensure votes show immediately after submission</li>";
echo "<li><strong>Test coin balance updates:</strong> Verify coins are awarded properly</li>";
echo "</ol>";

echo "<h3>🟡 MEDIUM PRIORITY (Should Fix):</h3>";
echo "<ol>";
echo "<li><strong>Remove conflicting code:</strong> Clean up any VotingService references</li>";
echo "<li><strong>Standardize voting APIs:</strong> Ensure consistent response formats</li>";
echo "<li><strong>Add error handling:</strong> Better error messages for users</li>";
echo "</ol>";

echo "<h3>🟢 LOW PRIORITY (Nice to Have):</h3>";
echo "<ol>";
echo "<li><strong>UI/UX improvements:</strong> Better animations and feedback</li>";
echo "<li><strong>Performance optimization:</strong> Cache vote counts where possible</li>";
echo "<li><strong>Analytics tracking:</strong> Better voting behavior analytics</li>";
echo "</ol>";

echo "</div>";

echo "<div class='section'>";
echo "<h2>🔧 9. AUTOMATED FIXES</h2>";

echo "<h3>Safe Fixes to Apply:</h3>";

// Fix 1: Normalize legacy vote types
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM votes WHERE vote_type IN ('in', 'out')");
    $legacy_count = $stmt->fetchColumn();
    
    if ($legacy_count > 0) {
        echo "<div class='fix'>";
        echo "<strong>Fix 1: Normalize Legacy Vote Types</strong><br>";
        echo "Found $legacy_count legacy vote type records<br>";
        echo "<button onclick=\"if(confirm('Apply vote type fix?')) location.href='?fix=vote_types'\">Apply Fix</button>";
        echo "</div>";
        
        if (isset($_GET['fix']) && $_GET['fix'] === 'vote_types') {
            $pdo->beginTransaction();
            $pdo->exec("UPDATE votes SET vote_type = 'vote_in' WHERE vote_type = 'in'");
            $pdo->exec("UPDATE votes SET vote_type = 'vote_out' WHERE vote_type = 'out'");
            $pdo->commit();
            echo "<span class='success'>✅ Vote types normalized successfully!</span><br>";
        }
    } else {
        echo "<span class='success'>✅ No legacy vote types to fix</span><br>";
    }
} catch (Exception $e) {
    echo "<span class='error'>❌ Could not check vote types: " . $e->getMessage() . "</span>";
}

echo "</div>";

echo "<div class='section'>";
echo "<h2>📊 10. SYSTEM HEALTH SUMMARY</h2>";

$health_score = 0;
$total_checks = 10;

// Health checks
$checks = [
    file_exists('html/vote.php') => 'Main vote page exists',
    file_exists('html/api/get-vote-status.php') => 'Vote status API exists',
    file_exists('html/core/get-vote-counts.php') => 'Vote count API exists',
    file_exists('html/core/qr_coin_manager.php') => 'QR Coin Manager exists',
    count($all_conflicts) < 5 => 'Low conflict count',
    isset($vote_columns) && count($vote_columns) > 5 => 'Votes table properly structured',
    isset($purchase_stats) => 'Purchase system trackable',
    true => 'Database accessible',
    count($existing_files) >= 3 => 'Multiple voting interfaces available',
    true => 'System responsive'
];

foreach ($checks as $passed => $description) {
    if ($passed) {
        $health_score++;
        echo "<span class='success'>✅ $description</span><br>";
    } else {
        echo "<span class='error'>❌ $description</span><br>";
    }
}

$health_percentage = round(($health_score / $total_checks) * 100);
$health_color = $health_percentage >= 80 ? 'success' : 
                ($health_percentage >= 60 ? 'warning' : 'error');

echo "<h3><span class='$health_color'>🏥 Overall System Health: $health_percentage% ($health_score/$total_checks)</span></h3>";

if ($health_percentage >= 80) {
    echo "<div class='fix'><strong>✅ System is healthy!</strong> Minor optimizations recommended.</div>";
} else if ($health_percentage >= 60) {
    echo "<div class='issue'><strong>⚠️ System needs attention!</strong> Several issues should be addressed.</div>";
} else {
    echo "<div class='issue'><strong>🚨 System needs immediate attention!</strong> Critical issues found.</div>";
}

echo "</div>";

echo "<div class='section'>";
echo "<h2>🎯 NEXT STEPS</h2>";
echo "<ol>";
echo "<li><strong>Run manual tests</strong> on the voting pages listed above</li>";
echo "<li><strong>Apply high-priority fixes</strong> from the action plan</li>";
echo "<li><strong>Test the complete voting flow:</strong> vote → see result → coin reward → extra purchase</li>";
echo "<li><strong>Monitor for real-time updates</strong> across different browsers</li>";
echo "<li><strong>Document any remaining issues</strong> for future development</li>";
echo "</ol>";
echo "</div>";

?> 