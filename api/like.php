<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/preferences.php';
require_once __DIR__ . '/../includes/notifications.php';
header('Content-Type: application/json');

// Auth Guard
if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'You must be signed in to like posts.']);
    exit;
}

$userId = (int)$_SESSION['user']['id'];
$postId = $_POST['id'] ?? null;

if ($postId === null) {
    http_response_code(400);
    echo json_encode(['error' => 'Post ID is required.']);
    exit;
}

try {
    ensure_preferences_schema($pdo);

    // Author's settings decide whether likes are allowed
    $stmtPost = $pdo->prepare('
        SELECT p.user_id, up.allow_likes
        FROM posts p
        LEFT JOIN user_preferences up ON up.user_id = p.user_id
        WHERE p.id = ?
    ');
    $stmtPost->execute([$postId]);
    $post = $stmtPost->fetch();

    if (!$post) {
        http_response_code(404);
        echo json_encode(['error' => 'That post no longer exists.']);
        exit;
    }

    $isAuthor = (int)$post['user_id'] === $userId;
    if (!can_viewer_like(['allow_likes' => $post['allow_likes'] ?? 1], $isAuthor)) {
        http_response_code(403);
        echo json_encode(['error' => 'Likes are turned off for this post.']);
        exit;
    }

    // Check if like already exists
    $stmt = $pdo->prepare('SELECT 1 FROM likes WHERE post_id = ? AND user_id = ?');
    $stmt->execute([$postId, $userId]);
    $alreadyLiked = $stmt->fetch();

    if ($alreadyLiked) {
        // Remove like
        $stmt = $pdo->prepare('DELETE FROM likes WHERE post_id = ? AND user_id = ?');
        $stmt->execute([$postId, $userId]);
        $liked = false;
        notification_delete($pdo, (int)$post['user_id'], $userId, 'like', (int)$postId);
    } else {
        // Add like
        $stmt = $pdo->prepare('INSERT INTO likes (post_id, user_id) VALUES (?, ?)');
        $stmt->execute([$postId, $userId]);
        $liked = true;
        notification_create($pdo, (int)$post['user_id'], $userId, 'like', (int)$postId);
    }

    // Get total like count
    $stmtCount = $pdo->prepare('SELECT COUNT(*) FROM likes WHERE post_id = ?');
    $stmtCount->execute([$postId]);
    $likeCount = $stmtCount->fetchColumn();

    echo json_encode([
        'likes' => (int)$likeCount,
        'liked' => $liked,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
