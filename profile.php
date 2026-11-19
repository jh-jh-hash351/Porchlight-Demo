<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/accounts.php';
require_once __DIR__ . '/includes/preferences.php';
require_once __DIR__ . '/includes/profile_edit.php';
require_once __DIR__ . '/includes/icons.php';

$user = $_SESSION['user'] ?? null;

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$user) {
        header('Location: login.php');
        exit;
    }

    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        die('Invalid CSRF token.');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'update_avatar') {
        $result = handle_profile_avatar_upload($pdo, (int)$user['id'], $_FILES['profile_pic'] ?? []);
    } elseif ($action === 'update_username') {
        $result = change_username($pdo, (int)$user['id'], trim($_POST['username'] ?? ''));
    } else {
        $result = ['error' => 'Unknown action.'];
    }

    $error = $result['error'] ?? '';
    $success = $result['success'] ?? '';
}

$profileUser = null;
$pic = null;
$picUrl = null;
$nameNote = null;
$canRename = false;
$locationLabel = '';

if ($user) {
    $profileUser = fetch_profile_user($pdo, (int)$user['id']);
}

if ($profileUser) {
    $pic = $profileUser['profile_pic'] ?? null;
    $picUrl = profile_pic_url($pic);
    $nameNote = name_change_note($profileUser);
    $canRename = $nameNote === null;

    $prefs = get_preferences($pdo, (int)$profileUser['id']);
    if ((int)$prefs['show_location'] === 1) {
        $city = trim($prefs['city']);
        $region = trim($prefs['region']);
        $locationLabel = trim($city . (($city !== '' && $region !== '') ? ', ' : '') . $region);
    }
}

$displayName = $profileUser['username'] ?? ($user['username'] ?? '');
$displayInitials = $profileUser ? initials_for($profileUser['username']) : ($user['initials'] ?? '??');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - Porchlight</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <script src="js/theme.js"></script>
    <style>
        .profile-card {
            max-width: 560px;
            margin: 40px auto;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-card);
            padding: 32px;
        }
        .profile-head {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            gap: 6px;
            padding-bottom: 24px;
            border-bottom: 1px solid var(--border);
        }
        .profile-pic {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            background: var(--surface-muted);
            border: 2px solid var(--accent);
            margin-bottom: 8px;
        }
        .profile-pic-fallback {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: var(--surface-muted);
            color: var(--accent-strong);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 38px;
            font-weight: 600;
            border: 2px solid var(--accent);
            margin-bottom: 8px;
        }
        .profile-name {
            font-family: var(--font-serif);
            font-size: 28px;
            margin: 0;
        }
        .profile-sub {
            color: var(--text-secondary);
            font-size: 14px;
        }
        .avatar-form {
            display: flex;
            gap: 10px;
            align-items: center;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 10px;
        }
        .avatar-form input[type="file"] { font-size: 12px; max-width: 240px; }
        .profile-section {
            margin-top: 24px;
        }
        .profile-section-title {
            font-weight: 500;
            margin-bottom: 12px;
        }
        .profile-form {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .profile-field {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        .profile-field label {
            font-size: 13px;
            font-weight: 500;
            color: var(--text-secondary);
        }
        .profile-field input {
            padding: 9px 12px;
            border: 1px solid var(--border);
            border-radius: var(--radius-control);
            background: var(--surface-muted);
            color: var(--text-primary);
            font: inherit;
            font-size: 14px;
            outline: none;
        }
        .profile-field input:focus { border-color: var(--accent); }
        .profile-field input:disabled { color: var(--text-muted); cursor: not-allowed; }
        .profile-note {
            font-size: 12px;
            color: var(--text-muted);
        }
        .profile-note.is-locked { color: var(--accent-strong); font-weight: 500; }
        .profile-meta {
            display: flex;
            flex-direction: column;
            gap: 6px;
            font-size: 14px;
            color: var(--text-secondary);
            margin-top: 8px;
        }
        .profile-meta strong { color: var(--text-primary); font-weight: 500; }
        .profile-actions {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-top: 28px;
            padding-top: 20px;
            border-top: 1px solid var(--border);
        }
        .profile-message {
            margin-bottom: 16px;
            font-size: 14px;
        }
        .profile-message.is-error { color: var(--like); }
        .profile-message.is-success { color: var(--positive); }
        .profile-guest {
            text-align: center;
            padding: 20px 0;
        }
    </style>
</head>
<body>
    <button class="icon-btn theme-toggle theme-float" aria-label="Toggle dark mode">
        <span class="icon-sun"><?= icon('sun') ?></span>
        <span class="icon-moon"><?= icon('moon') ?></span>
    </button>
    <div class="layout" style="justify-content: center;">
        <div class="profile-card">
            <?php if ($profileUser): ?>
                <div class="profile-head">
                    <?php if ($picUrl !== null): ?>
                        <img src="<?= security_encode($picUrl) ?>" class="profile-pic" alt="Profile picture">
                    <?php else: ?>
                        <div class="profile-pic-fallback" aria-hidden="true"><?= security_encode($displayInitials) ?></div>
                    <?php endif; ?>
                    <h1 class="profile-name"><?= security_encode($displayName) ?></h1>
                    <p class="profile-sub"><?= $locationLabel !== '' ? security_encode($locationLabel) : 'Neighborhood Member' ?></p>

                    <form class="avatar-form" action="profile.php" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        <input type="hidden" name="action" value="update_avatar">
                        <input type="file" name="profile_pic" accept="image/jpeg,image/png,image/gif" required>
                        <button type="submit" class="btn btn-primary" style="height: 32px;">Upload picture</button>
                    </form>
                </div>

                <?php if ($error): ?>
                    <div class="profile-message is-error" style="margin-top: 16px;"><?= security_encode($error) ?></div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="profile-message is-success" style="margin-top: 16px;"><?= security_encode($success) ?></div>
                <?php endif; ?>

                <div class="profile-section">
                    <p class="profile-section-title">Display name</p>
                    <form class="profile-form" action="profile.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        <input type="hidden" name="action" value="update_username">
                        <div class="profile-field">
                            <label for="username">Username</label>
                            <input type="text" id="username" name="username" maxlength="20" minlength="3"
                                   value="<?= security_encode($profileUser['username']) ?>"
                                   <?= $canRename ? '' : 'disabled' ?> required>
                            <span class="profile-note<?= $canRename ? '' : ' is-locked' ?>">
                                <?= $canRename
                                    ? 'Names can only be changed once every 30 days.'
                                    : security_encode($nameNote) ?>
                            </span>
                        </div>
                        <button type="submit" class="btn btn-primary" style="align-self: flex-start; height: 34px;" <?= $canRename ? '' : 'disabled' ?>>Save name</button>
                    </form>
                </div>

                <div class="profile-section">
                    <p class="profile-section-title">Account</p>
                    <div class="profile-meta">
                        <span>Email: <strong><?= security_encode($profileUser['email']) ?></strong></span>
                        <span>Member since: <strong><?= security_encode(date('M j, Y', strtotime($profileUser['created_at']))) ?></strong></span>
                    </div>
                </div>

                <div class="profile-actions">
                    <a href="settings.php" class="btn btn-ghost">Account settings</a>
                    <a href="index.php" class="btn btn-primary">Back to Feed</a>
                </div>
            <?php else: ?>
                <div class="profile-guest">
                    <h1 class="profile-name" style="margin-bottom: 10px;">Profile</h1>
                    <p class="profile-sub" style="margin-bottom: 20px;">Please <a href="login.php" style="color: var(--accent);">sign in</a> to view and edit your profile.</p>
                    <a href="index.php" class="btn btn-primary">Back to Feed</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php require __DIR__ . '/includes/site-footer.php'; ?>
</body>
</html>
