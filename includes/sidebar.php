<?php
/**
 * Role sidebar (admin / technician / customer app shells)
 */

declare(strict_types=1);

$user = current_user();
$role = $user['role'] ?? '';
$activeNav = $activeNav ?? 'dashboard';

switch ($role) {
    case 'admin':
        $links = [
            ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => 'admin/index.php', 'icon' => 'bi-speedometer2'],
            ['key' => 'tickets', 'label' => 'Repair Tickets', 'href' => 'admin/tickets.php', 'icon' => 'bi-ticket-detailed'],
            ['key' => 'claims', 'label' => 'Warranty Claims', 'href' => 'admin/claims.php', 'icon' => 'bi-shield-check'],
            ['key' => 'customers', 'label' => 'Customers', 'href' => 'admin/customers.php', 'icon' => 'bi-people'],
            ['key' => 'technicians', 'label' => 'Technicians', 'href' => 'admin/technicians.php', 'icon' => 'bi-tools'],
            ['key' => 'reports', 'label' => 'Reports', 'href' => 'admin/reports.php', 'icon' => 'bi-bar-chart'],
            ['key' => 'settings', 'label' => 'Settings', 'href' => 'admin/settings.php', 'icon' => 'bi-gear'],
        ];
        break;
    case 'technician':
        $links = [
            ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => 'technician/index.php', 'icon' => 'bi-speedometer2'],
            ['key' => 'assigned', 'label' => 'Assigned Repairs', 'href' => 'technician/tickets.php', 'icon' => 'bi-wrench-adjustable'],
            ['key' => 'claims', 'label' => 'Warranty Claims', 'href' => 'technician/claims.php', 'icon' => 'bi-shield-check'],
        ];
        break;
    case 'customer':
        $links = [
            ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => 'customer/index.php', 'icon' => 'bi-speedometer2'],
            ['key' => 'repairs', 'label' => 'My Repairs', 'href' => 'customer/repairs.php', 'icon' => 'bi-phone'],
            ['key' => 'claims', 'label' => 'Warranty Claims', 'href' => 'customer/claims.php', 'icon' => 'bi-shield-check'],
            ['key' => 'book', 'label' => 'Book a Repair', 'href' => 'customer/book.php', 'icon' => 'bi-plus-circle'],
        ];
        break;
    default:
        $links = [];
}

$homePath = $role !== '' ? role_home_path($role) : 'index.php';
?>
<div class="sidebar-backdrop" id="sidebarBackdrop" hidden></div>
<aside class="rapid-sidebar" tabindex="-1" id="appSidebar" aria-label="<?= e(ucfirst($role)) ?> navigation">
    <div class="sidebar-brand">
        <?php
        $brandVariant = 'compact';
        $brandHref = url($homePath);
        $brandClass = 'sidebar-brand-link';
        require __DIR__ . '/brand_lockup.php';
        ?>
        <button type="button" class="sidebar-close" id="sidebarClose" aria-label="Close menu">
            <i class="bi bi-x-lg" aria-hidden="true"></i>
        </button>
    </div>
    <div class="sidebar-role"><?= e($role) ?> portal</div>
    <nav class="sidebar-nav">
        <?php foreach ($links as $link): ?>
            <?php
            $isActive = $activeNav === $link['key'];
            $soon = !empty($link['soon']);
            ?>
            <a class="sidebar-link <?= $isActive ? 'active' : '' ?><?= $soon ? ' is-soon' : '' ?>"
               href="<?= e(url($link['href'])) ?>"
               <?= $isActive ? 'aria-current="page"' : '' ?>
               <?= $soon ? 'title="Coming in the next build step"' : '' ?>>
                <i class="bi <?= e($link['icon']) ?>" aria-hidden="true"></i>
                <span><?= e($link['label']) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>
</aside>
