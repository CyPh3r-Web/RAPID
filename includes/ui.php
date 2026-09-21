<?php
/**
 * Design-system helpers: tokens-backed markup for RAPID screens.
 */

declare(strict_types=1);

function money_php(float $amount): string
{
    return '₱' . number_format($amount, 2);
}

function media_angle_normalize(string $angle): string
{
    $angle = strtolower(trim($angle));
    return in_array($angle, ['front', 'back', 'screen', 'other'], true) ? $angle : 'other';
}

/**
 * @return array{angle:?string, name:string}
 */
function media_angle_from_name(string $fileName): array
{
    if (preg_match('/^\[(front|back|screen|other)\]\s*(.+)$/i', $fileName, $m)) {
        return ['angle' => strtolower($m[1]), 'name' => $m[2]];
    }
    return ['angle' => null, 'name' => $fileName];
}

function media_angle_label(?string $angle): string
{
    $map = ['front' => 'Front', 'back' => 'Back', 'screen' => 'Screen', 'other' => 'Other'];
    return $map[$angle ?? ''] ?? 'Photo';
}

function status_icon(string $status): string
{
    switch ($status) {
        case 'completed':
            return 'bi-check-circle-fill';
        case 'ready_for_pickup':
            return 'bi-box-seam';
        case 'repairing':
            return 'bi-wrench-adjustable';
        case 'diagnosing':
            return 'bi-search';
        case 'received':
        case 'booking_submitted':
            return 'bi-inbox';
        case 'quotation_pending':
        case 'awaiting_approval':
            return 'bi-hourglass-split';
        case 'approved':
            return 'bi-hand-thumbs-up';
        case 'declined':
        case 'cancelled':
            return 'bi-x-circle';
        default:
            return 'bi-dot';
    }
}

function render_status_badge(string $status): void
{
    echo '<span class="badge-status ' . e(status_badge_class($status)) . '">';
    echo '<i class="bi ' . e(status_icon($status)) . '" aria-hidden="true"></i>';
    echo e(status_label($status));
    echo '</span>';
}

/**
 * Customer-facing 5-stage tracker (collapses internal workflow).
 *
 * @return array<string, array{label:string, icon:string, statuses:string[]}>
 */
function public_progress_stages(): array
{
    return [
        'received' => [
            'label' => 'Received',
            'icon' => 'bi-inbox',
            'statuses' => ['booking_submitted', 'received'],
        ],
        'diagnosing' => [
            'label' => 'Diagnosing',
            'icon' => 'bi-search',
            'statuses' => ['diagnosing', 'quotation_pending', 'awaiting_approval', 'approved'],
        ],
        'repairing' => [
            'label' => 'Repairing',
            'icon' => 'bi-wrench-adjustable',
            'statuses' => ['repairing'],
        ],
        'ready_for_pickup' => [
            'label' => 'Ready for Pickup',
            'icon' => 'bi-box-seam',
            'statuses' => ['ready_for_pickup'],
        ],
        'completed' => [
            'label' => 'Completed',
            'icon' => 'bi-check-circle',
            'statuses' => ['completed'],
        ],
    ];
}

function public_stage_key(string $status): string
{
    foreach (public_progress_stages() as $key => $stage) {
        if (in_array($status, $stage['statuses'], true)) {
            return $key;
        }
    }
    return $status;
}

/**
 * @param list<array{status:string, remarks?:string, created_at:string}> $historyRows
 */
function public_stage_timestamp(array $historyRows, array $mappedStatuses): ?string
{
    $earliest = null;
    foreach ($historyRows as $row) {
        if (!in_array($row['status'], $mappedStatuses, true)) {
            continue;
        }
        if ($earliest === null || strcmp((string) $row['created_at'], $earliest) < 0) {
            $earliest = (string) $row['created_at'];
        }
    }
    return $earliest;
}

function eta_remaining_label(?string $estimatedCompletion): ?string
{
    if ($estimatedCompletion === null || $estimatedCompletion === '' || $estimatedCompletion === '0000-00-00 00:00:00') {
        return null;
    }
    $ts = strtotime($estimatedCompletion);
    if ($ts === false) {
        return null;
    }
    $diff = $ts - time();
    if ($diff <= 0) {
        return 'Estimate elapsed · ' . date('M j, g:i A', $ts);
    }
    $hours = (int) ceil($diff / 3600);
    if ($hours < 24) {
        return 'Est. ' . $hours . ' hour' . ($hours === 1 ? '' : 's') . ' remaining';
    }
    $days = (int) ceil($hours / 24);
    return 'Est. ' . $days . ' day' . ($days === 1 ? '' : 's') . ' remaining';
}

/**
 * Horizontal on desktop, vertical on mobile.
 *
 * @param list<array{status:string, remarks?:string, created_at:string}> $historyRows
 */
function render_public_stepper(string $currentStatus, array $historyRows, ?string $estimatedCompletion = null): void
{
    $stages = public_progress_stages();
    $keys = array_keys($stages);
    $currentKey = public_stage_key($currentStatus);
    $currentIdx = array_search($currentKey, $keys, true);
    if ($currentIdx === false) {
        $currentIdx = -1;
    }
    $eta = ($currentKey !== 'completed' && $currentKey !== 'ready_for_pickup')
        ? eta_remaining_label($estimatedCompletion)
        : null;
    $isException = in_array($currentStatus, ['cancelled', 'declined'], true);

    echo '<ol class="progress-stepper" aria-label="Repair progress">';
    foreach ($keys as $idx => $key) {
        $stage = $stages[$key];
        $stamp = public_stage_timestamp($historyRows, $stage['statuses']);
        $isCurrent = !$isException && $key === $currentKey;
        $isDone = $currentIdx >= 0 && $idx < $currentIdx;
        $state = $isCurrent ? 'is-current' : ($isDone || $stamp ? 'is-done' : 'is-pending');
        echo '<li class="progress-step ' . $state . '">';
        echo '<span class="progress-step-marker" aria-hidden="true"><i class="bi ' . e($isDone || $stamp && !$isCurrent ? 'bi-check-lg' : $stage['icon']) . '"></i></span>';
        echo '<div class="progress-step-body">';
        echo '<div class="progress-step-label">' . e($stage['label']) . '</div>';
        echo '<div class="progress-step-meta">';
        if ($stamp) {
            echo e(format_datetime($stamp));
        } elseif ($isCurrent) {
            echo 'Current stage';
            if ($eta) {
                echo ' · ' . e($eta);
            }
        } else {
            echo 'Pending';
        }
        echo '</div></div></li>';
    }
    echo '</ol>';

    if ($isException) {
        echo '<p class="progress-exception"><i class="bi ' . e(status_icon($currentStatus)) . '" aria-hidden="true"></i> ';
        echo e(status_label($currentStatus));
        echo '</p>';
    }
}

function get_empty_state_svg(string $icon): string
{
    $svgs = [
        'bi-inbox' => '<svg width="80" height="80" viewBox="0 0 80 80" fill="none" xmlns="http://www.w3.org/2000/svg">
            <rect x="10" y="20" width="60" height="45" rx="6" fill="#E2E8F0" stroke="#CBD5E1" stroke-width="2"/>
            <path d="M10 35L35 50L40 47L45 50L70 35" stroke="#94A3B8" stroke-width="2" stroke-linecap="round"/>
            <rect x="25" y="8" width="30" height="20" rx="4" fill="#F1F5F9" stroke="#CBD5E1" stroke-width="2"/>
            <line x1="32" y1="14" x2="48" y2="14" stroke="#94A3B8" stroke-width="2" stroke-linecap="round"/>
            <line x1="32" y1="20" x2="42" y2="20" stroke="#94A3B8" stroke-width="2" stroke-linecap="round"/>
        </svg>',
        'bi-ticket-perforated' => '<svg width="80" height="80" viewBox="0 0 80 80" fill="none" xmlns="http://www.w3.org/2000/svg">
            <rect x="10" y="20" width="60" height="40" rx="6" fill="#E2E8F0" stroke="#CBD5E1" stroke-width="2"/>
            <circle cx="10" cy="40" r="6" fill="#F8FAFC"/>
            <circle cx="70" cy="40" r="6" fill="#F8FAFC"/>
            <line x1="20" y1="30" x2="40" y2="30" stroke="#94A3B8" stroke-width="2" stroke-linecap="round"/>
            <line x1="20" y1="38" x2="50" y2="38" stroke="#94A3B8" stroke-width="2" stroke-linecap="round"/>
            <line x1="20" y1="46" x2="35" y2="46" stroke="#94A3B8" stroke-width="2" stroke-linecap="round"/>
            <rect x="50" y="42" width="14" height="10" rx="2" fill="#091C39" opacity="0.2"/>
        </svg>',
        'bi-people' => '<svg width="80" height="80" viewBox="0 0 80 80" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle cx="30" cy="28" r="10" fill="#E2E8F0" stroke="#CBD5E1" stroke-width="2"/>
            <path d="M10 58C10 48 18 42 30 42C42 42 50 48 50 58" stroke="#CBD5E1" stroke-width="2" fill="#F1F5F9"/>
            <circle cx="52" cy="32" r="8" fill="#E2E8F0" stroke="#CBD5E1" stroke-width="2"/>
            <path d="M38 58C38 50 44 46 52 46C60 46 66 50 66 58" stroke="#CBD5E1" stroke-width="2" fill="#F1F5F9"/>
        </svg>',
        'bi-wrench-adjustable' => '<svg width="80" height="80" viewBox="0 0 80 80" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M55 25C55 16.7 48.3 10 40 10C31.7 10 25 16.7 25 25C25 31.4 29 36.8 34.6 39L20 55L25 60L40 44C48 44 55 37 55 25Z" fill="#E2E8F0" stroke="#CBD5E1" stroke-width="2"/>
            <circle cx="40" cy="25" r="8" fill="#F1F5F9" stroke="#94A3B8" stroke-width="2"/>
            <line x1="22" y1="58" x2="28" y2="52" stroke="#94A3B8" stroke-width="3" stroke-linecap="round"/>
            <rect x="45" y="50" width="20" height="12" rx="3" fill="#F1F5F9" stroke="#CBD5E1" stroke-width="2"/>
            <line x1="50" y1="56" x2="60" y2="56" stroke="#94A3B8" stroke-width="2" stroke-linecap="round"/>
        </svg>',
        'bi-shield-check' => '<svg width="80" height="80" viewBox="0 0 80 80" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M40 8L12 20V38C12 54 24 66 40 72C56 66 68 54 68 38V20L40 8Z" fill="#E2E8F0" stroke="#CBD5E1" stroke-width="2"/>
            <path d="M30 40L37 47L52 32" stroke="#091C39" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" opacity="0.5"/>
        </svg>',
        'bi-bell' => '<svg width="80" height="80" viewBox="0 0 80 80" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M40 10C28 10 20 20 20 32V48L14 56H66L60 48V32C60 20 52 10 40 10Z" fill="#E2E8F0" stroke="#CBD5E1" stroke-width="2"/>
            <ellipse cx="40" cy="64" rx="8" ry="6" fill="#F1F5F9" stroke="#CBD5E1" stroke-width="2"/>
            <circle cx="54" cy="18" r="8" fill="#F43F5E" opacity="0.3"/>
        </svg>',
        'bi-kanban' => '<svg width="80" height="80" viewBox="0 0 80 80" fill="none" xmlns="http://www.w3.org/2000/svg">
            <rect x="8" y="12" width="18" height="56" rx="4" fill="#E2E8F0" stroke="#CBD5E1" stroke-width="2"/>
            <rect x="31" y="12" width="18" height="56" rx="4" fill="#E2E8F0" stroke="#CBD5E1" stroke-width="2"/>
            <rect x="54" y="12" width="18" height="56" rx="4" fill="#E2E8F0" stroke="#CBD5E1" stroke-width="2"/>
            <rect x="12" y="20" width="10" height="14" rx="2" fill="#091C39" opacity="0.3"/>
            <rect x="12" y="38" width="10" height="10" rx="2" fill="#091C39" opacity="0.2"/>
            <rect x="35" y="20" width="10" height="18" rx="2" fill="#F59E0B" opacity="0.3"/>
            <rect x="58" y="20" width="10" height="12" rx="2" fill="#10B981" opacity="0.3"/>
        </svg>',
        'default' => '<svg width="80" height="80" viewBox="0 0 80 80" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle cx="40" cy="40" r="28" fill="#E2E8F0" stroke="#CBD5E1" stroke-width="2"/>
            <circle cx="40" cy="36" r="8" fill="#F1F5F9" stroke="#94A3B8" stroke-width="2"/>
            <path d="M26 54C26 48 32 44 40 44C48 44 54 48 54 54" stroke="#94A3B8" stroke-width="2" stroke-linecap="round"/>
        </svg>'
    ];
    
    return $svgs[$icon] ?? $svgs['default'];
}

function render_empty_state(string $title, string $body = '', ?string $actionHref = null, ?string $actionLabel = null, string $icon = 'bi-inbox'): void
{
    echo '<div class="empty-state">';
    echo '<div class="empty-state-icon" aria-hidden="true">';
    echo get_empty_state_svg($icon);
    echo '</div>';
    echo '<p class="empty-state-title">' . e($title) . '</p>';
    if ($body !== '') {
        echo '<p class="empty-state-body">' . e($body) . '</p>';
    }
    if ($actionHref && $actionLabel) {
        echo '<a class="btn btn-rapid-primary btn-sm" href="' . e($actionHref) . '">' . e($actionLabel) . '</a>';
    }
    echo '</div>';
}

function render_error_state(string $message, string $title = 'Something went wrong'): void
{
    echo '<div class="state-error" role="alert">';
    echo '<i class="bi bi-exclamation-triangle" aria-hidden="true"></i>';
    echo '<div><strong>' . e($title) . '</strong>';
    echo '<p>' . e($message) . '</p></div>';
    echo '</div>';
}

function render_loading_state(string $label = 'Loading…'): void
{
    echo '<div class="state-loading" role="status" aria-live="polite">';
    echo '<span class="skeleton-spinner" aria-hidden="true"></span>';
    echo '<span>' . e($label) . '</span>';
    echo '</div>';
}

function render_ticket_copy_btn(string $ticketNumber): void
{
    echo '<button type="button" class="ticket-copy-btn" data-copy-btn="' . e($ticketNumber) . '" title="Copy ticket number" aria-label="Copy ticket number ' . e($ticketNumber) . '">';
    echo '<i class="bi bi-clipboard" aria-hidden="true"></i>';
    echo '</button>';
}

/**
 * Ticket number with a per-row copy icon.
 *
 * @param array{class?:string, tag?:string, heading?:bool} $opts
 */
function render_ticket_number(string $ticketNumber, ?string $href = null, array $opts = []): void
{
    $numberClass = $opts['class'] ?? 'ticket-mono';
    $tag = $opts['tag'] ?? 'span';
    $heading = !empty($opts['heading']);
    $wrapTag = $heading ? 'div' : 'span';
    $wrapClass = 'ticket-number-cell' . ($heading ? ' ticket-number-heading' : '');
    $safeTag = in_array($tag, ['span', 'strong', 'h1', 'div'], true) ? $tag : 'span';

    echo '<' . $wrapTag . ' class="' . e($wrapClass) . '">';
    if ($href !== null && $href !== '') {
        echo '<a class="' . e($numberClass) . '" href="' . e($href) . '">' . e($ticketNumber) . '</a>';
    } else {
        echo '<' . $safeTag . ' class="' . e($numberClass) . '">' . e($ticketNumber) . '</' . $safeTag . '>';
    }
    render_ticket_copy_btn($ticketNumber);
    echo '</' . $wrapTag . '>';
}

function render_ticket_id_block(string $ticketNumber, bool $showQr = true): void
{
    echo '<div class="ticket-id-block">';
    echo '<div class="ticket-id-kicker">Ticket number</div>';
    echo '<div class="ticket-id-row">';
    echo '<div class="ticket-id-code ticket-mono" data-copy="' . e($ticketNumber) . '">' . e($ticketNumber) . '</div>';
    echo '<button type="button" class="btn btn-rapid-outline btn-sm" data-copy-btn="' . e($ticketNumber) . '" aria-label="Copy ticket number">';
    echo '<i class="bi bi-clipboard" aria-hidden="true"></i> Copy</button>';
    echo '</div>';
    if ($showQr) {
        echo '<div class="ticket-qr-wrap">';
        echo '<canvas class="ticket-qr" data-qr="' . e($ticketNumber) . '" width="128" height="128" aria-label="QR code for ' . e($ticketNumber) . '"></canvas>';
        echo '<span class="ticket-qr-caption">Scan to look up</span>';
        echo '</div>';
    }
    echo '</div>';
}

function render_notify_channels(string $context = 'Status changes notify the customer'): void
{
    echo '<div class="notify-channels" title="' . e($context) . '">';
    echo '<span class="notify-chip" title="In-app notification"><i class="bi bi-bell" aria-hidden="true"></i> In-app</span>';
    echo '<span class="notify-chip" title="Email notification queued"><i class="bi bi-envelope" aria-hidden="true"></i> Email</span>';
    echo '<span class="notify-chip" title="SMS notification queued"><i class="bi bi-phone" aria-hidden="true"></i> SMS</span>';
    echo '</div>';
}

/**
 * @param array<string, mixed> $quote
 */
function render_quotation_card(array $quote, bool $canRespond = false): void
{
    $status = (string) ($quote['status'] ?? 'pending');
    $labor = (float) ($quote['labor_cost'] ?? 0);
    $parts = (float) ($quote['parts_cost'] ?? 0);
    $tax = (float) ($quote['other_cost'] ?? 0);
    $total = (float) ($quote['total_amount'] ?? ($labor + $parts + $tax));
    $badge = $status === 'approved' ? 'success' : ($status === 'declined' ? 'danger' : 'warning');

    echo '<div class="quote-card">';
    echo '<div class="quote-card-head">';
    echo '<h2 class="text-sm font-semibold m-0">Repair quotation</h2>';
    echo '<span class="badge-status badge-status-' . $badge . '">';
    echo '<i class="bi ' . e($status === 'approved' ? 'bi-check-circle-fill' : ($status === 'declined' ? 'bi-x-circle' : 'bi-hourglass-split')) . '" aria-hidden="true"></i>';
    echo e(ucfirst($status));
    echo '</span></div>';

    echo '<table class="quote-table"><tbody>';
    echo '<tr><th>Parts</th><td>' . e(money_php($parts)) . '</td></tr>';
    echo '<tr><th>Labor</th><td>' . e(money_php($labor)) . '</td></tr>';
    echo '<tr><th>Tax &amp; fees</th><td>' . e(money_php($tax)) . '</td></tr>';
    echo '<tr class="quote-total"><th>Total</th><td>' . e(money_php($total)) . '</td></tr>';
    echo '</tbody></table>';

    if (!empty($quote['notes'])) {
        echo '<p class="quote-notes">' . nl2br(e((string) $quote['notes'])) . '</p>';
    }
    if (!empty($quote['valid_until'])) {
        echo '<p class="quote-valid">Valid until ' . e(format_date((string) $quote['valid_until'])) . '</p>';
    }

    if ($canRespond && $status === 'pending') {
        echo '<div class="quote-actions">';
        echo '<button type="button" class="btn btn-outline-secondary" data-quote-decide="declined">Decline</button>';
        echo '<button type="button" class="btn btn-rapid-primary" data-quote-decide="approved">Approve quotation</button>';
        echo '</div>';
        echo '<form method="post" class="hidden" id="quoteRespondForm" data-disable-on-submit>';
        echo csrf_field();
        echo '<input type="hidden" name="action" value="quotation_response">';
        echo '<input type="hidden" name="decision" id="quoteDecision" value="">';
        echo '<input type="hidden" name="decline_note" id="quoteNote" value="">';
        echo '</form>';
    }
    echo '</div>';
}

/**
 * @param array<string, mixed> $warranty
 */
function render_warranty_card(array $warranty, ?string $claimHref = null, ?string $originalTicket = null): void
{
    $daysTotal = max(1, (int) ($warranty['warranty_days'] ?? 30));
    $remaining = (int) ($warranty['remaining_days'] ?? 0);
    if (!isset($warranty['remaining_days'])) {
        $end = strtotime((string) ($warranty['warranty_end'] ?? ''));
        $today = strtotime(date('Y-m-d'));
        $remaining = $end ? max(0, (int) ceil(($end - $today) / 86400)) : 0;
    }
    $status = (string) ($warranty['computed_status'] ?? $warranty['warranty_status'] ?? 'active');
    $claimable = array_key_exists('is_claimable', $warranty)
        ? (bool) $warranty['is_claimable']
        : ($remaining > 0 && $status !== 'expired');
    $ringPct = (int) round(min(100, max(0, ($remaining / $daysTotal) * 100)));
    $wBadge = $status === 'expired' ? 'muted' : ($status === 'expiring_soon' ? 'warning' : 'success');
    $wLabel = (string) ($warranty['label'] ?? ucwords(str_replace('_', ' ', $status)));

    echo '<div class="warranty-card">';
    echo '<div class="warranty-ring" style="--p:' . $ringPct . '" role="img" aria-label="' . $remaining . ' days remaining of ' . $daysTotal . '">';
    echo '<div class="warranty-ring-inner">';
    echo '<strong>' . $remaining . '</strong>';
    echo '<span>days left</span>';
    echo '</div></div>';
    echo '<div class="warranty-body">';
    echo '<span class="badge-status badge-status-' . $wBadge . '"><i class="bi bi-shield-check" aria-hidden="true"></i> ';
    echo e($wLabel);
    echo '</span>';
    echo '<p class="warranty-window">' . e(format_date((string) $warranty['warranty_start'])) . ' – ' . e(format_date((string) $warranty['warranty_end']));
    echo ' · ' . $daysTotal . '-day window</p>';
    if ($originalTicket) {
        echo '<p class="warranty-linked">Linked to original ticket ';
        render_ticket_number($originalTicket);
        echo '</p>';
    }
    if ($claimHref && $claimable) {
        echo '<a class="btn btn-rapid-primary btn-sm" href="' . e($claimHref) . '">File a warranty claim</a>';
    } elseif (!$claimable) {
        echo '<p class="text-sm text-rapid-muted mb-0">This warranty window has ended.</p>';
    }
    echo '</div></div>';
}

function render_kanban(array $tickets, string $detailPath): void
{
    render_stage_table($tickets, $detailPath);
}

function render_stage_table(array $tickets, string $detailPath): void
{
    $cols = public_progress_stages();
    $rows = [];
    $counts = ['active' => 0];
    foreach (array_keys($cols) as $key) {
        $counts[$key] = 0;
    }

    foreach ($tickets as $t) {
        $key = public_stage_key((string) $t['current_status']);
        if (!isset($cols[$key])) {
            continue;
        }
        $t['_stage'] = $key;
        $rows[] = $t;
        $counts[$key]++;
        if ($key !== 'completed') {
            $counts['active']++;
        }
    }

    if ($rows === []) {
        echo '<p class="stage-empty">No tickets in this view.</p>';
        return;
    }

    static $boardSeq = 0;
    $boardSeq++;
    $filterName = 'stage-filter-' . $boardSeq;

    $pills = [
        'active' => ['label' => 'Active', 'icon' => 'bi-lightning-charge'],
    ] + $cols;

    echo '<div class="stage-board" data-stage-board>';
    echo '<div class="stage-toolbar">';
    echo '<p class="stage-toolbar-label">Filter by stage</p>';
    echo '<div class="stage-pills" role="tablist" aria-label="Filter by stage">';

    $first = true;
    foreach ($pills as $key => $stage) {
        $id = $filterName . '-' . $key;
        echo '<input class="stage-filter-input" type="radio" name="' . e($filterName) . '" id="' . e($id) . '" value="' . e($key) . '" data-stage-filter="' . e($key) . '"';
        if ($first) {
            echo ' checked';
        }
        echo '>';
        echo '<label class="stage-pill" for="' . e($id) . '">';
        echo '<i class="bi ' . e($stage['icon']) . '" aria-hidden="true"></i>';
        echo '<span>' . e($stage['label']) . '</span>';
        echo '<span class="stage-pill-count">' . (int) $counts[$key] . '</span>';
        echo '</label>';
        $first = false;
    }
    echo '</div></div>';

    echo '<div class="stage-table-wrap">';
    echo '<table class="rapid-table stage-table mb-0">';
    echo '<thead><tr><th>Ticket</th><th>Customer</th><th>Device</th><th>Status</th><th></th></tr></thead><tbody>';

    foreach ($rows as $t) {
        $href = url($detailPath . '?id=' . (int) $t['id']);
        $isClaim = !empty($t['problem_description']) && is_warranty_claim_ticket((string) $t['problem_description']);
        $stage = (string) $t['_stage'];

        echo '<tr class="stage-row" data-stage="' . e($stage) . '">';
        echo '<td><span class="ticket-number-cell">';
        echo '<a class="stage-link" href="' . e($href) . '"><span class="stage-ticket">' . e((string) $t['ticket_number']) . '</span>';
        if ($isClaim || !empty($t['warranty_flag'])) {
            echo ' <span class="badge-status badge-status-warning">Claim</span>';
        }
        echo '</a>';
        render_ticket_copy_btn((string) $t['ticket_number']);
        echo '</span></td>';
        echo '<td>' . e((string) ($t['customer_name'] ?? '—')) . '</td>';
        echo '<td>' . e(trim(($t['brand'] ?? '') . ' ' . ($t['model'] ?? ''))) . '</td>';
        echo '<td>';
        render_status_badge((string) $t['current_status']);
        echo '</td>';
        echo '<td class="stage-open-cell"><a class="stage-open" href="' . e($href) . '">Open <i class="bi bi-chevron-right" aria-hidden="true"></i></a></td>';
        echo '</tr>';
    }

    echo '</tbody></table></div>';
    echo '<p class="stage-empty">No tickets in this stage.</p>';
    echo '</div>';
}

function render_before_after_media(array $mediaRows): void
{
    $before = [];
    $after = [];
    foreach ($mediaRows as $m) {
        if (($m['media_category'] ?? '') === 'before_repair') {
            $before[] = $m;
        } elseif (($m['media_category'] ?? '') === 'after_repair') {
            $after[] = $m;
        }
    }

    echo '<div class="media-compare">';
    echo '<div><h3>Before</h3>';
    if (!$before) {
        echo '<p class="text-sm text-rapid-muted mb-0">No before-repair media.</p>';
    } else {
        echo '<div class="media-grid">';
        foreach ($before as $m) {
            render_media_tile($m);
        }
        echo '</div>';
    }
    echo '</div><div><h3>After</h3>';
    if (!$after) {
        echo '<p class="text-sm text-rapid-muted mb-0">No after-repair media yet.</p>';
    } else {
        echo '<div class="media-grid">';
        foreach ($after as $m) {
            render_media_tile($m);
        }
        echo '</div>';
    }
    echo '</div></div>';
}

function render_media_tile(array $m): void
{
    $parsed = media_angle_from_name((string) $m['file_name']);
    echo '<div class="media-item">';
    if ($parsed['angle']) {
        echo '<span class="media-angle-tag">' . e(media_angle_label($parsed['angle'])) . '</span>';
    }
    if (is_image_mime((string) $m['file_type'])) {
        echo '<a href="' . e(media_public_url($m['file_path'])) . '" target="_blank" rel="noopener">';
        echo '<img src="' . e(media_public_url($m['file_path'])) . '" alt="' . e($parsed['name']) . '">';
        echo '</a>';
    } elseif (is_video_mime((string) $m['file_type'])) {
        echo '<video controls preload="metadata" src="' . e(media_public_url($m['file_path'])) . '"></video>';
    }
    echo '<div class="media-caption">' . e(ucfirst(str_replace('_', ' ', (string) ($m['media_category'] ?? '')))) . ' · ' . e($parsed['name']) . '</div>';
    echo '</div>';
}

function is_warranty_claim_ticket(string $problemDescription): bool
{
    return stripos($problemDescription, '[Warranty claim for ') === 0;
}

function warranty_claim_original_from_problem(string $problemDescription): ?string
{
    if (preg_match('/^\[Warranty claim for (RPR-\d{4}-\d{6})\]/i', $problemDescription, $m)) {
        return strtoupper($m[1]);
    }
    return null;
}

/**
 * @return list<array<string, mixed>>
 */
function get_recent_notifications(int $userId, int $limit = 8): array
{
    $limit = max(1, min(20, $limit));
    try {
        $stmt = db()->prepare(
            "SELECT id, ticket_id, title, message, is_read, created_at
             FROM notifications WHERE user_id = ?
             ORDER BY created_at DESC, id DESC LIMIT $limit"
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll() ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

function unread_notification_count(int $userId): int
{
    try {
        $stmt = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

function normalize_phone(string $phone): string
{
    return preg_replace('/\D+/', '', $phone) ?? '';
}

function contacts_match(string $input, string $email, string $phone): bool
{
    $input = trim($input);
    if ($input === '') {
        return false;
    }
    if (strcasecmp($input, $email) === 0) {
        return true;
    }
    $in = normalize_phone($input);
    $ph = normalize_phone($phone);
    if ($in === '' || $ph === '') {
        return false;
    }
    return $in === $ph || (strlen($in) >= 4 && substr($ph, -strlen($in)) === $in);
}
