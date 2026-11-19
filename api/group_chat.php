<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/message_groups.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'You must be signed in to use group chats.']);
    exit;
}

$meId = (int)$_SESSION['user']['id'];
$groupId = (int)($_REQUEST['group'] ?? 0);

if ($groupId <= 0) {
    http_response_code(422);
    echo json_encode(['error' => 'A valid group is required.']);
    exit;
}

if (!chat_group_is_member($pdo, $groupId, $meId)) {
    http_response_code(404);
    echo json_encode(['error' => 'That group chat is not available.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid CSRF token.']);
        exit;
    }

    $body = trim($_POST['body'] ?? '');
    if ($body === '') {
        http_response_code(422);
        echo json_encode(['error' => 'Write a message first.']);
        exit;
    }
    if (strlen($body) > 2000) {
        http_response_code(422);
        echo json_encode(['error' => 'Messages can be at most 2000 characters.']);
        exit;
    }

    try {
        $message = chat_group_send_message($pdo, $groupId, $meId, $body);
        chat_group_mark_read($pdo, $groupId, $meId, (int)$message['id']);

        echo json_encode([
            'id' => (int)$message['id'],
            'html' => chat_group_message_html($message, $meId),
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Could not send your message.']);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

$afterId = (int)($_GET['after'] ?? 0);

try {
    $messages = chat_group_messages($pdo, $groupId, $afterId);

    $rendered = [];
    $lastId = $afterId;
    foreach ($messages as $message) {
        $rendered[] = chat_group_message_html($message, $meId);
        $lastId = max($lastId, (int)$message['id']);
    }
    chat_group_mark_read($pdo, $groupId, $meId, $lastId);

    echo json_encode([
        'messages' => $rendered,
        'last_id' => $lastId,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Could not load messages.']);
}
