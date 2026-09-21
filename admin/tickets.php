<?php
/**
 * Admin — repair tickets list
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$q = trim((string) ($_GET['q'] ?? ''));
$status = trim((string) ($_GET['status'] ?? ''));
$technician = (int) ($_GET['technician'] ?? 0);
$priority = trim((string) ($_GET['priority'] ?? ''));
$warranty = trim((string) ($_GET['warranty'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 15;
$offset = ($page - 1) * $perPage;

$where = ['1=1'];
$params = [];

if ($q !== '') {
    $where[] = '(rt.ticket_number LIKE ? OR cu.first_name LIKE ? OR cu.last_name LIKE ? OR cu.phone LIKE ? OR d.brand LIKE ? OR d.model LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like, $like, $like);
}
if ($status !== '' && isset(TICKET_STATUSES[$status])) {
    $where[] = 'rt.current_status = ?';
    $params[] = $status;
}
if ($technician > 0) {
    $where[] = 'rt.assigned_technician_id = ?';
    $params[] = $technician;
}
if (in_array($priority, ['low', 'normal', 'high', 'urgent'], true)) {
    $where[] = 'rt.priority = ?';
    $params[] = $priority;
}
if ($warranty === 'active') {
    $where[] = "EXISTS (SELECT 1 FROM warranties w WHERE w.ticket_id = rt.id AND w.warranty_end >= CURDATE() AND w.warranty_status IN ('active','expiring_soon'))";
} elseif ($warranty === 'claim') {
    $where[] = "rt.problem_description LIKE '[Warranty claim for %'";
} elseif ($warranty === 'none') {
    $where[] = 'NOT EXISTS (SELECT 1 FROM warranties w WHERE w.ticket_id = rt.id)';
}

$whereSql = implode(' AND ', $where);
$technicians = list_active_technicians();

$countStmt = db()->prepare(
    "SELECT COUNT(*) FROM repair_tickets rt
     INNER JOIN devices d ON d.id = rt.device_id
     INNER JOIN customers c ON c.id = rt.customer_id
     INNER JOIN users cu ON cu.id = c.user_id
     WHERE $whereSql"
);
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($total / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

$listStmt = db()->prepare(
    "SELECT rt.id, rt.ticket_number, rt.current_status, rt.priority, rt.created_at,
            d.brand, d.model, d.device_type,
            CONCAT(cu.first_name, ' ', cu.last_name) AS customer_name,
            CONCAT(COALESCE(tu.first_name,''), ' ', COALESCE(tu.last_name,'')) AS technician_name
     FROM repair_tickets rt
     INNER JOIN devices d ON d.id = rt.device_id
     INNER JOIN customers c ON c.id = rt.customer_id
     INNER JOIN users cu ON cu.id = c.user_id
     LEFT JOIN technicians t ON t.id = rt.assigned_technician_id
     LEFT JOIN users tu ON tu.id = t.user_id
     WHERE $whereSql
     ORDER BY rt.created_at DESC, rt.id DESC
     LIMIT $perPage OFFSET $offset"
);
$listStmt->execute($params);
$tickets = $listStmt->fetchAll();

$pageTitle = 'Repair Tickets';
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
        <div class="page-header">
            <h1>Repair tickets</h1>
            <p>Search, filter, assign technicians, and monitor progress.</p>
        </div>

        <form method="get" class="filter-bar">
            <div>
                <label class="form-label" for="q">Search</label>
                <input type="text" class="form-control" id="q" name="q" value="<?= e($q) ?>" placeholder="Ticket, name, phone, device…">
            </div>
            <div>
                <label class="form-label" for="status">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">All</option>
                    <?php foreach (TICKET_STATUSES as $key => $label): ?>
                        <option value="<?= e($key) ?>" <?= $status === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="form-label" for="priority">Priority</label>
                <select class="form-select" id="priority" name="priority">
                    <option value="">All</option>
                    <?php foreach (['low','normal','high','urgent'] as $p): ?>
                        <option value="<?= e($p) ?>" <?= $priority === $p ? 'selected' : '' ?>><?= e(ucfirst($p)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="form-label" for="technician">Technician</label>
                <select class="form-select" id="technician" name="technician">
                    <option value="0">All</option>
                    <?php foreach ($technicians as $tech): ?>
                        <option value="<?= (int) $tech['id'] ?>" <?= $technician === (int) $tech['id'] ? 'selected' : '' ?>>
                            <?= e($tech['first_name'] . ' ' . $tech['last_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="form-label" for="warranty">Warranty</label>
                <select class="form-select" id="warranty" name="warranty">
                    <option value="">All</option>
                    <option value="active" <?= $warranty === 'active' ? 'selected' : '' ?>>Active warranty</option>
                    <option value="claim" <?= $warranty === 'claim' ? 'selected' : '' ?>>Warranty claim</option>
                    <option value="none" <?= $warranty === 'none' ? 'selected' : '' ?>>No warranty</option>
                </select>
            </div>
            <div class="flex gap-2">
                <button class="btn btn-rapid-primary" type="submit">Filter</button>
                <a class="btn btn-outline-secondary" href="<?= e(url('admin/tickets.php')) ?>">Reset</a>
            </div>
        </form>

        <div class="rapid-card p-0 overflow-hidden">
            <?php if (!$tickets): ?>
                <div class="empty-state">
                    <i class="bi bi-ticket-perforated"></i>
                    <p class="mb-0 font-semibold text-rapid">No tickets match your filters.</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="rapid-table">
                        <thead>
                            <tr>
                                <th>Ticket</th>
                                <th>Customer</th>
                                <th>Device</th>
                                <th>Status</th>
                                <th>Priority</th>
                                <th>Technician</th>
                                <th>Date</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tickets as $t): ?>
                                <tr>
                                    <td class="whitespace-nowrap">
                                        <?php render_ticket_number($t['ticket_number'], url('admin/ticket.php?id=' . (int) $t['id'])); ?>
                                    </td>
                                    <td><?= e($t['customer_name']) ?></td>
                                    <td><?= e($t['brand'] . ' ' . $t['model']) ?></td>
                                    <td><span class="badge-status <?= e(status_badge_class($t['current_status'])) ?>"><?= e(status_label($t['current_status'])) ?></span></td>
                                    <td><span class="badge-status <?= e(priority_badge_class($t['priority'])) ?>"><?= e(ucfirst($t['priority'])) ?></span></td>
                                    <td><?= e(trim($t['technician_name']) !== '' ? $t['technician_name'] : '—') ?></td>
                                    <td class="text-sm whitespace-nowrap"><?= e(format_datetime($t['created_at'], 'M j, Y')) ?></td>
                                    <td class="text-right"><a class="btn btn-sm btn-rapid-outline" href="<?= e(url('admin/ticket.php?id=' . (int) $t['id'])) ?>">Manage</a></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($totalPages > 1): ?>
                    <div class="flex justify-between items-center px-3 py-2 border-t border-rapid-border text-sm">
                        <span class="text-rapid-muted"><?= (int) $total ?> ticket(s)</span>
                        <nav>
                            <?php
                            $qs = http_build_query(array_filter([
                                'q' => $q,
                                'status' => $status,
                                'priority' => $priority,
                                'technician' => $technician ?: null,
                                'warranty' => $warranty !== '' ? $warranty : null,
                            ]));
                            $base = url('admin/tickets.php') . ($qs !== '' ? '?' . $qs . '&' : '?');
                            ?>
                            <?php if ($page > 1): ?><a class="btn btn-sm btn-outline-secondary" href="<?= e($base . 'page=' . ($page - 1)) ?>">Prev</a><?php endif; ?>
                            <span class="mx-2">Page <?= (int) $page ?> / <?= (int) $totalPages ?></span>
                            <?php if ($page < $totalPages): ?><a class="btn btn-sm btn-outline-secondary" href="<?= e($base . 'page=' . ($page + 1)) ?>">Next</a><?php endif; ?>
                        </nav>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
