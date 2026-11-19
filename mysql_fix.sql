-- 1. Roles Table
CREATE TABLE IF NOT EXISTS roles (
    role_id INT AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(50) UNIQUE NOT NULL
);

INSERT IGNORE INTO roles (role_name) VALUES ('Admin'), ('User');

-- 2. Users Table (Updated to match login.php requirements)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    status VARCHAR(20) DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 3. Login Attempts Table (Needed for Rate Limiting in login.php)
CREATE TABLE IF NOT EXISTS login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    username VARCHAR(50),
    success TINYINT(1) NOT NULL,
    attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create a default Admin User (Password: admin123)
INSERT INTO users (username, email, password, status) 
VALUES ('admin', 'admin@example.com', '$2y$10$mS7pC9XG6H.S.FkP.L0SveQfP0K9GqO7jGk3zZ5z7z7z7z7z7z7z7', 'Active')
ON DUPLICATE KEY UPDATE status='Active';
