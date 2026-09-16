<?php
require_once __DIR__ . "/../config/config.php";
require_once __DIR__ . "/../config/database.php";

header("Content-Type: application/json");

try {
    $db = getDB();
    $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    
    $users = [];
    if (in_array("users", $tables)) {
        $users = $db->query("SELECT id, username, role, first_name, last_name, face_registered FROM users")->fetchAll(PDO::FETCH_ASSOC);
    }
    
    echo json_encode([
        "status" => "ok",
        "db_host" => DB_HOST,
        "db_name" => DB_NAME,
        "tables" => $tables,
        "users_count" => count($users),
        "users" => $users
    ], JSON_PRETTY_PRINT);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
