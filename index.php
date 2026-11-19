<?php
require_once __DIR__ . '/includes/data.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/icons.php';
$posts = get_all_posts();

// Get current user info if logged in
$user = $_SESSION['user'] ?? null;
$userName = $user ? $user['username'] : 'Guest';
$userInitials = $user ? $user['initials'] : '??';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Porchlight</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/style.css?v=<?= filemtime(__DIR__ . '/css/style.css') ?>">
<meta name="csrf-token" content="<?= generate_csrf_token() ?>">
<script src="js/theme.js"></script>
</head>
<body data-authenticated="<?= $user ? 'true' : 'false' ?>">

<header class="topbar">
  <div class="topbar-inner">
    <button class="icon-btn nav-toggle" id="navToggle" aria-label="Open navigation"><?= icon('menu') ?></button>
    <a class="brand" href="index.php" style="display: flex; align-items: center; gap: 10px;">
      <img src="images/logo.png" alt="Porchlight Logo" style="height: 32px; width: auto; border-radius: 4px;">
      <span>Porchlight</span>
    </a>
    <div class="search">
      <?= icon('search') ?>
      <input type="text" id="searchInput" placeholder="Search neighbors and posts">
    </div>
    <div class="topbar-actions">
      <?php if (!$user): ?>
        <a href="login.php" class="btn btn-ghost" style="height: 32px; font-size: 13px;">Sign In</a>
        <a href="signup.php" class="btn btn-primary" style="height: 32px; font-size: 13px;">Sign Up</a>
        <button class="icon-btn theme-toggle" aria-label="Toggle dark mode">
          <span class="icon-sun"><?= icon('sun') ?></span>
          <span class="icon-moon"><?= icon('moon') ?></span>
        </button>
      <?php else: ?>
        <button class="icon-btn" aria-label="Notifications"><?= icon('bell') ?></button>
        <button class="icon-btn" aria-label="Messages"><?= icon('mail') ?></button>
        <button class="icon-btn theme-toggle" id="themeToggle" aria-label="Toggle dark mode">
          <span class="icon-sun"><?= icon('sun') ?></span>
          <span class="icon-moon"><?= icon('moon') ?></span>
        </button>
        <div class="avatar" title="<?= htmlspecialchars($userName) ?>" aria-hidden="true"><?= htmlspecialchars($userInitials) ?></div>
        <a href="logout.php" class="btn btn-ghost" style="height: 32px; font-size: 13px;">Logout</a>
      <?php endif; ?>
    </div>
  </div>
</header>

<div class="overlay" id="overlay"></div>

<div class="layout">

  <aside class="sidebar-left" id="sidebarLeft">
    <nav class="nav">
      <a class="nav-item is-active" href="index.php"><?= icon('home') ?><span>Home</span></a>
      <a class="nav-item" href="explore.php"><?= icon('compass') ?><span>Explore</span></a>
      <a class="nav-item" href="messages.php"><?= icon('message') ?><span>Messages</span></a>
      <a class="nav-item" href="groups.php"><?= icon('users') ?><span>Groups</span></a>
      <a class="nav-item" href="profile.php"><?= icon('user') ?><span>Profile</span></a>
      <a class="nav-item" href="settings.php"><?= icon('settings') ?><span>Settings</span></a>
      <a class="nav-item" href="booksave.php"><?= icon('bookmark') ?><span>Booksave</span></a>
    </nav>
  </aside>

  <main class="feed">
    <form class="composer" id="composerForm" data-authenticated="<?= $user ? 'true' : 'false' ?>">
      <div class="avatar" aria-hidden="true"><?= htmlspecialchars($userInitials) ?></div>
      <div class="composer-body">
        <textarea id="composerText" placeholder="Share something with the neighborhood" rows="2"></textarea>
        <div class="composer-preview" id="composerPreview" hidden>
          <img id="composerPreviewImage" alt="Selected photo preview" hidden>
          <video id="composerPreviewVideo" controls playsinline muted hidden></video>
          <div class="composer-preview-meta">
            <span class="composer-preview-name" id="composerPreviewName"></span>
            <button type="button" class="btn btn-ghost composer-preview-remove" id="composerPreviewRemove">Remove</button>
          </div>
        </div>
        <div class="composer-actions">
          <span class="composer-error" id="composerError"></span>
          <label class="btn btn-ghost" style="cursor: pointer; height: 32px; font-size: 13px;">
            <?= icon('image') ?> <span style="margin-left: 4px;">Photo/Video</span>
            <input type="file" id="composerImage" accept="image/*,video/*" style="display: none;">
          </label>
          <button type="submit" class="btn btn-primary">Post</button>
        </div>
      </div>
    </form>

    <div class="post-list" id="postList">
      <?php foreach ($posts as $post): ?>
        <?php include __DIR__ . '/includes/post-card.php'; ?>
      <?php endforeach; ?>
    </div>

    <p class="empty-state" id="emptyState" hidden>No posts match your search.</p>
  </main>

  <aside class="sidebar-right">
    <div class="panel">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
        <p class="panel-title" style="margin: 0;">Local Spots</p>
        <button id="locateMe" class="icon-btn" style="width: 24px; height: 24px; font-size: 12px;" title="Update location">
          <?= icon('compass') ?>
        </button>
      </div>
      <ul class="tag-list" id="localSpotsList">
        <li><a href="https://www.google.com/maps/search/cafe+near+me" target="_blank" data-query="cafe">☕ Local Cafe</a></li>
        <li><a href="https://www.google.com/maps/search/bakery+near+me" target="_blank" data-query="bakery">🥐 Neighborhood Bakery</a></li>
        <li><a href="https://www.google.com/maps/search/grocery+store+near+me" target="_blank" data-query="grocery+store">🛒 Corner Store</a></li>
      </ul>
    </div>
    <div class="panel">
      <p class="panel-title">People to follow</p>
      <ul class="people-list">
        <li>
          <div class="avatar avatar-sm">CT</div>
          <span>Cong TV <span style="color: #1da1f2; margin-left: 2px;">✓</span></span>
          <button class="btn btn-ghost follow-btn" data-id="3" type="button">Follow</button>
        </li>
        <li>
          <div class="avatar avatar-sm">MY</div>
          <span>Mimiyuuuh <span style="color: #1da1f2; margin-left: 2px;">✓</span></span>
          <button class="btn btn-ghost follow-btn" data-id="4" type="button">Follow</button>
        </li>
        <li>
          <div class="avatar avatar-sm">IA</div>
          <span>Ivana Alawi <span style="color: #1da1f2; margin-left: 2px;">✓</span></span>
          <button class="btn btn-ghost follow-btn" data-id="5" type="button">Follow</button>
        </li>
        <li>
          <div class="avatar avatar-sm">NG</div>
          <span>Niana Guerrero <span style="color: #1da1f2; margin-left: 2px;">✓</span></span>
          <button class="btn btn-ghost follow-btn" data-id="6" type="button">Follow</button>
        </li>
      </ul>
    </div>
  </aside>

</div>

<script src="js/script.js?v=<?= filemtime(__DIR__ . '/js/script.js') ?>"></script>
<div style="position: fixed; bottom: 10px; left: 10px; font-size: 11px; color: var(--text-muted); opacity: 0.6; pointer-events: none; z-index: 100;">
  v0.8
</div>
</body>
</html>
