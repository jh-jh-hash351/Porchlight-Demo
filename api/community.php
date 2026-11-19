<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/communities.php';
require_once __DIR__ . '/../includes/notifications.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'You must be signed in to manage communities.']);
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
$action = $_POST['action'] ?? '';
$communityId = (int)($_POST['id'] ?? 0);

try {
    switch ($action) {
        case 'create':
            $result = create_community(
                $pdo,
                $userId,
                $_POST['name'] ?? '',
                $_POST['description'] ?? '',
                $_POST['join_policy'] ?? 'open'
            );
            if (isset($result['error'])) {
                http_response_code(422);
                echo json_encode($result);
                exit;
            }
            echo json_encode(['created' => true, 'id' => $result['id']]);
            exit;

        case 'join':
            if ($communityId <= 0) {
                http_response_code(422);
                echo json_encode(['error' => 'Choose a community to join.']);
                exit;
            }
            $beforeJoin = community_membership($pdo, $communityId, $userId);
            $status = join_community($pdo, $userId, $communityId);
            if ($status === 'missing') {
                http_response_code(404);
                echo json_encode(['error' => 'That community no longer exists.']);
                exit;
            }
            if ($status === 'pending' && !$beforeJoin) {
                $adminMap = community_admins_map($pdo, [$communityId]);
                foreach ($adminMap[$communityId] ?? [] as $admin) {
                    notification_create($pdo, (int)$admin['id'], $userId, 'group_request', $communityId);
                }
            }
            echo json_encode(['status' => $status]);
            exit;

        case 'leave':
            if ($communityId <= 0) {
                http_response_code(422);
                echo json_encode(['error' => 'Choose a community.']);
                exit;
            }
            $beforeLeave = community_membership($pdo, $communityId, $userId);
            $status = leave_community($pdo, $userId, $communityId);
            if ($status === 'admin') {
                http_response_code(409);
                echo json_encode(['error' => 'Admins cannot leave their own community.']);
                exit;
            }
            if ($beforeLeave && $beforeLeave['status'] === 'pending') {
                notification_clear_group_requests($pdo, $communityId, $userId);
            }
            echo json_encode(['status' => $status]);
            exit;

        case 'approve':
        case 'decline':
            $targetUserId = (int)($_POST['user_id'] ?? 0);
            if ($communityId <= 0 || $targetUserId <= 0) {
                http_response_code(422);
                echo json_encode(['error' => 'Choose a join request.']);
                exit;
            }
            $previousMembership = community_membership($pdo, $communityId, $targetUserId);
            $status = respond_to_community_request(
                $pdo,
                $communityId,
                $userId,
                $targetUserId,
                $action === 'approve'
            );
            if ($status === 'forbidden') {
                http_response_code(403);
                echo json_encode(['error' => 'Only an admin of this community can review requests.']);
                exit;
            }
            if ($previousMembership && $previousMembership['status'] === 'pending') {
                notification_clear_group_requests($pdo, $communityId, $targetUserId);
                if ($action === 'approve' && $status === 'member') {
                    notification_create($pdo, $targetUserId, $userId, 'group_approved', $communityId);
                } elseif ($action === 'decline' && $status === 'none') {
                    notification_create($pdo, $targetUserId, $userId, 'group_declined', $communityId);
                }
            }
            echo json_encode(['status' => $status]);
            exit;

        default:
            http_response_code(422);
            echo json_encode(['error' => 'Unknown community action.']);
            exit;
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Could not update the community right now.']);
}
?>
