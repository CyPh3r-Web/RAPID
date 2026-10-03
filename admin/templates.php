<?php
/**
 * Admin — repair templates list
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

ensure_repair_templates_schema();
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $action = (string) ($_POST['action'] ?? '');
    $id = (int) ($_POST['template_id'] ?? 0);
    if ($action === 'deactivate' && $id > 0) {
        $r = set_repair_template_active($id, false);
        flash_set($r['ok'] ? 'success' : 'error', $r['ok'] ? 'Template deactivated.' : ($r['error'] ?? 'Failed.'));
        redirect('admin/templates.php');
    }
    if ($action === 'activate' && $id > 0) {
        $r = set_repair_template_active($id, true);
        flash_set($r['ok'] ? 'success' : 'error', $r['ok'] ? 'Template activated.' : ($r['error'] ?? 'Failed.'));
        redirect('admin/templates.php');
    }
}

$templates = list_repair_templates(false);
// ponytail: one items query per template; fine for a shop-sized list, batch it if templates grow into the hundreds.
foreach ($templates as &$tpl) {
    $full = get_repair_template((int) $tpl['id']);
    $tpl['items'] = $full['items'] ?? [];
    $partsTotal = 0.0;
    foreach ($tpl['items'] as $item) {
        $partsTotal += (float) $item['quantity'] * (float) $item['unit_price'];
    }
    $tpl['est_total'] = $partsTotal + (float) $tpl['labor_cost'] + (float) $tpl['other_cost'];
}
unset($tpl);

$pageTitle = 'Repair Templates';
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
        <div class="page-header flex flex-wrap justify-between items-start gap-2">
            <div>
                <h1>Repair templates</h1>
                <p>Prefill labor and typical parts on technician quotations.</p>
            </div>
            <a class="btn btn-rapid-primary btn-sm" data-modal-form href="<?= e(url('admin/template_form.php')) ?>">Add template</a>
        </div>

        <?php if (!$templates): ?>
            <div class="rapid-card">
                <div class="empty-state"><p class="mb-0">No templates yet. Add “Screen replace” or similar jobs.</p></div>
            </div>
        <?php else: ?>
            <div class="template-grid">
                <?php foreach ($templates as $tpl): ?>
                    <?php
                    $isActive = (int) $tpl['is_active'] === 1;
                    $partNames = array_map(static fn ($i) => (string) $i['description'], $tpl['items']);
                    ?>
                    <article class="rapid-card template-card<?= $isActive ? '' : ' is-inactive' ?>">
                        <div class="flex justify-between items-start gap-2">
                            <span class="badge-status badge-status-primary"><?= e($tpl['device_type'] ?: 'Any device') ?></span>
                            <span class="badge-status <?= $isActive ? 'badge-status-success' : 'badge-status-muted' ?>"><?= $isActive ? 'Active' : 'Inactive' ?></span>
                        </div>
                        <h2 class="template-card-title"><?= e($tpl['name']) ?></h2>
                        <p class="template-card-parts">
                            <?= $partNames ? e(implode(', ', $partNames)) : 'No typical parts — labor only' ?>
                        </p>
                        <div class="template-card-foot">
                            <span>Labor <?= e(money_php((float) $tpl['labor_cost'])) ?></span>
                            <strong>Est. <?= e(money_php((float) $tpl['est_total'])) ?></strong>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <a class="btn btn-sm btn-rapid-outline" data-modal-form href="<?= e(url('admin/template_form.php?id=' . (int) $tpl['id'])) ?>">Edit</a>
                            <form method="post" data-disable-on-submit>
                                <?= csrf_field() ?>
                                <input type="hidden" name="template_id" value="<?= (int) $tpl['id'] ?>">
                                <input type="hidden" name="action" value="<?= $isActive ? 'deactivate' : 'activate' ?>">
                                <button type="submit" class="btn btn-sm btn-outline-secondary"><?= $isActive ? 'Deactivate' : 'Activate' ?></button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
