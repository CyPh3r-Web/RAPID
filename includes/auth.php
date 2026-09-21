<?php
/**
 * Authentication & session management
 */

declare(strict_types=1);

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/csrf.php';

function start_app_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => BASE_URL === '' ? '/' : BASE_URL . '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();

    // Inactivity timeout
    if (!empty($_SESSION['user_id'])) {
        $last = (int) ($_SESSION['last_activity'] ?? 0);
        if ($last > 0 && (time() - $last) > SESSION_TIMEOUT) {
            $_SESSION = [];
            session_destroy();
            session_start();
            flash_set('warning', 'Your session expired due to inactivity. Please sign in again.');
            return;
        }
        $_SESSION['last_activity'] = time();
    }
}

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    static $cached = null;
    static $cachedId = null;

    $id = (int) $_SESSION['user_id'];
    if ($cached !== null && $cachedId === $id) {
        return $cached;
    }

    try {
        $stmt = db()->prepare(
            'SELECT id, role, first_name, last_name, email, phone, status
             FROM users WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $user = $stmt->fetch();

        if (!$user || $user['status'] !== 'active') {
            logout_user();
            return null;
        }

        $cached = $user;
        $cachedId = $id;
        return $user;
    } catch (Throwable $e) {
        error_log('current_user failed: ' . $e->getMessage());
        return null;
    }
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['last_activity'] = time();
    log_activity((int) $user['id'], 'login', 'User signed in.');
}

function logout_user(): void
{
    $userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    if ($userId) {
        log_activity($userId, 'logout', 'User signed out.');
    }

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', (bool) $params['secure'], (bool) $params['httponly']);
    }

    session_destroy();
}

function require_login(): void
{
    if (!is_logged_in()) {
        flash_set('warning', 'Please sign in to continue.');
        redirect('auth/login.php');
    }
}

/**
 * @param string|string[] $roles
 */
function require_role($roles): void
{
    require_login();
    $user = current_user();
    $roles = (array) $roles;

    if (!$user || !in_array($user['role'], $roles, true)) {
        flash_set('error', 'You do not have permission to access that page.');
        redirect(role_home_path($user['role'] ?? 'customer'));
    }
}

function role_home_path(string $role): string
{
    switch ($role) {
        case 'admin':
            return 'admin/index.php';
        case 'technician':
            return 'technician/index.php';
        case 'customer':
            return 'customer/index.php';
        default:
            return 'index.php';
    }
}

function attempt_login(string $email, string $password): array
{
    $email = strtolower(trim($email));

    if ($email === '' || $password === '') {
        return ['success' => false, 'message' => 'Email and password are required.'];
    }

    try {
        $stmt = db()->prepare(
            'SELECT id, role, first_name, last_name, email, phone, password, status
             FROM users WHERE email = ? LIMIT 1'
        );
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            return ['success' => false, 'message' => 'Invalid email or password.'];
        }

        if ($user['status'] !== 'active') {
            return ['success' => false, 'message' => 'Your account is inactive. Contact support.'];
        }

        unset($user['password']);
        login_user($user);

        return ['success' => true, 'user' => $user];
    } catch (Throwable $e) {
        return ['success' => false, 'message' => friendly_error($e)];
    }
}

function register_customer(array $data): array
{
    $first = trim($data['first_name'] ?? '');
    $last = trim($data['last_name'] ?? '');
    $email = strtolower(trim($data['email'] ?? ''));
    $phone = trim($data['phone'] ?? '');
    $address = trim($data['address'] ?? '');
    $password = $data['password'] ?? '';
    $confirm = $data['password_confirm'] ?? '';

    $errors = [];

    if ($first === '' || strlen($first) > 100) {
        $errors[] = 'First name is required.';
    }
    if ($last === '' || strlen($last) > 100) {
        $errors[] = 'Last name is required.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'A valid email address is required.';
    }
    if ($phone === '') {
        $errors[] = 'Phone number is required.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Password confirmation does not match.';
    }

    if ($errors) {
        return ['success' => false, 'message' => implode(' ', $errors)];
    }

    $pdo = db();

    try {
        $check = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $check->execute([$email]);
        if ($check->fetch()) {
            return ['success' => false, 'message' => 'An account with that email already exists.'];
        }

        $pdo->beginTransaction();

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare(
            'INSERT INTO users (role, first_name, last_name, email, phone, password, status)
             VALUES (\'customer\', ?, ?, ?, ?, ?, \'active\')'
        );
        $stmt->execute([$first, $last, $email, $phone, $hash]);
        $userId = (int) $pdo->lastInsertId();

        $cust = $pdo->prepare('INSERT INTO customers (user_id, address) VALUES (?, ?)');
        $cust->execute([$userId, $address !== '' ? $address : null]);

        $pdo->commit();

        log_activity($userId, 'register', 'Customer account created.');

        return ['success' => true, 'user_id' => $userId];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['success' => false, 'message' => friendly_error($e)];
    }
}

// Bootstrap session for every include of auth.php
start_app_session();
