<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/auth_guard.php';
require_once __DIR__ . '/includes/icons.php';
require_once __DIR__ . '/includes/messages.php';
require_once __DIR__ . '/includes/friends.php';

require_auth();

$me = $_SESSION['user'];
$meId = (int)$me['id'];

$peer = null;
$peerStatus = null;
$isFriend = false;
$messages = [];
$pageError = '';

if (isset($_GET['with']) && $_GET['with'] !== '') {
    $peerId = (int)$_GET['with'];

    if ($peerId === $meId) {
        $pageError = "You can't message yourself.";
    } else {
        $peer = get_active_user($pdo, $peerId);

        if (!$peer) {
            $pageError = 'That neighbor is not available.';
        } else {
            $peerStatus = friend_status($pdo, $meId, (int)$peer['id']);
            $isFriend = $peerStatus === 'friends';

            if ($isFriend) {
                mark_conversation_read($pdo, $meId, (int)$peer['id']);
                $messages = get_conversation_messages($pdo, $meId, (int)$peer['id']);
            }
        }
    }
}

$conversations = get_conversations($pdo, $meId);

$stmtPeople = $pdo->prepare("SELECT id, username, profile_pic FROM users WHERE status = 'Active' AND id <> ? ORDER BY username ASC");
$stmtPeople->execute([$meId]);
$people = $stmtPeople->fetchAll();

$friendStatuses = friend_status_map($pdo, $meId, array_column($people, 'id'));
usort($people, function (array $a, array $b) use ($friendStatuses): int {
    $aFriends = ($friendStatuses[(int)$a['id']] ?? 'none') === 'friends';
    $bFriends = ($friendStatuses[(int)$b['id']] ?? 'none') === 'friends';
    if ($aFriends !== $bFriends) {
        return $aFriends ? -1 : 1;
    }
    return strcasecmp($a['username'], $b['username']);
});

$incomingRequests = get_incoming_requests($pdo, $meId);

$lastMessageId = 0;
if ($messages) {
    $lastMessage = $messages[count($messages) - 1];
    $lastMessageId = (int)$lastMessage['id'];
}

$avatar = function (array $userRow, string $className): string {
    $pic = message_profile_pic($userRow);
    if ($pic !== null) {
        return '<img src="' . security_encode($pic) . '" class="' . $className . '" alt="">';
    }
    return '<div class="' . $className . '">' . security_encode(message_initials($userRow['username'])) . '</div>';
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - Porchlight</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <script src="js/theme.js"></script>
    <style>
        .messenger-wrap {
            max-width: 1120px;
            margin: 20px auto 40px;
            padding: 0 16px;
        }
        .messenger {
            display: grid;
            grid-template-columns: 320px minmax(0, 1fr);
            gap: 16px;
            height: calc(100vh - 100px);
            min-height: 460px;
        }
        .conv-panel,
        .chat-panel {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-card);
            display: flex;
            flex-direction: column;
            min-height: 0;
            overflow: hidden;
        }
        .conv-panel { padding: 14px 10px; }
        .conv-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            padding: 2px 6px 10px;
        }
        .conv-head h1 {
            font-family: var(--font-serif);
            font-size: 20px;
            margin: 0;
        }
        .new-message-panel { padding: 0 4px 10px; }
        .new-message-panel input {
            width: 100%;
            padding: 8px 10px;
            border: 1px solid var(--border);
            border-radius: var(--radius-control);
            background: var(--surface-muted);
            color: var(--text-primary);
            font: inherit;
            font-size: 13px;
            outline: none;
        }
        .new-message-panel input:focus { border-color: var(--accent); }
        .people-results {
            list-style: none;
            margin: 8px 0 0;
            padding: 0;
            max-height: 180px;
            overflow-y: auto;
        }
        .people-result a {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 7px 8px;
            border-radius: var(--radius-control);
            font-size: 14px;
            color: var(--text-primary);
        }
        .people-result a:hover { background: var(--surface-muted); }
        .people-empty {
            font-size: 13px;
            color: var(--text-muted);
            padding: 8px;
        }
        .people-result-row {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 7px 8px;
            font-size: 14px;
            color: var(--text-primary);
        }
        .people-name {
            flex: 1;
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .people-result-row .btn {
            flex-shrink: 0;
            height: 28px;
            padding: 0 10px;
            font-size: 12px;
        }
        .fr-panel {
            padding: 0 4px 10px;
            border-bottom: 1px solid var(--border);
            margin-bottom: 8px;
        }
        .fr-title {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0 0 6px;
            padding: 0 4px;
        }
        .fr-badge {
            background: var(--accent);
            color: var(--on-accent);
            font-size: 11px;
            font-weight: 700;
            min-width: 18px;
            height: 18px;
            border-radius: 9px;
            padding: 0 5px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .fr-list {
            list-style: none;
            margin: 0;
            padding: 0;
            max-height: 190px;
            overflow-y: auto;
        }
        .fr-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 6px 4px;
        }
        .fr-meta {
            flex: 1;
            min-width: 0;
            display: flex;
            flex-direction: column;
        }
        .fr-meta .conv-name { font-size: 13px; }
        .fr-sub { font-size: 11px; color: var(--text-muted); }
        .fr-actions {
            display: flex;
            gap: 4px;
            flex-shrink: 0;
        }
        .fr-actions .btn {
            height: 26px;
            padding: 0 8px;
            font-size: 11px;
        }
        .chat-locked .btn { margin-top: 14px; }
        .conv-list {
            list-style: none;
            margin: 0;
            padding: 0;
            flex: 1;
            min-height: 0;
            overflow-y: auto;
        }
        .conv-item a {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 9px 8px;
            border-radius: var(--radius-control);
            color: var(--text-primary);
        }
        .conv-item a:hover { background: var(--surface-muted); }
        .conv-item.is-active a { background: var(--surface-muted); }
        .conv-avatar,
        .chat-avatar,
        .msg-avatar {
            width: 40px;
            height: 40px;
            flex-shrink: 0;
            border-radius: 50%;
            object-fit: cover;
            background: var(--surface-muted);
            color: var(--accent-strong);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            font-weight: 600;
            border: 1px solid var(--border);
            overflow: hidden;
        }
        .chat-avatar { width: 36px; height: 36px; }
        .msg-avatar { width: 28px; height: 28px; font-size: 11px; align-self: flex-end; }
        .conv-meta { flex: 1; min-width: 0; }
        .conv-top {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            gap: 8px;
        }
        .conv-name {
            font-size: 14px;
            font-weight: 600;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .conv-time { font-size: 11px; color: var(--text-muted); flex-shrink: 0; }
        .conv-preview {
            font-size: 13px;
            color: var(--text-secondary);
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .conv-unread {
            background: var(--accent);
            color: var(--on-accent);
            font-size: 11px;
            font-weight: 700;
            min-width: 18px;
            height: 18px;
            border-radius: 9px;
            padding: 0 5px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .conv-empty {
            color: var(--text-muted);
            font-size: 13px;
            text-align: center;
            padding: 28px 14px;
            line-height: 1.6;
        }
        .chat-head {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 16px;
            border-bottom: 1px solid var(--border);
        }
        .chat-back {
            display: none;
            font-size: 24px;
            line-height: 1;
            color: var(--text-secondary);
            padding: 0 4px;
        }
        .chat-head-meta { display: flex; flex-direction: column; min-width: 0; }
        .chat-name { font-weight: 600; font-size: 15px; }
        .chat-sub { font-size: 12px; color: var(--text-muted); }
        .chat-scroll {
            flex: 1;
            min-height: 0;
            overflow-y: auto;
            padding: 18px 16px 8px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .msg-row { display: flex; align-items: flex-end; gap: 8px; }
        .msg-row.is-me { justify-content: flex-end; }
        .msg-content {
            display: flex;
            flex-direction: column;
            max-width: 68%;
            min-width: 0;
        }
        .msg-row.is-me .msg-content { align-items: flex-end; }
        .msg-bubble {
            padding: 9px 13px;
            border-radius: 16px;
            background: var(--surface-muted);
            color: var(--text-primary);
            font-size: 14px;
            line-height: 1.45;
            word-wrap: break-word;
        }
        .msg-row.is-me .msg-bubble {
            background: var(--accent);
            color: var(--on-accent);
            border-bottom-right-radius: 4px;
        }
        .msg-row.is-them .msg-bubble { border-bottom-left-radius: 4px; }
        .msg-time { font-size: 10px; color: var(--text-muted); margin-top: 4px; }
        .chat-intro {
            text-align: center;
            padding: 30px 12px 10px;
        }
        .chat-intro-title {
            font-weight: 600;
            color: var(--text-primary);
            margin: 0 0 4px;
        }
        .chat-intro-sub { margin: 0; font-size: 13px; color: var(--text-muted); }
        .chat-form {
            display: flex;
            gap: 10px;
            padding: 12px 14px;
            border-top: 1px solid var(--border);
        }
        .chat-form input {
            flex: 1;
            min-width: 0;
            padding: 10px 14px;
            border: 1px solid var(--border);
            border-radius: 20px;
            background: var(--surface-muted);
            color: var(--text-primary);
            font: inherit;
            font-size: 14px;
            outline: none;
        }
        .chat-form input:focus { border-color: var(--accent); }
        .chat-form .btn { border-radius: 20px; }
        .chat-empty {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 40px;
            color: var(--text-muted);
        }
        .chat-empty .icon {
            width: 42px;
            height: 42px;
            margin-bottom: 12px;
            color: var(--accent);
        }
        .chat-empty h2 {
            font-family: var(--font-serif);
            color: var(--text-primary);
            font-size: 20px;
            margin: 0 0 6px;
        }
        .chat-empty p { margin: 0; font-size: 14px; }
        @media (max-width: 760px) {
            .messenger { grid-template-columns: 1fr; height: calc(100vh - 84px); }
            .messenger .chat-panel { display: none; }
            .messenger.has-chat .conv-panel { display: none; }
            .messenger.has-chat .chat-panel { display: flex; }
            .chat-back { display: inline-block; }
        }
    </style>
</head>
<body>
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

    <div class="messenger-wrap">
        <input type="hidden" id="csrfToken" value="<?= generate_csrf_token() ?>">
        <div class="messenger<?= $peer ? ' has-chat' : '' ?>">
            <aside class="conv-panel">
                <div class="conv-head">
                    <h1>Messages</h1>
                    <button type="button" class="btn btn-ghost" id="newMessageBtn" style="height: 32px; font-size: 13px;">New message</button>
                </div>

                <?php if ($incomingRequests): ?>
                    <div class="fr-panel" id="friendRequests">
                        <p class="fr-title">Friend requests <span class="fr-badge"><?= count($incomingRequests) ?></span></p>
                        <ul class="fr-list">
                            <?php foreach ($incomingRequests as $request): ?>
                                <li class="fr-item" data-username="<?= security_encode(strtolower($request['username'])) ?>">
                                    <?= $avatar($request, 'conv-avatar') ?>
                                    <div class="fr-meta">
                                        <span class="conv-name"><?= security_encode($request['username']) ?></span>
                                        <span class="fr-sub">wants to be friends</span>
                                    </div>
                                    <div class="fr-actions">
                                        <button type="button" class="btn btn-primary friend-action" data-id="<?= (int)$request['id'] ?>" data-status="incoming" data-action="accept">Accept</button>
                                        <button type="button" class="btn btn-ghost friend-action" data-id="<?= (int)$request['id'] ?>" data-status="incoming" data-action="decline">Decline</button>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <div class="new-message-panel" id="newMessagePanel" hidden>
                    <input type="text" id="peopleSearch" placeholder="Search neighbors">
                    <ul class="people-results">
                        <?php foreach ($people as $person): ?>
                            <?php $personStatus = $friendStatuses[(int)$person['id']] ?? 'none'; ?>
                            <li class="people-result" data-username="<?= security_encode(strtolower($person['username'])) ?>">
                                <?php if ($personStatus === 'friends'): ?>
                                    <a href="messages.php?with=<?= (int)$person['id'] ?>">
                                        <?= $avatar($person, 'conv-avatar') ?>
                                        <span><?= security_encode($person['username']) ?></span>
                                    </a>
                                <?php else: ?>
                                    <div class="people-result-row">
                                        <?= $avatar($person, 'conv-avatar') ?>
                                        <span class="people-name"><?= security_encode($person['username']) ?></span>
                                        <?= friend_action_button((int)$person['id'], $personStatus) ?>
                                    </div>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                        <?php if (!$people): ?>
                            <li class="people-empty">No other neighbors yet.</li>
                        <?php endif; ?>
                    </ul>
                </div>

                <ul class="conv-list">
                    <?php if (!$conversations): ?>
                        <li class="conv-empty">No conversations yet.<br>Start a new message to say hello.</li>
                    <?php endif; ?>

                    <?php foreach ($conversations as $conversation): ?>
                        <?php $isActive = $peer && (int)$peer['id'] === (int)$conversation['peer_id']; ?>
                        <li class="conv-item<?= $isActive ? ' is-active' : '' ?>">
                            <a href="messages.php?with=<?= (int)$conversation['peer_id'] ?>">
                                <?= $avatar($conversation, 'conv-avatar') ?>
                                <div class="conv-meta">
                                    <div class="conv-top">
                                        <span class="conv-name"><?= security_encode($conversation['username']) ?></span>
                                        <span class="conv-time"><?= security_encode(message_list_time($conversation['last_time'])) ?></span>
                                    </div>
                                    <div class="conv-preview"><?= $conversation['last_sender_id'] === $meId ? 'You: ' : '' ?><?= security_encode($conversation['last_message']) ?></div>
                                </div>
                                <?php if ((int)$conversation['unread'] > 0): ?>
                                    <span class="conv-unread"><?= (int)$conversation['unread'] ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </aside>

            <section class="chat-panel">
                <?php if ($peer && !$isFriend): ?>
                    <div class="chat-empty chat-locked">
                        <?= icon('users') ?>
                        <h2>You and <?= security_encode($peer['username']) ?> aren't friends yet</h2>
                        <p>Send a friend request, then you can message each other.</p>
                        <?= friend_action_button((int)$peer['id'], $peerStatus ?? 'none', 'primary') ?>
                    </div>
                <?php elseif ($peer): ?>
                    <header class="chat-head">
                        <a href="messages.php" class="chat-back" aria-label="Back to conversations">&lsaquo;</a>
                        <?= $avatar($peer, 'chat-avatar') ?>
                        <div class="chat-head-meta">
                            <span class="chat-name"><?= security_encode($peer['username']) ?></span>
                            <span class="chat-sub">Neighborhood chat</span>
                        </div>
                    </header>

                    <div class="chat-scroll" id="chatScroll" data-last-id="<?= $lastMessageId ?>">
                        <?php if (!$messages): ?>
                            <div class="chat-intro">
                                <p class="chat-intro-title">Say hello to <?= security_encode($peer['username']) ?></p>
                                <p class="chat-intro-sub">This is the start of your conversation.</p>
                            </div>
                        <?php endif; ?>

                        <?php foreach ($messages as $message): ?>
                            <?= message_html($message, $meId, $peer) ?>
                        <?php endforeach; ?>
                    </div>

                    <form class="chat-form" id="chatForm" data-peer="<?= (int)$peer['id'] ?>">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        <input type="text" id="chatInput" name="body" placeholder="Message <?= security_encode($peer['username']) ?>..." autocomplete="off" required maxlength="2000">
                        <button type="submit" class="btn btn-primary">Send</button>
                    </form>
                <?php else: ?>
                    <div class="chat-empty">
                        <?= icon('message') ?>
                        <h2><?= $pageError ? security_encode($pageError) : 'Your messages' ?></h2>
                        <p><?= $pageError ? 'Pick another neighbor from your conversations.' : 'Select a conversation or start a new message.' ?></p>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </div>

    <script src="js/messages.js"></script>
</body>
</html>
