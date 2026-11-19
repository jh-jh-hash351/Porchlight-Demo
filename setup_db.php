<?php
$host = 'localhost';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS porchlight_db");
    $pdo->exec("USE porchlight_db");

    echo "Repairing database tables...<br>";

    // 1. Roles Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS roles (role_id INT AUTO_INCREMENT PRIMARY KEY, role_name VARCHAR(50) UNIQUE NOT NULL)");
    $pdo->exec("INSERT IGNORE INTO roles (role_name) VALUES ('Admin'), ('User')");

    // 2. Users Table (Added profile_pic column to fix the current error)
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        status VARCHAR(20) DEFAULT 'Active',
        profile_pic VARCHAR(255) DEFAULT NULL,
        username_changed_at DATETIME NULL DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // In case the table already existed without the column, we add it manually
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN profile_pic VARCHAR(255) DEFAULT NULL");
    } catch (Exception $e) {
        // Column already exists, ignore error
    }

    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN username_changed_at DATETIME NULL DEFAULT NULL");
    } catch (Exception $e) {
        // Column already exists, ignore error
    }

    // 3. Posts Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS posts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        content TEXT NOT NULL,
        image_path VARCHAR(255),
        image VARCHAR(255),
        media_type VARCHAR(10) NOT NULL DEFAULT 'image',
        visibility VARCHAR(20) NOT NULL DEFAULT 'public',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    try {
        $pdo->exec("ALTER TABLE posts ADD COLUMN visibility VARCHAR(20) NOT NULL DEFAULT 'public'");
    } catch (Exception $e) {
        // Column already exists, ignore error
    }

    try {
        $pdo->exec("ALTER TABLE posts ADD COLUMN media_type VARCHAR(10) NOT NULL DEFAULT 'image'");
    } catch (Exception $e) {
        // Column already exists, ignore error
    }

    // 3b. User Preferences (Security, Location, Likes, Posts settings)
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_preferences (
        user_id INT NOT NULL PRIMARY KEY,
        city VARCHAR(100) NOT NULL DEFAULT '',
        region VARCHAR(100) NOT NULL DEFAULT '',
        show_location TINYINT(1) NOT NULL DEFAULT 1,
        hide_like_counts TINYINT(1) NOT NULL DEFAULT 0,
        allow_likes TINYINT(1) NOT NULL DEFAULT 1,
        can_comment VARCHAR(20) NOT NULL DEFAULT 'everyone',
        default_visibility VARCHAR(20) NOT NULL DEFAULT 'public',
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT fk_user_preferences_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    // 4. Comments Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS comments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        post_id INT NOT NULL,
        user_id INT NOT NULL,
        comment_text TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    // 5. Likes Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS likes (
        user_id INT NOT NULL,
        post_id INT NOT NULL,
        PRIMARY KEY (user_id, post_id),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
    )");

    // 6. Follows Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS follows (
        follower_id INT NOT NULL,
        following_id INT NOT NULL,
        PRIMARY KEY (follower_id, following_id),
        FOREIGN KEY (follower_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (following_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    // 7. Login Attempts Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS login_attempts (id INT AUTO_INCREMENT PRIMARY KEY, ip_address VARCHAR(45) NOT NULL, username VARCHAR(50), success TINYINT(1) NOT NULL, attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");

    // 8. Messages Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sender_id INT NOT NULL,
        recipient_id INT NOT NULL,
        body TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        read_at TIMESTAMP NULL DEFAULT NULL,
        INDEX idx_messages_pair (sender_id, recipient_id),
        INDEX idx_messages_recipient (recipient_id, read_at),
        FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (recipient_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    // 9. Friendships Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS friendships (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_low INT NOT NULL,
        user_high INT NOT NULL,
        requested_by INT NOT NULL,
        status ENUM('pending', 'accepted', 'declined') NOT NULL DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        responded_at TIMESTAMP NULL DEFAULT NULL,
        UNIQUE KEY uniq_friend_pair (user_low, user_high),
        INDEX idx_friends_status (status),
        CONSTRAINT fk_friends_low FOREIGN KEY (user_low) REFERENCES users(id) ON DELETE CASCADE,
        CONSTRAINT fk_friends_high FOREIGN KEY (user_high) REFERENCES users(id) ON DELETE CASCADE
    )");

    // 10. Saves (Booksave) Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS saves (
        user_id INT NOT NULL,
        post_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (user_id, post_id),
        INDEX idx_saves_user (user_id, created_at),
        CONSTRAINT fk_saves_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        CONSTRAINT fk_saves_post FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
    )");

    // 11. Communities Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS communities (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(80) NOT NULL,
        description TEXT NOT NULL,
        join_policy ENUM('open', 'approval') NOT NULL DEFAULT 'open',
        created_by INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_community_name (name),
        CONSTRAINT fk_communities_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
    )");

    // 12. Community Members Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS community_members (
        community_id INT NOT NULL,
        user_id INT NOT NULL,
        role ENUM('admin', 'member') NOT NULL DEFAULT 'member',
        status ENUM('active', 'pending') NOT NULL DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (community_id, user_id),
        INDEX idx_cm_user (user_id, status),
        INDEX idx_cm_status (community_id, status),
        CONSTRAINT fk_cm_community FOREIGN KEY (community_id) REFERENCES communities(id) ON DELETE CASCADE,
        CONSTRAINT fk_cm_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    // 13. Chat Groups Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS chat_groups (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(80) NOT NULL,
        created_by INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_chat_groups_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
    )");

    // 14. Chat Group Members Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS chat_group_members (
        group_id INT NOT NULL,
        user_id INT NOT NULL,
        last_read_message_id INT NOT NULL DEFAULT 0,
        joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (group_id, user_id),
        INDEX idx_cgm_user (user_id),
        CONSTRAINT fk_cgm_group FOREIGN KEY (group_id) REFERENCES chat_groups(id) ON DELETE CASCADE,
        CONSTRAINT fk_cgm_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    // 15. Chat Group Messages Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS chat_group_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        group_id INT NOT NULL,
        sender_id INT NOT NULL,
        body TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_cgmessages_group (group_id, id),
        CONSTRAINT fk_cgmessages_group FOREIGN KEY (group_id) REFERENCES chat_groups(id) ON DELETE CASCADE,
        CONSTRAINT fk_cgmessages_sender FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    // 16. Notifications Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        actor_id INT NULL,
        type VARCHAR(32) NOT NULL,
        entity_id INT NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        read_at DATETIME NULL DEFAULT NULL,
        INDEX idx_notifications_user (user_id, read_at, id),
        INDEX idx_notifications_entity (type, entity_id),
        CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        CONSTRAINT fk_notifications_actor FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL
    )");

    // Default Admin User
    $passHash = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (username, email, password, status) VALUES (?, ?, ?, 'Active') ON DUPLICATE KEY UPDATE password = ?");
    $stmt->execute(['admin', 'admin@example.com', $passHash, $passHash]);

    echo "<strong style='color: green; font-size: 1.2em;'>Success! All tables and columns fixed. You can now refresh your website.</strong>";
} catch (Exception $e) {
    echo "<strong style='color: red; font-size: 1.2em;'>CRITICAL ERROR:</strong> " . $e->getMessage();
}
?>
