<?php
/**
 * CSRF protection helpers
 */

declare(strict_types=1);

function csrf_token(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return '';
    }

    if (empty($_SESSION['_csrf_token']) || !is_string($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['_csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_verify(?string $token = null): bool
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return false;
    }

    $token ??= $_POST['_csrf'] ?? '';
    $sessionToken = $_SESSION['_csrf_token'] ?? '';

    if (!is_string($token) || $token === '' || !is_string($sessionToken) || $sessionToken === '') {
        return false;
    }

    return hash_equals($sessionToken, $token);
}

function csrf_require(): void
{
    if (!csrf_verify()) {
        flash_set('error', 'Invalid or expired security token. Please try again.');
        $ref = $_SERVER['HTTP_REFERER'] ?? url('auth/login.php');
        header('Location: ' . $ref);
        exit;
    }
}
