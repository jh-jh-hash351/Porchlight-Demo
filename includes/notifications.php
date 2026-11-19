<?php
require_once __DIR__ . '/communities.php';

function ensure_notifications_schema(PDO $pdo): void {
    static $ensured = false;
    if ($ensured) {
        return;
    }

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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $ensured = true;
}

/**
 * Creates a notification for $userId about something $actorId did.
 * Duplicate action notifications are replaced so repeated clicks stay tidy.
 */
function notification_create(PDO $pdo, int $userId, int $actorId, string $type, int $entityId = 0): void {
    if ($userId <= 0 || $actorId <= 0 || $userId === $actorId) {
        return;
    }

    ensure_notifications_schema($pdo);

    $replaceable = ['like', 'friend_request', 'friend_accept', 'group_request', 'group_approved', 'group_declined'];
    if (in_array($type, $replaceable, true)) {
        $stmt = $pdo->prepare('
            DELETE FROM notifications
            WHERE user_id = ? AND actor_id = ? AND type = ? AND entity_id = ?
        ');
        $stmt->execute([$userId, $actorId, $type, $entityId]);
    }

    $stmt = $pdo->prepare('
        INSERT INTO notifications (user_id, actor_id, type, entity_id)
        VALUES (?, ?, ?, ?)
    ');
    $stmt->execute([$userId, $actorId, $type, $entityId]);
}

function notification_delete(PDO $pdo, int $userId, int $actorId, string $type, int $entityId = 0): void {
    if ($userId <= 0 || $actorId <= 0) {
        return;
    }

    ensure_notifications_schema($pdo);

    $stmt = $pdo->prepare('
        DELETE FROM notifications
        WHERE user_id = ? AND actor_id = ? AND type = ? AND entity_id = ?
    ');
    $stmt->execute([$userId, $actorId, $type, $entityId]);
}

function notification_clear_post(PDO $pdo, int $postId): void {
    if ($postId <= 0) {
        return;
    }

    ensure_notifications_schema($pdo);

    $stmt = $pdo->prepare("DELETE FROM notifications WHERE entity_id = ? AND type IN ('like', 'comment')");
    $stmt->execute([$postId]);
}

function notification_clear_group_requests(PDO $pdo, int $communityId, int $userId): void {
    if ($communityId <= 0 || $userId <= 0) {
        return;
    }

    ensure_notifications_schema($pdo);

    $stmt = $pdo->prepare("
        DELETE FROM notifications
        WHERE type = 'group_request' AND entity_id = ? AND actor_id = ?
    ");
    $stmt->execute([$communityId, $userId]);
}

function count_unread_notifications(PDO $pdo, int $userId): int {
    if ($userId <= 0) {
        return 0;
    }

    ensure_notifications_schema($pdo);

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL');
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}

function get_notifications(PDO $pdo, int $userId, int $limit = 100): array {
    ensure_notifications_schema($pdo);
    ensure_communities_schema($pdo);

    $limit = max(1, min(200, $limit));
    $stmt = $pdo->prepare("
        SELECT n.*, u.username AS actor_username, u.profile_pic AS actor_pic,
               c.name AS group_name
        FROM notifications n
        LEFT JOIN users u ON u.id = n.actor_id
        LEFT JOIN communities c ON c.id = n.entity_id
            AND n.type IN ('group_request', 'group_approved', 'group_declined')
        WHERE n.user_id = ?
        ORDER BY n.id DESC
        LIMIT $limit
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function notification_mark_all_read(PDO $pdo, int $userId): void {
    if ($userId <= 0) {
        return;
    }

    ensure_notifications_schema($pdo);

    $stmt = $pdo->prepare('UPDATE notifications SET read_at = NOW() WHERE user_id = ? AND read_at IS NULL');
    $stmt->execute([$userId]);
}
?>
