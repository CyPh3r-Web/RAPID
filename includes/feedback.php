<?php
/**
 * Customer feedback: one 1–5 star rating (+ optional comment) per completed ticket.
 */

declare(strict_types=1);

const FEEDBACK_COMMENT_MAX_LENGTH = 1000;

function ensure_feedback_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }

    try {
        db()->exec(
            "CREATE TABLE IF NOT EXISTS `ticket_feedback` (
              `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
              `ticket_id` INT UNSIGNED NOT NULL,
              `customer_id` INT UNSIGNED NOT NULL,
              `technician_id` INT UNSIGNED DEFAULT NULL,
              `rating` TINYINT UNSIGNED NOT NULL,
              `comment` TEXT DEFAULT NULL,
              `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uq_ticket_feedback_ticket` (`ticket_id`),
              KEY `idx_ticket_feedback_technician` (`technician_id`),
              CONSTRAINT `fk_ticket_feedback_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `repair_tickets` (`id`) ON DELETE CASCADE,
              CONSTRAINT `fk_ticket_feedback_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
              CONSTRAINT `fk_ticket_feedback_technician` FOREIGN KEY (`technician_id`) REFERENCES `technicians` (`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $done = true;
    } catch (Throwable $e) {
        error_log('ensure_feedback_schema failed: ' . $e->getMessage());
    }
}

/**
 * @return array<string,mixed>|null
 */
function get_ticket_feedback(int $ticketId): ?array
{
    ensure_feedback_schema();
    $stmt = db()->prepare('SELECT * FROM ticket_feedback WHERE ticket_id = ? LIMIT 1');
    $stmt->execute([$ticketId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * @return array{ok:bool, error?:string}
 */
function submit_ticket_feedback(int $ticketId, int $customerId, int $userId, int $rating, string $comment): array
{
    ensure_feedback_schema();

    $comment = trim($comment);
    if ($rating < 1 || $rating > 5) {
        return ['ok' => false, 'error' => 'Choose a rating from 1 to 5 stars.'];
    }
    if ((function_exists('mb_strlen') ? mb_strlen($comment) : strlen($comment)) > FEEDBACK_COMMENT_MAX_LENGTH) {
        return ['ok' => false, 'error' => 'Comments must be ' . FEEDBACK_COMMENT_MAX_LENGTH . ' characters or fewer.'];
    }

    $ticket = get_customer_ticket($ticketId, $customerId);
    if (!$ticket) {
        return ['ok' => false, 'error' => 'Ticket not found.'];
    }
    if ($ticket['current_status'] !== 'completed') {
        return ['ok' => false, 'error' => 'You can rate a repair once it is completed.'];
    }
    if (get_ticket_feedback($ticketId)) {
        return ['ok' => false, 'error' => 'You already rated this repair. Thank you!'];
    }

    $technicianId = !empty($ticket['assigned_technician_id']) ? (int) $ticket['assigned_technician_id'] : null;
    try {
        db()->prepare(
            'INSERT INTO ticket_feedback (ticket_id, customer_id, technician_id, rating, comment)
             VALUES (?, ?, ?, ?, ?)'
        )->execute([$ticketId, $customerId, $technicianId, $rating, $comment !== '' ? $comment : null]);
    } catch (PDOException $e) {
        // Unique key: a double submit raced past the check above.
        if ((string) $e->getCode() === '23000') {
            return ['ok' => false, 'error' => 'You already rated this repair. Thank you!'];
        }
        return ['ok' => false, 'error' => friendly_error($e)];
    }

    $msg = 'Ticket ' . $ticket['ticket_number'] . ' was rated ' . $rating . '/5' . ($comment !== '' ? ': "' . $comment . '"' : '.');
    if ($technicianId) {
        $techUserId = get_technician_user_id($technicianId);
        if ($techUserId) {
            create_notification($techUserId, 'New customer feedback', $msg, $ticketId);
        }
    }
    notify_admins('New customer feedback', $msg, $ticketId);
    log_activity($userId, 'feedback_submitted', $msg);

    return ['ok' => true];
}

/**
 * @return array{avg:?float, count:int}
 */
function feedback_overall(): array
{
    ensure_feedback_schema();
    try {
        $row = db()->query('SELECT AVG(rating) AS avg_rating, COUNT(*) AS cnt FROM ticket_feedback')->fetch();
        return [
            'avg' => $row['avg_rating'] !== null ? round((float) $row['avg_rating'], 1) : null,
            'count' => (int) $row['cnt'],
        ];
    } catch (Throwable $e) {
        error_log('feedback_overall failed: ' . $e->getMessage());
        return ['avg' => null, 'count' => 0];
    }
}

/**
 * @return array<int, array{avg:float, count:int}> keyed by technicians.id
 */
function technician_ratings(): array
{
    ensure_feedback_schema();
    $out = [];
    try {
        foreach (db()->query(
            'SELECT technician_id, AVG(rating) AS avg_rating, COUNT(*) AS cnt
             FROM ticket_feedback WHERE technician_id IS NOT NULL GROUP BY technician_id'
        )->fetchAll() as $r) {
            $out[(int) $r['technician_id']] = ['avg' => round((float) $r['avg_rating'], 1), 'count' => (int) $r['cnt']];
        }
    } catch (Throwable $e) {
        error_log('technician_ratings failed: ' . $e->getMessage());
    }
    return $out;
}

function render_stars(int $rating): void
{
    echo '<span class="rating-stars" role="img" aria-label="' . $rating . ' out of 5 stars">';
    for ($i = 1; $i <= 5; $i++) {
        echo '<i class="bi ' . ($i <= $rating ? 'bi-star-fill' : 'bi-star') . '" aria-hidden="true"></i>';
    }
    echo '</span>';
}

function render_feedback_card(array $feedback): void
{
    echo '<div class="feedback-card">';
    render_stars((int) $feedback['rating']);
    echo '<span class="text-sm text-rapid-muted">' . e(format_datetime((string) $feedback['created_at'])) . '</span>';
    if (!empty($feedback['comment'])) {
        echo '<p class="feedback-comment">' . nl2br(e((string) $feedback['comment'])) . '</p>';
    }
    echo '</div>';
}

/** Rating form; the page must handle POST action=feedback via submit_ticket_feedback(). */
function render_feedback_form(): void
{
    echo '<form method="post" class="feedback-form" data-disable-on-submit>';
    echo csrf_field();
    echo '<input type="hidden" name="action" value="feedback">';
    echo '<p class="form-label" id="rating_label">How was your repair?</p>';
    echo '<fieldset class="rating-input" aria-labelledby="rating_label">';
    // Reverse order so the CSS sibling selector can light up every star up to the hovered/checked one.
    for ($i = 5; $i >= 1; $i--) {
        echo '<input type="radio" id="rating_' . $i . '" name="rating" value="' . $i . '" required>';
        echo '<label for="rating_' . $i . '" title="' . $i . ' star' . ($i === 1 ? '' : 's') . '"><i class="bi bi-star-fill" aria-hidden="true"></i><span class="sr-only">' . $i . ' star' . ($i === 1 ? '' : 's') . '</span></label>';
    }
    echo '</fieldset>';
    echo '<label class="sr-only" for="feedback_comment">Comment</label>';
    echo '<textarea class="form-control" id="feedback_comment" name="feedback_comment" rows="2" maxlength="' . FEEDBACK_COMMENT_MAX_LENGTH . '" placeholder="Tell us what went well or what we can improve (optional)"></textarea>';
    echo '<button type="submit" class="btn btn-rapid-primary btn-sm mt-2">Submit feedback</button>';
    echo '</form>';
}
