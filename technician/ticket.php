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
        $other = (float) ($_POST['other_cost'] ?? 0);
        $validUntil = trim((string) ($_POST['valid_until'] ?? ''));
        $validUntil = $validUntil !== '' ? $validUntil : null;
        $partsParsed = normalize_quotation_part_lines(
            (array) ($_POST['part_name'] ?? []),
            (array) ($_POST['part_qty'] ?? []),
            (array) ($_POST['part_unit_price'] ?? []),
            (array) ($_POST['part_id'] ?? [])
        );
        if (!$partsParsed['ok']) {
            $error = $partsParsed['error'] ?? 'Invalid parts list.';
        } else {
            $result = create_quotation(
                $ticketId,
                $technicianId,
                (int) $user['id'],
                $labor,
                $partsParsed['lines'] ?? [],
                $other,
                (string) ($_POST['notes'] ?? ''),
                $validUntil
            );
            if ($result['ok']) {
                flash_set('success', 'Quotation sent to the customer for approval.');
                redirect('technician/ticket.php?id=' . $ticketId . '&process=1');
            }
            $error = $result['error'] ?? 'Could not create quotation.';
        }
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
    } elseif ($action === 'message') {
        $result = post_ticket_message($ticketId, $user, (string) ($_POST['message_body'] ?? ''), $_FILES['attachment'] ?? []);
        if ($result['ok']) {
            redirect('technician/ticket.php?id=' . $ticketId . '#messages');
        }
        $error = $result['error'] ?? 'Could not send message.';
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

ensure_parts_schema();
$quotation = db()->prepare('SELECT * FROM quotations WHERE ticket_id = ? ORDER BY id DESC LIMIT 1');
$quotation->execute([$ticketId]);
$quotationRow = $quotation->fetch() ?: null;
if ($quotationRow) {
    $quotationRow['items'] = get_quotation_items((int) $quotationRow['id']);
}
$catalogParts = list_parts_catalog(true);
$billing = ticket_billing($ticketId);
$messages = get_ticket_messages($ticketId);

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

$quotePartRows = [];
if ($postedAction === 'quotation') {
    $postedIds = (array) ($_POST['part_id'] ?? []);
    $postedNames = (array) ($_POST['part_name'] ?? []);
    $postedQtys = (array) ($_POST['part_qty'] ?? []);
    $postedPrices = (array) ($_POST['part_unit_price'] ?? []);
    $count = max(count($postedIds), count($postedNames), count($postedQtys), count($postedPrices), 1);
    for ($i = 0; $i < $count; $i++) {
        $quotePartRows[] = [
            'part_id' => (string) ($postedIds[$i] ?? ''),
            'description' => (string) ($postedNames[$i] ?? ''),
            'quantity' => (string) ($postedQtys[$i] ?? '1'),
            'unit_price' => (string) ($postedPrices[$i] ?? '0'),
        ];
    }
} elseif (!empty($quotationRow['items'])) {
    foreach ($quotationRow['items'] as $item) {
        $quotePartRows[] = [
            'part_id' => (string) ($item['part_id'] ?? ''),
            'description' => (string) ($item['description'] ?? ''),
            'quantity' => (string) ($item['quantity'] ?? '1'),
            'unit_price' => (string) ($item['unit_price'] ?? '0'),
        ];
    }
}
if (!$quotePartRows) {
    $quotePartRows[] = ['part_id' => '', 'description' => '', 'quantity' => '1', 'unit_price' => '0'];
}

$renderCatalogOptions = static function (string $selectedId) use ($catalogParts): void {
    echo '<option value="">Custom part…</option>';
    $currentCat = null;
    foreach ($catalogParts as $part) {
        $cat = trim((string) ($part['category'] ?? ''));
        if ($cat !== '' && $cat !== $currentCat) {
            if ($currentCat !== null) {
                echo '</optgroup>';
            }
            echo '<optgroup label="' . e($cat) . '">';
            $currentCat = $cat;
        } elseif ($cat === '' && $currentCat !== null) {
            echo '</optgroup>';
            $currentCat = null;
        }
        $pid = (string) (int) $part['id'];
        $price = number_format((float) $part['unit_price'], 2, '.', '');
        $label = (string) $part['name'];
        if (!empty($part['sku'])) {
            $label .= ' (' . $part['sku'] . ')';
        }
        $label .= ' — ₱' . number_format((float) $part['unit_price'], 2);
        $stock = (int) $part['stock_qty'];
        $label .= $stock > 0 ? ' · ' . $stock . ' in stock' : ' · out of stock';
        echo '<option value="' . e($pid) . '"'
            . ' data-name="' . e((string) $part['name']) . '"'
            . ' data-price="' . e($price) . '"'
            . ($selectedId === $pid ? ' selected' : '')
            . '>' . e($label) . '</option>';
    }
    if ($currentCat !== null) {
        echo '</optgroup>';
    }
};
$remarksValue = $postedAction === 'status' ? (string) ($_POST['remarks'] ?? '') : '';

ai_ensure_schema();
$aiConfigured = ai_is_configured();
$aiCached = ai_get_cached_suggestion($ticketId, AI_KIND_TECHNICIAN_REPAIR);
if ($aiCached) {
    $aiCached['payload'] = ai_enrich_suggested_parts($aiCached['payload']);
}
$aiHash = ai_ticket_context_hash($ticket, $mediaRows, $diagnosisRow);
$aiStale = $aiCached && !hash_equals((string) $aiCached['context_hash'], $aiHash);

$pageTitle = $ticket['ticket_number'];
$showSidebar = true;
$navVariant = 'app';
$activeNav = 'assigned';
$bodyClass = 'app-body';
$pageScripts = ['js/ai-assist.js'];

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="app-shell">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="app-main">
        <div class="page-header flex flex-wrap justify-between items-start gap-3">
            <div>
                <?php render_ticket_number($ticket['ticket_number'], null, ['tag' => 'h1', 'heading' => true]); ?>
                <p class="mb-0">
                    <?= e($ticket['brand'] . ' ' . $ticket['model']) ?>
                    <span class="text-rapid-muted">·</span>
                    <?= e($ticket['customer_first_name'] . ' ' . $ticket['customer_last_name']) ?>
                    <?php if (!empty($ticket['customer_phone'])): ?>
                        <span class="text-rapid-muted">·</span>
                        <?= e($ticket['customer_phone']) ?>
                    <?php endif; ?>
                </p>
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

        <div class="tech-ticket-layout">
            <div class="tech-ticket-main">
                <section class="rapid-card tech-ticket-hero mb-3">
                    <div class="tech-ticket-meta">
                        <span><strong>Priority</strong> <?= e(ucfirst((string) $ticket['priority'])) ?></span>
                        <span><strong>Condition</strong> <?= e($ticket['physical_condition'] ?: '—') ?></span>
                    </div>
                    <h2 class="tech-ticket-section-title">Problem</h2>
                    <p class="tech-ticket-problem mb-0"><?= nl2br(e($ticket['problem_description'])) ?></p>
                </section>

                <section class="rapid-card mb-3">
                    <div class="flex flex-wrap justify-between items-center gap-2 mb-3">
                        <h2 class="tech-ticket-section-title mb-0">Media</h2>
                    </div>
                    <?php if (!$mediaRows): ?>
                        <p class="text-sm text-rapid-muted mb-3">No photos yet.</p>
                    <?php else: ?>
                        <?php render_before_after_media($mediaRows); ?>
                    <?php endif; ?>
                    <form method="post" enctype="multipart/form-data" data-disable-on-submit class="tech-ticket-upload">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="media">
                        <select class="form-select" id="media_category" name="media_category" aria-label="Media category">
                            <option value="diagnosis">Diagnosis</option>
                            <option value="during_repair" selected>During repair</option>
                            <option value="after_repair">After repair</option>
                        </select>
                        <input type="file" class="form-control" id="media" name="media[]" multiple
                               accept=".jpg,.jpeg,.png,.webp,.mp4,.mov,image/*,video/mp4,video/quicktime"
                               aria-label="Upload files">
                        <button type="submit" class="btn btn-rapid-outline">Upload</button>
                    </form>
                </section>

<?php if ($canProcess): ?>
                <section class="rapid-card mb-3 process-inline" id="processModal" data-process-modal data-inline
                         data-start-step="<?= e($startStep) ?>" data-open="<?= $openProcess ? '1' : '0' ?>"
                         aria-labelledby="processModalTitle" tabindex="-1">
                    <div class="flex flex-wrap justify-between items-center gap-2 mb-3">
                        <h2 id="processModalTitle" class="tech-ticket-section-title mb-0">Process repair</h2>
                    </div>
            <ol class="wizard-steps" aria-label="Repair process steps">
                <?php foreach ($processSteps as $i => $step): ?>
                    <li class="<?= $step['id'] === $startStep ? 'is-current' : '' ?>" data-process-goto="<?= e($step['id']) ?>">
                        <span class="wiz-num"><?= (int) ($i + 1) ?></span>
                        <span><?= e($step['label']) ?></span>
                    </li>
                <?php endforeach; ?>
            </ol>

            <div class="wizard-panel" data-step="diagnose" <?= $startStep === 'diagnose' ? '' : 'hidden' ?>>
                <h3 class="text-sm font-semibold text-rapid mb-3">Diagnosis</h3>
                <?php if ($canDiagnose): ?>
                    <form method="post" data-disable-on-submit>
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="diagnose">
                        <div class="mb-3">
                            <label class="form-label" for="diagnosis">Findings <span class="text-red-700">*</span></label>
                            <textarea class="form-control" id="diagnosis" name="diagnosis" rows="4" required placeholder="What you found…"><?= e($diagnosisValue) ?></textarea>
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
                    <p class="mb-1"><?= nl2br(e($diagnosisRow['diagnosis'])) ?></p>
                    <?php if ($diagnosisRow['recommended_action']): ?>
                        <p class="text-sm mb-0 text-rapid-muted"><?= nl2br(e($diagnosisRow['recommended_action'])) ?></p>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="text-sm text-rapid-muted mb-0">Not available at this stage.</p>
                <?php endif; ?>
            </div>

            <div class="wizard-panel" data-step="quote" <?= $startStep === 'quote' ? '' : 'hidden' ?>>
                <h3 class="text-sm font-semibold text-rapid mb-3">Quotation</h3>
                <?php if ($canQuote): ?>
                    <?php $quoteTemplates = repair_templates_for_quote_ui(); ?>
                    <form method="post" data-disable-on-submit data-quote-form
                          data-repair-templates="<?= e(json_encode($quoteTemplates, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="quotation">

                        <?php if ($quoteTemplates): ?>
                            <div class="mb-3">
                                <label class="form-label" for="repair_template">Repair template</label>
                                <div class="flex flex-wrap gap-2">
                                    <select class="form-select" id="repair_template" data-repair-template-select>
                                        <option value="">Choose a template…</option>
                                        <?php foreach ($quoteTemplates as $tpl): ?>
                                            <option value="<?= (int) $tpl['id'] ?>"><?= e($tpl['name']) ?><?= $tpl['device_type'] !== '' ? ' (' . e($tpl['device_type']) . ')' : '' ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="button" class="btn btn-rapid-outline btn-sm" data-apply-template>Apply</button>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="mb-3">
                            <div class="flex justify-between items-center gap-2 mb-2 flex-wrap">
                                <label class="form-label mb-0">Parts needed</label>
                                <div class="flex flex-wrap gap-2">
                                    <button type="button" class="btn btn-rapid-outline btn-sm" data-add-ai-parts hidden>
                                        <i class="bi bi-stars" aria-hidden="true"></i> Use AI suggestions
                                    </button>
                                    <button type="button" class="btn btn-rapid-outline btn-sm" data-add-part>
                                        <i class="bi bi-plus-lg" aria-hidden="true"></i> Add part
                                    </button>
                                </div>
                            </div>
                            <p class="text-xs text-rapid-muted mb-2" data-ai-parts-hint hidden>
                                AI suggested parts are ready — review them after adding. You can still edit or remove lines.
                            </p>
                            <?php if (!$catalogParts): ?>
                                <p class="text-sm text-rapid-muted mb-2">No catalog parts yet — type a custom part name, or ask an admin to add items under Parts Catalog.</p>
                            <?php endif; ?>
                            <div class="quote-parts-list" data-quote-parts>
                                <?php foreach ($quotePartRows as $idx => $partRow): ?>
                                    <?php
                                    $selectedPartId = (string) ($partRow['part_id'] ?? '');
                                    $isCatalog = $selectedPartId !== '' && $selectedPartId !== '0';
                                    $pQty = (float) ($partRow['quantity'] !== '' ? $partRow['quantity'] : 0);
                                    $pUnit = (float) ($partRow['unit_price'] !== '' ? $partRow['unit_price'] : 0);
                                    $pLine = number_format($pQty * $pUnit, 2, '.', '');
                                    ?>
                                    <div class="quote-part-row" data-quote-part-row>
                                        <div class="quote-part-catalog">
                                            <label class="form-label sr-only">Catalog part</label>
                                            <select class="form-select" name="part_id[]" data-part-catalog <?= $idx === 0 ? 'id="part_catalog_0"' : '' ?>>
                                                <?php $renderCatalogOptions($isCatalog ? $selectedPartId : ''); ?>
                                            </select>
                                        </div>
                                        <div class="quote-part-name-wrap">
                                            <label class="form-label sr-only">Part name</label>
                                            <input type="text" class="form-control" name="part_name[]" data-part-name placeholder="Part name" value="<?= e($partRow['description']) ?>" maxlength="255" <?= $isCatalog ? 'readonly' : '' ?>>
                                        </div>
                                        <div>
                                            <label class="form-label sr-only">Qty</label>
                                            <input type="number" step="0.01" min="0.01" class="form-control quote-part-qty" name="part_qty[]" placeholder="Qty" value="<?= e($partRow['quantity']) ?>">
                                        </div>
                                        <div>
                                            <label class="form-label sr-only">Unit price</label>
                                            <input type="number" step="0.01" min="0" class="form-control quote-part-price" name="part_unit_price[]" placeholder="Unit price" value="<?= e($partRow['unit_price']) ?>">
                                        </div>
                                        <div class="quote-part-line">
                                            <span class="text-sm text-rapid-muted">Line</span>
                                            <strong>₱<span data-part-line><?= e($pLine) ?></span></strong>
                                        </div>
                                        <button type="button" class="btn btn-outline-secondary btn-sm quote-part-remove" data-remove-part aria-label="Remove part" <?= count($quotePartRows) <= 1 ? 'hidden' : '' ?>>
                                            <i class="bi bi-trash" aria-hidden="true"></i>
                                        </button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <template id="quotePartRowTemplate">
                                <div class="quote-part-row" data-quote-part-row>
                                    <div class="quote-part-catalog">
                                        <label class="form-label sr-only">Catalog part</label>
                                        <select class="form-select" name="part_id[]" data-part-catalog>
                                            <?php $renderCatalogOptions(''); ?>
                                        </select>
                                    </div>
                                    <div class="quote-part-name-wrap">
                                        <label class="form-label sr-only">Part name</label>
                                        <input type="text" class="form-control" name="part_name[]" data-part-name placeholder="Part name" value="" maxlength="255">
                                    </div>
                                    <div>
                                        <label class="form-label sr-only">Qty</label>
                                        <input type="number" step="0.01" min="0.01" class="form-control quote-part-qty" name="part_qty[]" placeholder="Qty" value="1">
                                    </div>
                                    <div>
                                        <label class="form-label sr-only">Unit price</label>
                                        <input type="number" step="0.01" min="0" class="form-control quote-part-price" name="part_unit_price[]" placeholder="Unit price" value="0">
                                    </div>
                                    <div class="quote-part-line">
                                        <span class="text-sm text-rapid-muted">Line</span>
                                        <strong>₱<span data-part-line>0.00</span></strong>
                                    </div>
                                    <button type="button" class="btn btn-outline-secondary btn-sm quote-part-remove" data-remove-part aria-label="Remove part">
                                        <i class="bi bi-trash" aria-hidden="true"></i>
                                    </button>
                                </div>
                            </template>
                            <p class="text-sm mt-2 mb-0">Parts subtotal: <strong>₱<span data-parts-subtotal>0.00</span></strong></p>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mb-2">
                            <div>
                                <label class="form-label" for="labor_cost">Labor</label>
                                <input type="number" step="0.01" min="0" class="form-control quote-cost" id="labor_cost" name="labor_cost" value="<?= e($laborCost) ?>">
                            </div>
                            <div>
                                <label class="form-label" for="other_cost">Tax &amp; fees</label>
                                <input type="number" step="0.01" min="0" class="form-control quote-cost" id="other_cost" name="other_cost" value="<?= e($otherCost) ?>">
                            </div>
                        </div>
                        <p class="text-sm mb-3">Total: <strong>₱<span data-quote-total>0.00</span></strong></p>
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
                            Quotation already sent. Review it on the ticket page.
                        <?php else: ?>
                            Save the diagnosis first.
                        <?php endif; ?>
                    </p>
                <?php endif; ?>
            </div>

            <div class="wizard-panel" data-step="status" <?= $startStep === 'status' ? '' : 'hidden' ?>>
                <h3 class="text-sm font-semibold text-rapid mb-3">Status</h3>
                <?php if (!$techPreferred): ?>
                    <p class="text-rapid-muted text-sm mb-0">
                        <?php if (in_array('approved', $nextStatuses, true) || in_array('declined', $nextStatuses, true)): ?>
                            Waiting for customer approval.
                        <?php else: ?>
                            No status change available right now.
                        <?php endif; ?>
                    </p>
                <?php else: ?>
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
                </section>
<?php endif; ?>

                <?php if ($diagnosisRow || $quotationRow): ?>
                    <section class="rapid-card mb-3">
                        <h2 class="tech-ticket-section-title">On file</h2>
                        <div class="tech-ticket-onfile">
                            <?php if ($diagnosisRow): ?>
                                <div>
                                    <h3>Diagnosis</h3>
                                    <p><?= nl2br(e($diagnosisRow['diagnosis'])) ?></p>
                                    <?php if ($diagnosisRow['recommended_action']): ?>
                                        <p class="tech-ticket-muted"><span>Recommended</span> <?= nl2br(e($diagnosisRow['recommended_action'])) ?></p>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($quotationRow): ?>
                                <div class="tech-ticket-quote">
                                    <?php render_quotation_card($quotationRow, false, url('technician/quotation_print.php?id=' . $ticketId)); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </section>
                <?php endif; ?>

                <?php render_ticket_thread($messages, (int) $user['id'], 'Messages with customer'); ?>
            </div>

            <aside class="tech-ticket-side">
                <?php if ($feedback = get_ticket_feedback($ticketId)): ?>
                    <div class="rapid-card mb-3">
                        <h2 class="tech-ticket-section-title">Customer feedback</h2>
                        <?php render_feedback_card($feedback); ?>
                    </div>
                <?php endif; ?>
                <?php if ($billing['quote_id'] !== null): ?>
                    <div class="rapid-card mb-3" id="billing">
                        <h2 class="tech-ticket-section-title">Payments</h2>
                        <?php render_billing_card($billing); ?>
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
                    <div class="flex flex-wrap justify-between items-center gap-2 mb-2">
                        <h2 class="tech-ticket-section-title mb-0">
                            <i class="bi bi-stars text-rapid-accent" aria-hidden="true"></i> AI assist
                        </h2>
                        <button type="button" class="btn btn-rapid-primary btn-sm" id="aiSuggestBtn">
                            <?= $aiCached ? 'Refresh' : 'Suggest' ?>
                        </button>
                    </div>
                    <p class="ai-assist-status text-xs text-rapid-muted mb-2" id="aiAssistStatus"></p>
                    <div class="alert alert-danger ai-assist-error mb-2" id="aiAssistError" hidden></div>
                    <?php if (!$aiConfigured): ?>
                        <p class="text-sm text-rapid-muted mb-0" id="aiAssistEmpty">Add a Gemini API key in Settings.</p>
                        <div id="aiAssistResult" hidden></div>
                    <?php else: ?>
                        <p class="text-sm text-rapid-muted mb-0" id="aiAssistEmpty" <?= $aiCached ? 'hidden' : '' ?>>
                            Get causes, tests, and optional parts.
                        </p>
                        <div id="aiAssistResult" <?= $aiCached ? '' : 'hidden' ?>></div>
                    <?php endif; ?>
                </div>

                <div class="rapid-card">
                    <h2 class="tech-ticket-section-title">Timeline</h2>
                    <ul class="status-timeline tech-ticket-timeline">
                        <?php foreach ($historyRows as $h): ?>
                            <li class="done">
                                <span class="dot"></span>
                                <div class="tl-title"><?= e(status_label($h['status'])) ?></div>
                                <div class="tl-meta"><?= e(format_datetime($h['created_at'])) ?><?= $h['remarks'] ? ' · ' . e($h['remarks']) : '' ?></div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </aside>
        </div>
    </main>
</div>


<?php require_once __DIR__ . '/../includes/footer.php'; ?>
