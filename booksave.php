<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/data.php';
require_once __DIR__ . '/includes/icons.php';

$user = $_SESSION['user'] ?? null;
$savedPosts = $user ? get_saved_posts($pdo, (int)$user['id']) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booksave - Porchlight</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <meta name="csrf-token" content="<?= generate_csrf_token() ?>">
    <script src="js/theme.js"></script>
    <style>
        .booksave-head {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 4px;
        }
        .booksave-title {
            font-family: var(--font-serif);
            font-size: 28px;
            margin: 0;
        }
        .booksave-count {
            font-size: 13px;
            color: var(--text-muted);
        }
        .booksave-sub {
            color: var(--text-secondary);
            font-size: 14px;
            margin-bottom: 18px;
        }
        .booksave-main {
            max-width: 680px;
            width: 100%;
            margin: 0 auto;
        }
    </style>
</head>
<body data-booksave="true" data-authenticated="<?= $user ? 'true' : 'false' ?>">
    <header class="topbar">
        <div class="topbar-inner">
            <a class="brand" href="index.php" style="display: flex; align-items: center; gap: 10px;">
                <img src="images/logo.png" alt="Porchlight Logo" style="height: 32px; width: auto; border-radius: 4px;">
                <span>Porchlight</span>
            </a>
            <div class="topbar-actions" style="margin-left: auto;">
                <a href="index.php" class="btn btn-ghost" style="height: 32px; font-size: 13px;">Back to Feed</a>
                <button class="icon-btn theme-toggle" aria-label="Toggle dark mode">
                    <span class="icon-sun"><?= icon('sun') ?></span>
                    <span class="icon-moon"><?= icon('moon') ?></span>
                </button>
            </div>
        </div>
    </header>

    <div class="layout">
        <main class="feed booksave-main">
            <?php if ($user): ?>
                <div class="booksave-head">
                    <h1 class="booksave-title">Booksave</h1>
                    <span class="booksave-count"><?= count($savedPosts) ?> saved</span>
                </div>
                <p class="booksave-sub">Photos and videos you saved from the neighborhood feed.</p>

                <div class="post-list" id="postList">
                    <?php foreach ($savedPosts as $post): ?>
                        <?php include __DIR__ . '/includes/post-card.php'; ?>
                    <?php endforeach; ?>
                </div>

                <div class="empty-state" id="booksaveEmpty" <?= $savedPosts ? 'hidden' : '' ?>>
                    <p>Nothing saved yet.</p>
                    <p style="margin-top: 8px;">Tap <strong>Save</strong> on a post in the feed to keep its photo or video here.</p>
                    <a href="index.php" class="btn btn-primary" style="margin-top: 18px;">Browse the feed</a>
                </div>
            <?php else: ?>
                <div class="panel" style="padding: 40px; text-align: center;">
                    <h1 class="booksave-title" style="margin-bottom: 12px;">Booksave</h1>
                    <p style="color: var(--text-secondary); margin-bottom: 20px;">Please <a href="login.php" style="color: var(--accent);">sign in</a> to see the posts you saved.</p>
                    <a href="index.php" class="btn btn-primary">Back to Feed</a>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <?php require __DIR__ . '/includes/site-footer.php'; ?>

    <script src="js/script.js"></script>
</body>
</html>
