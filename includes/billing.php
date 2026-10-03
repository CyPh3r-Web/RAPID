<?php
/**
 * Payments & balance tracking per repair ticket.
 * Amount due = latest approved quotation total; balance = due − non-voided payments.
 */

declare(strict_types=1);

const PAYMENT_METHODS = [
    'cash' => 'Cash',
    'gcash' => 'GCash',
    'maya' => 'Maya',
    'bank_transfer' => 'Bank transfer',
    'card' => 'Card',
];

function ensure_billing_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }

    try {
        db()->exec(
            "CREATE TABLE IF NOT EXISTS `payments` (
              `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
              `ticket_id` INT UNSIGNED NOT NULL,
              `amount` DECIMAL(10,2) NOT NULL,
              `method` ENUM('cash', 'gcash', 'maya', 'bank_transfer', 'card') NOT NULL DEFAULT 'cash',
              `reference_no` VARCHAR(100) DEFAULT NULL,
              `notes` VARCHAR(255) DEFAULT NULL,
              `received_by` INT UNSIGNED DEFAULT NULL,
              `voided_at` DATETIME DEFAULT NULL,
              `voided_by` INT UNSIGNED DEFAULT NULL,
              `void_reason` VARCHAR(255) DEFAULT NULL,
              `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              KEY `idx_payments_ticket` (`ticket_id`),
              KEY `idx_payments_created` (`created_at`),
              CONSTRAINT `fk_payments_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `repair_tickets` (`id`) ON DELETE CASCADE,
              CONSTRAINT `fk_payments_received_by` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
              CONSTRAINT `fk_payments_voided_by` FOREIGN KEY (`voided_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $done = true;
    } catch (Throwable $e) {
        error_log('ensure_billing_schema failed: ' . $e->getMessage());
    }
}

function payment_receipt_number(int $paymentId): string
{
    return 'OR-' . str_pad((string) $paymentId, 6, '0', STR_PAD_LEFT);
}

function payment_method_label(string $method): string
{
    return PAYMENT_METHODS[$method] ?? ucwords(str_replace('_', ' ', $method));
}

/**
 * @return array{due:float, paid:float, balance:float, quote_id:?int, payments:list<array<string,mixed>>}
 */
function ticket_billing(int $ticketId): array
{
    ensure_billing_schema();

    $q = db()->prepare(
        "SELECT id, total_amount FROM quotations
         WHERE ticket_id = ? AND status = 'approved'
         ORDER BY id DESC LIMIT 1"
    );
    $q->execute([$ticketId]);
    $quote = $q->fetch();

    $p = db()->prepare(
        "SELECT p.*, CONCAT(u.first_name, ' ', u.last_name) AS received_by_name
         FROM payments p
         LEFT JOIN users u ON u.id = p.received_by
         WHERE p.ticket_id = ?
         ORDER BY p.created_at ASC, p.id ASC"
    );
    $p->execute([$ticketId]);
    $payments = $p->fetchAll() ?: [];

    $paid = 0.0;
    foreach ($payments as $row) {
        if ($row['voided_at'] === null) {
            $paid += (float) $row['amount'];
        }
    }

    $due = $quote ? (float) $quote['total_amount'] : 0.0;
    $paid = round($paid, 2);

    return [
        'due' => $due,
        'paid' => $paid,
        'balance' => round(max(0.0, $due - $paid), 2),
        'quote_id' => $quote ? (int) $quote['id'] : null,
        'payments' => $payments,
    ];
}

/**
 * @return array{ok:bool, error?:string, payment_id?:int}
 */
function record_payment(int $ticketId, float $amount, string $method, string $reference, string $notes, int $userId): array
{
    ensure_billing_schema();

    $amount = round($amount, 2);
    $reference = trim($reference);
    $notes = trim($notes);

    if ($amount <= 0) {
        return ['ok' => false, 'error' => 'Payment amount must be greater than zero.'];
    }
    if (!isset(PAYMENT_METHODS[$method])) {
        return ['ok' => false, 'error' => 'Select a valid payment method.'];
    }
    if ($method !== 'cash' && $reference === '') {
        return ['ok' => false, 'error' => 'Reference number is required for ' . payment_method_label($method) . ' payments.'];
    }
    if (strlen($reference) > 100 || strlen($notes) > 255) {
        return ['ok' => false, 'error' => 'Reference or notes are too long.'];
    }

    $pdo = db();
    try {
        $pdo->beginTransaction();

        // Row lock serializes concurrent payments on the same ticket so the balance check holds.
        $t = $pdo->prepare('SELECT id, ticket_number, current_status FROM repair_tickets WHERE id = ? FOR UPDATE');
        $t->execute([$ticketId]);
        $ticket = $t->fetch();
        if (!$ticket) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => 'Ticket not found.'];
        }
        if ($ticket['current_status'] === 'cancelled') {
            $pdo->rollBack();
            return ['ok' => false, 'error' => 'Cannot record payments on a cancelled ticket.'];
        }

        $billing = ticket_billing($ticketId);
        if ($billing['quote_id'] === null) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => 'Payments can be recorded once the customer approves a quotation.'];
        }
        if ($amount > $billing['balance'] + 0.001) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => 'Amount exceeds the remaining balance of ' . money_php($billing['balance']) . '.'];
        }

        $pdo->prepare(
            'INSERT INTO payments (ticket_id, amount, method, reference_no, notes, received_by)
             VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([
            $ticketId,
            number_format($amount, 2, '.', ''),
            $method,
            $reference !== '' ? $reference : null,
            $notes !== '' ? $notes : null,
            $userId,
        ]);
        $paymentId = (int) $pdo->lastInsertId();
        $pdo->commit();

        $balance = round($billing['balance'] - $amount, 2);
        $customerUserId = get_customer_user_id_for_ticket($ticketId);
        if ($customerUserId) {
            create_notification(
                $customerUserId,
                'Payment received',
                'We received ' . money_php($amount) . ' for ticket ' . $ticket['ticket_number'] . '. '
                    . ($balance > 0 ? 'Remaining balance: ' . money_php($balance) . '.' : 'Fully paid — thank you!'),
                $ticketId
            );
        }
        log_activity($userId, 'payment_recorded', payment_receipt_number($paymentId) . ' ' . money_php($amount) . ' for ' . $ticket['ticket_number']);

        return ['ok' => true, 'payment_id' => $paymentId];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['ok' => false, 'error' => friendly_error($e)];
    }
}

/**
 * Void (never delete) a mistaken payment so the audit trail stays intact.
 *
 * @return array{ok:bool, error?:string}
 */
function void_payment(int $paymentId, int $ticketId, int $userId, string $reason): array
{
    ensure_billing_schema();
    $reason = trim($reason);
    if ($reason === '') {
        return ['ok' => false, 'error' => 'Give a reason for voiding this payment.'];
    }

    try {
        $stmt = db()->prepare(
            'UPDATE payments SET voided_at = NOW(), voided_by = ?, void_reason = ?
             WHERE id = ? AND ticket_id = ? AND voided_at IS NULL'
        );
        $stmt->execute([$userId, substr($reason, 0, 255), $paymentId, $ticketId]);
        if ($stmt->rowCount() === 0) {
            return ['ok' => false, 'error' => 'Payment not found or already voided.'];
        }
        log_activity($userId, 'payment_voided', payment_receipt_number($paymentId) . ' voided: ' . $reason);
        return ['ok' => true];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => friendly_error($e)];
    }
}

/**
 * @return array<string,mixed>|null payment row joined with its ticket number
 */
function get_payment(int $paymentId): ?array
{
    ensure_billing_schema();
    $stmt = db()->prepare(
        "SELECT p.*, rt.ticket_number, rt.customer_id, CONCAT(u.first_name, ' ', u.last_name) AS received_by_name
         FROM payments p
         INNER JOIN repair_tickets rt ON rt.id = p.ticket_id
         LEFT JOIN users u ON u.id = p.received_by
         WHERE p.id = ? LIMIT 1"
    );
    $stmt->execute([$paymentId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Shop-wide receivables: open balances on non-cancelled tickets plus total collected.
 *
 * @return array{unpaid_tickets:int, outstanding:float, collected:float}
 */
function billing_totals(): array
{
    ensure_billing_schema();
    try {
        $row = db()->query(
            "SELECT COUNT(*) AS unpaid_tickets, COALESCE(SUM(due - paid), 0) AS outstanding
             FROM (
                 SELECT q.total_amount AS due,
                        COALESCE((SELECT SUM(p.amount) FROM payments p
                                  WHERE p.ticket_id = rt.id AND p.voided_at IS NULL), 0) AS paid
                 FROM repair_tickets rt
                 INNER JOIN quotations q ON q.id = (
                     SELECT q2.id FROM quotations q2
                     WHERE q2.ticket_id = rt.id AND q2.status = 'approved'
                     ORDER BY q2.id DESC LIMIT 1
                 )
                 WHERE rt.current_status <> 'cancelled'
             ) x
             WHERE due - paid > 0"
        )->fetch();
        $collected = (float) db()->query('SELECT COALESCE(SUM(amount), 0) FROM payments WHERE voided_at IS NULL')->fetchColumn();
        return [
            'unpaid_tickets' => (int) $row['unpaid_tickets'],
            'outstanding' => round((float) $row['outstanding'], 2),
            'collected' => round($collected, 2),
        ];
    } catch (Throwable $e) {
        error_log('billing_totals failed: ' . $e->getMessage());
        return ['unpaid_tickets' => 0, 'outstanding' => 0.0, 'collected' => 0.0];
    }
}

/**
 * Amount due / paid / balance summary with payment list.
 * $receiptPath gets "?id={paymentId}" appended; $voidable shows the admin void control.
 */
function render_billing_card(array $billing, ?string $receiptPath = null, bool $voidable = false): void
{
    $balance = $billing['balance'];
    $hasQuote = $billing['quote_id'] !== null;

    echo '<div class="billing-summary">';
    echo '<div><span>Amount due</span><strong>' . e($hasQuote ? money_php($billing['due']) : '—') . '</strong></div>';
    echo '<div><span>Paid</span><strong>' . e(money_php($billing['paid'])) . '</strong></div>';
    echo '<div class="' . ($hasQuote && $balance <= 0 ? 'is-paid' : ($balance > 0 ? 'is-due' : '')) . '"><span>Balance</span><strong>'
        . e($hasQuote ? money_php($balance) : '—') . '</strong></div>';
    echo '</div>';

    if (!$hasQuote) {
        echo '<p class="text-sm text-rapid-muted mb-0">Billing starts once a quotation is approved.</p>';
        return;
    }
    if ($balance <= 0) {
        echo '<p class="billing-paid-note"><i class="bi bi-check-circle-fill" aria-hidden="true"></i> Fully paid</p>';
    }

    if (!$billing['payments']) {
        echo '<p class="text-sm text-rapid-muted mb-0">No payments recorded yet.</p>';
        return;
    }

    echo '<ul class="payment-list">';
    foreach ($billing['payments'] as $p) {
        $voided = $p['voided_at'] !== null;
        echo '<li class="' . ($voided ? 'is-voided' : '') . '">';
        echo '<div class="payment-list-main">';
        echo '<strong>' . e(money_php((float) $p['amount'])) . '</strong>';
        echo '<span>' . e(payment_method_label((string) $p['method']))
            . ($p['reference_no'] ? ' · Ref ' . e((string) $p['reference_no']) : '')
            . ' · ' . e(format_datetime((string) $p['created_at'])) . '</span>';
        if ($voided) {
            echo '<span class="payment-void-note">Voided: ' . e((string) $p['void_reason']) . '</span>';
        } elseif ($p['notes']) {
            echo '<span>' . e((string) $p['notes']) . '</span>';
        }
        echo '</div>';
        echo '<div class="payment-list-actions">';
        if ($receiptPath && !$voided) {
            echo '<a class="btn btn-sm btn-rapid-outline" href="' . e(url($receiptPath . '?id=' . (int) $p['id'])) . '" target="_blank" rel="noopener">'
                . '<i class="bi bi-receipt" aria-hidden="true"></i> ' . e(payment_receipt_number((int) $p['id'])) . '</a>';
        }
        if ($voidable && !$voided) {
            echo '<form method="post" class="payment-void-form" data-disable-on-submit>';
            echo csrf_field();
            echo '<input type="hidden" name="action" value="void_payment">';
            echo '<input type="hidden" name="payment_id" value="' . (int) $p['id'] . '">';
            echo '<input type="text" class="form-control form-control-sm" name="void_reason" required maxlength="255" placeholder="Void reason" aria-label="Void reason">';
            echo '<button type="submit" class="btn btn-sm btn-outline-secondary">Void</button>';
            echo '</form>';
        }
        echo '</div>';
        echo '</li>';
    }
    echo '</ul>';
}

/**
 * Print-ready official receipt (reuses the quotation print layout).
 *
 * @param array<string,mixed> $ticket   ticket row with device + customer name fields
 * @param array<string,mixed> $payment  row from get_payment()
 */
function render_payment_receipt(array $ticket, array $payment, array $billing): void
{
    $shop = quotation_shop_profile();
    $customerName = trim((string) ($ticket['customer_first_name'] ?? '') . ' ' . (string) ($ticket['customer_last_name'] ?? ''));
    $deviceLabel = trim((string) ($ticket['brand'] ?? '') . ' ' . (string) ($ticket['model'] ?? ''));
    $voided = $payment['voided_at'] !== null;

    echo '<article class="quote-print-doc">';
    echo '<header class="quote-print-header">';
    echo '<div class="quote-print-brand">';
    echo '<img class="quote-print-logo" src="' . e(asset('images/rapid.png')) . '" alt="" width="72" height="72">';
    echo '<div>';
    echo '<p class="quote-print-kicker">' . e(APP_FULL_NAME) . '</p>';
    echo '<h1 class="quote-print-title">' . e(APP_NAME) . '</h1>';
    echo '<p class="quote-print-subtitle">Official Receipt' . ($voided ? ' — VOID' : '') . '</p>';
    echo '</div></div>';
    echo '<div class="quote-print-shop">';
    echo '<strong>' . e($shop['shop_name']) . '</strong>';
    echo '<span>' . e($shop['shop_address']) . '</span>';
    echo '<span>' . e($shop['shop_phone']) . ' · ' . e($shop['shop_email']) . '</span>';
    echo '</div></header>';

    echo '<section class="quote-print-meta">';
    echo '<div><span class="label">Receipt no.</span><strong>' . e(payment_receipt_number((int) $payment['id'])) . '</strong></div>';
    echo '<div><span class="label">Ticket</span><strong>' . e((string) $payment['ticket_number']) . '</strong></div>';
    echo '<div><span class="label">Date</span><strong>' . e(format_datetime((string) $payment['created_at'])) . '</strong></div>';
    echo '<div><span class="label">Method</span><strong>' . e(payment_method_label((string) $payment['method'])) . '</strong></div>';
    echo '</section>';

    echo '<section class="quote-print-parties">';
    echo '<div><span class="label">Received from</span><strong>' . e($customerName !== '' ? $customerName : '—') . '</strong></div>';
    echo '<div><span class="label">Device</span><strong>' . e($deviceLabel !== '' ? $deviceLabel : '—') . '</strong></div>';
    echo '</section>';

    echo '<section class="quote-print-block">';
    echo '<h2>Payment</h2>';
    echo '<table class="quote-print-table"><tbody>';
    echo '<tr><td>Repair amount due</td><td class="num">' . e(money_php($billing['due'])) . '</td></tr>';
    echo '<tr><td>This payment' . ($payment['reference_no'] ? ' (Ref ' . e((string) $payment['reference_no']) . ')' : '') . '</td><td class="num">' . e(money_php((float) $payment['amount'])) . '</td></tr>';
    echo '<tr><td>Total paid to date</td><td class="num">' . e(money_php($billing['paid'])) . '</td></tr>';
    echo '<tr class="total"><td>Remaining balance</td><td class="num">' . e(money_php($billing['balance'])) . '</td></tr>';
    echo '</tbody></table>';
    echo '</section>';

    if ($voided) {
        echo '<section class="quote-print-block"><h2>Voided</h2><p>' . e((string) $payment['void_reason']) . '</p></section>';
    }

    echo '<footer class="quote-print-footer">';
    echo '<p>Amounts are in Philippine pesos (₱). Received by ' . e(trim((string) ($payment['received_by_name'] ?? '')) ?: $shop['shop_name']) . '.</p>';
    echo '<p class="quote-print-sig">Authorized signature: ____________________________</p>';
    echo '</footer>';
    echo '</article>';
}
