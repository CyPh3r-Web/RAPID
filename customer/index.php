<?php
/**
 * Customer dashboard
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

notify_expiring_warranties(10);

$pdo = db();

$activeStatuses = [
    'booking_submitted', 'received', 'diagnosing', 'quotation_pending',
    'awaiting_approval', 'approved', 'repairing', 'ready_for_pickup',
];
$in = "'" . implode("','", $activeStatuses) . "'";

$stmt = $pdo->prepare("SELECT COUNT(*) FROM repair_tickets WHERE customer_id = ? AND current_status IN ($in)");
$stmt->execute([$customerId]);
$activeCount = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM repair_tickets WHERE customer_id = ? AND current_status IN ('quotation_pending','awaiting_approval')"
);
$stmt->execute([$customerId]);
$pendingQuotes = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM repair_tickets WHERE customer_id = ? AND current_status = 'ready_for_pickup'"
);
$stmt->execute([$customerId]);
$readyCount = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM warranties w
     INNER JOIN repair_tickets rt ON rt.id = w.ticket_id
     WHERE rt.customer_id = ? AND w.warranty_end >= CURDATE() AND w.warranty_status IN ('active','expiring_soon')"
);
$stmt->execute([$customerId]);
$warrantyCount = (int) $stmt->fetchColumn();

$recent = $pdo->prepare(
    "SELECT rt.id, rt.ticket_number, rt.current_status, rt.created_at, d.brand, d.model
     FROM repair_tickets rt
     INNER JOIN devices d ON d.id = rt.device_id
     WHERE rt.customer_id = ?
     ORDER BY rt.created_at DESC, rt.id DESC
     LIMIT 5"
);
$recent->execute([$customerId]);
$recentTickets = $recent->fetchAll();

$pageTitle = 'Customer Dashboard';
$showSidebar = true;
$navVariant = 'app';
$activeNav = 'dashboard';
$bodyClass = 'app-body';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="app-shell">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="app-main">
        <div class="page-header flex flex-wrap justify-between items-start gap-2">
            <div>
                <h1>My RAPID portal</h1>
                <p>Welcome, <?= e($user['first_name']) ?>. Book repairs and track progress from here.</p>
            </div>
            <a class="btn btn-rapid-primary btn-sm" href="<?= e(url('customer/book.php')) ?>">
                <i class="bi bi-plus-lg"></i> Book a repair
            </a>
        </div>

        <?php if ($pendingQuotes > 0): ?>
            <div class="quote-banner" role="status">
                <span><i class="bi bi-hourglass-split" aria-hidden="true"></i> You have <?= (int) $pendingQuotes ?> quotation<?= $pendingQuotes === 1 ? '' : 's' ?> awaiting action</span>
                <a class="btn btn-sm btn-rapid-outline" href="<?= e(url('customer/repairs.php?status=awaiting_approval')) ?>">Review</a>
            </div>
        <?php endif; ?>

        <div class="metric-strip xl:grid-cols-4">
            <a href="<?= e(url('customer/repairs.php')) ?>">
                <div class="label">Active repairs</div>
                <div class="value"><?= (int) $activeCount ?></div>
            </a>
            <a href="<?= e(url('customer/repairs.php?status=awaiting_approval')) ?>">
                <div class="label">Pending quotations</div>
                <div class="value"><?= (int) $pendingQuotes ?></div>
            </a>
            <a href="<?= e(url('customer/repairs.php?status=ready_for_pickup')) ?>">
                <div class="label">Ready for pickup</div>
                <div class="value"><?= (int) $readyCount ?></div>
            </a>
            <div class="metric-item">
                <div class="label">Active warranties</div>
                <div class="value"><?= (int) $warrantyCount ?></div>
            </div>
        </div>

        <div class="rapid-card p-0 overflow-hidden">
            <div class="flex justify-between items-center px-3 py-3 border-b border-rapid-border">
                <h2 class="text-sm font-semibold text-rapid mb-0">Recent tickets</h2>
                <a class="text-sm" href="<?= e(url('customer/repairs.php')) ?>">View all</a>
            </div>
            <?php if (!$recentTickets): ?>
                <div class="empty-state">
                    <i class="bi bi-ticket-perforated"></i>
                    <p class="mb-1 font-semibold text-rapid">No repair tickets yet.</p>
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
                                <th>Status</th>
                                <th>Date</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentTickets as $t): ?>
                                <tr>
                                    <td><?php render_ticket_number($t['ticket_number'], url('customer/ticket.php?id=' . (int) $t['id'])); ?></td>
                                    <td><?= e($t['brand'] . ' ' . $t['model']) ?></td>
                                    <td>
                                        <span class="badge-status <?= e(status_badge_class($t['current_status'])) ?>">
                                            <?= e(status_label($t['current_status'])) ?>
                                        </span>
                                    </td>
                                    <td class="text-sm whitespace-nowrap"><?= e(format_datetime($t['created_at'], 'M j, Y')) ?></td>
                                    <td class="text-right">
                                        <a class="btn btn-sm btn-rapid-outline" href="<?= e(url('customer/ticket.php?id=' . (int) $t['id'])) ?>">View</a>
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
