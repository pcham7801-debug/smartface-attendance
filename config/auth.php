<?php
// config/auth.php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function getUserRole() {
    return $_SESSION['role'] ?? null;
}

function isAdmin() {
    return isLoggedIn() && getUserRole() === 'admin';
}

function isStudent() {
    return isLoggedIn() && getUserRole() === 'student';
}

function requireLogin() {
    if (!isLoggedIn()) {
        setFlash('danger', 'Please log in to access this page.');
        header('Location: ' . baseUrl('auth/login.php'));
        exit();
    }
}

function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        setFlash('danger', 'Unauthorized access! Administrator rights required.');
        header('Location: ' . baseUrl('student/dashboard.php'));
        exit();
    }
}

function requireStudent() {
    requireLogin();
    if (!isStudent()) {
        setFlash('danger', 'Unauthorized access! Student area only.');
        header('Location: ' . baseUrl('admin/dashboard.php'));
        exit();
    }
}

// Log actions to audit_logs
function logAudit($action, $description = '') {
    try {
        $db = getDB();
        $userId = $_SESSION['user_id'] ?? null;
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $stmt = $db->prepare("INSERT INTO audit_logs (user_id, action, description, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$userId, $action, $description, $ip]);
    } catch (Exception $e) {
        // Silently handle audit log errors
    }
}
?>
