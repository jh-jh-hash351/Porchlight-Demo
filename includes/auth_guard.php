<?php
/**
 * Authentication and Authorization Guards for Porchlight
 */

function require_auth() {
    if (session_status() === PHP_SESSION_NONE) {
        if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
    }

    if (!isset($_SESSION['user'])) {
        header('Location: login.php');
        exit;
    }

    global $pdo;
    $userId = $_SESSION['user']['id'];

    // Verify user still exists and is active
    $stmt = $pdo->prepare('SELECT status FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $status = $stmt->fetchColumn();

    if (!$status || $status !== 'Active') {
        session_destroy();
        header('Location: login.php?error=account_inactive');
        exit;
    }
}

function require_role($role) {
    require_auth();

    global $pdo;
    $userId = $_SESSION['user']['id'];

    $stmt = $pdo->prepare('SELECT role FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $userRole = $stmt->fetchColumn();

    if ($userRole !== $role) {
        http_response_code(403);
        die('Forbidden: You do not have the required permissions to access this resource.');
    }
}
?>
