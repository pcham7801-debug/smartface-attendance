<?php
// config/config.php

ob_start();

// Application Timezone
date_default_timezone_set('Asia/Manila');

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    session_start();
}

define('SITE_NAME', 'SmartFace Attendance Monitoring System');
define('APP_ROOT', dirname(__DIR__));

/**
 * Robust Base URL Helper
 * Automatically detects the application root directory relative to web root
 */
function baseUrl($path = '') {
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $dir = str_replace('\\', '/', dirname($scriptName));
    
    // Strip subfolder routes (admin, student, auth, api) to locate base app folder
    $base = preg_replace('#/(admin|student|auth|api)(/.*)?$#i', '', $dir);
    if ($base === '/' || $base === '\\') {
        $base = '';
    }
    
    return rtrim($base, '/') . '/' . ltrim($path, '/');
}

// Generate CSRF token
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Verify CSRF token
function verifyCsrfToken($token) {
    if (isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token)) {
        return true;
    }
    return false;
}

// Flash Message helper
function setFlash($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type, // success, danger, warning, info
        'message' => $message
    ];
}

function displayFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        echo '<div class="alert alert-' . htmlspecialchars($flash['type']) . ' alert-dismissible fade show" role="alert">
                ' . htmlspecialchars($flash['message']) . '
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
              </div>';
    }
}

// Escaping helper
function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

// Get system setting from database
function getSetting($key, $default = '') {
    require_once __DIR__ . '/database.php';
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $res = $stmt->fetch();
        return $res ? $res['setting_value'] : $default;
    } catch (Exception $ex) {
        return $default;
    }
}
?>
