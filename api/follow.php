<?php
require_once __DIR__ . '/../includes/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'You must be signed in to follow users.']);
    exit;
}

$userId = $_SESSION['user']['id'];
$followingId = $_POST['id'] ?? null;

if (!$followingId) {
    http_response_code(400);
    echo json_encode(['error' => 'User ID is required.']);
    exit;
}

if ($userId == $followingId) {
    http_response_code(400);
    echo json_encode(['error' => 'You cannot follow yourself.']);
    exit;
}

try {
    // Check if already following
    $stmt = $pdo->prepare('SELECT 1 FROM follows WHERE follower_id = ? AND following_id = ?');
    $stmt->execute([$userId, $followingId]);
    $isFollowing = $stmt->fetch();

    if ($isFollowing) {
        // Unfollow
        $stmt = $pdo->prepare('DELETE FROM follows WHERE follower_id = ? AND following_id = ?');
        $stmt->execute([$userId, $followingId]);
        $status = 'unfollowed';
    } else {
        // Follow
        $stmt = $pdo->prepare('INSERT INTO follows (follower_id, following_id) VALUES (?, ?)');
        $stmt->execute([$userId, $followingId]);
        $status = 'followed';
    }

    echo json_encode([
        'status' => $status,
        'following' => ($status === 'followed')
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
