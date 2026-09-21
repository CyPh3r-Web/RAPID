<?php
/**
 * Admin — warranty claim detail / update
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$user = current_user();
$claimId = (int) ($_GET['id'] ?? 0);
$claim = $claimId > 0 ? get_claim_full($claimId) : null;

if (!$claim) {
    flash_set('error', 'Claim not found.');
    redirect('admin/claims.php');
}

$technicians = list_active_technicians();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $newStatus = (string) ($_POST['claim_status'] ?? $claim['claim_status']);
    $resolution = trim((string) ($_POST['resolution'] ?? ''));
    $techId = (int) ($_POST['technician_id'] ?? 0);
    $result = update_warranty_claim(
        $claimId,
        (int) $user['id'],
        'admin',
        $newStatus,
        $resolution,
        $techId > 0 ? $techId : null
    );
    if ($result['ok']) {
        flash_set('success', 'Claim updated.');
        redirect('admin/claim.php?id=' . $claimId);
    }
    $error = $result['error'] ?? 'Update failed.';
    $claim = get_claim_full($claimId);
}

$warranty = get_warranty_for_ticket((int) $claim['original_ticket_id']);
$next = allowed_claim_transitions($claim['claim_status']);
$statusOptions = array_unique(array_merge([$claim['claim_status']], $next));

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
                <h1>Claim #<?= (int) $claim['id'] ?></h1>
                <p>Original ticket: <?php render_ticket_number($claim['ticket_number'], url('admin/ticket.php?id=' . (int) $claim['original_ticket_id'])); ?></p>
            </div>
            <div class="flex gap-2 items-center">
                <span class="badge-status <?= e(claim_badge_class($claim['claim_status'])) ?>"><?= e(claim_status_label($claim['claim_status'])) ?></span>
                <a class="btn btn-rapid-outline btn-sm" href="<?= e(url('admin/claims.php')) ?>">Back</a>
            </div>
        </div>

        <?php if ($error !== ''): ?>
            <div class="alert alert-danger"><?= e($error) ?></div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-3">
            <div class="lg:col-span-7">
                <div class="rapid-card mb-3">
                    <h2 class="text-sm font-semibold text-rapid mb-3">Claim &amp; customer</h2>
                    <dl class="detail-dl grid grid-cols-1 sm:grid-cols-[9rem_1fr] gap-x-4 mb-0">
                        <dt>Customer</dt>
                        <dd><?= e($claim['customer_first_name'] . ' ' . $claim['customer_last_name']) ?></dd>
                        <dt>Contact</dt>
                        <dd><?= e($claim['customer_phone'] ?: '—') ?> · <?= e($claim['customer_email']) ?></dd>
                        <dt>Device</dt>
                        <dd><?= e($claim['brand'] . ' ' . $claim['model']) ?></dd>
                        <dt>Issue</dt>
                        <dd><?= nl2br(e($claim['issue_description'])) ?></dd>
                        <dt>Original problem</dt>
                        <dd class="mb-0"><?= nl2br(e($claim['original_problem'])) ?></dd>
                    </dl>
                </div>
                <?php if ($warranty): ?>
                    <div class="rapid-card">
                        <h2 class="text-sm font-semibold text-rapid mb-2">Warranty</h2>
                        <p class="text-sm mb-0">
                            <?= (int) $warranty['warranty_days'] ?> days ·
                            <?= e(format_date($warranty['warranty_start'])) ?> – <?= e(format_date($warranty['warranty_end'])) ?> ·
                            <?= (int) $warranty['remaining_days'] ?> day(s) left ·
                            <span class="badge-status <?= e($warranty['computed_status'] === 'expired' ? 'badge-status-danger' : 'badge-status-success') ?>"><?= e($warranty['label']) ?></span>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
            <div class="lg:col-span-5">
                <div class="rapid-card">
                    <h2 class="text-sm font-semibold text-rapid mb-3">Update claim</h2>
                    <form method="post" data-disable-on-submit>
                        <?= csrf_field() ?>
                        <div class="mb-3">
                            <label class="form-label" for="claim_status">Status</label>
                            <select class="form-select" id="claim_status" name="claim_status" required>
                                <?php foreach ($statusOptions as $st): ?>
                                    <option value="<?= e($st) ?>" <?= $claim['claim_status'] === $st ? 'selected' : '' ?>><?= e(claim_status_label($st)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="technician_id">Technician</label>
                            <select class="form-select" id="technician_id" name="technician_id">
                                <option value="0">Unassigned</option>
                                <?php foreach ($technicians as $tech): ?>
                                    <option value="<?= (int) $tech['id'] ?>" <?= (int) $claim['technician_id'] === (int) $tech['id'] ? 'selected' : '' ?>>
                                        <?= e($tech['first_name'] . ' ' . $tech['last_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="resolution">Resolution / notes</label>
                            <textarea class="form-control" id="resolution" name="resolution" rows="4"><?= e((string) $claim['resolution']) ?></textarea>
                        </div>
                        <button type="submit" class="btn btn-rapid-primary w-full">Save changes</button>
                    </form>
                </div>
            </div>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
