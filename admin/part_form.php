<?php
/**
 * Admin — add / edit catalog part
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

ensure_parts_schema();

$user = current_user();
$id = (int) ($_GET['id'] ?? 0);
$editing = $id > 0;
$row = $editing ? get_part_by_id($id) : null;

if ($editing && !$row) {
    flash_set('error', 'Part not found.');
    redirect('admin/parts.php');
}

$form = [
    'sku' => (string) ($row['sku'] ?? ''),
    'name' => (string) ($row['name'] ?? ''),
    'category' => (string) ($row['category'] ?? ''),
    'unit_price' => $row ? (string) $row['unit_price'] : '0',
    'notes' => (string) ($row['notes'] ?? ''),
    'is_active' => $row ? (string) (int) $row['is_active'] : '1',
];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $form['sku'] = trim((string) ($_POST['sku'] ?? ''));
    $form['name'] = trim((string) ($_POST['name'] ?? ''));
    $form['category'] = trim((string) ($_POST['category'] ?? ''));
    $form['unit_price'] = trim((string) ($_POST['unit_price'] ?? '0'));
    $form['notes'] = trim((string) ($_POST['notes'] ?? ''));
    $form['is_active'] = isset($_POST['is_active']) ? '1' : '0';

    $result = save_part($editing ? $id : null, [
        'sku' => $form['sku'],
        'name' => $form['name'],
        'category' => $form['category'],
        'unit_price' => $form['unit_price'],
        'notes' => $form['notes'],
        'is_active' => $form['is_active'] === '1',
    ]);

    if ($result['ok']) {
        $savedId = (int) ($result['part_id'] ?? 0);
        log_activity(
            (int) $user['id'],
            $editing ? 'part_update' : 'part_create',
            ($editing ? 'Updated' : 'Created') . ' part #' . $savedId
        );
        flash_set('success', $editing ? 'Part updated.' : 'Part added to catalog.');
        redirect('admin/parts.php');
    }
    $error = $result['error'] ?? 'Save failed.';
}

$pageTitle = $editing ? 'Edit Part' : 'Add Part';
$showSidebar = true;
$navVariant = 'app';
$activeNav = 'parts';
$bodyClass = 'app-body';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="app-shell">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="app-main">
        <div class="page-header">
            <h1><?= $editing ? 'Edit part' : 'Add part' ?></h1>
            <p><?= $editing ? 'Update catalog details and sell price.' : 'Add a priced part technicians can select on quotations.' ?></p>
        </div>

        <?php if ($error !== ''): ?>
            <div class="alert alert-danger"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post" class="rapid-card max-w-[720px]" data-disable-on-submit>
            <?= csrf_field() ?>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div class="md:col-span-2">
                    <label class="form-label" for="name">Part name</label>
                    <input class="form-control" id="name" name="name" required maxlength="255" value="<?= e($form['name']) ?>" placeholder="e.g. iPhone 13 LCD digitizer">
                </div>
                <div>
                    <label class="form-label" for="sku">SKU <span class="text-rapid-muted font-normal">(optional)</span></label>
                    <input class="form-control" id="sku" name="sku" maxlength="60" value="<?= e($form['sku']) ?>" placeholder="LCD-IP13">
                </div>
                <div>
                    <label class="form-label" for="category">Category <span class="text-rapid-muted font-normal">(optional)</span></label>
                    <input class="form-control" id="category" name="category" maxlength="100" value="<?= e($form['category']) ?>" placeholder="Phone, Laptop, Tablet…">
                </div>
                <div>
                    <label class="form-label" for="unit_price">Unit price (₱)</label>
                    <input type="number" step="0.01" min="0" class="form-control" id="unit_price" name="unit_price" required value="<?= e($form['unit_price']) ?>">
                </div>
                <div class="flex items-end pb-1">
                    <label class="inline-flex items-center gap-2 text-sm">
                        <input type="checkbox" name="is_active" value="1" <?= $form['is_active'] === '1' ? 'checked' : '' ?>>
                        Active in catalog
                    </label>
                </div>
                <div class="md:col-span-2">
                    <label class="form-label" for="notes">Notes</label>
                    <textarea class="form-control" id="notes" name="notes" rows="2" placeholder="Optional internal note…"><?= e($form['notes']) ?></textarea>
                </div>
            </div>
            <div class="flex gap-2 mt-3">
                <button type="submit" class="btn btn-rapid-primary"><?= $editing ? 'Save changes' : 'Add part' ?></button>
                <a class="btn btn-outline-secondary" href="<?= e(url('admin/parts.php')) ?>">Cancel</a>
            </div>
        </form>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
