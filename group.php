<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/icons.php';
require_once __DIR__ . '/includes/profile_edit.php';
require_once __DIR__ . '/includes/communities.php';

$user = $_SESSION['user'] ?? null;
$meId = $user ? (int)$user['id'] : 0;
$groupId = (int)($_GET['id'] ?? 0);

$group = $groupId > 0 ? get_community($pdo, $groupId) : null;
$admins = [];
$members = [];
$pending = [];
$viewerStatus = 'none';

if ($group) {
    $adminMap = community_admins_map($pdo, [$groupId]);
    $admins = $adminMap[$groupId] ?? [];
    $members = get_community_members($pdo, $groupId);

    if ($meId) {
        $membership = community_membership($pdo, $groupId, $meId);
        if ($membership) {
            if ($membership['status'] === 'pending') {
                $viewerStatus = 'pending';
            } elseif ($membership['role'] === 'admin') {
                $viewerStatus = 'admin';
            } else {
                $viewerStatus = 'member';
            }
        }
    }

    if ($viewerStatus === 'admin' && $group['pending_count'] > 0) {
        $pending = get_pending_requests($pdo, $groupId);
    }
}

function group_avatar(array $row, string $class = 'avatar avatar-sm'): string {
    $pic = profile_pic_url($row['profile_pic'] ?? null);
    if ($pic !== null) {
        return '<img src="' . security_encode($pic) . '" class="' . $class . '" alt="" style="object-fit: cover;">';
    }
    $name = $row['username'] ?? '?';
    $initials = strtoupper(substr($name, 0, 1)) . (strlen($name) > 1 ? strtoupper(substr($name, 1, 1)) : '');
    return '<div class="' . $class . '">' . security_encode($initials) . '</div>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $group ? security_encode($group['name']) . ' - Porchlight' : 'Group - Porchlight' ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css?v=<?= filemtime(__DIR__ . '/css/style.css') ?>">
    <meta name="csrf-token" content="<?= generate_csrf_token() ?>">
    <script src="js/theme.js"></script>
</head>
<body data-communities="true" data-authenticated="<?= $user ? 'true' : 'false' ?>">
    <header class="topbar">
        <div class="topbar-inner">
            <a class="brand" href="index.php" style="display: flex; align-items: center; gap: 10px;">
                <img src="images/logo.png" alt="Porchlight Logo" style="height: 32px; width: auto; border-radius: 4px;">
                <span>Porchlight</span>
            </a>
            <div class="topbar-actions" style="margin-left: auto;">
                <?php if (!$user): ?>
                    <a href="login.php" class="btn btn-primary btn-small">Sign In</a>
                <?php endif; ?>
                <a href="groups.php" class="btn btn-ghost btn-small">All Groups</a>
                <button class="icon-btn theme-toggle" aria-label="Toggle dark mode">
                    <span class="icon-sun"><?= icon('sun') ?></span>
                    <span class="icon-moon"><?= icon('moon') ?></span>
                </button>
            </div>
        </div>
    </header>

    <div class="layout">
        <main class="feed groups-main">
            <a class="group-back" href="groups.php">&larr; All groups</a>

            <?php if (!$group): ?>
                <div class="panel" style="padding: 36px; text-align: center;">
                    <h1 class="group-title" style="margin-bottom: 10px;">Group not found</h1>
                    <p style="color: var(--text-secondary); margin-bottom: 20px;">This group may have been removed.</p>
                    <a href="groups.php" class="btn btn-primary">Browse groups</a>
                </div>
            <?php else: ?>
                <?php
                    $metaParts = [
                        $group['member_count'] === 1 ? '1 member' : $group['member_count'] . ' members',
                        community_policy_label($group['join_policy']),
                        'Created ' . date('M Y', strtotime($group['created_at'])),
                    ];
                    if ($viewerStatus === 'admin' && $group['pending_count'] > 0) {
                        $metaParts[] = $group['pending_count'] . ' waiting for approval';
                    }
                    $adminLabels = [];
                    foreach ($admins as $admin) {
                        $label = security_encode($admin['username']);
                        if ((int)$admin['id'] === $meId) {
                            $label .= ' (you)';
                        }
                        $adminLabels[] = '<span>' . $label . '</span>';
                    }
                ?>
                <article class="panel group-hero">
                    <div class="group-hero-head">
                        <div>
                            <h1 class="group-title"><?= security_encode($group['name']) ?></h1>
                            <p class="community-meta"><?= security_encode(implode(' · ', $metaParts)) ?></p>
                        </div>
                        <div class="community-actions">
                            <?php if (!$user): ?>
                                <a href="login.php" class="btn btn-ghost btn-small">Sign in to join</a>
                            <?php elseif ($viewerStatus === 'admin'): ?>
                                <span class="community-badge">Admin</span>
                            <?php elseif ($viewerStatus === 'member'): ?>
                                <span class="community-status">Joined</span>
                                <button type="button" class="btn btn-ghost btn-small community-action"
                                        data-action="leave" data-id="<?= $groupId ?>"
                                        data-confirm="Leave this group?">Leave</button>
                            <?php elseif ($viewerStatus === 'pending'): ?>
                                <span class="community-status">Waiting for approval</span>
                                <button type="button" class="btn btn-ghost btn-small community-action"
                                        data-action="leave" data-id="<?= $groupId ?>"
                                        data-confirm="Cancel your join request?">Cancel request</button>
                            <?php else: ?>
                                <button type="button" class="btn btn-primary btn-small community-action"
                                        data-action="join" data-id="<?= $groupId ?>">
                                    <?= $group['join_policy'] === 'approval' ? 'Request to join' : 'Join' ?>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <p class="community-desc"><?= nl2br(security_encode($group['description'])) ?></p>

                    <?php if ($adminLabels): ?>
                        <p class="community-admins">
                            <strong><?= count($adminLabels) === 1 ? 'Admin' : 'Admins' ?>:</strong>
                            <?= implode(', ', $adminLabels) ?>
                        </p>
                    <?php endif; ?>
                </article>

                <?php if ($pending): ?>
                    <section class="panel group-section">
                        <h2>Join requests <span class="group-count"><?= count($pending) ?></span></h2>
                        <ul class="member-list">
                            <?php foreach ($pending as $request): ?>
                                <li class="request-row">
                                    <?= group_avatar($request) ?>
                                    <div class="request-info">
                                        <p class="request-name"><?= security_encode($request['username']) ?></p>
                                        <p class="request-meta">
                                            On Porchlight since <?= security_encode(date('M Y', strtotime($request['member_since']))) ?>
                                            · asked <?= security_encode(date('M j, Y', strtotime($request['requested_at']))) ?>
                                        </p>
                                    </div>
                                    <div class="request-actions">
                                        <button type="button" class="btn btn-primary btn-small community-action"
                                                data-action="approve" data-id="<?= $groupId ?>"
                                                data-user="<?= (int)$request['id'] ?>">
                                            <?= icon('check') ?> Approve
                                        </button>
                                        <button type="button" class="btn btn-ghost btn-small community-action"
                                                data-action="decline" data-id="<?= $groupId ?>"
                                                data-user="<?= (int)$request['id'] ?>"
                                                data-confirm="Decline this request?">
                                            <?= icon('x') ?> Decline
                                        </button>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </section>
                <?php endif; ?>

                <section class="panel group-section">
                    <h2>Members <span class="group-count"><?= count($members) ?></span></h2>
                    <?php if (!$members): ?>
                        <p class="community-status">No members yet.</p>
                    <?php else: ?>
                        <ul class="member-list">
                            <?php foreach ($members as $member): ?>
                                <li class="member-row">
                                    <?= group_avatar($member) ?>
                                    <div class="member-info">
                                        <p class="member-name">
                                            <?= security_encode($member['username']) ?><?= (int)$member['id'] === $meId ? ' (you)' : '' ?>
                                        </p>
                                        <p class="member-meta">Joined <?= security_encode(date('M Y', strtotime($member['joined_at']))) ?></p>
                                    </div>
                                    <?php if ($member['role'] === 'admin'): ?>
                                        <span class="community-badge">Admin</span>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </section>
            <?php endif; ?>
        </main>
    </div>

    <?php require __DIR__ . '/includes/site-footer.php'; ?>

    <script src="js/script.js?v=<?= filemtime(__DIR__ . '/js/script.js') ?>"></script>
</body>
</html>
