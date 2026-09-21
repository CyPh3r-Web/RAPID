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
