<?php
/**
 * Friend request helpers.
 * One row per pair of users, stored canonically (user_low < user_high).
 */

function ensure_friends_schema(PDO $pdo): void {
    static $ensured = false;
    if ($ensured) {
        return;
    }

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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $ensured = true;
}

function friend_pair(int $a, int $b): array {
    return $a < $b ? [$a, $b] : [$b, $a];
}

function friendship_row(PDO $pdo, int $a, int $b): ?array {
    ensure_friends_schema($pdo);
    [$low, $high] = friend_pair($a, $b);

    $stmt = $pdo->prepare('SELECT * FROM friendships WHERE user_low = ? AND user_high = ?');
    $stmt->execute([$low, $high]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Returns: 'friends' | 'incoming' | 'outgoing' | 'none' | 'self'
 */
function friend_status(PDO $pdo, int $viewerId, int $otherId): string {
    if ($viewerId === $otherId) {
        return 'self';
    }

    $row = friendship_row($pdo, $viewerId, $otherId);
    if (!$row || $row['status'] === 'declined') {
        return 'none';
    }
    if ($row['status'] === 'accepted') {
        return 'friends';
    }
    return (int)$row['requested_by'] === $viewerId ? 'outgoing' : 'incoming';
}

/**
 * Same as friend_status() but for many users in one query.
 * Returns [userId => status].
 */
function friend_status_map(PDO $pdo, int $viewerId, array $userIds): array {
    ensure_friends_schema($pdo);

    $ids = array_values(array_unique(array_map('intval', $userIds)));
    $map = [];
    foreach ($ids as $id) {
        $map[$id] = 'none';
    }
    if (!$ids) {
        return $map;
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("
        SELECT *
        FROM friendships
        WHERE (user_low = ? AND user_high IN ($placeholders))
           OR (user_high = ? AND user_low IN ($placeholders))
    ");
    $stmt->execute(array_merge([$viewerId], $ids, [$viewerId], $ids));

    foreach ($stmt->fetchAll() as $row) {
        if ($row['status'] === 'declined') {
            continue;
        }
        $other = (int)$row['user_low'] === $viewerId ? (int)$row['user_high'] : (int)$row['user_low'];
        if ($row['status'] === 'accepted') {
            $map[$other] = 'friends';
        } else {
            $map[$other] = (int)$row['requested_by'] === $viewerId ? 'outgoing' : 'incoming';
        }
    }

    return $map;
}

function are_friends(PDO $pdo, int $a, int $b): bool {
    return friend_status($pdo, $a, $b) === 'friends';
}

function send_friend_request(PDO $pdo, int $fromId, int $toId): string {
    ensure_friends_schema($pdo);
    [$low, $high] = friend_pair($fromId, $toId);

    $row = friendship_row($pdo, $fromId, $toId);
    if ($row && $row['status'] === 'accepted') {
        return 'friends';
    }
    if ($row && $row['status'] === 'pending') {
        return (int)$row['requested_by'] === $fromId ? 'outgoing' : 'incoming';
    }

    $stmt = $pdo->prepare("
        INSERT INTO friendships (user_low, user_high, requested_by, status)
        VALUES (?, ?, ?, 'pending')
        ON DUPLICATE KEY UPDATE
            requested_by = VALUES(requested_by),
            status = 'pending',
            created_at = NOW(),
            responded_at = NULL
    ");
    $stmt->execute([$low, $high, $fromId]);

    return 'outgoing';
}

function respond_friend_request(PDO $pdo, int $meId, int $otherId, bool $accept): string {
    $row = friendship_row($pdo, $meId, $otherId);
    if (!$row || $row['status'] !== 'pending') {
        return friend_status($pdo, $meId, $otherId);
    }
    if ((int)$row['requested_by'] === $meId) {
        return 'outgoing';
    }

    $stmt = $pdo->prepare('UPDATE friendships SET status = ?, responded_at = NOW() WHERE id = ?');
    $stmt->execute([$accept ? 'accepted' : 'declined', $row['id']]);

    return $accept ? 'friends' : 'none';
}

function cancel_friend_request(PDO $pdo, int $meId, int $otherId): string {
    $row = friendship_row($pdo, $meId, $otherId);
    if ($row && $row['status'] === 'pending' && (int)$row['requested_by'] === $meId) {
        $stmt = $pdo->prepare('DELETE FROM friendships WHERE id = ?');
        $stmt->execute([$row['id']]);
    }
    return friend_status($pdo, $meId, $otherId);
}

function get_friends(PDO $pdo, int $userId): array {
    ensure_friends_schema($pdo);

    $stmt = $pdo->prepare("
        SELECT u.id, u.username, u.profile_pic
        FROM friendships f
        JOIN users u ON u.id = IF(f.user_low = ?, f.user_high, f.user_low)
        WHERE (f.user_low = ? OR f.user_high = ?)
          AND f.status = 'accepted'
          AND u.status = 'Active'
        ORDER BY u.username ASC
    ");
    $stmt->execute([$userId, $userId, $userId]);
    return $stmt->fetchAll();
}

function get_incoming_requests(PDO $pdo, int $userId): array {
    ensure_friends_schema($pdo);

    $stmt = $pdo->prepare("
        SELECT f.id AS request_id, f.created_at AS requested_at,
               u.id, u.username, u.profile_pic
        FROM friendships f
        JOIN users u ON u.id = IF(f.user_low = ?, f.user_high, f.user_low)
        WHERE (f.user_low = ? OR f.user_high = ?)
          AND f.status = 'pending'
          AND f.requested_by <> ?
          AND u.status = 'Active'
        ORDER BY f.created_at DESC
    ");
    $stmt->execute([$userId, $userId, $userId, $userId]);
    return $stmt->fetchAll();
}

function friend_action_label(string $status): string {
    switch ($status) {
        case 'friends':
            return 'Friends';
        case 'outgoing':
            return 'Requested';
        case 'incoming':
            return 'Accept request';
        default:
            return 'Add friend';
    }
}

function friend_action_button(int $userId, string $status, string $variant = 'ghost'): string {
    if ($status === 'self') {
        return '';
    }

    $actions = [
        'incoming' => 'accept',
        'outgoing' => 'cancel',
        'friends' => 'none',
        'none' => 'send',
    ];

    $action = $actions[$status] ?? 'send';
    $label = friend_action_label($status);
    $disabled = $status === 'friends' ? ' disabled' : '';

    return '<button type="button" class="btn btn-' . $variant . ' friend-action" data-id="' . $userId . '"'
        . ' data-status="' . $status . '" data-action="' . $action . '"' . $disabled . '>'
        . htmlspecialchars($label)
        . '</button>';
}
?>
