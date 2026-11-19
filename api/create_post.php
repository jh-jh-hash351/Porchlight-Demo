<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/icons.php';
require_once __DIR__ . '/../includes/data.php';
require_once __DIR__ . '/../includes/preferences.php';
require_once __DIR__ . '/../includes/post_media.php';
header('Content-Type: application/json');

// PHP discards the whole request when it exceeds post_max_size; report it clearly.
$contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
$postMaxIni = ini_get('post_max_size');
$unit = strtolower(substr($postMaxIni, -1));
$postMaxBytes = (float) $postMaxIni * (strpos('kmg', $unit) !== false ? pow(1024, strpos('kmg', $unit) + 1) : 1);
if ($contentLength > 0 && $postMaxBytes > 0 && $contentLength > $postMaxBytes) {
    http_response_code(413);
    echo json_encode(['error' => 'That photo or video is too large for the server (' . $postMaxIni . ' max). Raise post_max_size and upload_max_filesize in php.ini.']);
    exit;
}

// Auth Guard
if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'You must be signed in to post.']);
    exit;
}

$user = $_SESSION['user'];
$text = trim($_POST['text'] ?? '');

$media = store_post_media($_FILES['media'] ?? $_FILES['image'] ?? []);
if (isset($media['error'])) {
    http_response_code(422);
    echo json_encode(['error' => $media['error']]);
    exit;
}

$imagePath = $media['path'] ?? null;
$mediaType = $media['type'] ?? null;

if ($text === '' && $imagePath === null) {
    http_response_code(422);
    echo json_encode(['error' => 'Write something or add a photo or video before posting.']);
    exit;
}

try {
    ensure_posts_media_schema($pdo);

    $prefs = get_preferences($pdo, (int)$user['id']);
    $GLOBALS['hide_like_counts'] = (int)$prefs['hide_like_counts'] === 1;

    $stmt = $pdo->prepare('INSERT INTO posts (user_id, content, image, media_type, visibility) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([
        $user['id'],
        htmlspecialchars($text, ENT_QUOTES, 'UTF-8'),
        $imagePath,
        $mediaType ?? 'image',
        $prefs['default_visibility'],
    ]);
    $postId = $pdo->lastInsertId();

    $stmtPic = $pdo->prepare('SELECT profile_pic FROM users WHERE id = ?');
    $stmtPic->execute([(int)$user['id']]);
    $profilePic = $stmtPic->fetchColumn();

    $post = [
        'id' => $postId,
        'user_id' => (int)$user['id'],
        'author' => $user['username'],
        'initials' => $user['initials'],
        'profile_pic' => $profilePic ?: null,
        'time' => time(),
        'content' => htmlspecialchars($text, ENT_QUOTES, 'UTF-8'),
        'image' => $imagePath,
        'media_type' => $mediaType ?? 'image',
        'likes' => 0,
        'liked' => false,
        'saved' => false,
        'comments' => [],
        'can_like' => true,
        'can_comment' => can_viewer_comment($prefs, true),
        'can_delete' => true,
    ];

    ob_start();
    include __DIR__ . '/../includes/post-card.php';
    $html = ob_get_clean();

    echo json_encode(['html' => $html]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error.']);
}
?>
