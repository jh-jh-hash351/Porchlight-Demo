<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/auth_guard.php';
require_once __DIR__ . '/includes/accounts.php';
require_once __DIR__ . '/includes/preferences.php';
require_once __DIR__ . '/includes/profile_edit.php';
require_once __DIR__ . '/includes/icons.php';

require_auth();

$user = $_SESSION['user'];

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Verification
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        die('Invalid CSRF token.');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'switch_account') {
        if (switch_to_account($pdo, (int)($_POST['account_id'] ?? 0))) {
            header('Location: index.php');
            exit;
        }
        $error = 'That account is no longer available.';
    } elseif ($action === 'remove_account') {
        $accountId = (int)($_POST['account_id'] ?? 0);
        if (isset(accounts_list()[$accountId]) && (int)$user['id'] !== $accountId) {
            forget_account($accountId);
            $success = 'Account removed from the switcher.';
        }
    } elseif ($action === 'change_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        $stmt = $pdo->prepare('SELECT password FROM users WHERE id = ?');
        $stmt->execute([$user['id']]);
        $storedHash = $stmt->fetchColumn();

        if (!$storedHash || !password_verify($currentPassword, $storedHash)) {
            $error = 'Your current password is incorrect.';
        } elseif (strlen($newPassword) < 8) {
            $error = 'Your new password must be at least 8 characters.';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'The new passwords do not match.';
        } elseif ($newPassword === $currentPassword) {
            $error = 'Your new password must be different from the current one.';
        } else {
            $stmt = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
            $stmt->execute([password_hash($newPassword, PASSWORD_DEFAULT), $user['id']]);
            $success = 'Password updated.';
        }
    } elseif ($action === 'update_location') {
        save_preferences($pdo, (int)$user['id'], [
            'city' => substr(trim($_POST['city'] ?? ''), 0, 100),
            'region' => substr(trim($_POST['region'] ?? ''), 0, 100),
            'show_location' => isset($_POST['show_location']) ? 1 : 0,
        ]);
        $success = 'Location and region saved.';
    } elseif ($action === 'update_likes') {
        save_preferences($pdo, (int)$user['id'], [
            'hide_like_counts' => isset($_POST['hide_like_counts']) ? 1 : 0,
            'allow_likes' => isset($_POST['allow_likes']) ? 1 : 0,
        ]);
        $success = 'Like preferences saved.';
    } elseif ($action === 'update_posts') {
        $commentPolicy = $_POST['can_comment'] ?? 'everyone';
        if (!in_array($commentPolicy, ['everyone', 'only_me', 'nobody'], true)) {
            $commentPolicy = 'everyone';
        }

        $visibility = $_POST['default_visibility'] ?? 'public';
        if (!in_array($visibility, ['public', 'only_me'], true)) {
            $visibility = 'public';
        }

        save_preferences($pdo, (int)$user['id'], [
            'can_comment' => $commentPolicy,
            'default_visibility' => $visibility,
        ]);
        $success = 'Post settings saved.';
    } elseif ($action === 'update_avatar') {
        $result = handle_profile_avatar_upload($pdo, (int)$user['id'], $_FILES['profile_pic'] ?? []);
        if (isset($result['error'])) {
            $error = $result['error'];
        } else {
            $success = $result['success'];
        }
    }
}

// Keep the signed-in account pinned at the top of the switcher with fresh details
$stmt = $pdo->prepare('SELECT id, username, email, profile_pic FROM users WHERE id = ?');
$stmt->execute([$user['id']]);
$currentAccount = $stmt->fetch() ?: null;

if ($currentAccount) {
    remember_account($currentAccount);
}

$pic = $currentAccount ? profile_pic_url($currentAccount['profile_pic']) : null;

$prefs = get_preferences($pdo, (int)$user['id']);

$loginActivity = [];
if ($currentAccount) {
    $stmtActivity = $pdo->prepare('
        SELECT ip_address, success, attempt_time
        FROM login_attempts
        WHERE username = ? OR username = ?
        ORDER BY attempt_time DESC
        LIMIT 5
    ');
    $stmtActivity->execute([$currentAccount['username'], $currentAccount['email']]);
    $loginActivity = $stmtActivity->fetchAll();
}

$accounts = accounts_list();
$currentId = (int)$user['id'];
if (isset($accounts[$currentId])) {
    $currentAccountEntry = $accounts[$currentId];
    unset($accounts[$currentId]);
    $accounts = [$currentId => $currentAccountEntry] + $accounts;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Porchlight</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <script src="js/theme.js"></script>
    <style>
        .settings-card {
            max-width: 600px;
            margin: 40px auto;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-card);
            padding: 32px;
        }
        .settings-title {
            font-family: var(--font-serif);
            margin-bottom: 24px;
        }
        .auth-message {
            text-align: left;
            margin-bottom: 16px;
            font-size: 14px;
        }
        .auth-message.is-error { color: var(--like); }
        .auth-message.is-success { color: var(--positive); }
        .profile-upload {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 16px;
            margin-bottom: 32px;
            padding-bottom: 32px;
            border-bottom: 1px solid var(--border);
        }
        .current-pic {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            background: var(--surface-muted);
            border: 2px solid var(--accent);
        }
        .avatar-fallback {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: var(--surface-muted);
            color: var(--accent-strong);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            font-weight: 600;
            border: 2px solid var(--accent);
        }
        .settings-section + .settings-section {
            margin-top: 28px;
            padding-top: 28px;
            border-top: 1px solid var(--border);
        }
        .settings-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
            font-weight: 500;
        }
        .settings-heading a {
            font-size: 13px;
            font-weight: 600;
            color: var(--accent-strong);
        }
        .account-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .account-row {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 10px;
            border-radius: var(--radius-control);
        }
        .account-row:hover { background: var(--surface-muted); }
        .account-avatar {
            width: 46px;
            height: 46px;
            flex-shrink: 0;
            border-radius: 50%;
            object-fit: cover;
            background: var(--surface-muted);
            border: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
            font-weight: 600;
            color: var(--accent-strong);
            overflow: hidden;
        }
        .account-meta {
            flex: 1;
            min-width: 0;
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        .account-name {
            font-size: 14px;
            font-weight: 500;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .account-hint {
            font-size: 12px;
            color: var(--text-muted);
        }
        .account-badge {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .05em;
            text-transform: uppercase;
            color: var(--positive);
        }
        .account-actions {
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .account-switch-btn,
        .account-remove-btn {
            background: none;
            border: none;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            padding: 6px 10px;
            border-radius: var(--radius-control);
        }
        .account-switch-btn { color: var(--accent-strong); }
        .account-remove-btn {
            color: var(--text-muted);
            font-size: 16px;
            line-height: 1;
            padding: 6px 8px;
        }
        .account-switch-btn:hover,
        .account-remove-btn:hover { background: var(--surface); }
        .account-remove-btn:hover { color: var(--like); }
        .add-account-row {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 10px;
            border-radius: var(--radius-control);
            color: var(--accent-strong);
            font-size: 14px;
            font-weight: 600;
        }
        .add-account-row:hover { background: var(--surface-muted); }
        .add-account-icon {
            width: 46px;
            height: 46px;
            flex-shrink: 0;
            border-radius: 50%;
            border: 1px dashed var(--border-strong);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            font-weight: 400;
            color: var(--text-secondary);
        }
        .settings-actions {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 32px;
        }
        .settings-group {
            border-top: 1px solid var(--border);
            margin-top: 28px;
            padding-top: 18px;
        }
        .settings-group > summary {
            display: flex;
            align-items: center;
            justify-content: space-between;
            cursor: pointer;
            font-weight: 500;
            list-style: none;
            padding: 4px 0;
        }
        .settings-group > summary::-webkit-details-marker { display: none; }
        .summary-arrow {
            color: var(--text-muted);
            font-size: 20px;
            line-height: 1;
            transition: transform .15s ease;
        }
        .settings-group[open] .summary-arrow { transform: rotate(90deg); }
        .settings-form {
            display: flex;
            flex-direction: column;
            gap: 12px;
            padding-top: 16px;
        }
        .settings-field {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        .settings-field label {
            font-size: 13px;
            font-weight: 500;
            color: var(--text-secondary);
        }
        .settings-field input[type="text"],
        .settings-field input[type="password"],
        .settings-field select {
            padding: 9px 12px;
            border: 1px solid var(--border);
            border-radius: var(--radius-control);
            background: var(--surface-muted);
            color: var(--text-primary);
            font: inherit;
            font-size: 14px;
            outline: none;
        }
        .settings-field input:focus,
        .settings-field select:focus { border-color: var(--accent); }
        .settings-check {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
            color: var(--text-primary);
            cursor: pointer;
        }
        .settings-check input { accent-color: var(--accent); width: 16px; height: 16px; }
        .settings-note {
            font-size: 12px;
            color: var(--text-muted);
            margin-top: -4px;
        }
        .activity-list {
            list-style: none;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            gap: 8px;
            font-size: 13px;
            color: var(--text-secondary);
        }
        .activity-list .is-ok { color: var(--positive); font-weight: 600; }
        .activity-list .is-bad { color: var(--like); font-weight: 600; }
        .activity-time { color: var(--text-muted); }
    </style>
</head>
<body>
    <button class="icon-btn theme-toggle theme-float" aria-label="Toggle dark mode">
        <span class="icon-sun"><?= icon('sun') ?></span>
        <span class="icon-moon"><?= icon('moon') ?></span>
    </button>
    <div class="layout" style="justify-content: center;">
        <div class="settings-card">
            <h1 class="settings-title">Account Settings</h1>

            <?php if ($error): ?>
                <div class="auth-message is-error"><?= security_encode($error) ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="auth-message is-success"><?= security_encode($success) ?></div>
            <?php endif; ?>

            <div class="profile-upload">
                <?php if ($pic): ?>
                    <img src="<?= security_encode($pic) ?>" class="current-pic" alt="Profile">
                <?php else: ?>
                    <div class="avatar-fallback"><?= security_encode($user['initials']) ?></div>
                <?php endif; ?>

                <form action="settings.php" method="POST" enctype="multipart/form-data" style="display: flex; gap: 10px; align-items: center;">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    <input type="hidden" name="action" value="update_avatar">
                    <input type="file" name="profile_pic" accept="image/*" required style="font-size: 12px;">
                    <button type="submit" class="btn btn-primary" style="height: 32px;">Upload</button>
                </form>
            </div>

            <div class="settings-section">
                <div class="settings-heading">
                    <span>Switch accounts</span>
                    <a href="login.php?add=1">Add account</a>
                </div>

                <ul class="account-list">
                    <?php foreach ($accounts as $account): ?>
                        <?php $isCurrent = (int)$account['id'] === $currentId; ?>
                        <li class="account-row">
                            <?php $accountPic = profile_pic_url($account['profile_pic'] ?? null); ?>
                            <?php if ($accountPic !== null): ?>
                                <img src="<?= security_encode($accountPic) ?>" class="account-avatar" alt="">
                            <?php else: ?>
                                <div class="account-avatar"><?= security_encode($account['initials']) ?></div>
                            <?php endif; ?>
                            <div class="account-meta">
                                <span class="account-name"><?= security_encode($account['username']) ?></span>
                                <?php if ($isCurrent): ?>
                                    <span class="account-badge">Current</span>
                                <?php else: ?>
                                    <span class="account-hint">Tap switch to use this account</span>
                                <?php endif; ?>
                            </div>
                            <div class="account-actions">
                                <?php if (!$isCurrent): ?>
                                    <form action="settings.php" method="POST">
                                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                        <input type="hidden" name="action" value="switch_account">
                                        <input type="hidden" name="account_id" value="<?= (int)$account['id'] ?>">
                                        <button type="submit" class="account-switch-btn">Switch</button>
                                    </form>
                                    <form action="settings.php" method="POST">
                                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                        <input type="hidden" name="action" value="remove_account">
                                        <input type="hidden" name="account_id" value="<?= (int)$account['id'] ?>">
                                        <button type="submit" class="account-remove-btn" title="Remove <?= security_encode($account['username']) ?>" aria-label="Remove <?= security_encode($account['username']) ?>">&times;</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <a href="login.php?add=1" class="add-account-row">
                    <span class="add-account-icon">+</span>
                    <span>Add another account</span>
                </a>
            </div>

            <div class="settings-section">
                <p style="font-weight: 500; margin-bottom: 8px;">User Information</p>
                <p style="color: var(--text-secondary); font-size: 14px;">Username: <strong><?= security_encode($user['username']) ?></strong></p>
                <p style="font-size: 13px; margin-top: 6px;">
                    <a href="profile.php" style="color: var(--accent-strong); font-weight: 600;">Edit your name and picture on your profile &rsaquo;</a>
                </p>
            </div>

            <details class="settings-group">
                <summary><span>Security</span><span class="summary-arrow" aria-hidden="true">&rsaquo;</span></summary>

                <form class="settings-form" action="settings.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    <input type="hidden" name="action" value="change_password">
                    <div class="settings-field">
                        <label for="current_password">Current password</label>
                        <input type="password" id="current_password" name="current_password" autocomplete="current-password" required>
                    </div>
                    <div class="settings-field">
                        <label for="new_password">New password</label>
                        <input type="password" id="new_password" name="new_password" minlength="8" autocomplete="new-password" required>
                        <span class="settings-note">Use at least 8 characters.</span>
                    </div>
                    <div class="settings-field">
                        <label for="confirm_password">Confirm new password</label>
                        <input type="password" id="confirm_password" name="confirm_password" minlength="8" autocomplete="new-password" required>
                    </div>
                    <button type="submit" class="btn btn-primary" style="align-self: flex-start; height: 34px;">Update password</button>
                </form>

                <?php if ($loginActivity): ?>
                    <div style="margin-top: 20px;">
                        <p style="font-size: 13px; font-weight: 500; margin-bottom: 10px;">Recent sign-in activity</p>
                        <ul class="activity-list">
                            <?php foreach ($loginActivity as $attempt): ?>
                                <li>
                                    <span class="<?= (int)$attempt['success'] === 1 ? 'is-ok' : 'is-bad' ?>"><?= (int)$attempt['success'] === 1 ? 'Successful' : 'Failed' ?></span>
                                    <span class="activity-time">&middot; <?= security_encode(date('M j, H:i', strtotime($attempt['attempt_time']))) ?> &middot; <?= security_encode($attempt['ip_address']) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </details>

            <details class="settings-group">
                <summary><span>Location &amp; Region</span><span class="summary-arrow" aria-hidden="true">&rsaquo;</span></summary>

                <form class="settings-form" action="settings.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    <input type="hidden" name="action" value="update_location">
                    <div class="settings-field">
                        <label for="city">Neighborhood or city</label>
                        <input type="text" id="city" name="city" maxlength="100" value="<?= security_encode($prefs['city']) ?>" placeholder="e.g. Maple Heights">
                    </div>
                    <div class="settings-field">
                        <label for="region">Region, state or country</label>
                        <input type="text" id="region" name="region" maxlength="100" value="<?= security_encode($prefs['region']) ?>" placeholder="e.g. Ohio, United States">
                    </div>
                    <label class="settings-check">
                        <input type="checkbox" name="show_location" value="1" <?= (int)$prefs['show_location'] === 1 ? 'checked' : '' ?>>
                        Show my location on my profile
                    </label>
                    <button type="submit" class="btn btn-primary" style="align-self: flex-start; height: 34px;">Save location</button>
                </form>
            </details>

            <details class="settings-group">
                <summary><span>Likes</span><span class="summary-arrow" aria-hidden="true">&rsaquo;</span></summary>

                <form class="settings-form" action="settings.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    <input type="hidden" name="action" value="update_likes">
                    <label class="settings-check">
                        <input type="checkbox" name="hide_like_counts" value="1" <?= (int)$prefs['hide_like_counts'] === 1 ? 'checked' : '' ?>>
                        Hide like counts on posts
                    </label>
                    <span class="settings-note">Applies everywhere you browse in Porchlight.</span>
                    <label class="settings-check">
                        <input type="checkbox" name="allow_likes" value="1" <?= (int)$prefs['allow_likes'] === 1 ? 'checked' : '' ?>>
                        Allow people to like my posts
                    </label>
                    <span class="settings-note">When off, only you can like your own posts.</span>
                    <button type="submit" class="btn btn-primary" style="align-self: flex-start; height: 34px;">Save like preferences</button>
                </form>
            </details>

            <details class="settings-group">
                <summary><span>Posts</span><span class="summary-arrow" aria-hidden="true">&rsaquo;</span></summary>

                <form class="settings-form" action="settings.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    <input type="hidden" name="action" value="update_posts">
                    <div class="settings-field">
                        <label for="can_comment">Who can comment on my posts</label>
                        <select id="can_comment" name="can_comment">
                            <option value="everyone" <?= $prefs['can_comment'] === 'everyone' ? 'selected' : '' ?>>Everyone</option>
                            <option value="only_me" <?= $prefs['can_comment'] === 'only_me' ? 'selected' : '' ?>>Only me</option>
                            <option value="nobody" <?= $prefs['can_comment'] === 'nobody' ? 'selected' : '' ?>>Nobody</option>
                        </select>
                    </div>
                    <div class="settings-field">
                        <label for="default_visibility">Default visibility for new posts</label>
                        <select id="default_visibility" name="default_visibility">
                            <option value="public" <?= $prefs['default_visibility'] === 'public' ? 'selected' : '' ?>>Everyone (public feed)</option>
                            <option value="only_me" <?= $prefs['default_visibility'] === 'only_me' ? 'selected' : '' ?>>Only me</option>
                        </select>
                        <span class="settings-note">Existing posts stay as they are.</span>
                    </div>
                    <button type="submit" class="btn btn-primary" style="align-self: flex-start; height: 34px;">Save post settings</button>
                </form>
            </details>

            <div class="settings-actions">
                <a href="logout.php" class="btn btn-ghost">Log out</a>
                <a href="index.php" class="btn btn-ghost">Back to Feed</a>
            </div>
        </div>
    </div>

    <?php require __DIR__ . '/includes/site-footer.php'; ?>
</body>
</html>
