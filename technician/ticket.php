<?php
/**
 * Technician — manage assigned ticket (diagnose, quote, status, media)
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_role('technician');

$user = current_user();
$technicianId = get_technician_id_for_user((int) $user['id']);

if (!$technicianId) {
    flash_set('error', 'Technician profile not found.');
    redirect('auth/logout.php');
}

$ticketId = (int) ($_GET['id'] ?? 0);
$ticket = $ticketId > 0 ? get_ticket_full($ticketId) : null;

if (!$ticket || (int) ($ticket['assigned_technician_id'] ?? 0) !== $technicianId) {
    flash_set('error', 'Ticket not found or not assigned to you.');
    redirect('technician/tickets.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $action = $_POST['action'] ?? '';

    if ($action === 'diagnose') {
        $estDate = trim((string) ($_POST['estimated_date'] ?? ''));
        $estTime = trim((string) ($_POST['estimated_time'] ?? '17:00'));
        $estimated = null;
        if ($estDate !== '') {
            $ts = strtotime($estDate . ' ' . ($estTime !== '' ? $estTime : '17:00') . ':00');
            $estimated = $ts ? date('Y-m-d H:i:s', $ts) : null;
        }
        $result = save_diagnosis(
            $ticketId,
            $technicianId,
            (int) $user['id'],
            (string) ($_POST['diagnosis'] ?? ''),
            (string) ($_POST['recommended_action'] ?? ''),
            $estimated
        );
        if ($result['ok']) {
            flash_set('success', 'Diagnosis saved.');
            redirect('technician/ticket.php?id=' . $ticketId . '&process=1');
        }
        $error = $result['error'] ?? 'Could not save diagnosis.';
    } elseif ($action === 'quotation') {
        $labor = (float) ($_POST['labor_cost'] ?? 0);
        $parts = (float) ($_POST['parts_cost'] ?? 0);
        $other = (float) ($_POST['other_cost'] ?? 0);
        $validUntil = trim((string) ($_POST['valid_until'] ?? ''));
        $validUntil = $validUntil !== '' ? $validUntil : null;
        $result = create_quotation(
            $ticketId,
            $technicianId,
            (int) $user['id'],
            $labor,
            $parts,
            $other,
            (string) ($_POST['notes'] ?? ''),
            $validUntil
        );
        if ($result['ok']) {
            flash_set('success', 'Quotation sent to the customer for approval.');
            redirect('technician/ticket.php?id=' . $ticketId . '&process=1');
        }
        $error = $result['error'] ?? 'Could not create quotation.';
    } elseif ($action === 'status') {
        $result = update_ticket_status(
            $ticketId,
            (string) ($_POST['new_status'] ?? ''),
            (int) $user['id'],
            trim((string) ($_POST['remarks'] ?? ''))
        );
        if ($result['ok']) {
            flash_set('success', 'Status updated.');
            redirect('technician/ticket.php?id=' . $ticketId);
        }
        $error = $result['error'] ?? 'Status update failed.';
    } elseif ($action === 'media') {
        $category = (string) ($_POST['media_category'] ?? 'during_repair');
        $allowedCat = ['diagnosis', 'during_repair', 'after_repair'];
        if (!in_array($category, $allowedCat, true)) {
            $category = 'during_repair';
        }
        $upload = store_uploaded_media($_FILES['media'] ?? [], 'repairs', 6);
        if (!$upload['ok']) {
            $error = $upload['error'];
        } elseif (empty($upload['files'])) {
            $error = 'Select at least one file to upload.';
        } else {
            $stmt = db()->prepare(
                'INSERT INTO device_media (ticket_id, uploaded_by, file_name, file_path, file_type, media_category)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            foreach ($upload['files'] as $f) {
                $stmt->execute([
                    $ticketId,
                    (int) $user['id'],
                    $f['file_name'],
                    $f['file_path'],
                    $f['file_type'],
                    $category,
                ]);
            }
            flash_set('success', 'Media uploaded.');
            redirect('technician/ticket.php?id=' . $ticketId);
        }
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

// Technician-facing transitions (exclude customer-only approve/decline from UI prompts where awkward)
$nextStatuses = allowed_status_transitions($ticket['current_status']);
$techPreferred = array_values(array_filter($nextStatuses, function ($s) {
    return !in_array($s, ['approved', 'declined'], true);
}));

$canDiagnose = in_array($ticket['current_status'], ['received', 'diagnosing', 'quotation_pending', 'declined'], true);
$canQuote = in_array($ticket['current_status'], ['diagnosing', 'quotation_pending', 'declined', 'awaiting_approval'], true);
$canProcess = $canDiagnose || $canQuote || $techPreferred;

$processSteps = [
    ['id' => 'diagnose', 'label' => 'Diagnosis'],
    ['id' => 'quote', 'label' => 'Quotation'],
    ['id' => 'status', 'label' => 'Status'],
];
$processStepIds = array_column($processSteps, 'id');

$postedAction = $_SERVER['REQUEST_METHOD'] === 'POST' ? (string) ($_POST['action'] ?? '') : '';
$startStep = (string) ($_GET['step'] ?? '');
if ($error !== '' && in_array($postedAction, ['diagnose', 'quotation', 'status'], true)) {
    $startStep = $postedAction === 'quotation' ? 'quote' : $postedAction;
}
if ($startStep === '' || !in_array($startStep, $processStepIds, true)) {
    $quoteNeedsWork = $canQuote && (!$quotationRow || in_array((string) ($quotationRow['status'] ?? ''), ['declined', 'expired'], true));
    if ($canDiagnose && !$diagnosisRow) {
        $startStep = 'diagnose';
    } elseif ($quoteNeedsWork) {
        $startStep = 'quote';
    } else {
        $startStep = in_array('status', $processStepIds, true) ? 'status' : ($processStepIds[0] ?? 'diagnose');
    }
}

$openProcess = $canProcess && (
    isset($_GET['process'])
    || ($error !== '' && in_array($postedAction, ['diagnose', 'quotation', 'status'], true))
);

$diagnosisValue = $postedAction === 'diagnose'
    ? (string) ($_POST['diagnosis'] ?? '')
    : (string) ($diagnosisRow['diagnosis'] ?? '');
$recommendedValue = $postedAction === 'diagnose'
    ? (string) ($_POST['recommended_action'] ?? '')
    : (string) ($diagnosisRow['recommended_action'] ?? '');
$estDate = $postedAction === 'diagnose' ? trim((string) ($_POST['estimated_date'] ?? '')) : '';
$estTime = $postedAction === 'diagnose' ? trim((string) ($_POST['estimated_time'] ?? '17:00')) : '17:00';
if ($postedAction !== 'diagnose' && $diagnosisRow && !empty($diagnosisRow['estimated_completion'])) {
    $estTs = strtotime((string) $diagnosisRow['estimated_completion']);
    if ($estTs) {
        $estDate = date('Y-m-d', $estTs);
        $estTime = date('H:i', $estTs);
    }
}
if ($estTime === '') {
    $estTime = '17:00';
}

$laborCost = $postedAction === 'quotation' ? (string) ($_POST['labor_cost'] ?? '0') : (string) ($quotationRow['labor_cost'] ?? '0');
$partsCost = $postedAction === 'quotation' ? (string) ($_POST['parts_cost'] ?? '0') : (string) ($quotationRow['parts_cost'] ?? '0');
$otherCost = $postedAction === 'quotation' ? (string) ($_POST['other_cost'] ?? '0') : (string) ($quotationRow['other_cost'] ?? '0');
$quoteNotes = $postedAction === 'quotation' ? (string) ($_POST['notes'] ?? '') : (string) ($quotationRow['notes'] ?? '');
$validUntil = $postedAction === 'quotation'
    ? trim((string) ($_POST['valid_until'] ?? ''))
    : (string) ($quotationRow['valid_until'] ?? '');
if ($validUntil === '') {
    $validUntil = date('Y-m-d', strtotime('+7 days'));
} else {
    $validTs = strtotime($validUntil);
    $validUntil = $validTs ? date('Y-m-d', $validTs) : date('Y-m-d', strtotime('+7 days'));
}
$remarksValue = $postedAction === 'status' ? (string) ($_POST['remarks'] ?? '') : '';

ai_ensure_schema();
$aiConfigured = ai_is_configured();
$aiCached = ai_get_cached_suggestion($ticketId, AI_KIND_TECHNICIAN_REPAIR);
$aiHash = ai_ticket_context_hash($ticket, $mediaRows, $diagnosisRow);
$aiStale = $aiCached && !hash_equals((string) $aiCached['context_hash'], $aiHash);

$pageTitle = $ticket['ticket_number'];
$showSidebar = true;
$navVariant = 'app';
$activeNav = 'assigned';
$bodyClass = 'app-body' . ($openProcess ? ' process-modal-open' : '');
$pageScripts = ['js/ai-assist.js'];

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
                <?php if ($canProcess): ?>
                    <button type="button" class="btn btn-rapid-primary btn-sm" data-process-open>
                        <i class="bi bi-list-check" aria-hidden="true"></i> Process repair
                    </button>
                <?php endif; ?>
                <a class="btn btn-rapid-outline btn-sm" href="<?= e(url('technician/tickets.php')) ?>">Back</a>
            </div>
        </div>

        <?php if ($error !== ''): ?>
            <div class="alert alert-danger"><?= e($error) ?></div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-3">
            <div class="lg:col-span-7">
                <div class="rapid-card mb-3">
                    <h2 class="text-sm font-semibold text-rapid mb-3">Job details</h2>
                    <dl class="detail-dl grid grid-cols-1 sm:grid-cols-[9rem_1fr] gap-x-4 mb-0">
                        <dt>Problem</dt>
                        <dd><?= nl2br(e($ticket['problem_description'])) ?></dd>
                        <dt>Customer phone</dt>
                        <dd><?= e($ticket['customer_phone'] ?: '—') ?></dd>
                        <dt>Priority</dt>
                        <dd class="capitalize"><?= e($ticket['priority']) ?></dd>
                        <dt>Device condition</dt>
                        <dd class="mb-0"><?= e($ticket['physical_condition'] ?: '—') ?></dd>
                    </dl>
                </div>

                <div class="rapid-card mb-3">
                    <h2 class="text-sm font-semibold text-rapid mb-3">Before / after</h2>
                    <?php if (!$mediaRows): ?>
                        <?php render_empty_state('No media uploaded', 'Before-repair photos appear here after booking.', null, null, 'bi-camera'); ?>
                    <?php else: ?>
                        <?php render_before_after_media($mediaRows); ?>
                    <?php endif; ?>
                    <form method="post" enctype="multipart/form-data" data-disable-on-submit class="border-t border-rapid-border pt-3 mt-3">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="media">
                        <div class="grid grid-cols-1 md:grid-cols-12 gap-2 items-end">
                            <div class="md:col-span-4">
                                <label class="form-label" for="media_category">Category</label>
                                <select class="form-select" id="media_category" name="media_category">
                                    <option value="diagnosis">Diagnosis</option>
                                    <option value="during_repair" selected>During repair</option>
                                    <option value="after_repair">After repair</option>
                                </select>
                            </div>
                            <div class="md:col-span-5">
                                <label class="form-label" for="media">Files</label>
                                <input type="file" class="form-control" id="media" name="media[]" multiple
                                       accept=".jpg,.jpeg,.png,.webp,.mp4,.mov,image/*,video/mp4,video/quicktime">
                            </div>
                            <div class="md:col-span-3">
                                <button type="submit" class="btn btn-rapid-outline w-full">Upload</button>
                            </div>
                        </div>
                    </form>
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
                <?php if ($canProcess): ?>
                    <div class="rapid-card mb-3">
                        <h2 class="text-sm font-semibold text-rapid mb-2">Process this repair</h2>
                        <p class="text-sm text-rapid-muted mb-3">Work through the job one step at a time: diagnosis, quotation, then status.</p>
                        <ol class="process-step-list">
                            <?php foreach ($processSteps as $i => $step): ?>
                                <li>
                                    <span class="process-step-num"><?= (int) ($i + 1) ?></span>
                                    <?= e($step['label']) ?>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                        <button type="button" class="btn btn-rapid-primary w-full" data-process-open>
                            Continue
                        </button>
                    </div>
                <?php endif; ?>

                <div class="rapid-card ai-assist-card mb-3"
                     id="aiAssist"
                     data-ticket-id="<?= (int) $ticketId ?>"
                     data-endpoint="<?= e(url('api/ai_suggest.php')) ?>"
                     data-configured="<?= $aiConfigured ? '1' : '0' ?>"
                     data-stale="<?= $aiStale ? '1' : '0' ?>"
                     data-generated-at="<?= e($aiCached['created_at'] ?? '') ?>"
                     <?php if ($aiCached): ?>data-cached="<?= e(json_encode($aiCached['payload'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>"<?php endif; ?>>
                    <div class="flex flex-wrap justify-between items-start gap-2 mb-2">
                        <div>
                            <span class="ai-assist-badge"><i class="bi bi-stars" aria-hidden="true"></i> AI assistant</span>
                            <h2 class="text-sm font-semibold text-rapid mt-2 mb-0">Suggest repair steps</h2>
                        </div>
                        <button type="button" class="btn btn-rapid-primary btn-sm" id="aiSuggestBtn">
                            <?= $aiCached ? 'Refresh suggestion' : 'Suggest repair steps' ?>
                        </button>
                    </div>
                    <p class="text-rapid-muted text-xs mb-2">
                        Built from this device, the reported problem<?= $mediaRows ? ', photos' : '' ?>, and similar RAPID jobs. Verify on the unit before you act.
                    </p>
                    <p class="ai-assist-status text-xs text-rapid-muted mb-2" id="aiAssistStatus"></p>
                    <div class="alert alert-danger ai-assist-error mb-2" id="aiAssistError" hidden></div>
                    <?php if (!$aiConfigured): ?>
                        <p class="text-sm text-rapid-muted mb-0" id="aiAssistEmpty">
                            Ask an admin to paste a free Gemini API key in Settings, then click Suggest repair steps.
                        </p>
                        <div id="aiAssistResult" hidden></div>
                    <?php else: ?>
                        <p class="text-sm text-rapid-muted mb-0" id="aiAssistEmpty" <?= $aiCached ? 'hidden' : '' ?>>
                            Click Suggest repair steps for likely causes, tests, and a diagnosis draft you can edit.
                        </p>
                        <div id="aiAssistResult" <?= $aiCached ? '' : 'hidden' ?>></div>
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

<?php if ($canProcess): ?>
<div class="rapid-modal"
     id="processModal"
     data-process-modal
     data-start-step="<?= e($startStep) ?>"
     data-open="<?= $openProcess ? '1' : '0' ?>"
     <?= $openProcess ? '' : 'hidden' ?>>
    <div class="rapid-modal-backdrop" data-process-close></div>
    <div class="rapid-modal-dialog" data-process-dialog role="dialog" aria-modal="true" aria-labelledby="processModalTitle" tabindex="-1">
        <div class="rapid-modal-header">
            <div>
                <h2 id="processModalTitle" class="text-lg font-semibold text-rapid mb-1">Process repair</h2>
                <p class="text-sm text-rapid-muted mb-0"><?= e($ticket['ticket_number']) ?> · <?= e($ticket['brand'] . ' ' . $ticket['model']) ?></p>
            </div>
            <button type="button" class="btn btn-rapid-outline btn-sm" data-process-close aria-label="Close">
                <i class="bi bi-x-lg" aria-hidden="true"></i>
            </button>
        </div>
        <div class="rapid-modal-body">
            <ol class="wizard-steps" aria-label="Repair process steps">
                <?php foreach ($processSteps as $i => $step): ?>
                    <li class="<?= $step['id'] === $startStep ? 'is-current' : '' ?>" data-process-goto="<?= e($step['id']) ?>">
                        <span class="wiz-num"><?= (int) ($i + 1) ?></span>
                        <span><?= e($step['label']) ?></span>
                    </li>
                <?php endforeach; ?>
            </ol>

            <div class="wizard-panel" data-step="diagnose" <?= $startStep === 'diagnose' ? '' : 'hidden' ?>>
                <h3 class="text-sm font-semibold text-rapid mb-2">Add diagnosis</h3>
                <?php if ($canDiagnose): ?>
                    <p class="text-sm text-rapid-muted mb-3">Record findings first. Save this step, then continue to quotation.</p>
                    <form method="post" data-disable-on-submit>
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="diagnose">
                        <div class="mb-3">
                            <label class="form-label" for="diagnosis">Diagnosis <span class="text-red-700">*</span></label>
                            <textarea class="form-control" id="diagnosis" name="diagnosis" rows="4" required placeholder="Findings…"><?= e($diagnosisValue) ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="recommended_action">Recommended action</label>
                            <textarea class="form-control" id="recommended_action" name="recommended_action" rows="2"><?= e($recommendedValue) ?></textarea>
                        </div>
                        <div class="grid grid-cols-12 gap-2 mb-3">
                            <div class="col-span-7">
                                <label class="form-label" for="estimated_date">Est. completion</label>
                                <input type="date" class="form-control" id="estimated_date" name="estimated_date" min="<?= e(date('Y-m-d')) ?>" value="<?= e($estDate) ?>">
                            </div>
                            <div class="col-span-5">
                                <label class="form-label" for="estimated_time">Time</label>
                                <input type="time" class="form-control" id="estimated_time" name="estimated_time" value="<?= e($estTime) ?>">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-rapid-primary w-full">Save diagnosis</button>
                    </form>
                <?php elseif ($diagnosisRow): ?>
                    <p class="text-sm text-rapid-muted mb-2">Diagnosis is already on file for this stage.</p>
                    <p class="mb-1"><?= nl2br(e($diagnosisRow['diagnosis'])) ?></p>
                    <?php if ($diagnosisRow['recommended_action']): ?>
                        <p class="text-sm mb-0"><strong>Recommended:</strong> <?= nl2br(e($diagnosisRow['recommended_action'])) ?></p>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="text-sm text-rapid-muted mb-0">Diagnosis is not available at this stage.</p>
                <?php endif; ?>
            </div>

            <div class="wizard-panel" data-step="quote" <?= $startStep === 'quote' ? '' : 'hidden' ?>>
                <h3 class="text-sm font-semibold text-rapid mb-2">Create quotation</h3>
                <?php if ($canQuote): ?>
                    <p class="text-sm text-rapid-muted mb-3">Send a quote to the customer. Totals are confirmed on the server.</p>
                    <form method="post" data-disable-on-submit data-quote-form>
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="quotation">
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 mb-2">
                            <div>
                                <label class="form-label" for="labor_cost">Labor</label>
                                <input type="number" step="0.01" min="0" class="form-control quote-cost" id="labor_cost" name="labor_cost" value="<?= e($laborCost) ?>">
                            </div>
                            <div>
                                <label class="form-label" for="parts_cost">Parts</label>
                                <input type="number" step="0.01" min="0" class="form-control quote-cost" id="parts_cost" name="parts_cost" value="<?= e($partsCost) ?>">
                            </div>
                            <div>
                                <label class="form-label" for="other_cost">Tax &amp; fees</label>
                                <input type="number" step="0.01" min="0" class="form-control quote-cost" id="other_cost" name="other_cost" value="<?= e($otherCost) ?>">
                            </div>
                        </div>
                        <p class="text-sm mb-3">Estimated total: <strong>₱<span data-quote-total>0.00</span></strong> <span class="text-rapid-muted">(recalculated on server)</span></p>
                        <div class="mb-3">
                            <label class="form-label" for="valid_until">Valid until</label>
                            <input type="date" class="form-control" id="valid_until" name="valid_until" min="<?= e(date('Y-m-d')) ?>" value="<?= e($validUntil) ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="notes">Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="2"><?= e($quoteNotes) ?></textarea>
                        </div>
                        <button type="submit" class="btn btn-rapid-primary w-full">Send quotation</button>
                    </form>
                <?php else: ?>
                    <p class="text-sm text-rapid-muted mb-0">
                        <?php if ($quotationRow): ?>
                            A quotation is already on this ticket. Review it on the page, or wait until this job is back in the quotation stage.
                        <?php else: ?>
                            Quotation is not available yet. Save the diagnosis first, then return to this step.
                        <?php endif; ?>
                    </p>
                <?php endif; ?>
            </div>

            <div class="wizard-panel" data-step="status" <?= $startStep === 'status' ? '' : 'hidden' ?>>
                <h3 class="text-sm font-semibold text-rapid mb-2">Update status</h3>
                <?php if (!$techPreferred): ?>
                    <p class="text-rapid-muted text-sm mb-0">
                        <?php if (in_array('approved', $nextStatuses, true) || in_array('declined', $nextStatuses, true)): ?>
                            Waiting for the customer to approve or decline the quotation.
                        <?php else: ?>
                            No technician status changes available right now.
                        <?php endif; ?>
                    </p>
                <?php else: ?>
                    <p class="text-sm text-rapid-muted mb-3">Move the ticket forward when this step of the repair is done.</p>
                    <form method="post" data-disable-on-submit>
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="status">
                        <div class="mb-3">
                            <label class="form-label" for="new_status">New status</label>
                            <select class="form-select" id="new_status" name="new_status" required>
                                <?php foreach ($techPreferred as $ns): ?>
                                    <option value="<?= e($ns) ?>" <?= $postedAction === 'status' && ($_POST['new_status'] ?? '') === $ns ? 'selected' : '' ?>><?= e(status_label($ns)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="remarks">Remarks</label>
                            <textarea class="form-control" id="remarks" name="remarks" rows="2" placeholder="Optional note…"><?= e($remarksValue) ?></textarea>
                        </div>
                        <button type="submit" class="btn btn-rapid-primary w-full">Update status</button>
                    </form>
                <?php endif; ?>
            </div>

            <div class="rapid-modal-nav">
                <button type="button" class="btn btn-rapid-outline" data-process-prev>Back</button>
                <button type="button" class="btn btn-rapid-outline" data-process-next>Next step</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
