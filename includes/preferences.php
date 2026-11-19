<?php
/**
 * Per-user preferences behind the Security, Location & Region, Likes and Posts
 * settings sections. Sensible defaults are returned until a user saves changes.
 */

function ensure_preferences_schema(PDO $pdo): void {
    static $ensured = false;
    if ($ensured) {
        return;
    }

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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $hasVisibility = $pdo->query("SHOW COLUMNS FROM posts LIKE 'visibility'")->fetch();
    if (!$hasVisibility) {
        $pdo->exec("ALTER TABLE posts ADD COLUMN visibility VARCHAR(20) NOT NULL DEFAULT 'public'");
    }

    $ensured = true;
}

function default_preferences(): array {
    return [
        'city' => '',
        'region' => '',
        'show_location' => 1,
        'hide_like_counts' => 0,
        'allow_likes' => 1,
        'can_comment' => 'everyone',
        'default_visibility' => 'public',
    ];
}

function get_preferences(PDO $pdo, ?int $userId): array {
    ensure_preferences_schema($pdo);

    $prefs = default_preferences();
    if (!$userId) {
        return $prefs;
    }

    $stmt = $pdo->prepare('SELECT * FROM user_preferences WHERE user_id = ?');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();

    if ($row) {
        unset($row['user_id'], $row['updated_at']);
        $prefs = array_merge($prefs, $row);
    }

    return $prefs;
}

function save_preferences(PDO $pdo, int $userId, array $changes): void {
    $prefs = array_merge(get_preferences($pdo, $userId), $changes);

    $stmt = $pdo->prepare('
        INSERT INTO user_preferences (user_id, city, region, show_location, hide_like_counts, allow_likes, can_comment, default_visibility)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            city = VALUES(city),
            region = VALUES(region),
            show_location = VALUES(show_location),
            hide_like_counts = VALUES(hide_like_counts),
            allow_likes = VALUES(allow_likes),
            can_comment = VALUES(can_comment),
            default_visibility = VALUES(default_visibility)
    ');
    $stmt->execute([
        $userId,
        (string)$prefs['city'],
        (string)$prefs['region'],
        (int)$prefs['show_location'],
        (int)$prefs['hide_like_counts'],
        (int)$prefs['allow_likes'],
        (string)$prefs['can_comment'],
        (string)$prefs['default_visibility'],
    ]);
}

function can_viewer_like(array $authorPrefs, bool $isAuthor): bool {
    return $isAuthor || (int)($authorPrefs['allow_likes'] ?? 1) === 1;
}

function can_viewer_comment(array $authorPrefs, bool $isAuthor): bool {
    $policy = $authorPrefs['can_comment'] ?? 'everyone';
    if ($policy === 'nobody') {
        return false;
    }
    return $isAuthor || $policy === 'everyone';
}
