<?php
require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/core/session.php';
require_once __DIR__ . '/core/auth.php';

echo "<h1>Session Status Check</h1>";

echo "<h2>Session Data:</h2>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

echo "<h2>Authentication Status:</h2>";
echo "<ul>";
echo "<li>is_logged_in(): " . (is_logged_in() ? "YES" : "NO") . "</li>";
echo "<li>has_role('business'): " . (has_role('business') ? "YES" : "NO") . "</li>";
echo "</ul>";

echo "<h2>Session Cookie:</h2>";
$session_cookie_name = session_name();
echo "<p>Session Cookie Name: " . $session_cookie_name . "</p>";
echo "<p>Session Cookie Value: " . ($_COOKIE[$session_cookie_name] ?? 'Not Set') . "</p>";

echo "<h2>Actions:</h2>";
echo "<p><a href='login.php'>Go to Login Page</a></p>";
echo "<p><a href='logout.php'>Logout</a></p>";
?> 