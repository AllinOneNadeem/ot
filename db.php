<?php
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error_log.txt');
error_reporting(E_ALL);
// ============================================================
// DATABASE CONNECTION (FIXED FOR EMOJIS & UTF8MB4)
// NOTE: Real password removed for public GitHub - set it on the server.
// ============================================================
$host = 'localhost';
$dbname = 'otplux_test';
$username = 'otplux_test';
$password = 'YOUR_DB_PASSWORD_HERE';

try {
    // charset=utf8 की जगह charset=utf8mb4 किया गया है ताकि इमोजी सपोर्ट मिल सके
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
    ]);
} catch (PDOException $e) {
    die("Database Connection Failed: " . $e->getMessage());
}
?>