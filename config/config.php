<?php
/**
 * Application configuration — RAPID Repair System
 */

declare(strict_types=1);

date_default_timezone_set('Asia/Manila');

define('APP_DEBUG', true);

if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}

/**
 * Resolve project base URL path for subdirectory installs (e.g. /RAPID).
 */
function resolve_base_path(): string
{
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $dir = str_replace('\\', '/', dirname($script));

    $markers = ['/auth', '/admin', '/technician', '/customer', '/api'];
    foreach ($markers as $marker) {
        $pos = strrpos($dir, $marker);
        if ($pos !== false) {
            $dir = substr($dir, 0, $pos);
            break;
        }
    }

    $dir = rtrim($dir, '/');
    if ($dir === '' || $dir === '.' || $dir === '/') {
        return '';
    }

    return $dir;
}

define('BASE_PATH', resolve_base_path());
define('BASE_URL', BASE_PATH);

define('APP_NAME', 'RAPID');
define('APP_FULL_NAME', 'RAPID: Repair Assessment, Progress, and Issue Documentation');
define('APP_SUBTITLE', 'Online Device Repair Ticketing and Progress Monitoring System');
define('SHOP_NAME', 'RAPID Device Care');

define('SESSION_NAME', 'RAPID_SESSION');
define('SESSION_TIMEOUT', 1800);

define('DEFAULT_WARRANTY_DAYS', 30);

define('UPLOAD_MAX_SIZE', 10 * 1024 * 1024);
define('UPLOAD_ALLOWED_IMAGES', ['image/jpeg', 'image/png', 'image/webp']);
define('UPLOAD_ALLOWED_VIDEOS', ['video/mp4', 'video/quicktime']);

define('ROOT_PATH', dirname(__DIR__));
define('UPLOAD_PATH', ROOT_PATH . DIRECTORY_SEPARATOR . 'uploads');

/** Google AI Studio key. Prefer Admin → Settings; this is a local fallback. */
define('GEMINI_API_KEY', getenv('GEMINI_API_KEY') ?: '');
define('GEMINI_MODEL', 'gemini-flash-lite-latest');
define('GEMINI_API_URL', 'https://generativelanguage.googleapis.com/v1beta/models');

define('TICKET_STATUSES', [
    'booking_submitted'   => 'Booking Submitted',
    'received'            => 'Received',
    'diagnosing'          => 'Diagnosing',
    'quotation_pending'   => 'Quotation Pending',
    'awaiting_approval'   => 'Awaiting Customer Approval',
    'approved'            => 'Approved',
    'repairing'           => 'Repairing',
    'ready_for_pickup'    => 'Ready for Pickup',
    'completed'           => 'Completed',
    'declined'            => 'Declined',
    'cancelled'           => 'Cancelled',
]);
