<?php
/**
 * Repair workflow: status transitions, ticket updates, quotes
 */

declare(strict_types=1);

/**
 * Allowed next statuses from a given status.
 */
function allowed_status_transitions(string $from): array
{
    $map = [
        'booking_submitted' => ['received', 'cancelled'],
        'received'          => ['diagnosing', 'cancelled'],
        'diagnosing'        => ['quotation_pending', 'cancelled'],
        'quotation_pending' => ['awaiting_approval', 'cancelled'],
        'awaiting_approval' => ['approved', 'declined', 'cancelled'],
        'approved'          => ['repairing', 'cancelled'],
        'repairing'         => ['ready_for_pickup', 'cancelled'],
        'ready_for_pickup'  => ['completed', 'cancelled'],
        'declined'          => ['quotation_pending', 'cancelled'],
        'completed'         => [],
        'cancelled'         => [],
    ];

    return $map[$from] ?? [];
}

function can_transition_status(string $from, string $to): bool
{
    return in_array($to, allowed_status_transitions($from), true);
}

function get_technician_id_for_user(int $userId): ?int
{
    $stmt = db()->prepare('SELECT id FROM technicians WHERE user_id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $id = $stmt->fetchColumn();
    return $id !== false ? (int) $id : null;
}

function list_active_technicians(): array
{
    return db()->query(
        "SELECT t.id, t.specialization, t.availability_status,
                u.first_name, u.last_name, u.email, u.phone
         FROM technicians t
         INNER JOIN users u ON u.id = t.user_id
         WHERE u.status = 'active'
         ORDER BY u.first_name, u.last_name"
    )->fetchAll();
}

/**
 * Full ticket row for staff (admin or assigned tech).
 */
function get_ticket_full(int $ticketId): ?array
{
    $stmt = db()->prepare(
        'SELECT rt.*,
                d.device_type, d.brand, d.model, d.serial_number, d.imei, d.color,
                d.accessories, d.physical_condition,
                c.id AS customer_profile_id, cu.id AS customer_user_id,
                cu.first_name AS customer_first_name, cu.last_name AS customer_last_name,
                cu.email AS customer_email, cu.phone AS customer_phone,
                t.id AS technician_profile_id,
                tu.first_name AS technician_first_name, tu.last_name AS technician_last_name,
                CONCAT(COALESCE(tu.first_name, \'\'), \' \', COALESCE(tu.last_name, \'\')) AS technician_name
         FROM repair_tickets rt
         INNER JOIN devices d ON d.id = rt.device_id
         INNER JOIN customers c ON c.id = rt.customer_id
         INNER JOIN users cu ON cu.id = c.user_id
         LEFT JOIN technicians t ON t.id = rt.assigned_technician_id
         LEFT JOIN users tu ON tu.id = t.user_id
         WHERE rt.id = ?
         LIMIT 1'
    );
    $stmt->execute([$ticketId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function get_customer_user_id_for_ticket(int $ticketId): ?int
{
    $stmt = db()->prepare(
        'SELECT c.user_id FROM repair_tickets rt
         INNER JOIN customers c ON c.id = rt.customer_id
         WHERE rt.id = ? LIMIT 1'
    );
    $stmt->execute([$ticketId]);
    $id = $stmt->fetchColumn();
    return $id !== false ? (int) $id : null;
}

/**
 * Update ticket status with history + customer notification.
 *
 * @return array{ok:bool, error?:string}
 */
function update_ticket_status(int $ticketId, string $newStatus, int $updatedByUserId, string $remarks = ''): array
{
    if (!isset(TICKET_STATUSES[$newStatus])) {
        return ['ok' => false, 'error' => 'Invalid status.'];
    }

    $pdo = db();
    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare('SELECT id, ticket_number, current_status FROM repair_tickets WHERE id = ? FOR UPDATE');
        $stmt->execute([$ticketId]);
        $ticket = $stmt->fetch();

        if (!$ticket) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => 'Ticket not found.'];
        }

        $from = $ticket['current_status'];
        if ($from === $newStatus) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => 'Ticket is already in that status.'];
        }

        if (!can_transition_status($from, $newStatus)) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => 'Invalid status transition from ' . status_label($from) . ' to ' . status_label($newStatus) . '.'];
        }

        $sets = ['current_status = ?'];
        $params = [$newStatus];

        if ($newStatus === 'received') {
            $sets[] = 'received_at = COALESCE(received_at, NOW())';
        }
        if ($newStatus === 'completed') {
            $sets[] = 'completed_at = NOW()';
        }

        $params[] = $ticketId;
        $pdo->prepare('UPDATE repair_tickets SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($params);

        $pdo->prepare(
            'INSERT INTO repair_status_history (ticket_id, status, remarks, updated_by) VALUES (?, ?, ?, ?)'
        )->execute([
            $ticketId,
            $newStatus,
            $remarks !== '' ? $remarks : 'Status updated to ' . status_label($newStatus) . '.',
            $updatedByUserId,
        ]);

        $pdo->commit();

        $customerUserId = get_customer_user_id_for_ticket($ticketId);
        if ($customerUserId) {
            $msg = 'Ticket ' . $ticket['ticket_number'] . ' is now: ' . status_label($newStatus) . '.';
            if ($newStatus === 'ready_for_pickup') {
                $msg = 'Ticket ' . $ticket['ticket_number'] . ' is ready for pickup.';
            }
            if ($newStatus === 'completed') {
                $msg = 'Ticket ' . $ticket['ticket_number'] . ' is completed. Your warranty period will start now.';
            }
            create_notification(
                $customerUserId,
                $newStatus === 'ready_for_pickup' ? 'Ready for pickup' : ($newStatus === 'completed' ? 'Repair completed' : 'Repair status updated'),
                $msg,
                $ticketId
            );
        }

        if ($newStatus === 'completed') {
            start_warranty_for_ticket($ticketId);
        }

        log_activity($updatedByUserId, 'status_update', 'Ticket ' . $ticket['ticket_number'] . ' → ' . $newStatus);

        return ['ok' => true];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['ok' => false, 'error' => friendly_error($e)];
    }
}

/**
 * Assign technician; optionally move booking_submitted → received.
 *
 * @return array{ok:bool, error?:string}
 */
function assign_technician_to_ticket(int $ticketId, int $technicianId, int $adminUserId, bool $markReceived = true): array
{
    $pdo = db();
    try {
        $check = $pdo->prepare(
            "SELECT t.id FROM technicians t
             INNER JOIN users u ON u.id = t.user_id
             WHERE t.id = ? AND u.status = 'active' LIMIT 1"
        );
        $check->execute([$technicianId]);
        if (!$check->fetch()) {
            return ['ok' => false, 'error' => 'Technician not found or inactive.'];
        }

        $stmt = $pdo->prepare('SELECT id, ticket_number, current_status, assigned_technician_id FROM repair_tickets WHERE id = ?');
        $stmt->execute([$ticketId]);
        $ticket = $stmt->fetch();
        if (!$ticket) {
            return ['ok' => false, 'error' => 'Ticket not found.'];
        }

        if (in_array($ticket['current_status'], ['completed', 'cancelled'], true)) {
            return ['ok' => false, 'error' => 'Cannot assign a technician on a closed ticket.'];
        }

        $pdo->prepare('UPDATE repair_tickets SET assigned_technician_id = ? WHERE id = ?')
            ->execute([$technicianId, $ticketId]);

        log_activity($adminUserId, 'assign_technician', 'Assigned technician #' . $technicianId . ' to ' . $ticket['ticket_number']);

        // Notify technician
        $techUser = $pdo->prepare('SELECT user_id FROM technicians WHERE id = ?');
        $techUser->execute([$technicianId]);
        $techUserId = (int) $techUser->fetchColumn();
        if ($techUserId) {
            create_notification(
                $techUserId,
                'New repair assignment',
                'You were assigned to ticket ' . $ticket['ticket_number'] . '.',
                $ticketId
            );
        }

        $customerUserId = get_customer_user_id_for_ticket($ticketId);
        if ($customerUserId) {
            create_notification(
                $customerUserId,
                'Technician assigned',
                'A technician was assigned to ticket ' . $ticket['ticket_number'] . '.',
                $ticketId
            );
        }

        if ($markReceived && $ticket['current_status'] === 'booking_submitted') {
            return update_ticket_status($ticketId, 'received', $adminUserId, 'Device marked received and technician assigned.');
        }

        return ['ok' => true];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => friendly_error($e)];
    }
}

/**
 * Save diagnosis; move to quotation_pending when coming from diagnosing/received.
 *
 * @return array{ok:bool, error?:string}
 */
function save_diagnosis(int $ticketId, int $technicianId, int $userId, string $diagnosis, string $recommended, ?string $estimatedCompletion): array
{
    if (trim($diagnosis) === '') {
        return ['ok' => false, 'error' => 'Diagnosis notes are required.'];
    }

    $pdo = db();
    try {
        $ticket = get_ticket_full($ticketId);
        if (!$ticket) {
            return ['ok' => false, 'error' => 'Ticket not found.'];
        }
        if ((int) ($ticket['assigned_technician_id'] ?? 0) !== $technicianId) {
            return ['ok' => false, 'error' => 'This ticket is not assigned to you.'];
        }

        $pdo->beginTransaction();

        $pdo->prepare(
            'INSERT INTO diagnoses (ticket_id, technician_id, diagnosis, recommended_action, estimated_completion)
             VALUES (?, ?, ?, ?, ?)'
        )->execute([
            $ticketId,
            $technicianId,
            trim($diagnosis),
            $recommended !== '' ? trim($recommended) : null,
            $estimatedCompletion,
        ]);

        if ($estimatedCompletion) {
            $pdo->prepare('UPDATE repair_tickets SET estimated_completion = ? WHERE id = ?')
                ->execute([$estimatedCompletion, $ticketId]);
        }

        $pdo->commit();

        $customerUserId = get_customer_user_id_for_ticket($ticketId);
        if ($customerUserId) {
            create_notification(
                $customerUserId,
                'Diagnosis completed',
                'A diagnosis was added for ticket ' . $ticket['ticket_number'] . '.',
                $ticketId
            );
        }

        // Advance workflow toward quotation
        $status = $ticket['current_status'];
        if ($status === 'received') {
            $r = update_ticket_status($ticketId, 'diagnosing', $userId, 'Diagnosis started.');
            if (!$r['ok']) {
                return $r;
            }
            $status = 'diagnosing';
        }
        if ($status === 'diagnosing') {
            $r = update_ticket_status($ticketId, 'quotation_pending', $userId, 'Diagnosis complete; quotation pending.');
            if (!$r['ok']) {
                return $r;
            }
        }

        log_activity($userId, 'diagnosis_saved', 'Diagnosis saved for ' . $ticket['ticket_number']);
        return ['ok' => true];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['ok' => false, 'error' => friendly_error($e)];
    }
}

/**
 * Create quotation; totals recalculated server-side; ticket → awaiting_approval.
 *
 * @return array{ok:bool, error?:string, quotation_id?:int}
 */
function create_quotation(
    int $ticketId,
    int $technicianId,
    int $userId,
    float $labor,
    float $parts,
    float $other,
    string $notes,
    ?string $validUntil
): array {
    if ($labor < 0 || $parts < 0 || $other < 0) {
        return ['ok' => false, 'error' => 'Costs cannot be negative.'];
    }

    $total = round($labor + $parts + $other, 2);

    $pdo = db();
    try {
        $ticket = get_ticket_full($ticketId);
        if (!$ticket) {
            return ['ok' => false, 'error' => 'Ticket not found.'];
        }
        if ((int) ($ticket['assigned_technician_id'] ?? 0) !== $technicianId) {
            return ['ok' => false, 'error' => 'This ticket is not assigned to you.'];
        }

        $allowedFrom = ['quotation_pending', 'diagnosing', 'declined', 'awaiting_approval'];
        if (!in_array($ticket['current_status'], $allowedFrom, true)) {
            return ['ok' => false, 'error' => 'Quotations can only be issued at the diagnosis/quotation stage.'];
        }

        $pdo->beginTransaction();

        // Expire prior pending quotes
        $pdo->prepare(
            "UPDATE quotations SET status = 'expired' WHERE ticket_id = ? AND status = 'pending'"
        )->execute([$ticketId]);

        $pdo->prepare(
            'INSERT INTO quotations
                (ticket_id, technician_id, labor_cost, parts_cost, other_cost, total_amount, notes, status, valid_until)
             VALUES (?, ?, ?, ?, ?, ?, ?, \'pending\', ?)'
        )->execute([
            $ticketId,
            $technicianId,
            number_format($labor, 2, '.', ''),
            number_format($parts, 2, '.', ''),
            number_format($other, 2, '.', ''),
            number_format($total, 2, '.', ''),
            $notes !== '' ? $notes : null,
            $validUntil,
        ]);
        $quoteId = (int) $pdo->lastInsertId();

        $pdo->commit();

        // Move status toward awaiting approval
        $status = $ticket['current_status'];
        if ($status === 'diagnosing') {
            $r = update_ticket_status($ticketId, 'quotation_pending', $userId, 'Quotation prepared.');
            if (!$r['ok']) {
                return $r;
            }
            $status = 'quotation_pending';
        }
        if ($status === 'declined') {
            $r = update_ticket_status($ticketId, 'quotation_pending', $userId, 'New quotation issued after decline.');
            if (!$r['ok']) {
                return $r;
            }
            $status = 'quotation_pending';
        }
        if ($status === 'quotation_pending') {
            $r = update_ticket_status($ticketId, 'awaiting_approval', $userId, 'Quotation sent to customer for approval.');
            if (!$r['ok']) {
                return $r;
            }
        } elseif ($status === 'awaiting_approval') {
            // Already waiting; just notify
            $customerUserId = get_customer_user_id_for_ticket($ticketId);
            if ($customerUserId) {
                create_notification(
                    $customerUserId,
                    'Quotation available',
                    'A new quotation is ready for ticket ' . $ticket['ticket_number'] . '. Total: ₱' . number_format($total, 2) . '.',
                    $ticketId
                );
            }
        }

        $customerUserId = get_customer_user_id_for_ticket($ticketId);
        if ($customerUserId && $status !== 'awaiting_approval') {
            // Notification also fired by status update; add quote-specific one
            create_notification(
                $customerUserId,
                'Quotation available',
                'Please review the quotation for ticket ' . $ticket['ticket_number'] . '. Total: ₱' . number_format($total, 2) . '.',
                $ticketId
            );
        }

        log_activity($userId, 'quotation_created', 'Quotation #' . $quoteId . ' for ' . $ticket['ticket_number']);
        return ['ok' => true, 'quotation_id' => $quoteId];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['ok' => false, 'error' => friendly_error($e)];
    }
}

/**
 * Customer approves or declines the latest pending quotation.
 *
 * @return array{ok:bool, error?:string}
 */
function respond_to_quotation(int $ticketId, int $customerId, int $userId, string $decision, string $note = ''): array
{
    $decision = strtolower($decision);
    if (!in_array($decision, ['approved', 'declined'], true)) {
        return ['ok' => false, 'error' => 'Invalid decision.'];
    }

    $pdo = db();
    try {
        $ticket = get_customer_ticket($ticketId, $customerId);
        if (!$ticket) {
            return ['ok' => false, 'error' => 'Ticket not found.'];
        }

        if ($ticket['current_status'] !== 'awaiting_approval') {
            return ['ok' => false, 'error' => 'This ticket is not awaiting quotation approval.'];
        }

        $q = $pdo->prepare(
            "SELECT id, technician_id FROM quotations
             WHERE ticket_id = ? AND status = 'pending'
             ORDER BY id DESC LIMIT 1"
        );
        $q->execute([$ticketId]);
        $quote = $q->fetch();
        if (!$quote) {
            return ['ok' => false, 'error' => 'No pending quotation found.'];
        }

        $pdo->prepare('UPDATE quotations SET status = ? WHERE id = ?')
            ->execute([$decision, (int) $quote['id']]);

        $remarks = $decision === 'approved'
            ? 'Customer approved the quotation.'
            : 'Customer declined the quotation.' . ($note !== '' ? ' Note: ' . $note : '');

        $r = update_ticket_status($ticketId, $decision, $userId, $remarks);
        if (!$r['ok']) {
            return $r;
        }

        // Notify assigned technician
        if (!empty($ticket['assigned_technician_id'])) {
            $tu = $pdo->prepare('SELECT user_id FROM technicians WHERE id = ?');
            $tu->execute([(int) $ticket['assigned_technician_id']]);
            $techUserId = (int) $tu->fetchColumn();
            if ($techUserId) {
                create_notification(
                    $techUserId,
                    $decision === 'approved' ? 'Quotation approved' : 'Quotation declined',
                    'Customer ' . ($decision === 'approved' ? 'approved' : 'declined') . ' the quotation for ' . $ticket['ticket_number'] . '.',
                    $ticketId
                );
            }
        }

        notify_admins(
            'Quotation ' . $decision,
            'Ticket ' . $ticket['ticket_number'] . ' quotation was ' . $decision . ' by the customer.',
            $ticketId
        );

        return ['ok' => true];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => friendly_error($e)];
    }
}

/**
 * Priority badge class
 */
function priority_badge_class(string $priority): string
{
    switch ($priority) {
        case 'urgent':
            return 'badge-status-danger';
        case 'high':
            return 'badge-status-warning';
        case 'low':
            return 'badge-status-muted';
        default:
            return 'badge-status-primary';
    }
}

