<?php
/**
 * Printable RAPID quotation document helpers.
 */

declare(strict_types=1);

/**
 * @return array{shop_name:string,shop_phone:string,shop_email:string,shop_hours:string,shop_address:string}
 */
function quotation_shop_profile(): array
{
    return [
        'shop_name' => (string) (get_setting('shop_name', SHOP_NAME) ?: SHOP_NAME),
        'shop_phone' => (string) (get_setting('shop_phone', '09101495174') ?: '09101495174'),
        'shop_email' => (string) (get_setting('shop_email', 'support@rapid.local') ?: 'support@rapid.local'),
        'shop_hours' => (string) (get_setting('shop_hours', 'Mon–Sat, 9:00 AM – 6:00 PM') ?: 'Mon–Sat, 9:00 AM – 6:00 PM'),
        'shop_address' => (string) (get_setting('shop_address', 'Esposado, Cannery Site, Polomolok, South Cotabato 9505')
            ?: 'Esposado, Cannery Site, Polomolok, South Cotabato 9505'),
    ];
}

/**
 * Render a print-ready RAPID quotation document.
 *
 * @param array<string,mixed> $ticket
 * @param array<string,mixed> $quote
 * @param array<string,mixed>|null $diagnosis
 */
function render_quotation_document(array $ticket, array $quote, ?array $diagnosis = null): void
{
    $shop = quotation_shop_profile();
    $items = $quote['items'] ?? null;
    if (!is_array($items) && !empty($quote['id'])) {
        $items = get_quotation_items((int) $quote['id']);
    }
    if (!is_array($items)) {
        $items = [];
    }

    $labor = (float) ($quote['labor_cost'] ?? 0);
    $parts = (float) ($quote['parts_cost'] ?? 0);
    $tax = (float) ($quote['other_cost'] ?? 0);
    $total = (float) ($quote['total_amount'] ?? ($labor + $parts + $tax));
    $status = (string) ($quote['status'] ?? 'pending');

    $customerName = trim(
        (string) ($ticket['customer_first_name'] ?? '') . ' ' . (string) ($ticket['customer_last_name'] ?? '')
    );
    if ($customerName === '') {
        $customerName = trim(
            (string) ($ticket['first_name'] ?? '') . ' ' . (string) ($ticket['last_name'] ?? '')
        );
    }
    $deviceLabel = trim(
        (string) ($ticket['brand'] ?? '') . ' ' . (string) ($ticket['model'] ?? '')
    );
    $ticketNumber = (string) ($ticket['ticket_number'] ?? '');
    $issued = !empty($quote['created_at']) ? format_datetime((string) $quote['created_at']) : format_datetime(date('Y-m-d H:i:s'));

    echo '<article class="quote-print-doc">';

    echo '<header class="quote-print-header">';
    echo '<div class="quote-print-brand">';
    echo '<img class="quote-print-logo" src="' . e(asset('images/rapid.png')) . '" alt="" width="72" height="72">';
    echo '<div>';
    echo '<p class="quote-print-kicker">' . e(APP_FULL_NAME) . '</p>';
    echo '<h1 class="quote-print-title">' . e(APP_NAME) . '</h1>';
    echo '<p class="quote-print-subtitle">Repair Quotation</p>';
    echo '</div></div>';
    echo '<div class="quote-print-shop">';
    echo '<strong>' . e($shop['shop_name']) . '</strong>';
    echo '<span>' . e($shop['shop_address']) . '</span>';
    echo '<span>' . e($shop['shop_phone']) . ' · ' . e($shop['shop_email']) . '</span>';
    echo '<span>' . e($shop['shop_hours']) . '</span>';
    echo '</div></header>';

    echo '<section class="quote-print-meta">';
    echo '<div><span class="label">Ticket</span><strong>' . e($ticketNumber) . '</strong></div>';
    echo '<div><span class="label">Issued</span><strong>' . e($issued) . '</strong></div>';
    echo '<div><span class="label">Status</span><strong>' . e(ucfirst($status)) . '</strong></div>';
    if (!empty($quote['valid_until'])) {
        echo '<div><span class="label">Valid until</span><strong>' . e(format_date((string) $quote['valid_until'])) . '</strong></div>';
    }
    echo '</section>';

    echo '<section class="quote-print-parties">';
    echo '<div><span class="label">Customer</span><strong>' . e($customerName !== '' ? $customerName : '—') . '</strong>';
    if (!empty($ticket['customer_phone']) || !empty($ticket['phone'])) {
        echo '<span>' . e((string) ($ticket['customer_phone'] ?? $ticket['phone'] ?? '')) . '</span>';
    }
    echo '</div>';
    echo '<div><span class="label">Device</span><strong>' . e($deviceLabel !== '' ? $deviceLabel : '—') . '</strong>';
    if (!empty($ticket['device_type'])) {
        echo '<span>' . e((string) $ticket['device_type']) . '</span>';
    }
    if (!empty($ticket['serial_number'])) {
        echo '<span>S/N ' . e((string) $ticket['serial_number']) . '</span>';
    }
    echo '</div></section>';

    if (!empty($ticket['problem_description'])) {
        echo '<section class="quote-print-block">';
        echo '<h2>Reported problem</h2>';
        echo '<p>' . nl2br(e((string) $ticket['problem_description'])) . '</p>';
        echo '</section>';
    }

    if ($diagnosis && trim((string) ($diagnosis['diagnosis'] ?? '')) !== '') {
        echo '<section class="quote-print-block">';
        echo '<h2>Diagnosis</h2>';
        echo '<p>' . nl2br(e((string) $diagnosis['diagnosis'])) . '</p>';
        if (!empty($diagnosis['recommended_action'])) {
            echo '<p><strong>Recommended:</strong> ' . nl2br(e((string) $diagnosis['recommended_action'])) . '</p>';
        }
        echo '</section>';
    }

    echo '<section class="quote-print-block">';
    echo '<h2>Quotation details</h2>';
    echo '<table class="quote-print-table">';
    echo '<thead><tr><th>Description</th><th class="num">Qty</th><th class="num">Unit price</th><th class="num">Amount</th></tr></thead>';
    echo '<tbody>';

    if ($items) {
        foreach ($items as $item) {
            $qty = (float) ($item['quantity'] ?? 0);
            $unit = (float) ($item['unit_price'] ?? 0);
            $line = (float) ($item['line_total'] ?? ($qty * $unit));
            $qtyLabel = rtrim(rtrim(number_format($qty, 2, '.', ''), '0'), '.') ?: '0';
            echo '<tr>';
            echo '<td>' . e((string) ($item['description'] ?? '')) . '</td>';
            echo '<td class="num">' . e($qtyLabel) . '</td>';
            echo '<td class="num">' . e(money_php($unit)) . '</td>';
            echo '<td class="num">' . e(money_php($line)) . '</td>';
            echo '</tr>';
        }
        echo '<tr class="sub"><td colspan="3">Parts subtotal</td><td class="num">' . e(money_php($parts)) . '</td></tr>';
    } elseif ($parts > 0) {
        echo '<tr><td>Parts</td><td class="num">—</td><td class="num">—</td><td class="num">' . e(money_php($parts)) . '</td></tr>';
    }

    echo '<tr><td>Labor</td><td class="num">—</td><td class="num">—</td><td class="num">' . e(money_php($labor)) . '</td></tr>';
    echo '<tr><td>Tax &amp; fees</td><td class="num">—</td><td class="num">—</td><td class="num">' . e(money_php($tax)) . '</td></tr>';
    echo '<tr class="total"><td colspan="3">Total</td><td class="num">' . e(money_php($total)) . '</td></tr>';
    echo '</tbody></table>';
    echo '</section>';

    if (!empty($quote['notes'])) {
        echo '<section class="quote-print-block">';
        echo '<h2>Notes</h2>';
        echo '<p>' . nl2br(e((string) $quote['notes'])) . '</p>';
        echo '</section>';
    }

    echo '<footer class="quote-print-footer">';
    echo '<p>This document is a <strong>' . e(APP_NAME) . '</strong> repair quotation. Amounts are in Philippine pesos (₱). ';
    echo 'Final work depends on customer approval and parts availability.</p>';
    echo '<p class="quote-print-sig">Customer signature: ____________________________ &nbsp;&nbsp; Date: ______________</p>';
    echo '</footer>';

    echo '</article>';
}
