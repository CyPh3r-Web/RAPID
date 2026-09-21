<?php
/**
 * Customer — warranty claim detail
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

$claimId = (int) ($_GET['id'] ?? 0);
$claim = $claimId > 0 ? get_claim_full($claimId) : null;

if (!$claim || (int) $claim['customer_id'] !== $customerId) {
    flash_set('error', 'Claim not found.');
    redirect('customer/claims.php');
}

$warranty = get_warranty_for_ticket((int) $claim['original_ticket_id']);

$pageTitle = 'Claim #' . $claimId;
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
        <div class="page-header flex flex-wrap justify-between items-start gap-2">
            <div>
                <h1>Warranty claim #<?= (int) $claim['id'] ?></h1>
                <p>Original ticket: <?php render_ticket_number($claim['ticket_number'], url('customer/ticket.php?id=' . (int) $claim['original_ticket_id'])); ?></p>
            </div>
            <div class="flex gap-2 items-center">
                <span class="badge-status <?= e(claim_badge_class($claim['claim_status'])) ?>"><?= e(claim_status_label($claim['claim_status'])) ?></span>
                <a class="btn btn-rapid-outline btn-sm" href="<?= e(url('customer/claims.php')) ?>">Back</a>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-3">
            <div class="lg:col-span-7">
                <div class="rapid-card mb-3">
                    <h2 class="text-sm font-semibold text-rapid mb-3">Claim details</h2>
                    <dl class="detail-dl mb-0 grid grid-cols-1 sm:grid-cols-[9rem_1fr] gap-x-4">
                        <dt>Device</dt>
                        <dd><?= e($claim['brand'] . ' ' . $claim['model']) ?> (<?= e($claim['device_type']) ?>)</dd>
                        <dt>Issue</dt>
                        <dd><?= nl2br(e($claim['issue_description'])) ?></dd>
                        <dt>Filed</dt>
                        <dd><?= e(format_datetime($claim['created_at'])) ?></dd>
                        <dt>Technician</dt>
                        <dd><?= e(trim((string) $claim['technician_name']) !== '' ? $claim['technician_name'] : 'Pending assignment') ?></dd>
                        <dt>Resolution</dt>
                        <dd class="mb-0"><?= $claim['resolution'] ? nl2br(e($claim['resolution'])) : '—' ?></dd>
                    </dl>
                </div>
            </div>
            <div class="lg:col-span-5">
                <?php if ($warranty): ?>
                    <div class="rapid-card">
                        <h2 class="text-sm font-semibold text-rapid mb-3">Warranty</h2>
                        <p class="mb-1"><strong><?= (int) $warranty['warranty_days'] ?> days</strong></p>
                        <p class="text-sm mb-1">Started: <?= e(format_date($warranty['warranty_start'])) ?></p>
                        <p class="text-sm mb-1">Expires: <?= e(format_date($warranty['warranty_end'])) ?></p>
                        <p class="text-sm mb-1">Remaining: <?= (int) $warranty['remaining_days'] ?> day(s)</p>
                        <p class="mb-0">
                            <span class="badge-status <?= e($warranty['computed_status'] === 'expired' ? 'badge-status-danger' : ($warranty['computed_status'] === 'expiring_soon' ? 'badge-status-warning' : 'badge-status-success')) ?>">
                                <?= e($warranty['label']) ?>
                            </span>
                        </p>
                        <a class="btn btn-rapid-outline btn-sm mt-3" href="<?= e(url('customer/ticket.php?id=' . (int) $claim['original_ticket_id'])) ?>">View original ticket</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
