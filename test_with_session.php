<?php
session_start();

// Simulate logged-in user
$_SESSION['user_id'] = 1; // Use the test user we found
$_SESSION['username'] = 'sharedvaluevending';

echo "=== Session-Aware Purchase Test ===\n";
echo "User ID: " . $_SESSION['user_id'] . "\n";
echo "Session ID: " . session_id() . "\n\n";

// Test the purchase API endpoint
$purchase_data = [
    'item_id' => 8, // From our backend test
    'machine_id' => 'test',
    'source' => 'session_test'
];

echo "Testing purchase with data: " . json_encode($purchase_data) . "\n\n";

// Simulate the API call
$_POST = []; // Clear POST
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['CONTENT_TYPE'] = 'application/json';
file_put_contents('php://input', json_encode($purchase_data));

// Capture output
ob_start();

try {
    // Include the API file
    include 'html/api/purchase-discount.php';
    $output = ob_get_contents();
    
    echo "API Response:\n";
    echo $output . "\n";
    
    // Try to decode the response
    $result = json_decode($output, true);
    if ($result) {
        if ($result['success']) {
            echo "✅ Purchase successful!\n";
            echo "Discount Code: " . $result['discount_code'] . "\n";
        } else {
            echo "❌ Purchase failed: " . ($result['error'] ?? 'Unknown error') . "\n";
        }
    } else {
        echo "❌ Invalid JSON response\n";
    }
    
} catch (Exception $e) {
    echo "❌ Exception: " . $e->getMessage() . "\n";
}

ob_end_clean();
?> 