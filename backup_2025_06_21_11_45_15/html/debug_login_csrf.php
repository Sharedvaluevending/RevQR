<?php
require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/core/session.php';
require_once __DIR__ . '/core/csrf.php';

echo "<h1>Login CSRF Debug</h1>";

// Display detailed session and environment information
echo "<h2>Environment Check</h2>";
echo "HTTPS: " . (isset($_SERVER['HTTPS']) ? 'Yes' : 'No') . "<br>";
echo "Session Status: " . (session_status() === PHP_SESSION_ACTIVE ? 'Active' : 'Inactive') . "<br>";
echo "Session ID: " . session_id() . "<br>";
echo "Session Name: " . session_name() . "<br>";
echo "Cookie Secure Setting: " . ini_get('session.cookie_secure') . "<br>";
echo "Cookie HttpOnly Setting: " . ini_get('session.cookie_httponly') . "<br>";

echo "<h2>Session Data</h2>";
echo "<pre>" . print_r($_SESSION, true) . "</pre>";

echo "<h2>CSRF Token Test</h2>";
$token = generate_csrf_token();
echo "Generated Token: " . $token . "<br>";
echo "Token in Session: " . ($_SESSION['csrf_token'] ?? 'NOT SET') . "<br>";
echo "Tokens Match: " . ($token === ($_SESSION['csrf_token'] ?? '') ? 'Yes' : 'No') . "<br>";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<h2>POST Request Debug</h2>";
    echo "POST CSRF Token: " . ($_POST['csrf_token'] ?? 'NOT PROVIDED') . "<br>";
    echo "Session CSRF Token: " . ($_SESSION['csrf_token'] ?? 'NOT SET') . "<br>";
    
    if (isset($_POST['csrf_token']) && isset($_SESSION['csrf_token'])) {
        echo "Validation Result: " . (validate_csrf_token($_POST['csrf_token']) ? 'VALID' : 'INVALID') . "<br>";
    } else {
        echo "Validation Result: CANNOT TEST - Missing tokens<br>";
    }
}

echo "<h2>Test Form</h2>";
?>

<form method="POST" style="border: 1px solid #ccc; padding: 10px; margin: 10px 0;">
    <input type="hidden" name="csrf_token" value="<?php echo $token; ?>">
    <input type="text" name="test_username" placeholder="Test Username" value="test">
    <input type="password" name="test_password" placeholder="Test Password" value="test">
    <select name="test_role">
        <option value="business">Business</option>
        <option value="admin">Admin</option>
        <option value="user">User</option>
    </select>
    <button type="submit">Test Login Form</button>
</form>

<h2>Instructions</h2>
<ul>
    <li>Check if HTTPS is enabled (required for secure cookies)</li>
    <li>Verify session is active with valid ID</li>
    <li>Confirm CSRF token is generated and stored</li>
    <li>Test form submission to see validation results</li>
    <li>If tokens don't match, there's a session issue</li>
</ul>

<p><a href="/html/login.php">Back to Login Page</a></p> 