<?php
$host = 'localhost';
$db   = 'porchlight_db';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    
    // Fix Users Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (id INT AUTO_INCREMENT PRIMARY KEY, username VARCHAR(50) UNIQUE NOT NULL, email VARCHAR(100) UNIQUE NOT NULL, password VARCHAR(255) NOT NULL, status VARCHAR(20) DEFAULT 'Active', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
    
    $columns = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('status', $columns)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN status VARCHAR(20) DEFAULT 'Active'");
    }
    if (!in_array('email', $columns)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN email VARCHAR(100) UNIQUE NOT NULL DEFAULT 'temp@example.com'");
    }

    // Fix Login Attempts Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS login_attempts (id INT AUTO_INCREMENT PRIMARY KEY, ip_address VARCHAR(45) NOT NULL, username VARCHAR(50), success TINYINT(1) NOT NULL, attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
    
    // Fix Roles Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS roles (role_id INT AUTO_INCREMENT PRIMARY KEY, role_name VARCHAR(50) UNIQUE NOT NULL)");
    $pdo->exec("INSERT IGNORE INTO roles (role_name) VALUES ('Admin'), ('User')");
    
    // Create/Update Admin (Password: admin123)
    $passHash = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (username, email, password, status) VALUES (?, ?, ?, 'Active') ON DUPLICATE KEY UPDATE password = ?");
    $stmt->execute(['admin', 'admin@example.com', $passHash, $passHash]);
    
    echo "SUCCESS: MySQL Database 'porchlight_db' is now fully repaired!\n";
    echo "Credentials: admin / admin123\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
?>
