<?php
// config/database.php

/**
 * --------------------------------------------------------------------------
 * Database Configuration (Local XAMPP & Cloud Hosting like InfinityFree)
 * --------------------------------------------------------------------------
 * When running locally on XAMPP, this automatically connects to localhost.
 * When deploying to InfinityFree:
 * Replace the values below with your InfinityFree MySQL details from your control panel:
 */
// Check if actually running on InfinityFree hosting server
$is_infinityfree = (
    strpos(__DIR__, 'infinityfree') !== false ||
    strpos($_SERVER['DOCUMENT_ROOT'] ?? '', 'infinityfree') !== false ||
    strpos($_SERVER['DOCUMENT_ROOT'] ?? '', '/home/vol') !== false
);

if ($is_infinityfree) {
    // Production / InfinityFree Cloud Database
    define('DB_HOST', 'sql104.infinityfree.com');
    define('DB_USER', 'if0_42913503');
    define('DB_PASS', '9UDdNkxpEoOT4U');
    define('DB_NAME', 'if0_42913503_smartface');
    define('DB_PORT', '3306');
} else {
    // Read environment variables (Render, Railway, Docker, or Local XAMPP)
    $env_host = trim(getenv('DB_HOST') ?: '');
    $env_user = trim(getenv('DB_USER') ?: '');
    $env_pass = trim(getenv('DB_PASS') ?: '');
    $env_name = trim(getenv('DB_NAME') ?: '');
    $env_port = trim(getenv('DB_PORT') ?: '');

    // If an environment variable is an unresolved template like ${{MySQL.MYSQLHOST}} or empty, fall back to localhost
    $db_host = (empty($env_host) || strpos($env_host, '${{') !== false) ? 'localhost' : $env_host;
    $db_user = (empty($env_user) || strpos($env_user, '${{') !== false) ? 'root' : $env_user;
    $db_pass = (strpos($env_pass, '${{') !== false) ? '' : $env_pass;
    $db_name = (empty($env_name) || strpos($env_name, '${{') !== false) ? 'smartface_attendance' : $env_name;
    $db_port = (empty($env_port) || strpos($env_port, '${{') !== false) ? '3306' : $env_port;

    define('DB_HOST', $db_host);
    define('DB_USER', $db_user);
    define('DB_PASS', $db_pass);
    define('DB_NAME', $db_name);
    define('DB_PORT', $db_port);
}

class Database {
    private static $instance = null;
    private $conn;

    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            $this->conn = new PDO($dsn, DB_USER, DB_PASS, $options);
            $this->conn->exec("SET time_zone = '+08:00'");
        } catch (PDOException $e) {
            die("Database Connection Error: " . $e->getMessage());
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance->conn;
    }
}

function getDB() {
    return Database::getInstance();
}
?>
