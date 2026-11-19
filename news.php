<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/icons.php';

$user = $_SESSION['user'] ?? null;

$updates = [
    [
        'version' => 'v0.9',
        'date' => 'November 19, 2026',
        'title' => 'Delete posts & media previews',
        'items' => [
            'Delete your own posts from the feed or from Booksave.',
            'See a preview of the photo or video you picked before posting, with a Remove button.',
            'Clearer messages when a photo or video is too large to upload.',
        ],
    ],
    [
        'version' => 'v0.8',
        'date' => 'November 19, 2026',
        'title' => 'Photos, videos & Booksave',
        'items' => [
            'Attach a photo or video to a post, or post media with no text at all.',
            'Tap Save on a post to keep its photo or video in Booksave.',
            'Videos play right in the feed.',
        ],
    ],
    [
        'version' => 'v0.7',
        'date' => 'November 16, 2026',
        'title' => 'Profiles & friends',
        'items' => [
            'Upload a profile picture and update your display name.',
            'Name changes are available once every 30 days.',
            'Add friends first — messaging unlocks after a request is accepted.',
        ],
    ],
    [
        'version' => 'v0.6',
        'date' => 'November 16, 2026',
        'title' => 'Settings & dark mode',
        'items' => [
            'Switch accounts and manage security, location, likes, and post settings.',
            'Dark mode is shared across every page.',
        ],
    ],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patch Notes - Porchlight</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css?v=<?= filemtime(__DIR__ . '/css/style.css') ?>">
    <script src="js/theme.js"></script>
    <style>
        .news-main {
            max-width: 680px;
            width: 100%;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            gap: 14px;
        }
        .news-head { margin-bottom: 4px; }
        .news-eyebrow {
            display: block;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            font-size: 11px;
            font-weight: 600;
            color: var(--text-muted);
            margin-bottom: 6px;
        }
        .news-title {
            font-family: var(--font-serif);
            font-size: 30px;
            margin: 0 0 8px;
        }
        .news-sub {
            color: var(--text-secondary);
            font-size: 14px;
        }
        .news-entry { padding: 20px; }
        .news-entry-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 8px;
        }
        .news-version {
            font-size: 12px;
            font-weight: 600;
            color: var(--accent-strong);
            background: var(--surface-muted);
            border: 1px solid var(--border);
            border-radius: 999px;
            padding: 2px 10px;
        }
        .news-date { font-size: 12px; color: var(--text-muted); }
        .news-entry h2 {
            font-family: var(--font-serif);
            font-size: 20px;
            margin: 0 0 10px;
        }
        .news-entry ul {
            margin: 0;
            padding-left: 20px;
            list-style: disc;
            color: var(--text-secondary);
            font-size: 14px;
            line-height: 1.7;
        }
        .news-entry li + li { margin-top: 2px; }
        .news-foot {
            text-align: center;
            font-size: 13px;
            color: var(--text-muted);
            padding: 6px 0 24px;
        }
        .news-foot a { color: var(--accent-strong); }
    </style>
</head>
<body data-authenticated="<?= $user ? 'true' : 'false' ?>">
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
        <main class="feed news-main">
            <div class="news-head">
                <span class="news-eyebrow">News &amp; updates</span>
                <h1 class="news-title">Patch Notes</h1>
                <p class="news-sub">Everything that changed around the porch, newest first.</p>
            </div>

            <?php foreach ($updates as $update): ?>
                <article class="panel news-entry">
                    <div class="news-entry-head">
                        <span class="news-version"><?= htmlspecialchars($update['version']) ?></span>
                        <span class="news-date"><?= htmlspecialchars($update['date']) ?></span>
                    </div>
                    <h2><?= htmlspecialchars($update['title']) ?></h2>
                    <ul>
                        <?php foreach ($update['items'] as $item): ?>
                            <li><?= htmlspecialchars($item) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </article>
            <?php endforeach; ?>

            <p class="news-foot">
                Spotted a bug? Let us know in <a href="messages.php">Messages</a>.
            </p>
        </main>
    </div>

    <?php require __DIR__ . '/includes/site-footer.php'; ?>
</body>
</html>
