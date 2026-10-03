<?php
/**
 * Customer — warranty claims list
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

$stmt = db()->prepare(
    'SELECT wc.id, wc.claim_status, wc.created_at, wc.issue_description, wc.original_ticket_id,
            rt.ticket_number, d.brand, d.model
     FROM warranty_claims wc
     INNER JOIN repair_tickets rt ON rt.id = wc.original_ticket_id
     INNER JOIN devices d ON d.id = wc.device_id
     WHERE wc.customer_id = ?
     ORDER BY wc.created_at DESC'
);
$stmt->execute([$customerId]);
$claims = $stmt->fetchAll();

// Warranties still running, soonest to expire first.
$stmt = db()->prepare(
    'SELECT w.ticket_id, rt.ticket_number, d.brand, d.model
     FROM warranties w
     INNER JOIN repair_tickets rt ON rt.id = w.ticket_id
     INNER JOIN devices d ON d.id = rt.device_id
     WHERE rt.customer_id = ? AND w.warranty_end >= CURDATE()
     ORDER BY w.warranty_end ASC'
);
$stmt->execute([$customerId]);
$activeWarranties = [];
foreach ($stmt->fetchAll() as $row) {
    $w = get_warranty_for_ticket((int) $row['ticket_id']);
    if ($w) {
        $activeWarranties[] = $row + ['w' => $w];
    }
}

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
            <p>Problem came back after a repair? File a claim while the warranty is active.</p>
        </div>

        <?php if ($activeWarranties): ?>
            <h2 class="text-lg mb-3">Active warranties</h2>
            <div class="warranty-tiles mb-4">
                <?php foreach ($activeWarranties as $aw): ?>
                    <?php
                    $w = $aw['w'];
                    $days = max(1, (int) $w['warranty_days']);
                    $left = max(0, (int) $w['remaining_days']);
                    $expiring = $w['computed_status'] === 'expiring_soon';
                    ?>
                    <article class="rapid-card warranty-tile">
                        <div class="flex justify-between items-start gap-2">
                            <div>
                                <h3 class="text-base mb-0"><?= e($aw['brand'] . ' ' . $aw['model']) ?></h3>
                                <span class="ticket-mono text-sm"><?= e($aw['ticket_number']) ?></span>
                            </div>
                            <span class="badge-status <?= $expiring ? 'badge-status-warning' : 'badge-status-success' ?>"><?= e($w['label']) ?></span>
                        </div>
                        <p class="text-sm text-rapid-muted mb-0">
                            Until <?= e(format_date($w['warranty_end'])) ?> · <strong class="<?= $expiring ? 'text-amber-800' : 'text-rapid' ?>"><?= $left ?> day<?= $left === 1 ? '' : 's' ?> left</strong>
                        </p>
                        <div class="warranty-tile-bar" role="img" aria-label="<?= $left ?> of <?= $days ?> warranty days left">
                            <span class="<?= $expiring ? 'is-expiring' : '' ?>" style="width: <?= (int) round($left / $days * 100) ?>%"></span>
                        </div>
                        <?php if (!empty($w['is_claimable'])): ?>
                            <a class="btn btn-sm btn-rapid-outline self-start" href="<?= e(url('customer/claim_file.php?ticket_id=' . (int) $aw['ticket_id'])) ?>">File a claim</a>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
            <h2 class="text-lg mb-3">My claims</h2>
        <?php endif; ?>

        <div class="rapid-card p-0 overflow-hidden">
            <?php if (!$claims): ?>
                <div class="empty-state">
                    <i class="bi bi-shield-check"></i>
                    <p class="mb-1 font-semibold text-rapid">No warranty claims yet.</p>
                    <p class="mb-0 text-sm">If a completed repair is still under warranty, you can file a claim from the ticket page.</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="rapid-table mb-0">
                        <thead>
                            <tr>
                                <th>Claim</th>
                                <th>Original ticket</th>
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
                                    <td><?php render_ticket_number($c['ticket_number'], url('customer/ticket.php?id=' . (int) $c['original_ticket_id'])); ?></td>
                                    <td><?= e($c['brand'] . ' ' . $c['model']) ?></td>
                                    <td><span class="badge-status <?= e(claim_badge_class($c['claim_status'])) ?>"><?= e(claim_status_label($c['claim_status'])) ?></span></td>
                                    <td class="text-sm whitespace-nowrap"><?= e(format_datetime($c['created_at'], 'M j, Y')) ?></td>
                                    <td class="text-right"><a class="btn btn-sm btn-rapid-outline" href="<?= e(url('customer/claim.php?id=' . (int) $c['id'])) ?>">View</a></td>
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
