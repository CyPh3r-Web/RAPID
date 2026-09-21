<?php
/**
 * Admin — reports & charts (ApexCharts + Apache ECharts)
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$pdo = db();

function report_pairs(PDO $pdo, string $sql): array
{
    $rows = $pdo->query($sql)->fetchAll();
    $labels = [];
    $data = [];
    foreach ($rows as $r) {
        $labels[] = (string) $r['label'];
        $data[] = (int) $r['cnt'];
    }
    return ['labels' => $labels, 'data' => $data];
}

$statusRows = $pdo->query(
    'SELECT current_status AS code, COUNT(*) AS cnt FROM repair_tickets GROUP BY current_status ORDER BY cnt DESC'
)->fetchAll();
$statusLabels = [];
$statusData = [];
foreach ($statusRows as $r) {
    $statusLabels[] = status_label($r['code']);
    $statusData[] = (int) $r['cnt'];
}

$openedByMonth = [];
foreach ($pdo->query(
    "SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, COUNT(*) AS cnt
     FROM repair_tickets
     WHERE created_at >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 11 MONTH), '%Y-%m-01')
     GROUP BY ym"
)->fetchAll() as $r) {
    $openedByMonth[$r['ym']] = (int) $r['cnt'];
}

$completedByMonth = [];
foreach ($pdo->query(
    "SELECT DATE_FORMAT(completed_at, '%Y-%m') AS ym, COUNT(*) AS cnt
     FROM repair_tickets
     WHERE completed_at IS NOT NULL
       AND completed_at >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 11 MONTH), '%Y-%m-01')
     GROUP BY ym"
)->fetchAll() as $r) {
    $completedByMonth[$r['ym']] = (int) $r['cnt'];
}

$monthLabels = [];
$monthOpened = [];
$monthClosed = [];
$cursor = new DateTimeImmutable('first day of this month');
$cursor = $cursor->modify('-11 months');
for ($i = 0; $i < 12; $i++) {
    $ym = $cursor->format('Y-m');
    $monthLabels[] = $cursor->format('M Y');
    $monthOpened[] = $openedByMonth[$ym] ?? 0;
    $monthClosed[] = $completedByMonth[$ym] ?? 0;
    $cursor = $cursor->modify('+1 month');
}

$brands = report_pairs(
    $pdo,
    "SELECT d.brand AS label, COUNT(*) AS cnt
     FROM repair_tickets rt
     INNER JOIN devices d ON d.id = rt.device_id
     GROUP BY d.brand
     ORDER BY cnt DESC
     LIMIT 8"
);

$types = report_pairs(
    $pdo,
    "SELECT d.device_type AS label, COUNT(*) AS cnt
     FROM repair_tickets rt
     INNER JOIN devices d ON d.id = rt.device_id
     GROUP BY d.device_type
     ORDER BY cnt DESC
     LIMIT 6"
);

$completed = (int) $pdo->query("SELECT COUNT(*) FROM repair_tickets WHERE current_status = 'completed'")->fetchColumn();
$open = (int) $pdo->query("SELECT COUNT(*) FROM repair_tickets WHERE current_status NOT IN ('completed','cancelled')")->fetchColumn();
$cancelled = (int) $pdo->query("SELECT COUNT(*) FROM repair_tickets WHERE current_status = 'cancelled'")->fetchColumn();
$totalTickets = (int) $pdo->query('SELECT COUNT(*) FROM repair_tickets')->fetchColumn();

$avgDays = $pdo->query(
    'SELECT AVG(DATEDIFF(completed_at, created_at)) FROM repair_tickets WHERE completed_at IS NOT NULL'
)->fetchColumn();
$avgDays = $avgDays !== null ? round((float) $avgDays, 1) : null;

$approvedRevenue = (float) $pdo->query(
    "SELECT COALESCE(SUM(total_amount), 0) FROM quotations WHERE status = 'approved'"
)->fetchColumn();

$claimRows = $pdo->query(
    'SELECT claim_status, COUNT(*) AS cnt FROM warranty_claims GROUP BY claim_status'
)->fetchAll();
$claimLabels = [];
$claimData = [];
foreach ($claimRows as $r) {
    $claimLabels[] = claim_status_label($r['claim_status']);
    $claimData[] = (int) $r['cnt'];
}
$claimTotal = array_sum($claimData);

$techRows = $pdo->query(
    "SELECT CONCAT(u.first_name, ' ', u.last_name) AS label, COUNT(rt.id) AS cnt
     FROM technicians t
     INNER JOIN users u ON u.id = t.user_id
     LEFT JOIN repair_tickets rt
       ON rt.assigned_technician_id = t.id
      AND rt.current_status NOT IN ('completed', 'cancelled')
     GROUP BY t.id, u.first_name, u.last_name
     ORDER BY cnt DESC, u.first_name ASC
     LIMIT 8"
)->fetchAll();
$techLabels = [];
$techData = [];
foreach ($techRows as $r) {
    $techLabels[] = $r['label'];
    $techData[] = (int) $r['cnt'];
}

$funnelMap = [
    'Intake' => 0,
    'Diagnosis' => 0,
    'In repair' => 0,
    'Ready' => 0,
    'Completed' => 0,
];
$funnelGroups = [
    'Intake' => ['booking_submitted', 'received'],
    'Diagnosis' => ['diagnosing', 'quotation_pending', 'awaiting_approval'],
    'In repair' => ['approved', 'repairing'],
    'Ready' => ['ready_for_pickup'],
    'Completed' => ['completed'],
];
foreach ($statusRows as $r) {
    foreach ($funnelGroups as $name => $codes) {
        if (in_array($r['code'], $codes, true)) {
            $funnelMap[$name] += (int) $r['cnt'];
        }
    }
}
$funnel = [
    'labels' => array_keys($funnelMap),
    'data' => array_values($funnelMap),
];

$completionRate = $totalTickets > 0 ? (int) round(($completed / $totalTickets) * 100) : 0;

$chartPayload = [
    'months' => [
        'labels' => $monthLabels,
        'opened' => $monthOpened,
        'completed' => $monthClosed,
    ],
    'status' => ['labels' => $statusLabels, 'data' => $statusData],
    'outcome' => [
        'labels' => ['Completed', 'Open', 'Cancelled'],
        'data' => [$completed, $open, $cancelled],
    ],
    'brands' => $brands,
    'types' => $types,
    'claims' => ['labels' => $claimLabels, 'data' => $claimData],
    'technicians' => ['labels' => $techLabels, 'data' => $techData],
    'funnel' => $funnel,
    'completionRate' => $completionRate,
];

$hasCharts = $totalTickets > 0;

$pageTitle = 'Reports';
$showSidebar = true;
$navVariant = 'app';
$activeNav = 'reports';
$bodyClass = 'app-body';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="app-shell">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="app-main">
        <div class="page-header reports-header">
            <div>
                <h1>Operations reports</h1>
                <p>Live ticket, quotation, and warranty numbers from the RAPID database.</p>
            </div>
            <div class="reports-toolbar">
                <span class="reports-chip"><i class="bi bi-calendar3" aria-hidden="true"></i> Last 12 months</span>
                <button type="button" class="btn btn-rapid-outline btn-sm" id="reportsPrint">
                    <i class="bi bi-printer" aria-hidden="true"></i> Print
                </button>
            </div>
        </div>

        <div class="reports-kpis">
            <div class="reports-kpi">
                <div class="reports-kpi-icon"><i class="bi bi-ticket-detailed" aria-hidden="true"></i></div>
                <div>
                    <div class="label">Total tickets</div>
                    <div class="value"><?= (int) $totalTickets ?></div>
                </div>
            </div>
            <div class="reports-kpi">
                <div class="reports-kpi-icon is-blue"><i class="bi bi-wrench-adjustable" aria-hidden="true"></i></div>
                <div>
                    <div class="label">Open repairs</div>
                    <div class="value"><?= (int) $open ?></div>
                </div>
            </div>
            <div class="reports-kpi">
                <div class="reports-kpi-icon is-ok"><i class="bi bi-check2-circle" aria-hidden="true"></i></div>
                <div>
                    <div class="label">Completed</div>
                    <div class="value"><?= (int) $completed ?></div>
                    <div class="hint"><?= (int) $completionRate ?>% of all tickets</div>
                </div>
            </div>
            <div class="reports-kpi">
                <div class="reports-kpi-icon is-amber"><i class="bi bi-stopwatch" aria-hidden="true"></i></div>
                <div>
                    <div class="label">Avg. turnaround</div>
                    <div class="value"><?= $avgDays === null ? '—' : e((string) $avgDays) ?><?php if ($avgDays !== null): ?><span class="unit"> days</span><?php endif; ?></div>
                </div>
            </div>
            <div class="reports-kpi">
                <div class="reports-kpi-icon is-violet"><i class="bi bi-currency-dollar" aria-hidden="true"></i></div>
                <div>
                    <div class="label">Approved quotes</div>
                    <div class="value value-sm"><?= e(money_php($approvedRevenue)) ?></div>
                </div>
            </div>
            <div class="reports-kpi">
                <div class="reports-kpi-icon is-rose"><i class="bi bi-shield-check" aria-hidden="true"></i></div>
                <div>
                    <div class="label">Warranty claims</div>
                    <div class="value"><?= (int) $claimTotal ?></div>
                </div>
            </div>
        </div>

        <?php if (!$hasCharts): ?>
            <div class="rapid-card empty-state">
                <p class="mb-0">Not enough data yet to render charts. Create repair tickets to populate reports.</p>
            </div>
        <?php else: ?>
            <div class="chart-card chart-card-wide mb-4">
                <div class="chart-card-head">
                    <div>
                        <h2>Repair volume</h2>
                        <p>Tickets opened vs completed each month</p>
                    </div>
                    <span class="chart-lib">ApexCharts</span>
                </div>
                <div class="chart-host chart-host-lg" id="chartVolume"></div>
            </div>

            <div class="reports-grid mb-4">
                <div class="chart-card">
                    <div class="chart-card-head">
                        <div>
                            <h2>Repair pipeline</h2>
                            <p>How work currently sits in the shop</p>
                        </div>
                        <span class="chart-lib">ECharts</span>
                    </div>
                    <div class="chart-host" id="chartFunnel"></div>
                </div>
                <div class="chart-card">
                    <div class="chart-card-head">
                        <div>
                            <h2>Tickets by status</h2>
                            <p>Live mix across every workflow state</p>
                        </div>
                        <span class="chart-lib">ApexCharts</span>
                    </div>
                    <div class="chart-host" id="chartStatus"></div>
                </div>
            </div>

            <div class="reports-grid mb-4">
                <div class="chart-card">
                    <div class="chart-card-head">
                        <div>
                            <h2>Most common brands</h2>
                            <p>Devices booked for repair</p>
                        </div>
                        <span class="chart-lib">ApexCharts</span>
                    </div>
                    <div class="chart-host" id="chartBrands"></div>
                </div>
                <div class="chart-card">
                    <div class="chart-card-head">
                        <div>
                            <h2>Technician workload</h2>
                            <p>Open tickets assigned right now</p>
                        </div>
                        <span class="chart-lib">ECharts</span>
                    </div>
                    <div class="chart-host" id="chartTechnicians"></div>
                </div>
            </div>

            <div class="reports-grid reports-grid-3">
                <div class="chart-card">
                    <div class="chart-card-head">
                        <div>
                            <h2>Outcomes</h2>
                            <p>Completed, open, cancelled</p>
                        </div>
                        <span class="chart-lib">ApexCharts</span>
                    </div>
                    <div class="chart-host chart-host-sm" id="chartOutcome"></div>
                </div>
                <div class="chart-card">
                    <div class="chart-card-head">
                        <div>
                            <h2>Device types</h2>
                            <p>What customers bring in</p>
                        </div>
                        <span class="chart-lib">ApexCharts</span>
                    </div>
                    <div class="chart-host chart-host-sm" id="chartTypes"></div>
                </div>
                <div class="chart-card">
                    <div class="chart-card-head">
                        <div>
                            <h2>Warranty claims</h2>
                            <p><?= $claimTotal ? 'Claims by current status' : 'No claims filed yet' ?></p>
                        </div>
                        <span class="chart-lib">ApexCharts</span>
                    </div>
                    <?php if ($claimTotal): ?>
                        <div class="chart-host chart-host-sm" id="chartClaims"></div>
                    <?php else: ?>
                        <div class="chart-empty">No warranty claims to chart.</div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/apexcharts@3.54.1/dist/apexcharts.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/echarts@5.6.0/dist/echarts.min.js"></script>
<script>
window.__RAPID_CHARTS__ = <?= json_encode($chartPayload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<script src="<?= e(asset('js/reports.js')) ?>"></script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
