<?php
/**
 * Customer / staff login
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

if (is_logged_in()) {
    $u = current_user();
    redirect(role_home_path($u['role']));
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $result = attempt_login($_POST['email'] ?? '', $_POST['password'] ?? '');
    if ($result['success']) {
        flash_set('success', 'Welcome back, ' . $result['user']['first_name'] . '.');
        redirect(role_home_path($result['user']['role']));
    }
    $error = $result['message'];
}

$pageTitle = 'Sign in';
$bodyClass = 'auth-page';
require_once __DIR__ . '/../includes/header.php';
$authTab = 'login';
?>

<div class="auth-shell">
    <?php require __DIR__ . '/../includes/auth_brand.php'; ?>

    <main class="auth-main">
        <div class="auth-panel">
            <div class="auth-toolbar">
                <a class="auth-toolbar-link hidden lg:inline-flex" href="<?= e(url('index.php')) ?>">
                    <i class="bi bi-arrow-left" aria-hidden="true"></i> Home
                </a>
                <?php
                $brandVariant = 'compact';
                $brandHref = url('index.php');
                $brandClass = 'lg:hidden';
                require __DIR__ . '/../includes/brand_lockup.php';
                ?>
                <a class="auth-toolbar-link" href="<?= e(url('track.php')) ?>">Track repair</a>
            </div>

            <?php require __DIR__ . '/../includes/auth_tabs.php'; ?>

            <h1>Welcome back</h1>
            <p class="auth-lead">Sign in to your RAPID repair portal.</p>

            <?php if ($error !== ''): ?>
                <div class="alert alert-danger py-2 text-sm" role="alert">
                    <i class="bi bi-exclamation-circle" aria-hidden="true"></i>
                    <span><?= e($error) ?></span>
                </div>
            <?php endif; ?>

            <form method="post" action="" data-disable-on-submit autocomplete="on">
                <?= csrf_field() ?>
                <div class="mb-4">
                    <label class="form-label" for="email">Email</label>
                    <div class="auth-input">
                        <i class="bi bi-envelope" aria-hidden="true"></i>
                        <input type="email" class="form-control" id="email" name="email" required
                               placeholder="you@email.com"
                               value="<?= e($_POST['email'] ?? '') ?>" autocomplete="username">
                    </div>
                </div>
                <div class="mb-5">
                    <label class="form-label" for="password">Password</label>
                    <div class="auth-input">
                        <i class="bi bi-lock" aria-hidden="true"></i>
                        <input type="password" class="form-control" id="password" name="password" required
                               placeholder="Enter your password" autocomplete="current-password">
                        <button type="button" class="auth-password-toggle" data-password-toggle aria-label="Show password">
                            <i class="bi bi-eye" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>
                <button type="submit" class="btn btn-rapid-primary btn-lg w-full">Sign in</button>
            </form>

            <p class="auth-footnote">
                New customer?
                <a href="<?= e(url('auth/register.php')) ?>">Create an account</a>
            </p>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
