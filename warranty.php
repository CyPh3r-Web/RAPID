<?php
/**
 * Public warranty certificate verification (QR target)
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';

ensure_warranty_certificate_schema();
$code = strtoupper(trim((string) ($_GET['code'] ?? '')));
$warranty = $code !== '' ? get_warranty_by_verify_code($code) : null;

$pageTitle = 'Warranty verify';
$showSidebar = false;
$bodyClass = 'quote-print-page';
$navVariant = 'public';

require_once __DIR__ . '/includes/header.php';
?>

<div class="quote-print-toolbar no-print">
    <a class="btn btn-rapid-outline btn-sm" href="<?= e(url('index.php')) ?>">Home</a>
    <a class="btn btn-rapid-outline btn-sm" href="<?= e(url('track.php')) ?>">Track repair</a>
</div>

<?php if (!$warranty): ?>
    <article class="quote-print-doc">
        <h1 class="quote-print-title" style="font-size:1.8rem"><?= e(APP_NAME) ?></h1>
        <p class="quote-print-subtitle">Warranty verification</p>
        <p>No warranty certificate matched that code. Check the QR or code on the printed certificate.</p>
    </article>
<?php else: ?>
    <?php
    $ticket = [
        'id' => (int) $warranty['ticket_id'],
        'ticket_number' => $warranty['ticket_number'],
        'brand' => $warranty['brand'],
        'model' => $warranty['model'],
        'serial_number' => $warranty['serial_number'] ?? '',
        'customer_first_name' => $warranty['customer_first_name'],
        'customer_last_name' => $warranty['customer_last_name'],
    ];
    render_warranty_certificate($ticket, $warranty);
    ?>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
