<?php
require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/core/session.php';
require_once __DIR__ . '/core/auth.php';

echo "<h1>Cross References Debug</h1>";

// Check session first
echo "<h2>Session Check:</h2>";
echo "User ID: " . ($_SESSION['user_id'] ?? 'NOT SET') . "<br>";
echo "Role: " . ($_SESSION['role'] ?? 'NOT SET') . "<br>";
echo "Business ID: " . ($_SESSION['business_id'] ?? 'NOT SET') . "<br>";
echo "is_logged_in(): " . (is_logged_in() ? 'TRUE' : 'FALSE') . "<br>";

if (!is_logged_in()) {
    echo "<div style='color: red;'>❌ NOT LOGGED IN - Please visit debug_login.php first</div>";
    echo "<a href='/debug_login.php'>Go to Login Debug Page</a>";
    exit;
}

// Get business ID using the same method as the real page
$stmt = $pdo->prepare("SELECT b.id FROM businesses b JOIN users u ON b.id = u.business_id WHERE u.id = ?");
$stmt->execute([$_SESSION['user_id']]);
$business = $stmt->fetch();
$business_id = $business ? $business['id'] : 0;

echo "<h2>Business ID Detection:</h2>";
echo "Detected Business ID: " . $business_id . "<br>";

if ($business_id == 0) {
    echo "<div style='color: red;'>❌ No business found for user</div>";
    exit;
}

echo "<h2>Testing Cross-Reference Query:</h2>";

try {
    // Test the main summary query
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT v.id) as total_votes,
            COUNT(DISTINCT s.id) as total_sales,
            COALESCE(SUM(s.quantity * s.sale_price), 0) as total_revenue,
            COUNT(DISTINCT m.id) as active_machines,
            ROUND(
                CASE 
                    WHEN COUNT(DISTINCT v.id) > 0 
                    THEN (COUNT(DISTINCT s.id) * 100.0 / COUNT(DISTINCT v.id))
                    ELSE 0 
                END, 1
            ) as overall_conversion
        FROM votes v
        JOIN machines m ON v.machine_id = m.id
        LEFT JOIN sales s ON s.business_id = m.business_id 
            AND DATE(v.created_at) = DATE(s.sale_time)
        WHERE m.business_id = ?
        AND v.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $stmt->execute([$business_id]);
    $summary = $stmt->fetch();
    
    echo "<div style='color: green;'>✅ Query executed successfully!</div>";
    echo "<h3>Summary Results:</h3>";
    echo "Total Votes: " . $summary['total_votes'] . "<br>";
    echo "Total Sales: " . $summary['total_sales'] . "<br>";
    echo "Total Revenue: $" . number_format($summary['total_revenue'], 2) . "<br>";
    echo "Active Machines: " . $summary['active_machines'] . "<br>";
    echo "Overall Conversion: " . $summary['overall_conversion'] . "%<br>";
    
    if ($summary['total_votes'] > 0 || $summary['total_sales'] > 0) {
        echo "<div style='color: green; font-weight: bold;'>✅ DATA FOUND! The cross-references page should work.</div>";
        echo "<h3>Quick Links:</h3>";
        echo "<a href='/business/cross_references_details.php'>Go to Real Cross References Page</a><br>";
        echo "<a href='/business/dashboard.php'>Go to Dashboard</a><br>";
    } else {
        echo "<div style='color: orange;'>⚠️ No votes or sales data found in the last 30 days.</div>";
    }
    
} catch (Exception $e) {
    echo "<div style='color: red;'>❌ Query failed!</div>";
    echo "Error: " . $e->getMessage() . "<br>";
    echo "Trace: " . $e->getTraceAsString() . "<br>";
}

echo "<h2>Raw Data Check:</h2>";
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as vote_count FROM votes v JOIN machines m ON v.machine_id = m.id WHERE m.business_id = ?");
    $stmt->execute([$business_id]);
    $vote_count = $stmt->fetch()['vote_count'];
    echo "Total votes for business: " . $vote_count . "<br>";
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as sales_count FROM sales WHERE business_id = ?");
    $stmt->execute([$business_id]);
    $sales_count = $stmt->fetch()['sales_count'];
    echo "Total sales for business: " . $sales_count . "<br>";
    
} catch (Exception $e) {
    echo "Error checking raw data: " . $e->getMessage() . "<br>";
}
?> 