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
            <p>Follow-up requests linked to your completed repairs.</p>
        </div>

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
