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

// Running warranties, soonest to expire first (same source as the claims page).
$stmt = $pdo->prepare(
    'SELECT w.ticket_id, d.brand, d.model
     FROM warranties w
     INNER JOIN repair_tickets rt ON rt.id = w.ticket_id
     INNER JOIN devices d ON d.id = rt.device_id
     WHERE rt.customer_id = ? AND w.warranty_end >= CURDATE()
     ORDER BY w.warranty_end ASC
     LIMIT 4'
);
$stmt->execute([$customerId]);
$activeWarranties = [];
foreach ($stmt->fetchAll() as $row) {
    $w = get_warranty_for_ticket((int) $row['ticket_id']);
    if ($w) {
        $activeWarranties[] = $row + ['w' => $w];
    }
}
$recentTickets = $recent->fetchAll();

// Newest active repair gets the big progress card.
$stmt = $pdo->prepare(
    "SELECT rt.id, rt.ticket_number, rt.current_status, rt.estimated_completion, d.brand, d.model
     FROM repair_tickets rt
     INNER JOIN devices d ON d.id = rt.device_id
     WHERE rt.customer_id = ? AND rt.current_status IN ($in)
     ORDER BY rt.updated_at DESC, rt.id DESC
     LIMIT 1"
);
$stmt->execute([$customerId]);
$activeTicket = $stmt->fetch() ?: null;
$activeHistory = [];
if ($activeTicket) {
    $stmt = $pdo->prepare(
        'SELECT status, remarks, created_at FROM repair_status_history
         WHERE ticket_id = ? ORDER BY created_at ASC, id ASC'
    );
    $stmt->execute([(int) $activeTicket['id']]);
    $activeHistory = $stmt->fetchAll();
}

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

        <?php if ($activeTicket): ?>
            <?php $activeUrl = url('customer/ticket.php?id=' . (int) $activeTicket['id']); ?>
            <section class="rapid-card active-repair-card mb-4" aria-labelledby="activeRepairTitle">
                <div class="flex flex-wrap justify-between items-start gap-3 mb-5">
                    <div class="flex items-center gap-3">
                        <span class="active-repair-icon" aria-hidden="true"><i class="bi bi-phone"></i></span>
                        <div>
                            <div class="text-xs font-bold uppercase tracking-wider text-rapid-muted">Active repair</div>
                            <h2 id="activeRepairTitle" class="text-xl mb-0"><?= e($activeTicket['brand'] . ' ' . $activeTicket['model']) ?></h2>
                            <span class="ticket-mono text-sm"><?= e($activeTicket['ticket_number']) ?></span>
                        </div>
                    </div>
                    <span class="badge-status <?= e(status_badge_class($activeTicket['current_status'])) ?>">
                        <?= e(status_label($activeTicket['current_status'])) ?>
                    </span>
                </div>
                <?php render_public_stepper($activeTicket['current_status'], $activeHistory, $activeTicket['estimated_completion'] ?? null); ?>
                <a class="inline-block mt-5 text-sm font-bold" href="<?= e($activeUrl) ?>">Track this repair <span aria-hidden="true">→</span></a>
            </section>
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

        <div class="dash-split">
        <div class="rapid-card p-0 overflow-hidden dash-split-main">
            <div class="flex justify-between items-center px-4 py-3 border-b border-rapid-border">
                <h2 class="text-lg mb-0">Recent repairs</h2>
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
                <ul class="repair-list">
                    <?php foreach ($recentTickets as $t): ?>
                        <li>
                            <a class="repair-row" href="<?= e(url('customer/ticket.php?id=' . (int) $t['id'])) ?>">
                                <span class="repair-row-main">
                                    <strong><?= e($t['brand'] . ' ' . $t['model']) ?></strong>
                                    <span class="text-sm text-rapid-muted"><span class="ticket-mono"><?= e($t['ticket_number']) ?></span> · <?= e(format_datetime($t['created_at'], 'M j, Y')) ?></span>
                                </span>
                                <span class="badge-status <?= e(status_badge_class($t['current_status'])) ?>"><?= e(status_label($t['current_status'])) ?></span>
                                <i class="bi bi-chevron-right repair-row-chev" aria-hidden="true"></i>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <section class="rapid-card dash-split-side" aria-labelledby="warrantiesTitle">
            <div class="flex justify-between items-center gap-2 mb-4">
                <h2 id="warrantiesTitle" class="text-lg mb-0">Warranties</h2>
                <a class="text-sm font-bold" href="<?= e(url('customer/claims.php')) ?>">Claims</a>
            </div>
            <?php if (!$activeWarranties): ?>
                <p class="text-sm text-rapid-muted mb-0">No active warranties. Completed repairs start one automatically.</p>
            <?php else: ?>
                <ul class="load-list">
                    <?php foreach ($activeWarranties as $aw): ?>
                        <?php
                        $w = $aw['w'];
                        $left = max(0, (int) $w['remaining_days']);
                        $expiring = $w['computed_status'] === 'expiring_soon';
                        ?>
                        <li>
                            <div class="load-list-row">
                                <span class="font-bold"><?= e($aw['brand'] . ' ' . $aw['model']) ?></span>
                                <span class="<?= $expiring ? 'text-amber-800 font-bold' : 'text-rapid-muted' ?>"><?= $left ?> day<?= $left === 1 ? '' : 's' ?> left</span>
                            </div>
                            <div class="warranty-tile-bar" role="img" aria-label="<?= $left ?> of <?= (int) $w['warranty_days'] ?> warranty days left"><span class="<?= $expiring ? 'is-expiring' : '' ?>" style="width: <?= (int) round($left / max(1, (int) $w['warranty_days']) * 100) ?>%"></span></div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
