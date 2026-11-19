<?php
/**
 * Group chat helpers for the Messages page.
 * Direct messages stay in `messages`; group chats use their own tables.
 */

require_once __DIR__ . '/messages.php';
require_once __DIR__ . '/friends.php';

function ensure_chat_groups_schema(PDO $pdo): void {
    static $ensured = false;
    if ($ensured) {
        return;
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS chat_groups (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(80) NOT NULL,
        created_by INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_chat_groups_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS chat_group_members (
        group_id INT NOT NULL,
        user_id INT NOT NULL,
        last_read_message_id INT NOT NULL DEFAULT 0,
        joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (group_id, user_id),
        INDEX idx_cgm_user (user_id),
        CONSTRAINT fk_cgm_group FOREIGN KEY (group_id) REFERENCES chat_groups(id) ON DELETE CASCADE,
        CONSTRAINT fk_cgm_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS chat_group_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        group_id INT NOT NULL,
        sender_id INT NOT NULL,
        body TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_cgmessages_group (group_id, id),
        CONSTRAINT fk_cgmessages_group FOREIGN KEY (group_id) REFERENCES chat_groups(id) ON DELETE CASCADE,
        CONSTRAINT fk_cgmessages_sender FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $ensured = true;
}

function chat_group_get(PDO $pdo, int $groupId): ?array {
    ensure_chat_groups_schema($pdo);

    $stmt = $pdo->prepare('SELECT * FROM chat_groups WHERE id = ?');
    $stmt->execute([$groupId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function chat_group_is_member(PDO $pdo, int $groupId, int $userId): bool {
    ensure_chat_groups_schema($pdo);

    $stmt = $pdo->prepare('SELECT 1 FROM chat_group_members WHERE group_id = ? AND user_id = ?');
    $stmt->execute([$groupId, $userId]);
    return (bool)$stmt->fetchColumn();
}

/**
 * Active members of a group chat, oldest first.
 */
function chat_group_members(PDO $pdo, int $groupId): array {
    ensure_chat_groups_schema($pdo);

    $stmt = $pdo->prepare("
        SELECT u.id, u.username, u.profile_pic, m.joined_at
        FROM chat_group_members m
        JOIN users u ON u.id = m.user_id
        WHERE m.group_id = ? AND u.status = 'Active'
        ORDER BY m.joined_at ASC, u.username ASC
    ");
    $stmt->execute([$groupId]);
    return $stmt->fetchAll();
}

/**
 * Group chats for the conversation sidebar, with last message and unread count.
 */
function chat_group_get_chats(PDO $pdo, int $userId): array {
    ensure_chat_groups_schema($pdo);

    $stmt = $pdo->prepare("
        SELECT g.id, g.name, g.created_by, g.created_at,
               (SELECT COUNT(*) FROM chat_group_members cm
                 WHERE cm.group_id = g.id) AS member_count,
               (SELECT gm.body FROM chat_group_messages gm
                 WHERE gm.group_id = g.id ORDER BY gm.id DESC LIMIT 1) AS last_message,
               (SELECT gm.sender_id FROM chat_group_messages gm
                 WHERE gm.group_id = g.id ORDER BY gm.id DESC LIMIT 1) AS last_sender_id,
               (SELECT gm.created_at FROM chat_group_messages gm
                 WHERE gm.group_id = g.id ORDER BY gm.id DESC LIMIT 1) AS last_time,
               (SELECT u.username FROM chat_group_messages gm
                 JOIN users u ON u.id = gm.sender_id
                 WHERE gm.group_id = g.id ORDER BY gm.id DESC LIMIT 1) AS last_sender_name,
               (SELECT COUNT(*) FROM chat_group_messages gm
                 WHERE gm.group_id = g.id
                   AND gm.id > mine.last_read_message_id
                   AND gm.sender_id <> ?) AS unread
        FROM chat_groups g
        JOIN chat_group_members mine ON mine.group_id = g.id AND mine.user_id = ?
        ORDER BY COALESCE(last_time, g.created_at) DESC, g.id DESC
    ");
    $stmt->execute([$userId, $userId]);

    $rows = $stmt->fetchAll();
    foreach ($rows as &$row) {
        $row['id'] = (int)$row['id'];
        $row['member_count'] = (int)$row['member_count'];
        $row['unread'] = (int)$row['unread'];
        $row['last_sender_id'] = $row['last_sender_id'] !== null ? (int)$row['last_sender_id'] : null;
    }
    unset($row);

    return $rows;
}

/**
 * Creates a group where the creator picks friends as members.
 * Returns ['id' => int] or ['error' => string].
 */
function chat_group_create(PDO $pdo, int $creatorId, string $name, array $memberIds): array {
    ensure_chat_groups_schema($pdo);

    $name = trim(preg_replace('/\s+/', ' ', $name));
    if (strlen($name) < 2 || strlen($name) > 60) {
        return ['error' => 'Group name must be between 2 and 60 characters.'];
    }

    $selected = array_values(array_unique(array_filter(
        array_map('intval', $memberIds),
        function (int $id) use ($creatorId): bool {
            return $id > 0 && $id !== $creatorId;
        }
    )));

    if (!$selected) {
        return ['error' => 'Choose at least one friend for the group.'];
    }
    if (count($selected) > 49) {
        return ['error' => 'Group chats can have up to 50 people.'];
    }

    $friendIds = array_map(static function (array $friend): int {
        return (int)$friend['id'];
    }, get_friends($pdo, $creatorId));

    if (array_diff($selected, $friendIds)) {
        return ['error' => 'You can only add friends to a group chat.'];
    }

    try {
        $pdo->beginTransaction();

        $stmtGroup = $pdo->prepare('INSERT INTO chat_groups (name, created_by) VALUES (?, ?)');
        $stmtGroup->execute([$name, $creatorId]);
        $groupId = (int)$pdo->lastInsertId();

        $stmtMember = $pdo->prepare('INSERT INTO chat_group_members (group_id, user_id) VALUES (?, ?)');
        $stmtMember->execute([$groupId, $creatorId]);
        foreach ($selected as $memberId) {
            $stmtMember->execute([$groupId, $memberId]);
        }

        $pdo->commit();
        return ['id' => $groupId];
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['error' => 'Could not create the group chat right now.'];
    }
}

function chat_group_messages(PDO $pdo, int $groupId, int $afterId = 0): array {
    ensure_chat_groups_schema($pdo);

    if ($afterId > 0) {
        $stmt = $pdo->prepare("
            SELECT gm.*, u.username, u.profile_pic
            FROM chat_group_messages gm
            JOIN users u ON u.id = gm.sender_id
            WHERE gm.group_id = ? AND gm.id > ?
            ORDER BY gm.id ASC
            LIMIT 100
        ");
        $stmt->execute([$groupId, $afterId]);
        return $stmt->fetchAll();
    }

    $stmt = $pdo->prepare("
        SELECT gm.*, u.username, u.profile_pic
        FROM chat_group_messages gm
        JOIN users u ON u.id = gm.sender_id
        WHERE gm.group_id = ?
        ORDER BY gm.id DESC
        LIMIT 200
    ");
    $stmt->execute([$groupId]);

    return array_reverse($stmt->fetchAll());
}

function chat_group_send_message(PDO $pdo, int $groupId, int $senderId, string $body): array {
    ensure_chat_groups_schema($pdo);

    $stmt = $pdo->prepare('INSERT INTO chat_group_messages (group_id, sender_id, body) VALUES (?, ?, ?)');
    $stmt->execute([$groupId, $senderId, $body]);

    $stmtMessage = $pdo->prepare("
        SELECT gm.*, u.username, u.profile_pic
        FROM chat_group_messages gm
        JOIN users u ON u.id = gm.sender_id
        WHERE gm.id = ?
    ");
    $stmtMessage->execute([(int)$pdo->lastInsertId()]);
    return $stmtMessage->fetch();
}

function chat_group_mark_read(PDO $pdo, int $groupId, int $userId, int $messageId): void {
    ensure_chat_groups_schema($pdo);

    $stmt = $pdo->prepare("
        UPDATE chat_group_members
        SET last_read_message_id = GREATEST(last_read_message_id, ?)
        WHERE group_id = ? AND user_id = ?
    ");
    $stmt->execute([max(0, $messageId), $groupId, $userId]);
}

function chat_group_message_html(array $message, int $viewerId): string {
    $isMine = (int)$message['sender_id'] === $viewerId;
    $body = nl2br(htmlspecialchars($message['body'], ENT_QUOTES, 'UTF-8'));
    $time = htmlspecialchars(date('M j, g:i A', strtotime($message['created_at'])));
    $senderName = htmlspecialchars($message['username'], ENT_QUOTES, 'UTF-8');

    $avatar = '';
    $senderLabel = '';
    if (!$isMine) {
        $pic = message_profile_pic($message);
        if ($pic !== null) {
            $avatar = '<img src="' . htmlspecialchars($pic, ENT_QUOTES, 'UTF-8') . '" class="msg-avatar" alt="">';
        } else {
            $avatar = '<div class="msg-avatar msg-avatar-fallback">' . htmlspecialchars(message_initials($message['username'])) . '</div>';
        }
        $senderLabel = '<span class="msg-sender">' . $senderName . '</span>';
    }

    return '<div class="msg-row ' . ($isMine ? 'is-me' : 'is-them') . '" data-id="' . (int)$message['id'] . '">'
        . $avatar
        . '<div class="msg-content">'
        . $senderLabel
        . '<div class="msg-bubble">' . $body . '</div>'
        . '<span class="msg-time">' . $time . '</span>'
        . '</div>'
        . '</div>';
}
