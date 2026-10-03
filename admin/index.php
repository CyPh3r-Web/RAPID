<?php
/**
 * Admin dashboard
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$user = current_user();
$pdo = db();

$totalTickets = (int) $pdo->query('SELECT COUNT(*) FROM repair_tickets')->fetchColumn();
$pending = (int) $pdo->query("SELECT COUNT(*) FROM repair_tickets WHERE current_status IN ('booking_submitted','received','quotation_pending','awaiting_approval')")->fetchColumn();
$inProgress = (int) $pdo->query("SELECT COUNT(*) FROM repair_tickets WHERE current_status IN ('diagnosing','approved','repairing')")->fetchColumn();
$completed = (int) $pdo->query("SELECT COUNT(*) FROM repair_tickets WHERE current_status = 'completed'")->fetchColumn();
$unassigned = (int) $pdo->query('SELECT COUNT(*) FROM repair_tickets WHERE assigned_technician_id IS NULL AND current_status NOT IN (\'completed\',\'cancelled\')')->fetchColumn();
$customers = (int) $pdo->query('SELECT COUNT(*) FROM customers')->fetchColumn();
$technicians = (int) $pdo->query('SELECT COUNT(*) FROM technicians')->fetchColumn();
$activeWarranties = (int) $pdo->query("SELECT COUNT(*) FROM warranties WHERE warranty_end >= CURDATE() AND warranty_status IN ('active','expiring_soon')")->fetchColumn();
$openClaims = (int) $pdo->query("SELECT COUNT(*) FROM warranty_claims WHERE claim_status NOT IN ('rejected','resolved')")->fetchColumn();

$statusCounts = $pdo->query('SELECT current_status, COUNT(*) FROM repair_tickets GROUP BY current_status')
    ->fetchAll(PDO::FETCH_KEY_PAIR);
$pipeline = [
    ['label' => 'Intake', 'statuses' => ['booking_submitted', 'received'], 'color' => '#94A3B8'],
    ['label' => 'Diagnosing', 'statuses' => ['diagnosing'], 'color' => '#3D85F0'],
    ['label' => 'Quotation', 'statuses' => ['quotation_pending', 'awaiting_approval'], 'color' => '#E3A008'],
    ['label' => 'Repairing', 'statuses' => ['approved', 'repairing'], 'color' => '#0062D9'],
    ['label' => 'Ready for pickup', 'statuses' => ['ready_for_pickup'], 'color' => '#0891B2'],
];
$pipelineTotal = 0;
foreach ($pipeline as &$stage) {
    $stage['count'] = 0;
    foreach ($stage['statuses'] as $s) {
        $stage['count'] += (int) ($statusCounts[$s] ?? 0);
    }
    $pipelineTotal += $stage['count'];
}
unset($stage);

// Open tickets that need an admin nudge; first matching reason wins.
$attention = $pdo->query(
    "SELECT * FROM (
        SELECT rt.id, rt.ticket_number, rt.current_status, rt.updated_at, rt.problem_description,
               d.brand, d.model, CONCAT(cu.first_name, ' ', cu.last_name) AS customer_name,
               CASE
                   WHEN rt.assigned_technician_id IS NULL THEN 'No technician assigned'
                   WHEN rt.estimated_completion IS NOT NULL AND rt.estimated_completion < NOW()
                        AND rt.current_status IN ('diagnosing', 'approved', 'repairing') THEN 'Past estimated completion'
                   WHEN rt.current_status = 'awaiting_approval' AND rt.updated_at < NOW() - INTERVAL 2 DAY THEN 'Quote unanswered 2+ days'
                   WHEN rt.current_status = 'ready_for_pickup' AND rt.updated_at < NOW() - INTERVAL 3 DAY THEN 'Not picked up 3+ days'
               END AS reason
        FROM repair_tickets rt
        INNER JOIN devices d ON d.id = rt.device_id
        INNER JOIN customers c ON c.id = rt.customer_id
        INNER JOIN users cu ON cu.id = c.user_id
        WHERE rt.current_status NOT IN ('completed', 'cancelled', 'declined')
     ) t
     WHERE reason IS NOT NULL
     ORDER BY updated_at ASC
     LIMIT 8"
)->fetchAll();

$board = $pdo->query(
    "SELECT rt.id, rt.ticket_number, rt.current_status, rt.problem_description, d.brand, d.model,
            CONCAT(cu.first_name,' ',cu.last_name) AS customer_name
     FROM repair_tickets rt
     INNER JOIN devices d ON d.id = rt.device_id
     INNER JOIN customers c ON c.id = rt.customer_id
     INNER JOIN users cu ON cu.id = c.user_id
     WHERE rt.current_status NOT IN ('cancelled')
     ORDER BY rt.updated_at DESC
     LIMIT 80"
)->fetchAll();

$pageTitle = 'Admin Dashboard';
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
                <h1>Administrator dashboard</h1>
                <p>Welcome, <?= e($user['first_name']) ?>. Monitor tickets and assign technicians.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a class="btn btn-rapid-primary btn-sm" href="<?= e(url('admin/tickets.php')) ?>">View all tickets</a>
                <a class="btn btn-rapid-outline btn-sm" href="<?= e(url('admin/reports.php')) ?>">Reports</a>
            </div>
        </div>

        <div class="metric-strip">
            <a href="<?= e(url('admin/tickets.php')) ?>"><div class="label">Total tickets</div><div class="value"><?= $totalTickets ?></div></a>
            <a href="<?= e(url('admin/tickets.php?status=booking_submitted')) ?>"><div class="label">Pending / intake</div><div class="value"><?= $pending ?></div></a>
            <div class="metric-item"><div class="label">In progress</div><div class="value"><?= $inProgress ?></div></div>
            <div class="metric-item"><div class="label">Completed</div><div class="value"><?= $completed ?></div></div>
            <a href="<?= e(url('admin/tickets.php')) ?>"><div class="label">Unassigned</div><div class="value"><?= $unassigned ?></div></a>
            <a href="<?= e(url('admin/customers.php')) ?>"><div class="label">Customers</div><div class="value"><?= $customers ?></div></a>
            <a href="<?= e(url('admin/technicians.php')) ?>"><div class="label">Technicians</div><div class="value"><?= $technicians ?></div></a>
            <a href="<?= e(url('admin/claims.php')) ?>"><div class="label">Active warranties</div><div class="value"><?= $activeWarranties ?></div></a>
            <a href="<?= e(url('admin/claims.php')) ?>"><div class="label">Open claims</div><div class="value"><?= $openClaims ?></div></a>
        </div>

        <section class="rapid-card pipeline-card mb-4" aria-labelledby="pipelineTitle">
            <div class="flex flex-wrap justify-between items-center gap-2 mb-3">
                <h2 id="pipelineTitle" class="text-lg mb-0">Repair pipeline</h2>
                <span class="text-sm text-rapid-muted"><?= (int) $pipelineTotal ?> open ticket<?= $pipelineTotal === 1 ? '' : 's' ?></span>
            </div>
            <?php if ($pipelineTotal > 0): ?>
                <div class="pipeline-bar" aria-hidden="true">
                    <?php foreach ($pipeline as $stage): ?>
                        <?php if ($stage['count'] > 0): ?>
                            <span style="flex: <?= (int) $stage['count'] ?>; background: <?= e($stage['color']) ?>" title="<?= e($stage['label']) ?>: <?= (int) $stage['count'] ?>"></span>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <div class="pipeline-legend">
                <?php foreach ($pipeline as $stage): ?>
                    <a class="pipeline-stage" href="<?= e(url('admin/tickets.php?status=' . $stage['statuses'][0])) ?>">
                        <span class="pipeline-label"><span class="pipeline-swatch" style="background: <?= e($stage['color']) ?>"></span><?= e($stage['label']) ?></span>
                        <span class="pipeline-count"><?= (int) $stage['count'] ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="rapid-card p-0 overflow-hidden mb-4" aria-labelledby="attentionTitle">
            <div class="flex justify-between items-center gap-2 px-4 py-3 border-b border-rapid-border">
                <h2 id="attentionTitle" class="text-lg mb-0">Needs attention</h2>
                <span class="text-sm text-rapid-muted"><?= count($attention) ?> ticket<?= count($attention) === 1 ? '' : 's' ?></span>
            </div>
            <?php if (!$attention): ?>
                <p class="px-4 py-5 mb-0 text-sm text-rapid-muted"><i class="bi bi-check-circle text-emerald-700" aria-hidden="true"></i> Nothing is blocked right now.</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="rapid-table mb-0">
                        <thead>
                            <tr>
                                <th>Ticket</th>
                                <th>Device</th>
                                <th>Customer</th>
                                <th>Status</th>
                                <th>Why</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($attention as $row): ?>
                                <tr>
                                    <td><?php render_ticket_number($row['ticket_number'], url('admin/ticket.php?id=' . (int) $row['id'])); ?></td>
                                    <td>
                                        <div class="font-semibold"><?= e($row['brand'] . ' ' . $row['model']) ?></div>
                                        <div class="text-sm text-rapid-muted problem-cell"><?= e($row['problem_description']) ?></div>
                                    </td>
                                    <td><?= e($row['customer_name']) ?></td>
                                    <td><span class="badge-status <?= e(status_badge_class($row['current_status'])) ?>"><?= e(status_label($row['current_status'])) ?></span></td>
                                    <td class="text-sm attention-reason"><?= e($row['reason']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <div class="rapid-card p-0 overflow-hidden mb-4">
            <div class="flex justify-between items-center px-4 py-3 border-b border-rapid-border">
                <h2 class="text-sm font-semibold text-rapid mb-0">Tickets by stage</h2>
                <a class="text-sm" href="<?= e(url('admin/tickets.php')) ?>">View all tickets</a>
            </div>
            <?php if (!$board): ?>
                <?php render_empty_state('No open tickets', 'New bookings will appear here.', url('admin/tickets.php'), 'View tickets', 'bi-kanban'); ?>
            <?php else: ?>
                <?php render_stage_table($board, 'admin/ticket.php'); ?>
            <?php endif; ?>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
