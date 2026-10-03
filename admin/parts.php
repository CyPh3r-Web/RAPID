<?php
/**
 * Admin — parts catalog list
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

ensure_parts_schema();

$user = current_user();
$q = trim((string) ($_GET['q'] ?? ''));
$status = trim((string) ($_GET['status'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $action = (string) ($_POST['action'] ?? '');
    $partId = (int) ($_POST['part_id'] ?? 0);
    if ($action === 'deactivate' && $partId > 0) {
        $result = set_part_active($partId, false);
        if ($result['ok']) {
            log_activity((int) $user['id'], 'part_deactivate', 'Deactivated part #' . $partId);
            flash_set('success', 'Part deactivated.');
        } else {
            flash_set('error', $result['error'] ?? 'Could not deactivate part.');
        }
        redirect('admin/parts.php');
    }
    if ($action === 'activate' && $partId > 0) {
        $result = set_part_active($partId, true);
        if ($result['ok']) {
            log_activity((int) $user['id'], 'part_activate', 'Activated part #' . $partId);
            flash_set('success', 'Part activated.');
        } else {
            flash_set('error', $result['error'] ?? 'Could not activate part.');
        }
        redirect('admin/parts.php');
    }
}

$where = ['1=1'];
$params = [];
if ($q !== '') {
    $where[] = '(name LIKE ? OR sku LIKE ? OR category LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like);
}
if ($status === 'active') {
    $where[] = 'is_active = 1';
} elseif ($status === 'inactive') {
    $where[] = 'is_active = 0';
} elseif ($status === 'low') {
    $where[] = 'is_active = 1 AND stock_qty <= reorder_level';
}
$whereSql = implode(' AND ', $where);

$count = db()->prepare("SELECT COUNT(*) FROM parts WHERE $whereSql");
$count->execute($params);
$total = (int) $count->fetchColumn();
$totalPages = max(1, (int) ceil($total / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

$stmt = db()->prepare(
    "SELECT id, sku, name, category, unit_price, stock_qty, reorder_level, is_active, updated_at
     FROM parts
     WHERE $whereSql
     ORDER BY is_active DESC, category IS NULL, category ASC, name ASC
     LIMIT $perPage OFFSET $offset"
);
$stmt->execute($params);
$parts = $stmt->fetchAll();

$pageTitle = 'Parts Catalog';
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
        <div class="page-header flex flex-wrap justify-between items-start gap-2">
            <div>
                <h1>Parts catalog</h1>
                <p>Manage priced parts and stock on hand. Stock is deducted when a customer approves a quotation.</p>
            </div>
            <a class="btn btn-rapid-primary btn-sm" data-modal-form href="<?= e(url('admin/part_form.php')) ?>">Add part</a>
        </div>

        <form method="get" class="filter-bar">
            <div>
                <label class="form-label" for="q">Search</label>
                <input type="text" class="form-control" id="q" name="q" value="<?= e($q) ?>" placeholder="Name, SKU, category…">
            </div>
            <div>
                <label class="form-label" for="status">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">All</option>
                    <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    <option value="low" <?= $status === 'low' ? 'selected' : '' ?>>Low / out of stock</option>
                </select>
            </div>
            <div class="flex gap-2">
                <button class="btn btn-rapid-primary" type="submit">Filter</button>
                <a class="btn btn-outline-secondary" href="<?= e(url('admin/parts.php')) ?>">Reset</a>
            </div>
        </form>

        <div class="rapid-card p-0 overflow-hidden">
            <?php if (!$parts): ?>
                <div class="empty-state"><p class="mb-0">No parts found. Add your first catalog item.</p></div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="rapid-table">
                        <thead>
                            <tr>
                                <th>SKU</th>
                                <th>Name</th>
                                <th>Category</th>
                                <th class="text-right">Unit price</th>
                                <th class="text-right">Stock</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($parts as $part): ?>
                                <tr>
                                    <td class="tabular-nums"><?= e($part['sku'] ?: '—') ?></td>
                                    <td class="font-semibold"><?= e($part['name']) ?></td>
                                    <td><?= e($part['category'] ?: '—') ?></td>
                                    <td class="text-right tabular-nums"><?= e(money_php((float) $part['unit_price'])) ?></td>
                                    <td class="text-right">
                                        <span class="badge-status <?= e(stock_badge_class((int) $part['stock_qty'], (int) $part['reorder_level'])) ?>" title="Reorder at <?= (int) $part['reorder_level'] ?>">
                                            <?= (int) $part['stock_qty'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge-status <?= (int) $part['is_active'] === 1 ? 'badge-status-success' : 'badge-status-muted' ?>">
                                            <?= (int) $part['is_active'] === 1 ? 'Active' : 'Inactive' ?>
                                        </span>
                                    </td>
                                    <td class="text-right whitespace-nowrap">
                                        <div class="flex flex-wrap gap-2 justify-end">
                                            <a class="btn btn-sm btn-rapid-outline" data-modal-form href="<?= e(url('admin/part_form.php?id=' . (int) $part['id'])) ?>">Edit</a>
                                            <?php if ((int) $part['is_active'] === 1): ?>
                                                <form method="post" data-disable-on-submit>
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="deactivate">
                                                    <input type="hidden" name="part_id" value="<?= (int) $part['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-secondary">Deactivate</button>
                                                </form>
                                            <?php else: ?>
                                                <form method="post" data-disable-on-submit>
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="activate">
                                                    <input type="hidden" name="part_id" value="<?= (int) $part['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-secondary">Activate</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($totalPages > 1): ?>
            <div class="flex flex-wrap gap-2 justify-between items-center mt-3">
                <p class="text-sm text-rapid-muted mb-0">Page <?= $page ?> of <?= $totalPages ?> · <?= $total ?> part<?= $total === 1 ? '' : 's' ?></p>
                <div class="flex gap-2">
                    <?php if ($page > 1): ?>
                        <a class="btn btn-sm btn-rapid-outline" href="<?= e(url('admin/parts.php?page=' . ($page - 1) . '&q=' . urlencode($q) . '&status=' . urlencode($status))) ?>">Previous</a>
                    <?php endif; ?>
                    <?php if ($page < $totalPages): ?>
                        <a class="btn btn-sm btn-rapid-outline" href="<?= e(url('admin/parts.php?page=' . ($page + 1) . '&q=' . urlencode($q) . '&status=' . urlencode($status))) ?>">Next</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
