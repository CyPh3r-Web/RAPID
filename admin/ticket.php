<?php
/**
 * Admin — ticket detail: assign technician, update status, view media/quote
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$user = current_user();
$ticketId = (int) ($_GET['id'] ?? 0);
$ticket = $ticketId > 0 ? get_ticket_full($ticketId) : null;

if (!$ticket) {
    flash_set('error', 'Ticket not found.');
    redirect('admin/tickets.php');
}

$technicians = list_active_technicians();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $action = $_POST['action'] ?? '';

    if ($action === 'assign') {
        $techId = (int) ($_POST['technician_id'] ?? 0);
        $markReceived = !empty($_POST['mark_received']);
        if ($techId <= 0) {
            $error = 'Select a technician.';
        } else {
            $result = assign_technician_to_ticket($ticketId, $techId, (int) $user['id'], $markReceived);
            if ($result['ok']) {
                flash_set('success', 'Technician assigned successfully.');
                redirect('admin/ticket.php?id=' . $ticketId);
            }
            $error = $result['error'] ?? 'Assignment failed.';
        }
    } elseif ($action === 'status') {
        $newStatus = (string) ($_POST['new_status'] ?? '');
        $remarks = trim((string) ($_POST['remarks'] ?? ''));
        $result = update_ticket_status($ticketId, $newStatus, (int) $user['id'], $remarks);
        if ($result['ok']) {
            flash_set('success', 'Status updated to ' . status_label($newStatus) . '.');
            redirect('admin/ticket.php?id=' . $ticketId);
        }
        $error = $result['error'] ?? 'Status update failed.';
    }

    $ticket = get_ticket_full($ticketId);
}

$history = db()->prepare('SELECT status, remarks, created_at FROM repair_status_history WHERE ticket_id = ? ORDER BY created_at ASC, id ASC');
$history->execute([$ticketId]);
$historyRows = $history->fetchAll();

$media = db()->prepare('SELECT * FROM device_media WHERE ticket_id = ? ORDER BY uploaded_at ASC');
$media->execute([$ticketId]);
$mediaRows = $media->fetchAll();

$diagnosis = db()->prepare('SELECT * FROM diagnoses WHERE ticket_id = ? ORDER BY id DESC LIMIT 1');
$diagnosis->execute([$ticketId]);
$diagnosisRow = $diagnosis->fetch() ?: null;

$quotation = db()->prepare('SELECT * FROM quotations WHERE ticket_id = ? ORDER BY id DESC LIMIT 1');
$quotation->execute([$ticketId]);
$quotationRow = $quotation->fetch() ?: null;

$nextStatuses = allowed_status_transitions($ticket['current_status']);

$pageTitle = $ticket['ticket_number'];
$showSidebar = true;
$navVariant = 'app';
$activeNav = 'tickets';
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
                <p><?= e($ticket['brand'] . ' ' . $ticket['model']) ?> · <?= e($ticket['customer_first_name'] . ' ' . $ticket['customer_last_name']) ?></p>
            </div>
            <div class="flex flex-wrap gap-2 items-center">
                <span class="badge-status <?= e(status_badge_class($ticket['current_status'])) ?>"><?= e(status_label($ticket['current_status'])) ?></span>
                <a class="btn btn-rapid-outline btn-sm" href="<?= e(url('admin/tickets.php')) ?>">Back</a>
            </div>
        </div>

        <?php if ($error !== ''): ?>
            <div class="alert alert-danger"><?= e($error) ?></div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-3">
            <div class="lg:col-span-7">
                <div class="rapid-card mb-3">
                    <h2 class="text-sm font-semibold text-rapid mb-3">Ticket &amp; customer</h2>
                    <dl class="detail-dl grid grid-cols-1 sm:grid-cols-[9rem_1fr] gap-x-4 mb-0">
                        <dt>Problem</dt>
                        <dd><?= nl2br(e($ticket['problem_description'])) ?></dd>
                        <dt>Customer</dt>
                        <dd><?= e($ticket['customer_first_name'] . ' ' . $ticket['customer_last_name']) ?></dd>
                        <dt>Phone / email</dt>
                        <dd><?= e($ticket['customer_phone'] ?: '—') ?> · <?= e($ticket['customer_email']) ?></dd>
                        <dt>Priority</dt>
                        <dd class="capitalize"><?= e($ticket['priority']) ?></dd>
                        <dt>Appointment</dt>
                        <dd><?= e(format_datetime($ticket['appointment_date'])) ?></dd>
                        <dt>Technician</dt>
                        <dd class="mb-0"><?= e(trim((string) $ticket['technician_name']) !== '' ? $ticket['technician_name'] : 'Unassigned') ?></dd>
                    </dl>
                </div>

                <div class="rapid-card mb-3">
                    <h2 class="text-sm font-semibold text-rapid mb-3">Before / after</h2>
                    <?php if (!$mediaRows): ?>
                        <?php render_empty_state('No media uploaded', 'Before-repair photos appear here after booking.', null, null, 'bi-camera'); ?>
                    <?php else: ?>
                        <?php render_before_after_media($mediaRows); ?>
                    <?php endif; ?>
                </div>

                <?php if ($diagnosisRow): ?>
                    <div class="rapid-card mb-3">
                        <h2 class="text-sm font-semibold text-rapid mb-2">Latest diagnosis</h2>
                        <p class="mb-1"><?= nl2br(e($diagnosisRow['diagnosis'])) ?></p>
                        <?php if ($diagnosisRow['recommended_action']): ?>
                            <p class="text-sm mb-0"><strong>Recommended:</strong> <?= nl2br(e($diagnosisRow['recommended_action'])) ?></p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if ($quotationRow): ?>
                    <div class="rapid-card mb-3">
                        <?php render_quotation_card($quotationRow, false); ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="lg:col-span-5">
                <div class="rapid-card mb-3">
                    <h2 class="text-sm font-semibold text-rapid mb-3">Assign technician</h2>
                    <form method="post" data-disable-on-submit>
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="assign">
                        <div class="mb-3">
                            <label class="form-label" for="technician_id">Technician</label>
                            <select class="form-select" id="technician_id" name="technician_id" required>
                                <option value="">Select…</option>
                                <?php foreach ($technicians as $tech): ?>
                                    <option value="<?= (int) $tech['id'] ?>" <?= (int) $ticket['assigned_technician_id'] === (int) $tech['id'] ? 'selected' : '' ?>>
                                        <?= e($tech['first_name'] . ' ' . $tech['last_name']) ?>
                                        <?= $tech['specialization'] ? ' — ' . e($tech['specialization']) : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php if ($ticket['current_status'] === 'booking_submitted'): ?>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" value="1" id="mark_received" name="mark_received" checked>
                                <label class="text-sm" for="mark_received">Also mark as Received</label>
                            </div>
                        <?php endif; ?>
                        <button type="submit" class="btn btn-rapid-primary w-full">Save assignment</button>
                    </form>
                </div>

                <div class="rapid-card mb-3">
                    <h2 class="text-sm font-semibold text-rapid mb-3">Update status</h2>
                    <?php if (!$nextStatuses): ?>
                        <p class="text-rapid-muted text-sm mb-0">No further status transitions from <?= e(status_label($ticket['current_status'])) ?>.</p>
                    <?php else: ?>
                        <form method="post" data-disable-on-submit>
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="status">
                            <div class="mb-3">
                                <label class="form-label" for="new_status">New status</label>
                                <select class="form-select" id="new_status" name="new_status" required>
                                    <?php foreach ($nextStatuses as $ns): ?>
                                        <option value="<?= e($ns) ?>"><?= e(status_label($ns)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="remarks">Remarks</label>
                                <textarea class="form-control" id="remarks" name="remarks" rows="2" placeholder="Optional note…"></textarea>
                            </div>
                            <button type="submit" class="btn btn-rapid-outline w-full">Update status</button>
                        </form>
                    <?php endif; ?>
                </div>

                <div class="rapid-card">
                    <h2 class="text-sm font-semibold text-rapid mb-3">Status history</h2>
                    <ul class="status-timeline">
                        <?php foreach ($historyRows as $h): ?>
                            <li class="done">
                                <span class="dot"></span>
                                <div class="tl-title"><?= e(status_label($h['status'])) ?></div>
                                <div class="tl-meta"><?= e(format_datetime($h['created_at'])) ?><?= $h['remarks'] ? ' · ' . e($h['remarks']) : '' ?></div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
