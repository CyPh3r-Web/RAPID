<?php
/**
 * Technician — warranty claims (assigned or unassigned linked to my tickets)
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

$status = trim((string) ($_GET['status'] ?? ''));

$where = ['(wc.technician_id = ? OR (wc.technician_id IS NULL AND rt.assigned_technician_id = ?))'];
$params = [$technicianId, $technicianId];
if ($status !== '' && isset(CLAIM_STATUSES[$status])) {
    $where[] = 'wc.claim_status = ?';
    $params[] = $status;
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
            <p>Claims assigned to you or linked to your completed repairs.</p>
        </div>

        <form method="get" class="rapid-card mb-3">
            <div class="grid grid-cols-1 md:grid-cols-12 gap-2 items-end">
                <div class="md:col-span-6">
                    <label class="form-label" for="status">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">All</option>
                        <?php foreach (CLAIM_STATUSES as $key => $label): ?>
                            <option value="<?= e($key) ?>" <?= $status === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="md:col-span-6 flex gap-2">
                    <button class="btn btn-rapid-primary" type="submit">Filter</button>
                    <a class="btn btn-outline-secondary" href="<?= e(url('technician/claims.php')) ?>">Reset</a>
                </div>
            </div>
        </form>

        <div class="rapid-card p-0 overflow-hidden">
            <?php if (!$claims): ?>
                <div class="empty-state">
                    <i class="bi bi-shield-check"></i>
                    <p class="mb-0">No warranty claims in your queue.</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="rapid-table mb-0">
                        <thead>
                            <tr>
                                <th>Claim</th>
                                <th>Original ticket</th>
                                <th>Customer</th>
                                <th>Device</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($claims as $c): ?>
                                <tr>
                                    <td class="font-semibold">#<?= (int) $c['id'] ?></td>
                                    <td><?php render_ticket_number($c['ticket_number'], url('technician/ticket.php?id=' . (int) $c['original_ticket_id'])); ?></td>
                                    <td><?= e($c['customer_name']) ?></td>
                                    <td><?= e($c['brand'] . ' ' . $c['model']) ?></td>
                                    <td><span class="badge-status <?= e(claim_badge_class($c['claim_status'])) ?>"><?= e(claim_status_label($c['claim_status'])) ?></span></td>
                                    <td class="text-right"><a class="btn btn-sm btn-rapid-outline" href="<?= e(url('technician/claim.php?id=' . (int) $c['id'])) ?>">Open</a></td>
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
