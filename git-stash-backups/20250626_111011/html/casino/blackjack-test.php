<?php
// Test version without login check
require_once __DIR__ . '/../core/config.php';

echo "<h1>Blackjack Test Page</h1>";
echo "<p>If you can see this, the file is being accessed correctly.</p>";
echo "<p>Location ID: " . ($_GET['location_id'] ?? 'none') . "</p>";
echo "<p>Current URL: " . $_SERVER['REQUEST_URI'] . "</p>";
echo "<p>Session Status: " . session_status() . "</p>";

session_start();
echo "<p>User ID in session: " . ($_SESSION['user_id'] ?? 'NOT SET') . "</p>";

echo "<h3>All GET Parameters:</h3>";
echo "<pre>" . print_r($_GET, true) . "</pre>";

echo "<h3>All SERVER vars:</h3>";
echo "<pre>" . print_r($_SERVER, true) . "</pre>";
?> 