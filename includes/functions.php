<?php
/**
 * Shared helper functions
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = ''): string
{
    $path = ltrim($path, '/');
    $base = BASE_URL;

    $hash = '';
    $hashPos = strpos($path, '#');
    if ($hashPos !== false) {
        $hash = substr($path, $hashPos);
        $path = substr($path, 0, $hashPos);
    }

    $query = '';
    $qPos = strpos($path, '?');
    if ($qPos !== false) {
        $query = substr($path, $qPos);
        $path = substr($path, 0, $qPos);
    }

    if ($path !== '') {
        if (preg_match('#(?:^|/)index\.php$#i', $path)) {
            $path = (string) preg_replace('#index\.php$#i', '', $path);
        } elseif (preg_match('#\.php$#i', $path)) {
            $path = (string) preg_replace('#\.php$#i', '', $path);
        }
    }

    if ($path === '' || $path === '/') {
        $href = $base === '' ? '/' : $base . '/';
    } else {
        $href = ($base === '' ? '' : $base) . '/' . $path;
    }

    return $href . $query . $hash;
}

function asset(string $path): string
{
    return url('assets/' . ltrim($path, '/'));
}

function redirect(string $path): void
{
    if (preg_match('#^https?://#i', $path)) {
        header('Location: ' . $path);
        exit;
    }

    header('Location: ' . url($path));
    exit;
}

function flash_set(string $type, string $message): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function flash_get(): ?array
{
    if (empty($_SESSION['flash'])) {
        return null;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

function client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}

function format_datetime(?string $datetime, string $format = 'M j, Y g:i A'): string
{
    if ($datetime === null || $datetime === '' || $datetime === '0000-00-00 00:00:00') {
        return '—';
    }
    $ts = strtotime($datetime);
    return $ts ? date($format, $ts) : '—';
}

function format_date(?string $date, string $format = 'F j, Y'): string
{
    if ($date === null || $date === '' || $date === '0000-00-00') {
        return '—';
    }
    $ts = strtotime($date);
    return $ts ? date($format, $ts) : '—';
}

function status_label(string $status): string
{
    return TICKET_STATUSES[$status] ?? ucwords(str_replace('_', ' ', $status));
}

function status_badge_class(string $status): string
{
    switch ($status) {
        case 'completed':
            return 'badge-status-success';
        case 'ready_for_pickup':
        case 'approved':
            return 'badge-status-info';
        case 'repairing':
        case 'diagnosing':
        case 'received':
        case 'booking_submitted':
            return 'badge-status-primary';
        case 'quotation_pending':
        case 'awaiting_approval':
            return 'badge-status-warning';
        case 'declined':
        case 'cancelled':
            return 'badge-status-danger';
        default:
            return 'badge-status-muted';
    }
}

/**
 * Generate next ticket number: RPR-YYYY-000001
 */
function generate_ticket_number(PDO $pdo): string
{
    $year = date('Y');
    $prefix = 'RPR-' . $year . '-';

    $stmt = $pdo->prepare(
        'SELECT ticket_number FROM repair_tickets
         WHERE ticket_number LIKE ?
         ORDER BY id DESC LIMIT 1'
    );
    $stmt->execute([$prefix . '%']);
    $last = $stmt->fetchColumn();

    $next = 1;
    if ($last && preg_match('/(\d+)$/', (string) $last, $m)) {
        $next = (int) $m[1] + 1;
    }

    return $prefix . str_pad((string) $next, 6, '0', STR_PAD_LEFT);
}

function log_activity(?int $userId, string $action, string $description = ''): void
{
    try {
        $stmt = db()->prepare(
            'INSERT INTO activity_logs (user_id, action, description, ip_address)
             VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$userId, $action, $description, client_ip()]);
    } catch (Throwable $e) {
        error_log('Activity log failed: ' . $e->getMessage());
    }
}

function create_notification(int $userId, string $title, string $message, ?int $ticketId = null): void
{
    try {
        $stmt = db()->prepare(
            'INSERT INTO notifications (user_id, ticket_id, title, message)
             VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$userId, $ticketId, $title, $message]);
    } catch (Throwable $e) {
        error_log('Notification create failed: ' . $e->getMessage());
    }
}

function get_setting(string $key, ?string $default = null): ?string
{
    try {
        $stmt = db()->prepare('SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1');
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();
        return $value !== false ? (string) $value : $default;
    } catch (Throwable $e) {
        return $default;
    }
}

function set_setting(string $key, string $value): bool
{
    try {
        $stmt = db()->prepare(
            'INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
        );
        $stmt->execute([$key, $value]);
        return true;
    } catch (Throwable $e) {
        error_log('set_setting failed: ' . $e->getMessage());
        return false;
    }
}

function friendly_error(Throwable $e, string $fallback = 'Something went wrong while processing your request. Please try again.'): string
{
    error_log($e->getMessage());
    return (defined('APP_DEBUG') && APP_DEBUG) ? $e->getMessage() : $fallback;
}

/**
 * Resolve customers.id for a user_id, or null if not a customer profile.
 */
function get_customer_id_for_user(int $userId): ?int
{
    $stmt = db()->prepare('SELECT id FROM customers WHERE user_id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $id = $stmt->fetchColumn();
    return $id !== false ? (int) $id : null;
}

/**
 * Notify all active admin users.
 */
function notify_admins(string $title, string $message, ?int $ticketId = null): void
{
    try {
        $admins = db()->query(
            "SELECT id FROM users WHERE role = 'admin' AND status = 'active'"
        )->fetchAll();
        foreach ($admins as $admin) {
            create_notification((int) $admin['id'], $title, $message, $ticketId);
        }
    } catch (Throwable $e) {
        error_log('notify_admins failed: ' . $e->getMessage());
    }
}

/**
 * Fetch a ticket owned by a customer (by customers.id). Returns null if missing/unauthorized.
 */
function get_customer_ticket(int $ticketId, int $customerId): ?array
{
    $stmt = db()->prepare(
        'SELECT rt.*, d.device_type, d.brand, d.model, d.serial_number, d.imei, d.color,
                d.accessories, d.physical_condition,
                CONCAT(tu.first_name, \' \', tu.last_name) AS technician_name
         FROM repair_tickets rt
         INNER JOIN devices d ON d.id = rt.device_id
         LEFT JOIN technicians t ON t.id = rt.assigned_technician_id
         LEFT JOIN users tu ON tu.id = t.user_id
         WHERE rt.id = ? AND rt.customer_id = ?
         LIMIT 1'
    );
    $stmt->execute([$ticketId, $customerId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

require_once __DIR__ . '/upload.php';
require_once __DIR__ . '/warranty.php';
require_once __DIR__ . '/workflow.php';
require_once __DIR__ . '/ai.php';
require_once __DIR__ . '/ui.php';
require_once __DIR__ . '/admin_users.php';
