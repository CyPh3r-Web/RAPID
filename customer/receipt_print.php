<?php
/**
 * Customer — printable payment receipt (ownership enforced)
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

$payment = get_payment((int) ($_GET['id'] ?? 0));
$ticket = $payment ? get_customer_ticket((int) $payment['ticket_id'], $customerId) : null;
if (!$payment || !$ticket) {
    flash_set('error', 'Receipt not found or you do not have access.');
    redirect('customer/repairs.php');
}
$ticketId = (int) $ticket['id'];

// Customer ticket query has no name fields — use the logged-in customer.
$ticket['customer_first_name'] = (string) ($user['first_name'] ?? '');
$ticket['customer_last_name'] = (string) ($user['last_name'] ?? '');

$pageTitle = 'Receipt · ' . payment_receipt_number((int) $payment['id']);
$showSidebar = false;
$navVariant = 'app';
$bodyClass = 'quote-print-page';

require_once __DIR__ . '/../includes/header.php';
?>

<div class="quote-print-toolbar no-print">
    <a class="btn btn-rapid-outline btn-sm" href="<?= e(url('customer/ticket.php?id=' . $ticketId . '#billing')) ?>">Back to ticket</a>
    <button type="button" class="btn btn-rapid-primary btn-sm" onclick="window.print()">
        <i class="bi bi-printer" aria-hidden="true"></i> Print receipt
    </button>
</div>

<?php render_payment_receipt($ticket, $payment, ticket_billing($ticketId)); ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
