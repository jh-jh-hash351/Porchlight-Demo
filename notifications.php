<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/icons.php';
require_once __DIR__ . '/includes/profile_edit.php';
require_once __DIR__ . '/includes/notifications.php';

$user = $_SESSION['user'] ?? null;
if (!$user) {
    header('Location: login.php');
    exit;
}

$meId = (int)$user['id'];
$notifications = get_notifications($pdo, $meId);

$newCount = 0;
foreach ($notifications as $notification) {
    if (empty($notification['read_at'])) {
        $newCount++;
    }
}

notification_mark_all_read($pdo, $meId);

function notification_time(?string $timestamp): string {
    if (!$timestamp) {
        return '';
    }
    $time = strtotime($timestamp);
    if ($time === false) {
        return '';
    }
    $diff = time() - $time;
    if ($diff < 60) {
        return 'Just now';
    }
    if ($diff < 3600) {
        return floor($diff / 60) . 'm ago';
    }
    if ($diff < 86400) {
        return floor($diff / 3600) . 'h ago';
    }
    if ($diff < 604800) {
        return floor($diff / 86400) . 'd ago';
    }
    return date('M j', $time);
}

function notification_href(array $notification): string {
    $entityId = (int)$notification['entity_id'];
    $actorId = (int)$notification['actor_id'];

    switch ($notification['type']) {
        case 'like':
        case 'comment':
            return 'index.php#post-' . $entityId;
        case 'friend_request':
            return 'messages.php';
        case 'friend_accept':
            return 'messages.php?with=' . $actorId;
        case 'group_request':
        case 'group_approved':
        case 'group_declined':
            return 'group.php?id=' . $entityId;
        default:
            return 'index.php';
    }
}

function notification_icon_name(string $type): string {
    if ($type === 'like') {
        return 'heart';
    }
    if ($type === 'comment') {
        return 'message';
    }
    if ($type === 'friend_request' || $type === 'friend_accept') {
        return 'user';
    }
    if ($type === 'group_request' || $type === 'group_approved' || $type === 'group_declined') {
        return 'users';
    }
    return 'bell';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Notifications - Porchlight</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/style.css?v=<?= filemtime(__DIR__ . '/css/style.css') ?>">
<script src="js/theme.js"></script>
</head>
<body data-authenticated="true">

<header class="topbar">
  <div class="topbar-inner">
    <a class="brand" href="index.php" style="display: flex; align-items: center; gap: 10px;">
      <img src="images/logo.png" alt="Porchlight Logo" style="height: 32px; width: auto; border-radius: 4px;">
      <span>Porchlight</span>
    </a>
    <div class="topbar-actions" style="margin-left: auto;">
      <a href="index.php" class="btn btn-ghost btn-small">Back to Feed</a>
      <details class="icon-menu">
        <summary class="icon-btn" aria-label="Email options" title="Email options"><?= icon('mail') ?></summary>
        <div class="icon-menu-panel">
          <a href="https://mail.google.com/" target="_blank" rel="noopener noreferrer"><span class="icon-menu-mark">G</span>Google</a>
          <a href="https://www.linkedin.com/" target="_blank" rel="noopener noreferrer"><span class="icon-menu-mark">in</span>LinkedIn</a>
          <a href="https://mail.yahoo.com/" target="_blank" rel="noopener noreferrer"><span class="icon-menu-mark">Y</span>Yahoo</a>
        </div>
      </details>
      <button class="icon-btn theme-toggle" aria-label="Toggle dark mode">
        <span class="icon-sun"><?= icon('sun') ?></span>
        <span class="icon-moon"><?= icon('moon') ?></span>
      </button>
    </div>
  </div>
</header>

<div class="layout">
  <main class="feed notifications-main">
    <div class="notifications-head">
      <div>
        <h1 class="notifications-title">Notifications</h1>
        <p class="notifications-sub">Likes, comments, friend requests, and group updates.</p>
      </div>
      <span class="notifications-count"><?= $newCount > 0 ? $newCount . ' new' : 'All caught up' ?></span>
    </div>

    <?php if (!$notifications): ?>
      <div class="notifications-empty">
        <?= icon('bell') ?>
        <h2>Nothing here yet</h2>
        <p>When someone likes your post, comments, adds you, or reviews a group request, it will show up here.</p>
      </div>
    <?php else: ?>
      <div class="notification-list">
        <?php foreach ($notifications as $notification): ?>
          <?php
            $type = (string)$notification['type'];
            $actorName = $notification['actor_username'] ?: 'Someone';
            $groupName = $notification['group_name'] ?: 'a group';
            $actorLabel = '<strong>' . security_encode($actorName) . '</strong>';
            $groupLabel = '<strong>' . security_encode($groupName) . '</strong>';

            switch ($type) {
                case 'like':
                    $sentence = $actorLabel . ' liked your post.';
                    break;
                case 'comment':
                    $sentence = $actorLabel . ' commented on your post.';
                    break;
                case 'friend_request':
                    $sentence = $actorLabel . ' sent you a friend request.';
                    break;
                case 'friend_accept':
                    $sentence = $actorLabel . ' accepted your friend request.';
                    break;
                case 'group_request':
                    $sentence = $actorLabel . ' asked to join ' . $groupLabel . '.';
                    break;
                case 'group_approved':
                    $sentence = 'Your request to join ' . $groupLabel . ' was approved.';
                    break;
                case 'group_declined':
                    $sentence = 'Your request to join ' . $groupLabel . ' was declined.';
                    break;
                default:
                    $sentence = $actorLabel . ' interacted with your account.';
                    break;
            }

            $actorPic = profile_pic_url($notification['actor_pic'] ?? null);
            $initials = strtoupper(substr($actorName, 0, 1));
            if (strlen($actorName) > 1) {
                $initials .= strtoupper(substr($actorName, 1, 1));
            }
            $isUnread = empty($notification['read_at']);
          ?>
          <a class="notification-item<?= $isUnread ? ' is-unread' : '' ?>" href="<?= security_encode(notification_href($notification)) ?>">
            <span class="notification-avatar-wrap">
              <?php if ($actorPic !== null): ?>
                <img src="<?= security_encode($actorPic) ?>" class="avatar" alt="" style="object-fit: cover;">
              <?php else: ?>
                <span class="avatar"><?= security_encode($initials) ?></span>
              <?php endif; ?>
              <span class="notification-type-icon<?= $type === 'like' ? ' is-like' : '' ?>">
                <?= icon(notification_icon_name($type)) ?>
              </span>
            </span>
            <span class="notification-body">
              <span class="notification-text"><?= $sentence ?></span>
              <span class="notification-time"><?= security_encode(notification_time($notification['created_at'])) ?></span>
            </span>
            <?php if ($isUnread): ?>
              <span class="notification-dot" aria-label="Unread"></span>
            <?php endif; ?>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </main>
</div>

<?php require __DIR__ . '/includes/site-footer.php'; ?>
<script src="js/dropdown.js?v=<?= filemtime(__DIR__ . '/js/dropdown.js') ?>"></script>
</body>
</html>
