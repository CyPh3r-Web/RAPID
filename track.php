<?php
/**
 * Public repair ticket tracking (limited fields, no PII)
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

$pageTitle = 'Track Repair';
$bodyClass = 'track-page';
$navVariant = 'public';

$ticketInput = trim((string) ($_GET['ticket'] ?? ''));
$contactInput = trim((string) ($_GET['contact'] ?? ''));
$ticket = null;
$history = [];
$notFound = false;
$dbError = '';
$lookupAttempted = $ticketInput !== '' || $contactInput !== '';

if ($lookupAttempted) {
    $ticketInput = strtoupper($ticketInput);

    if ($ticketInput === '' || $contactInput === '') {
        $notFound = true;
    } elseif (!preg_match('/^RPR-\d{4}-\d{6}$/', $ticketInput)) {
        $notFound = true;
    } else {
        try {
            $stmt = db()->prepare(
                'SELECT rt.ticket_number, rt.current_status, rt.received_at, rt.estimated_completion,
                        rt.created_at, d.device_type, d.brand, d.model,
                        cu.email AS customer_email, cu.phone AS customer_phone
                 FROM repair_tickets rt
                 INNER JOIN devices d ON d.id = rt.device_id
                 INNER JOIN customers c ON c.id = rt.customer_id
                 INNER JOIN users cu ON cu.id = c.user_id
                 WHERE rt.ticket_number = ?
                 LIMIT 1'
            );
            $stmt->execute([$ticketInput]);
            $row = $stmt->fetch();

            if (!$row || !contacts_match($contactInput, (string) $row['customer_email'], (string) ($row['customer_phone'] ?? ''))) {
                $notFound = true;
            } else {
                unset($row['customer_email'], $row['customer_phone']);
                $ticket = $row;
                $h = db()->prepare(
                    'SELECT status, remarks, created_at
                     FROM repair_status_history
                     WHERE ticket_id = (
                        SELECT id FROM repair_tickets WHERE ticket_number = ? LIMIT 1
                     )
                     ORDER BY created_at ASC, id ASC'
                );
                $h->execute([$ticketInput]);
                $history = $h->fetchAll();
            }
        } catch (Throwable $e) {
            $dbError = friendly_error($e, 'Unable to look up that ticket right now. Please try again.');
        }
    }
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<section class="section-block">
    <div class="container-rapid max-w-[820px]">
        <h2 class="mb-1">Track your repair</h2>
        <p class="section-lead">Enter your ticket number and the phone or email used on the booking.</p>

        <div class="rapid-card mb-4">
            <form method="get" action="" data-disable-on-submit class="grid grid-cols-1 md:grid-cols-12 gap-2 items-end">
                <div class="md:col-span-5">
                    <label class="form-label" for="ticket">Ticket number</label>
                    <input type="text" class="form-control" id="ticket" name="ticket"
                           value="<?= e($ticketInput) ?>"
                           placeholder="RPR-2026-000001" required
                           pattern="RPR-\d{4}-\d{6}" autocomplete="off">
                </div>
                <div class="md:col-span-4">
                    <label class="form-label" for="contact">Phone or email</label>
                    <input type="text" class="form-control" id="contact" name="contact"
                           value="<?= e($contactInput) ?>"
                           placeholder="09••• or email" required autocomplete="off">
                </div>
                <div class="md:col-span-3">
                    <button type="submit" class="btn btn-rapid-primary w-full">Track ticket</button>
                </div>
            </form>
        </div>

        <?php if ($dbError !== ''): ?>
            <?php render_error_state($dbError, 'Lookup unavailable'); ?>
        <?php elseif ($lookupAttempted && $notFound): ?>
            <div class="rapid-card">
                <?php render_empty_state('Ticket not found', 'Check the ticket number and the phone or email on the booking. Format: RPR-YYYY-000001', null, null, 'bi-ticket-perforated'); ?>
            </div>
        <?php elseif ($ticket): ?>
            <?php
            $isReady = $ticket['current_status'] === 'ready_for_pickup';
            $isDone = $ticket['current_status'] === 'completed';
            ?>
            <div class="rapid-card mb-3">
                <?php render_ticket_id_block($ticket['ticket_number']); ?>
                <div class="mt-3"><?php render_status_badge($ticket['current_status']); ?></div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-1 mb-3">
                    <div>
                        <div class="text-rapid-muted text-sm">Device</div>
                        <div class="font-semibold">
                            <?= e($ticket['brand'] . ' ' . $ticket['model']) ?>
                            <span class="text-rapid-muted font-normal">(<?= e($ticket['device_type']) ?>)</span>
                        </div>
                    </div>
                    <div>
                        <div class="text-rapid-muted text-sm">Date received</div>
                        <div class="font-semibold"><?= e(format_datetime($ticket['received_at'])) ?></div>
                    </div>
                    <div>
                        <div class="text-rapid-muted text-sm">Estimated completion</div>
                        <div class="font-semibold"><?= e(format_datetime($ticket['estimated_completion'])) ?></div>
                    </div>
                    <div>
                        <div class="text-rapid-muted text-sm">Ready for pickup</div>
                        <div class="font-semibold">
                            <?php if ($isReady || $isDone): ?>
                                <span class="badge-status badge-status-success"><i class="bi bi-check-circle-fill" aria-hidden="true"></i> Yes<?= $isDone ? ' · completed' : '' ?></span>
                            <?php else: ?>
                                <span class="badge-status badge-status-muted"><i class="bi bi-clock" aria-hidden="true"></i> Not yet</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <?php if ($isReady): ?>
                    <div class="alert alert-success py-2 mb-0 text-sm">
                        Your device is ready for pickup at <?= e(SHOP_NAME) ?>.
                    </div>
                <?php endif; ?>
            </div>

            <div class="rapid-card">
                <h3 class="text-base font-semibold text-rapid mb-3">Repair progress</h3>
                <?php render_public_stepper($ticket['current_status'], $history, $ticket['estimated_completion'] ?? null); ?>
                <?php render_notify_channels(); ?>
            </div>
        <?php else: ?>
            <div class="rapid-card">
                <?php render_empty_state('Look up a repair', 'Enter a ticket number and phone or email to view public progress.', null, null, 'bi-search'); ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
