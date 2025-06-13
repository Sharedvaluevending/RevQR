<?php
require_once __DIR__ . '/core/csrf.php';

echo "<h1>CSRF Token Debug</h1>";

// Display session information
echo "<h2>Session Information</h2>";
echo "Session Status: " . (session_status() === PHP_SESSION_ACTIVE ? 'Active' : 'Inactive') . "<br>";
echo "Session ID: " . session_id() . "<br>";
echo "Session Data: <pre>" . print_r($_SESSION, true) . "</pre>";

// Generate and display token
echo "<h2>CSRF Token</h2>";
$token = generate_csrf_token();
echo "Generated Token: " . $token . "<br>";
echo "Token Length: " . strlen($token) . "<br>";

// Test form
echo "<h2>Test Form</h2>";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<h3>POST Data Received</h3>";
    echo "<pre>" . print_r($_POST, true) . "</pre>";
    
    try {
        $result = verify_csrf_token();
        echo "<div style='color: green;'>CSRF Token Validation: SUCCESS</div>";
    } catch (Exception $e) {
        echo "<div style='color: red;'>CSRF Token Validation: FAILED - " . $e->getMessage() . "</div>";
    }
}
?>

<form method="POST">
    <input type="hidden" name="csrf_token" value="<?php echo $token; ?>">
    <input type="text" name="test_field" placeholder="Test input" value="<?php echo $_POST['test_field'] ?? ''; ?>">
    <button type="submit">Test CSRF</button>
</form>

<h2>Instructions</h2>
<p>This page helps debug CSRF token issues:</p>
<ul>
    <li>Check if session is active and has proper session ID</li>
    <li>Verify CSRF token is generated correctly</li>
    <li>Test form submission with CSRF token</li>
    <li>View detailed error information</li>
</ul> 