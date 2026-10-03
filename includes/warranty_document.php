<?php
/**
 * Digital RAPID warranty certificate helpers.
 */

declare(strict_types=1);

function ensure_warranty_certificate_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    try {
        $pdo = db();
        $col = $pdo->query("SHOW COLUMNS FROM `warranties` LIKE 'verify_code'")->fetch();
        if (!$col) {
            $pdo->exec(
                "ALTER TABLE `warranties`
                 ADD COLUMN `verify_code` VARCHAR(32) DEFAULT NULL AFTER `warranty_status`,
                 ADD UNIQUE KEY `uq_warranties_verify` (`verify_code`)"
            );
        }
        $done = true;
    } catch (Throwable $e) {
        error_log('ensure_warranty_certificate_schema failed: ' . $e->getMessage());
    }
}

function warranty_ensure_verify_code(int $warrantyId): string
{
    ensure_warranty_certificate_schema();
    $stmt = db()->prepare('SELECT verify_code FROM warranties WHERE id = ? LIMIT 1');
    $stmt->execute([$warrantyId]);
    $code = $stmt->fetchColumn();
    if (is_string($code) && $code !== '') {
        return $code;
    }
    $code = strtoupper(bin2hex(random_bytes(8)));
    db()->prepare('UPDATE warranties SET verify_code = ? WHERE id = ? AND (verify_code IS NULL OR verify_code = \'\')')
        ->execute([$code, $warrantyId]);
    $stmt->execute([$warrantyId]);
    $fresh = $stmt->fetchColumn();
    return is_string($fresh) && $fresh !== '' ? $fresh : $code;
}

/**
 * @return array<string,mixed>|null
 */
function get_warranty_by_verify_code(string $code): ?array
{
    ensure_warranty_certificate_schema();
    $code = strtoupper(trim($code));
    if ($code === '') {
        return null;
    }
    $stmt = db()->prepare(
        'SELECT w.*, rt.ticket_number, rt.customer_id, rt.device_id, rt.completed_at,
                d.device_type, d.brand, d.model, d.serial_number,
                cu.first_name AS customer_first_name, cu.last_name AS customer_last_name
         FROM warranties w
         INNER JOIN repair_tickets rt ON rt.id = w.ticket_id
         INNER JOIN devices d ON d.id = rt.device_id
         INNER JOIN customers c ON c.id = rt.customer_id
         INNER JOIN users cu ON cu.id = c.user_id
         WHERE w.verify_code = ?
         LIMIT 1'
    );
    $stmt->execute([$code]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }
    $state = compute_warranty_state($row);
    return array_merge($row, $state);
}

/**
 * @param array<string,mixed> $ticket
 * @param array<string,mixed> $warranty
 */
function render_warranty_certificate(array $ticket, array $warranty): void
{
    notify_ensure_settings();
    $shopName = (string) (get_setting('shop_name', SHOP_NAME) ?: SHOP_NAME);
    $shopPhone = (string) (get_setting('shop_phone', '') ?: '');
    $shopEmail = (string) (get_setting('shop_email', '') ?: '');
    $shopAddress = (string) (get_setting('shop_address', '') ?: '');

    $warrantyId = (int) ($warranty['id'] ?? 0);
    $code = $warrantyId > 0
        ? warranty_ensure_verify_code($warrantyId)
        : (string) ($warranty['verify_code'] ?? '');
    $verifyUrl = $code !== '' ? absolute_url('warranty.php?code=' . urlencode($code)) : '';
    $claimUrl = absolute_url('customer/claim_file.php?ticket_id=' . (int) ($ticket['id'] ?? 0));

    $customerName = trim(
        (string) ($ticket['customer_first_name'] ?? $warranty['customer_first_name'] ?? '') . ' ' .
        (string) ($ticket['customer_last_name'] ?? $warranty['customer_last_name'] ?? '')
    );
    $device = trim((string) ($ticket['brand'] ?? '') . ' ' . (string) ($ticket['model'] ?? ''));
    $state = compute_warranty_state($warranty);

    echo '<article class="quote-print-doc warranty-cert-doc">';
    echo '<header class="quote-print-header">';
    echo '<div class="quote-print-brand">';
    echo '<img class="quote-print-logo" src="' . e(asset('images/rapid.png')) . '" alt="" width="72" height="72">';
    echo '<div>';
    echo '<p class="quote-print-kicker">' . e(APP_FULL_NAME) . '</p>';
    echo '<h1 class="quote-print-title">' . e(APP_NAME) . '</h1>';
    echo '<p class="quote-print-subtitle">Digital Warranty Certificate</p>';
    echo '</div></div>';
    echo '<div class="quote-print-shop">';
    echo '<strong>' . e($shopName) . '</strong>';
    if ($shopAddress !== '') {
        echo '<span>' . e($shopAddress) . '</span>';
    }
    if ($shopPhone !== '' || $shopEmail !== '') {
        echo '<span>' . e(trim($shopPhone . ($shopPhone && $shopEmail ? ' · ' : '') . $shopEmail)) . '</span>';
    }
    echo '</div></header>';

    echo '<section class="quote-print-meta">';
    echo '<div><span class="label">Ticket</span><strong>' . e((string) ($ticket['ticket_number'] ?? '')) . '</strong></div>';
    echo '<div><span class="label">Status</span><strong>' . e((string) ($state['label'] ?? 'Active')) . '</strong></div>';
    echo '<div><span class="label">Starts</span><strong>' . e(format_date((string) $warranty['warranty_start'])) . '</strong></div>';
    echo '<div><span class="label">Ends</span><strong>' . e(format_date((string) $warranty['warranty_end'])) . '</strong></div>';
    echo '</section>';

    echo '<section class="quote-print-parties">';
    echo '<div><span class="label">Customer</span><strong>' . e($customerName !== '' ? $customerName : '—') . '</strong></div>';
    echo '<div><span class="label">Device</span><strong>' . e($device !== '' ? $device : '—') . '</strong>';
    if (!empty($ticket['serial_number']) || !empty($warranty['serial_number'])) {
        echo '<span>S/N ' . e((string) ($ticket['serial_number'] ?? $warranty['serial_number'])) . '</span>';
    }
    echo '</div></section>';

    echo '<section class="quote-print-block">';
    echo '<h2>Coverage</h2>';
    echo '<p>This certificate confirms a <strong>' . (int) ($warranty['warranty_days'] ?? 0) . '-day</strong> repair warranty ';
    echo 'for the work completed on ticket <strong>' . e((string) ($ticket['ticket_number'] ?? '')) . '</strong>. ';
    echo 'Coverage applies to the repaired issue under shop terms. Physical damage, liquid damage after pickup, and unrelated failures are not covered.</p>';
    echo '</section>';

    echo '<section class="warranty-cert-qr-row">';
    if ($verifyUrl !== '') {
        echo '<div class="warranty-cert-qr">';
        echo '<canvas class="ticket-qr" data-qr="' . e($verifyUrl) . '" width="148" height="148" aria-label="Warranty verification QR"></canvas>';
        echo '<p>Scan to verify</p>';
        echo '<code>' . e($code) . '</code>';
        echo '</div>';
    }
    echo '<div class="warranty-cert-qr">';
    echo '<canvas class="ticket-qr" data-qr="' . e($claimUrl) . '" width="148" height="148" aria-label="Warranty claim QR"></canvas>';
    echo '<p>Scan to file a claim</p>';
    echo '<span class="text-xs">Opens the RAPID claim form when signed in</span>';
    echo '</div>';
    echo '</section>';

    echo '<footer class="quote-print-footer">';
    echo '<p>Issued by <strong>' . e($shopName) . '</strong> via <strong>' . e(APP_NAME) . '</strong>. ';
    echo 'Keep this certificate for warranty service.</p>';
    echo '</footer>';
    echo '</article>';
}
