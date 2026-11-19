<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/security.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'You must be signed in to delete a post.']);
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
    echo json_encode(['error' => 'Choose a post to delete.']);
    exit;
}

$stmt = $pdo->prepare('SELECT id, user_id, image FROM posts WHERE id = ?');
$stmt->execute([$postId]);
$post = $stmt->fetch();

if (!$post) {
    http_response_code(404);
    echo json_encode(['error' => 'That post no longer exists.']);
    exit;
}

if ((int)$post['user_id'] !== $userId) {
    http_response_code(403);
    echo json_encode(['error' => 'You can only delete your own posts.']);
    exit;
}

try {
    $stmtDelete = $pdo->prepare('DELETE FROM posts WHERE id = ? AND user_id = ?');
    $stmtDelete->execute([$postId, $userId]);

    $image = $post['image'] ?: '';
    $uploadsDir = realpath(dirname(__DIR__) . '/uploads');
    $fullPath = $image !== '' ? realpath(dirname(__DIR__) . '/' . $image) : false;
    if ($uploadsDir && $fullPath && strpos($fullPath, $uploadsDir . DIRECTORY_SEPARATOR) === 0) {
        @unlink($fullPath);
    }

    echo json_encode(['deleted' => true]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Could not delete that post.']);
}
?>
