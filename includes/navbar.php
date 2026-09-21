<?php
/**
 * Top navigation
 */

declare(strict_types=1);

$user = current_user();
$navVariant = $navVariant ?? 'public'; // public | app
$unreadCount = ($navVariant === 'app' && $user) ? count_unread_notifications((int) $user['id']) : 0;
$fluid = $navVariant === 'app';
$isApp = $navVariant === 'app' && $user;
?>
<nav class="rapid-navbar <?= $isApp ? 'rapid-navbar-app' : 'rapid-navbar-public' ?>">
    <div class="<?= $fluid ? 'container-fluid-rapid' : 'container-rapid' ?>">
        <div class="flex flex-wrap items-center gap-3">
            <?php if ($isApp): ?>
                <button type="button" class="sidebar-toggle" id="sidebarToggle" aria-controls="appSidebar" aria-expanded="false" aria-label="Open menu">
                    <i class="bi bi-list" aria-hidden="true"></i>
                </button>
                <span class="app-navbar-brand lg:hidden">
                    <img class="brand-logo brand-logo-sm" src="<?= e(asset('images/rapid.png')) ?>" alt="" width="32" height="32">
                    <span class="app-navbar-title"><?= e(APP_NAME) ?></span>
                </span>
                <div class="flex items-center gap-3 ml-auto">
                    <div class="relative" data-dropdown>
                        <button class="nav-notify" type="button" id="notifyToggle" aria-expanded="false" aria-haspopup="true" title="Notifications" data-dropdown-toggle>
                            <i class="bi bi-bell" aria-hidden="true"></i>
                            <span class="notify-badge <?= $unreadCount > 0 ? '' : 'is-hidden' ?>" id="notifyBadge"><?= $unreadCount > 99 ? '99+' : (int) $unreadCount ?></span>
                        </button>
                        <div class="dropdown-panel notify-dropdown" id="notifyMenu" role="menu" data-dropdown-panel>
                            <div class="dropdown-header flex justify-between items-center">
                                <span>Notifications</span>
                                <button type="button" class="btn-link text-xs" id="notifyMarkAll">Mark all read</button>
                            </div>
                            <div id="notifyList" class="notify-list">
                                <div class="px-3 py-3 text-rapid-muted text-sm">Loading…</div>
                            </div>
                        </div>
                    </div>
                    <div class="relative" data-dropdown>
                        <button class="btn-user-menu" type="button" aria-expanded="false" aria-haspopup="true" data-dropdown-toggle>
                            <span class="user-avatar"><?= e(strtoupper(substr($user['first_name'], 0, 1))) ?></span>
                            <span class="hidden md:inline"><?= e($user['first_name'] . ' ' . $user['last_name']) ?></span>
                            <i class="bi bi-chevron-down text-xs text-rapid-muted" aria-hidden="true"></i>
                        </button>
                        <div class="dropdown-panel" role="menu" data-dropdown-panel>
                            <div class="dropdown-header capitalize"><?= e($user['role']) ?></div>
                            <a class="dropdown-item" href="<?= e(url(role_home_path($user['role']))) ?>">Dashboard</a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item" href="<?= e(url('auth/logout.php')) ?>">Sign out</a>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <?php
                $brandVariant = 'compact';
                $brandHref = url('index.php');
                $brandClass = '';
                require __DIR__ . '/brand_lockup.php';
                ?>
                <button type="button" class="btn btn-rapid-outline btn-sm ml-auto lg:hidden" id="publicNavToggle" aria-controls="publicNavMobile" aria-expanded="false" aria-label="Toggle navigation">
                    <i class="bi bi-list text-lg" aria-hidden="true"></i>
                </button>
                <div class="hidden lg:flex items-center gap-1 ml-auto">
                    <a class="nav-link-rapid" href="<?= e(url('index.php')) ?>#how-it-works">How it works</a>
                    <a class="nav-link-rapid" href="<?= e(url('index.php')) ?>#services">Services</a>
                    <a class="nav-link-rapid" href="<?= e(url('track.php')) ?>">Track repair</a>
                    <a class="nav-link-rapid" href="<?= e(url('index.php')) ?>#contact">Contact</a>
                    <?php if ($user): ?>
                        <a class="btn btn-rapid-primary btn-sm ml-2" href="<?= e(url(role_home_path($user['role']))) ?>">Dashboard</a>
                        <a class="nav-link-rapid" href="<?= e(url('auth/logout.php')) ?>">Sign out</a>
                    <?php else: ?>
                        <a class="nav-link-rapid" href="<?= e(url('auth/login.php')) ?>">Sign in</a>
                        <a class="btn btn-rapid-primary btn-sm ml-2" href="<?= e(url('auth/register.php')) ?>">Register</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if (!$isApp): ?>
            <div class="flex-col gap-1 border-t border-rapid-border mt-2 pt-2" id="publicNavMobile" hidden>
                <a class="nav-link-rapid block" href="<?= e(url('index.php')) ?>#how-it-works">How it works</a>
                <a class="nav-link-rapid block" href="<?= e(url('index.php')) ?>#services">Services</a>
                <a class="nav-link-rapid block" href="<?= e(url('track.php')) ?>">Track repair</a>
                <a class="nav-link-rapid block" href="<?= e(url('index.php')) ?>#contact">Contact</a>
                <?php if ($user): ?>
                    <a class="btn btn-rapid-primary btn-sm mt-1 w-fit" href="<?= e(url(role_home_path($user['role']))) ?>">Dashboard</a>
                    <a class="nav-link-rapid block" href="<?= e(url('auth/logout.php')) ?>">Sign out</a>
                <?php else: ?>
                    <a class="nav-link-rapid block" href="<?= e(url('auth/login.php')) ?>">Sign in</a>
                    <a class="btn btn-rapid-primary btn-sm mt-1 w-fit" href="<?= e(url('auth/register.php')) ?>">Register</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</nav>
