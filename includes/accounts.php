<?php
/**
 * Saved-account helpers for the Instagram-style "Switch accounts" flow.
 * Accounts are kept in the session; switching is only allowed to accounts
 * the visitor has already signed into during this session.
 */

function accounts_list(): array {
    return (isset($_SESSION['accounts']) && is_array($_SESSION['accounts'])) ? $_SESSION['accounts'] : [];
}

function initials_for(string $username): string {
    $initials = strtoupper(substr($username, 0, 1));
    if (strlen($username) > 1) {
        $initials .= strtoupper(substr($username, 1, 1));
    }
    return $initials;
}

function remember_account(array $user): void {
    $username = (string)($user['username'] ?? '');
    if ($username === '' || empty($user['id'])) {
        return;
    }

    if (!isset($_SESSION['accounts']) || !is_array($_SESSION['accounts'])) {
        $_SESSION['accounts'] = [];
    }

    $_SESSION['accounts'][(int)$user['id']] = [
        'id' => (int)$user['id'],
        'username' => $username,
        'initials' => initials_for($username),
        'profile_pic' => $user['profile_pic'] ?? null,
    ];
}

function forget_account(int $accountId): void {
    if (isset($_SESSION['accounts'][$accountId])
        && (int)($_SESSION['user']['id'] ?? 0) !== $accountId) {
        unset($_SESSION['accounts'][$accountId]);
    }
}

function session_user_from_row(array $row): array {
    return [
        'id' => (int)$row['id'],
        'username' => $row['username'],
        'initials' => initials_for($row['username']),
    ];
}

function switch_to_account(PDO $pdo, int $accountId): bool {
    if (!isset(accounts_list()[$accountId])) {
        return false;
    }

    $stmt = $pdo->prepare('SELECT id, username, profile_pic, status FROM users WHERE id = ?');
    $stmt->execute([$accountId]);
    $row = $stmt->fetch();

    if (!$row || $row['status'] !== 'Active') {
        forget_account($accountId);
        return false;
    }

    if ((int)($_SESSION['user']['id'] ?? 0) === $accountId) {
        return true;
    }

    $_SESSION['user'] = session_user_from_row($row);
    remember_account($row);
    session_regenerate_id(true);
    return true;
}
