<?php
/**
 * Customer — file warranty claim for a completed ticket
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

$ticketId = (int) ($_GET['ticket_id'] ?? 0);
$ticket = $ticketId > 0 ? get_customer_ticket($ticketId, $customerId) : null;
$warranty = $ticket ? get_warranty_for_ticket($ticketId) : null;

if (!$ticket || !$warranty) {
    flash_set('error', 'Warranty claim is not available for that ticket.');
    redirect('customer/repairs.php');
}

if (!$warranty['is_claimable']) {
    flash_set('error', 'This warranty has expired. Claims cannot be filed.');
    redirect('customer/ticket.php?id=' . $ticketId);
}

$error = '';
$issue = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $issue = trim((string) ($_POST['issue_description'] ?? ''));
    $result = file_warranty_claim($ticketId, $customerId, (int) $user['id'], $issue);
    if ($result['ok']) {
        flash_set('success', 'Warranty claim submitted.');
        redirect('customer/claim.php?id=' . (int) $result['claim_id']);
    }
    $error = $result['error'] ?? 'Could not submit claim.';
}

$pageTitle = 'File Warranty Claim';
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
            <h1>File warranty claim</h1>
            <p>Report the same issue during an active warranty period.</p>
        </div>

        <?php if ($error !== ''): ?>
            <div class="alert alert-danger"><?= e($error) ?></div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-3">
            <div class="lg:col-span-5">
                <div class="rapid-card mb-3">
                    <h2 class="text-sm font-semibold text-rapid mb-3">Original repair</h2>
                    <?php render_warranty_card($warranty, null, $ticket['ticket_number']); ?>
                    <dl class="detail-dl mb-0 mt-3 grid grid-cols-1 sm:grid-cols-[9rem_1fr] gap-x-4">
                        <dt>Device</dt>
                        <dd class="mb-0"><?= e($ticket['brand'] . ' ' . $ticket['model']) ?></dd>
                    </dl>
                </div>
            </div>
            <div class="lg:col-span-7">
                <form method="post" class="rapid-card" data-disable-on-submit>
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label" for="issue_description">Issue description <span class="text-red-700">*</span></label>
                        <textarea class="form-control" id="issue_description" name="issue_description" rows="5" required minlength="10"
                                  placeholder="Describe the recurring problem…"><?= e($issue) ?></textarea>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="submit" class="btn btn-rapid-primary">Submit claim</button>
                        <a class="btn btn-outline-secondary" href="<?= e(url('customer/ticket.php?id=' . $ticketId)) ?>">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
