<?php
// auth/logout.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/auth.php';

if (isLoggedIn()) {
    logAudit('User Logout', "User " . ($_SESSION['username'] ?? '') . " logged out.");
}

$_SESSION = array();

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

session_start();
setFlash('info', 'You have been logged out successfully.');
header('Location: ' . baseUrl('auth/login.php'));
exit();
?>
