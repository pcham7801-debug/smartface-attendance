<?php
// index.php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/auth.php';

if (isLoggedIn()) {
    if (isAdmin()) {
        header('Location: ' . baseUrl('admin/dashboard.php'));
    } else {
        header('Location: ' . baseUrl('student/dashboard.php'));
    }
} else {
    header('Location: ' . baseUrl('auth/login.php'));
}
exit();
?>
