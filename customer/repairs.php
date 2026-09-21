<?php
/**
 * Customer — My Repairs list
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_role('customer');

$user = current_user();
$customerId = get_customer_id_for_user((int) $user['id']);

if (!$customerId) {
    flash_set('error', 'Customer profile not found.');
    redirect('auth/logout.php');
}

$q = trim((string) ($_GET['q'] ?? ''));
$status = trim((string) ($_GET['status'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

$where = ['rt.customer_id = ?'];
$params = [$customerId];

if ($q !== '') {
    $where[] = '(rt.ticket_number LIKE ? OR d.brand LIKE ? OR d.model LIKE ? OR rt.problem_description LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like);
}

if ($status !== '' && isset(TICKET_STATUSES[$status])) {
    $where[] = 'rt.current_status = ?';
    $params[] = $status;
}

$whereSql = implode(' AND ', $where);

$countStmt = db()->prepare(
    "SELECT COUNT(*) FROM repair_tickets rt
     INNER JOIN devices d ON d.id = rt.device_id
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
    "SELECT rt.id, rt.ticket_number, rt.problem_description, rt.current_status, rt.priority, rt.created_at,
            d.brand, d.model, d.device_type
     FROM repair_tickets rt
     INNER JOIN devices d ON d.id = rt.device_id
     WHERE $whereSql
     ORDER BY rt.created_at DESC, rt.id DESC
     LIMIT $perPage OFFSET $offset"
);
$listStmt->execute($params);
$tickets = $listStmt->fetchAll();

$pageTitle = 'My Repairs';
$showSidebar = true;
$navVariant = 'app';
$activeNav = 'repairs';
$bodyClass = 'app-body';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="app-shell">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="app-main">
        <div class="page-header flex flex-wrap justify-between items-start gap-2">
            <div>
                <h1>My repairs</h1>
                <p>View and track all of your repair tickets.</p>
            </div>
            <a class="btn btn-rapid-primary btn-sm" href="<?= e(url('customer/book.php')) ?>">
                <i class="bi bi-plus-lg"></i> Book a repair
            </a>
        </div>

        <form method="get" class="rapid-card mb-3">
            <div class="grid grid-cols-1 md:grid-cols-12 gap-2 items-end">
                <div class="md:col-span-5">
                    <label class="form-label" for="q">Search</label>
                    <input type="text" class="form-control" id="q" name="q" value="<?= e($q) ?>"
                           placeholder="Ticket, brand, model…">
                </div>
                <div class="md:col-span-4">
                    <label class="form-label" for="status">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">All statuses</option>
                        <?php foreach (TICKET_STATUSES as $key => $label): ?>
                            <option value="<?= e($key) ?>" <?= $status === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="md:col-span-3 flex gap-2">
                    <button type="submit" class="btn btn-rapid-primary grow">Filter</button>
                    <a class="btn btn-outline-secondary" href="<?= e(url('customer/repairs.php')) ?>">Reset</a>
                </div>
            </div>
        </form>

        <div class="rapid-card p-0 overflow-hidden">
            <?php if (!$tickets): ?>
                <div class="empty-state">
                    <i class="bi bi-ticket-perforated"></i>
                    <p class="mb-1 font-semibold text-rapid">No repair tickets found.</p>
                    <p class="mb-3 text-sm">When you submit a repair request, it will appear here.</p>
                    <a class="btn btn-rapid-primary btn-sm" href="<?= e(url('customer/book.php')) ?>">Book a repair</a>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="rapid-table mb-0">
                        <thead>
                            <tr>
                                <th>Ticket</th>
                                <th>Device</th>
                                <th>Problem</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tickets as $t): ?>
                                <tr>
                                    <td class="whitespace-nowrap">
                                        <?php render_ticket_number($t['ticket_number'], url('customer/ticket.php?id=' . (int) $t['id'])); ?>
                                    </td>
                                    <td>
                                        <?= e($t['brand'] . ' ' . $t['model']) ?>
                                        <div class="text-sm text-rapid-muted"><?= e($t['device_type']) ?></div>
                                    </td>
                                    <td class="problem-cell"><?= e(strlen($t['problem_description']) > 80 ? substr($t['problem_description'], 0, 77) . '…' : $t['problem_description']) ?></td>
                                    <td>
                                        <span class="badge-status <?= e(status_badge_class($t['current_status'])) ?>">
                                            <?= e(status_label($t['current_status'])) ?>
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap text-sm"><?= e(format_datetime($t['created_at'], 'M j, Y')) ?></td>
                                    <td class="text-right">
                                        <a class="btn btn-sm btn-rapid-outline" href="<?= e(url('customer/ticket.php?id=' . (int) $t['id'])) ?>">View</a>
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
                            $base = url('customer/repairs.php') . ($qs !== '' ? '?' . $qs . '&' : '?');
                            ?>
                            <?php if ($page > 1): ?>
                                <a class="btn btn-sm btn-outline-secondary" href="<?= e($base . 'page=' . ($page - 1)) ?>">Prev</a>
                            <?php endif; ?>
                            <span class="mx-2">Page <?= (int) $page ?> / <?= (int) $totalPages ?></span>
                            <?php if ($page < $totalPages): ?>
                                <a class="btn btn-sm btn-outline-secondary" href="<?= e($base . 'page=' . ($page + 1)) ?>">Next</a>
                            <?php endif; ?>
                        </nav>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
