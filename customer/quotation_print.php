<?php
/**
 * Customer — printable RAPID quotation
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/quotation_document.php';
require_role('customer');

$user = current_user();
$customerId = get_customer_id_for_user((int) $user['id']);
if (!$customerId) {
    flash_set('error', 'Customer profile not found.');
    redirect('auth/logout.php');
}

$ticketId = (int) ($_GET['id'] ?? 0);
$ticket = $ticketId > 0 ? get_customer_ticket($ticketId, $customerId) : null;
if (!$ticket) {
    flash_set('error', 'Ticket not found or you do not have access.');
    redirect('customer/repairs.php');
}

$quotation = db()->prepare(
    'SELECT id, labor_cost, parts_cost, other_cost, total_amount, notes, status, valid_until, created_at
     FROM quotations WHERE ticket_id = ? ORDER BY id DESC LIMIT 1'
);
$quotation->execute([$ticketId]);
$quote = $quotation->fetch() ?: null;
if (!$quote) {
    flash_set('error', 'No quotation is available for this repair yet.');
    redirect('customer/ticket.php?id=' . $ticketId);
}
$quote['items'] = get_quotation_items((int) $quote['id']);

// Customer ticket query has no name fields — use the logged-in customer.
$ticket['customer_first_name'] = (string) ($user['first_name'] ?? '');
$ticket['customer_last_name'] = (string) ($user['last_name'] ?? '');
$ticket['customer_phone'] = (string) ($user['phone'] ?? '');

$diagnosis = db()->prepare('SELECT diagnosis, recommended_action FROM diagnoses WHERE ticket_id = ? ORDER BY id DESC LIMIT 1');
$diagnosis->execute([$ticketId]);
$diagnosisRow = $diagnosis->fetch() ?: null;

$autoPrint = isset($_GET['print']);
$pageTitle = 'Quotation · ' . $ticket['ticket_number'];
$showSidebar = false;
$navVariant = 'app';
$bodyClass = 'quote-print-page';

require_once __DIR__ . '/../includes/header.php';
?>

<div class="quote-print-toolbar no-print">
    <a class="btn btn-rapid-outline btn-sm" href="<?= e(url('customer/ticket.php?id=' . $ticketId)) ?>">Back to ticket</a>
    <button type="button" class="btn btn-rapid-primary btn-sm" onclick="window.print()">
        <i class="bi bi-printer" aria-hidden="true"></i> Print quotation
    </button>
</div>

<?php render_quotation_document($ticket, $quote, $diagnosisRow); ?>

<?php if ($autoPrint): ?>
<script>window.addEventListener('load', function () { window.print(); });</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
