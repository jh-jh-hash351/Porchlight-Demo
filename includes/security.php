<?php
/**
 * Security Utilities for Porchlight
 */

function generate_csrf_token() {
    if (session_status() === PHP_SESSION_NONE) {
        if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    if (session_status() === PHP_SESSION_NONE) {
        if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
    }
    if (!isset($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

function security_encode($data) {
    if ($data === null) return '';
    return htmlspecialchars((string)$data, ENT_QUOTES, 'UTF-8');
}

function validate_input($data, $filter = FILTER_DEFAULT) {
    return filter_var($data, $filter);
}
?>
