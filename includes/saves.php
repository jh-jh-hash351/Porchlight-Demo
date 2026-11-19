<?php
/**
 * Booksave helpers: users can save posts and find them on the Booksave page.
 */

function ensure_saves_schema(PDO $pdo): void {
    static $ensured = false;
    if ($ensured) {
        return;
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS saves (
        user_id INT NOT NULL,
        post_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (user_id, post_id),
        INDEX idx_saves_user (user_id, created_at),
        CONSTRAINT fk_saves_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        CONSTRAINT fk_saves_post FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $ensured = true;
}

function is_post_saved(PDO $pdo, int $userId, int $postId): bool {
    ensure_saves_schema($pdo);

    $stmt = $pdo->prepare('SELECT 1 FROM saves WHERE user_id = ? AND post_id = ?');
    $stmt->execute([$userId, $postId]);
    return (bool)$stmt->fetch();
}

/**
 * Saves or unsaves a post. Returns the new saved state.
 */
function toggle_save(PDO $pdo, int $userId, int $postId): bool {
    ensure_saves_schema($pdo);

    if (is_post_saved($pdo, $userId, $postId)) {
        $stmt = $pdo->prepare('DELETE FROM saves WHERE user_id = ? AND post_id = ?');
        $stmt->execute([$userId, $postId]);
        return false;
    }

    $stmt = $pdo->prepare('INSERT IGNORE INTO saves (user_id, post_id) VALUES (?, ?)');
    $stmt->execute([$userId, $postId]);
    return true;
}
?>
