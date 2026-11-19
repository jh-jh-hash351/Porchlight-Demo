<?php
/**
 * One-to-one messaging helpers for the Messages page.
 * Conversations are derived from message pairs, Messenger-style.
 */

function ensure_messages_schema(PDO $pdo): void {
    static $ensured = false;
    if ($ensured) {
        return;
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sender_id INT NOT NULL,
        recipient_id INT NOT NULL,
        body TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        read_at TIMESTAMP NULL DEFAULT NULL,
        INDEX idx_messages_pair (sender_id, recipient_id),
        INDEX idx_messages_recipient (recipient_id, read_at),
        CONSTRAINT fk_messages_sender FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
        CONSTRAINT fk_messages_recipient FOREIGN KEY (recipient_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $ensured = true;
}

function message_initials(string $username): string {
    $initials = strtoupper(substr($username, 0, 1));
    if (strlen($username) > 1) {
        $initials .= strtoupper(substr($username, 1, 1));
    }
    return $initials;
}

function message_list_time(string $timestamp): string {
    $time = strtotime($timestamp);
    if (date('Y-m-d', $time) === date('Y-m-d')) {
        return date('g:i A', $time);
    }
    if (date('Y', $time) === date('Y')) {
        return date('M j', $time);
    }
    return date('M j, Y', $time);
}

function message_profile_pic(array $user): ?string {
    $pic = $user['profile_pic'] ?? null;
    if (!$pic) {
        return null;
    }
    $path = dirname(__DIR__) . '/' . ltrim($pic, '/\\');
    return is_file($path) ? $pic : null;
}

function get_active_user(PDO $pdo, int $userId): ?array {
    $stmt = $pdo->prepare("SELECT id, username, profile_pic FROM users WHERE id = ? AND status = 'Active'");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    return $user ?: null;
}

function get_conversations(PDO $pdo, int $userId): array {
    ensure_messages_schema($pdo);

    $stmt = $pdo->prepare("
        SELECT m.*,
               CASE WHEN m.sender_id = ? THEN m.recipient_id ELSE m.sender_id END AS peer_id
        FROM messages m
        WHERE m.sender_id = ? OR m.recipient_id = ?
        ORDER BY m.created_at DESC, m.id DESC
    ");
    $stmt->execute([$userId, $userId, $userId]);
    $rows = $stmt->fetchAll();

    $conversations = [];
    foreach ($rows as $row) {
        $peerId = (int)$row['peer_id'];
        if (!isset($conversations[$peerId])) {
            $conversations[$peerId] = [
                'peer_id' => $peerId,
                'last_message' => $row['body'],
                'last_sender_id' => (int)$row['sender_id'],
                'last_time' => $row['created_at'],
                'unread' => 0,
            ];
        }
        if ((int)$row['recipient_id'] === $userId
            && $row['read_at'] === null
            && (int)$row['sender_id'] === $peerId) {
            $conversations[$peerId]['unread']++;
        }
    }

    if (!$conversations) {
        return [];
    }

    $peerIds = array_keys($conversations);
    $placeholders = implode(',', array_fill(0, count($peerIds), '?'));
    $stmtUsers = $pdo->prepare("
        SELECT id, username, profile_pic
        FROM users
        WHERE status = 'Active' AND id IN ($placeholders)
    ");
    $stmtUsers->execute($peerIds);

    $peers = [];
    foreach ($stmtUsers->fetchAll() as $peer) {
        $peers[(int)$peer['id']] = $peer;
    }

    $result = [];
    foreach ($conversations as $peerId => $conversation) {
        if (!isset($peers[$peerId])) {
            continue;
        }
        $peer = $peers[$peerId];
        $conversation['username'] = $peer['username'];
        $conversation['profile_pic'] = $peer['profile_pic'];
        $conversation['initials'] = message_initials($peer['username']);
        $result[] = $conversation;
    }

    return $result;
}

function get_conversation_messages(PDO $pdo, int $userId, int $peerId, int $afterId = 0): array {
    ensure_messages_schema($pdo);

    if ($afterId > 0) {
        $stmt = $pdo->prepare("
            SELECT *
            FROM messages
            WHERE ((sender_id = ? AND recipient_id = ?) OR (sender_id = ? AND recipient_id = ?))
              AND id > ?
            ORDER BY id ASC
            LIMIT 100
        ");
        $stmt->execute([$userId, $peerId, $peerId, $userId, $afterId]);
        return $stmt->fetchAll();
    }

    $stmt = $pdo->prepare("
        SELECT *
        FROM (
            SELECT *
            FROM messages
            WHERE (sender_id = ? AND recipient_id = ?) OR (sender_id = ? AND recipient_id = ?)
            ORDER BY id DESC
            LIMIT 200
        ) recent
        ORDER BY id ASC
    ");
    $stmt->execute([$userId, $peerId, $peerId, $userId]);
    return $stmt->fetchAll();
}

function send_message(PDO $pdo, int $senderId, int $recipientId, string $body): array {
    ensure_messages_schema($pdo);

    $stmt = $pdo->prepare('INSERT INTO messages (sender_id, recipient_id, body) VALUES (?, ?, ?)');
    $stmt->execute([$senderId, $recipientId, $body]);

    $stmt = $pdo->prepare('SELECT * FROM messages WHERE id = ?');
    $stmt->execute([(int)$pdo->lastInsertId()]);
    return $stmt->fetch();
}

function mark_conversation_read(PDO $pdo, int $userId, int $peerId): void {
    ensure_messages_schema($pdo);

    $stmt = $pdo->prepare('UPDATE messages SET read_at = NOW() WHERE recipient_id = ? AND sender_id = ? AND read_at IS NULL');
    $stmt->execute([$userId, $peerId]);
}

function message_html(array $message, int $viewerId, array $peer): string {
    $isMine = (int)$message['sender_id'] === $viewerId;
    $body = nl2br(htmlspecialchars($message['body'], ENT_QUOTES, 'UTF-8'));
    $time = htmlspecialchars(date('M j, g:i A', strtotime($message['created_at'])));

    $avatar = '';
    if (!$isMine) {
        $pic = message_profile_pic($peer);
        if ($pic !== null) {
            $avatar = '<img src="' . htmlspecialchars($pic, ENT_QUOTES, 'UTF-8') . '" class="msg-avatar" alt="">';
        } else {
            $avatar = '<div class="msg-avatar msg-avatar-fallback">' . htmlspecialchars(message_initials($peer['username'])) . '</div>';
        }
    }

    return '<div class="msg-row ' . ($isMine ? 'is-me' : 'is-them') . '" data-id="' . (int)$message['id'] . '">'
        . $avatar
        . '<div class="msg-content">'
        . '<div class="msg-bubble">' . $body . '</div>'
        . '<span class="msg-time">' . $time . '</span>'
        . '</div>'
        . '</div>';
}
