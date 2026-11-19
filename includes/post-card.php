<?php
require_once __DIR__ . '/profile_edit.php';
$canLike = $post['can_like'] ?? true;
$canComment = $post['can_comment'] ?? true;
$hideLikeCount = !empty($GLOBALS['hide_like_counts']);
$likeCountStyle = $hideLikeCount ? ' style="display: none;"' : '';
$authorPic = profile_pic_url($post['profile_pic'] ?? null);
?>
<article class="post" data-id="<?= (int) $post['id'] ?>">
  <div class="post-head">
    <?php if ($authorPic !== null): ?>
      <img src="<?= htmlspecialchars($authorPic) ?>" class="avatar" style="object-fit: cover;">
    <?php else: ?>
      <div class="avatar"><?= $post['initials'] ?></div>
    <?php endif; ?>
    <div>
      <p class="post-author"><?= $post['author'] ?></p>
      <p class="post-time"><?= time_ago($post['time']) ?></p>
    </div>
    <?php if (!empty($post['can_delete'])): ?>
      <button type="button" class="action-btn delete-btn post-delete" data-id="<?= (int) $post['id'] ?>" title="Delete post" aria-label="Delete post">
        <?= icon('trash') ?>
      </button>
    <?php endif; ?>
  </div>

  <p class="post-content"><?= $post['content'] ?></p>

  <?php if (!empty($post['image'])): ?>
    <?php
      $isVideoFile = (bool) preg_match('/\.(mp4|webm|mov|ogv|ogg|m4v)$/i', $post['image']);
      $mediaType = ($post['media_type'] ?? 'image') === 'video' || $isVideoFile ? 'video' : 'image';
    ?>
    <div class="post-media">
      <?php if ($mediaType === 'video'): ?>
        <video src="<?= htmlspecialchars($post['image']) ?>" controls preload="metadata" playsinline></video>
      <?php else: ?>
        <img src="<?= htmlspecialchars($post['image']) ?>" alt="Post photo" loading="lazy">
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <div class="post-actions">
    <?php if ($canLike): ?>
      <button class="action-btn like-btn<?= $post['liked'] ? ' is-liked' : '' ?>" data-id="<?= (int) $post['id'] ?>">
        <?= icon('heart') ?><span class="like-count"<?= $likeCountStyle ?>><?= (int) $post['likes'] ?></span>
      </button>
    <?php else: ?>
      <span class="action-btn like-btn is-disabled" title="Likes are turned off for this post">
        <?= icon('heart') ?><span class="like-count"<?= $likeCountStyle ?>><?= (int) $post['likes'] ?></span>
      </span>
    <?php endif; ?>
    <button class="action-btn comment-toggle" data-id="<?= (int) $post['id'] ?>">
      <?= icon('message') ?><span class="comment-count"><?= count($post['comments']) ?></span>
    </button>
    <button class="action-btn save-btn<?= !empty($post['saved']) ? ' is-saved' : '' ?>" data-id="<?= (int) $post['id'] ?>" type="button" aria-pressed="<?= !empty($post['saved']) ? 'true' : 'false' ?>">
      <?= icon('bookmark') ?><span><?= !empty($post['saved']) ? 'Saved' : 'Save' ?></span>
    </button>
    <button class="action-btn share-btn" type="button">
      <?= icon('share') ?><span>Share</span>
    </button>
  </div>

  <div class="comments" hidden>
    <ul class="comment-list">
      <?php foreach ($post['comments'] as $c): ?>
        <li><strong><?= $c['author'] ?></strong> <?= $c['text'] ?></li>
      <?php endforeach; ?>
    </ul>
    <?php if ($canComment): ?>
      <form class="comment-form" data-id="<?= (int) $post['id'] ?>">
        <input type="text" placeholder="Write a comment" required>
        <button type="submit" class="icon-btn" aria-label="Send comment"><?= icon('send') ?></button>
      </form>
    <?php else: ?>
      <p class="comments-off">Comments are turned off for this post.</p>
    <?php endif; ?>
  </div>
</article>
