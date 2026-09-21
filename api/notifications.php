<?php
/**
 * Notifications JSON API (session-authenticated)
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../includes/auth.php';

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

$user = current_user();
$userId = (int) $user['id'];
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    if ($method === 'GET') {
        $items = get_notifications_for_user($userId, 20);
        $out = [];
        foreach ($items as $n) {
            $out[] = [
                'id' => (int) $n['id'],
                'ticket_id' => $n['ticket_id'] !== null ? (int) $n['ticket_id'] : null,
                'title' => $n['title'],
                'message' => $n['message'],
                'is_read' => (int) $n['is_read'] === 1,
                'created_at' => $n['created_at'],
                'created_label' => format_datetime($n['created_at'], 'M j, g:i A'),
            ];
        }
        echo json_encode([
            'ok' => true,
            'unread' => count_unread_notifications($userId),
            'items' => $out,
        ]);
        exit;
    }

    if ($method === 'POST') {
        $raw = file_get_contents('php://input');
        $json = json_decode($raw ?: '[]', true);
        if (!is_array($json)) {
            $json = $_POST;
        }

        // CSRF for state-changing
        $token = $json['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (!csrf_verify(is_string($token) ? $token : null)) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token']);
            exit;
        }

        $action = $json['action'] ?? '';
        if ($action === 'mark_all') {
            mark_all_notifications_read($userId);
            echo json_encode(['ok' => true, 'unread' => 0]);
            exit;
        }
        if ($action === 'mark_read') {
            $id = (int) ($json['id'] ?? 0);
            mark_notification_read($userId, $id);
            echo json_encode(['ok' => true, 'unread' => count_unread_notifications($userId)]);
            exit;
        }

        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Unknown action']);
        exit;
    }

    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => friendly_error($e)]);
}
