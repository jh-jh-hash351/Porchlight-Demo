<?php
$host = 'localhost';
$db   = 'porchlight_db';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    
    // 1. Roles
    $pdo->exec("CREATE TABLE IF NOT EXISTS roles (role_id INT AUTO_INCREMENT PRIMARY KEY, role_name VARCHAR(50) UNIQUE NOT NULL)");
    $pdo->exec("INSERT IGNORE INTO roles (role_name) VALUES ('Admin'), ('User')");
    
    // 2. Users
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (id INT AUTO_INCREMENT PRIMARY KEY, username VARCHAR(50) UNIQUE NOT NULL, email VARCHAR(100) UNIQUE NOT NULL, password VARCHAR(255) NOT NULL, status VARCHAR(20) DEFAULT 'Active', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
    
    // 3. Login Attempts (Crucial to stop "Database error occurred")
    $pdo->exec("CREATE TABLE IF NOT EXISTS login_attempts (id INT AUTO_INCREMENT PRIMARY KEY, ip_address VARCHAR(45) NOT NULL, username VARCHAR(50), success TINYINT(1) NOT NULL, attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
    
    // 4. Default Admin (Password: admin123)
    $passHash = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (username, email, password, status) VALUES (?, ?, ?, 'Active') ON DUPLICATE KEY UPDATE password = ?");
    $stmt->execute(['admin', 'admin@example.com', $passHash, $passHash]);
    
    echo "MySQL Database 'porchlight_db' has been fully repaired!\n";
    echo "Default User: admin / admin123\n";
} catch (Exception $e) {
    echo "CRITICAL ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
?>
