<?php
/**
 * Profile editing helpers: avatar uploads and username changes
 * (name changes are limited to once every 30 days).
 */

require_once __DIR__ . '/accounts.php';

const PROFILE_NAME_COOLDOWN_DAYS = 30;

function ensure_profile_schema(PDO $pdo): void {
    static $ensured = false;
    if ($ensured) {
        return;
    }

    $columns = $pdo->query("SHOW COLUMNS FROM users LIKE 'username_changed_at'")->fetchAll();
    if (!$columns) {
        $pdo->exec('ALTER TABLE users ADD COLUMN username_changed_at DATETIME NULL DEFAULT NULL');
    }

    $ensured = true;
}

function profile_pic_url(?string $pic): ?string {
    if (!$pic) {
        return null;
    }
    $path = dirname(__DIR__) . '/' . ltrim($pic, '/\\');
    return is_file($path) ? $pic : null;
}

/**
 * Loads a user with cooldown data computed by MySQL, so PHP and MySQL
 * timezone settings can't disagree about the remaining days.
 */
function fetch_profile_user(PDO $pdo, int $userId): ?array {
    ensure_profile_schema($pdo);

    $days = PROFILE_NAME_COOLDOWN_DAYS;
    $stmt = $pdo->prepare("
        SELECT id, username, email, profile_pic, created_at, username_changed_at,
               TIMESTAMPDIFF(SECOND, NOW(), DATE_ADD(username_changed_at, INTERVAL $days DAY)) AS cooldown_seconds,
               DATE_FORMAT(DATE_ADD(username_changed_at, INTERVAL $days DAY), '%b %e, %Y') AS cooldown_until
        FROM users
        WHERE id = ?
    ");
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function name_change_note(array $row): ?string {
    $secondsLeft = $row['cooldown_seconds'] ?? null;
    if ($secondsLeft === null || (int)$secondsLeft <= 0) {
        return null;
    }

    $daysLeft = (int)ceil(((int)$secondsLeft) / 86400);
    $until = trim((string)($row['cooldown_until'] ?? ''));
    if ($until === '') {
        $until = 'later';
    }

    return 'You can change your name again on ' . $until
        . ' (' . $daysLeft . ' day' . ($daysLeft === 1 ? '' : 's') . ' left).';
}

function validate_username(string $name): ?string {
    if ($name === '') {
        return 'Please enter a name.';
    }
    if (mb_strlen($name) < 3 || mb_strlen($name) > 20) {
        return 'Names must be between 3 and 20 characters.';
    }
    if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) {
        return 'Names can only contain letters, numbers and underscores.';
    }
    return null;
}

/**
 * Changes the signed-in user's username.
 * Returns ['success' => string] or ['error' => string].
 */
function change_username(PDO $pdo, int $userId, string $newName): array {
    ensure_profile_schema($pdo);

    $row = fetch_profile_user($pdo, $userId);
    if (!$row) {
        return ['error' => 'Your account could not be found.'];
    }

    if (strcasecmp($newName, (string)$row['username']) === 0) {
        return ['success' => 'Your name is unchanged.'];
    }

    if ((int)($row['cooldown_seconds'] ?? 0) > 0) {
        $daysLeft = (int)ceil(((int)$row['cooldown_seconds']) / 86400);
        $until = trim((string)($row['cooldown_until'] ?? ''));
        return ['error' => 'You can only change your name once every '
            . PROFILE_NAME_COOLDOWN_DAYS . ' days. Try again on '
            . ($until !== '' ? $until : 'later') . ' (' . $daysLeft . ' day'
            . ($daysLeft === 1 ? '' : 's') . ' left).'];
    }

    $validationError = validate_username($newName);
    if ($validationError !== null) {
        return ['error' => $validationError];
    }

    $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ? AND id <> ?');
    $stmt->execute([$newName, $userId]);
    if ($stmt->fetch()) {
        return ['error' => 'That name is already taken.'];
    }

    $stmt = $pdo->prepare('UPDATE users SET username = ?, username_changed_at = NOW() WHERE id = ?');
    $stmt->execute([$newName, $userId]);

    $_SESSION['user']['username'] = $newName;
    $_SESSION['user']['initials'] = initials_for($newName);
    remember_account([
        'id' => $userId,
        'username' => $newName,
        'profile_pic' => $row['profile_pic'],
    ]);

    return ['success' => 'Your name was updated. You can change it again in '
        . PROFILE_NAME_COOLDOWN_DAYS . ' days.'];
}

/**
 * Handles an uploaded profile picture.
 * Returns ['success' => string] or ['error' => string].
 */
function handle_profile_avatar_upload(PDO $pdo, int $userId, array $file): array {
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['error' => 'Choose an image to upload.'];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['error' => 'Failed to upload image.'];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);
    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
    ];

    if (!isset($extensions[$mimeType])) {
        return ['error' => 'Invalid file type. Please upload a JPG, PNG or GIF.'];
    }

    $filename = bin2hex(random_bytes(16)) . '.' . $extensions[$mimeType];
    $uploadsDir = dirname(__DIR__) . '/uploads';

    if (!is_dir($uploadsDir) && !mkdir($uploadsDir, 0755, true) && !is_dir($uploadsDir)) {
        return ['error' => 'Failed to upload image.'];
    }

    if (!move_uploaded_file($file['tmp_name'], $uploadsDir . '/' . $filename)) {
        return ['error' => 'Failed to upload image.'];
    }

    $storedPath = 'uploads/' . $filename;
    $stmt = $pdo->prepare('UPDATE users SET profile_pic = ? WHERE id = ?');
    $stmt->execute([$storedPath, $userId]);

    $stmt = $pdo->prepare('SELECT id, username, profile_pic FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    if ($row) {
        remember_account($row);
    }

    return ['success' => 'Profile picture updated successfully!'];
}
?>
