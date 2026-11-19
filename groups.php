<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/icons.php';
require_once __DIR__ . '/includes/communities.php';

$user = $_SESSION['user'] ?? null;
$meId = $user ? (int)$user['id'] : 0;

$communities = get_communities($pdo, $meId ?: null);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Groups - Porchlight</title>
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
                <a href="index.php" class="btn btn-ghost btn-small">Back to Feed</a>
                <button class="icon-btn theme-toggle" aria-label="Toggle dark mode">
                    <span class="icon-sun"><?= icon('sun') ?></span>
                    <span class="icon-moon"><?= icon('moon') ?></span>
                </button>
            </div>
        </div>
    </header>

    <div class="layout">
        <main class="feed groups-main">
            <div class="groups-head">
                <h1 class="groups-title">Groups</h1>
                <span class="groups-count"><?= count($communities) ?> <?= count($communities) === 1 ? 'group' : 'groups' ?></span>
            </div>
            <p class="groups-sub">Join a group — or start one and choose how neighbors get in.</p>

            <?php if ($user): ?>
                <details class="panel community-create" <?= $communities ? '' : 'open' ?>>
                    <summary><?= icon('plus') ?> Create a group</summary>
                    <form class="community-form" id="communityForm">
                        <div>
                            <label class="field-label" for="communityName">Group name</label>
                            <input type="text" id="communityName" name="name" maxlength="80" required placeholder="e.g. Maple Street Gardeners">
                        </div>
                        <div>
                            <label class="field-label" for="communityDescription">What is it about?</label>
                            <textarea id="communityDescription" name="description" rows="3" maxlength="1000" required placeholder="Tell neighbors what this group is for."></textarea>
                        </div>
                        <div>
                            <label class="field-label">How should people join?</label>
                            <div class="policy-options">
                                <label class="policy-option">
                                    <input type="radio" name="join_policy" value="open" checked>
                                    <strong>Open — anyone can join</strong>
                                    <span>Neighbors join instantly. No approval needed.</span>
                                </label>
                                <label class="policy-option">
                                    <input type="radio" name="join_policy" value="approval">
                                    <strong>Approval — I review requests</strong>
                                    <span>You see who asked and approve or decline.</span>
                                </label>
                            </div>
                        </div>
                        <div class="community-form-footer">
                            <span class="community-error" id="communityError"></span>
                            <button type="submit" class="btn btn-primary">Create group</button>
                        </div>
                    </form>
                </details>
            <?php else: ?>
                <div class="panel community-guest">
                    <p>Sign in to create a group, join one, or request to join.</p>
                    <div class="community-guest-actions">
                        <a href="login.php" class="btn btn-ghost btn-small">Sign In</a>
                        <a href="signup.php" class="btn btn-primary btn-small">Sign Up</a>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!$communities): ?>
                <div class="empty-state">
                    <p>No groups yet.</p>
                    <p style="margin-top: 6px;">
                        <?= $user ? 'Create the first one above.' : 'Sign in to start the first one.' ?>
                    </p>
                </div>
            <?php endif; ?>

            <?php foreach ($communities as $community): ?>
                <?php
                    $communityId = (int)$community['id'];
                    $viewerStatus = $community['viewer_status'];
                    $metaParts = [
                        $community['member_count'] === 1 ? '1 member' : $community['member_count'] . ' members',
                        community_policy_label($community['join_policy']),
                    ];
                    if ($viewerStatus === 'admin' && $community['pending_count'] > 0) {
                        $metaParts[] = $community['pending_count'] . ' waiting for approval';
                    }
                    $adminLabels = [];
                    foreach ($community['admins'] as $admin) {
                        $label = security_encode($admin['username']);
                        if ((int)$admin['id'] === $meId) {
                            $label .= ' (you)';
                        }
                        $adminLabels[] = '<span>' . $label . '</span>';
                    }
                ?>
                <article class="panel community-card" data-community="<?= $communityId ?>">
                    <div class="community-card-head">
                        <div>
                            <h2 class="community-name">
                                <a href="group.php?id=<?= $communityId ?>"><?= security_encode($community['name']) ?></a>
                            </h2>
                            <p class="community-meta"><?= security_encode(implode(' · ', $metaParts)) ?></p>
                        </div>
                        <div class="community-actions">
                            <a href="group.php?id=<?= $communityId ?>" class="btn btn-ghost btn-small">View</a>
                            <?php if (!$user): ?>
                                <a href="login.php" class="btn btn-ghost btn-small">Sign in to join</a>
                            <?php elseif ($viewerStatus === 'admin'): ?>
                                <span class="community-badge">Admin</span>
                            <?php elseif ($viewerStatus === 'member'): ?>
                                <span class="community-status">Joined</span>
                                <button type="button" class="btn btn-ghost btn-small community-action"
                                        data-action="leave" data-id="<?= $communityId ?>"
                                        data-confirm="Leave this group?">Leave</button>
                            <?php elseif ($viewerStatus === 'pending'): ?>
                                <span class="community-status">Waiting for approval</span>
                                <button type="button" class="btn btn-ghost btn-small community-action"
                                        data-action="leave" data-id="<?= $communityId ?>"
                                        data-confirm="Cancel your join request?">Cancel request</button>
                            <?php else: ?>
                                <button type="button" class="btn btn-primary btn-small community-action"
                                        data-action="join" data-id="<?= $communityId ?>">
                                    <?= $community['join_policy'] === 'approval' ? 'Request to join' : 'Join' ?>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <p class="community-desc"><?= nl2br(security_encode($community['description'])) ?></p>

                    <?php if ($adminLabels): ?>
                        <p class="community-admins">
                            <strong><?= count($adminLabels) === 1 ? 'Admin' : 'Admins' ?>:</strong>
                            <?= implode(', ', $adminLabels) ?>
                        </p>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </main>
    </div>

    <?php require __DIR__ . '/includes/site-footer.php'; ?>

    <script src="js/script.js?v=<?= filemtime(__DIR__ . '/js/script.js') ?>"></script>
</body>
</html>
