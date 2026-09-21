<?php
/**
 * Admin — customers list
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$q = trim((string) ($_GET['q'] ?? ''));
$status = trim((string) ($_GET['status'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 15;
$offset = ($page - 1) * $perPage;

$where = ["u.role = 'customer'"];
$params = [];
if ($q !== '') {
    $where[] = '(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like);
}
if (in_array($status, ['active', 'inactive'], true)) {
    $where[] = 'u.status = ?';
    $params[] = $status;
}
$whereSql = implode(' AND ', $where);

$count = db()->prepare("SELECT COUNT(*) FROM customers c INNER JOIN users u ON u.id = c.user_id WHERE $whereSql");
$count->execute($params);
$total = (int) $count->fetchColumn();
$totalPages = max(1, (int) ceil($total / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

$stmt = db()->prepare(
    "SELECT c.id, c.address, u.first_name, u.last_name, u.email, u.phone, u.status, u.created_at,
            (SELECT COUNT(*) FROM repair_tickets rt WHERE rt.customer_id = c.id) AS ticket_count
     FROM customers c
     INNER JOIN users u ON u.id = c.user_id
     WHERE $whereSql
     ORDER BY u.created_at DESC
     LIMIT $perPage OFFSET $offset"
);
$stmt->execute($params);
$customers = $stmt->fetchAll();

$pageTitle = 'Customers';
$showSidebar = true;
$navVariant = 'app';
$activeNav = 'customers';
$bodyClass = 'app-body';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="app-shell">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="app-main">
        <div class="page-header flex flex-wrap justify-between items-start gap-2">
            <div>
                <h1>Customers</h1>
                <p>Search, view, and manage customer accounts.</p>
            </div>
            <a class="btn btn-rapid-primary btn-sm" href="<?= e(url('admin/customer_form.php')) ?>">Add customer</a>
        </div>

        <form method="get" class="filter-bar">
            <div>
                <label class="form-label" for="q">Search</label>
                <input type="text" class="form-control" id="q" name="q" value="<?= e($q) ?>" placeholder="Name, email, phone…">
            </div>
            <div>
                <label class="form-label" for="status">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">All</option>
                    <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
            <div class="flex gap-2">
                <button class="btn btn-rapid-primary" type="submit">Filter</button>
                <a class="btn btn-outline-secondary" href="<?= e(url('admin/customers.php')) ?>">Reset</a>
            </div>
        </form>

        <div class="rapid-card p-0 overflow-hidden">
            <?php if (!$customers): ?>
                <div class="empty-state"><p class="mb-0">No customers found.</p></div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="rapid-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Tickets</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($customers as $c): ?>
                                <tr>
                                    <td class="font-semibold"><?= e($c['first_name'] . ' ' . $c['last_name']) ?></td>
                                    <td><?= e($c['email']) ?></td>
                                    <td><?= e($c['phone'] ?: '—') ?></td>
                                    <td><?= (int) $c['ticket_count'] ?></td>
                                    <td>
                                        <span class="badge-status <?= $c['status'] === 'active' ? 'badge-status-success' : 'badge-status-muted' ?>">
                                            <?= e(ucfirst($c['status'])) ?>
                                        </span>
                                    </td>
                                    <td class="text-right whitespace-nowrap">
                                        <a class="btn btn-sm btn-rapid-outline" href="<?= e(url('admin/customer.php?id=' . (int) $c['id'])) ?>">View</a>
                                        <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('admin/customer_form.php?id=' . (int) $c['id'])) ?>">Edit</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($totalPages > 1): ?>
                    <div class="flex justify-between items-center px-3 py-2 border-t border-rapid-border text-sm">
                        <span class="text-rapid-muted"><?= $total ?> customer(s)</span>
                        <nav>
                            <?php
                            $qs = http_build_query(array_filter(['q' => $q, 'status' => $status]));
                            $base = url('admin/customers.php') . ($qs !== '' ? '?' . $qs . '&' : '?');
                            ?>
                            <?php if ($page > 1): ?><a class="btn btn-sm btn-outline-secondary" href="<?= e($base . 'page=' . ($page - 1)) ?>">Prev</a><?php endif; ?>
                            <span class="mx-2">Page <?= $page ?> / <?= $totalPages ?></span>
                            <?php if ($page < $totalPages): ?><a class="btn btn-sm btn-outline-secondary" href="<?= e($base . 'page=' . ($page + 1)) ?>">Next</a><?php endif; ?>
                        </nav>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
