<?php
/**
 * Per-ticket message thread between customer, assigned technician, and admins.
 * Access control stays in each page (customer owns ticket / tech assigned / admin).
 */

declare(strict_types=1);

const TICKET_MESSAGE_MAX_LENGTH = 2000;

function ensure_messages_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }

    try {
        db()->exec(
            "CREATE TABLE IF NOT EXISTS `ticket_messages` (
              `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
              `ticket_id` INT UNSIGNED NOT NULL,
              `user_id` INT UNSIGNED NOT NULL,
              `body` TEXT DEFAULT NULL,
              `attachment_name` VARCHAR(255) DEFAULT NULL,
              `attachment_path` VARCHAR(500) DEFAULT NULL,
              `attachment_type` VARCHAR(100) DEFAULT NULL,
              `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              KEY `idx_ticket_messages_ticket` (`ticket_id`, `created_at`),
              CONSTRAINT `fk_ticket_messages_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `repair_tickets` (`id`) ON DELETE CASCADE,
              CONSTRAINT `fk_ticket_messages_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $done = true;
    } catch (Throwable $e) {
        error_log('ensure_messages_schema failed: ' . $e->getMessage());
    }
}

/**
 * @return list<array<string,mixed>>
 */
function get_ticket_messages(int $ticketId): array
{
    ensure_messages_schema();
    $stmt = db()->prepare(
        'SELECT m.*, u.first_name, u.last_name, u.role
         FROM ticket_messages m
         INNER JOIN users u ON u.id = m.user_id
         WHERE m.ticket_id = ?
         ORDER BY m.created_at ASC, m.id ASC'
    );
    $stmt->execute([$ticketId]);
    return $stmt->fetchAll() ?: [];
}

/**
 * Caller must already have verified $user may access $ticketId.
 *
 * @param array<string,mixed> $user  current_user()
 * @param array<string,mixed> $file  $_FILES['attachment'] (optional)
 * @return array{ok:bool, error?:string}
 */
function post_ticket_message(int $ticketId, array $user, string $body, array $file = []): array
{
    ensure_messages_schema();

    $body = trim($body);
    if ((function_exists('mb_strlen') ? mb_strlen($body) : strlen($body)) > TICKET_MESSAGE_MAX_LENGTH) {
        return ['ok' => false, 'error' => 'Messages must be ' . TICKET_MESSAGE_MAX_LENGTH . ' characters or fewer.'];
    }

    $upload = store_uploaded_media($file, 'messages', 1);
    if (!$upload['ok']) {
        return ['ok' => false, 'error' => $upload['error'] ?? 'Attachment upload failed.'];
    }
    $attachment = $upload['files'][0] ?? null;

    if ($body === '' && !$attachment) {
        return ['ok' => false, 'error' => 'Write a message or attach a photo.'];
    }

    $ticket = get_ticket_full($ticketId);
    if (!$ticket) {
        return ['ok' => false, 'error' => 'Ticket not found.'];
    }

    $senderId = (int) $user['id'];
    try {
        db()->prepare(
            'INSERT INTO ticket_messages (ticket_id, user_id, body, attachment_name, attachment_path, attachment_type)
             VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([
            $ticketId,
            $senderId,
            $body !== '' ? $body : null,
            $attachment['file_name'] ?? null,
            $attachment['file_path'] ?? null,
            $attachment['file_type'] ?? null,
        ]);
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => friendly_error($e)];
    }

    // In-app only: chat volume would make email/SMS noisy.
    $senderName = trim((string) $user['first_name'] . ' ' . (string) $user['last_name']);
    $preview = $body !== '' ? $body : 'Sent an attachment.';
    if ((function_exists('mb_strlen') ? mb_strlen($preview) : strlen($preview)) > 120) {
        $preview = (function_exists('mb_substr') ? mb_substr($preview, 0, 117) : substr($preview, 0, 117)) . '...';
    }
    $title = 'New message on ' . $ticket['ticket_number'];
    $message = $senderName . ': ' . $preview;

    $recipients = [(int) $ticket['customer_user_id']];
    if (!empty($ticket['technician_profile_id'])) {
        $recipients[] = (int) get_technician_user_id((int) $ticket['technician_profile_id']);
    }
    foreach (array_unique(array_filter($recipients)) as $recipientId) {
        if ($recipientId !== $senderId) {
            create_notification($recipientId, $title, $message, $ticketId);
        }
    }
    if (($user['role'] ?? '') === 'customer' && empty($ticket['technician_profile_id'])) {
        notify_admins($title, $message, $ticketId);
    }

    return ['ok' => true];
}

function get_technician_user_id(int $technicianId): ?int
{
    $stmt = db()->prepare('SELECT user_id FROM technicians WHERE id = ? LIMIT 1');
    $stmt->execute([$technicianId]);
    $id = $stmt->fetchColumn();
    return $id !== false ? (int) $id : null;
}

/**
 * Thread + composer. The page must handle POST action=message via post_ticket_message().
 */
function render_ticket_thread(array $messages, int $currentUserId, string $title = 'Messages'): void
{
    echo '<section class="rapid-card ticket-thread" id="messages">';
    echo '<h2 class="text-sm font-semibold text-rapid mb-3">' . e($title) . '</h2>';

    if (!$messages) {
        echo '<p class="text-sm text-rapid-muted mb-3">No messages yet. Ask a question or share an update about this repair.</p>';
    } else {
        echo '<ol class="thread-list">';
        foreach ($messages as $m) {
            $mine = (int) $m['user_id'] === $currentUserId;
            $role = (string) $m['role'];
            echo '<li class="thread-msg' . ($mine ? ' is-mine' : '') . '">';
            echo '<div class="thread-meta">';
            echo '<strong>' . e($mine ? 'You' : trim($m['first_name'] . ' ' . $m['last_name'])) . '</strong>';
            if (!$mine) {
                echo ' <span class="thread-role">' . e($role === 'admin' ? 'Shop' : ucfirst($role)) . '</span>';
            }
            echo ' · <time datetime="' . e((string) $m['created_at']) . '">' . e(format_datetime((string) $m['created_at'])) . '</time>';
            echo '</div>';
            echo '<div class="thread-bubble">';
            if ($m['body'] !== null && $m['body'] !== '') {
                echo '<p>' . nl2br(e((string) $m['body'])) . '</p>';
            }
            if (!empty($m['attachment_path'])) {
                $src = media_public_url((string) $m['attachment_path']);
                if (is_image_mime((string) $m['attachment_type'])) {
                    echo '<a href="' . e($src) . '" target="_blank" rel="noopener"><img class="thread-attachment" src="' . e($src) . '" alt="' . e((string) $m['attachment_name']) . '"></a>';
                } elseif (is_video_mime((string) $m['attachment_type'])) {
                    echo '<video class="thread-attachment" controls preload="metadata" src="' . e($src) . '"></video>';
                }
            }
            echo '</div>';
            echo '</li>';
        }
        echo '</ol>';
    }

    echo '<form method="post" enctype="multipart/form-data" class="thread-composer" data-disable-on-submit>';
    echo csrf_field();
    echo '<input type="hidden" name="action" value="message">';
    echo '<label class="sr-only" for="message_body">Message</label>';
    echo '<textarea class="form-control" id="message_body" name="message_body" rows="2" maxlength="' . TICKET_MESSAGE_MAX_LENGTH . '" placeholder="Write a message…"></textarea>';
    echo '<div class="thread-composer-actions">';
    echo '<label class="thread-attach"><i class="bi bi-paperclip" aria-hidden="true"></i> <span>Photo/video</span>';
    echo '<input type="file" name="attachment" accept=".jpg,.jpeg,.png,.webp,.mp4,.mov"></label>';
    echo '<button type="submit" class="btn btn-rapid-primary btn-sm"><i class="bi bi-send" aria-hidden="true"></i> Send</button>';
    echo '</div>';
    echo '</form>';
    echo '</section>';
}
