<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/messages.php';
require_once __DIR__ . '/../includes/friends.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'You must be signed in to send messages.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token.']);
    exit;
}

$senderId = (int)$_SESSION['user']['id'];
$recipientId = (int)($_POST['to'] ?? 0);
$body = trim($_POST['body'] ?? '');

if ($recipientId <= 0 || $body === '') {
    http_response_code(422);
    echo json_encode(['error' => 'Pick a recipient and write a message.']);
    exit;
}

if ($recipientId === $senderId) {
    http_response_code(422);
    echo json_encode(['error' => "You can't message yourself."]);
    exit;
}

if (strlen($body) > 2000) {
    http_response_code(422);
    echo json_encode(['error' => 'Messages can be at most 2000 characters.']);
    exit;
}

$peer = get_active_user($pdo, $recipientId);
if (!$peer) {
    http_response_code(404);
    echo json_encode(['error' => 'That neighbor is not available.']);
    exit;
}

if (!are_friends($pdo, $senderId, $recipientId)) {
    http_response_code(403);
    echo json_encode(['error' => 'You need to add each other as friends before messaging.']);
    exit;
}

try {
    $message = send_message($pdo, $senderId, $recipientId, $body);

    echo json_encode([
        'id' => (int)$message['id'],
        'html' => message_html($message, $senderId, $peer),
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Could not send your message.']);
}
