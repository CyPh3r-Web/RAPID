<?php
/**
 * Customer registration
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
    $result = register_customer($_POST);
    if ($result['success']) {
        flash_set('success', 'Account created. Please sign in.');
        redirect('auth/login.php');
    }
    $error = $result['message'];
}

$pageTitle = 'Register';
$bodyClass = 'auth-page';
require_once __DIR__ . '/../includes/header.php';
$authTab = 'register';
?>

<div class="auth-shell">
    <?php require __DIR__ . '/../includes/auth_brand.php'; ?>

    <main class="auth-main">
        <div class="auth-panel auth-panel-wide">
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

            <h1>Create your account</h1>
            <p class="auth-lead">Register to book repairs and track progress online.</p>

            <?php if ($error !== ''): ?>
                <div class="alert alert-danger py-2 text-sm" role="alert">
                    <i class="bi bi-exclamation-circle" aria-hidden="true"></i>
                    <span><?= e($error) ?></span>
                </div>
            <?php endif; ?>

            <form method="post" action="" data-disable-on-submit autocomplete="on">
                <?= csrf_field() ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="form-label" for="first_name">First name</label>
                        <div class="auth-input">
                            <i class="bi bi-person" aria-hidden="true"></i>
                            <input type="text" class="form-control" id="first_name" name="first_name" required maxlength="100"
                                   placeholder="Juan"
                                   value="<?= e($_POST['first_name'] ?? '') ?>">
                        </div>
                    </div>
                    <div>
                        <label class="form-label" for="last_name">Last name</label>
                        <div class="auth-input">
                            <i class="bi bi-person" aria-hidden="true"></i>
                            <input type="text" class="form-control" id="last_name" name="last_name" required maxlength="100"
                                   placeholder="Dela Cruz"
                                   value="<?= e($_POST['last_name'] ?? '') ?>">
                        </div>
                    </div>
                    <div>
                        <label class="form-label" for="email">Email</label>
                        <div class="auth-input">
                            <i class="bi bi-envelope" aria-hidden="true"></i>
                            <input type="email" class="form-control" id="email" name="email" required
                                   placeholder="you@email.com"
                                   value="<?= e($_POST['email'] ?? '') ?>" autocomplete="email">
                        </div>
                    </div>
                    <div>
                        <label class="form-label" for="phone">Phone</label>
                        <div class="auth-input">
                            <i class="bi bi-telephone" aria-hidden="true"></i>
                            <input type="text" class="form-control" id="phone" name="phone" required maxlength="30"
                                   placeholder="09xx xxx xxxx"
                                   value="<?= e($_POST['phone'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="md:col-span-2">
                        <label class="form-label" for="address">Address <span class="text-rapid-muted font-normal">(optional)</span></label>
                        <textarea class="form-control" id="address" name="address" rows="2" placeholder="Street, city"><?= e($_POST['address'] ?? '') ?></textarea>
                    </div>
                    <div>
                        <label class="form-label" for="password">Password</label>
                        <div class="auth-input">
                            <i class="bi bi-lock" aria-hidden="true"></i>
                            <input type="password" class="form-control" id="password" name="password" required minlength="8"
                                   placeholder="At least 8 characters" autocomplete="new-password">
                            <button type="button" class="auth-password-toggle" data-password-toggle aria-label="Show password">
                                <i class="bi bi-eye" aria-hidden="true"></i>
                            </button>
                        </div>
                        <div class="form-text">At least 8 characters.</div>
                    </div>
                    <div>
                        <label class="form-label" for="password_confirm">Confirm password</label>
                        <div class="auth-input">
                            <i class="bi bi-lock" aria-hidden="true"></i>
                            <input type="password" class="form-control" id="password_confirm" name="password_confirm" required minlength="8"
                                   placeholder="Repeat password" autocomplete="new-password">
                            <button type="button" class="auth-password-toggle" data-password-toggle aria-label="Show password">
                                <i class="bi bi-eye" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <button type="submit" class="btn btn-rapid-primary btn-lg w-full mt-5">Create account</button>
            </form>

            <p class="auth-footnote">
                Already registered?
                <a href="<?= e(url('auth/login.php')) ?>">Sign in</a>
            </p>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
