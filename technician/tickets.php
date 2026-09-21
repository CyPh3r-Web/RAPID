<?php
/**
 * Technician — assigned repairs list
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

$q = trim((string) ($_GET['q'] ?? ''));
$status = trim((string) ($_GET['status'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 15;
$offset = ($page - 1) * $perPage;

$where = ['rt.assigned_technician_id = ?'];
$params = [$technicianId];

if ($q !== '') {
    $where[] = '(rt.ticket_number LIKE ? OR cu.first_name LIKE ? OR cu.last_name LIKE ? OR d.brand LIKE ? OR d.model LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like, $like);
}
if ($status !== '' && isset(TICKET_STATUSES[$status])) {
    $where[] = 'rt.current_status = ?';
    $params[] = $status;
}

$whereSql = implode(' AND ', $where);

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
            CONCAT(cu.first_name, ' ', cu.last_name) AS customer_name
     FROM repair_tickets rt
     INNER JOIN devices d ON d.id = rt.device_id
     INNER JOIN customers c ON c.id = rt.customer_id
     INNER JOIN users cu ON cu.id = c.user_id
     WHERE $whereSql
     ORDER BY FIELD(rt.priority,'urgent','high','normal','low'), rt.created_at ASC
     LIMIT $perPage OFFSET $offset"
);
$listStmt->execute($params);
$tickets = $listStmt->fetchAll();

$pageTitle = 'Assigned Repairs';
$showSidebar = true;
$navVariant = 'app';
$activeNav = 'assigned';
$bodyClass = 'app-body';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="app-shell">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="app-main">
        <div class="page-header">
            <h1>Assigned repairs</h1>
            <p>Diagnose devices, issue quotations, and update repair progress one step at a time.</p>
        </div>

        <form method="get" class="rapid-card mb-3">
            <div class="grid grid-cols-1 md:grid-cols-12 gap-2 items-end">
                <div class="md:col-span-5">
                    <label class="form-label" for="q">Search</label>
                    <input type="text" class="form-control" id="q" name="q" value="<?= e($q) ?>" placeholder="Ticket, customer, device…">
                </div>
                <div class="md:col-span-4">
                    <label class="form-label" for="status">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">All</option>
                        <?php foreach (TICKET_STATUSES as $key => $label): ?>
                            <option value="<?= e($key) ?>" <?= $status === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="md:col-span-3 flex gap-2">
                    <button class="btn btn-rapid-primary grow" type="submit">Filter</button>
                    <a class="btn btn-outline-secondary" href="<?= e(url('technician/tickets.php')) ?>">Reset</a>
                </div>
            </div>
        </form>

        <div class="rapid-card p-0 overflow-hidden">
            <?php if (!$tickets): ?>
                <div class="empty-state">
                    <i class="bi bi-wrench-adjustable"></i>
                    <p class="mb-1 font-semibold text-rapid">No assigned repairs found.</p>
                    <p class="mb-0 text-sm">Tickets appear here after an administrator assigns them to you.</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="rapid-table mb-0">
                        <thead>
                            <tr>
                                <th>Ticket</th>
                                <th>Device</th>
                                <th>Customer</th>
                                <th>Status</th>
                                <th>Priority</th>
                                <th>Date</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tickets as $t): ?>
                                <tr>
                                    <td class="whitespace-nowrap">
                                        <?php render_ticket_number($t['ticket_number'], url('technician/ticket.php?id=' . (int) $t['id'])); ?>
                                    </td>
                                    <td><?= e($t['brand'] . ' ' . $t['model']) ?></td>
                                    <td><?= e($t['customer_name']) ?></td>
                                    <td><span class="badge-status <?= e(status_badge_class($t['current_status'])) ?>"><?= e(status_label($t['current_status'])) ?></span></td>
                                    <td><span class="badge-status <?= e(priority_badge_class($t['priority'])) ?>"><?= e(ucfirst($t['priority'])) ?></span></td>
                                    <td class="text-sm whitespace-nowrap"><?= e(format_datetime($t['created_at'], 'M j, Y')) ?></td>
                                    <td class="text-right whitespace-nowrap">
                                        <a class="btn btn-sm btn-rapid-outline" href="<?= e(url('technician/ticket.php?id=' . (int) $t['id'])) ?>">View</a>
                                        <?php if (!in_array($t['current_status'], ['completed', 'cancelled'], true)): ?>
                                            <a class="btn btn-sm btn-rapid-primary" href="<?= e(url('technician/ticket.php?id=' . (int) $t['id'] . '&process=1')) ?>">Process</a>
                                        <?php endif; ?>
                                    </td>
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
                            $qs = http_build_query(array_filter(['q' => $q, 'status' => $status]));
                            $base = url('technician/tickets.php') . ($qs !== '' ? '?' . $qs . '&' : '?');
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
