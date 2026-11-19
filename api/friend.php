<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/friends.php';
require_once __DIR__ . '/../includes/messages.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'You must be signed in to manage friends.']);
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
$otherId = (int)($_POST['id'] ?? 0);
$action = $_POST['action'] ?? '';

if ($otherId <= 0 || $otherId === $meId) {
    http_response_code(422);
    echo json_encode(['error' => 'Pick a valid neighbor.']);
    exit;
}

$allowed = ['send', 'accept', 'decline', 'cancel'];
if (!in_array($action, $allowed, true)) {
    http_response_code(422);
    echo json_encode(['error' => 'Unknown friend action.']);
    exit;
}

$peer = get_active_user($pdo, $otherId);
if (!$peer) {
    http_response_code(404);
    echo json_encode(['error' => 'That neighbor is not available.']);
    exit;
}

try {
    switch ($action) {
        case 'send':
            $status = send_friend_request($pdo, $meId, $otherId);
            break;
        case 'accept':
            $status = respond_friend_request($pdo, $meId, $otherId, true);
            break;
        case 'decline':
            $status = respond_friend_request($pdo, $meId, $otherId, false);
            break;
        default:
            $status = cancel_friend_request($pdo, $meId, $otherId);
            break;
    }

    echo json_encode([
        'status' => $status,
        'label' => friend_action_label($status),
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Could not update the friend request.']);
}
?>
