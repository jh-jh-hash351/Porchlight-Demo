<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/preferences.php';
require_once __DIR__ . '/saves.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function get_all_posts() {
    global $pdo;

    $userId = $_SESSION['user']['id'] ?? null;
    $viewerId = $userId ? (int)$userId : 0;

    ensure_saves_schema($pdo);

    $viewerPrefs = get_preferences($pdo, $userId ? (int)$userId : null);
    $GLOBALS['hide_like_counts'] = (int)$viewerPrefs['hide_like_counts'] === 1;

    // Get all posts with author info and each author's like/comment settings
    $stmt = $pdo->prepare("
        SELECT p.*, u.username, u.id as user_id, u.profile_pic,
               up.allow_likes, up.can_comment,
               (sv.post_id IS NOT NULL) AS saved
        FROM posts p
        JOIN users u ON p.user_id = u.id
        LEFT JOIN user_preferences up ON up.user_id = p.user_id
        LEFT JOIN saves sv ON sv.post_id = p.id AND sv.user_id = ?
        WHERE p.visibility = 'public' OR p.user_id = ?
        ORDER BY p.created_at DESC
    ");
    $stmt->execute([$viewerId, $viewerId]);
    $posts = $stmt->fetchAll();

    $finalPosts = [];
    foreach ($posts as $post) {
        $finalPosts[] = build_post($pdo, $post, $viewerId, !empty($post['saved']));
    }

    return $finalPosts;
}

function get_saved_posts(PDO $pdo, int $userId): array {
    ensure_saves_schema($pdo);

    $viewerPrefs = get_preferences($pdo, $userId);
    $GLOBALS['hide_like_counts'] = (int)$viewerPrefs['hide_like_counts'] === 1;

    $stmt = $pdo->prepare("
        SELECT p.*, u.username, u.id as user_id, u.profile_pic,
               up.allow_likes, up.can_comment,
               1 AS saved
        FROM saves sv
        JOIN posts p ON p.id = sv.post_id
        JOIN users u ON u.id = p.user_id
        LEFT JOIN user_preferences up ON up.user_id = p.user_id
        WHERE sv.user_id = ? AND (p.visibility = 'public' OR p.user_id = ?)
        ORDER BY sv.created_at DESC, p.id DESC
    ");
    $stmt->execute([$userId, $userId]);
    $posts = $stmt->fetchAll();

    $finalPosts = [];
    foreach ($posts as $post) {
        $finalPosts[] = build_post($pdo, $post, $userId, true);
    }

    return $finalPosts;
}

function build_post(PDO $pdo, array $post, int $userId, bool $saved): array {
    $name = $post['username'];
    $initials = strtoupper(substr($name, 0, 1)) . (strlen($name) > 1 ? strtoupper(substr($name, 1, 1)) : '');

    $stmtLikes = $pdo->prepare("SELECT COUNT(*) FROM likes WHERE post_id = ?");
    $stmtLikes->execute([$post['id']]);
    $likeCount = $stmtLikes->fetchColumn();

    $isLiked = false;
    if ($userId) {
        $stmtLiked = $pdo->prepare("SELECT 1 FROM likes WHERE post_id = ? AND user_id = ?");
        $stmtLiked->execute([$post['id'], $userId]);
        $isLiked = (bool)$stmtLiked->fetch();
    }

    $stmtComments = $pdo->prepare("
        SELECT c.*, u.username as author, u.profile_pic as user_pic
        FROM comments c
        JOIN users u ON c.user_id = u.id
        WHERE c.post_id = ?
        ORDER BY c.created_at ASC
    ");
    $stmtComments->execute([$post['id']]);
    $comments = $stmtComments->fetchAll();

    $isAuthor = $userId && (int)$post['user_id'] === (int)$userId;
    $authorPrefs = [
        'allow_likes' => $post['allow_likes'] ?? 1,
        'can_comment' => $post['can_comment'] ?? 'everyone',
    ];

    return [
        'id' => $post['id'],
        'user_id' => (int)$post['user_id'],
        'author' => $post['username'],
        'initials' => $initials,
        'profile_pic' => $post['profile_pic'],
        'time' => strtotime($post['created_at']),
        'content' => $post['content'],
        'image' => $post['image'],
        'media_type' => $post['media_type'] ?? 'image',
        'likes' => (int)$likeCount,
        'liked' => $isLiked,
        'saved' => $saved,
        'comments' => $comments,
        'can_like' => can_viewer_like($authorPrefs, (bool)$isAuthor),
        'can_comment' => can_viewer_comment($authorPrefs, (bool)$isAuthor),
        'can_delete' => $isAuthor,
    ];
}

function time_ago($timestamp) {
    $diff = time() - (int) $timestamp;
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    return floor($diff / 86400) . 'd ago';
}

function find_post_index($id) {
    return $id;
}
?>
