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
    define('DB_HOST', getenv('DB_HOST') ?: 'sql104.infinityfree.com');
    define('DB_USER', getenv('DB_USER') ?: 'if0_42913503');
    define('DB_PASS', getenv('DB_PASS') ?: '9UDdNkxpEoOT4U');
    define('DB_NAME', getenv('DB_NAME') ?: 'if0_42913503_smartface');
    define('DB_PORT', getenv('DB_PORT') ?: '3306');
} else {
    // Running on local machine (XAMPP localhost, Wi-Fi IP, or Cloudflare tunnel)
    define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
    define('DB_USER', getenv('DB_USER') ?: 'root');
    define('DB_PASS', getenv('DB_PASS') ?: '');
    define('DB_NAME', getenv('DB_NAME') ?: 'smartface_attendance');
    define('DB_PORT', getenv('DB_PORT') ?: '3306');
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
