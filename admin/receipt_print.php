<?php
/**
 * Admin — printable payment receipt
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$payment = get_payment((int) ($_GET['id'] ?? 0));
$ticket = $payment ? get_ticket_full((int) $payment['ticket_id']) : null;
if (!$payment || !$ticket) {
    flash_set('error', 'Payment not found.');
    redirect('admin/tickets.php');
}
$ticketId = (int) $ticket['id'];

$pageTitle = 'Receipt · ' . payment_receipt_number((int) $payment['id']);
$showSidebar = false;
$navVariant = 'app';
$bodyClass = 'quote-print-page';

require_once __DIR__ . '/../includes/header.php';
?>

<div class="quote-print-toolbar no-print">
    <a class="btn btn-rapid-outline btn-sm" href="<?= e(url('admin/ticket.php?id=' . $ticketId . '#billing')) ?>">Back to ticket</a>
    <button type="button" class="btn btn-rapid-primary btn-sm" onclick="window.print()">
        <i class="bi bi-printer" aria-hidden="true"></i> Print receipt
    </button>
</div>

<?php render_payment_receipt($ticket, $payment, ticket_billing($ticketId)); ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
