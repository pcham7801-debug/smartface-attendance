<?php
// admin/index.php
// Redirects to admin dashboard if logged in, otherwise to admin login portal
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/auth.php';

if (isLoggedIn()) {
    if (isAdmin()) {
        header('Location: ' . baseUrl('admin/dashboard.php'));
    } else {
        header('Location: ' . baseUrl('student/dashboard.php'));
    }
    exit();
}

header('Location: ' . baseUrl('admin/login.php'));
exit();
