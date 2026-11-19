<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/messages.php';
require_once __DIR__ . '/../includes/friends.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'You must be signed in to read messages.']);
    exit;
}

$viewerId = (int)$_SESSION['user']['id'];
$peerId = (int)($_GET['with'] ?? 0);
$afterId = (int)($_GET['after'] ?? 0);

if ($peerId <= 0 || $peerId === $viewerId) {
    http_response_code(422);
    echo json_encode(['error' => 'A valid conversation is required.']);
    exit;
}

$peer = get_active_user($pdo, $peerId);
if (!$peer) {
    http_response_code(404);
    echo json_encode(['error' => 'That neighbor is not available.']);
    exit;
}

if (!are_friends($pdo, $viewerId, $peerId)) {
    http_response_code(403);
    echo json_encode(['error' => 'You need to be friends to read this conversation.']);
    exit;
}

try {
    mark_conversation_read($pdo, $viewerId, $peerId);

    $messages = get_conversation_messages($pdo, $viewerId, $peerId, $afterId);

    $rendered = [];
    $lastId = $afterId;
    foreach ($messages as $message) {
        $rendered[] = message_html($message, $viewerId, $peer);
        $lastId = max($lastId, (int)$message['id']);
    }

    echo json_encode([
        'messages' => $rendered,
        'last_id' => $lastId,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Could not load messages.']);
}
