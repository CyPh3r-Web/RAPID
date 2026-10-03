<?php
/**
 * Warranty & warranty-claim helpers
 */

declare(strict_types=1);

define('CLAIM_STATUSES', [
    'submitted' => 'Submitted',
    'reviewing' => 'Reviewing',
    'approved'  => 'Approved',
    'rejected'  => 'Rejected',
    'repairing' => 'Repairing',
    'resolved'  => 'Resolved',
]);

function claim_status_label(string $status): string
{
    return CLAIM_STATUSES[$status] ?? ucwords(str_replace('_', ' ', $status));
}

function claim_badge_class(string $status): string
{
    switch ($status) {
        case 'resolved':
        case 'approved':
            return 'badge-status-success';
        case 'submitted':
        case 'reviewing':
        case 'repairing':
            return 'badge-status-warning';
        case 'rejected':
            return 'badge-status-danger';
        default:
            return 'badge-status-muted';
    }
}

function allowed_claim_transitions(string $from): array
{
    $map = [
        'submitted' => ['reviewing', 'approved', 'rejected'],
        'reviewing' => ['approved', 'rejected'],
        'approved'  => ['repairing', 'rejected'],
        'repairing' => ['resolved'],
        'rejected'  => [],
        'resolved'  => [],
    ];
    return $map[$from] ?? [];
}

function warranty_days_setting(): int
{
    $days = (int) get_setting('warranty_days', (string) DEFAULT_WARRANTY_DAYS);
    return $days > 0 ? $days : DEFAULT_WARRANTY_DAYS;
}

/**
 * Compute live warranty fields from dates (do not hardcode remaining days).
 *
 * @return array{remaining_days:int, computed_status:string, is_claimable:bool, label:string}
 */
function compute_warranty_state(array $warranty): array
{
    $today = new DateTimeImmutable('today');
    $end = DateTimeImmutable::createFromFormat('Y-m-d', substr((string) $warranty['warranty_end'], 0, 10));
    if (!$end) {
        return [
            'remaining_days' => 0,
            'computed_status' => 'expired',
            'is_claimable' => false,
            'label' => 'EXPIRED',
        ];
    }

    $endDay = $end->setTime(0, 0, 0);
    $diff = (int) $today->diff($endDay)->format('%r%a');

    if ($diff < 0) {
        $status = 'expired';
        $label = 'EXPIRED';
        $claimable = false;
        $remaining = 0;
    } elseif ($diff <= 7) {
        $status = 'expiring_soon';
        $label = 'EXPIRING SOON';
        $claimable = true;
        $remaining = $diff;
    } else {
        $status = 'active';
        $label = 'ACTIVE';
        $claimable = true;
        $remaining = $diff;
    }

    return [
        'remaining_days' => $remaining,
        'computed_status' => $status,
        'is_claimable' => $claimable,
        'label' => $label,
    ];
}

function sync_warranty_status_row(int $warrantyId, string $computedStatus): void
{
    try {
        db()->prepare('UPDATE warranties SET warranty_status = ? WHERE id = ?')
            ->execute([$computedStatus, $warrantyId]);
    } catch (Throwable $e) {
        error_log('sync_warranty_status_row: ' . $e->getMessage());
    }
}

function get_warranty_for_ticket(int $ticketId): ?array
{
    $stmt = db()->prepare('SELECT * FROM warranties WHERE ticket_id = ? LIMIT 1');
    $stmt->execute([$ticketId]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }

    $state = compute_warranty_state($row);
    if ($row['warranty_status'] !== $state['computed_status']) {
        sync_warranty_status_row((int) $row['id'], $state['computed_status']);
        $row['warranty_status'] = $state['computed_status'];
    }

    return array_merge($row, $state);
}

/**
 * Start warranty when repair is completed. Idempotent.
 *
 * @return array{ok:bool, error?:string, warranty_id?:int}
 */
function start_warranty_for_ticket(int $ticketId, ?int $days = null): array
{
    $pdo = db();
    try {
        $existing = $pdo->prepare('SELECT id FROM warranties WHERE ticket_id = ? LIMIT 1');
        $existing->execute([$ticketId]);
        $existingId = $existing->fetchColumn();
        if ($existingId) {
            if (function_exists('warranty_ensure_verify_code')) {
                warranty_ensure_verify_code((int) $existingId);
            }
            return ['ok' => true, 'warranty_id' => (int) $existingId];
        }

        $ticket = $pdo->prepare("SELECT id, ticket_number, current_status FROM repair_tickets WHERE id = ? LIMIT 1");
        $ticket->execute([$ticketId]);
        $t = $ticket->fetch();
        if (!$t) {
            return ['ok' => false, 'error' => 'Ticket not found.'];
        }
        if ($t['current_status'] !== 'completed') {
            return ['ok' => false, 'error' => 'Warranty can only start on completed repairs.'];
        }

        $days = $days !== null && $days > 0 ? $days : warranty_days_setting();
        $start = date('Y-m-d');
        $end = date('Y-m-d', strtotime('+' . $days . ' days'));

        $pdo->prepare(
            'INSERT INTO warranties (ticket_id, warranty_start, warranty_end, warranty_days, warranty_status)
             VALUES (?, ?, ?, ?, \'active\')'
        )->execute([$ticketId, $start, $end, $days]);

        $warrantyId = (int) $pdo->lastInsertId();
        if (function_exists('warranty_ensure_verify_code')) {
            warranty_ensure_verify_code($warrantyId);
        }

        $customerUserId = get_customer_user_id_for_ticket($ticketId);
        if ($customerUserId) {
            create_notification(
                $customerUserId,
                'Warranty started',
                'A ' . $days . '-day warranty started for ticket ' . $t['ticket_number'] . '. Expires ' . format_date($end) . '.',
                $ticketId
            );
        }

        return ['ok' => true, 'warranty_id' => $warrantyId];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => friendly_error($e)];
    }
}

/**
 * File a warranty claim (customer). Requires active/expiring_soon warranty.
 *
 * @return array{ok:bool, error?:string, claim_id?:int}
 */
function file_warranty_claim(int $ticketId, int $customerId, int $userId, string $issueDescription): array
{
    $issueDescription = trim($issueDescription);
    if (strlen($issueDescription) < 10) {
        return ['ok' => false, 'error' => 'Please describe the issue (at least 10 characters).'];
    }

    $pdo = db();
    try {
        $ticket = get_customer_ticket($ticketId, $customerId);
        if (!$ticket) {
            return ['ok' => false, 'error' => 'Ticket not found.'];
        }
        if ($ticket['current_status'] !== 'completed') {
            return ['ok' => false, 'error' => 'Warranty claims are only for completed repairs.'];
        }

        $warranty = get_warranty_for_ticket($ticketId);
        if (!$warranty) {
            return ['ok' => false, 'error' => 'No warranty record found for this ticket.'];
        }
        if (!$warranty['is_claimable']) {
            return ['ok' => false, 'error' => 'This warranty has expired. Claims cannot be filed.'];
        }

        $open = $pdo->prepare(
            "SELECT id FROM warranty_claims
             WHERE original_ticket_id = ? AND customer_id = ?
               AND claim_status NOT IN ('rejected','resolved')
             LIMIT 1"
        );
        $open->execute([$ticketId, $customerId]);
        if ($open->fetch()) {
            return ['ok' => false, 'error' => 'You already have an open warranty claim for this ticket.'];
        }

        $pdo->prepare(
            'INSERT INTO warranty_claims
                (original_ticket_id, customer_id, device_id, issue_description, claim_status, technician_id)
             VALUES (?, ?, ?, ?, \'submitted\', ?)'
        )->execute([
            $ticketId,
            $customerId,
            (int) $ticket['device_id'],
            $issueDescription,
            $ticket['assigned_technician_id'] ?: null,
        ]);
        $claimId = (int) $pdo->lastInsertId();
        record_claim_status($claimId, 'submitted', $userId);

        create_notification(
            $userId,
            'Warranty claim submitted',
            'Your warranty claim for ticket ' . $ticket['ticket_number'] . ' was submitted.',
            $ticketId
        );
        notify_admins(
            'New warranty claim',
            'Customer filed a warranty claim on ' . $ticket['ticket_number'] . '.',
            $ticketId
        );

        if (!empty($ticket['assigned_technician_id'])) {
            $tu = $pdo->prepare('SELECT user_id FROM technicians WHERE id = ?');
            $tu->execute([(int) $ticket['assigned_technician_id']]);
            $techUserId = (int) $tu->fetchColumn();
            if ($techUserId) {
                create_notification(
                    $techUserId,
                    'Warranty claim assigned',
                    'A warranty claim was filed for ticket ' . $ticket['ticket_number'] . '.',
                    $ticketId
                );
            }
        }

        log_activity($userId, 'warranty_claim', 'Filed claim #' . $claimId . ' for ' . $ticket['ticket_number']);
        return ['ok' => true, 'claim_id' => $claimId];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => friendly_error($e)];
    }
}

function get_claim_full(int $claimId): ?array
{
    $stmt = db()->prepare(
        'SELECT wc.*,
                rt.ticket_number, rt.problem_description AS original_problem, rt.completed_at,
                d.device_type, d.brand, d.model,
                cu.first_name AS customer_first_name, cu.last_name AS customer_last_name,
                cu.email AS customer_email, cu.phone AS customer_phone,
                c.user_id AS customer_user_id,
                CONCAT(COALESCE(tu.first_name,\'\'), \' \', COALESCE(tu.last_name,\'\')) AS technician_name
         FROM warranty_claims wc
         INNER JOIN repair_tickets rt ON rt.id = wc.original_ticket_id
         INNER JOIN devices d ON d.id = wc.device_id
         INNER JOIN customers c ON c.id = wc.customer_id
         INNER JOIN users cu ON cu.id = c.user_id
         LEFT JOIN technicians t ON t.id = wc.technician_id
         LEFT JOIN users tu ON tu.id = t.user_id
         WHERE wc.id = ?
         LIMIT 1'
    );
    $stmt->execute([$claimId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Staff update claim status / resolution / technician.
 *
 * @return array{ok:bool, error?:string}
 */
function update_warranty_claim(
    int $claimId,
    int $actorUserId,
    string $actorRole,
    string $newStatus,
    string $resolution = '',
    ?int $technicianId = null
): array {
    if (!isset(CLAIM_STATUSES[$newStatus])) {
        return ['ok' => false, 'error' => 'Invalid claim status.'];
    }

    $pdo = db();
    try {
        $claim = get_claim_full($claimId);
        if (!$claim) {
            return ['ok' => false, 'error' => 'Claim not found.'];
        }

        $from = $claim['claim_status'];
        if ($from !== $newStatus && !in_array($newStatus, allowed_claim_transitions($from), true)) {
            return ['ok' => false, 'error' => 'Invalid claim transition from ' . claim_status_label($from) . ' to ' . claim_status_label($newStatus) . '.'];
        }

        // Technicians may only update claims assigned to them (or unassigned → self)
        if ($actorRole === 'technician') {
            $myTechId = get_technician_id_for_user($actorUserId);
            if (!$myTechId) {
                return ['ok' => false, 'error' => 'Technician profile not found.'];
            }
            $assigned = (int) ($claim['technician_id'] ?? 0);
            if ($assigned && $assigned !== $myTechId) {
                return ['ok' => false, 'error' => 'This claim is assigned to another technician.'];
            }
            $technicianId = $myTechId;
        }

        $sets = ['claim_status = ?'];
        $params = [$newStatus];

        if ($resolution !== '') {
            $sets[] = 'resolution = ?';
            $params[] = $resolution;
        } elseif (in_array($newStatus, ['rejected', 'resolved'], true) && trim((string) $claim['resolution']) === '' && $resolution === '') {
            // allow empty but encourage note — not required
        }

        if ($technicianId !== null && $technicianId > 0) {
            $sets[] = 'technician_id = ?';
            $params[] = $technicianId;
        } elseif ($actorRole === 'technician' && empty($claim['technician_id'])) {
            $sets[] = 'technician_id = ?';
            $params[] = get_technician_id_for_user($actorUserId);
        }

        $params[] = $claimId;
        $pdo->prepare('UPDATE warranty_claims SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($params);
        if ($newStatus !== $from) {
            record_claim_status($claimId, $newStatus, $actorUserId, $resolution !== '' ? $resolution : null);
        }

        create_notification(
            (int) $claim['customer_user_id'],
            'Warranty claim updated',
            'Claim on ticket ' . $claim['ticket_number'] . ' is now: ' . claim_status_label($newStatus) . '.',
            (int) $claim['original_ticket_id']
        );

        log_activity($actorUserId, 'claim_update', 'Claim #' . $claimId . ' → ' . $newStatus);
        return ['ok' => true];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => friendly_error($e)];
    }
}

/**
 * Notify customers whose warranties expire within 7 days (best-effort; can be run on dashboard load).
 */
function notify_expiring_warranties(int $limit = 20): void
{
    try {
        $rows = db()->query(
            "SELECT w.id, w.ticket_id, w.warranty_end, rt.ticket_number, c.user_id
             FROM warranties w
             INNER JOIN repair_tickets rt ON rt.id = w.ticket_id
             INNER JOIN customers c ON c.id = rt.customer_id
             WHERE w.warranty_end BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
               AND w.warranty_status IN ('active','expiring_soon')
             LIMIT " . (int) $limit
        )->fetchAll();

        foreach ($rows as $row) {
            $state = compute_warranty_state($row);
            sync_warranty_status_row((int) $row['id'], $state['computed_status']);

            // Avoid spamming: only notify once per day via activity check is heavy;
            // send a light notification keyed by checking recent similar notification.
            $check = db()->prepare(
                "SELECT id FROM notifications
                 WHERE user_id = ? AND ticket_id = ? AND title = 'Warranty expiring soon'
                   AND created_at >= DATE_SUB(NOW(), INTERVAL 3 DAY)
                 LIMIT 1"
            );
            $check->execute([(int) $row['user_id'], (int) $row['ticket_id']]);
            if ($check->fetch()) {
                continue;
            }

            create_notification(
                (int) $row['user_id'],
                'Warranty expiring soon',
                'Warranty for ticket ' . $row['ticket_number'] . ' expires on ' . format_date($row['warranty_end']) . ' (' . $state['remaining_days'] . ' day(s) left).',
                (int) $row['ticket_id']
            );
        }
    } catch (Throwable $e) {
        error_log('notify_expiring_warranties: ' . $e->getMessage());
    }
}

/**
 * Claim status history table, created on first use (like repair_templates).
 */
function ensure_claim_history_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    try {
        db()->exec(
            "CREATE TABLE IF NOT EXISTS `claim_status_history` (
              `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
              `claim_id` INT UNSIGNED NOT NULL,
              `status` ENUM('submitted', 'reviewing', 'approved', 'rejected', 'repairing', 'resolved') NOT NULL,
              `changed_by` INT UNSIGNED DEFAULT NULL,
              `remarks` TEXT DEFAULT NULL,
              `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              KEY `idx_claim_history_claim` (`claim_id`),
              CONSTRAINT `fk_claim_history_claim` FOREIGN KEY (`claim_id`) REFERENCES `warranty_claims` (`id`) ON DELETE CASCADE,
              CONSTRAINT `fk_claim_history_user` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $done = true;
    } catch (Throwable $e) {
        error_log('ensure_claim_history_schema: ' . $e->getMessage());
    }
}

function record_claim_status(int $claimId, string $status, ?int $userId = null, ?string $remarks = null): void
{
    ensure_claim_history_schema();
    try {
        db()->prepare('INSERT INTO claim_status_history (claim_id, status, changed_by, remarks) VALUES (?, ?, ?, ?)')
            ->execute([$claimId, $status, $userId ?: null, $remarks]);
    } catch (Throwable $e) {
        // History is informational; never block the claim update itself.
        error_log('record_claim_status: ' . $e->getMessage());
    }
}

/**
 * @return array<string,string> status => earliest time it was reached
 */
function get_claim_status_times(int $claimId): array
{
    ensure_claim_history_schema();
    try {
        $stmt = db()->prepare(
            'SELECT status, MIN(created_at) FROM claim_status_history WHERE claim_id = ? GROUP BY status'
        );
        $stmt->execute([$claimId]);
        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Claim progress stepper (same markup as the repair stepper).
 * Stage dates come from claim_status_history; claims filed before that table
 * existed fall back to filed date + last update.
 *
 * @param array{id?:int, claim_status:string, created_at:string, updated_at?:string} $claim
 */
function render_claim_stepper(array $claim): void
{
    $times = !empty($claim['id']) ? get_claim_status_times((int) $claim['id']) : [];
    $stages = [
        'submitted' => ['label' => 'Submitted', 'icon' => 'bi-send'],
        'reviewing' => ['label' => 'Reviewing', 'icon' => 'bi-search'],
        'approved' => ['label' => 'Approved', 'icon' => 'bi-hand-thumbs-up'],
        'repairing' => ['label' => 'Repairing', 'icon' => 'bi-wrench-adjustable'],
        'resolved' => ['label' => 'Resolved', 'icon' => 'bi-check-circle'],
    ];
    $status = (string) $claim['claim_status'];
    $rejected = $status === 'rejected';
    $keys = array_keys($stages);
    // A rejected claim stopped after review.
    $currentIdx = $rejected ? 1 : (int) array_search($status, $keys, true);

    echo '<ol class="progress-stepper" aria-label="Claim progress">';
    foreach ($keys as $idx => $key) {
        $isDone = $idx < $currentIdx || ($rejected && $idx === $currentIdx) || ($status === 'resolved' && $key === 'resolved');
        $isCurrent = !$rejected && $idx === $currentIdx && $status !== 'resolved';
        $state = $isCurrent ? 'is-current' : ($isDone ? 'is-done' : 'is-pending');
        if (!empty($times[$key])) {
            $meta = ($isCurrent ? 'Since ' : '') . format_datetime((string) $times[$key]);
        } elseif ($key === 'submitted') {
            $meta = format_datetime((string) $claim['created_at']);
        } elseif ($idx === $currentIdx && !empty($claim['updated_at'])) {
            $meta = ($isCurrent ? 'Since ' : '') . format_datetime((string) $claim['updated_at']);
        } else {
            $meta = $isDone ? 'Done' : 'Pending';
        }
        echo '<li class="progress-step ' . $state . '"' . ($isCurrent ? ' aria-current="step"' : '') . '>';
        echo '<span class="progress-step-marker" aria-hidden="true"><i class="bi ' . e($isDone ? 'bi-check-lg' : $stages[$key]['icon']) . '"></i></span>';
        echo '<div class="progress-step-body">';
        echo '<div class="progress-step-label">' . e($stages[$key]['label']) . '</div>';
        echo '<div class="progress-step-meta">' . e($meta) . '</div>';
        echo '</div></li>';
    }
    echo '</ol>';

    if ($rejected) {
        echo '<p class="progress-exception"><i class="bi bi-x-circle" aria-hidden="true"></i> Claim rejected';
        echo !empty($claim['updated_at']) ? ' · ' . e(format_datetime((string) $claim['updated_at'])) : '';
        echo '</p>';
    }
}
