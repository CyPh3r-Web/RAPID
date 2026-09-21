<?php
/**
 * Admin — customer detail
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$id = (int) ($_GET['id'] ?? 0);
$stmt = db()->prepare(
    'SELECT c.id, c.address, c.created_at, u.id AS user_id, u.first_name, u.last_name, u.email, u.phone, u.status
     FROM customers c INNER JOIN users u ON u.id = c.user_id WHERE c.id = ? LIMIT 1'
);
$stmt->execute([$id]);
$customer = $stmt->fetch();

if (!$customer) {
    flash_set('error', 'Customer not found.');
    redirect('admin/customers.php');
}

$tickets = db()->prepare(
    "SELECT rt.id, rt.ticket_number, rt.current_status, rt.created_at, d.brand, d.model
     FROM repair_tickets rt
     INNER JOIN devices d ON d.id = rt.device_id
     WHERE rt.customer_id = ?
     ORDER BY rt.created_at DESC LIMIT 20"
);
$tickets->execute([$id]);
$ticketRows = $tickets->fetchAll();

$pageTitle = $customer['first_name'] . ' ' . $customer['last_name'];
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
                <h1><?= e($customer['first_name'] . ' ' . $customer['last_name']) ?></h1>
                <p><?= e($customer['email']) ?></p>
            </div>
            <div class="flex gap-2">
                <a class="btn btn-rapid-outline btn-sm" href="<?= e(url('admin/customer_form.php?id=' . $id)) ?>">Edit</a>
                <a class="btn btn-outline-secondary btn-sm" href="<?= e(url('admin/customers.php')) ?>">Back</a>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-3">
            <div class="lg:col-span-5">
                <div class="rapid-card">
                    <h2 class="text-sm font-semibold text-rapid mb-3">Profile</h2>
                    <dl class="detail-dl grid grid-cols-1 sm:grid-cols-[9rem_1fr] gap-x-4 mb-0">
                        <dt>Phone</dt>
                        <dd><?= e($customer['phone'] ?: '—') ?></dd>
                        <dt>Address</dt>
                        <dd><?= e($customer['address'] ?: '—') ?></dd>
                        <dt>Status</dt>
                        <dd>
                            <span class="badge-status <?= $customer['status'] === 'active' ? 'badge-status-success' : 'badge-status-muted' ?>">
                                <?= e(ucfirst($customer['status'])) ?>
                            </span>
                        </dd>
                        <dt>Joined</dt>
                        <dd class="mb-0"><?= e(format_datetime($customer['created_at'])) ?></dd>
                    </dl>
                </div>
            </div>
            <div class="lg:col-span-7">
                <div class="rapid-card p-0 overflow-hidden">
                    <div class="px-3 py-3 border-b border-rapid-border"><h2 class="text-sm font-semibold text-rapid mb-0">Recent tickets</h2></div>
                    <?php if (!$ticketRows): ?>
                        <div class="empty-state py-4"><p class="mb-0 text-sm">No tickets yet.</p></div>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="rapid-table">
                                <thead><tr><th>Ticket</th><th>Device</th><th>Status</th><th></th></tr></thead>
                                <tbody>
                                    <?php foreach ($ticketRows as $t): ?>
                                        <tr>
                                            <td><?php render_ticket_number($t['ticket_number'], url('admin/ticket.php?id=' . (int) $t['id'])); ?></td>
                                            <td><?= e($t['brand'] . ' ' . $t['model']) ?></td>
                                            <td><span class="badge-status <?= e(status_badge_class($t['current_status'])) ?>"><?= e(status_label($t['current_status'])) ?></span></td>
                                            <td class="text-right"><a class="btn btn-sm btn-rapid-outline" href="<?= e(url('admin/ticket.php?id=' . (int) $t['id'])) ?>">Open</a></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
