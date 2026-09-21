<?php
/**
 * Technician dashboard
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

$pdo = db();
$countFor = function (string $sql) use ($pdo, $technicianId): int {
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$technicianId]);
    return (int) $stmt->fetchColumn();
};

$assigned = $countFor('SELECT COUNT(*) FROM repair_tickets WHERE assigned_technician_id = ? AND current_status NOT IN (\'completed\',\'cancelled\')');
$diagnosing = $countFor("SELECT COUNT(*) FROM repair_tickets WHERE assigned_technician_id = ? AND current_status IN ('received','diagnosing')");
$awaiting = $countFor("SELECT COUNT(*) FROM repair_tickets WHERE assigned_technician_id = ? AND current_status = 'awaiting_approval'");
$repairing = $countFor("SELECT COUNT(*) FROM repair_tickets WHERE assigned_technician_id = ? AND current_status IN ('approved','repairing')");
$ready = $countFor("SELECT COUNT(*) FROM repair_tickets WHERE assigned_technician_id = ? AND current_status = 'ready_for_pickup'");
$claimStmt = $pdo->prepare(
    "SELECT COUNT(*) FROM warranty_claims wc
     INNER JOIN repair_tickets rt ON rt.id = wc.original_ticket_id
     WHERE (wc.technician_id = ? OR (wc.technician_id IS NULL AND rt.assigned_technician_id = ?))
       AND wc.claim_status NOT IN ('rejected','resolved')"
);
$claimStmt->execute([$technicianId, $technicianId]);
$claimsOpen = (int) $claimStmt->fetchColumn();

$recent = $pdo->prepare(
    "SELECT rt.id, rt.ticket_number, rt.current_status, rt.priority, rt.created_at, d.brand, d.model,
            CONCAT(cu.first_name,' ',cu.last_name) AS customer_name
     FROM repair_tickets rt
     INNER JOIN devices d ON d.id = rt.device_id
     INNER JOIN customers c ON c.id = rt.customer_id
     INNER JOIN users cu ON cu.id = c.user_id
     WHERE rt.assigned_technician_id = ?
     ORDER BY FIELD(rt.priority,'urgent','high','normal','low'), rt.updated_at DESC
     LIMIT 8"
);
$recent->execute([$technicianId]);
$recentTickets = $recent->fetchAll();

$boardStmt = $pdo->prepare(
    "SELECT rt.id, rt.ticket_number, rt.current_status, rt.problem_description, d.brand, d.model,
            CONCAT(cu.first_name,' ',cu.last_name) AS customer_name
     FROM repair_tickets rt
     INNER JOIN devices d ON d.id = rt.device_id
     INNER JOIN customers c ON c.id = rt.customer_id
     INNER JOIN users cu ON cu.id = c.user_id
     WHERE rt.assigned_technician_id = ? AND rt.current_status NOT IN ('cancelled')
     ORDER BY rt.updated_at DESC
     LIMIT 60"
);
$boardStmt->execute([$technicianId]);
$board = $boardStmt->fetchAll();

$pageTitle = 'Technician Dashboard';
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
                <h1>Technician workspace</h1>
                <p>Welcome, <?= e($user['first_name']) ?>. Work your assigned repair queue.</p>
            </div>
            <a class="btn btn-rapid-primary btn-sm" href="<?= e(url('technician/tickets.php')) ?>">Assigned repairs</a>
        </div>

        <div class="metric-strip">
            <a href="<?= e(url('technician/tickets.php')) ?>">
                <div class="label">Assigned</div>
                <div class="value"><?= $assigned ?></div>
            </a>
            <a href="<?= e(url('technician/tickets.php?status=diagnosing')) ?>">
                <div class="label">Diagnosing</div>
                <div class="value"><?= $diagnosing ?></div>
            </a>
            <a href="<?= e(url('technician/tickets.php?status=awaiting_approval')) ?>">
                <div class="label">Awaiting approval</div>
                <div class="value"><?= $awaiting ?></div>
            </a>
            <a href="<?= e(url('technician/tickets.php?status=repairing')) ?>">
                <div class="label">Repairing</div>
                <div class="value"><?= $repairing ?></div>
            </a>
            <a href="<?= e(url('technician/tickets.php?status=ready_for_pickup')) ?>">
                <div class="label">Ready for pickup</div>
                <div class="value"><?= $ready ?></div>
            </a>
            <a href="<?= e(url('technician/claims.php')) ?>">
                <div class="label">Open claims</div>
                <div class="value"><?= $claimsOpen ?></div>
            </a>
        </div>

        <?php if ($board): ?>
            <div class="rapid-card p-0 overflow-hidden mb-4">
                <div class="flex justify-between items-center px-3 py-3 border-b border-rapid-border">
                    <h2 class="text-sm font-semibold text-rapid mb-0">Assigned work by stage</h2>
                    <a class="text-sm" href="<?= e(url('technician/tickets.php')) ?>">View all</a>
                </div>
                <?php render_stage_table($board, 'technician/ticket.php'); ?>
            </div>
        <?php endif; ?>

        <div class="rapid-card p-0 overflow-hidden">
            <div class="flex justify-between items-center px-3 py-3 border-b border-rapid-border">
                <h2 class="text-sm font-semibold text-rapid mb-0">Your queue</h2>
                <a class="text-sm" href="<?= e(url('technician/tickets.php')) ?>">View all</a>
            </div>
            <?php if (!$recentTickets): ?>
                <div class="empty-state">
                    <i class="bi bi-wrench-adjustable"></i>
                    <p class="mb-0">No tickets assigned yet.</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="rapid-table mb-0">
                        <thead><tr><th>Ticket</th><th>Device</th><th>Customer</th><th>Status</th><th>Priority</th><th></th></tr></thead>
                        <tbody>
                            <?php foreach ($recentTickets as $t): ?>
                                <tr>
                                    <td><?php render_ticket_number($t['ticket_number'], url('technician/ticket.php?id=' . (int) $t['id'])); ?></td>
                                    <td><?= e($t['brand'] . ' ' . $t['model']) ?></td>
                                    <td><?= e($t['customer_name']) ?></td>
                                    <td><span class="badge-status <?= e(status_badge_class($t['current_status'])) ?>"><?= e(status_label($t['current_status'])) ?></span></td>
                                    <td><span class="badge-status <?= e(priority_badge_class($t['priority'])) ?>"><?= e(ucfirst($t['priority'])) ?></span></td>
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
            <?php endif; ?>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
