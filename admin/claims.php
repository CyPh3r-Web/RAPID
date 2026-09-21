<?php
/**
 * Admin — warranty claims list
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$status = trim((string) ($_GET['status'] ?? ''));
$q = trim((string) ($_GET['q'] ?? ''));

$where = ['1=1'];
$params = [];
if ($status !== '' && isset(CLAIM_STATUSES[$status])) {
    $where[] = 'wc.claim_status = ?';
    $params[] = $status;
}
if ($q !== '') {
    $where[] = '(rt.ticket_number LIKE ? OR cu.first_name LIKE ? OR cu.last_name LIKE ? OR d.brand LIKE ? OR d.model LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like, $like);
}
$whereSql = implode(' AND ', $where);

$stmt = db()->prepare(
    "SELECT wc.id, wc.claim_status, wc.created_at, wc.original_ticket_id,
            rt.ticket_number, d.brand, d.model,
            CONCAT(cu.first_name,' ',cu.last_name) AS customer_name
     FROM warranty_claims wc
     INNER JOIN repair_tickets rt ON rt.id = wc.original_ticket_id
     INNER JOIN devices d ON d.id = wc.device_id
     INNER JOIN customers c ON c.id = wc.customer_id
     INNER JOIN users cu ON cu.id = c.user_id
     WHERE $whereSql
     ORDER BY wc.created_at DESC"
);
$stmt->execute($params);
$claims = $stmt->fetchAll();

$pageTitle = 'Warranty Claims';
$showSidebar = true;
$navVariant = 'app';
$activeNav = 'claims';
$bodyClass = 'app-body';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="app-shell">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="app-main">
        <div class="page-header">
            <h1>Warranty claims</h1>
            <p>Review and process customer warranty follow-ups.</p>
        </div>

        <form method="get" class="filter-bar">
            <div>
                <label class="form-label" for="q">Search</label>
                <input type="text" class="form-control" id="q" name="q" value="<?= e($q) ?>" placeholder="Ticket, customer, device…">
            </div>
            <div>
                <label class="form-label" for="status">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">All</option>
                    <?php foreach (CLAIM_STATUSES as $key => $label): ?>
                        <option value="<?= e($key) ?>" <?= $status === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex gap-2">
                <button class="btn btn-rapid-primary" type="submit">Filter</button>
                <a class="btn btn-outline-secondary" href="<?= e(url('admin/claims.php')) ?>">Reset</a>
            </div>
        </form>

        <div class="rapid-card p-0 overflow-hidden">
            <?php if (!$claims): ?>
                <div class="empty-state">
                    <i class="bi bi-shield-check"></i>
                    <p class="mb-0 font-semibold text-rapid">No warranty claims found.</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="rapid-table">
                        <thead>
                            <tr>
                                <th>Claim</th>
                                <th>Original ticket</th>
                                <th>Customer</th>
                                <th>Device</th>
                                <th>Status</th>
                                <th>Filed</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($claims as $c): ?>
                                <tr>
                                    <td class="font-semibold">#<?= (int) $c['id'] ?></td>
                                    <td><?php render_ticket_number($c['ticket_number'], url('admin/ticket.php?id=' . (int) $c['original_ticket_id'])); ?></td>
                                    <td><?= e($c['customer_name']) ?></td>
                                    <td><?= e($c['brand'] . ' ' . $c['model']) ?></td>
                                    <td><span class="badge-status <?= e(claim_badge_class($c['claim_status'])) ?>"><?= e(claim_status_label($c['claim_status'])) ?></span></td>
                                    <td class="text-sm whitespace-nowrap"><?= e(format_datetime($c['created_at'], 'M j, Y')) ?></td>
                                    <td class="text-right"><a class="btn btn-sm btn-rapid-outline" href="<?= e(url('admin/claim.php?id=' . (int) $c['id'])) ?>">Manage</a></td>
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
