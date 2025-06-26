<?php
// Fix headers already sent issue
if (headers_sent()) {
    error_log("Headers already sent when trying to start session");
    return;
}

if (session_status() === PHP_SESSION_NONE) {
    session_name("revenueqr_session");
    session_set_cookie_params([
        "lifetime" => 0,
        "path" => "/",
        "domain" => "",
        "secure" => isset($_SERVER["HTTPS"]),
        "httponly" => true,
        "samesite" => "Lax"
    ]);
    session_start();
}

// Regenerate session ID periodically for security
if (!isset($_SESSION["last_regeneration"])) {
    $_SESSION["last_regeneration"] = time();
} elseif (time() - $_SESSION["last_regeneration"] > 300) { // 5 minutes
    session_regenerate_id(true);
    $_SESSION["last_regeneration"] = time();
}
?>