<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/message_groups.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'You must be signed in to create group chats.']);
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

$meId = (int)$_SESSION['user']['id'];
$name = trim($_POST['name'] ?? '');
$members = $_POST['members'] ?? [];
if (!is_array($members)) {
    $members = [];
}

$result = chat_group_create($pdo, $meId, $name, $members);
if (isset($result['error'])) {
    http_response_code(422);
    echo json_encode(['error' => $result['error']]);
    exit;
}

echo json_encode([
    'id' => $result['id'],
    'redirect' => 'messages.php?group=' . $result['id'],
]);
