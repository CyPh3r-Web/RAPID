<?php
/**
 * Technician AI repair suggestions (session + CSRF)
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../includes/auth.php';

$user = current_user();
if (!$user) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($user['role'] !== 'technician') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Forbidden']);
    exit;
}

$technicianId = get_technician_id_for_user((int) $user['id']);

if (!$technicianId) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Technician profile not found.']);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$raw = file_get_contents('php://input');
$json = json_decode($raw ?: '[]', true);
if (!is_array($json)) {
    $json = $_POST;
}

$token = $json['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (!csrf_verify(is_string($token) ? $token : null)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

$ticketId = (int) ($json['ticket_id'] ?? 0);
$force = !empty($json['force']);

$ticket = $ticketId > 0 ? get_ticket_full($ticketId) : null;
if (!$ticket || (int) ($ticket['assigned_technician_id'] ?? 0) !== $technicianId) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Ticket not found or not assigned to you.']);
    exit;
}

try {
    $result = ai_suggest_technician_repair($ticketId, $force);
    if (empty($result['ok'])) {
        http_response_code(400);
        echo json_encode($result);
        exit;
    }

    if (empty($result['cached'])) {
        log_activity((int) $user['id'], 'ai_suggest', 'AI repair suggestion for ' . $ticket['ticket_number']);
    }

    echo json_encode($result, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => friendly_error($e, 'Could not generate a suggestion. Please try again.')]);
}
