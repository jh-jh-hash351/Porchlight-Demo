<?php
/**
 * Post media helpers: image/video uploads and the posts.media_type column.
 */

function ensure_posts_media_schema(PDO $pdo): void {
    static $ensured = false;
    if ($ensured) {
        return;
    }

    $columns = $pdo->query("SHOW COLUMNS FROM posts LIKE 'media_type'")->fetchAll();
    if (!$columns) {
        $pdo->exec("ALTER TABLE posts ADD COLUMN media_type VARCHAR(10) NOT NULL DEFAULT 'image'");
    }

    $ensured = true;
}

/**
 * Stores an uploaded image or video for a post.
 * Returns ['path' => ?string, 'type' => ?string] or ['error' => string].
 */
function store_post_media(array $file): array {
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['path' => null, 'type' => null];
    }
    if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
        return ['error' => 'That file is larger than the server upload limit (' . ini_get('upload_max_filesize') . '). Choose a smaller file or raise upload_max_filesize in php.ini.'];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['error' => 'Failed to upload the file. It may be larger than the server allows.'];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);

    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'video/mp4' => 'mp4',
        'video/webm' => 'webm',
        'video/quicktime' => 'mov',
        'video/ogg' => 'ogv',
    ];

    if (!isset($extensions[$mimeType])) {
        return ['error' => 'Unsupported file type. Use JPG, PNG, GIF, WEBP, MP4, WEBM, MOV or OGG.'];
    }

    $uploadsDir = dirname(__DIR__) . '/uploads';
    if (!is_dir($uploadsDir) && !mkdir($uploadsDir, 0755, true) && !is_dir($uploadsDir)) {
        return ['error' => 'Could not create the uploads folder.'];
    }

    $filename = 'post_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $extensions[$mimeType];
    if (!move_uploaded_file($file['tmp_name'], $uploadsDir . '/' . $filename)) {
        return ['error' => 'Failed to save the uploaded file.'];
    }

    return [
        'path' => 'uploads/' . $filename,
        'type' => strpos($mimeType, 'video/') === 0 ? 'video' : 'image',
    ];
}
?>
