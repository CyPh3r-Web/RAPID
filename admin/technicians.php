<?php
/**
 * Admin — technicians list
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$q = trim((string) ($_GET['q'] ?? ''));
$status = trim((string) ($_GET['status'] ?? ''));

$where = ["u.role = 'technician'"];
$params = [];
if ($q !== '') {
    $where[] = '(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR t.specialization LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like);
}
if (in_array($status, ['active', 'inactive'], true)) {
    $where[] = 'u.status = ?';
    $params[] = $status;
}
$whereSql = implode(' AND ', $where);

$stmt = db()->prepare(
    "SELECT t.id, t.specialization, t.availability_status,
            u.first_name, u.last_name, u.email, u.phone, u.status,
            (SELECT COUNT(*) FROM repair_tickets rt WHERE rt.assigned_technician_id = t.id AND rt.current_status NOT IN ('completed','cancelled')) AS open_jobs
     FROM technicians t
     INNER JOIN users u ON u.id = t.user_id
     WHERE $whereSql
     ORDER BY u.first_name, u.last_name"
);
$stmt->execute($params);
$technicians = $stmt->fetchAll();

$pageTitle = 'Technicians';
$showSidebar = true;
$navVariant = 'app';
$activeNav = 'technicians';
$bodyClass = 'app-body';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="app-shell">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="app-main">
        <div class="page-header flex flex-wrap justify-between items-start gap-2">
            <div>
                <h1>Technicians</h1>
                <p>Manage technician accounts, specialization, and availability.</p>
            </div>
            <a class="btn btn-rapid-primary btn-sm" href="<?= e(url('admin/technician_form.php')) ?>">Add technician</a>
        </div>

        <form method="get" class="filter-bar">
            <div>
                <label class="form-label" for="q">Search</label>
                <input type="text" class="form-control" id="q" name="q" value="<?= e($q) ?>" placeholder="Name, email, specialization…">
            </div>
            <div>
                <label class="form-label" for="status">Account status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">All</option>
                    <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
            <div class="flex gap-2">
                <button class="btn btn-rapid-primary" type="submit">Filter</button>
                <a class="btn btn-outline-secondary" href="<?= e(url('admin/technicians.php')) ?>">Reset</a>
            </div>
        </form>

        <div class="rapid-card p-0 overflow-hidden">
            <?php if (!$technicians): ?>
                <div class="empty-state"><p class="mb-0">No technicians found.</p></div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="rapid-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Specialization</th>
                                <th>Availability</th>
                                <th>Open jobs</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($technicians as $t): ?>
                                <tr>
                                    <td>
                                        <div class="font-semibold"><?= e($t['first_name'] . ' ' . $t['last_name']) ?></div>
                                        <div class="text-sm text-rapid-muted"><?= e($t['email']) ?></div>
                                    </td>
                                    <td><?= e($t['specialization'] ?: '—') ?></td>
                                    <td class="capitalize"><?= e($t['availability_status']) ?></td>
                                    <td><?= (int) $t['open_jobs'] ?></td>
                                    <td>
                                        <span class="badge-status <?= $t['status'] === 'active' ? 'badge-status-success' : 'badge-status-muted' ?>">
                                            <?= e(ucfirst($t['status'])) ?>
                                        </span>
                                    </td>
                                    <td class="text-right">
                                        <a class="btn btn-sm btn-rapid-outline" href="<?= e(url('admin/technician_form.php?id=' . (int) $t['id'])) ?>">Edit</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
