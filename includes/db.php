<?php
/**
 * Database connection helper - Configured for MySQL (phpMyAdmin)
 */

// Database configuration
$host = 'localhost';
$db   = 'porchlight_db';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

// Secure Session Configuration
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => false,
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    session_start();
}

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // This is where the "Database error occurred" comes from
    die("Database connection failed: " . $e->getMessage());
}
?>
