<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/saves.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'You must be signed in to save posts.']);
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

$userId = (int)$_SESSION['user']['id'];
$postId = (int)($_POST['id'] ?? 0);

if ($postId <= 0) {
    http_response_code(422);
    echo json_encode(['error' => 'Choose a post to save.']);
    exit;
}

$stmt = $pdo->prepare('SELECT id, user_id, visibility FROM posts WHERE id = ?');
$stmt->execute([$postId]);
$post = $stmt->fetch();

if (!$post || ($post['visibility'] !== 'public' && (int)$post['user_id'] !== $userId)) {
    http_response_code(404);
    echo json_encode(['error' => 'That post is not available.']);
    exit;
}

try {
    $saved = toggle_save($pdo, $userId, $postId);
    echo json_encode(['saved' => $saved]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Could not update your saves.']);
}
?>
