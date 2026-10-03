<?php
/**
 * Admin — add / edit repair template
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

ensure_repair_templates_schema();
ensure_parts_schema();

$user = current_user();
$id = (int) ($_GET['id'] ?? 0);
$editing = $id > 0;
$row = $editing ? get_repair_template($id) : null;
if ($editing && !$row) {
    flash_set('error', 'Template not found.');
    redirect('admin/templates.php');
}

$catalog = list_parts_catalog(true);
$form = [
    'name' => (string) ($row['name'] ?? ''),
    'device_type' => (string) ($row['device_type'] ?? ''),
    'labor_cost' => $row ? (string) $row['labor_cost'] : '0',
    'other_cost' => $row ? (string) $row['other_cost'] : '0',
    'notes' => (string) ($row['notes'] ?? ''),
    'is_active' => $row ? (string) (int) $row['is_active'] : '1',
];
$itemRows = [];
if ($row && !empty($row['items'])) {
    foreach ($row['items'] as $item) {
        $itemRows[] = [
            'part_id' => (string) ($item['part_id'] ?? ''),
            'description' => (string) ($item['description'] ?? ''),
            'quantity' => (string) ($item['quantity'] ?? '1'),
            'unit_price' => (string) ($item['unit_price'] ?? '0'),
        ];
    }
}
if (!$itemRows) {
    $itemRows[] = ['part_id' => '', 'description' => '', 'quantity' => '1', 'unit_price' => '0'];
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $form['name'] = trim((string) ($_POST['name'] ?? ''));
    $form['device_type'] = trim((string) ($_POST['device_type'] ?? ''));
    $form['labor_cost'] = trim((string) ($_POST['labor_cost'] ?? '0'));
    $form['other_cost'] = trim((string) ($_POST['other_cost'] ?? '0'));
    $form['notes'] = trim((string) ($_POST['notes'] ?? ''));
    $form['is_active'] = isset($_POST['is_active']) ? '1' : '0';

    $names = (array) ($_POST['part_name'] ?? []);
    $qtys = (array) ($_POST['part_qty'] ?? []);
    $prices = (array) ($_POST['part_unit_price'] ?? []);
    $partIds = (array) ($_POST['part_id'] ?? []);
    $items = [];
    $count = max(count($names), count($qtys), count($prices), count($partIds));
    for ($i = 0; $i < $count; $i++) {
        $items[] = [
            'part_id' => (int) ($partIds[$i] ?? 0),
            'description' => (string) ($names[$i] ?? ''),
            'quantity' => (string) ($qtys[$i] ?? '1'),
            'unit_price' => (string) ($prices[$i] ?? '0'),
        ];
    }
    $itemRows = $items ?: $itemRows;

    $result = save_repair_template($editing ? $id : null, [
        'name' => $form['name'],
        'device_type' => $form['device_type'],
        'labor_cost' => $form['labor_cost'],
        'other_cost' => $form['other_cost'],
        'notes' => $form['notes'],
        'is_active' => $form['is_active'] === '1',
    ], $items);

    if ($result['ok']) {
        log_activity((int) $user['id'], $editing ? 'template_update' : 'template_create', 'Template #' . (int) $result['template_id']);
        flash_set('success', $editing ? 'Template updated.' : 'Template created.');
        redirect('admin/templates.php');
    }
    $error = $result['error'] ?? 'Save failed.';
}

$pageTitle = $editing ? 'Edit Template' : 'Add Template';
$showSidebar = true;
$navVariant = 'app';
$activeNav = 'templates';
$bodyClass = 'app-body';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="app-shell">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="app-main">
        <div class="page-header">
            <h1><?= $editing ? 'Edit template' : 'Add template' ?></h1>
            <p>Example: “Screen replace” with labor + display part.</p>
        </div>

        <?php if ($error !== ''): ?>
            <div class="alert alert-danger"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post" class="rapid-card max-w-[860px]" data-disable-on-submit data-quote-form>
            <?= csrf_field() ?>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-3">
                <div class="md:col-span-2">
                    <label class="form-label" for="name">Template name</label>
                    <input class="form-control" id="name" name="name" required value="<?= e($form['name']) ?>" placeholder="iPhone screen replace">
                </div>
                <div>
                    <label class="form-label" for="device_type">Device type</label>
                    <input class="form-control" id="device_type" name="device_type" value="<?= e($form['device_type']) ?>" placeholder="Phone, Laptop…">
                </div>
                <div class="flex items-end pb-1">
                    <label class="inline-flex items-center gap-2 text-sm">
                        <input type="checkbox" name="is_active" value="1" <?= $form['is_active'] === '1' ? 'checked' : '' ?>>
                        Active
                    </label>
                </div>
                <div>
                    <label class="form-label" for="labor_cost">Labor</label>
                    <input type="number" step="0.01" min="0" class="form-control quote-cost" id="labor_cost" name="labor_cost" value="<?= e($form['labor_cost']) ?>">
                </div>
                <div>
                    <label class="form-label" for="other_cost">Tax &amp; fees</label>
                    <input type="number" step="0.01" min="0" class="form-control quote-cost" id="other_cost" name="other_cost" value="<?= e($form['other_cost']) ?>">
                </div>
                <div class="md:col-span-2">
                    <label class="form-label" for="notes">Notes</label>
                    <textarea class="form-control" id="notes" name="notes" rows="2"><?= e($form['notes']) ?></textarea>
                </div>
            </div>

            <div class="mb-3">
                <div class="flex justify-between items-center gap-2 mb-2">
                    <label class="form-label mb-0">Typical parts</label>
                    <button type="button" class="btn btn-rapid-outline btn-sm" data-add-part>
                        <i class="bi bi-plus-lg" aria-hidden="true"></i> Add part
                    </button>
                </div>
                <div class="quote-parts-list" data-quote-parts>
                    <?php foreach ($itemRows as $partRow): ?>
                        <?php $selected = (string) ($partRow['part_id'] ?? ''); ?>
                        <div class="quote-part-row" data-quote-part-row>
                            <div class="quote-part-catalog">
                                <select class="form-select" name="part_id[]" data-part-catalog>
                                    <option value="">Custom…</option>
                                    <?php foreach ($catalog as $part): ?>
                                        <?php $pid = (string) (int) $part['id']; ?>
                                        <option value="<?= e($pid) ?>"
                                            data-name="<?= e((string) $part['name']) ?>"
                                            data-price="<?= e(number_format((float) $part['unit_price'], 2, '.', '')) ?>"
                                            <?= $selected === $pid ? 'selected' : '' ?>>
                                            <?= e($part['name']) ?> — ₱<?= e(number_format((float) $part['unit_price'], 2)) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="quote-part-name-wrap">
                                <input type="text" class="form-control" name="part_name[]" data-part-name placeholder="Part name" value="<?= e($partRow['description']) ?>" maxlength="255" <?= $selected !== '' && $selected !== '0' ? 'readonly' : '' ?>>
                            </div>
                            <div>
                                <input type="number" step="0.01" min="0.01" class="form-control quote-part-qty" name="part_qty[]" value="<?= e($partRow['quantity']) ?>">
                            </div>
                            <div>
                                <input type="number" step="0.01" min="0" class="form-control quote-part-price" name="part_unit_price[]" value="<?= e($partRow['unit_price']) ?>">
                            </div>
                            <div class="quote-part-line">
                                <span class="text-sm text-rapid-muted">Line</span>
                                <strong>₱<span data-part-line>0.00</span></strong>
                            </div>
                            <button type="button" class="btn btn-outline-secondary btn-sm quote-part-remove" data-remove-part aria-label="Remove">
                                <i class="bi bi-trash" aria-hidden="true"></i>
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
                <template id="quotePartRowTemplate">
                    <div class="quote-part-row" data-quote-part-row>
                        <div class="quote-part-catalog">
                            <select class="form-select" name="part_id[]" data-part-catalog>
                                <option value="">Custom…</option>
                                <?php foreach ($catalog as $part): ?>
                                    <option value="<?= (int) $part['id'] ?>"
                                        data-name="<?= e((string) $part['name']) ?>"
                                        data-price="<?= e(number_format((float) $part['unit_price'], 2, '.', '')) ?>">
                                        <?= e($part['name']) ?> — ₱<?= e(number_format((float) $part['unit_price'], 2)) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="quote-part-name-wrap">
                            <input type="text" class="form-control" name="part_name[]" data-part-name placeholder="Part name" value="" maxlength="255">
                        </div>
                        <div>
                            <input type="number" step="0.01" min="0.01" class="form-control quote-part-qty" name="part_qty[]" value="1">
                        </div>
                        <div>
                            <input type="number" step="0.01" min="0" class="form-control quote-part-price" name="part_unit_price[]" value="0">
                        </div>
                        <div class="quote-part-line">
                            <span class="text-sm text-rapid-muted">Line</span>
                            <strong>₱<span data-part-line>0.00</span></strong>
                        </div>
                        <button type="button" class="btn btn-outline-secondary btn-sm quote-part-remove" data-remove-part aria-label="Remove">
                            <i class="bi bi-trash" aria-hidden="true"></i>
                        </button>
                    </div>
                </template>
            </div>

            <div class="flex gap-2">
                <button type="submit" class="btn btn-rapid-primary"><?= $editing ? 'Save changes' : 'Create template' ?></button>
                <a class="btn btn-outline-secondary" href="<?= e(url('admin/templates.php')) ?>">Cancel</a>
            </div>
        </form>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
