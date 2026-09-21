<?php
/**
 * Customer — ticket details (ownership enforced)
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_role('customer');

$user = current_user();
$customerId = get_customer_id_for_user((int) $user['id']);

if (!$customerId) {
    flash_set('error', 'Customer profile not found.');
    redirect('auth/logout.php');
}

$ticketId = (int) ($_GET['id'] ?? 0);
$ticket = $ticketId > 0 ? get_customer_ticket($ticketId, $customerId) : null;

if (!$ticket) {
    flash_set('error', 'Ticket not found or you do not have access.');
    redirect('customer/repairs.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $action = $_POST['action'] ?? '';
    if ($action === 'quotation_response') {
        $decision = (string) ($_POST['decision'] ?? '');
        $note = trim((string) ($_POST['decline_note'] ?? ''));
        $result = respond_to_quotation($ticketId, $customerId, (int) $user['id'], $decision, $note);
        if ($result['ok']) {
            flash_set(
                'success',
                $decision === 'approved'
                    ? 'Quotation approved. Repair can proceed.'
                    : 'Quotation declined. The shop may issue a new quote.'
            );
            redirect('customer/ticket.php?id=' . $ticketId);
        }
        flash_set('error', $result['error'] ?? 'Could not process your response.');
        redirect('customer/ticket.php?id=' . $ticketId);
    }
}

$history = db()->prepare(
    'SELECT status, remarks, created_at FROM repair_status_history
     WHERE ticket_id = ? ORDER BY created_at ASC, id ASC'
);
$history->execute([$ticketId]);
$historyRows = $history->fetchAll();

$media = db()->prepare(
    'SELECT file_name, file_path, file_type, media_category, uploaded_at
     FROM device_media WHERE ticket_id = ? ORDER BY uploaded_at ASC, id ASC'
);
$media->execute([$ticketId]);
$mediaRows = $media->fetchAll();

$diagnosis = db()->prepare(
    'SELECT diagnosis, recommended_action, estimated_completion, created_at
     FROM diagnoses WHERE ticket_id = ? ORDER BY id DESC LIMIT 1'
);
$diagnosis->execute([$ticketId]);
$diagnosisRow = $diagnosis->fetch() ?: null;

$quotation = db()->prepare(
    'SELECT labor_cost, parts_cost, other_cost, total_amount, notes, status, valid_until, created_at
     FROM quotations WHERE ticket_id = ? ORDER BY id DESC LIMIT 1'
);
$quotation->execute([$ticketId]);
$quotationRow = $quotation->fetch() ?: null;

$workflowOrder = [
    'booking_submitted',
    'received',
    'diagnosing',
    'quotation_pending',
    'awaiting_approval',
    'approved',
    'repairing',
    'ready_for_pickup',
    'completed',
];

$reached = [];
foreach ($historyRows as $row) {
    $reached[$row['status']] = $row;
}

$pageTitle = $ticket['ticket_number'];
$showSidebar = true;
$navVariant = 'app';
$activeNav = 'repairs';
$bodyClass = 'app-body';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="app-shell">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="app-main">
        <div class="page-header flex flex-wrap justify-between items-start gap-2">
            <div>
                <?php render_ticket_number($ticket['ticket_number'], null, ['tag' => 'h1', 'heading' => true]); ?>
                <p>
                    <?= e($ticket['brand'] . ' ' . $ticket['model']) ?>
                    · <?= e($ticket['device_type']) ?>
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <?php render_status_badge($ticket['current_status']); ?>
                <a class="btn btn-rapid-outline btn-sm" href="<?= e(url('customer/repairs.php')) ?>">Back to list</a>
            </div>
        </div>

        <?php if ($ticket['current_status'] === 'awaiting_approval' && $quotationRow && ($quotationRow['status'] ?? '') === 'pending'): ?>
            <div class="quote-banner" role="status">
                <span><i class="bi bi-hourglass-split" aria-hidden="true"></i> Quotation awaiting your approval · <?= e($ticket['ticket_number']) ?></span>
                <a class="btn btn-sm btn-rapid-outline" href="#quotation">Review quote</a>
            </div>
        <?php endif; ?>

        <?php
        $claimOriginal = warranty_claim_original_from_problem((string) $ticket['problem_description']);
        if ($claimOriginal):
        ?>
            <div class="quote-banner quote-banner-info" role="status">
                <span class="warranty-flag"><i class="bi bi-shield-exclamation" aria-hidden="true"></i> Warranty claim linked to <?php render_ticket_number($claimOriginal); ?></span>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-3">
            <div class="lg:col-span-7">
                <div class="rapid-card mb-3">
                    <?php render_ticket_id_block($ticket['ticket_number']); ?>
                </div>

                <div class="rapid-card mb-3">
                    <h2 class="text-sm font-semibold text-rapid mb-3">Ticket details</h2>
                    <dl class="detail-dl mb-0 grid grid-cols-1 sm:grid-cols-[9rem_1fr] gap-x-4">
                        <dt>Problem</dt>
                        <dd><?= nl2br(e($ticket['problem_description'])) ?></dd>

                        <dt>Priority</dt>
                        <dd class="capitalize"><?= e($ticket['priority']) ?></dd>

                        <dt>Appointment</dt>
                        <dd><?= e(format_datetime($ticket['appointment_date'])) ?></dd>

                        <dt>Technician</dt>
                        <dd><?= e($ticket['technician_name'] ?: 'Not assigned yet') ?></dd>

                        <dt>Received</dt>
                        <dd><?= e(format_datetime($ticket['received_at'])) ?></dd>

                        <dt>Created</dt>
                        <dd class="mb-0"><?= e(format_datetime($ticket['created_at'])) ?></dd>
                    </dl>
                </div>

                <div class="rapid-card mb-3">
                    <h2 class="text-sm font-semibold text-rapid mb-3">Device</h2>
                    <dl class="detail-dl mb-0 grid grid-cols-1 sm:grid-cols-[9rem_1fr] gap-x-4">
                        <dt>Brand / model</dt>
                        <dd><?= e($ticket['brand'] . ' ' . $ticket['model']) ?></dd>
                        <dt>Color</dt>
                        <dd><?= e($ticket['color'] ?: '—') ?></dd>
                        <dt>Serial</dt>
                        <dd><?= e($ticket['serial_number'] ?: '—') ?></dd>
                        <dt>IMEI</dt>
                        <dd><?= e($ticket['imei'] ?: '—') ?></dd>
                        <dt>Accessories</dt>
                        <dd><?= e($ticket['accessories'] ?: '—') ?></dd>
                        <dt>Condition</dt>
                        <dd class="mb-0"><?= e($ticket['physical_condition'] ?: '—') ?></dd>
                    </dl>
                </div>

                <div class="rapid-card mb-3">
                    <h2 class="text-sm font-semibold text-rapid mb-3">Before repair media</h2>
                    <?php
                    $before = array_filter($mediaRows, function ($m) {
                        return $m['media_category'] === 'before_repair';
                    });
                    ?>
                    <?php if (!$before): ?>
                        <?php render_empty_state('No before-repair photos', 'You can still track this ticket. Photos were not attached at booking.', null, null, 'bi-camera'); ?>
                    <?php else: ?>
                        <div class="media-grid">
                            <?php foreach ($before as $m):
                                $parsed = media_angle_from_name($m['file_name']);
                            ?>
                                <div class="media-item">
                                    <?php if ($parsed['angle']): ?>
                                        <span class="media-angle-tag"><?= e(media_angle_label($parsed['angle'])) ?></span>
                                    <?php endif; ?>
                                    <?php if (is_image_mime($m['file_type'])): ?>
                                        <a href="<?= e(media_public_url($m['file_path'])) ?>" target="_blank" rel="noopener">
                                            <img src="<?= e(media_public_url($m['file_path'])) ?>" alt="<?= e($parsed['name']) ?>">
                                        </a>
                                    <?php elseif (is_video_mime($m['file_type'])): ?>
                                        <video controls preload="metadata" src="<?= e(media_public_url($m['file_path'])) ?>"></video>
                                    <?php else: ?>
                                        <a href="<?= e(media_public_url($m['file_path'])) ?>" target="_blank" rel="noopener"><?= e($parsed['name']) ?></a>
                                    <?php endif; ?>
                                    <div class="media-caption"><?= e($parsed['name']) ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($diagnosisRow): ?>
                    <div class="rapid-card mb-3">
                        <h2 class="text-sm font-semibold text-rapid mb-3">Diagnosis</h2>
                        <p class="mb-2"><?= nl2br(e($diagnosisRow['diagnosis'])) ?></p>
                        <?php if ($diagnosisRow['recommended_action']): ?>
                            <p class="mb-0 text-sm"><strong>Recommended:</strong> <?= nl2br(e($diagnosisRow['recommended_action'])) ?></p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if ($quotationRow): ?>
                    <div class="rapid-card mb-3" id="quotation">
                        <?php render_quotation_card($quotationRow, $ticket['current_status'] === 'awaiting_approval' && ($quotationRow['status'] ?? '') === 'pending'); ?>
                        <?php render_notify_channels('You will be notified in-app, by email, and by SMS when the quote status changes.'); ?>
                    </div>
                <?php endif; ?>

                <?php
                $warrantyLive = get_warranty_for_ticket($ticketId);
                ?>
                <?php if ($warrantyLive): ?>
                    <div class="rapid-card mb-3">
                        <h2 class="text-sm font-semibold text-rapid mb-3">Warranty</h2>
                        <?php
                        render_warranty_card(
                            $warrantyLive,
                            $warrantyLive['is_claimable'] ? url('customer/claim_file.php?ticket_id=' . $ticketId) : null,
                            $ticket['ticket_number']
                        );
                        ?>
                    </div>
                <?php elseif ($ticket['current_status'] === 'completed'): ?>
                    <div class="rapid-card mb-3">
                        <h2 class="text-sm font-semibold text-rapid mb-2">Warranty</h2>
                        <?php render_loading_state('Warranty record is being prepared…'); ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="lg:col-span-5">
                <div class="rapid-card">
                    <h2 class="text-sm font-semibold text-rapid mb-3">Progress tracker</h2>
                    <?php render_public_stepper($ticket['current_status'], $historyRows, $ticket['estimated_completion'] ?? null); ?>
                    <?php render_notify_channels(); ?>
                </div>
            </div>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
