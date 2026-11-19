<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/preferences.php';
require_once __DIR__ . '/../includes/profile_edit.php';
require_once __DIR__ . '/../includes/data.php';
header('Content-Type: application/json');

// Auth Guard
if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'You must be signed in to comment.']);
    exit;
}

$user = $_SESSION['user'];
$postId = $_POST['id'] ?? null;
$text = trim($_POST['text'] ?? '');

if ($postId === null || $text === '') {
    http_response_code(422);
    echo json_encode(['error' => 'Post ID and comment text are required.']);
    exit;
}

try {
    ensure_preferences_schema($pdo);

    // Author's settings decide who may comment
    $stmtPost = $pdo->prepare('
        SELECT p.user_id, up.can_comment
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

    $isAuthor = (int)$post['user_id'] === (int)$user['id'];
    if (!can_viewer_comment(['can_comment' => $post['can_comment'] ?? 'everyone'], $isAuthor)) {
        http_response_code(403);
        echo json_encode(['error' => 'Comments are turned off for this post.']);
        exit;
    }

    $stmt = $pdo->prepare('INSERT INTO comments (post_id, user_id, content) VALUES (?, ?, ?)');
    $stmt->execute([$postId, $user['id'], htmlspecialchars($text, ENT_QUOTES, 'UTF-8')]);

    // Get user's current profile pic
    $stmtPic = $pdo->prepare('SELECT profile_pic FROM users WHERE id = ?');
    $stmtPic->execute([$user['id']]);
    $profilePic = $stmtPic->fetchColumn();

    $comment = [
        'author' => $user['username'],
        'text' => htmlspecialchars($text, ENT_QUOTES, 'UTF-8'),
        'profile_pic' => $profilePic,
        'initials' => $user['initials']
    ];

    // Get updated comment count
    $stmtCount = $pdo->prepare('SELECT COUNT(*) FROM comments WHERE post_id = ?');
    $stmtCount->execute([$postId]);
    $count = $stmtCount->fetchColumn();

    // Build HTML with Avatar
    $avatarHtml = '';
    $picUrl = profile_pic_url($profilePic);
    if ($picUrl !== null) {
        $avatarHtml = '<img src="' . htmlspecialchars($picUrl) . '" class="avatar avatar-sm" style="margin-right: 8px; border-radius: 50%;">';
    } else {
        $avatarHtml = '<div class="avatar avatar-sm" style="margin-right: 8px;">' . htmlspecialchars($comment['initials']) . '</div>';
    }

    $html = '<li>' . $avatarHtml . '<strong>' . htmlspecialchars($comment['author']) . '</strong> ' . $comment['text'] . '</li>';

    echo json_encode([
        'html' => $html,
        'comment_count' => (int)$count,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
