<?php
/**
 * Admin — printable RAPID quotation
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/quotation_document.php';
require_role('admin');

$ticketId = (int) ($_GET['id'] ?? 0);
$ticket = $ticketId > 0 ? get_ticket_full($ticketId) : null;
if (!$ticket) {
    flash_set('error', 'Ticket not found.');
    redirect('admin/tickets.php');
}

$quotation = db()->prepare('SELECT * FROM quotations WHERE ticket_id = ? ORDER BY id DESC LIMIT 1');
$quotation->execute([$ticketId]);
$quote = $quotation->fetch() ?: null;
if (!$quote) {
    flash_set('error', 'No quotation is available for this ticket yet.');
    redirect('admin/ticket.php?id=' . $ticketId);
}
$quote['items'] = get_quotation_items((int) $quote['id']);

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
    <a class="btn btn-rapid-outline btn-sm" href="<?= e(url('admin/ticket.php?id=' . $ticketId)) ?>">Back to ticket</a>
    <button type="button" class="btn btn-rapid-primary btn-sm" onclick="window.print()">
        <i class="bi bi-printer" aria-hidden="true"></i> Print quotation
    </button>
</div>

<?php render_quotation_document($ticket, $quote, $diagnosisRow); ?>

<?php if ($autoPrint): ?>
<script>window.addEventListener('load', function () { window.print(); });</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
