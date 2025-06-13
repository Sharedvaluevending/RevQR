<?php
/**
 * Debug QR Manager Issue
 * Check what QR codes exist and session status
 */

require_once __DIR__ . '/html/core/config.php';
require_once __DIR__ . '/html/core/session.php';
require_once __DIR__ . '/html/core/auth.php';

echo "<h1>🔍 QR Manager Debug - Recent QR Codes Investigation</h1>";

// Check session status
echo "<h2>1. Session Status</h2>";
echo "Session started: " . (session_status() === PHP_SESSION_ACTIVE ? "✅ Yes" : "❌ No") . "<br>";
echo "User logged in: " . (is_logged_in() ? "✅ Yes" : "❌ No") . "<br>";

if (is_logged_in()) {
    echo "User ID: " . ($_SESSION['user_id'] ?? 'Not set') . "<br>";
    echo "User role: " . ($_SESSION['user_role'] ?? 'Not set') . "<br>";
    echo "Business ID: " . ($_SESSION['business_id'] ?? 'Not set') . "<br>";
} else {
    echo "<div style='background: #f8d7da; padding: 10px; margin: 10px 0; border-radius: 5px;'>";
    echo "❌ <strong>Not logged in</strong> - This is why you're seeing the login page!<br>";
    echo "Please log in as a business user first.";
    echo "</div>";
}

// Check recent QR codes regardless of business
echo "<h2>2. All Recent QR Codes (Last 24 Hours)</h2>";
try {
    $stmt = $pdo->prepare("
        SELECT 
            id, 
            business_id,
            code, 
            qr_type, 
            url,
            machine_name,
            created_at,
            status,
            meta
        FROM qr_codes 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ORDER BY created_at DESC
        LIMIT 20
    ");
    $stmt->execute();
    $recent_qr_codes = $stmt->fetchAll();
    
    if (!empty($recent_qr_codes)) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr style='background: #f1f1f1;'>";
        echo "<th>ID</th><th>Business ID</th><th>Code</th><th>Type</th><th>URL/Machine</th><th>Created</th><th>Status</th>";
        echo "</tr>";
        
        foreach ($recent_qr_codes as $qr) {
            $meta = json_decode($qr['meta'], true);
            $business_id_from_meta = $meta['business_id'] ?? 'N/A';
            
            echo "<tr>";
            echo "<td>" . $qr['id'] . "</td>";
            echo "<td>" . $qr['business_id'] . " (meta: $business_id_from_meta)</td>";
            echo "<td style='font-family: monospace;'>" . substr($qr['code'], 0, 20) . "...</td>";
            echo "<td>" . $qr['qr_type'] . "</td>";
            echo "<td style='max-width: 200px; overflow: hidden;'>" . 
                 ($qr['machine_name'] ?: substr($qr['url'], 0, 50)) . "...</td>";
            echo "<td>" . date('M j H:i', strtotime($qr['created_at'])) . "</td>";
            echo "<td>" . $qr['status'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        echo "<p>✅ Found " . count($recent_qr_codes) . " QR codes created in the last 24 hours</p>";
    } else {
        echo "<p>❌ No QR codes found in the last 24 hours</p>";
    }
    
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "<br>";
}

// Check QR codes by business
echo "<h2>3. QR Codes by Business</h2>";
try {
    $stmt = $pdo->prepare("
        SELECT 
            business_id,
            COUNT(*) as total_codes,
            COUNT(CASE WHEN status = 'active' THEN 1 END) as active_codes,
            MAX(created_at) as latest_created
        FROM qr_codes 
        GROUP BY business_id
        ORDER BY latest_created DESC
    ");
    $stmt->execute();
    $business_stats = $stmt->fetchAll();
    
    if (!empty($business_stats)) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr style='background: #f1f1f1;'>";
        echo "<th>Business ID</th><th>Total QR Codes</th><th>Active</th><th>Latest Created</th>";
        echo "</tr>";
        
        foreach ($business_stats as $stats) {
            echo "<tr>";
            echo "<td>" . ($stats['business_id'] ?: 'NULL') . "</td>";
            echo "<td>" . $stats['total_codes'] . "</td>";
            echo "<td>" . $stats['active_codes'] . "</td>";
            echo "<td>" . ($stats['latest_created'] ? date('M j, Y H:i', strtotime($stats['latest_created'])) : 'Never') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "<br>";
}

// Check business table
echo "<h2>4. Business User Associations</h2>";
try {
    $stmt = $pdo->prepare("
        SELECT 
            b.id as business_id,
            b.name as business_name,
            b.user_id,
            u.username
        FROM businesses b
        LEFT JOIN users u ON b.user_id = u.id
        ORDER BY b.id
    ");
    $stmt->execute();
    $businesses = $stmt->fetchAll();
    
    if (!empty($businesses)) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr style='background: #f1f1f1;'>";
        echo "<th>Business ID</th><th>Business Name</th><th>User ID</th><th>Username</th>";
        echo "</tr>";
        
        foreach ($businesses as $business) {
            echo "<tr>";
            echo "<td>" . $business['business_id'] . "</td>";
            echo "<td>" . htmlspecialchars($business['business_name']) . "</td>";
            echo "<td>" . $business['user_id'] . "</td>";
            echo "<td>" . htmlspecialchars($business['username']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "<br>";
}

// Show login info
echo "<h2>5. What You Need to Do</h2>";

if (!is_logged_in()) {
    echo "<div style='background: #d1ecf1; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "<h3>🔑 Login Required</h3>";
    echo "<p>You need to log in to see your QR codes. Here's what to do:</p>";
    echo "<ol>";
    echo "<li>Go to: <a href='https://revenueqr.sharedvaluevending.com/login.php' target='_blank'>Login Page</a></li>";
    echo "<li>Select <strong>'Business'</strong> as your role</li>";
    echo "<li>Enter your business credentials</li>";
    echo "<li>Then go back to: <a href='https://revenueqr.sharedvaluevending.com/qr_manager.php' target='_blank'>QR Manager</a></li>";
    echo "</ol>";
    echo "</div>";
} else {
    $user_business_id = $_SESSION['business_id'] ?? null;
    
    if ($user_business_id) {
        // Show QR codes for this specific business
        echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
        echo "<h3>✅ You're logged in as Business ID: $user_business_id</h3>";
        
        try {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as count, MAX(created_at) as latest
                FROM qr_codes 
                WHERE business_id = ? AND status = 'active'
            ");
            $stmt->execute([$user_business_id]);
            $user_qr_stats = $stmt->fetch();
            
            echo "<p>Your QR codes: " . $user_qr_stats['count'] . " active</p>";
            if ($user_qr_stats['latest']) {
                echo "<p>Latest QR created: " . date('M j, Y H:i', strtotime($user_qr_stats['latest'])) . "</p>";
            }
            
            // Show recent QR codes for this business
            $stmt = $pdo->prepare("
                SELECT id, code, qr_type, created_at
                FROM qr_codes 
                WHERE business_id = ? AND status = 'active'
                ORDER BY created_at DESC
                LIMIT 5
            ");
            $stmt->execute([$user_business_id]);
            $user_qr_codes = $stmt->fetchAll();
            
            if (!empty($user_qr_codes)) {
                echo "<h4>Your Recent QR Codes:</h4>";
                echo "<ul>";
                foreach ($user_qr_codes as $qr) {
                    echo "<li>" . $qr['qr_type'] . " - " . substr($qr['code'], 0, 15) . "... (" . date('M j H:i', strtotime($qr['created_at'])) . ")</li>";
                }
                echo "</ul>";
            } else {
                echo "<p>⚠️ No QR codes found for your business. Try generating some new ones!</p>";
            }
            
        } catch (Exception $e) {
            echo "<p>❌ Error checking your QR codes: " . $e->getMessage() . "</p>";
        }
        
        echo "</div>";
    }
}

// Show next steps
echo "<div style='background: #fff3cd; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
echo "<h3>📋 Next Steps</h3>";
echo "<ol>";
echo "<li><strong>If not logged in:</strong> Log in as business user first</li>";
echo "<li><strong>If logged in but no QR codes:</strong> Go to <a href='https://revenueqr.sharedvaluevending.com/qr-generator.php' target='_blank'>QR Generator</a> and create some</li>";
echo "<li><strong>If QR codes exist but don't show:</strong> Clear browser cache and try again</li>";
echo "<li><strong>Check this debug page again</strong> after generating new QR codes</li>";
echo "</ol>";
echo "</div>";

?> 