<?php
/**
 * Admin — printable warranty certificate
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$ticketId = (int) ($_GET['id'] ?? 0);
$ticket = $ticketId > 0 ? get_ticket_full($ticketId) : null;
if (!$ticket) {
    flash_set('error', 'Ticket not found.');
    redirect('admin/tickets.php');
}

$warranty = get_warranty_for_ticket($ticketId);
if (!$warranty) {
    flash_set('error', 'No warranty certificate for this ticket yet.');
    redirect('admin/ticket.php?id=' . $ticketId);
}

$autoPrint = isset($_GET['print']);
$pageTitle = 'Warranty · ' . $ticket['ticket_number'];
$showSidebar = false;
$bodyClass = 'quote-print-page';

require_once __DIR__ . '/../includes/header.php';
?>

<div class="quote-print-toolbar no-print">
    <a class="btn btn-rapid-outline btn-sm" href="<?= e(url('admin/ticket.php?id=' . $ticketId)) ?>">Back</a>
    <button type="button" class="btn btn-rapid-primary btn-sm" onclick="window.print()">
        <i class="bi bi-printer" aria-hidden="true"></i> Print certificate
    </button>
</div>

<?php render_warranty_certificate($ticket, $warranty); ?>

<?php if ($autoPrint): ?>
<script>window.addEventListener('load', function () { window.print(); });</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
